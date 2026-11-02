<?php

/**

 * Hamyar Doctors — Section 11: Public REST API (namespace hj/v1)

 *

 * Routes:

 * GET /wp-json/hj/v1/doctor/<id>

 * GET /wp-json/hj/v1/doctors?q=&specialty=&province=&city=&per_page=&page=

 *

 * Privacy: allow-list output + final scrub. Only publish + not hidden + public doctors.

 * Cache: transient (per doctor / per list) + global version bump on any change.

 * Admin status page: /wp-admin/admin-post.php?action=hj_rest_status

 *

 * Constants (optional, in wp-config.php):

 * define('HJ_REST_DISABLED', true); // turn the API off completely

 * define('HJ_REST_CACHE_TTL', 900); // seconds; 0 = no transient cache

 */

if (!defined('ABSPATH')) { exit; }



if (!class_exists('HJ_Doctors_REST')) :



class HJ_Doctors_REST {



    const NS = 'hj/v1';

    const VER_OPTION = 'hj_rest_ver';

    const PT = 'doctor';



    private static $booted = false;

    private static $ver_cache = null;

    private static $bump_pending = false;



    /* ------------------------------------------------------------------

     * Boot

     * ------------------------------------------------------------------ */

    public static function boot() {

        if (self::$booted) { return; }

        self::$booted = true;



        add_action('rest_api_init', array(__CLASS__, 'register_routes'));

        add_filter('rest_post_dispatch', array(__CLASS__, 'dispatch_headers'), 99, 3);

        add_filter('rest_pre_serve_request', array(__CLASS__, 'cors_headers'), 20, 4);



        // Cache invalidation

        add_action('save_post_doctor', array(__CLASS__, 'on_post_change'), 99, 1);

        add_action('before_delete_post', array(__CLASS__, 'on_post_change'), 99, 1);

        add_action('trashed_post', array(__CLASS__, 'on_post_change'), 99, 1);

        add_action('untrashed_post', array(__CLASS__, 'on_post_change'), 99, 1);

        add_action('transition_post_status', array(__CLASS__, 'on_transition'), 99, 3);

        add_action('added_post_meta', array(__CLASS__, 'on_meta'), 99, 3);

        add_action('updated_post_meta', array(__CLASS__, 'on_meta'), 99, 3);

        add_action('deleted_post_meta', array(__CLASS__, 'on_meta'), 99, 3);

        add_action('set_object_terms', array(__CLASS__, 'on_object_terms'), 99, 1);

        add_action('created_term', array(__CLASS__, 'on_term'), 99, 3);

        add_action('edited_term', array(__CLASS__, 'on_term'), 99, 3);

        add_action('delete_term', array(__CLASS__, 'on_term'), 99, 3);



        // Admin tools

        add_action('admin_post_hj_rest_status', array(__CLASS__, 'status_page'));

        add_action('admin_post_hj_rest_purge', array(__CLASS__, 'purge_action'));

    }



    public static function enabled() {

        if (defined('HJ_REST_DISABLED') && HJ_REST_DISABLED) { return false; }

        return (bool) apply_filters('hj_rest_enabled', true);

    }



    public static function ttl() {

        $ttl = defined('HJ_REST_CACHE_TTL') ? (int) HJ_REST_CACHE_TTL : 900;

        $ttl = (int) apply_filters('hj_rest_cache_ttl', $ttl);

        return max(0, $ttl);

    }



    /* ------------------------------------------------------------------

     * Routes

     * ------------------------------------------------------------------ */

    public static function register_routes() {

        if (!self::enabled()) { return; }



        register_rest_route(self::NS, '/doctor/(?P<id>\d+)', array(

            'methods' => 'GET',

            'callback' => array(__CLASS__, 'get_doctor'),

            'permission_callback' => '__return_true',

            'args' => array(

                'id' => array(

                    'validate_callback' => array(__CLASS__, 'validate_id'),

                ),

            ),

        ));



        register_rest_route(self::NS, '/doctors', array(

            'methods' => 'GET',

            'callback' => array(__CLASS__, 'get_doctors'),

            'permission_callback' => '__return_true',

        ));

    }



    public static function validate_id($v) {

        return is_numeric($v) && (int) $v > 0;

    }



    /* ------------------------------------------------------------------

     * Callbacks

     * ------------------------------------------------------------------ */

    public static function get_doctor($req) {

        try {

            $id = (int) $req['id'];



            // Public check ALWAYS runs (even on cache hit) so a hidden doctor disappears instantly.

            if (!self::is_public($id)) {

                $t = self::throttle();

                if ($t) { return $t; }

                return self::not_found();

            }



            $key = 'hj_rest_d_' . $id;

            $item = self::cache_get($key);

            if ($item === false) {

                $t = self::throttle();

                if ($t) { return $t; }

                $item = self::build_item($id, true);

                if ($item === null) { return self::not_found(); }

                self::cache_set($key, $item);

            }

            return new WP_REST_Response($item, 200);

        } catch (Throwable $e) {

            return self::fail($e);

        }

    }



    public static function get_doctors($req) {

        try {

            $p = self::list_params($req);



            if ($p['q'] !== '') {

                // Free-text search: never cached (unbounded keys), always throttled.

                $t = self::throttle();

                if ($t) { return $t; }

                if (!function_exists('hj_doctor_search_ids')) {

                    return new WP_Error('hj_search_unavailable', 'جست‌وجو در دسترس نیست.', array('status' => 501));

                }

                $ids = hj_doctor_search_ids($p['q']);

                if ($ids === null) {

                    return new WP_Error('hj_q_short', 'عبارت جست‌وجو باید حداقل ۲ حرف باشد.', array('status' => 400));

                }

                $res = self::query_ids($p, is_array($ids) ? array_map('intval', $ids) : array());

            } else {

                $key = 'hj_rest_l_' . md5(wp_json_encode(array($p['specialty'], $p['province'], $p['city'], $p['per_page'], $p['page'])));

                $res = self::cache_get($key);

                if ($res === false) {

                    $t = self::throttle();

                    if ($t) { return $t; }

                    $res = self::query_ids($p, null);

                    self::cache_set($key, $res);

                }

            }



            $items = array();

            foreach ($res['ids'] as $id) {

                $it = self::item_for_list((int) $id);

                if ($it !== null) { $items[] = $it; }

            }



            $out = new WP_REST_Response(array(

                'items' => $items,

                'total' => (int) $res['total'],

                'pages' => (int) $res['pages'],

                'page' => (int) $p['page'],

                'per_page' => (int) $p['per_page'],

            ), 200);

            $out->header('X-WP-Total', (string) (int) $res['total']);

            $out->header('X-WP-TotalPages', (string) (int) $res['pages']);

            return $out;

        } catch (Throwable $e) {

            return self::fail($e);

        }

    }



    private static function list_params($req) {

        $q = sanitize_text_field((string) $req->get_param('q'));

        $q = function_exists('mb_substr') ? mb_substr($q, 0, 60) : substr($q, 0, 60);



        $pp = (int) $req->get_param('per_page');

        if ($pp <= 0) { $pp = 10; }

        $pp = min(20, max(1, $pp));



        $pg = (int) $req->get_param('page');

        $pg = min(500, max(1, $pg));



        return array(

            'q' => trim($q),

            'specialty' => sanitize_title((string) $req->get_param('specialty')),

            'province' => sanitize_title((string) $req->get_param('province')),

            'city' => sanitize_title((string) $req->get_param('city')),

            'per_page' => $pp,

            'page' => $pg,

        );

    }



    private static function query_ids($p, $ids = null) {

        $empty = array('ids' => array(), 'total' => 0, 'pages' => 0);



        $args = array(

            'post_type' => self::PT,

            'post_status' => 'publish',

            'posts_per_page' => $p['per_page'],

            'paged' => $p['page'],

            'fields' => 'ids',

            'no_found_rows' => false,

            'has_password' => false,

            'ignore_sticky_posts' => true,

            'orderby' => array('title' => 'ASC'),

            'meta_query' => self::visibility_meta_query(),

            'hj_rest' => 1,

        );



        if (is_array($ids)) {

            if (empty($ids)) { return $empty; }

            $args['post__in'] = $ids;

            $args['orderby'] = 'post__in';

        }



        $tax = array();

        foreach (array('specialty', 'province', 'city') as $t) {

            if ($p[$t] !== '') {

                $tax[] = array('taxonomy' => $t, 'field' => 'slug', 'terms' => array($p[$t]));

            }

        }

        if ($tax) {

            $tax['relation'] = 'AND';

            $args['tax_query'] = $tax;

        }



        $q = new WP_Query($args);

        return array(

            'ids' => array_map('intval', (array) $q->posts),

            'total' => (int) $q->found_posts,

            'pages' => (int) $q->max_num_pages,

        );

    }



    private static function visibility_meta_query() {

        if (class_exists('HJ_Doctors_Archive_Render') && method_exists('HJ_Doctors_Archive_Render', 'visibility_meta_query')) {

            try {

                $m = HJ_Doctors_Archive_Render::visibility_meta_query();

                if (is_array($m) && !empty($m)) { return $m; }

            } catch (Throwable $e) { /* fall through */ }

        }

        return array(

            'relation' => 'OR',

            array('key' => 'profile_hidden', 'compare' => 'NOT EXISTS'),

            array('key' => 'profile_hidden', 'value' => '1', 'compare' => '!='),

        );

    }



    /* ------------------------------------------------------------------

     * Public check (strict, user-independent)

     * ------------------------------------------------------------------ */

    private static function truthy($v) {

        if (is_array($v)) { return false; }

        return in_array(strtolower(trim((string) $v)), array('1', 'true', 'yes', 'on'), true);

    }



    public static function why_not_public($id) {

        $id = (int) $id;

        $p = $id > 0 ? get_post($id) : null;

        if (!$p || $p->post_type !== self::PT) { return 'پست پزشک پیدا نشد'; }

        if ($p->post_status !== 'publish') { return 'وضعیت پست: ' . $p->post_status; }

        if (!empty($p->post_password)) { return 'پست رمزدار است'; }

        if (self::truthy(get_post_meta($id, 'profile_hidden', true))) { return 'profile_hidden فعال است'; }

        if (function_exists('hj_dp_is_public') && !hj_dp_is_public($id)) { return 'hj_dp_is_public = false (وضعیت پروفایل تأییدشده نیست)'; }

        return '';

    }



    public static function is_public($id) {

        return self::why_not_public($id) === '';

    }



    /* ------------------------------------------------------------------

     * Item builder (allow-list)

     * ------------------------------------------------------------------ */

    private static function item_for_list($id) {

        if (!self::is_public($id)) { return null; }

        $key = 'hj_rest_d_' . $id;

        $item = self::cache_get($key);

        if ($item === false) {

            $item = self::build_item($id, true);

            if ($item === null) { return null; }

            self::cache_set($key, $item);

        }

        return $item;

    }



    public static function build_raw($id) {

        $post = get_post($id);

        if (!$post) { return null; }



        $name_keys = apply_filters('hj_rest_name_keys', array('fa' => 'name_fa', 'en' => 'name_en'));

        $spec_keys = apply_filters('hj_rest_spec_keys', array('fa' => 'specialty_fa', 'en' => 'specialty_en'));



        $name = array(

            'fa' => self::meta_text($id, isset($name_keys['fa']) ? $name_keys['fa'] : 'name_fa'),

            'en' => self::meta_text($id, isset($name_keys['en']) ? $name_keys['en'] : 'name_en'),

        );

        if ($name['fa'] === '' && $name['en'] === '') {

            $name['fa'] = self::txt(get_the_title($id));

        }



        $specialty = array(

            'fa' => self::meta_text($id, isset($spec_keys['fa']) ? $spec_keys['fa'] : 'specialty_fa'),

            'en' => self::meta_text($id, isset($spec_keys['en']) ? $spec_keys['en'] : 'specialty_en'),

        );



        $specialties = array();

        foreach (array_slice(self::terms_of($id, 'specialty'), 0, 5) as $t) {

            $specialties[] = self::term_out($t);

        }



        $city_terms = self::terms_of($id, 'city');

        $city_term = $city_terms ? $city_terms[0] : null;



        $prov_terms = self::terms_of($id, 'province');

        $prov_term = $prov_terms ? $prov_terms[0] : null;

        if (!$prov_term && $city_term && function_exists('hj_get_city_province_id')) {

            $pid = (int) hj_get_city_province_id($city_term->term_id);

            if ($pid > 0) {

                $pt = get_term($pid, 'province');

                if ($pt && !is_wp_error($pt)) { $prov_term = $pt; }

            }

        }



        $photo = '';

        if (function_exists('hj_dp_photo_url')) {

            $photo = (string) hj_dp_photo_url($id);

        }

        if ($photo === '') {

            $photo = (string) get_the_post_thumbnail_url($id, 'medium');

        }



        return array(

            'id' => (int) $id,

            'url' => esc_url_raw(get_permalink($id)),

            'name' => $name,

            'specialty' => $specialty,

            'specialties' => $specialties,

            'province' => $prov_term ? self::term_out($prov_term) : null,

            'city' => $city_term ? self::term_out($city_term) : null,

            'photo' => $photo !== '' ? esc_url_raw($photo) : '',

            'rating' => self::rating($id),

        );

    }



    public static function build_item($id, $checked = false) {

        if (!$checked && !self::is_public($id)) { return null; }

        $raw = self::build_raw($id);

        if ($raw === null) { return null; }



        $item = self::prune($raw);

        $item = apply_filters('hj_rest_doctor_item', $item, (int) $id);

        $item = self::scrub($item);

        $item = self::prune($item);

        return is_array($item) ? $item : null;

    }



    private static function rating($id) {

        if (function_exists('hj_doctor_get_rating_stats')) {

            $stats = hj_doctor_get_rating_stats($id);

        } else {

            $stats = apply_filters('hj_doctor_rating_stats', array(), $id);

        }

        if (!is_array($stats)) { return null; }



        // Only the final public average + real approved count. Pending / hidden values are never read here.

        $approved = isset($stats['approved']) ? (int) $stats['approved'] : 0;

        $avg = isset($stats['avg']) ? (float) $stats['avg'] : 0.0;

        $min = max(1, (int) apply_filters('hj_pr_min_votes', 5));



        $visible = ($approved >= $min && $avg > 0);

        $visible = (bool) apply_filters('hj_rest_rating_visible', $visible, $id, array('approved' => $approved, 'avg' => $avg));

        if (!$visible || $avg <= 0) { return null; }



        return array(

            'average' => round(min(5, max(1, $avg)), 1),

            'count' => $approved,

            'scale' => 5,

        );

    }



    private static function meta_text($id, $key) {

        $v = get_post_meta($id, (string) $key, true);

        if (!is_scalar($v)) { return ''; }

        return self::txt($v);

    }



    private static function txt($s) {

        if (!is_scalar($s)) { return ''; }

        $s = wp_strip_all_tags((string) $s);

        $s = wp_specialchars_decode($s, ENT_QUOTES);

        $r = preg_replace('/\s+/u', ' ', $s);

        if ($r === null) { $r = $s; }

        return trim($r);

    }



    private static function terms_of($id, $tax) {

        $t = get_the_terms($id, $tax);

        if (empty($t) || is_wp_error($t)) { return array(); }

        return array_values($t);

    }



    private static function term_out($t) {

        $o = array('name' => self::txt($t->name), 'slug' => (string) $t->slug);

        $en = get_term_meta($t->term_id, 'hj_name_en', true);

        if (is_string($en) && trim($en) !== '') { $o['name_en'] = self::txt($en); }

        return $o;

    }



    private static function prune($v) {

        if (is_array($v)) {

            $is_list = !empty($v) && array_keys($v) === range(0, count($v) - 1);

            $o = array();

            foreach ($v as $k => $x) {

                $x = self::prune($x);

                if ($x === null || $x === '' || $x === array()) { continue; }

                $o[$k] = $x;

            }

            return $is_list ? array_values($o) : $o;

        }

        if (is_string($v)) { return trim($v); }

        return $v;

    }



    private static function scrub_pattern() {

        $default = '/(internal|staff|manual|override|weight|pending|licen[cs]e|nezam|phone|mobile|email|address|document|status|hidden|verified|author)/i';

        return (string) apply_filters('hj_rest_scrub_pattern', $default);

    }



    private static function scrub($v) {

        if (!is_array($v)) { return $v; }

        $pat = self::scrub_pattern();

        $o = array();

        foreach ($v as $k => $x) {

            if (is_string($k) && $pat !== '' && @preg_match($pat, $k) === 1) { continue; }

            $o[$k] = self::scrub($x);

        }

        return $o;

    }



    /* ------------------------------------------------------------------

     * Transient cache (value = array('v'=>global version,'d'=>data))

     * ------------------------------------------------------------------ */

    private static function new_ver() {

        return substr(md5(uniqid('hj', true)), 0, 10);

    }



    public static function ver() {

        if (self::$ver_cache === null) {

            $v = get_option(self::VER_OPTION, false);

            if ($v === false || $v === '') {

                $v = self::new_ver();

                add_option(self::VER_OPTION, $v, '', 'yes');

            }

            self::$ver_cache = (string) $v;

        }

        return self::$ver_cache;

    }



    private static function cache_get($key) {

        if (self::ttl() <= 0) { return false; }

        $c = get_transient($key);

        if (is_array($c) && isset($c['v']) && array_key_exists('d', $c) && $c['v'] === self::ver()) {

            return $c['d'];

        }

        return false;

    }



    private static function cache_set($key, $data) {

        $ttl = self::ttl();

        if ($ttl <= 0) { return; }

        set_transient($key, array('v' => self::ver(), 'd' => $data), $ttl);

    }



    /* ------------------------------------------------------------------

     * Invalidation (debounced to once per request, executed on shutdown)

     * ------------------------------------------------------------------ */

    private static function schedule_bump() {

        if (self::$bump_pending) { return; }

        self::$bump_pending = true;

        add_action('shutdown', array(__CLASS__, 'bump_now'), 1);

    }



    public static function bump_now() {

        $v = self::new_ver();

        if (get_option(self::VER_OPTION, false) === false) {

            add_option(self::VER_OPTION, $v, '', 'yes');

        } else {

            update_option(self::VER_OPTION, $v);

        }

        self::$ver_cache = $v;

        self::$bump_pending = false;

    }



    public static function on_post_change($post_id) {

        if (get_post_type($post_id) === self::PT) { self::schedule_bump(); }

    }



    public static function on_transition($new, $old, $post) {

        if ($new !== $old && $post && isset($post->post_type) && $post->post_type === self::PT) {

            self::schedule_bump();

        }

    }



    public static function on_meta($meta_id, $post_id, $key) {

        $key = (string) $key;

        if ($key === '') { return; }

        $ignored = apply_filters('hj_rest_ignored_meta_keys', array('_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_encloseme', '_pingme'));

        if (in_array($key, (array) $ignored, true)) { return; }

        if (strpos($key, '_hj_view') === 0 || strpos($key, 'hj_views') === 0) { return; }

        if (get_post_type($post_id) === self::PT) { self::schedule_bump(); }

    }



    public static function on_object_terms($object_id) {

        if (get_post_type($object_id) === self::PT) { self::schedule_bump(); }

    }



    public static function on_term($term_id, $tt_id = 0, $taxonomy = '') {

        if (in_array($taxonomy, array('specialty', 'specialty_type', 'province', 'city'), true)) {

            self::schedule_bump();

        }

    }



    /* ------------------------------------------------------------------

     * Rate limit (only on non-cached paths)

     * ------------------------------------------------------------------ */

    private static function throttle() {

        if (!function_exists('hj_sec_rate_limit')) { return null; }

        $cfg = (array) apply_filters('hj_rest_rate_limit', array(90, 600)); // [requests, seconds]

        $limit = isset($cfg[0]) ? max(1, (int) $cfg[0]) : 90;

        $win = isset($cfg[1]) ? max(30, (int) $cfg[1]) : 600;



        $ok = hj_sec_rate_limit('rest', $limit, $win);

        if ($ok === false) {

            $r = new WP_REST_Response(array(

                'code' => 'hj_rate_limited',

                'message' => 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.',

                'data' => array('status' => 429),

            ), 429);

            $r->header('Retry-After', (string) min(600, $win));

            return $r;

        }

        return null;

    }



    private static function not_found() {

        return new WP_Error('hj_not_found', 'پزشک یافت نشد.', array('status' => 404));

    }



    private static function fail($e) {

        if (function_exists('hj_doctors_log')) {

            hj_doctors_log('REST error: ' . $e->getMessage());

        }

        return new WP_Error('hj_rest_error', 'خطای داخلی.', array('status' => 500));

    }



    /* ------------------------------------------------------------------

     * HTTP headers + CORS (only for /hj/v1/*)

     * ------------------------------------------------------------------ */

    private static function is_our_route($request) {

        return ($request instanceof WP_REST_Request) && strpos($request->get_route(), '/' . self::NS) === 0;

    }



    public static function dispatch_headers($result, $server, $request) {

        if (!self::is_our_route($request) || !($result instanceof WP_HTTP_Response)) { return $result; }



        $ttl = self::ttl();

        if ((int) $result->get_status() === 200 && $ttl > 0) {

            $max = max(0, (int) apply_filters('hj_rest_http_max_age', min(60, $ttl)));

            $smax = max(0, (int) apply_filters('hj_rest_http_s_maxage', min(300, $ttl)));

            $result->header('Cache-Control', 'public, max-age=' . $max . ', s-maxage=' . $smax);

        } else {

            $result->header('Cache-Control', 'no-store');

        }

        $result->header('X-Robots-Tag', 'noindex');

        $result->header('X-Content-Type-Options', 'nosniff');

        return $result;

    }



    public static function cors_headers($served, $result, $request, $server) {

        if (!self::is_our_route($request) || headers_sent()) { return $served; }



        // Public data, no credentials. Core's credentialed CORS is replaced for our routes only.

        header_remove('Access-Control-Allow-Credentials');



        $allowed = (array) apply_filters('hj_rest_allowed_origins', array()); // empty = any origin

        if (empty($allowed)) {

            header('Access-Control-Allow-Origin: *');

        } else {

            $norm = array_map(array(__CLASS__, 'norm_origin'), $allowed);

            $origin = function_exists('get_http_origin') ? get_http_origin() : '';

            if ($origin && in_array(self::norm_origin($origin), $norm, true)) {

                header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));

            } else {

                header_remove('Access-Control-Allow-Origin');

            }

            header('Vary: Origin', false);

        }

        header('Access-Control-Allow-Methods: GET, OPTIONS');

        header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Retry-After');

        return $served;

    }



    private static function norm_origin($o) {

        return untrailingslashit(esc_url_raw((string) $o));

    }



    /* ------------------------------------------------------------------

     * Admin: purge + status page (no terminal needed)

     * ------------------------------------------------------------------ */

    private static function admin_ok() {

        if (!is_user_logged_in()) { return false; }

        if (current_user_can('manage_options')) { return true; }

        return function_exists('hj_doctors_user_can_manage') && hj_doctors_user_can_manage('doctors');

    }



    public static function purge_action() {

        if (!self::admin_ok()) { wp_die('دسترسی ندارید.', '', array('response' => 403)); }

        check_admin_referer('hj_rest_purge');

        self::bump_now();

        wp_safe_redirect(admin_url('admin-post.php?action=hj_rest_status&purged=1'));

        exit;

    }



    private static function collect_keys($v, &$out) {

        if (!is_array($v)) { return; }

        foreach ($v as $k => $x) {

            if (is_string($k)) { $out[] = $k; }

            self::collect_keys($x, $out);

        }

    }



    public static function status_page() {

        if (!self::admin_ok()) { wp_die('دسترسی ندارید.', '', array('response' => 403)); }

        nocache_headers();



        $e = 'esc_html';

        $row = function ($label, $ok, $extra = '') use ($e) {

            echo '<tr><td>' . $e($label) . '</td><td class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'OK' : 'ناموجود') . '</td><td>' . $e($extra) . '</td></tr>';

        };



        header('Content-Type: text/html; charset=utf-8');

        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>hj_rest_status</title>';

        echo '<style>body{font-family:Tahoma,sans-serif;background:#f4f8fb;color:#061A2E;margin:0;padding:24px}';

        echo 'h1{font-size:20px}h2{font-size:16px;margin-top:28px;color:#0F3556}table{border-collapse:collapse;background:#fff;width:100%;max-width:980px}';

        echo 'td,th{border:1px solid #dbe6ee;padding:8px 10px;text-align:right;font-size:13px}.ok{color:#0EA5A0;font-weight:700}.bad{color:#6B7C8F;font-weight:700}';

        echo 'pre{background:#061A2E;color:#d9f7f5;padding:14px;border-radius:8px;overflow:auto;direction:ltr;text-align:left;max-width:980px;font-size:12px}';

        echo 'a.b,button{background:#0EA5A0;color:#fff;border:0;padding:8px 14px;border-radius:6px;text-decoration:none;cursor:pointer;font-size:13px}</style></head><body>';

        echo '<h1>وضعیت REST API پزشکان (hj/v1)</h1>';



        if (!empty($_GET['purged'])) { echo '<p class="ok">کش پاک شد (نسخهٔ جدید ثبت شد).</p>'; }



        // 1) Config

        echo '<h2>۱) پیکربندی</h2><table>';

        $row('API فعال است', self::enabled(), self::enabled() ? '' : 'HJ_REST_DISABLED یا فیلتر hj_rest_enabled');

        $row('کش transient فعال است', self::ttl() > 0, 'TTL = ' . self::ttl() . ' ثانیه');

        echo '<tr><td>نسخهٔ کش</td><td colspan="2">' . $e(self::ver()) . '</td></tr>';

        echo '<tr><td>آدرس پایه</td><td colspan="2" dir="ltr">' . $e(rest_url('hj/v1/')) . '</td></tr>';

        echo '</table>';



        // 2) Dependencies

        echo '<h2>۲) وابستگی‌ها</h2><table>';

        $row('hj_dp_is_public', function_exists('hj_dp_is_public'));

        $row('hj_doctor_get_rating_stats', function_exists('hj_doctor_get_rating_stats'));

        $row('hj_doctor_search_ids', function_exists('hj_doctor_search_ids'), 'فقط برای پارامتر q');

        $row('hj_dp_photo_url', function_exists('hj_dp_photo_url'));

        $row('hj_sec_rate_limit', function_exists('hj_sec_rate_limit'));

        $row('visibility_meta_query (بخش ۸)', class_exists('HJ_Doctors_Archive_Render') && method_exists('HJ_Doctors_Archive_Render', 'visibility_meta_query'));

        echo '</table>';



        // 3) Samples + leak self-test

        $ids = get_posts(array(

            'post_type' => self::PT,

            'post_status' => 'any',

            'numberposts' => 15,

            'orderby' => 'ID',

            'order' => 'ASC',

            'fields' => 'ids',

        ));



        $allowed_top = array('id', 'url', 'name', 'specialty', 'specialties', 'province', 'city', 'photo', 'rating');

        $sens_keys = array('medical_license', 'phone_clinic', 'phone_mobile', 'address_clinic', 'address_hospital');

        $pat = self::scrub_pattern();



        echo '<h2>۳) پزشکان نمونه و آزمون نشت اطلاعات</h2><table><tr><th>ID</th><th>عنوان</th><th>وضعیت پست</th><th>عمومی؟</th><th>دلیل ۴۰۴</th><th>آزمون نشت</th><th>لینک API</th></tr>';

        foreach ($ids as $id) {

            $why = self::why_not_public($id);

            $public = ($why === '');

            $test = '—';

            if ($public) {

                $item = self::build_item($id, true);

                $json = wp_json_encode($item, JSON_UNESCAPED_UNICODE);

                $fail = array();



                foreach ((array) $item as $k => $_) {

                    if (!in_array($k, $allowed_top, true)) { $fail[] = 'کلید غیرمجاز: ' . $k; }

                }

                $keys = array();

                self::collect_keys($item, $keys);

                foreach ($keys as $k) {

                    if ($pat !== '' && @preg_match($pat, $k) === 1) { $fail[] = 'کلید حساس: ' . $k; }

                }

                foreach ($sens_keys as $mk) {

                    $mv = get_post_meta($id, $mk, true);

                    if (is_string($mv) && strlen(trim($mv)) >= 5 && stripos((string) $json, trim($mv)) !== false) {

                        $fail[] = 'مقدار ' . $mk . ' در خروجی است';

                    }

                }

                $test = $fail ? ('FAIL — ' . implode(' | ', $fail)) : 'PASS';

            }

            $link = rest_url('hj/v1/doctor/' . $id);

            echo '<tr><td>' . (int) $id . '</td><td>' . $e(get_the_title($id)) . '</td><td>' . $e(get_post_status($id)) . '</td>';

            echo '<td class="' . ($public ? 'ok' : 'bad') . '">' . ($public ? 'بله' : 'خیر') . '</td><td>' . $e($why) . '</td>';

            echo '<td class="' . (strpos($test, 'FAIL') === 0 ? 'bad' : 'ok') . '">' . $e($test) . '</td>';

            echo '<td dir="ltr"><a href="' . esc_url($link) . '" target="_blank" rel="noopener">/doctor/' . (int) $id . '</a></td></tr>';

        }

        echo '</table>';



        // 4) One doctor preview

        $sel = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($sel > 0) {

            echo '<h2>۴) خروجی دقیق پزشک ' . (int) $sel . '</h2>';

            $why = self::why_not_public($sel);

            if ($why !== '') {

                echo '<p class="bad">این پزشک در API دیده نمی‌شود: ' . $e($why) . '</p>';

            } else {

                echo '<pre>' . $e(wp_json_encode(self::build_item($sel, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';

            }

        } else {

            echo '<p>برای دیدن خروجی دقیق یک پزشک: <code>&amp;id=114</code> را به آدرس همین صفحه اضافه کنید.</p>';

        }



        // 5) Purge

        echo '<h2>۵) پاک‌کردن کش</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

        echo '<input type="hidden" name="action" value="hj_rest_purge">';

        wp_nonce_field('hj_rest_purge');

        echo '<button type="submit">پاک‌کردن کش API</button></form>';

        echo '<p style="color:#6B7C8F;font-size:12px">تغییر در پروفایل، وضعیت، ترم‌ها و متای پزشک کش را خودکار پاک می‌کند. '

           . 'تأیید رأی از داشبورد قدیمی «آرای پزشکان» ممکن است تا پایان TTL دیده نشود؛ در این حالت از همین دکمه استفاده کنید.</p>';

        echo '</body></html>';

        exit;

    }

}



endif;



HJ_Doctors_REST::boot();


<?php

/**
 * Hamyar Doctors — Section 9: Native Rating Bridge (hj_doctor_votes)
 *
 * File: wp-content/plugins/hamyar-doctors/includes/rating-bridge.php
 *
 * Principles:
 * - Read/write bridge to existing `hj_doctor_votes` table (never alters schema).
 * - Dynamic column & table-name detection (fault-tolerant: no fatal errors if schema differs).
 * - Admin-only hidden field `internal_staff_stars` (+ weight) blended into final star average,
 *   never shown as a separate rating on public pages or doctor dashboard.
 * - Full admin manual control: +/- delta adjustment (`hj_rating_manual_delta`),
 *   exact final override (`hj_rating_manual_override`), and force-show toggle.
 * - Enforces: 1..5 stars, guest voting, 1 vote per IP hash per doctor, max 5 doctors per IP,
 *   signed cookie, rate limit, pending approval on insert, >=5 votes public display rule.
 * - Rating rules are NEVER printed in the UI.
 */



if (!defined('ABSPATH')) {

    exit;

}



if (!class_exists('HJ_Doctors_Rating_Bridge')) :



final class HJ_Doctors_Rating_Bridge {



    const NONCE_VOTE = 'hj_rb_vote';

    const NONCE_META = 'hj_rb_admin_meta';

    const COOKIE_NAME = 'hj_voted_docs';

    const COOKIE_TTL = 31536000; // 1 year



    // Post meta keys (admin-only controls & sorting cache)

    const META_STAFF_STARS = 'internal_staff_stars';

    const META_STAFF_WEIGHT = 'internal_staff_weight';

    const META_DELTA = 'hj_rating_manual_delta';

    const META_OVERRIDE = 'hj_rating_manual_override';

    const META_FORCE_SHOW = 'hj_rating_force_show';

    const META_CACHE_AVG = '_hj_rating_public_avg';

    const META_CACHE_COUNT = '_hj_rating_public_count';



    private static $schema_cache = null;

    private static $stats_mem = array();



    public static function init() {

        // Provide official stats to sections 6, 7, 8, 11, 12.

        add_filter('hj_doctor_rating_stats', array(__CLASS__, 'filter_rating_stats'), 10, 2);



        // Render interactive rating box on single-doctor (section 7).

        add_filter('hj_doctor_rating_box_html', array(__CLASS__, 'filter_rating_box_html'), 10, 2);

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 25);



        // Archive sort by highest rating (section 8).

        add_filter('hj_ar_sort_options', array(__CLASS__, 'add_sort_option'));

        add_action('hj_ar_apply_sort', array(__CLASS__, 'apply_sort'), 10, 2);



        // Public & logged-in AJAX vote handler.

        add_action('wp_ajax_hj_rb_vote', array(__CLASS__, 'ajax_vote'));

        add_action('wp_ajax_nopriv_hj_rb_vote', array(__CLASS__, 'ajax_vote'));



        // Protect admin-only rating meta from non-admin edits.

        add_action('add_meta_boxes_doctor', array(__CLASS__, 'register_metabox'));

        add_action('save_post_doctor', array(__CLASS__, 'save_metabox'), 20, 2);



        // Admin quick vote-cleanup & diagnostics (no terminal needed).

        add_action('admin_post_hj_rb_cleanup', array(__CLASS__, 'admin_cleanup'));

        add_action('admin_post_hj_rb_status', array(__CLASS__, 'admin_status'));

    }



    /* ================================================================== */

    /* Small helpers */

    /* ================================================================== */



    private static function log($msg) {

        if (function_exists('hj_doctors_log')) {

            hj_doctors_log('[rating-bridge] ' . $msg);

        }

    }



    private static function fa($v) {

        if (function_exists('hj_digits')) {

            return hj_digits((string) $v, 'fa');

        }

        return str_replace(

            array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),

            array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'),

            (string) $v

        );

    }



    private static function en($v) {

        if (function_exists('hj_to_en_digits')) {

            return hj_to_en_digits((string) $v);

        }

        $l = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

        return str_replace(

            array_merge(array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'), array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩')),

            array_merge($l, $l),

            (string) $v

        );

    }



    private static function can_manage_ratings() {

        if (function_exists('hj_doctors_user_can_manage')) {

            return hj_doctors_user_can_manage('ratings');

        }

        return current_user_can('manage_options');

    }



    private static function is_public_doctor($id) {

        $id = (int) $id;

        if (!$id || get_post_type($id) !== 'doctor') {

            return false;

        }

        if (function_exists('hj_dp_is_public')) {

            return (bool) hj_dp_is_public($id);

        }

        return get_post_status($id) === 'publish';

    }



    private static function min_votes() {

        return max(1, (int) apply_filters('hj_pr_min_votes', 5));

    }



    private static function max_doctors_per_ip() {

        return max(1, (int) apply_filters('hj_rb_max_doctors_per_ip', 5));

    }



    /* ================================================================== */

    /* Fault-tolerant table & column discovery */

    /* ================================================================== */



    public static function schema() {

        if (self::$schema_cache !== null) {

            return self::$schema_cache;

        }



        global $wpdb;

        $prev_suppress = $wpdb->suppress_errors(true);



        $candidates = array(

            $wpdb->prefix . 'hj_doctor_votes',

            'hj_doctor_votes',

        );

        $custom = (string) apply_filters('hj_rb_table_name', '');

        if ($custom !== '') {

            array_unshift($candidates, $custom);

        }

        $candidates = array_values(array_unique($candidates));



        $found_table = '';

        $col_rows = array();

        foreach ($candidates as $tb) {

            $tb_clean = preg_replace('/[^A-Za-z0-9_]/', '', $tb);

            if ($tb_clean === '') {

                continue;

            }

            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tb_clean));

            if ($exists === $tb_clean) {

                $rows = $wpdb->get_results("SHOW COLUMNS FROM `{$tb_clean}`", ARRAY_A);

                if (is_array($rows) && $rows) {

                    $found_table = $tb_clean;

                    $col_rows = $rows;

                    break;

                }

            }

        }



        if ($found_table === '') {

            $wpdb->suppress_errors($prev_suppress);

            self::$schema_cache = array('ok' => false, 'table' => '');

            return self::$schema_cache;

        }



        $cols = array();

        $types = array();

        foreach ($col_rows as $r) {

            if (!empty($r['Field'])) {

                $f = (string) $r['Field'];

                $cols[] = $f;

                $types[$f] = isset($r['Type']) ? strtolower((string) $r['Type']) : '';

            }

        }



        $pick = function ($list) use ($cols) {

            foreach ($list as $c) {

                if (in_array($c, $cols, true)) {

                    return $c;

                }

            }

            return '';

        };



        $map = apply_filters('hj_rb_column_map', array(

            'id' => $pick(array('id', 'ID', 'vote_id')),

            'doctor_id' => $pick(array('doctor_id', 'post_id', 'doctor')),

            'rating' => $pick(array('rating', 'stars', 'score', 'vote', 'value')),

            'status' => $pick(array('status', 'is_approved', 'approved', 'state')),

            'ip_hash' => $pick(array('ip_hash', 'user_ip_hash', 'voter_ip_hash', 'ip', 'voter_ip', 'hash')),

            'created_at' => $pick(array('created_at', 'voted_at', 'date_created', 'created', 'vote_date', 'time')),

            'user_id' => $pick(array('user_id', 'voter_id')),

        ), $cols, $found_table);



        // Validate mapped columns against actual columns whitelist.

        foreach ($map as $k => $v) {

            if (!is_string($v) || !in_array($v, $cols, true)) {

                $map[$k] = '';

            }

        }



        // Detect how pending/approved are stored in the status column.

        $status_col = $map['status'];

        $status_numeric = false;

        if ($status_col !== '' && isset($types[$status_col])) {

            $t = $types[$status_col];

            if (strpos($t, 'int') !== false || strpos($t, 'bit') !== false || strpos($t, 'bool') !== false) {

                $status_numeric = true;

            }

        }



        $wpdb->suppress_errors($prev_suppress);



        self::$schema_cache = array(

            'ok' => ($map['doctor_id'] !== '' && $map['rating'] !== ''),

            'table' => $found_table,

            'cols' => $cols,

            'types' => $types,

            'map' => $map,

            'status_numeric' => $status_numeric,

        );

        return self::$schema_cache;

    }



    /** SQL condition for approved rows (safe: column name comes from SHOW COLUMNS whitelist). */

    private static function approved_sql_cond($schema) {

        $sc = $schema['map']['status'];

        if ($sc === '') {

            return '1=1';

        }

        if ($schema['status_numeric']) {

            return "`{$sc}` = 1";

        }

        return "(`{$sc}` IN ('approved','approve','publish','published','1','active','yes'))";

    }



    private static function pending_insert_value($schema) {

        $default = $schema['status_numeric'] ? 0 : 'pending';

        return apply_filters('hj_rb_pending_value', $default, $schema);

    }



    /* ================================================================== */

    /* IP hashing & signed cookie */

    /* ================================================================== */



    private static function client_ip() {

        if (function_exists('hj_sec_client_ip')) {

            return (string) hj_sec_client_ip();

        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';

    }



    /**
     * Candidate hashes for the current IP so we match existing rows whether the
     * site originally hashed IPs with plain sha256, hmac-sha256, or md5.
     */

    private static function ip_hashes() {

        $ip = self::client_ip();

        $sha = hash('sha256', $ip);

        $hmac = hash_hmac('sha256', $ip, wp_salt('auth'));

        $md5 = md5($ip);

        $primary = (string) apply_filters('hj_rb_hash_ip', $sha, $ip);

        return array_values(array_unique(array($primary, $sha, $hmac, $md5)));

    }



    private static function cookie_sign($csv) {

        return hash_hmac('sha256', 'hj_voted|' . $csv, wp_salt('auth'));

    }



    public static function cookie_doctors() {

        if (empty($_COOKIE[self::COOKIE_NAME])) {

            return array();

        }

        $raw = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));

        $parts = explode('|', $raw);

        if (count($parts) !== 2) {

            return array();

        }

        if (!hash_equals(self::cookie_sign($parts[0]), $parts[1])) {

            return array();

        }

        $ids = array();

        foreach (explode(',', $parts[0]) as $x) {

            $n = absint($x);

            if ($n > 0) {

                $ids[] = $n;

            }

        }

        return array_values(array_unique($ids));

    }



    private static function set_cookie_doctors($ids) {

        if (headers_sent()) {

            return;

        }

        $ids = array_slice(array_values(array_unique(array_map('absint', $ids))), 0, 20);

        $csv = implode(',', $ids);

        $val = $csv . '|' . self::cookie_sign($csv);

        setcookie(

            self::COOKIE_NAME,

            $val,

            array(

                'expires' => time() + self::COOKIE_TTL,

                'path' => '/',

                'domain' => (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) ? COOKIE_DOMAIN : '',

                'secure' => is_ssl(),

                'httponly' => true,

                'samesite' => 'Lax',

            )

        );

        $_COOKIE[self::COOKIE_NAME] = $val;

    }



    public static function has_voted_for($doctor_id) {

        $doctor_id = (int) $doctor_id;

        if (in_array($doctor_id, self::cookie_doctors(), true)) {

            return true;

        }

        $s = self::schema();

        if (!$s['ok'] || $s['map']['ip_hash'] === '') {

            return false;

        }

        global $wpdb;

        $prev = $wpdb->suppress_errors(true);

        $hashes = self::ip_hashes();

        $ph = implode(',', array_fill(0, count($hashes), '%s'));

        $tb = $s['table'];

        $dc = $s['map']['doctor_id'];

        $ic = $s['map']['ip_hash'];

        $sql = $wpdb->prepare(

            "SELECT 1 FROM `{$tb}` WHERE `{$dc}` = %d AND `{$ic}` IN ({$ph}) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL

            array_merge(array($doctor_id), $hashes)

        );

        $found = (bool) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL

        $wpdb->suppress_errors($prev);

        return $found;

    }



    private static function ip_distinct_doctors_count() {

        $s = self::schema();

        if (!$s['ok'] || $s['map']['ip_hash'] === '') {

            return count(self::cookie_doctors());

        }

        global $wpdb;

        $prev = $wpdb->suppress_errors(true);

        $hashes = self::ip_hashes();

        $ph = implode(',', array_fill(0, count($hashes), '%s'));

        $tb = $s['table'];

        $dc = $s['map']['doctor_id'];

        $ic = $s['map']['ip_hash'];

        $sql = $wpdb->prepare(

            "SELECT COUNT(DISTINCT `{$dc}`) FROM `{$tb}` WHERE `{$ic}` IN ({$ph})", // phpcs:ignore WordPress.DB.PreparedSQL

            $hashes

        );

        $cnt = (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL

        $wpdb->suppress_errors($prev);

        return max($cnt, count(self::cookie_doctors()));

    }



    /* ================================================================== */

    /* Stats: public votes + hidden staff blend + admin controls          */

    /* ================================================================== */



    /**
     * Full rating stats for one doctor (per-request cached).
     *
     * @param int $doctor_id Post ID.
     * @return array|null
     */

    public static function stats($doctor_id) {

        $doctor_id = (int) $doctor_id;

        if (!$doctor_id) {

            return null;

        }

        if (isset(self::$stats_mem[$doctor_id])) {

            return self::$stats_mem[$doctor_id];

        }

        $s = self::schema();

        $out = array(

            'doctor_id'    => $doctor_id,

            'ok'           => (bool) $s['ok'],

            'approved'     => 0,

            'pending'      => 0,

            'avg'          => 0.0,

            'public_avg'   => 0.0,

            'staff_stars'  => 0.0,

            'staff_weight' => 0.0,

            'delta'        => 0.0,

            'override'     => null,

            'force_show'   => false,

            'display'      => false,

            'min_votes'    => self::min_votes(),

        );

        if ($s['ok']) {

            global $wpdb;

            $prev = $wpdb->suppress_errors(true);

            $tb = $s['table'];

            $m  = $s['map'];

            $dc = $m['doctor_id'];

            $rc = $m['rating'];

            $cond = self::approved_sql_cond($s);

            $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL

                $wpdb->prepare("SELECT COUNT(*) AS c, AVG(`{$rc}`) AS a FROM `{$tb}` WHERE `{$dc}` = %d AND {$cond}", $doctor_id), // phpcs:ignore WordPress.DB.PreparedSQL

                ARRAY_A

            );

            if (is_array($row)) {

                $out['approved']   = (int) (isset($row['c']) ? $row['c'] : 0);

                $out['public_avg'] = isset($row['a']) && null !== $row['a'] ? (float) $row['a'] : 0.0;

            }

            if ($m['status'] !== '') {

                $sc = $m['status'];

                $p = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL

                    $wpdb->prepare("SELECT COUNT(*) FROM `{$tb}` WHERE `{$dc}` = %d AND NOT ({$cond})", $doctor_id) // phpcs:ignore WordPress.DB.PreparedSQL

                );

                $out['pending'] = (int) $p;

            }

            $wpdb->suppress_errors($prev);

        }

        // Admin-only hidden staff rating, blended by weight (never shown separately).

        $staff = (float) get_post_meta($doctor_id, self::META_STAFF_STARS, true);

        $staff = min(5.0, max(0.0, $staff));

        $weight = (float) get_post_meta($doctor_id, self::META_STAFF_WEIGHT, true);

        $weight = min(100.0, max(0.0, $weight));

        $delta = (float) get_post_meta($doctor_id, self::META_DELTA, true);

        $delta = min(5.0, max(-5.0, $delta));

        $override_raw = get_post_meta($doctor_id, self::META_OVERRIDE, true);

        $override = null;

        if ('' !== (string) $override_raw) {

            $o = (float) self::en($override_raw);

            if ($o >= 1 && $o <= 5) {

                $override = $o;

            }

        }

        $force = (bool) get_post_meta($doctor_id, self::META_FORCE_SHOW, true);

        $n    = $out['approved'];

        $avg  = $out['public_avg'];

        $final = $avg;

        if ($staff > 0) {

            $final = ($n > 0) ? (($avg * $n) + ($staff * $weight)) / ($n + $weight) : $staff;

        }

        $final += $delta;

        if (null !== $override) {

            $final = $override;

        }

        $final = min(5.0, max(0.0, $final));

        $out['staff_stars']  = $staff;

        $out['staff_weight'] = $weight;

        $out['delta']        = $delta;

        $out['override']     = $override;

        $out['force_show']   = $force;

        $out['avg']          = round($final, 2);

        $out['display']      = ($n >= $out['min_votes']) || $force;

        self::$stats_mem[$doctor_id] = $out;

        return $out;

    }



    /** Persist the sorting cache metas used by the "rating" archive sort. */

    public static function refresh_cache($doctor_id) {

        $st = self::stats($doctor_id);

        if (!is_array($st)) {

            return;

        }

        update_post_meta((int) $doctor_id, self::META_CACHE_COUNT, (int) $st['approved']);

        update_post_meta((int) $doctor_id, self::META_CACHE_AVG, round((float) $st['avg'], 2));

    }



    /**
     * Official stats for sections 6, 7, 8, 11, 12.
     *
     * @param mixed $stats Previous value (null by default).
     * @param int   $id    Doctor post ID.
     * @return array|null
     */

    public static function filter_rating_stats($stats, $id) {

        $st = self::stats((int) $id);

        if (!is_array($st)) {

            return $stats;

        }

        // Force-show lifts the public min-votes gate for consumers (section 8 card).

        if ($st['force_show'] && $st['approved'] < $st['min_votes']) {

            $st['approved'] = $st['min_votes'];

        }

        return $st;

    }



    /* ================================================================== */

    /* Archive sort by rating (section 8)                                  */

    /* ================================================================== */



    public static function add_sort_option($options) {

        $options['rating'] = 'پرامتیازترین';

        return $options;

    }



    public static function apply_sort($query, $sort) {

        if ($sort !== 'rating') {

            return;

        }

        $query->set('meta_key', self::META_CACHE_AVG);

        $query->set('orderby', 'meta_value_num');

        $query->set('order', 'DESC');

    }



    /* ================================================================== */

    /* Interactive rating box on single doctor (section 7)                 */

    /* ================================================================== */



    public static function filter_rating_box_html($html, $id) {

        $id = (int) $id;

        if (!$id || !self::is_public_doctor($id)) {

            return $html;

        }

        $st     = self::stats($id);

        $voted  = self::has_voted_for($id);

        $avg    = is_array($st) ? (float) $st['avg'] : 0.0;

        $show   = is_array($st) && (bool) $st['display'];

        $fill   = min(100, max(0, (int) round(($avg / 5) * 100)));

        $avg_fa = str_replace('.', '٫', self::fa(number_format($avg, 1)));

        ob_start();

        ?>

        <div class="hj-rb" data-doc="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE_VOTE)); ?>">

            <div class="hj-rb__row">

                <span class="hj-rb-stars" style="--hj-fill: <?php echo (int) $fill; ?>%" aria-hidden="true"><i>★★★★★</i></span>

                <?php if ($show && $avg > 0) : ?>

                    <b class="hj-rb__avg"><?php echo esc_html($avg_fa); ?></b>

                <?php endif; ?>

            </div>

            <?php if (!$voted) : ?>

                <div class="hj-rb__vote">

                    <?php for ($i = 5; $i >= 1; $i--) : ?>

                        <button type="button" class="hj-rb__vote" data-stars="<?php echo (int) $i; ?>"><?php echo esc_html(self::fa($i)); ?> ★</button>

                    <?php endfor; ?>

                </div>

            <?php endif; ?>

            <p class="hj-rb__msg" aria-live="polite"><?php echo $voted ? 'امتیاز شما ثبت شد.' : ''; ?></p>

        </div>

        <style>
            .hj-rb{margin:12px 0;padding:14px;border:1px solid #E3ECF3;border-radius:14px;background:#fff;direction:rtl}
            .hj-rb__row{display:flex;align-items:center;gap:8px}
            .hj-rb-stars{position:relative;display:inline-block;direction:ltr;letter-spacing:2px;line-height:1;color:#D5E1EB;white-space:nowrap}
            .hj-rb-stars::before{content:"★★★★★"}
            .hj-rb-stars>i{position:absolute;top:0;bottom:0;left:0;width:var(--hj-fill,0);overflow:hidden;white-space:nowrap;font-style:normal;color:#0EA5A0}
            .hj-rb__avg{font-size:.9rem;color:#0F3556}
            .hj-rb__vote{display:flex;flex-direction:row-reverse;gap:6px;margin-top:10px}
            .hj-rb__vote button{border:1px solid #D3DFEA;background:#fff;border-radius:8px;padding:4px 10px;cursor:pointer;font:inherit;color:#0F3556}
            .hj-rb__vote button:hover{border-color:#0EA5A0;color:#0EA5A0}
            .hj-rb__msg{margin:8px 0 0;font-size:.82rem;color:#6B7C8F}
        </style>

        <?php

        return $html . ob_get_clean();

    }



    public static function enqueue() {

        if (!is_singular('doctor')) {

            return;

        }

        wp_enqueue_script('jquery');

        wp_add_inline_script('jquery', self::vote_js());

    }



    private static function vote_js() {

        return <<<'JS'
jQuery(function($){
  $(document).on('click', '.hj-rb__vote', function(e){
    e.preventDefault();
    var btn = $(this), box = btn.closest('.hj-rb');
    if (box.data('busy')) { return; }
    box.data('busy', 1);
    $.post(window.ajaxurl, {
      action: 'hj_rb_vote',
      nonce: box.data('nonce'),
      doctor_id: box.data('doc'),
      stars: btn.data('stars')
    }).done(function(r){
      if (r && r.success) {
        box.find('.hj-rb__vote').remove();
        box.find('.hj-rb__msg').text('امتیاز شما ثبت شد.');
      } else {
        box.find('.hj-rb__msg').text((r && r.data && r.data.message) ? r.data.message : 'خطا در ثبت.');
        box.data('busy', 0);
      }
    }).fail(function(){
      box.find('.hj-rb__msg').text('خطا در ثبت.');
      box.data('busy', 0);
    });
  });
});
JS;

    }



    /* ================================================================== */

    /* AJAX vote (public + logged-in)                                     */

    /* ================================================================== */



    public static function ajax_vote() {

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_VOTE)) {

            wp_send_json_error(array('message' => 'نشست نامعتبر است.'), 403);

        }

        $id = isset($_POST['doctor_id']) ? absint(wp_unslash($_POST['doctor_id'])) : 0;

        $stars = isset($_POST['stars']) ? (int) wp_unslash($_POST['stars']) : 0;

        if ($stars < 1 || $stars > 5) {

            wp_send_json_error(array('message' => 'امتیاز نامعتبر است.'), 422);

        }

        if (!self::is_public_doctor($id)) {

            wp_send_json_error(array('message' => 'پروفایل در دسترس نیست.'), 404);

        }

        if (self::has_voted_for($id)) {

            wp_send_json_error(array('message' => 'قبلاً ثبت شده است.'), 409);

        }

        if (self::ip_distinct_doctors_count() >= self::max_doctors_per_ip()) {

            wp_send_json_error(array('message' => 'امکان ثبت‌نظر بیشتر وجود ندارد.'), 429);

        }

        if (function_exists('hj_sec_rate_limit') && !hj_sec_rate_limit('rb_vote', 10, 3600, self::client_ip())) {

            wp_send_json_error(array('message' => 'درخواست‌های زیاد؛ کمی بعد دوباره تلاش کنید.'), 429);

        }

        $s = self::schema();

        if (!$s['ok']) {

            wp_send_json_error(array('message' => 'سامانهٔ امتیاز در دسترس نیست.'), 500);

        }

        global $wpdb;

        $tb = $s['table'];

        $m  = $s['map'];

        $data    = array($m['doctor_id'] => $id, $m['rating'] => $stars);

        $formats = array('%d', '%d');

        if ($m['status'] !== '') {

            $data[$m['status']] = self::pending_insert_value($s);

            $formats[] = $s['status_numeric'] ? '%d' : '%s';

        }

        if ($m['ip_hash'] !== '') {

            $hashes = self::ip_hashes();

            $data[$m['ip_hash']] = $hashes[0];

            $formats[] = '%s';

        }

        if ($m['user_id'] !== '') {

            $data[$m['user_id']] = get_current_user_id();

            $formats[] = '%d';

        }

        if ($m['created_at'] !== '') {

            $t = isset($s['types'][$m['created_at']]) ? $s['types'][$m['created_at']] : '';

            $is_ts = (strpos($t, 'int') !== false || strpos($t, 'timestamp') !== false);

            $data[$m['created_at']] = $is_ts ? time() : current_time('mysql');

            $formats[] = $is_ts ? '%d' : '%s';

        }

        $prev = $wpdb->suppress_errors(true);

        $ok = $wpdb->insert($tb, $data, $formats);

        $wpdb->suppress_errors($prev);

        if (false === $ok) {

            self::log('insert failed doctor=' . $id);

            wp_send_json_error(array('message' => 'خطا در ثبت.'), 500);

        }

        self::set_cookie_doctors(array_merge(self::cookie_doctors(), array($id)));

        unset(self::$stats_mem[$id]);

        self::log('vote inserted doctor=' . $id . ' stars=' . $stars);

        wp_send_json_success(array('voted' => true, 'doctor' => $id, 'stars' => $stars));

    }



    /* ================================================================== */

    /* Admin-only meta box (hidden staff rating + manual controls)         */

    /* ================================================================== */



    public static function register_metabox() {

        if (!self::can_manage_ratings()) {

            return;

        }

        add_meta_box('hj-rb-rating', 'کنترل‌های امتیاز (فقط مدیر)', array(__CLASS__, 'render_metabox'), 'doctor', 'side', 'default');

    }



    public static function render_metabox($post) {

        wp_nonce_field(self::NONCE_META, 'hj_rb_meta_nonce');

        $id = (int) $post->ID;

        $staff    = get_post_meta($id, self::META_STAFF_STARS, true);

        $weight   = get_post_meta($id, self::META_STAFF_WEIGHT, true);

        $delta    = get_post_meta($id, self::META_DELTA, true);

        $override = get_post_meta($id, self::META_OVERRIDE, true);

        $force    = (bool) get_post_meta($id, self::META_FORCE_SHOW, true);

        $st = self::stats($id);

        ?>

        <p>
            <label for="hj-rb-staff">امتیاز nội staff (۰ تا ۵)</label><br>
            <input type="number" id="hj-rb-staff" name="<?php echo esc_attr(self::META_STAFF_STARS); ?>" value="<?php echo esc_attr((string) $staff); ?>" min="0" max="5" step="0.5" style="width:100%">
        </p>

        <p>
            <label for="hj-rb-weight">وزن staff</label><br>
            <input type="number" id="hj-rb-weight" name="<?php echo esc_attr(self::META_STAFF_WEIGHT); ?>" value="<?php echo esc_attr('' === (string) $weight ? '1' : (string) $weight); ?>" min="0" max="100" step="1" style="width:100%">
        </p>

        <p>
            <label for="hj-rb-delta">تغییر دستی (δ)</label><br>
            <input type="number" id="hj-rb-delta" name="<?php echo esc_attr(self::META_DELTA); ?>" value="<?php echo esc_attr((string) $delta); ?>" min="-5" max="5" step="0.1" style="width:100%">
        </p>

        <p>
            <label for="hj-rb-override">override نهایی (خالی = tựکار)</label><br>
            <input type="number" id="hj-rb-override" name="<?php echo esc_attr(self::META_OVERRIDE); ?>" value="<?php echo esc_attr((string) $override); ?>" min="1" max="5" step="0.5" style="width:100%">
        </p>

        <p>
            <label><input type="checkbox" name="<?php echo esc_attr(self::META_FORCE_SHOW); ?>" value="1" <?php checked($force); ?>> نمایش اجباری امتیاز</label>
        </p>

        <?php if (is_array($st)) : ?>

            <p style="font-size:12px;color:#666">
               وضعیت فعلی: میانگین <?php echo esc_html(self::fa(number_format((float) $st['avg'], 2))); ?> — رأی تأییدشده: <?php echo esc_html(self::fa($st['approved'])); ?> — در انتظار: <?php echo esc_html(self::fa($st['pending'])); ?>
            </p>

        <?php endif; ?>

        <?php

    }



    public static function save_metabox($post_id, $post) {

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {

            return;

        }

        if (wp_is_post_revision($post_id)) {

            return;

        }

        if (get_post_type($post_id) !== 'doctor') {

            return;

        }

        // Non-admins can never write the admin-only rating meta.

        if (!self::can_manage_ratings()) {

            return;

        }

        if (!isset($_POST['hj_rb_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hj_rb_meta_nonce'])), self::NONCE_META)) {

            return;

        }

        $staff = isset($_POST[self::META_STAFF_STARS]) ? (float) self::en(wp_unslash($_POST[self::META_STAFF_STARS])) : 0;

        update_post_meta($post_id, self::META_STAFF_STARS, min(5.0, max(0.0, $staff)));

        $weight = isset($_POST[self::META_STAFF_WEIGHT]) ? (float) self::en(wp_unslash($_POST[self::META_STAFF_WEIGHT])) : 1;

        update_post_meta($post_id, self::META_STAFF_WEIGHT, min(100.0, max(0.0, $weight)));

        $delta = isset($_POST[self::META_DELTA]) ? (float) self::en(wp_unslash($_POST[self::META_DELTA])) : 0;

        update_post_meta($post_id, self::META_DELTA, min(5.0, max(-5.0, $delta)));

        $override_raw = isset($_POST[self::META_OVERRIDE]) ? trim((string) self::en(wp_unslash($_POST[self::META_OVERRIDE]))) : '';

        if ('' === $override_raw) {

            delete_post_meta($post_id, self::META_OVERRIDE);

        } else {

            $o = (float) $override_raw;

            update_post_meta($post_id, self::META_OVERRIDE, min(5.0, max(1.0, $o)));

        }

        $force = !empty($_POST[self::META_FORCE_SHOW]) ? 1 : 0;

        update_post_meta($post_id, self::META_FORCE_SHOW, $force);

        unset(self::$stats_mem[(int) $post_id]);

        self::refresh_cache($post_id);

    }



    /* ================================================================== */

    /* Admin-only cleanup & status pages (no terminal needed)              */

    /* ================================================================== */



    private static function plain_exit($lines) {

        nocache_headers();

        header('Content-Type: text/plain; charset=utf-8');

        echo esc_html(implode("\n", $lines));

        exit;

    }



    public static function admin_cleanup() {

        if (!self::can_manage_ratings()) {

            wp_die('دسترسی غیرمجاز', '', array('response' => 403));

        }

        check_admin_referer('hj_rb_cleanup');

        $L = array('Hamyar Doctors - rating cleanup (section 9)', '');

        $s = self::schema();

        if (!$s['ok']) {

            $L[] = 'votes table NOT FOUND - nothing to clean.';

            self::plain_exit($L);

        }

        global $wpdb;

        $tb   = $s['table'];

        $m    = $s['map'];

        $dc   = $m['doctor_id'];

        $cond = self::approved_sql_cond($s);

        $where = "NOT ({$cond})";

        if ($m['created_at'] !== '') {

            $cc = $m['created_at'];

            $t  = isset($s['types'][$cc]) ? $s['types'][$cc] : '';

            $is_ts = (strpos($t, 'int') !== false || strpos($t, 'timestamp') !== false);

            $cutoff = $is_ts ? (time() - 30 * DAY_IN_SECONDS) : gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);

            $where .= $wpdb->prepare(" AND `{$cc}` < %s", $cutoff); // phpcs:ignore WordPress.DB.PreparedSQL

        }

        $prev = $wpdb->suppress_errors(true);

        $ids = $wpdb->get_col("SELECT DISTINCT `{$dc}` FROM `{$tb}` WHERE {$where}"); // phpcs:ignore WordPress.DB.PreparedSQL

        $deleted = $wpdb->query("DELETE FROM `{$tb}` WHERE {$where}"); // phpcs:ignore WordPress.DB.PreparedSQL

        $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tb}`"); // phpcs:ignore WordPress.DB.PreparedSQL

        $wpdb->suppress_errors($prev);

        $ids = is_array($ids) ? $ids : array();

        foreach ($ids as $did) {

            unset(self::$stats_mem[(int) $did]);

            self::refresh_cache((int) $did);

        }

        $L[] = 'Deleted rows: ' . (int) $deleted;

        $L[] = 'Affected doctors: ' . count($ids);

        $L[] = 'Remaining rows: ' . $remaining;

        $L[] = '';

        $L[] = 'Cleanup page: wp-admin/admin-post.php?action=hj_rb_cleanup';

        self::plain_exit($L);

    }



    public static function admin_status() {

        if (!self::can_manage_ratings()) {

            wp_die('دسترسی غیرمجاز', '', array('response' => 403));

        }

        $L = array();

        $L[] = 'Hamyar Doctors - rating bridge status (section 9)';

        $L[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';

        $L[] = '';

        $s = self::schema();

        $L[] = 'Table: ' . ($s['ok'] ? $s['table'] : 'NOT FOUND');

        if ($s['ok']) {

            $L[] = 'Columns: ' . implode(', ', $s['cols']);

            foreach ($s['map'] as $k => $v) {

                $L[] = sprintf('  map %-10s => %s', $k, '' === $v ? '(none)' : $v);

            }

            $L[] = 'status_numeric: ' . ($s['status_numeric'] ? 'yes' : 'no');

            global $wpdb;

            $tb   = $s['table'];

            $m    = $s['map'];

            $dc   = $m['doctor_id'];

            $rc   = $m['rating'];

            $cond = self::approved_sql_cond($s);

            $prev = $wpdb->suppress_errors(true);

            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tb}`"); // phpcs:ignore WordPress.DB.PreparedSQL

            $appr  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tb}` WHERE {$cond}"); // phpcs:ignore WordPress.DB.PreparedSQL

            $wpdb->suppress_errors($prev);

            $L[] = '';

            $L[] = 'Rows total: ' . $total . ' | approved: ' . $appr . ' | other: ' . ($total - $appr);

            $L[] = '';

            $L[] = 'Top doctors by approved votes:';

            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL

                "SELECT `{$dc}` AS d, COUNT(*) AS c, AVG(`{$rc}`) AS a FROM `{$tb}` WHERE {$cond} GROUP BY `{$dc}` ORDER BY c DESC, a DESC LIMIT 10" // phpcs:ignore WordPress.DB.PreparedSQL

            , ARRAY_A);

            if (is_array($rows) && $rows) {

                foreach ($rows as $r) {

                    $L[] = sprintf('  doctor #%d: %d votes, avg %s', (int) $r['d'], (int) $r['c'], round((float) $r['a'], 2));

                }

            } else {

                $L[] = '  (none)';

            }

            $cached = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_CACHE_AVG)); // phpcs:ignore WordPress.DB.PreparedSQL

            $L[] = '';

            $L[] = 'Doctors with sort cache meta (' . self::META_CACHE_AVG . '): ' . $cached;

        }

        $L[] = '';

        $L[] = 'min_votes (hj_pr_min_votes): ' . self::min_votes();

        $L[] = 'max_doctors_per_ip (hj_rb_max_doctors_per_ip): ' . self::max_doctors_per_ip();

        $L[] = 'Cookie: ' . self::COOKIE_NAME . ' (HMAC-signed)';

        $L[] = 'AJAX: wp-admin/admin-ajax.php?action=hj_rb_vote';

        $L[] = '';

        $L[] = 'Hooks:';

        $L[] = '  hj_doctor_rating_stats: ' . (false !== has_filter('hj_doctor_rating_stats', array(__CLASS__, 'filter_rating_stats')) ? 'OK' : 'MISSING');

        $L[] = '  hj_ar_sort_options: ' . (false !== has_filter('hj_ar_sort_options', array(__CLASS__, 'add_sort_option')) ? 'OK' : 'MISSING');

        $L[] = '  hj_ar_apply_sort: ' . (false !== has_action('hj_ar_apply_sort', array(__CLASS__, 'apply_sort')) ? 'OK' : 'MISSING');

        $L[] = '  wp_ajax_hj_rb_vote: ' . (false !== has_action('wp_ajax_hj_rb_vote') ? 'OK' : 'MISSING');

        $L[] = '  wp_ajax_nopriv_hj_rb_vote: ' . (false !== has_action('wp_ajax_nopriv_hj_rb_vote') ? 'OK' : 'MISSING');

        $L[] = '';

        $L[] = 'Status page: wp-admin/admin-post.php?action=hj_rb_status';

        self::plain_exit($L);

    }

}



HJ_Doctors_Rating_Bridge::init();

endif;

<?php

/**
 * Hamyar Doctors — Section 10: Smart search, header search, FacetWP bridge
 *
 * File: wp-content/plugins/hamyar-doctors/includes/search-filters.php
 *
 * - Smart search (PHP index, cached): title + name_fa/name_en/specialty_fa/specialty_en
 * + term names/slugs (specialty, specialty_type, province, city) with Persian normalisation.
 *
 * - Basic archive: ?hj_q=... is handled here (replaces the plain title search of section 8).
 *
 * - Header search: [hj_doctor_search], widget, optional primary-menu injection,
 * do_action('hj_doctor_header_search').
 *
 * - FacetWP bridge (OFF by default; enable with define('HJ_FACETWP_ENABLED', true);).
 *
 * - Correct "highest rating" sorting (supersedes the apply_sort of section 9).
 *
 * Filters:
 * hj_sf_name_keys, hj_sf_spec_keys, hj_sf_stopwords, hj_sf_max_index, hj_sf_search_ids,
 * hj_sf_register_facets, hj_sf_facetwp_rating_sort, hj_sf_load_css,
 * hj_sf_header_menu_inject, hj_sf_header_menu_location
 *
 * Public functions:
 * hj_doctor_search_ids($q), hj_doctor_search_form($args)
 *
 * Optional wp-config.php constants:
 *   define('HJ_FACETWP_ENABLED', true);        // turn the FacetWP mode on
 *   define('HJ_FACETWP_SEARCH_MODE', 'get');   // 'facet' (default) or 'get'
 */



if (!defined('ABSPATH')) {

    exit;

}



if (!class_exists('HJ_Doctors_Search')) :



final class HJ_Doctors_Search {



    const TRANSIENT = 'hj_sf_index_v1';



    private static $mem = null;

    private static $watch = null;

    private static $queue = array();

    private static $queue_hooked = false;



    public static function init() {

        // Basic archive: smart ?hj_q (runs after section 8's pre_get_posts at priority 10)

        add_action('pre_get_posts', array(__CLASS__, 'pre_get_posts'), 20);

        add_action('hj_ar_apply_sort', array(__CLASS__, 'fix_rating_sort'), 20, 2);

        add_filter('wp_robots', array(__CLASS__, 'robots'));



        // Header search

        add_shortcode('hj_doctor_search', array(__CLASS__, 'shortcode'));

        add_action('hj_doctor_header_search', array(__CLASS__, 'echo_form'));

        add_action('widgets_init', array(__CLASS__, 'register_widget'));

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 20);

        add_filter('wp_nav_menu_items', array(__CLASS__, 'menu_items'), 20, 2);



        // FacetWP

        add_filter('hj_ar_use_facetwp', array(__CLASS__, 'filter_use_facetwp'), 5);

        add_filter('hj_ar_show_basic_filters', array(__CLASS__, 'filter_show_basic'), 5);

        add_filter('facetwp_facets', array(__CLASS__, 'register_facets'));

        add_filter('facetwp_is_main_query', array(__CLASS__, 'facet_is_main_query'), 10, 2);

        add_filter('facetwp_facet_filter_posts', array(__CLASS__, 'facet_filter_posts'), 10, 2);

        add_filter('facetwp_facet_html', array(__CLASS__, 'facet_html'), 10, 2);

        add_action('hj_doctor_archive_filters', array(__CLASS__, 'print_facets'));

        add_action('hj_doctor_archive_after_results', array(__CLASS__, 'print_pager'));



        // Index invalidation + FacetWP re-index

        add_action('save_post_doctor', array(__CLASS__, 'on_post_change'), 99);

        add_action('trashed_post', array(__CLASS__, 'on_post_change'));

        add_action('untrashed_post', array(__CLASS__, 'on_post_change'));

        add_action('before_delete_post', array(__CLASS__, 'on_post_delete'));

        add_action('transition_post_status', array(__CLASS__, 'on_transition'), 10, 3);

        add_action('set_object_terms', array(__CLASS__, 'on_set_terms'), 10, 4);

        add_action('created_term', array(__CLASS__, 'on_term_change'), 10, 3);

        add_action('edited_term', array(__CLASS__, 'on_term_change'), 10, 3);

        add_action('delete_term', array(__CLASS__, 'on_term_change'), 10, 3);

        add_action('added_post_meta', array(__CLASS__, 'on_meta_change'), 10, 3);

        add_action('updated_post_meta', array(__CLASS__, 'on_meta_change'), 10, 3);

        add_action('deleted_post_meta', array(__CLASS__, 'on_meta_change'), 10, 3);



        // Admin tools (no terminal needed)

        add_action('admin_post_hj_sf_status', array(__CLASS__, 'admin_status'));

        add_action('admin_post_hj_sf_rebuild', array(__CLASS__, 'admin_rebuild'));

    }



    /* ================================================================== */

    /* Small helpers */

    /* ================================================================== */



    private static function log($m) {

        if (function_exists('hj_doctors_log')) {

            hj_doctors_log('[search] ' . $m);

        }

    }



    private static function cut($s, $max) {

        $s = (string) $s;

        return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);

    }



    private static function len($s) {

        return function_exists('mb_strlen') ? mb_strlen((string) $s, 'UTF-8') : strlen((string) $s);

    }



    private static function fa($s) {

        if (function_exists('hj_digits')) {

            return hj_digits((string) $s, 'fa');

        }

        return strtr((string) $s, array(

            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',

            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',

        ));

    }



    private static function taxes() {

        return array('specialty', 'specialty_type', 'province', 'city');

    }



    private static function is_listing() {

        return is_post_type_archive('doctor') || is_tax(self::taxes());

    }



    private static function archive_url() {

        $u = get_post_type_archive_link('doctor');

        return $u ? $u : home_url('/doctor/');

    }



    private static function facetwp_active() {

        return defined('FACETWP_VERSION') && function_exists('FWP');

    }



    private static function facetwp_enabled() {

        return defined('HJ_FACETWP_ENABLED') && HJ_FACETWP_ENABLED && self::facetwp_active();

    }



    private static function search_mode() {

        return (defined('HJ_FACETWP_SEARCH_MODE') && HJ_FACETWP_SEARCH_MODE === 'get') ? 'get' : 'facet';

    }



    /* ================================================================== */

    /* Persian-aware normalisation */

    /* ================================================================== */



    public static function norm($s) {

        $s = (string) $s;

        if ($s === '') {

            return '';

        }

        $s = strtr($s, array(

            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',

            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',

            'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه',

            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ؤ' => 'و',

        ));

        $r = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200C}\x{200D}\x{200E}\x{200F}\x{00AD}]/u', '', $s);

        if (is_string($r)) {

            $s = $r;

        }

        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

        $r = preg_replace('/\s+/u', ' ', $s);

        if (is_string($r)) {

            $s = $r;

        }

        return trim($s);

    }



    private static function tokens($norm) {

        $parts = preg_split('/[\s,،;؛\/\\\\\-_.]+/u', $norm, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($parts)) {

            return array();

        }

        $stop = array();

        $list = (array) apply_filters('hj_sf_stopwords', array('دکتر', 'دکترا', 'آقای', 'خانم', 'dr', 'doctor'));

        foreach ($list as $w) {

            $n = self::norm($w);

            if ($n !== '') {

                $stop[$n] = true;

            }

        }

        $all = array();

        $kept = array();

        foreach ($parts as $p) {

            $all[] = $p;

            if (!isset($stop[$p])) {

                $kept[] = $p;

            }

        }

        $use = $kept ? $kept : $all;

        return array_slice(array_values(array_unique($use)), 0, 6);

    }



    /* ================================================================== */

    /* Search index (PHP, cached in a transient) */

    /* ================================================================== */



    private static function meta_str($id, $key) {

        $v = get_post_meta($id, $key, true);

        return is_scalar($v) ? trim((string) $v) : '';

    }



    private static function name_keys() {

        return (array) apply_filters('hj_sf_name_keys', array('name_fa', 'name_en'));

    }



    private static function spec_keys() {

        return (array) apply_filters('hj_sf_spec_keys', array('specialty_fa', 'specialty_en'));

    }



    private static function build_index() {

        $max = max(50, (int) apply_filters('hj_sf_max_index', 5000));

        $args = array(

            'post_type' => 'doctor',

            'post_status' => 'publish',

            'posts_per_page' => $max,

            'no_found_rows' => true,

            'orderby' => 'ID',

            'order' => 'ASC',

            'ignore_sticky_posts' => true,

        );

        if (class_exists('HJ_Doctors_Archive_Render') && is_callable(array('HJ_Doctors_Archive_Render', 'visibility_meta_query'))) {

            $args['meta_query'] = HJ_Doctors_Archive_Render::visibility_meta_query();

        }

        $q = new WP_Query($args);



        $name_keys = self::name_keys();

        $spec_keys = self::spec_keys();

        $out = array();



        foreach ((array) $q->posts as $p) {

            $id = (int) $p->ID;



            $n = array($p->post_title);

            foreach ($name_keys as $k) {

                $n[] = self::meta_str($id, $k);

            }

            $s = array();

            foreach ($spec_keys as $k) {

                $s[] = self::meta_str($id, $k);

            }



            $t = array();

            $has_prov = false;

            $cities = array();

            foreach (self::taxes() as $tax) {

                if (!taxonomy_exists($tax)) {

                    continue;

                }

                $terms = get_the_terms($id, $tax);

                if (!$terms || is_wp_error($terms)) {

                    continue;

                }

                foreach ($terms as $term) {

                    $t[] = $term->name;

                    $t[] = urldecode($term->slug);

                    $en = get_term_meta($term->term_id, 'hj_name_en', true);

                    if (is_string($en) && $en !== '') {

                        $t[] = $en;

                    }

                    if ($tax === 'province') {

                        $has_prov = true;

                    }

                    if ($tax === 'city') {

                        $cities[] = (int) $term->term_id;

                    }

                }

            }

            if (!$has_prov && $cities && function_exists('hj_get_city_province_id') && taxonomy_exists('province')) {

                foreach ($cities as $cid) {

                    $pt_id = (int) hj_get_city_province_id($cid);

                    if ($pt_id) {

                        $pt = get_term($pt_id, 'province');

                        if ($pt && !is_wp_error($pt)) {

                            $t[] = $pt->name;

                            $t[] = urldecode($pt->slug);

                        }

                    }

                }

            }



            $out[$id] = array(

                'n' => self::norm(implode(' ', $n)),

                's' => self::norm(implode(' ', $s)),

                't' => self::norm(implode(' ', $t)),

            );

        }

        return $out;

    }



    public static function index() {

        if (self::$mem !== null) {

            return self::$mem;

        }

        $c = get_transient(self::TRANSIENT);

        if (is_array($c)) {

            self::$mem = $c;

            return $c;

        }

        $c = self::build_index();

        set_transient(self::TRANSIENT, $c, 6 * HOUR_IN_SECONDS);

        self::$mem = $c;

        return $c;

    }



    public static function flush_index() {

        self::$mem = null;

        delete_transient(self::TRANSIENT);

    }


    /* ================================================================== */

    /* Smart search scoring                                                */

    /* ================================================================== */



    private static function has($hay, $needle) {

        if ($needle === '') {

            return false;

        }

        if (function_exists('mb_strpos')) {

            return mb_strpos($hay, $needle, 0, 'UTF-8') !== false;

        }

        return strpos($hay, $needle) !== false;

    }



    /**
     * Doctor IDs matching the smart query, best relevance first.
     *
     * AND across words; scoring per hit: name 10, specialty 5, location 3.
     *
     * @param string $q Raw query.
     * @return int[]
     */

    public static function search_ids($q) {

        $q = self::norm($q);

        if ($q === '') {

            return array();

        }

        $tokens = self::tokens($q);

        if (!$tokens) {

            return array();

        }

        $scores = array();

        foreach (self::index() as $id => $row) {

            $ok    = true;

            $score = 0;

            foreach ($tokens as $tk) {

                $hit = false;

                if (self::has($row['n'], $tk)) {

                    $score += 10;

                    $hit = true;

                } elseif (self::has($row['s'], $tk)) {

                    $score += 5;

                    $hit = true;

                } elseif (self::has($row['t'], $tk)) {

                    $score += 3;

                    $hit = true;

                }

                if (!$hit) {

                    $ok = false;

                    break;

                }

            }

            if ($ok) {

                $scores[(int) $id] = $score;

            }

        }

        arsort($scores);

        $ids = array_map('intval', array_keys($scores));

        return (array) apply_filters('hj_sf_search_ids', $ids, $q);

    }



    /* ================================================================== */

    /* Main query: smart ?hj_q replaces the plain title search (section 8) */

    /* ================================================================== */



    public static function pre_get_posts($query) {

        if (is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {

            return;

        }

        if (!($query->is_post_type_archive('doctor') || $query->is_tax(self::taxes()))) {

            return;

        }

        $q = '';

        if (isset($_GET['hj_q']) && !is_array($_GET['hj_q'])) {

            $q = self::cut(sanitize_text_field(wp_unslash($_GET['hj_q'])), 60);

        }

        if (trim($q) === '') {

            return;

        }

        $ids = self::search_ids($q);

        $query->set('s', ''); // supersede section 8's title-only search

        $query->set('post__in', $ids ? $ids : array(0));

    }



    /**
     * Correct "highest rating" ordering: a single named meta clause, not an
     * OR group. Priority 20 supersedes section 9's apply_sort (priority 10).
     */

    public static function fix_rating_sort($query, $sort) {

        if ($sort !== 'rating') {

            return;

        }

        $mq = $query->get('meta_query');

        if (!is_array($mq)) {

            $mq = array();

        }

        $mq['hj_sf_rating_sort'] = array('key' => '_hj_rating_public_avg', 'compare' => 'EXISTS');

        $query->set('meta_query', $mq);

        $query->set('meta_key', '');

        $query->set('orderby', array('hj_sf_rating_sort' => 'DESC', 'title' => 'ASC'));

    }



    public static function robots($robots) {

        if (self::is_listing() && isset($_GET['hj_q']) && !is_array($_GET['hj_q']) && trim((string) wp_unslash($_GET['hj_q'])) !== '') {

            $robots['noindex'] = true;

            $robots['follow']  = true;

            unset($robots['index']);

        }

        return $robots;

    }



    /* ================================================================== */

    /* Header search form: [hj_doctor_search], widget, menu, action        */

    /* ================================================================== */



    public static function search_form($args = array()) {

        $a = shortcode_atts(

            array(

                'placeholder' => 'جست‌وجوی جراح، تخصص یا شهر…',

                'button'      => 'جست‌وجو',

                'class'       => '',

                'compact'     => false,

            ),

            (array) $args,

            'hj_doctor_search'

        );

        $q = '';

        if (isset($_GET['hj_q']) && !is_array($_GET['hj_q'])) {

            $q = sanitize_text_field(wp_unslash($_GET['hj_q']));

        }

        $cls = 'hj-hs' . ($a['compact'] ? ' hj-hs--compact' : '') . ('' !== $a['class'] ? ' ' . sanitize_html_class($a['class']) : '');

        $h  = '<form class="' . esc_attr($cls) . '" method="get" action="' . esc_url(self::archive_url()) . '" role="search">';

        $h .= '<input type="search" name="hj_q" value="' . esc_attr($q) . '" placeholder="' . esc_attr($a['placeholder']) . '" maxlength="60" autocomplete="off" aria-label="' . esc_attr($a['placeholder']) . '">';

        $h .= '<button type="submit">' . esc_html($a['button']) . '</button>';

        $h .= '</form>';

        return $h;

    }



    public static function shortcode($atts) {

        return self::search_form(is_array($atts) ? $atts : array());

    }



    public static function echo_form() {

        echo self::search_form(array()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside

    }



    public static function register_widget() {

        register_widget('HJ_Doctor_Search_Widget');

    }



    public static function menu_items($items, $args) {

        if (!apply_filters('hj_sf_header_menu_inject', false)) {

            return $items;

        }

        $loc = (string) apply_filters('hj_sf_header_menu_location', 'primary');

        if (isset($args->theme_location) && (string) $args->theme_location !== $loc) {

            return $items;

        }

        return $items . '<li class="menu-item hj-hs-menu">' . self::search_form(array('compact' => true)) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    }



    public static function enqueue() {

        if (is_admin() || !apply_filters('hj_sf_load_css', true)) {

            return;

        }

        wp_register_style('hj-sf-css', false, array(), defined('HJ_DOCTORS_VERSION') ? HJ_DOCTORS_VERSION : '1');

        wp_enqueue_style('hj-sf-css');

        wp_add_inline_style('hj-sf-css', self::inline_css());

    }



    private static function inline_css() {

        return '.hj-hs{display:flex;gap:6px;align-items:center}'

            . '.hj-hs input[type=search]{min-width:180px;padding:6px 10px;border:1px solid #D3DFEA;border-radius:10px;font:inherit;font-size:.9rem}'

            . '.hj-hs button{padding:6px 14px;border:0;border-radius:10px;background:#0EA5A0;color:#fff;font:inherit;font-size:.9rem;cursor:pointer}'

            . '.hj-hs button:hover{background:#0F3556}'

            . '.hj-hs--compact input[type=search]{min-width:130px}'

            . '.hj-hs-menu .hj-hs{margin:0}';

    }



    /* ================================================================== */

    /* FacetWP bridge (OFF by default)                                     */
    /*                                                                     */
    /* wp-config.php: define('HJ_FACETWP_ENABLED', true);                  */
    /*                define('HJ_FACETWP_SEARCH_MODE', 'get'); (optional)  */
    /* ================================================================== */



    public static function filter_use_facetwp($v) {

        return self::facetwp_enabled() ? true : $v;

    }



    public static function filter_show_basic($v) {

        return self::facetwp_enabled() ? false : $v;

    }



    public static function register_facets($facets) {

        if (!is_array($facets)) {

            $facets = array();

        }

        $facets[] = array('name' => 'hj_search', 'type' => 'search', 'label' => 'جست‌وجو', 'placeholder' => 'نام جراح، تخصص یا شهر…');

        $facets[] = array('name' => 'hj_specialty', 'type' => 'dropdown', 'label' => 'تخصص', 'source' => 'specialty');

        $facets[] = array('name' => 'hj_type', 'type' => 'dropdown', 'label' => 'نوع تخصص', 'source' => 'specialty_type');

        $facets[] = array('name' => 'hj_province', 'type' => 'dropdown', 'label' => 'استان', 'source' => 'province');

        $facets[] = array('name' => 'hj_city', 'type' => 'dropdown', 'label' => 'شهر', 'source' => 'city');

        $facets[] = array(

            'name'         => 'hj_sort',

            'type'         => 'sort',

            'label'        => 'مرتب‌سازی',

            'sort_options' => array(

                array('label' => 'ترتیب الفبایی', 'name' => 'name'),

                array('label' => 'جدیدترین', 'name' => 'new'),

                array('label' => 'پرامتیازترین', 'name' => 'rating'),

            ),

        );

        $facets[] = array('name' => 'hj_pager', 'type' => 'pager', 'label' => 'صفحه‌بندی');

        return (array) apply_filters('hj_sf_register_facets', $facets);

    }



    public static function facet_is_main_query($is, $query) {

        if ($query instanceof WP_Query && ($query->is_post_type_archive('doctor') || $query->is_tax(self::taxes()))) {

            return true;

        }

        return $is;

    }



    private static function facet_selected($name) {

        if (function_exists('FWP') && isset(FWP()->facet) && isset(FWP()->facet->selected_values) && is_array(FWP()->facet->selected_values)) {

            $sv = FWP()->facet->selected_values;

            return isset($sv[$name]) ? (array) $sv[$name] : array();

        }

        return array();

    }



    /**
     * AND relation across facets: every selected facet narrows the ID set.
     * Defensive: any unknown shape returns the incoming filter untouched.
     */

    public static function facet_filter_posts($filter, $facet) {

        if (!self::facetwp_enabled() || !function_exists('FWP')) {

            return $filter;

        }

        $name = is_array($facet) && isset($facet['name']) ? (string) $facet['name'] : '';

        $tax_map = array(

            'hj_specialty' => 'specialty',

            'hj_type'      => 'specialty_type',

            'hj_province'  => 'province',

            'hj_city'      => 'city',

        );

        if ($name === 'hj_search') {

            $vals = self::facet_selected('hj_search');

            $q = ($vals && isset($vals[0])) ? (string) $vals[0] : '';

            if ($q !== '') {

                $ids = self::search_ids($q);

                $ids = $ids ? $ids : array(0);

                return ($filter === true) ? $ids : array_values(array_intersect((array) $filter, $ids));

            }

            return $filter;

        }

        if (!isset($tax_map[$name])) {

            return $filter;

        }

        $vals = self::facet_selected($name);

        if (!$vals) {

            return $filter;

        }

        $ids = array();

        foreach ($vals as $slug) {

            $term = get_term_by('slug', sanitize_title((string) $slug), $tax_map[$name]);

            if ($term && !is_wp_error($term)) {

                $ids = array_merge($ids, get_objects_in_term($term->term_id, $tax_map[$name]));

            }

        }

        $ids = array_map('intval', $ids);

        if (!$ids) {

            $ids = array(0);

        }

        return ($filter === true) ? $ids : array_values(array_intersect((array) $filter, $ids));

    }



    /** Persian digits inside facet counters. */

    public static function facet_html($html, $facet) {

        return preg_replace_callback(

            '/\d+/',

            function ($m) {

                return self::fa($m[0]);

            },

            (string) $html

        );

    }



    public static function print_facets() {

        if (!self::facetwp_enabled()) {

            return;

        }

        echo '<div class="hj-ar__facets hj-sf__facets">';

        foreach (self::register_facets(array()) as $f) {

            if (isset($f['name'])) {

                echo do_shortcode('[facetwp facet="' . esc_attr($f['name']) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP markup

            }

        }

        echo '</div>';

    }



    public static function print_pager() {

        if (!self::facetwp_enabled()) {

            return;

        }

        echo do_shortcode('[facetwp pager="hj_pager"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP markup

    }



    /* ================================================================== */

    /* Index invalidation + FacetWP re-index                               */

    /* ================================================================== */



    private static function queue_flush() {

        self::$queue[] = 1;

        if (!self::$queue_hooked) {

            self::$queue_hooked = true;

            add_action('shutdown', array(__CLASS__, 'flush_queue'));

        }

    }



    public static function flush_queue() {

        if (self::$queue) {

            self::flush_index();

            self::$queue = array();

        }

    }



    private static function fwp_index_post($post_id) {

        if (!self::facetwp_active() || !function_exists('FWP')) {

            return;

        }

        try {

            if (isset(FWP()->indexer) && is_object(FWP()->indexer) && method_exists(FWP()->indexer, 'index')) {

                FWP()->indexer->index(array('post_id' => (int) $post_id));

            }

        } catch (\Throwable $e) {

            self::log('facetwp index failed: ' . $e->getMessage());

        }

    }



    public static function on_post_change($post_id) {

        if (get_post_type($post_id) === 'doctor') {

            self::queue_flush();

            self::fwp_index_post($post_id);

        }

    }



    public static function on_post_delete($post_id) {

        self::on_post_change($post_id);

    }



    public static function on_transition($new_status, $old_status, $post) {

        if (is_object($post) && isset($post->post_type) && $post->post_type === 'doctor') {

            self::queue_flush();

        }

    }



    public static function on_set_terms($post_id, $terms, $tt_ids, $taxonomy) {

        if (in_array($taxonomy, self::taxes(), true)) {

            self::queue_flush();

        }

    }



    public static function on_term_change($term_id, $tt_id, $taxonomy) {

        if (in_array($taxonomy, self::taxes(), true)) {

            self::queue_flush();

        }

    }



    public static function on_meta_change($meta_id, $post_id, $key) {

        if (get_post_type($post_id) !== 'doctor') {

            return;

        }

        $index_keys = array('name_fa', 'name_en', 'specialty_fa', 'specialty_en', 'profile_hidden', 'profile_status');

        $rating_keys = array('internal_staff_stars', 'internal_staff_weight', 'hj_rating_manual_delta', 'hj_rating_manual_override', 'hj_rating_force_show', '_hj_rating_public_avg', '_hj_rating_public_count');

        if (in_array($key, $index_keys, true)) {

            self::queue_flush();

        }

        if (in_array($key, $rating_keys, true)) {

            self::queue_flush();

            if (class_exists('HJ_Doctors_Rating_Bridge') && is_callable(array('HJ_Doctors_Rating_Bridge', 'refresh_cache'))) {

                HJ_Doctors_Rating_Bridge::refresh_cache((int) $post_id);

            }

        }

    }



    /* ================================================================== */

    /* Admin tools (no terminal needed)                                    */

    /* ================================================================== */



    private static function plain_exit($lines) {

        nocache_headers();

        header('Content-Type: text/plain; charset=utf-8');

        echo esc_html(implode("\n", $lines));

        exit;

    }



    public static function admin_status() {

        if (!current_user_can('manage_options')) {

            wp_die('دسترسی غیرمجاز', '', array('response' => 403));

        }

        $L = array();

        $L[] = 'Hamyar Doctors - search & filters status (section 10)';

        $L[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';

        $L[] = '';

        $L[] = 'FacetWP plugin active: ' . (self::facetwp_active() ? 'yes' : 'no');

        $L[] = 'HJ_FACETWP_ENABLED: ' . ((defined('HJ_FACETWP_ENABLED') && HJ_FACETWP_ENABLED) ? 'on' : 'off');

        $L[] = 'FacetWP mode: ' . (self::facetwp_enabled() ? 'ON' : 'OFF') . ' | search mode: ' . self::search_mode();

        $L[] = 'Basic GET filters (section 8): ' . (self::facetwp_enabled() ? 'hidden' : 'shown');

        $idx = self::index();

        $L[] = 'Search index: ' . count($idx) . ' public doctors (transient "' . self::TRANSIENT . '")';

        $L[] = 'Archive URL: ' . self::archive_url();

        $L[] = '';

        $L[] = 'Hooks:';

        $L[] = '  pre_get_posts smart hj_q: ' . (false !== has_action('pre_get_posts', array(__CLASS__, 'pre_get_posts')) ? 'OK' : 'MISSING');

        $L[] = '  hj_ar_apply_sort rating fix: ' . (false !== has_action('hj_ar_apply_sort', array(__CLASS__, 'fix_rating_sort')) ? 'OK' : 'MISSING');

        $L[] = '  shortcode hj_doctor_search: ' . (shortcode_exists('hj_doctor_search') ? 'OK' : 'MISSING');

        $L[] = '  action hj_doctor_header_search: ' . (false !== has_action('hj_doctor_header_search') ? 'OK' : 'MISSING');

        $L[] = '  hj_doctor_archive_filters facets: ' . (false !== has_action('hj_doctor_archive_filters', array(__CLASS__, 'print_facets')) ? 'OK' : 'MISSING');

        $L[] = '';

        $L[] = 'Rebuild index: ' . wp_nonce_url(admin_url('admin-post.php?action=hj_sf_rebuild'), 'hj_sf_rebuild');

        $L[] = 'Status page: wp-admin/admin-post.php?action=hj_sf_status';

        self::plain_exit($L);

    }



    public static function admin_rebuild() {

        if (!current_user_can('manage_options')) {

            wp_die('دسترسی غیرمجاز', '', array('response' => 403));

        }

        check_admin_referer('hj_sf_rebuild');

        self::flush_index();

        $idx = self::index(); // rebuilds and caches the PHP index

        $rated = 0;

        if (class_exists('HJ_Doctors_Rating_Bridge') && is_callable(array('HJ_Doctors_Rating_Bridge', 'refresh_cache'))) {

            foreach (array_keys($idx) as $id) {

                HJ_Doctors_Rating_Bridge::refresh_cache((int) $id);

                $rated++;

            }

        }

        $fwp = 0;

        foreach (array_keys($idx) as $id) {

            self::fwp_index_post($id);

            $fwp++;

        }

        $L = array();

        $L[] = 'Rebuild done.';

        $L[] = 'Search index: ' . count($idx) . ' doctors';

        $L[] = 'Rating caches refreshed: ' . $rated;

        $L[] = 'FacetWP rows indexed: ' . $fwp . ' (plugin ' . (self::facetwp_active() ? 'active' : 'inactive') . ')';

        $L[] = '';

        $L[] = 'Status: ' . admin_url('admin-post.php?action=hj_sf_status');

        self::plain_exit($L);

    }

}



HJ_Doctors_Search::init();

endif;



if (!class_exists('HJ_Doctor_Search_Widget')) :



/**
 * «جست‌وجوی پزشکان (همیار)» sidebar widget.
 */

class HJ_Doctor_Search_Widget extends WP_Widget {



    public function __construct() {

        parent::__construct(

            'hj_doctor_search',

            'جست‌وجوی پزشکان (همیار)',

            array('description' => 'فرم جست‌وجوی هوشمند پزشکان (بخش ۱۰).')

        );

    }



    public function widget($args, $instance) {

        $title = !empty($instance['title']) ? (string) $instance['title'] : '';

        echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup

        if ($title !== '') {

            echo $args['before_title'] . esc_html($title) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        }

        echo HJ_Doctors_Search::search_form(array()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside

        echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    }



    public function form($instance) {

        $title = isset($instance['title']) ? (string) $instance['title'] : '';

        ?>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">عنوان:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>

        <?php

    }



    public function update($new_instance, $old_instance) {

        return array('title' => isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '');

    }

}



endif;



if (!function_exists('hj_doctor_search_ids')) {

    /**
     * Doctor IDs matching a smart search query (section 10 index).
     *
     * @param string $q Query text.
     * @return int[]
     */

    function hj_doctor_search_ids($q) {

        return HJ_Doctors_Search::search_ids($q);

    }

}



if (!function_exists('hj_doctor_search_form')) {

    /**
     * Doctor search form HTML (class hj-hs, param hj_q).
     *
     * @param array $args placeholder|button|class|compact.
     * @return string
     */

    function hj_doctor_search_form($args = array()) {

        return HJ_Doctors_Search::search_form($args);

    }

}

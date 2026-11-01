<?php

/**
 * Hamyar Doctors — Section 8: Doctor archive + taxonomy listings
 *
 * File: wp-content/plugins/hamyar-doctors/includes/archive-render.php
 *
 * - Main query of /doctor/ and specialty|province|city|specialty_type archives:
 *   only public profiles (publish + not hidden + status not draft/pending/rejected).
 *
 * - Basic GET filters (no JS): hj_q, hj_specialty, hj_province, hj_city, hj_type, hj_sort.
 *
 * - Doctor card renderer (empty fields are never printed; Persian digits).
 *
 * Hooks / filters:
 *
 *   do_action('hj_doctor_archive_filters');            // section 10 (FacetWP) prints here
 *
 *   apply_filters('hj_ar_use_facetwp', false);         // section 10 turns this on
 *
 *   apply_filters('hj_ar_show_basic_filters', true);
 *
 *   apply_filters('hj_ar_per_page', 12);
 *
 *   apply_filters('hj_ar_sort_options', array(...));   // section 9 may add "rating"
 *
 *   apply_filters('hj_ar_default_sort', 'name');
 *
 *   do_action('hj_ar_apply_sort', $query, $sort);      // for extra sort keys
 *
 *   apply_filters('hj_doctor_card_extra_html', '', $id);
 *
 *   apply_filters('hj_doctor_rating_stats', null, $id); // section 9
 *
 *   apply_filters('hj_pr_min_votes', 5);
 *
 * Public functions:
 *
 *   hj_doctor_archive_html(), hj_doctor_card_html($id), hj_dp_is_public($post_id)
 *
 * Admin status page (no terminal needed):
 *
 *   wp-admin/admin-post.php?action=hj_ar_status
 */



if (!defined('ABSPATH')) {

    exit;

}



if (!class_exists('HJ_Doctors_Archive_Render')) :



final class HJ_Doctors_Archive_Render {



    private static $css_done = false;



    public static function init() {

        add_action('pre_get_posts', array(__CLASS__, 'pre_get_posts'));

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 20);

        add_filter('body_class', array(__CLASS__, 'body_class'));

        add_filter('wp_robots', array(__CLASS__, 'robots'));

        add_action('admin_post_hj_ar_status', array(__CLASS__, 'admin_status'));

    }



    /* ================================================================== */

    /* Context                                                             */

    /* ================================================================== */



    private static function taxes() {

        return array('specialty', 'province', 'city', 'specialty_type');

    }



    private static function filter_map() {

        return array(

            'hj_type'      => 'specialty_type',

            'hj_specialty' => 'specialty',

            'hj_province'  => 'province',

            'hj_city'      => 'city',

        );

    }



    public static function is_listing() {

        return is_post_type_archive('doctor') || is_tax(self::taxes());

    }



    private static function per_page() {

        return max(1, (int) apply_filters('hj_ar_per_page', 12));

    }



    private static function use_facetwp() {

        return (bool) apply_filters('hj_ar_use_facetwp', false);

    }



    /* ================================================================== */

    /* GET params                                                          */

    /* ================================================================== */



    private static function qparam($key) {

        if (!isset($_GET[$key]) || is_array($_GET[$key])) {

            return '';

        }

        return sanitize_title(wp_unslash($_GET[$key]));

    }



    private static function qtext() {

        if (!isset($_GET['hj_q']) || is_array($_GET['hj_q'])) {

            return '';

        }

        $v = sanitize_text_field(wp_unslash($_GET['hj_q']));

        return function_exists('mb_substr') ? mb_substr($v, 0, 60) : substr($v, 0, 60);

    }



    private static function sort_options() {

        return (array) apply_filters('hj_ar_sort_options', array(

            'name' => 'ترتیب الفبایی',

            'new'  => 'جدیدترین',

        ));

    }



    private static function current_sort() {

        $opts = self::sort_options();

        $k    = isset($_GET['hj_sort']) && !is_array($_GET['hj_sort']) ? sanitize_key(wp_unslash($_GET['hj_sort'])) : '';

        if ($k !== '' && isset($opts[$k])) {

            return $k;

        }

        $d = (string) apply_filters('hj_ar_default_sort', 'name');

        return isset($opts[$d]) ? $d : 'name';

    }



    private static function has_filters() {

        foreach (array_keys(self::filter_map()) as $p) {

            if (self::qparam($p) !== '') {

                return true;

            }

        }

        return self::qtext() !== '';

    }



    private static function has_params() {

        return self::has_filters() || isset($_GET['hj_sort']);

    }



    /* ================================================================== */

    /* Main query                                                          */

    /* ================================================================== */



    /** Stored profile_status values that must NOT be public. */

    private static function hidden_status_values() {

        $map = apply_filters('hj_dash_status_map', array(

            'draft' => 'draft', 'pending' => 'pending', 'published' => 'published', 'rejected' => 'rejected',

        ));

        $vals = array();

        foreach (array('draft', 'pending', 'rejected') as $k) {

            if (isset($map[$k])) {

                $vals[] = (string) $map[$k];

            }

        }

        return array_values(array_unique($vals));

    }



    public static function visibility_meta_query() {

        return array(

            'relation' => 'AND',

            array(

                'relation' => 'OR',

                array('key' => 'profile_hidden', 'compare' => 'NOT EXISTS'),

                array('key' => 'profile_hidden', 'value' => array('', '0'), 'compare' => 'IN'),

            ),

            array(

                'relation' => 'OR',

                array('key' => 'profile_status', 'compare' => 'NOT EXISTS'),

                array('key' => 'profile_status', 'value' => self::hidden_status_values(), 'compare' => 'NOT IN'),

            ),

        );

    }



    public static function pre_get_posts($query) {

        if (is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {

            return;

        }

        if (!($query->is_post_type_archive('doctor') || $query->is_tax(self::taxes()))) {

            return;

        }



        $query->set('post_type', 'doctor');

        $query->set('post_status', 'publish');

        $query->set('posts_per_page', self::per_page());

        $query->set('ignore_sticky_posts', true);



        // Visibility

        $mine     = self::visibility_meta_query();

        $existing = $query->get('meta_query');

        if (is_array($existing) && $existing) {

            $mine = array('relation' => 'AND', $existing, $mine);

        }

        $query->set('meta_query', $mine);



        // Taxonomy filters from GET (slugs)

        $tq = $query->get('tax_query');

        $tq = is_array($tq) ? $tq : array();

        $added = false;

        foreach (self::filter_map() as $param => $tax) {

            $slug = self::qparam($param);

            if ($slug === '' || !taxonomy_exists($tax)) {

                continue;

            }

            $term = get_term_by('slug', $slug, $tax);

            if (!$term || is_wp_error($term)) {

                continue;

            }

            $tq[]  = array('taxonomy' => $tax, 'field' => 'term_id', 'terms' => array((int) $term->term_id));

            $added = true;

        }

        if ($added) {

            $tq['relation'] = 'AND';

            $query->set('tax_query', $tq);

        }



        // Search by title (section 10 provides the full search)

        $q = self::qtext();

        if ($q !== '') {

            $query->set('s', $q);

        }



        // Sort

        $sort = self::current_sort();

        if ($sort === 'new') {

            $query->set('orderby', 'date');

            $query->set('order', 'DESC');

        } elseif ($sort === 'name') {

            $query->set('orderby', 'title');

            $query->set('order', 'ASC');

        } else {

            do_action('hj_ar_apply_sort', $query, $sort);

        }



        if (self::use_facetwp()) {

            $query->set('facetwp', true);

        }

    }



    /* ================================================================== */

    /* Visibility of a single profile (re-checked at render time)         */

    /* ================================================================== */



    /**
     * Whether a doctor profile is publicly visible right now.
     *
     * Mirrors the main-query rules: publish + not hidden + status not
     * draft/pending/rejected. Profiles without the new meta (demo data,
     * /doctor/114/) count as public.
     *
     * @param int $post_id Post ID; 0 means the current post.
     * @return bool
     */

    public static function is_public($post_id = 0) {

        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();

        if (!$post_id || !get_post($post_id)) {

            return false;

        }

        if ('publish' !== (string) get_post_status($post_id)) {

            return false;

        }

        $hidden = get_post_meta($post_id, 'profile_hidden', true);

        if ($hidden && '0' !== (string) $hidden) {

            return false;

        }

        $status = (string) get_post_meta($post_id, 'profile_status', true);

        if ('' !== $status && in_array($status, self::hidden_status_values(), true)) {

            return false;

        }

        return true;

    }



    /* ================================================================== */

    /* Assets / body class / robots                                        */

    /* ================================================================== */



    public static function enqueue() {

        if (self::$css_done) {

            return;

        }

        self::$css_done = true;

        wp_register_style('hj-ar-css', hj_doctors_url('assets/css/archive.css'), array(), HJ_DOCTORS_VERSION);

        if (self::is_listing()) {

            wp_enqueue_style('hj-ar-css');

        }

    }



    public static function body_class($classes) {

        if (self::is_listing()) {

            $classes[] = 'hj-doctor-archive';

        }

        return $classes;

    }



    /**
     * Filtered listing pages are noindex; clean /doctor/ and /specialty/x/
     * archives stay indexable.
     */

    public static function robots($robots) {

        if (self::is_listing() && self::has_params()) {

            $robots['noindex'] = true;

            $robots['follow']  = true;

            unset($robots['index']);

        }

        return $robots;

    }



    /* ================================================================== */

    /* Admin status page — wp-admin/admin-post.php?action=hj_ar_status     */

    /* ================================================================== */



    public static function admin_status() {

        if (!current_user_can('manage_options')) {

            wp_die('دسترسی غیرمجاز', '', array('response' => 403));

        }

        global $wpdb;

        $lines = array();

        $ok = function ($label, $cond) use (&$lines) {

            $lines[] = sprintf('%-46s %s', $label, $cond ? 'OK' : 'MISSING');

        };

        $val = function ($label, $value) use (&$lines) {

            $lines[] = sprintf('%-46s %s', $label, (string) $value);

        };

        $lines[] = 'Hamyar Doctors - archive status (section 8)';

        $lines[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';

        $lines[] = '';

        $val('Plugin version', defined('HJ_DOCTORS_VERSION') ? HJ_DOCTORS_VERSION : '?');

        $ok('Post type "doctor" registered', post_type_exists('doctor'));

        $val('Archive URL', (string) get_post_type_archive_link('doctor'));

        foreach (self::taxes() as $tax) {

            $ok("Taxonomy {$tax} registered", taxonomy_exists($tax));

            $n = get_terms(array('taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'count'));

            $val("  terms in {$tax}", is_wp_error($n) ? 'error' : (int) $n);

        }

        $lines[] = '';

        $val('per_page (hj_ar_per_page)', self::per_page());

        $val('use_facetwp (hj_ar_use_facetwp)', self::use_facetwp() ? 'true' : 'false');

        $val('show_basic_filters', apply_filters('hj_ar_show_basic_filters', true) ? 'true' : 'false');

        $val('sort_options (hj_ar_sort_options)', implode(', ', array_keys(self::sort_options())));

        $val('default_sort (hj_ar_default_sort)', (string) apply_filters('hj_ar_default_sort', 'name'));

        $hidden_vals = self::hidden_status_values();

        $val('hidden_status_values', $hidden_vals ? implode(', ', $hidden_vals) : '(none)');

        $ok('filter hj_doctor_rating_stats attached', false !== has_filter('hj_doctor_rating_stats'));

        $ok('filter hj_ar_use_facetwp attached', false !== has_filter('hj_ar_use_facetwp'));

        $ok('filter hj_ar_show_basic_filters attached', false !== has_filter('hj_ar_show_basic_filters'));

        $ok('filter hj_doctor_card_extra_html attached', false !== has_filter('hj_doctor_card_extra_html'));

        $ok('action hj_doctor_archive_filters attached', false !== has_action('hj_doctor_archive_filters'));

        $ok('action hj_ar_apply_sort attached', false !== has_action('hj_ar_apply_sort'));

        $lines[] = '';

        $pc = wp_count_posts('doctor');

        foreach (array('publish', 'draft', 'pending', 'private', 'trash') as $st) {

            $val("posts {$st}", isset($pc->$st) ? (int) $pc->$st : 0);

        }

        $rows = $wpdb->get_results(

            $wpdb->prepare(

                "SELECT pm.meta_key, pm.meta_value, COUNT(*) AS c
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = %s AND pm.meta_key IN ('profile_hidden', 'profile_status')
                 GROUP BY pm.meta_key, pm.meta_value
                 ORDER BY pm.meta_key, pm.meta_value",

                'doctor'

            )

        );

        if ($rows) {

            foreach ($rows as $r) {

                $val('meta ' . $r->meta_key . ' = ' . ('' === $r->meta_value ? "''" : $r->meta_value), (int) $r->c);

            }

        } else {

            $val('meta profile_hidden/profile_status', 'none');

        }

        $pub = new WP_Query(array(

            'post_type'      => 'doctor',

            'post_status'    => 'publish',

            'posts_per_page' => 1,

            'fields'         => 'ids',

            'meta_query'     => self::visibility_meta_query(),

        ));

        $val('public doctors (archive meta query)', (int) $pub->found_posts);

        if (get_post(114)) {

            $val('hj_dp_is_public(114)', hj_dp_is_public(114) ? 'true' : 'false');

        }

        $lines[] = '';

        $lines[] = 'Status page: wp-admin/admin-post.php?action=hj_ar_status';

        nocache_headers();

        header('Content-Type: text/plain; charset=utf-8');

        echo esc_html(implode("\n", $lines));

        exit;

    }



    /* ================================================================== */

    /* Rendering helpers                                                   */

    /* ================================================================== */



    /** Latin digits -> Persian digits for display. */

    private static function fa($x) {

        return str_replace(

            array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),

            array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'),

            (string) $x

        );

    }



    /** Persian/Arabic digits -> Latin (helper of section 1). */

    private static function en($x) {

        return function_exists('hj_to_en_digits') ? hj_to_en_digits($x) : (string) $x;

    }



    /** Read a profile field without a hard ACF dependency. */

    private static function field($id, $key) {

        if (function_exists('hj_doctors_field')) {

            $v = hj_doctors_field($key, $id);

            return is_array($v) ? '' : (string) $v;

        }

        return (string) get_post_meta($id, $key, true);

    }



    private static function terms_of($id, $tax) {

        if (!taxonomy_exists($tax)) {

            return array();

        }

        $terms = wp_get_object_terms($id, $tax, array('fields' => 'all'));

        return is_wp_error($terms) ? array() : $terms;

    }



    private static function term_names($id, $tax) {

        $names = array();

        foreach (self::terms_of($id, $tax) as $t) {

            $names[] = $t->name;

        }

        return $names;

    }



    /** Heading parts for the current listing. */

    private static function heading() {

        $base = array('kicker' => '', 'title' => 'آرشیو پزشکان', 'back' => '');

        if (is_post_type_archive('doctor')) {

            return $base;

        }

        $term = get_queried_object();

        if ($term instanceof WP_Term) {

            $kickers = array(

                'specialty'      => 'رشتهٔ',

                'specialty_type' => 'نوع تخصص',

                'province'       => 'استان',

                'city'           => 'شهر',

            );

            return array(

                'kicker' => isset($kickers[$term->taxonomy]) ? $kickers[$term->taxonomy] : '',

                'title'  => $term->name,

                'back'   => (string) get_post_type_archive_link('doctor'),

            );

        }

        return $base;

    }



    /** Specialty chips above the filter bar. */

    private static function chips_html() {

        $terms = get_terms(array(

            'taxonomy' => 'specialty',

            'hide_empty' => true,

            'orderby'    => 'name',

            'order'      => 'ASC',

            'number'     => 40,

        ));

        if (is_wp_error($terms) || empty($terms)) {

            return '';

        }

        $active = self::qparam('hj_specialty');

        $qo = get_queried_object();

        if ($qo instanceof WP_Term && 'specialty' === $qo->taxonomy) {

            $active = $qo->slug;

        }

        $base       = (string) get_post_type_archive_link('doctor');

        $all_active = ('' === $active) && is_post_type_archive('doctor');

        $h = '<nav class="hj-ar__chips" aria-label="دسته‌بندی بر اساس تخصص"><ul>';

        $h .= '<li><a class="hj-ar__chip' . ($all_active ? ' is-active' : '') . '" href="' . esc_url($base) . '">همهٔ تخصص‌ها</a></li>';

        foreach ($terms as $t) {

            $cls = ($t->slug === $active) ? 'hj-ar__chip is-active' : 'hj-ar__chip';

            $h .= '<li><a class="' . esc_attr($cls) . '" href="' . esc_url(get_term_link($t)) . '">'

                . esc_html($t->name)

                . ' <span class="hj-ar__chip-count">' . esc_html(self::fa($t->count)) . '</span></a></li>';

        }

        $h .= '</ul></nav>';

        return $h;

    }



    private static function select_html($name, $label, $terms, $current, $placeholder) {

        if (is_wp_error($terms)) {

            $terms = array();

        }

        $id = 'hj-ar-' . $name;

        $h = '<div class="hj-ar__field"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>'

            . '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">'

            . '<option value="">' . esc_html($placeholder) . '</option>';

        foreach ($terms as $t) {

            $h .= '<option value="' . esc_attr($t->slug) . '"' . selected($t->slug, $current, false) . '>' . esc_html($t->name) . '</option>';

        }

        $h .= '</select></div>';

        return $h;

    }



    /**
     * Basic no-JS GET filter bar. Section 10 hides it through
     * hj_ar_show_basic_filters and prints FacetWP at
     * hj_doctor_archive_filters instead.
     */

    private static function filters_html() {

        if (!apply_filters('hj_ar_show_basic_filters', true)) {

            return '';

        }

        $params = array('hj_q', 'hj_specialty', 'hj_province', 'hj_city', 'hj_type', 'hj_sort', 'paged');

        $action = remove_query_arg($params);

        $q    = self::qtext();

        $spec = self::qparam('hj_specialty');

        $prov = self::qparam('hj_province');

        $city = self::qparam('hj_city');

        $sort = self::current_sort();

        $specialties = get_terms(array('taxonomy' => 'specialty', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC'));

        $provinces   = get_terms(array('taxonomy' => 'province', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC'));

        // City list is populated for the chosen province after "apply".

        $city_args = array('taxonomy' => 'city', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC');

        if ('' !== $prov) {

            $pterm = get_term_by('slug', $prov, 'province');

            if ($pterm && !is_wp_error($pterm)) {

                $city_args['meta_query'] = array(

                    array('key' => 'hj_province_id', 'value' => (string) (int) $pterm->term_id, 'compare' => '='),

                );

            }

        }

        $cities = get_terms($city_args);

        $h = '<form class="hj-ar__filters" method="get" action="' . esc_url($action) . '">';

        $h .= '<div class="hj-ar__field"><label for="hj-ar-hj_q">جست‌وجوی نام پزشک</label>'

            . '<input type="search" id="hj-ar-hj_q" name="hj_q" value="' . esc_attr($q) . '" placeholder="نام فارسی پزشک" maxlength="60"></div>';

        $h .= self::select_html('hj_specialty', 'تخصص', $specialties, $spec, 'همهٔ تخصص‌ها');

        $h .= self::select_html('hj_province', 'استان', $provinces, $prov, 'همهٔ استان‌ها');

        $h .= self::select_html('hj_city', 'شهر', $cities, $city, 'همهٔ شهرها');

        $h .= '<div class="hj-ar__field"><label for="hj-ar-hj_sort">مرتب‌سازی</label>'

            . '<select id="hj-ar-hj_sort" name="hj_sort">';

        foreach (self::sort_options() as $k => $label) {

            $h .= '<option value="' . esc_attr($k) . '"' . selected($k, $sort, false) . '>' . esc_html($label) . '</option>';

        }

        $h .= '</select></div>';

        $h .= '<div class="hj-ar__actions"><button type="submit" class="hj-ar__submit">اعمال فیلتر</button>';

        if (self::has_filters()) {

            $h .= '<a class="hj-ar__clear" href="' . esc_url($action) . '">پاک کردن فیلترها</a>';

        }

        $h .= '</div></form>';

        return $h;

    }



    /**
     * Rating line — only with at least hj_pr_min_votes approved votes and
     * stats supplied through the hj_doctor_rating_stats filter (section 9).
     */

    private static function rating_html($id) {

        $stats = apply_filters('hj_doctor_rating_stats', null, $id);

        if (!is_array($stats)) {

            return '';

        }

        $min      = max(1, (int) apply_filters('hj_pr_min_votes', 5));

        $approved = isset($stats['approved']) ? (int) $stats['approved'] : 0;

        $avg      = isset($stats['avg']) ? (float) $stats['avg'] : 0.0;

        if ($approved < $min || $avg <= 0) {

            return '';

        }

        $avg_fa = str_replace('.', '٫', self::fa(number_format($avg, 1)));

        return '<span class="hj-doc-card__rating">★ ' . esc_html($avg_fa)

            . ' <span class="hj-doc-card__votes">(' . esc_html(self::fa($approved)) . ' رأی)</span></span>';

    }



    /* ================================================================== */

    /* Card + archive                                                      */

    /* ================================================================== */



    /**
     * One doctor card. Empty fields are never printed; digits are Persian.
     *
     * @param int $id Post ID.
     * @return string HTML (empty string when the profile is not public).
     */

    public static function card_html($id) {

        $id = (int) $id;

        if (!$id || !self::is_public($id)) {

            return '';

        }

        $name_fa = trim(self::field($id, 'name_fa'));

        if ('' === $name_fa) {

            $name_fa = (string) get_the_title($id);

        }

        $name_en = trim(self::field($id, 'name_en'));

        $spec_fa = trim(self::field($id, 'specialty_fa'));

        $photo = function_exists('hj_doctors_photo_url') ? (string) hj_doctors_photo_url($id) : '';

        $permalink = (string) get_permalink($id);

        $verified = ('published' === (string) get_post_meta($id, 'profile_status', true));

        $spec_terms = self::terms_of($id, 'specialty');

        if ($spec_terms) {

            $parts = array();

            foreach ($spec_terms as $t) {

                $parts[] = '<a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . '</a>';

            }

            $spec_html = implode('، ', $parts);

        } else {

            $spec_html = ('' !== $spec_fa) ? esc_html($spec_fa) : '';

        }

        $meta_parts = array();

        $cities = self::term_names($id, 'city');

        if ($cities) {

            $meta_parts[] = '<span class="hj-doc-card__city">شهر: ' . esc_html(implode('، ', $cities)) . '</span>';

        }

        $provinces = self::term_names($id, 'province');

        if ($provinces) {

            $meta_parts[] = '<span class="hj-doc-card__province">استان: ' . esc_html(implode('، ', $provinces)) . '</span>';

        }

        $exp = trim(self::en(self::field($id, 'experience_years')));

        if ('' !== $exp) {

            $meta_parts[] = '<span class="hj-doc-card__exp">سابقه: ' . esc_html(self::fa($exp)) . ' سال</span>';

        }

        $rating = self::rating_html($id);

        $extra = apply_filters('hj_doctor_card_extra_html', '', $id);

        ob_start();

        ?>

        <article class="hj-doc-card">

            <a class="hj-doc-card__photo" href="<?php echo esc_url($permalink); ?>" tabindex="-1" aria-hidden="true">

                <?php if ('' !== $photo) : ?>

                    <img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr('عکس ' . $name_fa); ?>" loading="lazy">

                <?php endif; ?>

            </a>

            <div class="hj-doc-card__body">

                <h2 class="hj-doc-card__name">

                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($name_fa); ?></a>

                    <?php if ($verified) : ?>

                        <span class="hj-doc-card__badge" title="پروفایل توسط مدیر تأیید شده است">✓ تأییدشده</span>

                    <?php endif; ?>

                </h2>

                <?php if ('' !== $name_en) : ?>

                    <p class="hj-doc-card__name-en" dir="ltr"><?php echo esc_html($name_en); ?></p>

                <?php endif; ?>

                <?php if ('' !== $spec_html) : ?>

                    <p class="hj-doc-card__spec"><?php echo $spec_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped term names above. ?></p>

                <?php endif; ?>

                <?php if ($meta_parts) : ?>

                    <p class="hj-doc-card__meta"><?php echo implode(' ', $meta_parts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts are escaped above. ?></p>

                <?php endif; ?>

                <?php if ('' !== $rating) : ?>

                    <p class="hj-doc-card__rating-line"><?php echo $rating; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside rating_html(). ?></p>

                <?php endif; ?>

                <?php if (is_string($extra) && '' !== $extra) : ?>

                    <div class="hj-doc-card__extra"><?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Provided by the hj_doctor_card_extra_html filter. ?></div>

                <?php endif; ?>

                <a class="hj-doc-card__btn" href="<?php echo esc_url($permalink); ?>">مشاهدهٔ پروفایل</a>

            </div>

        </article>

        <?php

        return ob_get_clean();

    }



    private static function grid_html() {

        if (!have_posts()) {

            return '<div class="hj-ar__empty"><p>پزشکی با این فیلترها یافت نشد.</p></div>';

        }

        $h = '<div class="hj-ar__grid">';

        while (have_posts()) {

            the_post();

            $id = get_the_ID();

            // Re-check visibility at display time (belt and braces).

            if (!hj_dp_is_public($id)) {

                continue;

            }

            $h .= self::card_html($id);

        }

        $h .= '</div>';

        ob_start();

        the_posts_pagination(array(

            'prev_text' => '‹ قبلی',

            'next_text' => 'بعدی ›',

            'mid_size'  => 1,

        ));

        $pag = trim(ob_get_clean());

        if ('' !== $pag) {

            $h .= '<div class="hj-ar__pagination">' . $pag . '</div>';

        }

        return $h;

    }



    /**
     * Full listing page (header + content + footer) as one HTML string.
     *
     * @return string
     */

    public static function archive_html() {

        global $wp_query;

        $found = ($wp_query instanceof WP_Query && isset($wp_query->found_posts)) ? (int) $wp_query->found_posts : 0;

        $head = self::heading();

        ob_start();

        get_header();

        ?>

        <div class="container hj-ar-wrap">

            <main id="primary" class="hj-ar">

                <header class="hj-ar__header">

                    <?php if ('' !== $head['back']) : ?>

                        <a class="hj-ar__back" href="<?php echo esc_url($head['back']); ?>">← همهٔ پزشکان</a>

                    <?php endif; ?>

                    <?php if ('' !== $head['kicker']) : ?>

                        <p class="hj-ar__kicker"><?php echo esc_html($head['kicker']); ?></p>

                    <?php endif; ?>

                    <h1 class="hj-ar__title"><?php echo esc_html($head['title']); ?></h1>

                    <p class="hj-ar__count"><?php echo esc_html(self::fa($found) . ' پزشک'); ?></p>

                </header>

                <?php echo self::chips_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>

                <div class="hj-ar__filterbar">

                    <?php do_action('hj_doctor_archive_filters'); ?>

                    <?php echo self::filters_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>

                </div>

                <?php echo self::grid_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>

            </main>

        </div>

        <?php

        get_footer();

        return ob_get_clean();

    }

}

endif;

HJ_Doctors_Archive_Render::init();

if (!function_exists('hj_doctor_archive_html')) {

    /**
     * Full doctor listing page (archive + specialty/province/city/type).
     *
     * @return string
     */

    function hj_doctor_archive_html() {

        return HJ_Doctors_Archive_Render::archive_html();

    }

}

if (!function_exists('hj_doctor_card_html')) {

    /**
     * HTML of one doctor card; empty when the profile is not public.
     *
     * @param int $id Post ID.
     * @return string
     */

    function hj_doctor_card_html($id) {

        return HJ_Doctors_Archive_Render::card_html($id);

    }

}

if (!function_exists('hj_dp_is_public')) {

    /**
     * Whether a doctor profile is publicly visible right now.
     *
     * @param int $post_id Post ID; 0 means the current post.
     * @return bool
     */

    function hj_dp_is_public($post_id = 0) {

        return HJ_Doctors_Archive_Render::is_public($post_id);

    }

}

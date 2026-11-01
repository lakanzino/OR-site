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



    private static function ensure_assets() {

        if (self::$css_done) {

            return;

        }

        self::$css_done = true;

        wp_register_style('hj-ar-css', hj_doctors_url('assets/css/archive.css'), array(), HJ_DOCTORS_VERSION);

        wp_enqueue_style('hj-ar-css');

    }



    public static function enqueue() {

        if (self::is_listing()) {

            self::ensure_assets();

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
    /* Rendering helpers                                                   */
    /* ================================================================== */



    private static function archive_url() {

        $u = get_post_type_archive_link('doctor');

        return (is_string($u) && '' !== $u) ? $u : home_url('/');

    }



    private static function terms_for($tax, $args = array()) {

        if (!taxonomy_exists($tax)) {

            return array();

        }

        $terms = get_terms(array_merge(array(

            'taxonomy'   => $tax,

            'hide_empty' => true,

            'orderby'    => 'name',

            'order'      => 'ASC',

        ), $args));

        return is_wp_error($terms) ? array() : $terms;

    }



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



    /* ================================================================== */
    /* Doctor card                                                         */
    /* ================================================================== */



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

        $fill   = min(100, max(0, (int) round(($avg / 5) * 100)));

        $avg_fa = str_replace('.', '٫', self::fa(number_format($avg, 1)));

        return '<span class="hj-ar-stars" style="--hj-fill: ' . $fill . '%" aria-hidden="true"><i>★★★★★</i></span>'

            . '<b>' . esc_html($avg_fa) . '</b>'

            . '<span class="hj-ar-card__muted">(' . esc_html(self::fa($approved)) . ' رأی)</span>';

    }



    /**
     * One doctor card. Empty fields are never printed; digits are Persian.
     *
     * @param int $id Post ID.
     * @return string HTML (empty string when the profile is not public).
     */

    public static function card($id) {

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

                $lnk = get_term_link($t);

                if (is_wp_error($lnk)) {

                    $parts[] = esc_html($t->name);

                } else {

                    $parts[] = '<a href="' . esc_url($lnk) . '">' . esc_html($t->name) . '</a>';

                }

            }

            $spec_html = implode('، ', $parts);

        } else {

            $spec_html = ('' !== $spec_fa) ? esc_html($spec_fa) : '';

        }

        $meta = array();

        $cities = self::term_names($id, 'city');

        if ($cities) {

            $meta[] = '<li>شهر: ' . esc_html(implode('، ', $cities)) . '</li>';

        }

        $provinces = self::term_names($id, 'province');

        if ($provinces) {

            $meta[] = '<li>استان: ' . esc_html(implode('، ', $provinces)) . '</li>';

        }

        $exp = trim(self::en(self::field($id, 'experience_years')));

        if ('' !== $exp) {

            $meta[] = '<li>سابقه: ' . esc_html(self::fa($exp)) . ' سال</li>';

        }

        if ($verified) {

            $meta[] = '<li class="hj-ar-card__verified">✓ تأییدشده</li>';

        }

        $rate  = self::rating_html($id);

        $extra = apply_filters('hj_doctor_card_extra_html', '', $id);

        ob_start();

        ?>

        <article class="hj-ar-card">

            <div class="hj-ar-card__top">

                <?php if ('' !== $photo) : ?>

                    <a class="hj-ar-card__photo" href="<?php echo esc_url($permalink); ?>" tabindex="-1" aria-hidden="true"><img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr('عکس ' . $name_fa); ?>" loading="lazy"></a>

                <?php endif; ?>

                <div class="hj-ar-card__body">

                    <h2 class="hj-ar-card__name"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($name_fa); ?></a></h2>

                    <?php if ('' !== $name_en) : ?>

                        <p class="hj-ar-card__name-en" dir="ltr"><?php echo esc_html($name_en); ?></p>

                    <?php endif; ?>

                    <?php if ('' !== $spec_html) : ?>

                        <p class="hj-ar-card__spec"><?php echo $spec_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped term names above. ?></p>

                    <?php endif; ?>

                    <?php if ($meta) : ?>

                        <ul class="hj-ar-card__meta"><?php echo implode('', $meta); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Items escaped above. ?></ul>

                    <?php endif; ?>

                    <?php if ('' !== $rate) : ?>

                        <div class="hj-ar-card__rate"><?php echo $rate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside rating_html(). ?></div>

                    <?php endif; ?>

                    <?php if (is_string($extra) && '' !== $extra) : ?>

                        <div class="hj-ar-card__extra"><?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Provided by the hj_doctor_card_extra_html filter. ?></div>

                    <?php endif; ?>

                </div>

            </div>

            <a class="hj-ar-card__cta" href="<?php echo esc_url($permalink); ?>">مشاهدهٔ پروفایل</a>

        </article>

        <?php

        return ob_get_clean();

    }



    /* ================================================================== */

    /* Filter bar (fallback until FacetWP, section 10)                     */

    /* ================================================================== */



    private static function selected() {

        $sel = array();

        $q   = get_queried_object();

        foreach (self::filter_map() as $param => $tax) {

            $v = self::qparam($param);

            if ($v === '' && $q instanceof WP_Term && $q->taxonomy === $tax) {

                $v = $q->slug;

            }

            $sel[$param] = $v;

        }

        if ($sel['hj_city'] !== '' && $sel['hj_province'] === '' && function_exists('hj_get_city_province_id')) {

            $c = get_term_by('slug', $sel['hj_city'], 'city');

            if ($c && !is_wp_error($c)) {

                $pid = (int) hj_get_city_province_id($c->term_id);

                if ($pid) {

                    $p = get_term($pid, 'province');

                    if ($p && !is_wp_error($p)) {

                        $sel['hj_province'] = $p->slug;

                    }

                }

            }

        }

        return $sel;

    }



    private static function select($name, $label, $options, $selected, $placeholder) {

        $h  = '<div class="hj-ar__field"><label for="hj-ar-' . esc_attr($name) . '">' . esc_html($label) . '</label>';

        $h .= '<select id="hj-ar-' . esc_attr($name) . '" name="' . esc_attr($name) . '">';

        $h .= '<option value="">' . esc_html($placeholder) . '</option>';

        foreach ($options as $val => $text) {

            $h .= '<option value="' . esc_attr($val) . '"' . selected((string) $selected, (string) $val, false) . '>' . esc_html($text) . '</option>';

        }

        return $h . '</select></div>';

    }



    private static function terms_to_options($terms) {

        $o = array();

        foreach ($terms as $t) {

            $o[$t->slug] = $t->name;

        }

        return $o;

    }



    private static function filter_bar() {

        $sel = self::selected();



        $h  = '<form class="hj-ar__filters" method="get" action="' . esc_url(self::archive_url()) . '" role="search" aria-label="فیلتر پزشکان">';

        $h .= '<div class="hj-ar__field hj-ar__field--q"><label for="hj-ar-q">جست‌وجوی نام</label>'

            . '<input id="hj-ar-q" type="search" name="hj_q" value="' . esc_attr(self::qtext()) . '" maxlength="60" autocomplete="off"></div>';



        $h .= self::select('hj_specialty', 'تخصص', self::terms_to_options(self::terms_for('specialty')), $sel['hj_specialty'], 'همه');



        $types = self::terms_for('specialty_type');

        if ($types) {

            $h .= self::select('hj_type', 'نوع تخصص', self::terms_to_options($types), $sel['hj_type'], 'همه');

        }



        $provs = self::terms_for('province');

        if ($provs) {

            $h .= self::select('hj_province', 'استان', self::terms_to_options($provs), $sel['hj_province'], 'همه');

        }



        if ($sel['hj_province'] !== '') {

            $p = get_term_by('slug', $sel['hj_province'], 'province');

            if ($p && !is_wp_error($p)) {

                $cities = self::terms_for('city', array(

                    'meta_query' => array(array('key' => 'hj_province_id', 'value' => (int) $p->term_id, 'type' => 'NUMERIC')),

                ));

                if ($cities) {

                    $h .= self::select('hj_city', 'شهر', self::terms_to_options($cities), $sel['hj_city'], 'همه');

                }

            }

        }



        $h .= self::select('hj_sort', 'مرتب‌سازی', self::sort_options(), self::current_sort(), 'پیش‌فرض');



        $h .= '<div class="hj-ar__actions"><button type="submit" class="hj-ar__btn">اعمال فیلتر</button>';

        if (self::has_filters()) {

            $h .= '<a class="hj-ar__reset" href="' . esc_url(self::archive_url()) . '">پاک کردن</a>';

        }

        $h .= '</div></form>';

        return $h;

    }



    /* ================================================================== */

    /* Pagination                                                          */

    /* ================================================================== */



    private static function pagination($paged, $max) {

        if ($max < 2) {

            return '';

        }

        $paged = max(1, (int) $paged);

        $pages = array();

        for ($i = 1; $i <= $max; $i++) {

            if ($i === 1 || $i === $max || abs($i - $paged) <= 2) {

                $pages[] = $i;

            }

        }

        $h  = '<nav class="hj-ar__pager" aria-label="صفحه‌بندی"><ul>';

        if ($paged > 1) {

            $h .= '<li><a href="' . esc_url(get_pagenum_link($paged - 1)) . '" rel="prev">قبلی</a></li>';

        }

        $last = 0;

        foreach ($pages as $p) {

            if ($last && $p - $last > 1) {

                $h .= '<li class="hj-ar__gap" aria-hidden="true">…</li>';

            }

            if ($p === $paged) {

                $h .= '<li><span class="is-current" aria-current="page">' . esc_html(self::fa($p)) . '</span></li>';

            } else {

                $h .= '<li><a href="' . esc_url(get_pagenum_link($p)) . '">' . esc_html(self::fa($p)) . '</a></li>';

            }

            $last = $p;

        }

        if ($paged < $max) {

            $h .= '<li><a href="' . esc_url(get_pagenum_link($paged + 1)) . '" rel="next">بعدی</a></li>';

        }

        return $h . '</ul></nav>';

    }



    /* ================================================================== */

    /* Page                                                                */

    /* ================================================================== */



    private static function heading() {

        $q = get_queried_object();

        if ($q instanceof WP_Term && !is_wp_error($q)) {

            switch ($q->taxonomy) {

                case 'province':

                    return 'پزشکان استان ' . $q->name;

                case 'city':

                    return 'پزشکان شهر ' . $q->name;

                default:

                    return 'پزشکان ' . $q->name;

            }

        }

        return 'پزشکان و جراحان';

    }



    public static function render_page() {

        self::ensure_assets();

        global $wp_query;



        $q       = get_queried_object();

        $is_term = ($q instanceof WP_Term && !is_wp_error($q));

        $total   = (int) $wp_query->found_posts;

        $paged   = max(1, (int) get_query_var('paged'));

        $fwp     = self::use_facetwp();



        $chips = self::terms_for('specialty');



        ob_start();

        ?>

<section class="hj-ar" dir="rtl" lang="fa">



  <nav class="hj-ar__crumbs" aria-label="مسیر صفحه">

    <a href="<?php echo esc_url(home_url('/')); ?>">خانه</a>

    <span aria-hidden="true">‹</span>

    <?php if ($is_term) : ?>

      <a href="<?php echo esc_url(self::archive_url()); ?>">پزشکان</a>

      <span aria-hidden="true">‹</span>

      <span><?php echo esc_html($q->name); ?></span>

    <?php else : ?>

      <span>پزشکان</span>

    <?php endif; ?>

  </nav>



  <header class="hj-ar__head">

    <h1 class="hj-ar__title"><?php echo esc_html(self::heading()); ?></h1>

    <?php if ($total > 0) : ?>

      <p class="hj-ar__count"><?php echo esc_html(self::fa($total)); ?> پزشک</p>

    <?php endif; ?>

    <?php

    if ($is_term) {

        $desc = term_description();

        if (is_string($desc) && trim(wp_strip_all_tags($desc)) !== '') {

            echo '<div class="hj-ar__desc">' . wp_kses_post($desc) . '</div>';

        }

    }

    ?>

  </header>



  <?php if ($chips) : ?>

  <nav class="hj-ar__chips" aria-label="تخصص‌ها">

    <a class="hj-ar__chip<?php echo (!$is_term || $q->taxonomy !== 'specialty') ? ' is-active' : ''; ?>" href="<?php echo esc_url(self::archive_url()); ?>">همه</a>

    <?php foreach ($chips as $t) :

        $lnk = get_term_link($t);

        if (is_wp_error($lnk)) {

            continue;

        }

        $active = ($is_term && $q->taxonomy === 'specialty' && (int) $q->term_id === (int) $t->term_id);

        ?>

      <a class="hj-ar__chip<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url($lnk); ?>"><?php echo esc_html($t->name); ?></a>

    <?php endforeach; ?>

  </nav>

  <?php endif; ?>



  <?php

  ob_start();

  do_action('hj_doctor_archive_filters');

  $hook_filters = trim((string) ob_get_clean());

  if ($hook_filters !== '') {

      echo '<div class="hj-ar__facets">' . $hook_filters . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- hook output (section 10)

  }

  if (apply_filters('hj_ar_show_basic_filters', true)) {

      echo self::filter_bar(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside

  }

  ?>



  <div class="hj-ar__results<?php echo $fwp ? ' facetwp-template' : ''; ?>" id="hj-ar-results">

    <?php

    $shown = 0;

    if (have_posts()) {

        echo '<div class="hj-ar__grid">';

        while (have_posts()) {

            the_post();

            $pid = get_the_ID();

            if (!self::is_public($pid)) {

                continue;

            }

            echo self::card($pid); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside

            $shown++;

        }

        echo '</div>';

    }

    if ($shown === 0) {

        echo '<div class="hj-ar__empty"><p>' . (self::has_filters() ? 'پزشکی با این مشخصات پیدا نشد.' : 'هنوز پزشکی در این بخش ثبت نشده است.') . '</p>';

        if (self::has_filters() || $is_term) {

            echo '<a class="hj-ar__btn" href="' . esc_url(self::archive_url()) . '">نمایش همهٔ پزشکان</a>';

        }

        echo '</div>';

    }

    echo self::pagination($paged, (int) $wp_query->max_num_pages); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside

    ?>

  </div>

</section>

        <?php

        return ob_get_clean();

    }



    /* ================================================================== */

    /* Admin-only status page                                              */

    /* /wp-admin/admin-post.php?action=hj_ar_status                        */

    /* ================================================================== */



    public static function admin_status() {

        if (!current_user_can('manage_options')) {

            wp_die('دسترسی غیرمجاز', '', array('response' => 403));

        }

        $L = array();



        $L[] = 'Child templates:';

        foreach (array('archive-doctor.php', 'taxonomy-specialty.php', 'taxonomy-province.php', 'taxonomy-city.php', 'taxonomy-specialty_type.php') as $f) {

            $p   = locate_template($f);

            $L[] = sprintf('  %-30s %s', $f, $p ? 'OK' : 'NOT FOUND');

        }

        $L[] = 'Archive URL: ' . self::archive_url();

        $L[] = 'Per page: ' . self::per_page() . '   | default sort: ' . self::current_sort();

        $L[] = 'FacetWP active: ' . (defined('FACETWP_VERSION') ? 'yes' : 'no') . '   | hj_ar_use_facetwp: ' . (self::use_facetwp() ? 'on' : 'off');

        $L[] = 'Hidden profile_status values: ' . implode(', ', self::hidden_status_values());

        $L[] = '';



        $published = get_posts(array('post_type' => 'doctor', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids'));

        $by_query  = get_posts(array(

            'post_type'   => 'doctor', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',

            'meta_query'  => self::visibility_meta_query(),

        ));

        $by_func = array();

        foreach ($published as $id) {

            if (self::is_public($id)) {

                $by_func[] = $id;

            }

        }

        $L[] = 'Published doctors: ' . count($published);

        $L[] = 'Public by archive query: ' . count($by_query);

        $L[] = 'Public by hj_dp_is_public(): ' . count($by_func) . (function_exists('hj_dp_is_public') ? '' : '  (function missing; fallback = post_status)');

        $diff = array_merge(array_diff($by_query, $by_func), array_diff($by_func, $by_query));

        $L[]  = 'Mismatch IDs (query vs function): ' . ($diff ? implode(', ', $diff) : 'none');

        $L[]  = '';



        $expected = array('orthopedics', 'urology', 'ophthalmology', 'cardiology', 'neurosurgery', 'gynecology', 'ent', 'dermatology', 'general-surgery', 'cosmetic', 'anesthesiology');

        $L[] = 'Specialty terms (slug → count):';

        foreach ($expected as $slug) {

            $t   = get_term_by('slug', $slug, 'specialty');

            $L[] = sprintf('  %-18s %s', $slug, ($t && !is_wp_error($t)) ? (int) $t->count : 'MISSING');

        }

        foreach (array('province', 'city', 'specialty_type') as $tax) {

            $c   = wp_count_terms(array('taxonomy' => $tax, 'hide_empty' => false));

            $L[] = 'Terms in ' . $tax . ': ' . (is_wp_error($c) ? 'n/a' : (int) $c);

        }



        nocache_headers();

        header('Content-Type: text/plain; charset=utf-8');

        echo esc_html(implode("\n", $L));

        exit;

    }

}



HJ_Doctors_Archive_Render::init();



/** Full archive page HTML (already escaped). Uses the main query. */

if (!function_exists('hj_doctor_archive_html')) {

    function hj_doctor_archive_html() {

        return HJ_Doctors_Archive_Render::render_page();

    }

}



/** One doctor card (already escaped). */

if (!function_exists('hj_doctor_card_html')) {

    function hj_doctor_card_html($id) {

        return HJ_Doctors_Archive_Render::card($id);

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



endif;

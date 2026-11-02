<?php

/**

 * Hamyar Doctors — Section 12a: [hj_doctor_card] shortcode + standalone widget card renderer.

 *

 * Usage: [hj_doctor_card id="114"]

 * [hj_doctor_card id="114" layout="auto|vertical|horizontal" en="1|0"]

 * If id is omitted inside a single doctor page, the current doctor is used.

 *

 * Data source: HJ_Doctors_REST::build_item() (allow-list, same as the public REST API).

 * Constants (optional): define('HJ_WD_DISABLED', true); // disables embed endpoints (embed.php)

 */

if (!defined('ABSPATH')) { exit; }



if (!class_exists('HJ_Doctors_Shortcodes')) :



class HJ_Doctors_Shortcodes {



    const STAR = '<svg class="hj-wd-star" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M10 1.6l2.5 5.3 5.8.8-4.2 4.1 1 5.8L10 14.8l-5.1 2.8 1-5.8L1.7 7.7l5.8-.8z"/></svg>';

    const PIN = '<svg class="hj-wd-pin" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M10 18s6-5.2 6-10a6 6 0 10-12 0c0 4.8 6 10 6 10z"/><circle cx="10" cy="8" r="2.2"/></svg>';

    const CHEV = '<svg class="hj-wd-chev" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M12.5 4.5L7 10l5.5 5.5"/></svg>';



    private static $booted = false;



    public static function boot() {

        if (self::$booted) { return; }

        self::$booted = true;

        add_shortcode('hj_doctor_card', array(__CLASS__, 'shortcode'));

        add_action('wp_enqueue_scripts', array(__CLASS__, 'maybe_enqueue'), 20);

    }



    /* ------------------------------------------------------------------

     * Paths / assets

     * ------------------------------------------------------------------ */

    public static function base_url() {

        if (defined('HJ_DOCTORS_URL')) { return trailingslashit(HJ_DOCTORS_URL); }

        return trailingslashit(plugin_dir_url(dirname(dirname(__FILE__)) . '/hamyar-doctors.php'));

    }



    public static function base_path() {

        if (defined('HJ_DOCTORS_PATH')) { return trailingslashit(HJ_DOCTORS_PATH); }

        return trailingslashit(dirname(dirname(__FILE__)));

    }



    public static function plugin_ver() {

        return defined('HJ_DOCTORS_VERSION') ? (string) HJ_DOCTORS_VERSION : '1';

    }



    public static function asset_ver($rel) {

        $f = self::base_path() . ltrim($rel, '/');

        $m = @filemtime($f); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        return self::plugin_ver() . ($m ? '.' . $m : '');

    }



    public static function css_url() {

        return self::base_url() . 'assets/css/widget.css';

    }



    public static function ensure_style() {

        if (!wp_style_is('hj-widget', 'registered')) {

            wp_register_style('hj-widget', self::css_url(), array(), self::asset_ver('assets/css/widget.css'));

        }

        wp_enqueue_style('hj-widget');

    }



    public static function maybe_enqueue() {

        if (!is_singular()) { return; }

        $p = get_post();

        if ($p && has_shortcode((string) $p->post_content, 'hj_doctor_card')) {

            self::ensure_style();

        }

    }



    /* ------------------------------------------------------------------

     * Shortcode

     * ------------------------------------------------------------------ */

    public static function shortcode($atts) {

        $a = shortcode_atts(array(

            'id' => '',

            'layout' => 'auto',

            'en' => '1',

        ), (array) $atts, 'hj_doctor_card');



        $id = absint($a['id']);

        if (!$id && is_singular('doctor')) { $id = (int) get_queried_object_id(); }

        if (!$id) { return ''; }



        $html = self::card_html($id, array(

            'layout' => $a['layout'],

            'show_en' => ((string) $a['en'] !== '0'),

        ));

        if ($html === '') { return ''; }



        self::ensure_style();

        return $html;

    }



    /* ------------------------------------------------------------------

     * Public check (same rules as the REST API)

     * ------------------------------------------------------------------ */

    public static function is_public($id) {

        $id = (int) $id;

        if ($id < 1) { return false; }

        if (class_exists('HJ_Doctors_REST') && method_exists('HJ_Doctors_REST', 'is_public')) {

            return (bool) HJ_Doctors_REST::is_public($id);

        }

        $p = get_post($id);

        if (!$p || $p->post_type !== 'doctor' || $p->post_status !== 'publish' || !empty($p->post_password)) { return false; }

        $h = get_post_meta($id, 'profile_hidden', true);

        if (is_scalar($h) && in_array(strtolower(trim((string) $h)), array('1', 'true', 'yes', 'on'), true)) { return false; }

        if (function_exists('hj_dp_is_public') && !hj_dp_is_public($id)) { return false; }

        return true;

    }



    /* ------------------------------------------------------------------

     * Args + cache

     * ------------------------------------------------------------------ */

    public static function norm_args($args) {

        $args = is_array($args) ? $args : array();

        $l = isset($args['layout']) ? strtolower(trim((string) $args['layout'])) : 'auto';

        $map = array('v' => 'v', 'vertical' => 'v', 'h' => 'h', 'horizontal' => 'h', 'auto' => 'auto');

        $l = isset($map[$l]) ? $map[$l] : 'auto';

        return array(

            'layout' => $l,

            'show_en' => !isset($args['show_en']) || (bool) $args['show_en'],

            'external' => !empty($args['external']),

        );

    }



    public static function ttl() {

        $d = defined('HJ_REST_CACHE_TTL') ? (int) HJ_REST_CACHE_TTL : 900;

        $ttl = (int) apply_filters('hj_wd_cache_ttl', $d);

        return max(0, $ttl);

    }



    private static function cache_ver() {

        $v = (class_exists('HJ_Doctors_REST') && method_exists('HJ_Doctors_REST', 'ver')) ? (string) HJ_Doctors_REST::ver() : 'x';

        return $v . '|' . self::plugin_ver();

    }



    public static function cache_key($id, $args) {

        $a = self::norm_args($args);

        return 'hj_wd_c_' . md5((int) $id . '|' . $a['layout'] . '|' . (int) $a['show_en'] . '|' . (int) $a['external']);

    }



    public static function cache_read($key) {

        if (self::ttl() <= 0) { return false; }

        $c = get_transient($key);

        if (is_array($c) && isset($c['v']) && isset($c['d']) && is_string($c['d']) && $c['v'] === self::cache_ver()) {

            return $c['d'];

        }

        return false;

    }



    private static function cache_write($key, $html) {

        $ttl = self::ttl();

        if ($ttl <= 0) { return; }

        set_transient($key, array('v' => self::cache_ver(), 'd' => $html), $ttl);

    }



    /* ------------------------------------------------------------------

     * Card

     * ------------------------------------------------------------------ */

    public static function card_html($id, $args = array()) {

        try {

            $id = (int) $id;

            $a = self::norm_args($args);

            if (!self::is_public($id)) { return ''; }



            $key = self::cache_key($id, $a);

            $hit = self::cache_read($key);

            if ($hit !== false) { return $hit; }



            $item = self::item($id);

            if (!is_array($item)) { return ''; }



            $html = self::render($id, $item, $a);

            if ($html !== '') { self::cache_write($key, $html); }

            return $html;

        } catch (Throwable $e) {

            if (function_exists('hj_doctors_log')) { hj_doctors_log('Widget card error: ' . $e->getMessage()); }

            return '';

        }

    }



    private static function item($id) {

        if (class_exists('HJ_Doctors_REST') && method_exists('HJ_Doctors_REST', 'build_item')) {

            $it = HJ_Doctors_REST::build_item($id, true);

            if (is_array($it)) { return $it; }

        }

        return self::fallback_item($id);

    }



    private static function plain($s) {

        if (!is_scalar($s)) { return ''; }

        $s = wp_strip_all_tags((string) $s);

        $s = wp_specialchars_decode($s, ENT_QUOTES);

        $r = preg_replace('/\s+/u', ' ', $s);

        return trim($r === null ? $s : $r);

    }



    private static function pick($arr, $key) {

        if (!is_array($arr) || !isset($arr[$key]) || !is_scalar($arr[$key])) { return ''; }

        return trim((string) $arr[$key]);

    }



    private static function fa($s) {

        return strtr((string) $s, array(

            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',

            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',

        ));

    }



    private static function fallback_item($id) {

        $specs = array();

        $terms = get_the_terms($id, 'specialty');

        if (!empty($terms) && !is_wp_error($terms)) {

            foreach (array_slice(array_values($terms), 0, 2) as $t) {

                $specs[] = array('name' => self::plain($t->name), 'slug' => (string) $t->slug);

            }

        }

        return array(

            'id' => $id,

            'url' => esc_url_raw(get_permalink($id)),

            'name' => array('fa' => self::plain(get_the_title($id))),

            'specialties' => $specs,

            'photo' => (string) get_the_post_thumbnail_url($id, 'medium'),

        );

    }



    private static function rating_html($avg, $cnt) {

        $avg = min(5, max(1, (float) $avg));

        $num = str_replace('.', '٫', self::fa(number_format($avg, 1, '.', '')));

        $pct = round($avg / 5 * 100, 1);

        $row = str_repeat(self::STAR, 5);

        $label = 'امتیاز ' . $num . ' از ' . self::fa('5');



        $o = '<span class="hj-wd-rating">';

        $o .= '<span class="hj-wd-stars" role="img" aria-label="' . esc_attr($label) . '">';

        $o .= '<span class="hj-wd-row hj-wd-row--bg">' . $row . '</span>';

        $o .= '<span class="hj-wd-clip" style="width:' . esc_attr($pct) . '%"><span class="hj-wd-row hj-wd-row--fg">' . $row . '</span></span>';

        $o .= '</span>';

        $o .= '<span class="hj-wd-rate-num">' . esc_html($num) . '</span>';

        if ($cnt > 0) {

            $o .= '<span class="hj-wd-rate-cnt">(' . esc_html(self::fa((string) (int) $cnt)) . ' رأی)</span>';

        }

        $o .= '</span>';

        return $o;

    }



    private static function render($id, $item, $a) {

        // Names

        $name = (isset($item['name']) && is_array($item['name'])) ? $item['name'] : array();

        $n_fa = self::pick($name, 'fa');

        $n_en = self::pick($name, 'en');

        if ($n_fa === '' && $n_en === '') { return ''; }



        // Specialty (meta first, then taxonomy terms)

        $sp = (isset($item['specialty']) && is_array($item['specialty'])) ? $item['specialty'] : array();

        $s_fa = self::pick($sp, 'fa');

        $s_en = self::pick($sp, 'en');

        if (($s_fa === '' || $s_en === '') && !empty($item['specialties']) && is_array($item['specialties'])) {

            $f = array(); $e = array();

            foreach (array_slice($item['specialties'], 0, 2) as $t) {

                $x = self::pick($t, 'name'); if ($x !== '') { $f[] = $x; }

                $y = self::pick($t, 'name_en'); if ($y !== '') { $e[] = $y; }

            }

            if ($s_fa === '' && $f) { $s_fa = implode('، ', $f); }

            if ($s_en === '' && $e) { $s_en = implode(', ', $e); }

        }



        // Location

        $loc = array();

        foreach (array('city', 'province') as $k) {

            if (isset($item[$k]) && is_array($item[$k])) {

                $x = self::pick($item[$k], 'name');

                if ($x !== '') { $loc[] = $x; }

            }

        }

        $loc_txt = implode('، ', $loc);



        // Photo

        $photo = self::pick($item, 'photo');

        if ($photo === '' && function_exists('hj_doctors_default_photo_url')) {

            $photo = (string) hj_doctors_default_photo_url();

        }



        // URL

        $url = self::pick($item, 'url');

        if ($url === '') { $url = (string) get_permalink($id); }



        // Rating (only when the API layer decided it is visible)

        $rate = '';

        if (isset($item['rating']) && is_array($item['rating'])) {

            $avg = isset($item['rating']['average']) ? (float) $item['rating']['average'] : 0.0;

            $cnt = isset($item['rating']['count']) ? (int) $item['rating']['count'] : 0;

            if ($avg > 0) { $rate = self::rating_html($avg, $cnt); }

        }



        $name_en_primary = ($n_fa === '');

        $name_main = $name_en_primary ? $n_en : $n_fa;

        $name_sub = (!$name_en_primary && $a['show_en']) ? $n_en : '';



        $spec_en_primary = ($s_fa === '' && $s_en !== '');

        $spec_main = $spec_en_primary ? $s_en : $s_fa;

        $spec_sub = (!$spec_en_primary && $a['show_en']) ? $s_en : '';



        $cls = 'hj-wd hj-wd--' . $a['layout'];



        $o = '<div class="hj-wd-wrap">';

        $o .= '<article class="' . esc_attr($cls) . '" dir="rtl" lang="fa" data-hj-doctor="' . (int) $id . '">';

        $o .= '<a class="hj-wd-link" href="' . esc_url($url) . '"' . ($a['external'] ? ' target="_blank" rel="noopener"' : '') . '>';



        if ($photo !== '') {

            $o .= '<span class="hj-wd-photo"><img class="hj-wd-img" src="' . esc_url($photo) . '" alt="' . esc_attr($name_main) . '" width="88" height="88" loading="lazy" decoding="async"></span>';

        }



        $o .= '<span class="hj-wd-body">';

        $o .= '<span class="hj-wd-name' . ($name_en_primary ? ' hj-wd-name--en' : '') . '"' . ($name_en_primary ? ' lang="en" dir="ltr"' : '') . '>' . esc_html($name_main) . '</span>';

        if ($name_sub !== '') {

            $o .= '<span class="hj-wd-en" lang="en" dir="ltr">' . esc_html($name_sub) . '</span>';

        }

        if ($spec_main !== '') {

            $o .= '<span class="hj-wd-spec' . ($spec_en_primary ? ' hj-wd-name--en' : '') . '"' . ($spec_en_primary ? ' lang="en" dir="ltr"' : '') . '>' . esc_html($spec_main) . '</span>';

        }

        if ($spec_sub !== '') {

            $o .= '<span class="hj-wd-en" lang="en" dir="ltr">' . esc_html($spec_sub) . '</span>';

        }

        if ($loc_txt !== '') {

            $o .= '<span class="hj-wd-loc">' . self::PIN . '<span class="hj-wd-loc-t">' . esc_html($loc_txt) . '</span></span>';

        }

        $o .= $rate;

        $o .= '<span class="hj-wd-cta">مشاهدهٔ پروفایل' . self::CHEV . '</span>';

        $o .= '</span>';



        $o .= '</a></article></div>';



        return (string) apply_filters('hj_wd_card_html', $o, (int) $id, $item, $a);

    }

}



endif;



if (!function_exists('hj_doctor_widget_card_html')) {

    function hj_doctor_widget_card_html($id, $args = array()) {

        return HJ_Doctors_Shortcodes::card_html($id, $args);

    }

}



HJ_Doctors_Shortcodes::boot();

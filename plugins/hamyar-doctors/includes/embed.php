<?php

/**

 * Hamyar Doctors — Section 12b: Embed box + iframe endpoint + loader script.

 *

 * /?hj_widget=<ID>[&layout=v|h] → minimal iframe page (public doctors only)

 * /?hj_widget_js=1 → loader script (builds iframe + auto height)

 * action hj_doctor_embed_box → embed box on the doctor profile

 * status page: /wp-admin/admin-post.php?action=hj_wd_status[&id=114]

 *

 * Constants (optional): define('HJ_WD_DISABLED', true); // turn embed endpoints off

 * Filters: hj_wd_enabled, hj_wd_frame_ancestors ('*' by default), hj_wd_rate_limit,

 * hj_wd_font_css_url, hj_wd_show_embed_box, hj_wd_embed_show_title

 */

if (!defined('ABSPATH')) { exit; }



if (!class_exists('HJ_Doctors_Embed')) :



class HJ_Doctors_Embed {



    private static $booted = false;



    public static function boot() {

        if (self::$booted) { return; }

        self::$booted = true;



        add_action('hj_doctor_embed_box', array(__CLASS__, 'print_box'), 10, 1);

        add_action('init', array(__CLASS__, 'maybe_serve'), 1);

        add_action('admin_post_hj_wd_status', array(__CLASS__, 'status_page'));

    }



    public static function enabled() {

        if (defined('HJ_WD_DISABLED') && HJ_WD_DISABLED) { return false; }

        return (bool) apply_filters('hj_wd_enabled', true);

    }



    private static function ready() {

        return class_exists('HJ_Doctors_Shortcodes');

    }



    /* ------------------------------------------------------------------

     * Routing (runs on init:1, before the theme and canonical redirects)

     * ------------------------------------------------------------------ */

    public static function maybe_serve() {

        if (is_admin()) { return; }

        $m = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

        if ($m !== 'GET' && $m !== 'HEAD') { return; }



        if (isset($_GET['hj_widget_js'])) {

            if (!self::enabled()) { return; }

            self::serve_js();

        }

        if (isset($_GET['hj_widget'])) {

            if (!self::enabled() || !self::ready()) { return; }

            self::serve_page();

        }

    }



    private static function throttled() {

        if (!function_exists('hj_sec_rate_limit')) { return false; }

        $cfg = (array) apply_filters('hj_wd_rate_limit', array(120, 600)); // [requests, seconds]

        $limit = isset($cfg[0]) ? max(1, (int) $cfg[0]) : 120;

        $win = isset($cfg[1]) ? max(30, (int) $cfg[1]) : 600;

        return hj_sec_rate_limit('widget', $limit, $win) === false;

    }



    private static function frame_ancestors() {

        $anc = apply_filters('hj_wd_frame_ancestors', '*');

        if (is_array($anc)) { $anc = implode(' ', $anc); }

        $anc = trim((string) preg_replace('/[^A-Za-z0-9:\/\.\-\*\s_]/', '', (string) $anc));

        return $anc === '' ? '*' : $anc;

    }



    private static function frame_headers() {

        if (headers_sent()) { return; }

        header_remove('X-Frame-Options');

        header('Content-Security-Policy: frame-ancestors ' . self::frame_ancestors());

        header('Content-Type: text/html; charset=utf-8');

        header('X-Robots-Tag: noindex, nofollow');

        header('X-Content-Type-Options: nosniff');

        header('Referrer-Policy: strict-origin-when-cross-origin');

    }



    private static function blank($status) {

        status_header((int) $status);

        nocache_headers();

        self::frame_headers();

        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>&nbsp;</title></head><body></body></html>';

        exit;

    }



    /* ------------------------------------------------------------------

     * iframe page

     * ------------------------------------------------------------------ */

    private static function serve_page() {

        $raw = wp_unslash($_GET['hj_widget']);

        $id = is_scalar($raw) ? absint($raw) : 0;



        $lraw = (isset($_GET['layout']) && is_scalar($_GET['layout'])) ? sanitize_key(wp_unslash($_GET['layout'])) : 'auto';

        $args = HJ_Doctors_Shortcodes::norm_args(array('layout' => $lraw, 'external' => true));



        if ($id < 1 || !HJ_Doctors_Shortcodes::is_public($id)) {

            if (self::throttled()) { self::blank(429); }

            self::blank(404);

        }



        $key = HJ_Doctors_Shortcodes::cache_key($id, $args);

        if (HJ_Doctors_Shortcodes::cache_read($key) === false && self::throttled()) {

            self::blank(429);

        }



        $card = HJ_Doctors_Shortcodes::card_html($id, $args);

        if ($card === '') { self::blank(404); }



        status_header(200);

        self::frame_headers();

        if (!headers_sent()) { header('Cache-Control: public, max-age=300, s-maxage=300'); }



        $css = HJ_Doctors_Shortcodes::css_url() . '?ver=' . rawurlencode(HJ_Doctors_Shortcodes::asset_ver('assets/css/widget.css'));

        $font = (string) apply_filters('hj_wd_font_css_url', '');

        $ttl = wp_strip_all_tags(get_the_title($id));



        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">';

        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';

        echo '<meta name="robots" content="noindex,nofollow">';

        echo '<title>' . esc_html($ttl) . '</title>';

        if ($font !== '') { echo '<link rel="stylesheet" href="' . esc_url($font) . '">'; }

        echo '<link rel="stylesheet" href="' . esc_url($css) . '">';

        echo '</head><body class="hj-wd-frame"><div id="hj-wd-root" class="hj-wd-page">' . $card . '</div>';

        echo '<script>(function(){var r=document.getElementById("hj-wd-root");'

           . 'if(!r||!window.parent||window.parent===window){return;}var last=0;'

           . 'function s(){var h=Math.ceil(r.getBoundingClientRect().height);'

           . 'if(h&&h!==last){last=h;window.parent.postMessage({type:"hj-widget-height",id:' . (int) $id . ',height:h},"*");}}'

           . 'if(window.ResizeObserver){new ResizeObserver(s).observe(r);}'

           . 'window.addEventListener("load",s);window.addEventListener("resize",s);s();})();</script>';

        echo '</body></html>';

        exit;

    }



    /* ------------------------------------------------------------------

     * Loader script

     * ------------------------------------------------------------------ */

    private static function origin() {

        $p = wp_parse_url(home_url('/'));

        $o = (isset($p['scheme']) ? $p['scheme'] : 'https') . '://' . (isset($p['host']) ? $p['host'] : '');

        if (!empty($p['port'])) { $o .= ':' . (int) $p['port']; }

        return $o;

    }



    private static function serve_js() {

        $js = <<<'JS'

(function (w, d) {

  if (w.__hjWdLoader) { if (w.hjDoctorEmbedInit) { w.hjDoctorEmbedInit(); } return; }

  w.__hjWdLoader = 1;

  var BASE = __HJ_BASE__, ORIGIN = __HJ_ORIGIN__, frames = [];

  function mk(el) {

    if (el.getAttribute('data-hj-ready')) { return; }

    var id = parseInt(el.getAttribute('data-hj-doctor'), 10);

    if (!id || id < 1) { return; }

    el.setAttribute('data-hj-ready', '1');

    var l = el.getAttribute('data-hj-layout') || 'auto';

    if (l !== 'v' && l !== 'h') { l = 'auto'; }

    var h = (l === 'h') ? 200 : 340;

    var f = d.createElement('iframe');

    f.src = BASE + '?hj_widget=' + id + (l === 'auto' ? '' : '&layout=' + l);

    f.title = 'Doctor card';

    f.setAttribute('loading', 'lazy');

    f.setAttribute('scrolling', 'no');

    f.setAttribute('width', '100%');

    f.style.cssText = 'border:0;display:block;width:100%;max-width:560px;margin:0 auto;overflow:hidden;background:transparent;height:' + h + 'px';

    el.appendChild(f);

    frames.push(f);

  }

  function init() {

    var n = d.querySelectorAll('.hj-doctor-embed[data-hj-doctor]');

    for (var i = 0; i < n.length; i++) { mk(n[i]); }

  }

  w.hjDoctorEmbedInit = init;

  w.addEventListener('message', function (e) {

    if (e.origin !== ORIGIN) { return; }

    var m = e.data;

    if (!m || m.type !== 'hj-widget-height') { return; }

    var h = parseInt(m.height, 10);

    if (!h || h < 60 || h > 1200) { return; }

    for (var i = 0; i < frames.length; i++) {

      if (frames[i].contentWindow === e.source) { frames[i].style.height = h + 'px'; }

    }

  });

  if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); } else { init(); }

})(window, document);

JS;

        $js = str_replace(

            array('__HJ_BASE__', '__HJ_ORIGIN__'),

            array(wp_json_encode(home_url('/')), wp_json_encode(self::origin())),

            $js

        );



        status_header(200);

        if (!headers_sent()) {

            header('Content-Type: application/javascript; charset=utf-8');

            header('Cache-Control: public, max-age=3600');

            header('X-Content-Type-Options: nosniff');

            header('Access-Control-Allow-Origin: *');

        }

        echo $js;

        exit;

    }



    /* ------------------------------------------------------------------

     * Embed codes

     * ------------------------------------------------------------------ */

    public static function codes($id) {

        $id = (int) $id;

        $base = home_url('/');

        $host = (string) wp_parse_url($base, PHP_URL_HOST);

        $title = 'کارت پزشک | ' . $host;

        $js = esc_url_raw($base . '?hj_widget_js=1');



        $suffix = array('auto' => '', 'v' => '&layout=v', 'h' => '&layout=h');

        $codes = array();

        foreach ($suffix as $k => $sfx) {

            $src = str_replace('&', '&amp;', esc_url_raw($base . '?hj_widget=' . $id . $sfx));

            $h = ($k === 'h') ? 200 : 340;

            $codes['iframe_' . $k] = '<iframe src="' . $src . '" title="' . esc_attr($title) . '" width="100%" height="' . $h . '" '

                . 'style="border:0;display:block;width:100%;max-width:560px;margin:0 auto" loading="lazy" '

                . 'referrerpolicy="strict-origin-when-cross-origin"></iframe>';

            $codes['script_' . $k] = '<div class="hj-doctor-embed" data-hj-doctor="' . $id . '" data-hj-layout="' . $k . '"></div>' . "\n"

                . '<script async src="' . $js . '"></script>';

        }

        return $codes;

    }



    /* ------------------------------------------------------------------

     * Embed box (profile page)

     * ------------------------------------------------------------------ */

    public static function print_box($post_id = 0) {

        $id = is_scalar($post_id) ? absint($post_id) : 0;

        if (!$id) { $id = (int) get_the_ID(); }

        if (!$id) { $id = (int) get_queried_object_id(); }

        echo self::box_html($id); // phpcs:ignore WordPress.Security.EscapeOutput

    }



    public static function box_html($id) {

        $id = (int) $id;

        if ($id < 1 || get_post_type($id) !== 'doctor') { return ''; }

        if (!self::enabled() || !self::ready()) { return ''; }

        if (!apply_filters('hj_wd_show_embed_box', true, $id)) { return ''; }

        if (!HJ_Doctors_Shortcodes::is_public($id)) { return ''; } // preview/hidden profiles: no embed code



        HJ_Doctors_Shortcodes::ensure_style();

        if (!wp_script_is('hj-copy-embed', 'registered')) {

            wp_register_script('hj-copy-embed', HJ_Doctors_Shortcodes::base_url() . 'assets/js/copy-embed.js', array(), HJ_Doctors_Shortcodes::asset_ver('assets/js/copy-embed.js'), true);

        }

        wp_enqueue_script('hj-copy-embed');



        $codes = self::codes($id);

        $tid = 'hj-eb-t-' . $id;

        $cid = 'hj-eb-c-' . $id;

        $lid = 'hj-eb-l-' . $id;



        $o = '<section class="hj-eb hj-eb--nojs" aria-labelledby="' . esc_attr($tid) . '"'

            . ' data-hj-codes="' . esc_attr(wp_json_encode($codes)) . '"'

            . ' data-hj-msg-ok="کپی شد" data-hj-msg-fail="متن را انتخاب و کپی کنید" data-hj-label="کپی">';

        if (apply_filters('hj_wd_embed_show_title', true)) {

            $o .= '<span class="hj-eb-title" id="' . esc_attr($tid) . '">جاسازی این پزشک در سایت شما</span>';

        } else {

            $o .= '<span class="hj-eb-title hj-eb-title--sr" id="' . esc_attr($tid) . '">جاسازی این پزشک در سایت شما</span>';

        }

        $o .= '<span class="hj-eb-sub">کد را کپی کنید و در صفحهٔ سایت خود بچسبانید.</span>';



        $o .= '<div class="hj-eb-bar">';

        $o .= '<div class="hj-eb-tabs" role="group" aria-label="نوع کد">';

        $o .= '<button type="button" class="hj-eb-tab is-active" data-hj-type="iframe" aria-pressed="true">iframe</button>';

        $o .= '<button type="button" class="hj-eb-tab" data-hj-type="script" aria-pressed="false">اسکریپت (ارتفاع خودکار)</button>';

        $o .= '</div>';

        $o .= '<label class="hj-eb-lbl" for="' . esc_attr($lid) . '">چیدمان</label>';

        $o .= '<select class="hj-eb-sel" id="' . esc_attr($lid) . '" data-hj-layout>'

            . '<option value="auto">خودکار (بر اساس عرض)</option>'

            . '<option value="v">عمودی</option>'

            . '<option value="h">افقی</option>'

            . '</select>';

        $o .= '</div>';



        $o .= '<textarea class="hj-eb-code" id="' . esc_attr($cid) . '" readonly rows="4" dir="ltr" spellcheck="false" data-hj-code>'

            . esc_textarea($codes['iframe_auto']) . '</textarea>';



        $o .= '<div class="hj-eb-actions">';

        $o .= '<button type="button" class="hj-eb-copy" data-hj-copy>کپی</button>';

        $o .= '<span class="hj-eb-msg" role="status" aria-live="polite"></span>';

        $o .= '</div>';

        $o .= '</section>';



        return $o;

    }



    /* ------------------------------------------------------------------

     * Status page (browser only)

     * ------------------------------------------------------------------ */

    private static function admin_ok() {

        if (!is_user_logged_in()) { return false; }

        if (current_user_can('manage_options')) { return true; }

        return function_exists('hj_doctors_user_can_manage') && hj_doctors_user_can_manage('doctors');

    }



    public static function status_page() {

        if (!self::admin_ok()) { wp_die('دسترسی ندارید.', '', array('response' => 403)); }

        nocache_headers();

        header('Content-Type: text/html; charset=utf-8');



        $e = 'esc_html';

        $row = function ($label, $ok, $extra = '') use ($e) {

            echo '<tr><td>' . $e($label) . '</td><td class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'OK' : 'ناموجود') . '</td><td>' . $e($extra) . '</td></tr>';

        };



        $css_file = (class_exists('HJ_Doctors_Shortcodes') ? HJ_Doctors_Shortcodes::base_path() : '') . 'assets/css/widget.css';

        $js_file = (class_exists('HJ_Doctors_Shortcodes') ? HJ_Doctors_Shortcodes::base_path() : '') . 'assets/js/copy-embed.js';



        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>hj_wd_status</title>';

        echo '<style>body{font-family:Tahoma,sans-serif;background:#f4f8fb;color:#061A2E;margin:0;padding:24px}';

        echo 'h1{font-size:20px}h2{font-size:16px;margin-top:28px;color:#0F3556}table{border-collapse:collapse;background:#fff;width:100%;max-width:980px}';

        echo 'td,th{border:1px solid #dbe6ee;padding:8px 10px;text-align:right;font-size:13px}.ok{color:#0EA5A0;font-weight:700}.bad{color:#6B7C8F;font-weight:700}';

        echo 'textarea{width:100%;max-width:980px;direction:ltr;font-family:monospace;font-size:12px}.frames{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start}';

        echo '.frames iframe{border:1px dashed #9fb4c4;background:#fff}</style></head><body>';

        echo '<h1>وضعیت ویجت و جاسازی پزشکان (بخش ۱۲)</h1>';



        echo '<h2>۱) پیکربندی و وابستگی‌ها</h2><table>';

        $row('ماژول جاسازی فعال است', self::enabled(), self::enabled() ? '' : 'HJ_WD_DISABLED یا فیلتر hj_wd_enabled');

        $row('کلاس HJ_Doctors_Shortcodes', class_exists('HJ_Doctors_Shortcodes'));

        $row('کلاس HJ_Doctors_REST (منبع داده)', class_exists('HJ_Doctors_REST'), 'اگر نباشد، کارت حداقلی ساخته می‌شود');

        $row('فایل widget.css', file_exists($css_file), $css_file);

        $row('فایل copy-embed.js', file_exists($js_file), $js_file);

        $row('hj_doctors_default_photo_url', function_exists('hj_doctors_default_photo_url'));

        $row('hj_sec_rate_limit', function_exists('hj_sec_rate_limit'));

        echo '<tr><td>فعال بودن کش</td><td colspan="2">TTL = ' . (int) (class_exists('HJ_Doctors_Shortcodes') ? HJ_Doctors_Shortcodes::ttl() : 0) . ' ثانیه</td></tr>';

        echo '<tr><td>frame-ancestors</td><td colspan="2" dir="ltr">' . $e(self::frame_ancestors()) . '</td></tr>';

        echo '</table>';



        $ids = get_posts(array('post_type' => 'doctor', 'post_status' => 'any', 'numberposts' => 15, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'));



        $sel = isset($_GET['id']) ? absint($_GET['id']) : 0;

        echo '<h2>۲) پزشکان نمونه</h2><table><tr><th>ID</th><th>عنوان</th><th>عمومی؟</th><th>صفحهٔ iframe</th><th>پیش‌نمایش</th></tr>';

        foreach ($ids as $i) {

            $pub = class_exists('HJ_Doctors_Shortcodes') && HJ_Doctors_Shortcodes::is_public($i);

            if (!$sel && $pub) { $sel = (int) $i; }

            echo '<tr><td>' . (int) $i . '</td><td>' . $e(get_the_title($i)) . '</td>';

            echo '<td class="' . ($pub ? 'ok' : 'bad') . '">' . ($pub ? 'بله' : 'خیر') . '</td>';

            echo '<td dir="ltr">' . ($pub ? '<a target="_blank" rel="noopener" href="' . esc_url(home_url('/?hj_widget=' . (int) $i)) . '">?hj_widget=' . (int) $i . '</a>' : '—') . '</td>';

            echo '<td>' . ($pub ? '<a href="' . esc_url(admin_url('admin-post.php?action=hj_wd_status&id=' . (int) $i)) . '">نمایش در همین صفحه</a>' : '—') . '</td></tr>';

        }

        echo '</table>';



        if ($sel > 0 && class_exists('HJ_Doctors_Shortcodes') && HJ_Doctors_Shortcodes::is_public($sel)) {

            $u = home_url('/?hj_widget=' . $sel);

            $uh = home_url('/?hj_widget=' . $sel . '&layout=h');

            $js = home_url('/?hj_widget_js=1');

            echo '<h2>۳) پیش‌نمایش پزشک ' . (int) $sel . '</h2>';

            echo '<div class="frames">';

            echo '<div><p>عرض ۲۶۰ (سایدبار) — خودکار</p><iframe src="' . esc_url($u) . '" width="260" height="380"></iframe></div>';

            echo '<div><p>عرض ۶۰۰ (وسط صفحه) — خودکار</p><iframe src="' . esc_url($u) . '" width="600" height="230"></iframe></div>';

            echo '<div><p>عرض ۳۰۰ — افقی اجباری</p><iframe src="' . esc_url($uh) . '" width="300" height="260"></iframe></div>';

            echo '</div>';

            echo '<h2>۴) نسخهٔ اسکریپت (ارتفاع خودکار)</h2>';

            echo '<div style="max-width:420px;border:1px dashed #9fb4c4;padding:6px;background:#fff"><div class="hj-doctor-embed" data-hj-doctor="' . (int) $sel . '" data-hj-layout="auto"></div></div>';

            echo '<script async src="' . esc_url($js) . '"></script>';

            $codes = self::codes($sel);

            echo '<h2>۵) کدهای تولیدشده</h2>';

            foreach ($codes as $k => $c) {

                echo '<p dir="ltr" style="text-align:left;margin:10px 0 2px">' . $e($k) . '</p><textarea readonly rows="3">' . esc_textarea($c) . '</textarea>';

            }

        } else {

            echo '<p>هیچ پزشک عمومی برای پیش‌نمایش نیست. یک پزشک را منتشر و تأیید کنید.</p>';

        }



        echo '<p style="color:#6B7C8F;font-size:12px;margin-top:24px">پاک‌کردن کش: از دکمهٔ صفحهٔ <code>hj_rest_status</code> استفاده کنید (کش کارت‌ها با همان نسخه پاک می‌شود).</p>';

        echo '</body></html>';

        exit;

    }

}



endif;



HJ_Doctors_Embed::boot();

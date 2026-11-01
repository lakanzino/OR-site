/* =========================================================================

 * Hamyar Doctors — Section 13: Medical Premium theme layer (APPEND ONLY)

 *

 * wp-config.php constants (all optional):

 * define('HJ_FONTS_MODE', 'local'); // local (default) | cdn | off

 * define('HJ_DARK_MODE', 'off'); // off (default) | auto

 * define('HJ_THEME_LAYER_DISABLED', true); // turn this whole layer off

 *

 * Admin-only preview (no OS setting needed): ?hj_theme=dark / ?hj_theme=light

 * Status page: /wp-admin/admin-post.php?action=hj_theme_status

 * ========================================================================= */

if (!class_exists('HJ_Theme_Layer')) :



class HJ_Theme_Layer {



    const FONT_FA = 'assets/fonts/Vazirmatn-Variable.woff2';

    const FONT_EN = 'assets/fonts/Inter-Variable.woff2';



    private static $booted = false;

    private static $scope = null;



    public static function boot() {

        if (self::$booted) { return; }

        self::$booted = true;



        add_action('admin_post_hj_theme_status', array(__CLASS__, 'status_page'));



        if (defined('HJ_THEME_LAYER_DISABLED') && HJ_THEME_LAYER_DISABLED) { return; }

        if (defined('HJ_DOCTORS_SAFE_MODE') && HJ_DOCTORS_SAFE_MODE) { return; }



        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 30);

        add_filter('body_class', array(__CLASS__, 'body_class'), 20);

        add_action('wp_head', array(__CLASS__, 'preload'), 3);

        add_filter('hj_wd_font_css_url', array(__CLASS__, 'widget_font_url'));

    }



    /* ---------------- paths ---------------- */

    private static function purl() { return defined('HJ_DOCTORS_URL') ? trailingslashit(HJ_DOCTORS_URL) : ''; }

    private static function ppath() { return defined('HJ_DOCTORS_PATH') ? trailingslashit(HJ_DOCTORS_PATH) : ''; }

    private static function active() { return self::purl() !== '' && self::ppath() !== ''; }



    private static function ver($rel) {

        $m = @filemtime(self::ppath() . ltrim($rel, '/'));

        $v = defined('HJ_DOCTORS_VERSION') ? (string) HJ_DOCTORS_VERSION : '1';

        return $v . ($m ? '.' . $m : '');

    }



    /* ---------------- settings ---------------- */

    public static function fonts_mode() {

        $m = defined('HJ_FONTS_MODE') ? strtolower((string) HJ_FONTS_MODE) : 'local';

        $m = (string) apply_filters('hj_theme_fonts_mode', $m);

        return in_array($m, array('local', 'cdn', 'off'), true) ? $m : 'local';

    }



    public static function dark_mode() {

        $m = defined('HJ_DARK_MODE') ? strtolower((string) HJ_DARK_MODE) : 'off';

        $m = (string) apply_filters('hj_theme_dark_mode', $m);

        return in_array($m, array('off', 'auto'), true) ? $m : 'off';

    }



    /** CDN URLs (written from memory: open each in the browser once to verify). */

    public static function cdn_urls() {

        $u = array(

            'https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css',

            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',

        );

        $u = apply_filters('hj_theme_cdn_urls', $u);

        return is_array($u) ? array_values(array_filter(array_map('esc_url_raw', $u))) : array();

    }



    /** Admin-only preview of the dark/light theme via ?hj_theme= */

    private static function preview() {

        if (!isset($_GET['hj_theme']) || !is_scalar($_GET['hj_theme'])) { return ''; }

        if (!current_user_can('manage_options')) { return ''; }

        $v = sanitize_key(wp_unslash($_GET['hj_theme']));

        return in_array($v, array('dark', 'light'), true) ? $v : '';

    }



    /* ---------------- scope: only plugin pages ---------------- */

    public static function in_scope() {

        if (self::$scope !== null) { return self::$scope; }

        $s = false;



        if (is_singular('doctor') || is_post_type_archive('doctor')

            || is_tax(array('specialty', 'province', 'city', 'specialty_type'))) {

            $s = true;

        }

        if (!$s && is_singular()) {

            $p = get_post();

            if ($p) {

                foreach (array('hj_doctor_dashboard', 'hj_doctor_card', 'hj_doctor_search', 'hj_google_login') as $sc) {

                    if (has_shortcode((string) $p->post_content, $sc)) { $s = true; break; }

                }

            }

            if (!$s && is_page('my-profile')) { $s = true; }

        }

        self::$scope = (bool) apply_filters('hj_theme_in_scope', $s);

        return self::$scope;

    }



    public static function body_class($c) {

        if (!self::in_scope()) { return $c; }

        $c[] = 'hj-scope';

        $pv = self::preview();

        if ($pv === 'dark') { $c[] = 'hj-theme-dark'; }

        if ($pv === 'light') { $c[] = 'hj-theme-light'; }

        return $c;

    }



    /* ---------------- assets ---------------- */

    private static function local_font_ok() {

        return file_exists(self::ppath() . self::FONT_FA);

    }



    public static function enqueue() {

        if (!self::active() || !self::in_scope()) { return; }



        $deps = array();



        // Fonts

        $mode = self::fonts_mode();

        if ($mode === 'local' && self::local_font_ok()) {

            wp_enqueue_style('hj-fonts', self::purl() . 'assets/fonts/fonts.css', array(), self::ver('assets/fonts/fonts.css'));

            $deps[] = 'hj-fonts';

        } elseif ($mode === 'cdn') {

            $i = 0;

            foreach (self::cdn_urls() as $u) {

                $h = 'hj-fonts-cdn-' . (++$i);

                wp_enqueue_style($h, $u, array(), null);

                $deps[] = $h;

            }

        }



        // Load after the section stylesheets that are already enqueued (only registered handles are safe as deps)

        foreach (array('hj-profile', 'hj-archive', 'hj-dashboard', 'hj-widget') as $h) {

            if (wp_style_is($h, 'enqueued')) { $deps[] = $h; }

        }



        wp_enqueue_style('hj-theme', self::purl() . 'assets/css/theme.css', $deps, self::ver('assets/css/theme.css'));



        // Dark mode: forced by admin preview, or "auto" (OS preference) when enabled

        $pv = self::preview();

        $media = null;

        if ($pv === 'dark') {

            $media = 'all';

        } elseif ($pv !== 'light' && self::dark_mode() === 'auto') {

            $media = '(prefers-color-scheme: dark)';

        }

        if ($media !== null) {

            wp_enqueue_style('hj-theme-dark', self::purl() . 'assets/css/theme-dark.css', array('hj-theme'), self::ver('assets/css/theme-dark.css'), $media);

        }



        wp_enqueue_script('hj-theme', self::purl() . 'assets/js/theme.js', array(), self::ver('assets/js/theme.js'), true);

    }



    public static function preload() {

        if (!self::active() || !self::in_scope() || self::fonts_mode() !== 'local' || !self::local_font_ok()) { return; }

        echo '<link rel="preload" href="' . esc_url(self::purl() . self::FONT_FA) . '" as="font" type="font/woff2" crossorigin>' . "\n";

    }



    /** Gives the widget iframe page (section 12) the same fonts. */

    public static function widget_font_url($u) {

        if ($u !== '' || !self::active()) { return $u; }

        $m = self::fonts_mode();

        if ($m === 'local' && self::local_font_ok()) { return self::purl() . 'assets/fonts/fonts.css'; }

        if ($m === 'cdn') {

            $c = self::cdn_urls();

            if (!empty($c[0])) { return $c[0]; }

        }

        return $u;

    }



    /* ---------------- status page (browser only) ---------------- */

    public static function status_page() {

        if (!current_user_can('manage_options')) { wp_die('دسترسی ندارید.', '', array('response' => 403)); }

        nocache_headers();

        header('Content-Type: text/html; charset=utf-8');



        $e = 'esc_html';

        $row = function ($label, $ok, $extra = '') use ($e) {

            echo '<tr><td>' . $e($label) . '</td><td class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'OK' : 'ناموجود') . '</td><td>' . $e($extra) . '</td></tr>';

        };

        $pp = self::ppath();



        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>hj_theme_status</title>';

        echo '<style>body{font-family:Tahoma,sans-serif;background:#f4f8fb;color:#061A2E;margin:0;padding:24px}';

        echo 'h1{font-size:20px}h2{font-size:16px;margin-top:28px;color:#0F3556}table{border-collapse:collapse;background:#fff;width:100%;max-width:980px}';

        echo 'td,th{border:1px solid #dbe6ee;padding:8px 10px;text-align:right;font-size:13px}.ok{color:#0EA5A0;font-weight:700}.bad{color:#6B7C8F;font-weight:700}';

        echo 'a{color:#0EA5A0}</style></head><body>';

        echo '<h1>وضعیت لایهٔ تم (بخش ۱۳)</h1>';



        echo '<h2>۱) پیکربندی</h2><table>';

        $off = defined('HJ_THEME_LAYER_DISABLED') && HJ_THEME_LAYER_DISABLED;

        $row('لایهٔ تم فعال است', !$off && self::active(), $off ? 'HJ_THEME_LAYER_DISABLED' : (self::active() ? '' : 'ثابت‌های پلاگین پیدا نشد'));

        echo '<tr><td>حالت فونت</td><td colspan="2">' . $e(self::fonts_mode()) . '</td></tr>';

        echo '<tr><td>حالت دارک (ثابت)</td><td colspan="2">' . $e(self::dark_mode()) . ' &nbsp;(پیش‌فرض: off)</td></tr>';

        echo '</table>';



        echo '<h2>۲) فایل‌ها</h2><table>';

        $files = array('assets/css/theme.css', 'assets/css/theme-dark.css', 'assets/js/theme.js', 'assets/fonts/fonts.css', self::FONT_FA, self::FONT_EN);

        foreach ($files as $f) {

            $ex = $pp !== '' && file_exists($pp . $f);

            $row($f, $ex, $ex ? (round(filesize($pp . $f) / 1024, 1) . ' KB') : '');

        }

        echo '</table>';



        echo '<h2>۳) اسکن alert / confirm / prompt در JSهای پلاگین</h2><table>';

        $js = $pp !== '' ? glob($pp . 'assets/js/*.js') : array();

        if (!$js) { echo '<tr><td colspan="3">فایلی پیدا نشد.</td></tr>'; }

        foreach ((array) $js as $f) {

            $src = @file_get_contents($f);

            $found = ($src !== false && preg_match('/\b(?:window\s*\.\s*)?(alert|confirm|prompt)\s*\(/', $src, $m)) ? $m[1] : '';

            echo '<tr><td>' . $e(basename($f)) . '</td><td class="' . ($found === '' ? 'ok' : 'bad') . '">' . ($found === '' ? 'تمیز' : 'بررسی دستی') . '</td><td>' . $e($found !== '' ? 'عبارت «' . $found . '(» پیدا شد' : '') . '</td></tr>';

        }

        echo '</table>';



        echo '<h2>۴) لینک‌های پیش‌نمایش (فقط وقتی با ادمین وارد شده‌اید)</h2><ul>';

        $links = array(

            'آرشیو پزشکان — دارک' => home_url('/doctor/?hj_theme=dark'),

            'آرشیو پزشکان — روشن' => home_url('/doctor/?hj_theme=light'),

        );

        $ids = get_posts(array('post_type' => 'doctor', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'));

        if ($ids) {

            $links['پروفایل پزشک ' . (int) $ids[0] . ' — دارک'] = add_query_arg('hj_theme', 'dark', get_permalink($ids[0]));

            $dash = function_exists('hj_doctors_dashboard_url') ? (string) hj_doctors_dashboard_url() : home_url('/my-profile/');

            $links['داشبورد (ادمین، ویرایش پزشک ' . (int) $ids[0] . ') — دارک'] = add_query_arg(array('hj_edit' => (int) $ids[0], 'hj_theme' => 'dark'), $dash);

        }

        foreach ($links as $t => $u) {

            echo '<li><a target="_blank" rel="noopener" href="' . esc_url($u) . '">' . $e($t) . '</a></li>';

        }

        echo '</ul>';

        echo '<p style="color:#6B7C8F;font-size:12px">اگر دارک ‌مود را بعد از بررسی می‌خواهید برای همه فعال کنید: <code>define(\'HJ_DARK_MODE\',\'auto\');</code> در wp-config.php</p>';

        echo '</body></html>';

        exit;

    }

}



endif;



HJ_Theme_Layer::boot();

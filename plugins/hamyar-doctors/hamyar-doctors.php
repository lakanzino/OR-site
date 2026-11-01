<?php
/**
 * Plugin Name: همیار پزشک‌ها
 * Description: نوع محتوای «پزشک» با تخصص و شهر، زمینه‌های نمایه (JSON محلی ACF بدون Sync دستی) و دسترسی نقشِ پزشک فقط به نمایهٔ خودش؛ مستقل از قالب فرزند و بی‌صدا در debug.log.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: همیار جراحی
 * License: GPL-2.0-or-later
 * Text Domain: hamyar-doctors
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HJ_DOCTORS_VERSION', '0.1.0' );
define( 'HJ_DOCTORS_FILE', __FILE__ );
define( 'HJ_DOCTORS_DIR', plugin_dir_path( __FILE__ ) );
define( 'HJ_DOCTORS_URL', plugin_dir_url( __FILE__ ) );
define( 'HJ_DOCTORS_CPT', 'doctor' );
define( 'HJ_DOCTORS_ROLE', 'doctor' );
define( 'HJ_DOCTORS_VERSION_OPTION', 'hj_doctors_version' );

/* -------------------------------------------------------------------------
 * بخش ۱: helperها — مسیر/نشانی، تبدیل ارقام و عکس پیش‌فرض
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_doctors_path' ) ) {
	/**
	 * مسیر مطلق یک فایل داخل افزونه.
	 *
	 * @param string $rel مسیر نسبی مانند 'includes/cpt.php'.
	 * @return string
	 */
	function hj_doctors_path( $rel = '' ) {
		return HJ_DOCTORS_DIR . ltrim( (string) $rel, '/' );
	}
}

if ( ! function_exists( 'hj_doctors_url' ) ) {
	/**
	 * نشانی عمومی یک فایل داخل افزونه.
	 *
	 * @param string $rel مسیر نسبی مانند 'assets/img/doctor-default.jpg'.
	 * @return string
	 */
	function hj_doctors_url( $rel = '' ) {
		return HJ_DOCTORS_URL . ltrim( (string) $rel, '/' );
	}
}

if ( ! function_exists( 'hj_to_en_digits' ) ) {
	/**
	 * Converts Persian/Arabic-Indic digits to Latin 0-9.
	 *
	 * هر دو مجموعه رقم (فارسی و عربی) نگاشت می‌شوند؛ نگهبان
	 * function_exists اجازه می‌دهد تابع جای دیگری هم تعریف شده باشد
	 * بدون اینکه collision رخ دهد.
	 *
	 * @param mixed $text ورودی دلخواه؛ به رشته تبدیل می‌شود.
	 * @return string
	 */
	function hj_to_en_digits( $text ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = range( 0, 9 );

		return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $text );
	}
}

if ( ! function_exists( 'hj_doctors_default_photo_url' ) ) {
	/**
	 * Default profile photo.
	 *
	 * اگر فایل عکس داخل بسته نباشد (یا نصفه اکسترکت شده باشد) رشتهٔ
	 * خالی برمی‌گردد تا قالب خودش جایگزین کند؛ هیچ خطایی ثبت نمی‌شود.
	 *
	 * @return string
	 */
	function hj_doctors_default_photo_url() {
		$rel  = 'assets/img/doctor-default.jpg';
		$path = hj_doctors_path( $rel );

		return is_readable( $path ) ? hj_doctors_url( $rel ) : '';
	}
}

if ( ! function_exists( 'hj_doctors_log' ) ) {
	/**
	 * ثبت رویدادهای داخلی افزونه بدون لمس debug.log.
	 *
	 * پیام‌ها فقط در گزینهٔ hj_doctors_log نگه داشته می‌شوند (۲۰ تای آخر)؛
	 * error_log فقط وقتی روشن است که HJ_DOCTORS_DEBUG صریحاً true تعریف شده
	 * باشد، پس در حالت عادی حتی یک خط به debug.log نوشته نمی‌شود.
	 *
	 * @param string $message پیام رویداد.
	 */
	function hj_doctors_log( $message ) {
		if ( defined( 'HJ_DOCTORS_DEBUG' ) && HJ_DOCTORS_DEBUG ) {
			error_log( '[hamyar-doctors] ' . (string) $message );
		}

		$log   = get_option( 'hj_doctors_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'time'    => gmdate( 'c' ),
			'message' => (string) $message,
		);

		update_option( 'hj_doctors_log', array_slice( $log, -20 ), false );
	}
}

/* -------------------------------------------------------------------------
 * کلاس اصلی: بارگذاری بخش‌ها و قلاب‌های فعال/غیرفعال
 * ---------------------------------------------------------------------- */

/**
 * کلاس اصلی افزونه؛ تنها یک نمونه ساخته می‌شود.
 */
final class HJ_Doctors {

	/**
	 * نمونهٔ یگانه.
	 *
	 * @var HJ_Doctors|null
	 */
	private static $instance = null;

	/**
	 * نمونهٔ یگانه را برمی‌گرداند.
	 *
	 * @return HJ_Doctors
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * شامل‌کردن فایل‌های بخش‌ها و ثبت قلاب‌ها.
	 */
	private function __construct() {
		$this->includes();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

		register_activation_hook( HJ_DOCTORS_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( HJ_DOCTORS_FILE, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * فایل‌های بخش‌های ۲ تا ۴ را بارگذاری می‌کند.
	 *
	 * هر فایل که روی دیسک نباشد بی‌صدا رد می‌شود تا افزونه حتی با
	 * اکسترکت نصفه هم صفحه را سفید نکند.
	 */
	private function includes() {
		// escape hatch: با این ثابت هیچ ماژولی لود نمی‌شود تا اگر فایلی خطا
		// داد، سایت با حذف/جایگزینی همان فایل درست شود (صفحهٔ سفید نمی‌ماند).
		if ( defined( 'HJ_DOCTORS_SAFE_MODE' ) && HJ_DOCTORS_SAFE_MODE ) {
			return;
		}

		$files = array(
			'includes/roles.php',        // بخش ۴ — نقش پزشک، capabilityها و قفل پیشخوان.
			'includes/security.php',     // بخش ۴ — primitiveهای سقف حجم و rate limit.
			'includes/taxonomies.php',   // بخش ۲ — تخصص و شهر.
			'includes/cpt.php',          // بخش ۲ — نوع محتوای پزشک.
			'includes/acf-fields.php',   // بخش ۳ — زمینه‌های نمایه.
			'includes/dashboard.php',    // بخش ۴ — صفحهٔ /my-profile/ و شورت‌کد شروع.
			'includes/auth-google.php',  // بخش ۵ — پل ورود با گوگل (Nextend).
			'includes/profile-render.php', // بخش ۷ — صفحهٔ عمومی تک‌پزشک.
		);

		foreach ( $files as $file ) {
			$path = hj_doctors_path( $file );
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * فعال‌سازی: نقش‌ها، capabilityها و rewriteها.
	 */
	public static function activate() {
		if ( class_exists( 'HJ_Doctors_Roles' ) ) {
			HJ_Doctors_Roles::sync_role();
		}
		if ( function_exists( 'hj_doctors_register_taxonomies' ) ) {
			hj_doctors_register_taxonomies();
		}
		if ( function_exists( 'hj_doctors_register_post_type' ) ) {
			hj_doctors_register_post_type();
		}
		if ( function_exists( 'hj_doctors_ensure_dashboard_page' ) ) {
			hj_doctors_ensure_dashboard_page();
		}

		flush_rewrite_rules();
		update_option( HJ_DOCTORS_VERSION_OPTION, HJ_DOCTORS_VERSION );
	}

	/**
	 * غیرفعال‌سازی: فقط cleanup مسیرها؛ داده‌ها دست‌نخورده می‌مانند.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * اگر نسخهٔ افزونه بالا رفته باشد، نقش‌ها/capabilityها همگام می‌شوند.
	 */
	public function maybe_upgrade() {
		if ( HJ_DOCTORS_VERSION === get_option( HJ_DOCTORS_VERSION_OPTION ) ) {
			return;
		}
		if ( class_exists( 'HJ_Doctors_Roles' ) ) {
			HJ_Doctors_Roles::sync_role();
		}
		update_option( HJ_DOCTORS_VERSION_OPTION, HJ_DOCTORS_VERSION );
	}

	/**
	 * بارگذاری ترجمهٔ فارسی.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'hamyar-doctors',
			false,
			dirname( plugin_basename( HJ_DOCTORS_FILE ) ) . '/languages'
		);
	}
}

/**
 * نقطهٔ ورود یگانهٔ افزونه.
 *
 * @return HJ_Doctors
 */
function hj_doctors() {
	return HJ_Doctors::instance();
}
add_action( 'plugins_loaded', 'hj_doctors' );

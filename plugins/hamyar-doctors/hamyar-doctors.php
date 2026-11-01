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
		$files = array(
			'includes/capabilities.php', // بخش ۴ — نقش و capabilityها.
			'includes/taxonomies.php',   // بخش ۲ — تخصص و شهر.
			'includes/cpt.php',          // بخش ۲ — نوع محتوای پزشک.
			'includes/acf-fields.php',   // بخش ۳ — زمینه‌های نمایه.
			'includes/security.php',     // بخش ۴ — دسترسی نقش پزشک.
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
		if ( function_exists( 'hj_doctors_install_roles' ) ) {
			hj_doctors_install_roles();
		}
		if ( function_exists( 'hj_doctors_register_taxonomies' ) ) {
			hj_doctors_register_taxonomies();
		}
		if ( function_exists( 'hj_doctors_register_post_type' ) ) {
			hj_doctors_register_post_type();
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
		if ( function_exists( 'hj_doctors_install_roles' ) ) {
			hj_doctors_install_roles();
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

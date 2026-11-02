<?php
/**
 * Plugin Name: همیار پزشک‌ها
 * Description: نوع محتوای «پزشک» با تخصص و شهر، زمینه‌های نمایه (JSON محلی ACF بدون Sync دستی) و دسترسی نقشِ پزشک فقط به نمایهٔ خودش؛ مستقل از قالب فرزند و بی‌صدا در debug.log.
 * Version: 0.3.1
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

define( 'HJ_DOCTORS_VERSION', '0.3.1' );
define( 'HJ_DOCTORS_FILE', __FILE__ );
define( 'HJ_DOCTORS_DIR', plugin_dir_path( __FILE__ ) );
define( 'HJ_DOCTORS_URL', plugin_dir_url( __FILE__ ) );
define( 'HJ_DOCTORS_CPT', 'doctor' );
define( 'HJ_DOCTORS_ROLE', 'doctor' );
define( 'HJ_DOCTORS_VERSION_OPTION', 'hj_doctors_version' );

/* -------------------------------------------------------------------------
 * بخش ۱: helperها
 * ---------------------------------------------------------------------- */
if ( ! function_exists( 'hj_doctors_path' ) ) {
	function hj_doctors_path( $rel = '' ) {
		return HJ_DOCTORS_DIR . ltrim( (string) $rel, '/' );
	}
}

if ( ! function_exists( 'hj_doctors_url' ) ) {
	function hj_doctors_url( $rel = '' ) {
		return HJ_DOCTORS_URL . ltrim( (string) $rel, '/' );
	}
}

if ( ! function_exists( 'hj_to_en_digits' ) ) {
	function hj_to_en_digits( $text ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = range( 0, 9 );
		return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $text );
	}
}

if ( ! function_exists( 'hj_doctors_default_photo_url' ) ) {
	function hj_doctors_default_photo_url() {
		$rel  = 'assets/img/doctor-default.jpg';
		$path = hj_doctors_path( $rel );
		return is_readable( $path ) ? hj_doctors_url( $rel ) : '';
	}
}

if ( ! function_exists( 'hj_doctors_log' ) ) {
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
 * کلاس اصلی
 * ---------------------------------------------------------------------- */
final class HJ_Doctors {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	private function includes() {
		if ( defined( 'HJ_DOCTORS_SAFE_MODE' ) && HJ_DOCTORS_SAFE_MODE ) {
			return;
		}

		// ترتیب اصلاح شده: هسته‌ها (cpt و tax) اول، سپس ماژول‌های جدید، سپس بقیه
		$files = array(
			'includes/roles.php',
			'includes/security.php',
			'includes/taxonomies.php',            // ثبت تاکسونومی‌ها
			'includes/cpt.php',                   // ثبت پست‌تایپ جراح (باید قبل از Seeder باشد)

			// ماژول‌های ارتقایافته جدید (بدون تکرار)
			'includes/iran-locations-seeder.php',
			'includes/monetization-vip.php',
			'includes/tech-navigator.php',
			'includes/profile-render.php',

			// سایر ماژول‌های قبلی
			'includes/acf-fields.php',
			'includes/dashboard.php',
			'includes/member-account.php',
			'includes/magazine.php',
			'includes/review-admin.php',
			'includes/auth-google.php',
			'includes/archive-render.php',
			'includes/rating-bridge.php',
			'includes/search-filters.php',
			'includes/rest-api.php',
			'includes/shortcodes.php',
			'includes/embed.php',
		);

		foreach ( $files as $file ) {
			$path = hj_doctors_path( $file );
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}

		// بلوک تکراری در انتها حذف شد تا خطای Fatal Error و CPT not found رخ ندهد.
	}

	public static function activate() {
		self::instance();
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
		if ( function_exists( 'hj_member_ensure_account_page' ) ) {
			hj_member_ensure_account_page();
		}
		flush_rewrite_rules();
		update_option( HJ_DOCTORS_VERSION_OPTION, HJ_DOCTORS_VERSION );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function maybe_upgrade() {
		if ( HJ_DOCTORS_VERSION === get_option( HJ_DOCTORS_VERSION_OPTION ) ) {
			return;
		}
		if ( class_exists( 'HJ_Doctors_Roles' ) ) {
			HJ_Doctors_Roles::sync_role();
		}
		if ( function_exists( 'hj_doctors_ensure_dashboard_page' ) ) {
			hj_doctors_ensure_dashboard_page();
		}
		if ( function_exists( 'hj_member_ensure_account_page' ) ) {
			hj_member_ensure_account_page();
		}
		update_option( HJ_DOCTORS_VERSION_OPTION, HJ_DOCTORS_VERSION );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'hamyar-doctors',
			false,
			dirname( plugin_basename( HJ_DOCTORS_FILE ) ) . '/languages'
		);
	}
}

function hj_doctors() {
	return HJ_Doctors::instance();
}
add_action( 'plugins_loaded', 'hj_doctors' );

register_activation_hook( HJ_DOCTORS_FILE, array( 'HJ_Doctors', 'activate' ) );
register_deactivation_hook( HJ_DOCTORS_FILE, array( 'HJ_Doctors', 'deactivate' ) );

<?php
/**
 * Plugin Name: همیار جراحی — دستیار نشانه‌ها (پیش‌نمایش)
 * Description: برگهٔ مستقل و غیرعمومی برای اجرای «دستیار نشانه‌ها» روی موتور مستقل پروژه. بستهٔ دانش و موتور از داخل همین افزونه بارگذاری می‌شوند و پاسخ‌های کاربر هیچ‌گاه به سرور فرستاده یا ذخیره نمی‌شوند.
 * Version: 0.1.15
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: همیار جراحی
 * License: GPL-2.0-or-later
 * Text Domain: hamyar-symptom-checker
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HJ_SC_VERSION', '0.1.15' );
define( 'HJ_SC_FILE', __FILE__ );
define( 'HJ_SC_DIR', plugin_dir_path( __FILE__ ) );
define( 'HJ_SC_URL', plugin_dir_url( __FILE__ ) );
define( 'HJ_SC_PAGE_OPTION', 'hj_symptom_checker_page_id' );
define( 'HJ_SC_PAGE_SLUG', 'symptom-checker' );
define( 'HJ_SC_ACTIVATION_PENDING', 'hj_sc_activation_pending' );
define( 'HJ_SC_LAST_ERROR', 'hj_sc_last_error' );

/**
 * Record the last error this plugin hit while setting itself up.
 *
 * It contains only plugin/diagnostic data (message, file, line, versions).
 * Nothing about what a visitor answers is ever stored anywhere.
 *
 * @param string $message Human readable message.
 * @param array  $extra   Extra context merged into the stored record.
 */
function hjsc_record_error( $message, $extra = array() ) {
	$record = array_merge(
		array(
			'message'        => (string) $message,
			'time'           => gmdate( 'c' ),
			'plugin_version' => HJ_SC_VERSION,
			'php'            => PHP_VERSION,
			'wp'             => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'missing_files'  => hjsc_missing_files(),
		),
		is_array( $extra ) ? $extra : array()
	);
	update_option( HJ_SC_LAST_ERROR, $record );
}

/**
 * Shipping files that are not on disk. A partially extracted ZIP is the most
 * common reason a plugin "fails to activate on this site" while it works
 * everywhere else, so the plugin can name the missing file itself.
 *
 * @return string[] Relative paths.
 */
function hjsc_missing_files() {
	$expected = array(
		'hamyar-symptom-checker.php',
		'README.md',
		'includes/admin.php',
		'includes/bundle-source.php',
		'includes/mapping.php',
		'includes/page.php',
		'includes/settings.php',
		'templates/checker-page.php',
		'assets/README.md',
		'assets/SOURCES.json',
		'assets/data/knowledge-bundle.v3.json',
		'assets/engine/data/evidence-map.v1.json',
		'assets/engine/src/engine.js',
		'assets/engine/src/evidence.js',
		'assets/engine/src/validate-bundle.js',
		'assets/engine/src/errors.js',
		'assets/ui/app.js',
		'assets/ui/age-gate.js',
		'assets/ui/view-model.js',
		'assets/ui/question-help.js',
		'assets/ui/styles.css',
		'assets/ui/anatomy-hero.jpg',
	);
	$missing = array();
	foreach ( $expected as $relative ) {
		if ( ! is_readable( HJ_SC_DIR . $relative ) ) {
			$missing[] = $relative;
		}
	}
	return $missing;
}

/**
 * Capture a fatal that happens while the activation request is still running.
 *
 * A real fatal cannot be caught by try/catch, so a shutdown handler is the only
 * way to leave the administrator a message they can forward instead of a blank
 * "the plugin could not be activated" screen.
 */
function hjsc_capture_activation_fatal() {
	$error = error_get_last();
	if ( ! $error || ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
		return;
	}
	if ( ! get_option( HJ_SC_ACTIVATION_PENDING ) ) {
		return;
	}
	hjsc_record_error(
		'fatal: ' . $error['message'],
		array(
			'file' => $error['file'],
			'line' => $error['line'],
		)
	);
}

/**
 * Plain-text report for the administrator: versions, missing files, last error.
 *
 * @return string
 */
function hjsc_diagnostics_text() {
	$error = get_option( HJ_SC_LAST_ERROR, array() );
	$lines = array(
		'plugin: ' . HJ_SC_VERSION,
		'bundle: ' . ( function_exists( 'hjsc_bundle_version' ) ? hjsc_bundle_version() : '?' ),
		'php: ' . PHP_VERSION,
		'wp: ' . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '?' ),
	);
	if ( is_array( $error ) && ! empty( $error['message'] ) ) {
		$lines[] = 'error: ' . $error['message'];
		if ( ! empty( $error['file'] ) ) {
			$lines[] = 'at: ' . $error['file'] . ':' . ( $error['line'] ?? 0 );
		}
	}
	$missing = hjsc_missing_files();
	if ( $missing ) {
		$lines[] = 'missing: ' . implode( ', ', $missing );
	}
	return implode( "\n", $lines );
}

/**
 * Show the report only to administrators, and only when something went wrong.
 */
function hjsc_activation_notice() {
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$error   = get_option( HJ_SC_LAST_ERROR, array() );
	$missing = hjsc_missing_files();
	if ( empty( $error['message'] ) && ! $missing ) {
		return;
	}
	// Self-heal: once the page exists and every file is there, the old message
	// is history and must not nag the administrator forever.
	if ( ! $missing && function_exists( 'hjsc_page_id' ) && hjsc_page_id() > 0 ) {
		delete_option( HJ_SC_LAST_ERROR );
		return;
	}

	echo '<div class="notice notice-error"><p><strong>';
	echo esc_html( 'دستیار نشانه‌ها: راه‌اندازی کامل نشد' );
	echo '</strong></p><p>';
	echo esc_html( 'این متن را برای پیگیری بفرستید؛ نیازی به حذف هیچ افزونهٔ دیگری نیست.' );
	echo '</p><pre style="white-space:pre-wrap;direction:ltr;text-align:left">';
	echo esc_html( hjsc_diagnostics_text() );
	echo '</pre></div>';
}
add_action( 'admin_notices', 'hjsc_activation_notice' );

/*
 * Load the two include files through a guard.
 *
 * A ZIP that was only partly extracted (or a file removed by a security plugin)
 * must not turn into "the plugin triggered a fatal error": the plugin loads,
 * says which file is missing, and the rest of the site keeps working.
 */
$hj_sc_include_files = array( 'includes/admin.php', 'includes/bundle-source.php', 'includes/mapping.php', 'includes/page.php', 'includes/settings.php' );
$hj_sc_include_missing = array();
foreach ( $hj_sc_include_files as $hj_sc_relative ) {
	$hj_sc_path = HJ_SC_DIR . $hj_sc_relative;
	if ( is_readable( $hj_sc_path ) ) {
		require_once $hj_sc_path;
	} else {
		$hj_sc_include_missing[] = $hj_sc_relative;
	}
}
unset( $hj_sc_include_files, $hj_sc_include_missing, $hj_sc_relative, $hj_sc_path );

if ( ! function_exists( 'hjsc_activate' ) ) {
	/**
	 * Fallback so activation can never call an undefined function: it records
	 * why the page could not be created instead of taking the site down.
	 */
	function hjsc_activate() {
		hjsc_record_error( 'includes/page.php is missing, the page was not created' );
	}
}

register_activation_hook( __FILE__, 'hjsc_activate' );
add_action( 'admin_notices', 'hjsc_activation_notice' );

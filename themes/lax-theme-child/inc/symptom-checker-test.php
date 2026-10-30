<?php
/**
 * Symptom checker: test space at /symptom-checker-test/.
 *
 * Not linked from navigation, noindex, and shown with a permanent warning.
 * Data: data/symptom-checker/*.json, built by tools/build-symptom-db.py.
 *
 * @package Lax_Theme_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request is the test space URL.
 *
 * @return bool
 */
function hj_sc_is_test_request() {
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
	return '/symptom-checker-test' === untrailingslashit( $path );
}

/**
 * Serve the test page before WordPress resolves a 404.
 */
function hj_sc_template_redirect() {
	if ( ! hj_sc_is_test_request() ) {
		return;
	}
	$GLOBALS['hj_sc_test_page'] = true;
	status_header( 200 );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	include get_stylesheet_directory() . '/symptom-checker-test.php';
	exit;
}
add_action( 'template_redirect', 'hj_sc_template_redirect', 1 );

/**
 * Keep the test page out of search engines.
 */
function hj_sc_noindex_meta() {
	if ( ! empty( $GLOBALS['hj_sc_test_page'] ) ) {
		echo "<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
	}
}
add_action( 'wp_head', 'hj_sc_noindex_meta', 1 );

/**
 * Assets for the test page only.
 */
function hj_sc_enqueue_assets() {
	if ( empty( $GLOBALS['hj_sc_test_page'] ) ) {
		return;
	}
	$uri = get_stylesheet_directory_uri();
	wp_enqueue_style( 'hj-symptom-checker', $uri . '/assets/css/symptom-checker.css', array(), LAX_THEME_CHILD_VERSION );
	wp_enqueue_script( 'hj-symptom-engine', $uri . '/assets/js/symptom-checker-engine.js', array(), LAX_THEME_CHILD_VERSION, true );
	wp_enqueue_script( 'hj-symptom-ui', $uri . '/assets/js/symptom-checker-ui.js', array( 'hj-symptom-engine' ), LAX_THEME_CHILD_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'hj_sc_enqueue_assets', 20 );

/**
 * Emergency number shown by the checker. Set with the filter `hj_emergency_number`
 * or the theme option `hj_emergency_number`. Persian digits are kept as Persian.
 *
 * @return string
 */
function hj_sc_emergency_number() {
	$raw = get_theme_mod( 'hj_emergency_number', '۱۱۵' );
	$raw = apply_filters( 'hj_emergency_number', $raw );
	return hj_sc_sanitize_emergency_number( $raw );
}

/**
 * Keep only digits (Persian ۰-۹, Arabic ٠-٩, or Latin 0-9) and show them in Persian.
 * Falls back to ۱۱۵ when nothing usable remains. Never strips a valid Persian digit.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function hj_sc_sanitize_emergency_number( $value ) {
	$map = array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	);
	$latin = strtr( (string) $value, $map );
	$digits = preg_replace( '/[^0-9]/', '', $latin );
	if ( '' === $digits || strlen( $digits ) > 6 ) {
		return '۱۱۵';
	}
	return strtr( $digits, array_flip( array_slice( $map, 0, 10, true ) ) );
}

/**
 * Counts for the page header. Read from the generated data; zeros when missing.
 *
 * @return array{diseases:int,rankable:int,tags:int,redflags:int}
 */
function hj_sc_stats() {
	$base  = get_stylesheet_directory() . '/data/symptom-checker/';
	$stats = array( 'diseases' => 0, 'rankable' => 0, 'tags' => 0, 'redflags' => 0 );
	$dis   = is_readable( $base . 'diseases.json' ) ? json_decode( (string) file_get_contents( $base . 'diseases.json' ), true ) : null;
	$voc   = is_readable( $base . 'vocab.json' ) ? json_decode( (string) file_get_contents( $base . 'vocab.json' ), true ) : null;
	if ( is_array( $dis ) && isset( $dis['diseases'] ) ) {
		$stats['diseases'] = count( $dis['diseases'] );
		$stats['rankable'] = count( array_filter( $dis['diseases'], function ( $d ) {
			return ! empty( $d['rankable'] );
		} ) );
	}
	if ( is_array( $voc ) ) {
		$stats['tags']     = isset( $voc['tags'] ) ? count( $voc['tags'] ) : 0;
		$stats['redflags'] = isset( $voc['redFlags'] ) ? count( $voc['redFlags'] ) : 0;
	}
	return $stats;
}

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

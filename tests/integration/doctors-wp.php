<?php
/**
 * Run ONLY on a disposable WordPress installation with hamyar-doctors active:
 * wp eval-file tests/integration/doctors-wp.php
 * Exercises real WP users, page resolution and Nextend hook callbacks (not OAuth).
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'HJ_Doctors_Auth_Google' ) ) {
	throw new RuntimeException( 'WordPress and the active hamyar-doctors plugin are required.' );
}
ob_start();
function hj_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
$old_page = get_option( 'hj_doctors_dashboard_page' );
$old_user = get_current_user_id();
$created = array();
$page_id = 0;
try {
	$subscriber = wp_insert_user( array( 'user_login' => 'hjtest-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$signup = wp_insert_user( array( 'user_login' => 'hjtest-signup-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$admin = wp_insert_user( array( 'user_login' => 'hjtest-admin-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	hj_test_assert( ! is_wp_error( $subscriber ) && ! is_wp_error( $signup ) && ! is_wp_error( $admin ), 'Could not create test users' );
	$created = array( $subscriber, $signup, $admin );
	unset( $_COOKIE['hj_doc_intent'] );
	$_COOKIE['hj_doc_intent'] = time() . '|invalid';
	do_action( 'nsl_login', $subscriber, 'google' );
	hj_test_assert( in_array( 'subscriber', get_userdata( $subscriber )->roles, true ), 'Invalid intent changed the role' );
	unset( $_COOKIE['hj_doc_intent'] );
	do_action( 'nsl_login', $subscriber, 'google' );
	hj_test_assert( in_array( 'subscriber', get_userdata( $subscriber )->roles, true ), 'Normal login changed the role' );
	$ts = time();
	$_COOKIE['hj_doc_intent'] = $ts . '|' . hash_hmac( 'sha256', 'hj_doc_intent|' . $ts, wp_salt( 'auth' ) );
	do_action( 'nsl_login', $subscriber, 'facebook' );
	hj_test_assert( in_array( 'subscriber', get_userdata( $subscriber )->roles, true ), 'Non-Google login changed the role' );
	do_action( 'nsl_login', $subscriber, 'google' );
	hj_test_assert( in_array( 'doctor', get_userdata( $subscriber )->roles, true ), 'Google intent did not assign doctor' );
	do_action( 'nsl_register_new_user', $signup, 'google' );
	hj_test_assert( in_array( 'doctor', get_userdata( $signup )->roles, true ), 'Google registration did not assign doctor' );
	do_action( 'nsl_login', $admin, 'google' );
	hj_test_assert( in_array( 'administrator', get_userdata( $admin )->roles, true ), 'Administrator was downgraded' );
	hj_test_assert( apply_filters( 'nsl_google_last_location_redirect', home_url( '/other/' ), '/other/' ) === hj_doctors_dashboard_url(), 'Nextend redirect failed' );

	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'HJ test dashboard', 'post_content' => 'Editor content', 'post_name' => 'hj-test-dashboard-' . wp_generate_password( 8, false ) ), true );
	hj_test_assert( ! is_wp_error( $page_id ), 'Could not create test page' );
	update_option( 'hj_doctors_dashboard_page', $page_id );
	hj_test_assert( hj_doctors_ensure_dashboard_page() === $page_id, 'Registered page was replaced' );
	hj_test_assert( get_post_field( 'post_content', $page_id ) === 'Editor content', 'Existing content was modified' );
	hj_test_assert( hj_doctors_dashboard_url() === get_permalink( $page_id ), 'Registered permalink ignored' );
	wp_set_current_user( $admin );
	ob_start(); HJ_Doctors_Dashboard::dashboard_page_notice(); $notice = ob_get_clean();
	hj_test_assert( strpos( $notice, '[hj_doctors_dashboard]' ) !== false, 'Missing shortcode warning not displayed' );
	wp_set_current_user( $subscriber );
	hj_test_assert( strpos( do_shortcode( '[hj_doctors_dashboard]' ), 'hj-dash-form' ) !== false, 'Doctor dashboard did not render' );
} finally {
	unset( $_COOKIE['hj_doc_intent'] );
	update_option( 'hj_doctors_dashboard_page', $old_page );
	wp_set_current_user( $old_user );
	if ( $page_id && ! is_wp_error( $page_id ) ) { wp_delete_post( $page_id, true ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $created as $id ) { wp_delete_user( $id ); }
}
ob_end_clean();
echo "Doctor WordPress integration checks passed.\n";

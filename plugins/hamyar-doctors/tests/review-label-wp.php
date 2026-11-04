<?php
/**
 * Destructive fixture: creates a doctor and vote table. Run ONLY in a fresh,
 * entirely disposable Playground WordPress/SQLite database, never live data.
 * Guards are additional barriers, not proof that an environment is disposable.
 */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST || ! defined( 'ABSPATH' ) || '/wordpress/' !== ABSPATH || ! class_exists( 'WP_SQLite_Driver' ) ) {
	exit( 'Disposable Playground SQLite required.' );
}
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes';
if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
	throw new Exception( 'Unexpected or existing vote table; refusing test.' );
}
function hj_review_label_check( $value, $message ) {
	if ( ! $value ) { throw new Exception( 'FAIL: ' . $message ); }
	echo 'PASS: ' . $message . "\n";
}
// Simulate the version-option mismatch seen after replacing 0.3.10 files.
// This does not reproduce the filesystem replacement itself or all DB effects.
wp_set_current_user( 1 );
do_action( 'init' );
$front = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic home', 'post_content' => 'Keep synthetic home intact' ), true );
hj_review_label_check( ! is_wp_error( $front ) && $front > 0, 'create synthetic front page' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
update_option( HJ_DOCTORS_VERSION_OPTION, '0.3.10' );
do_action( 'admin_init' );
hj_review_label_check( '0.3.12' === get_option( HJ_DOCTORS_VERSION_OPTION ), 'upgrade updates stored version' );
hj_review_label_check( (int) get_option( 'page_on_front' ) === $front && 'Keep synthetic home intact' === get_post( $front )->post_content, 'selected synthetic home remains unchanged' );
foreach ( array( 'hj_iran_locations_seeded_v1', 'hj_iran_locations_repaired_v2', 'hj_demo_doctors_seeded_v1' ) as $option ) {
	hj_review_label_check( false === get_option( $option ), 'upgrade does not create seed marker ' . $option );
}
hj_review_label_check( 0 === (int) wp_count_posts( 'doctor' )->publish, 'upgrade does not create published doctors' );
hj_review_label_check( false !== $wpdb->query( "CREATE TABLE `{$table}` (id INTEGER PRIMARY KEY, doctor_id INTEGER NOT NULL, rating INTEGER NOT NULL, comment TEXT, status VARCHAR(20))" ), 'create disposable fixture table' );
$doctor = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic doctor' ), true );
hj_review_label_check( ! is_wp_error( $doctor ) && $doctor > 0, 'create disposable profile' );
hj_review_label_check( 1 === $wpdb->insert( $table, array( 'id' => 1, 'doctor_id' => $doctor, 'rating' => 4, 'comment' => 'Synthetic approved comment', 'status' => 'approved' ) ), 'create approved synthetic vote' );
hj_review_label_check( 1 === $wpdb->insert( $table, array( 'id' => 2, 'doctor_id' => $doctor, 'rating' => 2, 'comment' => 'Synthetic pending comment', 'status' => 'pending' ) ), 'create pending synthetic vote' );
$before = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
$reviews = HJ_Profile_Render::public_reviews( $doctor );
$after = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
hj_review_label_check( 1 === count( $reviews ), 'pending review omitted in this status-column fixture' );
hj_review_label_check( 'نظر ثبت‌شده در سایت' === $reviews[0]['author'], 'neutral label on approved review' );
hj_review_label_check( 'Synthetic approved comment' === $reviews[0]['comment'] && 4 === $reviews[0]['rating'], 'review content and rating unchanged' );
hj_review_label_check( $before === $after, 'reading review leaves synthetic votes unchanged' );
file_put_contents( '/wordpress/hj-review-label-result.txt', "PASS: review label fixture\n" );

<?php
/**
 * Exploratory fixture, not a safety acceptance test. Creates and alters a vote table.
 * Run ONLY with an entirely disposable Playground WordPress/SQLite installation.
 * The flag and SQLite checks are extra barriers, NOT evidence an environment is safe.
 */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST || ! defined( 'ABSPATH' ) || '/wordpress/' !== ABSPATH || ! class_exists( 'WP_SQLite_Driver' ) ) {
	exit( 'Disposable Playground SQLite required.' );
}
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes';
if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) { throw new Exception( 'Unexpected table name' ); }
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
	throw new Exception( 'Fixture table already exists; refusing to modify it.' );
}
function hj_explore_check( $condition, $message ) {
	if ( ! $condition ) { throw new Exception( $message ); }
}
function hj_explore_clear_bridge_cache() {
	foreach ( array( 'schema_cache' => null, 'stats_mem' => array() ) as $key => $value ) {
		$property = new ReflectionProperty( 'HJ_Doctors_Rating_Bridge', $key );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}
}
function hj_explore_snapshot( $doctor_id ) {
	hj_explore_clear_bridge_cache();
	$schema  = HJ_Doctors_Rating_Bridge::schema();
	$stats   = HJ_Doctors_Rating_Bridge::stats( $doctor_id );
	$reviews = HJ_Profile_Render::public_reviews( $doctor_id );
	hj_explore_check( ! empty( $schema['ok'] ) && is_array( $stats ) && is_array( $reviews ), 'Fixture read failed' );
	return array(
		'status_detected' => ! empty( $schema['map']['status'] ),
		'approved_count'  => $stats['approved'],
		'pending_count'   => $stats['pending'],
		'public_review_count' => count( $reviews ),
		'public_label' => $reviews ? $reviews[0]['author'] : null,
	);
}
$created = $wpdb->query( "CREATE TABLE `{$table}` (id INTEGER PRIMARY KEY, doctor_id INTEGER NOT NULL, rating INTEGER NOT NULL, comment TEXT)" );
hj_explore_check( false !== $created, 'Fixture table creation failed: ' . $wpdb->last_error );
$doctor = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic test doctor' ), true );
hj_explore_check( ! is_wp_error( $doctor ) && $doctor > 0, 'Fixture doctor creation failed' );
hj_explore_check( 1 === $wpdb->insert( $table, array( 'id' => 1, 'doctor_id' => $doctor, 'rating' => 4, 'comment' => 'Synthetic review' ) ), 'Fixture insertion failed' );
$report = array( 'without_status' => hj_explore_snapshot( $doctor ) );
hj_explore_check( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN status VARCHAR(20)" ), 'Fixture alter failed' );
foreach ( array( 'pending', 'approved' ) as $state ) {
	hj_explore_check( false !== $wpdb->update( $table, array( 'status' => $state ), array( 'id' => 1 ) ), 'Fixture status update failed' );
	$report[ $state ] = hj_explore_snapshot( $doctor );
}
hj_explore_check( false !== $wpdb->update( $table, array( 'status' => null ), array( 'id' => 1 ) ), 'Fixture null update failed' );
$report['null_status'] = hj_explore_snapshot( $doctor );
// Simulate a separately configured bridge table; profile reviews still use the
// default prefixed table. No claim is made about the real site's configuration.
$alt = $wpdb->prefix . 'hj_doctor_votes_fixture_alt';
hj_explore_check( preg_match( '/^[A-Za-z0-9_]+$/', $alt ) && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $alt ) ) !== $alt, 'Alternate fixture table already exists or invalid' );
hj_explore_check( false !== $wpdb->query( "CREATE TABLE `{$alt}` (id INTEGER PRIMARY KEY, doctor_id INTEGER NOT NULL, rating INTEGER NOT NULL, comment TEXT, status VARCHAR(20))" ), 'Alternate table creation failed' );
hj_explore_check( 1 === $wpdb->insert( $alt, array( 'id' => 1, 'doctor_id' => $doctor, 'rating' => 5, 'comment' => 'Synthetic alternate review', 'status' => 'approved' ) ), 'Alternate fixture insertion failed' );
add_filter( 'hj_rb_table_name', static function () use ( $alt ) { return $alt; } );
$report['custom_bridge_table'] = hj_explore_snapshot( $doctor );
file_put_contents( '/wordpress/hj-vote-behavior-exploration.json', wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo "DONE: disposable vote behavior observations written.\n";

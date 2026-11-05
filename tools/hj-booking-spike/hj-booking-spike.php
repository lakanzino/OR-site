<?php
/**
 * Plugin Name: HJ Booking — Disposable Slot Race Spike
 * Description: Synthetic uniqueness experiment, never a booking system or production plugin.
 * Version: 0.0.1
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || true !== HJ_BOOKING_DISPOSABLE_FIXTURE || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) {
	exit( 'Disposable CI only.' );
}

/** Synthetic table only: one fixed-duration slot, no personal data or user-facing hooks. */
function hj_booking_spike_install() {
	global $wpdb;
	$table = $wpdb->prefix . 'hj_booking_spike';
	return $wpdb->query( "CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, doctor_id BIGINT UNSIGNED NOT NULL, starts_at_utc DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY doctor_start (doctor_id, starts_at_utc)) ENGINE=InnoDB" );
}

/** Result 'unavailable' includes duplicate or DB failure; no retry or success assumption. */
function hj_booking_spike_reserve( $doctor_id, $utc_start ) {
	global $wpdb;
	if ( ! is_int( $doctor_id ) || $doctor_id < 1 || ! is_string( $utc_start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $utc_start ) ) { return 'invalid'; }
	$previous = $wpdb->suppress_errors( true );
	try {
		$result = $wpdb->insert( $wpdb->prefix . 'hj_booking_spike', array( 'doctor_id' => $doctor_id, 'starts_at_utc' => $utc_start ), array( '%d', '%s' ) );
		return 1 === $result ? 'reserved' : 'unavailable';
	} finally { $wpdb->suppress_errors( $previous ); }
}

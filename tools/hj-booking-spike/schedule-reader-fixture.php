<?php
/** Real WP metadata + mock ACF reader tests, disposable CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
$mode = $argv[1] ?? '';
if ( ! in_array( $mode, array( 'acf', 'fallback' ), true ) ) { exit( 1 ); }
if ( $mode === 'acf' ) {
	function get_field( $selector, $doctor ) {
		if ( $selector !== 'working_hours' ) { return false; }
		return get_post_meta( $doctor, 'fixture_acf_result', true );
	}
}
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
register_post_type( 'doctor', array( 'public' => true ) );
require __DIR__ . '/schedule-reader-experiment.php';
function reader_expect( $v, $msg ) { if ( ! $v ) { throw new RuntimeException( $msg ); } echo "PASS: $msg\n"; }
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic reader ' . $mode ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Could not create fixture doctor' ); }
$rows = array( array( 'day_of_week' => 'wednesday', 'is_closed' => '0', 'start_time' => '09:00', 'end_time' => '12:00:00' ), array( 'day_of_week' => 'thursday', 'is_closed' => '1', 'start_time' => '', 'end_time' => '' ) );
$expected = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ), array( 'day_of_week' => 'thursday', 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ) );
if ( $mode === 'acf' ) {
	update_post_meta( $doc, 'fixture_acf_result', $rows );
	$r = hj_spike_read_schedule( $doc );
	reader_expect( $r['status'] === 'ok' && $r['rows'] === $expected, 'mock get_field yields normalized schedule' );
	update_post_meta( $doc, 'fixture_acf_result', array( array( 'day_of_week' => 'wednesday', 'is_closed' => '0', 'start_time' => '99:00', 'end_time' => '12:00' ) ) );
	reader_expect( hj_spike_read_schedule( $doc )['status'] === 'invalid_schedule', 'mock get_field malformed data fails closed' );
	update_post_meta( $doc, 'fixture_acf_result', array() );
	reader_expect( hj_spike_read_schedule( $doc )['status'] === 'invalid_schedule', 'mock get_field empty data fails closed' );
} else {
	update_post_meta( $doc, 'working_hours', $rows );
	$r = hj_spike_read_schedule( $doc );
	reader_expect( $r['status'] === 'ok' && $r['rows'] === $expected, 'serialized fallback array' );
	update_post_meta( $doc, 'working_hours', '2' );
	foreach ( $rows as $i => $row ) { foreach ( $row as $name => $value ) { update_post_meta( $doc, 'working_hours_' . $i . '_' . $name, $value ); } }
	$r = hj_spike_read_schedule( $doc );
	reader_expect( $r['status'] === 'ok' && $r['rows'] === $expected, 'ACF-style counted metadata without get_field' );
	delete_post_meta( $doc, 'working_hours_0_start_time' );
	reader_expect( hj_spike_read_schedule( $doc )['status'] === 'invalid_schedule', 'missing repeater subfield fails closed' );
	update_post_meta( $doc, 'working_hours', $rows );
	add_post_meta( $doc, 'working_hours', $rows );
	reader_expect( hj_spike_read_schedule( $doc )['status'] === 'invalid_schedule', 'duplicate parent metadata fails closed' );
}
reader_expect( hj_spike_normalize_schedule( array( $rows[0], $rows[0] ) )['status'] === 'invalid_schedule', 'duplicate weekday fails closed' );
reader_expect( hj_spike_normalize_schedule( array( array_merge( $rows[0], array( 'is_closed' => 'garbage' ) ) ) )['status'] === 'invalid_schedule', 'invalid closed flag fails closed' );

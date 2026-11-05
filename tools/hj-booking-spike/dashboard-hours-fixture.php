<?php
/** Mock dashboard + snapshot experiment in disposable CI, not real ACF. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
function get_field( $key, $id ) {
	if ( $key !== 'working_hours' ) { return false; }
	$count = get_post_meta( $id, 'working_hours', true );
	if ( is_array( $count ) ) { return $count; }
	if ( ! is_scalar( $count ) || ! preg_match( '/^[0-7]$/D', (string) $count ) ) { return false; }
	$rows = array();
	for ( $i = 0; $i < (int) $count; $i++ ) {
		$row = array();
		foreach ( array( 'day_of_week', 'is_closed', 'start_time', 'end_time' ) as $name ) { $row[$name] = get_post_meta( $id, 'working_hours_' . $i . '_' . $name, true ); }
		$rows[] = $row;
	}
	return $rows;
}
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/policy-validation.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/schedule-reader-experiment.php'; require __DIR__ . '/schedule-write-experiment.php'; require __DIR__ . '/snapshot-experiment.php'; require __DIR__ . '/dashboard-hours-experiment.php';
function dash_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $patient = get_user_by( 'login', 'synthetic_patient' );
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic dashboard', 'post_author' => $doctor->ID ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'profile failed' ); }
update_option( 'timezone_string', 'Asia/Tehran' );
update_post_meta( $doc, 'working_hours_external', 'preserve-me' );
update_post_meta( $doc, '_working_hours', 'fixture_reference' );
$db = hj_interval_connection();
try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $doc, 30, 5, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
$now = strtotime( '2030-01-01 UTC' ); $key = static function ( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); };
$open = array( 'wednesday' => array( 'start' => '09:00', 'end' => '12:00' ) );
$closed = array( 'wednesday' => array( 'closed' => '1' ) );
$bad = array( 'wednesday' => array( 'start' => '12:00', 'end' => '09:00' ) );
wp_set_current_user( $patient->ID );
dash_expect( hj_spike_dashboard_save_hours( $doc, $open, 0 )['status'] === 'forbidden', 'patient cannot save hours' );
wp_set_current_user( $doctor->ID );
dash_expect( hj_spike_dashboard_save_hours( $doc, $bad, 0 )['status'] === 'invalid', 'invalid hour range rejected' );
$r = hj_spike_dashboard_save_hours( $doc, $open, 0 );
dash_expect( $r['status'] === 'saved' && $r['revision'] === 1, 'fallback storage and revision one saved' );
$read = hj_spike_read_schedule( $doc );
dash_expect( $read['status'] === 'ok' && $read['rows'][0]['start_time'] === '09:00:00', 'fallback array readable by mock' );
wp_set_current_user( $patient->ID );
dash_expect( hj_spike_snapshot_create( $doc, '2030-01-02 05:30:00', $key( 18001 ), '09123456789', '1234567891', $now )['status'] === 'created', 'patient books fallback snapshot' );
wp_set_current_user( $doctor->ID );
$r = hj_spike_dashboard_save_hours( $doc, $closed, 1, 'acf_style' );
dash_expect( $r['status'] === 'saved' && $r['revision'] === 2, 'ACF-style storage and revision two saved' );
$read = hj_spike_read_schedule( $doc );
dash_expect( $read['status'] === 'ok' && $read['rows'][0]['is_closed'] === 1, 'mock ACF sees closed day' );
dash_expect( get_post_meta( $doc, 'working_hours_external', true ) === 'preserve-me' && get_post_meta( $doc, '_working_hours', true ) === 'fixture_reference', 'unrelated metadata and field reference retained' );
wp_set_current_user( $patient->ID );
dash_expect( hj_spike_snapshot_create( $doc, '2030-01-02 06:05:00', $key( 18002 ), '09123456789', '1234567891', $now )['status'] === 'invalid_slot', 'closed snapshot blocks booking' );
wp_set_current_user( $doctor->ID );
dash_expect( hj_spike_dashboard_save_hours( $doc, $open, 1, 'acf_style' )['status'] === 'revision_conflict', 'stale revision rejected before writes' );
dash_expect( hj_spike_read_schedule( $doc )['rows'][0]['is_closed'] === 1, 'stale save left metadata untouched' );
dash_expect( hj_spike_dashboard_save_hours( $doc, $open, 2, 'acf_style' )['status'] === 'saved', 'reopen at revision three' );
wp_set_current_user( $patient->ID );
dash_expect( hj_spike_snapshot_create( $doc, '2030-01-02 06:05:00', $key( 18003 ), '09123456789', '1234567891', $now )['status'] === 'created', 'booking sees reopened snapshot' );
wp_set_current_user( $doctor->ID );
dash_expect( hj_spike_dashboard_save_hours( $doc, $closed, 3, 'acf_style', 'disconnect_before_snapshot' )['status'] === 'unknown', 'connection killed between storage and snapshot reports unknown' );
dash_expect( hj_spike_read_schedule( $doc )['rows'][0]['is_closed'] === 0, 'connection kill rolled back intermediate storage changes' );
$db = hj_interval_connection();
try { $row = hj_interval_query( $db, 'SELECT revision FROM hjt_hjb_spike_schedules WHERE doctor_id=?', 'i', $doc ); } finally { $db->close(); }
dash_expect( (int) $row[0]['revision'] === 3, 'connection kill left snapshot revision unchanged' );
dash_expect( hj_spike_dashboard_save_hours( $doc, array(), 3, 'fallback' )['status'] === 'saved', 'empty schedule saved' );
wp_set_current_user( $patient->ID );
dash_expect( hj_spike_snapshot_create( $doc, '2030-01-02 06:40:00', $key( 18004 ), '09123456789', '1234567891', $now )['status'] === 'schedule_unsupported', 'empty snapshot fails closed' );

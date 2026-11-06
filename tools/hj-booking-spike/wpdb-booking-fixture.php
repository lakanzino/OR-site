<?php
/** CI-only AJAX booking adapter sharing the real dashboard port's lock and snapshot. Not production. */
if ( PHP_SAPI !== 'cli-server' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require __DIR__ . '/schedule-engine.php';
require __DIR__ . '/schedule-reader-experiment.php';
require __DIR__ . '/policy-validation.php';

function hj_ci_barrier( $name, $wait ) {
	if ( ! preg_match( '/^[a-z-]+[a-f0-9]{32}$/D', $name ) ) { throw new RuntimeException( 'Invalid fixture barrier' ); }
	$prefix = getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-test-' . $name;
	if ( false === file_put_contents( $prefix . '-ready', 'ready' ) ) { throw new RuntimeException( 'Fixture barrier signal failed' ); }
	if ( $wait ) {
		$until = microtime( true ) + 25;
		while ( ! is_file( $prefix . '-released' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
		if ( ! is_file( $prefix . '-released' ) ) { throw new RuntimeException( 'Fixture barrier timed out' ); }
	}
}
function hj_ci_sql( $db, $sql ) {
	$result = $db->query( $sql );
	if ( $result === false ) { throw new RuntimeException( 'Fixture SQL failure' ); }
	return $result;
}
function hj_ci_row( $db, $sql ) {
	$result = $db->get_row( $sql, ARRAY_A );
	if ( $db->last_error ) { throw new RuntimeException( 'Fixture SQL failure' ); }
	return $result;
}
function hj_ci_rows( $db, $sql ) {
	$result = $db->get_results( $sql, ARRAY_A );
	if ( $db->last_error || ! is_array( $result ) ) { throw new RuntimeException( 'Fixture SQL failure' ); }
	return $result;
}
/** Both dashboard and booking take this same mutex before reading capacity. */
function hj_ci_lock_doctor( $db, $doctor ) {
	return hj_ci_row( $db, $db->prepare( 'SELECT * FROM hjt_hjb_spike_doctors WHERE doctor_id=%d FOR UPDATE', $doctor ) );
}
function hj_ci_read_snapshot( $db, $doctor ) {
	$row = hj_ci_row( $db, $db->prepare( 'SELECT revision,rows_json,rows_hash FROM hjt_hjb_spike_schedules WHERE doctor_id=%d', $doctor ) );
	if ( ! $row || ! hash_equals( $row['rows_hash'], hash( 'sha256', $row['rows_json'] ) ) ) { return false; }
	$decoded = json_decode( $row['rows_json'], true );
	if ( $decoded === array() ) { return array( 'rows' => array(), 'revision' => (int) $row['revision'] ); }
	$normalized = hj_spike_normalize_schedule( $decoded );
	if ( $normalized['status'] !== 'ok' ) { return false; }
	return array( 'rows' => $normalized['rows'], 'revision' => (int) $row['revision'] );
}
function hj_ci_slots_locked( $db, $doctor, $config, $date ) {
	$snapshot = hj_ci_read_snapshot( $db, $doctor );
	if ( $snapshot === false ) { return false; }
	$occupied = hj_ci_rows( $db, $db->prepare( "SELECT starts_at,blocked_until,status FROM hjt_hjb_spike_appointments WHERE doctor_id=%d AND status IN ('pending','confirmed')", $doctor ) );
	// A fixed synthetic clock avoids dependence on the CI runner's wall time.
	$slots = hj_schedule_slots( $date, $snapshot['rows'], (int) $config['duration_minutes'], (int) $config['buffer_minutes'], $occupied, strtotime( '2030-01-01 UTC' ), 30 );
	return array( 'slots' => $slots, 'revision' => $snapshot['revision'] );
}
/** Same-key replay is checked before availability, so a committed result survives later closure. */
function hj_ci_booking_operation( $mode, $doctor, $date, $start = '', $key = '', $phone = '', $national = '', $fault = '' ) {
	$patient = get_current_user_id();
	if ( ! $patient || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	if ( ! preg_match( '/^2030-01-02$/D', $date ) ) { return array( 'status' => 'invalid' ); } // fixture scope, not a production rule
	if ( $mode === 'create' ) {
		$phone = hj_spike_phone( $phone ); $national = hj_spike_national_code( $national );
		if ( $phone === false || $national === false || ! preg_match( '/^[a-f0-9]{32}$/D', $key ) || ! preg_match( '/^2030-01-02 [0-2][0-9]:[0-5][0-9]:00$/D', $start ) ) { return array( 'status' => 'invalid' ); }
		$hash = hash_hmac( 'sha256', wp_json_encode( array( $doctor, $patient, $start, $phone, $national ) ), wp_salt( 'auth' ) );
	}
	$db = new HJ_CI_No_Reconnect_WPDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->suppress_errors( true ); $begun = false; $committing = false;
	try {
		hj_ci_sql( $db, 'START TRANSACTION' ); $begun = true;
		if ( $mode === 'create' && $fault === 'signal_before_lock' ) { hj_ci_barrier( 'booking-wait-' . $key, false ); }
		$config = hj_ci_lock_doctor( $db, $doctor );
		if ( ! $config ) { return array( 'status' => 'invalid_doctor' ); }
		if ( $mode === 'create' && $fault === 'hold_after_lock' ) { hj_ci_barrier( 'booking-hold-' . $key, true ); }
		if ( $mode === 'create' ) {
			$old = hj_ci_row( $db, $db->prepare( 'SELECT id,request_hash FROM hjt_hjb_spike_appointments WHERE request_key=%s', $key ) );
			if ( $old ) {
				return hash_equals( $old['request_hash'], $hash ) ? array( 'status' => 'replayed', 'id' => (int) $old['id'] ) : array( 'status' => 'idempotency_conflict' );
			}
		}
		$available = hj_ci_slots_locked( $db, $doctor, $config, $date );
		if ( $available === false ) { return array( 'status' => 'invalid_schedule' ); }
		if ( $mode === 'slots' ) { return array( 'status' => 'ok' ) + $available; }
		$start_ts = strtotime( $start . ' UTC' );
		$end = gmdate( 'Y-m-d H:i:s', $start_ts + (int) $config['duration_minutes'] * 60 );
		$blocked = gmdate( 'Y-m-d H:i:s', $start_ts + ( (int) $config['duration_minutes'] + (int) $config['buffer_minutes'] ) * 60 );
		if ( $start < $config['window_start'] || $end > $config['window_end'] || ! in_array( $start, $available['slots'], true ) ) { return array( 'status' => 'invalid_slot' ); }
		$n = $db->query( $db->prepare( "INSERT INTO hjt_hjb_spike_appointments (doctor_id,patient_id,request_key,request_hash,starts_at,ends_at,blocked_until,status) VALUES (%d,%d,%s,%s,%s,%s,%s,'pending')", $doctor, $patient, $key, $hash, $start, $end, $blocked ) );
		if ( $n !== 1 ) { throw new RuntimeException( 'Fixture insert failed' ); }
		$id = (int) $db->insert_id;
		hj_ci_sql( $db, $db->prepare( 'INSERT INTO hjt_hjb_spike_details (appointment_id,phone,national_code) VALUES (%d,%s,%s)', $id, $phone, $national ) );
		hj_ci_sql( $db, $db->prepare( 'INSERT INTO hjt_hjb_ci_booking_audit (appointment_id,revision) VALUES (%d,%d)', $id, $available['revision'] ) );
		if ( $fault === 'kill_before_commit' ) {
			$conn = (int) $db->get_var( 'SELECT CONNECTION_ID()' ); $killer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $killer->suppress_errors( true );
			try { if ( ! $conn || false === $killer->query( 'KILL CONNECTION ' . $conn ) ) { throw new RuntimeException( 'KILL failed' ); } } finally { $killer->close(); }
		}
		$committing = true;
		if ( false === $db->query( 'COMMIT' ) ) { return array( 'status' => 'unknown' ); }
		$begun = false;
		if ( $fault === 'disconnect_after_commit' ) {
			// Kill DB connection only after acknowledged COMMIT. A test-controlled
			// barrier lets the HTTP client close its socket before any response.
			$conn = (int) $db->get_var( 'SELECT CONNECTION_ID()' ); $killer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $killer->suppress_errors( true );
			try { if ( ! $conn || false === $killer->query( 'KILL CONNECTION ' . $conn ) ) { throw new RuntimeException( 'KILL failed' ); } } finally { $killer->close(); }
			$prefix = getenv( 'RUNNER_TEMP' ) . '/hj-booking-commit-' . $key;
			file_put_contents( $prefix . '-ready', 'committed' );
			$until = microtime( true ) + 20;
			while ( ! is_file( $prefix . '-released' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
			if ( ! is_file( $prefix . '-released' ) ) { throw new RuntimeException( 'Lost-response barrier timed out' ); }
		}
		return array( 'status' => 'created', 'id' => $id, 'revision' => $available['revision'] );
	} catch ( Throwable $e ) {
		return array( 'status' => $committing ? 'unknown' : 'error' );
	} finally {
		if ( $begun && ! $committing ) { $db->query( 'ROLLBACK' ); }
		$db->close();
	}
}
function hj_ci_booking_handler( $mode ) {
	check_ajax_referer( 'hj_ci_booking', 'nonce' );
	$doctor = isset( $_POST['doctor'] ) ? absint( $_POST['doctor'] ) : 0;
	$val = static function ( $key ) { return isset( $_POST[$key] ) && is_string( $_POST[$key] ) ? wp_unslash( $_POST[$key] ) : ''; };
	$result = hj_ci_booking_operation( $mode, $doctor, $val('date'), $val('start'), $val('key'), $val('phone'), $val('national'), $val('fault') );
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_hj_ci_slots', function () { hj_ci_booking_handler( 'slots' ); } );
add_action( 'wp_ajax_hj_ci_create', function () { hj_ci_booking_handler( 'create' ); } );
add_action( 'wp_ajax_hj_ci_booking_nonce', function () { wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_ci_booking' ) ) ); } );
add_action( 'wp_ajax_hj_ci_booking_audit', function () {
	$doctor = isset( $_POST['doctor'] ) ? absint( $_POST['doctor'] ) : 0;
	if ( ! current_user_can( 'edit_post', $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT a.id,a.request_key,a.starts_at,a.patient_id,b.revision,d.appointment_id AS details_id FROM hjt_hjb_spike_appointments a LEFT JOIN hjt_hjb_ci_booking_audit b ON a.id=b.appointment_id LEFT JOIN hjt_hjb_spike_details d ON d.appointment_id=a.id WHERE a.doctor_id=%d ORDER BY a.id', $doctor ), ARRAY_A );
	if ( $wpdb->last_error ) { wp_send_json_error( array(), 500 ); }
	wp_send_json_success( $rows );
} );

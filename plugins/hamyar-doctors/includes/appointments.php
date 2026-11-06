<?php
/** Optional booking backend. No routes, UI, or automatic migration are enabled here. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/appointment-schedule.php';

/** Transactions must never use wpdb's normal reconnect-and-replay behaviour. */
class HJ_Booking_Transaction_DB extends wpdb {
	public function check_connection( $allow_bail = true ) { return false; }
	public function booking_errno() { return $this->dbh instanceof mysqli ? mysqli_errno( $this->dbh ) : 0; }
}
function hj_booking_enabled() { return defined( 'HJ_BOOKING_ENABLED' ) && HJ_BOOKING_ENABLED === true; }
function hj_booking_table( $name ) {
	global $wpdb;
	$allowed = array( 'doctors', 'schedules', 'appointments', 'details', 'secretaries' );
	if ( ! in_array( $name, $allowed, true ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', $wpdb->prefix ) ) { throw new RuntimeException( 'Invalid booking table' ); }
	return $wpdb->prefix . 'hj_booking_' . $name;
}
/** Explicit admin-only migration. Does not run automatically or enable booking. */
function hj_booking_install_schema() {
	global $wpdb;
	if ( ! hj_booking_enabled() || ! current_user_can( 'manage_options' ) || get_class( $wpdb ) !== 'wpdb' ) { return false; }
	$version = (int) get_option( 'hj_booking_schema_version', 0 );
	if ( $version > 1 ) { return false; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$d = hj_booking_table( 'doctors' ); $s = hj_booking_table( 'schedules' ); $a = hj_booking_table( 'appointments' ); $p = hj_booking_table( 'details' ); $m = hj_booking_table( 'secretaries' );
	dbDelta( "CREATE TABLE $d (
 doctor_id bigint(20) unsigned NOT NULL,
 duration_minutes int NOT NULL DEFAULT 30,
 buffer_minutes int NOT NULL DEFAULT 5,
 enabled tinyint NOT NULL DEFAULT 0,
 PRIMARY KEY  (doctor_id)
) ENGINE=InnoDB $charset;" );
	dbDelta( "CREATE TABLE $s (
 doctor_id bigint(20) unsigned NOT NULL,
 revision bigint(20) unsigned NOT NULL,
 rows_json longtext NOT NULL,
 rows_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY  (doctor_id)
) ENGINE=InnoDB $charset;" );
	dbDelta( "CREATE TABLE $a (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 doctor_id bigint(20) unsigned NOT NULL,
 patient_id bigint(20) unsigned NOT NULL,
 request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 starts_at datetime NOT NULL,
 ends_at datetime NOT NULL,
 blocked_until datetime NOT NULL,
 schedule_revision bigint(20) unsigned NOT NULL,
 status varchar(20) NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY request_key (request_key),
 KEY doctor_start (doctor_id,starts_at),
 KEY patient_start (patient_id,starts_at)
) ENGINE=InnoDB $charset;" );
	dbDelta( "CREATE TABLE $p (
 appointment_id bigint(20) unsigned NOT NULL,
 phone varchar(24) NOT NULL,
 national_code char(10) NOT NULL,
 PRIMARY KEY  (appointment_id)
) ENGINE=InnoDB $charset;" );
	dbDelta( "CREATE TABLE $m (
 user_id bigint(20) unsigned NOT NULL,
 doctor_id bigint(20) unsigned NOT NULL,
 PRIMARY KEY  (user_id,doctor_id)
) ENGINE=InnoDB $charset;" );
	$required = array( $d => array( 'doctor_id','duration_minutes','buffer_minutes','enabled' ), $s => array( 'doctor_id','revision','rows_json','rows_hash' ), $a => array( 'id','doctor_id','patient_id','request_key','request_hash','starts_at','ends_at','blocked_until','schedule_revision','status' ), $p => array( 'appointment_id','phone','national_code' ), $m => array( 'user_id','doctor_id' ) );
	foreach ( $required as $table => $fields ) {
		$info = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
		if ( ! $info || strtoupper( $info['Engine'] ?? '' ) !== 'INNODB' || array_diff( $fields, $wpdb->get_col( "SHOW COLUMNS FROM `$table`" ) ) ) { return false; }
	}
	$indexes = $wpdb->get_results( "SHOW INDEX FROM `$a`", ARRAY_A ); $unique = false;
	foreach ( (array) $indexes as $index ) { if ( $index['Key_name'] === 'request_key' && (int) $index['Non_unique'] === 0 && $index['Column_name'] === 'request_key' ) { $unique = true; } }
	if ( ! $unique ) { return false; }
	update_option( 'hj_booking_schema_version', 1, false );
	return (int) get_option( 'hj_booking_schema_version' ) === 1;
}
function hj_booking_ready() {
	global $wpdb;
	return hj_booking_enabled() && (int) get_option( 'hj_booking_schema_version', 0 ) === 1 && get_class( $wpdb ) === 'wpdb';
}
function hj_booking_can_configure( $doctor ) {
	return is_user_logged_in() && get_post_type( $doctor ) === 'doctor' && function_exists( 'hj_doctors_user_can_edit_profile' ) && hj_doctors_user_can_edit_profile( $doctor );
}
function hj_booking_can_manage( $doctor ) {
	global $wpdb;
	if ( ! is_user_logged_in() || get_post_type( $doctor ) !== 'doctor' ) { return false; }
	if ( function_exists( 'hj_doctors_user_can_edit_profile' ) && hj_doctors_user_can_edit_profile( $doctor ) ) { return true; }
	if ( ! current_user_can( 'hj_manage_appointments' ) ) { return false; }
	$table = hj_booking_table( 'secretaries' );
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `$table` WHERE user_id=%d AND doctor_id=%d", get_current_user_id(), $doctor ) );
}
function hj_booking_query( $db, $sql ) {
	$r = $db->query( $sql );
	if ( $r === false ) { throw new RuntimeException( 'Booking query failed', $db->booking_errno() ); }
	return $r;
}
function hj_booking_row( $db, $sql ) {
	$r = $db->get_row( $sql, ARRAY_A );
	if ( $db->last_error ) { throw new RuntimeException( 'Booking query failed', $db->booking_errno() ); }
	return $r;
}
function hj_booking_rows( $db, $sql ) {
	$r = $db->get_results( $sql, ARRAY_A );
	if ( $db->last_error || ! is_array( $r ) ) { throw new RuntimeException( 'Booking query failed', $db->booking_errno() ); }
	return $r;
}
/** Test callbacks are only available when a server-side test constant is enabled. */
function hj_booking_test_event( $event, $db, $context ) {
	if ( defined( 'HJ_BOOKING_TESTING' ) && HJ_BOOKING_TESTING === true ) { do_action( 'hj_booking_test_event', $event, $db, $context ); }
}
/** Callback only performs DB operations; retries are allowed only before COMMIT. */
function hj_booking_transaction( $doctor, $operation, $callback ) {
	if ( ! hj_booking_ready() ) { return array( 'status' => 'unavailable' ); }
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$db = new HJ_Booking_Transaction_DB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->suppress_errors( true ); $begun = false; $committing = false;
		try {
			hj_booking_query( $db, 'SET SESSION innodb_lock_wait_timeout = 3' );
			hj_booking_query( $db, 'START TRANSACTION' ); $begun = true;
			$table = hj_booking_table( 'doctors' );
			hj_booking_test_event( 'before_lock', $db, array( 'operation' => $operation, 'doctor' => $doctor ) );
			// This insert itself is transactional and serializes initial creation.
			hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id) VALUES (%d) ON DUPLICATE KEY UPDATE doctor_id=doctor_id", $doctor ) );
			$config = hj_booking_row( $db, $db->prepare( "SELECT * FROM `$table` WHERE doctor_id=%d FOR UPDATE", $doctor ) );
			if ( ! $config ) { throw new RuntimeException( 'Doctor mutex missing' ); }
			hj_booking_test_event( 'after_lock', $db, array( 'operation' => $operation, 'doctor' => $doctor ) );
			$result = $callback( $db, $config );
			if ( ! is_array( $result ) || ! isset( $result['status'] ) ) { throw new RuntimeException( 'Invalid transaction result' ); }
			hj_booking_test_event( 'before_commit', $db, array( 'operation' => $operation, 'doctor' => $doctor, 'result' => $result ) );
			$committing = true;
			if ( false === $db->query( 'COMMIT' ) ) { return array( 'status' => 'unknown' ); }
			$begun = false;
			hj_booking_test_event( 'after_commit', $db, array( 'operation' => $operation, 'doctor' => $doctor, 'result' => $result ) );
			return $result;
		} catch ( Throwable $e ) {
			if ( $committing ) { return array( 'status' => 'unknown' ); }
			if ( ! in_array( $e->getCode(), array( 1205, 1213 ), true ) ) { return array( 'status' => 'error' ); }
		} finally {
			if ( $begun && ! $committing ) { $db->query( 'ROLLBACK' ); }
			$db->close();
		}
		if ( $attempt < 2 ) { usleep( 20000 * ( $attempt + 1 ) ); }
	}
	return array( 'status' => 'busy' );
}
function hj_booking_normalize_schedule( $input ) {
	if ( ! is_array( $input ) || count( $input ) > 7 ) { return false; }
	$days = array( 'saturday','sunday','monday','tuesday','wednesday','thursday','friday' ); $seen = array(); $result = array();
	foreach ( $input as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['day_of_week'], $row['is_closed'], $row['start_time'], $row['end_time'] ) ) { return false; }
		$day = $row['day_of_week']; $closed = $row['is_closed']; $start = $row['start_time']; $end = $row['end_time'];
		if ( ! is_string( $day ) || ! in_array( $day, $days, true ) || isset( $seen[$day] ) || ! in_array( $closed, array( 0,1,'0','1',false,true ), true ) || ! is_string( $start ) || ! is_string( $end ) ) { return false; }
		$seen[$day] = true; $closed = (int) $closed;
		if ( ! $closed ) {
			$pattern = '/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D';
			if ( ! preg_match( $pattern, $start ) || ! preg_match( $pattern, $end ) ) { return false; }
			if ( strlen( $start ) === 5 ) { $start .= ':00'; } if ( strlen( $end ) === 5 ) { $end .= ':00'; }
			if ( $start >= $end ) { return false; }
		} else { $start = ''; $end = ''; }
		$result[] = array( 'day_of_week' => $day, 'is_closed' => $closed, 'start_time' => $start, 'end_time' => $end );
	}
	return $result;
}
/** Called by dashboard after its existing nonce/ownership and form validation. */
function hj_booking_save_schedule( $doctor, $rows ) {
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	$normalized = hj_booking_normalize_schedule( $rows );
	if ( $normalized === false ) { return array( 'status' => 'invalid' ); }
	$json = wp_json_encode( $normalized ); if ( ! is_string( $json ) ) { return array( 'status' => 'invalid' ); }
	$hash = hash( 'sha256', $json );
	return hj_booking_transaction( $doctor, 'schedule', function ( $db ) use ( $doctor, $json, $hash ) {
		$table = hj_booking_table( 'schedules' );
		$old = hj_booking_row( $db, $db->prepare( "SELECT revision FROM `$table` WHERE doctor_id=%d FOR UPDATE", $doctor ) );
		$rev = $old ? (int) $old['revision'] + 1 : 1;
		if ( $old ) { $n = hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d", $rev, $json, $hash, $doctor ) ); }
		else { $n = hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id,revision,rows_json,rows_hash) VALUES (%d,%d,%s,%s)", $doctor, $rev, $json, $hash ) ); }
		if ( $n !== 1 ) { throw new RuntimeException( 'Schedule save failed' ); }
		return array( 'status' => 'saved', 'revision' => $rev );
	} );
}
/** Explicit doctor configuration, disabled until set. */
function hj_booking_configure( $doctor, $duration, $buffer, $enabled ) {
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! is_int( $duration ) || $duration < 1 || $duration > 240 || ! is_int( $buffer ) || $buffer < 0 || $buffer > 120 || ! is_bool( $enabled ) ) { return array( 'status' => 'invalid' ); }
	return hj_booking_transaction( $doctor, 'configure', function ( $db ) use ( $doctor, $duration, $buffer, $enabled ) {
		$table = hj_booking_table( 'doctors' );
		hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET duration_minutes=%d,buffer_minutes=%d,enabled=%d WHERE doctor_id=%d", $duration, $buffer, $enabled ? 1 : 0, $doctor ) );
		return array( 'status' => 'configured' );
	} );
}
function hj_booking_snapshot_locked( $db, $doctor ) {
	$table = hj_booking_table( 'schedules' );
	$row = hj_booking_row( $db, $db->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d", $doctor ) );
	if ( ! $row || ! hash_equals( $row['rows_hash'], hash( 'sha256', $row['rows_json'] ) ) ) { return false; }
	$data = json_decode( $row['rows_json'], true ); $rows = hj_booking_normalize_schedule( $data );
	return $rows === false ? false : array( 'rows' => $rows, 'revision' => (int) $row['revision'] );
}
function hj_booking_slots_locked( $db, $doctor, $config, $date ) {
	$snapshot = hj_booking_snapshot_locked( $db, $doctor );
	if ( $snapshot === false ) { return false; }
	$table = hj_booking_table( 'appointments' );
	$occupied = hj_booking_rows( $db, $db->prepare( "SELECT starts_at,blocked_until,status FROM `$table` WHERE doctor_id=%d AND status IN ('pending','confirmed')", $doctor ) );
	return array( 'slots' => hj_booking_compute_slots( $date, $snapshot['rows'], (int) $config['duration_minutes'], (int) $config['buffer_minutes'], $occupied, time(), 30 ), 'revision' => $snapshot['revision'] );
}
function hj_booking_slots( $doctor, $date ) {
	if ( ! is_user_logged_in() || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) { return array( 'status' => 'invalid' ); }
	return hj_booking_transaction( $doctor, 'slots', function ( $db, $config ) use ( $doctor, $date ) {
		if ( ! (int) $config['enabled'] ) { return array( 'status' => 'unavailable' ); }
		$r = hj_booking_slots_locked( $db, $doctor, $config, $date );
		return $r === false ? array( 'status' => 'invalid_schedule' ) : array( 'status' => 'ok' ) + $r;
	} );
}
function hj_booking_phone( $value ) {
	if ( ! is_string( $value ) ) { return false; }
	$s = function_exists( 'hj_to_en_digits' ) ? hj_to_en_digits( $value ) : $value;
	if ( str_starts_with( $s, '+98' ) ) { $s = '0' . substr( $s, 3 ); } elseif ( str_starts_with( $s, '0098' ) ) { $s = '0' . substr( $s, 4 ); }
	return preg_match( '/^09[0-9]{9}$/D', $s ) ? $s : false;
}
function hj_booking_national_code( $value ) {
	if ( ! is_string( $value ) ) { return false; }
	$s = function_exists( 'hj_to_en_digits' ) ? hj_to_en_digits( $value ) : $value;
	if ( ! preg_match( '/^[0-9]{10}$/D', $s ) || preg_match( '/^(\d)\1{9}$/D', $s ) ) { return false; }
	$sum = 0; for ( $i = 0; $i < 9; $i++ ) { $sum += (int) $s[$i] * ( 10 - $i ); }
	$r = $sum % 11; return (int) $s[9] === ( $r < 2 ? $r : 11 - $r ) ? $s : false;
}
/** Request key is random and stable across retries; caller must retain it after unknown. */
function hj_booking_create( $doctor, $start, $key, $phone, $national ) {
	$patient = get_current_user_id();
	if ( ! $patient || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	$phone = hj_booking_phone( $phone ); $national = hj_booking_national_code( $national );
	if ( $phone === false || $national === false || ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{32}$/D', $key ) || ! is_string( $start ) ) { return array( 'status' => 'invalid' ); }
	$utc = new DateTimeZone( 'UTC' ); $when = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start, $utc );
	if ( ! $when || $when->format( 'Y-m-d H:i:s' ) !== $start ) { return array( 'status' => 'invalid' ); }
	$date = $when->setTimezone( wp_timezone() )->format( 'Y-m-d' );
	$hash = hash_hmac( 'sha256', wp_json_encode( array( $doctor, $patient, $start, $phone, $national ) ), wp_salt( 'auth' ) );
	return hj_booking_transaction( $doctor, 'create', function ( $db, $config ) use ( $doctor, $patient, $start, $when, $key, $phone, $national, $hash, $date ) {
		$table = hj_booking_table( 'appointments' ); $details = hj_booking_table( 'details' );
		$existing = hj_booking_row( $db, $db->prepare( "SELECT id,request_hash FROM `$table` WHERE request_key=%s", $key ) );
		if ( $existing ) { return hash_equals( $existing['request_hash'], $hash ) ? array( 'status' => 'replayed', 'id' => (int) $existing['id'] ) : array( 'status' => 'idempotency_conflict' ); }
		if ( ! (int) $config['enabled'] ) { return array( 'status' => 'unavailable' ); }
		$available = hj_booking_slots_locked( $db, $doctor, $config, $date );
		if ( $available === false ) { return array( 'status' => 'invalid_schedule' ); }
		if ( ! in_array( $start, $available['slots'], true ) ) { return array( 'status' => 'invalid_slot' ); }
		$end = gmdate( 'Y-m-d H:i:s', $when->getTimestamp() + (int) $config['duration_minutes'] * 60 );
		$blocked = gmdate( 'Y-m-d H:i:s', $when->getTimestamp() + ( (int) $config['duration_minutes'] + (int) $config['buffer_minutes'] ) * 60 );
		$n = hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id,patient_id,request_key,request_hash,starts_at,ends_at,blocked_until,schedule_revision,status) VALUES (%d,%d,%s,%s,%s,%s,%s,%d,'pending')", $doctor, $patient, $key, $hash, $start, $end, $blocked, $available['revision'] ) );
		if ( $n !== 1 ) { throw new RuntimeException( 'Booking insert failed' ); }
		$id = (int) $db->insert_id;
		if ( 1 !== hj_booking_query( $db, $db->prepare( "INSERT INTO `$details` (appointment_id,phone,national_code) VALUES (%d,%s,%s)", $id, $phone, $national ) ) ) { throw new RuntimeException( 'Booking details insert failed' ); }
		return array( 'status' => 'created', 'id' => $id, 'revision' => $available['revision'] );
	} );
}
function hj_booking_change_status( $doctor, $id, $status ) {
	if ( ! hj_booking_can_manage( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! in_array( $status, array( 'confirmed', 'cancelled' ), true ) || ! is_int( $id ) || $id < 1 ) { return array( 'status' => 'invalid' ); }
	return hj_booking_transaction( $doctor, 'status', function ( $db ) use ( $doctor, $id, $status ) {
		$table = hj_booking_table( 'appointments' );
		$n = hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET status=%s WHERE doctor_id=%d AND id=%d AND status IN ('pending','confirmed')", $status, $doctor, $id ) );
		return array( 'status' => $n === 1 ? 'updated' : 'not_found' );
	} );
}

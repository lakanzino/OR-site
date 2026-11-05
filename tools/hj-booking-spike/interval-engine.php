<?php
/** Disposable CI-only interval-lock experiment. Not production code. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }

/** A new, non-persistent connection per transaction; no wpdb auto-reconnect. */
function hj_interval_connection() {
	mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
	$db = new mysqli( '127.0.0.1', DB_USER, DB_PASSWORD, DB_NAME, 3306 );
	$db->set_charset( 'utf8mb4' );
	$db->query( 'SET SESSION innodb_lock_wait_timeout = 3' );
	return $db;
}
function hj_interval_query( $db, $sql, $types = '', ...$args ) {
	$stmt = $db->prepare( $sql );
	try {
		if ( $types !== '' ) { $stmt->bind_param( $types, ...$args ); }
		$stmt->execute();
		if ( $stmt->field_count ) { return $stmt->get_result()->fetch_all( MYSQLI_ASSOC ); }
		return $stmt->affected_rows;
	} finally { $stmt->close(); }
}
function hj_interval_install() {
	$db = hj_interval_connection();
	try {
		$db->query( 'CREATE TABLE hjt_hjb_spike_doctors (doctor_id BIGINT UNSIGNED PRIMARY KEY, duration_minutes INT NOT NULL, buffer_minutes INT NOT NULL, window_start DATETIME NOT NULL, window_end DATETIME NOT NULL) ENGINE=InnoDB' );
		$db->query( "CREATE TABLE hjt_hjb_spike_appointments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, doctor_id BIGINT UNSIGNED NOT NULL, patient_id BIGINT UNSIGNED NOT NULL, request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, blocked_until DATETIME NOT NULL, status VARCHAR(20) NOT NULL, UNIQUE KEY request_key (request_key), KEY doctor_start (doctor_id, starts_at), KEY patient_start (patient_id, starts_at)) ENGINE=InnoDB" );
		$db->query( "CREATE TABLE hjt_hjb_spike_details (appointment_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, phone VARCHAR(24) NOT NULL, national_code CHAR(10) NOT NULL) ENGINE=InnoDB" );
	} finally { $db->close(); }
}
function hj_interval_timestamp( $s ) {
	if ( ! is_string( $s ) ) { return false; }
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $s, new DateTimeZone( 'UTC' ) );
	return $d && $d->format( 'Y-m-d H:i:s' ) === $s ? $d->getTimestamp() : false;
}
/** This transaction wrapper NEVER retries an ambiguous COMMIT or a disconnected connection. */
function hj_interval_transaction( $doctor_id, $callback ) {
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$db = null; $committing = false; $begun = false;
		try {
			$db = hj_interval_connection();
			$db->begin_transaction(); $begun = true;
			$rows = hj_interval_query( $db, 'SELECT * FROM hjt_hjb_spike_doctors WHERE doctor_id = ? FOR UPDATE', 'i', $doctor_id );
			if ( count( $rows ) !== 1 ) { $db->rollback(); $begun = false; return array( 'status' => 'invalid_doctor' ); }
			$result = $callback( $db, $rows[0] );
			$committing = true;
			$db->commit(); $begun = false;
			return $result;
		} catch ( mysqli_sql_exception $e ) {
			if ( $begun && ! $committing && $db ) { try { $db->rollback(); } catch ( Throwable $ignored ) {} }
			if ( $committing || in_array( $e->getCode(), array( 2006, 2013, 2055 ), true ) ) { return array( 'status' => 'unknown' ); }
			if ( in_array( $e->getCode(), array( 1205, 1213 ), true ) ) {
				if ( $attempt < 2 ) { usleep( 20000 * ( $attempt + 1 ) ); continue; }
				return array( 'status' => 'busy' );
			}
			throw $e;
		} finally { if ( $db ) { try { $db->close(); } catch ( Throwable $ignored ) {} } }
	}
	return array( 'status' => 'busy' );
}
/** All configuration writes and appointment state writes must use the same mutex. */
function hj_interval_configure( $doctor_id, $duration, $buffer, $start, $end ) {
	if ( $doctor_id < 1 || $duration < 1 || $duration > 240 || $buffer < 0 || $buffer > 120 || hj_interval_timestamp( $start ) === false || hj_interval_timestamp( $end ) === false || $start >= $end ) { return array( 'status' => 'invalid' ); }
	return hj_interval_transaction( $doctor_id, function ( $db ) use ( $doctor_id, $duration, $buffer, $start, $end ) {
		hj_interval_query( $db, 'UPDATE hjt_hjb_spike_doctors SET duration_minutes=?, buffer_minutes=?, window_start=?, window_end=? WHERE doctor_id=?', 'iissi', $duration, $buffer, $start, $end, $doctor_id );
		return array( 'status' => 'configured' );
	} );
}
function hj_interval_create( $doctor_id, $patient_id, $start, $key, $fault = '', $schedule_validator = null, $details = null ) {
	$start_ts = hj_interval_timestamp( $start );
	if ( ! is_int( $doctor_id ) || $doctor_id < 1 || ! is_int( $patient_id ) || $patient_id < 1 || $start_ts === false || ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{32}$/D', $key ) || ! in_array( $fault, array( '', 'disconnect_before_commit', 'lose_commit_response' ), true ) ) { return array( 'status' => 'invalid' ); }
	if ( $details !== null && ( ! is_array( $details ) || ! isset( $details['phone'], $details['national_code'] ) || ! is_string( $details['phone'] ) || ! is_string( $details['national_code'] ) ) ) { return array( 'status' => 'invalid' ); }
	$hash = hash_hmac( 'sha256', json_encode( array( $doctor_id, $patient_id, $start, $details ), JSON_THROW_ON_ERROR ), wp_salt( 'auth' ) );
	$result = hj_interval_transaction( $doctor_id, function ( $db, $config ) use ( $doctor_id, $patient_id, $start, $start_ts, $key, $hash, $fault, $schedule_validator, $details ) {
		$existing = hj_interval_query( $db, 'SELECT id, request_hash, status FROM hjt_hjb_spike_appointments WHERE request_key=?', 's', $key );
		if ( $existing ) {
			if ( ! hash_equals( $existing[0]['request_hash'], $hash ) ) { return array( 'status' => 'idempotency_conflict' ); }
			return array( 'status' => 'replayed', 'id' => (int) $existing[0]['id'], 'appointment_status' => $existing[0]['status'] );
		}
		$end = gmdate( 'Y-m-d H:i:s', $start_ts + (int) $config['duration_minutes'] * 60 );
		$blocked = gmdate( 'Y-m-d H:i:s', $start_ts + ( (int) $config['duration_minutes'] + (int) $config['buffer_minutes'] ) * 60 );
		if ( $start < $config['window_start'] || $end > $config['window_end'] ) { return array( 'status' => 'invalid_slot' ); }
		if ( $schedule_validator !== null && ( ! is_callable( $schedule_validator ) || ! $schedule_validator( $db, $config, $start ) ) ) { return array( 'status' => 'invalid_slot' ); }
		$overlap = hj_interval_query( $db, "SELECT id FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed') AND starts_at < ? AND blocked_until > ? LIMIT 1", 'iss', $doctor_id, $blocked, $start );
		if ( $overlap ) { return array( 'status' => 'overlap' ); }
		try {
			hj_interval_query( $db, "INSERT INTO hjt_hjb_spike_appointments (doctor_id, patient_id, request_key, request_hash, starts_at, ends_at, blocked_until, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')", 'iisssss', $doctor_id, $patient_id, $key, $hash, $start, $end, $blocked );
		} catch ( mysqli_sql_exception $e ) {
			// Another doctor may have committed the same globally unique key after
			// our snapshot. Do not reveal its owner or replay a different request.
			if ( $e->getCode() === 1062 ) { return array( 'status' => 'idempotency_conflict' ); }
			throw $e;
		}
		$id = $db->insert_id;
		if ( $details !== null ) {
			hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_details (appointment_id, phone, national_code) VALUES (?, ?, ?)', 'iss', $id, $details['phone'], $details['national_code'] );
		}
		if ( $fault === 'disconnect_before_commit' ) {
			// CI-only injection: close server connection after INSERT before COMMIT.
			$killer = hj_interval_connection();
			try { $killer->query( 'KILL CONNECTION ' . (int) $db->thread_id ); } finally { $killer->close(); }
		}
		return array( 'status' => 'created', 'id' => $id );
	} );
	// Simulate a lost response after successful COMMIT; retry MUST reuse the same key.
	if ( $fault === 'lose_commit_response' && $result['status'] === 'created' ) { return array( 'status' => 'unknown' ); }
	return $result;
}
function hj_interval_cancel( $doctor_id, $id ) {
	return hj_interval_transaction( $doctor_id, function ( $db ) use ( $doctor_id, $id ) {
		$n = hj_interval_query( $db, "UPDATE hjt_hjb_spike_appointments SET status='cancelled' WHERE doctor_id=? AND id=? AND status IN ('pending','confirmed')", 'ii', $doctor_id, $id );
		return array( 'status' => $n === 1 ? 'cancelled' : 'not_found' );
	} );
}

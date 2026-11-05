<?php
/** Integrated access/schedule experiment. Not a production API or HTTP endpoint. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }
/** Caller identity always comes from WordPress, never from a submitted patient ID. */
function hj_secured_create( $doctor, $start, $key, $phone, $national, $now ) {
	$patient = get_current_user_id();
	$phone = hj_spike_phone( $phone ); $national = hj_spike_national_code( $national );
	if ( ! $patient || ! get_userdata( $patient ) || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	if ( $phone === false || $national === false ) { return array( 'status' => 'invalid' ); }
	$validator = function ( $db, $config, $utc_start ) use ( $doctor, $now ) {
		// Only the single serialized fallback meta row is safe to lock here. An
		// ACF repeater is multi-row and needs a different, coordinated adapter.
		if ( function_exists( 'get_field' ) ) { return 'schedule_unsupported'; }
		$meta = hj_interval_query( $db, "SELECT meta_value FROM hjt_postmeta WHERE post_id=? AND meta_key='working_hours' FOR UPDATE", 'i', $doctor );
		if ( count( $meta ) !== 1 ) { return false; }
		$hours = maybe_unserialize( $meta[0]['meta_value'] );
		$local = ( new DateTimeImmutable( $utc_start, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
		$occupied = hj_interval_query( $db, "SELECT starts_at, blocked_until, status FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed')", 'i', $doctor );
		return in_array( $utc_start, hj_schedule_slots( $local->format( 'Y-m-d' ), $hours, (int) $config['duration_minutes'], (int) $config['buffer_minutes'], $occupied, $now, 30 ), true );
	};
	return hj_interval_create( (int) $doctor, (int) $patient, $start, $key, '', $validator, array( 'phone' => $phone, 'national_code' => $national ) );
}
function hj_secured_change_status( $doctor, $id, $target, $now = null ) {
	$uid = get_current_user_id();
	if ( ! $uid || ! hj_spike_can_manage( $uid, $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! in_array( $target, array( 'confirmed', 'cancelled', 'completed' ), true ) ) { return array( 'status' => 'invalid' ); }
	$now = $now ?? time();
	if ( ! is_int( $now ) ) { return array( 'status' => 'invalid' ); }
	return hj_interval_transaction( (int) $doctor, function ( $db ) use ( $doctor, $id, $target, $now ) {
		$rows = hj_interval_query( $db, 'SELECT status, ends_at FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND id=?', 'ii', $doctor, $id );
		if ( ! $rows ) { return array( 'status' => 'not_found' ); }
		$old = $rows[0]['status'];
		$allowed = array( 'pending' => array( 'confirmed', 'cancelled' ), 'confirmed' => array( 'completed', 'cancelled' ) );
		if ( ! in_array( $target, $allowed[$old] ?? array(), true ) ) { return array( 'status' => 'invalid_transition' ); }
		if ( $target === 'completed' && ( hj_interval_timestamp( $rows[0]['ends_at'] ) === false || hj_interval_timestamp( $rows[0]['ends_at'] ) > $now ) ) { return array( 'status' => 'invalid_transition' ); }
		hj_interval_query( $db, 'UPDATE hjt_hjb_spike_appointments SET status=? WHERE doctor_id=? AND id=? AND status=?', 'siis', $target, $doctor, $id, $old );
		return array( 'status' => 'updated' );
	} );
}
/** Bound, prepared queries. No caller-supplied SQL identifiers. */
function hj_secured_read( $scope, $doctor = 0, $page = 1, $limit = 20, $status = '', $from = '', $before = '' ) {
	$uid = get_current_user_id();
	if ( ! $uid || ! get_userdata( $uid ) ) { return array( 'status' => 'forbidden' ); }
	if ( $scope === 'doctor' && ! hj_spike_can_manage( $uid, $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( $scope === 'admin' && ! user_can( $uid, 'manage_options' ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! in_array( $scope, array( 'doctor', 'patient', 'admin' ), true ) || ! is_int( $page ) || $page < 1 || $page > 10000 || ! is_int( $limit ) || $limit < 1 || $limit > 50 || ! in_array( $status, array( '', 'pending', 'confirmed', 'cancelled', 'completed' ), true ) ) { return array( 'status' => 'invalid' ); }
	$where = $scope === 'doctor' ? 'a.doctor_id=?' : ( $scope === 'patient' ? 'a.patient_id=?' : '1=1' );
	$types = ''; $args = array();
	if ( $scope !== 'admin' ) { $types .= 'i'; $args[] = $scope === 'doctor' ? $doctor : $uid; }
	elseif ( $doctor ) {
		if ( ! is_int( $doctor ) || $doctor < 1 ) { return array( 'status' => 'invalid' ); }
		$where .= ' AND a.doctor_id=?'; $types .= 'i'; $args[] = $doctor;
	}
	if ( $status !== '' ) { $where .= ' AND a.status=?'; $types .= 's'; $args[] = $status; }
	if ( $from !== '' ) {
		if ( hj_interval_timestamp( $from ) === false ) { return array( 'status' => 'invalid' ); }
		$where .= ' AND a.starts_at>=?'; $types .= 's'; $args[] = $from;
	}
	if ( $before !== '' ) {
		if ( hj_interval_timestamp( $before ) === false || ( $from !== '' && $from >= $before ) ) { return array( 'status' => 'invalid' ); }
		$where .= ' AND a.starts_at<?'; $types .= 's'; $args[] = $before;
	}
	$offset = ( $page - 1 ) * $limit; $types .= 'ii'; $args[] = $limit; $args[] = $offset;
	$db = hj_interval_connection();
	try {
		$rows = hj_interval_query( $db, "SELECT a.id,a.doctor_id,a.patient_id,a.starts_at,a.ends_at,a.status,d.phone,d.national_code FROM hjt_hjb_spike_appointments a LEFT JOIN hjt_hjb_spike_details d ON d.appointment_id=a.id WHERE $where ORDER BY a.starts_at,a.id LIMIT ? OFFSET ?", $types, ...$args );
		return array( 'status' => 'ok', 'rows' => $rows );
	} finally { $db->close(); }
}

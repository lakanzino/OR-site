<?php
/** Canonical snapshot experiment. Does not synchronize real dashboard/ACF writes. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }
function hj_spike_publish_snapshot( $doctor, $input, $expected_revision ) {
	if ( ! hj_spike_can_configure( get_current_user_id(), $doctor ) ) { return array( 'status' => 'forbidden' ); }
	$norm = hj_spike_normalize_schedule( $input );
	if ( $norm['status'] !== 'ok' || ! is_int( $expected_revision ) || $expected_revision < 0 ) { return array( 'status' => 'invalid' ); }
	$json = wp_json_encode( $norm['rows'] );
	if ( ! is_string( $json ) ) { return array( 'status' => 'invalid' ); }
	$hash = hash( 'sha256', $json );
	return hj_interval_transaction( (int) $doctor, function ( $db ) use ( $doctor, $expected_revision, $json, $hash ) {
		$rows = hj_interval_query( $db, 'SELECT revision FROM hjt_hjb_spike_schedules WHERE doctor_id=? FOR UPDATE', 'i', $doctor );
		$revision = $rows ? (int) $rows[0]['revision'] : 0;
		if ( $revision !== $expected_revision ) { return array( 'status' => 'revision_conflict' ); }
		$next = $revision + 1;
		if ( $rows ) { hj_interval_query( $db, 'UPDATE hjt_hjb_spike_schedules SET revision=?,rows_json=?,rows_hash=? WHERE doctor_id=?', 'issi', $next, $json, $hash, $doctor ); }
		else { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_schedules (doctor_id,revision,rows_json,rows_hash) VALUES (?,?,?,?)', 'iiss', $doctor, $next, $json, $hash ); }
		return array( 'status' => 'published', 'revision' => $next );
	} );
}
/** Caller holds doctor mutex in the same connection; no WP/ACF cache involved. */
function hj_spike_snapshot_rows_locked( $db, $doctor ) {
	$rows = hj_interval_query( $db, 'SELECT revision,rows_json,rows_hash FROM hjt_hjb_spike_schedules WHERE doctor_id=?', 'i', $doctor );
	if ( count( $rows ) !== 1 || ! hash_equals( $rows[0]['rows_hash'], hash( 'sha256', $rows[0]['rows_json'] ) ) ) { return array( 'status' => 'invalid_schedule' ); }
	$data = json_decode( $rows[0]['rows_json'], true );
	$normalized = hj_spike_normalize_schedule( $data );
	if ( $normalized['status'] !== 'ok' ) { return $normalized; }
	$normalized['revision'] = (int) $rows[0]['revision'];
	return $normalized;
}
/** Separate CI-only API; never attach this experimental source-of-truth to a live site. */
function hj_spike_snapshot_create( $doctor, $start, $key, $phone, $national, $now ) {
	$patient = get_current_user_id();
	$phone = hj_spike_phone( $phone ); $national = hj_spike_national_code( $national );
	if ( ! $patient || ! get_userdata( $patient ) || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	if ( $phone === false || $national === false || ! is_int( $now ) ) { return array( 'status' => 'invalid' ); }
	$validator = function ( $db, $config, $utc_start ) use ( $doctor, $now ) {
		$schedule = hj_spike_snapshot_rows_locked( $db, $doctor );
		if ( $schedule['status'] !== 'ok' ) { return 'schedule_unsupported'; }
		$local = ( new DateTimeImmutable( $utc_start, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
		$occupied = hj_interval_query( $db, "SELECT starts_at,blocked_until,status FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed')", 'i', $doctor );
		return in_array( $utc_start, hj_schedule_slots( $local->format( 'Y-m-d' ), $schedule['rows'], (int) $config['duration_minutes'], (int) $config['buffer_minutes'], $occupied, $now, 30 ), true );
	};
	return hj_interval_create( (int) $doctor, (int) $patient, $start, $key, '', $validator, array( 'phone' => $phone, 'national_code' => $national ) );
}

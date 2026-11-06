<?php
/** Disposable synthetic dashboard-hours writer; NOT a real ACF or dashboard adapter. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }

/** Matches the default dashboard day keys; dashboard filters are not modeled. */
function hj_spike_dash_days() {
	return array( 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' );
}
function hj_spike_dash_time_hm( $s ) {
	$s = trim( (string) $s );
	if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $s, $m ) ) {
		return sprintf( '%02d:%02d', min( 23, (int) $m[1] ), min( 59, (int) $m[2] ) );
	}
	return '';
}
/** Model default dashboard parsing; see time_hm's clamping in the real dashboard. */
function hj_spike_dash_parse_hours( $hours_in ) {
	if ( ! is_array( $hours_in ) ) { return array( 'status' => 'invalid', 'errors' => array( 'hours' => 'invalid' ) ); }
	$rows = array(); $errors = array();
	foreach ( hj_spike_dash_days() as $key ) {
		$r = isset( $hours_in[$key] ) && is_array( $hours_in[$key] ) ? $hours_in[$key] : array();
		$closed = ! empty( $r['closed'] );
		$s = isset( $r['start'] ) && is_scalar( $r['start'] ) ? hj_spike_dash_time_hm( sanitize_text_field( (string) $r['start'] ) ) : '';
		$e = isset( $r['end'] ) && is_scalar( $r['end'] ) ? hj_spike_dash_time_hm( sanitize_text_field( (string) $r['end'] ) ) : '';
		if ( $closed ) { $rows[] = array( 'day_of_week' => $key, 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ); continue; }
		if ( $s === '' && $e === '' ) { continue; }
		if ( $s === '' || $e === '' || strcmp( $s, $e ) >= 0 ) { $errors['hours_' . $key] = 'ساعت شروع باید قبل از پایان باشد.'; continue; }
		$rows[] = array( 'day_of_week' => $key, 'is_closed' => 0, 'start_time' => $s . ':00', 'end_time' => $e . ':00' );
	}
	if ( $errors ) { return array( 'status' => 'invalid', 'errors' => $errors ); }
	if ( ! $rows ) { return array( 'status' => 'ok', 'rows' => array() ); }
	$norm = hj_spike_normalize_schedule( $rows );
	return $norm['status'] === 'ok' ? array( 'status' => 'ok', 'rows' => $norm['rows'] ) : array( 'status' => 'invalid', 'errors' => array( 'hours' => $norm['reason'] ) );
}
/**
 * CI-only synthetic storage. Delete only explicit parent/numbered fixture keys;
 * never use a broad LIKE that could remove unrelated metadata. It does not
 * model ACF field references, filters, hooks, persistent caches or its API.
 */
function hj_spike_dash_write_storage_locked( $db, $doctor, $rows, $storage ) {
	$keys = array( 'working_hours' );
	for ( $i = 0; $i < 7; $i++ ) {
		foreach ( array( 'day_of_week', 'is_closed', 'start_time', 'end_time' ) as $name ) { $keys[] = 'working_hours_' . $i . '_' . $name; }
	}
	foreach ( $keys as $key ) {
		$existing = hj_interval_query( $db, 'SELECT meta_id FROM hjt_postmeta WHERE post_id=? AND meta_key=? FOR UPDATE', 'is', $doctor, $key );
		if ( count( $existing ) > 1 ) { return false; }
	}
	foreach ( $keys as $key ) { hj_interval_query( $db, 'DELETE FROM hjt_postmeta WHERE post_id=? AND meta_key=?', 'is', $doctor, $key ); }
	$parent = $storage === 'fallback' ? maybe_serialize( $rows ) : (string) count( $rows );
	hj_interval_query( $db, "INSERT INTO hjt_postmeta (post_id,meta_key,meta_value) VALUES (?, 'working_hours', ?)", 'is', $doctor, $parent );
	if ( $storage === 'acf_style' ) {
		foreach ( $rows as $i => $row ) {
			foreach ( array( 'day_of_week', 'is_closed', 'start_time', 'end_time' ) as $name ) {
				$key = 'working_hours_' . $i . '_' . $name; $value = (string) $row[$name];
				hj_interval_query( $db, 'INSERT INTO hjt_postmeta (post_id,meta_key,meta_value) VALUES (?,?,?)', 'iss', $doctor, $key, $value );
			}
		}
	}
	return true;
}
/** Both synthetic storage and snapshot write use one transaction/connection. */
function hj_spike_dashboard_save_hours( $doctor, $hours_in, $expected_revision, $storage = 'fallback', $fault = '' ) {
	if ( ! hj_spike_can_configure( get_current_user_id(), $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! in_array( $storage, array( 'fallback', 'acf_style' ), true ) || ! is_int( $expected_revision ) || $expected_revision < 0 || ! in_array( $fault, array( '', 'disconnect_before_snapshot' ), true ) ) { return array( 'status' => 'invalid' ); }
	$parsed = hj_spike_dash_parse_hours( $hours_in );
	if ( $parsed['status'] !== 'ok' ) { return $parsed; }
	$rows = $parsed['rows']; $json = wp_json_encode( $rows );
	if ( ! is_string( $json ) ) { return array( 'status' => 'invalid' ); }
	$hash = hash( 'sha256', $json );
	$result = hj_interval_transaction( (int) $doctor, function ( $db ) use ( $doctor, $rows, $expected_revision, $json, $hash, $storage, $fault ) {
		$snap = hj_interval_query( $db, 'SELECT revision FROM hjt_hjb_spike_schedules WHERE doctor_id=? FOR UPDATE', 'i', $doctor );
		$revision = $snap ? (int) $snap[0]['revision'] : 0;
		if ( $revision !== $expected_revision ) { return array( 'status' => 'revision_conflict', 'revision' => $revision ); }
		if ( ! hj_spike_dash_write_storage_locked( $db, $doctor, $rows, $storage ) ) { return array( 'status' => 'ambiguous_metadata' ); }
		if ( $fault === 'disconnect_before_snapshot' ) {
			$killer = hj_interval_connection();
			try { $killer->query( 'KILL CONNECTION ' . (int) $db->thread_id ); } finally { $killer->close(); }
		}
		$next = $revision + 1;
		if ( $snap ) { hj_interval_query( $db, 'UPDATE hjt_hjb_spike_schedules SET revision=?,rows_json=?,rows_hash=? WHERE doctor_id=?', 'issi', $next, $json, $hash, $doctor ); }
		else { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_schedules (doctor_id,revision,rows_json,rows_hash) VALUES (?,?,?,?)', 'iiss', $doctor, $next, $json, $hash ); }
		return array( 'status' => 'saved', 'revision' => $next );
	} );
	clean_post_cache( $doctor );
	return $result;
}

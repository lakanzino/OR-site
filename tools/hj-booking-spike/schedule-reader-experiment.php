<?php
/** Schedule-format experiment only; not connected to live/production booking. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }

/** Strict normalization; missing or inconsistent data is never interpreted as open. */
function hj_spike_normalize_schedule( $input ) {
	if ( ! is_array( $input ) || ! count( $input ) || count( $input ) > 7 ) { return array( 'status' => 'invalid_schedule', 'reason' => 'empty_or_invalid_rows' ); }
	$days = array( 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' );
	$seen = array(); $result = array();
	foreach ( $input as $row ) {
		if ( ! is_array( $row ) || ! array_key_exists( 'day_of_week', $row ) || ! array_key_exists( 'is_closed', $row ) || ! array_key_exists( 'start_time', $row ) || ! array_key_exists( 'end_time', $row ) ) { return array( 'status' => 'invalid_schedule', 'reason' => 'missing_field' ); }
		$day = $row['day_of_week']; $closed = $row['is_closed'];
		if ( ! is_string( $day ) || ! in_array( $day, $days, true ) || isset( $seen[$day] ) || ! in_array( $closed, array( 0, 1, '0', '1', false, true ), true ) ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_day_or_closed' ); }
		$seen[$day] = true; $closed = (int) $closed;
		$start = $row['start_time']; $end = $row['end_time'];
		if ( ! is_string( $start ) || ! is_string( $end ) ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_time' ); }
		if ( ! $closed ) {
			$pattern = '/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D';
			if ( ! preg_match( $pattern, $start ) || ! preg_match( $pattern, $end ) ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_time' ); }
			if ( strlen( $start ) === 5 ) { $start .= ':00'; }
			if ( strlen( $end ) === 5 ) { $end .= ':00'; }
			if ( $start >= $end ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_time_range' ); }
		} else { $start = ''; $end = ''; }
		$result[] = array( 'day_of_week' => $day, 'is_closed' => $closed, 'start_time' => $start, 'end_time' => $end );
	}
	return array( 'status' => 'ok', 'rows' => $result );
}

/**
 * Caller must coordinate the entire read with *all* writers. This reader itself
 * does not obtain a lock: get_field may use WP's separate connection/cache and
 * cannot make ACF's multi-statement writes atomic.
 */
function hj_spike_read_schedule( $doctor_id ) {
	if ( ! is_int( $doctor_id ) || $doctor_id < 1 || get_post_type( $doctor_id ) !== 'doctor' ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_doctor' ); }
	if ( function_exists( 'get_field' ) ) {
		try { $raw = get_field( 'working_hours', $doctor_id ); }
		catch ( Throwable $e ) { return array( 'status' => 'invalid_schedule', 'reason' => 'acf_read_error' ); }
		return hj_spike_normalize_schedule( $raw );
	}
	$meta = get_post_meta( $doctor_id, 'working_hours', false );
	if ( count( $meta ) !== 1 ) { return array( 'status' => 'invalid_schedule', 'reason' => 'missing_or_duplicate_meta' ); }
	$raw = $meta[0];
	if ( is_array( $raw ) ) { return hj_spike_normalize_schedule( $raw ); }
	if ( ! is_string( $raw ) && ! is_int( $raw ) ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_count' ); }
	if ( ! preg_match( '/^[1-7]$/D', (string) $raw ) ) { return array( 'status' => 'invalid_schedule', 'reason' => 'invalid_count' ); }
	$rows = array();
	for ( $i = 0; $i < (int) $raw; $i++ ) {
		$row = array();
		foreach ( array( 'day_of_week', 'is_closed', 'start_time', 'end_time' ) as $name ) {
			$values = get_post_meta( $doctor_id, 'working_hours_' . $i . '_' . $name, false );
			if ( count( $values ) !== 1 ) { return array( 'status' => 'invalid_schedule', 'reason' => 'missing_or_duplicate_subfield' ); }
			$row[$name] = $values[0];
		}
		$rows[] = $row;
	}
	return hj_spike_normalize_schedule( $rows );
}

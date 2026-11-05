<?php
/** Fixture-only coordinated fallback working_hours writer. Not an ACF adapter. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }
function hj_spike_can_configure( $uid, $doctor ) {
	$user = get_userdata( $uid );
	if ( ! $user || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) === 'trash' ) { return false; }
	return user_can( $uid, 'manage_options' ) || ( in_array( 'doctor', (array) $user->roles, true ) && hj_doctors_user_owns_profile( $uid, $doctor ) );
}
function hj_spike_set_hours( $doctor, $rows ) {
	if ( ! hj_spike_can_configure( get_current_user_id(), $doctor ) ) { return array( 'status' => 'forbidden' ); }
	// ACF repeater writes multiple postmeta rows; this experiment handles ONLY
	// the serialized fallback representation, never pretends to support ACF writes.
	if ( function_exists( 'get_field' ) || ! is_array( $rows ) || count( $rows ) > 7 ) { return array( 'status' => 'unsupported' ); }
	$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ); $seen = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['day_of_week'], $row['is_closed'], $row['start_time'], $row['end_time'] ) || ! in_array( $row['day_of_week'], $days, true ) || isset( $seen[$row['day_of_week']] ) || ! in_array( $row['is_closed'], array( 0, 1 ), true ) || ! is_string( $row['start_time'] ) || ! is_string( $row['end_time'] ) ) { return array( 'status' => 'invalid' ); }
		$seen[$row['day_of_week']] = true;
		if ( ! $row['is_closed'] && ( ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]:00$/D', $row['start_time'] ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]:00$/D', $row['end_time'] ) || $row['start_time'] >= $row['end_time'] ) ) { return array( 'status' => 'invalid' ); }
		if ( $row['is_closed'] && ( $row['start_time'] !== '' || $row['end_time'] !== '' ) ) { return array( 'status' => 'invalid' ); }
	}
	$serialized = maybe_serialize( $rows );
	$result = hj_interval_transaction( (int) $doctor, function ( $db ) use ( $doctor, $serialized ) {
		$found = hj_interval_query( $db, "SELECT meta_id FROM hjt_postmeta WHERE post_id=? AND meta_key='working_hours' FOR UPDATE", 'i', $doctor );
		if ( count( $found ) > 1 ) { return array( 'status' => 'ambiguous_metadata' ); }
		if ( $found ) { hj_interval_query( $db, 'UPDATE hjt_postmeta SET meta_value=? WHERE meta_id=?', 'si', $serialized, (int) $found[0]['meta_id'] ); }
		else { hj_interval_query( $db, "INSERT INTO hjt_postmeta (post_id,meta_key,meta_value) VALUES (?, 'working_hours', ?)", 'is', $doctor, $serialized ); }
		return array( 'status' => 'updated' );
	} );
	// Even unknown commits invalidate local cache; future reads must revalidate.
	clean_post_cache( $doctor );
	return $result;
}
function hj_spike_set_duration( $doctor, $duration, $buffer ) {
	if ( ! hj_spike_can_configure( get_current_user_id(), $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! is_int( $duration ) || $duration < 1 || $duration > 240 || ! is_int( $buffer ) || $buffer < 0 || $buffer > 120 ) { return array( 'status' => 'invalid' ); }
	return hj_interval_transaction( (int) $doctor, function ( $db ) use ( $doctor, $duration, $buffer ) {
		hj_interval_query( $db, 'UPDATE hjt_hjb_spike_doctors SET duration_minutes=?, buffer_minutes=? WHERE doctor_id=?', 'iii', $duration, $buffer, $doctor );
		return array( 'status' => 'updated' );
	} );
}

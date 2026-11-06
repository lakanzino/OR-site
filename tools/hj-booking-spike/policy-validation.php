<?php
/** Disposable CI-only policy and input experiment; not wired to the interval engine. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || ! in_array( PHP_SAPI, array( 'cli', 'cli-server' ), true ) || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }
function hj_spike_digits( $input ) {
	if ( ! is_string( $input ) ) { return ''; }
	return strtr( $input, array_combine( array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩' ), str_split( '01234567890123456789' ) ) );
}
function hj_spike_national_code( $input ) {
	$s = hj_spike_digits( $input );
	if ( ! preg_match( '/^[0-9]{10}$/D', $s ) || preg_match( '/^(\d)\1{9}$/D', $s ) ) { return false; }
	$sum = 0;
	for ( $i = 0; $i < 9; $i++ ) { $sum += (int) $s[$i] * ( 10 - $i ); }
	$r = $sum % 11;
	return (int) $s[9] === ( $r < 2 ? $r : 11 - $r ) ? $s : false;
}
function hj_spike_phone( $input ) {
	$s = hj_spike_digits( $input );
	if ( str_starts_with( $s, '+98' ) ) { $s = '0' . substr( $s, 3 ); }
	elseif ( str_starts_with( $s, '0098' ) ) { $s = '0' . substr( $s, 4 ); }
	return preg_match( '/^09[0-9]{9}$/D', $s ) ? $s : false;
}
/** An explicit mapping created only by a fixture administrator; no user meta trust. */
function hj_spike_assign_secretary( $secretary, $doctor ) {
	global $wpdb;
	$actor = get_current_user_id();
	if ( ! $actor || ! user_can( $actor, 'manage_options' ) || ! get_userdata( $secretary ) || get_post_type( $doctor ) !== 'doctor' ) { return false; }
	return false !== $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO hjt_hjb_spike_secretaries (user_id,doctor_id) VALUES (%d,%d)', $secretary, $doctor ) );
}
function hj_spike_can_manage( $uid, $doctor ) {
	global $wpdb;
	$user = get_userdata( $uid );
	if ( ! $user || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) === 'trash' ) { return false; }
	if ( user_can( $uid, 'manage_options' ) ) { return true; }
	if ( in_array( 'doctor', (array) $user->roles, true ) && hj_doctors_user_owns_profile( $uid, $doctor ) ) { return true; }
	if ( ! user_can( $uid, 'hj_spike_manage_appointments' ) ) { return false; }
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM hjt_hjb_spike_secretaries WHERE user_id=%d AND doctor_id=%d', $uid, $doctor ) );
}
function hj_spike_can_view( $uid, $doctor, $patient ) {
	return $uid > 0 && get_userdata( $uid ) && ( (int) $uid === (int) $patient || hj_spike_can_manage( $uid, $doctor ) );
}

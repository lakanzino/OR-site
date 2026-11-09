<?php
/** Durable, fail-closed first-profile claim for the dashboard writer. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Do not replay a claim on a reconnected database session. */
class HJ_Doctors_Profile_Claim_DB extends wpdb {
	public function check_connection( $allow_bail = true ) { return false; }
}

/**
 * Acquire a permanent one-per-user creation claim.
 *
 * This is not a lease: neither an error nor a timeout proves a prior writer has
 * stopped. A failed/unknown claim requires investigation, never auto-removal.
 * It coordinates only writers that call this function.
 *
 * @param int $user_id Authenticated doctor user ID.
 * @return bool True only when exactly one durable claim was inserted.
 */
function hj_doctors_claim_first_profile( $user_id ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( $user_id <= 0 || get_class( $wpdb ) !== 'wpdb' || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $wpdb->options ) ) { return false; }
	$table = $wpdb->options;
	$db = null;
	try {
		// Independent session: do not depend on the caller's transaction or locks.
		$db = new HJ_Doctors_Profile_Claim_DB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->suppress_errors( true );
		$engine = $db->get_var( $db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( $db->last_error || ! is_string( $engine ) || strcasecmp( $engine, 'InnoDB' ) !== 0 ) { return false; }
		$indexes = $db->get_results( "SHOW INDEX FROM `$table`", ARRAY_A );
		if ( $db->last_error || ! is_array( $indexes ) ) { return false; }
		$keys = array();
		foreach ( $indexes as $index ) {
			if ( (int) $index['Non_unique'] === 0 ) { $keys[$index['Key_name']][] = $index; }
		}
		$unique = false;
		foreach ( $keys as $parts ) {
			if ( count( $parts ) === 1 && $parts[0]['Column_name'] === 'option_name' && $parts[0]['Sub_part'] === null ) { $unique = true; break; }
		}
		if ( ! $unique || false === $db->query( 'SET autocommit = 1' ) || false === $db->query( 'SET SESSION innodb_lock_wait_timeout = 3' ) ) { return false; }
		if ( defined( 'HJ_BOOKING_TESTING' ) && HJ_BOOKING_TESTING === true ) { do_action( 'hj_doctors_profile_claim_test_event', 'before_insert', $db, $user_id ); }
		$name = 'hj_doctors_first_profile_claim_' . $user_id;
		$result = $db->query( $db->prepare( "INSERT IGNORE INTO `$table` (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, '1' ) );
		return $result === 1;
	} catch ( Throwable $e ) {
		return false;
	} finally {
		if ( $db ) { $db->close(); }
	}
}

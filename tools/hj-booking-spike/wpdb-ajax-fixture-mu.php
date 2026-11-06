<?php
/** Disposable PHP dev-server MU plugin. Never ship or activate on a real site. */
if ( PHP_SAPI !== 'cli-server' ) { return; }
if ( getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || ! defined( 'DB_NAME' ) || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI server only.' ); }

/** WordPress wpdb query semantics but no silent reconnect/replay while locked. */
class HJ_CI_No_Reconnect_WPDB extends wpdb {
	public function check_connection( $allow_bail = true ) { return false; }
}

/** Called from ONE replaced line in a disposable copy of the actual dashboard. */
function hj_ci_port_save_hours( $doctor, $rows ) {
	if ( ! current_user_can( 'edit_post', $doctor ) || ! is_array( $rows ) || count( $rows ) > 7 ) { return array( 'status' => 'forbidden' ); }
	$json = wp_json_encode( $rows );
	if ( ! is_string( $json ) ) { return array( 'status' => 'invalid' ); }
	$hash = hash( 'sha256', $json );
	$db = new HJ_CI_No_Reconnect_WPDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->suppress_errors( true ); $committing = false; $in_tx = false; $result = array( 'status' => 'error' );
	try {
		if ( false === $db->query( 'START TRANSACTION' ) ) { return array( 'status' => 'error' ); }
		$in_tx = true;
		$locked = $db->get_row( $db->prepare( 'SELECT doctor_id FROM hjt_hjb_spike_doctors WHERE doctor_id=%d FOR UPDATE', $doctor ), ARRAY_A );
		if ( ! $locked || $db->last_error ) { return array( 'status' => 'error' ); }
		$old = $db->get_row( $db->prepare( 'SELECT revision FROM hjt_hjb_spike_schedules WHERE doctor_id=%d FOR UPDATE', $doctor ), ARRAY_A );
		if ( $db->last_error ) { return array( 'status' => 'error' ); }
		$rev = $old ? (int) $old['revision'] + 1 : 1;
		if ( $old ) {
			$n = $db->query( $db->prepare( 'UPDATE hjt_hjb_spike_schedules SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d', $rev, $json, $hash, $doctor ) );
		} else {
			$n = $db->query( $db->prepare( 'INSERT INTO hjt_hjb_spike_schedules (doctor_id,revision,rows_json,rows_hash) VALUES (%d,%d,%s,%s)', $doctor, $rev, $json, $hash ) );
		}
		if ( $n !== 1 ) { return array( 'status' => 'error' ); }
		if ( isset( $_POST['hj_ci_kill_before_commit'] ) && $_POST['hj_ci_kill_before_commit'] === '1' ) {
			$id = (int) $db->get_var( 'SELECT CONNECTION_ID()' );
			$killer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $killer->suppress_errors( true );
			try { if ( $id < 1 || false === $killer->query( 'KILL CONNECTION ' . $id ) ) { throw new RuntimeException( 'Fixture connection kill failed' ); } } finally { $killer->close(); }
			// Probe the exact auto-reconnect hazard: this must NOT execute on a new connection.
			$after_kill = $db->query( $db->prepare( "UPDATE hjt_hjb_spike_schedules SET rows_json='KILL_ESCAPE' WHERE doctor_id=%d", $doctor ) );
			if ( false !== $after_kill ) { throw new RuntimeException( 'Unsafe replay after transaction connection died' ); }
		}
		$committing = true;
		if ( false === $db->query( 'COMMIT' ) ) { return array( 'status' => 'unknown' ); }
		$in_tx = false;
		$result = array( 'status' => 'saved', 'revision' => $rev );
	} finally {
		if ( $in_tx && ! $committing ) { $db->query( 'ROLLBACK' ); }
		$db->close();
	}
	// Display-only mirror is deliberately AFTER COMMIT and not part of SoT.
	// Simulated failure proves it cannot undo the canonical schedule.
	if ( ! isset( $_POST['hj_ci_mirror_fail'] ) ) {
		try { update_post_meta( $doctor, 'working_hours', $rows ); } catch ( Throwable $ignored ) {}
	}
	return $result;
}
require __DIR__ . '/wpdb-booking-fixture.php';
add_action( 'wp_ajax_hj_ci_dashboard_nonce', function () { wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_dash_nonce' ) ) ); } );
add_action( 'wp_ajax_hj_ci_schedule_state', function () {
	$doctor = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0;
	if ( ! $doctor || ! current_user_can( 'edit_post', $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT revision,rows_json,rows_hash FROM hjt_hjb_spike_schedules WHERE doctor_id=%d', $doctor ), ARRAY_A );
	wp_send_json_success( array( 'snapshot' => $row, 'display' => get_post_meta( $doctor, 'working_hours', true ) ) );
} );

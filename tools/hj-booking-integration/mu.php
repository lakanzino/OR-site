<?php
/** Fault injection and HTTP adapters only; all booking logic is plugin source. */
if ( PHP_SAPI !== 'cli-server' ) { return; }
if ( getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
function hj_prod_ci_barrier( $name, $wait ) {
	if ( ! preg_match( '/^[a-z-]+[a-f0-9]{32}$/D', $name ) ) { throw new RuntimeException( 'Bad fixture barrier' ); }
	$prefix = getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-test-' . $name;
	file_put_contents( $prefix . '-ready', 'ready' );
	if ( $wait ) {
		$until = microtime( true ) + 25;
		while ( ! is_file( $prefix . '-released' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
		if ( ! is_file( $prefix . '-released' ) ) { throw new RuntimeException( 'Fixture barrier timeout' ); }
	}
}
function hj_prod_ci_kill( $db ) {
	$id = (int) $db->get_var( 'SELECT CONNECTION_ID()' );
	$killer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $killer->suppress_errors( true );
	try { if ( ! $id || false === $killer->query( 'KILL CONNECTION ' . $id ) ) { throw new RuntimeException( 'Fixture KILL failed' ); } } finally { $killer->close(); }
}
add_action( 'hj_booking_test_event', function ( $event, $db, $context ) {
	$operation = $context['operation'];
	$key = isset( $_POST['key'] ) && is_string( $_POST['key'] ) ? $_POST['key'] : '';
	$fault = isset( $_POST['fault'] ) && is_string( $_POST['fault'] ) ? $_POST['fault'] : '';
	if ( $operation === 'schedule' && $event === 'before_lock' && isset( $_POST['hj_ci_signal_lock'] ) ) { hj_prod_ci_barrier( 'save-wait-' . $_POST['hj_ci_signal_lock'], false ); }
	if ( $operation === 'schedule' && $event === 'after_lock' && isset( $_POST['hj_ci_hold_lock'] ) ) { hj_prod_ci_barrier( 'save-hold-' . $_POST['hj_ci_hold_lock'], true ); }
	if ( $operation === 'create' && $event === 'before_lock' && $fault === 'signal_before_lock' ) { hj_prod_ci_barrier( 'booking-wait-' . $key, false ); }
	if ( $operation === 'create' && $event === 'after_lock' && $fault === 'hold_after_lock' ) { hj_prod_ci_barrier( 'booking-hold-' . $key, true ); }
	if ( $event === 'before_commit' && ( ( $operation === 'schedule' && isset( $_POST['hj_ci_kill_before_commit'] ) ) || ( $operation === 'create' && $fault === 'kill_before_commit' ) ) ) {
		hj_prod_ci_kill( $db );
		if ( $operation === 'schedule' ) {
			$table = hj_booking_table( 'schedules' );
			if ( false !== $db->query( $db->prepare( "UPDATE `$table` SET rows_json='KILL_ESCAPE' WHERE doctor_id=%d", $context['doctor'] ) ) ) { throw new RuntimeException( 'Unsafe replay' ); }
		}
	}
	if ( $event === 'after_commit' && $operation === 'create' && $fault === 'disconnect_after_commit' && ( $context['result']['status'] ?? '' ) === 'created' ) {
		hj_prod_ci_kill( $db );
		$prefix = getenv( 'RUNNER_TEMP' ) . '/hj-booking-commit-' . $key;
		file_put_contents( $prefix . '-ready', 'committed' );
		$until = microtime( true ) + 20;
		while ( ! is_file( $prefix . '-released' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
		if ( ! is_file( $prefix . '-released' ) ) { throw new RuntimeException( 'Lost response barrier timeout' ); }
	}
}, 10, 3 );
add_filter( 'update_post_metadata', function ( $check, $id, $key ) {
	return $key === 'working_hours' && isset( $_POST['hj_ci_mirror_fail'] ) ? true : $check;
}, 10, 3 );
add_action( 'wp_ajax_hj_ci_dashboard_nonce', function () { wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_dash_nonce' ) ) ); } );
add_action( 'wp_ajax_hj_ci_booking_nonce', function () { wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_ci_booking' ) ) ); } );
add_action( 'wp_ajax_hj_ci_schedule_state', function () {
	$doctor = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0;
	if ( ! current_user_can( 'edit_post', $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb; $table = hj_booking_table( 'schedules' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d", $doctor ), ARRAY_A );
	wp_send_json_success( array( 'snapshot' => $row, 'display' => get_post_meta( $doctor, 'working_hours', true ) ) );
} );
add_action( 'wp_ajax_hj_ci_corrupt_snapshot', function () {
	$doctor = absint( $_POST['doctor'] ?? 0 );
	if ( ! current_user_can( 'edit_post', $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb; $table = hj_booking_table( 'schedules' );
	$n = $wpdb->query( $wpdb->prepare( "UPDATE `$table` SET rows_hash=%s WHERE doctor_id=%d", str_repeat( '0', 64 ), $doctor ) );
	if ( $n !== 1 ) { wp_send_json_error( array(), 500 ); }
	wp_send_json_success( array( 'ok' => true ) );
} );
function hj_prod_ci_handler( $mode ) {
	check_ajax_referer( 'hj_ci_booking', 'nonce' );
	$doctor = isset( $_POST['doctor'] ) ? absint( $_POST['doctor'] ) : 0;
	$val = static function ( $key ) { return isset( $_POST[$key] ) && is_string( $_POST[$key] ) ? wp_unslash( $_POST[$key] ) : ''; };
	if ( $mode === 'slots' ) { $result = hj_booking_slots( $doctor, $val( 'date' ) ); }
	elseif ( $mode === 'create' ) { $result = hj_booking_create( $doctor, $val( 'start' ), $val( 'key' ), $val( 'phone' ), $val( 'national' ) ); }
	else {
		if ( ! hj_booking_can_manage( $doctor ) ) { wp_send_json_error( array(), 403 ); }
		$result = hj_booking_change_status( $doctor, absint( $_POST['id'] ?? 0 ), $val( 'status' ) );
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_hj_ci_slots', function () { hj_prod_ci_handler( 'slots' ); } );
add_action( 'wp_ajax_hj_ci_create', function () { hj_prod_ci_handler( 'create' ); } );
add_action( 'wp_ajax_hj_ci_status', function () { hj_prod_ci_handler( 'status' ); } );
add_action( 'wp_ajax_hj_ci_booking_audit', function () {
	$doctor = absint( $_POST['doctor'] ?? 0 );
	if ( ! current_user_can( 'edit_post', $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb; $table = hj_booking_table( 'appointments' ); $details = hj_booking_table( 'details' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.id,a.request_key,a.starts_at,a.patient_id,a.status,a.schedule_revision AS revision,d.appointment_id AS details_id FROM `$table` a LEFT JOIN `$details` d ON a.id=d.appointment_id WHERE a.doctor_id=%d ORDER BY a.id", $doctor ), ARRAY_A );
	if ( $wpdb->last_error ) { wp_send_json_error( array(), 500 ); }
	wp_send_json_success( $rows );
} );

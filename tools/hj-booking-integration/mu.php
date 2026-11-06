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
	if ( $operation === 'status' && $event === 'after_lock' && isset( $_POST['hj_ci_status_hold'] ) ) { hj_prod_ci_barrier( 'status-hold-' . $_POST['hj_ci_status_hold'], true ); }
	if ( $operation === 'status' && $event === 'before_lock' && isset( $_POST['hj_ci_status_signal'] ) ) { hj_prod_ci_barrier( 'status-wait-' . $_POST['hj_ci_status_signal'], false ); }
	if ( $operation === 'create' && $event === 'before_lock' && isset( $_POST['hj_ci_create_signal'] ) ) { hj_prod_ci_barrier( 'create-wait-' . $_POST['hj_ci_create_signal'], false ); }
	if ( $operation === 'create' && $event === 'after_lock' && isset( $_POST['hj_ci_create_hold'] ) ) { hj_prod_ci_barrier( 'create-hold-' . $_POST['hj_ci_create_hold'], true ); }
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
	wp_send_json_success( array( 'snapshot' => $row, 'display' => get_post_meta( $doctor, 'working_hours', true ), 'name' => get_post_meta( $doctor, 'name_fa', true ) ) );
} );
add_action( 'wp_ajax_hj_ci_render_dashboard', function () {
	wp_send_json_success( array( 'html' => HJ_Doctors_Dashboard::shortcode_render() ) );
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

// Only a provider stub: exercises our redirect/intent plumbing, not OAuth itself.
if ( ( isset( $_GET['hj_member_google'] ) || isset( $_POST['hj_ci_provider'] ) ) && ! class_exists( 'NextendSocialLogin' ) ) {
 class NextendSocialLogin {
  public static function isProviderEnabled( $provider ) { return $provider === 'google'; }
  public static function getLoginUrl() { return site_url( 'wp-login.php' ); }
 }
}
function hj_ci_render_patient_form() {
 $page = absint( $_POST['page'] ?? 0 );
 if ( ! get_post( $page ) ) { wp_send_json_error( array(), 400 ); }
 global $wp_query;
 $wp_query->queried_object = get_post( $page ); $wp_query->queried_object_id = $page;
 $wp_query->is_singular = true; $wp_query->is_page = true;
 wp_send_json_success( array( 'html' => do_shortcode( '[hj_booking_form]' ) ) );
}
add_action( 'wp_ajax_hj_ci_render_patient_form', 'hj_ci_render_patient_form' );
add_action( 'wp_ajax_nopriv_hj_ci_render_patient_form', 'hj_ci_render_patient_form' );
function hj_ci_render_patient_profile() {
 $id = absint( $_POST['doctor'] ?? 0 );
 global $wp_query; $wp_query->queried_object = get_post( $id ); $wp_query->queried_object_id = $id;
 $wp_query->is_singular = true; $wp_query->is_single = true;
 wp_send_json_success( array( 'html' => HJ_Profile_Page::render( $id ) ) );
}
add_action( 'wp_ajax_hj_ci_render_patient_profile', 'hj_ci_render_patient_profile' );
add_action( 'wp_ajax_nopriv_hj_ci_render_patient_profile', 'hj_ci_render_patient_profile' );
add_action( 'wp_ajax_hj_ci_status_nonce', function () {
 $id = absint( $_POST['id'] ?? 0 );
 wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_booking_status_' . $id ) ) );
} );
add_action( 'wp_ajax_hj_ci_render_patient_bookings', function () { wp_send_json_success( array( 'html' => do_shortcode( '[hj_patient_bookings]' ) ) ); } );
add_action( 'wp_ajax_hj_ci_render_management', function () {
 $id = absint( $_POST['doctor'] ?? 0 );
 $html = hj_booking_management_render( 'doctor', $id );
 wp_send_json_success( array( 'html' => $html ) );
} );
add_action( 'wp_ajax_hj_ci_set_past', function () {
 $doctor = absint( $_POST['doctor'] ?? 0 ); $id = absint( $_POST['id'] ?? 0 );
 if ( ! hj_booking_can_manage( $doctor ) ) { wp_send_json_error( array(), 403 ); }
 $result = hj_booking_transaction( $doctor, 'fixture_past', function ( $db ) use ( $doctor, $id ) {
  $table = hj_booking_table( 'appointments' );
  hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET starts_at='2020-01-01 09:00:00',ends_at='2020-01-01 09:30:00',blocked_until='2020-01-01 09:35:00' WHERE id=%d AND doctor_id=%d", $id, $doctor ) );
  return array( 'status' => 'updated' );
 } );
 wp_send_json_success( $result );
} );

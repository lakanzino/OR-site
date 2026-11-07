<?php
/** Fault injection and HTTP adapters only; all booking logic is plugin source. */
if ( PHP_SAPI !== 'cli-server' ) { return; }
if ( getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
function hj_prod_ci_barrier( $name, $wait, $marker = 'ready' ) {
	if ( ! preg_match( '/^[a-z-]+[a-f0-9]{32}$/D', $name ) ) { throw new RuntimeException( 'Bad fixture barrier' ); }
	$prefix = getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-test-' . $name;
	// Publish only a complete marker; readers must not see a newly created empty file.
	$ready = $prefix . '-ready'; $temporary = $ready . '-' . getmypid() . '.tmp';
	if ( false === file_put_contents( $temporary, (string) $marker ) || ! rename( $temporary, $ready ) ) { @unlink( $temporary ); throw new RuntimeException( 'Fixture barrier marker failed' ); }
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
	if ( $operation === 'status' && $event === 'before_lock' && isset( $_POST['hj_ci_status_signal'] ) ) { hj_prod_ci_barrier( 'status-wait-' . $_POST['hj_ci_status_signal'], false, (int) $db->get_var( 'SELECT CONNECTION_ID()' ) ); }
	if ( $operation === 'create' && $event === 'before_lock' && isset( $_POST['hj_ci_create_signal'] ) ) { hj_prod_ci_barrier( 'create-wait-' . $_POST['hj_ci_create_signal'], false, (int) $db->get_var( 'SELECT CONNECTION_ID()' ) ); }
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
// CI-only barrier after the product's first-profile check, before WordPress inserts the post.
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
 if ( ( $_POST['action'] ?? '' ) === 'hj_dash_save' && ( $_POST['pid'] ?? '' ) === '0' && isset( $_POST['hj_ci_profile_insert_hold'] ) && is_string( $_POST['hj_ci_profile_insert_hold'] ) && ( $data['post_type'] ?? '' ) === 'doctor' && empty( $postarr['ID'] ) ) {
  hj_prod_ci_barrier( 'profile-hold-' . $_POST['hj_ci_profile_insert_hold'], true, getmypid() );
 }
 if ( ( $_POST['action'] ?? '' ) === 'hj_dash_save' && ( $_POST['pid'] ?? '' ) === '0' && ( $_POST['hj_ci_claim_prototype'] ?? '' ) === '1' && ( $data['post_type'] ?? '' ) === 'doctor' && empty( $postarr['ID'] ) ) {
  $token = $_POST['hj_ci_claim_token'] ?? '';
  if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/D', $token ) ) { wp_send_json( array( 'success'=>false, 'message'=>'bad fixture claim' ), 400 ); }
  hj_prod_ci_barrier( 'claim-hold-' . $token, true, getmypid() );
  global $wpdb; $table = $wpdb->prefix . 'ci_profile_claims';
  $previous_suppression = $wpdb->suppress_errors( true );
  $n = $wpdb->query( $wpdb->prepare( "INSERT INTO `$table` (user_id, claim_token) VALUES (%d, %s) ON DUPLICATE KEY UPDATE user_id=user_id", get_current_user_id(), $token ) );
  $wpdb->suppress_errors( $previous_suppression );
  // Duplicate, unexpected row count and SQL errors all reject without creating a post.
  if ( $n !== 1 ) { wp_send_json( array( 'success'=>false, 'message'=>'CI claim unavailable' ), 409 ); }
  if ( isset( $_POST['hj_ci_claim_hold_owned'] ) ) { hj_prod_ci_barrier( 'claim-owned-' . $token, true, getmypid() ); }
  if ( isset( $_POST['hj_ci_claim_kill_wpdb'] ) ) { $GLOBALS['hj_ci_claim_old_db_id'] = (int) $wpdb->get_var( 'SELECT CONNECTION_ID()' ); hj_prod_ci_kill( $wpdb ); }
 }
 return $data;
}, 10, 2 );
// Report the actual WordPress writer connection after the deliberately killed one reconnects.
add_action( 'wp_after_insert_post', function ( $id, $post, $update ) {
 if ( $update || $post->post_type !== 'doctor' || empty( $GLOBALS['hj_ci_claim_old_db_id'] ) || ! isset( $_POST['hj_ci_claim_kill_wpdb'], $_POST['hj_ci_claim_token'] ) ) { return; }
 global $wpdb; $new_id = (int) $wpdb->get_var( 'SELECT CONNECTION_ID()' );
 hj_prod_ci_barrier( 'claim-reconnect-' . $_POST['hj_ci_claim_token'], false, $GLOBALS['hj_ci_claim_old_db_id'] . ',' . $new_id );
}, 10, 3 );
// Read only this authenticated user's CI-only claim, without changing it.
add_action( 'wp_ajax_hj_ci_profile_claim', function () {
 if ( ! hj_doctors_is_doctor_user() ) { wp_send_json_error( array(), 403 ); }
 global $wpdb; $table=$wpdb->prefix . 'ci_profile_claims';
 $token=$wpdb->get_var( $wpdb->prepare( "SELECT claim_token FROM `$table` WHERE user_id=%d", get_current_user_id() ) );
 if ( $wpdb->last_error ) { wp_send_json_error( array(), 503 ); }
 wp_send_json_success( array( 'token'=>$token ) );
} );
add_filter( 'update_post_metadata', function ( $check, $id, $key ) {
	return $key === 'working_hours' && isset( $_POST['hj_ci_mirror_fail'] ) ? true : $check;
}, 10, 3 );
add_filter( 'hj_dash_time_storage_format', function ( $format ) { return isset( $_POST['hj_ci_time_format'] ) && $_POST['hj_ci_time_format'] === 'H:i' ? 'H:i' : $format; } );
add_action( 'wp_ajax_hj_ci_dashboard_nonce', function () { wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_dash_nonce' ) ) ); } );
add_action( 'wp_ajax_hj_ci_booking_nonce', function () { wp_send_json_success( array( 'nonce' => wp_create_nonce( 'hj_ci_booking' ) ) ); } );
add_action( 'wp_ajax_hj_ci_schedule_state', function () {
	$doctor = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0;
	if ( ! current_user_can( 'edit_post', $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb; $table = hj_booking_table( 'schedules' );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d", $doctor ), ARRAY_A );
	wp_send_json_success( array( 'snapshot' => $row, 'display' => get_post_meta( $doctor, 'working_hours', true ), 'name' => get_post_meta( $doctor, 'name_fa', true ) ) );
} );
// CI-only introspection of the caller's synthetic doctor profiles.
add_action( 'wp_ajax_hj_ci_own_profiles', function () {
 if ( ! hj_doctors_is_doctor_user() ) { wp_send_json_error( array(), 403 ); }
 $ids = get_posts( array( 'post_type'=>'doctor', 'post_status'=>array('draft','pending','publish','private'), 'author'=>get_current_user_id(), 'posts_per_page'=>20, 'fields'=>'ids', 'orderby'=>'ID', 'order'=>'ASC', 'suppress_filters'=>true ) );
 wp_send_json_success( array( 'ids'=>array_map('intval',$ids), 'linked'=>(int)get_user_meta(get_current_user_id(),'hj_doctor_post_id',true) ) );
} );
add_action( 'wp_ajax_hj_ci_render_dashboard', function () {
	wp_send_json_success( array( 'html' => HJ_Doctors_Dashboard::shortcode_render() ) );
} );
// CI-only adapters for the actual experimental capacity-settings shortcode.
add_action( 'wp_ajax_hj_ci_render_capacity_settings', function () {
	$page = absint( $_POST['page'] ?? 0 ); $post = get_post( $page );
	if ( ! $post ) { wp_send_json_error( array(), 400 ); }
	global $wp_query; $wp_query->queried_object = $post; $wp_query->queried_object_id = $page; $wp_query->is_singular = true; $wp_query->is_page = true;
	wp_send_json_success( array( 'html'=>do_shortcode( $post->post_content ) ) );
} );
// Exercise our field filter with a simulated ACF field, not the ACF plugin.
add_action( 'wp_ajax_hj_ci_acf_hours_label', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array(), 403 ); }
	if ( ! function_exists( 'set_current_screen' ) ) { require_once ABSPATH . 'wp-admin/includes/screen.php'; }
	set_current_screen( 'doctor' );
	$field = array( 'key'=>'field_hj_working_hours', 'name'=>'working_hours', 'type'=>'repeater', 'label'=>'ساعات کاری', 'instructions'=>'توسط داشبورد پر می‌شود.' );
	wp_send_json_success( apply_filters( 'acf/prepare_field/key=field_hj_working_hours', $field ) );
} );
add_action( 'wp_ajax_hj_ci_review_editor_nonce', function () {
	$doctor = absint( $_POST['doctor'] ?? 0 );
	if ( ! current_user_can( 'edit_post', $doctor ) || get_post_type( $doctor ) !== 'doctor' ) { wp_send_json_error( array(), 403 ); }
	wp_send_json_success( array( 'post_nonce'=>wp_create_nonce( 'update-post_' . $doctor ) ) );
} );
add_action( 'wp_ajax_hj_ci_capacity_state', function () {
	$doctor = absint( $_POST['doctor'] ?? 0 ); if ( ! hj_booking_can_configure( $doctor ) ) { wp_send_json_error( array(), 403 ); }
	global $wpdb; $d = hj_booking_table( 'doctors' ); $s = hj_booking_table( 'schedules' );
	$config = $wpdb->get_row( $wpdb->prepare( "SELECT duration_minutes,buffer_minutes,enabled,activation_generation FROM `$d` WHERE doctor_id=%d", $doctor ), ARRAY_A );
	$snapshot = $wpdb->get_row( $wpdb->prepare( "SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d", $doctor ), ARRAY_A );
	wp_send_json_success( array( 'config'=>$config, 'snapshot'=>$snapshot ) );
} );
add_action( 'wp_ajax_hj_ci_capacity_configure', function () {
	check_ajax_referer( 'hj_dash_nonce', '_hjn' ); $doctor = absint( $_POST['doctor'] ?? 0 );
	$duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 45; $buffer = isset( $_POST['buffer'] ) ? absint( $_POST['buffer'] ) : 10;
	$result = hj_booking_configure( $doctor, $duration, $buffer, false ); wp_send_json_success( $result );
} );
// CI-only mutation for synthetic snapshots. Never shipped in the plugin.
add_action( 'wp_ajax_hj_ci_replace_snapshot', function () {
	check_ajax_referer( 'hj_dash_nonce', '_hjn' );
	$doctor = absint( $_POST['doctor'] ?? 0 );
	$raw = isset( $_POST['rows_json'] ) && is_string( $_POST['rows_json'] ) ? wp_unslash( $_POST['rows_json'] ) : '';
	if ( ! hj_booking_can_configure( $doctor ) || strlen( $raw ) > 10000 ) { wp_send_json_error( array(), 403 ); }
	$result = hj_booking_transaction( $doctor, 'ci_snapshot', function ( $db ) use ( $doctor, $raw ) {
		$table = hj_booking_table( 'schedules' );
		$n = hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET rows_json=%s,rows_hash=%s WHERE doctor_id=%d", $raw, hash( 'sha256', $raw ), $doctor ) );
		return array( 'status' => $n === 1 ? 'updated' : 'error' );
	} );
	wp_send_json_success( $result );
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
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.id,a.request_key,a.request_hash,a.starts_at,a.ends_at,a.blocked_until,a.patient_id,a.status,a.schedule_revision AS revision,d.appointment_id AS details_id,SHA2(CONCAT_WS('|',d.phone,d.national_code),256) AS details_hash FROM `$table` a LEFT JOIN `$details` d ON a.id=d.appointment_id WHERE a.doctor_id=%d ORDER BY a.id", $doctor ), ARRAY_A );
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
add_action( 'wp_ajax_hj_ci_render_member_account', function () {
 $page=absint($_POST['page'] ?? 0); $post=get_post($page);
 if (!$post || $post->post_type!=='page') { wp_send_json_error(array(),400); }
 global $wp_query; $wp_query->queried_object=$post; $wp_query->queried_object_id=$page; $wp_query->is_singular=true; $wp_query->is_page=true;
 wp_send_json_success(array('html'=>do_shortcode('[hj_member_account]')));
} );
// Synthetic fixed payload, authorized only inside this disposable MU fixture.
add_action( 'wp_ajax_hj_ci_patient_extra_xss', function () {
 if ( ! hj_booking_capacity_v2_ready() || ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'hj_booking_ui', 'nonce', false ) ) { wp_send_json_error( array(), 403 ); }
 global $wpdb; $table=hj_booking_patient_details_v2_table(); $id=absint( $_POST['id'] ?? 0 );
 $result=$wpdb->update( $table, array( 'first_name'=>'<img src=x onerror=alert(1)>', 'note'=>'<script>alert(2)</script>' ), array( 'appointment_id'=>$id ) );
 if ( $result !== 1 ) { wp_send_json_error( array(), 400 ); } wp_send_json_success( array( 'updated'=>true ) );
} );
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
// Observe the fixture user's connection executing the actual mutex INSERT;
// return only a boolean, never query text or private appointment data.
add_action( 'wp_ajax_hj_ci_mutex_query_in_progress', function () {
 if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array(), 403 ); }
 global $wpdb; $id = absint( $_POST['connection'] ?? 0 ); $found = false;
 $processes = $wpdb->get_results( 'SHOW FULL PROCESSLIST', ARRAY_A );
 if ( $wpdb->last_error || ! is_array( $processes ) ) { wp_send_json_error( array(), 500 ); }
 foreach ( $processes as $process ) {
  if ( (int) $process['Id'] === $id && is_string( $process['Info'] ) && str_contains( $process['Info'], 'INSERT INTO `' . hj_booking_table( 'doctors' ) . '`' ) ) { $found = true; }
 }
 wp_send_json_success( array( 'in_progress' => $found ) );
} );

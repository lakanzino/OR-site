<?php
/** Integrated actual storage/permissions experiment on disposable WordPress. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php';
require __DIR__ . '/policy-validation.php';
require __DIR__ . '/schedule-engine.php';
require __DIR__ . '/secured-experiment.php';
function secured_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
function fixture_user_id( $login ) { $u = get_user_by( 'login', $login ); if ( ! $u ) { throw new RuntimeException( 'Fixture user missing' ); } return (int) $u->ID; }
$admin = fixture_user_id( 'fixture_admin' ); $doctor = fixture_user_id( 'synthetic_doctor_a' );
$secretary = fixture_user_id( 'synthetic_secretary' ); $patient = fixture_user_id( 'synthetic_patient' );
$other_patient = fixture_user_id( 'synthetic_patient_b' ); $other_doctor = fixture_user_id( 'synthetic_doctor_b' );
$posts = get_posts( array( 'post_type' => 'doctor', 'author' => $doctor, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 1 ) );
if ( ! $posts ) { throw new RuntimeException( 'Fixture doctor profile missing' ); }
$profile = (int) $posts[0];
$rows = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) );
update_post_meta( $profile, 'working_hours', $rows );
update_option( 'timezone_string', 'Asia/Tehran' );
$db = hj_interval_connection();
try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $profile, 30, 0, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
$now = strtotime( '2030-01-01 UTC' );
$key = static function ( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); };
wp_set_current_user( 0 );
secured_expect( hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9001 ), '09123456789', '1234567891', $now )['status'] === 'forbidden', 'anonymous cannot create' );
wp_set_current_user( $patient );
secured_expect( hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9001 ), '09123456789', '1234567890', $now )['status'] === 'invalid', 'invalid national code blocked on create' );
secured_expect( hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9001 ), '02112345678', '1234567891', $now )['status'] === 'invalid', 'invalid mobile blocked on create' );
$r = hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9001 ), '۰۹۱۲۳۴۵۶۷۸۹', '۱۲۳۴۵۶۷۸۹۱', $now );
secured_expect( $r['status'] === 'created', 'patient creates with validated normalized data' ); $id = $r['id'];
secured_expect( hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9001 ), '09123456789', '1234567891', $now )['status'] === 'replayed', 'canonical identical details replay' );
secured_expect( hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9001 ), '09123456780', '1234567891', $now )['status'] === 'idempotency_conflict', 'changed details cannot reuse key' );
secured_expect( hj_secured_change_status( $profile, $id, 'confirmed' )['status'] === 'forbidden', 'patient cannot change status' );
secured_expect( hj_secured_read( 'doctor', $profile )['status'] === 'forbidden' && hj_secured_read( 'admin' )['status'] === 'forbidden', 'patient cannot read doctor or admin lists' );
$list = hj_secured_read( 'patient' );
secured_expect( $list['status'] === 'ok' && count( $list['rows'] ) === 1 && $list['rows'][0]['phone'] === '09123456789' && $list['rows'][0]['national_code'] === '1234567891', 'patient sees own saved normalized details' );
wp_set_current_user( $other_patient );
secured_expect( count( hj_secured_read( 'patient' )['rows'] ) === 0 && hj_secured_change_status( $profile, $id, 'cancelled' )['status'] === 'forbidden', 'other patient cannot see or modify booking' );
wp_set_current_user( $other_doctor );
secured_expect( hj_secured_read( 'doctor', $profile )['status'] === 'forbidden' && hj_secured_change_status( $profile, $id, 'confirmed' )['status'] === 'forbidden', 'other doctor cannot view or modify' );
wp_set_current_user( $doctor );
secured_expect( count( hj_secured_read( 'doctor', $profile )['rows'] ) === 1 && hj_secured_change_status( $profile, $id, 'confirmed' )['status'] === 'updated', 'owner sees and confirms' );
wp_set_current_user( $secretary );
secured_expect( count( hj_secured_read( 'doctor', $profile )['rows'] ) === 1 && hj_secured_change_status( $profile, $id, 'cancelled' )['status'] === 'updated', 'mapped secretary sees and cancels' );
wp_set_current_user( $patient );
$r2 = hj_secured_create( $profile, '2030-01-02 05:30:00', $key( 9002 ), '09123456789', '1234567891', $now );
secured_expect( $r2['status'] === 'created', 'patient rebooks after cancellation' );
wp_set_current_user( $admin );
secured_expect( hj_secured_change_status( $profile, $r2['id'], 'confirmed' )['status'] === 'updated' && hj_secured_change_status( $profile, $r2['id'], 'completed' )['status'] === 'updated', 'admin confirms and completes' );
secured_expect( hj_secured_change_status( $profile, $r2['id'], 'confirmed' )['status'] === 'invalid_transition', 'completed cannot be reactivated' );
$list = hj_secured_read( 'admin', $profile, 1, 1, 'completed', '2030-01-02 00:00:00', '2030-01-03 00:00:00' );
secured_expect( $list['status'] === 'ok' && count( $list['rows'] ) === 1 && (int) $list['rows'][0]['id'] === (int) $r2['id'], 'admin doctor/date/status filtered paginated read' );
secured_expect( count( hj_secured_read( 'admin', $profile, 2, 1, 'completed' )['rows'] ) === 0, 'pagination does not leak extra results' );

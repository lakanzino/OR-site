<?php
/** Integrated race on synthetic users in the disposable CI fixture only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/policy-validation.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/secured-experiment.php'; require __DIR__ . '/schedule-write-experiment.php';
function race_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
function race_uid( $login ) { $u = get_user_by( 'login', $login ); if ( ! $u ) { throw new RuntimeException( 'Missing synthetic user' ); } return (int) $u->ID; }
$doctor = race_uid( 'synthetic_doctor_a' );
$profile = (int) get_posts( array( 'post_type' => 'doctor', 'author' => $doctor, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 1 ) )[0];
$key = static function ( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); };
$now = strtotime( '2030-01-01 UTC' ); $mode = $argv[1] ?? '';
if ( $mode === 'setup' ) {
	for ( $i = 0; $i < 12; $i++ ) { $uid = wp_insert_user( array( 'user_login' => 'synthetic_race_' . $i, 'user_pass' => 'synthetic-only', 'role' => 'subscriber' ) ); if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Cannot create synthetic user' ); } }
	wp_set_current_user( $doctor );
	$open = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) );
	race_expect( hj_spike_set_hours( $profile, $open )['status'] === 'updated', 'doctor updates fallback hours under mutex' );
	$slots = hj_schedule_slots( '2030-01-02', hj_doctors_field( 'working_hours', $profile ), 30, 0, array(), $now, 30 );
	race_expect( in_array( '2030-01-02 06:00:00', $slots, true ), 'generate real WordPress hours before race' );
} elseif ( $mode === 'worker' ) {
	$i = (int) ( $argv[2] ?? -1 ); if ( $i < 0 || $i > 11 ) { exit( 1 ); }
	wp_set_current_user( race_uid( 'synthetic_race_' . $i ) );
	touch( '/tmp/hj-secured-ready-' . $i ); $until = microtime( true ) + 30;
	while ( ! file_exists( '/tmp/hj-secured-go' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( '/tmp/hj-secured-go' ) ) { exit( 2 ); }
	$r = hj_secured_create( $profile, '2030-01-02 06:00:00', $key( 10000 + $i ), '09123456789', '1234567891', $now );
	echo $r['status'];
} elseif ( $mode === 'finish' ) {
	$db = hj_interval_connection();
	try { $rows = hj_interval_query( $db, "SELECT id FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND starts_at='2030-01-02 06:00:00' AND status='pending'", 'i', $profile ); } finally { $db->close(); }
	race_expect( count( $rows ) === 1, 'exactly one actual scoped booking from concurrent race' );
	wp_set_current_user( race_uid( 'synthetic_secretary' ) );
	race_expect( hj_secured_change_status( $profile, (int) $rows[0]['id'], 'cancelled' )['status'] === 'updated', 'secretary cancels winner' );
	wp_set_current_user( race_uid( 'synthetic_race_0' ) );
	$r = hj_secured_create( $profile, '2030-01-02 06:00:00', $key( 11000 ), '09123456789', '1234567891', $now );
	race_expect( $r['status'] === 'created', 'patient rebooks same slot after cancellation' );
	wp_set_current_user( $doctor ); race_expect( hj_secured_change_status( $profile, $r['id'], 'confirmed' )['status'] === 'updated', 'doctor confirms rebooking' );
	wp_set_current_user( race_uid( 'synthetic_secretary' ) ); race_expect( hj_secured_change_status( $profile, $r['id'], 'completed' )['status'] === 'updated', 'secretary completes rebooking' );
	wp_set_current_user( race_uid( 'fixture_admin' ) ); $found = hj_secured_read( 'admin', $profile, 1, 50, 'completed', '2030-01-02 06:00:00', '2030-01-02 06:00:01' );
	race_expect( count( $found['rows'] ) === 1, 'admin sees completed rebooking with filters' );
} else { exit( 1 ); }

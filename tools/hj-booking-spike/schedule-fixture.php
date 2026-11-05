<?php
/** WP timezone and existing working_hours-format experiment, disposable CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php';
require __DIR__ . '/schedule-engine.php';
function schedule_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
update_option( 'timezone_string', 'Asia/Tehran' );
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic hours' ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Could not create synthetic profile' ); }
$rows = array(
	array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '10:40:00' ),
	array( 'day_of_week' => 'thursday', 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ),
);
update_post_meta( $doc, 'working_hours', $rows );
$loaded = hj_doctors_field( 'working_hours', $doc );
schedule_expect( $loaded === $rows, 'existing fallback working_hours field loaded from WordPress' );
$now = strtotime( '2030-01-01 00:00:00 UTC' );
$slots = hj_schedule_slots( '2030-01-02', $loaded, 30, 5, array(), $now, 30 );
schedule_expect( $slots === array( '2030-01-02 05:30:00', '2030-01-02 06:05:00', '2030-01-02 06:40:00' ), 'Tehran timezone and duration plus buffer slots' );
schedule_expect( ! hj_schedule_slots( '2030-01-03', $loaded, 30, 5, array(), $now, 30 ), 'closed day yields no slots' );
schedule_expect( ! hj_schedule_slots( '2030-02-30', $loaded, 30, 5, array(), $now, 30 ), 'invalid calendar date yields no slots' );
schedule_expect( ! hj_schedule_slots( '2030-01-02', $loaded, 30, 5, array(), strtotime( '2030-01-03 UTC' ), 30 ), 'past slots rejected' );
schedule_expect( ! hj_schedule_slots( '2030-01-02', $loaded, 30, 5, array(), strtotime( '2029-12-01 UTC' ), 30 ), 'slots beyond horizon rejected' );
$occupied = array( array( 'starts_at' => $slots[1], 'blocked_until' => '2030-01-02 06:40:00', 'status' => 'pending' ) );
schedule_expect( hj_schedule_slots( '2030-01-02', $loaded, 30, 5, $occupied, $now, 30 ) === array( $slots[0], $slots[2] ), 'occupied pending interval excluded; adjacent slot retained' );
$occupied[0]['status'] = 'cancelled';
schedule_expect( hj_schedule_slots( '2030-01-02', $loaded, 30, 5, $occupied, $now, 30 ) === $slots, 'cancelled interval does not block' );
$duplicate = array_merge( $rows, array( $rows[0] ) );
schedule_expect( ! hj_schedule_slots( '2030-01-02', $duplicate, 30, 5, array(), $now, 30 ), 'duplicate weekday fails closed' );
$tz = new DateTimeZone( 'Europe/Berlin' );
schedule_expect( hj_schedule_local_time( '2030-03-31 02:30:00', $tz ) === false, 'nonexistent DST wall time rejected' );
schedule_expect( hj_schedule_local_time( '2030-10-27 02:30:00', $tz ) === false, 'ambiguous DST wall time rejected' );
schedule_expect( hj_schedule_local_time( '2030-10-27 03:30:00', $tz ) !== false, 'ordinary DST wall time accepted' );
// This experiment reads real WordPress metadata while holding the fixture's doctor
// lock. It does not yet make existing dashboard/ACF writers take that lock.
$db = hj_interval_connection();
try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $doc, 30, 5, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
$validator = function ( $db, $config, $utc_start ) use ( $doc, $now ) {
	clean_post_cache( $doc );
	$actual = hj_doctors_field( 'working_hours', $doc );
	$local = ( new DateTimeImmutable( $utc_start, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
	$occupied = hj_interval_query( $db, "SELECT starts_at, blocked_until, status FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed')", 'i', $doc );
	$available = hj_schedule_slots( $local->format( 'Y-m-d' ), $actual, (int) $config['duration_minutes'], (int) $config['buffer_minutes'], $occupied, $now, 30 );
	return in_array( $utc_start, $available, true );
};
$key = static function ( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); };
schedule_expect( hj_interval_create( $doc, 201, $slots[0], $key( 8001 ), '', $validator )['status'] === 'created', 'create validates real fallback working_hours under fixture lock' );
schedule_expect( hj_interval_create( $doc, 202, '2030-01-02 05:40:00', $key( 8002 ), '', $validator )['status'] === 'invalid_slot', 'off-grid slot rejected under lock' );
schedule_expect( hj_interval_create( $doc, 202, '2030-01-03 05:30:00', $key( 8003 ), '', $validator )['status'] === 'invalid_slot', 'closed day create rejected under lock' );

<?php
/** Snapshot writes versus authenticated bookings, disposable CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/policy-validation.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/schedule-reader-experiment.php'; require __DIR__ . '/schedule-write-experiment.php'; require __DIR__ . '/snapshot-experiment.php';
$mode = $argv[1] ?? ''; $path = '/tmp/hj-snapshot-race-doctor';
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $now = strtotime( '2030-01-01 UTC' );
if ( $mode === 'setup' ) {
	$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic snapshot race', 'post_author' => $doctor->ID ), true );
	if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Synthetic profile failed' ); }
	update_option( 'timezone_string', 'Asia/Tehran' );
	$db = hj_interval_connection();
	try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $doc, 30, 5, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
	wp_set_current_user( $doctor->ID );
	$open = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) );
	if ( hj_spike_publish_snapshot( $doc, $open, 0 )['status'] !== 'published' ) { throw new RuntimeException( 'Snapshot publish failed' ); }
	file_put_contents( $path, (string) $doc ); echo "PASS: snapshot race setup\n"; exit;
}
if ( ! is_file( $path ) ) { throw new RuntimeException( 'Fixture missing' ); }
$doc = (int) file_get_contents( $path );
if ( $mode === 'worker' ) {
	$i = (int) ( $argv[2] ?? -1 ); if ( $i < 0 || $i > 12 ) { exit( 1 ); }
	$user = $i === 12 ? $doctor : get_user_by( 'login', 'synthetic_race_' . $i );
	wp_set_current_user( $user->ID );
	touch( '/tmp/hj-snapshot-ready-' . $i ); $until = microtime( true ) + 30;
	while ( ! file_exists( '/tmp/hj-snapshot-go' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( '/tmp/hj-snapshot-go' ) ) { exit( 2 ); }
	if ( $i === 12 ) {
		$closed = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ) );
		echo hj_spike_publish_snapshot( $doc, $closed, 1 )['status'];
	} else {
		$key = str_pad( dechex( 16000 + $i ), 32, '0', STR_PAD_LEFT );
		echo hj_spike_snapshot_create( $doc, '2030-01-02 05:30:00', $key, '09123456789', '1234567891', $now )['status'];
	}
} elseif ( $mode === 'verify' ) {
	$db = hj_interval_connection();
	try {
		$rows = hj_interval_query( $db, "SELECT id FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status='pending'", 'i', $doc );
		$revision = hj_interval_query( $db, 'SELECT revision FROM hjt_hjb_spike_schedules WHERE doctor_id=?', 'i', $doc );
		if ( count( $rows ) > 1 || (int) $revision[0]['revision'] !== 2 ) { throw new RuntimeException( 'Snapshot race produced unsafe result' ); }
	} finally { $db->close(); }
	wp_set_current_user( get_user_by( 'login', 'synthetic_race_0' )->ID );
	$r = hj_spike_snapshot_create( $doc, '2030-01-02 06:05:00', str_pad( dechex( 17000 ), 32, '0', STR_PAD_LEFT ), '09123456789', '1234567891', $now );
	if ( $r['status'] !== 'invalid_slot' ) { throw new RuntimeException( 'Closed snapshot did not block subsequent booking' ); }
	echo "PASS: snapshot writer and patient race serialized; subsequent booking blocked\n";
} else { exit( 1 ); }

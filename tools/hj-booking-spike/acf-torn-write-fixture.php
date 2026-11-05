<?php
/** Demonstrate a non-cooperating multirow ACF-style writer. Disposable CI ONLY. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
// Model only the visible result of a repeater read, not the ACF Pro implementation.
function get_field( $key, $id ) {
	if ( $key !== 'working_hours' ) { return false; }
	$row = array();
	foreach ( array( 'day_of_week', 'is_closed', 'start_time', 'end_time' ) as $name ) { $row[$name] = get_post_meta( $id, 'working_hours_0_' . $name, true ); }
	return array( $row );
}
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
register_post_type( 'doctor', array( 'public' => true ) );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/schedule-reader-experiment.php';
$path = '/tmp/hj-acf-torn-profile'; $mode = $argv[1] ?? '';
if ( $mode === 'setup' ) {
	$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic torn schedule' ), true );
	if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Synthetic post failed' ); }
	update_option( 'timezone_string', 'Asia/Tehran' );
	update_post_meta( $doc, 'working_hours', '1' );
	foreach ( array( 'day_of_week' => 'wednesday', 'is_closed' => '0', 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) as $name => $value ) { update_post_meta( $doc, 'working_hours_0_' . $name, $value ); }
	$db = hj_interval_connection();
	try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $doc, 30, 0, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
	file_put_contents( $path, (string) $doc );
	echo "PASS: prepared multirow schedule counterexample\n"; exit;
}
if ( ! is_file( $path ) ) { throw new RuntimeException( 'Fixture doctor missing' ); }
$doc = (int) file_get_contents( $path );
function torn_wait( $name ) {
	$until = microtime( true ) + 15;
	while ( ! file_exists( $name ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( $name ) ) { throw new RuntimeException( 'Counterexample barrier timed out' ); }
}
if ( $mode === 'writer' ) {
	update_post_meta( $doc, 'working_hours_0_start_time', '10:00:00' );
	touch( '/tmp/hj-acf-torn-half' );
	torn_wait( '/tmp/hj-acf-torn-read' );
	update_post_meta( $doc, 'working_hours_0_end_time', '11:00:00' );
	touch( '/tmp/hj-acf-torn-done' );
	echo 'writer_done';
} elseif ( $mode === 'book' ) {
	$validator = function ( $db, $config, $start ) use ( $doc ) {
		clean_post_cache( $doc );
		$schedule = hj_spike_read_schedule( $doc );
		if ( $schedule['status'] !== 'ok' ) { return false; }
		$slots = hj_schedule_slots( '2030-01-02', $schedule['rows'], (int) $config['duration_minutes'], (int) $config['buffer_minutes'], array(), strtotime( '2030-01-01 UTC' ), 30 );
		touch( '/tmp/hj-acf-torn-read' );
		torn_wait( '/tmp/hj-acf-torn-done' );
		return in_array( $start, $slots, true );
	};
	$r = hj_interval_create( $doc, 999, '2030-01-02 08:00:00', str_repeat( 'a', 31 ) . 'b', '', $validator );
	echo $r['status'];
} elseif ( $mode === 'verify' ) {
	clean_post_cache( $doc ); $schedule = hj_spike_read_schedule( $doc );
	$slots = hj_schedule_slots( '2030-01-02', $schedule['rows'], 30, 0, array(), strtotime( '2030-01-01 UTC' ), 30 );
	$db = hj_interval_connection();
	try { $rows = hj_interval_query( $db, "SELECT id FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND starts_at='2030-01-02 08:00:00' AND status='pending'", 'i', $doc ); } finally { $db->close(); }
	if ( count( $rows ) !== 1 || in_array( '2030-01-02 08:00:00', $slots, true ) ) { throw new RuntimeException( 'Counterexample did not reproduce' ); }
	echo "PASS: EXPECTED COUNTEREXAMPLE: valid-looking ACF mock snapshot was torn during an uncoordinated writer\n";
} else { exit( 1 ); }

<?php
/** Run only on the disposable GitHub Actions service. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require __DIR__ . '/interval-engine.php';
function expect( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } echo "PASS: $message\n"; }
function key_for( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); }
function config_doctor( $id, $duration = 30, $buffer = 0 ) {
	$db = hj_interval_connection();
	try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $id, $duration, $buffer, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
}
$mode = $argv[1] ?? '';
if ( $mode === 'setup' ) {
	hj_interval_install();
	foreach ( array( 101, 102, 103, 104, 105, 106 ) as $id ) { config_doctor( $id, in_array( $id, array( 103, 106 ), true ) ? 60 : 30 ); }
	for ( $i = 0; $i < 10; $i++ ) {
		$start = gmdate( 'Y-m-d H:i:s', strtotime( '2030-01-01 09:00:00 UTC' ) + $i * 1800 );
		$r = hj_interval_create( 101, 201, $start, key_for( $i + 1 ) );
		expect( $r['status'] === 'created', 'synthetic booking ' . ( $i + 1 ) );
		if ( $i === 0 ) { $first = $r['id']; }
	}
	expect( hj_interval_create( 101, 201, '2030-01-01 09:15:00', key_for( 21 ) )['status'] === 'overlap', 'different-start overlap rejected' );
	expect( hj_interval_create( 101, 201, '2030-01-01 09:00:00', key_for( 1 ) )['status'] === 'replayed', 'idempotency replay' );
	expect( hj_interval_create( 101, 202, '2030-01-01 09:00:00', key_for( 1 ) )['status'] === 'idempotency_conflict', 'different request cannot reuse key' );
	expect( hj_interval_cancel( 101, $first )['status'] === 'cancelled', 'cancel existing' );
	expect( hj_interval_create( 101, 201, '2030-01-01 09:00:00', key_for( 22 ) )['status'] === 'created', 'rebook cancelled slot' );
	expect( hj_interval_configure( 104, 45, 15, '2030-01-01 00:00:00', '2030-01-10 00:00:00' )['status'] === 'configured', 'set duration 45 and buffer 15' );
	expect( hj_interval_create( 104, 201, '2030-01-02 09:00:00', key_for( 23 ) )['status'] === 'created', 'booking uses changed settings' );
	expect( hj_interval_create( 104, 201, '2030-01-02 09:50:00', key_for( 24 ) )['status'] === 'overlap', 'buffer blocks new booking' );
	expect( hj_interval_create( 104, 201, '2030-01-02 10:00:00', key_for( 25 ) )['status'] === 'created', 'half-open boundary accepted' );
	expect( hj_interval_configure( 104, 20, 0, '2030-01-01 00:00:00', '2030-01-10 00:00:00' )['status'] === 'configured', 'change duration and buffer again' );
	expect( hj_interval_create( 104, 201, '2030-01-02 10:44:00', key_for( 26 ) )['status'] === 'overlap', 'prior buffered interval remains occupied' );
	expect( hj_interval_create( 104, 201, '2030-01-02 11:00:00', key_for( 27 ) )['status'] === 'created', 'new duration applies to future request' );
	expect( hj_interval_create( 105, 201, '2030-01-03 09:00:00', key_for( 28 ), 'disconnect_before_commit' )['status'] === 'unknown', 'connection killed inside transaction produces unknown' );
	$r = hj_interval_create( 105, 201, '2030-01-03 09:00:00', key_for( 28 ) );
	expect( $r['status'] === 'created', 'reconnect with same key finds rolled back insert and creates once' );
	expect( hj_interval_create( 105, 201, '2030-01-03 10:00:00', key_for( 29 ), 'lose_commit_response' )['status'] === 'unknown', 'lost successful commit response produces unknown' );
	expect( hj_interval_create( 105, 201, '2030-01-03 10:00:00', key_for( 29 ) )['status'] === 'replayed', 'retry after lost response reconciles committed row' );
} elseif ( $mode === 'race' ) {
	$worker = getenv( 'HJ_SPIKE_WORKER' ); $kind = getenv( 'HJ_SPIKE_KIND' );
	if ( ! is_string( $worker ) || ! preg_match( '/^[0-9]{1,2}$/', $worker ) || ! in_array( $kind, array( 'same', 'overlap', 'mixed' ), true ) ) { exit( 1 ); }
	touch( '/tmp/hj-interval-ready-' . $worker );
	$until = microtime( true ) + 30;
	while ( ! file_exists( '/tmp/hj-interval-go' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( '/tmp/hj-interval-go' ) ) { exit( 2 ); }
	$i = (int) $worker;
	$offset = $kind === 'overlap' ? $i * 300 : ( $kind === 'mixed' ? intdiv( $i, 3 ) * 7200 + ( $i % 3 ) * 300 : 0 );
	$start = gmdate( 'Y-m-d H:i:s', strtotime( '2030-01-02 09:00:00 UTC' ) + $offset );
	$did = $kind === 'same' ? 102 : ( $kind === 'overlap' ? 103 : 106 );
	$r = hj_interval_create( $did, 200 + $i, $start, key_for( 1000 + $i + ( $kind === 'same' ? 0 : ( $kind === 'overlap' ? 100 : 200 ) ) ) );
	echo $r['status'];
} elseif ( $mode === 'verify' ) {
	$db = hj_interval_connection();
	try {
		foreach ( array( 102, 103 ) as $id ) {
			$rows = hj_interval_query( $db, "SELECT COUNT(*) AS n FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed')", 'i', $id );
			expect( (int) $rows[0]['n'] === 1, 'exactly one race winner for doctor ' . $id );
		}
		$rows = hj_interval_query( $db, "SELECT starts_at, blocked_until FROM hjt_hjb_spike_appointments WHERE doctor_id=106 AND status IN ('pending','confirmed') ORDER BY starts_at" );
		expect( count( $rows ) === 4, 'four independent groups each have one race winner' );
		for ( $i = 1; $i < count( $rows ); $i++ ) { expect( $rows[$i-1]['blocked_until'] <= $rows[$i]['starts_at'], 'mixed-race winners are nonoverlapping' ); }
		$rows = hj_interval_query( $db, 'SELECT request_key, COUNT(*) AS n FROM hjt_hjb_spike_appointments WHERE doctor_id=105 GROUP BY request_key' );
		expect( count( $rows ) === 2 && (int) $rows[0]['n'] === 1 && (int) $rows[1]['n'] === 1, 'reconnect did not duplicate either request' );
	} finally { $db->close(); }
} else { exit( 1 ); }

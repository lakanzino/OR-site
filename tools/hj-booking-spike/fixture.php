<?php
/** ONLY on the empty disposable GitHub Actions MariaDB fixture. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $wpdb->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require __DIR__ . '/hj-booking-spike.php';
$mode = $argv[1] ?? '';
if ( 'install' === $mode ) {
	if ( false === hj_booking_spike_install() ) { throw new RuntimeException( 'Could not create disposable spike table: ' . $wpdb->last_error ); }
	for ( $i = 0; $i < 10; ++$i ) {
		$time = gmdate( 'Y-m-d H:i:s', strtotime( '2030-01-01 09:00:00 UTC' ) + $i * 1800 );
		if ( 'reserved' !== hj_booking_spike_reserve( 101, $time ) ) { throw new RuntimeException( 'Failed synthetic reservation ' . $i ); }
	}
	if ( 'unavailable' !== hj_booking_spike_reserve( 101, '2030-01-01 09:00:00' ) ) { throw new RuntimeException( 'Sequential duplicate accepted' ); }
	if ( 'invalid' !== hj_booking_spike_reserve( 0, '2030-01-01 09:00:00' ) ) { throw new RuntimeException( 'Invalid doctor accepted' ); }
	// Demonstrate why this proof of uniqueness cannot be promoted to booking:
	// assuming 30-minute durations, these two starts overlap yet both insert.
	if ( 'reserved' !== hj_booking_spike_reserve( 303, '2030-01-03 09:00:00' ) || 'reserved' !== hj_booking_spike_reserve( 303, '2030-01-03 09:15:00' ) ) { throw new RuntimeException( 'Unexpected behavior in overlap counterexample' ); }
	echo "PASS: ten synthetic fixed slots and duplicate guard\n";
	echo "EXPECTED LIMITATION: unique starts allow differently-started overlapping slots\n";
} elseif ( 'race' === $mode ) {
	$worker = getenv( 'HJ_SPIKE_WORKER' );
	if ( ! is_string( $worker ) || ! preg_match( '/^[0-9]{1,2}$/', $worker ) ) { exit( 1 ); }
	touch( '/tmp/hj-booking-spike-ready-' . $worker );
	$until = microtime( true ) + 30;
	while ( ! file_exists( '/tmp/hj-booking-spike-go' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( '/tmp/hj-booking-spike-go' ) ) { exit( 2 ); }
	echo hj_booking_spike_reserve( 202, '2030-01-02 09:00:00' );
} elseif ( 'verify' === $mode ) {
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hj_booking_spike WHERE doctor_id=202 AND starts_at_utc='2030-01-02 09:00:00'" );
	$initial = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hj_booking_spike WHERE doctor_id=101" );
	if ( 1 !== $total || 10 !== $initial ) { throw new RuntimeException( 'Unexpected row counts' ); }
	echo "PASS: exactly one race winner; ten unrelated synthetic slots retained\n";
} else { exit( 1 ); }

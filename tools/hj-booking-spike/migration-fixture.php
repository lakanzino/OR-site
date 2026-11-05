<?php
/** Destructive only against two fixed synthetic tables in the disposable CI DB. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require __DIR__ . '/migration-experiment.php';
function migration_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
global $wpdb;
function migration_reset() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS hjt_hjb_migration_appointments' );
	$wpdb->query( 'DROP TABLE IF EXISTS hjt_hjb_migration_locks' );
	delete_option( 'hj_booking_spike_schema_version' );
}
try {
	migration_reset();
	migration_expect( hj_spike_migrate( 2 ) === 2, 'fresh v2 migration' );
	migration_expect( hj_spike_migrate( 2 ) === 2, 'v2 migration is repeatable' );
	migration_reset();
	migration_expect( hj_spike_migrate( 1 ) === 1, 'previous synthetic schema v1' );
	$wpdb->insert( 'hjt_hjb_migration_appointments', array( 'doctor_id' => 100, 'patient_id' => 200, 'starts_at' => '2030-01-02 09:00:00', 'ends_at' => '2030-01-02 09:30:00', 'status' => 'pending' ) );
	migration_expect( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM hjt_hjb_migration_appointments' ) === 1, 'seed pre-upgrade record' );
	migration_expect( hj_spike_migrate( 2 ) === 2, 'upgrade v1 to v2' );
	migration_expect( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM hjt_hjb_migration_appointments' ) === 1, 'upgrade preserves existing record' );
	try { hj_spike_migrate( 1 ); throw new RuntimeException( 'Downgrade unexpectedly allowed' ); }
	catch ( RuntimeException $e ) { migration_expect( $e->getMessage() === 'Refusing schema downgrade', 'schema downgrade refused' ); }
} finally { migration_reset(); }

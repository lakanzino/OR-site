<?php
/** Destructive only against fixed migration-test tables in the disposable CI DB. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require __DIR__ . '/migration-experiment.php';
function migration_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
global $wpdb;
function migration_reset() {
	global $wpdb;
	foreach ( array( 'hjt_hjb_migration_appointments', 'hjt_hjb_migration_doctors', 'hjt_hjb_migration_details', 'hjt_hjb_migration_secretaries', 'hjt_hjb_migration_schedules' ) as $table ) { $wpdb->query( "DROP TABLE IF EXISTS `$table`" ); }
	delete_option( 'hjb_migration_schema_version' );
}
try {
	migration_reset();
	migration_expect( hj_spike_migrate( 3, 'hjb_migration_' ) === 3, 'fresh v3 migration' );
	migration_expect( hj_spike_migrate( 3, 'hjb_migration_' ) === 3, 'v3 migration is repeatable' );
	migration_reset();
	migration_expect( hj_spike_migrate( 1, 'hjb_migration_' ) === 1, 'previous synthetic schema v1' );
	$wpdb->insert( 'hjt_hjb_migration_appointments', array( 'doctor_id' => 100, 'patient_id' => 200, 'request_key' => str_repeat( 'a', 32 ), 'request_hash' => str_repeat( 'b', 64 ), 'starts_at' => '2030-01-02 09:00:00', 'ends_at' => '2030-01-02 09:30:00', 'blocked_until' => '2030-01-02 09:30:00', 'status' => 'pending' ) );
	migration_expect( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM hjt_hjb_migration_appointments' ) === 1, 'seed pre-upgrade record' );
	migration_expect( hj_spike_migrate( 2, 'hjb_migration_' ) === 2, 'upgrade v1 to v2' );
	migration_expect( hj_spike_migrate( 3, 'hjb_migration_' ) === 3, 'upgrade v2 to v3' );
	migration_expect( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM hjt_hjb_migration_appointments' ) === 1, 'upgrade preserves existing record' );
	try { hj_spike_migrate( 1, 'hjb_migration_' ); throw new RuntimeException( 'Downgrade unexpectedly allowed' ); }
	catch ( RuntimeException $e ) { migration_expect( $e->getMessage() === 'Refusing schema downgrade', 'schema downgrade refused' ); }
} finally { migration_reset(); }

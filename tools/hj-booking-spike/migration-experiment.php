<?php
/** Versioned migration experiment, disposable CI only, not production installer. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }
function hj_spike_migrate( $target = 2 ) {
	global $wpdb;
	if ( ! in_array( $target, array( 1, 2 ), true ) ) { throw new InvalidArgumentException( 'Unsupported target' ); }
	$current = (int) get_option( 'hj_booking_spike_schema_version', 0 );
	if ( $current > $target ) { throw new RuntimeException( 'Refusing schema downgrade' ); }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$lock = $wpdb->prefix . 'hjb_migration_locks';
	$table = $wpdb->prefix . 'hjb_migration_appointments';
	// Strictly controlled by the guarded fixture. No destructive reset in this function.
	if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $lock ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', $table ) ) { throw new RuntimeException( 'Unsupported prefix' ); }
	$extra = $target >= 2 ? "phone varchar(24) NOT NULL DEFAULT '',\n national_code char(10) NOT NULL DEFAULT '',\n" : '';
	dbDelta( "CREATE TABLE $lock (
 doctor_id bigint(20) unsigned NOT NULL,
 PRIMARY KEY  (doctor_id)
) ENGINE=InnoDB $charset;" );
	dbDelta( "CREATE TABLE $table (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 doctor_id bigint(20) unsigned NOT NULL,
 patient_id bigint(20) unsigned NOT NULL,
 starts_at datetime NOT NULL,
 ends_at datetime NOT NULL,
 status varchar(20) NOT NULL,
 $extra PRIMARY KEY  (id),
 KEY doctor_start (doctor_id,starts_at),
 KEY patient_start (patient_id,starts_at)
) ENGINE=InnoDB $charset;" );
	foreach ( array( $lock, $table ) as $name ) {
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
		if ( ! is_array( $status ) || strtoupper( $status['Engine'] ?? '' ) !== 'INNODB' ) { throw new RuntimeException( 'Schema missing or non-InnoDB' ); }
	}
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );
	$required = array( 'id', 'doctor_id', 'patient_id', 'starts_at', 'ends_at', 'status' );
	if ( $target >= 2 ) { $required = array_merge( $required, array( 'phone', 'national_code' ) ); }
	if ( array_diff( $required, $columns ) ) { throw new RuntimeException( 'Schema incomplete; version not advanced' ); }
	update_option( 'hj_booking_spike_schema_version', $target, false );
	if ( (int) get_option( 'hj_booking_spike_schema_version' ) !== $target ) { throw new RuntimeException( 'Could not persist schema version' ); }
	return $target;
}

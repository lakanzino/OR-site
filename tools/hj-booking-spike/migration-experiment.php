<?php
/** Versioned migration experiment, disposable CI only, not production installer. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }
/** Real spike tables and a separate migration-test namespace use identical schema. */
function hj_spike_migrate( $target = 2, $namespace = 'hjb_spike_' ) {
	global $wpdb;
	if ( ! in_array( $target, array( 1, 2 ), true ) || ! in_array( $namespace, array( 'hjb_spike_', 'hjb_migration_' ), true ) ) { throw new InvalidArgumentException( 'Unsupported schema target' ); }
	$base = $wpdb->prefix . $namespace;
	$option = $namespace . 'schema_version';
	$current = (int) get_option( $option, 0 );
	if ( $current > $target ) { throw new RuntimeException( 'Refusing schema downgrade' ); }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$doctors = $base . 'doctors'; $appointments = $base . 'appointments'; $details = $base . 'details'; $secretaries = $base . 'secretaries';
	dbDelta( "CREATE TABLE $doctors (
 doctor_id bigint(20) unsigned NOT NULL,
 duration_minutes int NOT NULL,
 buffer_minutes int NOT NULL,
 window_start datetime NOT NULL,
 window_end datetime NOT NULL,
 PRIMARY KEY  (doctor_id)
) ENGINE=InnoDB $charset;" );
	dbDelta( "CREATE TABLE $appointments (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 doctor_id bigint(20) unsigned NOT NULL,
 patient_id bigint(20) unsigned NOT NULL,
 request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 starts_at datetime NOT NULL,
 ends_at datetime NOT NULL,
 blocked_until datetime NOT NULL,
 status varchar(20) NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY request_key (request_key),
 KEY doctor_start (doctor_id,starts_at),
 KEY patient_start (patient_id,starts_at)
) ENGINE=InnoDB $charset;" );
	$required = array( $doctors => array( 'doctor_id', 'duration_minutes', 'buffer_minutes', 'window_start', 'window_end' ), $appointments => array( 'id', 'doctor_id', 'patient_id', 'request_key', 'request_hash', 'starts_at', 'ends_at', 'blocked_until', 'status' ) );
	if ( $target >= 2 ) {
		dbDelta( "CREATE TABLE $details (
 appointment_id bigint(20) unsigned NOT NULL,
 phone varchar(24) NOT NULL,
 national_code char(10) NOT NULL,
 PRIMARY KEY  (appointment_id)
) ENGINE=InnoDB $charset;" );
		dbDelta( "CREATE TABLE $secretaries (
 user_id bigint(20) unsigned NOT NULL,
 doctor_id bigint(20) unsigned NOT NULL,
 PRIMARY KEY  (user_id,doctor_id)
) ENGINE=InnoDB $charset;" );
		$required[$details] = array( 'appointment_id', 'phone', 'national_code' );
		$required[$secretaries] = array( 'user_id', 'doctor_id' );
	}
	foreach ( $required as $name => $fields ) {
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ), ARRAY_A );
		if ( ! is_array( $status ) || strtoupper( $status['Engine'] ?? '' ) !== 'INNODB' ) { throw new RuntimeException( 'Schema missing or non-InnoDB' ); }
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `$name`" );
		if ( array_diff( $fields, $columns ) ) { throw new RuntimeException( 'Schema incomplete; version not advanced' ); }
	}
	$indexes = $wpdb->get_results( "SHOW INDEX FROM `$appointments`", ARRAY_A );
	$unique = false;
	foreach ( $indexes as $index ) { if ( $index['Key_name'] === 'request_key' && (int) $index['Non_unique'] === 0 && $index['Column_name'] === 'request_key' ) { $unique = true; } }
	if ( ! $unique ) { throw new RuntimeException( 'Missing idempotency constraint' ); }
	update_option( $option, $target, false );
	if ( (int) get_option( $option ) !== $target ) { throw new RuntimeException( 'Could not persist schema version' ); }
	return $target;
}

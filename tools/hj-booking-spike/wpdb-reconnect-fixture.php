<?php
/** Demonstrates stock wpdb reconnect behavior in empty disposable CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
$first = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$observer = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$first->suppress_errors( true ); $observer->suppress_errors( true );
if ( false === $observer->query( 'CREATE TABLE hjt_hjb_wpdb_reconnect_test (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB' ) ) { throw new RuntimeException( 'Could not create synthetic table' ); }
try {
	if ( false === $first->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Could not start transaction' ); }
	$id = (int) $first->get_var( 'SELECT CONNECTION_ID()' );
	if ( $id < 1 || false === $observer->query( 'KILL CONNECTION ' . $id ) ) { throw new RuntimeException( 'Could not kill synthetic connection' ); }
	// Intentionally unsafe: demonstrate that retrying through stock wpdb may
	// reconnect and run a write on a fresh autocommit connection without a lock.
	$sql = 'INSERT IGNORE INTO hjt_hjb_wpdb_reconnect_test (id) VALUES (1)';
	$first->query( $sql );
	$first->query( $sql );
	$new = (int) $first->get_var( 'SELECT CONNECTION_ID()' );
	$count = (int) $observer->get_var( 'SELECT COUNT(*) FROM hjt_hjb_wpdb_reconnect_test WHERE id=1' );
	if ( $new === $id || $new < 1 || $count !== 1 ) { throw new RuntimeException( 'Could not reproduce unguarded wpdb reconnect counterexample' ); }
	echo "PASS: EXPECTED COUNTEREXAMPLE: stock wpdb reconnected and wrote outside killed transaction\n";
} finally { $observer->query( 'DROP TABLE IF EXISTS hjt_hjb_wpdb_reconnect_test' ); $first->close(); $observer->close(); }

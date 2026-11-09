<?php
/** CI-only transaction holds a synthetic user's claim to test bounded waits. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
$token = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{32}$/D', $token ) ) { exit( 1 ); }
$user = get_user_by( 'login', 'synthetic_claim_lock' );
if ( ! $user ) { throw new RuntimeException( 'Synthetic account missing' ); }
$prefix = getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-test-profile-lock-' . $token;
$db = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); $db->suppress_errors( true );
try {
 if ( false === $db->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Cannot start fixture transaction' ); }
 $table = $GLOBALS['wpdb']->options; $name = 'hj_doctors_first_profile_claim_' . $user->ID;
 if ( 1 !== $db->query( $db->prepare( "INSERT INTO `$table` (option_name, option_value, autoload) VALUES (%s, 'fixture', 'no')", $name ) ) ) { throw new RuntimeException( 'Cannot hold fixture claim' ); }
 if ( false === file_put_contents( $prefix . '-ready', 'ready' ) ) { throw new RuntimeException( 'Cannot signal fixture readiness' ); }
 $until = microtime( true ) + 25;
 while ( ! is_file( $prefix . '-released' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
 if ( ! is_file( $prefix . '-released' ) ) { throw new RuntimeException( 'Fixture lock timeout' ); }
} finally { $db->query( 'ROLLBACK' ); $db->close(); }

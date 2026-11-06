<?php
/** Unregister only the disposable plugin; the whole database is destroyed after the CI job. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
$active = (array) get_option( 'active_plugins', array() );
update_option( 'active_plugins', array_values( array_diff( $active, array( 'hamyar-doctors/hamyar-doctors.php' ) ) ) );

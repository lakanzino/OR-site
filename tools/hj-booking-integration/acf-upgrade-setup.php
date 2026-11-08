<?php
/** CI-only: activate ACF and synthetic plugin, then set up one synthetic surgeon. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
$file = getenv( 'RUNNER_TEMP' ) . '/hj-acf-active-original.json';
$mode = $argv[1] ?? '';
if ( $mode === 'activate' ) {
 if ( file_exists( $file ) ) { throw new RuntimeException( 'Existing ACF fixture state' ); }
 file_put_contents( $file, wp_json_encode( get_option( 'active_plugins', array() ) ) );
 update_option( 'active_plugins', array( 'advanced-custom-fields/acf.php', 'hamyar-doctors/hamyar-doctors.php' ) );
} elseif ( $mode === 'create' ) {
 if ( ! function_exists( 'acf' ) || acf()->settings['version'] !== '6.8.11' || HJ_DOCTORS_VERSION !== '0.3.22' || ! get_role( 'doctor' ) ) { throw new RuntimeException( 'Wrong plugin versions or missing doctor role' ); }
 if ( get_user_by( 'login', 'synthetic_acf_upgrade' ) ) { throw new RuntimeException( 'ACF fixture user already exists' ); }
 $id = wp_insert_user( array( 'user_login'=>'synthetic_acf_upgrade', 'user_pass'=>'disposable-test-password', 'role'=>'doctor' ) );
 if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Synthetic ACF user creation failed' ); }
} elseif ( $mode === 'restore' ) {
 if ( is_file( $file ) ) { $active = json_decode( file_get_contents( $file ), true ); if ( ! is_array( $active ) ) { throw new RuntimeException( 'Invalid fixture state' ); } update_option( 'active_plugins', $active ); unlink( $file ); }
} else { exit( 1 ); }

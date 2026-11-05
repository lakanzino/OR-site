<?php
/** Read-only verification for isolated CI fixture only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || getenv( 'HJ_TEST_PLUGIN' ) !== 'review-marker-http-current' ) { exit( 1 ); }
require '/wordpress/wp-load.php'; global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $wpdb->prefix !== 'hjt_' ) { exit( 1 ); }
$meta = json_decode( file_get_contents( getenv( 'RUNNER_TEMP' ) . '/review-marker-meta.json' ), true );
$table = $wpdb->prefix . 'hj_doctor_votes'; $after = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
if ( ! is_array( $meta ) || ! is_array( $after ) || $after !== $meta['before'] ) { throw new RuntimeException( 'HTTP requests mutated synthetic votes.' ); }
echo "PASS: AJAX reads preserve all synthetic vote rows.\n";

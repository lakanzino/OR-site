<?php
/** Verify the synthetic prior row survived ALTER and import; disposable CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || getenv( 'HJ_TEST_PLUGIN' ) !== 'vote-lab-http-0.3.12' ) { exit( 1 ); }
require '/wordpress/wp-load.php'; global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $wpdb->prefix !== 'hjt_' ) { exit( 1 ); }
$table = $wpdb->prefix . 'hj_doctor_votes';
$old = $wpdb->get_row( "SELECT * FROM `{$table}` WHERE id = 12345", ARRAY_A );
if ( ! is_array( $old ) || (int) $old['post_id'] !== 424242 || (int) $old['user_id'] !== 9 || (int) $old['rating'] !== 3 || $old['ip_hash'] !== str_repeat( 'a', 64 ) || $old['status'] !== 'approved' || $old['source'] !== 'fixture' || $old['reason'] !== 'synthetic existing row' || $old['voted_at'] !== '2026-01-01 12:00:00' || $old['created_at'] !== '2026-01-01 12:00:00' || $old['comment'] !== null ) { throw new RuntimeException( 'Existing synthetic row changed.' ); }
$new = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE id <> 12345 ORDER BY id", ARRAY_A );
if ( count( $new ) !== 2 ) { throw new RuntimeException( 'Unexpected inserted rows.' ); }
foreach ( $new as $row ) {
	if ( (int) $row['user_id'] !== 0 || strlen( $row['ip_hash'] ) !== 64 || $row['source'] !== 'demo' || $row['reason'] !== 'demo' || ! $row['voted_at'] || ! $row['created_at'] || strpos( $row['comment'], '[نظر آزمایشی HJ-' ) !== 0 ) { throw new RuntimeException( 'Inserted fixture fields invalid.' ); }
}
echo "PASS: existing synthetic row unchanged, new required fields populated, new comment column.\n";

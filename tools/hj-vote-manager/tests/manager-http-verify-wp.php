<?php
/** DB checks and controlled audit-failure setup in throwaway CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || ! in_array( getenv( 'HJ_TEST_PLUGIN' ), array( 'vote-manager-http-0.3.12', 'vote-manager-http-current' ), true ) ) { exit( 1 ); }
require '/wordpress/wp-load.php'; global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $wpdb->prefix !== 'hjt_' ) { exit( 1 ); }
$vote = $wpdb->prefix . 'hj_doctor_votes'; $audit = $wpdb->prefix . 'hj_vote_manager_audit_v1'; $action = $argv[1] ?? '';
if ( $action === 'initial' ) {
	$tables = $wpdb->get_col( 'SHOW TABLES' );
	if ( ! is_array( $tables ) || in_array( $audit, $tables, true ) || (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$vote}`" ) !== 54 ) { throw new RuntimeException( 'Unexpected initial state.' ); }
	echo "PASS: page/denied requests created no audit table or rows.\n"; exit;
}
if ( $action === 'fail-audit-setup' ) {
	if ( false === $wpdb->query( "ALTER TABLE `{$audit}` ADD CONSTRAINT fixture_reason CHECK (reason <> 'force audit failure')" ) ) { throw new RuntimeException( 'Could not create synthetic failure constraint.' ); }
	echo "PASS: audit failure condition installed.\n"; exit;
}
if ( $action === 'trigger-create' ) { if ( false === $wpdb->query( "CREATE TRIGGER hjt_fixture_guard BEFORE DELETE ON `{$vote}` FOR EACH ROW SET @hj_fixture_flag = 1" ) ) { throw new RuntimeException( 'Fixture trigger failed.' ); } exit; }
if ( $action === 'trigger-drop' ) { if ( false === $wpdb->query( 'DROP TRIGGER hjt_fixture_guard' ) ) { throw new RuntimeException( 'Fixture trigger removal failed.' ); } exit; }
if ( $action === 'foreign-key-create' ) { if ( false === $wpdb->query( "CREATE TABLE hjt_fixture_ref (ref_id BIGINT UNSIGNED NOT NULL, CONSTRAINT fixture_ref FOREIGN KEY (ref_id) REFERENCES `{$vote}` (id)) ENGINE=InnoDB" ) ) { throw new RuntimeException( 'Fixture foreign key failed.' ); } exit; }
if ( $action === 'foreign-key-drop' ) { if ( false === $wpdb->query( 'DROP TABLE hjt_fixture_ref' ) ) { throw new RuntimeException( 'Fixture foreign key removal failed.' ); } exit; }
if ( $action === 'touch-unrelated-column' ) { if ( 1 !== $wpdb->query( "UPDATE `{$vote}` SET ip_hash = 'changed-by-synthetic-writer' WHERE id=102" ) ) { throw new RuntimeException( 'Fixture race failed.' ); } exit; }
if ( ! in_array( $action, array( 'final', 'delete-final' ), true ) ) { exit( 1 ); }
$a = $wpdb->get_row( "SELECT * FROM `{$vote}` WHERE id=101", ARRAY_A );
$b = $wpdb->get_row( "SELECT * FROM `{$vote}` WHERE id=102", ARRAY_A );
$c = $wpdb->get_row( "SELECT * FROM `{$vote}` WHERE id=103", ARRAY_A );
$history = $wpdb->get_results( "SELECT * FROM `{$audit}` ORDER BY log_id", ARRAY_A );
$prefix = '[نظر آزمایشی HJ-12345678-1234-4567-8123-123456789abc] ';
if ( ! is_array( $a ) || $a['status'] !== 'rejected' || (int) $a['rating'] !== 2 || $a['comment'] !== $prefix . 'Synthetic revised' || $a['ip_hash'] !== str_repeat( 'a', 64 ) || $a['source'] !== 'fixture' || $a['voted_at'] !== '2026-01-01 12:00:00' ) { throw new RuntimeException( 'First vote not correct or failure did not roll back.' ); }
if ( $action === 'final' && ( ! is_array( $b ) || $b['status'] !== 'approved' || (int) $b['rating'] !== 3 || $b['comment'] !== '' || $b['ip_hash'] !== 'changed-by-synthetic-writer' ) ) { throw new RuntimeException( 'Empty comment update or delete rollback failed.' ); }
if ( $action === 'delete-final' && ( $b !== null || (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$vote}`" ) !== 53 ) ) { throw new RuntimeException( 'Expected exactly one deleted row.' ); }
if ( ! is_array( $c ) || $c['status'] !== 'approved' || (int) $c['rating'] !== 4 || $c['comment'] !== 'Unmarked profile synthetic comment' || $c['ip_hash'] !== str_repeat( 'c', 64 ) ) { throw new RuntimeException( 'Unmarked vote changed.' ); }
if ( ! is_array( $history ) || count( $history ) !== ( $action === 'delete-final' ? 3 : 2 ) || $history[0]['old_status'] !== 'approved' || $history[0]['new_status'] !== 'rejected' || (int) $history[0]['old_rating'] !== 5 || (int) $history[0]['new_rating'] !== 2 || $history[0]['reason'] !== 'Synthetic moderation reason' || (int) $history[0]['actor_id'] < 1 || $history[0]['old_comment'] !== $prefix . 'Synthetic comment' || $history[0]['new_comment'] !== $prefix . 'Synthetic revised' || $history[1]['old_comment'] !== null || $history[1]['new_comment'] !== '' ) { throw new RuntimeException( 'Audit does not contain exact old/new values or rollback failed.' ); }
if ( $action === 'delete-final' && ( (int) $history[2]['vote_id'] !== 102 || $history[2]['old_status'] !== 'approved' || $history[2]['new_status'] !== 'deleted' || (int) $history[2]['old_rating'] !== 3 || (int) $history[2]['new_rating'] !== 0 || $history[2]['old_comment'] !== '' || $history[2]['new_comment'] !== null || $history[2]['reason'] !== 'Delete synthetic vote' || (int) $history[2]['actor_id'] < 1 ) ) { throw new RuntimeException( 'Delete audit did not record correct details.' ); }
$meta = json_decode( file_get_contents( getenv( 'RUNNER_TEMP' ) . '/manager-test-meta.json' ), true );
if ( ! class_exists( 'HJ_Profile_Render' ) || ! is_array( $meta ) || HJ_Profile_Render::public_reviews( (int) $meta['demo'] ) !== array() ) { throw new RuntimeException( 'Rejected/empty comments should not be public in the installed baseline reader.' ); }
echo "PASS: synthetic votes, audit, marker preservation, profile reader and failure rollback verified.\n";

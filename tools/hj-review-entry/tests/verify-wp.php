<?php
/** Read-only verification in disposable GitHub Actions MariaDB only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || ! in_array( getenv( 'HJ_TEST_PLUGIN' ), array( 'review-entry-http-0.3.12', 'review-entry-http-current' ), true ) ) { exit( 1 ); }
require '/wordpress/wp-load.php'; global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $wpdb->prefix !== 'hjt_' ) { exit( 1 ); }
$meta = json_decode( file_get_contents( getenv( 'RUNNER_TEMP' ) . '/review-entry-meta.json' ), true ); $table = $wpdb->prefix . 'hj_doctor_votes';
$action = $argv[1] ?? 'verify';
if ( $action === 'unknown-add' ) { if ( false === $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN unknown_required INT NOT NULL" ) ) { throw new RuntimeException( 'Fixture alteration failed.' ); } exit; }
if ( $action === 'unknown-drop' ) { if ( false === $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN unknown_required" ) ) { throw new RuntimeException( 'Fixture alteration failed.' ); } exit; }
if ( $action === 'myisam' ) { if ( false === $wpdb->query( "ALTER TABLE `{$table}` ENGINE=MyISAM" ) ) { throw new RuntimeException( 'Fixture engine change failed.' ); } exit; }
if ( $action === 'innodb' ) { if ( false === $wpdb->query( "ALTER TABLE `{$table}` ENGINE=InnoDB" ) ) { throw new RuntimeException( 'Fixture engine restoration failed.' ); } exit; }
if ( $action !== 'verify' ) { exit( 1 ); }
$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A ); $tables = $wpdb->get_col( 'SHOW TABLES' ); sort( $tables );
if ( ! is_array( $meta ) || ! is_array( $rows ) || count( $rows ) !== 3 || $tables !== $meta['tables'] ) { throw new RuntimeException( 'Expected two inserts and no additional table.' ); }
if ( (int) $rows[0]['id'] !== 100 || $rows[0]['reason'] !== 'untouched' || $rows[0]['comment'] !== 'Existing synthetic review' || $rows[0]['ip_hash'] !== str_repeat( 'a', 64 ) ) { throw new RuntimeException( 'Existing vote changed.' ); }
if ( (int) $rows[1]['post_id'] !== $meta['doctor'] || $rows[1]['comment'] !== 'Synthetic Name — Synthetic comment' || $rows[1]['rating'] !== '4' || $rows[1]['status'] !== 'approved' || $rows[1]['created_at'] !== '2024-05-17 12:00:00' || $rows[1]['voted_at'] !== '2024-05-17 12:00:00' || $rows[1]['source'] !== '' || $rows[1]['reason'] !== '' || (int) $rows[1]['user_id'] !== 0 ) { throw new RuntimeException( 'Inserted review fields do not match form.' ); }
if ( $rows[2]['comment'] !== 'Second Name — Second comment' || $rows[2]['rating'] !== '5' || $rows[2]['created_at'] !== '2025-08-01 12:00:00' || $rows[1]['ip_hash'] === $rows[2]['ip_hash'] ) { throw new RuntimeException( 'Second synthetic vote is not correct or placeholder not unique.' ); }
foreach ( array( $rows[1]['ip_hash'], $rows[2]['ip_hash'] ) as $hash ) {
	if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $hash ) || in_array( $hash, array( hash( 'sha256', '127.0.0.1' ), hash( 'sha256', '203.0.113.99' ), '127.0.0.1', '203.0.113.99' ), true ) ) { throw new RuntimeException( 'Expected random placeholder, never IP or its hash.' ); }
}
$reviews = HJ_Profile_Render::public_reviews( (int) $meta['doctor'] );
$current = getenv( 'HJ_TEST_PLUGIN' ) === 'review-entry-http-current';
$expected_text = $current ? 'Synthetic comment' : 'Synthetic Name — Synthetic comment';
$expected_author = $current ? 'Synthetic Name' : 'نظر ثبت‌شده در سایت';
if ( count( $reviews ) !== 2 || $reviews[1]['comment'] !== $expected_text || $reviews[1]['date'] !== '2024/05/17' || (int) $reviews[1]['rating'] !== 4 || $reviews[1]['author'] !== $expected_author ) { throw new RuntimeException( 'Public reader differs from expected fields.' ); }
if ( $current ) {
	$page = HJ_Profile_Page::render( (int) $meta['doctor'] );
	$alternate = HJ_Profile_Render::get_instance()->render_profile( (int) $meta['doctor'] );
	if ( strpos( $page, '<strong>Synthetic Name</strong>' ) === false || strpos( $page, '<p>Synthetic comment</p>' ) === false || strpos( $page, 'نظر ثبت‌شده در سایت' ) !== false || strpos( $alternate, 'class="hj-review__author">Synthetic Name</span>' ) === false || strpos( $alternate, 'class="hj-review__body">Synthetic comment</div>' ) === false || strpos( $alternate, 'نظر ثبت‌شده در سایت' ) !== false ) { throw new RuntimeException( 'Public profile output did not place the name separately.' ); }
}
echo "PASS: two entries, dates, public reader, no IP, placeholder and untouched existing record.\n";

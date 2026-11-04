<?php
/** Creates a vote table. Run only in an isolated, disposable WordPress database. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST ) { exit; }
require '/wordpress/wp-load.php';
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
header( 'Content-Type: text/plain; charset=utf-8' );
function hj_vote_schema_assert( $value, $message ) {
	if ( ! $value ) { http_response_code( 500 ); throw new Exception( 'FAIL: ' . $message ); }
	echo 'PASS: ' . $message . "\n";
}
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes';
if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) { throw new Exception( 'Unexpected fixture table name' ); }
$empty = HJ_Doctors_Rating_Bridge::schema();
hj_vote_schema_assert( 'نامعلوم یا شناسایی نشد' === hj_doctors_vote_schema_summary( $empty )['table'], 'missing vote table reported as unknown' );
$result = $wpdb->query( "CREATE TABLE `{$table}` (id INTEGER PRIMARY KEY, doctor_id INTEGER NOT NULL, rating INTEGER NOT NULL)" );
if ( false === $result ) { throw new Exception( 'Could not create disposable fixture table: ' . $wpdb->last_error ); }
$cache = new ReflectionProperty( 'HJ_Doctors_Rating_Bridge', 'schema_cache' );
$cache->setAccessible( true );
$cache->setValue( null, null );
$schema = HJ_Doctors_Rating_Bridge::schema();
$summary = hj_doctors_vote_schema_summary( $schema );
hj_vote_schema_assert( ! empty( $schema['ok'] ) && 'توسط پل رأی شناسایی شد' === $summary['table'], 'bridge discovers fixture vote table' );
foreach ( array( 'id', 'doctor_id', 'rating' ) as $key ) {
	hj_vote_schema_assert( 'شناسایی شد' === $summary[ $key ], 'recognized fixture mapping: ' . $key );
}
hj_vote_schema_assert( 'شناسایی نشد' === $summary['status'], 'absent status column is not treated as approval' );
$details = hj_doctors_vote_structure_details( $schema );
hj_vote_schema_assert( 'نام جدول یکسان است' === $details['profile_table'] && 'بله' === $details['primary_id'] && 'نامعلوم' === $details['status_type'], 'single primary key and absent status metadata' );
$inserted = $wpdb->insert( $table, array( 'id' => 12345, 'doctor_id' => 424242, 'rating' => 3 ), array( '%d', '%d', '%d' ) );
hj_vote_schema_assert( 1 === $inserted, 'disposable vote fixture inserted' );
$before = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
hj_vote_schema_assert( is_array( $before ) && count( $before ) === 1, 'fixture vote row exists' );
wp_set_current_user( 1 );
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start(); hj_doctors_data_status_page(); $html = ob_get_clean();
$after = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
hj_vote_schema_assert( $before === $after, 'report did not mutate fixture vote rows' );
hj_vote_schema_assert( false !== strpos( $html, 'جدول رأی</th><td>توسط پل رأی شناسایی شد</td>' ) && false !== strpos( $html, 'ستون وضعیت</th><td>شناسایی نشد</td>' ), 'report displays recognized table and absent status' );
hj_vote_schema_assert( false !== strpos( $html, 'شناسه به‌تنهایی کلید اصلی است</th><td>بله</td>' ) && false !== strpos( $html, 'جدول پل و جدول پیش‌فرض نمایش نظر</th><td>نام جدول یکسان است</td>' ), 'report displays metadata without column names' );
hj_vote_schema_assert( false === strpos( $html, '424242' ) && false === strpos( $html, '12345' ), 'report does not expose fixture vote or doctor identifiers' );
$result = $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN status VARCHAR(20)" );
if ( false === $result ) { throw new Exception( 'Could not add fixture status column: ' . $wpdb->last_error ); }
$cache->setValue( null, null );
$schema = HJ_Doctors_Rating_Bridge::schema();
$summary = hj_doctors_vote_schema_summary( $schema );
hj_vote_schema_assert( 'شناسایی شد' === $summary['status'], 'status column recognized after schema refresh' );
$details = hj_doctors_vote_structure_details( $schema );
hj_vote_schema_assert( 'بله' === $details['primary_id'] && 'متنی یا فهرستی' === $details['status_type'], 'primary key and text status metadata' );
echo "DONE: disposable vote schema fixture.\n";
file_put_contents( '/wordpress/hj-vote-schema-result.txt', "PASS: disposable vote schema fixture\n" );

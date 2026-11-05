<?php
/** Destructive fixture for an empty, isolated CI WordPress/MariaDB only. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || ! HJ_DISPOSABLE_TEST || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes'; $tables = $wpdb->get_col( 'SHOW TABLES' );
if ( $wpdb->prefix !== 'hjt_' || ! is_array( $tables ) || $wpdb->last_error !== '' || in_array( $table, $tables, true ) ) { throw new RuntimeException( 'Unexpected fixture.' ); }
if ( false === $wpdb->query( "CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, rating TINYINT UNSIGNED NOT NULL, ip_hash CHAR(64) NOT NULL, status VARCHAR(12) NOT NULL, source VARCHAR(10) NOT NULL, reason TEXT NULL, voted_at DATETIME NOT NULL, created_at DATETIME NOT NULL, comment TEXT NULL) ENGINE=InnoDB" ) ) { throw new RuntimeException( 'Fixture table failed.' ); }
$dest = WP_PLUGIN_DIR . '/hj-vote-manager'; if ( file_exists( $dest ) || ! mkdir( $dest ) || ! copy( getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-vote-manager/hj-vote-manager.php', $dest . '/hj-vote-manager.php' ) ) { throw new RuntimeException( 'Fixture plugin copy failed.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'hamyar-doctors/hamyar-doctors.php', 'hj-vote-manager/hj-vote-manager.php' ) as $p ) { $r = activate_plugin( $p ); if ( is_wp_error( $r ) ) { throw new RuntimeException( 'Activation failed.' ); } }
$demo = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic manager doctor' ), true );
$other = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Unmarked doctor' ), true );
$many = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Pagination synthetic' ), true );
if ( is_wp_error( $demo ) || is_wp_error( $other ) || is_wp_error( $many ) || ! $demo || ! $other || ! $many ) { throw new RuntimeException( 'Doctor setup failed.' ); }
update_post_meta( $demo, '_hj_demo_doctor', 'synthetic-fixture' );
update_post_meta( $many, '_hj_demo_doctor', 'synthetic-pagination' );
$sub = wp_create_user( 'manager_subscriber', 'disposable-password', 'manager-sub@example.invalid' );
if ( is_wp_error( $sub ) ) { throw new RuntimeException( 'User setup failed.' ); }
$rows = array(
	array( 'id' => 101, 'post_id' => $demo, 'user_id' => 0, 'rating' => 5, 'ip_hash' => str_repeat( 'a', 64 ), 'status' => 'approved', 'source' => 'fixture', 'reason' => null, 'voted_at' => '2026-01-01 12:00:00', 'created_at' => '2026-01-01 12:00:00', 'comment' => '[نظر آزمایشی HJ-12345678-1234-4567-8123-123456789abc] Synthetic comment' ),
	array( 'id' => 102, 'post_id' => $demo, 'user_id' => 0, 'rating' => 3, 'ip_hash' => str_repeat( 'b', 64 ), 'status' => 'pending', 'source' => 'fixture', 'reason' => null, 'voted_at' => '2026-01-01 12:00:00', 'created_at' => '2026-01-01 12:00:00', 'comment' => null ),
	array( 'id' => 103, 'post_id' => $other, 'user_id' => 7, 'rating' => 4, 'ip_hash' => str_repeat( 'c', 64 ), 'status' => 'approved', 'source' => 'fixture', 'reason' => 'synthetic original', 'voted_at' => '2026-01-01 12:00:00', 'created_at' => '2026-01-01 12:00:00', 'comment' => 'Unmarked profile synthetic comment' ),
);
foreach ( $rows as $row ) { if ( 1 !== $wpdb->insert( $table, $row ) ) { throw new RuntimeException( 'Vote setup failed.' ); } }
for ( $i = 0; $i < 51; $i++ ) { $row = $rows[0]; unset( $row['id'] ); $row['post_id'] = $many; $row['comment'] = 'Pagination fixture ' . $i; if ( 1 !== $wpdb->insert( $table, $row ) ) { throw new RuntimeException( 'Pagination fixture failed.' ); } }
file_put_contents( getenv( 'RUNNER_TEMP' ) . '/manager-test-meta.json', wp_json_encode( array( 'demo' => $demo, 'other' => $other, 'many' => $many ) ) );
echo "PASS: disposable manager HTTP fixture ready.\n";

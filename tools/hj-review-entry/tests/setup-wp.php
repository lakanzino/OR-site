<?php
/** Destructive synthetic setup in fresh CI MariaDB only, never production. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || ! HJ_DISPOSABLE_TEST || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
global $wpdb; $table = $wpdb->prefix . 'hj_doctor_votes'; $tables = $wpdb->get_col( 'SHOW TABLES' );
if ( $wpdb->prefix !== 'hjt_' || ! is_array( $tables ) || $wpdb->last_error !== '' || in_array( $table, $tables, true ) ) { throw new RuntimeException( 'Unexpected fixture.' ); }
if ( false === $wpdb->query( "CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, rating TINYINT UNSIGNED NOT NULL, ip_hash CHAR(64) NOT NULL UNIQUE, status VARCHAR(12) NOT NULL, source VARCHAR(10) NOT NULL, reason TEXT NOT NULL, voted_at DATETIME NOT NULL, created_at DATETIME NOT NULL, comment TEXT NULL) ENGINE=InnoDB" ) ) { throw new RuntimeException( 'Fixture table failed.' ); }
$old = array( 'id' => 100, 'post_id' => 424242, 'user_id' => 99, 'rating' => 3, 'ip_hash' => str_repeat( 'a', 64 ), 'status' => 'approved', 'source' => 'fixture', 'reason' => 'untouched', 'voted_at' => '2026-01-01 12:00:00', 'created_at' => '2026-01-01 12:00:00', 'comment' => 'Existing synthetic review' );
if ( 1 !== $wpdb->insert( $table, $old ) ) { throw new RuntimeException( 'Existing synthetic vote failed.' ); }
$dest = WP_PLUGIN_DIR . '/hj-review-entry'; if ( file_exists( $dest ) || ! mkdir( $dest ) || ! copy( getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-review-entry/hj-review-entry.php', $dest . '/hj-review-entry.php' ) ) { throw new RuntimeException( 'Fixture plugin copy failed.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'hamyar-doctors/hamyar-doctors.php', 'hj-review-entry/hj-review-entry.php' ) as $plugin ) { $r = activate_plugin( $plugin ); if ( is_wp_error( $r ) ) { throw new RuntimeException( 'Activation failed.' ); } }
$doctor = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic unmarked surgeon' ), true );
$draft = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'draft', 'post_title' => 'Synthetic draft surgeon' ), true );
if ( is_wp_error( $doctor ) || is_wp_error( $draft ) || ! $doctor || ! $draft ) { throw new RuntimeException( 'Doctor setup failed.' ); }
$sub = wp_create_user( 'entry_subscriber', 'disposable-password', 'entry-sub@example.invalid' ); if ( is_wp_error( $sub ) ) { throw new RuntimeException( 'User setup failed.' ); }
$tables = $wpdb->get_col( 'SHOW TABLES' ); sort( $tables );
file_put_contents( getenv( 'RUNNER_TEMP' ) . '/review-entry-meta.json', wp_json_encode( array( 'doctor' => $doctor, 'draft' => $draft, 'tables' => $tables ) ) );
echo "PASS: independent review entry fixture ready.\n";

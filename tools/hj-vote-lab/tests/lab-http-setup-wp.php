<?php
/** DESTRUCTIVE setup: fresh disposable GitHub Actions WordPress/MariaDB ONLY. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || ! HJ_DISPOSABLE_TEST || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes'; $tables = $wpdb->get_col( 'SHOW TABLES' );
if ( $wpdb->prefix !== 'hjt_' || ! is_array( $tables ) || $wpdb->last_error !== '' || in_array( $table, $tables, true ) ) { throw new RuntimeException( 'Unexpected fixture.' ); }
if ( false === $wpdb->query( "CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL DEFAULT 0, rating TINYINT NOT NULL, status VARCHAR(20) NOT NULL, comment TEXT NOT NULL, ip_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB" ) ) { throw new RuntimeException( 'Fixture table creation failed.' ); }
$dest = WP_PLUGIN_DIR . '/hj-vote-lab'; if ( file_exists( $dest ) || ! mkdir( $dest ) || ! copy( getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-vote-lab/hj-vote-lab.php', $dest . '/hj-vote-lab.php' ) ) { throw new RuntimeException( 'Fixture install failed.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'hamyar-doctors/hamyar-doctors.php', 'hj-vote-lab/hj-vote-lab.php' ) as $plugin ) { $result = activate_plugin( $plugin ); if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Fixture activation failed.' ); } }
$doctor = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic doctor HTTP' ), true );
if ( is_wp_error( $doctor ) || ! $doctor ) { throw new RuntimeException( 'Fixture doctor failed.' ); }
update_post_meta( $doctor, '_hj_demo_doctor', 'fixture-http' );
$sub = wp_create_user( 'lab_http_subscriber', 'disposable-password', 'lab-http@example.invalid' );
if ( is_wp_error( $sub ) ) { throw new RuntimeException( 'Fixture user failed.' ); }
echo "PASS: disposable HTTP fixture ready.\n";

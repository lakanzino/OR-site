<?php
/** Synthetic vote data and assertions; only an isolated, empty CI MariaDB. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || ! HJ_DISPOSABLE_TEST || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes'; $tables = $wpdb->get_col( 'SHOW TABLES' );
if ( $wpdb->prefix !== 'hjt_' || ! is_array( $tables ) || $wpdb->last_error !== '' || in_array( $table, $tables, true ) ) { throw new RuntimeException( 'Unexpected fixture.' ); }
if ( false === $wpdb->query( "CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id BIGINT UNSIGNED NOT NULL, rating TINYINT NOT NULL, status VARCHAR(12) NOT NULL, comment TEXT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB" ) ) { throw new RuntimeException( 'Fixture table failed.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$r = activate_plugin( 'hamyar-doctors/hamyar-doctors.php' ); if ( is_wp_error( $r ) ) { throw new RuntimeException( 'Activation failed.' ); }
$demo = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic demo doctor' ), true );
$other = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Unmarked doctor' ), true );
if ( is_wp_error( $demo ) || is_wp_error( $other ) || ! $demo || ! $other ) { throw new RuntimeException( 'Doctor setup failed.' ); }
update_post_meta( $demo, '_hj_demo_doctor', 'fixture-demo' );
$marker = '[نظر آزمایشی HJ-12345678-1234-4567-8123-123456789abc] ';
$malformed = '[نظر آزمایشی HJ-12345678-1234-1567-8123-123456789abc] ';
$rows = array(
	array( 'post_id' => $demo, 'rating' => 5, 'status' => 'approved', 'comment' => $marker . 'Synthetic <b>approved</b> & text', 'created_at' => '2026-01-01 12:00:01' ),
	array( 'post_id' => $demo, 'rating' => 4, 'status' => 'approved', 'comment' => 'Ordinary synthetic text', 'created_at' => '2026-01-01 12:00:02' ),
	array( 'post_id' => $demo, 'rating' => 3, 'status' => 'approved', 'comment' => $malformed . 'Do not rewrite', 'created_at' => '2026-01-01 12:00:03' ),
	array( 'post_id' => $demo, 'rating' => 2, 'status' => 'approved', 'comment' => 'Infix ' . $marker . 'do not rewrite', 'created_at' => '2026-01-01 12:00:04' ),
	array( 'post_id' => $demo, 'rating' => 1, 'status' => 'pending', 'comment' => $marker . 'Pending synthetic', 'created_at' => '2026-01-01 12:00:05' ),
	array( 'post_id' => $other, 'rating' => 5, 'status' => 'approved', 'comment' => $marker . 'Unmarked physician', 'created_at' => '2026-01-01 12:00:06' ),
);
foreach ( $rows as $row ) { if ( 1 !== $wpdb->insert( $table, $row ) ) { throw new RuntimeException( 'Vote setup failed.' ); } }
$before = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
$reviews = HJ_Profile_Render::public_reviews( $demo ); $comments = array_column( $reviews, 'comment' );
if ( count( $reviews ) !== 4 || ! in_array( '[نظر آزمایشی] Synthetic <b>approved</b> & text', $comments, true ) || ! in_array( 'Ordinary synthetic text', $comments, true ) || ! in_array( $malformed . 'Do not rewrite', $comments, true ) || ! in_array( 'Infix ' . $marker . 'do not rewrite', $comments, true ) ) { throw new RuntimeException( 'Demo reader output wrong.' ); }
$other_reviews = HJ_Profile_Render::public_reviews( $other );
if ( count( $other_reviews ) !== 1 || $other_reviews[0]['comment'] !== $marker . 'Unmarked physician' ) { throw new RuntimeException( 'Unmarked reader changed.' ); }
$html = HJ_Profile_Render::get_instance()->render_profile( $demo );
if ( strpos( $html, '[نظر آزمایشی] Synthetic &lt;b&gt;approved&lt;/b&gt; &amp; text' ) === false || strpos( $html, '<b>approved</b>' ) !== false || strpos( $html, $marker . 'Synthetic' ) !== false ) { throw new RuntimeException( 'Profile output or HTML escaping wrong.' ); }
$after = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
if ( $before !== $after ) { throw new RuntimeException( 'Reading mutated synthetic votes.' ); }
wp_set_current_user( 0 );
file_put_contents( getenv( 'RUNNER_TEMP' ) . '/review-marker-meta.json', wp_json_encode( array( 'demo' => $demo, 'other' => $other, 'nonce' => wp_create_nonce( 'hj_profile_reviews_nonce' ), 'before' => $before ) ) );
echo "PASS: synthetic reader, escaping, statuses and unchanged database.\n";

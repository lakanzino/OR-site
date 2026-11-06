<?php
/** Creates synthetic users/posts only on the empty disposable WordPress CI database. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action( 'init' );
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $other = get_user_by( 'login', 'synthetic_doctor_b' );
if ( ! $doctor || ! $other ) { throw new RuntimeException( 'Synthetic roles fixture missing' ); }
wp_set_password( 'disposable-test-password', $doctor->ID ); wp_set_password( 'disposable-test-password', $other->ID );
for ( $i = 0; $i < 12; $i++ ) {
	$patient = get_user_by( 'login', 'synthetic_race_' . $i );
	if ( ! $patient ) { throw new RuntimeException( 'Synthetic patient missing' ); }
	wp_set_password( 'disposable-test-password', $patient->ID );
}
update_option( 'timezone_string', 'Asia/Tehran' );
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic AJAX doctor', 'post_author' => $doctor->ID ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Synthetic profile creation failed' ); }
global $wpdb;
if ( false === $wpdb->query( 'CREATE TABLE hjt_hjb_ci_booking_audit (appointment_id bigint unsigned NOT NULL PRIMARY KEY, revision bigint unsigned NOT NULL) ENGINE=InnoDB' ) ) { throw new RuntimeException( 'Fixture audit schema failed' ); }
if ( 1 !== $wpdb->insert( 'hjt_hjb_spike_doctors', array( 'doctor_id' => $doc, 'duration_minutes' => 30, 'buffer_minutes' => 5, 'window_start' => '2030-01-01 00:00:00', 'window_end' => '2030-01-10 00:00:00' ) ) ) { throw new RuntimeException( 'Synthetic doctor lock row failed' ); }
update_option( 'active_plugins', array( 'hamyar-doctors/hamyar-doctors.php' ) );
file_put_contents( getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-ajax-meta.json', wp_json_encode( array( 'doctor' => $doc ) ) );
echo "PASS: disposable copied plugin and synthetic doctor setup\n";

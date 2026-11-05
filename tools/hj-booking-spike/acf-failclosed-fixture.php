<?php
/** ACF presence is simulated; this is not an ACF integration test. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
function get_field( $name, $id ) { return get_post_meta( $id, $name, true ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/policy-validation.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/secured-experiment.php';
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $patient = get_user_by( 'login', 'synthetic_race_0' );
$ids = get_posts( array( 'post_type' => 'doctor', 'author' => $doctor->ID, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 1 ) );
wp_set_current_user( $patient->ID );
$r = hj_secured_create( (int) $ids[0], '2030-01-02 07:45:00', str_repeat( 'f', 32 ), '09123456789', '1234567891', strtotime( '2030-01-01 UTC' ) );
if ( $r['status'] !== 'invalid_slot' ) { throw new RuntimeException( 'ACF mock was not rejected' ); }
echo "PASS: mock ACF presence fails closed; real ACF integration not tested\n";

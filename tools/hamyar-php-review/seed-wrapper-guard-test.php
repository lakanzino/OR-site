<?php
/** Stub-only test: never run on a WordPress installation. */
if ( ! defined( 'HJ_ISOLATED_SEED_HOOK_TEST' ) || HJ_ISOLATED_SEED_HOOK_TEST !== true || defined( 'ABSPATH' ) || ! defined( 'HJ_TEST_GUARD_MODE' ) ) {
    exit( "This test must run in isolation.\n" );
}
define( 'ABSPATH', '/isolated/' );
define( 'HJ_DOCTORS_CPT', 'doctor' );
$GLOBALS['calls'] = array();
$GLOBALS['options'] = array();
$GLOBALS['allowed'] = true;
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function current_user_can( $capability ) { return $GLOBALS['allowed']; }
function set_transient( $name, $value, $expiry ) { $GLOBALS['calls'][] = 'transient'; }
// Define stubs before including the real wrappers to observe the boundary.
function hj_iran_locations_seed() { $GLOBALS['calls'][] = 'location_seed'; return array( 'message' => 'ok' ); }
function hj_iran_locations_repair_terms() { $GLOBALS['calls'][] = 'repair'; return array( 'provinces_renamed' => 0, 'cities_renamed' => 0, 'cities_merged' => 0, 'cities_deleted' => 0, 'specs_deleted' => 0 ); }
function hj_demo_doctors_seed() { $GLOBALS['calls'][] = 'demo_seed'; return array( 'message' => 'ok' ); }
require '/repo/plugins/hamyar-doctors/includes/iran-locations-seeder.php';
require '/repo/plugins/hamyar-doctors/includes/demo-doctors.php';
function verify_calls( $expected, $context ) {
    if ( $GLOBALS['calls'] !== $expected ) {
        echo 'FAIL: ' . $context . ' ' . json_encode( $GLOBALS['calls'] ) . "\n";
        exit( 1 );
    }
    $GLOBALS['calls'] = array();
}
$enabled = HJ_TEST_GUARD_MODE === 'true';
hj_iran_locations_auto_seed();
verify_calls( $enabled ? array( 'location_seed', 'transient' ) : array(), 'location seed' );
$GLOBALS['options']['hj_iran_locations_seeded_v1'] = 1;
hj_iran_locations_auto_repair();
verify_calls( $enabled ? array( 'repair', 'transient' ) : array(), 'repair' );
hj_demo_doctors_auto_seed();
verify_calls( $enabled ? array( 'demo_seed', 'transient' ) : array(), 'demo seed' );
// Existing capability and marker checks must still stop an explicitly enabled wrapper.
$GLOBALS['allowed'] = false;
$GLOBALS['options'] = array( 'hj_iran_locations_seeded_v1' => 1 );
hj_iran_locations_auto_repair();
hj_demo_doctors_auto_seed();
$GLOBALS['options'] = array();
hj_iran_locations_auto_seed();
verify_calls( array(), 'capability' );
$GLOBALS['allowed'] = true;
$GLOBALS['options'] = array( 'hj_iran_locations_seeded_v1' => 1, 'hj_iran_locations_repaired_v2' => 1, 'hj_demo_doctors_seeded_v1' => HJ_DEMO_DOCTORS_VERSION );
hj_iran_locations_auto_seed();
hj_iran_locations_auto_repair();
hj_demo_doctors_auto_seed();
verify_calls( array(), 'existing markers' );
echo 'PASS: direct wrapper guard mode ' . HJ_TEST_GUARD_MODE . "; capability and markers preserved.\n";

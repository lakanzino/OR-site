<?php
/** Isolated WordPress hook stubs. Never use with a live WordPress installation. */
if ( ! defined( 'HJ_ISOLATED_SEED_HOOK_TEST' ) || HJ_ISOLATED_SEED_HOOK_TEST !== true || defined( 'ABSPATH' ) ) {
    exit( "This test must run in isolation.\n" );
}
define( 'ABSPATH', '/isolated/' );
define( 'HJ_DOCTORS_CPT', 'doctor' );
$GLOBALS['hj_test_hooks'] = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['hj_test_hooks'][$hook][] = array( $callback, $priority, $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    add_action( $hook, $callback, $priority, $accepted_args );
}
require '/repo/plugins/hamyar-doctors/includes/iran-locations-seeder.php';
require '/repo/plugins/hamyar-doctors/includes/demo-doctors.php';
$errors = array();
$automatic = array( 'hj_iran_locations_auto_seed', 'hj_iran_locations_auto_repair', 'hj_demo_doctors_auto_seed' );
foreach ( $GLOBALS['hj_test_hooks']['admin_init'] ?? array() as $hook ) {
    if ( in_array( $hook[0], $automatic, true ) ) {
        $errors[] = 'Unexpected automatic callback: ' . $hook[0];
    }
}
foreach ( array_merge( $automatic, array( 'hj_iran_locations_seed', 'hj_iran_locations_repair_terms', 'hj_demo_doctors_seed', 'hj_demo_doctors_delete' ) ) as $function ) {
    if ( ! function_exists( $function ) ) { $errors[] = 'Missing function: ' . $function; }
}
$required = array(
    'admin_menu' => 'hj_iran_locations_add_tools_page',
    'admin_post_hj_demo_doctors_action' => 'hj_demo_doctors_handle_action',
    'hj_iran_tools_extra' => 'hj_demo_doctors_tools_section',
    'created_city' => 'hj_iran_locations_save_city_meta',
    'edited_city' => 'hj_iran_locations_save_city_meta',
);
foreach ( $required as $hook => $callback ) {
    $callbacks = array_column( $GLOBALS['hj_test_hooks'][$hook] ?? array(), 0 );
    if ( ! in_array( $callback, $callbacks, true ) ) { $errors[] = 'Missing hook: ' . $hook; }
}
if ( $errors ) { foreach ( $errors as $error ) { echo "FAIL: $error\n"; } exit( 1 ); }
echo "PASS: three automatic admin hooks absent; explicit tools and city hooks registered.\n";

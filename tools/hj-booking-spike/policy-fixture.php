<?php
/** WordPress user/role policy experiment in disposable CI. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/policy-validation.php';
function expect_policy( $value, $label ) { if ( ! $value ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
expect_policy( hj_spike_national_code( '۱۲۳۴۵۶۷۸۹۱' ) === '1234567891', 'Persian digits and checksum' );
expect_policy( hj_spike_national_code( '١٢٣٤٥٦٧٨٩١' ) === '1234567891', 'Arabic digits and checksum' );
foreach ( array( '1234567890', '0000000000', '123456789', '123456789a', '۱۲۳۴۵۶۷۸۹۰' ) as $code ) { expect_policy( hj_spike_national_code( $code ) === false, 'reject invalid national code' ); }
expect_policy( hj_spike_phone( '۰۹۱۲۳۴۵۶۷۸۹' ) === '09123456789', 'normalize Persian mobile' );
expect_policy( hj_spike_phone( '+989123456789' ) === '09123456789', 'normalize international mobile' );
foreach ( array( '0912345678', '02112345678', '09123456789x', '0912 3456789' ) as $phone ) { expect_policy( hj_spike_phone( $phone ) === false, 'reject invalid phone' ); }
global $wpdb;
if ( $wpdb->get_var( "SHOW TABLES LIKE 'hjt_hjb_spike_secretaries'" ) !== 'hjt_hjb_spike_secretaries' ) { throw new RuntimeException( 'Mapping table missing from migration' ); }
add_role( 'hj_spike_secretary', 'Synthetic secretary', array( 'read' => true, 'hj_spike_manage_appointments' => true ) );
$admin = get_user_by( 'login', 'fixture_admin' )->ID;
$doctor = wp_insert_user( array( 'user_login' => 'synthetic_doctor_a', 'user_pass' => 'synthetic-only', 'role' => 'doctor' ) );
$other_doctor = wp_insert_user( array( 'user_login' => 'synthetic_doctor_b', 'user_pass' => 'synthetic-only', 'role' => 'doctor' ) );
$secretary = wp_insert_user( array( 'user_login' => 'synthetic_secretary', 'user_pass' => 'synthetic-only', 'role' => 'hj_spike_secretary' ) );
$patient = wp_insert_user( array( 'user_login' => 'synthetic_patient', 'user_pass' => 'synthetic-only', 'role' => 'subscriber' ) );
$other_patient = wp_insert_user( array( 'user_login' => 'synthetic_patient_b', 'user_pass' => 'synthetic-only', 'role' => 'subscriber' ) );
foreach ( array( $doctor, $other_doctor, $secretary, $patient, $other_patient ) as $uid ) { if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); } }
$profile = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic A', 'post_author' => $doctor ), true );
$other_profile = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic B', 'post_author' => $other_doctor ), true );
if ( is_wp_error( $profile ) || is_wp_error( $other_profile ) ) { throw new RuntimeException( 'Could not create synthetic profiles' ); }
wp_set_current_user( $patient );
expect_policy( ! hj_spike_assign_secretary( $secretary, $other_profile ), 'subscriber cannot assign secretary' );
wp_set_current_user( $admin );
expect_policy( hj_spike_assign_secretary( $secretary, $profile ), 'administrator assigns one secretary' );
expect_policy( hj_spike_assign_secretary( $patient, $profile ) && ! hj_spike_can_manage( $patient, $profile ), 'mapping alone without secretary capability is insufficient' );
update_user_meta( $patient, 'hj_doctor_post_id', $profile );
expect_policy( ! hj_spike_can_manage( $patient, $profile ), 'subscriber cannot gain doctor access through profile meta' );
expect_policy( hj_spike_can_manage( $admin, $profile ) && hj_spike_can_manage( $admin, $other_profile ), 'admin can manage both' );
expect_policy( hj_spike_can_manage( $doctor, $profile ) && ! hj_spike_can_manage( $doctor, $other_profile ), 'surgeon own profile only' );
expect_policy( hj_spike_can_manage( $secretary, $profile ) && ! hj_spike_can_manage( $secretary, $other_profile ), 'secretary mapped profile only' );
expect_policy( ! hj_spike_can_manage( $patient, $profile ) && ! hj_spike_can_manage( 0, $profile ), 'patient and anonymous cannot manage' );
expect_policy( hj_spike_can_view( $patient, $profile, $patient ) && ! hj_spike_can_view( $other_patient, $profile, $patient ) && ! hj_spike_can_view( 0, $profile, $patient ), 'only owning patient can view' );
expect_policy( hj_spike_can_view( $doctor, $profile, $patient ) && hj_spike_can_view( $secretary, $profile, $patient ) && ! hj_spike_can_view( $other_doctor, $profile, $patient ), 'viewer boundaries for surgeon and secretary' );

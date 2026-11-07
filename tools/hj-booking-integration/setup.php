<?php
/** Install only in the isolated CI WordPress database. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action( 'init' );
if ( function_exists( 'get_field' ) || function_exists( 'update_field' ) ) { throw new RuntimeException( 'Expected no ACF in the disposable fixture' ); }
echo "PASS: integration exercises WordPress without ACF or ACF Pro\n";
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $other = get_user_by( 'login', 'synthetic_doctor_b' ); $admin = get_user_by( 'login', 'fixture_admin' );
if ( ! $doctor || ! $other || ! $admin ) { throw new RuntimeException( 'Synthetic fixture users missing' ); }
// Dedicated first-profile users: isolated CI fixtures, never real accounts.
foreach ( array( 'synthetic_new_profile', 'synthetic_profile_race', 'synthetic_profile_failure' ) as $login ) {
 if ( get_user_by( 'login', $login ) ) { throw new RuntimeException( 'First-profile fixture already exists' ); }
 $id = wp_insert_user( array( 'user_login'=>$login, 'user_pass'=>'disposable-test-password', 'role'=>'doctor' ) );
 if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'First-profile fixture user failed' ); }
}
wp_set_password( 'disposable-test-password', $doctor->ID ); wp_set_password( 'disposable-test-password', $other->ID ); wp_set_password( 'disposable-test-password', $admin->ID );
$secretary = get_user_by( 'login', 'synthetic_secretary' );
$patient_a = get_user_by( 'login', 'synthetic_patient' ); $patient_b = get_user_by( 'login', 'synthetic_patient_b' );
if ( ! $secretary || ! $patient_a || ! $patient_b || ! get_role( 'hj_booking_secretary' ) ) { throw new RuntimeException( 'Management test users/role missing' ); }
$secretary->set_role( 'hj_booking_secretary' );
wp_set_password( 'disposable-test-password', $secretary->ID ); wp_set_password( 'disposable-test-password', $patient_a->ID ); wp_set_password( 'disposable-test-password', $patient_b->ID );
wp_update_user( array( 'ID' => $patient_a->ID, 'display_name' => 'Synthetic Patient Alpha' ) ); wp_update_user( array( 'ID' => $patient_b->ID, 'display_name' => 'Synthetic Patient Beta' ) );
for ( $i = 0; $i < 12; $i++ ) { $user = get_user_by( 'login', 'synthetic_race_' . $i ); if ( ! $user ) { throw new RuntimeException( 'Synthetic patient missing' ); } wp_set_password( 'disposable-test-password', $user->ID ); }
update_option( 'timezone_string', 'Asia/Tehran' );
wp_set_current_user( $admin->ID );
if ( ! hj_booking_install_schema() ) { throw new RuntimeException( 'Production booking schema install failed' ); }
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic production backend doctor', 'post_author' => $doctor->ID ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Synthetic doctor post failed' ); }
update_user_meta( $doctor->ID, 'hj_doctor_post_id', $doc );
update_post_meta( $doc, 'working_hours', array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '10:00:00', 'end_time' => '11:00:00' ) ) );
wp_set_current_user( $doctor->ID );
if ( hj_booking_configure( (int) $doc, 30, 5, true )['status'] !== 'configured' ) { throw new RuntimeException( 'Doctor config failed' ); }
wp_set_current_user( $admin->ID );
$doc2 = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Second synthetic booking doctor', 'post_author' => $other->ID ), true );
if ( is_wp_error( $doc2 ) || hj_booking_configure( (int) $doc2, 30, 5, true )['status'] !== 'configured' || hj_booking_save_schedule( (int) $doc2, array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00', 'end_time' => '12:00' ) ), 0 )['status'] !== 'saved' ) { throw new RuntimeException( 'Second synthetic doctor setup failed' ); }
update_user_meta( $other->ID, 'hj_doctor_post_id', $doc2 );
// Public-profile tests: preserve an existing external link; do not point a
// disabled, unconfigured, or corrupt schedule at an unavailable form.
update_post_meta( $doc, 'hj_booking_url', 'https://example.invalid/external-booking' );
$fixtures = array();
foreach ( array( 'disabled', 'missing', 'corrupt' ) as $kind ) {
 $id = wp_insert_post( array( 'post_type'=>'doctor', 'post_status'=>'publish', 'post_title'=>'Synthetic ' . $kind, 'post_author'=>$admin->ID ), true );
 if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Profile gate fixture failed' ); }
 update_post_meta( $id, 'hj_booking_url', 'https://example.invalid/external-booking' );
 if ( hj_booking_configure( (int)$id, 30, 0, $kind !== 'disabled' )['status'] !== 'configured' ) { throw new RuntimeException( 'Profile gate config failed' ); }
 if ( $kind !== 'missing' && hj_booking_save_schedule( (int)$id, array( array( 'day_of_week'=>'wednesday', 'is_closed'=>0, 'start_time'=>'09:00', 'end_time'=>'12:00' ) ), 0 )['status'] !== 'saved' ) { throw new RuntimeException( 'Profile gate schedule failed' ); }
 if ( $kind === 'corrupt' ) { global $wpdb; $table=hj_booking_table( 'schedules' ); if ( $wpdb->update( $table, array( 'rows_hash'=>str_repeat('0',64) ), array( 'doctor_id'=>$id ) ) !== 1 ) { throw new RuntimeException( 'Profile gate corruption failed' ); } }
 $fixtures[$kind] = $id;
}
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic booking', 'post_content' => '[hj_booking_form]' ), true );
if ( is_wp_error( $page ) ) { throw new RuntimeException( 'Synthetic page failed' ); }
$account=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic legacy account','post_content'=>'[hj_member_account]'),true);
if (is_wp_error($account) || !$account) { throw new RuntimeException('Synthetic legacy account failed'); }
update_option( 'active_plugins', array( 'hamyar-doctors/hamyar-doctors.php' ) );
file_put_contents( getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-ajax-meta.json', wp_json_encode( array( 'doctor' => $doc, 'doctor2' => $doc2, 'profile_gates' => $fixtures, 'page' => $page, 'account' => $account, 'secretary' => $secretary->ID ) ) );
echo "PASS: repository plugin loaded without patch; production schema and synthetic doctor configured\n";

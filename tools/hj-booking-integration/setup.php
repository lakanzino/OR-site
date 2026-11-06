<?php
/** Install only in the isolated CI WordPress database. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action( 'init' );
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $other = get_user_by( 'login', 'synthetic_doctor_b' ); $admin = get_user_by( 'login', 'fixture_admin' );
if ( ! $doctor || ! $other || ! $admin ) { throw new RuntimeException( 'Synthetic fixture users missing' ); }
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
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic booking', 'post_content' => '[hj_booking_form]' ), true );
if ( is_wp_error( $page ) ) { throw new RuntimeException( 'Synthetic page failed' ); }
update_option( 'active_plugins', array( 'hamyar-doctors/hamyar-doctors.php' ) );
file_put_contents( getenv( 'RUNNER_TEMP' ) . '/hj-wpdb-ajax-meta.json', wp_json_encode( array( 'doctor' => $doc, 'doctor2' => $doc2, 'page' => $page, 'secretary' => $secretary->ID ) ) );
echo "PASS: repository plugin loaded without patch; production schema and synthetic doctor configured\n";

<?php
/** Disposable GitHub Actions WordPress only. Never point this at a real DB. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_' || HJ_DOCTORS_VERSION !== '0.3.22' ) { exit(1); }
if ( defined('HJ_BOOKING_ENABLED') && HJ_BOOKING_ENABLED ) { throw new RuntimeException('Booking must remain disabled'); }
$user = wp_create_user('journey_surgeon', 'fixture-journey-only', 'journey@example.invalid');
if ( is_wp_error($user) || ! hj_doctors_assign_doctor_role($user) ) { throw new RuntimeException('surgeon fixture setup failed'); }
$province = wp_insert_term('استان آزمایشی مسیر', 'province', array('slug'=>'journey-province'));
$city = wp_insert_term('شهر آزمایشی مسیر', 'city', array('slug'=>'journey-city'));
if (is_wp_error($province) || is_wp_error($city)) { throw new RuntimeException('fixture locations failed'); }
update_term_meta($city['term_id'], 'hj_province_id', $province['term_id']);
$other = wp_insert_post(array('post_type'=>'doctor','post_status'=>'draft','post_title'=>'Other synthetic surgeon','post_author'=>1),true);
if (is_wp_error($other)) { throw new RuntimeException('other fixture failed'); }
update_option('hj_fixture_surgeon_journey', array('province'=>$province['term_id'],'city'=>$city['term_id'],'other'=>$other), false);
echo "PASS disposable surgeon journey setup; booking disabled\n";

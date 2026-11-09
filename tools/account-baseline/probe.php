<?php
/** Disposable CI ONLY: observes 0.3.22 behavior, not a security or OAuth E2E test. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $wpdb->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { throw new RuntimeException('Unexpected fixture configuration'); }
require WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
do_action('init');
if ( HJ_DOCTORS_VERSION !== '0.3.22' ) { throw new RuntimeException('Wrong baseline'); }
$ok = static function($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
$make = static function($name, $role) {
    $id = wp_create_user($name, 'fixture-password-only', $name . '@example.invalid');
    if (is_wp_error($id)) { throw new RuntimeException('Unable to create fixture user'); }
    (new WP_User($id))->set_role($role);
    return $id;
};
$subscriber = $make('normal_fixture', 'subscriber');
$member = $make('member_fixture', 'subscriber');
$admin = $make('admin_fixture', 'administrator');
$surgeon = $make('surgeon_fixture', 'subscriber');
$ok(hj_doctors_assign_doctor_role($surgeon), 'Fixture surgeon role assignment failed');
$ok(!hj_doctors_assign_doctor_role($admin) && in_array('administrator', get_userdata($admin)->roles, true), 'Administrator must remain unchanged');
$ts = time();
$_COOKIE[HJ_Doctors_Auth_Google::INTENT_COOKIE] = $ts . '|' . hash_hmac('sha256', 'hj_doc_intent|' . $ts, wp_salt('auth'));
HJ_Doctors_Auth_Google::on_nsl_login($subscriber, 'google');
$roles = get_userdata($subscriber)->roles;
$ok(in_array('doctor', $roles, true) && !in_array('subscriber', $roles, true), 'Expected existing subscriber role to be replaced in baseline');
unset($_COOKIE[HJ_Doctors_Auth_Google::INTENT_COOKIE]);
$_COOKIE['hj_member_intent'] = $ts . '|' . hash_hmac('sha256', 'hj_member_intent|' . $ts, wp_salt('auth'));
HJ_Doctors_Auth_Google::on_nsl_login($member, 'google');
$ok(in_array('subscriber', get_userdata($member)->roles, true) && !in_array('doctor', get_userdata($member)->roles, true), 'Member intent should keep member role');
unset($_COOKIE['hj_member_intent']);
wp_set_current_user($surgeon);
$html = do_shortcode('[hj_member_account]');
$ok(false !== strpos($html, 'hj_member_save'), 'Expected member-account form also rendered for surgeon in baseline');
$ok(hj_doctors_user_can_access_dashboard($surgeon), 'Surgeon should access own dashboard capability');
wp_set_current_user($member);
$html = do_shortcode('[hj_doctors_dashboard]');
$ok(false !== strpos($html, 'hj_doctors_join'), 'Expected member opt-in form in baseline');
echo "PASS: 0.3.22 replaces subscriber role on surgeon-intent callback\n";
echo "PASS: member intent preserved member role in this fixture\n";
echo "PASS: administrator role was not replaced\n";
echo "PASS: member-account form also renders for surgeon\n";
echo "PASS: member sees surgeon opt-in form\n";
echo "NOTE: Nextend callback simulated; no OAuth, no live configuration, no booking or full permissions tested\n";

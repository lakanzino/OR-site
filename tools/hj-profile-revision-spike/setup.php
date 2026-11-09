<?php
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_') { exit(1); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (getenv('HJ_SPIKE_ACF') === 'free' && (!function_exists('acf') || acf()->settings['version'] !== '6.8.11')) { throw new RuntimeException('ACF Free fixture missing or wrong version'); }
if (getenv('HJ_SPIKE_ACF') === 'none' && function_exists('acf')) { throw new RuntimeException('Unexpected ACF in fixture'); }
$result=activate_plugin('hamyar-doctors/hamyar-doctors.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action('init');
if (HJ_DOCTORS_VERSION !== getenv('HJ_SPIKE_VERSION')) { throw new RuntimeException('unexpected plugin baseline'); }
foreach (array('surgeon_fixture','normal_fixture') as $login) {
    $id=wp_create_user($login,'disposable-spike-only',$login.'@example.invalid');
    if (is_wp_error($id) || !hj_doctors_assign_doctor_role($id)) { throw new RuntimeException('synthetic surgeon setup failed'); }
}
echo "PASS disposable plugin setup ".HJ_DOCTORS_VERSION."\n";

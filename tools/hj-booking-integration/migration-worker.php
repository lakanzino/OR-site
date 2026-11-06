<?php
/** Isolated CI workers: share a barrier and call the real migration concurrently. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
require_once getenv('GITHUB_WORKSPACE') . '/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
$admin=get_user_by('login','fixture_admin'); if (!$admin || !hj_booking_enabled()) { exit(1); } wp_set_current_user($admin->ID);
$barrier=getenv('RUNNER_TEMP') . '/hj-booking-migration-barrier';
file_put_contents($barrier . '-' . getmypid(), 'ready');
$until=microtime(true)+20;
while (!is_file($barrier . '-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); }
if (!is_file($barrier . '-release')) { exit(1); }
if (!hj_booking_install_schema() || !hj_booking_ready()) { exit(1); }
echo "PASS: concurrent real plugin schema migration\n";

<?php
if (PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' || getenv('HJ_SPIKE_ACF') !== 'free') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_') { exit(1); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$r=activate_plugin('advanced-custom-fields/acf.php');
if (is_wp_error($r)) { throw new RuntimeException($r->get_error_message()); }
echo "PASS ACF Free activated in disposable fixture only\n";

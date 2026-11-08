<?php
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result = activate_plugin('hamyar-doctors/hamyar-doctors.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
switch_theme('lax-theme-child');
$member = get_user_by('login','member_fixture'); wp_set_password('fixture-password-only', $member->ID);
$id = hj_member_ensure_account_page(); if (!$id) { throw new RuntimeException('account page missing'); }
echo "PASS HTTP fixture setup\n";

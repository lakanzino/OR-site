<?php
/** Disposable WordPress/MariaDB fixture, NOT a real OAuth or live-site test. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $wpdb->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
require WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action('init');
require dirname(__DIR__, 2) . '/themes/lax-theme-child/inc/member-entry-ui.php';
$ok = static function($condition, $message) { if (!$condition) { throw new RuntimeException($message); } };
$member = get_user_by('login', 'member_fixture'); $other = get_user_by('login', 'surgeon_fixture');
$ok($member && $other, 'Fixture users missing');
wp_set_current_user($member->ID);
$html = do_shortcode('[hj_member_account]');
$ok(strpos($html, 'profile.php') === false, 'Patient UI must not link to WordPress profile');
$ok(strpos($html, 'ناحیه اختصاصی جراحان') === false, 'Surgeon entry removed from member account');
$ok(strpos($html, 'خروج</a>') !== false, 'Logout remains available');
$ok(strpos($html, 'hj_member_save') !== false && strpos($html, 'hj_child_nickname_save') !== false, 'Both account fields must remain');
$ok(strpos($html, 'hj-member-activity') !== false && strpos($html, 'hj_member_suggestion') !== false, 'Existing activity and suggestion remain');
$ok(is_wp_error(hj_child_save_nickname($other->ID, 'unwanted')), 'Cannot modify another account');
$ok(is_wp_error(hj_child_save_nickname($member->ID, '')), 'Empty nickname rejected');
$ok(is_wp_error(hj_child_save_nickname($member->ID, str_repeat('x', 81))), 'Overlong nickname rejected');
$ok(!is_wp_error(hj_child_save_nickname($member->ID, '<b>نام آزمایشی</b>')), 'Nickname update');
$ok(get_user_meta($member->ID, 'nickname', true) === 'نام آزمایشی', 'Markup stripped and nickname saved');
$ok(!in_array('doctor', get_userdata($member->ID)->roles, true), 'Role unchanged');
wp_set_current_user($other->ID);
$surgeon_html = do_shortcode('[hj_member_account]');
$ok(strpos($surgeon_html, 'ناحیه اختصاصی جراحان') === false && strpos($surgeon_html, 'خروج</a>') !== false, 'Public account hides surgeon link for all viewers and retains logout');
$ok(hj_doctors_user_can_access_dashboard($other->ID), 'Surgeon capability unchanged');
wp_set_current_user(0);
$guest_html = do_shortcode('[hj_member_account]');
$ok(strpos($guest_html, 'ناحیه اختصاصی جراحان') === false && strpos($guest_html, 'ورود به حساب کاربری') !== false, 'Guest member login remains without surgeon link');
$ok(is_wp_error(hj_child_save_nickname($member->ID, 'guest')), 'Guest denied');
$booking = '<section id="hj-booking" class="hj-booking"><h2>درخواست نوبت</h2><form>unchanged</form></section>';
$with_notice = hj_child_booking_account_notice($booking);
$ok(strpos($with_notice, 'hj-child-booking-account-notice') !== false && strpos($with_notice, '<form>unchanged</form>') !== false, 'Notice added without altering booking form');
$ok(hj_child_booking_account_notice($with_notice) === $with_notice, 'No duplicate notice');
$ok(hj_child_booking_account_notice('<a href="https://wa.me/1">واتساپ</a>') === '<a href="https://wa.me/1">واتساپ</a>', 'External WhatsApp unchanged');
wp_set_current_user($member->ID);
$ok(hj_child_booking_account_notice($booking) === $booking, 'Notice only for guests');
echo "PASS: patient account, nickname validation and scoped booking notice on disposable WordPress\n";
echo "NOTE: No real OAuth, Nextend, HTTP nonce/redirect, or live-site test\n";

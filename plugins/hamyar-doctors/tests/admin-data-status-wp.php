<?php
/** Destructive fixture: run only in an isolated, disposable WordPress database. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST ) { exit; }
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
wp_set_current_user( 1 );
do_action( 'init' );
header( 'Content-Type: text/plain; charset=utf-8' );
function hj_status_test_assert( $value, $message ) {
	if ( ! $value ) { http_response_code( 500 ); throw new Exception( 'FAIL: ' . $message ); }
	echo 'PASS: ' . $message . "\n";
}
class HJ_Status_Test_Denied extends Exception {}
add_filter( 'wp_die_handler', function () { return function ( $message, $title = '', $args = array() ) { throw new HJ_Status_Test_Denied( 'blocked' ); }; } );
$subscriber = wp_create_user( 'status-test-' . wp_generate_password( 8, false ), 'password-for-disposable-site' );
if ( is_wp_error( $subscriber ) ) { throw new Exception( 'fixture user failed' ); }
global $wpdb;
$initial = wp_count_posts( 'doctor' );
$initial_published = (int) $initial->publish;
$initial_draft = (int) $initial->draft;
$initial_marked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND pm.meta_key = %s", 'doctor', '_hj_demo_doctor' ) );
$secret = 'DO_NOT_EXPOSE_TEST_TITLE';
$p1 = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => $secret ) );
$p2 = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'draft', 'post_title' => $secret . '2' ) );
update_post_meta( $p1, '_hj_demo_doctor', 'sample' );
wp_insert_term( 'Fixture city', 'city' );
update_option( HJ_DOCTORS_VERSION_OPTION, '<script>unexpected</script>' );
$before = array( wp_count_posts( 'doctor' )->publish, wp_count_posts( 'doctor' )->draft, get_post_meta( $p1, '_hj_demo_doctor', true ), get_option( HJ_DOCTORS_VERSION_OPTION ), wp_count_terms( array( 'taxonomy' => 'city', 'hide_empty' => false ) ) );
$_SERVER['REQUEST_METHOD'] = 'GET';
wp_set_current_user( $subscriber );
$denied = false;
try { hj_doctors_data_status_page(); } catch ( HJ_Status_Test_Denied $e ) { $denied = true; }
hj_status_test_assert( $denied, 'subscriber denied' );
( new WP_User( $subscriber ) )->set_role( 'doctor' );
wp_set_current_user( $subscriber );
$denied = false;
try { hj_doctors_data_status_page(); } catch ( HJ_Status_Test_Denied $e ) { $denied = true; }
hj_status_test_assert( $denied, 'doctor denied' );
wp_set_current_user( 0 );
$denied = false;
try { hj_doctors_data_status_page(); } catch ( HJ_Status_Test_Denied $e ) { $denied = true; }
hj_status_test_assert( $denied, 'anonymous denied' );
wp_set_current_user( 1 );
$_SERVER['REQUEST_METHOD'] = 'POST';
$denied = false;
try { hj_doctors_data_status_page(); } catch ( HJ_Status_Test_Denied $e ) { $denied = true; }
hj_status_test_assert( $denied, 'POST denied for administrator' );
$_SERVER['REQUEST_METHOD'] = 'GET';
add_action( 'admin_init', 'hj_demo_doctors_auto_seed', 0 );
ob_start(); hj_doctors_data_status_page(); $html = ob_get_clean();
remove_action( 'admin_init', 'hj_demo_doctors_auto_seed', 0 );
hj_status_test_assert( false !== strpos( $html, 'در این قلاب متصل است' ), 'priority zero detected correctly' );
hj_status_test_assert( false === strpos( $html, $secret ) && false === stripos( $html, '<form' ) && false === strpos( $html, 'admin-post.php' ), 'no personal title, forms or action links' );
hj_status_test_assert( false === strpos( $html, '<script>unexpected</script>' ) && false !== strpos( $html, '&lt;script&gt;unexpected&lt;/script&gt;' ), 'database version escaped' );
hj_status_test_assert( false !== strpos( $html, 'منتشرشده</th><td>' . ( $initial_published + 1 ) . '</td>' ) && false !== strpos( $html, 'پیش‌نویس</th><td>' . ( $initial_draft + 1 ) . '</td>' ) && false !== strpos( $html, 'دارای نشانگر نمونه، در همه وضعیت‌ها</th><td>' . ( $initial_marked + 1 ) . '</td>' ), 'published, draft and marked fixture counts are correct' );
$after = array( wp_count_posts( 'doctor' )->publish, wp_count_posts( 'doctor' )->draft, get_post_meta( $p1, '_hj_demo_doctor', true ), get_option( HJ_DOCTORS_VERSION_OPTION ), wp_count_terms( array( 'taxonomy' => 'city', 'hide_empty' => false ) ) );
hj_status_test_assert( $before === $after, 'selected database state unchanged by report rendering' );
$real_posts = $wpdb->posts;
$previous_suppression = $wpdb->suppress_errors( true );
$wpdb->posts = 'hj_missing_fixture_posts';
ob_start(); hj_doctors_data_status_page(); $error_html = ob_get_clean();
$wpdb->posts = $real_posts;
$wpdb->suppress_errors( $previous_suppression );
hj_status_test_assert( false !== strpos( $error_html, 'منتشرشده</th><td>نامعلوم</td>' ) && false !== strpos( $error_html, 'دارای نشانگر نمونه، در همه وضعیت‌ها</th><td>نامعلوم</td>' ), 'database errors shown as unknown, not zero' );
$_SERVER['REQUEST_METHOD'] = 'GET';
hj_doctors_data_status_menu();
global $submenu;
$items = isset( $submenu['edit.php?post_type=doctor'] ) ? $submenu['edit.php?post_type=doctor'] : array();
$slugs = array_column( $items, 2 );
hj_status_test_assert( in_array( 'hj-doctors-data-status', $slugs, true ), 'separate doctor submenu registered' );
echo "DONE: isolated WordPress " . get_bloginfo( 'version' ) . "\n";

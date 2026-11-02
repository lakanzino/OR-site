<?php
/** Run on a disposable WordPress site with child theme and doctors plugin active. */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'hj_static_home_upgrade_0336' ) || ! post_type_exists( 'doctor' ) ) { throw new RuntimeException( 'Active child theme and doctors plugin required.' ); }
function hj_home_test_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$old_user = get_current_user_id(); wp_set_current_user( $admin[0]->ID );
$keys = array( 'show_on_front', 'page_on_front', 'hj_static_home_page_id', 'hj_static_home_0336_done', 'hj_static_home_0336_previous', 'hj_static_home_0336_error', 'hj_static_home_0336_lock' );
$old = array(); foreach ( $keys as $key ) { $old[$key] = get_option( $key ); }
$posts = array();
try {
	wp_set_current_user( 0 );
	$google = do_shortcode( '[hj_home_google]' );
	hj_home_test_assert( false !== strpos( $google, 'hj_google_start=1' ), 'Google entry must use the doctor intent flow' );
	wp_set_current_user( $admin[0]->ID );
	hj_home_test_assert( '' === do_shortcode( '[hj_home_google]' ), 'Google entry should be hidden for signed-in users' );
	// Default and missing status, pending and draft must never enter the showcase.
	$cases = array( 'Approved Person' => array( 'publish', 'published' ), 'Pending Person' => array( 'pending', 'pending' ), 'Draft Person' => array( 'draft', 'published' ), 'Missing Status' => array( 'publish', '' ) );
	foreach ( $cases as $name => $settings ) {
		$id = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => $settings[0], 'post_title' => $name, 'post_date' => gmdate( 'Y-m-d H:i:s', time() ) ), true );
		hj_home_test_assert( ! is_wp_error( $id ), 'Test doctor creation failed' ); $posts[] = $id;
		if ( '' === $settings[1] ) { delete_post_meta( $id, 'hj_profile_status' ); } else { update_post_meta( $id, 'hj_profile_status', $settings[1] ); }
		update_post_meta( $id, 'specialty_fa', 'Test Specialty' );
	}
	$html = do_shortcode( '[hj_home_surgeons]' );
	hj_home_test_assert( strpos( $html, 'Approved Person' ) !== false && strpos( $html, 'Test Specialty' ) !== false, 'Approved profile absent' );
	foreach ( array( 'Pending Person', 'Draft Person', 'Missing Status' ) as $name ) { hj_home_test_assert( strpos( $html, $name ) === false, 'Unapproved profile exposed: ' . $name ); }
	hj_home_test_assert( strpos( $html, esc_url( get_permalink( $posts[0] ) ) ) !== false, 'Card does not link to the profile' );
	update_post_meta( $posts[0], 'hj_profile_status', 'pending' );
	$remaining = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'meta_key' => 'hj_profile_status', 'meta_value' => 'published', 'post__not_in' => $posts, 'numberposts' => 1, 'fields' => 'ids' ) );
	$empty = do_shortcode( '[hj_home_surgeons]' );
	if ( ! $remaining ) { hj_home_test_assert( false !== strpos( $empty, 'هنوز پروفایل' ) && false === strpos( $empty, 'Approved Person' ), 'Empty state contains a fake or unapproved doctor' ); }
	update_post_meta( $posts[0], 'hj_profile_status', 'published' );

	$newsletter = '<!-- wp:group {"className":"hj-magazine-section"} --><div class="wp-block-group hj-magazine-section"><!-- wp:paragraph --><p>Newsletter editor changes</p><!-- /wp:paragraph --><!-- wp:shortcode -->[hj_magazine_subscribe]<!-- /wp:shortcode --></div><!-- /wp:group -->';
	$old_content = $newsletter . '<!-- wp:paragraph --><p>Existing editorial changes</p><!-- /wp:paragraph -->';
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Old home', 'post_content' => $old_content ), true );
	hj_home_test_assert( ! is_wp_error( $page ), 'Test page creation failed' ); $posts[] = $page;
	update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $page );
	delete_option( 'hj_static_home_0336_done' ); delete_option( 'hj_static_home_0336_lock' );
	hj_static_home_upgrade_0336(); $new_id = (int) get_option( 'page_on_front' ); $posts[] = $new_id;
	$new_content = get_post_field( 'post_content', $new_id );
	hj_home_test_assert( $new_id !== $page && get_post_field( 'post_content', $page ) === $old_content, 'Old page was overwritten' );
	hj_home_test_assert( strpos( $new_content, 'Newsletter editor changes' ) !== false && substr_count( $new_content, '[hj_magazine_subscribe]' ) === 1, 'Newsletter was lost or duplicated' );
	hj_home_test_assert( strpos( $new_content, '[hj_home_surgeons]' ) !== false && strpos( $new_content, '{{' ) === false, 'Showcase or link replacement missing' );
	hj_home_test_assert( (int) get_option( 'hj_static_home_0336_previous' )['page_on_front'] === $page, 'Rollback reference missing' );
	hj_static_home_upgrade_0336(); hj_home_test_assert( (int) get_option( 'page_on_front' ) === $new_id, 'Upgrade not idempotent' );
} finally {
	foreach ( $keys as $key ) { if ( false === $old[$key] ) { delete_option( $key ); } else { update_option( $key, $old[$key] ); } }
	foreach ( array_unique( $posts ) as $id ) { if ( $id ) { wp_delete_post( $id, true ); } }
	wp_set_current_user( $old_user );
}
echo "Homepage showcase and migration checks passed.\n";

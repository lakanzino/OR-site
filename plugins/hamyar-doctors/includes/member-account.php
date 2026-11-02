<?php
/** Separate account page for ordinary members; doctor dashboard remains separate. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function hj_member_account_url() {
	$page = get_post( (int) get_option( 'hj_member_account_page' ) );
	return $page && 'page' === $page->post_type && 'publish' === $page->post_status && has_shortcode( $page->post_content, 'hj_member_account' ) ? get_permalink( $page ) : home_url( '/member-account/' );
}
function hj_member_ensure_account_page() {
	$page = get_post( (int) get_option( 'hj_member_account_page' ) );
	if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status && has_shortcode( $page->post_content, 'hj_member_account' ) ) { return $page->ID; }
	$page = get_page_by_path( 'member-account' );
	if ( $page ) {
		// Never overwrite or assume ownership of an existing unrelated page.
		if ( 'publish' === $page->post_status && has_shortcode( $page->post_content, 'hj_member_account' ) ) { update_option( 'hj_member_account_page', $page->ID, false ); return $page->ID; }
	}
	$slug = $page ? 'member-account-hj' : 'member-account';
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'حساب کاربری', 'post_name' => $slug, 'post_content' => '[hj_member_account]' ), true );
	if ( is_wp_error( $id ) || ! $id ) { return 0; }
	update_option( 'hj_member_account_page', $id, false );
	return $id;
}
add_action( 'template_redirect', function () {
	$id = (int) get_option( 'hj_member_account_page' );
	if ( $id && is_page( $id ) ) { if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); } nocache_headers(); }
}, 0 );
add_shortcode( 'hj_member_account_link', function () {
	return '<a href="' . esc_url( hj_member_account_url() ) . '">حساب کاربری اعضا</a>';
} );
add_shortcode( 'hj_member_account', function () {
	if ( ! is_user_logged_in() ) {
		return '<div class="hj-member"><p>برای دیدن حساب کاربری اعضا ابتدا وارد شوید.</p><p><a href="' . esc_url( wp_login_url( hj_member_account_url() ) ) . '">ورود به حساب کاربری</a></p><p><a href="' . esc_url( hj_doctors_dashboard_url() ) . '">ناحیه اختصاصی جراحان</a></p></div>';
	}
	$user = wp_get_current_user();
	$out = '<div class="hj-member"><p>ایمیل حساب: <b>' . esc_html( $user->user_email ) . '</b></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hj_member_save">' . wp_nonce_field( 'hj_member_save', '_wpnonce', true, false ) . '<label>نام نمایشی <input name="display_name" maxlength="80" required value="' . esc_attr( $user->display_name ) . '"></label> <button type="submit">ذخیره</button></form>';
	if ( ! hj_doctors_is_doctor_user( $user ) ) { $out .= '<p><a href="' . esc_url( admin_url( 'profile.php' ) ) . '">تنظیمات کامل حساب</a></p>'; }
	$out .= '<p><a href="' . esc_url( hj_doctors_dashboard_url() ) . '">ناحیه اختصاصی جراحان</a> | <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">خروج</a></p></div>';
	return $out;
} );
add_action( 'admin_post_hj_member_save', function () {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! is_user_logged_in() ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_member_save' );
	$name = isset( $_POST['display_name'] ) && is_string( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
	if ( '' === $name || strlen( $name ) > 320 ) { wp_die( 'نام نمایشی نامعتبر است.', '', array( 'response' => 400 ) ); }
	wp_update_user( array( 'ID' => get_current_user_id(), 'display_name' => $name ) );
	wp_safe_redirect( hj_member_account_url() ); exit;
} );

<?php
/** Patient-facing account presentation; authentication and booking remain in the plugin. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Add the account requirement only to an actual internal booking form. */
function hj_child_booking_account_notice( $html ) {
	if ( is_user_logged_in() || ! function_exists( 'hj_member_account_url' ) || strpos( $html, 'hj-child-booking-account-notice' ) !== false ) { return $html; }
	$needle = '<h2>درخواست نوبت</h2>';
	if ( strpos( $html, 'id="hj-booking"' ) === false || strpos( $html, $needle ) === false ) { return $html; }
	$notice = '<p class="hj-child-booking-account-notice">برای ثبت نوبت در سامانه، ابتدا باید حساب کاربری داشته باشید یا وارد شوید. <a href="' . esc_url( hj_member_account_url() ) . '">ورود به حساب کاربری</a></p>';
	return preg_replace( '/' . preg_quote( $needle, '/' ) . '/', $needle . $notice, $html, 1 );
}

/** Validate and update only the current user's nickname. */
function hj_child_save_nickname( $user_id, $raw ) {
	if ( ! is_user_logged_in() || (int) $user_id !== get_current_user_id() || ! current_user_can( 'read' ) ) { return new WP_Error( 'forbidden', 'دسترسی مجاز نیست.' ); }
	$user = wp_get_current_user();
	if ( (array) $user->roles !== array( 'subscriber' ) ) { return new WP_Error( 'forbidden', 'دسترسی مجاز نیست.' ); }
	if ( ! is_string( $raw ) ) { return new WP_Error( 'invalid', 'نام مستعار معتبر نیست.' ); }
	$name = sanitize_text_field( $raw );
	if ( '' === $name || strlen( $name ) > 320 || ! preg_match( '/^.{1,80}$/us', $name ) ) { return new WP_Error( 'invalid', 'نام مستعار باید بین ۱ تا ۸۰ نویسه باشد.' ); }
	return wp_update_user( array( 'ID' => $user_id, 'nickname' => $name ) );
}
add_action( 'admin_post_hj_child_nickname_save', function () {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! is_user_logged_in() ) { wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_child_nickname_save' );
	$raw = isset( $_POST['nickname'] ) && is_string( $_POST['nickname'] ) ? wp_unslash( $_POST['nickname'] ) : '';
	$result = hj_child_save_nickname( get_current_user_id(), $raw );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) ); }
	wp_safe_redirect( function_exists( 'hj_member_account_url' ) ? hj_member_account_url() : home_url( '/member-account/' ) );
	exit;
} );

/** Adapt only the plugin's recognised account and homepage markup. */
add_filter( 'do_shortcode_tag', function ( $output, $tag ) {
	if ( 'hj_booking_form' === $tag ) { return hj_child_booking_account_notice( $output ); }
	if ( 'hj_member_google' === $tag && is_front_page() ) {
		$icon = '<span class="hj-member-google__icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 18 18" focusable="false"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/><path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg></span>';
		return str_replace( '<span class="hj-member-google__icon" aria-hidden="true">G</span>', $icon, $output );
	}
	if ( 'hj_member_account' !== $tag || ! is_user_logged_in() ) { return $output; }
	$user = wp_get_current_user();
	// Do not alter administrator, surgeon or custom-role account UIs.
	if ( ! in_array( 'subscriber', (array) $user->roles, true ) || count( (array) $user->roles ) !== 1 ) { return $output; }
	$link = '<p><a href="' . esc_url( admin_url( 'profile.php' ) ) . '">تنظیمات کامل حساب</a></p>';
	if ( strpos( $output, $link ) === false ) { return $output; }
	$form = '<form class="hj-child-nickname" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
		. '<input type="hidden" name="action" value="hj_child_nickname_save">'
		. wp_nonce_field( 'hj_child_nickname_save', '_wpnonce', true, false )
		. '<label for="hj-child-nickname">نام مستعار</label> <input id="hj-child-nickname" name="nickname" maxlength="80" required value="' . esc_attr( get_user_meta( $user->ID, 'nickname', true ) ) . '"> <button type="submit">ذخیره نام مستعار</button></form>';
	return str_replace( $link, $form, $output );
}, 20, 2 );

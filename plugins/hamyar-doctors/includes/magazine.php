<?php
/** Explicit, confirmed email subscriptions. No bulk-mail service is implied. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'init', function () {
	register_post_type( 'hj_subscription', array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'query_var' => false, 'map_meta_cap' => false, 'capabilities' => array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_options' ), 'supports' => array( 'title' ) ) );
} );
function hj_magazine_form() {
	$privacy = get_privacy_policy_url();
	$out = '<form class="hj-magazine-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hj_magazine_subscribe">';
	$out .= '<label for="hj-magazine-email">نشانی جیمیل یا ایمیل</label><div class="hj-magazine-row"><input type="email" id="hj-magazine-email" name="email" maxlength="254" required autocomplete="email" placeholder="name@gmail.com"><button type="submit">اشتراک رایگان</button></div>';
	$out .= '<label class="hj-magazine-consent"><input type="checkbox" name="consent" value="1" required> مایلم ایمیل‌های مجله را دریافت کنم و می‌دانم هر زمان می‌توانم لغو اشتراک کنم.</label>';
	$out .= '<div class="hj-magazine-trap" aria-hidden="true"><label>این فیلد را خالی بگذارید <input name="website" tabindex="-1" autocomplete="off"></label></div>';
	$out .= '<p>برای تکمیل اشتراک، لینک تأیید به ایمیل شما ارسال می‌شود.' . ( $privacy ? ' <a href="' . esc_url( $privacy ) . '">حریم خصوصی</a>' : '' ) . '</p></form>';
	return $out;
}
add_shortcode( 'hj_magazine_subscribe', 'hj_magazine_form' );
function hj_magazine_find( $email ) {
	$ids = get_posts( array( 'post_type' => 'hj_subscription', 'post_status' => array( 'draft', 'publish' ), 'meta_key' => '_hj_email', 'meta_value' => $email, 'fields' => 'ids', 'numberposts' => 1 ) );
	return $ids ? (int) $ids[0] : 0;
}
function hj_magazine_message( $text, $code = 200 ) {
	wp_die( esc_html( $text ) . '<p><a href="' . esc_url( home_url( '/' ) ) . '">بازگشت به سایت</a></p>', 'مجله همیار جراحی', array( 'response' => $code ) );
}
function hj_magazine_subscribe() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { hj_magazine_message( 'درخواست نامعتبر است.', 405 ); }
	if ( ! empty( $_POST['website'] ) || empty( $_POST['consent'] ) ) { hj_magazine_message( 'لطفاً رضایت دریافت ایمیل را تأیید کنید.', 400 ); }
	$email = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? strtolower( trim( sanitize_email( wp_unslash( $_POST['email'] ) ) ) ) : '';
	if ( ! is_email( $email ) || strlen( $email ) > 254 ) { hj_magazine_message( 'نشانی ایمیل معتبر نیست.', 400 ); }
	if ( function_exists( 'hj_sec_rate_limit' ) && ( ! hj_sec_rate_limit( 'mag_ip', 10, HOUR_IN_SECONDS, true ) || ! hj_sec_rate_limit( 'mag_email', 3, HOUR_IN_SECONDS, $email ) ) ) { hj_magazine_message( 'تعداد درخواست‌ها زیاد است؛ بعداً تلاش کنید.', 429 ); }
	// Atomic per-address lock prevents concurrent requests creating duplicate records.
	$lock = 'hj_mag_lock_' . md5( $email );
	$locked_at = (int) get_option( $lock );
	if ( $locked_at && $locked_at < time() - 120 ) { delete_option( $lock ); }
	if ( ! add_option( $lock, time(), '', 'no' ) ) { hj_magazine_message( 'درخواست در حال بررسی است؛ چند لحظه بعد دوباره تلاش کنید.' ); }
	$id = hj_magazine_find( $email );
	if ( $id && 'publish' === get_post_status( $id ) ) { delete_option( $lock ); hj_magazine_message( 'اگر نشانی قابل ثبت باشد، وضعیت اشتراک یا پیام تأیید را در ایمیل خود بررسی کنید.' ); }
	if ( ! $id ) {
		$id = wp_insert_post( array( 'post_type' => 'hj_subscription', 'post_status' => 'draft', 'post_title' => $email ), true );
		if ( is_wp_error( $id ) || ! $id ) { delete_option( $lock ); hj_magazine_message( 'ثبت درخواست ممکن نیست؛ بعداً تلاش کنید.', 500 ); }
		update_post_meta( $id, '_hj_email', $email );
	}
	$confirm = bin2hex( random_bytes( 32 ) );
	$cancel = bin2hex( random_bytes( 32 ) );
	update_post_meta( $id, '_hj_confirm_hash', hash( 'sha256', $confirm ) );
	update_post_meta( $id, '_hj_confirm_expires', time() + 2 * DAY_IN_SECONDS );
	update_post_meta( $id, '_hj_cancel_hash', hash( 'sha256', $cancel ) );
	update_post_meta( $id, '_hj_requested_at', gmdate( 'Y-m-d H:i:s' ) );
	$confirm_url = add_query_arg( array( 'action' => 'hj_magazine_confirm', 'id' => $id, 'token' => $confirm ), admin_url( 'admin-post.php' ) );
	$cancel_url = add_query_arg( array( 'action' => 'hj_magazine_cancel', 'id' => $id, 'token' => $cancel ), admin_url( 'admin-post.php' ) );
	$sent = wp_mail( $email, 'تأیید اشتراک مجله همیار جراحی', "برای تأیید اشتراک رایگان روی این لینک کلیک کنید (تا ۴۸ ساعت معتبر است):\n\n$confirm_url\n\nبرای لغو اشتراک:\n$cancel_url\n\nاگر درخواست نداده‌اید، این پیام را نادیده بگیرید." );
	delete_option( $lock );
	if ( ! $sent ) { hj_doctors_log( 'Magazine confirmation email could not be sent; check WordPress mail settings.' ); hj_magazine_message( 'ارسال پیام تأیید ممکن نشد؛ بعداً تلاش کنید.', 503 ); }
	hj_magazine_message( 'درخواست دریافت شد. برای تکمیل اشتراک، ایمیل تأیید را بررسی کنید.' );
}
add_action( 'admin_post_nopriv_hj_magazine_subscribe', 'hj_magazine_subscribe' );
add_action( 'admin_post_hj_magazine_subscribe', 'hj_magazine_subscribe' );
function hj_magazine_token_request( $action ) {
	$input = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? $_POST : $_GET;
	$id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
	$token = isset( $input['token'] ) && is_string( $input['token'] ) ? sanitize_text_field( wp_unslash( $input['token'] ) ) : '';
	$key = 'confirm' === $action ? '_hj_confirm_hash' : '_hj_cancel_hash';
	$hash = $id ? get_post_meta( $id, $key, true ) : '';
	if ( get_post_type( $id ) !== 'hj_subscription' || ! is_string( $hash ) || ! $hash || ! preg_match( '/^[a-f0-9]{64}$/', $token ) || ! hash_equals( $hash, hash( 'sha256', $token ) ) ) { hj_magazine_message( 'این لینک نامعتبر یا منقضی شده است.', 400 ); }
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		nocache_headers(); header( 'Referrer-Policy: no-referrer' );
		$label = 'confirm' === $action ? 'تأیید اشتراک' : 'لغو اشتراک';
		wp_die( '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hj_magazine_' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $id . '"><input type="hidden" name="token" value="' . esc_attr( $token ) . '"><button type="submit">' . esc_html( $label ) . '</button></form>', 'مجله همیار جراحی', array( 'response' => 200 ) );
	}
	if ( 'confirm' === $action ) {
		if ( (int) get_post_meta( $id, '_hj_confirm_expires', true ) < time() ) { hj_magazine_message( 'لینک تأیید منقضی شده است؛ دوباره ثبت‌نام کنید.', 400 ); }
		$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) || ! $result ) { hj_magazine_message( 'تأیید اشتراک ناموفق بود؛ دوباره تلاش کنید.', 500 ); }
		update_post_meta( $id, '_hj_confirmed_at', gmdate( 'Y-m-d H:i:s' ) );
		delete_post_meta( $id, '_hj_confirm_hash' );
		hj_magazine_message( 'اشتراک رایگان شما تأیید شد.' );
	}
	if ( ! wp_delete_post( $id, true ) ) { hj_magazine_message( 'لغو اشتراک ناموفق بود؛ دوباره تلاش کنید.', 500 ); }
	hj_magazine_message( 'اشتراک لغو و نشانی ایمیل حذف شد.' );
}
foreach ( array( 'confirm', 'cancel' ) as $action ) {
	add_action( 'admin_post_nopriv_hj_magazine_' . $action, function () use ( $action ) { hj_magazine_token_request( $action ); } );
	add_action( 'admin_post_hj_magazine_' . $action, function () use ( $action ) { hj_magazine_token_request( $action ); } );
}
add_action( 'admin_menu', function () { add_management_page( 'مشترکان مجله', 'مشترکان مجله', 'manage_options', 'hj-magazine', 'hj_magazine_admin' ); } );
function hj_magazine_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
	$query = new WP_Query( array( 'post_type' => 'hj_subscription', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 50, 'paged' => $page, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => false ) );
	echo '<div class="wrap"><h1>مشترکان مجله</h1><p>فقط اشتراک‌های تأییدشده برای ارسال ایمیل مجازند. ارسال خودکار مجله در این افزونه پیاده‌سازی نشده است.</p><p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hj_magazine_export' ), 'hj_magazine_export' ) ) . '">خروجی CSV مشترکان تأییدشده</a></p><table class="widefat striped"><thead><tr><th>ایمیل</th><th>وضعیت</th><th>تاریخ تأیید (UTC)</th><th>عملیات</th></tr></thead><tbody>';
	foreach ( $query->posts as $p ) {
		$delete = wp_nonce_url( admin_url( 'admin-post.php?action=hj_magazine_delete&id=' . (int) $p->ID ), 'hj_magazine_delete_' . $p->ID );
		echo '<tr><td>' . esc_html( get_post_meta( $p->ID, '_hj_email', true ) ) . '</td><td>' . ( 'publish' === $p->post_status ? 'تأییدشده' : 'در انتظار تأیید' ) . '</td><td>' . esc_html( get_post_meta( $p->ID, '_hj_confirmed_at', true ) ) . '</td><td><form method="post" action="' . esc_url( $delete ) . '"><button class="button" type="submit">حذف</button></form></td></tr>';
	}
	echo '</tbody></table>';
	for ( $i = 1; $i <= (int) $query->max_num_pages; $i++ ) { echo ' <a href="' . esc_url( add_query_arg( 'paged', $i, admin_url( 'tools.php?page=hj-magazine' ) ) ) . '">' . (int) $i . '</a> '; }
	echo '</div>';
}
add_action( 'admin_post_hj_magazine_delete', function () {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_magazine_delete_' . $id );
	if ( 'hj_subscription' === get_post_type( $id ) ) { wp_delete_post( $id, true ); }
	wp_safe_redirect( admin_url( 'tools.php?page=hj-magazine' ) ); exit;
} );
add_action( 'admin_post_hj_magazine_export', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_magazine_export' );
	nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="magazine-subscribers.csv"' );
	$out = fopen( 'php://output', 'w' ); fputcsv( $out, array( 'email', 'confirmed_at_utc' ) );
	$page = 1;
	do {
		$ids = get_posts( array( 'post_type' => 'hj_subscription', 'post_status' => 'publish', 'posts_per_page' => 200, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		foreach ( $ids as $id ) { $email = (string) get_post_meta( $id, '_hj_email', true ); if ( preg_match( '/^[=+\-@\t\r]/', $email ) ) { $email = "'" . $email; } fputcsv( $out, array( $email, get_post_meta( $id, '_hj_confirmed_at', true ) ) ); }
		$page++;
	} while ( count( $ids ) === 200 );
	fclose( $out ); exit;
} );

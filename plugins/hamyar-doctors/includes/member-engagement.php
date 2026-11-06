<?php
/** Ordinary member Google entry and private, read-only activity. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Suggestions use existing WordPress posts, without a custom database table. */
function hj_member_register_suggestions() {
	$caps = array();
	foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ) as $cap ) { $caps[ $cap ] = 'manage_options'; }
	register_post_type( 'hj_suggestion', array(
		'labels' => array( 'name' => 'پیشنهادهای اعضا', 'singular_name' => 'پیشنهاد عضو', 'edit_item' => 'بررسی پیشنهاد', 'all_items' => 'همه پیشنهادها' ),
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => true, 'show_in_rest' => false, 'show_in_menu' => true,
		'map_meta_cap' => false, 'capabilities' => $caps, 'supports' => array( 'title', 'editor', 'author' ),
		'menu_icon' => 'dashicons-format-chat',
	) );
}
add_action( 'init', 'hj_member_register_suggestions' );

/** Signed member intent prevents a pending surgeon signup from changing roles. */
function hj_member_intent_cookie( $set ) {
	$ts = time();
	$value = $set ? $ts . '|' . hash_hmac( 'sha256', 'hj_member_intent|' . $ts, wp_salt( 'auth' ) ) : '';
	setcookie( 'hj_member_intent', $value, array( 'expires' => $set ? $ts + 1800 : $ts - 3600, 'path' => '/', 'domain' => ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
}
function hj_member_has_intent() {
	if ( empty( $_COOKIE['hj_member_intent'] ) || ! is_string( $_COOKIE['hj_member_intent'] ) ) { return false; }
	$parts = explode( '|', wp_unslash( $_COOKIE['hj_member_intent'] ) );
	if ( count( $parts ) !== 2 || ! ctype_digit( $parts[0] ) ) { return false; }
	$ts = (int) $parts[0];
	return $ts > 0 && $ts <= time() + 60 && time() - $ts <= 1800 && hash_equals( hash_hmac( 'sha256', 'hj_member_intent|' . $ts, wp_salt( 'auth' ) ), $parts[1] );
}

/** Entry stays separate from the doctor's signed-intent flow. */
function hj_member_google_start_url() { return add_query_arg( 'hj_member_google', '1', home_url( '/' ) ); }
function hj_member_google_is_ready() {
	return class_exists( 'NextendSocialLogin' ) && is_callable( array( 'NextendSocialLogin', 'isProviderEnabled' ) ) && NextendSocialLogin::isProviderEnabled( 'google' );
}
function hj_member_google_button() {
	return '<a class="hj-member-google" href="' . esc_url( hj_member_google_start_url() ) . '" rel="nofollow"><span class="hj-member-google__icon" aria-hidden="true">G</span><span>ورود یا ثبت‌نام با گوگل</span></a>';
}
add_shortcode( 'hj_member_google', 'hj_member_google_button' );
add_action( 'init', function () {
	if ( ! isset( $_GET['hj_member_google'] ) || is_admin() || wp_doing_ajax() || wp_doing_cron() ) { return; }
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	nocache_headers();
	$account = hj_member_account_url();
	if ( isset( $_GET['hj_booking_return'] ) && function_exists( 'hj_booking_intent_destination' ) ) {
		$booking_target = hj_booking_intent_destination();
		if ( $booking_target ) { $account = $booking_target; }
	}
	if ( is_user_logged_in() || ! hj_member_google_is_ready() ) { wp_safe_redirect( $account ); exit; }
	// A previously started surgeon flow must never turn this normal login into a doctor signup.
	if ( class_exists( 'HJ_Doctors_Auth_Google' ) ) {
		$name = HJ_Doctors_Auth_Google::INTENT_COOKIE;
		setcookie( $name, '', array( 'expires' => time() - 3600, 'path' => '/', 'domain' => ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	}
	hj_member_intent_cookie( true );
	$base = is_callable( array( 'NextendSocialLogin', 'getLoginUrl' ) ) ? NextendSocialLogin::getLoginUrl() : site_url( 'wp-login.php' );
	// add_query_arg does not encode newly supplied values; the nested URL may
	// contain its own query string and fragment (e.g. the booking form).
	wp_safe_redirect( add_query_arg( array( 'loginSocial' => 'google', 'redirect' => rawurlencode( $account ) ), $base ) ); exit;
}, 4 );

/** Account shortcode may be placed on any page, not only the default one. */
add_action( 'template_redirect', function () {
	$post = get_queried_object();
	if ( is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'hj_member_account' ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
	}
}, 0 );

/** Site administrators alone may edit or delete WordPress comments. */
add_filter( 'map_meta_cap', function ( $caps, $cap ) {
	return 'edit_comment' === $cap ? array( 'manage_options' ) : $caps;
}, 100, 2 );

/** Only display records explicitly tied to the current logged-in user ID. */
function hj_member_activity_html( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || $user_id !== get_current_user_id() ) { return ''; }
	$page = isset( $_GET['hj_activity_page'] ) ? max( 1, min( 1000, absint( wp_unslash( $_GET['hj_activity_page'] ) ) ) ) : 1;
	$out = '<section class="hj-member-activity"><h2>نظرهای من</h2><p>نظرهای ثبت‌شده با همین حساب در این بخش نمایش داده می‌شوند. ویرایش و حذف فقط توسط مدیر انجام می‌شود.</p>';
	$comments = get_comments( array( 'user_id' => $user_id, 'status' => 'all', 'type' => 'comment', 'number' => 21, 'offset' => ( $page - 1 ) * 20, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
	$shown = 0;
	foreach ( $comments as $comment ) {
		if ( $shown >= 20 ) { break; }
		if ( (int) $comment->user_id !== $user_id || ! in_array( (string) $comment->comment_approved, array( '0', '1' ), true ) ) { continue; }
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || 'publish' !== $post->post_status ) { continue; }
		$status = '1' === (string) $comment->comment_approved ? 'تأیید شده' : 'در انتظار بررسی';
		$out .= '<article class="hj-member-entry"><h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3><p>' . nl2br( esc_html( $comment->comment_content ) ) . '</p><small>' . esc_html( $status ) . ' · ' . esc_html( mysql2date( get_option( 'date_format' ), $comment->comment_date ) ) . '</small></article>';
		++$shown;
	}
	if ( ! $shown ) { $out .= '<p>نظری برای نمایش در این صفحه وجود ندارد.</p>'; }
	$out .= '<nav aria-label="صفحه‌های نظرها">';
	if ( $page > 1 ) { $out .= '<a href="' . esc_url( add_query_arg( 'hj_activity_page', $page - 1, hj_member_account_url() ) ) . '#hj-activity">نظرهای جدیدتر</a> '; }
	if ( count( $comments ) > 20 ) { $out .= '<a href="' . esc_url( add_query_arg( 'hj_activity_page', $page + 1, hj_member_account_url() ) ) . '#hj-activity">نظرهای قدیمی‌تر</a>'; }
	$out .= '</nav><h2>پیشنهادهای من</h2><p>پیشنهادها خصوصی هستند و تنها شما و مدیر می‌توانید آن‌ها را ببینید. از ارسال اطلاعات پزشکی حساس خودداری کنید.</p>';
	$suggestion_page = isset( $_GET['hj_suggestion_page'] ) ? max( 1, min( 1000, absint( wp_unslash( $_GET['hj_suggestion_page'] ) ) ) ) : 1;
	$suggestions = get_posts( array( 'post_type' => 'hj_suggestion', 'post_status' => array( 'pending', 'draft', 'publish', 'private' ), 'author' => $user_id, 'posts_per_page' => 21, 'offset' => ( $suggestion_page - 1 ) * 20, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false ) );
	$shown = 0;
	foreach ( $suggestions as $item ) {
		if ( $shown >= 20 ) { break; }
		if ( (int) $item->post_author !== $user_id || 'hj_suggestion' !== $item->post_type || ! in_array( $item->post_status, array( 'pending', 'draft', 'publish', 'private' ), true ) ) { continue; }
		$status = in_array( $item->post_status, array( 'publish', 'private' ), true ) ? 'بررسی شده' : 'در انتظار بررسی';
		$out .= '<article class="hj-member-entry"><p>' . nl2br( esc_html( $item->post_content ) ) . '</p><small>' . esc_html( $status ) . ' · ' . esc_html( get_the_date( '', $item ) ) . '</small></article>'; ++$shown;
	}
	if ( ! $shown ) { $out .= '<p>پیشنهادی برای نمایش در این صفحه وجود ندارد.</p>'; }
	$out .= '<nav aria-label="صفحه‌های پیشنهادها">';
	if ( $suggestion_page > 1 ) { $out .= '<a href="' . esc_url( add_query_arg( 'hj_suggestion_page', $suggestion_page - 1, hj_member_account_url() ) ) . '#hj-activity">پیشنهادهای جدیدتر</a> '; }
	if ( count( $suggestions ) > 20 ) { $out .= '<a href="' . esc_url( add_query_arg( 'hj_suggestion_page', $suggestion_page + 1, hj_member_account_url() ) ) . '#hj-activity">پیشنهادهای قدیمی‌تر</a>'; }
	$out .= '</nav>';
	if ( isset( $_GET['hj_suggestion'] ) && is_string( $_GET['hj_suggestion'] ) && 'sent' === sanitize_key( wp_unslash( $_GET['hj_suggestion'] ) ) ) { $out .= '<p role="status">پیشنهاد شما برای بررسی ثبت شد.</p>'; }
	$out .= '<form class="hj-member-suggestion" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hj_member_suggestion">' . wp_nonce_field( 'hj_member_suggestion', '_wpnonce', true, false ) . '<label for="hj-suggestion-text">پیشنهاد شما</label><textarea id="hj-suggestion-text" name="suggestion" maxlength="2000" required rows="4"></textarea><button type="submit">ارسال پیشنهاد برای مدیر</button></form></section>';
	return '<div id="hj-activity">' . $out . '</div>';
}
add_filter( 'do_shortcode_tag', function ( $output, $tag ) {
	if ( 'hj_member_account' !== $tag ) { return $output; }
	if ( ! is_user_logged_in() ) {
		return $output . '<div class="hj-member-entry-login">' . ( hj_member_google_is_ready() ? hj_member_google_button() : '<p>ورود با گوگل هنوز راه‌اندازی نشده است. می‌توانید از ورود معمولی استفاده کنید.</p>' ) . '</div>';
	}
	return $output . hj_member_activity_html( get_current_user_id() );
}, 10, 2 );

add_action( 'admin_post_hj_member_suggestion', function () {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! is_user_logged_in() ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_member_suggestion' );
	$raw = isset( $_POST['suggestion'] ) && is_string( $_POST['suggestion'] ) ? wp_unslash( $_POST['suggestion'] ) : '';
	$text = trim( sanitize_textarea_field( $raw ) );
	if ( '' === $text || preg_match_all( '/./us', $text ) > 2000 ) { wp_die( 'متن پیشنهاد نامعتبر است.', '', array( 'response' => 400 ) ); }
	if ( function_exists( 'hj_sec_rate_limit' ) && ! hj_sec_rate_limit( 'member_suggestion', 6, HOUR_IN_SECONDS ) ) { wp_die( 'تعداد پیشنهادها بیش از حد مجاز است؛ بعداً دوباره تلاش کنید.', '', array( 'response' => 429 ) ); }
	$id = wp_insert_post( wp_slash( array( 'post_type' => 'hj_suggestion', 'post_status' => 'pending', 'post_title' => 'پیشنهاد عضو ' . get_current_user_id(), 'post_content' => $text, 'post_author' => get_current_user_id() ) ), true );
	if ( is_wp_error( $id ) || ! $id ) { wp_die( 'ثبت پیشنهاد ناموفق بود.', '', array( 'response' => 500 ) ); }
	wp_safe_redirect( add_query_arg( 'hj_suggestion', 'sent', hj_member_account_url() ) . '#hj-activity' ); exit;
} );

add_action( 'wp_enqueue_scripts', function () {
	$path = hj_doctors_path( 'assets/css/member-engagement.css' );
	if ( is_readable( $path ) ) { wp_enqueue_style( 'hj-member-engagement', hj_doctors_url( 'assets/css/member-engagement.css' ), array(), HJ_DOCTORS_VERSION ); }
}, 1500 );

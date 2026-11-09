<?php
/** Scoped homepage subscription UI, using the existing magazine storage. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hj_home_magazine_render( $html, $block ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_front_page() || ( $block['blockName'] ?? null ) !== 'core/group' ) { return $html; }
	$classes = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! in_array( 'hj-magazine-section', $classes, true ) ) { return $html; }
	// Only adapt the known existing block; preserve unrecognised editorial content.
	$old_title = 'اشتراک رایگان مجله اینترنتی همیارجراحی، تنها با یک جیمیل';
	if ( false === strpos( $html, $old_title ) ) { return $html; }
	$title = '<h2 class="wp-block-heading hj-magazine-title">اینجا جیمیلت رو وارد کن.<span>هر وقت هم نخواستی، می‌تونی <strong class="hj-magazine-cancel">لغو</strong> کنی!</span></h2>';
	$updated = preg_replace( '~<h2\b[^>]*>\s*' . preg_quote( $old_title, '~' ) . '\s*</h2>~u', $title, $html, 1, $title_count );
	if ( 1 !== $title_count ) { return $html; }
	$updated = preg_replace( '~<p\b[^>]*\bclass="[^"]*\bhj-magazine-eyebrow\b[^"]*"[^>]*>.*?</p>~us', '', $updated, 1 );
	$updated = str_replace( 'برای دریافت خبرهای مجله، ایمیل خود را وارد و اشتراک را تأیید کنید.', 'تازه‌های همیار جراحی رو در ایمیلت دریافت کن.<span class="hj-magazine-arrow" aria-hidden="true">↓</span>', $updated );
	$updated = preg_replace( '~<p\b[^>]*\bclass="[^"]*\bhj-magazine-account\b[^"]*"[^>]*>.*?</p>~us', '', $updated, 1 );
	// WordPress can leave the shortcode for the_content's later shortcode pass.
	// Render it here when necessary so the known privacy link can be unlinked
	// within this block only; the underlying subscription action remains.
	if ( substr_count( $updated, '[hj_magazine_subscribe]' ) === 1 && shortcode_exists( 'hj_magazine_subscribe' ) ) {
		$updated = str_replace( '[hj_magazine_subscribe]', do_shortcode( '[hj_magazine_subscribe]' ), $updated );
	}
	// Only adapt the known plugin form; keep its server-side compatibility and
	// the action, email field and honeypot. Submitting the form replaces the
	// separate checkbox as the user's explicit subscription request.
	$consent = '<label class="hj-magazine-consent"><input type="checkbox" name="consent" value="1" required> مایلم ایمیل‌های مجله را دریافت کنم و می‌دانم هر زمان می‌توانم لغو اشتراک کنم.</label>';
	$form_start = '<form class="hj-magazine-form"';
	if ( substr_count( $updated, $consent ) === 1 && substr_count( $updated, $form_start ) === 1 ) {
		$updated = str_replace( $consent, '<input type="hidden" name="consent" value="1"><input type="hidden" name="hj_home_newsletter" value="1">', $updated );
		$updated = str_replace( $form_start, '<span id="hj-home-newsletter"></span><form data-hj-home-newsletter="1" class="hj-magazine-form"', $updated );
		$updated = str_replace( esc_url( admin_url( 'admin-post.php' ) ), esc_url( home_url( '/' ) . '#hj-home-newsletter' ), $updated );
		$review = isset( $GLOBALS['hj_newsletter_review'] ) ? $GLOBALS['hj_newsletter_review'] : '';
		$updated = str_replace( 'name="action" value="hj_magazine_subscribe"', 'name="action" value="hj_home_newsletter_subscribe"', $updated );
		$updated = str_replace( 'name="email" maxlength="254"', 'name="email" value="' . esc_attr( $review ) . '" maxlength="254"', $updated );
		$updated = str_replace( '</form>', '<input type="hidden" name="hj_reviewed_email" value="' . esc_attr( $review ) . '">' . wp_nonce_field( 'hj_home_newsletter', 'hj_newsletter_nonce', true, false ) . '</form>', $updated );
		$updated = str_replace( 'برای تکمیل اشتراک، لینک تأیید به ایمیل شما ارسال می‌شود.', 'با زدن دکمه، برای دریافت ایمیل‌های همیار جراحی مشترک می‌شوید.', $updated );
		$result = isset( $_GET['hj_newsletter_result'] ) && is_string( $_GET['hj_newsletter_result'] ) ? sanitize_key( wp_unslash( $_GET['hj_newsletter_result'] ) ) : '';
		$statuses = hj_home_newsletter_statuses();
		$text = $review ? 'یه بار جیمیلت رو چک کن، ببین درسته؟ اگه درسته یه بار دیگه دکمه اشتراک رو بزن.' : ( isset( $statuses[ $result ] ) ? $statuses[ $result ] : '' );
		$feedback = '<p class="hj-newsletter-status" data-state="' . esc_attr( $review ? 'review' : $result ) . '" role="status" aria-live="polite">' . esc_html( $text ) . '</p>';
		$updated = preg_replace( '~</form>~', $feedback . '</form>', $updated, 1 );
	}
	$privacy = get_privacy_policy_url();
	if ( $privacy ) {
		$anchor = '<a href="' . esc_url( $privacy ) . '">حریم خصوصی</a>';
		$updated = str_replace( array( ' ' . $anchor, $anchor ), '', $updated );
	}
	return $updated;
}
add_filter( 'render_block', 'hj_home_magazine_render', 20, 2 );

/** Messages are fixed on the server; email addresses and tokens never enter result URLs. */
function hj_home_newsletter_statuses() {
	return array(
		'confirmed' => 'شما مشترک همیار جراحی شدین.',
		'busy' => 'درخواست در حال بررسی است؛ کمی بعد دوباره تلاش کنید.',
		'invalid' => 'لطفاً یک نشانی ایمیل معتبر وارد کنید.',
		'rate' => 'تعداد درخواست‌ها زیاد است؛ لطفاً بعداً تلاش کنید.',
		'mail_failed' => 'ارسال ایمیل خوش‌آمد ممکن نشد و اشتراک ثبت نشد؛ لطفاً بعداً تلاش کنید.',
		'error' => 'ثبت اشتراک ممکن نشد؛ لطفاً بعداً دوباره تلاش کنید.',
	);
}
function hj_home_newsletter_respond( $status, $code = 200 ) {
	$messages = hj_home_newsletter_statuses();
	nocache_headers();
	if ( isset( $_SERVER['HTTP_X_HJ_NEWSLETTER_UI'] ) && '1' === $_SERVER['HTTP_X_HJ_NEWSLETTER_UI'] ) {
		wp_send_json( array( 'status' => $status, 'message' => $messages[ $status ] ), $code );
	}
	wp_safe_redirect( add_query_arg( 'hj_newsletter_result', $status, home_url( '/' ) ) . '#hj-home-newsletter' );
	exit;
}

/** The first POST (also the no-JS fallback) re-renders the homepage for review.
 * The second POST uses the same storage and cancel handler as plugin 0.3.22. */
function hj_home_newsletter_submit() {
	if ( ! is_front_page() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ( $_POST['action'] ?? '' ) !== 'hj_home_newsletter_subscribe' || ( $_POST['hj_home_newsletter'] ?? '' ) !== '1' ) { return; }
	nocache_headers();
	if ( ! isset( $_POST['hj_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_newsletter_nonce'] ) ), 'hj_home_newsletter' ) ) { hj_home_newsletter_respond( 'error', 403 ); }
	if ( ! empty( $_POST['website'] ) || ! function_exists( 'hj_magazine_find' ) || ! post_type_exists( 'hj_subscription' ) ) { hj_home_newsletter_respond( 'error', 400 ); }
	$raw = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? trim( wp_unslash( $_POST['email'] ) ) : '';
	$email = strtolower( $raw );
	if ( strlen( $email ) > 254 || sanitize_email( $email ) !== $email || ! is_email( $email ) ) { hj_home_newsletter_respond( 'invalid', 400 ); }
	$reviewed = isset( $_POST['hj_reviewed_email'] ) && is_string( $_POST['hj_reviewed_email'] ) ? trim( wp_unslash( $_POST['hj_reviewed_email'] ) ) : '';
	if ( $reviewed !== $raw ) { $GLOBALS['hj_newsletter_review'] = $raw; return; }
	if ( function_exists( 'hj_sec_rate_limit' ) && ( ! hj_sec_rate_limit( 'mag_ip', 10, HOUR_IN_SECONDS, true ) || ! hj_sec_rate_limit( 'mag_email', 3, HOUR_IN_SECONDS, $email ) ) ) { hj_home_newsletter_respond( 'rate', 429 ); }
	$lock = 'hj_mag_lock_' . md5( $email );
	$locked_at = (int) get_option( $lock );
	if ( $locked_at && $locked_at < time() - 120 ) { delete_option( $lock ); }
	if ( ! add_option( $lock, time(), '', 'no' ) ) { hj_home_newsletter_respond( 'busy', 409 ); }
	$id = hj_magazine_find( $email );
	// Preserve the existing active subscriber's original cancellation link.
	if ( $id && 'publish' === get_post_status( $id ) ) { delete_option( $lock ); hj_home_newsletter_respond( 'confirmed' ); }
	if ( ! $id ) {
		$id = wp_insert_post( array( 'post_type' => 'hj_subscription', 'post_status' => 'draft', 'post_title' => $email ), true );
		if ( is_wp_error( $id ) || ! $id ) { delete_option( $lock ); hj_home_newsletter_respond( 'error', 500 ); }
		update_post_meta( $id, '_hj_email', $email );
	}
	try { $cancel = bin2hex( random_bytes( 32 ) ); } catch ( Exception $e ) { delete_option( $lock ); hj_home_newsletter_respond( 'error', 500 ); }
	$cancel_url = add_query_arg( array( 'action' => 'hj_magazine_cancel', 'id' => $id, 'token' => $cancel ), admin_url( 'admin-post.php' ) );
	$articles = get_posts( array( 'post_type' => array( 'post', 'patient_guide', 'surgery' ), 'post_status' => 'publish', 'has_password' => false, 'numberposts' => 3, 'orderby' => array( 'comment_count' => 'DESC', 'date' => 'DESC' ), 'no_found_rows' => true ) );
	$body = '<div dir="rtl" style="font-family:Tahoma,Arial,sans-serif;line-height:1.9;color:#061710"><p>شما مشترک همیار جراحی شدین. خوش آمدید!</p>';
	if ( $articles ) {
		$body .= '<p>چند مقاله پیشنهادی از همیار جراحی:</p><ul>';
		foreach ( $articles as $article ) { $body .= '<li><a href="' . esc_url( get_permalink( $article ) ) . '">' . esc_html( get_the_title( $article ) ) . '</a></li>'; }
		$body .= '</ul>';
	}
	$body .= '<p style="text-align:left;font-size:12px"><a href="' . esc_url( $cancel_url ) . '">لغو اشتراک</a></p></div>';
	if ( ! wp_mail( $email, 'به همیار جراحی خوش آمدید', $body, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) { delete_option( $lock ); hj_home_newsletter_respond( 'mail_failed', 503 ); }
	update_post_meta( $id, '_hj_cancel_hash', hash( 'sha256', $cancel ) );
	if ( ! hash_equals( hash( 'sha256', $cancel ), (string) get_post_meta( $id, '_hj_cancel_hash', true ) ) ) { delete_option( $lock ); hj_home_newsletter_respond( 'error', 500 ); }
	update_post_meta( $id, '_hj_signup_method', 'on_site_review' );
	update_post_meta( $id, '_hj_requested_at', gmdate( 'Y-m-d H:i:s' ) );
	update_post_meta( $id, '_hj_confirmed_at', gmdate( 'Y-m-d H:i:s' ) );
	$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $result ) || ! $result ) { delete_option( $lock ); hj_home_newsletter_respond( 'error', 500 ); }
	delete_post_meta( $id, '_hj_confirm_hash' );
	delete_post_meta( $id, '_hj_confirm_expires' );
	delete_option( $lock );
	hj_home_newsletter_respond( 'confirmed' );
}
add_action( 'template_redirect', 'hj_home_newsletter_submit', 0 );
add_action( 'init', function () {
	if ( ( isset( $_GET['hj_newsletter_result'] ) || ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' ) && ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
}, 0 );
add_action( 'template_redirect', function () { if ( is_front_page() && isset( $_GET['hj_newsletter_result'] ) ) { nocache_headers(); } }, 0 );

/** Keep old confirmation links working for subscriptions from earlier versions. */
function hj_home_newsletter_confirm_die( $message, $title = '', $args = array() ) {
	if ( false !== strpos( wp_strip_all_tags( (string) $message ), 'اشتراک رایگان شما تأیید شد.' ) ) {
		nocache_headers();
		wp_safe_redirect( add_query_arg( 'hj_newsletter_result', 'confirmed', home_url( '/' ) ) . '#hj-home-newsletter' );
		exit;
	}
	_default_wp_die_handler( $message, $title, $args );
}
function hj_home_newsletter_confirm_handler() {
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { add_filter( 'wp_die_handler', function () { return 'hj_home_newsletter_confirm_die'; } ); }
}
add_action( 'admin_post_nopriv_hj_magazine_confirm', 'hj_home_newsletter_confirm_handler', 1 );
add_action( 'admin_post_hj_magazine_confirm', 'hj_home_newsletter_confirm_handler', 1 );

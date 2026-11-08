<?php
/** Presentation-only update of the known newsletter block on the front page. */
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
		$result = isset( $_GET['hj_newsletter_result'] ) && is_string( $_GET['hj_newsletter_result'] ) ? sanitize_key( wp_unslash( $_GET['hj_newsletter_result'] ) ) : '';
		$statuses = hj_home_newsletter_statuses();
		$text = isset( $statuses[ $result ] ) ? $statuses[ $result ] : '';
		$feedback = '<p class="hj-newsletter-status" role="status" aria-live="polite">' . esc_html( $text ) . '</p>';
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

/** No email or token is stored in URLs; messages intentionally avoid enumeration. */
function hj_home_newsletter_statuses() {
	return array(
		'confirmed' => 'شما مشترک همیار جراحی شدید.',
		'pending' => 'درخواست اشتراک ثبت شد. برای فعال‌شدن، لطفاً ایمیل تأیید را بررسی کنید.',
		'check' => 'اگر نشانی قابل ثبت باشد، وضعیت اشتراک یا ایمیل تأیید را بررسی کنید.',
		'busy' => 'درخواست در حال بررسی است؛ کمی بعد دوباره تلاش کنید.',
		'invalid' => 'لطفاً یک نشانی ایمیل معتبر وارد کنید.',
		'rate' => 'تعداد درخواست‌ها زیاد است؛ لطفاً بعداً تلاش کنید.',
		'mail_failed' => 'ارسال ایمیل تأیید ممکن نشد؛ لطفاً بعداً دوباره تلاش کنید.',
		'error' => 'نتیجه مشخص نیست؛ لطفاً ایمیل خود را بررسی کنید یا بعداً تلاش کنید.',
	);
}
function hj_home_newsletter_result( $message ) {
	$text = wp_strip_all_tags( (string) $message );
	$known = array(
		'درخواست دریافت شد. برای تکمیل اشتراک، ایمیل تأیید را بررسی کنید.' => 'pending',
		'اگر نشانی قابل ثبت باشد، وضعیت اشتراک یا پیام تأیید را در ایمیل خود بررسی کنید.' => 'check',
		'درخواست در حال بررسی است؛ چند لحظه بعد دوباره تلاش کنید.' => 'busy',
		'نشانی ایمیل معتبر نیست.' => 'invalid',
		'لطفاً رضایت دریافت ایمیل را تأیید کنید.' => 'invalid',
		'تعداد درخواست‌ها زیاد است؛ بعداً تلاش کنید.' => 'rate',
		'ارسال پیام تأیید ممکن نشد؛ بعداً تلاش کنید.' => 'mail_failed',
	);
	foreach ( $known as $needle => $status ) { if ( false !== strpos( $text, $needle ) ) { return $status; } }
	return 'error';
}
function hj_home_newsletter_die( $message, $title = '', $args = array() ) {
	$status = hj_home_newsletter_result( $message );
	$statuses = hj_home_newsletter_statuses();
	$code = in_array( $status, array( 'pending', 'check', 'busy' ), true ) ? 200 : ( 'invalid' === $status ? 400 : ( 'rate' === $status ? 429 : 503 ) );
	nocache_headers();
	if ( isset( $_SERVER['HTTP_X_HJ_NEWSLETTER_UI'] ) && '1' === $_SERVER['HTTP_X_HJ_NEWSLETTER_UI'] ) {
		status_header( $code );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'status' => $status, 'message' => $statuses[ $status ] ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'hj_newsletter_result', $status, home_url( '/' ) ) . '#hj-home-newsletter' );
	exit;
}
function hj_home_newsletter_install_handler() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['hj_home_newsletter'] ) || '1' !== $_POST['hj_home_newsletter'] ) { return; }
	add_filter( 'wp_die_handler', function () { return 'hj_home_newsletter_die'; } );
}
add_action( 'admin_post_nopriv_hj_magazine_subscribe', 'hj_home_newsletter_install_handler', 1 );
add_action( 'admin_post_hj_magazine_subscribe', 'hj_home_newsletter_install_handler', 1 );
add_action( 'init', function () {
	if ( isset( $_GET['hj_newsletter_result'] ) && ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
}, 0 );
add_action( 'template_redirect', function () { if ( is_front_page() && isset( $_GET['hj_newsletter_result'] ) ) { nocache_headers(); } }, 0 );

/** After a valid confirmation POST, show the actual activation in the site. */
function hj_home_newsletter_confirm_die( $message, $title = '', $args = array() ) {
	if ( false !== strpos( wp_strip_all_tags( (string) $message ), 'اشتراک رایگان شما تأیید شد.' ) ) {
		nocache_headers();
		wp_safe_redirect( add_query_arg( 'hj_newsletter_result', 'confirmed', home_url( '/' ) ) . '#hj-home-newsletter' );
		exit;
	}
	_default_wp_die_handler( $message, $title, $args );
}
function hj_home_newsletter_confirm_handler() {
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		add_filter( 'wp_die_handler', function () { return 'hj_home_newsletter_confirm_die'; } );
	}
}
add_action( 'admin_post_nopriv_hj_magazine_confirm', 'hj_home_newsletter_confirm_handler', 1 );
add_action( 'admin_post_hj_magazine_confirm', 'hj_home_newsletter_confirm_handler', 1 );

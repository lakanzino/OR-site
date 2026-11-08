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
	// within this block only; consent, fields, confirmation copy and action remain.
	if ( substr_count( $updated, '[hj_magazine_subscribe]' ) === 1 && shortcode_exists( 'hj_magazine_subscribe' ) ) {
		$updated = str_replace( '[hj_magazine_subscribe]', do_shortcode( '[hj_magazine_subscribe]' ), $updated );
	}
	$privacy = get_privacy_policy_url();
	if ( $privacy ) {
		$anchor = '<a href="' . esc_url( $privacy ) . '">حریم خصوصی</a>';
		$updated = str_replace( array( ' ' . $anchor, $anchor ), '', $updated );
	}
	return $updated;
}
add_filter( 'render_block', 'hj_home_magazine_render', 20, 2 );

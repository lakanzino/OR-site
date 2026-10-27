<?php
/**
 * Emergency access bar under the header (child 0.20.2).
 *
 * The patient reading a post-op page is often one hand deep and worried; the
 * whole point is that the emergency number is never more than one glance and
 * one tap away. The parent header already carries branding, navigation and
 * search, and the child theme does not override `header.php` — replacing it
 * would drop the custom logo, the parent's menu/search script and its skip
 * link. So this bar is injected through `wp_body_open` instead: one string of
 * markup, no request, no layout shift (a fixed line of text with a fixed
 * height, not an image or a web font).
 *
 * Rules kept on purpose:
 *  - only ۱۱۵; no other number is printed because no other number is verified;
 *  - no dose, no triage advice, no symptom list — the footer band and the
 *    article's red-flag box already carry the wording;
 *  - nothing is loaded on the front end but this markup and its CSS.
 *
 * @package Lax_Theme_Child
 * @since   0.20.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Should the bar render on this request?
 *
 * @return bool
 */
function hj_emergency_bar_enabled(): bool {
	$enabled = ! is_admin() && ! is_feed() && ! is_embed() && ! is_404();
	return (bool) apply_filters( 'hj_emergency_bar_enabled', $enabled );
}

/**
 * Print the bar right after `<body>`.
 *
 * @return void
 */
function hj_emergency_bar(): void {
	if ( ! hj_emergency_bar_enabled() ) {
		return;
	}
	?>
	<div class="hj-emergency-bar" role="region" aria-label="دسترسی اورژانسی">
		<div class="container hj-emergency-bar__inner">
			<p class="hj-emergency-bar__text">
				<strong>علائم خطر جدی؟</strong> منتظر خواندن مقاله نمانید؛ با تیم درمان یا اورژانس تماس بگیرید.
			</p>
			<a class="hj-emergency-bar__call" href="tel:115">
				<span class="hj-emergency-bar__number" aria-hidden="true">۱۱۵</span>
				<span class="hj-emergency-bar__label">تماس با اورژانس</span>
			</a>
		</div>
	</div>
	<?php
}
add_action( 'wp_body_open', 'hj_emergency_bar', 20 );

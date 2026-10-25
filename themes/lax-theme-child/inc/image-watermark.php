<?php
/**
 * Automatic image watermark (HamyarJarahi).
 *
 * Every featured image rendered through the standard WordPress thumbnail
 * functions gets a small, letter-spaced "HamyarJarahi" mark in the lower-left
 * area. Because the mark is added by code, it covers existing uploads and any
 * future image automatically — no image files are altered and no Persian text
 * is printed on the photos.
 *
 * The mark is decorative only: it is hidden from screen readers and cannot be
 * selected or clicked, so it never interferes with the image or the caption.
 *
 * @package Lax_Theme_Child
 * @since   0.12.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrap a featured image with the watermark overlay.
 *
 * @param string $html          Image markup.
 * @param int    $post_id       Post ID.
 * @param int    $thumbnail_id  Attachment ID.
 * @param string $size          Requested size.
 * @param array  $attr          Image attributes.
 * @return string
 */
function lax_theme_child_watermark_thumbnail( $html, $post_id = 0, $thumbnail_id = 0, $size = '', $attr = array() ) {
	if ( '' === trim( (string) $html ) ) {
		return $html;
	}

	if ( is_admin() || is_feed() ) {
		return $html;
	}

	// Never wrap twice (some themes call the filter more than once).
	if ( false !== strpos( $html, 'orsite-wm' ) ) {
		return $html;
	}

	return '<span class="orsite-thumb">' . $html
		. '<span class="orsite-wm" aria-hidden="true" dir="ltr"><b>H</b>amyar<i>J</i>arahi</span>'
		. '</span>';
}
add_filter( 'post_thumbnail_html', 'lax_theme_child_watermark_thumbnail', 10, 5 );

<?php
/**
 * One date format on every page: Solar Hijri (شمسی).
 *
 * Before this file a page could show three different things for the same
 * date: «اکتبر 22, 2026» inside pasted page content, «۲۰۲۶/۱۰/۲۰» in a
 * hardcoded "last updated" line, and «۳۰ مهر ۱۴۰۵» in the template byline.
 *
 * Two mechanisms fix that:
 *   1. Every rendered content string goes through the Gregorian→Jalali
 *      converter, including static pages, excerpts and widgets.
 *   2. Hardcoded "last updated" lines are removed from the seeded trust
 *      pages, because the template already prints one real, database-backed
 *      date. One date, one source.
 *
 * @package Lax_Theme_Child
 * @since   0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert any stray Gregorian date in the text to Jalali.
 *
 * @param string $text Rendered HTML.
 * @return string
 */
function hj_dates_normalize_text( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return $text;
	}
	if ( is_admin() || is_feed() ) {
		return $text;
	}
	if ( ! function_exists( 'hj_convert_inline_gregorian_dates' ) ) {
		return $text;
	}

	return hj_convert_inline_gregorian_dates( $text );
}

/**
 * Singular content: convert dates, then drop a duplicate "last updated" line.
 *
 * @param string $content Post content.
 * @return string
 */
function hj_dates_normalize_content( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}
	if ( is_admin() || is_feed() || ! is_singular() ) {
		return $content;
	}

	$content = hj_dates_normalize_text( $content );
	$content = hj_dates_strip_hardcoded_update_line( $content );

	return $content;
}
add_filter( 'the_content', 'hj_dates_normalize_content', 99 );

/**
 * Excerpts appear on cards and in the lead paragraph, so they need it too.
 *
 * @param string $excerpt Excerpt text.
 * @return string
 */
function hj_dates_normalize_excerpt( $excerpt ) {
	if ( is_admin() || is_feed() ) {
		return $excerpt;
	}
	return hj_dates_normalize_text( $excerpt );
}
add_filter( 'the_excerpt', 'hj_dates_normalize_excerpt', 99 );
add_filter( 'get_the_excerpt', 'hj_dates_normalize_excerpt', 99 );

/**
 * Text widgets are editor input and can carry a pasted date as well.
 *
 * @param string $text Widget content.
 * @return string
 */
function hj_dates_normalize_widget( $text ) {
	if ( is_admin() || is_feed() ) {
		return $text;
	}
	return hj_dates_normalize_text( $text );
}
add_filter( 'widget_text_content', 'hj_dates_normalize_widget', 99 );

/**
 * Remove a hardcoded "last updated" paragraph from seeded trust pages.
 *
 * Only touches the five seeded pages, only when the line carries a date, and
 * only because the template prints the real modified date right above the
 * content. Article content is never touched.
 *
 * @param string $content Post content.
 * @return string
 */
function hj_dates_strip_hardcoded_update_line( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	$post_id = (int) get_queried_object_id();
	if ( ! $post_id || ! function_exists( 'hj_is_seeded_page' ) || ! hj_is_seeded_page( $post_id ) ) {
		return $content;
	}

	$labels = 'آخرین\s+به[‌\s]?روزرسانی|آخرین\s+ویرایش|تاریخ\s+به[‌\s]?روزرسانی|به[‌\s]?روزرسانی\s+شده\s+در';
	$date   = '[^\n<>]{0,40}?(?:1[34]\d{2}|20[0-9]{2})[^\n<>]{0,20}';

	// Whole block element that exists only to carry the date.
	$cleaned = preg_replace(
		'#<(p|div|span)\b[^>]*>\s*(?:' . $labels . ')\s*[:\x{003A}]?\s*' . $date . '\s*</\1>#isu',
		'',
		$content
	);
	if ( is_string( $cleaned ) ) {
		$content = $cleaned;
	}

	// Same line written without markup.
	$cleaned = preg_replace( '#(?:' . $labels . ')\s*[:\x{003A}]?\s*' . $date . '#isu', '', $content );
	if ( is_string( $cleaned ) ) {
		$content = $cleaned;
	}

	return $content;
}

/**
 * Admin-only notice: the pages that still show a raw Gregorian year in their
 * stored content, so an editor can fix the source instead of the output.
 *
 * @return array<int,array{id:int,title:string,edit:string}>
 */
function hj_dates_pages_with_gregorian_year(): array {
	$out = array();

	$posts = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 200,
			'no_found_rows'  => true,
		)
	);

	foreach ( $posts as $post ) {
		if ( ! preg_match( '/(?:1[34]\d{2}|20[0-9]{2})[\/\-\.](?:0?[1-9]|1[0-2])[\/\-\.](?:0?[1-9]|[12]\d|3[01])/u', (string) $post->post_content ) ) {
			continue;
		}
		$out[] = array(
			'id'    => (int) $post->ID,
			'title' => get_the_title( $post ),
			'edit'  => (string) get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	return $out;
}

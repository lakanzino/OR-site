<?php
/**
 * Make article tables behave on every screen (child 0.21.0).
 *
 * The 0.14 stylesheet sets `table-layout: fixed`, which is fine for two or
 * three columns and turns ugly for four or five: on a desktop the cells squeeze
 * and the table drifts to one side, on a phone the last column is simply cut
 * off. Wrapping each table in a scroll container keeps the table at its natural
 * width and lets the reader swipe instead of guessing.
 *
 * @package Lax_Theme_Child
 * @since   0.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrap every table of an article body in `.hj-table-scroll`.
 *
 * @param string $content Post content HTML.
 * @return string
 */
function hj_wrap_article_tables( $content ) {
	if ( ! is_string( $content ) || false === stripos( $content, '<table' ) ) {
		return $content;
	}

	if ( false !== strpos( $content, 'hj-table-scroll' ) ) {
		return $content;
	}

	$wrapped = preg_replace_callback(
		'#<table\b.*?</table>#isu',
		static function ( $m ) {
			return '<div class="hj-table-scroll">' . $m[0] . '</div>';
		},
		$content
	);

	return is_string( $wrapped ) ? $wrapped : $content;
}
add_filter( 'the_content', 'hj_wrap_article_tables', 20 );

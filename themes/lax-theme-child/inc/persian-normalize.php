<?php
/**
 * Persian typography normalisation on save (child 0.16.0).
 *
 * Editors paste text from Word, Telegram and PDFs, which brings Arabic
 * letters (ي / ك / ى), Arabic-Indic digits and broken half-spaces into the
 * article. This file fixes those at save time, without touching HTML tags,
 * shortcodes or Latin text.
 *
 * Rules enforced here are structural, not editorial: no word is added or
 * removed, no medical claim is altered, no number is invented. Only the
 * character set and the half-space (ZWNJ) placement change.
 *
 * Turn it off for one request with: add_filter( 'hj_normalize_persian', '__return_false' );
 *
 * @package Lax_Theme_Child
 * @since   0.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Persian letter range plus ZWNJ, used to avoid touching Latin words. */
const HJ_FA_RANGE = '\x{0621}-\x{063A}\x{0641}-\x{064A}\x{067E}\x{0686}\x{0698}\x{06A9}\x{06AF}\x{06CC}\x{200C}';

/**
 * Normalise the text nodes of a string, leaving tags and shortcodes intact.
 *
 * @param string $text Raw value.
 * @return string
 */
function hj_normalize_persian( $text ) {
	if ( ! is_string( $text ) || '' === trim( $text ) ) {
		return $text;
	}

	if ( ! apply_filters( 'hj_normalize_persian', true, $text ) ) {
		return $text;
	}

	// Split on HTML tags and shortcodes; only the remaining segments are text.
	$parts = preg_split( '/(<[^>]*>|\[[^\]]*\])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) {
		return hj_normalize_persian_segment( $text );
	}

	foreach ( $parts as $index => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] || '[' === $part[0] ) {
			continue;
		}
		$parts[ $index ] = hj_normalize_persian_segment( $part );
	}

	return implode( '', $parts );
}

/**
 * Character and spacing fixes for one text segment.
 *
 * @param string $text Text without markup.
 * @return string
 */
function hj_normalize_persian_segment( $text ) {
	// 1. Arabic letters that Persian writes differently.
	$text = str_replace(
		array( 'ي', 'ك', 'ى', 'ة', '‏', '‎' ),
		array( 'ی', 'ک', 'ی', 'ه', '', '' ),
		$text
	);

	// 2. Arabic-Indic digits → Persian digits (never Latin: the site is Persian).
	$text = str_replace(
		array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		$text
	);

	// 3. Half-space (ZWNJ) for the Persian prefixes «می» و «نمی».
	$range = HJ_FA_RANGE;
	$text  = preg_replace(
		'/(^|[\s«"(\[])(ن?می) (['
		. $range
		. ']+)/u',
		'$1$2‌$3',
		$text
	);

	// 4. Half-space before plural / comparative suffixes.
	$text = preg_replace(
		'/([' . $range . ']+) (ها|های|هایی|تر|ترین)(?=[\s،.,!?:;»)\]]|$)/u',
		'$1‌$2',
		$text
	);

	// 5. Spacing hygiene: no double spaces, no space before punctuation.
	$text = preg_replace( '/\x{200C}{2,}/u', '‌', $text );
	$text = preg_replace( '/ {2,}/u', ' ', $text );
	$text = preg_replace( '/ +([،.,!?:;»%])/u', '$1', $text );
	$text = preg_replace( '/([،؛:])(?=[^\s\d])/u', '$1 ', $text );

	return $text;
}

/**
 * Apply to post content, title and excerpt while saving.
 *
 * @param string $value Saved value.
 * @return string
 */
function hj_normalize_persian_on_save( $value ) {
	return hj_normalize_persian( $value );
}
add_filter( 'content_save_pre', 'hj_normalize_persian_on_save', 5 );
add_filter( 'title_save_pre', 'hj_normalize_persian_on_save', 5 );
add_filter( 'excerpt_save_pre', 'hj_normalize_persian_on_save', 5 );

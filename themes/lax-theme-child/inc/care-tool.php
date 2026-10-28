<?php
/**
 * Display-only labels for the homepage care assistant.
 *
 * The original article title, URLs, stage titles and clinical data stay intact.
 *
 * @package Lax_Theme_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show the first part of a long surgery or day label in the selector.
 * A colon or a spaced em dash separates the explanatory suffix. Other dashes
 * are left alone because they can be part of a name or a numerical range.
 *
 * @param string $title Full article or care-stage title.
 * @return string Compact label, or the original if no usable first part exists.
 */
function hj_care_option_label( $title ) {
	$title = trim( (string) $title );
	$parts = preg_split( '/\s*[:：]\s*|\s+—\s+/u', $title, 2 );
	$first = is_array( $parts ) ? trim( $parts[0] ) : '';
	return '' !== $first ? $first : $title;
}

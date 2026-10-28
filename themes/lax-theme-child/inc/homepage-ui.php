<?php
/**
 * Presentation-only helpers for the homepage.
 *
 * Names, slugs, links and counts come from existing taxonomy data; nothing is
 * written back to WordPress or changed on archive pages.
 *
 * @package Lax_Theme_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translate only the generic English laparoscopy label on the homepage.
 * Other specialty labels, including more specific laparoscopy groups, are
 * returned unchanged and all original taxonomy URLs remain intact.
 *
 * @param string $name Stored specialty name.
 * @param string $slug Stored specialty slug.
 * @return string Homepage display name.
 */
function hj_home_specialty_label( $name, $slug = '' ) {
	$name = (string) $name;
	$plain = trim( $name );

	if ( preg_match( '/^(?:general[ _-]+)?laparoscop(?:y|ic)(?:[ _-]+surgery)?$/i', $plain ) ) {
		return 'general-laparoscopy' === $slug || preg_match( '/^general[ _-]/i', $plain )
			? 'لاپاروسکوپی عمومی'
			: 'لاپاروسکوپی';
	}

	return $name;
}

/**
 * Pick up to four real glossary topics. Prefer topics with published words,
 * then the site's four standard groups in their editorial order; use other
 * existing groups if necessary. Empty existing groups can fill remaining
 * places, but no categories or links are invented.
 *
 * @return array[] Existing groups with name, slug, URL and published count.
 */
function hj_home_glossary_topics() {
	if ( ! function_exists( 'hj_glossary_get_groups_with_counts' ) ) {
		return array();
	}

	$groups = hj_glossary_get_groups_with_counts( false );
	$groups = array_values( array_filter( $groups, static function ( $group ) {
		return is_array( $group ) && ! empty( $group['name'] ) && ! empty( $group['slug'] ) && ! empty( $group['url'] );
	} ) );

	$standard_order = array_flip( array( 'general-terms', 'anesthesia-terms', 'sterile-terms', 'device-terms' ) );
	usort( $groups, static function ( $a, $b ) use ( $standard_order ) {
		$a_count = (int) ( $a['count'] ?? 0 );
		$b_count = (int) ( $b['count'] ?? 0 );
		if ( ( $a_count > 0 ) !== ( $b_count > 0 ) ) {
			return $a_count > 0 ? -1 : 1;
		}

		$a_rank = $standard_order[ $a['slug'] ] ?? PHP_INT_MAX;
		$b_rank = $standard_order[ $b['slug'] ] ?? PHP_INT_MAX;
		if ( $a_rank !== $b_rank ) {
			return $a_rank <=> $b_rank;
		}
		if ( $a_count !== $b_count ) {
			return $b_count <=> $a_count;
		}
		return strcmp( (string) $a['slug'], (string) $b['slug'] );
	} );

	return array_slice( $groups, 0, 4 );
}

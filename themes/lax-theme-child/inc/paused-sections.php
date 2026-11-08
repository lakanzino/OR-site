<?php
/** Temporarily remove two archive entry points without changing stored content. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Match only the two local archive roots, never external URLs or descendants. */
function hj_paused_section_url( $url ) {
	if ( ! function_exists( 'hj_nav_same_destination' ) ) { return false; }
	foreach ( array( 'student_content' => '/student/', 'or_environment' => '/or-environment/' ) as $type => $fallback ) {
		$archive = get_post_type_archive_link( $type );
		if ( hj_nav_same_destination( $url, home_url( $fallback ) ) || ( $archive && hj_nav_same_destination( $url, $archive ) ) ) {
			return true;
		}
	}
	return false;
}

/** Hide only a known, single-link landing card; stored blocks stay intact. */
function hj_pause_home_card( $html, $block ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_front_page() || ! class_exists( 'WP_HTML_Tag_Processor' ) ) { return $html; }
	if ( ( $block['blockName'] ?? null ) !== 'core/group' ) { return $html; }
	$classes = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! in_array( 'hj-landing-card', $classes, true ) ) { return $html; }
	$processor = new WP_HTML_Tag_Processor( $html );
	$count = 0; $matches = false;
	while ( $processor->next_tag( 'a' ) ) {
		++$count;
		$href = $processor->get_attribute( 'href' );
		if ( is_string( $href ) && hj_paused_section_url( $href ) ) { $matches = true; }
	}
	return 1 === $count && $matches ? '' : $html;
}
add_filter( 'render_block', 'hj_pause_home_card', 10, 2 );

/** Filter public primary menu output and reparent retained children safely. */
function hj_pause_primary_menu_items( $items, $args ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || empty( $args->theme_location ) || 'primary' !== $args->theme_location ) { return $items; }
	$removed = array();
	foreach ( $items as $item ) {
		if ( ( 'post_type_archive' === $item->type && in_array( $item->object, array( 'student_content', 'or_environment' ), true ) ) || hj_paused_section_url( $item->url ) ) {
			$removed[ (string) $item->ID ] = (string) $item->menu_item_parent;
		}
	}
	if ( ! $removed ) { return $items; }
	$visible = array();
	foreach ( $items as $item ) {
		if ( isset( $removed[ (string) $item->ID ] ) ) { continue; }
		$parent = (string) $item->menu_item_parent; $seen = array();
		while ( isset( $removed[ $parent ] ) && ! isset( $seen[ $parent ] ) ) { $seen[ $parent ] = true; $parent = $removed[ $parent ]; }
		if ( isset( $seen[ $parent ] ) ) { $parent = '0'; }
		if ( $parent !== (string) $item->menu_item_parent ) { $item = clone $item; $item->menu_item_parent = $parent; }
		$visible[] = $item;
	}
	return $visible;
}
add_filter( 'wp_nav_menu_objects', 'hj_pause_primary_menu_items', 10, 2 );

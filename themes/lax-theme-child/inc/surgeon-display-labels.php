<?php
/** Known public surgeon interface labels; do not rewrite stored content or data. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hj_surgeon_labels_public_request() {
	return ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
}

/** Change only exact text in the recognized front-page blocks, never attributes. */
function hj_surgeon_home_labels( $html, $block ) {
	if ( ! hj_surgeon_labels_public_request() || ! is_front_page() ) { return $html; }
	$name = $block['blockName'] ?? '';
	$classes = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
	$rules = array();
	if ( 'core/paragraph' === $name && in_array( 'hj-landing-note', $classes, true ) ) {
		$rules['~(>\s*✦\s*)پزشک هستید؟(?=\s|<)~u'] = '$1جراح هستید؟';
	} elseif ( 'core/paragraph' === $name && in_array( 'hj-landing-eyebrow', $classes, true ) ) {
		$rules['~(>\s*)پزشکان و متخصصان(\s*<)~u'] = '$1جراحان و متخصصان$2';
	} elseif ( 'core/button' === $name && in_array( 'hj-btn-secondary', $classes, true ) ) {
		$rules['~(>\s*)ورود به پنل پزشک(\s*</a>)~u'] = '$1ورود به پنل جراح$2';
	} elseif ( 'core/paragraph' === $name && in_array( 'hj-landing-disclaimer', $classes, true ) ) {
		$rules['~(>\s*)اطلاعات این سایت جنبه آموزشی دارد و جایگزین مشورت با پزشک و تیم درمان نیست\.(\s*</p>)~u'] = '$1اطلاعات این سایت جنبه آموزشی دارد و جایگزین مشورت با تیم درمان نیست.$2';
	}
	foreach ( $rules as $pattern => $replacement ) {
		$updated = preg_replace( $pattern, $replacement, $html, 1 );
		if ( null !== $updated ) { $html = $updated; }
	}
	return $html;
}
add_filter( 'render_block', 'hj_surgeon_home_labels', 20, 2 );

/** Display the known dashboard page label without renaming the stored page. */
function hj_surgeon_dashboard_title( $title, $post_id = 0 ) {
	if ( ! hj_surgeon_labels_public_request() || ! is_page( 'my-profile' ) || (int) $post_id !== (int) get_queried_object_id() ) { return $title; }
	return in_array( trim( $title ), array( 'ناحیهٔ پزشک', 'ناحیه پزشک' ), true ) ? 'ناحیهٔ جراح' : $title;
}
add_filter( 'the_title', 'hj_surgeon_dashboard_title', 10, 2 );

/** Keep the title of the existing doctor archive readable without changing CPT labels. */
function hj_surgeon_document_title( $parts ) {
	if ( ! hj_surgeon_labels_public_request() || ! isset( $parts['title'] ) ) { return $parts; }
	if ( is_post_type_archive( 'doctor' ) && in_array( trim( $parts['title'] ), array( 'پزشک‌ها', 'پزشکان', 'بایگانی پزشک‌ها' ), true ) ) {
		$parts['title'] = 'جراحان';
	} elseif ( is_page( 'my-profile' ) && in_array( trim( $parts['title'] ), array( 'ناحیهٔ پزشک', 'ناحیه پزشک' ), true ) ) {
		$parts['title'] = 'ناحیهٔ جراح';
	}
	return $parts;
}
add_filter( 'document_title_parts', 'hj_surgeon_document_title' );

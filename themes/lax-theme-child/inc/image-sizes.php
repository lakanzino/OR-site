<?php
/**
 * Registered image sizes for the child theme.
 *
 * All sizes use soft resize (crop = false): if editors supply images at
 * the exact recommended dimensions, nothing is ever cropped.
 *
 * Recommended source files (WebP, sRGB, quality ~80):
 * - Featured image:        1200 x 675  (16:9)
 * - Full-width in article: 1200 x 675  (16:9)
 * - Half text / half image: 800 x 600  (4:3)
 * - 1/3 text / 2/3 image:  1200 x 750  (8:5)
 * - Archive card: generated automatically from the featured image.
 *
 * @package Lax_Theme_Child
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register proportional (never cropped) image sizes.
 */
function orsite_register_image_sizes() {
	add_image_size( 'orsite-featured', 1200, 675, false );
	add_image_size( 'orsite-card', 600, 338, false );
	add_image_size( 'orsite-full', 1200, 675, false );
	add_image_size( 'orsite-half', 800, 600, false );
	add_image_size( 'orsite-wide', 1200, 750, false );
}
add_action( 'after_setup_theme', 'orsite_register_image_sizes', 20 );

/**
 * Offer the child sizes in the editor size picker.
 *
 * @param array<string,string> $sizes Existing sizes.
 * @return array<string,string>
 */
function orsite_image_size_names( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'orsite-featured' => __( 'Featured (1200x675)', 'lax-theme-child' ),
			'orsite-full'     => __( 'Full width (1200x675)', 'lax-theme-child' ),
			'orsite-half'     => __( 'Half column (800x600)', 'lax-theme-child' ),
			'orsite-wide'     => __( 'Wide column (1200x750)', 'lax-theme-child' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'orsite_image_size_names' );

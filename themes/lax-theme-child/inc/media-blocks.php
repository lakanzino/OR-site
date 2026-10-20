<?php
/**
 * Ready-made image layouts for article content (block patterns).
 *
 * Three responsive models, all boxed with raised/inset shadows:
 * 1. Full-width image, highlighted caption below.
 * 2. Half text / half image.
 * 3. One-third text / two-thirds image.
 *
 * Editors only replace the placeholder image and texts; the layout,
 * spacing and responsiveness come from child.css.
 *
 * @package Lax_Theme_Child
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the media pattern category and the three layouts.
 */
function orsite_register_media_blocks() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category(
		'orsite-media',
		array( 'label' => __( 'Article media layouts', 'lax-theme-child' ) )
	);

	register_block_pattern(
		'lax-theme-child/media-full-caption',
		array(
			'title'       => __( 'Image: full width with caption', 'lax-theme-child' ),
			'description' => __( 'Full-width image (1200x675 WebP) with a highlighted caption box below.', 'lax-theme-child' ),
			'categories'  => array( 'orsite-media' ),
			'content'     => '<!-- wp:group {"className":"orsite-media orsite-media--full","layout":{"type":"constrained"}} --><div class="wp-block-group orsite-media orsite-media--full"><!-- wp:image {"sizeSlug":"orsite-full","className":"orsite-media__figure"} --><figure class="wp-block-image size-orsite-full orsite-media__figure"><img alt=""/></figure><!-- /wp:image --><!-- wp:paragraph {"className":"orsite-caption"} --><p class="orsite-caption">[توضیح تصویر — در صورت نیاز]</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
		)
	);

	register_block_pattern(
		'lax-theme-child/media-half',
		array(
			'title'       => __( 'Image: half text, half image', 'lax-theme-child' ),
			'description' => __( 'Two equal columns (image 800x600 WebP); stacks on mobile.', 'lax-theme-child' ),
			'categories'  => array( 'orsite-media' ),
			'content'     => '<!-- wp:group {"className":"orsite-media orsite-media--split","layout":{"type":"constrained"}} --><div class="wp-block-group orsite-media orsite-media--split"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"className":"orsite-media__text"} --><p class="orsite-media__text">[متن نمونه — توسط تیم محتوا جایگزین شود]</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:image {"sizeSlug":"orsite-half","className":"orsite-media__figure"} --><figure class="wp-block-image size-orsite-half orsite-media__figure"><img alt=""/></figure><!-- /wp:image --><!-- wp:paragraph {"className":"orsite-caption"} --><p class="orsite-caption">[توضیح تصویر — در صورت نیاز]</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->',
		)
	);

	register_block_pattern(
		'lax-theme-child/media-third',
		array(
			'title'       => __( 'Image: one-third text, two-thirds image', 'lax-theme-child' ),
			'description' => __( 'Narrow text column beside a wide image (1200x750 WebP); stacks on mobile.', 'lax-theme-child' ),
			'categories'  => array( 'orsite-media' ),
			'content'     => '<!-- wp:group {"className":"orsite-media orsite-media--wide","layout":{"type":"constrained"}} --><div class="wp-block-group orsite-media orsite-media--wide"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:paragraph {"className":"orsite-media__text"} --><p class="orsite-media__text">[متن نمونه — توسط تیم محتوا جایگزین شود]</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:image {"sizeSlug":"orsite-wide","className":"orsite-media__figure"} --><figure class="wp-block-image size-orsite-wide orsite-media__figure"><img alt=""/></figure><!-- /wp:image --><!-- wp:paragraph {"className":"orsite-caption"} --><p class="orsite-caption">[توضیح تصویر — در صورت نیاز]</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->',
		)
	);
}
add_action( 'init', 'orsite_register_media_blocks' );

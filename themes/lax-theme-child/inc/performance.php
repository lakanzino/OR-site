<?php
/**
 * Front-end hygiene for a light, fast medical article page (child 0.16.0).
 *
 * The parent theme already serves local Vazirmatn `woff2` with
 * `font-display: swap` and preloads the Regular weight, so fonts are not
 * touched here. What this file adds is the small set of requests that no
 * page of this site needs:
 *
 *  - WordPress emoji script and its DNS lookup on every page,
 *  - Dashicons for visitors who are not logged in,
 *  - the block library CSS on article types whose body is plain HTML,
 *  - JPEG re-encoding: new uploads are written as WebP when the server can,
 *  - lazy-loading: the first image keeps its priority, the rest stay lazy.
 *
 * @package Lax_Theme_Child
 * @since   0.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 1. Emoji: remove the script, styles and the extra DNS lookup.
 * ---------------------------------------------------------------------- */

/**
 * Disable the core emoji layer on the front end.
 *
 * @return void
 */
function hj_disable_emoji_layer(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'hj_disable_emoji_layer' );

/**
 * Drop the s.w.org preconnect that only the emoji script used.
 *
 * @param array  $urls          Resource hints.
 * @param string $relation_type Hint type.
 * @return array
 */
function hj_filter_resource_hints( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type ) {
		$urls = array_filter(
			(array) $urls,
			static function ( $url ) {
				return false === strpos( (string) $url, 's.w.org' );
			}
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'hj_filter_resource_hints', 10, 2 );

/* -------------------------------------------------------------------------
 * 2. Dashicons and block CSS for visitors.
 * ---------------------------------------------------------------------- */

/**
 * Drop Dashicons for logged-out visitors on the front end.
 *
 * @return void
 */
function hj_dequeue_dashicons_guests(): void {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}
	wp_dequeue_style( 'dashicons' );
	wp_deregister_style( 'dashicons' );
}
add_action( 'wp_enqueue_scripts', 'hj_dequeue_dashicons_guests', 100 );

/**
 * Drop the block library CSS on our article types when the body has no blocks.
 *
 * Article packages are plain HTML, so the block stylesheet is dead weight;
 * pages built with the editor keep it.
 *
 * @return void
 */
function hj_conditional_block_css(): void {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	$post = get_post();
	if ( ! $post || ! in_array( (string) $post->post_type, array( 'surgery', 'patient_guide', 'or_environment', 'glossary' ), true ) ) {
		return;
	}
	if ( function_exists( 'has_blocks' ) && has_blocks( $post->post_content ) ) {
		return;
	}
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'hj_conditional_block_css', 100 );

/* -------------------------------------------------------------------------
 * 3. Images: WebP output and sane lazy-loading.
 * ---------------------------------------------------------------------- */

/**
 * Ask WordPress to write WebP for new JPEG uploads (WP 6.1+).
 *
 * @param array $formats Mime => editor output format.
 * @return array
 */
function hj_prefer_webp_output( $formats ) {
	if ( ! is_array( $formats ) ) {
		return $formats;
	}
	$formats['image/jpeg'] = 'image/webp';
	return $formats;
}
add_filter( 'image_editor_output_format', 'hj_prefer_webp_output' );

/**
 * Keep the first image of the page eager; everything below stays lazy.
 *
 * @param int $threshold Number of images that skip `loading="lazy"`.
 * @return int
 */
function hj_lazy_threshold( $threshold ) {
	return 1;
}
add_filter( 'wp_omit_loading_attr_threshold', 'hj_lazy_threshold' );

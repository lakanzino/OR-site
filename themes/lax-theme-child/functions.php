<?php
/**
 * Lax Theme Child functions.
 *
 * Project-specific features for the operating-room education site live in
 * this child theme. Generic presentation stays in the `lax-theme` parent.
 *
 * @package Lax_Theme_Child
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAX_THEME_CHILD_VERSION' ) ) {
	define( 'LAX_THEME_CHILD_VERSION', '0.15.0' );
}

/**
 * Load Solar Hijri (Jalali) date conversion & unified byline helpers.
 */
require_once get_stylesheet_directory() . '/inc/jalali-date.php';

/**
 * Load content architecture (post types and taxonomies).
 */
require_once get_stylesheet_directory() . '/inc/content-types.php';

/** Disease directory and editable starter drafts. */
require_once get_stylesheet_directory() . '/inc/disease-directory.php';

/**
 * Load article display helpers (TOC, badges, related articles).
 */
require_once get_stylesheet_directory() . '/inc/article-display.php';

/**
 * Load image sizes and article media block patterns.
 */
require_once get_stylesheet_directory() . '/inc/image-watermark.php';
require_once get_stylesheet_directory() . '/inc/image-bulk-replace.php';
require_once get_stylesheet_directory() . '/inc/image-sizes.php';
require_once get_stylesheet_directory() . '/inc/media-blocks.php';

/**
 * Load the essential static pages (data is generated from
 * content/pages/*.md by scripts/build-essential-pages.py).
 */
require_once get_stylesheet_directory() . '/inc/essential-pages-data.php';
require_once get_stylesheet_directory() . '/inc/essential-pages.php';

/**
 * Load baseline technical SEO (silent when an SEO plugin is active).
 */
require_once get_stylesheet_directory() . '/inc/seo-basics.php';

/**
 * SEO index policy (0.13.0): empty and thin listings stay out of the index.
 */
require_once get_stylesheet_directory() . '/inc/seo-index-policy.php';

/**
 * One date format everywhere (0.13.0).
 */
require_once get_stylesheet_directory() . '/inc/date-consistency.php';

/**
 * Load analytics & search-console connections (dashboard codes page,
 * GA4/Clarity tags, verification metas, dashboard deep links).
 */
require_once get_stylesheet_directory() . '/inc/analytics.php';

/**
 * Load the GitHub updater (dashboard connection, versions, file-level updates).
 */
require_once get_stylesheet_directory() . '/inc/github-updater.php';

/**
 * Load high-performance Glossary & Auto-Tooltip Engine (5,000+ terms).
 */
require_once get_stylesheet_directory() . '/inc/glossary-engine.php';

/**
 * One-time repair of the numbered glossary import (0.13.0).
 */
require_once get_stylesheet_directory() . '/inc/glossary-cleanup.php';

/**
 * Load the multi-group package importer (ZIP -> post + SEO + featured image + audit).
 */
require_once get_stylesheet_directory() . '/inc/article-importer.php';

/**
 * Load the editorial roadmap catalog, admin publishing progress dashboard, and Glossary Admin.
 */
if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once get_stylesheet_directory() . '/inc/glossary-admin.php';
	require_once get_stylesheet_directory() . '/inc/content-roadmap-data.php';
	require_once get_stylesheet_directory() . '/inc/content-dashboard.php';
}

/**
 * Child theme setup.
 */
function lax_theme_child_setup() {
	load_child_theme_textdomain( 'lax-theme-child', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'lax_theme_child_setup', 11 );

/**
 * Enqueue child assets after the parent styles.
 *
 * The parent already enqueues the child `style.css` through its
 * `lax-theme-style` handle (via get_stylesheet_uri()); this function only
 * adds the child's component stylesheet, so nothing is loaded twice.
 */
function lax_theme_child_assets() {
	wp_enqueue_style(
		'lax-theme-child',
		get_stylesheet_directory_uri() . '/assets/css/child.css',
		array( 'lax-theme-style' ),
		LAX_THEME_CHILD_VERSION
	);

	wp_enqueue_style(
		'lax-theme-child-glossary',
		get_stylesheet_directory_uri() . '/assets/css/glossary.css',
		array( 'lax-theme-child' ),
		LAX_THEME_CHILD_VERSION
	);

	wp_enqueue_script(
		'lax-theme-child-fit-headings',
		get_stylesheet_directory_uri() . '/assets/js/fit-headings.js',
		array(),
		LAX_THEME_CHILD_VERSION,
		true
	);

	wp_enqueue_script(
		'lax-theme-child-article-layout',
		get_stylesheet_directory_uri() . '/assets/js/article-layout.js',
		array(),
		LAX_THEME_CHILD_VERSION,
		true
	);

	wp_enqueue_script(
		'lax-theme-child-glossary-tooltip',
		get_stylesheet_directory_uri() . '/assets/js/glossary-tooltip.js',
		array(),
		LAX_THEME_CHILD_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'lax_theme_child_assets', 20 );

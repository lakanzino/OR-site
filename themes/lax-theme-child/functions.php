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
	define( 'LAX_THEME_CHILD_VERSION', '0.33.26' );
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
require_once get_stylesheet_directory() . '/inc/article-tables.php';

/**
 * Load patient-need sections for article templates (scope note, ask-before
 * checklist, plain-words glossary, visible FAQ) — child 0.15.0.
 */

/**
 * Load the editorial quality layer (child 0.16.0):
 * Persian typography on save, pre-publish gate, freshness watch,
 * medical callout shortcode, SurgicalProcedure schema and front-end hygiene.
 */
require_once get_stylesheet_directory() . '/inc/persian-normalize.php';
require_once get_stylesheet_directory() . '/inc/source-whitelist.php';
/*
 * Not loaded on purpose (child 0.21.0):
 *  - inc/patient-needs.php and inc/quick-facts.php only rendered the
 *    patient-need boxes and the clinical summary that 0.15–0.20 injected into
 *    every article (even guides with no surgery). Their meta boxes would now
 *    collect text that never reaches the page, so they are out of the include
 *    chain until the article layout supports them again.
 */
require_once get_stylesheet_directory() . '/inc/references-box.php';
require_once get_stylesheet_directory() . '/inc/faq-accordion.php';
require_once get_stylesheet_directory() . '/inc/nav-menus.php';
// Reversible public navigation only; original archives and content remain intact.
$hj_paused_sections = get_stylesheet_directory() . '/inc/paused-sections.php';
if ( is_readable( $hj_paused_sections ) ) { include_once $hj_paused_sections; }
// Exact public UI labels only; stored pages and professional records stay intact.
$hj_surgeon_labels = get_stylesheet_directory() . '/inc/surgeon-display-labels.php';
if ( is_readable( $hj_surgeon_labels ) ) { include_once $hj_surgeon_labels; }
// Optional while an in-place theme update is copying files.
$hj_surgeons_path = get_stylesheet_directory() . '/inc/home-surgeons.php';
if ( is_readable( $hj_surgeons_path ) ) { include_once $hj_surgeons_path; }
// In-place updates copy files individually: do not break a concurrent request
// if this optional display helper has not been copied yet.
if ( is_readable( get_stylesheet_directory() . '/inc/care-tool.php' ) ) {
	require_once get_stylesheet_directory() . '/inc/care-tool.php';
}
// This helper is also optional during a file-by-file update. Clear the stat
// cache in persistent workers; include (rather than require) avoids a fatal
// error if a file changes between the readability check and the include.
$hj_homepage_ui_path = get_stylesheet_directory() . '/inc/homepage-ui.php';
clearstatcache( true, $hj_homepage_ui_path );
if ( is_readable( $hj_homepage_ui_path ) ) {
	@include_once $hj_homepage_ui_path; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Optional during an in-place update.
}
require_once get_stylesheet_directory() . '/inc/publish-gate.php';
require_once get_stylesheet_directory() . '/inc/content-freshness.php';
require_once get_stylesheet_directory() . '/inc/medical-boxes.php';
require_once get_stylesheet_directory() . '/inc/schema-procedure.php';
require_once get_stylesheet_directory() . '/inc/performance.php';

/** Presentation-only Soft UI tokens, editor styles and local-font preload. */
require_once get_stylesheet_directory() . '/inc/soft-ui.php';

/**
 * Article body is left exactly as the editor wrote it (child 0.21.0).
 *
 * 0.15–0.20 injected patient-need boxes, a references box and an FAQ
 * accordion into `the_content`. On the live site that produced two sources
 * lists, two FAQs and two disclaimers per article, and pushed the table of
 * contents to the bottom of the page. The injectors stay in the codebase for
 * later reuse but are unhooked here: one switch, not a rewrite.
 */
remove_filter( 'the_content', 'hj_refs_upgrade_content', 7 );
remove_filter( 'the_content', 'hj_faq_upgrade_content', 7 );

/**
 * Load image sizes and article media block patterns.
 */
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
 * Cache-busting version for a child asset.
 *
 * The theme version alone is not enough: after an in-place update the file
 * changes while the version string can lag (theme cache, stale package), and
 * the browser keeps the old stylesheet. The file's own mtime is the truth, so
 * the query string always moves when the bytes move.
 *
 * @param string $relative Path inside the child theme.
 * @return string
 */
function lax_theme_child_asset_ver( $relative ) {
	$file = get_stylesheet_directory() . '/' . ltrim( (string) $relative, '/' );
	$time = is_readable( $file ) ? (int) filemtime( $file ) : 0;
	return $time > 0 ? LAX_THEME_CHILD_VERSION . '.' . $time : LAX_THEME_CHILD_VERSION;
}

/**
 * Enqueue child assets after the parent styles.
 *
 * Keep the parent's font dependency as a source-less handle: the identical
 * local faces are declared in the child style.css instead. This prevents both
 * a duplicate font stylesheet and a duplicate regular-weight download.
 */
function lax_theme_child_assets() {
	$styles = wp_styles();
	if ( isset( $styles->registered['lax-theme-fonts'] ) ) {
		$parent_fonts = get_template_directory_uri() . '/assets/css/fonts.css';
		if ( $parent_fonts === $styles->registered['lax-theme-fonts']->src ) {
			// Preserve dependencies and any inline CSS attached by other code.
			$styles->registered['lax-theme-fonts']->src = false;
		}
	} else {
		wp_register_style( 'lax-theme-fonts', false, array(), null );
	}

	// The parent points this handle at the child stylesheet but versions it
	// with the parent version. Keep its dependencies/inline CSS and update only
	// the version, so third-party additions are not lost on re-registration.
	if ( isset( $styles->registered['lax-theme-style'] ) ) {
		$styles->registered['lax-theme-style']->ver = lax_theme_child_asset_ver( 'style.css' );
	} else {
		wp_enqueue_style(
			'lax-theme-style',
			get_stylesheet_uri(),
			array( 'lax-theme-main' ),
			lax_theme_child_asset_ver( 'style.css' )
		);
	}

	wp_enqueue_style(
		'lax-theme-child',
		get_stylesheet_directory_uri() . '/assets/css/child.css',
		array( 'lax-theme-style' ),
		lax_theme_child_asset_ver( 'assets/css/child.css' )
	);

	wp_enqueue_style(
		'lax-theme-child-glossary',
		get_stylesheet_directory_uri() . '/assets/css/glossary.css',
		array( 'lax-theme-child' ),
		lax_theme_child_asset_ver( 'assets/css/glossary.css' )
	);

	// Load the design layer last, after both legacy child stylesheets.
	wp_enqueue_style(
		'lax-theme-child-soft-ui',
		get_stylesheet_directory_uri() . '/assets/css/soft-ui.css',
		array( 'lax-theme-child-glossary' ),
		lax_theme_child_asset_ver( 'assets/css/soft-ui.css' )
	);


	// Let headings wrap at readable sizes instead of shrinking them on load.
	wp_enqueue_script(
		'lax-theme-child-soft-tabs',
		get_stylesheet_directory_uri() . '/assets/js/soft-tabs.js',
		array(),
		lax_theme_child_asset_ver( 'assets/js/soft-tabs.js' ),
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

/**
 * لایهٔ ریسپانسیو سراسری.
 *
 * آخرین شیت بارگذاری می‌شود (اولویت ۹۹۸، بعد از static-home.css که روی
 * ۱۰۰ است و پیش از نسخهٔ پشتیبان افزونه که روی ۹۹۹ است) تا چیدمان موبایل
 * یک‌دست بماند.
 */
function lax_theme_child_responsive_assets() {
	$relative = 'assets/css/responsive.css';
	$path     = get_stylesheet_directory() . '/' . $relative;

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_style(
		'lax-theme-responsive',
		get_stylesheet_directory_uri() . '/' . $relative,
		array(),
		lax_theme_child_asset_ver( $relative )
	);
}
add_action( 'wp_enqueue_scripts', 'lax_theme_child_responsive_assets', 998 );


/** Editable static homepage installer and presentation. */
// Updates copy files individually; do not fatal if the new helper has not arrived yet.
$hj_static_home_path = get_stylesheet_directory() . '/inc/static-home.php';
clearstatcache( true, $hj_static_home_path );
if ( is_readable( $hj_static_home_path ) ) {
	@include_once $hj_static_home_path; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular( 'doctor' ) ) {
		wp_enqueue_style( 'hj-doctor-profile', get_stylesheet_directory_uri() . '/assets/css/doctor-profile.css', array(), lax_theme_child_asset_ver( 'assets/css/doctor-profile.css' ) );
	}
}, 100 );

/** Emerald Glass is a reversible presentation layer, loaded only on the front end. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) { return; }
	$base = 'assets/css/hj-emerald-glass.css';
	$compat = 'assets/css/hj-emerald-glass-compat.css';
	if ( ! is_readable( get_stylesheet_directory() . '/' . $base ) ) { return; }
	wp_enqueue_style( 'hj-emerald-glass', get_stylesheet_directory_uri() . '/' . $base, array(), lax_theme_child_asset_ver( $base ) );
	if ( is_readable( get_stylesheet_directory() . '/' . $compat ) ) {
		wp_enqueue_style( 'hj-emerald-glass-compat', get_stylesheet_directory_uri() . '/' . $compat, array( 'hj-emerald-glass' ), lax_theme_child_asset_ver( $compat ) );
	}
}, 1200 );

/** Black RTL footer, independent of the optional glass design layer. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) { return; }
	$relative = 'assets/css/footer-black.css';
	if ( is_readable( get_stylesheet_directory() . '/' . $relative ) ) {
		wp_enqueue_style( 'hj-footer-black', get_stylesheet_directory_uri() . '/' . $relative, array(), lax_theme_child_asset_ver( $relative ) );
	}
}, 1300 );

/** Black header and menu are front-end presentation only. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) { return; }
	$relative = 'assets/css/header-black.css';
	if ( is_readable( get_stylesheet_directory() . '/' . $relative ) ) {
		wp_enqueue_style( 'hj-header-black', get_stylesheet_directory_uri() . '/' . $relative, array(), lax_theme_child_asset_ver( $relative ) );
	}
}, 1500 );

// Explicit draft workflow; never automatically applies content to the live page.
$hj_home_draft_path = get_stylesheet_directory() . '/inc/homepage-draft.php';
if ( is_readable( $hj_home_draft_path ) ) { require_once $hj_home_draft_path; }

// Opt-in targeted update of the current landing page and scoped presentation.
$hj_orbit_path = get_stylesheet_directory() . '/inc/homepage-orbit.php';
if ( is_readable( $hj_orbit_path ) ) { require_once $hj_orbit_path; }

/** Front-page visual study; no content migration or data changes. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! is_front_page() ) { return; }
	$relative = 'assets/css/home-calm-cyber.css';
	if ( is_readable( get_stylesheet_directory() . '/' . $relative ) ) {
		wp_enqueue_style( 'hj-home-calm-cyber', get_stylesheet_directory_uri() . '/' . $relative, array(), lax_theme_child_asset_ver( $relative ) );
	}
}, 1700 );

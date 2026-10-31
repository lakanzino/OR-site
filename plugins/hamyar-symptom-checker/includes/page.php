<?php
/**
 * The standalone, non-public page that hosts the checker.
 *
 * The page uses a plugin template (no shortcode, no iframe), so the tool can be
 * switched off by returning the page to draft / trashing it, and nothing leaks
 * into other pages: assets and styles are only enqueued for this one page.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ID of the page this plugin owns, or 0 when it is missing.
 */
function hj_sc_page_id() {
	$page_id = (int) get_option( HJ_SC_PAGE_OPTION, 0 );
	if ( $page_id <= 0 ) {
		return 0;
	}
	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return 0;
	}
	return $page_id;
}

/**
 * Create the draft page once. Never touches other pages and never overwrites an
 * existing page with the same slug.
 */
function hj_sc_activate() {
	if ( hj_sc_page_id() > 0 ) {
		return;
	}

	$existing = get_page_by_path( HJ_SC_PAGE_SLUG );
	if ( $existing instanceof WP_Post ) {
		update_option( HJ_SC_PAGE_OPTION, (int) $existing->ID );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'     => 'دستیار نشانه‌ها (پیش‌نمایش داخلی)',
			'post_name'      => HJ_SC_PAGE_SLUG,
			'post_content'   => '',
			'post_status'    => 'draft',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'meta_input'     => array(
				'_hj_symptom_checker' => HJ_SC_VERSION,
			),
		),
		true
	);

	if ( ! is_wp_error( $page_id ) && $page_id ) {
		update_option( HJ_SC_PAGE_OPTION, (int) $page_id );
	}
}

/**
 * Is the current front-end request the checker page?
 */
function hj_sc_is_checker_page() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return false;
	}
	$page_id = hj_sc_page_id();
	return $page_id > 0 && (int) get_queried_object_id() === $page_id;
}

/**
 * Use the plugin template for this page only.
 *
 * @param string $template Theme template chosen by WordPress.
 * @return string
 */
function hj_sc_template_include( $template ) {
	if ( ! hj_sc_is_checker_page() ) {
		return $template;
	}
	$own = HJ_SC_DIR . 'templates/checker-page.php';
	return is_readable( $own ) ? $own : $template;
}
add_filter( 'template_include', 'hj_sc_template_include', 99 );

/**
 * Cache-busting version from the file timestamp, so a rebuilt package is never
 * served from a stale cache.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hj_sc_asset_version( $relative ) {
	$path = HJ_SC_DIR . 'assets/' . ltrim( $relative, '/' );
	return is_readable( $path ) ? (string) filemtime( $path ) : HJ_SC_VERSION;
}

/**
 * URL of an asset inside the plugin.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hj_sc_asset_url( $relative ) {
	return HJ_SC_URL . 'assets/' . ltrim( $relative, '/' );
}

/**
 * Configuration handed to the browser. Everything here is public data: the
 * draft bundle, the evidence map and the resolved article links.
 *
 * @return array<string,mixed>
 */
function hj_sc_config() {
	return array(
		'pluginVersion' => HJ_SC_VERSION,
		'bundleVersion' => hj_sc_bundle_version(),
		'bundleUrl'     => hj_sc_asset_url( 'data/knowledge-bundle.v1.json' ),
		'evidenceUrl'   => hj_sc_asset_url( 'engine/data/evidence-map.v1.json' ),
		'topicLinks'    => (object) hj_sc_topic_links(),
	);
}

/**
 * JSON for the inline config script, hardened against `</script>` breakouts.
 */
function hj_sc_config_json() {
	return (string) wp_json_encode(
		hj_sc_config(),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
	);
}

/**
 * Enqueue the UI only on the checker page.
 */
function hj_sc_enqueue_assets() {
	if ( ! hj_sc_is_checker_page() ) {
		return;
	}

	wp_enqueue_style(
		'hj-sc-ui',
		hj_sc_asset_url( 'ui/styles.css' ),
		array(),
		hj_sc_asset_version( 'ui/styles.css' )
	);

	wp_enqueue_script(
		'hj-sc-app',
		hj_sc_asset_url( 'ui/app.js' ),
		array(),
		hj_sc_asset_version( 'ui/app.js' ),
		true
	);

	wp_add_inline_script( 'hj-sc-app', 'window.HJ_SC_CONFIG = ' . hj_sc_config_json() . ';', 'before' );

	add_filter( 'script_loader_tag', 'hj_sc_module_script_tag', 10, 3 );
}
add_action( 'wp_enqueue_scripts', 'hj_sc_enqueue_assets' );

/**
 * The entry file is an ES module; WordPress does not add `type="module"` itself.
 *
 * @param string $tag    Script tag markup.
 * @param string $handle Script handle.
 * @param string $src    Script source URL.
 * @return string
 */
function hj_sc_module_script_tag( $tag, $handle, $src ) {
	if ( 'hj-sc-app' !== $handle ) {
		return $tag;
	}

	// WordPress wraps the inline "before" data into the same tag string, so the
	// tag must be *amended*, never replaced: adding type="module" to the entry
	// script only keeps the config script (and any future inline data) intact.
	$updated = preg_replace_callback(
		'/<script\b(?=[^>]*\bid="hj-sc-app-js")/i',
		static function () {
			return '<script type="module"';
		},
		$tag,
		1
	);

	return is_string( $updated ) ? $updated : $tag;
}

/**
 * Keep the preview page out of search engines even if it is published by mistake.
 *
 * @param array<string,bool> $robots Robots directives.
 * @return array<string,bool>
 */
function hj_sc_robots( $robots ) {
	if ( ! hj_sc_is_checker_page() ) {
		return $robots;
	}
	unset( $robots['index'], $robots['follow'] );
	$robots['noindex']  = true;
	$robots['nofollow'] = true;
	return $robots;
}
add_filter( 'wp_robots', 'hj_sc_robots' );

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
function hjsc_page_id() {
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
 * Find a page by slug without `get_page_by_path()`: the query below only uses
 * `get_posts()`, so the plugin does not depend on a helper that a future
 * WordPress release could deprecate.
 *
 * @return int Existing page id, or 0.
 */
function hjsc_find_page_by_slug() {
	$found = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'any',
			'name'             => HJ_SC_PAGE_SLUG,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return ( is_array( $found ) && ! empty( $found ) ) ? (int) $found[0] : 0;
}



/**
 * Create the page, but never take the site down when something goes wrong.
 *
 * @return bool True when the page exists (or was adopted) afterwards.
 */
function hjsc_try_create_page() {
	if ( hjsc_page_id() > 0 ) {
		delete_option( HJ_SC_ACTIVATION_PENDING );
		return true;
	}

	try {
		$found = hjsc_find_page_by_slug();
		if ( $found > 0 ) {
			update_option( HJ_SC_PAGE_OPTION, $found );
			delete_option( HJ_SC_ACTIVATION_PENDING );
			return true;
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

		if ( is_wp_error( $page_id ) ) {
			hjsc_record_error( 'wp_insert_post failed: ' . $page_id->get_error_message() );
			return false;
		}
		if ( ! $page_id ) {
			hjsc_record_error( 'wp_insert_post returned no id' );
			return false;
		}

		update_option( HJ_SC_PAGE_OPTION, (int) $page_id );
		delete_option( HJ_SC_ACTIVATION_PENDING );
		return true;
	} catch ( \Throwable $error ) {
		hjsc_record_error(
			$error->getMessage(),
			array(
				'file' => $error->getFile(),
				'line' => $error->getLine(),
			)
		);
		return false;
	}
}


/**
 * Activation does one cheap thing: it leaves a note that the page still has to
 * be created, and arms the fatal catcher.
 *
 * Building the page during activation is what makes plugins fail to activate on
 * some hosts (memory limits, blocking security plugins, timeouts). The page is
 * therefore created on the next admin request instead — which is the very next
 * page load after clicking "activate" — so a problem there can never roll the
 * activation back and can never look like "this plugin is broken".
 */
function hjsc_activate() {
	update_option( HJ_SC_ACTIVATION_PENDING, time() );
	update_option( 'hj_sc_activation_attempts', 0 );
	register_shutdown_function( 'hjsc_capture_activation_fatal' );
}

/**
 * Second chance on the next admin request, for hosts where the activation
 * request is killed early (memory limit, timeout, security plugin).
 */
function hjsc_maybe_finish_activation() {
	if ( ! get_option( HJ_SC_ACTIVATION_PENDING ) ) {
		return;
	}
	$attempts = (int) get_option( 'hj_sc_activation_attempts', 0 ) + 1;
	update_option( 'hj_sc_activation_attempts', $attempts );
	if ( $attempts > 3 ) {
		hjsc_record_error( 'page creation still failing after 3 attempts' );
		delete_option( HJ_SC_ACTIVATION_PENDING );
		return;
	}
	hjsc_try_create_page();
}
add_action( 'admin_init', 'hjsc_maybe_finish_activation' );




/**
 * Is the current front-end request the checker page?
 */
function hjsc_is_checker_page() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return false;
	}
	$page_id = hjsc_page_id();
	return $page_id > 0 && (int) get_queried_object_id() === $page_id;
}

/**
 * Use the plugin template for this page only.
 *
 * @param string $template Theme template chosen by WordPress.
 * @return string
 */
function hjsc_template_include( $template ) {
	if ( ! hjsc_is_checker_page() ) {
		return $template;
	}
	$own = HJ_SC_DIR . 'templates/checker-page.php';
	return is_readable( $own ) ? $own : $template;
}
add_filter( 'template_include', 'hjsc_template_include', 99 );

/**
 * Cache-busting version from the file timestamp, so a rebuilt package is never
 * served from a stale cache.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hjsc_asset_version( $relative ) {
	$path = HJ_SC_DIR . 'assets/' . ltrim( $relative, '/' );
	return is_readable( $path ) ? (string) filemtime( $path ) : HJ_SC_VERSION;
}

/**
 * URL of an asset inside the plugin.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hjsc_asset_url( $relative ) {
	return HJ_SC_URL . 'assets/' . ltrim( $relative, '/' );
}

/**
 * Configuration handed to the browser. Everything here is public data: the
 * draft bundle, the evidence map and the resolved article links.
 *
 * @return array<string,mixed>
 */
function hjsc_config() {
	return array(
		'pluginVersion' => HJ_SC_VERSION,
		'bundleVersion' => hjsc_bundle_version(),
		'bundleUrl'     => hjsc_asset_url( 'data/knowledge-bundle.v1.json' ),
		'evidenceUrl'   => hjsc_asset_url( 'engine/data/evidence-map.v1.json' ),
		'topicLinks'    => (object) hjsc_topic_links(),
	);
}

/**
 * JSON for the inline config script, hardened against `</script>` breakouts.
 */
function hjsc_config_json() {
	return (string) wp_json_encode(
		hjsc_config(),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
	);
}

/**
 * Enqueue the UI only on the checker page.
 */
function hjsc_enqueue_assets() {
	if ( ! hjsc_is_checker_page() ) {
		return;
	}

	wp_enqueue_style(
		'hj-sc-ui',
		hjsc_asset_url( 'ui/styles.css' ),
		array(),
		hjsc_asset_version( 'ui/styles.css' )
	);

	wp_enqueue_script(
		'hj-sc-app',
		hjsc_asset_url( 'ui/app.js' ),
		array(),
		hjsc_asset_version( 'ui/app.js' ),
		true
	);

	wp_add_inline_script( 'hj-sc-app', 'window.HJ_SC_CONFIG = ' . hjsc_config_json() . ';', 'before' );

	add_filter( 'script_loader_tag', 'hjsc_module_script_tag', 10, 3 );
}
add_action( 'wp_enqueue_scripts', 'hjsc_enqueue_assets' );

/**
 * The entry file is an ES module; WordPress does not add `type="module"` itself.
 *
 * @param string $tag    Script tag markup.
 * @param string $handle Script handle.
 * @param string $src    Script source URL.
 * @return string
 */
function hjsc_module_script_tag( $tag, $handle, $src ) {
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
function hjsc_robots( $robots ) {
	if ( ! hjsc_is_checker_page() ) {
		return $robots;
	}
	unset( $robots['index'], $robots['follow'] );
	$robots['noindex']  = true;
	$robots['nofollow'] = true;
	return $robots;
}
add_filter( 'wp_robots', 'hjsc_robots' );

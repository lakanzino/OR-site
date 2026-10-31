<?php
/**
 * Where the knowledge bundle comes from.
 *
 * The rule this file implements (runbook v2, phase 5-a): **content can ship
 * without shipping the plugin**. A validated bundle file is dropped into
 * `wp-content/uploads/hj-sc/`, the plugin serves it, and going back to the
 * previous content is one option change — no file edits, no new zip.
 *
 * The package copy stays the last resort. If the uploaded file disappears, is
 * unreadable, is not JSON, has the wrong schema version or has no questions,
 * the plugin silently falls back to the bundle inside the zip. A visitor never
 * sees an empty questionnaire because someone uploaded a bad file.
 *
 * What this file deliberately does not do: it does not build bundles, does not
 * validate content (the CLI and the engine do that), does not accept a URL, and
 * does not write anything except the option an administrator sets.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Folder inside `uploads/` that holds published bundles. */
const HJ_SC_UPLOAD_SUBDIR = 'hj-sc';

/** Option holding the file name of the active bundle. */
const HJ_SC_ACTIVE_BUNDLE_OPTION = 'hj_sc_active_bundle';

/** Schema versions this plugin can read (see `symptom-checker/engine/src/adapter.js`). */
const HJ_SC_READABLE_SCHEMAS = array( '1.0.0', '2.0.0' );

/**
 * File name pattern produced by `author.py publish-bundle`:
 * `knowledge-bundle.<bundle_version>.<sha8>.json`.
 *
 * The name is a contract, not decoration: the version says what content it is,
 * the hash says whether the bytes are the ones that were validated.
 *
 * @return string
 */
function hjsc_bundle_filename_regex() {
	return '/^knowledge-bundle\.[0-9A-Za-z._-]+\.[0-9a-f]{8}\.json$/';
}

/**
 * Absolute path of the uploads folder, with a trailing slash.
 *
 * @return string Empty string when WordPress cannot tell us (rare, but fatal
 *                for uploaded bundles — the caller then uses the package copy).
 */
function hjsc_upload_dir() {
	$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array();
	if ( empty( $uploads['basedir'] ) || ! empty( $uploads['error'] ) ) {
		return '';
	}
	return rtrim( (string) $uploads['basedir'], '/\\' ) . '/' . HJ_SC_UPLOAD_SUBDIR . '/';
}

/**
 * Public URL of the uploads folder, with a trailing slash.
 *
 * @return string
 */
function hjsc_upload_url() {
	$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array();
	if ( empty( $uploads['baseurl'] ) || ! empty( $uploads['error'] ) ) {
		return '';
	}
	return rtrim( (string) $uploads['baseurl'], '/\\' ) . '/' . HJ_SC_UPLOAD_SUBDIR . '/';
}

/**
 * The bundle that ships inside the plugin package.
 *
 * @return string
 */
function hjsc_package_bundle_path() {
	return HJ_SC_DIR . 'assets/data/knowledge-bundle.v3.json';
}

/**
 * Read a JSON file without WordPress helpers, so the same code runs in tests.
 *
 * @param string $path Absolute path.
 * @return array<string,mixed>|null
 */
function hjsc_read_json_file( $path ) {
	if ( ! is_readable( $path ) ) {
		return null;
	}
	$decoded = function_exists( 'wp_json_file_decode' )
		? wp_json_file_decode( $path, array( 'associative' => true ) )
		: json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, no remote request.
	return is_array( $decoded ) ? $decoded : null;
}

/**
 * Is this a bundle this plugin can actually run?
 *
 * Checks structure only — never clinical content. A bundle whose content is
 * wrong is a review problem; a bundle whose *shape* is wrong would break the
 * page, and that is what this gate is for.
 *
 * @param array<string,mixed>|null $bundle Decoded JSON.
 * @return bool
 */
function hjsc_bundle_is_readable( $bundle ) {
	if ( ! is_array( $bundle ) ) {
		return false;
	}
	$schema = isset( $bundle['schema_version'] ) ? (string) $bundle['schema_version'] : '';
	if ( ! in_array( $schema, HJ_SC_READABLE_SCHEMAS, true ) ) {
		return false;
	}
	if ( empty( $bundle['questions'] ) || ! is_array( $bundle['questions'] ) ) {
		return false;
	}
	return true;
}

/**
 * Describe one candidate bundle file.
 *
 * @param string $path Absolute path.
 * @return array<string,mixed>|null Null when the file cannot be used.
 */
function hjsc_bundle_file_info( $path ) {
	$bundle = hjsc_read_json_file( $path );
	if ( ! hjsc_bundle_is_readable( $bundle ) ) {
		return null;
	}
	$name = basename( $path );
	$hash = '';
	if ( preg_match( '/\.([0-9a-f]{8})\.json$/', $name, $matches ) ) {
		$hash = $matches[1];
	}
	return array(
		'path'          => $path,
		'name'          => $name,
		'schema_version' => (string) $bundle['schema_version'],
		'bundle_version' => isset( $bundle['bundle_version'] ) ? (string) $bundle['bundle_version'] : '',
		'bundle_id'     => isset( $bundle['bundle_id'] ) ? (string) $bundle['bundle_id'] : '',
		'sha8'          => $hash,
	);
}

/**
 * Newest readable bundle in the uploads folder.
 *
 * "Newest" is by `bundle_version`, then by the hash in the name, so the answer
 * never depends on directory order or file timestamps.
 *
 * @return array<string,mixed>|null
 */
function hjsc_newest_uploaded_bundle() {
	$dir = hjsc_upload_dir();
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return null;
	}
	$candidates = glob( $dir . 'knowledge-bundle.*.json' );
	if ( ! is_array( $candidates ) ) {
		return null;
	}
	$found = array();
	foreach ( $candidates as $path ) {
		if ( ! preg_match( hjsc_bundle_filename_regex(), basename( $path ) ) ) {
			continue;
		}
		$info = hjsc_bundle_file_info( $path );
		if ( null !== $info ) {
			$found[] = $info;
		}
	}
	if ( empty( $found ) ) {
		return null;
	}
	usort(
		$found,
		static function ( $left, $right ) {
			$compare = version_compare( $right['bundle_version'], $left['bundle_version'] );
			return 0 !== $compare ? $compare : strcmp( $right['sha8'], $left['sha8'] );
		}
	);
	return $found[0];
}

/**
 * The bundle the page should use, and where it came from.
 *
 * Order: the active option (if it points at a usable file) → the newest usable
 * uploaded bundle (unless auto-detection is filtered off) → the package copy.
 *
 * @return array<string,mixed> Keys: path, url, source, name, sha8, bundle_version, schema_version.
 */
function hjsc_bundle_source() {
	static $source = null;
	if ( null !== $source ) {
		return $source;
	}

	$dir = hjsc_upload_dir();
	$url = hjsc_upload_url();

	$active = (string) get_option( HJ_SC_ACTIVE_BUNDLE_OPTION, '' );
	$active = (string) apply_filters( 'hjsc_active_bundle', $active );
	if ( '' !== $active && '' !== $dir && preg_match( hjsc_bundle_filename_regex(), $active ) ) {
		$info = hjsc_bundle_file_info( $dir . $active );
		if ( null !== $info ) {
			$source = $info + array(
				'url'    => $url . $info['name'],
				'source' => 'upload',
			);
			return $source;
		}
	}

	$autodetect = (bool) apply_filters( 'hjsc_bundle_autodetect', true );
	if ( $autodetect ) {
		$info = hjsc_newest_uploaded_bundle();
		if ( null !== $info ) {
			$source = $info + array(
				'url'    => $url . $info['name'],
				'source' => 'upload',
			);
			return $source;
		}
	}

	$package = hjsc_bundle_file_info( hjsc_package_bundle_path() );
	$source  = array(
		'path'           => hjsc_package_bundle_path(),
		'name'           => 'knowledge-bundle.v3.json',
		'url'            => HJ_SC_URL . 'assets/data/knowledge-bundle.v3.json',
		'source'         => 'package',
		'sha8'           => null === $package ? '' : $package['sha8'],
		'bundle_version' => null === $package ? '' : $package['bundle_version'],
		'schema_version' => null === $package ? '' : $package['schema_version'],
	);
	return $source;
}

/**
 * Bundle URL with cache-busting for uploaded files.
 *
 * The package copy keeps its historical URL (the plugin's own file mtime is
 * already in the asset version), while an uploaded file carries the hash from
 * its name, so replacing a bundle never serves a stale copy from LiteSpeed or
 * the browser cache.
 *
 * @return string
 */
function hjsc_bundle_url() {
	$source = hjsc_bundle_source();
	if ( 'upload' === $source['source'] && '' !== $source['sha8'] ) {
		$separator = false === strpos( $source['url'], '?' ) ? '?' : '&';
		return $source['url'] . $separator . 'ver=' . rawurlencode( $source['sha8'] );
	}
	return $source['url'];
}

/**
 * Small, non-secret description of the active bundle for the page config.
 *
 * @return array<string,mixed>
 */
function hjsc_bundle_status() {
	$source = hjsc_bundle_source();
	return array(
		'source'  => $source['source'],
		'name'    => $source['name'],
		'sha8'    => $source['sha8'],
		'schema'  => $source['schema_version'],
		'version' => $source['bundle_version'],
	);
}

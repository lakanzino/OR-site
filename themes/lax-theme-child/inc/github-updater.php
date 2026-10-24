<?php
/**
 * GitHub updater — dashboard connection, version display and file-level updates.
 *
 * Adds a dashboard page ("بروزرسانی گیت‌هاب") where the administrator can:
 *  - store a GitHub access token (or read it from the `GITHUB_ACCESS_TOKEN`
 *    constant in wp-config.php),
 *  - connect to the project repository and show the latest published release,
 *  - display installed parent/child theme versions next to the released ones,
 *  - preview which theme files changed (dry-run diff),
 *  - update the parent and/or child theme with one button — copying ONLY the
 *    files that actually differ, so each release replaces just its changes.
 *
 * Release contract for repository `lakanzino/OR-site`:
 *  - Release tag: `child-vX.Y.Z`
 *  - Assets: `lax-theme.zip`, `lax-theme-child.zip`, `orsite-release.json`
 *  - `orsite-release.json` declares parent/child versions and changelog
 *    bullets; the tag is the fallback for the child version.
 *
 * Security: fixed repository and asset allow-list, nonces and `update_themes`
 * capability on every action, the token is never printed in full and is never
 * written into logs, ZIP extraction rejects path traversal, and files are
 * copied through a temporary file + rename so a crash cannot half-write them.
 *
 * @package Lax_Theme_Child
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ORSITE_GH_REPO' ) ) {
	define( 'ORSITE_GH_REPO', 'lakanzino/OR-site' );
}
if ( ! defined( 'ORSITE_GH_API' ) ) {
	define( 'ORSITE_GH_API', 'https://api.github.com/repos/lakanzino/OR-site' );
}
if ( ! defined( 'ORSITE_GH_WEB' ) ) {
	define( 'ORSITE_GH_WEB', 'https://github.com/lakanzino/OR-site' );
}
if ( ! defined( 'ORSITE_GH_CHILD_ASSET' ) ) {
	define( 'ORSITE_GH_CHILD_ASSET', 'lax-theme-child.zip' );
}
if ( ! defined( 'ORSITE_GH_PARENT_ASSET' ) ) {
	define( 'ORSITE_GH_PARENT_ASSET', 'lax-theme.zip' );
}
if ( ! defined( 'ORSITE_GH_MANIFEST_ASSET' ) ) {
	define( 'ORSITE_GH_MANIFEST_ASSET', 'orsite-release.json' );
}
if ( ! defined( 'ORSITE_GH_BRANCH' ) ) {
	define( 'ORSITE_GH_BRANCH', 'main' );
}
if ( ! defined( 'ORSITE_GH_CHECK_INTERVAL' ) ) {
	define( 'ORSITE_GH_CHECK_INTERVAL', 3 * HOUR_IN_SECONDS );
}
if ( ! defined( 'ORSITE_GH_CHECK_RETRY' ) ) {
	define( 'ORSITE_GH_CHECK_RETRY', 15 * MINUTE_IN_SECONDS );
}

/* -------------------------------------------------------------------------
 * Token handling
 * ---------------------------------------------------------------------- */

/**
 * Resolve the GitHub token: wp-config.php constant wins, then the saved option.
 *
 * @return string Token or empty string.
 */
function orsite_gh_token() {
	if ( defined( 'GITHUB_ACCESS_TOKEN' ) && GITHUB_ACCESS_TOKEN ) {
		return (string) GITHUB_ACCESS_TOKEN;
	}
	return (string) get_option( 'orsite_gh_token', '' );
}

/**
 * Is the token provided through wp-config.php (so the option is read-only)?
 *
 * @return bool
 */
function orsite_gh_token_is_constant() {
	return defined( 'GITHUB_ACCESS_TOKEN' ) && (bool) GITHUB_ACCESS_TOKEN;
}

/**
 * Mask a token for display: first 4 and last 4 characters only.
 *
 * @param string $token Token.
 * @return string Masked token.
 */
function orsite_gh_mask_token( $token ) {
	$token = (string) $token;
	$len   = strlen( $token );
	if ( $len < 12 ) {
		return str_repeat( '•', 8 );
	}
	return substr( $token, 0, 4 ) . str_repeat( '•', 8 ) . substr( $token, -4 );
}

/* -------------------------------------------------------------------------
 * Low-level GitHub HTTP
 * ---------------------------------------------------------------------- */

/**
 * GET a URL with project headers and return body + code.
 *
 * @param string $url      Absolute URL.
 * @param bool   $is_api   Add GitHub API headers (Accept + token).
 * @param int    $timeout  Seconds.
 * @return array|WP_Error { body, code, headers }
 */
function orsite_gh_http( $url, $is_api = true, $timeout = 30 ) {
	$headers = array(
		'Accept'     => $is_api ? 'application/vnd.github+json' : '*/*',
		'User-Agent' => 'hamyarjarahi-theme-updater',
	);
	$token   = orsite_gh_token();
	if ( $is_api && $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'headers'     => $headers,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return array(
		'body'    => wp_remote_retrieve_body( $response ),
		'code'    => (int) wp_remote_retrieve_response_code( $response ),
		'headers' => wp_remote_retrieve_headers( $response ),
	);
}

/**
 * Fetch the release list and pick the newest release that carries our assets.
 *
 * Result is cached for one hour unless $force is true.
 *
 * @param bool $force Bypass cache.
 * @return array|WP_Error Normalized release: tag_name, name, published_at,
 *                        html_url, body, assets[] {name, browser_download_url, url, size}.
 */
function orsite_gh_release( $force = false ) {
	$cache = get_option( 'orsite_gh_release_cache' );
	if ( ! $force && is_array( $cache ) && isset( $cache['time'], $cache['data'] ) && ( time() - (int) $cache['time'] ) < HOUR_IN_SECONDS ) {
		return $cache['data'];
	}

	$res = orsite_gh_http( ORSITE_GH_API . '/releases?per_page=10' );
	if ( is_wp_error( $res ) ) {
		return is_array( $cache ) && isset( $cache['data'] ) ? $cache['data'] : $res;
	}
	if ( 200 !== $res['code'] ) {
		$hint = ( 403 === $res['code'] || 429 === $res['code'] )
			? __( 'نرخ درخواست گیت‌هاب پر شده است؛ یک توکن دسترسی ذخیره کنید و دوباره تلاش کنید.', 'lax-theme-child' )
			: __( 'پاسخ نامعتبر از API گیت‌هاب دریافت شد.', 'lax-theme-child' );
		$fallback = is_array( $cache ) && isset( $cache['data'] ) ? $cache['data'] : null;
		return $fallback ? $fallback : new WP_Error( 'orsite_gh_http_' . $res['code'], $hint );
	}

	$list = json_decode( $res['body'], true );
	if ( ! is_array( $list ) ) {
		return is_array( $cache ) && isset( $cache['data'] ) ? $cache['data'] : new WP_Error( 'orsite_gh_json', __( 'خروجی گیت‌هاب قابل خواندن نبود.', 'lax-theme-child' ) );
	}

	$want    = array( ORSITE_GH_CHILD_ASSET, ORSITE_GH_PARENT_ASSET );
	$release = null;
	foreach ( $list as $item ) {
		if ( ! empty( $item['draft'] ) || ! empty( $item['prerelease'] ) ) {
			continue;
		}
		if ( ! empty( $item['assets'] ) && is_array( $item['assets'] ) ) {
			foreach ( $item['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && in_array( $asset['name'], $want, true ) ) {
					$release = $item;
					break 2;
				}
			}
		}
		$candidate_tag = isset( $item['tag_name'] ) ? (string) $item['tag_name'] : '';
		if ( null !== orsite_gh_parse_tag_version( $candidate_tag, 'child-v' ) || null !== orsite_gh_parse_tag_version( $candidate_tag, 'parent-v' ) ) {
			$release = $item;
			break;
		}
	}

	if ( ! $release ) {
		$err = new WP_Error( 'orsite_gh_no_release', __( 'هیچ انتشاری با فایل‌های قالب در مخزن پیدا نشد.', 'lax-theme-child' ) );
		return is_array( $cache ) && isset( $cache['data'] ) ? $cache['data'] : $err;
	}

	$tag_name   = isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '';
	$normalized = array(
		'tag_name'     => $tag_name,
		'name'         => isset( $release['name'] ) ? (string) $release['name'] : '',
		'published_at' => isset( $release['published_at'] ) ? (string) $release['published_at'] : '',
		'html_url'     => isset( $release['html_url'] ) ? (string) $release['html_url'] : ORSITE_GH_WEB . '/releases',
		'body'         => isset( $release['body'] ) ? (string) $release['body'] : '',
		'assets'       => array(),
	);
	$have_names = array();
	if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			$aname                     = (string) $asset['name'];
			$have_names[ $aname ]      = true;
			$normalized['assets'][] = array(
				'name'                 => $aname,
				'browser_download_url' => isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '',
				'url'                  => isset( $asset['url'] ) ? (string) $asset['url'] : '',
				'accept'               => 'application/octet-stream',
				'size'                 => isset( $asset['size'] ) ? (int) $asset['size'] : 0,
			);
		}
	}
	if ( '' !== $tag_name ) {
		foreach ( array( ORSITE_GH_CHILD_ASSET, ORSITE_GH_PARENT_ASSET, ORSITE_GH_MANIFEST_ASSET ) as $expected_name ) {
			if ( isset( $have_names[ $expected_name ] ) ) {
				continue;
			}
			$normalized['assets'][] = array(
				'name'                 => $expected_name,
				'browser_download_url' => ORSITE_GH_WEB . '/raw/' . rawurlencode( $tag_name ) . '/releases/' . rawurlencode( $expected_name ),
				'url'                  => ORSITE_GH_API . '/contents/releases/' . rawurlencode( $expected_name ) . '?ref=' . rawurlencode( $tag_name ),
				'accept'               => 'application/vnd.github.raw+json',
				'size'                 => 0,
			);
		}
	}

	update_option( 'orsite_gh_release_cache', array( 'time' => time(), 'data' => $normalized ), false );
	return $normalized;
}

/**
 * Find one asset inside a normalized release.
 *
 * @param array  $release Release array.
 * @param string $name    Asset file name.
 * @return array|null
 */
function orsite_gh_find_asset( $release, $name ) {
	foreach ( $release['assets'] as $asset ) {
		if ( $asset['name'] === $name ) {
			return $asset;
		}
	}
	return null;
}

/**
 * Download a release asset to a temporary file.
 *
 * Public assets download from `browser_download_url` first (no token needed);
 * if that fails and a token exists, the API asset endpoint is used instead.
 *
 * @param array $asset Asset array from orsite_gh_find_asset().
 * @return string|WP_Error Path to the downloaded temp file.
 */
function orsite_gh_download_asset( $asset ) {
	$body = null;

	if ( ! empty( $asset['browser_download_url'] ) ) {
		$res = orsite_gh_http( $asset['browser_download_url'], false, 120 );
		if ( ! is_wp_error( $res ) && 200 === $res['code'] && '' !== $res['body'] ) {
			$body = $res['body'];
		}
	}

	if ( null === $body && ! empty( $asset['url'] ) ) {
		// Fall back to the API asset / contents endpoint.
		$accept  = ! empty( $asset['accept'] ) ? (string) $asset['accept'] : 'application/octet-stream';
		$headers = array(
			'Accept'     => $accept,
			'User-Agent' => 'hamyarjarahi-theme-updater',
		);
		if ( orsite_gh_token() ) {
			$headers['Authorization'] = 'Bearer ' . orsite_gh_token();
		}
		$response = wp_remote_get(
			$asset['url'],
			array(
				'timeout' => 120,
				'headers' => $headers,
			)
		);
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
		}
	}

	if ( null === $body || '' === $body ) {
		return new WP_Error( 'orsite_gh_download', sprintf(
			/* translators: %s: asset file name */
			__( 'دانلود فایل %s از گیت‌هاب ناموفق بود.', 'lax-theme-child' ),
			$asset['name']
		) );
	}

	$tmp = tempnam( get_temp_dir(), 'orsite-dl-' );
	if ( false === $tmp ) {
		return new WP_Error( 'orsite_gh_tmp', __( 'ساخت فایل موقت روی سرور ممکن نشد.', 'lax-theme-child' ) );
	}
	if ( false === file_put_contents( $tmp, $body ) ) {
		return new WP_Error( 'orsite_gh_write', __( 'نوشتن فایل موقت روی سرور ممکن نشد.', 'lax-theme-child' ) );
	}
	return $tmp;
}

/**
 * Parse "child-v1.2.3" style tags into a bare version string.
 *
 * @param string $tag    Tag name.
 * @param string $prefix Prefix such as `child-v` or `parent-v`.
 * @return string|null
 */
function orsite_gh_parse_tag_version( $tag, $prefix = 'child-v' ) {
	$tag = (string) $tag;
	if ( 0 !== strpos( $tag, $prefix ) ) {
		return null;
	}
	$version = substr( $tag, strlen( $prefix ) );
	return preg_match( '/^\d+(\.\d+)*$/', $version ) ? $version : null;
}

/**
 * Remote versions from the release manifest, falling back to tag parsing.
 *
 * The manifest download is cached for 30 minutes per release tag so the
 * dashboard widget and the native update list never re-download it.
 *
 * @param array $release      Normalized release.
 * @param bool  $bypass_cache Ignore the cached manifest.
 * @return array { child: ?string, parent: ?string, changelog: array, source: string }
 */
function orsite_gh_remote_versions( $release, $bypass_cache = false ) {
	$tag = isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '';
	$key = 'manifest_' . md5( $tag );

	$out = array(
		'child'     => orsite_gh_parse_tag_version( $tag, 'child-v' ),
		'parent'    => orsite_gh_parse_tag_version( $tag, 'parent-v' ),
		'changelog' => array(),
		'source'    => 'tag',
	);

	$cache = get_option( 'orsite_gh_versions_cache', array() );
	if ( ! $bypass_cache && '' !== $key && isset( $cache[ $key ]['time'], $cache[ $key ]['data'] ) && ( time() - (int) $cache[ $key ]['time'] ) < ( 30 * MINUTE_IN_SECONDS ) ) {
		return (array) $cache[ $key ]['data'];
	}

	$asset = orsite_gh_find_asset( $release, ORSITE_GH_MANIFEST_ASSET );
	if ( $asset ) {
		$file = orsite_gh_download_asset( $asset );
		if ( ! is_wp_error( $file ) ) {
			$json = json_decode( (string) file_get_contents( $file ), true );
			@unlink( $file );
			if ( is_array( $json ) ) {
				if ( isset( $json['child']['version'] ) && '' !== (string) $json['child']['version'] ) {
					$out['child'] = (string) $json['child']['version'];
				}
				if ( isset( $json['parent']['version'] ) && '' !== (string) $json['parent']['version'] ) {
					$out['parent'] = (string) $json['parent']['version'];
				}
				if ( isset( $json['changelog'] ) && is_array( $json['changelog'] ) ) {
					$out['changelog'] = $json['changelog'];
				}
				$out['source'] = 'manifest';
			}
		}
	}

	if ( '' !== $key ) {
		$cache            = is_array( $cache ) ? $cache : array();
		$cache[ $key ]    = array( 'time' => time(), 'data' => $out );
		update_option( 'orsite_gh_versions_cache', $cache, false );
	}

	return $out;
}

/**
 * Installed version of the repository branch for both themes.
 *
 * Reading `style.css` from `main` is what makes a plain commit (without a new
 * GitHub release/tag) visible in the dashboard as a pending update.
 *
 * @param bool $bypass_cache Ignore the 30-minute cache.
 * @return array { child: ?string, parent: ?string, sha: string, time: int, error: string }
 */
function orsite_gh_branch_versions( $bypass_cache = false ) {
	$empty = array( 'child' => null, 'parent' => null, 'sha' => '', 'time' => 0, 'error' => '' );
	$cache = get_option( 'orsite_gh_branch_cache' );

	if ( ! $bypass_cache && is_array( $cache ) && isset( $cache['time'] ) && ( time() - (int) $cache['time'] ) < ( 30 * MINUTE_IN_SECONDS ) ) {
		return $cache;
	}

	$out = $empty;
	$out['time'] = time();

	foreach ( array( 'child' => 'lax-theme-child', 'parent' => 'lax-theme' ) as $which => $slug ) {
		$url = ORSITE_GH_API . '/contents/themes/' . rawurlencode( $slug ) . '/style.css?ref=' . rawurlencode( ORSITE_GH_BRANCH );
		$res = orsite_gh_http( $url );
		if ( is_wp_error( $res ) ) {
			$out['error'] = $res->get_error_message();
			continue;
		}
		if ( 200 !== $res['code'] ) {
			$out['error'] = sprintf(
				/* translators: %d: HTTP status code */
				__( 'خواندن style.css از شاخه‌ی %1$s ناموفق بود (کد %2$d).', 'lax-theme-child' ),
				ORSITE_GH_BRANCH,
				(int) $res['code']
			);
			continue;
		}

		$json = json_decode( $res['body'], true );
		$css  = '';
		if ( is_array( $json ) ) {
			if ( ! empty( $json['content'] ) ) {
				$css = (string) base64_decode( preg_replace( '/\s+/', '', (string) $json['content'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- GitHub contents API payload.
			}
			if ( ! empty( $json['sha'] ) && 'child' === $which ) {
				$out['sha'] = substr( (string) $json['sha'], 0, 10 );
			}
		}

		if ( '' !== $css && preg_match( '/^\s*\*?\s*Version:\s*(.+)$/mi', $css, $m ) ) {
			$out[ $which ] = trim( $m[1] );
		}
	}

	if ( '' === $out['error'] || $out['child'] || $out['parent'] ) {
		update_option( 'orsite_gh_branch_cache', $out, false );
	}

	return $out;
}

/**
 * Synthetic package source for the repository branch.
 *
 * The GitHub branch ZIP (`codeload`) always exists even when no release was
 * published, so a plain push can still be applied with the file-level updater.
 * Each asset carries `kind=zipball` and the theme `subdir` inside the archive.
 *
 * @return array Normalized release-shaped array.
 */
function orsite_gh_branch_source() {
	$versions = orsite_gh_branch_versions();
	$zip      = 'https://codeload.github.com/' . ORSITE_GH_REPO . '/zip/refs/heads/' . ORSITE_GH_BRANCH;
	$tag      = ORSITE_GH_BRANCH . ( ! empty( $versions['sha'] ) ? ' @ ' . $versions['sha'] : '' );

	return array(
		'tag_name'     => 'main',
		'name'         => __( 'آخرین کد شاخه‌ی main در گیت‌هاب', 'lax-theme-child' ),
		'published_at' => '',
		'html_url'     => ORSITE_GH_WEB . '/commits/' . ORSITE_GH_BRANCH,
		'body'         => '',
		'branch'       => true,
		'branch_label' => $tag,
		'assets'       => array(
			array(
				'name'                 => ORSITE_GH_CHILD_ASSET,
				'browser_download_url' => $zip,
				'url'                  => ORSITE_GH_API . '/zipball/' . ORSITE_GH_BRANCH,
				'accept'               => 'application/vnd.github+json',
				'size'                 => 0,
				'kind'                 => 'zipball',
				'subdir'               => 'themes/lax-theme-child',
			),
			array(
				'name'                 => ORSITE_GH_PARENT_ASSET,
				'browser_download_url' => $zip,
				'url'                  => ORSITE_GH_API . '/zipball/' . ORSITE_GH_BRANCH,
				'accept'               => 'application/vnd.github+json',
				'size'                 => 0,
				'kind'                 => 'zipball',
				'subdir'               => 'themes/lax-theme',
			),
		),
	);
}

/**
 * Pick the freshest source for one theme: the published release or, when the
 * branch carries a higher version, the branch ZIP.
 *
 * @param string $which `child` or `parent`.
 * @param bool   $force Bypass the release cache.
 * @return array|WP_Error Normalized release-shaped array.
 */
function orsite_gh_package_source( $which = 'child', $force = false ) {
	$release = orsite_gh_release( $force );
	$branch  = orsite_gh_branch_versions( $force );
	$branch_v = isset( $branch[ $which ] ) ? $branch[ $which ] : null;

	if ( is_wp_error( $release ) ) {
		if ( $branch_v ) {
			return orsite_gh_branch_source();
		}
		return $release;
	}

	$versions   = orsite_gh_remote_versions( $release );
	$release_v  = isset( $versions[ $which ] ) ? $versions[ $which ] : null;
	$local      = orsite_gh_theme_info( $which );
	$local_v    = $local['version'];

	$branch_newer = $branch_v
		&& ( ! $release_v || version_compare( $branch_v, $release_v, '>' ) )
		&& version_compare( $branch_v, $local_v, '>' );

	return $branch_newer ? orsite_gh_branch_source() : $release;
}


/* -------------------------------------------------------------------------
 * Local theme info
 * ---------------------------------------------------------------------- */

/**
 * Installed theme information for the updater tables.
 *
 * @param string $which `child` or `parent`.
 * @return array { key, name, version, dir, asset }
 */
function orsite_gh_theme_info( $which ) {
	if ( 'parent' === $which ) {
		$theme = wp_get_theme( get_template() );
		$dir   = get_template_directory();
		$asset = ORSITE_GH_PARENT_ASSET;
	} else {
		$theme = wp_get_theme( get_stylesheet() );
		$dir   = get_stylesheet_directory();
		$asset = ORSITE_GH_CHILD_ASSET;
	}

	return array(
		'key'     => $which,
		'name'    => $theme->get( 'Name' ),
		'version' => (string) $theme->get( 'Version' ),
		'dir'     => $dir,
		'asset'   => $asset,
	);
}

/* -------------------------------------------------------------------------
 * Sync engine: extract, diff, apply (file-level updates)
 * ---------------------------------------------------------------------- */

/**
 * Recursively delete a directory (temp cleanup only).
 *
 * @param string $dir Directory path.
 */
function orsite_updater_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			orsite_updater_rrmdir( $path );
		} else {
			@unlink( $path );
		}
	}
	@rmdir( $dir );
}

/**
 * Should this file be ignored by the sync engine?
 *
 * @param string $rel Relative path.
 * @return bool
 */
function orsite_updater_skip_file( $rel ) {
	$rel  = str_replace( '\\', '/', (string) $rel );
	$base = basename( $rel );
	if ( '' === $base ) {
		return true;
	}
	if ( '.' === substr( $base, 0, 1 ) ) {
		return true;
	}
	if ( in_array( $base, array( 'Thumbs.db', 'desktop.ini' ), true ) ) {
		return true;
	}
	if ( preg_match( '/\.zip$/i', $base ) ) {
		return true;
	}
	foreach ( explode( '/', $rel ) as $segment ) {
		if ( '.' === substr( $segment, 0, 1 ) ) {
			return true;
		}
	}
	return false;
}

/**
 * List files under a directory tree as relative => absolute (junk skipped).
 *
 * @param string $dir Absolute directory.
 * @return array<string,string>
 */
function orsite_updater_list_files( $dir ) {
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	$base_len = strlen( rtrim( str_replace( '\\', '/', $dir ), '/' ) ) + 1;
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$abs = str_replace( '\\', '/', $file->getPathname() );
		$rel = substr( $abs, $base_len );
		if ( orsite_updater_skip_file( $rel ) ) {
			continue;
		}
		$out[ $rel ] = $abs;
	}
	return $out;
}

/**
 * Extract a theme ZIP into a temp directory and return its theme root.
 *
 * Rejects path traversal entries and packages without a `style.css`.
 *
 * @param string $zip_path Path to the ZIP file.
 * @param string $subdir   Optional theme folder inside the archive (used for
 *                         GitHub branch ZIPs: `themes/lax-theme-child`).
 * @return array|WP_Error { root, tmpdir }
 */
function orsite_updater_extract( $zip_path, $subdir = '' ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'orsite_no_zip', __( 'افزونه‌ی zip روی هاست فعال نیست؛ با پشتیبانی هاست تماس بگیرید.', 'lax-theme-child' ) );
	}

	$tmpdir = get_temp_dir() . 'orsite-x-' . wp_generate_password( 8, false, false );
	if ( ! wp_mkdir_p( $tmpdir ) ) {
		return new WP_Error( 'orsite_mkdir', __( 'ساخت پوشه‌ی موقت روی سرور ممکن نشد.', 'lax-theme-child' ) );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		orsite_updater_rrmdir( $tmpdir );
		return new WP_Error( 'orsite_zip_open', __( 'باز کردن فایل ZIP بروزرسانی ناموفق بود.', 'lax-theme-child' ) );
	}

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = (string) $zip->getNameIndex( $i );
		$name = str_replace( '\\', '/', $name );
		if ( '' === $name || '/' === substr( $name, -1 ) ) {
			continue;
		}
		if ( false !== strpos( $name, '..' ) || false !== strpos( $name, "\0" ) || preg_match( '#^(/|[a-zA-Z]:)#', $name ) ) {
			$zip->close();
			orsite_updater_rrmdir( $tmpdir );
			return new WP_Error( 'orsite_zip_slip', __( 'فایل ZIP مسیر نامعتبر دارد و رد شد.', 'lax-theme-child' ) );
		}
	}

	if ( ! $zip->extractTo( $tmpdir ) ) {
		$zip->close();
		orsite_updater_rrmdir( $tmpdir );
		return new WP_Error( 'orsite_zip_extract', __( 'استخراج فایل ZIP ناموفق بود.', 'lax-theme-child' ) );
	}
	$zip->close();

	// Locate the theme root: either the single top folder or the tmp dir itself.
	$entries = array_values( array_diff( (array) scandir( $tmpdir ), array( '.', '..' ) ) );
	$root    = $tmpdir;
	if ( 1 === count( $entries ) && is_dir( $tmpdir . '/' . $entries[0] ) ) {
		$root = $tmpdir . '/' . $entries[0];
	}

	// GitHub branch ZIPs keep the theme below `themes/<slug>/`.
	$subdir = trim( str_replace( '\\', '/', (string) $subdir ), '/' );
	if ( '' !== $subdir && ! file_exists( $root . '/style.css' ) ) {
		$candidate = $root . '/' . $subdir;
		if ( file_exists( $candidate . '/style.css' ) ) {
			$root = $candidate;
		}
	}

	if ( ! file_exists( $root . '/style.css' ) ) {
		orsite_updater_rrmdir( $tmpdir );
		return new WP_Error( 'orsite_zip_theme', __( 'فایل ZIP ساختار قالب وردپرس را ندارد (style.css پیدا نشد).', 'lax-theme-child' ) );
	}

	return array(
		'root'   => $root,
		'tmpdir' => $tmpdir,
	);
}

/**
 * Compare a package root with the installed theme directory.
 *
 * The plan copies only `changed` and `new` files. `stale` files (present in
 * the install but gone from the package) are reported but never deleted
 * automatically, so user-added files are safe.
 *
 * @param string $src_root  Extracted package root.
 * @param string $dest_dir  Installed theme directory.
 * @return array { copy: array<rel, array>, changed: string[], new: string[],
 *                unchanged: string[], stale: string[] }
 */
function orsite_updater_diff( $src_root, $dest_dir ) {
	$src = orsite_updater_list_files( $src_root );
	$dst = orsite_updater_list_files( $dest_dir );

	$plan = array(
		'copy'      => array(),
		'changed'   => array(),
		'new'       => array(),
		'unchanged' => array(),
		'stale'     => array(),
	);

	foreach ( $src as $rel => $abs ) {
		$dest_path = rtrim( str_replace( '\\', '/', $dest_dir ), '/' ) . '/' . $rel;
		if ( ! isset( $dst[ $rel ] ) ) {
			$plan['new'][] = $rel;
			$plan['copy'][ $rel ] = array( 'src' => $abs, 'dst' => $dest_path );
			continue;
		}
		$src_hash = hash_file( 'sha256', $abs );
		$dst_hash = hash_file( 'sha256', $dst[ $rel ] );
		if ( $src_hash === $dst_hash ) {
			$plan['unchanged'][] = $rel;
		} else {
			$plan['changed'][] = $rel;
			$plan['copy'][ $rel ] = array( 'src' => $abs, 'dst' => $dest_path );
		}
	}

	foreach ( $dst as $rel => $abs ) {
		if ( ! isset( $src[ $rel ] ) ) {
			$plan['stale'][] = $rel;
		}
	}

	return $plan;
}

/**
 * Apply a diff plan: copy changed/new files atomically (tmp + rename).
 *
 * @param array $plan Plan from orsite_updater_diff().
 * @return array { updated: string[], failed: string[] }
 */
function orsite_updater_apply( $plan ) {
	$result = array(
		'updated' => array(),
		'failed'  => array(),
	);

	foreach ( $plan['copy'] as $rel => $info ) {
		$dst_dir = dirname( $info['dst'] );
		if ( ! is_dir( $dst_dir ) && ! wp_mkdir_p( $dst_dir ) ) {
			$result['failed'][] = $rel;
			continue;
		}
		$tmp = $dst_dir . '/.' . basename( $info['dst'] ) . '.orsite-tmp';
		if ( ! @copy( $info['src'], $tmp ) ) {
			$result['failed'][] = $rel;
			continue;
		}
		if ( ! @rename( $tmp, $info['dst'] ) ) {
			@unlink( $tmp );
			$result['failed'][] = $rel;
			continue;
		}
		$result['updated'][] = $rel;
	}

	return $result;
}

/**
 * Run a full dry-run for one theme: download, extract, diff. No writes to the
 * installed theme.
 *
 * @param string $which `child` or `parent`.
 * @param array  $release Normalized release.
 * @return array|WP_Error { theme, release_tag, plan, tmpdir }
 */
function orsite_gh_plan_update( $which, $release ) {
	$theme  = orsite_gh_theme_info( $which );
	$asset  = orsite_gh_find_asset( $release, $theme['asset'] );
	if ( ! $asset ) {
		return new WP_Error( 'orsite_asset_missing', sprintf(
			/* translators: %s: asset file name */
			__( 'فایل %s در آخرین انتشار گیت‌هاب پیدا نشد.', 'lax-theme-child' ),
			$theme['asset']
		) );
	}

	$zip = orsite_gh_download_asset( $asset );
	if ( is_wp_error( $zip ) ) {
		return $zip;
	}

	$subdir  = ! empty( $asset['subdir'] ) ? (string) $asset['subdir'] : '';
	$extract = orsite_updater_extract( $zip, $subdir );
	@unlink( $zip );
	if ( is_wp_error( $extract ) ) {
		return $extract;
	}

	$plan = orsite_updater_diff( $extract['root'], $theme['dir'] );

	return array(
		'theme'      => $theme,
		'release_tag' => $release['tag_name'],
		'plan'       => $plan,
		'tmpdir'     => $extract['tmpdir'],
		'root'       => $extract['root'],
	);
}

/**
 * Update one theme in place: dry-run, then copy only changed/new files.
 *
 * @param string $which `child` or `parent`.
 * @return array|WP_Error Result summary.
 */
function orsite_gh_update_theme( $which ) {
	$release = orsite_gh_package_source( $which, true );
	if ( is_wp_error( $release ) ) {
		return $release;
	}

	$plan = orsite_gh_plan_update( $which, $release );
	if ( is_wp_error( $plan ) ) {
		return $plan;
	}

	$apply = orsite_updater_apply( $plan['plan'] );
	orsite_updater_rrmdir( $plan['tmpdir'] );

	if ( $apply['failed'] ) {
		return new WP_Error( 'orsite_apply_failed', sprintf(
			/* translators: 1: failed count, 2: file list */
			__( 'بروزرسانی ناتمام ماند؛ %1$d فایل کپی نشد: %2$s', 'lax-theme-child' ),
			count( $apply['failed'] ),
			implode( '، ', array_slice( $apply['failed'], 0, 10 ) )
		) );
	}

	$summary = array(
		'which'       => $which,
		'theme'       => $plan['theme'],
		'release_tag' => $plan['release_tag'],
		'updated'     => $apply['updated'],
		'unchanged'   => $plan['plan']['unchanged'],
		'stale'       => $plan['plan']['stale'],
		'time'        => time(),
	);

	$log            = (array) get_option( 'orsite_gh_last_update', array() );
	$log[ $which ]  = $summary;
	update_option( 'orsite_gh_last_update', $log, false );

	orsite_gh_sync_snapshot_after_update( $which );

	return $summary;
}

/**
 * Re-read the installed version after an in-place update and store it in the
 * dashboard snapshot so the widget and the native update list stop reporting
 * the update that was just applied.
 *
 * @param string $which `child` or `parent`.
 * @return string Installed version after the update.
 */
function orsite_gh_sync_snapshot_after_update( $which ) {
	$theme = orsite_gh_theme_info( $which );
	$data  = function_exists( 'get_file_data' )
		? get_file_data( $theme['dir'] . '/style.css', array( 'Version' => 'Version' ) )
		: array();
	$new   = ! empty( $data['Version'] ) ? (string) $data['Version'] : $theme['version'];

	$snapshot = get_option( 'orsite_gh_snapshot' );
	if ( is_array( $snapshot ) && ! empty( $snapshot['time'] ) ) {
		$latest = isset( $snapshot['latest'][ $which ] ) ? $snapshot['latest'][ $which ] : null;
		$snapshot['installed'][ $which ] = $new;
		$snapshot['update'][ $which ]    = $latest ? version_compare( $new, $latest, '<' ) : false;
		$snapshot['time']                = time();
		update_option( 'orsite_gh_snapshot', $snapshot, false );
	}

	delete_site_transient( 'update_themes' );

	return $new;
}

/* -------------------------------------------------------------------------
 * Admin page
 * ---------------------------------------------------------------------- */

/**
 * Register the updater page in the dashboard.
 */
function orsite_gh_admin_menu() {
	add_menu_page(
		__( 'بروزرسانی گیت‌هاب', 'lax-theme-child' ),
		__( 'بروزرسانی گیت‌هاب', 'lax-theme-child' ),
		'update_themes',
		'orsite-github-updater',
		'orsite_gh_render_page',
		'dashicons-update',
		58
	);
}
add_action( 'admin_menu', 'orsite_gh_admin_menu' );

/**
 * Admin notice when a newer release exists, and one-shot notice after an
 * automatic update run.
 */
function orsite_gh_admin_notice() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}

	// The updater page already shows the full state, so skip the duplication.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'orsite-github-updater' ) ) {
		return;
	}

	$done = get_option( 'orsite_gh_auto_done' );
	if ( is_array( $done ) && ( ! empty( $done['applied'] ) || ! empty( $done['errors'] ) ) ) {
		$parts = array();
		foreach ( (array) $done['applied'] as $which => $info ) {
			$parts[] = sprintf(
				'%1$s %2$s→%3$s (%4$d فایل)',
				'child' === $which ? __( 'قالب فرزند', 'lax-theme-child' ) : __( 'قالب والد', 'lax-theme-child' ),
				$info['from'],
				$info['to'],
				count( $info['updated'] )
			);
		}
		foreach ( (array) $done['errors'] as $which => $msg ) {
			$parts[] = sprintf( '%s: %s', 'child' === $which ? __( 'خطای فرزند', 'lax-theme-child' ) : __( 'خطای والد', 'lax-theme-child' ), $msg );
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>🔄 %1$s <strong>%2$s</strong> — <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'بروزرسانی خودکار از گیت‌هاب انجام شد:', 'lax-theme-child' ),
			implode( ' | ', $parts ),
			esc_url( admin_url( 'admin.php?page=orsite-github-updater' ) ),
			esc_html__( 'مشاهده‌ی لاگ', 'lax-theme-child' )
		);
		delete_option( 'orsite_gh_auto_done' );
	}

	$flag = get_option( 'orsite_gh_update_flag' );
	if ( empty( $flag['child'] ) && empty( $flag['parent'] ) ) {
		return;
	}
	$parts = array();
	if ( ! empty( $flag['child'] ) ) {
		$parts[] = sprintf( 'قالب فرزند %s', esc_html( $flag['child'] ) );
	}
	if ( ! empty( $flag['parent'] ) ) {
		$parts[] = sprintf( 'قالب والد %s', esc_html( $flag['parent'] ) );
	}
	printf(
		'<div class="notice notice-info is-dismissible"><p>%1$s <strong>%2$s</strong> — <a href="%3$s">%4$s</a></p></div>',
		esc_html__( 'نسخه‌ی تازه‌ای در گیت‌هاب منتشر شده است:', 'lax-theme-child' ),
		implode( __( ' و ', 'lax-theme-child' ), $parts ),
		esc_url( admin_url( 'admin.php?page=orsite-github-updater' ) ),
		esc_html__( 'بروزرسانی از صفحه‌ی بروزرسانی گیت‌هاب', 'lax-theme-child' )
	);
}
add_action( 'admin_notices', 'orsite_gh_admin_notice' );

/**
 * Handle POST actions (token save, check, update) then redirect back.
 */
function orsite_gh_handle_actions() {
	if ( ! isset( $_POST['orsite_gh_action'] ) ) {
		return;
	}
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'دسترسی غیرمجاز.', 'lax-theme-child' ) );
	}
	check_admin_referer( 'orsite_gh_admin' );

	$action = sanitize_key( wp_unslash( $_POST['orsite_gh_action'] ) );
	$page   = admin_url( 'admin.php?page=orsite-github-updater' );

	switch ( $action ) {
		case 'save_token':
			if ( orsite_gh_token_is_constant() ) {
				break;
			}
			$token = isset( $_POST['orsite_gh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['orsite_gh_token'] ) ) : '';
			if ( $token ) {
				update_option( 'orsite_gh_token', $token, false );
				update_option( 'orsite_gh_release_cache', array(), false );
				$redirect = add_query_arg( 'orsite_msg', 'token_saved', $page );
			} else {
				$redirect = add_query_arg( 'orsite_msg', 'token_empty', $page );
			}
			break;

		case 'clear_token':
			delete_option( 'orsite_gh_token' );
			update_option( 'orsite_gh_release_cache', array(), false );
			$redirect = add_query_arg( 'orsite_msg', 'token_cleared', $page );
			break;

		case 'save_settings':
			$auto = isset( $_POST['orsite_gh_auto_apply'] ) ? '1' : '0';
			update_option( 'orsite_gh_auto_apply', $auto, false );
			$redirect = add_query_arg( 'orsite_msg', 'settings_saved', $page );
			break;

		case 'check':
			$release = orsite_gh_release( true );
			if ( is_wp_error( $release ) ) {
				update_option( 'orsite_gh_last_check', array( 'time' => time(), 'error' => $release->get_error_message() ), false );
				$redirect = add_query_arg( 'orsite_msg', 'check_fail', $page );
				break;
			}
			$report = orsite_gh_build_check_report( $release );
			update_option( 'orsite_gh_last_check', array( 'time' => time(), 'error' => '' ), false );
			update_option( 'orsite_gh_last_diff', $report, false );
			orsite_gh_refresh_update_flag( $report );
			$redirect = add_query_arg( 'orsite_msg', 'check_ok', $page );
			break;

		case 'update_child':
		case 'update_parent':
		case 'update_all':
			set_time_limit( 300 );
			$targets = 'update_child' === $action ? array( 'child' )
				: ( 'update_parent' === $action ? array( 'parent' ) : array( 'child', 'parent' ) );
			$done    = array();
			$errors  = array();
			foreach ( $targets as $which ) {
				$theme  = orsite_gh_theme_info( $which );
				$remote = orsite_gh_current_remote_versions();
				$remote_v = isset( $remote[ $which ] ) ? $remote[ $which ] : null;
				if ( $remote_v && version_compare( $theme['version'], $remote_v, '>=' ) ) {
					$done[ $which ] = 'up-to-date';
					continue;
				}
				$result = orsite_gh_update_theme( $which );
				if ( is_wp_error( $result ) ) {
					$errors[ $which ] = $result->get_error_message();
				} else {
					$done[ $which ] = $result;
				}
			}
			update_option( 'orsite_gh_last_run', array( 'time' => time(), 'done' => $done, 'errors' => $errors ), false );
			$redirect = add_query_arg( 'orsite_msg', $errors ? 'update_fail' : 'update_ok', $page );
			break;

		default:
			$redirect = $page;
	}

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_init', 'orsite_gh_handle_actions' );

/**
 * Build the dry-run check report for both themes.
 *
 * @param array $release Normalized release.
 * @return array
 */
function orsite_gh_build_check_report( $release ) {
	$versions = orsite_gh_remote_versions( $release );
	$branch   = orsite_gh_branch_versions();
	$report   = array(
		'time'     => time(),
		'tag'      => $release['tag_name'],
		'html_url' => $release['html_url'],
		'body'     => $release['body'],
		'versions' => $versions,
		'branch'   => $branch,
		'themes'   => array(),
	);

	foreach ( array( 'child', 'parent' ) as $which ) {
		$theme = orsite_gh_theme_info( $which );
		$remote = isset( $versions[ $which ] ) ? $versions[ $which ] : null;
		$branch_v = isset( $branch[ $which ] ) ? $branch[ $which ] : null;
		$source   = $release;

		// A plain push to `main` (no new release yet) still shows up and can
		// be applied from the branch ZIP.
		if ( $branch_v && ( ! $remote || version_compare( $branch_v, $remote, '>' ) ) && version_compare( $branch_v, $theme['version'], '>' ) ) {
			$source = orsite_gh_branch_source();
			$remote = $branch_v;
		}

		$entry = array(
			'local'  => $theme['version'],
			'remote' => $remote,
			'name'   => $theme['name'],
			'source' => ! empty( $source['branch'] ) ? 'branch' : 'release',
		);
		$plan = orsite_gh_plan_update( $which, $source );
		if ( is_wp_error( $plan ) ) {
			$entry['error'] = $plan->get_error_message();
		} else {
			$entry['changed']   = $plan['plan']['changed'];
			$entry['new']       = $plan['plan']['new'];
			$entry['unchanged'] = count( $plan['plan']['unchanged'] );
			$entry['stale']     = $plan['plan']['stale'];
			orsite_updater_rrmdir( $plan['tmpdir'] );
		}
		$report['themes'][ $which ] = $entry;
	}

	return $report;
}

/**
 * Recompute the "update available" flag from a check report.
 *
 * @param array $report Report from orsite_gh_build_check_report().
 */
function orsite_gh_refresh_update_flag( $report ) {
	$flag = array( 'child' => null, 'parent' => null, 'checked' => time() );
	foreach ( array( 'child', 'parent' ) as $which ) {
		$entry = isset( $report['themes'][ $which ] ) ? $report['themes'][ $which ] : null;
		if ( $entry && ! empty( $entry['remote'] ) && version_compare( $entry['local'], $entry['remote'], '<' ) ) {
			$flag[ $which ] = $entry['remote'];
		}
	}
	update_option( 'orsite_gh_update_flag', $flag, false );
}

/**
 * Is automatic apply of higher-version updates enabled? (Default: on.)
 *
 * @return bool
 */
function orsite_gh_auto_apply_enabled() {
	return '0' !== (string) get_option( 'orsite_gh_auto_apply', '1' );
}

/**
 * Apply every higher-version update automatically (changed files only).
 *
 * Only runs for themes whose released version is strictly greater than the
 * installed one — it never downgrades and never rewrites equal versions.
 *
 * @param array $release Normalized release.
 * @return array { applied: array, errors: array }
 */
function orsite_gh_auto_apply_updates( $release ) {
	$out = array( 'applied' => array(), 'errors' => array() );
	if ( ! orsite_gh_auto_apply_enabled() ) {
		return $out;
	}

	$versions = orsite_gh_remote_versions( $release );
	foreach ( array( 'child', 'parent' ) as $which ) {
		$theme    = orsite_gh_theme_info( $which );
		$remote_v = isset( $versions[ $which ] ) ? $versions[ $which ] : null;
		if ( ! $remote_v || ! version_compare( $theme['version'], $remote_v, '<' ) ) {
			continue;
		}
		$result = orsite_gh_update_theme( $which );
		if ( is_wp_error( $result ) ) {
			$out['errors'][ $which ] = $result->get_error_message();
			continue;
		}
		$result['auto']   = true;
		$result['from']   = $theme['version'];
		$result['to']     = $remote_v;
		$out['applied'][ $which ] = $result;

		$log = (array) get_option( 'orsite_gh_last_update', array() );
		if ( isset( $log[ $which ] ) ) {
			$log[ $which ]['auto'] = true;
			$log[ $which ]['from'] = $theme['version'];
			$log[ $which ]['to']   = $remote_v;
			update_option( 'orsite_gh_last_update', $log, false );
		}
	}

	if ( $out['applied'] || $out['errors'] ) {
		update_option( 'orsite_gh_auto_done', array( 'time' => time(), 'applied' => $out['applied'], 'errors' => $out['errors'] ), false );
	}

	// Recompute the badge: anything applied is no longer "update available".
	$flag = array( 'child' => null, 'parent' => null, 'checked' => time() );
	foreach ( array( 'child', 'parent' ) as $which ) {
		if ( isset( $out['applied'][ $which ] ) ) {
			continue;
		}
		$theme    = orsite_gh_theme_info( $which );
		$remote_v = isset( $versions[ $which ] ) ? $versions[ $which ] : null;
		if ( $remote_v && version_compare( $theme['version'], $remote_v, '<' ) ) {
			$flag[ $which ] = $remote_v;
		}
	}
	update_option( 'orsite_gh_update_flag', $flag, false );

	return $out;
}

/**
 * Remote versions with a light cache, for the versions table.
 *
 * @return array { child: ?string, parent: ?string }
 */
function orsite_gh_current_remote_versions() {
	$release = orsite_gh_release( false );
	$out     = is_wp_error( $release ) ? array( 'child' => null, 'parent' => null ) : orsite_gh_remote_versions( $release );

	$branch = orsite_gh_branch_versions();
	foreach ( array( 'child', 'parent' ) as $which ) {
		$branch_v = isset( $branch[ $which ] ) ? $branch[ $which ] : null;
		if ( $branch_v && ( empty( $out[ $which ] ) || version_compare( $branch_v, $out[ $which ], '>' ) ) ) {
			$out[ $which ] = $branch_v;
		}
	}

	return $out;
}

/**
 * One cached, never-slow snapshot of the GitHub ↔ WordPress update state.
 *
 * This is the single source the dashboard widget, the admin notice, the admin
 * bar badge and the native WordPress update list read from, so an administrator
 * sees a new release (or a plain push to `main`) without opening any page.
 *
 * @param bool $force       Bypass the cache and hit GitHub again.
 * @param bool $allow_fetch When false, only cached data is returned (no HTTP).
 * @return array {
 *     time, ok, error, tag, name, html_url, published_at, body, changelog,
 *     installed[], latest[], source[], update[], packages[], branch[], checked_human
 * }
 */
function orsite_gh_update_snapshot( $force = false, $allow_fetch = true ) {
	$cache = get_option( 'orsite_gh_snapshot' );

	if ( ! $force && is_array( $cache ) && ! empty( $cache['time'] ) ) {
		$age   = time() - (int) $cache['time'];
		$limit = empty( $cache['ok'] ) ? ORSITE_GH_CHECK_RETRY : ORSITE_GH_CHECK_INTERVAL;
		if ( $age < $limit || ! $allow_fetch ) {
			$cache['stale'] = ( $age >= $limit );
			return $cache;
		}
	}
	if ( ! $allow_fetch ) {
		return array(
			'time'      => 0,
			'ok'        => false,
			'error'     => '',
			'installed' => array(),
			'latest'    => array(),
			'source'    => array(),
			'update'    => array(),
			'packages'  => array(),
			'branch'    => array(),
			'changelog' => array(),
			'stale'     => true,
		);
	}

	$branch = orsite_gh_branch_versions( $force );
	$snapshot = array(
		'time'         => time(),
		'ok'           => false,
		'error'        => '',
		'tag'          => '',
		'name'         => '',
		'html_url'     => ORSITE_GH_WEB . '/releases',
		'published_at' => '',
		'body'         => '',
		'changelog'    => array(),
		'installed'    => array(),
		'latest'       => array(),
		'source'       => array(),
		'update'       => array(),
		'packages'     => array(),
		'branch'       => array(
			'child'  => isset( $branch['child'] ) ? $branch['child'] : null,
			'parent' => isset( $branch['parent'] ) ? $branch['parent'] : null,
			'sha'    => isset( $branch['sha'] ) ? $branch['sha'] : '',
			'time'   => isset( $branch['time'] ) ? (int) $branch['time'] : 0,
		),
	);

	$release  = orsite_gh_release( $force );
	$versions = array( 'child' => null, 'parent' => null );

	if ( is_wp_error( $release ) ) {
		$snapshot['error'] = $release->get_error_message();
	} else {
		$versions          = orsite_gh_remote_versions( $release, $force );
		$snapshot['ok']    = true;
		$snapshot['tag']   = (string) $release['tag_name'];
		$snapshot['name']  = (string) $release['name'];
		$snapshot['html_url']     = (string) $release['html_url'];
		$snapshot['published_at'] = (string) $release['published_at'];
		$snapshot['body']         = (string) $release['body'];
		$snapshot['changelog']    = isset( $versions['changelog'] ) ? (array) $versions['changelog'] : array();
	}

	foreach ( array( 'child', 'parent' ) as $which ) {
		$theme   = orsite_gh_theme_info( $which );
		$rel_v   = isset( $versions[ $which ] ) ? $versions[ $which ] : null;
		$br_v    = $snapshot['branch'][ $which ];
		$latest  = $rel_v;
		$from    = 'release';
		if ( $br_v && ( ! $rel_v || version_compare( $br_v, $rel_v, '>' ) ) ) {
			$latest = $br_v;
			$from   = 'branch';
		}

		$snapshot['installed'][ $which ] = $theme['version'];
		$snapshot['latest'][ $which ]    = $latest;
		$snapshot['source'][ $which ]    = $from;
		$snapshot['update'][ $which ]    = $latest ? version_compare( $theme['version'], $latest, '<' ) : false;

		// Native WordPress theme updates can only consume a theme-root ZIP,
		// which is exactly what a published release asset is.
		$package = '';
		if ( ! is_wp_error( $release ) && 'release' === $from ) {
			$asset = orsite_gh_find_asset( $release, $theme['asset'] );
			if ( $asset && empty( $asset['kind'] ) && ! empty( $asset['browser_download_url'] ) ) {
				$package = (string) $asset['browser_download_url'];
			}
		}
		$snapshot['packages'][ $which ] = $package;
	}

	$snapshot['ok'] = $snapshot['ok'] || ( ! empty( $snapshot['latest']['child'] ) || ! empty( $snapshot['latest']['parent'] ) );

	update_option( 'orsite_gh_snapshot', $snapshot, false );

	// Keep the legacy flag used by the admin notice in sync.
	$flag = array( 'child' => null, 'parent' => null, 'checked' => time() );
	foreach ( array( 'child', 'parent' ) as $which ) {
		if ( ! empty( $snapshot['update'][ $which ] ) ) {
			$flag[ $which ] = $snapshot['latest'][ $which ];
		}
	}
	update_option( 'orsite_gh_update_flag', $flag, false );

	// Let WordPress rebuild the native theme-update list with these versions.
	delete_site_transient( 'update_themes' );

	return $snapshot;
}

/**
 * Feed the GitHub release into the native WordPress theme update list.
 *
 * This is what makes a new GitHub release appear under «پیشخوان → بروزرسانی‌ها»,
 * in «نمایش → پوسته‌ها» and in the admin menu update bubble. The filter never
 * performs an HTTP request: it only reads the cached snapshot.
 *
 * @param mixed $transient The `update_themes` site transient.
 * @return mixed
 */
function orsite_gh_inject_theme_update( $transient ) {
	if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
		return $transient;
	}

	$snapshot = orsite_gh_update_snapshot( false, false );
	if ( empty( $snapshot['time'] ) || empty( $snapshot['update'] ) ) {
		return $transient;
	}

	if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
		$transient->response = array();
	}

	foreach ( array( 'child' => 'lax-theme-child', 'parent' => 'lax-theme' ) as $which => $stylesheet ) {
		if ( empty( $snapshot['update'][ $which ] ) || 'release' !== $snapshot['source'][ $which ] ) {
			continue;
		}
		$package = isset( $snapshot['packages'][ $which ] ) ? (string) $snapshot['packages'][ $which ] : '';
		if ( '' === $package ) {
			// Fall back to the repository copy of the release ZIP.
			$package = ORSITE_GH_WEB . '/raw/' . rawurlencode( (string) $snapshot['tag'] ) . '/releases/' . rawurlencode( 'lax-theme-child' === $which ? ORSITE_GH_CHILD_ASSET : ORSITE_GH_PARENT_ASSET );
		}
		$transient->response[ $stylesheet ] = array(
			'theme'       => $stylesheet,
			'new_version' => (string) $snapshot['latest'][ $which ],
			'url'         => (string) $snapshot['html_url'],
			'package'     => $package,
		);
		if ( isset( $transient->no_update[ $stylesheet ] ) ) {
			unset( $transient->no_update[ $stylesheet ] );
		}
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'orsite_gh_inject_theme_update', 20 );

/**
 * Background check: refresh the snapshot, feed the notice/badge and apply
 * higher-version releases when automatic updates are enabled for this site.
 */
function orsite_gh_auto_check() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}
	if ( ! function_exists( 'wp_get_theme' ) ) {
		return;
	}

	$last = (int) get_option( 'orsite_gh_auto_check_time', 0 );
	if ( ( time() - $last ) < ( 15 * MINUTE_IN_SECONDS ) ) {
		return;
	}
	update_option( 'orsite_gh_auto_check_time', time(), false );

	$snapshot = orsite_gh_update_snapshot( false );
	if ( empty( $snapshot['ok'] ) ) {
		update_option( 'orsite_gh_last_check', array( 'time' => time(), 'error' => isset( $snapshot['error'] ) ? (string) $snapshot['error'] : '' ), false );
		return;
	}
	update_option( 'orsite_gh_last_check', array( 'time' => time(), 'error' => '' ), false );

	$release = orsite_gh_release( false );
	if ( is_wp_error( $release ) ) {
		return;
	}

	if ( orsite_gh_auto_apply_enabled() ) {
		orsite_gh_auto_apply_updates( $release );
	}
}
add_action( 'admin_init', 'orsite_gh_auto_check', 5 );

/**
 * Register the dashboard widget on /wp-admin/index.php.
 */
function orsite_gh_register_dashboard_widget() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'orsite_gh_dashboard_widget',
		__( 'بروزرسانی قالب از گیت‌هاب', 'lax-theme-child' ),
		'orsite_gh_render_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'orsite_gh_register_dashboard_widget' );

/**
 * Dashboard widget: connection state, installed vs. published versions and
 * the release changelog written in `releases/orsite-release.json`.
 */
function orsite_gh_render_dashboard_widget() {
	$snapshot  = orsite_gh_update_snapshot( false, false );
	$page_url  = admin_url( 'admin.php?page=orsite-github-updater' );
	$installed = array(
		'child'  => orsite_gh_theme_info( 'child' ),
		'parent' => orsite_gh_theme_info( 'parent' ),
	);
	$has_update = ! empty( $snapshot['update']['child'] ) || ! empty( $snapshot['update']['parent'] );
	?>
	<div class="orsite-gh-widget" dir="rtl">
		<style>
			.orsite-gh-widget { font-size: 13px; color: #071A33; }
			.orsite-gh-widget .ogw-row { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px dashed #D6E2EE; }
			.orsite-gh-widget .ogw-row:last-child { border-bottom: 0; }
			.orsite-gh-widget .ogw-k { color: #38536E; }
			.orsite-gh-widget code { direction: ltr; display: inline-block; background: #EEF4FA; border: 1px solid #B9C9D9; border-radius: 6px; padding: 0 6px; }
			.orsite-gh-widget .ogw-ok { color: #0A2F6B; font-weight: 700; }
			.orsite-gh-widget .ogw-new { color: #fff; background: #0A2F6B; border-radius: 6px; padding: 1px 8px; font-weight: 700; }
			.orsite-gh-widget .ogw-bad { color: #7a1220; font-weight: 700; }
			.orsite-gh-widget .ogw-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
			.orsite-gh-widget .ogw-actions .button { margin: 0; }
			.orsite-gh-widget ul { margin: 6px 16px 0 0; padding: 0; list-style: disc; }
			.orsite-gh-widget ul li { margin: 0 0 5px; line-height: 1.8; }
			.orsite-gh-widget h4 { margin: 14px 0 4px; color: #06265C; }
		</style>

		<?php if ( empty( $snapshot['time'] ) ) : ?>
			<p class="ogw-k"><?php esc_html_e( 'هنوز بررسی‌ای انجام نشده است. با دکمه‌ی زیر اتصال به گیت‌هاب را بررسی کنید.', 'lax-theme-child' ); ?></p>
		<?php else : ?>
			<div class="ogw-row">
				<span class="ogw-k"><?php esc_html_e( 'مخزن', 'lax-theme-child' ); ?></span>
				<a href="<?php echo esc_url( ORSITE_GH_WEB ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( ORSITE_GH_REPO ); ?></code></a>
			</div>
			<div class="ogw-row">
				<span class="ogw-k"><?php esc_html_e( 'وضعیت اتصال', 'lax-theme-child' ); ?></span>
				<?php if ( ! empty( $snapshot['ok'] ) ) : ?>
					<span class="ogw-ok"><?php esc_html_e( 'متصل', 'lax-theme-child' ); ?></span>
				<?php else : ?>
					<span class="ogw-bad"><?php echo esc_html( ! empty( $snapshot['error'] ) ? $snapshot['error'] : __( 'برقرار نشد', 'lax-theme-child' ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ogw-row">
				<span class="ogw-k"><?php esc_html_e( 'آخرین بررسی', 'lax-theme-child' ); ?></span>
				<span><?php echo esc_html( sprintf( '%s پیش', human_time_diff( (int) $snapshot['time'] ) ) ); ?></span>
			</div>
			<?php if ( ! empty( $snapshot['tag'] ) ) : ?>
				<div class="ogw-row">
					<span class="ogw-k"><?php esc_html_e( 'آخرین انتشار گیت‌هاب', 'lax-theme-child' ); ?></span>
					<a href="<?php echo esc_url( $snapshot['html_url'] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $snapshot['tag'] ); ?></code></a>
				</div>
			<?php endif; ?>
			<?php foreach ( $installed as $which => $theme ) : ?>
				<?php
				$latest = isset( $snapshot['latest'][ $which ] ) ? $snapshot['latest'][ $which ] : null;
				$update = ! empty( $snapshot['update'][ $which ] );
				$source = isset( $snapshot['source'][ $which ] ) ? $snapshot['source'][ $which ] : '';
				?>
				<div class="ogw-row">
					<span class="ogw-k"><?php echo esc_html( $theme['name'] ); ?></span>
					<span>
						<code><?php echo esc_html( $theme['version'] ); ?></code>
						<?php if ( $latest ) : ?>
							<?php echo esc_html( ' → ' ); ?><code><?php echo esc_html( $latest ); ?></code>
						<?php endif; ?>
						<?php if ( $update ) : ?>
							<span class="ogw-new"><?php esc_html_e( 'بروزرسانی موجود است', 'lax-theme-child' ); ?></span>
							<?php if ( 'branch' === $source ) : ?>
								<span class="ogw-k"><?php esc_html_e( '(از شاخه‌ی main)', 'lax-theme-child' ); ?></span>
							<?php endif; ?>
						<?php elseif ( $latest ) : ?>
							<span class="ogw-ok"><?php esc_html_e( 'به‌روز است', 'lax-theme-child' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>

			<?php if ( ! empty( $snapshot['branch']['child'] ) || ! empty( $snapshot['branch']['parent'] ) ) : ?>
				<p class="ogw-k" style="margin:10px 0 0">
					<?php
					$branch_parts = array();
					if ( ! empty( $snapshot['branch']['child'] ) ) {
						$branch_parts[] = sprintf( 'فرزند %s', $snapshot['branch']['child'] );
					}
					if ( ! empty( $snapshot['branch']['parent'] ) ) {
						$branch_parts[] = sprintf( 'والد %s', $snapshot['branch']['parent'] );
					}
					printf(
						/* translators: 1: branch name, 2: versions list, 3: commit sha */
						esc_html__( 'نسخه‌ی کد روی شاخه‌ی %1$s: %2$s%3$s', 'lax-theme-child' ),
						esc_html( ORSITE_GH_BRANCH ),
						esc_html( implode( ' — ', $branch_parts ) ),
						! empty( $snapshot['branch']['sha'] ) ? esc_html( ' (' . $snapshot['branch']['sha'] . ')' ) : ''
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( $has_update && ! empty( $snapshot['changelog'] ) ) : ?>
				<h4><?php esc_html_e( 'تغییرات نسخه‌ی تازه', 'lax-theme-child' ); ?></h4>
				<ul>
					<?php foreach ( array_slice( (array) $snapshot['changelog'], 0, 3 ) as $line ) : ?>
						<li><?php echo esc_html( (string) $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

		<div class="ogw-actions">
			<form method="post" action="<?php echo esc_url( $page_url ); ?>" style="display:inline">
				<?php wp_nonce_field( 'orsite_gh_admin', 'orsite_gh_nonce' ); ?>
				<button type="submit" name="orsite_gh_action" value="check" class="button button-primary"><?php esc_html_e( 'بررسی دوباره‌ی گیت‌هاب', 'lax-theme-child' ); ?></button>
			</form>
			<a class="button" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'صفحه‌ی بروزرسانی', 'lax-theme-child' ); ?></a>
			<?php if ( $has_update ) : ?>
				<form method="post" action="<?php echo esc_url( $page_url ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'فایل‌های تغییریافته‌ی هر دو قالب از آخرین نسخه جایگزین شوند؟', 'lax-theme-child' ) ); ?>')">
					<?php wp_nonce_field( 'orsite_gh_admin', 'orsite_gh_nonce' ); ?>
					<button type="submit" name="orsite_gh_action" value="update_all" class="button"><?php esc_html_e( 'بروزرسانی فوری', 'lax-theme-child' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Admin-bar badge: the pending GitHub update is visible on every screen.
 *
 * @param WP_Admin_Bar $bar Admin bar instance.
 */
function orsite_gh_admin_bar_node( $bar ) {
	if ( ! current_user_can( 'update_themes' ) || ! is_object( $bar ) ) {
		return;
	}
	$snapshot = orsite_gh_update_snapshot( false, false );
	if ( empty( $snapshot['update'] ) ) {
		return;
	}
	$parts = array();
	foreach ( array( 'child', 'parent' ) as $which ) {
		if ( ! empty( $snapshot['update'][ $which ] ) ) {
			$parts[] = ( 'child' === $which ? __( 'فرزند', 'lax-theme-child' ) : __( 'والد', 'lax-theme-child' ) ) . ' ' . $snapshot['latest'][ $which ];
		}
	}
	if ( ! $parts ) {
		return;
	}

	$bar->add_node(
		array(
			'id'    => 'orsite-gh-update',
			'title' => '⤴ ' . sprintf(
				/* translators: %s: versions list */
				__( 'بروزرسانی گیت‌هاب: %s', 'lax-theme-child' ),
				implode( ' | ', $parts )
			),
			'href'  => admin_url( 'admin.php?page=orsite-github-updater' ),
			'meta'  => array( 'title' => __( 'نسخه‌ی تازه‌ای در مخزن گیت‌هاب منتشر شده است', 'lax-theme-child' ) ),
		)
	);
}
add_action( 'admin_bar_menu', 'orsite_gh_admin_bar_node', 100 );

/**
 * Render file lists for the report cards.
 *
 * @param string[] $files  Relative paths.
 * @param int      $limit  Max items.
 * @return string HTML list.
 */
function orsite_gh_render_file_list( $files, $limit = 40 ) {
	$files = array_slice( (array) $files, 0, $limit );
	if ( ! $files ) {
		return '<em>' . esc_html__( '—', 'lax-theme-child' ) . '</em>';
	}
	$items = '';
	foreach ( $files as $f ) {
		$items .= '<li><code>' . esc_html( $f ) . '</code></li>';
	}
	if ( count( (array) $files ) >= $limit ) {
		$items .= '<li>…</li>';
	}
	return '<ul class="orsite-file-list">' . $items . '</ul>';
}

/**
 * One status/notice message from the ?orsite_msg= query arg.
 *
 * @return string Message HTML-ready text (already escaped on output).
 */
function orsite_gh_flash_message() {
	$msg = isset( $_GET['orsite_msg'] ) ? sanitize_key( wp_unslash( $_GET['orsite_msg'] ) ) : '';
	$map = array(
		'token_saved'   => array( 'success', __( 'توکن دسترسی ذخیره شد.', 'lax-theme-child' ) ),
		'token_empty'   => array( 'warning', __( 'توکنی وارد نشد.', 'lax-theme-child' ) ),
		'token_cleared' => array( 'success', __( 'توکن ذخیره‌شده پاک شد.', 'lax-theme-child' ) ),
		'settings_saved' => array( 'success', __( 'تنظیمات بروزرسانی خودکار ذخیره شد.', 'lax-theme-child' ) ),
		'check_ok'      => array( 'success', __( 'بررسی انجام شد؛ نتیجه در کارت «فایل‌های تغییریافته» آمده است.', 'lax-theme-child' ) ),
		'check_fail'    => array( 'error', __( 'بررسی ناموفق بود؛ جزئیات در کارت «وضعیت اتصال» است.', 'lax-theme-child' ) ),
		'update_ok'     => array( 'success', __( 'بروزرسانی انجام شد؛ لاگ در کارت «آخرین بروزرسانی» است.', 'lax-theme-child' ) ),
		'update_fail'   => array( 'error', __( 'بروزرسانی با خطا همراه بود؛ جزئیات در کارت «آخرین بروزرسانی» است.', 'lax-theme-child' ) ),
	);
	if ( ! isset( $map[ $msg ] ) ) {
		return '';
	}
	return sprintf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $map[ $msg ][0] ),
		esc_html( $map[ $msg ][1] )
	);
}

/**
 * The dashboard page itself.
 */
function orsite_gh_render_page() {
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'دسترسی غیرمجاز.', 'lax-theme-child' ) );
	}

	$token        = orsite_gh_token();
	$token_const  = orsite_gh_token_is_constant();
	$release      = orsite_gh_release( false );
	$release_err  = is_wp_error( $release ) ? $release->get_error_message() : '';
	$release_data = is_wp_error( $release ) ? null : $release;
	$remote       = $release_data ? orsite_gh_remote_versions( $release_data ) : array( 'child' => null, 'parent' => null, 'changelog' => array() );
	$branch       = orsite_gh_branch_versions();
	$remote_from  = array( 'child' => 'release', 'parent' => 'release' );
	foreach ( array( 'child', 'parent' ) as $which ) {
		$branch_v = isset( $branch[ $which ] ) ? $branch[ $which ] : null;
		if ( $branch_v && ( empty( $remote[ $which ] ) || version_compare( $branch_v, $remote[ $which ], '>' ) ) ) {
			$remote[ $which ]      = $branch_v;
			$remote_from[ $which ] = 'branch';
		}
	}
	$diff         = (array) get_option( 'orsite_gh_last_diff', array() );
	$last_update  = (array) get_option( 'orsite_gh_last_update', array() );
	$last_run     = (array) get_option( 'orsite_gh_last_run', array() );
	$last_check   = (array) get_option( 'orsite_gh_last_check', array() );
	$child        = orsite_gh_theme_info( 'child' );
	$parent       = orsite_gh_theme_info( 'parent' );
	$nonce        = wp_nonce_field( 'orsite_gh_admin', 'orsite_gh_nonce' );
	?>
	<div class="wrap orsite-gh-wrap" dir="rtl">
	<style>
	.orsite-gh-wrap { background: #F4F8FC; margin: 10px 20px 0 0; padding: 18px; border-radius: 12px; }
	.orsite-gh-wrap h1 { color: #041A3F; }
	.orsite-gh-wrap h2 { color: #06265C; font-size: 15px; margin: 0 0 10px; background: #06265C; color: #fff; display: inline-block; padding: 6px 14px; border-radius: 8px; }
	.orsite-card { background: #fff; border: 1px solid #B9C9D9; border-radius: 12px; padding: 16px 18px; margin: 0 0 18px; box-shadow: 0 6px 18px rgba(10, 47, 107, 0.12); }
	.orsite-card p { color: #071A33; }
	.orsite-meta { color: #38536E; font-size: 12.5px; }
	.orsite-btn { background: #0A2F6B !important; color: #fff !important; border: 1px solid #041A3F !important; border-radius: 8px; padding: 6px 16px !important; }
	.orsite-btn:hover { background: #06265C !important; }
	.orsite-btn-danger { background: #fff !important; color: #041A3F !important; border: 1px solid #B9C9D9 !important; }
	.orsite-table th { color: #041A3F; }
	.orsite-table td { vertical-align: top; }
	.orsite-file-list { margin: 4px 0 0 18px; }
	.orsite-file-list code { direction: ltr; display: inline-block; background: #EEF4FA; border: 1px solid #B9C9D9; border-radius: 6px; padding: 1px 6px; font-size: 11.5px; }
	.orsite-status-ok { color: #0A2F6B; font-weight: 700; }
	.orsite-status-new { color: #041A3F; background: #EEF4FA; border: 1px solid #0A2F6B; border-radius: 6px; padding: 1px 8px; font-weight: 700; }
	.orsite-status-bad { color: #7a1220; font-weight: 700; }
	</style>

	<h1><?php esc_html_e( 'بروزرسانی قالب‌ها از گیت‌هاب — همیار جراحی', 'lax-theme-child' ); ?></h1>
	<?php echo orsite_gh_flash_message(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from a whitelist. ?>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'وضعیت اتصال', 'lax-theme-child' ); ?></h2>
		<p>
			<?php esc_html_e( 'مخزن پروژه:', 'lax-theme-child' ); ?>
			<a href="<?php echo esc_url( ORSITE_GH_WEB ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( ORSITE_GH_REPO ); ?></code></a>
			<?php if ( $release_data ) : ?>
				— <span class="orsite-status-ok"><?php esc_html_e( 'متصل', 'lax-theme-child' ); ?></span>
			<?php else : ?>
				— <span class="orsite-status-bad"><?php echo esc_html( $release_err ? $release_err : __( 'اتصال برقرار نشده', 'lax-theme-child' ) ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( $token_const ) : ?>
			<p class="orsite-meta"><?php esc_html_e( 'توکن از ثابت GITHUB_ACCESS_TOKEN در wp-config.php خوانده می‌شود و در این صفحه قابل تغییر نیست.', 'lax-theme-child' ); ?></p>
		<?php else : ?>
			<form method="post" action="">
				<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput -- nonce field. ?>
				<p>
					<label for="orsite-gh-token"><strong><?php esc_html_e( 'توکن دسترسی گیت‌هاب (اختیاری)', 'lax-theme-child' ); ?></strong></label><br />
					<input type="password" id="orsite-gh-token" name="orsite_gh_token" value="" class="regular-text code" autocomplete="off" placeholder="<?php echo esc_attr( $token ? orsite_gh_mask_token( $token ) : 'github_pat_… / ghp_…' ); ?>" />
					<?php if ( $token ) : ?>
						<span class="orsite-meta"><?php printf( esc_html__( 'ذخیره‌شده: %s', 'lax-theme-child' ), esc_html( orsite_gh_mask_token( $token ) ) ); ?></span>
					<?php endif; ?>
				</p>
				<p class="orsite-meta">
					<?php esc_html_e( 'برای مخزن عمومی، دانلود بدون توکن هم کار می‌کند؛ توکن فقط نرخ درخواست API را بالا می‌برد و برای مخزن خصوصی لازم است. توکن Fine-grained با دسترسی فقط Contents: Read روی همین مخزن بسازید.', 'lax-theme-child' ); ?>
				</p>
				<p>
					<button type="submit" name="orsite_gh_action" value="save_token" class="button orsite-btn"><?php esc_html_e( 'ذخیره‌ی توکن', 'lax-theme-child' ); ?></button>
					<?php if ( $token ) : ?>
						<button type="submit" name="orsite_gh_action" value="clear_token" class="button orsite-btn-danger"><?php esc_html_e( 'پاک‌کردن توکن', 'lax-theme-child' ); ?></button>
					<?php endif; ?>
					<button type="submit" name="orsite_gh_action" value="check" class="button orsite-btn"><?php esc_html_e( 'بررسی اتصال و دریافت آخرین انتشار', 'lax-theme-child' ); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<?php if ( ! empty( $last_check['error'] ) ) : ?>
			<p class="orsite-status-bad"><?php echo esc_html( $last_check['error'] ); ?></p>
		<?php elseif ( ! empty( $last_check['time'] ) ) : ?>
			<p class="orsite-meta"><?php printf( esc_html__( 'آخرین بررسی: %s پیش', 'lax-theme-child' ), esc_html( human_time_diff( (int) $last_check['time'] ) ) ); ?></p>
		<?php endif; ?>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'بروزرسانی خودکار', 'lax-theme-child' ); ?></h2>
		<form method="post" action="">
			<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput -- nonce field. ?>
			<p>
				<label>
					<input type="checkbox" name="orsite_gh_auto_apply" value="1" <?php checked( orsite_gh_auto_apply_enabled() ); ?> />
					<strong><?php esc_html_e( 'اعمال خودکار به‌روزرسانی‌های با نسخه بالاتر', 'lax-theme-child' ); ?></strong>
				</label>
			</p>
			<p class="orsite-meta">
				<?php esc_html_e( 'هر ۳ ساعت آخرین Release گیت‌هاب بررسی می‌شود و نسخه‌ی تازه در پیشخوان، نوار مدیریت و صفحه‌ی «بروزرسانی‌ها»ی وردپرس نمایش داده می‌شود؛ اگر این گزینه روشن باشد فقط فایل‌های تغییریافته‌ی همان نسخه به‌صورت خودکار جایگزین می‌شوند. هرگز نسخه پایین‌تر نصب نمی‌شود و فایلی حذف نمی‌شود. اگر ترجیح می‌دهید خودتان دکمه را بزنید، تیک را بردارید.', 'lax-theme-child' ); ?>
			</p>
			<p>
				<button type="submit" name="orsite_gh_action" value="save_settings" class="button orsite-btn"><?php esc_html_e( 'ذخیره‌ی تنظیمات', 'lax-theme-child' ); ?></button>
			</p>
		</form>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'نسخه‌های قالب', 'lax-theme-child' ); ?></h2>
		<table class="widefat striped orsite-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'قالب', 'lax-theme-child' ); ?></th>
					<th><?php esc_html_e( 'نسخه‌ی نصب‌شده', 'lax-theme-child' ); ?></th>
					<th><?php esc_html_e( 'آخرین نسخه‌ی منتشرشده', 'lax-theme-child' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'lax-theme-child' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'lax-theme-child' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array( 'child' => $child, 'parent' => $parent ) as $key => $theme ) : ?>
				<?php
				$remote_v    = isset( $remote[ $key ] ) ? $remote[ $key ] : null;
				$update      = $remote_v && version_compare( $theme['version'], $remote_v, '<' );
				$from_branch = isset( $remote_from[ $key ] ) && 'branch' === $remote_from[ $key ];
				?>
				<tr>
					<td><strong><?php echo esc_html( $theme['name'] ); ?></strong><br /><span class="orsite-meta"><code><?php echo esc_html( $key === 'child' ? 'lax-theme-child' : 'lax-theme' ); ?></code></span></td>
					<td><code><?php echo esc_html( $theme['version'] ); ?></code></td>
					<td>
						<code><?php echo esc_html( $remote_v ? $remote_v : '—' ); ?></code>
						<?php if ( $remote_v && $from_branch ) : ?>
							<br /><span class="orsite-meta"><?php printf( esc_html__( 'از شاخه‌ی %s (بدون تگ ریلیز)', 'lax-theme-child' ), esc_html( ORSITE_GH_BRANCH ) ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $update ) : ?>
							<span class="orsite-status-new"><?php esc_html_e( 'بروزرسانی موجود است', 'lax-theme-child' ); ?></span>
						<?php elseif ( $remote_v ) : ?>
							<span class="orsite-status-ok"><?php esc_html_e( 'به‌روز است', 'lax-theme-child' ); ?></span>
						<?php else : ?>
							<span class="orsite-meta"><?php esc_html_e( 'نامشخص', 'lax-theme-child' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<form method="post" action="" style="display:inline">
							<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput -- nonce field. ?>
							<button type="submit" name="orsite_gh_action" value="<?php echo esc_attr( 'update_' . $key ); ?>" class="button orsite-btn" onclick="return confirm('<?php echo esc_js( __( 'فایل‌های تغییریافته‌ی این قالب روی وردپرس جایگزین شوند؟', 'lax-theme-child' ) ); ?>')">
								<?php $update ? esc_html_e( 'بروزرسانی فایل‌های تغییریافته', 'lax-theme-child' ) : esc_html_e( 'همگام‌سازی مجدد', 'lax-theme-child' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( ! empty( $branch['child'] ) || ! empty( $branch['parent'] ) || ! empty( $branch['error'] ) ) : ?>
			<p class="orsite-meta">
				<?php
				if ( ! empty( $branch['error'] ) ) {
					echo esc_html( $branch['error'] );
				} else {
					$branch_parts = array();
					if ( ! empty( $branch['child'] ) ) {
						$branch_parts[] = sprintf( 'فرزند %s', $branch['child'] );
					}
					if ( ! empty( $branch['parent'] ) ) {
						$branch_parts[] = sprintf( 'والد %s', $branch['parent'] );
					}
					printf(
						/* translators: 1: branch, 2: versions, 3: sha */
						esc_html__( 'کد موجود روی شاخه‌ی %1$s: %2$s%3$s — هر کامیت تازه روی این شاخه، بدون نیاز به تگ، در همین صفحه و در پیشخوان دیده می‌شود.', 'lax-theme-child' ),
						esc_html( ORSITE_GH_BRANCH ),
						esc_html( implode( ' — ', $branch_parts ) ),
						! empty( $branch['sha'] ) ? esc_html( ' (' . $branch['sha'] . ')' ) : ''
					);
				}
				?>
			</p>
		<?php endif; ?>
		<p>
			<form method="post" action="" style="display:inline">
				<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput -- nonce field. ?>
				<button type="submit" name="orsite_gh_action" value="update_all" class="button orsite-btn" onclick="return confirm('<?php echo esc_js( __( 'هر دو قالب با فایل‌های تغییریافته‌ی آخرین انتشار به‌روز شوند؟', 'lax-theme-child' ) ); ?>')">
					<?php esc_html_e( 'بروزرسانی هر دو قالب', 'lax-theme-child' ); ?>
				</button>
				<a class="button orsite-btn-danger" href="<?php echo esc_url( $release_data ? $release_data['html_url'] : ORSITE_GH_WEB . '/releases' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'صفحه‌ی Release در گیت‌هاب', 'lax-theme-child' ); ?></a>
			</form>
		</p>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'آخرین انتشار گیت‌هاب', 'lax-theme-child' ); ?></h2>
		<?php if ( $release_data ) : ?>
			<p>
				<strong><?php echo esc_html( $release_data['tag_name'] ); ?></strong>
				<?php if ( $release_data['name'] ) : ?>
					— <?php echo esc_html( $release_data['name'] ); ?>
				<?php endif; ?>
				<br />
				<span class="orsite-meta">
					<?php
					if ( $release_data['published_at'] ) {
						$ts = strtotime( $release_data['published_at'] );
						printf( esc_html__( 'تاریخ انتشار: %s', 'lax-theme-child' ), esc_html( wp_date( 'Y-m-d H:i', $ts ) ) );
					}
					?>
				</span>
			</p>
			<?php if ( ! empty( $remote['changelog'] ) && is_array( $remote['changelog'] ) ) : ?>
				<?php foreach ( $remote['changelog'] as $section => $bullets ) : ?>
					<p><strong><?php echo esc_html( is_string( $section ) ? $section : '' ); ?></strong></p>
					<ul><?php foreach ( (array) $bullets as $b ) : ?><li><?php echo esc_html( $b ); ?></li><?php endforeach; ?></ul>
				<?php endforeach; ?>
			<?php elseif ( $release_data['body'] ) : ?>
				<p class="orsite-meta"><?php echo esc_html( wp_html_excerpt( $release_data['body'], 600, '…' ) ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p class="orsite-status-bad"><?php echo esc_html( $release_err ? $release_err : __( 'هنوز انتشاری دریافت نشده است.', 'lax-theme-child' ) ); ?></p>
		<?php endif; ?>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'فایل‌های تغییریافته (پیش‌نمایش)', 'lax-theme-child' ); ?></h2>
		<?php if ( empty( $diff['themes'] ) ) : ?>
			<p class="orsite-meta"><?php esc_html_e( 'هنوز بررسی‌ای انجام نشده؛ دکمه‌ی «بررسی اتصال و دریافت آخرین انتشار» را بزنید.', 'lax-theme-child' ); ?></p>
		<?php else : ?>
			<p class="orsite-meta">
				<?php
				if ( ! empty( $diff['time'] ) ) {
					printf( esc_html__( 'بررسی: %s پیش — انتشار %s', 'lax-theme-child' ), esc_html( human_time_diff( (int) $diff['time'] ) ), esc_html( $diff['tag'] ) );
				}
				?>
			</p>
			<?php foreach ( array( 'child', 'parent' ) as $key ) : ?>
				<?php if ( empty( $diff['themes'][ $key ] ) ) { continue; } ?>
				<?php $entry = $diff['themes'][ $key ]; ?>
				<h3 style="margin:12px 0 4px"><?php echo esc_html( $entry['name'] ); ?> <span class="orsite-meta">(<?php echo esc_html( $entry['local'] . ' ← ' . ( $entry['remote'] ? $entry['remote'] : '?' ) ); ?>)</span></h3>
				<?php if ( ! empty( $entry['error'] ) ) : ?>
					<p class="orsite-status-bad"><?php echo esc_html( $entry['error'] ); ?></p>
				<?php else : ?>
					<p>
						<strong><?php esc_html_e( 'تغییریافته:', 'lax-theme-child' ); ?></strong> <?php echo (int) count( $entry['changed'] ); ?> |
						<strong><?php esc_html_e( 'تازه:', 'lax-theme-child' ); ?></strong> <?php echo (int) count( $entry['new'] ); ?> |
						<strong><?php esc_html_e( 'بدون تغییر:', 'lax-theme-child' ); ?></strong> <?php echo (int) $entry['unchanged']; ?> |
						<strong><?php esc_html_e( 'منسوخ (حذف نمی‌شوند):', 'lax-theme-child' ); ?></strong> <?php echo (int) count( $entry['stale'] ); ?>
					</p>
					<?php if ( $entry['changed'] ) : ?>
						<p class="orsite-meta"><?php esc_html_e( 'فایل‌هایی که با زدن دکمه‌ی بروزرسانی جایگزین می‌شوند:', 'lax-theme-child' ); ?></p>
						<?php echo orsite_gh_render_file_list( $entry['changed'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
					<?php endif; ?>
					<?php if ( $entry['new'] ) : ?>
						<p class="orsite-meta"><?php esc_html_e( 'فایل‌های تازه:', 'lax-theme-child' ); ?></p>
						<?php echo orsite_gh_render_file_list( $entry['new'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
					<?php endif; ?>
					<?php if ( $entry['stale'] ) : ?>
						<p class="orsite-meta"><?php esc_html_e( 'فایل‌های منسوخ (خودکار حذف نمی‌شوند):', 'lax-theme-child' ); ?></p>
						<?php echo orsite_gh_render_file_list( $entry['stale'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'آخرین بروزرسانی', 'lax-theme-child' ); ?></h2>
		<?php if ( ! empty( $last_run['errors'] ) ) : ?>
			<?php foreach ( $last_run['errors'] as $which => $msg ) : ?>
				<p class="orsite-status-bad"><?php echo esc_html( $which . ': ' . $msg ); ?></p>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php if ( empty( $last_update ) ) : ?>
			<p class="orsite-meta"><?php esc_html_e( 'هنوز بروزرسانی‌ای از این صفحه انجام نشده است.', 'lax-theme-child' ); ?></p>
		<?php else : ?>
			<?php foreach ( array( 'child', 'parent' ) as $key ) : ?>
				<?php if ( empty( $last_update[ $key ] ) ) { continue; } ?>
				<?php $log = $last_update[ $key ]; ?>
				<h3 style="margin:12px 0 4px">
					<?php echo esc_html( $log['theme']['name'] ); ?>
					<?php if ( ! empty( $log['auto'] ) ) : ?>
						<span class="orsite-status-new"><?php esc_html_e( 'خودکار', 'lax-theme-child' ); ?></span>
					<?php endif; ?>
				</h3>
				<p class="orsite-meta">
					<?php
					$ver = ! empty( $log['to'] ) ? sprintf( '%s → %s', $log['from'], $log['to'] ) : $log['release_tag'];
					printf(
						/* translators: 1: version or tag 2: time */
						esc_html__( 'از انتشار %1$s — %2$s پیش', 'lax-theme-child' ),
						esc_html( $ver ),
						esc_html( human_time_diff( (int) $log['time'] ) )
					);
					?>
				</p>
				<p>
					<strong><?php esc_html_e( 'به‌روزشده:', 'lax-theme-child' ); ?></strong> <?php echo (int) count( $log['updated'] ); ?> |
					<strong><?php esc_html_e( 'بدون تغییر:', 'lax-theme-child' ); ?></strong> <?php echo (int) count( $log['unchanged'] ); ?>
				</p>
				<?php if ( $log['updated'] ) : ?>
					<?php echo orsite_gh_render_file_list( $log['updated'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'راهنما', 'lax-theme-child' ); ?></h2>
		<ul style="margin:0 18px 0 0; padding:0">
			<li><?php esc_html_e( 'با هر انتشار تازه در گیت‌هاب، دکمه‌ی «بررسی» را بزنید؛ فایل‌های تغییریافته نمایش داده می‌شوند و با «بروزرسانی» فقط همان‌ها روی وردپرس جایگزین می‌شوند. اگر «بروزرسانی خودکار» فعال باشد، همین کار دو بار در روز بدون دخالت شما انجام می‌شود.', 'lax-theme-child' ); ?></li>
			<li><?php esc_html_e( 'فایل‌های اضافه‌ای که خودتان روی هاست گذاشته‌اید هرگز خودکار حذف نمی‌شوند.', 'lax-theme-child' ); ?></li>
			<li><?php esc_html_e( 'برای امنیت بیشتر، توکن را به‌جای این صفحه در wp-config.php با ثابت GITHUB_ACCESS_TOKEN قرار دهید.', 'lax-theme-child' ); ?></li>
			<li><?php esc_html_e( 'قالب‌ها را از همان صفحه‌ی Release گیت‌هاب می‌توانید دستی هم نصب کنید.', 'lax-theme-child' ); ?></li>
		</ul>
	</div>
	</div>
	<?php
}

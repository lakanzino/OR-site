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
 * Release selection (since 0.12.1): every release returned by the API is
 * parsed and the HIGHEST VERSION wins via version_compare(). The list order of
 * `GET /repos/:owner/:repo/releases` is not guaranteed to be newest-first —
 * for this repository it returns `child-v0.9.1` before `child-v0.12.0`, which
 * made older builds report the installed theme as "up to date" forever.
 *
 * Connection state is reported honestly: a failed call never hides behind the
 * cached release. The dashboard shows "قطع — داده‌ی ذخیره‌شده" with the real
 * HTTP/transport error, and offers a self-test of every GitHub endpoint the
 * updater uses (API, releases.atom, raw, codeload, token validity).
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
if ( ! defined( 'ORSITE_GH_RAW' ) ) {
	define( 'ORSITE_GH_RAW', 'https://raw.githubusercontent.com/lakanzino/OR-site' );
}
if ( ! defined( 'ORSITE_GH_CODELOAD' ) ) {
	define( 'ORSITE_GH_CODELOAD', 'https://codeload.github.com/lakanzino/OR-site' );
}

/* -------------------------------------------------------------------------
 * Token handling
 * ---------------------------------------------------------------------- */

/**
 * Resolve the GitHub token.
 *
 * Precedence: a token saved in this dashboard while "override" is on, then the
 * `GITHUB_ACCESS_TOKEN` constant in wp-config.php, then the saved option.
 *
 * @return string Token or empty string.
 */
function orsite_gh_token() {
	$saved = (string) get_option( 'orsite_gh_token', '' );
	if ( '' !== $saved && orsite_gh_token_override() ) {
		return $saved;
	}
	if ( defined( 'GITHUB_ACCESS_TOKEN' ) && GITHUB_ACCESS_TOKEN ) {
		return (string) GITHUB_ACCESS_TOKEN;
	}
	return $saved;
}

/**
 * Should the token saved in the dashboard win over wp-config.php?
 *
 * @return bool
 */
function orsite_gh_token_override() {
	return '1' === (string) get_option( 'orsite_gh_token_override', '0' );
}

/**
 * Where does the active token come from?
 *
 * @return string `override`, `constant`, `option` or `none`.
 */
function orsite_gh_token_source() {
	$saved = (string) get_option( 'orsite_gh_token', '' );
	if ( '' !== $saved && orsite_gh_token_override() ) {
		return 'override';
	}
	if ( defined( 'GITHUB_ACCESS_TOKEN' ) && GITHUB_ACCESS_TOKEN ) {
		return 'constant';
	}
	return '' !== $saved ? 'option' : 'none';
}

/**
 * Is the active token the one from wp-config.php?
 *
 * @return bool
 */
function orsite_gh_token_is_constant() {
	return 'constant' === orsite_gh_token_source();
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
 * One GET request that keeps everything needed to explain a failure.
 *
 * @param string $url  Absolute URL.
 * @param array  $args Optional: `auth` (bool), `accept` (string), `timeout`
 *                     (int), `limit` (int, bytes) and `token` (string|null).
 * @return array { url, body, code, error, ms, remaining, reset }
 */
function orsite_gh_fetch( $url, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'auth'    => false,
			'accept'  => 'application/vnd.github+json',
			'timeout' => 25,
			'limit'   => 0,
			'token'   => null,
		)
	);

	$token   = ( null === $args['token'] ) ? orsite_gh_token() : (string) $args['token'];
	$headers = array(
		'Accept'               => (string) $args['accept'],
		'User-Agent'           => 'hamyarjarahi-theme-updater',
		'X-GitHub-Api-Version' => '2022-11-28',
	);
	if ( $args['auth'] && '' !== $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$request = array(
		'timeout'     => max( 5, (int) $args['timeout'] ),
		'redirection' => 5,
		'headers'     => $headers,
	);
	if ( (int) $args['limit'] > 0 ) {
		$request['limit_response_size'] = (int) $args['limit'];
	}

	$start    = microtime( true );
	$response = wp_remote_get( $url, $request );
	$out      = array(
		'url'       => $url,
		'body'      => '',
		'code'      => 0,
		'error'     => '',
		'ms'        => 0,
		'remaining' => '',
		'reset'     => 0,
	);

	if ( is_wp_error( $response ) ) {
		$out['error'] = $response->get_error_message();
		$out['ms']    = (int) round( ( microtime( true ) - $start ) * 1000 );
		return $out;
	}

	$out['body'] = (string) wp_remote_retrieve_body( $response );
	$out['code'] = (int) wp_remote_retrieve_response_code( $response );

	$headers_all = wp_remote_retrieve_headers( $response );
	if ( is_object( $headers_all ) && method_exists( $headers_all, 'getAll' ) ) {
		$headers_all = $headers_all->getAll();
	} elseif ( is_object( $headers_all ) ) {
		$headers_all = get_object_vars( $headers_all );
	}
	if ( is_array( $headers_all ) ) {
		$headers_all = array_change_key_case( $headers_all, CASE_LOWER );
		if ( isset( $headers_all['x-ratelimit-remaining'] ) ) {
			$out['remaining'] = (string) $headers_all['x-ratelimit-remaining'];
		}
		if ( isset( $headers_all['x-ratelimit-reset'] ) ) {
			$out['reset'] = (int) $headers_all['x-ratelimit-reset'];
		}
	}

	$out['ms'] = (int) round( ( microtime( true ) - $start ) * 1000 );

	return $out;
}

/**
 * Human readable explanation for a failed GitHub response.
 *
 * @param array $res Result of orsite_gh_fetch().
 * @return string
 */
function orsite_gh_fetch_error( $res ) {
	$code = isset( $res['code'] ) ? (int) $res['code'] : 0;

	if ( ! empty( $res['error'] ) ) {
		/* translators: %s: transport error message */
		return sprintf( __( 'اتصال شبکه برقرار نشد: %s', 'lax-theme-child' ), $res['error'] );
	}

	$detail = '';
	if ( ! empty( $res['body'] ) ) {
		$json = json_decode( (string) $res['body'], true );
		if ( is_array( $json ) && ! empty( $json['message'] ) ) {
			$detail = (string) $json['message'];
		}
	}

	switch ( $code ) {
		case 401:
			$msg = __( 'توکن گیت‌هاب نامعتبر یا منقضی است (HTTP 401). توکن را اصلاح یا پاک کنید.', 'lax-theme-child' );
			break;
		case 403:
		case 429:
			$msg = __( 'گیت‌هاب درخواست را محدود کرده است (محدودیت نرخ یا دسترسی). چند دقیقه بعد دوباره تلاش کنید.', 'lax-theme-child' );
			break;
		case 404:
			$msg = __( 'مخزن یا فایل در گیت‌هاب پیدا نشد (HTTP 404). برای مخزن خصوصی، توکن لازم است.', 'lax-theme-child' );
			break;
		case 0:
			$msg = __( 'پاسخی از گیت‌هاب دریافت نشد (خطای شبکه یا مسدود بودن دامنه روی هاست).', 'lax-theme-child' );
			break;
		default:
			/* translators: %d: HTTP status code */
			$msg = sprintf( __( 'گیت‌هاب کد HTTP %d برگرداند.', 'lax-theme-child' ), $code );
	}

	if ( '' !== $detail ) {
		$msg .= ' (' . $detail . ')';
	}
	return $msg;
}

/**
 * Legacy helper: GET a URL and return body + code (WP_Error on transport
 * failure). Used by the download paths.
 *
 * @param string $url     Absolute URL.
 * @param bool   $is_api  Send API headers + token.
 * @param int    $timeout Seconds.
 * @return array|WP_Error { body, code, headers }
 */
function orsite_gh_http( $url, $is_api = true, $timeout = 30 ) {
	$res = orsite_gh_fetch(
		$url,
		array(
			'auth'    => (bool) $is_api,
			'accept'  => $is_api ? 'application/vnd.github+json' : '*/*',
			'timeout' => (int) $timeout,
		)
	);
	if ( '' !== $res['error'] ) {
		return new WP_Error( 'orsite_gh_transport', $res['error'] );
	}
	return array(
		'body'    => $res['body'],
		'code'    => $res['code'],
		'headers' => array(),
	);
}

/* -------------------------------------------------------------------------
 * Release discovery
 * ---------------------------------------------------------------------- */

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
 * Build the candidate list from raw release objects.
 *
 * Ordered by version (highest first) — never by the order the API happened to
 * return, because `/releases` is not guaranteed to be newest-first and used to
 * hand back an old tag first, which made the updater offer a stale release.
 *
 * @param array $items Raw release objects (API) or normalized atom items.
 * @return array
 */
function orsite_gh_release_candidates( $items ) {
	$cands = array();

	foreach ( (array) $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		if ( ! empty( $item['draft'] ) || ! empty( $item['prerelease'] ) ) {
			continue;
		}
		$tag = isset( $item['tag_name'] ) ? (string) $item['tag_name'] : '';
		if ( '' === $tag ) {
			continue;
		}
		$child  = orsite_gh_parse_tag_version( $tag, 'child-v' );
		$parent = ( null === $child ) ? orsite_gh_parse_tag_version( $tag, 'parent-v' ) : null;
		if ( null === $child && null === $parent ) {
			continue;
		}
		$when    = isset( $item['published_at'] ) ? (string) $item['published_at'] : '';
		$cands[] = array(
			'tag'     => $tag,
			'child'   => $child,
			'parent'  => $parent,
			'version' => (string) ( null !== $child ? $child : $parent ),
			'family'  => ( null !== $child ) ? 'child' : 'parent',
			'when'    => $when,
			'order'   => ( '' !== $when ) ? (int) strtotime( $when ) : 0,
			'item'    => $item,
		);
	}

	usort(
		$cands,
		static function ( $a, $b ) {
			$cmp = version_compare( $b['version'], $a['version'] );
			if ( 0 !== $cmp ) {
				return ( $cmp > 0 ) ? 1 : -1;
			}
			return $b['order'] <=> $a['order'];
		}
	);

	return $cands;
}

/**
 * Pick the highest child release, falling back to the highest parent release.
 *
 * @param array $cands Candidates from orsite_gh_release_candidates().
 * @return array|null
 */
function orsite_gh_pick_candidate( $cands ) {
	$fallback = null;
	foreach ( (array) $cands as $cand ) {
		if ( 'child' === $cand['family'] ) {
			return $cand;
		}
		if ( null === $fallback ) {
			$fallback = $cand;
		}
	}
	return $fallback;
}

/**
 * Strip candidate entries down to what the dashboard needs to store.
 *
 * @param array $cands Candidates.
 * @return array
 */
function orsite_gh_public_candidates( $cands ) {
	$out = array();
	foreach ( (array) $cands as $cand ) {
		$out[] = array(
			'tag'     => $cand['tag'],
			'version' => $cand['version'],
			'family'  => $cand['family'],
			'when'    => $cand['when'],
		);
		if ( count( $out ) >= 12 ) {
			break;
		}
	}
	return $out;
}

/**
 * Parse the public `releases.atom` feed into release objects.
 *
 * This channel needs no API and no token, so it still works on hosts where
 * api.github.com is blocked.
 *
 * @param string $body Atom XML.
 * @return array
 */
function orsite_gh_parse_atom_releases( $body ) {
	$items = array();
	if ( ! preg_match_all( '#<entry>(.*?)</entry>#s', (string) $body, $matches ) ) {
		return $items;
	}

	foreach ( $matches[1] as $entry ) {
		$tag = '';
		if ( preg_match( '#Repository/\d+/([^<]+)#', $entry, $m ) ) {
			$tag = trim( html_entity_decode( $m[1] ) );
		}
		if ( '' === $tag ) {
			continue;
		}

		$name = '';
		if ( preg_match( '#<title>(.*?)</title>#s', $entry, $m ) ) {
			$name = trim( html_entity_decode( wp_strip_all_tags( $m[1] ) ) );
		}

		$when = '';
		if ( preg_match( '#<updated>(.*?)</updated>#s', $entry, $m ) ) {
			$when = trim( $m[1] );
		}

		$link = ORSITE_GH_WEB . '/releases/tag/' . rawurlencode( $tag );
		if ( preg_match( '#<link[^>]+href="([^"]+)"#', $entry, $m ) ) {
			$link = $m[1];
		}

		$text = '';
		if ( preg_match( '#<content[^>]*>(.*?)</content>#s', $entry, $m ) ) {
			$text = trim( wp_strip_all_tags( html_entity_decode( $m[1] ) ) );
		}

		$items[] = array(
			'tag_name'     => $tag,
			'name'         => $name,
			'published_at' => $when,
			'html_url'     => $link,
			'body'         => $text,
			'assets'       => array(),
		);
	}

	return $items;
}

/**
 * Repository copy of a release file (used when no attachment is uploaded).
 *
 * @param string $tag  Release tag.
 * @param string $name File name.
 * @return array
 */
function orsite_gh_repo_asset( $tag, $name ) {
	return array(
		'name'                 => $name,
		'browser_download_url' => ORSITE_GH_WEB . '/raw/' . rawurlencode( $tag ) . '/releases/' . rawurlencode( $name ),
		'url'                  => ORSITE_GH_API . '/contents/releases/' . rawurlencode( $name ) . '?ref=' . rawurlencode( $tag ),
		'accept'               => 'application/vnd.github.raw+json',
		'size'                 => 0,
		'tag'                  => $tag,
	);
}

/**
 * Normalize one release: real attachments first, repository copies after.
 *
 * @param array  $item   Raw release object.
 * @param string $source `api` or `atom`.
 * @return array
 */
function orsite_gh_normalize_release( $item, $source = 'api' ) {
	$tag  = isset( $item['tag_name'] ) ? (string) $item['tag_name'] : '';
	$norm = array(
		'tag_name'     => $tag,
		'name'         => isset( $item['name'] ) ? (string) $item['name'] : '',
		'published_at' => isset( $item['published_at'] ) ? (string) $item['published_at'] : '',
		'html_url'     => ! empty( $item['html_url'] ) ? (string) $item['html_url'] : ORSITE_GH_WEB . '/releases/tag/' . rawurlencode( $tag ),
		'body'         => isset( $item['body'] ) ? (string) $item['body'] : '',
		'assets'       => array(),
		'_source'      => (string) $source,
		'_stale'       => false,
		'_error'       => '',
		'_fetched_at'  => time(),
	);

	$have = array();
	if ( ! empty( $item['assets'] ) && is_array( $item['assets'] ) ) {
		foreach ( $item['assets'] as $asset ) {
			if ( empty( $asset['name'] ) ) {
				continue;
			}
			$aname            = (string) $asset['name'];
			$have[ $aname ]   = true;
			$norm['assets'][] = array(
				'name'                 => $aname,
				'browser_download_url' => isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '',
				'url'                  => isset( $asset['url'] ) ? (string) $asset['url'] : '',
				'accept'               => 'application/octet-stream',
				'size'                 => isset( $asset['size'] ) ? (int) $asset['size'] : 0,
				'tag'                  => $tag,
			);
		}
	}

	if ( '' !== $tag ) {
		foreach ( array( ORSITE_GH_CHILD_ASSET, ORSITE_GH_PARENT_ASSET, ORSITE_GH_MANIFEST_ASSET ) as $expected ) {
			if ( isset( $have[ $expected ] ) ) {
				continue;
			}
			$norm['assets'][] = orsite_gh_repo_asset( $tag, $expected );
		}
	}

	return $norm;
}

/**
 * Remember how the last GitHub call went (drives the honest status card).
 *
 * @param array $state { ok, http, error, source, tag }.
 */
function orsite_gh_record_state( $state ) {
	$prev = (array) get_option( 'orsite_gh_last_fetch', array() );
	$now  = time();
	$same = isset( $prev['ok'] )
		&& (bool) $prev['ok'] === (bool) $state['ok']
		&& ( isset( $prev['tag'] ) ? (string) $prev['tag'] : '' ) === (string) $state['tag']
		&& ( isset( $prev['http'] ) ? (int) $prev['http'] : 0 ) === (int) $state['http'];

	if ( $same && ( $now - (int) ( isset( $prev['time'] ) ? $prev['time'] : 0 ) ) < 600 ) {
		return;
	}

	$state['time'] = $now;
	update_option( 'orsite_gh_last_fetch', $state, false );
}

/**
 * Ask GitHub for the newest theme release.
 *
 * Order of trust: REST API, then the public Atom feed. Every item is parsed and
 * the HIGHEST VERSION wins — the first item of the list is never trusted.
 *
 * @return array { release, error, http, source, candidates, tried, time }
 */
function orsite_gh_fetch_remote_release() {
	$out = array(
		'release'    => null,
		'error'      => '',
		'http'       => 0,
		'source'     => '',
		'candidates' => array(),
		'tried'      => array(),
		'time'       => time(),
	);

	$api_url       = ORSITE_GH_API . '/releases?per_page=100';
	$res           = orsite_gh_fetch( $api_url, array( 'auth' => true, 'timeout' => 15 ) );
	$out['tried'][] = array(
		'label' => __( 'API گیت‌هاب — فهرست انتشارها', 'lax-theme-child' ),
		'url'   => $api_url,
		'code'  => (int) $res['code'],
		'ms'    => (int) $res['ms'],
		'ok'    => ( 200 === (int) $res['code'] && '' === $res['error'] ),
		'error' => ( 200 === (int) $res['code'] && '' === $res['error'] ) ? '' : orsite_gh_fetch_error( $res ),
	);

	if ( 200 === (int) $res['code'] && '' === $res['error'] ) {
		$list = json_decode( (string) $res['body'], true );
		if ( is_array( $list ) ) {
			$cands = orsite_gh_release_candidates( $list );
			if ( $cands ) {
				$pick              = orsite_gh_pick_candidate( $cands );
				$out['release']    = orsite_gh_normalize_release( $pick['item'], 'api' );
				$out['source']     = 'api';
				$out['http']       = 200;
				$out['candidates'] = $cands;
				return $out;
			}
			$out['error'] = __( 'در مخزن هیچ انتشاری با تگ child-vX.Y.Z پیدا نشد.', 'lax-theme-child' );
		} else {
			$out['error'] = __( 'خروجی JSON فهرست انتشارها خوانا نبود.', 'lax-theme-child' );
		}
	} else {
		$out['http']  = (int) $res['code'];
		$out['error'] = orsite_gh_fetch_error( $res );
	}

	// Fallback channel: public Atom feed on github.com (no API, no token).
	$atom_url        = ORSITE_GH_WEB . '/releases.atom';
	$atom            = orsite_gh_fetch(
		$atom_url,
		array(
			'auth'    => false,
			'accept'  => 'application/atom+xml, application/xml;q=0.9, */*;q=0.8',
			'timeout' => 12,
		)
	);
	$out['tried'][] = array(
		'label' => __( 'خوراک Releases در github.com', 'lax-theme-child' ),
		'url'   => $atom_url,
		'code'  => (int) $atom['code'],
		'ms'    => (int) $atom['ms'],
		'ok'    => ( 200 === (int) $atom['code'] && '' === $atom['error'] ),
		'error' => ( 200 === (int) $atom['code'] && '' === $atom['error'] ) ? '' : orsite_gh_fetch_error( $atom ),
	);

	if ( 200 === (int) $atom['code'] && '' === $atom['error'] ) {
		$cands = orsite_gh_release_candidates( orsite_gh_parse_atom_releases( $atom['body'] ) );
		if ( $cands ) {
			$pick              = orsite_gh_pick_candidate( $cands );
			$out['release']    = orsite_gh_normalize_release( $pick['item'], 'atom' );
			$out['source']     = 'atom';
			$out['http']       = 200;
			$out['candidates'] = $cands;
			$out['error']      = '';
			return $out;
		}
		$out['error'] .= ' ' . __( 'خوراک Releases هم انتشاری برنگرداند.', 'lax-theme-child' );
	}

	return $out;
}

/**
 * Newest theme release, cached for one hour and marked when it goes stale.
 *
 * @param bool $force Bypass the cache.
 * @return array|WP_Error Normalized release, with `_stale` / `_error` flags.
 */
function orsite_gh_release( $force = false ) {
	$cache = get_option( 'orsite_gh_release_cache' );
	$valid = is_array( $cache ) && ! empty( $cache['data'] ) && is_array( $cache['data'] ) && ! empty( $cache['time'] );

	if ( ! $force && $valid && ( time() - (int) $cache['time'] ) < HOUR_IN_SECONDS ) {
		$cached                = $cache['data'];
		$cached['_stale']      = false;
		$cached['_error']      = '';
		$cached['_fetched_at'] = (int) $cache['time'];
		$cached['_source']     = isset( $cache['source'] ) ? (string) $cache['source'] : 'cache';
		return $cached;
	}

	// Back off for ten minutes after a failure so an unreachable host does not
	// turn every dashboard page load into two network timeouts.
	$state = (array) get_option( 'orsite_gh_last_fetch', array() );
	if ( ! $force && isset( $state['ok'], $state['time'] ) && ! $state['ok'] && ( time() - (int) $state['time'] ) < 10 * MINUTE_IN_SECONDS ) {
		if ( $valid ) {
			$stale                = $cache['data'];
			$stale['_stale']      = true;
			$stale['_fetched_at'] = (int) $cache['time'];
			$stale['_source']     = isset( $cache['source'] ) ? (string) $cache['source'] : 'cache';
			$stale['_error']      = isset( $state['error'] ) ? (string) $state['error'] : '';
			$stale['_http']       = isset( $state['http'] ) ? (int) $state['http'] : 0;
			return $stale;
		}
		return new WP_Error(
			'orsite_gh_connection',
			isset( $state['error'] ) && '' !== (string) $state['error'] ? (string) $state['error'] : __( 'اتصال به گیت‌هاب برقرار نشد.', 'lax-theme-child' )
		);
	}

	$attempt = orsite_gh_fetch_remote_release();

	if ( ! empty( $attempt['release'] ) ) {
		update_option(
			'orsite_gh_release_cache',
			array(
				'time'       => time(),
				'source'     => $attempt['source'],
				'data'       => $attempt['release'],
				'candidates' => orsite_gh_public_candidates( $attempt['candidates'] ),
				'tried'      => $attempt['tried'],
			),
			false
		);
		orsite_gh_record_state(
			array(
				'ok'     => true,
				'http'   => (int) $attempt['http'],
				'error'  => '',
				'source' => (string) $attempt['source'],
				'tag'    => (string) $attempt['release']['tag_name'],
			)
		);
		return $attempt['release'];
	}

	orsite_gh_record_state(
		array(
			'ok'     => false,
			'http'   => (int) $attempt['http'],
			'error'  => (string) $attempt['error'],
			'source' => '',
			'tag'    => '',
		)
	);

	if ( $valid ) {
		$stale                = $cache['data'];
		$stale['_stale']      = true;
		$stale['_fetched_at'] = (int) $cache['time'];
		$stale['_source']     = isset( $cache['source'] ) ? (string) $cache['source'] : 'cache';
		$stale['_error']      = (string) $attempt['error'];
		$stale['_http']       = (int) $attempt['http'];
		return $stale;
	}

	return new WP_Error(
		'orsite_gh_connection',
		'' !== $attempt['error'] ? (string) $attempt['error'] : __( 'اتصال به گیت‌هاب برقرار نشد.', 'lax-theme-child' )
	);
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
 * Download a release file to a temporary path.
 *
 * Tries in order: the release attachment, the copy committed at the release
 * tag on raw.githubusercontent.com, the contents API, and finally the whole
 * repository ZIP of that tag (codeload) — so an update still works when one of
 * the GitHub domains is unreachable from the host.
 *
 * @param array  $asset Asset array from orsite_gh_find_asset().
 * @param string $tag   Release tag, used by the fallbacks.
 * @return string|WP_Error Path to the downloaded temp file.
 */
function orsite_gh_download_asset( $asset, $tag = '' ) {
	$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
	$tag  = ( '' !== $tag ) ? (string) $tag : ( isset( $asset['tag'] ) ? (string) $asset['tag'] : '' );

	$sources = array();
	if ( ! empty( $asset['browser_download_url'] ) ) {
		$sources[] = array(
			'url'     => $asset['browser_download_url'],
			'auth'    => false,
			'accept'  => '*/*',
			'timeout' => 120,
		);
	}
	if ( '' !== $tag && '' !== $name ) {
		$sources[] = array(
			'url'     => ORSITE_GH_RAW . '/' . rawurlencode( $tag ) . '/releases/' . rawurlencode( $name ),
			'auth'    => false,
			'accept'  => '*/*',
			'timeout' => 120,
		);
	}
	if ( ! empty( $asset['url'] ) ) {
		$sources[] = array(
			'url'     => $asset['url'],
			'auth'    => true,
			'accept'  => ! empty( $asset['accept'] ) ? (string) $asset['accept'] : 'application/octet-stream',
			'timeout' => 120,
		);
	}

	$body = null;
	foreach ( $sources as $source ) {
		$res = orsite_gh_fetch(
			$source['url'],
			array(
				'auth'    => $source['auth'],
				'accept'  => $source['accept'],
				'timeout' => $source['timeout'],
			)
		);
		if ( 200 === (int) $res['code'] && '' !== $res['body'] ) {
			$body = $res['body'];
			break;
		}
	}

	if ( null === $body && '' !== $tag ) {
		// Last resort: the repository ZIP of the tag (contains themes/<slug>/).
		$res = orsite_gh_fetch(
			ORSITE_GH_CODELOAD . '/zip/refs/tags/' . rawurlencode( $tag ),
			array(
				'accept'  => 'application/zip, */*',
				'timeout' => 180,
			)
		);
		if ( 200 === (int) $res['code'] && '' !== $res['body'] ) {
			$body = $res['body'];
		}
	}

	if ( null === $body || '' === $body ) {
		return new WP_Error(
			'orsite_gh_download',
			sprintf(
				/* translators: %s: asset file name */
				__( 'دانلود فایل %s از گیت‌هاب ناموفق بود.', 'lax-theme-child' ),
				'' !== $name ? $name : 'ZIP'
			)
		);
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
 * Fetch `releases/orsite-release.json` for one tag from the first reachable
 * source (raw, github.com/raw, contents API).
 *
 * @param string $tag Release tag.
 * @return array|null
 */
function orsite_gh_fetch_manifest( $tag ) {
	$tag     = (string) $tag;
	$name    = ORSITE_GH_MANIFEST_ASSET;
	$sources = array(
		array(
			'url'    => ORSITE_GH_RAW . '/' . rawurlencode( $tag ) . '/releases/' . rawurlencode( $name ),
			'auth'   => false,
			'accept' => 'application/json, text/plain, */*',
		),
		array(
			'url'    => ORSITE_GH_WEB . '/raw/' . rawurlencode( $tag ) . '/releases/' . rawurlencode( $name ),
			'auth'   => false,
			'accept' => 'application/json, text/plain, */*',
		),
		array(
			'url'    => ORSITE_GH_API . '/contents/releases/' . rawurlencode( $name ) . '?ref=' . rawurlencode( $tag ),
			'auth'   => true,
			'accept' => 'application/vnd.github.raw+json',
		),
	);

	foreach ( $sources as $source ) {
		$res = orsite_gh_fetch(
			$source['url'],
			array(
				'auth'    => $source['auth'],
				'accept'  => $source['accept'],
				'timeout' => 20,
			)
		);
		if ( 200 !== (int) $res['code'] || '' === trim( (string) $res['body'] ) ) {
			continue;
		}
		$json = json_decode( (string) $res['body'], true );
		if ( is_array( $json ) ) {
			return $json;
		}
	}
	return null;
}

/**
 * Remote versions from the release manifest, falling back to tag parsing.
 *
 * The manifest is cached per tag for an hour so opening the dashboard does not
 * hit GitHub on every page load.
 *
 * @param array $release Normalized release.
 * @return array { child, parent, changelog, source, error }
 */
function orsite_gh_remote_versions( $release ) {
	$tag = isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '';
	$out = array(
		'child'     => orsite_gh_parse_tag_version( $tag, 'child-v' ),
		'parent'    => orsite_gh_parse_tag_version( $tag, 'parent-v' ),
		'changelog' => array(),
		'source'    => 'tag',
		'error'     => '',
	);

	if ( '' === $tag ) {
		return $out;
	}

	$cache = (array) get_option( 'orsite_gh_manifest_cache', array() );
	if ( isset( $cache[ $tag ]['time'], $cache[ $tag ]['data'] ) && ( time() - (int) $cache[ $tag ]['time'] ) < HOUR_IN_SECONDS ) {
		return (array) $cache[ $tag ]['data'];
	}

	$json = orsite_gh_fetch_manifest( $tag );
	if ( is_array( $json ) ) {
		if ( isset( $json['child']['version'] ) ) {
			$out['child'] = (string) $json['child']['version'];
		}
		if ( isset( $json['parent']['version'] ) ) {
			$out['parent'] = (string) $json['parent']['version'];
		}
		if ( isset( $json['changelog'] ) && is_array( $json['changelog'] ) ) {
			$out['changelog'] = $json['changelog'];
		}
		$out['source'] = 'manifest';
	} else {
		$out['error'] = __( 'خواندن مانیفست انتشار ناموفق بود؛ نسخه از روی تگ حساب شد.', 'lax-theme-child' );
	}

	$cache[ $tag ] = array(
		'time' => time(),
		'data' => $out,
	);
	if ( count( $cache ) > 5 ) {
		$cache = array_slice( $cache, -5, null, true );
	}
	update_option( 'orsite_gh_manifest_cache', $cache, false );

	return $out;
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
 * @param string $slug     Expected theme folder name (used when the ZIP is a
 *                         full repository archive with a themes/ folder).
 * @return array|WP_Error { root, tmpdir }
 */
function orsite_updater_extract( $zip_path, $slug = '' ) {
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

	$root = orsite_updater_find_root( $tmpdir, $slug );
	if ( '' === $root ) {
		orsite_updater_rrmdir( $tmpdir );
		return new WP_Error( 'orsite_zip_theme', __( 'فایل ZIP ساختار قالب وردپرس را ندارد (style.css پیدا نشد).', 'lax-theme-child' ) );
	}

	return array(
		'root'   => $root,
		'tmpdir' => $tmpdir,
	);
}

/**
 * Find the theme root inside an extracted package.
 *
 * Handles both a theme-only ZIP (`lax-theme-child/style.css`) and a full
 * repository archive (`OR-site-<sha>/themes/lax-theme-child/style.css`).
 *
 * @param string $tmpdir Extraction directory.
 * @param string $slug   Theme folder name.
 * @return string Absolute theme root or an empty string.
 */
function orsite_updater_find_root( $tmpdir, $slug = '' ) {
	$slug       = ( '' !== $slug ) ? $slug : 'lax-theme-child';
	$candidates = array();
	$entries    = array_values( array_diff( (array) scandir( $tmpdir ), array( '.', '..' ) ) );

	$tops = array( $tmpdir );
	if ( 1 === count( $entries ) && is_dir( $tmpdir . '/' . $entries[0] ) ) {
		$tops[] = $tmpdir . '/' . $entries[0];
	}
	foreach ( $tops as $top ) {
		$candidates[] = $top;
		$candidates[] = $top . '/' . $slug;
		$candidates[] = $top . '/themes/' . $slug;
	}

	foreach ( $candidates as $path ) {
		if ( is_dir( $path ) && file_exists( $path . '/style.css' ) ) {
			return $path;
		}
	}
	return '';
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

	$zip = orsite_gh_download_asset( $asset, isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '' );
	if ( is_wp_error( $zip ) ) {
		return $zip;
	}

	$slug    = 'parent' === $which ? 'lax-theme' : 'lax-theme-child';
	$extract = orsite_updater_extract( $zip, $slug );
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
	$release = orsite_gh_release( true );
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

	return $summary;
}

/* -------------------------------------------------------------------------
 * Diagnostics
 * ---------------------------------------------------------------------- */

/**
 * One self-test row for the connectivity table.
 *
 * @param string $key   Stable identifier.
 * @param string $label Human label.
 * @param string $url   URL to test.
 * @param array  $args  Arguments for orsite_gh_fetch().
 * @return array { key, label, url, code, ms, ok, error, remaining, detail, body }
 */
function orsite_gh_diag_row( $key, $label, $url, $args ) {
	$res = orsite_gh_fetch( $url, $args );
	$ok  = ( 200 === (int) $res['code'] && '' === $res['error'] );

	// A capped download may abort mid-transfer; reaching the host is enough.
	if ( ! $ok && 'codeload' === $key && false !== stripos( (string) $res['error'], 'outstanding read data' ) ) {
		$ok           = true;
		$res['error'] = '';
	}

	return array(
		'key'       => $key,
		'label'     => $label,
		'url'       => $url,
		'code'      => (int) $res['code'],
		'ms'        => (int) $res['ms'],
		'ok'        => $ok,
		'error'     => $ok ? '' : orsite_gh_fetch_error( $res ),
		'remaining' => (string) $res['remaining'],
		'detail'    => '',
		'body'      => (string) $res['body'],
	);
}

/**
 * Environment values that explain most "cannot reach GitHub" reports.
 *
 * @return array
 */
function orsite_gh_diag_env() {
	global $wp_version;

	$curl = '';
	if ( function_exists( 'curl_version' ) ) {
		$info = curl_version();
		if ( is_array( $info ) && ! empty( $info['version'] ) ) {
			$curl = (string) $info['version'];
		}
	}

	return array(
		'wp'        => (string) $wp_version,
		'php'       => PHP_VERSION,
		'curl'      => $curl,
		'openssl'   => defined( 'OPENSSL_VERSION_TEXT' ) ? (string) OPENSSL_VERSION_TEXT : '',
		'blocked'   => ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) ? 'WP_HTTP_BLOCK_EXTERNAL' : '',
		'proxy'     => ( defined( 'WP_PROXY_HOST' ) && WP_PROXY_HOST ) ? (string) WP_PROXY_HOST : '',
		'sslverify' => ( defined( 'WP_HTTP_SSL_VERIFY' ) && ! WP_HTTP_SSL_VERIFY ) ? 'WP_HTTP_SSL_VERIFY=false' : '',
		'zip'       => class_exists( 'ZipArchive' ) ? 'yes' : 'no',
		'temp'      => is_writable( get_temp_dir() ) ? 'yes' : 'no',
	);
}

/**
 * Run a one-click connectivity self-test and store the result.
 *
 * Covers everything the updater needs: the API, the release feed, the raw file
 * host, codeload, and (when a token exists) whether that token is accepted.
 *
 * @return array { time, token, masked, tests, env, release, fetch, cand }
 */
function orsite_gh_diagnostics() {
	$token = orsite_gh_token();
	$tests = array();

	$repo = orsite_gh_diag_row(
		'repo',
		__( 'مخزن در API گیت‌هاب', 'lax-theme-child' ),
		ORSITE_GH_API,
		array( 'auth' => true, 'timeout' => 20 )
	);
	$json = json_decode( (string) $repo['body'], true );
	if ( is_array( $json ) && ! empty( $json['full_name'] ) ) {
		$visibility     = isset( $json['visibility'] ) ? (string) $json['visibility'] : ( ! empty( $json['private'] ) ? 'private' : 'public' );
		$repo['detail'] = $json['full_name'] . ' · ' . $visibility;
		if ( ! empty( $json['pushed_at'] ) ) {
			$repo['detail'] .= ' · ' . (string) $json['pushed_at'];
		}
	}
	$tests[] = $repo;

	$tests[] = orsite_gh_diag_row(
		'releases',
		__( 'فهرست انتشارها (API)', 'lax-theme-child' ),
		ORSITE_GH_API . '/releases?per_page=5',
		array( 'auth' => true, 'timeout' => 20 )
	);

	$tests[] = orsite_gh_diag_row(
		'atom',
		__( 'خوراک Releases در github.com', 'lax-theme-child' ),
		ORSITE_GH_WEB . '/releases.atom',
		array( 'auth' => false, 'accept' => 'application/atom+xml, */*', 'timeout' => 20 )
	);

	$tests[] = orsite_gh_diag_row(
		'raw',
		__( 'فایل مانیفست (raw.githubusercontent.com)', 'lax-theme-child' ),
		ORSITE_GH_RAW . '/main/releases/' . ORSITE_GH_MANIFEST_ASSET,
		array( 'auth' => false, 'accept' => '*/*', 'timeout' => 20 )
	);

	$tests[] = orsite_gh_diag_row(
		'codeload',
		__( 'بسته‌ی ZIP مخزن (codeload.github.com)', 'lax-theme-child' ),
		ORSITE_GH_CODELOAD . '/zip/refs/heads/main',
		array( 'auth' => false, 'accept' => 'application/zip, */*', 'timeout' => 25, 'limit' => 2048 )
	);

	if ( '' !== $token ) {
		$user = orsite_gh_diag_row(
			'user',
			__( 'اعتبار توکن (GET /user)', 'lax-theme-child' ),
			'https://api.github.com/user',
			array( 'auth' => true, 'timeout' => 20 )
		);
		$json = json_decode( (string) $user['body'], true );
		if ( is_array( $json ) && ! empty( $json['login'] ) ) {
			$user['detail'] = 'login: ' . $json['login'];
			if ( ! empty( $json['type'] ) ) {
				$user['detail'] .= ' · ' . $json['type'];
			}
		}
		$tests[] = $user;
	}

	// Drop the response bodies before storing the report.
	foreach ( $tests as $index => $row ) {
		unset( $tests[ $index ]['body'] );
	}

	$release = orsite_gh_release( true );
	$fetch   = (array) get_option( 'orsite_gh_last_fetch', array() );
	$cache   = (array) get_option( 'orsite_gh_release_cache', array() );

	$report = array(
		'time'    => time(),
		'token'   => orsite_gh_token_source(),
		'masked'  => '' !== $token ? orsite_gh_mask_token( $token ) : '',
		'tests'   => array_values( $tests ),
		'env'     => orsite_gh_diag_env(),
		'release' => is_wp_error( $release )
			? array(
				'ok'    => false,
				'tag'   => '',
				'error' => $release->get_error_message(),
			)
			: array(
				'ok'     => empty( $release['_stale'] ),
				'tag'    => (string) $release['tag_name'],
				'source' => (string) $release['_source'],
				'error'  => (string) $release['_error'],
			),
		'fetch'   => $fetch,
		'cand'    => isset( $cache['candidates'] ) ? (array) $cache['candidates'] : array(),
	);

	update_option( 'orsite_gh_diag', $report, false );
	return $report;
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

	$state = (array) get_option( 'orsite_gh_last_fetch', array() );
	if ( isset( $state['ok'] ) && ! $state['ok'] && ( time() - (int) $state['time'] ) < DAY_IN_SECONDS ) {
		printf(
			'<div class="notice notice-error"><p>⚠️ %1$s <strong>%2$s</strong> — <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'اتصال به گیت‌هاب برقرار نشد؛ وضعیت هاست یا توکن را در صفحه‌ی بروزرسانی گیت‌هاب بررسی کنید:', 'lax-theme-child' ),
			esc_html( (string) ( isset( $state['error'] ) ? $state['error'] : '' ) ),
			esc_url( admin_url( 'admin.php?page=orsite-github-updater' ) ),
			esc_html__( 'تست کامل اتصال', 'lax-theme-child' )
		);
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
			$token = isset( $_POST['orsite_gh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['orsite_gh_token'] ) ) : '';
			if ( '' === $token ) {
				$redirect = add_query_arg( 'orsite_msg', 'token_empty', $page );
				break;
			}

			$constant = defined( 'GITHUB_ACCESS_TOKEN' ) && GITHUB_ACCESS_TOKEN;
			update_option( 'orsite_gh_token', $token, false );
			// A token typed in the dashboard must take effect even when
			// wp-config.php still holds an outdated constant.
			update_option( 'orsite_gh_token_override', $constant ? '1' : '0', false );
			update_option( 'orsite_gh_release_cache', array(), false );
			delete_option( 'orsite_gh_manifest_cache' );

			// Validate immediately so a bad paste is visible right away.
			$check = orsite_gh_fetch( 'https://api.github.com/user', array( 'auth' => true, 'timeout' => 20 ) );
			$json  = json_decode( (string) $check['body'], true );
			update_option(
				'orsite_gh_token_check',
				array(
					'time'  => time(),
					'ok'    => ( 200 === (int) $check['code'] && '' === $check['error'] ),
					'login' => ( is_array( $json ) && ! empty( $json['login'] ) ) ? (string) $json['login'] : '',
					'error' => ( 200 === (int) $check['code'] && '' === $check['error'] ) ? '' : orsite_gh_fetch_error( $check ),
				),
				false
			);

			$redirect = add_query_arg( 'orsite_msg', ( 200 === (int) $check['code'] && '' === $check['error'] ) ? 'token_saved' : 'token_bad', $page );
			break;

		case 'clear_token':
			delete_option( 'orsite_gh_token' );
			delete_option( 'orsite_gh_token_override' );
			delete_option( 'orsite_gh_token_check' );
			update_option( 'orsite_gh_release_cache', array(), false );
			delete_option( 'orsite_gh_manifest_cache' );
			$redirect = add_query_arg( 'orsite_msg', 'token_cleared', $page );
			break;

		case 'diagnose':
			set_time_limit( 180 );
			$diag = orsite_gh_diagnostics();
			$ok   = ! empty( $diag['release']['ok'] );
			foreach ( (array) $diag['tests'] as $test ) {
				if ( 'codeload' !== $test['key'] && empty( $test['ok'] ) ) {
					$ok = false;
					break;
				}
			}
			$redirect = add_query_arg( 'orsite_msg', $ok ? 'diag_ok' : 'diag_fail', $page );
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
			if ( ! empty( $release['_stale'] ) ) {
				update_option( 'orsite_gh_last_check', array( 'time' => time(), 'error' => (string) $release['_error'] ), false );
				$redirect = add_query_arg( 'orsite_msg', 'check_stale', $page );
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
	$report   = array(
		'time'     => time(),
		'tag'      => $release['tag_name'],
		'html_url' => $release['html_url'],
		'body'     => $release['body'],
		'versions' => $versions,
		'themes'   => array(),
	);

	foreach ( array( 'child', 'parent' ) as $which ) {
		$theme = orsite_gh_theme_info( $which );
		$entry = array(
			'local'  => $theme['version'],
			'remote' => isset( $versions[ $which ] ) ? $versions[ $which ] : null,
			'name'   => $theme['name'],
		);
		$plan = orsite_gh_plan_update( $which, $release );
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
	if ( is_wp_error( $release ) ) {
		return array( 'child' => null, 'parent' => null );
	}
	return orsite_gh_remote_versions( $release );
}

/**
 * Auto-check for updates twice a day in the admin (feeds the notice badge and
 * applies higher-version updates automatically when that option is enabled).
 */
function orsite_gh_auto_check() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}
	$last = (int) get_option( 'orsite_gh_auto_check_time', 0 );
	if ( ( time() - $last ) < ( 12 * HOUR_IN_SECONDS ) ) {
		return;
	}
	update_option( 'orsite_gh_auto_check_time', time(), false );

	$release = orsite_gh_release( false );
	if ( is_wp_error( $release ) ) {
		return;
	}
	$report = orsite_gh_build_check_report( $release );
	update_option( 'orsite_gh_last_diff', $report, false );
	orsite_gh_refresh_update_flag( $report );

	// Apply higher-version updates automatically (changed files only).
	orsite_gh_auto_apply_updates( $release );
}
add_action( 'admin_init', 'orsite_gh_auto_check', 20 );

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
	$check = (array) get_option( 'orsite_gh_token_check', array() );
	$login = ! empty( $check['login'] ) ? $check['login'] : '';

	$map = array(
		'token_saved'    => array( 'success', sprintf( /* translators: %s: GitHub login */ __( 'توکن ذخیره و تأیید شد%s.', 'lax-theme-child' ), '' !== $login ? ' (' . $login . ')' : '' ) ),
		'token_bad'      => array( 'error', sprintf( /* translators: %s: error text */ __( 'توکن ذخیره شد ولی گیت‌هاب آن را نپذیرفت: %s', 'lax-theme-child' ), ! empty( $check['error'] ) ? $check['error'] : __( 'بدون پاسخ', 'lax-theme-child' ) ) ),
		'token_empty'    => array( 'warning', __( 'توکنی وارد نشد.', 'lax-theme-child' ) ),
		'token_cleared'  => array( 'success', __( 'توکن ذخیره‌شده پاک شد؛ اگر GITHUB_ACCESS_TOKEN در wp-config.php باشد، همان استفاده می‌شود.', 'lax-theme-child' ) ),
		'settings_saved' => array( 'success', __( 'تنظیمات بروزرسانی خودکار ذخیره شد.', 'lax-theme-child' ) ),
		'check_ok'       => array( 'success', __( 'بررسی انجام شد؛ نتیجه در کارت «فایل‌های تغییریافته» آمده است.', 'lax-theme-child' ) ),
		'check_stale'    => array( 'error', __( 'اتصال به گیت‌هاب برقرار نشد و داده‌ی ذخیره‌شده نمایش داده می‌شود؛ جزئیات در کارت «وضعیت اتصال» است.', 'lax-theme-child' ) ),
		'check_fail'     => array( 'error', __( 'بررسی ناموفق بود؛ جزئیات در کارت «وضعیت اتصال» است.', 'lax-theme-child' ) ),
		'update_ok'      => array( 'success', __( 'بروزرسانی انجام شد؛ لاگ در کارت «آخرین بروزرسانی» است.', 'lax-theme-child' ) ),
		'update_fail'    => array( 'error', __( 'بروزرسانی با خطا همراه بود؛ جزئیات در کارت «آخرین بروزرسانی» است.', 'lax-theme-child' ) ),
		'diag_ok'        => array( 'success', __( 'همه‌ی مسیرهای گیت‌هاب پاسخ دادند؛ جدول تست در کارت «تست اتصال» است.', 'lax-theme-child' ) ),
		'diag_fail'      => array( 'error', __( 'دست‌کم یکی از مسیرهای گیت‌هاب پاسخ نداد؛ جدول تست را ببینید.', 'lax-theme-child' ) ),
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
	$source       = orsite_gh_token_source();
	$release      = orsite_gh_release( false );
	$release_err  = is_wp_error( $release ) ? $release->get_error_message() : '';
	$release_data = is_wp_error( $release ) ? null : $release;
	$remote       = $release_data ? orsite_gh_remote_versions( $release_data ) : array( 'child' => null, 'parent' => null, 'changelog' => array() );
	$stale        = $release_data ? ! empty( $release_data['_stale'] ) : false;
	$channel      = $release_data && ! empty( $release_data['_source'] ) ? (string) $release_data['_source'] : '';
	$cache        = (array) get_option( 'orsite_gh_release_cache', array() );
	$candidates   = isset( $cache['candidates'] ) ? (array) $cache['candidates'] : array();
	$diag         = (array) get_option( 'orsite_gh_diag', array() );
	$token_check  = (array) get_option( 'orsite_gh_token_check', array() );
	$diff         = (array) get_option( 'orsite_gh_last_diff', array() );
	$last_update  = (array) get_option( 'orsite_gh_last_update', array() );
	$last_run     = (array) get_option( 'orsite_gh_last_run', array() );
	$last_check   = (array) get_option( 'orsite_gh_last_check', array() );
	$child        = orsite_gh_theme_info( 'child' );
	$parent       = orsite_gh_theme_info( 'parent' );
	$nonce        = wp_nonce_field( 'orsite_gh_admin', 'orsite_gh_nonce' );

	$channel_label = 'api' === $channel ? __( 'API گیت‌هاب', 'lax-theme-child' )
		: ( 'atom' === $channel ? __( 'خوراک جایگزین github.com', 'lax-theme-child' ) : __( 'حافظه‌ی محلی', 'lax-theme-child' ) );
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
	.orsite-code { direction: ltr; display: inline-block; background: #EEF4FA; border: 1px solid #B9C9D9; border-radius: 6px; padding: 1px 6px; font-size: 11.5px; }
	</style>

	<h1><?php esc_html_e( 'بروزرسانی قالب‌ها از گیت‌هاب — همیار جراحی', 'lax-theme-child' ); ?></h1>
	<?php echo orsite_gh_flash_message(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from a whitelist. ?>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'وضعیت اتصال', 'lax-theme-child' ); ?></h2>
		<p>
			<?php esc_html_e( 'مخزن پروژه:', 'lax-theme-child' ); ?>
			<a href="<?php echo esc_url( ORSITE_GH_WEB ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( ORSITE_GH_REPO ); ?></code></a>
			<?php if ( $release_data && ! $stale ) : ?>
				— <span class="orsite-status-ok"><?php echo 'atom' === $channel ? esc_html__( 'متصل (کانال جایگزین)', 'lax-theme-child' ) : esc_html__( 'متصل', 'lax-theme-child' ); ?></span>
			<?php elseif ( $release_data && $stale ) : ?>
				— <span class="orsite-status-bad"><?php esc_html_e( 'قطع — داده‌ی ذخیره‌شده', 'lax-theme-child' ); ?></span>
			<?php else : ?>
				— <span class="orsite-status-bad"><?php esc_html_e( 'قطع', 'lax-theme-child' ); ?></span>
			<?php endif; ?>
		</p>

		<?php if ( $release_data && $stale ) : ?>
			<p class="orsite-status-bad"><?php echo esc_html( $release_data['_error'] ); ?></p>
			<p class="orsite-meta">
				<?php
				printf(
					/* translators: 1: relative time, 2: release tag */
					esc_html__( 'این اطلاعات از آخرین بازخوانی موفق (%1$s پیش — انتشار %2$s) آمده و ممکن است قدیمی باشد.', 'lax-theme-child' ),
					esc_html( human_time_diff( (int) $release_data['_fetched_at'] ) ),
					esc_html( (string) $release_data['tag_name'] )
				);
				?>
			</p>
		<?php elseif ( ! $release_data ) : ?>
			<p class="orsite-status-bad"><?php echo esc_html( '' !== $release_err ? $release_err : __( 'اتصال برقرار نشده', 'lax-theme-child' ) ); ?></p>
		<?php else : ?>
			<p class="orsite-meta">
				<?php
				printf(
					/* translators: 1: relative time, 2: channel name, 3: release tag */
					esc_html__( 'آخرین بازخوانی موفق: %1$s پیش از %2$s — آخرین انتشار: %3$s', 'lax-theme-child' ),
					esc_html( human_time_diff( (int) $release_data['_fetched_at'] ) ),
					esc_html( $channel_label ),
					esc_html( (string) $release_data['tag_name'] )
				);
				?>
			</p>
		<?php endif; ?>

		<p class="orsite-meta">
			<?php esc_html_e( 'منبع توکن:', 'lax-theme-child' ); ?>
			<?php
			if ( 'constant' === $source ) {
				esc_html_e( 'ثابت GITHUB_ACCESS_TOKEN در wp-config.php', 'lax-theme-child' );
			} elseif ( 'override' === $source ) {
				esc_html_e( 'توکن ذخیره‌شده در پیشخوان (بر ثابت wp-config.php اولویت دارد)', 'lax-theme-child' );
			} elseif ( 'option' === $source ) {
				esc_html_e( 'توکن ذخیره‌شده در پیشخوان', 'lax-theme-child' );
			} else {
				esc_html_e( 'بدون توکن — دسترسی عمومی مخزن', 'lax-theme-child' );
			}
			if ( '' !== $token ) :
				?>
				— <code class="orsite-code"><?php echo esc_html( orsite_gh_mask_token( $token ) ); ?></code>
			<?php endif; ?>
		</p>

		<?php if ( ! empty( $token_check['time'] ) ) : ?>
			<?php if ( ! empty( $token_check['ok'] ) ) : ?>
				<p class="orsite-status-ok">
					<?php
					printf(
						/* translators: 1: GitHub login, 2: relative time */
						esc_html__( 'توکن تأیید شد (%1$s) — %2$s پیش', 'lax-theme-child' ),
						esc_html( (string) $token_check['login'] ),
						esc_html( human_time_diff( (int) $token_check['time'] ) )
					);
					?>
				</p>
			<?php else : ?>
				<p class="orsite-status-bad">
					<?php
					printf(
						/* translators: %s: error text */
						esc_html__( 'توکن رد شد: %s', 'lax-theme-child' ),
						esc_html( (string) $token_check['error'] )
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<form method="post" action="">
			<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput -- nonce field. ?>
			<p>
				<label for="orsite-gh-token"><strong><?php esc_html_e( 'توکن دسترسی گیت‌هاب (اختیاری)', 'lax-theme-child' ); ?></strong></label><br />
				<input type="password" id="orsite-gh-token" name="orsite_gh_token" value="" class="regular-text code" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $token ? orsite_gh_mask_token( $token ) : 'github_pat_… / ghp_…' ); ?>" />
			</p>
			<p class="orsite-meta">
				<?php esc_html_e( 'برای مخزن عمومی، دانلود بدون توکن هم کار می‌کند؛ توکن فقط سقف نرخ درخواست API را بالا می‌برد و برای مخزن خصوصی لازم است. توکن Fine-grained با دسترسی فقط Contents: Read روی همین مخزن بسازید.', 'lax-theme-child' ); ?>
				<?php if ( 'constant' === $source ) : ?>
					<?php esc_html_e( 'اگر ثابت wp-config.php منقضی شده است، توکن تازه را همین‌جا ذخیره کنید؛ تا وقتی ذخیره باشد بر ثابت اولویت دارد و با «پاک‌کردن توکن» دوباره به ثابت برمی‌گردید.', 'lax-theme-child' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<button type="submit" name="orsite_gh_action" value="save_token" class="button orsite-btn"><?php esc_html_e( 'ذخیره و بررسی توکن', 'lax-theme-child' ); ?></button>
				<?php if ( '' !== $token || '' !== (string) get_option( 'orsite_gh_token', '' ) ) : ?>
					<button type="submit" name="orsite_gh_action" value="clear_token" class="button orsite-btn-danger"><?php esc_html_e( 'پاک‌کردن توکن', 'lax-theme-child' ); ?></button>
				<?php endif; ?>
				<button type="submit" name="orsite_gh_action" value="check" class="button orsite-btn"><?php esc_html_e( 'بررسی اتصال و دریافت آخرین انتشار', 'lax-theme-child' ); ?></button>
				<button type="submit" name="orsite_gh_action" value="diagnose" class="button orsite-btn-danger"><?php esc_html_e( 'تست کامل اتصال', 'lax-theme-child' ); ?></button>
			</p>
		</form>

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
				<?php esc_html_e( 'دو بار در روز آخرین Release گیت‌هاب بررسی می‌شود؛ اگر نسخه‌ای بالاتر از نصب فعلی منتشر شده باشد، فقط فایل‌های تغییریافته‌ی همان نسخه به‌صورت خودکار جایگزین می‌شوند. هرگز نسخه پایین‌تر نصب نمی‌شود و فایلی حذف نمی‌شود. اگر ترجیح می‌دهید خودتان دکمه را بزنید، تیک را بردارید.', 'lax-theme-child' ); ?>
			</p>
			<p>
				<button type="submit" name="orsite_gh_action" value="save_settings" class="button orsite-btn"><?php esc_html_e( 'ذخیره‌ی تنظیمات', 'lax-theme-child' ); ?></button>
			</p>
		</form>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'نسخه‌های دیده‌شده در گیت‌هاب', 'lax-theme-child' ); ?></h2>
		<?php if ( empty( $candidates ) ) : ?>
			<p class="orsite-meta"><?php esc_html_e( 'هنوز فهرستی ذخیره نشده است؛ دکمه‌ی «بررسی اتصال و دریافت آخرین انتشار» را بزنید.', 'lax-theme-child' ); ?></p>
		<?php else : ?>
			<p class="orsite-meta">
				<?php esc_html_e( 'نسخه‌ی برتر از میان همه‌ی انتشارها انتخاب می‌شود، نه اولین آیتم فهرست API؛ فهرست زیر دقیقاً همان چیزی است که دیده شده است.', 'lax-theme-child' ); ?>
			</p>
			<table class="widefat striped orsite-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'تگ', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'نسخه', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'خانواده', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'تاریخ انتشار (UTC)', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'lax-theme-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $candidates as $candidate ) : ?>
						<tr>
							<td><code class="orsite-code"><?php echo esc_html( (string) $candidate['tag'] ); ?></code></td>
							<td><code class="orsite-code"><?php echo esc_html( (string) $candidate['version'] ); ?></code></td>
							<td><?php echo 'child' === $candidate['family'] ? esc_html__( 'فرزند', 'lax-theme-child' ) : esc_html__( 'والد', 'lax-theme-child' ); ?></td>
							<td class="orsite-meta"><?php echo esc_html( (string) $candidate['when'] ); ?></td>
							<td>
								<?php if ( $release_data && (string) $release_data['tag_name'] === (string) $candidate['tag'] ) : ?>
									<span class="orsite-status-new"><?php esc_html_e( 'انتخاب‌شده', 'lax-theme-child' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="orsite-card">
		<h2><?php esc_html_e( 'تست اتصال', 'lax-theme-child' ); ?></h2>
		<?php if ( empty( $diag['tests'] ) ) : ?>
			<p class="orsite-meta"><?php esc_html_e( 'هنوز تستی اجرا نشده است؛ دکمه‌ی «تست کامل اتصال» را بزنید تا وضعیت تک‌تک مسیرهای گیت‌هاب و اعتبار توکن سنجیده شود.', 'lax-theme-child' ); ?></p>
		<?php else : ?>
			<p class="orsite-meta">
				<?php
				printf(
					/* translators: %s: relative time */
					esc_html__( 'آخرین تست: %s پیش', 'lax-theme-child' ),
					esc_html( human_time_diff( (int) $diag['time'] ) )
				);
				?>
			</p>
			<table class="widefat striped orsite-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'مسیر', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'نتیجه', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'کد', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'زمان', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'توضیح', 'lax-theme-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) $diag['tests'] as $test ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $test['label'] ); ?></td>
							<td>
								<?php if ( ! empty( $test['ok'] ) ) : ?>
									<span class="orsite-status-ok">✔ <?php esc_html_e( 'پاسخ داد', 'lax-theme-child' ); ?></span>
								<?php else : ?>
									<span class="orsite-status-bad">✖ <?php esc_html_e( 'ناموفق', 'lax-theme-child' ); ?></span>
								<?php endif; ?>
							</td>
							<td><code class="orsite-code"><?php echo esc_html( (string) $test['code'] ); ?></code></td>
							<td class="orsite-meta"><?php echo esc_html( (string) $test['ms'] ); ?> ms</td>
							<td class="orsite-meta">
								<?php echo esc_html( '' !== (string) $test['detail'] ? (string) $test['detail'] : (string) $test['error'] ); ?>
								<?php if ( '' !== (string) $test['remaining'] ) : ?>
									<br /><span class="orsite-code">rate-limit: <?php echo esc_html( (string) $test['remaining'] ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( ! empty( $diag['release'] ) ) : ?>
				<p>
					<?php esc_html_e( 'نتیجه‌ی یافتن آخرین انتشار:', 'lax-theme-child' ); ?>
					<?php if ( ! empty( $diag['release']['ok'] ) ) : ?>
						<span class="orsite-status-ok"><?php echo esc_html( (string) $diag['release']['tag'] ); ?></span>
					<?php else : ?>
						<span class="orsite-status-bad"><?php echo esc_html( '' !== (string) $diag['release']['error'] ? (string) $diag['release']['error'] : __( 'ناموفق', 'lax-theme-child' ) ); ?></span>
						<?php if ( ! empty( $diag['release']['tag'] ) ) : ?>
							<span class="orsite-meta"><?php printf( esc_html__( '(نمایش داده‌ی ذخیره‌شده‌ی %s)', 'lax-theme-child' ), esc_html( (string) $diag['release']['tag'] ) ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $diag['env'] ) ) : ?>
				<p class="orsite-meta">
					<?php
					$env = (array) $diag['env'];
					$bits = array();
					if ( ! empty( $env['wp'] ) ) {
						$bits[] = 'WordPress ' . $env['wp'];
					}
					if ( ! empty( $env['php'] ) ) {
						$bits[] = 'PHP ' . $env['php'];
					}
					if ( ! empty( $env['curl'] ) ) {
						$bits[] = 'cURL ' . $env['curl'];
					}
					$bits[] = 'ZipArchive: ' . ( ! empty( $env['zip'] ) && 'yes' === $env['zip'] ? '✔' : '✖' );
					$bits[] = __( 'پوشه‌ی موقت قابل نوشتن:', 'lax-theme-child' ) . ' ' . ( ! empty( $env['temp'] ) && 'yes' === $env['temp'] ? '✔' : '✖' );
					if ( ! empty( $env['blocked'] ) ) {
						$bits[] = '⚠ ' . $env['blocked'];
					}
					if ( ! empty( $env['proxy'] ) ) {
						$bits[] = 'proxy: ' . $env['proxy'];
					}
					if ( ! empty( $env['sslverify'] ) ) {
						$bits[] = '⚠ ' . $env['sslverify'];
					}
					echo esc_html( implode( ' · ', $bits ) );
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
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
				$remote_v = isset( $remote[ $key ] ) ? $remote[ $key ] : null;
				$update   = $remote_v && version_compare( $theme['version'], $remote_v, '<' );
				?>
				<tr>
					<td><strong><?php echo esc_html( $theme['name'] ); ?></strong><br /><span class="orsite-meta"><code><?php echo esc_html( $key === 'child' ? 'lax-theme-child' : 'lax-theme' ); ?></code></span></td>
					<td><code><?php echo esc_html( $theme['version'] ); ?></code></td>
					<td><code><?php echo esc_html( $remote_v ? $remote_v : '—' ); ?></code></td>
					<td>
						<?php if ( $update ) : ?>
							<span class="orsite-status-new"><?php esc_html_e( 'بروزرسانی موجود است', 'lax-theme-child' ); ?></span>
						<?php elseif ( $remote_v && version_compare( $theme['version'], $remote_v, '>' ) ) : ?>
							<span class="orsite-status-bad"><?php esc_html_e( 'نسخه‌ی نصب‌شده از آخرین انتشار جدیدتر است', 'lax-theme-child' ); ?></span>
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
		<?php if ( $release_data && $stale ) : ?>
			<p class="orsite-status-bad">
				<?php esc_html_e( 'این کارت از داده‌ی ذخیره‌شده پر شده است؛ اتصال تازه برقرار نشد:', 'lax-theme-child' ); ?>
				<?php echo esc_html( (string) $release_data['_error'] ); ?>
			</p>
		<?php endif; ?>
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

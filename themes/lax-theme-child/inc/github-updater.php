<?php
/**
 * GitHub release update channel for the child theme.
 *
 * WordPress only discovers theme updates from WordPress.org. This project is
 * distributed as GitHub Releases, so until it is published to the theme
 * directory the site would never see a new version in پیشخوان → به‌روزرسانی‌ها.
 *
 * This file asks the public GitHub API for the latest release whose tag looks
 * like `child-vX.Y.Z` and, when that version is newer than the installed one,
 * offers it through the normal theme-update flow.
 *
 * Rules:
 * - Never fatals: every network/AJAX failure degrades to "no update".
 * - Never auto-updates: the client keeps control (auto-update stays off).
 * - The ZIP asset in the release must contain a single top-level
 *   `lax-theme-child/` folder, which is what WordPress expects.
 *
 * @package Lax_Theme_Child
 * @since   0.9.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAX_THEME_CHILD_GH_REPO' ) ) {
	define( 'LAX_THEME_CHILD_GH_REPO', 'lakanzino/OR-site' );
}

/**
 * Fetch (and cache) the newest `child-v*` release from GitHub.
 *
 * @param bool $force Bypass the cache.
 * @return array<string,mixed>|null {
 *     @type string $version Normalised version, e.g. `0.9.2`.
 *     @type string $tag     Raw tag, e.g. `child-v0.9.2`.
 *     @type string $zip     Absolute URL of the release ZIP asset.
 *     @type string $url     Human URL of the release page.
 *     @type string $notes   Release body (markdown).
 *     @type string $date    ISO-8601 publication date.
 * }
 */
function lax_theme_child_github_release( $force = false ) {
	$cache_key = 'lax_theme_child_gh_release';
	$cached    = get_site_transient( $cache_key );

	if ( ! $force && is_array( $cached ) ) {
		return ! empty( $cached['version'] ) ? $cached : null;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . LAX_THEME_CHILD_GH_REPO . '/releases?per_page=20',
		array(
			'timeout'    => 12,
			'user-agent' => 'lax-theme-child/' . LAX_THEME_CHILD_VERSION . '; ' . home_url( '/' ),
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		// Cache the failure briefly so a broken API call cannot slow the dashboard.
		set_site_transient( $cache_key, array(), 30 * MINUTE_IN_SECONDS );
		return null;
	}

	$releases = json_decode( wp_remote_retrieve_body( $response ), true );
	$release  = null;

	if ( is_array( $releases ) ) {
		foreach ( $releases as $candidate ) {
			if ( empty( $candidate['tag_name'] ) || ! empty( $candidate['draft'] ) ) {
				continue;
			}
			if ( ! preg_match( '/^child-v(\d+\.\d+\.\d+)$/i', $candidate['tag_name'], $matches ) ) {
				continue;
			}

			$zip = '';
			foreach ( (array) ( $candidate['assets'] ?? array() ) as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
					$zip = $asset['browser_download_url'];
					break;
				}
			}

			if ( '' === $zip ) {
				continue;
			}

			$release = array(
				'version' => $matches[1],
				'tag'     => $candidate['tag_name'],
				'zip'     => $zip,
				'url'     => $candidate['html_url'] ?? '',
				'notes'   => (string) ( $candidate['body'] ?? '' ),
				'date'    => $candidate['published_at'] ?? '',
			);
			break;
		}
	}

	set_site_transient( $cache_key, $release ? $release : array(), 3 * HOUR_IN_SECONDS );

	return $release;
}

/**
 * Inject the GitHub release into the theme-update transient.
 *
 * @param mixed $transient The `update_themes` site transient.
 * @return mixed
 */
function lax_theme_child_github_check_update( $transient ) {
	if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
		return $transient;
	}

	$slug      = get_stylesheet();
	$installed = isset( $transient->checked[ $slug ] ) ? $transient->checked[ $slug ] : LAX_THEME_CHILD_VERSION;
	$release   = lax_theme_child_github_release();

	if ( ! $release || ! version_compare( $release['version'], $installed, '>' ) ) {
		return $transient;
	}

	$transient->response[ $slug ] = array(
		'theme'        => $slug,
		'new_version'  => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['zip'],
		'requires'     => '6.4',
		'requires_php' => '7.4',
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'lax_theme_child_github_check_update' );

/**
 * Fill the "مشاهده جزئیات نسخه" modal with the release notes.
 *
 * @param mixed  $result The API result (false by default).
 * @param string $action Requested action.
 * @param object $args   Request arguments.
 * @return mixed
 */
function lax_theme_child_github_theme_info( $result, $action, $args ) {
	if ( ! is_object( $args ) || empty( $args->slug ) || $args->slug !== get_stylesheet() ) {
		return $result;
	}

	$release = lax_theme_child_github_release();
	if ( ! $release ) {
		return $result;
	}

	$theme = wp_get_theme( get_stylesheet() );

	return (object) array(
		'name'          => $theme->get( 'Name' ),
		'slug'          => get_stylesheet(),
		'version'       => $release['version'],
		'author'        => $theme->get( 'Author' ),
		'homepage'      => $theme->get( 'ThemeURI' ),
		'requires'      => '6.4',
		'tested'        => '7.1',
		'requires_php'  => '7.4',
		'last_updated'  => $release['date'],
		'download_link' => $release['zip'],
		'sections'      => array(
			'description' => $theme->get( 'Description' ),
			'changelog'   => wpautop( esc_html( $release['notes'] ) ),
		),
	);
}
add_filter( 'themes_api', 'lax_theme_child_github_theme_info', 10, 3 );

/**
 * Drop the cached release when WordPress is forced to re-check for updates.
 *
 * @param mixed $transient The `update_themes` transient.
 * @return mixed
 */
function lax_theme_child_github_clear_cache( $transient ) {
	delete_site_transient( 'lax_theme_child_gh_release' );
	return $transient;
}
add_action( 'delete_site_transient_update_themes', 'lax_theme_child_github_clear_cache' );

<?php
/**
 * Plugin Name:       به‌روزرسانی قالب از GitHub — همیار جراحی
 * Plugin URI:        https://github.com/lakanzino/OR-site
 * Description:       کانال به‌روزرسانی قالب‌های lax-theme و lax-theme-child از GitHub Releases؛ نسخه‌ی جدید را در «پیشخوان → به‌روزرسانی‌ها» نشان می‌دهد. بدون نیاز به توکن، بدون به‌روزرسانی خودکار.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            محمدرضا لک
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lax-gh-updater
 *
 * @package Lax_Gh_Updater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LAXGHU_REPO', 'lakanzino/OR-site' );
define( 'LAXGHU_VERSION', '1.0.0' );

/**
 * Release tag prefix => theme stylesheet slug.
 *
 * @return array<string,string>
 */
function laxghu_channels() {
	return array(
		'child-v' => 'lax-theme-child',
		'theme-v' => 'lax-theme',
	);
}

/**
 * Fetch the newest release for every channel (cached for 3 hours).
 *
 * @param bool $force Bypass cache.
 * @return array<string,array<string,mixed>>
 */
function laxghu_releases( $force = false ) {
	$key    = 'laxghu_releases';
	$cached = get_site_transient( $key );

	if ( ! $force && is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . LAXGHU_REPO . '/releases?per_page=20',
		array(
			'timeout'    => 12,
			'user-agent' => 'lax-gh-updater/' . LAXGHU_VERSION . '; ' . home_url( '/' ),
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_site_transient( $key, array(), 30 * MINUTE_IN_SECONDS );
		return array();
	}

	$raw   = json_decode( wp_remote_retrieve_body( $response ), true );
	$found = array();

	foreach ( (array) $raw as $release ) {
		if ( empty( $release['tag_name'] ) || ! empty( $release['draft'] ) ) {
			continue;
		}

		foreach ( laxghu_channels() as $prefix => $slug ) {
			if ( isset( $found[ $slug ] ) ) {
				continue;
			}
			if ( ! preg_match( '/^' . preg_quote( $prefix, '/' ) . '(\d+\.\d+\.\d+)$/i', $release['tag_name'], $m ) ) {
				continue;
			}

			$zip = '';
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
					$zip = $asset['browser_download_url'];
					break;
				}
			}

			if ( '' === $zip ) {
				// Built packages are also committed under /releases at the same tag.
				$zip = 'https://raw.githubusercontent.com/' . LAXGHU_REPO . '/' . $release['tag_name']
					. '/releases/' . $slug . '-' . $m[1] . '.zip';
			}

			$found[ $slug ] = array(
				'version' => $m[1],
				'tag'     => $release['tag_name'],
				'zip'     => $zip,
				'url'     => $release['html_url'] ?? '',
				'notes'   => (string) ( $release['body'] ?? '' ),
				'date'    => $release['published_at'] ?? '',
			);
		}
	}

	set_site_transient( $key, $found, 3 * HOUR_IN_SECONDS );

	return $found;
}

/**
 * Offer newer theme versions through the standard update flow.
 *
 * @param mixed $transient The `update_themes` site transient.
 * @return mixed
 */
function laxghu_check_theme_updates( $transient ) {
	if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
		return $transient;
	}

	foreach ( laxghu_releases() as $slug => $release ) {
		$installed = $transient->checked[ $slug ] ?? '';
		if ( '' === $installed || ! version_compare( $release['version'], $installed, '>' ) ) {
			continue;
		}

		$transient->response[ $slug ] = array(
			'theme'        => $slug,
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['zip'],
			'requires'     => '6.4',
			'requires_php' => '7.4',
		);
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'laxghu_check_theme_updates' );

/**
 * Fill the "view version details" modal with the matching release notes.
 *
 * @param mixed  $result API result.
 * @param string $action Requested action.
 * @param object $args   Request args.
 * @return mixed
 */
function laxghu_theme_info( $result, $action, $args ) {
	if ( 'theme_information' !== $action || ! is_object( $args ) || empty( $args->slug ) ) {
		return $result;
	}

	$releases = laxghu_releases();
	if ( ! isset( $releases[ $args->slug ] ) ) {
		return $result;
	}

	$release = $releases[ $args->slug ];
	$theme   = wp_get_theme( $args->slug );

	return (object) array(
		'name'          => $theme->get( 'Name' ),
		'slug'          => $args->slug,
		'version'       => $release['version'],
		'author'        => $theme->get( 'Author' ),
		'homepage'      => 'https://github.com/' . LAXGHU_REPO,
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
add_filter( 'themes_api', 'laxghu_theme_info', 10, 3 );

/**
 * Refresh the cache when an admin opens the update screens.
 */
function laxghu_flush_on_admin_screen() {
	delete_site_transient( 'laxghu_releases' );
}
add_action( 'load-update-core.php', 'laxghu_flush_on_admin_screen' );
add_action( 'load-themes.php', 'laxghu_flush_on_admin_screen' );

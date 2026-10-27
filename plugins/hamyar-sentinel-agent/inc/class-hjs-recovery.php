<?php
/**
 * 404 recovery, done the boring way.
 *
 * A blanket `wp_safe_redirect()` on every 404 is the fastest way to turn a
 * typo into a soft-404 farm and a redirect loop: bad paths get 301'd to a
 * page that may itself be missing. So this class does three narrow things:
 *
 *  1. serves 301 only for paths an editor explicitly mapped,
 *  2. resolves a moved article through WordPress's own `_wp_old_slug` record
 *     (exactly one published match, no guessing) and remembers it,
 *  3. logs what is still missing so the next decision is data-driven.
 *
 * All of it runs only when `is_404()` is already true, so a normal page load
 * pays one option read at most.
 *
 * @package Hamyar_Sentinel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect map + 404 log.
 */
final class HJS_Recovery {

	/**
	 * Singleton instance.
	 *
	 * @var HJS_Recovery|null
	 */
	private static $instance = null;

	/**
	 * Instance accessor.
	 *
	 * @return HJS_Recovery
	 */
	public static function instance(): HJS_Recovery {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook the redirect check late: other plugins may resolve the 404 first.
	 */
	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 99 );
	}

	/**
	 * The explicit map (path => path).
	 *
	 * @return array<string,string>
	 */
	public function map(): array {
		$map = get_option( HJS_REDIRECT_OPTION, array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Logged misses, newest first.
	 *
	 * @return array<string,array{count:int,last:int}>
	 */
	public function misses(): array {
		$log = get_option( HJS_MISS_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Parse the dashboard textarea into a validated map.
	 *
	 * @param string $raw Lines of `from => to`.
	 * @return void
	 */
	public function save_map_from_lines( string $raw ): void {
		$map = array();

		foreach ( preg_split( '/\R/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			if ( ! preg_match( '/^(\/[^\s=>]*)\s*(?:=>|->|=)\s*(\/[^\s]*)$/u', $line, $m ) ) {
				continue;
			}

			$from = $this->clean_path( $m[1] );
			$to   = $this->clean_path( $m[2] );
			if ( ! $from || ! $to || $from === $to ) {
				continue;
			}

			$map[ $from ] = $to;
			if ( count( $map ) >= 200 ) {
				break;
			}
		}

		update_option( HJS_REDIRECT_OPTION, $map, false );
	}

	/**
	 * Normalise one path: leading slash, no query, no traversal, no host.
	 *
	 * @param string $path Raw path.
	 * @return string Empty when the path is not safe.
	 */
	private function clean_path( string $path ): string {
		$path = wp_parse_url( trim( $path ), PHP_URL_PATH );
		$path = is_string( $path ) ? $path : '';
		$path = '/' . ltrim( $path, '/' );
		$path = preg_replace( '/\s+/', '', $path );

		if ( ! is_string( $path ) || false !== strpos( $path, '..' ) || strlen( $path ) > 200 ) {
			return '';
		}

		return $path;
	}

	/**
	 * Requested path, or '' when we cannot read one safely.
	 *
	 * @return string
	 */
	private function requested_path(): string {
		global $wp;

		if ( isset( $wp->request ) && '' !== (string) $wp->request ) {
			return $this->clean_path( '/' . (string) $wp->request );
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return $this->clean_path( $uri );
	}

	/**
	 * Serve 301 when (and only when) we have an exact, safe answer.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! is_404() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$path = $this->requested_path();
		if ( '' === $path || '/' === $path ) {
			return;
		}

		$map = $this->map();
		if ( isset( $map[ $path ] ) ) {
			$this->go( $map[ $path ] );
			return;
		}

		$resolved = $this->resolve_old_slug( $path );
		if ( $resolved ) {
			$map[ $path ] = $resolved;
			update_option( HJS_REDIRECT_OPTION, $map, false );
			$this->go( $resolved );
			return;
		}

		$this->log_miss( $path );
	}

	/**
	 * Find exactly one published post whose recorded old slug matches the
	 * requested last segment. Two matches means "unknown" — we stay 404.
	 *
	 * @param string $path Requested path.
	 * @return string Target path or ''.
	 */
	private function resolve_old_slug( string $path ): string {
		$slug = trim( (string) wp_basename( $path ), '/' );
		if ( '' === $slug || strlen( $slug ) < 4 ) {
			return '';
		}

		$matches = get_posts(
			array(
				'post_type'      => hjs_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_wp_old_slug',
						'value'   => $slug,
						'compare' => '=',
					),
				),
			)
		);

		if ( 1 !== count( $matches ) ) {
			return '';
		}

		$target = wp_parse_url( (string) get_permalink( (int) $matches[0] ), PHP_URL_PATH );
		$target = is_string( $target ) ? $this->clean_path( $target ) : '';

		return $target === $path ? '' : $target;
	}

	/**
	 * 301 to a path on this site.
	 *
	 * @param string $path Clean path.
	 * @return void
	 */
	private function go( string $path ): void {
		wp_safe_redirect( home_url( $path ), 301 );
		exit;
	}

	/**
	 * Keep the last 40 misses, nothing more.
	 *
	 * @param string $path Requested path.
	 * @return void
	 */
	private function log_miss( string $path ): void {
		$log = $this->misses();

		if ( isset( $log[ $path ] ) ) {
			$log[ $path ]['count'] = (int) $log[ $path ]['count'] + 1;
			$log[ $path ]['last']  = time();
		} else {
			$log[ $path ] = array(
				'count' => 1,
				'last'  => time(),
			);
		}

		if ( count( $log ) > 40 ) {
			uasort(
				$log,
				static function ( $a, $b ) {
					return (int) $b['last'] <=> (int) $a['last'];
				}
			);
			$log = array_slice( $log, 0, 40, true );
		}

		update_option( HJS_MISS_OPTION, $log, false );
	}
}

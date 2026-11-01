<?php
/**
 * Hamyar Doctors — Section 5: Google OAuth bridge (Nextend Social Login)
 *
 * File: wp-content/plugins/hamyar-doctors/includes/auth-google.php
 *
 * What it does:
 *  - Provides a "doctor entry" URL (?hj_google_start=1) that sets a signed intent cookie
 *    and forwards to Nextend's Google login.
 *  - On Google registration/login with intent → gives the user the `doctor` role
 *    (via hj_doctors_assign_doctor_role) and sends them to /my-profile/.
 *  - Users who use Google WITHOUT intent (e.g. patients) are left untouched.
 *  - Rate limit: hj_sec_rate_limit('signup', 10, HOUR_IN_SECONDS).
 *  - Errors are logged only; no generic error is shown to visitors.
 *  - Admin-only status page for troubleshooting (no terminal needed).
 *
 * Generic adapter for other providers (e.g. PeproDev):
 *   do_action('hj_doctors_google_user_ready', $user_id, $is_new_user);
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HJ_Doctors_Auth_Google', false ) ) :

	final class HJ_Doctors_Auth_Google {

		const INTENT_COOKIE = 'hj_doc_intent';
		const INTENT_TTL    = 1800; // 30 minutes
		const META_PENDING  = 'hj_role_pending';
		const META_PROVIDER = 'hj_auth_provider';
		const META_REG_AT   = 'hj_auth_registered_at';

		public static function init() {
			// Entry point for the doctor flow.
			add_action( 'init', array( __CLASS__, 'maybe_start_flow' ), 5 );

			// Nextend hooks (names taken from Nextend docs; see agent note for fallbacks).
			add_action( 'nsl_register_new_user', array( __CLASS__, 'on_nsl_register' ), 10, 2 );
			add_action( 'nsl_login', array( __CLASS__, 'on_nsl_login' ), 10, 2 );
			add_filter( 'nsl_login_redirect_url', array( __CLASS__, 'filter_nsl_redirect' ), 10, 2 );
			add_filter( 'nsl_registration_redirect_url', array( __CLASS__, 'filter_nsl_redirect' ), 10, 2 );

			// Generic adapter for any other social plugin.
			add_action( 'hj_doctors_google_user_ready', array( __CLASS__, 'on_generic' ), 10, 2 );

			// Fallback redirect for doctors logging in anywhere.
			add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 20, 3 );

			// Button shortcode (used by the dashboard in section 6 as well).
			add_shortcode( 'hj_google_login', array( __CLASS__, 'shortcode' ) );

			// Admin-only status page.
			add_action( 'admin_post_hj_auth_status', array( __CLASS__, 'render_status' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Helpers                                                             */
		/* ------------------------------------------------------------------ */

		private static function log( $message ) {
			if ( function_exists( 'hj_doctors_log' ) ) {
				hj_doctors_log( '[auth-google] ' . $message );
			}
		}

		private static function dashboard_url() {
			if ( function_exists( 'hj_doctors_dashboard_url' ) ) {
				return hj_doctors_dashboard_url();
			}
			return home_url( '/my-profile/' );
		}

		private static function provider_active() {
			return class_exists( 'NextendSocialLogin' );
		}

		private static function is_google( $provider ) {
			$id = '';
			if ( is_object( $provider ) && method_exists( $provider, 'getId' ) ) {
				$id = (string) $provider->getId();
			} elseif ( is_string( $provider ) ) {
				$id = $provider;
			}
			return 'google' === $id;
		}

		private static function is_doctor( $user_id ) {
			$user = get_userdata( (int) $user_id );
			if ( ! $user ) {
				return false;
			}
			return in_array( 'doctor', (array) $user->roles, true );
		}

		/** Public: URL that starts the Google flow for doctors. */
		public static function start_url() {
			return add_query_arg( 'hj_google_start', '1', home_url( '/' ) );
		}

		/** Nextend login URL with redirect param. */
		private static function login_url( $redirect ) {
			return add_query_arg(
				array(
					'loginSocial' => 'google',
					'redirect'    => $redirect,
				),
				site_url( 'wp-login.php' )
			);
		}

		/* ------------------------------------------------------------------ */
		/* Signed intent cookie                                                */
		/* ------------------------------------------------------------------ */

		private static function sign( $ts ) {
			return hash_hmac( 'sha256', 'hj_doc_intent|' . (int) $ts, wp_salt( 'auth' ) );
		}

		private static function set_intent() {
			if ( headers_sent() ) {
				return;
			}
			$ts = time();
			setcookie(
				self::INTENT_COOKIE,
				$ts . '|' . self::sign( $ts ),
				array(
					'expires'  => $ts + self::INTENT_TTL,
					'path'     => '/',
					'domain'   => ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		private static function clear_intent() {
			if ( headers_sent() ) {
				return;
			}
			setcookie(
				self::INTENT_COOKIE,
				'',
				array(
					'expires'  => time() - 3600,
					'path'     => '/',
					'domain'   => ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		public static function has_intent() {
			if ( empty( $_COOKIE[ self::INTENT_COOKIE ] ) ) {
				return false;
			}
			$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::INTENT_COOKIE ] ) );
			$parts = explode( '|', $raw );
			if ( count( $parts ) !== 2 ) {
				return false;
			}
			$ts = (int) $parts[0];
			if ( $ts <= 0 || $ts > time() + 60 || ( time() - $ts ) > self::INTENT_TTL ) {
				return false;
			}
			return hash_equals( self::sign( $ts ), (string) $parts[1] );
		}

		/* ------------------------------------------------------------------ */
		/* Entry point: /?hj_google_start=1                                    */
		/* ------------------------------------------------------------------ */

		public static function maybe_start_flow() {
			if ( ! isset( $_GET['hj_google_start'] ) ) {
				return;
			}
			if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
				return;
			}

			nocache_headers();
			$dash = self::dashboard_url();

			if ( is_user_logged_in() ) {
				wp_safe_redirect( $dash );
				exit;
			}

			// Light throttle on the entry point itself (per IP: هنوز لاگین نیست).
			if ( function_exists( 'hj_sec_rate_limit' ) && ! hj_sec_rate_limit( 'google_start', 30, HOUR_IN_SECONDS, true ) ) {
				self::log( 'google_start rate limit exceeded' );
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}

			self::set_intent();

			if ( self::provider_active() ) {
				$target = self::login_url( $dash );
			} else {
				self::log( 'Nextend Social Login is not active; falling back to wp-login.php' );
				$target = wp_login_url( $dash );
			}

			wp_safe_redirect( $target );
			exit;
		}

		/* ------------------------------------------------------------------ */
		/* Social plugin callbacks                                             */
		/* ------------------------------------------------------------------ */

		public static function on_nsl_register( $user_id, $provider = null ) {
			if ( ! self::is_google( $provider ) ) {
				return;
			}
			self::process_user( $user_id, true );
		}

		public static function on_nsl_login( $user_id, $provider = null ) {
			if ( ! self::is_google( $provider ) ) {
				return;
			}
			self::process_user( $user_id, false );
		}

		public static function on_generic( $user_id, $is_new = false ) {
			self::process_user( $user_id, (bool) $is_new );
		}

		private static function process_user( $user_id, $is_new ) {
			$user_id = (int) $user_id;
			if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
				return;
			}

			if ( $is_new ) {
				update_user_meta( $user_id, self::META_PROVIDER, 'google' );
				update_user_meta( $user_id, self::META_REG_AT, time() );
			}

			if ( self::is_doctor( $user_id ) ) {
				delete_user_meta( $user_id, self::META_PENDING );
				self::clear_intent();
				return;
			}

			$intent  = self::has_intent();
			$pending = (bool) get_user_meta( $user_id, self::META_PENDING, true );
			$require = (bool) apply_filters( 'hj_doctors_google_require_intent', true );

			$eligible = $intent || $pending || ( $is_new && ! $require );
			if ( ! $eligible ) {
				return; // Normal (non-doctor) Google login — leave the user alone.
			}

			self::grant_role( $user_id );
			self::clear_intent();
		}

		private static function grant_role( $user_id ) {
			if ( ! function_exists( 'hj_doctors_assign_doctor_role' ) ) {
				self::log( 'hj_doctors_assign_doctor_role() missing (roles.php not loaded?)' );
				return false;
			}

			// Signup rate limit: 10 per hour (per IP — هنگام ثبت‌نام کاربر ممکن است
			// همین الان لاگین شده باشد، پس کلید کاربری بی‌معنی است). true = allowed.
			if ( function_exists( 'hj_sec_rate_limit' ) && ! hj_sec_rate_limit( 'signup', 10, HOUR_IN_SECONDS, true ) ) {
				update_user_meta( $user_id, self::META_PENDING, 1 );
				self::log( 'signup rate limit exceeded; role deferred for user ' . (int) $user_id );
				return false;
			}

			hj_doctors_assign_doctor_role( $user_id );

			if ( self::is_doctor( $user_id ) ) {
				delete_user_meta( $user_id, self::META_PENDING );
				return true;
			}

			// Not converted (e.g. protected role like administrator/editor) — this is intended.
			delete_user_meta( $user_id, self::META_PENDING );
			self::log( 'role not changed for user ' . (int) $user_id . ' (protected or not replaceable role)' );
			return false;
		}

		/* ------------------------------------------------------------------ */
		/* Redirects                                                           */
		/* ------------------------------------------------------------------ */

		public static function filter_nsl_redirect( $url, $provider = null ) {
			if ( null !== $provider && ! self::is_google( $provider ) ) {
				return $url;
			}
			if ( self::has_intent() ) {
				return self::dashboard_url();
			}
			return $url;
		}

		public static function filter_login_redirect( $redirect_to, $requested, $user ) {
			if ( ! ( $user instanceof WP_User ) ) {
				return $redirect_to;
			}
			if ( ! self::is_doctor( $user->ID ) ) {
				return $redirect_to;
			}
			if ( empty( $requested ) || false !== strpos( (string) $requested, 'wp-admin' ) ) {
				return self::dashboard_url();
			}
			return $redirect_to;
		}

		/* ------------------------------------------------------------------ */
		/* Button                                                              */
		/* ------------------------------------------------------------------ */

		public static function button_html( $label = '' ) {
			if ( '' === $label ) {
				$label = 'ورود یا ثبت‌نام با گوگل';
			}
			$svg = '<svg class="hj-google-btn__icon" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">'
				. '<path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>'
				. '<path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/>'
				. '<path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>'
				. '<path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>'
				. '</svg>';

			return '<a class="hj-google-btn" rel="nofollow" href="' . esc_url( self::start_url() ) . '">'
				. $svg
				. '<span class="hj-google-btn__label">' . esc_html( $label ) . '</span>'
				. '</a>';
		}

		public static function shortcode( $atts ) {
			if ( is_user_logged_in() ) {
				return '';
			}
			$a = shortcode_atts(
				array( 'label' => 'ورود یا ثبت‌نام با گوگل' ),
				$atts,
				'hj_google_login'
			);
			return self::button_html( sanitize_text_field( $a['label'] ) );
		}

		/* ------------------------------------------------------------------ */
		/* Admin-only status page                                              */
		/* /wp-admin/admin-post.php?action=hj_auth_status                      */
		/* ------------------------------------------------------------------ */

		public static function render_status() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}

			$lines = array();
			$ok    = function ( $label, $value ) use ( &$lines ) {
				$lines[] = sprintf( '%-42s %s', $label, $value ? 'OK' : 'MISSING' );
			};

			$nextend = self::provider_active();
			$ok( 'Nextend Social Login active', $nextend );

			$google_enabled = false;
			try {
				if ( $nextend && property_exists( 'NextendSocialLogin', 'enabledProviders' ) ) {
					$enabled        = \NextendSocialLogin::$enabledProviders;
					$google_enabled = is_array( $enabled ) && isset( $enabled['google'] );
				}
			} catch ( \Throwable $e ) {
				$google_enabled = false;
			}
			$ok( 'Google provider enabled in Nextend', $google_enabled );

			$ok( 'Hook nsl_register_new_user attached', false !== has_action( 'nsl_register_new_user', array( __CLASS__, 'on_nsl_register' ) ) );
			$ok( 'Hook nsl_login attached', false !== has_action( 'nsl_login', array( __CLASS__, 'on_nsl_login' ) ) );
			$ok( 'Role "doctor" exists', (bool) get_role( 'doctor' ) );
			$ok( 'hj_doctors_assign_doctor_role()', function_exists( 'hj_doctors_assign_doctor_role' ) );
			$ok( 'hj_sec_rate_limit()', function_exists( 'hj_sec_rate_limit' ) );
			$ok( 'Page /my-profile/ exists', (bool) get_page_by_path( 'my-profile' ) );
			$ok( 'HTTPS enabled', is_ssl() );
			$ok( 'PHP >= 7.3 (cookie SameSite)', PHP_VERSION_ID >= 70300 );

			$doctors = get_users(
				array(
					'role'   => 'doctor',
					'fields' => 'ID',
					'number' => 1,
				)
			);
			$ok( 'At least one user with role doctor', ! empty( $doctors ) );

			$lines[] = '';
			$lines[] = 'Doctor entry URL : ' . self::start_url();
			$lines[] = 'Dashboard URL    : ' . self::dashboard_url();
			$lines[] = 'Expected Redirect URI (verify in Nextend → Google → Getting Started):';
			$lines[] = '  ' . add_query_arg( 'loginSocial', 'google', site_url( 'wp-login.php' ) );

			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( implode( "\n", $lines ) );
			exit;
		}
	}

	HJ_Doctors_Auth_Google::init();

	/** Template helper: echo hj_doctors_google_login_button(); */
	if ( ! function_exists( 'hj_doctors_google_login_button' ) ) {
		/**
		 * HTML دکمهٔ ورود با گوگل (لینک به مسیر نیت).
		 *
		 * @param string $label برچسب دلخواه دکمه.
		 * @return string
		 */
		function hj_doctors_google_login_button( $label = '' ) {
			return HJ_Doctors_Auth_Google::button_html( $label );
		}
	}

	/** Public URL of the doctor entry flow. */
	if ( ! function_exists( 'hj_doctors_google_start_url' ) ) {
		/**
		 * نشانی عمومی شروع جریان ورود پزشکان با گوگل.
		 *
		 * @return string
		 */
		function hj_doctors_google_start_url() {
			return HJ_Doctors_Auth_Google::start_url();
		}
	}

endif;

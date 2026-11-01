<?php
/**
 * Role "doctor", custom capabilities and access rules.
 *
 * Design:
 * - The doctor role gets NO edit_posts / publish_posts / upload_files.
 *   Editing the OWN doctor profile is granted through map_meta_cap, so a
 *   doctor can never touch blog posts or other doctors' profiles.
 * - Admin-level hj_* capabilities are granted dynamically to users who have
 *   manage_options (nothing is written into the administrator role).
 * - Doctors cannot delete/hide profiles, change ratings, status or approval.
 * - Ownership = post_author OR user meta hj_doctor_post_id.
 * - wp-admin is closed for doctor-only users (they work in /my-profile/).
 *
 * Existing rating moderation capabilities (the current "آرای پزشکان" screen)
 * are NOT changed.
 *
 * @package hamyar-doctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HJ_Doctors_Roles', false ) ) :

	final class HJ_Doctors_Roles {

		const ROLE = 'doctor';
		const POST_TYPE = 'doctor';
		const SCHEMA = '1';

		const OPT_HASH = 'hj_doctors_roles_hash';
		const OPT_ADDED = 'hj_doctors_role_caps_added';

		const USER_META_PROFILE = 'hj_doctor_post_id';

		// Doctor-level capabilities.
		const CAP_EDIT_OWN = 'hj_edit_own_doctor_profile';
		const CAP_UPLOAD = 'hj_upload_profile_media';
		const CAP_VIEW_OWN_RATINGS = 'hj_view_own_rating_stats';

		// Admin-level capabilities.
		const CAP_MANAGE_DOCTORS = 'hj_manage_doctors';
		const CAP_APPROVE = 'hj_approve_profiles';
		const CAP_MANAGE_RATINGS = 'hj_manage_ratings';
		const CAP_VIEW_ALL_RATINGS = 'hj_view_all_rating_stats';
		const CAP_ANALYTICS = 'hj_view_analytics';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'sync_role' ), 5 );
			add_action( 'init', array( __CLASS__, 'register_user_meta' ), 20 );

			add_filter( 'user_has_cap', array( __CLASS__, 'grant_admin_caps' ), 10, 4 );
			add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta' ), 10, 4 );

			add_action( 'admin_init', array( __CLASS__, 'maybe_block_wp_admin' ), 1 );
			add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ), 20 );

			add_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ), 20 );
			add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'upload_prefilter' ), 20 );
		}

		/* --------------------------- Capability sets --------------------- */

		private static function all_caps() {
			return array_fill_keys(
				array(
					self::CAP_EDIT_OWN,
					self::CAP_UPLOAD,
					self::CAP_VIEW_OWN_RATINGS,
					self::CAP_MANAGE_DOCTORS,
					self::CAP_APPROVE,
					self::CAP_MANAGE_RATINGS,
					self::CAP_VIEW_ALL_RATINGS,
					self::CAP_ANALYTICS,
				),
				true
			);
		}

		/** Capabilities stored in the doctor role. Filterable (e.g. add 'upload_files'). */
		private static function doctor_caps() {
			$caps = array(
				'read'           => true,
				self::CAP_EDIT_OWN => true,
				self::CAP_UPLOAD => true,
				self::CAP_VIEW_OWN_RATINGS => true,
			);
			$caps = apply_filters( 'hj_doctors_doctor_role_caps', $caps );
			return is_array( $caps ) ? $caps : array( 'read' => true );
		}

		/** Capabilities that must never stay on the doctor role. */
		private static function forbidden_caps() {
			return array(
				'manage_options',
				'edit_others_posts',
				'delete_others_posts',
				'publish_posts',
				'promote_users',
				'edit_users',
				'delete_users',
				'install_plugins',
				'edit_theme_options',
			);
		}

		/* ------------------------------ Role sync ------------------------ */

		/** Creates/updates the role only when the definition changed (cheap hash check). */
		public static function sync_role() {
			$doctor_caps = self::doctor_caps();
			$hash        = md5( self::SCHEMA . '|' . wp_json_encode( $doctor_caps ) );

			if ( get_option( self::OPT_HASH ) === $hash && get_role( self::ROLE ) ) {
				return;
			}

			$role = get_role( self::ROLE );
			if ( ! $role ) {
				add_role( self::ROLE, 'پزشک', $doctor_caps );
				$role = get_role( self::ROLE );
			}
			if ( ! $role ) {
				hj_doctors_log( 'Could not create the doctor role.' );
				return;
			}

			foreach ( $doctor_caps as $cap => $grant ) {
				$role->add_cap( (string) $cap, (bool) $grant );
			}

			// Remove capabilities we added earlier that are not wanted any more.
			$previous = get_option( self::OPT_ADDED, array() );
			foreach ( (array) $previous as $cap ) {
				if ( ! isset( $doctor_caps[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}

			foreach ( self::forbidden_caps() as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
					hj_doctors_log( 'Removed a forbidden capability from the doctor role: ' . $cap );
				}
			}

			update_option( self::OPT_ADDED, array_keys( $doctor_caps ), false );
			update_option( self::OPT_HASH, $hash, false );
		}

		public static function register_user_meta() {
			register_meta(
				'user',
				self::USER_META_PROFILE,
				array(
					'type'              => 'integer',
					'single'            => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function () {
						return current_user_can( HJ_Doctors_Roles::CAP_MANAGE_DOCTORS );
					},
					'show_in_rest'      => false,
				)
			);
		}

		/* ------------------------------ Who is who ----------------------- */

		/**
		 * True for users who have the doctor role and are NOT administrators.
		 *
		 * @param WP_User|int|null $user User, ID, or null for the current user.
		 */
		public static function is_restricted( $user = null ) {
			if ( ! ( $user instanceof WP_User ) ) {
				$user = $user ? get_userdata( (int) $user ) : wp_get_current_user();
			}
			if ( ! $user || ! $user->exists() ) {
				return false;
			}
			if ( ! in_array( self::ROLE, (array) $user->roles, true ) ) {
				return false;
			}
			return ! $user->has_cap( 'manage_options' );
		}

		/* --------------------------- Capability filters ------------------ */

		/** Admin-level hj_* capabilities for users with manage_options (no DB change). */
		public static function grant_admin_caps( $allcaps, $caps, $args, $user ) {
			$all    = self::all_caps();
			$needed = false;
			foreach ( (array) $caps as $cap ) {
				if ( isset( $all[ $cap ] ) ) {
					$needed = true;
					break;
				}
			}
			if ( ! $needed ) {
				return $allcaps;
			}

			// Reads $allcaps directly: calling user_can() here would recurse.
			$holder = ! empty( $allcaps['manage_options'] );
			if ( ! apply_filters( 'hj_doctors_is_cap_holder', $holder, $user, $allcaps ) ) {
				return $allcaps;
			}

			foreach ( (array) $caps as $cap ) {
				if ( isset( $all[ $cap ] ) ) {
					$allcaps[ $cap ] = true;
				}
			}
			return $allcaps;
		}

		/**
		 * Doctor-only users: edit_post works only on their OWN doctor profile
		 * and delete_post is never allowed. Everything else is untouched.
		 */
		public static function map_meta( $caps, $cap, $user_id, $args ) {
			if ( 'edit_post' !== $cap && 'delete_post' !== $cap ) {
				return $caps;
			}
			if ( ! $user_id || empty( $args[0] ) ) {
				return $caps;
			}
			$post = get_post( $args[0] );
			if ( ! $post || self::POST_TYPE !== $post->post_type ) {
				return $caps;
			}
			if ( ! self::is_restricted( (int) $user_id ) ) {
				return $caps;
			}
			if ( 'delete_post' === $cap ) {
				return array( 'do_not_allow' );
			}
			return hj_doctors_user_owns_profile( (int) $user_id, (int) $post->ID )
				? array( self::CAP_EDIT_OWN )
				: array( 'do_not_allow' );
		}

		/* ----------------------------- wp-admin lock --------------------- */

		/**
		 * Doctors work in /my-profile/. Disable with:
		 * add_filter( 'hj_doctors_block_wp_admin', '__return_false' );
		 */
		public static function maybe_block_wp_admin() {
			if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return;
			}
			if ( ! is_user_logged_in() || ! self::is_restricted() ) {
				return;
			}
			if ( ! apply_filters( 'hj_doctors_block_wp_admin', true ) ) {
				return;
			}

			global $pagenow;
			$allowed = (array) apply_filters( 'hj_doctors_admin_allowed_pages', array( 'admin-post.php', 'async-upload.php' ) );
			if ( in_array( $pagenow, $allowed, true ) ) {
				return;
			}

			wp_safe_redirect( hj_doctors_dashboard_url() );
			exit;
		}

		public static function hide_admin_bar( $show ) {
			if ( $show && is_user_logged_in() && self::is_restricted() && apply_filters( 'hj_doctors_block_wp_admin', true ) ) {
				return false;
			}
			return $show;
		}

		/* ---------------------------- Upload limits ---------------------- */

		/** Doctors may only upload jpg / png / webp / pdf. */
		public static function restrict_upload_mimes( $mimes ) {
			if ( ! self::is_restricted() ) {
				return $mimes;
			}
			return array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
				'pdf'          => 'application/pdf',
			);
		}

		/** Capability, size (2 MB) and rate limit for doctor uploads. */
		public static function upload_prefilter( $file ) {
			if ( ! self::is_restricted() ) {
				return $file;
			}

			if ( ! current_user_can( self::CAP_UPLOAD ) ) {
				$file['error'] = __( 'شما اجازهٔ بارگذاری فایل ندارید.', 'hamyar-doctors' );
				return $file;
			}

			$max  = function_exists( 'hj_sec_max_upload_bytes' ) ? hj_sec_max_upload_bytes() : 2 * 1024 * 1024;
			$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
			if ( $size > $max ) {
				$file['error'] = __( 'حجم فایل باید حداکثر ۲ مگابایت باشد.', 'hamyar-doctors' );
				return $file;
			}

			if ( function_exists( 'hj_sec_rate_limit' ) ) {
				$limit = (int) apply_filters( 'hj_doctors_upload_rate_limit', 30 );
				if ( ! hj_sec_rate_limit( 'doctor_upload', $limit, HOUR_IN_SECONDS ) ) {
					$file['error'] = __( 'تعداد بارگذاری‌ها در این ساعت زیاد بوده است. بعداً تلاش کنید.', 'hamyar-doctors' );
				}
			}
			return $file;
		}
	}

	HJ_Doctors_Roles::init();

endif;

/* =========================================================================
 * Public helpers for later sections (guarded, prefixed hj_)
 * ====================================================================== */

if ( ! function_exists( 'hj_doctors_dashboard_url' ) ) {
	/** URL of the doctor dashboard (existing page). */
	function hj_doctors_dashboard_url() {
		return (string) apply_filters( 'hj_doctors_dashboard_url', home_url( '/my-profile/' ) );
	}
}

if ( ! function_exists( 'hj_doctors_is_doctor_user' ) ) {
	/** True for doctor-role users who are not administrators. */
	function hj_doctors_is_doctor_user( $user = null ) {
		return HJ_Doctors_Roles::is_restricted( $user );
	}
}

if ( ! function_exists( 'hj_doctors_get_profile_id' ) ) {
	/**
	 * Doctor post ID that belongs to a user (0 if none yet).
	 * Order: user meta hj_doctor_post_id, then the first doctor post authored by the user.
	 */
	function hj_doctors_get_profile_id( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return 0;
		}

		$linked = (int) get_user_meta( $user_id, HJ_Doctors_Roles::USER_META_PROFILE, true );
		if ( $linked && 'doctor' === get_post_type( $linked ) && 'trash' !== get_post_status( $linked ) ) {
			return $linked;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'doctor',
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}
}

if ( ! function_exists( 'hj_doctors_user_owns_profile' ) ) {
	/** True if the doctor post is authored by, or linked to, the user. */
	function hj_doctors_user_owns_profile( $user_id, $post_id ) {
		$user_id = (int) $user_id;
		$post    = get_post( (int) $post_id );
		if ( $user_id <= 0 || ! $post || 'doctor' !== $post->post_type ) {
			return false;
		}
		if ( (int) $post->post_author === $user_id ) {
			return true;
		}
		return (int) get_user_meta( $user_id, HJ_Doctors_Roles::USER_META_PROFILE, true ) === (int) $post->ID;
	}
}

if ( ! function_exists( 'hj_doctors_user_can_edit_profile' ) ) {
	/** Admin: any profile. Doctor: only their own. */
	function hj_doctors_user_can_edit_profile( $post_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, HJ_Doctors_Roles::CAP_MANAGE_DOCTORS ) ) {
			return true;
		}
		return user_can( $user_id, HJ_Doctors_Roles::CAP_EDIT_OWN ) && hj_doctors_user_owns_profile( $user_id, $post_id );
	}
}

if ( ! function_exists( 'hj_doctors_user_can_view_ratings' ) ) {
	/** Rating statistics: admin sees all, doctor only their own (read only). */
	function hj_doctors_user_can_view_ratings( $post_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, HJ_Doctors_Roles::CAP_VIEW_ALL_RATINGS ) ) {
			return true;
		}
		return user_can( $user_id, HJ_Doctors_Roles::CAP_VIEW_OWN_RATINGS ) && hj_doctors_user_owns_profile( $user_id, $post_id );
	}
}

if ( ! function_exists( 'hj_doctors_user_can_manage' ) ) {
	/**
	 * Admin-only actions: 'approve' (status, verified, publish), 'ratings',
	 * 'analytics', 'doctors' (delete/hide/all profiles).
	 */
	function hj_doctors_user_can_manage( $what = 'doctors', $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		$map = array(
			'approve'   => HJ_Doctors_Roles::CAP_APPROVE,
			'ratings'   => HJ_Doctors_Roles::CAP_MANAGE_RATINGS,
			'analytics' => HJ_Doctors_Roles::CAP_ANALYTICS,
			'doctors'   => HJ_Doctors_Roles::CAP_MANAGE_DOCTORS,
		);
		return isset( $map[ $what ] ) && user_can( $user_id, $map[ $what ] );
	}
}

if ( ! function_exists( 'hj_doctors_user_can_access_dashboard' ) ) {
	/** /my-profile/ is for doctors and administrators only. */
	function hj_doctors_user_can_access_dashboard( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		return user_can( $user_id, HJ_Doctors_Roles::CAP_EDIT_OWN ) || user_can( $user_id, HJ_Doctors_Roles::CAP_MANAGE_DOCTORS );
	}
}

if ( ! function_exists( 'hj_doctors_assign_doctor_role' ) ) {
	/**
	 * Safe role assignment for new sign-ups (used by Google login, section 5).
	 * Only users with no role or only "subscriber" are changed, so an
	 * administrator or editor can never be downgraded by a sign-up flow.
	 *
	 * @return bool True if the user now has the doctor role.
	 */
	function hj_doctors_assign_doctor_role( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		if ( in_array( 'doctor', $roles, true ) ) {
			return true;
		}

		if ( ! get_role( 'doctor' ) ) {
			HJ_Doctors_Roles::sync_role();
			if ( ! get_role( 'doctor' ) ) {
				return false;
			}
		}

		$replaceable = (array) apply_filters( 'hj_doctors_replaceable_roles', array( 'subscriber' ) );
		if ( empty( $roles ) || ! array_diff( $roles, $replaceable ) ) {
			$user->set_role( 'doctor' );
			return true;
		}
		return false;
	}
}

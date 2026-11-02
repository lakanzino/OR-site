<?php
/**
 * Hamyar Doctors — Section 6: doctor dashboard on /my-profile/
 *
 * Minimal two-column dashboard: one form column with five plain blocks
 * (bilingual basics, specialty & history, contact & address, working hours,
 * photo & private documents) and a small side column (live preview, profile
 * status & completeness, read-only ratings). AJAX save with per-field errors
 * under the offending field only — no separate notices, ever.
 *
 * Storage stays ACF (update_field) with the section-3 field group; documents
 * are NOT an ACF field: they live in uploads/hj-private/p<ID>/ with random
 * names and are downloadable only by the profile owner or an approver.
 *
 * Approval flow: doctor sends to `pending`; admin approves/publishes or
 * rejects from the doctors list (row actions) in wp-admin.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HJ_Doctors_Dashboard', false ) ) :

	final class HJ_Doctors_Dashboard {

		const NONCE           = 'hj_dash_nonce';
		const META_STATUS     = 'hj_profile_status';
		const META_DOCS       = 'hj_private_docs';
		const META_PHOTO_FLAG = 'hj_dash_photo_flag';

		public static function init() {
			add_shortcode( 'hj_doctors_dashboard', array( __CLASS__, 'shortcode_render' ) );

			add_action( 'wp_ajax_hj_dash_save', array( __CLASS__, 'ajax_save' ) );
			add_action( 'wp_ajax_hj_dash_photo', array( __CLASS__, 'ajax_upload_photo' ) );
			add_action( 'wp_ajax_hj_dash_photo_remove', array( __CLASS__, 'ajax_remove_photo' ) );
			add_action( 'wp_ajax_hj_dash_doc', array( __CLASS__, 'ajax_upload_doc' ) );
			add_action( 'wp_ajax_hj_dash_doc_delete', array( __CLASS__, 'ajax_delete_doc' ) );
			add_action( 'wp_ajax_hj_dash_submit', array( __CLASS__, 'ajax_submit' ) );
			add_action( 'wp_ajax_hj_doc_download', array( __CLASS__, 'ajax_download' ) );

			add_action( 'admin_post_hj_doctors_join', array( __CLASS__, 'join_doctors' ) );
			add_action( 'admin_notices', array( __CLASS__, 'dashboard_page_notice' ) );
			add_action( 'template_redirect', array( __CLASS__, 'redirect_old_dashboard_url' ) );
			add_action( 'template_redirect', array( __CLASS__, 'prevent_dashboard_cache' ), 0 );

			add_action( 'admin_post_hj_dash_moderate', array( __CLASS__, 'admin_moderate' ) );
			add_action( 'admin_post_hj_dash_status', array( __CLASS__, 'admin_status' ) );

			add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
			add_filter( 'manage_edit-doctor_columns', array( __CLASS__, 'admin_columns' ) );
			add_action( 'manage_doctor_posts_custom_column', array( __CLASS__, 'admin_column_value' ), 10, 2 );

			add_action( 'before_delete_post', array( __CLASS__, 'cleanup_private_files' ) );
		}

		/* ================================================================== */
		/* Page gate + assets                                                  */
		/* ================================================================== */

		/**
		 * شورت‌کد صفحهٔ /my-profile/: مهمان ← ورود، غیرمجاز ← پیام، وگرنه داشبورد.
		 *
		 * @param array|string $atts ویژگی‌های شورت‌کد.
		 * @return string
		 */
		public static function shortcode_render( $atts = array() ) {
			if ( ! is_user_logged_in() ) {
				$h  = '<div class="hj-dash"><p>' . esc_html__( 'برای دیدن داشبورد پزشک وارد شوید.', 'hamyar-doctors' ) . '</p>';
				$h .= '<p><a class="hj-dash__btn hj-dash__btn--primary" href="' . esc_url( wp_login_url( hj_doctors_dashboard_url() ) ) . '">' . esc_html__( 'ورود', 'hamyar-doctors' ) . '</a></p>';
				if ( shortcode_exists( 'hj_google_login' ) ) {
					$h .= do_shortcode( '[hj_google_login]' );
				}
				return $h . '</div>';
			}
			if ( ! hj_doctors_user_can_access_dashboard() ) {
				$user = wp_get_current_user();
				$replaceable = (array) apply_filters( 'hj_doctors_replaceable_roles', array( 'subscriber' ) );
				if ( ! array_diff( (array) $user->roles, $replaceable ) ) {
					return '<div class="hj-dash"><p>' . esc_html__( 'برای ساخت پروفایل پزشک، ثبت‌نام خود را تأیید کنید. پروفایل شما پیش از انتشار بررسی می‌شود.', 'hamyar-doctors' ) . '</p>'
						. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hj_doctors_join">'
						. wp_nonce_field( 'hj_doctors_join', '_wpnonce', true, false )
						. '<button type="submit">' . esc_html__( 'ثبت‌نام به عنوان پزشک', 'hamyar-doctors' ) . '</button></form></div>';
				}
				return '<div class="hj-dash"><p>' . esc_html__( 'شما دسترسی به داشبورد پزشک ندارید.', 'hamyar-doctors' ) . '</p></div>';
			}
			return self::render();
		}

		/** Explicit, CSRF-protected opt-in for an already signed-in subscriber. */
		public static function join_doctors() {
			if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! is_user_logged_in() ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}
			check_admin_referer( 'hj_doctors_join' );
			if ( ! hj_doctors_assign_doctor_role( get_current_user_id() ) ) {
				wp_die( 'این حساب امکان ثبت‌نام پزشک ندارد.', '', array( 'response' => 403 ) );
			}
			wp_safe_redirect( hj_doctors_dashboard_url() );
			exit;
		}

		/** Report a conflicting or empty page without overwriting an editor's work. */
		public static function dashboard_page_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$page = get_post( (int) get_option( 'hj_doctors_dashboard_page' ) );
			if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type || 'publish' !== $page->post_status || ! has_shortcode( $page->post_content, 'hj_doctors_dashboard' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'برگهٔ ناحیه پزشک منتشر نشده یا شورت‌کد [hj_doctors_dashboard] در محتوای آن پیدا نشد. برگه را بررسی و در صورت نیاز شورت‌کد را اضافه کنید؛ محتوای موجود تغییر نکرده است.', 'hamyar-doctors' ) . '</p></div>';
			}
		}

		/** Dashboard output contains private account data and nonces. */
		public static function prevent_dashboard_cache() {
			$id = (int) get_option( 'hj_doctors_dashboard_page' );
			if ( $id && is_page( $id ) ) {
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', true );
				}
				nocache_headers();
			}
		}

		/** Preserve old home-page links when the registered page was renamed. */
		public static function redirect_old_dashboard_url() {
			global $wp;
			$page = get_post( (int) get_option( 'hj_doctors_dashboard_page' ) );
			if ( is_404() && isset( $wp->request ) && 'my-profile' === trim( $wp->request, '/' )
				&& $page instanceof WP_Post && 'page' === $page->post_type && 'publish' === $page->post_status
				&& has_shortcode( $page->post_content, 'hj_doctors_dashboard' ) ) {
				wp_safe_redirect( get_permalink( $page ), 302 );
				exit;
			}
		}

		/**
		 * ثبت/چاپ JS و CSS داشبورد.
		 *
		 * @param bool $force فعلاً فقط برای هم‌خوانی با صدا زدن‌ها؛ همیشه enqueue می‌کند.
		 */
		private static function ensure_assets( $force = false ) {
			unset( $force );
			wp_register_script( 'hj-dash-js', hj_doctors_url( 'assets/js/dashboard.js' ), array(), HJ_DOCTORS_VERSION, true );
			wp_localize_script(
				'hj-dash-js',
				'HJ_DASH',
				array(
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE ),
					'maxSpec' => self::max_specialties(),
					'maxDocs' => self::max_docs(),
					'i18n'    => array(
						'fail'    => __( 'درخواست ناموفق بود؛ دوباره تلاش کنید.', 'hamyar-doctors' ),
						'session' => __( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.', 'hamyar-doctors' ),
					),
				)
			);
			wp_register_style( 'hj-dash-css', hj_doctors_url( 'assets/css/dashboard.css' ), array(), HJ_DOCTORS_VERSION );
			wp_enqueue_script( 'hj-dash-js' );
			wp_enqueue_style( 'hj-dash-css' );
		}

		/* ================================================================== */
		/* Small helpers                                                       */
		/* ================================================================== */

		private static function log( $message ) {
			if ( function_exists( 'hj_doctors_log' ) ) {
				hj_doctors_log( '[dashboard] ' . $message );
			}
		}

		private static function ok( $data ) {
			wp_send_json( array( 'success' => true, 'data' => $data ) );
		}

		private static function fail( $message, $code = 400, $extra = array() ) {
			wp_send_json( array_merge( array( 'success' => false, 'message' => (string) $message ), (array) $extra ), (int) $code );
		}

		/** ارقام لاتین را برای نمایش به فارسی می‌برد. */
		private static function fa( $x ) {
			return str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), (string) $x );
		}

		/** ارقام فارسی/عربی را به لاتین می‌برد (helper بخش ۱). */
		private static function en( $x ) {
			return function_exists( 'hj_to_en_digits' ) ? hj_to_en_digits( $x ) : (string) $x;
		}

		private static function cut( $s, $max ) {
			$s = (string) $s;
			$max = (int) $max;
			if ( $max > 0 && function_exists( 'mb_substr' ) && mb_strlen( $s ) > $max ) {
				return mb_substr( $s, 0, $max );
			}
			return $s;
		}

		/** نرمال‌سازی «H:i» یا «H:i:s» به «H:i»؛ نامعتبر ⇒ رشتهٔ خالی. */
		private static function time_hm( $s ) {
			$s = trim( (string) $s );
			if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $s, $m ) ) {
				return sprintf( '%02d:%02d', min( 23, (int) $m[1] ), min( 59, (int) $m[2] ) );
			}
			return '';
		}

		/** روزهای هفتهٔ ساعات کاری (کلیدها در ACF ذخیره می‌شوند). */
		private static function days() {
			$days = array(
				'saturday'  => 'شنبه',
				'sunday'    => 'یکشنبه',
				'monday'    => 'دوشنبه',
				'tuesday'   => 'سه‌شنبه',
				'wednesday' => 'چهارشنبه',
				'thursday'  => 'پنجشنبه',
				'friday'    => 'جمعه',
			);
			$filtered = apply_filters( 'hj_dash_days', $days );
			return ( is_array( $filtered ) && $filtered ) ? $filtered : $days;
		}

		private static function days_source() {
			$src = ( false !== has_filter( 'hj_dash_days' ) ) ? 'filter hj_dash_days' : 'default';
			return array( self::days(), $src );
		}

		private static function max_specialties() {
			return max( 1, (int) apply_filters( 'hj_dash_max_specialties', 5 ) );
		}

		private static function max_docs() {
			return max( 1, (int) apply_filters( 'hj_dash_max_docs', 5 ) );
		}

		private static function can_edit( $pid ) {
			if ( function_exists( 'hj_doctors_user_can_edit_profile' ) ) {
				return (bool) hj_doctors_user_can_edit_profile( (int) $pid );
			}
			return current_user_can( 'edit_post', (int) $pid );
		}

		private static function can_manage( $what ) {
			if ( function_exists( 'hj_doctors_user_can_manage' ) ) {
				return (bool) hj_doctors_user_can_manage( $what );
			}
			return current_user_can( 'manage_options' );
		}

		private static function is_doctor_user() {
			return function_exists( 'hj_doctors_is_doctor_user' ) ? (bool) hj_doctors_is_doctor_user() : false;
		}

		private static function terms( $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}
			$t = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 300,
				)
			);
			return is_wp_error( $t ) ? array() : $t;
		}

		private static function post_term_ids( $pid, $taxonomy ) {
			if ( ! $pid || ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}
			$ids = wp_get_object_terms( (int) $pid, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) ) {
				return array();
			}
			return array_map( 'intval', $ids );
		}

		/* ================================================================== */
		/* Storage (ACF with meta fallback)                                    */
		/* ================================================================== */

		private static function val( $pid, $key ) {
			if ( ! $pid ) {
				return '';
			}
			if ( function_exists( 'get_field' ) ) {
				$v = get_field( $key, (int) $pid );
				if ( null !== $v ) {
					return $v;
				}
			}
			return get_post_meta( (int) $pid, $key, true );
		}

		private static function set( $pid, $key, $value ) {
			if ( function_exists( 'update_field' ) ) {
				update_field( $key, $value, (int) $pid );
				return;
			}
			update_post_meta( (int) $pid, $key, $value );
		}

		private static function has_photo( $pid ) {
			$aid = (int) self::val( $pid, 'profile_photo' );
			return $aid > 0 && 'attachment' === get_post_type( $aid );
		}

		/** ردیف‌های ساعات کاری به نقشهٔ day => [closed,start,end]. */
		private static function hours_map( $pid ) {
			$rows = self::val( $pid, 'working_hours' );
			$map  = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					if ( ! is_array( $r ) || empty( $r['day_of_week'] ) ) {
						continue;
					}
					$map[ $r['day_of_week'] ] = array(
						'closed' => ! empty( $r['is_closed'] ),
						'start'  => self::time_hm( isset( $r['start_time'] ) ? $r['start_time'] : '' ),
						'end'    => self::time_hm( isset( $r['end_time'] ) ? $r['end_time'] : '' ),
					);
				}
			}
			return $map;
		}

		/* ================================================================== */
		/* Status & completeness                                               */
		/* ================================================================== */

		private static function status_map() {
			return array(
				'draft'     => __( 'پیش‌نویس', 'hamyar-doctors' ),
				'pending'   => __( 'در انتظار بررسی', 'hamyar-doctors' ),
				'published' => __( 'منتشرشده', 'hamyar-doctors' ),
				'rejected'  => __( 'نیازمند اصلاح', 'hamyar-doctors' ),
			);
		}

		private static function status_labels() {
			return self::status_map();
		}

		private static function status( $pid ) {
			$st = (string) get_post_meta( (int) $pid, self::META_STATUS, true );
			$map = self::status_map();
			return isset( $map[ $st ] ) ? $st : 'draft';
		}

		private static function write_status( $pid, $st ) {
			$map = self::status_map();
			if ( isset( $map[ $st ] ) ) {
				update_post_meta( (int) $pid, self::META_STATUS, $st );
			}
		}

		private static function completeness( $pid ) {
			$missing = array();
			$req     = array(
				'name_fa'        => __( 'نام فارسی', 'hamyar-doctors' ),
				'specialty_fa'   => __( 'عنوان تخصص', 'hamyar-doctors' ),
				'phone_clinic'   => __( 'تلفن مطب', 'hamyar-doctors' ),
				'address_clinic' => __( 'نشانی مطب', 'hamyar-doctors' ),
			);
			foreach ( $req as $k => $label ) {
				if ( trim( (string) ( is_array( self::val( $pid, $k ) ) ? '' : self::val( $pid, $k ) ) ) === '' ) {
					$missing[] = $label;
				}
			}
			if ( ! self::post_term_ids( $pid, 'city' ) ) {
				$missing[] = __( 'شهر', 'hamyar-doctors' );
			}

			$slots  = array( 'name_fa', 'name_en', 'specialty_fa', 'specialty_en', 'bio_fa', 'bio_en', 'medical_license', 'experience_years', 'education', 'phone_clinic', 'address_clinic', 'map_link', 'surgeries_performed' );
			$filled = 0;
			$total  = count( $slots ) + 3;
			foreach ( $slots as $k ) {
				if ( trim( (string) ( is_array( self::val( $pid, $k ) ) ? '' : self::val( $pid, $k ) ) ) !== '' ) {
					$filled++;
				}
			}
			if ( self::has_photo( $pid ) ) {
				$filled++;
			}
			if ( self::post_term_ids( $pid, 'city' ) ) {
				$filled++;
			}
			if ( self::hours_map( $pid ) ) {
				$filled++;
			}
			$pct = $total > 0 ? (int) round( ( $filled / $total ) * 100 ) : 0;

			return array(
				'missing'    => $missing,
				'can_submit' => ! $missing,
				'pct'        => min( 100, max( 0, $pct ) ),
			);
		}

		private static function state( $pid ) {
			$map = self::status_map();
			$st  = $pid ? self::status( $pid ) : 'draft';
			$c   = $pid ? self::completeness( $pid ) : array( 'missing' => array(), 'can_submit' => false, 'pct' => 0 );
			$name = $pid ? (string) ( is_array( self::val( $pid, 'name_fa' ) ) ? '' : self::val( $pid, 'name_fa' ) ) : '';

			return array(
				'status'        => $st,
				'status_label'  => isset( $map[ $st ] ) ? $map[ $st ] : $st,
				'completeness'  => $c['pct'],
				'pct_label'     => self::fa( $c['pct'] ) . '٪',
				'missing'       => $c['missing'],
				'can_submit'    => (bool) $c['can_submit'],
				'name'          => $name,
				'public_url'    => ( $pid && 'published' === $st ) ? (string) get_permalink( (int) $pid ) : '',
			);
		}

		private static function current_pid() {
			if ( ! function_exists( 'hj_doctors_get_profile_id' ) ) {
				return 0;
			}
			return (int) hj_doctors_get_profile_id();
		}

		/* ================================================================== */
		/* Ratings (read-only; table of the existing voting plugin)            */
		/* ================================================================== */

		private static function votes_columns() {
			global $wpdb;
			$candidates = array(
				$wpdb->prefix . 'hj_doctor_votes',
				$wpdb->prefix . 'doctor_votes',
				$wpdb->prefix . 'hj_ratings',
				$wpdb->prefix . 'hj_doctor_ratings',
			);
			foreach ( $candidates as $t ) {
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
					$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t}" );
					return array( $t, is_array( $cols ) ? $cols : array() );
				}
			}
			return array( '', array() );
		}

		private static function rating_stats( $pid ) {
			global $wpdb;
			list( $table, $cols ) = self::votes_columns();
			$zero = array( 'approved' => 0, 'pending' => 0, 'avg' => 0 );
			if ( ! $table || ! $cols ) {
				return $zero;
			}
			$doc = '';
			foreach ( array( 'doctor_id', 'doctor_post_id', 'post_id', 'profile_id' ) as $c ) {
				if ( in_array( $c, $cols, true ) ) { $doc = $c; break; }
			}
			$score = '';
			foreach ( array( 'score', 'rating', 'stars', 'vote' ) as $c ) {
				if ( in_array( $c, $cols, true ) ) { $score = $c; break; }
			}
			$stc = '';
			foreach ( array( 'status', 'vote_status', 'state' ) as $c ) {
				if ( in_array( $c, $cols, true ) ) { $stc = $c; break; }
			}
			if ( ! $doc || ! $score || ! $stc ) {
				return $zero;
			}
			$approved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$doc} = %d AND {$stc} = 'approved'", (int) $pid ) );
			$pending  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$doc} = %d AND {$stc} = 'pending'", (int) $pid ) );
			$avg      = (float) $wpdb->get_var( $wpdb->prepare( "SELECT AVG({$score}) FROM {$table} WHERE {$doc} = %d AND {$stc} = 'approved'", (int) $pid ) );
			return array( 'approved' => $approved, 'pending' => $pending, 'avg' => $avg );
		}

		/* ================================================================== */
		/* Field builders                                                      */
		/* ================================================================== */

		private static function f_text( $name, $label, $value, $o = array() ) {
			$id    = 'hj-dash-' . $name;
			$type  = isset( $o['type'] ) ? $o['type'] : 'text';
			$dir   = ( ! empty( $o['ltr'] ) || 'url' === $type ) ? 'ltr' : 'rtl';
			$attrs = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '"';
			$attrs .= ' value="' . esc_attr( (string) $value ) . '" dir="' . $dir . '"';
			if ( ! empty( $o['max'] ) ) { $attrs .= ' maxlength="' . (int) $o['max'] . '"'; }
			if ( ! empty( $o['ph'] ) ) { $attrs .= ' placeholder="' . esc_attr( $o['ph'] ) . '"'; }
			if ( ! empty( $o['pv'] ) ) { $attrs .= ' data-pv="' . esc_attr( $o['pv'] ) . '"'; }
			if ( ! empty( $o['mode'] ) ) { $attrs .= ' data-mode="' . esc_attr( $o['mode'] ) . '"'; }
			$req = ! empty( $o['req'] ) ? ' <span class="hj-dash__req">*</span>' : '';
			return '<div class="hj-dash__field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $req . '</label>'
				. '<input' . $attrs . '>'
				. '<small class="hj-dash__err" data-err="' . esc_attr( $name ) . '" role="status"></small></div>';
		}

		private static function f_area( $name, $label, $value, $o = array() ) {
			$id    = 'hj-dash-' . $name;
			$dir   = ! empty( $o['ltr'] ) ? 'ltr' : 'rtl';
			$attrs = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" dir="' . $dir . '"';
			$attrs .= ' rows="' . ( ! empty( $o['rows'] ) ? (int) $o['rows'] : 4 ) . '"';
			if ( ! empty( $o['max'] ) ) { $attrs .= ' maxlength="' . (int) $o['max'] . '"'; }
			if ( ! empty( $o['ph'] ) ) { $attrs .= ' placeholder="' . esc_attr( $o['ph'] ) . '"'; }
			if ( ! empty( $o['pv'] ) ) { $attrs .= ' data-pv="' . esc_attr( $o['pv'] ) . '"'; }
			return '<div class="hj-dash__field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
				. '<textarea' . $attrs . '>' . esc_textarea( (string) $value ) . '</textarea>'
				. '<small class="hj-dash__err" data-err="' . esc_attr( $name ) . '" role="status"></small></div>';
		}

		/* ================================================================== */
		/* Render                                                              */
		/* ================================================================== */

		public static function render() {
			self::ensure_assets( true );

			$uid  = get_current_user_id();
			$pid  = self::current_pid();
			$user = wp_get_current_user();
			$v    = function ( $k ) use ( $pid ) {
				$x = HJ_Doctors_Dashboard::get_text( $pid, $k );
				return $x;
			};

			$state    = $pid ? self::state( $pid ) : array(
				'status' => 'draft', 'status_label' => 'پیش‌نویس', 'completeness' => 0, 'pct_label' => self::fa( 0 ) . '٪',
				'missing' => array(), 'can_submit' => false, 'name' => '', 'public_url' => '',
			);
			$name     = $state['name'] !== '' ? $state['name'] : $user->display_name;
			$photo    = self::photo_url( $pid );
			$hours    = $pid ? self::hours_map( $pid ) : array();
			$types    = self::terms( 'specialty_type' );
			$specs    = self::terms( 'specialty' );
			$provs    = self::terms( 'province' );
			$cities   = self::terms( 'city' );
			$sel_type = self::post_term_ids( $pid, 'specialty_type' );
			$sel_spec = self::post_term_ids( $pid, 'specialty' );
			$sel_prov = self::post_term_ids( $pid, 'province' );
			$sel_city = self::post_term_ids( $pid, 'city' );
			$sel_type = $sel_type ? $sel_type[0] : 0;
			$sel_prov = $sel_prov ? $sel_prov[0] : 0;
			$sel_city = $sel_city ? $sel_city[0] : 0;
			$stats    = $pid ? self::rating_stats( $pid ) : null;
			$can_view = $pid && function_exists( 'hj_doctors_user_can_view_ratings' ) ? hj_doctors_user_can_view_ratings( $pid ) : false;
			$city_txt = '';
			if ( $sel_city ) {
				$t        = get_term( $sel_city, 'city' );
				$city_txt = ( $t && ! is_wp_error( $t ) ) ? $t->name : '';
			}
			$spec_fa = $v( 'specialty_fa' );
			$spec_en = $v( 'specialty_en' );

			ob_start();
			?>
<div class="hj-dash" id="hj-dash" dir="rtl" lang="fa">

  <header class="hj-dash__head">
    <div class="hj-dash__who">
      <img class="hj-dash__avatar hj-dash__avatar--sm" data-hj="avatar" src="<?php echo esc_url( $photo ); ?>" alt="" width="52" height="52">
      <div>
        <h2 class="hj-dash__title" data-hj="head-name"><?php echo esc_html( $name ); ?></h2>
        <span class="hj-dash__chip-status" data-hj="status-chip" data-status="<?php echo esc_attr( $state['status'] ); ?>"><?php echo esc_html( $state['status_label'] ); ?></span>
      </div>
    </div>
    <a class="hj-dash__link" data-hj="public-link" href="<?php echo esc_url( $state['public_url'] ); ?>"<?php echo $state['public_url'] ? '' : ' hidden'; ?>>مشاهدهٔ صفحهٔ عمومی</a>
  </header>

  <div class="hj-dash__grid">

    <form class="hj-dash__form" id="hj-dash-form" novalidate autocomplete="off">
      <input type="hidden" name="pid" value="<?php echo (int) $pid; ?>">

      <!-- ۱) اطلاعات پایه -->
      <section class="hj-dash__card">
        <h3 class="hj-dash__h">اطلاعات پایه</h3>

        <div class="hj-dash__photo">
          <img class="hj-dash__avatar" data-hj="avatar" src="<?php echo esc_url( $photo ); ?>" alt="" width="84" height="84">
          <div class="hj-dash__photo-actions">
            <input class="hj-dash__file" type="file" id="hj-dash-photo" accept="image/jpeg,image/png" data-needs-pid<?php echo $pid ? '' : ' disabled'; ?>>
            <label class="hj-dash__btn hj-dash__btn--ghost" for="hj-dash-photo">انتخاب عکس</label>
            <button type="button" class="hj-dash__btn hj-dash__btn--text" data-hj="photo-remove" data-needs-pid<?php echo ( $pid && self::has_photo( $pid ) ) ? '' : ' hidden'; ?>>حذف عکس</button>
            <small class="hj-dash__muted" data-hj="pid-hint"<?php echo $pid ? ' hidden' : ''; ?>>برای بارگذاری عکس، ابتدا یک‌بار اطلاعات را ذخیره کنید.</small>
            <small class="hj-dash__err" data-err="photo" role="status"></small>
          </div>
        </div>

        <div class="hj-dash__pair">
          <?php echo self::f_text( 'name_fa', 'نام و نام خانوادگی', $v( 'name_fa' ), array( 'req' => true, 'max' => 80, 'pv' => 'name_fa' ) ); ?>
          <?php echo self::f_text( 'name_en', 'Full name', $v( 'name_en' ), array( 'en' => true, 'max' => 80, 'pv' => 'name_en' ) ); ?>
        </div>
        <div class="hj-dash__pair">
          <?php echo self::f_text( 'specialty_fa', 'عنوان تخصص', $spec_fa, array( 'max' => 80, 'pv' => 'specialty_fa', 'ph' => 'مثلاً متخصص ارتوپدی' ) ); ?>
          <?php echo self::f_text( 'specialty_en', 'Specialty title', $spec_en, array( 'en' => true, 'max' => 80, 'pv' => 'specialty_en' ) ); ?>
        </div>
        <div class="hj-dash__pair">
          <?php echo self::f_area( 'bio_fa', 'معرفی کوتاه', $v( 'bio_fa' ), array( 'rows' => 5 ) ); ?>
          <?php echo self::f_area( 'bio_en', 'Short bio', $v( 'bio_en' ), array( 'en' => true, 'rows' => 5 ) ); ?>
        </div>
      </section>

      <!-- ۲) تخصص و سوابق -->
      <section class="hj-dash__card">
        <h3 class="hj-dash__h">تخصص و سوابق</h3>

        <?php if ( $types ) : ?>
        <div class="hj-dash__field">
          <label for="hj-dash-type">نوع تخصص</label>
          <select id="hj-dash-type" name="specialty_type">
            <option value="0">انتخاب کنید</option>
            <?php foreach ( $types as $t ) : ?>
              <option value="<?php echo (int) $t->term_id; ?>"<?php selected( $sel_type, (int) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
            <?php endforeach; ?>
          </select>
          <small class="hj-dash__err" data-err="specialty_type" role="status"></small>
        </div>
        <?php endif; ?>

        <?php if ( $specs ) : ?>
        <div class="hj-dash__field">
          <span class="hj-dash__label">رشته‌ها <em class="hj-dash__muted">(حداکثر <?php echo esc_html( self::fa( self::max_specialties() ) ); ?> مورد)</em></span>
          <div class="hj-dash__chips" id="hj-dash-specs">
            <?php
            foreach ( $specs as $t ) :
				$tid = (int) $t->term_id;
				$dt  = (int) get_term_meta( $tid, 'hj_specialty_type_id', true );
				?>
              <label class="hj-dash__chip" data-type="<?php echo $dt; ?>">
                <input type="checkbox" name="specialty[]" value="<?php echo $tid; ?>"<?php checked( in_array( $tid, $sel_spec, true ) ); ?>>
                <span><?php echo esc_html( $t->name ); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <small class="hj-dash__err" data-err="specialty" role="status"></small>
        </div>
        <?php endif; ?>

        <div class="hj-dash__pair">
          <?php echo self::f_text( 'medical_license', 'شمارهٔ نظام پزشکی', self::fa( $v( 'medical_license' ) ), array( 'ltr' => true, 'max' => 24, 'mode' => 'numeric' ) ); ?>
          <?php echo self::f_text( 'experience_years', 'سابقهٔ کار (سال)', self::fa( $v( 'experience_years' ) ), array( 'ltr' => true, 'max' => 3, 'mode' => 'numeric' ) ); ?>
        </div>
        <?php echo self::f_area( 'education', 'تحصیلات و دوره‌ها', $v( 'education' ), array( 'rows' => 4, 'max' => 1500 ) ); ?>
        <?php echo self::f_area( 'surgeries_performed', 'جراحی‌های انجام‌شده', $v( 'surgeries_performed' ), array( 'rows' => 4, 'max' => 7000, 'ph' => 'هر مورد در یک خط' ) ); ?>
      </section>

      <!-- ۳) تماس و نشانی -->
      <section class="hj-dash__card">
        <h3 class="hj-dash__h">تماس و نشانی</h3>

        <?php if ( $provs && $cities ) : ?>
        <div class="hj-dash__pair">
          <div class="hj-dash__field">
            <label for="hj-dash-province">استان</label>
            <select id="hj-dash-province" name="province">
              <option value="0">انتخاب کنید</option>
              <?php foreach ( $provs as $t ) : ?>
                <option value="<?php echo (int) $t->term_id; ?>"<?php selected( $sel_prov, (int) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
              <?php endforeach; ?>
            </select>
            <small class="hj-dash__err" data-err="province" role="status"></small>
          </div>
          <div class="hj-dash__field">
            <label for="hj-dash-city">شهر</label>
            <select id="hj-dash-city" name="city">
              <option value="0">انتخاب کنید</option>
              <?php foreach ( $cities as $t ) : ?>
                <option value="<?php echo (int) $t->term_id; ?>" data-province="<?php echo (int) get_term_meta( $t->term_id, 'hj_province_id', true ); ?>"<?php selected( $sel_city, (int) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
              <?php endforeach; ?>
            </select>
            <small class="hj-dash__err" data-err="city" role="status"></small>
          </div>
        </div>
        <?php endif; ?>

        <div class="hj-dash__pair">
          <?php echo self::f_text( 'phone_clinic', 'تلفن مطب', self::fa( $v( 'phone_clinic' ) ), array( 'ltr' => true, 'max' => 20, 'mode' => 'tel' ) ); ?>
          <?php echo self::f_text( 'phone_mobile', 'موبایل', self::fa( $v( 'phone_mobile' ) ), array( 'ltr' => true, 'max' => 20, 'mode' => 'tel' ) ); ?>
        </div>
        <div class="hj-dash__pair">
          <?php echo self::f_text( 'address_clinic', 'نشانی مطب', $v( 'address_clinic' ), array( 'max' => 250 ) ); ?>
          <?php echo self::f_text( 'address_hospital', 'نشانی بیمارستان', $v( 'address_hospital' ), array( 'max' => 250 ) ); ?>
        </div>
        <?php echo self::f_text( 'map_link', 'لینک نقشه (گوگل‌مپ، نشان یا بلد)', $v( 'map_link' ), array( 'ltr' => true, 'type' => 'url', 'max' => 300, 'ph' => 'https://' ) ); ?>
      </section>

      <!-- ۴) ساعات کاری -->
      <section class="hj-dash__card">
        <h3 class="hj-dash__h">ساعات کاری</h3>
        <div class="hj-dash__hours">
          <?php
          foreach ( self::days() as $key => $label ) :
				$r  = isset( $hours[ $key ] ) ? $hours[ $key ] : array( 'closed' => false, 'start' => '', 'end' => '' );
				$dk = esc_attr( $key );
				?>
            <div class="hj-dash__hrow">
              <span class="hj-dash__day"><?php echo esc_html( $label ); ?></span>
              <label class="hj-dash__tog">
                <input type="checkbox" name="hours[<?php echo $dk; ?>][closed]" value="1" data-hj="closed"<?php checked( $r['closed'] ); ?>>
                <span>تعطیل</span>
              </label>
              <input type="time" name="hours[<?php echo $dk; ?>][start]" value="<?php echo esc_attr( $r['start'] ); ?>" aria-label="شروع"<?php echo $r['closed'] ? ' disabled' : ''; ?> dir="ltr">
              <span class="hj-dash__to">تا</span>
              <input type="time" name="hours[<?php echo $dk; ?>][end]" value="<?php echo esc_attr( $r['end'] ); ?>" aria-label="پایان"<?php echo $r['closed'] ? ' disabled' : ''; ?> dir="ltr">
              <small class="hj-dash__err" data-err="hours_<?php echo $dk; ?>" role="status"></small>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ۵) مدارک -->
      <section class="hj-dash__card">
        <h3 class="hj-dash__h">مدارک</h3>
        <p class="hj-dash__muted">مدارک فقط برای بررسی مدیریت نگه‌داری می‌شوند و عمومی نیستند. فرمت PDF، JPG یا PNG.</p>
        <div class="hj-dash__docs-wrap">
          <input class="hj-dash__file" type="file" id="hj-dash-doc" accept="application/pdf,image/jpeg,image/png" data-needs-pid<?php echo $pid ? '' : ' disabled'; ?>>
          <label class="hj-dash__btn hj-dash__btn--ghost" for="hj-dash-doc">بارگذاری مدرک</label>
          <small class="hj-dash__err" data-err="doc" role="status"></small>
        </div>
        <div data-hj="docs"><?php echo $pid ? self::render_docs( $pid ) : ''; ?></div>
      </section>

      <div class="hj-dash__savebar">
        <button type="submit" class="hj-dash__btn hj-dash__btn--primary" data-hj="save">ذخیرهٔ تغییرات</button>
        <small class="hj-dash__err" data-err="form" role="status"></small>
      </div>
    </form>

    <aside class="hj-dash__side">

      <section class="hj-dash__card hj-dash__preview" aria-label="پیش‌نمایش">
        <img class="hj-dash__avatar hj-dash__avatar--lg" data-hj="avatar" src="<?php echo esc_url( $photo ); ?>" alt="" width="96" height="96">
        <div class="hj-dash__pv-name" data-pvout="name_fa"><?php echo esc_html( $v( 'name_fa' ) ); ?></div>
        <div class="hj-dash__pv-name-en" dir="ltr" data-pvout="name_en"><?php echo esc_html( $v( 'name_en' ) ); ?></div>
        <div class="hj-dash__pv-spec" data-pvout="specialty_fa"><?php echo esc_html( $spec_fa ); ?></div>
        <div class="hj-dash__pv-spec-en" dir="ltr" data-pvout="specialty_en"><?php echo esc_html( $spec_en ); ?></div>
        <div class="hj-dash__pv-city" data-hj="pv-city"><?php echo esc_html( $city_txt ); ?></div>
      </section>

      <section class="hj-dash__card">
        <h3 class="hj-dash__h">وضعیت پروفایل</h3>
        <div class="hj-dash__bar"><span data-hj="bar" style="width:<?php echo (int) $state['completeness']; ?>%"></span></div>
        <div class="hj-dash__pct"><span data-hj="pct"><?php echo esc_html( $state['pct_label'] ); ?></span> <span class="hj-dash__muted">تکمیل شده</span></div>
        <p class="hj-dash__muted" data-hj="missing"<?php echo $state['missing'] ? '' : ' hidden'; ?>><?php echo $state['missing'] ? esc_html( 'برای ارسال، تکمیل کنید: ' . implode( '، ', $state['missing'] ) ) : ''; ?></p>
        <button type="button" class="hj-dash__btn hj-dash__btn--primary hj-dash__btn--block" data-hj="submit-review"
          <?php echo in_array( $state['status'], array( 'draft', 'rejected' ), true ) ? '' : ' hidden'; ?>
          <?php echo ( $pid && $state['can_submit'] ) ? '' : ' disabled'; ?>>ارسال برای بررسی</button>
        <p class="hj-dash__muted" data-hj="pending-note"<?php echo $state['status'] === 'pending' ? '' : ' hidden'; ?>>پروفایل شما در صف بررسی است.</p>
        <small class="hj-dash__err" data-err="submit" role="status"></small>
      </section>

      <?php if ( $can_view && $stats ) : ?>
      <section class="hj-dash__card hj-dash__rating">
        <h3 class="hj-dash__h">امتیاز</h3>
        <?php
        $avg   = ( $stats['approved'] >= 5 ) ? $stats['avg'] : null;
        $full  = $avg !== null ? (int) round( $avg ) : 0;
        ?>
        <div class="hj-dash__avg">
          <strong><?php echo $avg !== null ? esc_html( self::fa( number_format_i18n( $avg, 1 ) ) ) : '—'; ?></strong>
          <span class="hj-dash__stars" aria-hidden="true"><?php echo esc_html( str_repeat( '★', $full ) . str_repeat( '☆', 5 - $full ) ); ?></span>
        </div>
        <div class="hj-dash__stat-row"><span>رأی‌های ثبت‌شده</span><b><?php echo esc_html( self::fa( $stats['approved'] ) ); ?></b></div>
        <div class="hj-dash__stat-row"><span>در انتظار بررسی</span><b><?php echo esc_html( self::fa( $stats['pending'] ) ); ?></b></div>
      </section>
      <?php endif; ?>

      <?php
      if ( $pid ) {
          ob_start();
          do_action( 'hj_doctor_dashboard_appointments', $pid );
          $apt = trim( ob_get_clean() );
          if ( $apt !== '' ) {
              echo '<section class="hj-dash__card hj-dash__apt">' . $apt . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput -- output of third-party action hook
          }
          ob_start();
          do_action( 'hj_doctor_dashboard_widgets', $pid );
          $wid = trim( ob_get_clean() );
          if ( $wid !== '' ) {
              echo '<section class="hj-dash__card hj-dash__widgets">' . $wid . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
          }
      }
      ?>
    </aside>
  </div>
</div>
			<?php
			return ob_get_clean();
		}

		/** Public wrapper used by the render closure. */
		public static function get_text( $pid, $k ) {
			$x = self::val( $pid, $k );
			return is_array( $x ) ? '' : (string) $x;
		}

		private static function photo_url( $pid ) {
			if ( $pid && self::has_photo( $pid ) ) {
				$u = wp_get_attachment_image_url( (int) self::val( $pid, 'profile_photo' ), 'medium' );
				if ( $u ) {
					return $u;
				}
			}
			if ( $pid && function_exists( 'hj_dp_photo_url' ) ) {
				$u = hj_dp_photo_url( $pid );
				if ( $u ) {
					return $u;
				}
			}
			return function_exists( 'hj_doctors_default_photo_url' ) ? hj_doctors_default_photo_url() : '';
		}

		/* ================================================================== */
		/* AJAX guard + input helpers                                          */
		/* ================================================================== */

		private static function guard( $bucket, $limit, $window, $need_pid = true ) {
			if ( ! is_user_logged_in() ) {
				self::fail( 'ابتدا وارد حساب خود شوید.', 401 );
			}
			$nonce = isset( $_POST['_hjn'] ) ? sanitize_text_field( wp_unslash( $_POST['_hjn'] ) ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
				self::fail( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.', 403 );
			}
			if ( function_exists( 'hj_sec_rate_limit' ) && ! hj_sec_rate_limit( 'dash_' . $bucket, $limit, $window, (string) get_current_user_id() ) ) {
				self::fail( 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.', 429 );
			}
			$pid = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0;
			if ( $need_pid && ( ! $pid || ! self::can_edit( $pid ) ) ) {
				self::fail( 'دسترسی غیرمجاز.', 403 );
			}
			return $pid;
		}

		private static function in( $key, $type = 'text', $max = 0 ) {
			$raw = ( isset( $_POST[ $key ] ) && ! is_array( $_POST[ $key ] ) ) ? wp_unslash( $_POST[ $key ] ) : '';
			if ( function_exists( 'hj_sec_clean' ) ) {
				$v = hj_sec_clean( $raw, $type );
			} elseif ( $type === 'textarea' ) {
				$v = sanitize_textarea_field( $raw );
			} elseif ( $type === 'url' ) {
				$v = esc_url_raw( $raw );
			} else {
				$v = sanitize_text_field( $raw );
			}
			return self::cut( is_scalar( $v ) ? (string) $v : '', $max );
		}

		private static function allowed_map_host( $url ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( $host === '' ) {
				return false;
			}
			$hosts = apply_filters( 'hj_doctors_map_hosts', array(
				'google.com', 'maps.google.com', 'goo.gl', 'maps.app.goo.gl', 'g.page',
				'neshan.org', 'nshn.ir', 'balad.ir',
			) );
			foreach ( (array) $hosts as $h ) {
				$h = strtolower( trim( (string) $h ) );
				if ( $h !== '' && ( $host === $h || substr( $host, -strlen( '.' . $h ) ) === '.' . $h ) ) {
					return true;
				}
			}
			return false;
		}

		/* ================================================================== */
		/* AJAX: save                                                          */
		/* ================================================================== */

		public static function ajax_save() {
			$pid = self::guard( 'save', 60, HOUR_IN_SECONDS, false );
			if ( $pid && ! self::can_edit( $pid ) ) {
				self::fail( 'دسترسی غیرمجاز.', 403 );
			}
			if ( ! $pid && ! self::is_doctor_user() ) {
				self::fail( 'دسترسی غیرمجاز.', 403 );
			}

			$errors = array();

			// ---- Text fields
			$name_fa = self::in( 'name_fa', 'text', 80 );
			$name_en = self::in( 'name_en', 'text', 80 );
			$spec_fa = self::in( 'specialty_fa', 'text', 80 );
			$spec_en = self::in( 'specialty_en', 'text', 80 );
			$bio_fa  = self::in( 'bio_fa', 'textarea', 2000 );
			$bio_en  = self::in( 'bio_en', 'textarea', 2000 );
			$edu     = self::in( 'education', 'textarea', 1500 );
			$addr_c  = self::in( 'address_clinic', 'text', 250 );
			$addr_h  = self::in( 'address_hospital', 'text', 250 );

			if ( $name_fa === '' ) {
				$errors['name_fa'] = 'نام را وارد کنید.';
			}

			// ---- License / experience (stored as Latin digits)
			$license = trim( self::en( self::in( 'medical_license', 'text', 24 ) ) );
			if ( $license !== '' && ! preg_match( '/^[A-Za-z0-9\-\/]{1,24}$/', $license ) ) {
				$errors['medical_license'] = 'شمارهٔ نظام پزشکی نامعتبر است.';
			}

			$exp = trim( self::en( self::in( 'experience_years', 'text', 3 ) ) );
			if ( $exp !== '' && ( ! ctype_digit( $exp ) || (int) $exp > 70 ) ) {
				$errors['experience_years'] = 'سابقه باید عددی بین ۰ تا ۷۰ باشد.';
			}

			// ---- Phones
			$phones = array();
			foreach ( array( 'phone_clinic', 'phone_mobile' ) as $k ) {
				$p = preg_replace( '/[\s\-\(\)]+/', '', self::en( self::in( $k, 'text', 20 ) ) );
				if ( $p !== '' && ! preg_match( '/^\+?\d{6,14}$/', $p ) ) {
					$errors[ $k ] = 'شمارهٔ تماس نامعتبر است.';
				}
				$phones[ $k ] = $p;
			}

			// ---- Map link
			$map = self::in( 'map_link', 'url', 300 );
			if ( $map !== '' ) {
				if ( strpos( $map, 'https://' ) !== 0 || ! self::allowed_map_host( $map ) ) {
					$errors['map_link'] = 'لینک باید https و از گوگل‌مپ، نشان یا بلد باشد.';
				}
			}

			// ---- Surgeries (one per line)
			$surg_raw = self::in( 'surgeries_performed', 'textarea', 7000 );
			$lines    = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $surg_raw ) as $ln ) {
				$ln = trim( self::cut( $ln, 120 ) );
				if ( $ln !== '' ) {
					$lines[] = $ln;
				}
				if ( count( $lines ) >= 60 ) {
					break;
				}
			}
			$surgeries = implode( "\n", $lines );

			// ---- Taxonomies
			$type_id = isset( $_POST['specialty_type'] ) ? absint( $_POST['specialty_type'] ) : 0;
			if ( $type_id && ( ! taxonomy_exists( 'specialty_type' ) || ! term_exists( $type_id, 'specialty_type' ) ) ) {
				$errors['specialty_type'] = 'انتخاب نامعتبر است.';
			}
			$spec_ids = array();
			if ( isset( $_POST['specialty'] ) && is_array( $_POST['specialty'] ) ) {
				foreach ( $_POST['specialty'] as $s ) {
					$s = absint( $s );
					if ( $s && taxonomy_exists( 'specialty' ) && term_exists( $s, 'specialty' ) ) {
						$spec_ids[] = $s;
					}
				}
				$spec_ids = array_values( array_unique( $spec_ids ) );
			}
			if ( count( $spec_ids ) > self::max_specialties() ) {
				$errors['specialty'] = 'تعداد رشته‌ها بیش از حد مجاز است.';
			}
			$prov_id = isset( $_POST['province'] ) ? absint( $_POST['province'] ) : 0;
			$city_id = isset( $_POST['city'] ) ? absint( $_POST['city'] ) : 0;
			if ( $prov_id && ( ! taxonomy_exists( 'province' ) || ! term_exists( $prov_id, 'province' ) ) ) {
				$errors['province'] = 'استان نامعتبر است.';
			}
			if ( $city_id ) {
				if ( ! taxonomy_exists( 'city' ) || ! term_exists( $city_id, 'city' ) ) {
					$errors['city'] = 'شهر نامعتبر است.';
				} else {
					$cp = function_exists( 'hj_get_city_province_id' ) ? (int) hj_get_city_province_id( $city_id ) : 0;
					if ( $prov_id && $cp && $cp !== $prov_id ) {
						$errors['city'] = 'این شهر متعلق به استان انتخاب‌شده نیست.';
					} elseif ( ! $prov_id && $cp ) {
						$prov_id = $cp;
					}
				}
			}

			// ---- Working hours
			$hours_in = ( isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ) ? wp_unslash( $_POST['hours'] ) : array();
			$rows     = array();
			$fmt      = apply_filters( 'hj_dash_time_storage_format', 'H:i:s' );
			foreach ( self::days() as $key => $label ) {
				$r = ( isset( $hours_in[ $key ] ) && is_array( $hours_in[ $key ] ) ) ? $hours_in[ $key ] : array();
				$closed = ! empty( $r['closed'] );
				$s = ( isset( $r['start'] ) && is_scalar( $r['start'] ) ) ? self::time_hm( sanitize_text_field( (string) $r['start'] ) ) : '';
				$e = ( isset( $r['end'] ) && is_scalar( $r['end'] ) ) ? self::time_hm( sanitize_text_field( (string) $r['end'] ) ) : '';
				if ( $closed ) {
					$rows[] = array( 'day_of_week' => $key, 'is_closed' => 1, 'start_time' => '', 'end_time' => '' );
					continue;
				}
				if ( $s === '' && $e === '' ) {
					continue;
				}
				if ( $s === '' || $e === '' || strcmp( $s, $e ) >= 0 ) {
					$errors[ 'hours_' . $key ] = 'ساعت شروع باید قبل از پایان باشد.';
					continue;
				}
				$rows[] = array(
					'day_of_week' => $key,
					'is_closed'   => 0,
					'start_time'  => ( $fmt === 'H:i' ) ? $s : $s . ':00',
					'end_time'    => ( $fmt === 'H:i' ) ? $e : $e . ':00',
				);
			}

			if ( $errors ) {
				self::fail( 'لطفاً موارد مشخص‌شده را اصلاح کنید.', 422, array( 'errors' => $errors ) );
			}

			// ---- Create profile on first save
			$created = false;
			if ( ! $pid ) {
				$uid      = get_current_user_id();
				$existing = function_exists( 'hj_doctors_get_profile_id' ) ? (int) hj_doctors_get_profile_id( $uid ) : 0;
				if ( $existing && self::can_edit( $existing ) ) {
					$pid = $existing;
				} else {
					$new = wp_insert_post( wp_slash( array(
						'post_type'   => 'doctor',
						'post_status' => 'draft',
						'post_title'  => $name_fa,
						'post_author' => $uid,
					) ), true );
					if ( is_wp_error( $new ) || ! $new ) {
						self::log( 'wp_insert_post failed for user ' . $uid );
						self::fail( 'ساخت پروفایل ناموفق بود؛ بعداً دوباره تلاش کنید.', 500 );
					}
					$pid = (int) $new;
					update_user_meta( $uid, 'hj_doctor_post_id', $pid );
					self::write_status( $pid, 'draft' );
					$created = true;
				}
			}

			// ---- Write ACF fields
			self::set( $pid, 'name_fa', $name_fa );
			self::set( $pid, 'name_en', $name_en );
			self::set( $pid, 'specialty_fa', $spec_fa );
			self::set( $pid, 'specialty_en', $spec_en );
			self::set( $pid, 'bio_fa', $bio_fa );
			self::set( $pid, 'bio_en', $bio_en );
			self::set( $pid, 'medical_license', $license );
			self::set( $pid, 'experience_years', $exp );
			self::set( $pid, 'education', $edu );
			self::set( $pid, 'surgeries_performed', $surgeries );
			self::set( $pid, 'phone_clinic', $phones['phone_clinic'] );
			self::set( $pid, 'phone_mobile', $phones['phone_mobile'] );
			self::set( $pid, 'address_clinic', $addr_c );
			self::set( $pid, 'address_hospital', $addr_h );
			self::set( $pid, 'map_link', $map );
			self::set( $pid, 'working_hours', $rows );

			// ---- Taxonomies
			if ( taxonomy_exists( 'specialty_type' ) ) {
				wp_set_object_terms( $pid, $type_id ? array( $type_id ) : array(), 'specialty_type' );
			}
			if ( taxonomy_exists( 'specialty' ) ) {
				wp_set_object_terms( $pid, $spec_ids, 'specialty' );
			}
			if ( taxonomy_exists( 'province' ) ) {
				wp_set_object_terms( $pid, $prov_id ? array( $prov_id ) : array(), 'province' );
			}
			if ( taxonomy_exists( 'city' ) ) {
				wp_set_object_terms( $pid, $city_id ? array( $city_id ) : array(), 'city' );
				if ( $city_id && function_exists( 'hj_doctors_sync_province_from_city' ) ) {
					hj_doctors_sync_province_from_city( $pid );
				}
			}

			// ---- Title sync (slug is never changed)
			if ( apply_filters( 'hj_dash_sync_title', true ) && get_the_title( $pid ) !== $name_fa ) {
				wp_update_post( wp_slash( array( 'ID' => $pid, 'post_title' => $name_fa ) ) );
			}

			// ---- Optional: edits to a published profile go back to review
			if ( self::status( $pid ) === 'published' && apply_filters( 'hj_dash_edit_requires_review', false ) && ! self::can_manage( 'approve' ) ) {
				self::write_status( $pid, 'pending' );
				wp_update_post( array( 'ID' => $pid, 'post_status' => 'pending' ) );
			}

			$data            = self::state( $pid );
			$data['pid']     = $pid;
			$data['created'] = $created;
			self::ok( $data );
		}

		/* ================================================================== */
		/* AJAX: photo                                                         */
		/* ================================================================== */

		private static function validate_file( $file, $kind ) {
			if ( ! is_array( $file ) || ! isset( $file['error'] ) || is_array( $file['error'] ) || empty( $file['tmp_name'] ) ) {
				return 'فایل نامعتبر است.';
			}
			if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
				return 'بارگذاری فایل ناموفق بود.';
			}
			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				return 'فایل نامعتبر است.';
			}
			if ( ! function_exists( 'hj_sec_validate_upload' ) ) {
				return 'ماژول امنیت بارگذاری در دسترس نیست.';
			}
			$r = hj_sec_validate_upload( $file, $kind );
			if ( is_wp_error( $r ) ) {
				return $r->get_error_message();
			}
			if ( $r === false ) {
				return 'نوع یا اندازهٔ فایل مجاز نیست.';
			}
			return '';
		}

		public static function ajax_upload_photo() {
			$pid = self::guard( 'upload_photo', 20, HOUR_IN_SECONDS );
			if ( empty( $_FILES['photo'] ) ) {
				self::fail( 'فایلی انتخاب نشده است.', 422 );
			}
			$err = self::validate_file( $_FILES['photo'], 'photo' );
			if ( $err ) {
				self::fail( $err, 422 );
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$old = (int) self::val( $pid, 'profile_photo' );
			$aid = media_handle_upload( 'photo', $pid, array( 'post_author' => get_current_user_id() ) );
			if ( is_wp_error( $aid ) ) {
				self::log( 'media_handle_upload: ' . $aid->get_error_message() );
				self::fail( 'ذخیرهٔ عکس ناموفق بود.', 500 );
			}
			update_post_meta( $aid, self::META_PHOTO_FLAG, 1 );
			self::set( $pid, 'profile_photo', (int) $aid );
			if ( apply_filters( 'hj_dash_sync_thumbnail', true ) ) {
				set_post_thumbnail( $pid, $aid );
			}
			if ( $old && $old !== (int) $aid && get_post_meta( $old, self::META_PHOTO_FLAG, true ) ) {
				wp_delete_attachment( $old, true );
			}

			$data        = self::state( $pid );
			$data['url'] = (string) wp_get_attachment_image_url( $aid, 'medium' );
			self::ok( $data );
		}

		public static function ajax_remove_photo() {
			$pid = self::guard( 'remove_photo', 20, HOUR_IN_SECONDS );
			$old = (int) self::val( $pid, 'profile_photo' );
			self::set( $pid, 'profile_photo', '' );
			if ( $old && get_post_meta( $old, self::META_PHOTO_FLAG, true ) ) {
				if ( (int) get_post_thumbnail_id( $pid ) === $old ) {
					delete_post_thumbnail( $pid );
				}
				wp_delete_attachment( $old, true );
			}
			$data        = self::state( $pid );
			$data['url'] = self::photo_url( $pid );
			self::ok( $data );
		}

		/* ================================================================== */
		/* Private documents                                                   */
		/* ================================================================== */

		private static function docs( $pid ) {
			$d = get_post_meta( $pid, self::META_DOCS, true );
			return is_array( $d ) ? array_values( $d ) : array();
		}

		private static function private_base() {
			if ( ! function_exists( 'hj_sec_private_dir' ) ) {
				return '';
			}
			$d = hj_sec_private_dir();
			return ( is_string( $d ) && $d !== '' ) ? untrailingslashit( $d ) : '';
		}

		private static function doc_path( $pid, $doc ) {
			$base = self::private_base();
			if ( $base === '' || empty( $doc['file'] ) ) {
				return '';
			}
			$rp_base = realpath( $base );
			$rp      = realpath( $base . '/' . ltrim( $doc['file'], '/' ) );
			if ( ! $rp_base || ! $rp || strpos( $rp, $rp_base . DIRECTORY_SEPARATOR ) !== 0 || ! is_file( $rp ) ) {
				return '';
			}
			if ( strpos( str_replace( '\\', '/', $rp ), '/p' . (int) $pid . '/' ) === false ) {
				return '';
			}
			return $rp;
		}

		private static function render_docs( $pid ) {
			$docs = self::docs( $pid );
			if ( ! $docs ) {
				return '';
			}
			$h = '<ul class="hj-dash__docs">';
			foreach ( $docs as $d ) {
				if ( empty( $d['id'] ) ) {
					continue;
				}
				$url = add_query_arg( array(
					'action'   => 'hj_doc_download',
					'pid'      => $pid,
					'doc'      => $d['id'],
					'_wpnonce' => wp_create_nonce( 'hj_doc_dl_' . $pid . '_' . $d['id'] ),
				), admin_url( 'admin-ajax.php' ) );
				$h .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $d['name'] ) . '</a>'
					. '<span class="hj-dash__muted">' . esc_html( self::fa( size_format( (int) $d['size'] ) ) ) . '</span>'
					. '<button type="button" class="hj-dash__btn hj-dash__btn--text" data-doc-del="' . esc_attr( $d['id'] ) . '">حذف</button></li>';
			}
			return $h . '</ul>';
		}

		public static function ajax_upload_doc() {
			$pid = self::guard( 'upload_doc', 20, HOUR_IN_SECONDS );
			if ( empty( $_FILES['doc'] ) ) {
				self::fail( 'فایلی انتخاب نشده است.', 422 );
			}
			$docs = self::docs( $pid );
			if ( count( $docs ) >= self::max_docs() ) {
				self::fail( 'به سقف تعداد مدارک رسیده‌اید.', 422 );
			}
			$file = $_FILES['doc'];
			$err  = self::validate_file( $file, 'document' );
			if ( $err ) {
				self::fail( $err, 422 );
			}
			if ( ! class_exists( 'finfo' ) ) {
				self::fail( 'بررسی نوع فایل در سرور ممکن نیست.', 500 );
			}
			$fi   = new finfo( FILEINFO_MIME_TYPE );
			$mime = (string) $fi->file( $file['tmp_name'] );
			$exts = array( 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png' );
			if ( ! isset( $exts[ $mime ] ) ) {
				self::fail( 'فقط PDF، JPG و PNG مجاز است.', 422 );
			}
			$base = self::private_base();
			if ( $base === '' ) {
				self::fail( 'پوشهٔ مدارک آماده نیست.', 500 );
			}
			$dir = $base . '/p' . (int) $pid;
			if ( ! wp_mkdir_p( $dir ) ) {
				self::fail( 'ذخیرهٔ مدرک ناموفق بود.', 500 );
			}
			if ( ! file_exists( $dir . '/index.php' ) ) {
				@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
			}
			$stored = bin2hex( random_bytes( 16 ) ) . '.' . $exts[ $mime ];
			$dest   = $dir . '/' . $stored;
			if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
				self::fail( 'ذخیرهٔ مدرک ناموفق بود.', 500 );
			}
			@chmod( $dest, 0640 );

			$docs[] = array(
				'id'   => substr( bin2hex( random_bytes( 8 ) ), 0, 12 ),
				'name' => self::cut( sanitize_text_field( wp_basename( (string) $file['name'] ) ), 80 ),
				'file' => 'p' . (int) $pid . '/' . $stored,
				'mime' => $mime,
				'size' => (int) filesize( $dest ),
				'time' => time(),
			);
			update_post_meta( $pid, self::META_DOCS, $docs );

			self::ok( array( 'docs_html' => self::render_docs( $pid ) ) );
		}

		public static function ajax_delete_doc() {
			$pid = self::guard( 'delete_doc', 30, HOUR_IN_SECONDS );
			$id  = isset( $_POST['doc'] ) ? sanitize_key( wp_unslash( $_POST['doc'] ) ) : '';
			$new = array();
			foreach ( self::docs( $pid ) as $d ) {
				if ( isset( $d['id'] ) && $d['id'] === $id ) {
					$path = self::doc_path( $pid, $d );
					if ( $path ) {
						@unlink( $path );
					}
					continue;
				}
				$new[] = $d;
			}
			update_post_meta( $pid, self::META_DOCS, $new );
			self::ok( array( 'docs_html' => self::render_docs( $pid ) ) );
		}

		public static function ajax_download() {
			$pid = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0;
			$id  = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
			$non = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! is_user_logged_in() || ! $pid || $id === '' || ! wp_verify_nonce( $non, 'hj_doc_dl_' . $pid . '_' . $id ) ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}
			if ( ! ( self::can_edit( $pid ) || self::can_manage( 'approve' ) ) ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}
			foreach ( self::docs( $pid ) as $d ) {
				if ( isset( $d['id'] ) && $d['id'] === $id ) {
					$path = self::doc_path( $pid, $d );
					if ( ! $path ) {
						break;
					}
					$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
					nocache_headers();
					header( 'Content-Type: ' . ( isset( $d['mime'] ) ? $d['mime'] : 'application/octet-stream' ) );
					header( 'Content-Disposition: attachment; filename="doc-' . $id . '.' . $ext . '"' );
					header( 'Content-Length: ' . filesize( $path ) );
					header( 'X-Content-Type-Options: nosniff' );
					readfile( $path );
					exit;
				}
			}
			wp_die( 'فایل یافت نشد', '', array( 'response' => 404 ) );
		}

		public static function cleanup_private_files( $post_id ) {
			if ( get_post_type( $post_id ) !== 'doctor' ) {
				return;
			}
			$base = self::private_base();
			if ( $base === '' ) {
				return;
			}
			$dir = $base . '/p' . (int) $post_id;
			if ( ! is_dir( $dir ) ) {
				return;
			}
			foreach ( (array) glob( $dir . '/*' ) as $f ) {
				if ( is_file( $f ) ) {
					@unlink( $f );
				}
			}
			@rmdir( $dir );
		}

		/* ================================================================== */
		/* AJAX: submit for review                                             */
		/* ================================================================== */

		public static function ajax_submit() {
			$pid = self::guard( 'submit', 10, HOUR_IN_SECONDS );
			$st  = self::status( $pid );
			if ( $st === 'published' || $st === 'pending' ) {
				self::ok( self::state( $pid ) );
			}
			$c = self::completeness( $pid );
			if ( ! $c['can_submit'] ) {
				self::fail( 'برای ارسال، این موارد را تکمیل کنید: ' . implode( '، ', $c['missing'] ), 422, array( 'missing' => $c['missing'] ) );
			}
			self::write_status( $pid, 'pending' );
			if ( get_post_status( $pid ) !== 'publish' ) {
				wp_update_post( array( 'ID' => $pid, 'post_status' => 'pending' ) );
			}
			self::ok( self::state( $pid ) );
		}

		/* ================================================================== */
		/* Admin: moderation, column, status page                              */
		/* ================================================================== */

		private static function mod_url( $pid, $do ) {
			return wp_nonce_url(
				add_query_arg( array( 'action' => 'hj_dash_moderate', 'pid' => $pid, 'do' => $do ), admin_url( 'admin-post.php' ) ),
				'hj_mod_' . $pid . '_' . $do
			);
		}

		public static function row_actions( $actions, $post ) {
			if ( ! $post || $post->post_type !== 'doctor' || ! self::can_manage( 'approve' ) ) {
				return $actions;
			}
			$st = self::status( $post->ID );
			if ( $st !== 'published' ) {
				$actions['hj_approve'] = '<a href="' . esc_url( self::mod_url( $post->ID, 'approve' ) ) . '">تأیید و انتشار</a>';
			}
			if ( $st === 'pending' ) {
				$actions['hj_reject'] = '<a href="' . esc_url( self::mod_url( $post->ID, 'reject' ) ) . '">نیازمند اصلاح</a>';
			}
			return $actions;
		}

		public static function admin_moderate() {
			$pid = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0;
			$do  = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
			check_admin_referer( 'hj_mod_' . $pid . '_' . $do );
			if ( ! $pid || get_post_type( $pid ) !== 'doctor' || ! self::can_manage( 'approve' ) ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}
			if ( $do === 'approve' ) {
				self::write_status( $pid, 'published' );
				wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
			} elseif ( $do === 'reject' ) {
				self::write_status( $pid, 'rejected' );
				wp_update_post( array( 'ID' => $pid, 'post_status' => 'draft' ) );
			}
			$back = wp_get_referer();
			wp_safe_redirect( $back ? $back : admin_url( 'edit.php?post_type=doctor' ) );
			exit;
		}

		public static function admin_columns( $cols ) {
			$out = array();
			foreach ( $cols as $k => $l ) {
				$out[ $k ] = $l;
				if ( $k === 'title' ) {
					$out['hj_profile_status'] = 'وضعیت پروفایل';
				}
			}
			if ( ! isset( $out['hj_profile_status'] ) ) {
				$out['hj_profile_status'] = 'وضعیت پروفایل';
			}
			return $out;
		}

		public static function admin_column_value( $col, $post_id ) {
			if ( $col !== 'hj_profile_status' ) {
				return;
			}
			$lab = self::status_labels();
			$st  = self::status( $post_id );
			echo esc_html( isset( $lab[ $st ] ) ? $lab[ $st ] : $st );
		}

		public static function admin_status() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}
			global $wpdb;
			$L  = array();
			$ok = function ( $label, $val ) use ( &$L ) {
				$L[] = sprintf( '%-46s %s', $label, $val ? 'OK' : 'MISSING' );
			};
			$ok( 'ACF (update_field)', function_exists( 'update_field' ) );
			$ok( 'ACF Pro repeater (acf_get_field)', function_exists( 'acf_get_field' ) );
			$ok( 'hj_sec_validate_upload()', function_exists( 'hj_sec_validate_upload' ) );
			$ok( 'hj_sec_private_dir()', function_exists( 'hj_sec_private_dir' ) );
			$ok( 'hj_sec_clean()', function_exists( 'hj_sec_clean' ) );
			$ok( 'hj_doctors_get_profile_id()', function_exists( 'hj_doctors_get_profile_id' ) );
			$page = get_post( (int) get_option( 'hj_doctors_dashboard_page' ) );
			$ok( 'Dashboard page published', $page instanceof WP_Post && 'page' === $page->post_type && 'publish' === $page->post_status );
			$ok( 'Dashboard shortcode in page content', $page instanceof WP_Post && has_shortcode( $page->post_content, 'hj_doctors_dashboard' ) );
			$L[] = 'Dashboard URL: ' . hj_doctors_dashboard_url();
			$ok( 'Taxonomy specialty has terms', (bool) self::terms( 'specialty' ) );
			$ok( 'Taxonomy province has terms', (bool) self::terms( 'province' ) );
			$ok( 'Taxonomy city has terms', (bool) self::terms( 'city' ) );
			$ok( 'Private dir resolvable', self::private_base() !== '' );

			list( $days, $src ) = self::days_source();
			$L[] = '';
			$L[] = 'Working-hours day values (source: ' . $src . '):';
			foreach ( $days as $k => $l ) {
				$L[] = '  ' . $k . ' => ' . $l;
			}

			$L[] = '';
			$L[] = 'Profile status map: ' . wp_json_encode( self::status_map() );

			list( $table, $cols ) = self::votes_columns();
			$L[] = '';
			$L[] = 'Votes table: ' . $table . ( $cols ? '' : '  (NOT FOUND)' );
			if ( $cols ) {
				$L[] = '  columns: ' . implode( ', ', $cols );
			}

			$L[] = '';
			$L[] = 'Existing meta keys on doctor posts (non-hidden, with counts):';
			$rows = $wpdb->get_results(
				"SELECT pm.meta_key AS k, COUNT(*) AS c
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = 'doctor' AND pm.meta_key NOT LIKE '\\_%'
				 GROUP BY pm.meta_key ORDER BY pm.meta_key ASC LIMIT 200",
				ARRAY_A
			);
			foreach ( (array) $rows as $r ) {
				$L[] = '  ' . $r['k'] . '  (' . $r['c'] . ')';
			}

			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( implode( "\n", $L ) );
			exit;
		}
	}

	HJ_Doctors_Dashboard::init();

endif;

/* =========================================================================
 * Page bootstrap carried from the section-4 starter (guarded)
 * ====================================================================== */

if ( ! function_exists( 'hj_doctors_ensure_dashboard_page' ) ) {
	/**
	 * صفحهٔ /my-profile/ را برمی‌گرداند و در صورت نبودن می‌سازد.
	 *
	 * @return int
	 */
	function hj_doctors_ensure_dashboard_page() {
		$existing = get_post( (int) get_option( 'hj_doctors_dashboard_page' ) );
		if ( $existing instanceof WP_Post && 'page' === $existing->post_type && 'publish' === $existing->post_status ) {
			return (int) $existing->ID;
		}
		$existing = get_page_by_path( 'my-profile' );
		if ( $existing instanceof WP_Post ) {
			update_option( 'hj_doctors_dashboard_page', (int) $existing->ID, false );
			return (int) $existing->ID;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'ناحیهٔ پزشک',
				'post_name'    => 'my-profile',
				'post_content' => '[hj_doctors_dashboard]',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		update_option( 'hj_doctors_dashboard_page', (int) $id, false );
		return (int) $id;
	}
}

if ( ! function_exists( 'hj_doctors_login_redirect' ) ) {
	/**
	 * کاربر با نقش پزشک بعد از ورود مستقیم به ناحیهٔ خودش می‌رود.
	 *
	 * @param string           $redirect_to نشانی پیشنهادی وردپرس.
	 * @param string           $requested   نشانی درخواست‌شده.
	 * @param WP_User|WP_Error $user        کاربر واردشده.
	 * @return string
	 */
	function hj_doctors_login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && function_exists( 'hj_doctors_is_doctor_user' ) && hj_doctors_is_doctor_user( $user ) ) {
			return hj_doctors_dashboard_url();
		}
		return $redirect_to;
	}
	add_filter( 'login_redirect', 'hj_doctors_login_redirect', 10, 3 );
}

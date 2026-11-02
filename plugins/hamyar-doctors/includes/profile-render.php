<?php
/**
 * ماژول ۶: قالب پروفایل تک جراح (Single Surgeon Profile).
 *
 * نمایش یکپارچه و حرفه‌ای پروفایل پزشک شامل:
 * - آمار (سابقه، تعداد جراحی، امتیاز)
 * - شمارهٔ نظام پزشکی
 * - تخصص‌ها و رشته‌ها
 * - نشان ستاره‌دار / VIP
 * - امتیاز ۵ ستاره و نظرات عمومی بیماران
 *
 * امنیت: nonce، sanitization، escaping، capability check، UTF-8 فارسی.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/************************************************************************
 * کلاس رندر پروفایل (Singleton)
 ************************************************************************/

if ( ! class_exists( 'HJ_Profile_Render' ) ) :

	final class HJ_Profile_Render {

		const NONCE_REVIEWS = 'hj_profile_reviews_nonce';

		private static $instance = null;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * سازندهٔ عمومی (۰.۳.۱).
		 *
		 * قبلاً private بود و در پایان فایل با new صدا زده می‌شد؛ در PHP 8
		 * این ترکیب یک Error کشنده می‌ساخت و کل سایت را سفید می‌کرد.
		 */
		public function __construct() {
			// شورت‌کد نمایش پروفایل: [hj_doctor_profile id="123"]
			add_shortcode( 'hj_doctor_profile', array( $this, 'shortcode_profile' ) );

			// جایگزینی قالب تک‌جراح با فیلتر template_include (نه echo/exit).
			add_filter( 'template_include', array( $this, 'filter_single_doctor_template' ), 99 );

			// استایل و اسکریپت صفحه پروفایل
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

			// AJAX بارگذاری نظرات
			add_action( 'wp_ajax_hj_profile_load_reviews', array( $this, 'ajax_load_reviews' ) );
			add_action( 'wp_ajax_nopriv_hj_profile_load_reviews', array( $this, 'ajax_load_reviews' ) );
		}

		/********************************************************************
		 * Assets
		 ********************************************************************/

		public function enqueue_assets() {
			if ( ! is_singular( 'doctor' ) && ! shortcode_exists( 'hj_doctor_profile' ) ) {
				return;
			}

			// همان فایل CSS ناوبر، استایل پروفایل را هم دارد؛ برای جلوگیری از
			// دانلود دوباره، اگر هندل مشترک ثبت شده باشد همان را صف می‌کنیم.
			if ( wp_style_is( 'hj-tech-navigator', 'registered' ) ) {
				wp_enqueue_style( 'hj-tech-navigator' );
			} else {
				wp_register_style( 'hj-profile-css', HJ_DOCTORS_URL . 'assets/css/tech-navigator.css', array(), HJ_DOCTORS_VERSION );
				wp_enqueue_style( 'hj-profile-css' );
			}

			wp_register_script( 'hj-profile-js', HJ_DOCTORS_URL . 'assets/js/tech-navigator.js', array( 'jquery' ), HJ_DOCTORS_VERSION, true );
			wp_localize_script( 'hj-profile-js', 'HJ_PROFILE_CONFIG', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_REVIEWS ),
			) );
			wp_enqueue_script( 'hj-profile-js' );
		}

		/********************************************************************
		 * Shortcode: [hj_doctor_profile id="123"]
		 ********************************************************************/

		public function shortcode_profile( $atts ) {
			$a = shortcode_atts( array(
				'id' => '',
			), $atts, 'hj_doctor_profile' );

			$id = absint( $a['id'] );
			if ( ! $id && is_singular( 'doctor' ) ) {
				$id = (int) get_queried_object_id();
			}
			if ( ! $id || get_post_type( $id ) !== 'doctor' ) {
				return '';
			}

			$this->enqueue_assets();

			return $this->render_profile( $id );
		}

		/********************************************************************
		 * Single doctor template override
		 ********************************************************************/

		/**
		 * انتخاب قالب تک‌جراح.
		 *
		 * قالب افزونه استفاده می‌شود تا هدر/فوتر و wp_head/wp_footer قالب
		 * حفظ شوند. برای بازگشت به قالب پوسته، فیلتر
		 * hj_doctors_use_theme_doctor_template را true کنید.
		 *
		 * @param string $template مسیر قالب پیشنهادی وردپرس.
		 * @return string
		 */
		public function filter_single_doctor_template( $template ) {
			if ( ! is_singular( 'doctor' ) ) {
				return $template;
			}

			/**
			 * از نسخهٔ ۰.۳.۳ قالب افزونه اولویت دارد تا با به‌روزرسانی
			 * افزونه، طراحی صفحهٔ پروفایل روی سایت عوض شود و کاربر مجبور
			 * به ویرایش دستی قالب نباشد. اگر قالبی می‌خواهد قالب خودش را
			 * داشته باشد، همین فیلتر را true برگرداند.
			 *
			 * @param bool $use_theme استفاده از قالب پوسته.
			 */
			$use_theme = (bool) apply_filters( 'hj_doctors_use_theme_doctor_template', false );

			if ( $use_theme ) {
				$theme_template = locate_template( array( 'single-doctor.php' ) );
				if ( $theme_template ) {
					return $theme_template;
				}
			}

			$plugin_template = HJ_DOCTORS_DIR . 'templates/single-doctor.php';
			if ( is_readable( $plugin_template ) ) {
				return $plugin_template;
			}

			return $template;
		}

		/********************************************************************
		 * Main renderer
		 ********************************************************************/

		public function render_profile( $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post || $post->post_type !== 'doctor' || $post->post_status !== 'publish' ) {
				return '<p class="hj-profile-notice">' . esc_html__( 'پروفایل یافت نشد.', 'hamyar-doctors' ) . '</p>';
			}

			// عدم نمایش پروفایل‌های مخفی
			if ( self::is_profile_hidden( $post_id ) ) {
				return '<p class="hj-profile-notice">' . esc_html__( 'این پروفایل در حال حاضر در دسترس نیست.', 'hamyar-doctors' ) . '</p>';
			}

			$name_fa      = hj_doctors_field( 'name_fa', $post_id ) ?: get_the_title( $post_id );
			$name_en      = hj_doctors_field( 'name_en', $post_id );
			$specialty_fa = hj_doctors_field( 'specialty_fa', $post_id );
			$bio_fa       = hj_doctors_field( 'bio_fa', $post_id );
			$license      = hj_doctors_field( 'medical_license', $post_id );
			$experience   = hj_doctors_field( 'experience_years', $post_id );
			$surgeries    = hj_doctors_field( 'surgeries_performed', $post_id );
			$education    = hj_doctors_field( 'education', $post_id );
			$phone_clinic = hj_doctors_field( 'phone_clinic', $post_id );
			$phone_mobile = hj_doctors_field( 'phone_mobile', $post_id );
			$address_clinic = hj_doctors_field( 'address_clinic', $post_id );
			$address_hospital = hj_doctors_field( 'address_hospital', $post_id );
			$map_link     = hj_doctors_field( 'map_link', $post_id );
			$photo_url    = hj_doctors_photo_url( $post_id );

			$comm = hj_get_surgeon_commercial_data( $post_id );

			// specialty terms
			$spec_terms = get_the_terms( $post_id, 'specialty' );
			$spec_list  = array();
			if ( ! is_wp_error( $spec_terms ) && is_array( $spec_terms ) ) {
				foreach ( $spec_terms as $t ) {
					// مقدار خام نگه داشته می‌شود و در زمان چاپ escape می‌گردد
					// (جلوگیری از escape دوباره و رعایت قاعدهٔ late escaping).
					$spec_list[] = array(
						'name' => (string) $t->name,
						'slug' => (string) $t->slug,
					);
				}
			}

			// location terms
			$city_terms  = get_the_terms( $post_id, 'city' );
			$prov_terms  = get_the_terms( $post_id, 'province' );
			$city_name   = '';
			$prov_name   = '';
			$city_id     = 0;
			if ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) {
				$city_obj = array_values( $city_terms )[0];
				$city_name = esc_html( $city_obj->name );
				$city_id   = (int) $city_obj->term_id;
				$prov_id   = hj_iran_get_city_province_id( $city_id );
				if ( $prov_id > 0 ) {
					$prov_term = get_term( $prov_id, 'province' );
					if ( $prov_term && ! is_wp_error( $prov_term ) ) {
						$prov_name = esc_html( $prov_term->name );
					}
				}
			} elseif ( ! is_wp_error( $prov_terms ) && ! empty( $prov_terms ) ) {
				$prov_obj = array_values( $prov_terms )[0];
				$prov_name = esc_html( $prov_obj->name );
			}

			// rating stats
			$rating_stats = apply_filters( 'hj_doctor_rating_stats', array(), $post_id );
			$rating_avg   = isset( $rating_stats['avg'] ) ? (float) $rating_stats['avg'] : 0.0;
			$rating_count = isset( $rating_stats['approved'] ) ? (int) $rating_stats['approved'] : 0;

			// reviews (public approved)
			$reviews = self::get_public_reviews( $post_id );

			ob_start();
			?>
			<section class="hj-profile" id="hj-doctor-profile-<?php echo (int) $post_id; ?>" dir="rtl" lang="fa">
				<div class="hj-profile__wrap">
					<!-- Header: photo + name + badges -->
					<div class="hj-profile__header">
						<div class="hj-profile__avatar-wrap">
							<?php if ( $photo_url ) : ?>
								<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $name_fa ); ?>" class="hj-profile__avatar" />
							<?php else : ?>
								<div class="hj-profile__avatar-placeholder">👨‍⚕️</div>
							<?php endif; ?>
							<?php if ( $comm['is_starred'] ) : ?>
								<span class="hj-profile__star-pin" title="جراح ستاره‌دار و برگزیده">★</span>
							<?php endif; ?>
						</div>

						<div class="hj-profile__identity">
							<h1 class="hj-profile__name">
								<?php echo esc_html( $name_fa ); ?>
								<?php if ( $name_en ) : ?>
									<span class="hj-profile__name-en">(<?php echo esc_html( $name_en ); ?>)</span>
								<?php endif; ?>
							</h1>

							<div class="hj-profile__badges">
								<?php if ( $comm['is_vip'] ) : ?>
									<span class="hj-badge hj-badge--vip">💎 جراح برگزیده VIP</span>
								<?php elseif ( $comm['is_starred'] ) : ?>
									<span class="hj-badge hj-badge--starred">⭐ ستاره‌دار</span>
								<?php endif; ?>
								<?php if ( ! empty( $comm['badge'] ) ) : ?>
									<span class="hj-badge hj-badge--custom"><?php echo esc_html( $comm['badge'] ); ?></span>
								<?php endif; ?>
							</div>

							<?php if ( $specialty_fa ) : ?>
								<p class="hj-profile__specialty"><?php echo esc_html( $specialty_fa ); ?></p>
							<?php endif; ?>

							<div class="hj-profile__location">
								<span class="hj-profile__loc-icon">📍</span>
								<?php if ( $city_name ) : echo esc_html( $city_name ); endif; ?>
								<?php if ( $city_name && $prov_name ) : echo ', '; endif; ?>
								<?php if ( $prov_name ) : echo esc_html( $prov_name ); endif; ?>
							</div>
						</div>
					</div>

					<!-- Stats row -->
					<div class="hj-profile__stats">
						<div class="hj-stat">
							<span class="hj-stat__value"><?php echo esc_html( $experience ? $experience : '۰' ); ?></span>
							<span class="hj-stat__label">سابقه کار (سال)</span>
						</div>
						<div class="hj-stat">
							<span class="hj-stat__value"><?php echo esc_html( $surgeries ? '+' . $surgeries : '۰' ); ?></span>
							<span class="hj-stat__label">جراحی انجام‌شده</span>
						</div>
						<div class="hj-stat hj-stat--rating">
							<span class="hj-stat__value">
								<span class="hj-star-icon">★</span>
								<?php echo $rating_avg > 0 ? esc_html( number_format_i18n( $rating_avg, 1 ) ) : '۰٫۰'; ?>
							</span>
							<span class="hj-stat__label">میانگین امتیاز<?php echo $rating_count > 0 ? ' (' . esc_html( number_format_i18n( $rating_count ) ) . ' رأی)' : ''; ?></span>
						</div>
						<div class="hj-stat">
							<span class="hj-stat__value"><?php echo esc_html( $license ? $license : '—' ); ?></span>
							<span class="hj-stat__label">شمارهٔ نظام پزشکی</span>
						</div>
					</div>

					<!-- Main content: bio & details -->
					<div class="hj-profile__body">
						<div class="hj-profile__column hj-profile__column--main">
							<?php if ( $bio_fa ) : ?>
								<div class="hj-profile__section">
									<h2 class="hj-profile__section-title">معرفی</h2>
									<div class="hj-profile__section-content"><?php echo wp_kses_post( nl2br( esc_html( $bio_fa ) ) ); ?></div>
								</div>
							<?php endif; ?>

							<?php if ( $education ) : ?>
								<div class="hj-profile__section">
									<h2 class="hj-profile__section-title">تحصیلات و دوره‌ها</h2>
									<div class="hj-profile__section-content"><?php echo wp_kses_post( nl2br( esc_html( $education ) ) ); ?></div>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $spec_list ) ) : ?>
								<div class="hj-profile__section">
									<h2 class="hj-profile__section-title">تخصص‌ها و رشته‌ها</h2>
									<div class="hj-profile__specialty-tags">
										<?php foreach ( $spec_list as $spec ) : ?>
											<span class="hj-spec-tag"><?php echo esc_html( $spec['name'] ); ?></span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( $surgeries ) : ?>
								<div class="hj-profile__section">
									<h2 class="hj-profile__section-title">جراحی‌های انجام‌شده</h2>
									<div class="hj-profile__surgeries-list">
										<?php
										$surgeries_lines = array_filter( array_map( 'trim', explode( "\n", $surgeries ) ) );
										foreach ( $surgeries_lines as $line ) : ?>
											<div class="hj-surgery-item">• <?php echo esc_html( $line ); ?></div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
						</div>

						<div class="hj-profile__column hj-profile__column--sidebar">
							<!-- Contact card -->
							<div class="hj-profile__card hj-profile__card--contact">
								<h3 class="hj-profile__card-title">تماس و نشانی</h3>
								<ul class="hj-contact-list">
									<?php if ( $phone_clinic ) : ?>
										<li><span class="hj-contact-icon">📞</span><?php echo esc_html( $phone_clinic ); ?></li>
									<?php endif; ?>
									<?php if ( $phone_mobile ) : ?>
										<li><span class="hj-contact-icon">📱</span><?php echo esc_html( $phone_mobile ); ?></li>
									<?php endif; ?>
									<?php if ( $address_clinic ) : ?>
										<li><span class="hj-contact-icon">🏥</span><?php echo esc_html( $address_clinic ); ?></li>
									<?php endif; ?>
									<?php if ( $address_hospital ) : ?>
										<li><span class="hj-contact-icon">🏥</span><?php echo esc_html( $address_hospital ); ?></li>
									<?php endif; ?>
									<?php if ( $map_link ) : ?>
										<li><a href="<?php echo esc_url( $map_link ); ?>" target="_blank" rel="noopener"><span class="hj-contact-icon">🗺️</span>نقشه و راه‌یاب</a></li>
									<?php endif; ?>
									<?php if ( ! empty( $comm['booking_url'] ) && $comm['is_starred'] ) : ?>
										<li><a href="<?php echo esc_url( $comm['booking_url'] ); ?>" target="_blank" rel="noopener" class="hj-contact-btn">نوبت‌دهی آنلاین ←</a></li>
									<?php endif; ?>
								</ul>
							</div>

							<!-- Rating & Reviews -->
							<div class="hj-profile__card hj-profile__card--reviews">
								<h3 class="hj-profile__card-title">
									نظرات بیماران
									<span class="hj-reviews-count">(<?php echo esc_html( $rating_count ); ?>)</span>
								</h3>

								<div class="hj-profile__rating-big">
									<span class="hj-profile__rating-num"><?php echo $rating_avg > 0 ? esc_html( number_format_i18n( $rating_avg, 1 ) ) : '۰٫۰'; ?></span>
									<div class="hj-profile__stars">
										<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
											<span class="hj-star <?php echo esc_attr( $i <= ceil( $rating_avg ) ? 'is-filled' : '' ); ?>">★</span>
										<?php endfor; ?>
									</div>
								</div>

								<div class="hj-profile__reviews-list" id="hj-profile-reviews-list-<?php echo (int) $post_id; ?>">
									<?php if ( ! empty( $reviews ) ) : ?>
										<?php foreach ( $reviews as $review ) : ?>
											<div class="hj-review">
												<div class="hj-review__header">
													<span class="hj-review__author"><?php echo esc_html( $review['author'] ); ?></span>
													<span class="hj-review__date"><?php echo esc_html( $review['date'] ); ?></span>
												</div>
												<div class="hj-review__stars">
													<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
														<span class="hj-star <?php echo $i <= (int) $review['rating'] ? 'is-filled' : ''; ?>">★</span>
													<?php endfor; ?>
												</div>
												<div class="hj-review__body"><?php echo esc_html( $review['comment'] ); ?></div>
											</div>
										<?php endforeach; ?>
									<?php else : ?>
										<p class="hj-profile-empty"><?php echo esc_html__( 'هنوز نظری ثبت نشده است.', 'hamyar-doctors' ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
			<?php
			return ob_get_clean();
		}

		/********************************************************************
		 * Helpers
		 ********************************************************************/

		private static function is_profile_hidden( $post_id ) {
			$hidden = get_post_meta( (int) $post_id, 'profile_hidden', true );
			if ( is_scalar( $hidden ) && in_array( strtolower( trim( (string) $hidden ) ), array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
			return false;
		}

		/**
		 * نظرات عمومی تأییدشدهٔ بیماران.
		 *
		 * ستون‌های جدول hj_doctor_votes با SHOW COLUMNS تشخیص داده می‌شوند تا
		 * اگر نام ستون‌ها (status / comment / created_at) متفاوت بود، به‌جای
		 * خطای SQL یا نمایش خالی، همان چیزی خوانده شود که واقعاً وجود دارد.
		 *
		 * @param int $post_id شناسهٔ پزشک.
		 * @return array<int,array{author:string,rating:int,comment:string,date:string}>
		 */
		/**
		 * دسترسی عمومی به نظرات تأییدشده (برای صفحهٔ پروفایل تازه).
		 *
		 * @param int $post_id شناسهٔ پزشک.
		 * @return array<int,array{author:string,rating:int,comment:string,date:string}>
		 */
		public static function public_reviews( $post_id ) {
			return self::get_public_reviews( (int) $post_id );
		}

		/**
		 * خواندن نظرات تأییدشده از جدول امتیاز.
		 *
		 * @param int $post_id شناسهٔ پزشک.
		 * @return array<int,array{author:string,rating:int,comment:string,date:string}>
		 */
		private static function get_public_reviews( $post_id ) {
			$post_id = (int) $post_id;
			$reviews = array();

			global $wpdb;
			$table = $wpdb->prefix . 'hj_doctor_votes';

			$prev = $wpdb->suppress_errors( true );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				$wpdb->suppress_errors( $prev );
				return $reviews;
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->suppress_errors( $prev );
			if ( ! is_array( $columns ) || empty( $columns ) ) {
				return $reviews;
			}
			$columns = array_map( 'strval', $columns );

			/**
			 * نخستین ستون موجود از فهرست نامزدها (فقط از میان ستون‌های واقعی).
			 *
			 * @param string[] $candidates نامزدها.
			 * @return string
			 */
			$pick = static function ( $candidates ) use ( $columns ) {
				foreach ( $candidates as $candidate ) {
					if ( in_array( $candidate, $columns, true ) ) {
						return $candidate;
					}
				}
				return '';
			};

			$col_doctor  = $pick( array( 'doctor_id', 'post_id', 'doctor' ) );
			$col_rating  = $pick( array( 'rating', 'stars', 'score', 'vote', 'value' ) );
			$col_comment = $pick( array( 'comment', 'review', 'content', 'body', 'text' ) );
			$col_status  = $pick( array( 'status', 'is_approved', 'approved', 'state' ) );
			$col_created = $pick( array( 'created_at', 'voted_at', 'date_created', 'created', 'vote_date', 'time' ) );

			// بدون ستون پزشک یا امتیاز، بازیابی معنا ندارد.
			if ( '' === $col_doctor || '' === $col_rating ) {
				return $reviews;
			}

			$where  = "`{$col_doctor}` = %d";
			$params = array( $post_id );

			if ( '' !== $col_status ) {
				$where   .= " AND `{$col_status}` IN ('approved','approve','publish','published','1','active','yes')";
			}

			$order = ( '' !== $col_created ) ? " ORDER BY `{$col_created}` DESC" : '';

			$sql = "SELECT * FROM `{$table}` WHERE {$where}{$order} LIMIT 10"; // phpcs:ignore WordPress.DB.PreparedSQL

			$prev = $wpdb->suppress_errors( true );
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->suppress_errors( $prev );

			if ( ! is_array( $rows ) ) {
				return $reviews;
			}

			foreach ( $rows as $row ) {
				$rating  = isset( $row[ $col_rating ] ) ? (int) $row[ $col_rating ] : 0;
				$comment = ( '' !== $col_comment && isset( $row[ $col_comment ] ) ) ? (string) $row[ $col_comment ] : '';
				$created = ( '' !== $col_created && isset( $row[ $col_created ] ) ) ? $row[ $col_created ] : '';

				if ( '' === trim( $comment ) ) {
					continue;
				}

				$timestamp = 0;
				if ( '' !== $created ) {
					$timestamp = is_numeric( $created ) ? (int) $created : (int) strtotime( (string) $created );
				}

				$reviews[] = array(
					'author'  => 'بیمار تأییدشده',
					'rating'  => $rating,
					'comment' => $comment,
					'date'    => $timestamp > 0 ? gmdate( 'Y/m/d', $timestamp ) : '',
				);
			}

			return $reviews;
		}

		/********************************************************************
		 * AJAX: load reviews
		 ********************************************************************/

		public function ajax_load_reviews() {
			check_ajax_referer( self::NONCE_REVIEWS, 'nonce' );

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			if ( ! $post_id || get_post_type( $post_id ) !== 'doctor' ) {
				wp_send_json_error( array( 'message' => 'شناسه نامعتبر است.' ), 400 );
			}

			$reviews = self::get_public_reviews( $post_id );
			wp_send_json_success( array( 'reviews' => $reviews, 'count' => count( $reviews ) ) );
		}
	}

	HJ_Profile_Render::get_instance();

endif;

/************************************************************************
 * Template tag for themes
 ************************************************************************/

if ( ! function_exists( 'hj_render_doctor_profile' ) ) {
	/**
	 * Render the single surgeon profile HTML.
	 *
	 * @param int $post_id Doctor post ID.
	 * @return string
	 */
	function hj_render_doctor_profile( $post_id = 0 ) {
		if ( ! $post_id && is_singular( 'doctor' ) ) {
			$post_id = (int) get_queried_object_id();
		}
		if ( ! $post_id || get_post_type( $post_id ) !== 'doctor' ) {
			return '';
		}
		return HJ_Profile_Render::get_instance()->render_profile( $post_id );
	}
}
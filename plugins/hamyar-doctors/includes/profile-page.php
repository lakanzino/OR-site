<?php
/**
 * ماژول ۷: صفحهٔ عمومی پروفایل پزشک.
 *
 * طراحی برگرفته از نمونهٔ تأییدشدهٔ کاربر: هیروی تیره، سه کارت اطلاعات/نشانی/
 * ساعات کاری، معرفی، خدمات، سوابق، نظرات و فرم ثبت نظر یا درخواست تماس.
 *
 * همهٔ داده‌ها از خودِ نوشتهٔ پزشک خوانده می‌شوند (ACF با فالبک متا)، هیچ
 * مقدار نمایشیِ ساختگی در خروجی نیست. فرم با nonce و پاک‌سازی کامل کار می‌کند
 * و هر درخواست به‌صورت دیدگاه «در انتظار تأیید» ثبت می‌شود تا مدیر بررسی کند.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HJ_Profile_Page' ) ) :

	final class HJ_Profile_Page {

		/** نام اکشن غیرسِ secret برای فرم. */
		const NONCE_LEAD = 'hj_profile_lead_action';

		/** فیلد تلهٔ ربات (باید خالی بماند). */
		const HONEYPOT = 'hj_company_website';

		/** کلید متای دیدگاه‌ها. */
		const META_TYPE   = 'hj_profile_request_type';
		const META_RATING = 'hj_profile_rating';
		const META_PHONE  = 'hj_profile_phone';

		public static function init() {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 25 );
			add_action( 'admin_post_hj_profile_lead', array( __CLASS__, 'handle_lead' ) );
			add_action( 'admin_post_nopriv_hj_profile_lead', array( __CLASS__, 'handle_lead' ) );
		}

		/****************************************************************
		 * دارایی‌ها
		 ****************************************************************/

		public static function enqueue_assets() {
			if ( ! is_singular( 'doctor' ) ) {
				return;
			}

			wp_enqueue_style(
				'hj-profile-page',
				HJ_DOCTORS_URL . 'assets/css/profile-page.css',
				array(),
				HJ_DOCTORS_VERSION
			);

			wp_enqueue_script(
				'hj-profile-page',
				HJ_DOCTORS_URL . 'assets/js/profile-page.js',
				array(),
				HJ_DOCTORS_VERSION,
				true
			);
		}

		/****************************************************************
		 * کمک‌تابع‌ها
		 ****************************************************************/

		/**
		 * خواندن فیلد با فالبک متا (بدون نیاز به ACF).
		 *
		 * @param string $key     نام فیلد.
		 * @param int    $post_id شناسهٔ پزشک.
		 * @return mixed
		 */
		private static function field( $key, $post_id ) {
			if ( function_exists( 'hj_doctors_field' ) ) {
				return hj_doctors_field( $key, $post_id );
			}
			return get_post_meta( $post_id, $key, true );
		}

		/**
		 * متن تک‌مقداری و امن.
		 *
		 * @param string $key     نام فیلد.
		 * @param int    $post_id شناسه.
		 * @return string
		 */
		private static function text( $key, $post_id ) {
			$value = self::field( $key, $post_id );
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		/**
		 * تبدیل متن چندخطی به آرایهٔ خطوطِ غیرخالی.
		 *
		 * @param string $text متن.
		 * @return string[]
		 */
		private static function lines( $text ) {
			$text = trim( (string) $text );
			if ( '' === $text ) {
				return array();
			}
			$out = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$out[] = $line;
				}
			}
			return $out;
		}

		/**
		 * پیوند تلفن قابل کلیک.
		 *
		 * @param string $phone شماره.
		 * @return string
		 */
		private static function tel_href( $phone ) {
			$digits = preg_replace( '/[^0-9+]/', '', (string) $phone );
			return '' === (string) $digits ? '' : 'tel:' . $digits;
		}

		/**
		 * نشانی نقشه: لینک ثبت‌شده، وگرنه جست‌وجوی گوگل روی نشانی مطب.
		 *
		 * @param string $map_link  لینک نقشه.
		 * @param string $address   نشانی.
		 * @param string $doctor    نام پزشک برای جست‌وجو.
		 * @return string
		 */
		private static function map_url( $map_link, $address, $doctor ) {
			$map_link = trim( (string) $map_link );
			if ( '' !== $map_link ) {
				$clean = esc_url_raw( $map_link, array( 'http', 'https' ) );
				if ( '' !== $clean ) {
					return $clean;
				}
			}

			$query = trim( $doctor . ' ' . $address );
			if ( '' === trim( $query ) ) {
				return '';
			}

			return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
		}

		/**
		 * ستاره‌های امتیاز به‌صورت متن.
		 *
		 * @param float $rating امتیاز ۰ تا ۵.
		 * @return string
		 */
		private static function stars_markup( $rating ) {
			$rating = max( 0, min( 5, (int) round( (float) $rating ) ) );
			$out    = '';
			for ( $i = 1; $i <= 5; $i++ ) {
				$out .= $i <= $rating
					? '<span class="hj-star">★</span>'
					: '<span class="hj-star hj-star-off">★</span>';
			}
			return '<span class="hj-stars" aria-hidden="true">' . $out . '</span>';
		}

		/**
		 * ساعات کاری از فیلد تکرارشو.
		 *
		 * @param int $post_id شناسه.
		 * @return array<int,array{label:string,time:string,closed:bool}>
		 */
		private static function hours( $post_id, $canonical_rows = null, $unavailable = false ) {
			$days = apply_filters(
				'hj_dash_days',
				array(
					'saturday'  => 'شنبه',
					'sunday'    => 'یکشنبه',
					'monday'    => 'دوشنبه',
					'tuesday'   => 'سه‌شنبه',
					'wednesday' => 'چهارشنبه',
					'thursday'  => 'پنجشنبه',
					'friday'    => 'جمعه',
				)
			);

			$rows = $unavailable ? array() : ( is_array( $canonical_rows ) ? $canonical_rows : self::field( 'working_hours', $post_id ) );
			$out  = array();

			if ( is_array( $rows ) && $rows ) {
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$key = isset( $row['day_of_week'] ) ? (string) $row['day_of_week'] : '';
					if ( '' === $key || ! isset( $days[ $key ] ) ) {
						continue;
					}
					$closed = ! empty( $row['is_closed'] );
					$start  = isset( $row['start_time'] ) ? (string) $row['start_time'] : '';
					$end    = isset( $row['end_time'] ) ? (string) $row['end_time'] : '';
					// Preserve meaningful seconds in validated snapshots; retain legacy display.
					if ( ! is_array( $canonical_rows ) || substr( $start, -3 ) === ':00' ) { $start = substr( $start, 0, 5 ); }
					if ( ! is_array( $canonical_rows ) || substr( $end, -3 ) === ':00' ) { $end = substr( $end, 0, 5 ); }
					$out[]  = array(
						'label'  => (string) $days[ $key ],
						'closed' => $closed,
						'time'   => $closed ? 'تعطیل' : trim( $start . ' - ' . $end, ' -' ),
					);
				}
			}

			// اگر چیزی ثبت نشده باشد، همان هفت روز با «ثبت نشده» نمایش داده می‌شود
			// تا قالب خالی و بی‌معنا نماند.
			if ( ! $out ) {
				foreach ( $days as $label ) {
					$out[] = array(
						'label'  => (string) $label,
						'closed' => false,
						'time'   => $unavailable ? 'در دسترس نیست' : 'ثبت نشده',
					);
				}
			}

			return $out;
		}

		/**
		 * نظرات: امتیازهای پل امتیاز + دیدگاه‌های تأییدشدهٔ فرم.
		 *
		 * @param int $post_id شناسهٔ پزشک.
		 * @return array<int,array{author:string,rating:int,comment:string,date:string}>
		 */
		private static function reviews( $post_id ) {
			$out = array();

			if ( class_exists( 'HJ_Profile_Render' ) && method_exists( 'HJ_Profile_Render', 'public_reviews' ) ) {
				$votes = HJ_Profile_Render::public_reviews( $post_id );
				if ( is_array( $votes ) ) {
					$out = $votes;
				}
			}

			$comments = get_comments(
				array(
					'post_id' => $post_id,
					'status'  => 'approve',
					'number'  => 6,
					'orderby' => 'comment_date_gmt',
					'order'   => 'DESC',
				)
			);

			foreach ( (array) $comments as $c ) {
				$rating = (int) get_comment_meta( $c->comment_ID, self::META_RATING, true );
				$type   = (string) get_comment_meta( $c->comment_ID, self::META_TYPE, true );

				// فقط نظرهایی که از همین فرم آمده‌اند (برچسب نوع یا امتیاز دارند).
				if ( '' === $type && $rating <= 0 ) {
					continue;
				}
				if ( 'review' !== $type && $rating <= 0 ) {
					continue;
				}

				array_unshift(
					$out,
					array(
						'author'  => (string) $c->comment_author,
						'rating'  => $rating,
						'comment' => (string) $c->comment_content,
						'date'    => mysql2date( 'Y/m/d', $c->comment_date ),
					)
				);
			}

			return array_slice( $out, 0, 6 );
		}

		/****************************************************************
		 * رندر صفحه
		 ****************************************************************/

		/**
		 * خروجی کامل صفحهٔ پروفایل.
		 *
		 * @param int $post_id شناسهٔ پزشک.
		 * @return string
		 */
		public static function render( $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );

			if ( ! $post || 'doctor' !== $post->post_type ) {
				return '<p class="hj-muted">' . esc_html__( 'پروفایل یافت نشد.', 'hamyar-doctors' ) . '</p>';
			}

			$name_fa      = self::text( 'name_fa', $post_id );
			$name_en      = self::text( 'name_en', $post_id );
			$specialty_fa = self::text( 'specialty_fa', $post_id );
			$bio_fa       = self::text( 'bio_fa', $post_id );
			$license      = self::text( 'medical_license', $post_id );
			$experience   = self::text( 'experience_years', $post_id );
			$surgeries    = self::text( 'surgeries_performed', $post_id );
			$education    = self::text( 'education', $post_id );
			$phone_clinic = self::text( 'phone_clinic', $post_id );
			$phone_mobile = self::text( 'phone_mobile', $post_id );
			$address      = self::text( 'address_clinic', $post_id );
			$hospital     = self::text( 'address_hospital', $post_id );
			$map_link     = self::text( 'map_link', $post_id );

			if ( '' === $name_fa ) {
				$name_fa = get_the_title( $post_id );
			}

			$photo = function_exists( 'hj_doctors_photo_url' ) ? (string) hj_doctors_photo_url( $post_id ) : '';
			$comm  = function_exists( 'hj_get_surgeon_commercial_data' )
				? (array) hj_get_surgeon_commercial_data( $post_id )
				: array();

			$booking_url = isset( $comm['booking_url'] ) ? esc_url_raw( (string) $comm['booking_url'] ) : '';
			$is_vip      = ! empty( $comm['is_vip'] );
			$is_starred  = ! empty( $comm['is_starred'] );
			$badge       = isset( $comm['badge'] ) ? (string) $comm['badge'] : '';

			// مکان.
			$city_name = '';
			$prov_name = '';
			$city_terms = get_the_terms( $post_id, 'city' );
			$prov_terms = get_the_terms( $post_id, 'province' );

			if ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) {
				$city_obj  = array_values( $city_terms )[0];
				$city_name = (string) $city_obj->name;
				if ( function_exists( 'hj_iran_get_city_province_id' ) ) {
					$prov_id   = (int) hj_iran_get_city_province_id( (int) $city_obj->term_id );
					$prov_term = $prov_id > 0 ? get_term( $prov_id, 'province' ) : null;
					if ( $prov_term && ! is_wp_error( $prov_term ) ) {
						$prov_name = (string) $prov_term->name;
					}
				}
			} elseif ( ! is_wp_error( $prov_terms ) && ! empty( $prov_terms ) ) {
				$prov_name = (string) array_values( $prov_terms )[0]->name;
			}

			$location = trim( implode( '، ', array_filter( array( $prov_name, $city_name ) ) ) );

			// تخصص‌ها.
			$spec_names = array();
			$spec_terms = get_the_terms( $post_id, 'specialty' );
			if ( ! is_wp_error( $spec_terms ) && ! empty( $spec_terms ) ) {
				foreach ( $spec_terms as $t ) {
					$spec_names[] = (string) $t->name;
				}
			}

			// امتیاز.
			$stats  = apply_filters( 'hj_doctor_rating_stats', array(), $post_id );
			$avg    = isset( $stats['avg'] ) ? (float) $stats['avg'] : 0.0;
			$count  = isset( $stats['approved'] ) ? (int) $stats['approved'] : 0;
			$review_list = self::reviews( $post_id );

			$map_url   = self::map_url( $map_link, $address, $name_fa . ' ' . $location );
			$tel_clinic = self::tel_href( $phone_clinic );
			$tel_mobile = self::tel_href( $phone_mobile );
			$office_hours = function_exists( 'hj_booking_public_office_hours' ) ? hj_booking_public_office_hours( $post_id ) : array( 'status' => 'legacy' );
			$office_unavailable = in_array( $office_hours['status'], array( 'unavailable', 'invalid_schedule' ), true );
			$canonical_hours = $office_hours['status'] === 'ok' ? $office_hours['rows'] : null;
			$booking_ready = function_exists( 'hj_booking_ready' ) && hj_booking_ready();
			$internal_booking = $booking_ready && function_exists( 'hj_booking_doctor_has_valid_schedule' ) && hj_booking_doctor_has_valid_schedule( $post_id );
			$booking = $internal_booking ? '#hj-booking' : ( '' !== $booking_url ? $booking_url : '#hj-appointment' );
			$external_only = $booking_ready && ! $internal_booking && '' !== $booking_url;
			$archive    = get_post_type_archive_link( 'doctor' );

			$services = self::lines( $surgeries );
			$edu      = self::lines( $education );
			$icon_set = array( '🦴', '🦿', '🦵', '💪', '⚙️', '🩺', '🦾', '🏥' );

			$lead_status = isset( $_GET['hj_lead'] ) ? sanitize_key( wp_unslash( $_GET['hj_lead'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			ob_start();
			?>
			<div class="hj-doc" dir="rtl" lang="fa">

				<nav class="hj-doc__nav" aria-label="<?php echo esc_attr__( 'بخش‌های پروفایل', 'hamyar-doctors' ); ?>">
					<div class="hj-doc__container">
						<div class="hj-doc__navlinks">
							<?php if ( $archive ) : ?>
								<a href="<?php echo esc_url( $archive ); ?>">همهٔ جراحان</a>
							<?php endif; ?>
							<a href="#hj-about">معرفی جراح</a>
							<a href="#hj-services">خدمات</a>
							<a href="#hj-education">سوابق</a>
							<a href="#hj-reviews">نظرات بیماران</a>
							<a href="#hj-contact">تماس</a>
						</div>
						<a class="hj-btn hj-btn--primary" href="<?php echo esc_url( $booking ); ?>"><?php echo $external_only ? 'نوبت‌دهی خارج از سایت' : 'نوبت‌گیری'; ?></a>
					</div>
				</nav>

				<section class="hj-hero">
					<div class="hj-doc__container hj-hero__inner">

						<div class="hj-hero__content">
							<span class="hj-hero__verified">✓
								<?php
								echo esc_html(
									'' !== $license
										? 'شمارهٔ نظام پزشکی: ' . $license
										: 'هویت و مدارک جراح پس از تأیید قابل نمایش است'
								);
								?>
							</span>

							<h1><?php echo esc_html( $name_fa ); ?></h1>

							<?php if ( '' !== $specialty_fa ) : ?>
								<div class="hj-hero__specialty"><?php echo esc_html( $specialty_fa ); ?></div>
							<?php endif; ?>

							<?php if ( '' !== $bio_fa ) : ?>
								<p class="hj-hero__bio"><?php echo esc_html( wp_trim_words( $bio_fa, 28, '…' ) ); ?></p>
							<?php endif; ?>

							<?php if ( '' !== $location ) : ?>
								<p class="hj-hero__location">📍 <?php echo esc_html( $location ); ?></p>
							<?php endif; ?>

							<?php if ( $is_vip || $is_starred || '' !== $badge ) : ?>
								<div class="hj-hero__badges">
									<?php if ( $is_vip ) : ?>
										<span class="hj-hero__badge">💎 جراح برگزیده VIP</span>
									<?php elseif ( $is_starred ) : ?>
										<span class="hj-hero__badge">⭐ ستاره‌دار</span>
									<?php endif; ?>
									<?php if ( '' !== $badge ) : ?>
										<span class="hj-hero__badge"><?php echo esc_html( $badge ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<div class="hj-hero__rating">
								<?php
								// خروجی stars_markup فقط span ثابت است.
								echo self::stars_markup( $avg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
								<small>
									<?php
									if ( $avg > 0 ) {
										echo esc_html(
											number_format_i18n( $avg, 1 ) . ' از ۵' .
											( $count > 0 ? ' — ' . number_format_i18n( $count ) . ' رأی' : '' )
										);
									} else {
										echo esc_html__( 'هنوز امتیازی ثبت نشده', 'hamyar-doctors' );
									}
									?>
								</small>
							</div>

							<div class="hj-hero__actions">
								<a class="hj-btn hj-btn--primary" href="<?php echo esc_url( $booking ); ?>">📅 <?php echo $external_only ? 'نوبت‌دهی خارج از سایت' : 'دریافت نوبت'; ?></a>
								<a class="hj-btn hj-btn--outline" href="#hj-reviews">مشاهده نظرات بیماران</a>
							</div>
						</div>

						<div class="hj-hero__image">
							<?php if ( '' !== $photo ) : ?>
								<img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name_fa ); ?>" loading="eager" decoding="async" />
							<?php else : ?>
								<div class="hj-hero__avatar-placeholder" aria-hidden="true">👨‍⚕️</div>
							<?php endif; ?>
						</div>

					</div>
				</section>

				<main class="hj-doc__main">
					<div class="hj-doc__container">

						<?php if ( 'sent' === $lead_status ) : ?>
							<div class="hj-notice hj-notice--ok" role="status">درخواست شما ثبت شد و پس از بررسی نمایش داده می‌شود.</div>
						<?php elseif ( 'invalid' === $lead_status ) : ?>
							<div class="hj-notice hj-notice--err" role="alert">ارسال انجام نشد؛ لطفاً فیلدها را کامل کنید و دوباره تلاش کنید.</div>
						<?php elseif ( 'limit' === $lead_status ) : ?>
							<div class="hj-notice hj-notice--err" role="alert">تعداد درخواست‌ها زیاد بود؛ کمی بعد دوباره تلاش کنید.</div>
						<?php endif; ?>

						<section class="hj-section hj-grid hj-grid--3" id="hj-appointment">

							<div class="hj-card">
								<h3>📞 اطلاعات تماس</h3>
								<?php if ( '' !== $phone_clinic ) : ?>
									<div class="hj-info-row">
										<span class="hj-info-icon">☎</span>
										<?php if ( '' !== $tel_clinic ) : ?>
											<a href="<?php echo esc_url( $tel_clinic ); ?>" dir="ltr"><?php echo esc_html( $phone_clinic ); ?></a>
										<?php else : ?>
											<span dir="ltr"><?php echo esc_html( $phone_clinic ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $phone_mobile ) : ?>
									<div class="hj-info-row">
										<span class="hj-info-icon">📱</span>
										<?php if ( '' !== $tel_mobile ) : ?>
											<a href="<?php echo esc_url( $tel_mobile ); ?>" dir="ltr"><?php echo esc_html( $phone_mobile ); ?></a>
										<?php else : ?>
											<span dir="ltr"><?php echo esc_html( $phone_mobile ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $address ) : ?>
									<div class="hj-info-row"><span class="hj-info-icon">📍</span><span><?php echo esc_html( $address ); ?></span></div>
								<?php endif; ?>
								<?php if ( '' !== $phone_clinic || '' !== $phone_mobile || '' !== $address ) : ?>
									<div class="hj-info-row"><span class="hj-info-icon">🕘</span><a href="#hj-contact">درخواست تماس</a></div>
								<?php else : ?>
									<p class="hj-muted">اطلاعات تماس این جراح هنوز ثبت نشده است.</p>
								<?php endif; ?>
							</div>

							<div class="hj-card">
								<h3>📍 نشانی مطب</h3>
								<?php if ( '' !== $address ) : ?>
									<p class="hj-muted"><?php echo esc_html( $address ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $hospital ) : ?>
									<p class="hj-muted" style="margin-top:8px">بیمارستان: <?php echo esc_html( $hospital ); ?></p>
								<?php endif; ?>
								<div class="hj-map-box">
									<div class="hj-map-emoji" aria-hidden="true">🗺️</div>
									<p class="hj-muted"><?php echo '' !== $map_url ? 'مسیر مطب را روی نقشه ببینید' : 'نقشه‌ای برای این مطب ثبت نشده است'; ?></p>
									<?php if ( '' !== $map_url ) : ?>
										<a class="hj-btn hj-btn--outline" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer nofollow">مسیریابی</a>
									<?php endif; ?>
								</div>
							</div>

							<div class="hj-card hj-appointment">
								<h3>🕒 <?php echo $office_hours['status'] === 'ok' ? 'ساعات نوبت‌دهی مطب' : 'ساعات حضور در مطب'; ?></h3>
								<?php foreach ( self::hours( $post_id, $canonical_hours, $office_unavailable ) as $row ) : ?>
									<div class="hj-time<?php echo $row['closed'] ? ' is-closed' : ''; ?>">
										<span><?php echo esc_html( $row['label'] ); ?></span>
										<strong><?php echo esc_html( $row['time'] ); ?></strong>
									</div>
								<?php endforeach; ?>
								<?php if ( $office_hours['status'] === 'missing' ) : ?><p class="hj-muted">ساعات بالا نمایشی است؛ برنامهٔ نوبت‌دهی داخلی هنوز ثبت نشده است.</p><?php endif; ?>
								<a class="hj-btn hj-btn--primary" href="<?php echo esc_url( $booking ); ?>"><?php echo $external_only ? 'نوبت‌دهی خارج از سایت' : 'درخواست نوبت'; ?></a>
								<?php if ( $internal_booking && '' !== $booking_url ) : ?><p><a href="<?php echo esc_url( $booking_url ); ?>">نوبت‌دهی خارج از سایت</a></p><?php endif; ?>
								<?php if ( $booking_ready && ! $internal_booking ) : ?><p class="hj-muted">نوبت‌دهی داخلی برای این جراح در دسترس نیست.</p><?php endif; ?>
								<p class="hj-muted" style="margin-top:8px">ساعات کاری را پیش از مراجعه با مطب تأیید کنید.</p>
							</div>

						</section>

						<?php if ( $internal_booking ) { echo hj_booking_form_shortcode( array( 'doctor_id' => (string) $post_id ) ); } ?>

						<section class="hj-section hj-grid hj-grid--2" id="hj-about">

							<div class="hj-card">
								<h3>👨‍⚕️ درباره <?php echo esc_html( $name_fa ); ?></h3>
								<div class="hj-profile-text">
									<?php if ( '' !== $bio_fa ) : ?>
										<p><?php echo esc_html( $bio_fa ); ?></p>
									<?php endif; ?>
									<?php
									$content = get_post_field( 'post_content', $post_id );
									$has_content = is_string( $content ) && '' !== trim( wp_strip_all_tags( $content ) );
									if ( $has_content ) {
										echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
									<?php if ( '' === $bio_fa && empty( $has_content ) ) : ?>
										<p class="hj-muted">توضیحاتی برای این جراح ثبت نشده است.</p>
									<?php endif; ?>
								</div>
								<?php if ( $spec_names ) : ?>
									<div class="hj-tags">
										<?php foreach ( $spec_names as $spec ) : ?>
											<span class="hj-tag"><?php echo esc_html( $spec ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>

							<div class="hj-card">
								<h3>🎓 اطلاعات حرفه‌ای</h3>
								<?php if ( '' !== $specialty_fa ) : ?>
									<div class="hj-info-row">تخصص: <?php echo esc_html( $specialty_fa ); ?></div>
								<?php endif; ?>
								<?php if ( '' !== $location ) : ?>
									<div class="hj-info-row">محل فعالیت: <?php echo esc_html( $location ); ?></div>
								<?php endif; ?>
								<?php if ( '' !== $license ) : ?>
									<div class="hj-info-row">شمارهٔ نظام پزشکی: <span dir="ltr"><?php echo esc_html( $license ); ?></span></div>
								<?php endif; ?>
								<?php if ( '' !== $experience ) : ?>
									<div class="hj-info-row">سابقهٔ کار: <?php echo esc_html( $experience ); ?> سال</div>
								<?php endif; ?>
								<?php if ( $name_en ) : ?>
									<div class="hj-info-row" dir="ltr"><?php echo esc_html( $name_en ); ?></div>
								<?php endif; ?>
								<p class="hj-muted" style="margin-top:12px">اطلاعات حرفه‌ای مطابق مدارک ثبت‌شده در پروندهٔ جراح نمایش داده می‌شود.</p>
							</div>

						</section>

						<?php
						$procedures = function_exists( 'hj_surgery_for_doctor' )
							? (array) hj_surgery_for_doctor( $post_id, 8 )
							: array();
						?>
						<?php if ( $procedures || $services ) : ?>
							<section class="hj-section" id="hj-services">
								<div class="hj-section__title">
									<h2><?php echo esc_html( $procedures ? 'عمل‌های انجام‌شده' : 'خدمات و جراحی‌ها' ); ?></h2>
									<?php if ( $procedures && function_exists( 'hj_surgery_guide_url' ) ) : ?>
										<a class="hj-guide-link" href="<?php echo esc_url( hj_surgery_guide_url() ); ?>">راهنمای کامل جراحی‌ها</a>
									<?php endif; ?>
								</div>

								<?php if ( $procedures ) : ?>
									<?php
									// شبکهٔ آیکون‌ها؛ هر آیکون به بخش همان جراحی در راهنما لینک است.
									echo hj_surgery_grid_html( $procedures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								<?php else : ?>
									<div class="hj-services">
										<?php foreach ( array_slice( $services, 0, 8 ) as $index => $service ) : ?>
											<div class="hj-service">
												<div class="hj-service__icon" aria-hidden="true"><?php echo esc_html( $icon_set[ $index % count( $icon_set ) ] ); ?></div>
												<h3><?php echo esc_html( $service ); ?></h3>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</section>
						<?php endif; ?>

						<section class="hj-section hj-grid hj-grid--2" id="hj-education">

							<div class="hj-card">
								<h3>🎓 سوابق تحصیلی و دوره‌ها</h3>
								<?php if ( $edu ) : ?>
									<?php foreach ( $edu as $line ) : ?>
										<div class="hj-timeline-item">
											<h4><?php echo esc_html( mb_strlen( $line ) > 90 ? mb_substr( $line, 0, 90 ) . '…' : $line ); ?></h4>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="hj-muted">سوابق تحصیلی این جراح ثبت نشده است.</p>
								<?php endif; ?>
							</div>

							<div class="hj-card">
								<h3>🏥 حوزه‌های تخصصی</h3>
								<?php if ( $spec_names ) : ?>
									<?php foreach ( $spec_names as $spec ) : ?>
										<div class="hj-info-row"><?php echo esc_html( $spec ); ?></div>
									<?php endforeach; ?>
								<?php endif; ?>
								<?php if ( $services ) : ?>
									<?php foreach ( array_slice( $services, 0, 5 ) as $service ) : ?>
										<div class="hj-info-row"><?php echo esc_html( $service ); ?></div>
									<?php endforeach; ?>
								<?php endif; ?>
								<?php if ( ! $spec_names && ! $services ) : ?>
									<p class="hj-muted">حوزهٔ تخصصی ثبت نشده است.</p>
								<?php endif; ?>
							</div>

						</section>

						<section class="hj-section" id="hj-reviews">
							<div class="hj-section__title">
								<h2>نظرات بیماران</h2>
								<span class="hj-muted"><?php echo $count > 0 ? esc_html( number_format_i18n( $count ) . ' رأی ثبت‌شده' ) : ''; ?></span>
							</div>

							<?php if ( $review_list ) : ?>
								<div class="hj-grid hj-grid--3">
									<?php foreach ( $review_list as $review ) : ?>
										<?php
										$author = isset( $review['author'] ) ? (string) $review['author'] : 'بیمار';
										$rating = isset( $review['rating'] ) ? (int) $review['rating'] : 0;
										$text   = isset( $review['comment'] ) ? (string) $review['comment'] : '';
										$date   = isset( $review['date'] ) ? (string) $review['date'] : '';
										$initial = function_exists( 'mb_substr' ) ? mb_substr( $author, 0, 1 ) : substr( $author, 0, 1 );
										?>
										<div class="hj-review">
											<div class="hj-review__header">
												<div class="hj-review__who">
													<?php if ( '' !== $author ) : ?><div class="hj-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></div><?php endif; ?>
													<strong><?php echo esc_html( $author ); ?></strong>
												</div>
												<?php if ( '' !== $date ) : ?>
													<span class="hj-muted"><?php echo esc_html( $date ); ?></span>
												<?php endif; ?>
											</div>
											<?php
											echo self::stars_markup( $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											?>
											<?php if ( '' !== $text ) : ?>
												<p><?php echo esc_html( wp_trim_words( $text, 40, '…' ) ); ?></p>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<div class="hj-card"><p class="hj-muted">هنوز نظر تأییدشده‌ای برای این جراح ثبت نشده است. اولین نظر را شما بنویسید.</p></div>
							<?php endif; ?>
						</section>

						<section class="hj-section" id="hj-contact">
							<div class="hj-card hj-card--form">
								<div class="hj-section__title"><h2>ثبت نظر یا درخواست تماس</h2></div>

								<form class="hj-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="hj_profile_lead" />
									<input type="hidden" name="hj_doctor_id" value="<?php echo (int) $post_id; ?>" />
									<?php wp_nonce_field( self::NONCE_LEAD, 'hj_profile_lead_nonce' ); ?>

									<div class="hj-field hj-hp" aria-hidden="true">
										<label for="hj-company-website">این فیلد را خالی بگذارید</label>
										<input type="text" id="hj-company-website" name="<?php echo esc_attr( self::HONEYPOT ); ?>" value="" tabindex="-1" autocomplete="off" />
									</div>

									<div class="hj-field">
										<label for="hj-lead-name">نام و نام خانوادگی</label>
										<input type="text" id="hj-lead-name" name="hj_lead_name" required maxlength="80" placeholder="نام خود را وارد کنید" />
									</div>

									<div class="hj-field">
										<label for="hj-lead-phone">شمارهٔ تماس (اختیاری)</label>
										<input type="tel" id="hj-lead-phone" name="hj_lead_phone" maxlength="20" dir="ltr" placeholder="09xxxxxxxxx" />
									</div>

									<div class="hj-field">
										<label for="hj-lead-type">نوع درخواست</label>
										<select id="hj-lead-type" name="hj_lead_type">
											<option value="review">ثبت نظر</option>
											<option value="callback">درخواست تماس</option>
											<option value="question">سؤال درباره نوبت</option>
										</select>
									</div>

									<div class="hj-field">
										<span class="hj-muted" style="font-size:12px;font-weight:600">امتیاز شما</span>
										<div class="hj-rating-pick">
											<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
												<input type="radio" id="hj-rate-<?php echo (int) $i; ?>" name="hj_lead_rating" value="<?php echo (int) $i; ?>" <?php checked( 5, $i ); ?> />
												<label for="hj-rate-<?php echo (int) $i; ?>" title="<?php echo esc_attr( $i . ' ستاره' ); ?>">★</label>
											<?php endfor; ?>
										</div>
									</div>

									<div class="hj-field hj-field--full">
										<label for="hj-lead-message">متن پیام</label>
										<textarea id="hj-lead-message" name="hj_lead_message" required maxlength="1500" placeholder="پیام خود را بنویسید..."></textarea>
									</div>

									<div class="hj-field hj-field--full">
										<label class="hj-consent">
											<input type="checkbox" name="hj_lead_consent" value="1" required />
											<span>با ثبت این پیام و نمایش آن پس از تأیید موافقم.</span>
										</label>
									</div>

									<div class="hj-field hj-field--full">
										<button class="hj-btn hj-btn--primary" type="submit">ارسال درخواست</button>
									</div>
								</form>
							</div>
						</section>

					</div>
				</main>

				<div class="hj-doc__mobile-booking">
					<a href="<?php echo esc_url( $booking ); ?>" class="hj-btn hj-btn--primary">📅 <?php echo $external_only ? 'نوبت‌دهی خارج از سایت' : 'دریافت نوبت آنلاین'; ?></a>
				</div>

			</div>
			<?php
			return (string) ob_get_clean();
		}

		/****************************************************************
		 * دریافت فرم
		 ****************************************************************/

		/**
		 * ثبت درخواست/نظر به‌صورت دیدگاه در انتظار تأیید.
		 *
		 * @return void
		 */
		public static function handle_lead() {
			$referer  = wp_get_referer();
			$redirect = $referer ? $referer : home_url( '/' );

			$nonce = isset( $_POST['hj_profile_lead_nonce'] )
				? sanitize_text_field( wp_unslash( $_POST['hj_profile_lead_nonce'] ) )
				: '';

			if ( ! wp_verify_nonce( $nonce, self::NONCE_LEAD ) ) {
				self::back( $redirect, 'invalid' );
			}

			// تلهٔ ربات: اگر پر شده باشد، بی‌صدا موفق نشان می‌دهیم.
			if ( ! empty( $_POST[ self::HONEYPOT ] ) ) {
				self::back( $redirect, 'sent' );
			}

			$doctor_id = isset( $_POST['hj_doctor_id'] ) ? absint( wp_unslash( $_POST['hj_doctor_id'] ) ) : 0;
			if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) || 'publish' !== get_post_status( $doctor_id ) ) {
				self::back( $redirect, 'invalid' );
			}

			$name    = isset( $_POST['hj_lead_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_lead_name'] ) ) : '';
			$phone   = isset( $_POST['hj_lead_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_lead_phone'] ) ) : '';
			$message = isset( $_POST['hj_lead_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hj_lead_message'] ) ) : '';
			$type    = isset( $_POST['hj_lead_type'] ) ? sanitize_key( wp_unslash( $_POST['hj_lead_type'] ) ) : 'review';
			$rating  = isset( $_POST['hj_lead_rating'] ) ? absint( wp_unslash( $_POST['hj_lead_rating'] ) ) : 0;
			$consent = ! empty( $_POST['hj_lead_consent'] );

			if ( ! in_array( $type, array( 'review', 'callback', 'question' ), true ) ) {
				$type = 'review';
			}

			$name    = mb_substr( $name, 0, 80 );
			$phone   = mb_substr( $phone, 0, 20 );
			$message = mb_substr( $message, 0, 1500 );
			$rating  = max( 0, min( 5, $rating ) );

			if ( '' === $name || '' === $message || ! $consent ) {
				self::back( $redirect, 'invalid' );
			}

			// محدودیت نرخ: حداکثر ۶ درخواست در ساعت برای هر نشانی شبکه.
			// پارامتر چهارم true یعنی کلید محدودیت بر پایهٔ IP ساخته شود
			// (hj_sec_rate_limit خودش REMOTE_ADDR را می‌خواند و هش می‌کند).
			if ( function_exists( 'hj_sec_rate_limit' ) && ! hj_sec_rate_limit( 'profile_lead', 6, 3600, true ) ) {
				self::back( $redirect, 'limit' );
			}

			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $doctor_id,
					'comment_author'       => $name,
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_content'      => $message,
					'comment_type'         => 'comment',
					'comment_approved'     => 0,
					'comment_agent'        => '',
					'comment_author_IP'    => '',
					'user_id'              => get_current_user_id(),
				)
			);

			if ( ! $comment_id ) {
				self::back( $redirect, 'invalid' );
			}

			update_comment_meta( $comment_id, self::META_TYPE, $type );
			update_comment_meta( $comment_id, self::META_RATING, $rating );
			if ( '' !== $phone ) {
				update_comment_meta( $comment_id, self::META_PHONE, $phone );
			}

			if ( 'review' !== $type ) {
				self::notify_admin( $doctor_id, $type, $name, $phone, $message );
			}

			self::back( $redirect, 'sent' );
		}

		/**
		 * اطلاع‌رسانی به مدیر برای درخواست تماس (بی‌اثر در صورت نبود ایمیل).
		 *
		 * @param int    $doctor_id شناسهٔ پزشک.
		 * @param string $type      نوع درخواست.
		 * @param string $name      نام.
		 * @param string $phone     تلفن.
		 * @param string $message   متن.
		 * @return void
		 */
		private static function notify_admin( $doctor_id, $type, $name, $phone, $message ) {
			$to = apply_filters( 'hj_profile_lead_notify_email', get_option( 'admin_email' ), $doctor_id );
			if ( ! is_string( $to ) || ! is_email( $to ) ) {
				return;
			}

			$labels = array(
				'callback' => 'درخواست تماس',
				'question' => 'سؤال درباره نوبت',
			);
			$subject = sprintf( '[همیار پزشک] %s برای %s', isset( $labels[ $type ] ) ? $labels[ $type ] : 'درخواست', get_the_title( $doctor_id ) );
			$body    = "نام: {$name}\nتلفن: {$phone}\n\n{$message}\n\n" . get_edit_post_link( $doctor_id, '' );

			wp_mail( $to, $subject, $body );
		}

		/**
		 * بازگشت امن به همان صفحه با پیام وضعیت.
		 *
		 * @param string $redirect نشانی بازگشت.
		 * @param string $status   وضعیت.
		 * @return void
		 */
		private static function back( $redirect, $status ) {
			$url = add_query_arg( 'hj_lead', $status, $redirect ) . '#hj-contact';
			wp_safe_redirect( $url );
			exit;
		}
	}

	HJ_Profile_Page::init();

endif;

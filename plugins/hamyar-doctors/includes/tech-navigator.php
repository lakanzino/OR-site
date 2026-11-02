<?php
/**
 * ماژول ۳: ناوبر دو مرحله‌ای (Dual-Funnel) جراحان با AJAX.
 *
 * - شورت‌کد [hj_tech_navigator] — هاب تکنولوژیک پزشکی با دو حالت:
 *   حالت A: استان → شهر → تخصص → نوار افقی جراحان (سقف ۲ در شهرستان‌های عادی، ۱۲ در کلان‌شهرها)
 *   حالت B: تخصص → ۳ شفاف + ۷ Blurred → ۳۱ استان → شهر → نوار جراحان
 * - نقاط پایانی AJAX: wp_ajax_hj_nav_drilldown / wp_ajax_nopriv_hj_nav_drilldown
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HJ_Tech_Navigator' ) ) :

	final class HJ_Tech_Navigator {

		public function __construct() {
			add_shortcode( 'hj_tech_navigator', array( $this, 'render_navigator_shortcode' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_ajax_hj_nav_drilldown', array( $this, 'ajax_handle_drilldown' ) );
			add_action( 'wp_ajax_nopriv_hj_nav_drilldown', array( $this, 'ajax_handle_drilldown' ) );
		}

		public function enqueue_assets() {
			wp_register_style( 'hj-tech-navigator', HJ_DOCTORS_URL . 'assets/css/tech-navigator.css', array(), HJ_DOCTORS_VERSION );
			wp_register_script( 'hj-tech-navigator', HJ_DOCTORS_URL . 'assets/js/tech-navigator.js', array( 'jquery' ), HJ_DOCTORS_VERSION, true );

			wp_localize_script( 'hj-tech-navigator', 'HJ_NAV_CONFIG', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hj_tech_nav_nonce' ),
			) );
		}

		public function render_navigator_shortcode( $atts ) {
			wp_enqueue_style( 'hj-tech-navigator' );
			wp_enqueue_script( 'hj-tech-navigator' );

			$provinces = get_terms( array(
				'taxonomy'   => 'province',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			) );

			$specialties = get_terms( array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
			) );

			if ( ! is_wp_error( $specialties ) && ! empty( $specialties ) ) {
				usort( $specialties, function( $a, $b ) {
					$oa = (int) get_term_meta( $a->term_id, 'hj_spec_order', true ) ?: 99;
					$ob = (int) get_term_meta( $b->term_id, 'hj_spec_order', true ) ?: 99;
					return $oa <=> $ob;
				} );
			}

			ob_start();
			?>
			<section class="hj-tech-hub" id="hj-surgeon-finder" dir="rtl" lang="fa">
				<div class="hj-tech-hub__header">
					<div class="hj-tech-badge">⚡ سامانه هوشمند و طبقه‌بندی‌شده معرفی جراحان ایران</div>
					<h2 class="hj-tech-hub__title">مسیر دسترسی به جراح متخصص را انتخاب کنید</h2>
					<p class="hj-tech-hub__subtitle">جستجوی ساختاریافته بر اساس <strong>۳۱ استان و شهرستان‌ها</strong> یا <strong>تخصص‌های جراحی</strong> با دسترسی مستقیم به پروفایل و نظرات بیماران</p>

					<div class="hj-mode-switcher" role="tablist">
						<button type="button" class="hj-mode-btn is-active" data-mode="location">
							<span class="hj-mode-icon">📍</span>
							<span>انتخاب بر اساس استان و شهرستان</span>
						</button>
						<button type="button" class="hj-mode-btn" data-mode="specialty">
							<span class="hj-mode-icon">🩺</span>
							<span>انتخاب بر اساس نوع تخصص جراحی</span>
						</button>
					</div>
				</div>

				<div class="hj-nav-breadcrumb" id="hj-nav-breadcrumb" style="display:none;">
					<button type="button" class="hj-back-step-btn" id="hj-back-step-btn">→ بازگشت به مرحله قبل</button>
					<div class="hj-breadcrumb-trail" id="hj-breadcrumb-trail"></div>
				</div>

				<div class="hj-view-panel is-active" id="hj-panel-location-root">
					<div class="hj-panel-head">
						<h3>۱. استان مورد نظر خود را انتخاب کنید (۳۱ استان)</h3>
						<input type="search" id="hj-province-quick-filter" class="hj-tech-search-input" placeholder="جستجوی سریع نام استان..." />
					</div>
					<div class="hj-provinces-grid" id="hj-provinces-grid-main">
						<?php if ( ! is_wp_error( $provinces ) ) : ?>
							<?php foreach ( $provinces as $prov ) : ?>
								<button type="button" class="hj-province-pill" data-province-id="<?php echo esc_attr( $prov->term_id ); ?>" data-province-name="<?php echo esc_attr( $prov->name ); ?>">
									<span class="hj-prov-dot"></span>
									<span class="hj-prov-name"><?php echo esc_html( $prov->name ); ?></span>
									<span class="hj-prov-arrow">←</span>
								</button>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="hj-view-panel" id="hj-panel-specialty-root">
					<div class="hj-panel-head">
						<h3>۱. تخصص جراحی مورد نظر را انتخاب کنید</h3>
					</div>
					<div class="hj-specialties-grid">
						<?php if ( ! is_wp_error( $specialties ) ) : ?>
							<?php foreach ( $specialties as $spec ) :
								$icon = get_term_meta( $spec->term_id, 'hj_spec_icon', true ) ?: '🩺';
							?>
								<button type="button" class="hj-specialty-card" data-specialty-id="<?php echo esc_attr( $spec->term_id ); ?>" data-specialty-name="<?php echo esc_attr( $spec->name ); ?>">
									<span class="hj-spec-card__icon"><?php echo esc_html( $icon ); ?></span>
									<div class="hj-spec-card__info">
										<span class="hj-spec-card__title"><?php echo esc_html( $spec->name ); ?></span>
										<span class="hj-spec-card__cta">مشاهده جراحان و انتخاب استان ←</span>
									</div>
								</button>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="hj-dynamic-stage" id="hj-dynamic-stage"></div>
			</section>
			<?php
			return ob_get_clean();
		}

		public function ajax_handle_drilldown() {
			check_ajax_referer( 'hj_tech_nav_nonce', 'nonce' );

			$step         = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
			$province_id  = isset( $_POST['province_id'] ) ? absint( $_POST['province_id'] ) : 0;
			$city_id      = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
			$specialty_id = isset( $_POST['specialty_id'] ) ? absint( $_POST['specialty_id'] ) : 0;

			if ( 'load_cities' === $step && $province_id > 0 ) {
				$html = $this->render_cities_of_province( $province_id, $specialty_id );
				wp_send_json_success( array( 'html' => $html ) );
			} elseif ( 'load_city_surgeons' === $step && $city_id > 0 ) {
				$html = $this->render_city_specialties_and_ribbons( $city_id, $specialty_id );
				wp_send_json_success( array( 'html' => $html ) );
			} elseif ( 'load_specialty_showcase' === $step && $specialty_id > 0 ) {
				$html = $this->render_specialty_top10_and_provinces( $specialty_id );
				wp_send_json_success( array( 'html' => $html ) );
			}

			wp_send_json_error( array( 'message' => 'درخواست نامعتبر است.' ) );
		}

		private function render_cities_of_province( $province_id, $specialty_id = 0 ) {
			$prov_term = get_term( $province_id, 'province' );
			$prov_name = ( $prov_term && ! is_wp_error( $prov_term ) ) ? $prov_term->name : 'استان انتخابی';

			$cities = get_terms( array(
				'taxonomy'   => 'city',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => 'hj_parent_province_id',
						'value'   => $province_id,
						'compare' => '=',
					),
				),
			) );

			if ( ! is_wp_error( $cities ) && ! empty( $cities ) ) {
				usort( $cities, function( $a, $b ) {
					$ma = (int) get_term_meta( $a->term_id, 'hj_is_major_city', true );
					$mb = (int) get_term_meta( $b->term_id, 'hj_is_major_city', true );
					if ( $ma !== $mb ) {
						return $mb <=> $ma;
					}
					return strcmp( $a->name, $b->name );
				} );
			}

			$spec_badge = '';
			if ( $specialty_id > 0 ) {
				$spec_term = get_term( $specialty_id, 'specialty' );
				if ( $spec_term && ! is_wp_error( $spec_term ) ) {
					$spec_badge = ' <span class="hj-inline-filter-tag">فیلتر تخصص: ' . esc_html( $spec_term->name ) . '</span>';
				}
			}

			ob_start();
			?>
			<div class="hj-stage-box">
				<div class="hj-panel-head">
					<h3>شهرستان‌های استان «<?php echo esc_html( $prov_name ); ?>» را انتخاب کنید <?php echo $spec_badge; ?></h3>
					<p class="hj-stage-hint">با انتخاب هر شهرستان، فهرست طبقه‌بندی‌شده تخصص‌ها و نام جراحان آن شهرستان به صورت نواری نمایش داده می‌شود.</p>
				</div>
				<?php if ( empty( $cities ) || is_wp_error( $cities ) ) : ?>
					<p class="hj-empty-notice">هنوز شهرستانی برای این استان ثبت نشده است.</p>
				<?php else : ?>
					<div class="hj-cities-grid">
						<?php foreach ( $cities as $city ) :
							$is_major = (int) get_term_meta( $city->term_id, 'hj_is_major_city', true );
						?>
							<button type="button" class="hj-city-btn <?php echo $is_major ? 'is-major-city' : ''; ?>" data-city-id="<?php echo esc_attr( $city->term_id ); ?>" data-city-name="<?php echo esc_attr( $city->name ); ?>" data-specialty-id="<?php echo esc_attr( $specialty_id ); ?>">
								<div class="hj-city-btn__top">
									<span class="hj-city-name"><?php echo esc_html( $city->name ); ?></span>
									<?php if ( $is_major ) : ?>
										<span class="hj-major-badge">مرکز / کلان‌شهر</span>
									<?php endif; ?>
								</div>
								<span class="hj-city-sub">مشاهده تخصص‌ها و جراحان ←</span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		private function render_city_specialties_and_ribbons( $city_id, $priority_specialty_id = 0 ) {
			$city_term  = get_term( $city_id, 'city' );
			$city_name  = ( $city_term && ! is_wp_error( $city_term ) ) ? $city_term->name : '';
			$slot_limit = hj_get_city_slot_limit( $city_id );
			$is_major   = ( $slot_limit > 2 );

			$specialties = get_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => false ) );
			if ( is_wp_error( $specialties ) || empty( $specialties ) ) {
				return '<p class="hj-empty-notice">تخصصی یافت نشد.</p>';
			}

			usort( $specialties, function( $a, $b ) use ( $priority_specialty_id ) {
				if ( $priority_specialty_id > 0 ) {
					if ( (int) $a->term_id === $priority_specialty_id ) return -1;
					if ( (int) $b->term_id === $priority_specialty_id ) return 1;
				}
				$oa = (int) get_term_meta( $a->term_id, 'hj_spec_order', true ) ?: 99;
				$ob = (int) get_term_meta( $b->term_id, 'hj_spec_order', true ) ?: 99;
				return $oa <=> $ob;
			} );

			ob_start();
			?>
			<div class="hj-stage-box hj-city-directory">
				<div class="hj-panel-head">
					<h3>فهرست تخصص‌ها و جراحان شهرستان «<?php echo esc_html( $city_name ); ?>»</h3>
					<div class="hj-slot-policy-banner">
						<?php if ( $is_major ) : ?>
							<span>🏙️ کلان‌شهر <?php echo esc_html( $city_name ); ?>: نمایش جراحان برتر به ترتیب امتیاز و رتبه ستاره‌دار</span>
						<?php else : ?>
							<span>✨ سهمیه انحصاری شهرستان <?php echo esc_html( $city_name ); ?>: نمایش حداکثر <strong>۲ جراح منتخب</strong> در هر رشته تخصصی</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="hj-specialty-sections-list">
					<?php foreach ( $specialties as $spec ) :
						$icon     = get_term_meta( $spec->term_id, 'hj_spec_icon', true ) ?: '🩺';
						$surgeons = $this->query_surgeons_for_slot( $city_id, $spec->term_id, $slot_limit );
						$is_highlighted = ( $priority_specialty_id > 0 && (int) $spec->term_id === $priority_specialty_id );
					?>
						<div class="hj-spec-ribbon-group <?php echo $is_highlighted ? 'is-highlighted-spec' : ''; ?>">
							<div class="hj-spec-ribbon-header">
								<div class="hj-spec-ribbon-title">
									<span class="hj-spec-icon"><?php echo esc_html( $icon ); ?></span>
									<h4><?php echo esc_html( $spec->name ); ?></h4>
									<span class="hj-spec-city-tag">در <?php echo esc_html( $city_name ); ?></span>
								</div>
								<span class="hj-slot-counter"><?php echo count( $surgeons ); ?> از <?php echo esc_html( $slot_limit ); ?> جایگاه فعال</span>
							</div>

							<?php if ( ! empty( $surgeons ) ) : ?>
								<div class="hj-surgeon-ribbons-stack">
									<?php foreach ( $surgeons as $doc_post ) :
										echo $this->render_single_surgeon_ribbon( $doc_post, false );
									endforeach; ?>
								</div>
							<?php else : ?>
								<div class="hj-empty-slot-ribbon">
									<span>در حال حاضر جایگاه تخصصی «<?php echo esc_html( $spec->name ); ?>» در شهرستان <?php echo esc_html( $city_name ); ?> خالی است.</span>
									<a href="<?php echo esc_url( home_url( '/?hj_google_start=1' ) ); ?>" class="hj-claim-slot-link">پزشک این رشته هستید؟ ثبت پروفایل در این جایگاه ←</a>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		private function render_specialty_top10_and_provinces( $specialty_id ) {
			$spec_term = get_term( $specialty_id, 'specialty' );
			$spec_name = ( $spec_term && ! is_wp_error( $spec_term ) ) ? $spec_term->name : 'تخصص انتخابی';
			$icon      = get_term_meta( $specialty_id, 'hj_spec_icon', true ) ?: '🩺';

			$top_surgeons = $this->query_surgeons_for_slot( 0, $specialty_id, 10 );
			$provinces    = get_terms( array( 'taxonomy' => 'province', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );

			ob_start();
			?>
			<div class="hj-stage-box">
				<div class="hj-specialty-spotlight">
					<div class="hj-panel-head">
						<h3><?php echo esc_html( $icon . ' ' . $spec_name ); ?> — ویترین جراحان برجسته</h3>
						<p class="hj-stage-hint">۳ جراح اول به صورت ویژه نمایش داده شده‌اند. برای مشاهده کامل جراحان <strong><?php echo esc_html( $spec_name ); ?></strong> در شهر خودتان، استان را از بخش پایین انتخاب کنید.</p>
					</div>

					<?php if ( ! empty( $top_surgeons ) ) : ?>
						<div class="hj-surgeon-ribbons-stack">
							<?php foreach ( $top_surgeons as $index => $doc_post ) :
								$is_blurred = ( $index >= 3 );
								echo $this->render_single_surgeon_ribbon( $doc_post, $is_blurred );
							endforeach; ?>
						</div>
					<?php else : ?>
						<div class="hj-empty-slot-ribbon" style="margin-bottom:20px;">
							<span>هنوز جراحی در رشته «<?php echo esc_html( $spec_name ); ?>» ثبت نشده است.</span>
							<a href="<?php echo esc_url( home_url( '/?hj_google_start=1' ) ); ?>" class="hj-claim-slot-link">اولین جراح ستاره‌دار این تخصص باشید ←</a>
						</div>
					<?php endif; ?>
				</div>

				<div class="hj-specialty-province-funnel">
					<div class="hj-panel-head">
						<h3>📍 انتخاب استان برای مشاهده جراحان «<?php echo esc_html( $spec_name ); ?>» در شهرستان شما (۳۱ استان)</h3>
					</div>
					<div class="hj-provinces-grid">
						<?php if ( ! is_wp_error( $provinces ) ) : ?>
							<?php foreach ( $provinces as $prov ) : ?>
								<button type="button" class="hj-province-pill" data-province-id="<?php echo esc_attr( $prov->term_id ); ?>" data-province-name="<?php echo esc_attr( $prov->name ); ?>" data-specialty-id="<?php echo esc_attr( $specialty_id ); ?>">
									<span class="hj-prov-dot"></span>
									<span class="hj-prov-name"><?php echo esc_html( $prov->name ); ?></span>
									<span class="hj-prov-arrow">←</span>
								</button>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		private function render_single_surgeon_ribbon( $post, $is_blurred = false ) {
			$post_id      = $post->ID;
			$name_fa      = get_post_meta( $post_id, 'name_fa', true ) ?: get_the_title( $post_id );
			$specialty_fa = get_post_meta( $post_id, 'specialty_fa', true );
			$exp_years    = get_post_meta( $post_id, 'experience_years', true );
			$surgeries    = get_post_meta( $post_id, 'surgeries_performed', true );
			$rating_avg   = (float) get_post_meta( $post_id, 'hj_rating_avg', true );
			$rating_count = (int) get_post_meta( $post_id, 'hj_rating_count', true );
			$comm         = hj_get_surgeon_commercial_data( $post_id );

			$avatar_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
			if ( ! $avatar_url ) {
				$avatar_url = hj_doctors_default_photo_url();
			}

			$city_terms = wp_get_post_terms( $post_id, 'city', array( 'fields' => 'names' ) );
			$city_label = ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) ? $city_terms[0] : '';

			$classes = array( 'hj-surgeon-ribbon' );
			if ( $comm['is_vip'] ) {
				$classes[] = 'is-vip-ribbon';
			} elseif ( $comm['is_starred'] ) {
				$classes[] = 'is-starred-ribbon';
			}
			if ( $is_blurred ) {
				$classes[] = 'is-blurred-teaser';
			}

			$permalink = $is_blurred ? '#hj-specialty-province-funnel' : get_permalink( $post_id );

			ob_start();
			?>
			<a href="<?php echo esc_url( $permalink ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" <?php echo $is_blurred ? 'onclick="hjScrollToProvinceFunnel(event)"' : ''; ?>>
				<div class="hj-ribbon__right">
					<div class="hj-ribbon__avatar-wrap">
						<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $name_fa ); ?>" class="hj-ribbon__avatar" loading="lazy" />
						<?php if ( $comm['is_starred'] ) : ?>
							<span class="hj-ribbon__star-pin" title="جراح ستاره‌دار و برگزیده">★</span>
						<?php endif; ?>
					</div>

					<div class="hj-ribbon__identity">
						<div class="hj-ribbon__name-row">
							<span class="hj-ribbon__name"><?php echo esc_html( $name_fa ); ?></span>
							<?php if ( $comm['is_vip'] ) : ?>
								<span class="hj-badge-vip">💎 جراح برگزیده VIP</span>
							<?php elseif ( $comm['is_starred'] ) : ?>
								<span class="hj-badge-starred">⭐ ستاره‌دار</span>
							<?php endif; ?>
							<?php if ( ! empty( $comm['badge'] ) ) : ?>
								<span class="hj-badge-custom"><?php echo esc_html( $comm['badge'] ); ?></span>
							<?php endif; ?>
						</div>

						<div class="hj-ribbon__meta-row">
							<?php if ( $specialty_fa ) : ?>
								<span class="hj-ribbon__spec"><?php echo esc_html( $specialty_fa ); ?></span>
							<?php endif; ?>
							<?php if ( $city_label ) : ?>
								<span class="hj-ribbon__city">📍 <?php echo esc_html( $city_label ); ?></span>
							<?php endif; ?>
							<?php if ( $exp_years ) : ?>
								<span class="hj-ribbon__exp">سابقه: <?php echo esc_html( $exp_years ); ?> سال</span>
							<?php endif; ?>
							<?php if ( $surgeries ) : ?>
								<span class="hj-ribbon__surg">+<?php echo esc_html( $surgeries ); ?> جراحی</span>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="hj-ribbon__left">
					<div class="hj-ribbon__rating-pill">
						<span class="hj-star-icon">★</span>
						<span class="hj-rating-num"><?php echo $rating_avg > 0 ? esc_html( number_format_i18n( $rating_avg, 1 ) ) : 'جدید'; ?></span>
						<?php if ( $rating_count > 0 ) : ?>
							<span class="hj-rating-cnt">(<?php echo esc_html( number_format_i18n( $rating_count ) ); ?> نظر)</span>
						<?php endif; ?>
					</div>
					<span class="hj-ribbon__action-btn">
						<?php echo $is_blurred ? 'انتخاب استان برای مشاهده 🔒' : 'مشاهده پروفایل جراح ←'; ?>
					</span>
				</div>
			</a>
			<?php
			return ob_get_clean();
		}

		private function query_surgeons_for_slot( $city_id, $specialty_id, $limit ) {
			$tax_query = array( 'relation' => 'AND' );
			if ( $city_id > 0 ) {
				$tax_query[] = array(
					'taxonomy' => 'city',
					'field'    => 'term_id',
					'terms'    => $city_id,
				);
			}
			if ( $specialty_id > 0 ) {
				$tax_query[] = array(
					'taxonomy' => 'specialty',
					'field'    => 'term_id',
					'terms'    => $specialty_id,
				);
			}

			$args = array(
				'post_type'      => 'doctor',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'tax_query'      => $tax_query,
				'meta_query'     => array(
					array(
						'key'     => 'hj_profile_status',
						'value'   => 'published',
						'compare' => '=',
					),
				),
			);

			$posts = get_posts( $args );
			if ( empty( $posts ) ) {
				return array();
			}

			usort( $posts, function( $a, $b ) {
				$ca = hj_get_surgeon_commercial_data( $a->ID );
				$cb = hj_get_surgeon_commercial_data( $b->ID );
				if ( $ca['priority'] !== $cb['priority'] ) {
					return $cb['priority'] <=> $ca['priority'];
				}
				$ra = (float) get_post_meta( $a->ID, 'hj_rating_avg', true );
				$rb = (float) get_post_meta( $b->ID, 'hj_rating_avg', true );
				return $rb <=> $ra;
			} );

			return array_slice( $posts, 0, $limit );
		}
	}

	new HJ_Tech_Navigator();

endif;
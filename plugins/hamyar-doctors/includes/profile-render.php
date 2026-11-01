<?php
/**
 * Hamyar Doctors — Section 7: Public single-doctor profile renderer
 *
 * File: wp-content/plugins/hamyar-doctors/includes/profile-render.php
 *
 * Rules implemented:
 *  - Empty fields are never printed.
 *  - FA text: Persian digits. EN text: Latin digits. Bilingual content is manual only.
 *  - Private documents / system fields are never rendered.
 *  - Non-public profiles: 404 for visitors, preview (noindex) for owner/admin.
 *
 * Hooks emitted:
 *   do_action('hj_doctor_after_schedule', $doctor_id);
 *   do_action('hj_doctor_rating_box', $doctor_id);
 *   do_action('hj_doctor_embed_box', $doctor_id);
 * Filters:
 *   hj_doctor_rating_box_html ('' , $id), hj_pr_rating_shortcode ('' ; supports {id}),
 *   hj_doctor_rating_stats (null, $id) => array('avg'=>, 'approved'=>),
 *   hj_pr_min_votes (5), hj_doctors_map_hosts
 * Public functions:
 *   hj_doctor_profile_html($id), hj_pr_is_viewable($id)
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HJ_Doctors_Profile_Render', false ) ) :

	final class HJ_Doctors_Profile_Render {

		private static $css_done = false;

		public static function init() {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
			add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
			add_action( 'wp_head', array( __CLASS__, 'jsonld' ), 5 );
			add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
			add_action( 'admin_post_hj_pr_status', array( __CLASS__, 'admin_status' ) );
		}

		/* ================================================================== */
		/* Assets / head                                                       */
		/* ================================================================== */

		public static function enqueue() {
			if ( is_singular( 'doctor' ) ) {
				self::ensure_assets();
			}
		}

		private static function ensure_assets() {
			if ( self::$css_done ) {
				return;
			}
			self::$css_done = true;
			$base = defined( 'HJ_DOCTORS_URL' ) ? trailingslashit( HJ_DOCTORS_URL ) : trailingslashit( plugins_url( '', dirname( __FILE__ ) ) );
			$ver  = defined( 'HJ_DOCTORS_VERSION' ) ? HJ_DOCTORS_VERSION : '1';
			if ( ! wp_style_is( 'hj-profile', 'registered' ) ) {
				wp_register_style( 'hj-profile', $base . 'assets/css/profile.css', array(), $ver );
			}
			wp_enqueue_style( 'hj-profile' );
		}

		public static function body_class( $classes ) {
			if ( is_singular( 'doctor' ) ) {
				$classes[] = 'hj-pr-page';
			}
			return $classes;
		}

		public static function robots( $robots ) {
			if ( is_singular( 'doctor' ) ) {
				$id = get_queried_object_id();
				if ( $id && ! self::is_public( $id ) ) {
					$robots['noindex']  = true;
					$robots['nofollow'] = true;
				}
			}
			return $robots;
		}

		/* ================================================================== */
		/* Access                                                              */
		/* ================================================================== */

		public static function is_public( $id ) {
			$id = (int) $id;
			if ( function_exists( 'hj_dp_is_public' ) ) {
				return (bool) hj_dp_is_public( $id );
			}
			return get_post_status( $id ) === 'publish';
		}

		public static function can_preview( $id ) {
			return is_user_logged_in() && current_user_can( 'edit_post', (int) $id );
		}

		public static function is_viewable( $id ) {
			$id = (int) $id;
			if ( ! $id || get_post_type( $id ) !== 'doctor' ) {
				return false;
			}
			return self::is_public( $id ) || self::can_preview( $id );
		}

		/* ================================================================== */
		/* Small helpers                                                       */
		/* ================================================================== */

		private static function digits_fa_list() {
			return array( '۰', '', '۲', '', '۴', '', '۶', '', '۸', '' );
		}

		private static function digits_ar_list() {
			return array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		}

		private static function fa( $s ) {
			$s = (string) $s;
			if ( function_exists( 'hj_digits' ) ) {
				return hj_digits( $s, 'fa' );
			}
			return str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), self::digits_fa_list(), $s );
		}

		private static function en( $s ) {
			$s = (string) $s;
			if ( function_exists( 'hj_digits' ) ) {
				return hj_digits( $s, 'en' );
			}
			$l = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
			return str_replace( array_merge( self::digits_fa_list(), self::digits_ar_list() ), array_merge( $l, $l ), $s );
		}

		/** Raw ACF/meta value (unformatted). */
		private static function val( $id, $name ) {
			$v = function_exists( 'get_field' ) ? get_field( $name, $id, false ) : get_post_meta( $id, $name, true );
			if ( is_array( $v ) ) {
				return $v;
			}
			return ( $v === null || $v === false ) ? '' : trim( (string) $v );
		}

		private static function txt( $id, $name ) {
			$v = self::val( $id, $name );
			return is_array( $v ) ? '' : $v;
		}

		private static function lines( $text, $max = 60 ) {
			$out = array();
			foreach ( preg_split( '/\R/u', (string) $text ) as $ln ) {
				$ln = trim( $ln );
				if ( $ln !== '' ) {
					$out[] = $ln;
				}
				if ( count( $out ) >= $max ) {
					break;
				}
			}
			return $out;
		}

		private static function paragraphs( $text, $mode ) {
			$h = '';
			foreach ( self::lines( $text, 40 ) as $ln ) {
				$ln = ( $mode === 'en' ) ? self::en( $ln ) : self::fa( $ln );
				$h .= '<p>' . esc_html( $ln ) . '</p>';
			}
			return $h;
		}

		private static function term_links( $id, $tax ) {
			$out = array();
			if ( ! taxonomy_exists( $tax ) ) {
				return $out;
			}
			$ts = get_the_terms( $id, $tax );
			if ( ! $ts || is_wp_error( $ts ) ) {
				return $out;
			}
			foreach ( $ts as $t ) {
				$u = get_term_link( $t );
				if ( is_wp_error( $u ) ) {
					continue;
				}
				$out[] = array( $t->name, $u );
			}
			return $out;
		}

		public static function photo( $id, $size = 'medium_large' ) {
			$raw = self::val( $id, 'profile_photo' );
			$aid = is_array( $raw ) ? 0 : (int) $raw;
			if ( $aid && get_post_type( $aid ) === 'attachment' ) {
				$u = wp_get_attachment_image_url( $aid, $size );
				if ( $u ) {
					return $u;
				}
			}
			if ( has_post_thumbnail( $id ) ) {
				$u = get_the_post_thumbnail_url( $id, $size );
				if ( $u ) {
					return $u;
				}
			}
			if ( function_exists( 'hj_dp_photo_url' ) ) {
				$u = hj_dp_photo_url( $id );
				if ( $u ) {
					return $u;
				}
			}
			return function_exists( 'hj_doctors_default_photo_url' ) ? hj_doctors_default_photo_url() : '';
		}

		private static function map_url_ok( $url ) {
			if ( strpos( $url, 'https://' ) !== 0 ) {
				return false;
			}
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

		/* ---------------------------- hours ------------------------------- */

		private static function days() {
			static $cache = null;
			if ( $cache !== null ) {
				return $cache;
			}
			$days = array();
			if ( function_exists( 'acf_get_field' ) ) {
				$f = acf_get_field( 'working_hours' );
				if ( is_array( $f ) && ! empty( $f['sub_fields'] ) ) {
					foreach ( $f['sub_fields'] as $sf ) {
						if ( isset( $sf['name'] ) && $sf['name'] === 'day_of_week' && ! empty( $sf['choices'] ) ) {
							foreach ( $sf['choices'] as $k => $l ) {
								$days[ (string) $k ] = (string) $l;
							}
							break;
						}
					}
				}
			}
			if ( ! $days ) {
				$days = array(
					'saturday'  => 'شنبه',
					'sunday'    => 'یکشنبه',
					'monday'    => 'دوشنبه',
					'tuesday'   => 'سه‌شنبه',
					'wednesday' => 'چهارشنبه',
					'thursday'  => 'پنجشنبه',
					'friday'    => 'جمعه',
				);
			}
			$cache = $days;
			return $cache;
		}

		private static function time_hm( $v ) {
			$v = self::en( trim( (string) $v ) );
			if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $v, $m ) ) {
				$h  = (int) $m[1];
				$mi = (int) $m[2];
				if ( $h < 24 && $mi < 60 ) {
					return sprintf( '%02d:%02d', $h, $mi );
				}
			}
			return '';
		}

		/** @return array of array(label, closed(bool), start, end) */
		private static function hours_rows( $id ) {
			$rows = self::val( $id, 'working_hours' );
			if ( ! is_array( $rows ) || ! $rows ) {
				return array();
			}
			$days = self::days();
			$map  = array();
			foreach ( $rows as $r ) {
				if ( ! is_array( $r ) || empty( $r['day_of_week'] ) ) {
					continue;
				}
				$map[ (string) $r['day_of_week'] ] = array(
					'closed' => ! empty( $r['is_closed'] ),
					'start'  => self::time_hm( isset( $r['start_time'] ) ? $r['start_time'] : '' ),
					'end'    => self::time_hm( isset( $r['end_time'] ) ? $r['end_time'] : '' ),
				);
			}
			$out = array();
			foreach ( $days as $k => $label ) {
				if ( ! isset( $map[ $k ] ) ) {
					continue;
				}
				$r = $map[ $k ];
				if ( $r['closed'] || ( $r['start'] && $r['end'] ) ) {
					$out[] = array( $label, $r['closed'], $r['start'], $r['end'] );
				}
				unset( $map[ $k ] );
			}
			foreach ( $map as $k => $r ) { // values not found in the ACF choices: never lose data
				if ( $r['closed'] || ( $r['start'] && $r['end'] ) ) {
					$out[] = array( (string) $k, $r['closed'], $r['start'], $r['end'] );
				}
			}
			return $out;
		}

		/* ---------------------------- rating ------------------------------ */

		/** Official stats come from section 9 via filter; no guessing on the public page. */
		public static function rating_stats( $id ) {
			$s = apply_filters( 'hj_doctor_rating_stats', null, (int) $id );
			if ( ! is_array( $s ) ) {
				return null;
			}
			return array(
				'avg'      => isset( $s['avg'] ) ? (float) $s['avg'] : 0.0,
				'approved' => isset( $s['approved'] ) ? (int) $s['approved'] : 0,
			);
		}

		private static function public_avg( $id ) {
			$s = self::rating_stats( $id );
			$min = max( 1, (int) apply_filters( 'hj_pr_min_votes', 5 ) );
			if ( ! $s || $s['approved'] < $min || $s['avg'] <= 0 ) {
				return null;
			}
			return $s;
		}

		/* ================================================================== */
		/* JSON-LD                                                             */
		/* ================================================================== */

		public static function jsonld() {
			if ( ! is_singular( 'doctor' ) ) {
				return;
			}
			$id = get_queried_object_id();
			if ( ! $id || ! self::is_public( $id ) ) {
				return;
			}
			$name = self::txt( $id, 'name_fa' );
			if ( $name === '' ) {
				$name = get_the_title( $id );
			}
			$d = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Physician',
				'name'     => $name,
				'url'      => get_permalink( $id ),
			);
			$en = self::txt( $id, 'name_en' );
			if ( $en !== '' ) {
				$d['alternateName'] = $en;
			}
			$img = self::photo( $id, 'medium_large' );
			if ( $img ) {
				$d['image'] = $img;
			}
			$spec = self::txt( $id, 'specialty_fa' );
			if ( $spec !== '' ) {
				$d['description'] = $spec;
			}
			$tel = preg_replace( '/[^\d+]/', '', self::en( self::txt( $id, 'phone_clinic' ) ) );
			if ( $tel !== '' ) {
				$d['telephone'] = $tel;
			}
			$street = self::txt( $id, 'address_clinic' );
			if ( $street === '' ) {
				$street = self::txt( $id, 'address_hospital' );
			}
			$city = self::term_links( $id, 'city' );
			$prov = self::term_links( $id, 'province' );
			if ( $street !== '' || $city || $prov ) {
				$a = array( '@type' => 'PostalAddress', 'addressCountry' => 'IR' );
				if ( $street !== '' ) {
					$a['streetAddress'] = $street;
				}
				if ( $city ) {
					$a['addressLocality'] = $city[0][0];
				}
				if ( $prov ) {
					$a['addressRegion'] = $prov[0][0];
				}
				$d['address'] = $a;
			}
			$avg = self::public_avg( $id );
			if ( $avg ) {
				$d['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => round( $avg['avg'], 1 ),
					'ratingCount' => $avg['approved'],
					'bestRating'  => 5,
					'worstRating' => 1,
				);
			}
			echo '<script type="application/ld+json">'
				. wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
				. '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		/* ================================================================== */
		/* Render                                                              */
		/* ================================================================== */

		private static function capture( $hook, $id ) {
			ob_start();
			do_action( $hook, $id );
			return trim( (string) ob_get_clean() );
		}

		public static function render( $id ) {
			$id = (int) $id;
			if ( ! $id || get_post_type( $id ) !== 'doctor' ) {
				return '';
			}
			self::ensure_assets();

			$name_fa = self::txt( $id, 'name_fa' );
			if ( $name_fa === '' ) {
				$name_fa = get_the_title( $id );
			}
			$name_en = self::txt( $id, 'name_en' );
			$spec_fa = self::txt( $id, 'specialty_fa' );
			$spec_en = self::txt( $id, 'specialty_en' );
			$bio_fa  = self::txt( $id, 'bio_fa' );
			$bio_en  = self::txt( $id, 'bio_en' );
			$edu     = self::lines( self::txt( $id, 'education' ), 40 );
			$surg    = self::lines( self::txt( $id, 'surgeries_performed' ), 60 );
			$license = self::txt( $id, 'medical_license' );
			$exp     = self::en( self::txt( $id, 'experience_years' ) );
			$exp     = ( ctype_digit( $exp ) && (int) $exp > 0 ) ? (int) $exp : 0;
			$verified = self::txt( $id, 'is_verified' ) === '1';
			$preview  = ! self::is_public( $id );

			$tags = array();
			foreach ( array( 'specialty_type', 'specialty', 'city', 'province' ) as $tax ) {
				foreach ( self::term_links( $id, $tax ) as $t ) {
					$tags[] = $t;
				}
			}

			$archive = get_post_type_archive_link( 'doctor' );
			if ( ! $archive ) {
				$archive = home_url( '/doctor/' );
			}

			// Contact
			$phones = array(
				'phone_clinic' => 'تلفن مطب',
				'phone_mobile' => 'موبایل',
			);
			$contact = '';
			foreach ( $phones as $key => $label ) {
				$p = self::txt( $id, $key );
				if ( $p === '' ) {
					continue;
				}
				$tel  = preg_replace( '/[^\d+]/', '', self::en( $p ) );
				$disp = esc_html( self::fa( $p ) );
				$contact .= '<li class="hj-pr__row"><span class="hj-pr__k">' . esc_html( $label ) . '</span>'
					. ( $tel !== ''
						? '<a class="hj-pr__v hj-pr__v--ltr" dir="ltr" href="' . esc_url( 'tel:' . $tel ) . '">' . $disp . '</a>'
						: '<span class="hj-pr__v hj-pr__v--ltr" dir="ltr">' . $disp . '</span>' )
					. '</li>';
			}
			foreach ( array( 'address_clinic' => 'نشانی مطب', 'address_hospital' => 'نشانی بیمارستان' ) as $key => $label ) {
				$a = self::txt( $id, $key );
				if ( $a === '' ) {
					continue;
				}
				$contact .= '<li class="hj-pr__row hj-pr__row--block"><span class="hj-pr__k">' . esc_html( $label ) . '</span>'
					. '<span class="hj-pr__v">' . esc_html( self::fa( $a ) ) . '</span></li>';
			}
			$map = self::txt( $id, 'map_link' );
			$map_btn = '';
			if ( $map !== '' && self::map_url_ok( $map ) ) {
				$map_btn = '<a class="hj-pr__btn" href="' . esc_url( $map ) . '" target="_blank" rel="noopener noreferrer nofollow">مسیریابی روی نقشه</a>';
			}

			// Hours
			$hours = self::hours_rows( $id );

			// Rating / embed slots
			$rating_html = (string) apply_filters( 'hj_doctor_rating_box_html', '', $id );
			$sc          = (string) apply_filters( 'hj_pr_rating_shortcode', '' );
			if ( $rating_html === '' && $sc !== '' ) {
				$rating_html = do_shortcode( str_replace( '{id}', (string) $id, $sc ) );
			}
			$rating_html .= self::capture( 'hj_doctor_rating_box', $id );
			$embed_html   = self::capture( 'hj_doctor_embed_box', $id );
			$avg          = self::public_avg( $id );

			$photo = self::photo( $id );

			ob_start();
			?>
<article class="hj-pr" id="hj-doctor-<?php echo (int) $id; ?>" dir="rtl" lang="fa">

  <nav class="hj-pr__crumbs" aria-label="مسیر صفحه">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a>
    <span aria-hidden="true">‹</span>
    <a href="<?php echo esc_url( $archive ); ?>">پزشکان</a>
    <span aria-hidden="true">‹</span>
    <span><?php echo esc_html( $name_fa ); ?></span>
  </nav>

  <header class="hj-pr__hero">
    <?php if ( $photo ) : ?>
    <div class="hj-pr__photo">
      <img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name_fa ); ?>" width="168" height="168" decoding="async" fetchpriority="high">
    </div>
    <?php endif; ?>

    <div class="hj-pr__id">
      <?php if ( $preview ) : ?>
        <span class="hj-pr__chip hj-pr__chip--preview">پیش‌نمایش (عمومی نیست)</span>
      <?php endif; ?>

      <h1 class="hj-pr__name"><?php echo esc_html( $name_fa ); ?></h1>
      <?php if ( $name_en !== '' ) : ?>
        <p class="hj-pr__name-en" dir="ltr" lang="en"><?php echo esc_html( self::en( $name_en ) ); ?></p>
      <?php endif; ?>

      <?php if ( $spec_fa !== '' ) : ?>
        <p class="hj-pr__spec"><?php echo esc_html( self::fa( $spec_fa ) ); ?></p>
      <?php endif; ?>
      <?php if ( $spec_en !== '' ) : ?>
        <p class="hj-pr__spec-en" dir="ltr" lang="en"><?php echo esc_html( self::en( $spec_en ) ); ?></p>
      <?php endif; ?>

      <?php if ( $verified || $exp || $license !== '' || $avg ) : ?>
      <ul class="hj-pr__facts">
        <?php if ( $verified ) : ?>
          <li class="hj-pr__verified"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 0l1.9 1.5 2.4-.3.9 2.3 2.2 1.1-.5 2.4 1.1 2.1-1.8 1.7-.1 2.4-2.4.5L8 16l-1.7-1.3-2.4-.5-.1-2.4L1.9 10l1.1-2.1-.5-2.4 2.2-1.1.9-2.3 2.4.3L8 0zm-1 10.6l4-4-1-1-3 3-1.5-1.5-1 1L7 10.6z"/></svg><span>پروفایل تأییدشده</span></li>
        <?php endif; ?>
        <?php if ( $exp ) : ?>
          <li><b><?php echo esc_html( self::fa( $exp ) ); ?></b> سال سابقه</li>
        <?php endif; ?>
        <?php if ( $license !== '' ) : ?>
          <li>شمارهٔ نظام پزشکی: <b dir="ltr"><?php echo esc_html( self::fa( $license ) ); ?></b></li>
        <?php endif; ?>
        <?php if ( $avg ) :
            $fill = max( 0, min( 100, (int) round( $avg['avg'] / 5 * 100 ) ) );
            $num  = self::fa( str_replace( '.', '٫', number_format( $avg['avg'], 1, '.', '' ) ) );
            ?>
          <li class="hj-pr__avg">
            <span class="hj-pr-stars" role="img" aria-label="<?php echo esc_attr( 'امتیاز ' . $num . ' از ۵' ); ?>" style="--hj-fill:<?php echo (int) $fill; ?>%"><i aria-hidden="true">★★★★★</i></span>
            <b><?php echo esc_html( $num ); ?></b>
            <span class="hj-pr__muted">(<?php echo esc_html( self::fa( $avg['approved'] ) ); ?> رأی)</span>
          </li>
        <?php endif; ?>
      </ul>
      <?php endif; ?>

      <?php if ( $tags ) : ?>
      <ul class="hj-pr__tags">
        <?php foreach ( $tags as $t ) : ?>
          <li><a class="hj-pr__tag" href="<?php echo esc_url( $t[1] ); ?>"><?php echo esc_html( $t[0] ); ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </header>

  <div class="hj-pr__grid">

    <div class="hj-pr__main">

      <?php if ( $bio_fa !== '' || $bio_en !== '' ) : ?>
      <section class="hj-pr__card" aria-labelledby="hj-pr-about">
        <h2 class="hj-pr__h" id="hj-pr-about">دربارهٔ پزشک</h2>
        <?php if ( $bio_fa !== '' ) : ?>
          <div class="hj-pr__text"><?php echo self::paragraphs( $bio_fa, 'fa' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
        <?php endif; ?>
        <?php if ( $bio_en !== '' ) : ?>
          <div class="hj-pr__en" dir="ltr" lang="en">
            <h3 class="hj-pr__h-en">About</h3>
            <?php echo self::paragraphs( $bio_en, 'en' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
          </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ( $edu ) : ?>
      <section class="hj-pr__card" aria-labelledby="hj-pr-edu">
        <h2 class="hj-pr__h" id="hj-pr-edu">تحصیلات و دوره‌ها</h2>
        <ul class="hj-pr__list">
          <?php foreach ( $edu as $ln ) : ?>
            <li><?php echo esc_html( self::fa( $ln ) ); ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if ( $surg ) : ?>
      <section class="hj-pr__card" aria-labelledby="hj-pr-surg">
        <h2 class="hj-pr__h" id="hj-pr-surg">جراحی‌ها و خدمات</h2>
        <ul class="hj-pr__list">
          <?php foreach ( $surg as $ln ) : ?>
            <li><?php echo esc_html( self::fa( $ln ) ); ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

    </div>

    <aside class="hj-pr__side">

      <?php if ( $contact !== '' || $map_btn !== '' ) : ?>
      <section class="hj-pr__card" aria-labelledby="hj-pr-contact">
        <h2 class="hj-pr__h" id="hj-pr-contact">تماس و نشانی</h2>
        <?php if ( $contact !== '' ) : ?>
          <ul class="hj-pr__rows"><?php echo $contact; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?></ul>
        <?php endif; ?>
        <?php echo $map_btn; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
      </section>
      <?php endif; ?>

      <?php if ( $hours ) : ?>
      <section class="hj-pr__card" aria-labelledby="hj-pr-hours">
        <h2 class="hj-pr__h" id="hj-pr-hours">ساعات کاری</h2>
        <ul class="hj-pr__hours">
          <?php foreach ( $hours as $r ) : ?>
            <li<?php echo $r[1] ? ' class="is-closed"' : ''; ?>>
              <span class="hj-pr__day"><?php echo esc_html( $r[0] ); ?></span>
              <?php if ( $r[1] ) : ?>
                <span class="hj-pr__time">تعطیل</span>
              <?php else : ?>
                <span class="hj-pr__time"><bdi dir="ltr"><?php echo esc_html( self::fa( $r[2] ) ); ?></bdi> تا <bdi dir="ltr"><?php echo esc_html( self::fa( $r[3] ) ); ?></bdi></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php
      $after = self::capture( 'hj_doctor_after_schedule', $id );
      if ( $after !== '' ) {
          echo '<div class="hj-pr__slot">' . $after . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- hook output
      }
      if ( $rating_html !== '' ) {
          echo '<section class="hj-pr__card hj-pr__rating" id="hj-rating">' . $rating_html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
      }
      if ( $embed_html !== '' ) {
          echo '<section class="hj-pr__card hj-pr__embed">' . $embed_html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
      }
      ?>
    </aside>
  </div>
</article>
			<?php
			return ob_get_clean();
		}

		/* ================================================================== */
		/* Admin-only status page                                              */
		/* /wp-admin/admin-post.php?action=hj_pr_status[&id=114]               */
		/* ================================================================== */

		public static function admin_status() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) );
			}
			$L  = array();
			$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

			$tpl = locate_template( 'single-doctor.php' );
			$L[] = 'Child template single-doctor.php: ' . ( $tpl ? $tpl : 'NOT FOUND' );
			$L[] = 'Active stylesheet dir: ' . get_stylesheet_directory();
			$L[] = 'hj_dp_is_public(): ' . ( function_exists( 'hj_dp_is_public' ) ? 'OK' : 'MISSING (fallback: post_status)' );
			$L[] = 'hj_dp_photo_url(): ' . ( function_exists( 'hj_dp_photo_url' ) ? 'OK' : 'MISSING (fallback used)' );
			$L[] = 'Rating stats provider (hj_doctor_rating_stats): ' . ( has_filter( 'hj_doctor_rating_stats' ) ? 'attached' : 'none (expected until section 9)' );
			$L[] = 'Rating shortcode filter (hj_pr_rating_shortcode): ' . ( has_filter( 'hj_pr_rating_shortcode' ) ? 'attached' : 'none' );
			$L[] = '';

			if ( ! $id ) {
				$L[] = 'Doctors (add &id=ID for details):';
				$q = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'any', 'numberposts' => 30, 'orderby' => 'ID', 'order' => 'ASC' ) );
				foreach ( $q as $p ) {
					$L[] = sprintf( '  #%d  [%s]  public=%s  %s', $p->ID, $p->post_status, self::is_public( $p->ID ) ? 'yes' : 'no', $p->post_name );
				}
			} elseif ( get_post_type( $id ) !== 'doctor' ) {
				$L[] = 'ID ' . $id . ' is not a doctor post.';
			} else {
				$L[] = 'Doctor #' . $id . '  slug=' . get_post_field( 'post_name', $id ) . '  post_status=' . get_post_status( $id );
				$L[] = 'is_public: ' . ( self::is_public( $id ) ? 'yes' : 'no' );
				$L[] = '';
				$L[] = 'Field presence (yes = will be shown):';
				foreach ( array( 'name_fa', 'name_en', 'specialty_fa', 'specialty_en', 'bio_fa', 'bio_en', 'education', 'surgeries_performed',
					'medical_license', 'experience_years', 'phone_clinic', 'phone_mobile', 'address_clinic', 'address_hospital', 'map_link' ) as $k ) {
					$v = self::val( $id, $k );
					$L[] = sprintf( '  %-22s %s', $k, ( $v !== '' && $v !== array() ) ? 'yes' : '-' );
				}
				$L[] = '  profile_photo          ' . ( self::photo( $id ) ? 'yes (' . self::photo( $id ) . ')' : '-' );
				$L[] = '';
				$L[] = 'Map link host allowed: ' . ( self::txt( $id, 'map_link' ) === '' ? '-' : ( self::map_url_ok( self::txt( $id, 'map_link' ) ) ? 'yes' : 'NO (button hidden)' ) );
				$rows = self::val( $id, 'working_hours' );
				$L[]  = 'working_hours rows in DB: ' . ( is_array( $rows ) ? count( $rows ) : 0 ) . '  | rendered rows: ' . count( self::hours_rows( $id ) );
				if ( is_array( $rows ) ) {
					$known = array_keys( self::days() );
					foreach ( $rows as $r ) {
						if ( is_array( $r ) && ! empty( $r['day_of_week'] ) && ! in_array( (string) $r['day_of_week'], $known, true ) ) {
							$L[] = '  day value not in ACF choices: ' . $r['day_of_week'];
						}
					}
				}
				foreach ( array( 'specialty_type', 'specialty', 'city', 'province' ) as $tax ) {
					$L[] = 'terms ' . $tax . ': ' . count( self::term_links( $id, $tax ) );
				}
				$L[] = 'Permalink: ' . get_permalink( $id );
			}

			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( implode( "\n", $L ) );
			exit;
		}
	}

	HJ_Doctors_Profile_Render::init();

	/** Full HTML of the profile (already escaped). */
	if ( ! function_exists( 'hj_doctor_profile_html' ) ) {
		/**
		 * HTML کامل پروفایل عمومی (escape شده).
		 *
		 * @param int $id شناسهٔ نوشتهٔ پزشک.
		 * @return string
		 */
		function hj_doctor_profile_html( $id ) {
			return HJ_Doctors_Profile_Render::render( $id );
		}
	}

	/** Visitor may see this doctor page? (public, or owner/admin preview) */
	if ( ! function_exists( 'hj_pr_is_viewable' ) ) {
		/**
		 * آیا بازدیدکننده مجاز به دیدن این صفحه است؟
		 *
		 * @param int $id شناسهٔ نوشتهٔ پزشک.
		 * @return bool
		 */
		function hj_pr_is_viewable( $id ) {
			return HJ_Doctors_Profile_Render::is_viewable( $id );
		}
	}

endif;

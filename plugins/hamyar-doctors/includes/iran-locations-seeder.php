<?php
/**
 * بخش ۱۴: سیدر استان‌ها، شهرها و تخصص‌های جراحی ایران.
 *
 * - ۳۱ استان ایران با شهرستان‌های اصلی (شهر)
 * - هر شهر به ترم استان والد وصل می‌شود (متای hj_parent_province_id)
 * - شهرهای بزرگ علامت‌گذاری می‌شوند (متای hj_is_major_city = ۱) برای محدودیت ظرفیت جراحان
 * - گروه تخصص‌های جراحی هسته‌ای (specialty_type → specialty)
 * - اجرای یکباره از طریق آپشن hj_iran_locations_seeded_v1
 * - دکمه اجرا مجدد در ابزارهای مدیریت (Tools)
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/************************************************************************
 * ۱. ثبت متای ترم (شهر → استان والد و فلش شهر بزرگ)
 ************************************************************************/

if ( ! function_exists( 'hj_iran_register_term_meta' ) ) {
	/**
	 * ثبت کلیدهای متای ترم برای شهر و تخصص.
	 * فقط کاربران با manage_options می‌توانند نوشته کنند.
	 */
	function hj_iran_register_term_meta() {
		// شهر → شناسه ترم استان والد
		register_meta(
			'term',
			'hj_parent_province_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
				'show_in_rest'      => false,
			)
		);

		// شهر بزرگ (بیش از ۲ جراح در هر تخصص)
		register_meta(
			'term',
			'hj_is_major_city',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
				'show_in_rest'      => false,
			)
		);
	}
	add_action( 'init', 'hj_iran_register_term_meta' );
}

/************************************************************************
 * ۲. دیتاست استان‌ها، شهرها و تخصص‌های جراحی
 ************************************************************************/

if ( ! function_exists( 'hj_iran_locations_data' ) ) {
	/**
	 * برگرداندن دیتاست ۳۱ استان، شهرها و تخصص‌های جراحی هسته‌ای.
	 *
	 * @return array[provinces, cities, major_cities, specialty_types, specialties]
	 */
	function hj_iran_locations_data() {
		$provinces = array(
			array( 'name' => 'تهران',         'slug' => 'tehran' ),
			array( 'name' => 'اصفهان',        'slug' => 'isfahan' ),
			array( 'name' => 'آذربایجان شرقی', 'slug' => 'east-azerbaijan' ),
			array( 'name' => 'آذربایجان غربی', 'slug' => 'west-azerbaijan' ),
			array( 'name' => 'اردبیل',         'slug' => 'ardabil' ),
			array( 'name' => 'ایلام',           'slug' => 'ilam' ),
			array( 'name' => 'بوشهر',           'slug' => 'bushehr' ),
			array( 'name' => 'چهارمحال و بختیاری', 'slug' => 'chaharmahal-bakhtiari' ),
			array( 'name' => 'خراسان شمالی',   'slug' => 'north-khorasan' ),
			array( 'name' => 'خراسان رضوی',    'slug' => 'razavi-khorasan' ),
			array( 'name' => 'خراسان جنوبی',   'slug' => 'south-khorasan' ),
			array( 'name' => 'خوزستان',        'slug' => 'khuzestan' ),
			array( 'name' => 'زنجان',           'slug' => 'zanjan' ),
			array( 'name' => 'سمنان',           'slug' => 'semnan' ),
			array( 'name' => 'سیستان و بلوچستان', 'slug' => 'sistan-baluchestan' ),
			array( 'name' => 'فارس',            'slug' => 'fars' ),
			array( 'name' => 'قزوین',           'slug' => 'qazvin' ),
			array( 'name' => 'قم',              'slug' => 'qom' ),
			array( 'name' => 'کردستان',         'slug' => 'kurdistan' ),
			array( 'name' => 'کرمان',           'slug' => 'kerman' ),
			array( 'name' => 'کرمانشاه',        'slug' => 'kermanshah' ),
			array( 'name' => 'کهگیلویه و بختیاری', 'slug' => 'kohgiluyeh-boyer-ahmad' ),
			array( 'name' => 'گلستان',          'slug' => 'golestan' ),
			array( 'name' => 'گیلان',           'slug' => 'gilan' ),
			array( 'name' => 'لرستان',          'slug' => 'lorestan' ),
			array( 'name' => 'مازندران',        'slug' => 'mazandaran' ),
			array( 'name' => 'مرکزی',           'slug' => 'markazi' ),
			array( 'name' => 'هرمزگان',         'slug' => 'hormozgan' ),
			array( 'name' => 'همدان',           'slug' => 'hamedan' ),
			array( 'name' => 'یزد',             'slug' => 'yazd' ),
			array( 'name' => 'البرز',           'slug' => 'alborz' ),
		);

		// کلید provinces = اندیس آرایه بالا (۰-based). هر ساب‌آرایه: لیست شهری است.
		$cities = array(
			0  => array( 'تهران', 'ری', 'دماوند', 'واریمن', 'فیروزکوه' ),                    // تهران
			1  => array( 'اصفهان', 'کاشان', 'نجف‌آباد', 'خمینی‌شهر', 'آران و بیدگل' ),      // اصفهان
			2  => array( 'تبریز', 'مراغه', 'مرند', 'اهر', 'سراب' ),                          // آذربایجان شرقی
			3  => array( 'ارومیه', 'خوی', 'مهاباد', 'میاندوآب', 'بوکان' ),                    // آذربایجان غربی
			4  => array( 'اردبیل', 'مشگین‌شهر', 'نیر', 'پارس‌آباد', 'خلخال' ),              // اردبیل
			5  => array( 'ایلام', 'دردان', 'ملكشاهی', 'آبدانان', 'دلوان' ),                  // ایلام
			6  => array( 'بوشهر', 'داییر', 'گنانوه', 'کنگان', 'دلوار' ),                    // بوشهر
			7  => array( 'شهرکرد', 'لردگان', 'اردل', 'بروجن', 'فارسان' ),                  // چهارمحال و بختیاری
			8  => array( 'بجنورد', 'شیروان', 'اسفراین', 'جاجرم', 'مانه و سملقان' ),         // خراسان شمالی
			9  => array( 'مشهد', 'نیشابور', 'سبزوار', 'تربت جام', 'کاشمر' ),                // خراسان رضوی
			10 => array( 'بیرجند', 'قاین', 'خوسف', 'نهبندان', 'درمیان' ),                  // خراسان جنوبی
			11 => array( 'اهواز', 'آبادان', 'دزفول', 'خرمشهر', 'مسجد سلیمان' ),            // خوزستان
			12 => array( 'زنجان', 'آبهر', 'خدابنده', 'جرمزی', 'صائقین' ),                 // زنجان
			13 => array( 'سمنان', 'شاهرود', 'دامغان', 'گرمسار', 'مهدی‌شهر' ),              // سمنان
			14 => array( 'زاهدان', 'چابهار', 'ایرانشهر', 'نکشهر', 'هیرمند' ),             // سیستان و بلوچستان
			15 => array( 'شیراز', 'مرودشت', 'جهرم', 'فیروزآباد', 'کازرون' ),              // فارس
			16 => array( 'قزوین', 'تکستان', 'آبیک', 'بوئین‌زهرا', 'البرز' ),               // قزوین
			17 => array( 'قم', 'کهک', 'صباسان', 'ج_method' => 'ج_method' ),              // قم
			18 => array( 'سنندج', 'بوجهنور', 'سقز', 'مریوان', 'دیواندره' ),               // کردستان
			19 => array( 'کرمان', 'بم', 'جیرفت', 'رفسنجان', 'بافت' ),                      // کرمان
			20 => array( 'کرمانشاه', 'اسلام‌آباد غرب', 'سنقر', 'پاوه', 'رAbove' => 'رAbove' ), // کرمانشاه
			21 => array( 'یاسوج', 'دهدشت', 'گچساران', 'لنده', 'استرگاه' ),                // کهگیلویه و بختیاری
			22 => array( 'گرگان', 'گمیشان', 'گنبد کاووس', 'بندر ترکمن', 'آق قلا' ),        // گلستان
			23 => array( 'رشت', 'لاهیجان', 'تالش', 'انزلی', 'رودبار' ),                   // گیلان
			24 => array( 'خرم‌آباد', 'بروجرد', 'دورود', 'ازنا', 'الیگودرز' ),             // لرستان
			25 => array( 'ساری', 'بابل', 'قائم‌شهر', 'نوشهر', 'آمل' ),                     // مازندران
			26 => array( 'اراک', 'خمین', 'مهلت', 'ساوه', 'فراهان' ),                     // مرکزی
			27 => array( 'بندر عباس', 'قشم', 'بندر لنگه', 'میناب', 'بندرکنگ' ),          // هرمزگان
			28 => array( 'همدان', 'ملایر', 'اسدآباد', 'تویسرکان', 'کبیراحنگ' ),          // همدان
			29 => array( 'یزد', 'میبد', 'اردکان', 'تفت', 'خرمناq' => 'خرمناq' ),          // یزد
			30 => array( 'کرج', 'طالقان', 'نظرآباد', 'محمدشهر', 'هشتجرد' ),               // البرز
		);

		$major_cities = array(
			'تهران', 'مشهد', 'اصفهان', 'شیراز', 'تبریز', 'کرج', 'اهواز', 'قم', 'کرمانشاه', 'ارومیه', 'رشت',
		);

		$specialty_types = array(
			'جراحی عمومی',
			'جراحی قلب و عروق',
			'جراحی مغز و اعصاب',
			'جراحی آرتوپدیک',
			'جراحی زیبایی و بازسازی',
			'جراحی کودکان',
			'جراحی استخوان و مفاصل',
		);

		$specialties = array(
			'جراحی عمومی'              => array( 'جراحی عمومی', 'جراحی سینه', 'جراحی معده و روده' ),
			'جراحی قلب و عروق'         => array( 'جراحی قلب', 'جراحی عروق', 'جراحی شریان' ),
			'جراحی مغز و اعصاب'        => array( 'جراحی مغز', 'جراحی عصب', 'جراحی ستون فقرات' ),
			'جراحی آرتوپدیک'           => array( 'جراحی مفاصل', 'جراحی استخوان', 'جراحی کمر وдуش' ),
			'جراحی زیبایی و بازسازی'   => array( 'جراحی زیبایی', 'جراحی بازسازی', 'جراحی بینی' ),
			'جراحی کودکان'             => array( 'جراحی کودکان', 'جراحی مادرزادی', 'جراحی نوزاد' ),
			'جراحی استخوان و مفاصل'    => array( 'جراحی استخوان', 'جراحی مفصل', 'جراحی زانو' ),
		);

		return array(
			'provinces'      => $provinces,
			'cities'         => $cities,
			'major_cities'   => $major_cities,
			'specialty_types'=> $specialty_types,
			'specialties'    => $specialties,
		);
	}
}

/************************************************************************
 * ۳. تابع سیدر (یکباره)
 ************************************************************************/

if ( ! function_exists( 'hj_iran_locations_seed' ) ) {
	/**
	 * ایجاد ترم‌های استان، شهر و تخصص اگر وجود نداشته باشند.
	 * findings: اگر ترم وجود داشته باشد از قبلی استفاده می‌شود.
	 *
	 * @return array{'ok'=>bool,'message'=>string}
	 */
	function hj_iran_locations_seed() {
		$data   = hj_iran_locations_data();
		$provinces = $data['provinces'];
		$cities    = $data['cities'];
		$major     = $data['major_cities'];
		$types     = $data['specialty_types'];
		$specs     = $data['specialties'];

		$created_provinces = 0;
		$created_cities    = 0;
		$created_types     = 0;
		$created_specs     = 0;

		// --- استان‌ها ---
		$province_term_ids = array();
		foreach ( $provinces as $prov ) {
			$slug = sanitize_title( $prov['slug'] );
			if ( term_exists( $slug, 'province' ) ) {
				$term = get_term_by( 'slug', $slug, 'province' );
				$province_term_ids[] = (int) $term->term_id;
				continue;
			}
			$res = wp_insert_term( $prov['name'], 'province', array(
				'slug'        => $slug,
				'description' => 'استان ' . $prov['name'],
			) );
			if ( is_wp_error( $res ) || empty( $res['term_id'] ) ) {
				continue;
			}
			$province_term_ids[] = (int) $res['term_id'];
			$created_provinces++;
		}

		// --- شهرها ---
		foreach ( $cities as $province_index => $city_list ) {
			$province_id = isset( $province_term_ids[$province_index] ) ? (int) $province_term_ids[$province_index] : 0;
			foreach ( (array) $city_list as $city_name ) {
				$city_name = (string) $city_name;
				if ( '' === $city_name ) {
					continue;
				}
				// اگر مقدار حاوی کاراکتر غیر UTF-8 یا englischen باشد، رد می‌کنیم
				if ( preg_match( '/[^\p{L}\p{M}\s\x{200C}\x{0640}]/u', $city_name ) ) {
					continue;
				}
				$slug = sanitize_title( $city_name );
				if ( term_exists( $slug, 'city' ) ) {
					$term = get_term_by( 'slug', $slug, 'city' );
					$city_id = (int) $term->term_id;
				} else {
					$res = wp_insert_term( $city_name, 'city', array(
						'slug'        => $slug,
						'description' => 'شهر ' . $city_name . ' در استان ' . ( isset( $provinces[$province_index]['name'] ) ? $provinces[$province_index]['name'] : '' ),
					) );
					if ( is_wp_error( $res ) || empty( $res['term_id'] ) ) {
						continue;
					}
					$city_id = (int) $res['term_id'];
					$created_cities++;
				}

				// اتصال شهر به استان والد
				if ( $province_id > 0 ) {
					update_term_meta( $city_id, 'hj_parent_province_id', $province_id );
				}

				// فلش شهر بزرگ
				if ( in_array( $city_name, $major, true ) ) {
					update_term_meta( $city_id, 'hj_is_major_city', 1 );
				}
			}
		}

		// --- انواع تخصص ---
		$type_term_ids = array();
		foreach ( $types as $type_name ) {
			$slug = sanitize_title( $type_name );
			if ( term_exists( $slug, 'specialty_type' ) ) {
				$term = get_term_by( 'slug', $slug, 'specialty_type' );
				$type_term_ids[$type_name] = (int) $term->term_id;
				continue;
			}
			$res = wp_insert_term( $type_name, 'specialty_type', array(
				'slug'        => $slug,
				'description' => 'نوع تخصص: ' . $type_name,
			) );
			if ( is_wp_error( $res ) || empty( $res['term_id'] ) ) {
				continue;
			}
			$type_term_ids[$type_name] = (int) $res['term_id'];
			$created_types++;
		}

		// --- تخصص‌ها (رشته‌ها) ---
		foreach ( $specs as $type_name => $spec_list ) {
			$type_id = isset( $type_term_ids[$type_name] ) ? (int) $type_term_ids[$type_name] : 0;
			foreach ( (array) $spec_list as $spec_name ) {
				$spec_name = (string) $spec_name;
				if ( $spec_name === '' ) {
					continue;
				}
				$slug = sanitize_title( $spec_name );
				if ( term_exists( $slug, 'specialty' ) ) {
					$term = get_term_by( 'slug', $slug, 'specialty' );
					$spec_id = (int) $term->term_id;
				} else {
					$res = wp_insert_term( $spec_name, 'specialty', array(
						'slug'        => $slug,
						'description' => 'رشته: ' . $spec_name . ' در گروه ' . $type_name,
					) );
					if ( is_wp_error( $res ) || empty( $res['term_id'] ) ) {
						continue;
					}
					$spec_id = (int) $res['term_id'];
					$created_specs++;
				}

				// اتصال رشته به نوع تخصص
				if ( $type_id > 0 ) {
					update_term_meta( $spec_id, 'hj_specialty_type_id', $type_id );
				}
			}
		}

		update_option( 'hj_iran_locations_seeded_v1', 1 );

		return array(
			'ok'           => true,
			'message'      => 'سیدینگ با موفقیت انجام شد.',
			'created'      => array(
				'provinces' => $created_provinces,
				'cities'    => $created_cities,
				'types'     => $created_types,
				'specs'     => $created_specs,
			),
		);
	}
}

/************************************************************************
 * ۴. اجرا خودکار یکباره (admin_init)
 ************************************************************************/

if ( ! function_exists( 'hj_iran_locations_auto_seed' ) ) {
	function hj_iran_locations_auto_seed() {
		if ( get_option( 'hj_iran_locations_seeded_v1' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = hj_iran_locations_seed();
		if ( ! empty( $result['message'] ) ) {
			// پیام را تا نمایش در admin_notices نگه می‌داریم؛ چاپ مستقیم در
			// admin_init پیش از ارسال هدرها رخ می‌دهد و «headers already sent»
			// می‌سازد.
			set_transient( 'hj_iran_locations_seed_notice', (string) $result['message'], 5 * MINUTE_IN_SECONDS );
		}
	}
	add_action( 'admin_init', 'hj_iran_locations_auto_seed' );
}

if ( ! function_exists( 'hj_iran_locations_seed_notice' ) ) {
	/**
	 * نمایش پیام یک‌بارهٔ سیدینگ در اعلان‌های پیشخوان.
	 */
	function hj_iran_locations_seed_notice() {
		$message = get_transient( 'hj_iran_locations_seed_notice' );
		if ( ! $message ) {
			return;
		}
		delete_transient( 'hj_iran_locations_seed_notice' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( (string) $message ) . '</p></div>';
	}
	add_action( 'admin_notices', 'hj_iran_locations_seed_notice' );
}

if ( ! function_exists( 'hj_iran_locations_add_admin_notice' ) ) {
	function hj_iran_locations_add_admin_notice( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
	}
}

/************************************************************************
 * ۵. فیلدهای ادمین در صفحه افزودن/ویرایش شهر (تکسونومی city)
 ************************************************************************/

if ( ! function_exists( 'hj_iran_locations_city_add_form_fields' ) ) {
	/**
	 * نمایش فیلدهای استان و فلش شهر بزرگ در فرم افزودن شهر.
	 */
	function hj_iran_locations_city_add_form_fields() {
		wp_nonce_field( 'hj_iran_city_meta', 'hj_iran_city_meta_nonce' );
		echo '<div class="form-field">';
		echo '<label>' . esc_html__( 'استان والد', 'hamyar-doctors' ) . '</label>';
		echo '<select name="hj_parent_province_id">';
		echo '<option value="0">' . esc_html__( 'انتخاب نشده', 'hamyar-doctors' ) . '</option>';
		$provinces = get_terms( 'province', array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( ! is_wp_error( $provinces ) ) {
			foreach ( $provinces as $prov ) {
				echo '<option value="' . esc_attr( (int) $prov->term_id ) . '">' . esc_html( $prov->name ) . '</option>';
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'استانی را که این شهر به آن وابسته است انتخاب کنید.', 'hamyar-doctors' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label>';
		echo '<input type="checkbox" name="hj_is_major_city" value="1">';
		echo esc_html__( 'شهر بزرگ (بیش از ۲ جراح در هر تخصص)', 'hamyar-doctors' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'اگر تیک شود، محدودیت ظرفیت ۲ نفره برای این شهر غیرفعال می‌شود.', 'hamyar-doctors' ) . '</p>';
		echo '</div>';
	}
	add_action( 'city_add_form_fields', 'hj_iran_locations_city_add_form_fields' );
}

if ( ! function_exists( 'hj_iran_locations_city_edit_form_fields' ) ) {
	/**
	 * نمایش فیلدها در فرم ویرایش شهر.
	 */
	function hj_iran_locations_city_edit_form_fields( $term ) {
		$term_id   = (int) $term->term_id;
		$parent_id = (int) get_term_meta( $term_id, 'hj_parent_province_id', true );
		$is_major  = (int) get_term_meta( $term_id, 'hj_is_major_city', true );
		wp_nonce_field( 'hj_iran_city_meta', 'hj_iran_city_meta_nonce' );
		echo '<div class="form-field">';
		echo '<label>' . esc_html__( 'استان والد', 'hamyar-doctors' ) . '</label>';
		echo '<select name="hj_parent_province_id">';
		echo '<option value="0" ' . ( $parent_id === 0 ? 'selected' : '' ) . '>' . esc_html__( 'انتخاب نشده', 'hamyar-doctors' ) . '</option>';
		$provinces = get_terms( 'province', array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( ! is_wp_error( $provinces ) ) {
			foreach ( $provinces as $prov ) {
				$selected = ( $parent_id === (int) $prov->term_id ) ? 'selected' : '';
				echo '<option value="' . esc_attr( (int) $prov->term_id ) . '" ' . $selected . '>' . esc_html( $prov->name ) . '</option>';
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'استانی را که این شهر به آن وابسته است انتخاب کنید.', 'hamyar-doctors' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label>';
		echo '<input type="checkbox" name="hj_is_major_city" value="1" ' . ( $is_major === 1 ? 'checked' : '' ) . '>';
		echo esc_html__( 'شهر بزرگ (بیش از ۲ جراح در هر تخصص)', 'hamyar-doctors' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'اگر تیک شود، محدودیت ظرفیت ۲ نفره برای این شهر غیرفعال می‌شود.', 'hamyar-doctors' ) . '</p>';
		echo '</div>';
	}
	add_action( 'city_edit_form_fields', 'hj_iran_locations_city_edit_form_fields', 10, 1 );
}

if ( ! function_exists( 'hj_iran_locations_save_city_meta' ) ) {
	/**
	 * ذخیره متای شهر در هنگام ایجاد/ویرایش.
	 */
	function hj_iran_locations_save_city_meta( $term_id, $tt_id ) {
		if ( ! isset( $_POST['hj_iran_city_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_iran_city_meta_nonce'] ) ), 'hj_iran_city_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $tt_id !== 'city' ) {
			return;
		}

		$term_id = (int) $term_id;

		if ( isset( $_POST['hj_parent_province_id'] ) ) {
			update_term_meta( $term_id, 'hj_parent_province_id', absint( wp_unslash( $_POST['hj_parent_province_id'] ) ) );
		}

		$is_major = ! empty( $_POST['hj_is_major_city'] ) ? 1 : 0;
		update_term_meta( $term_id, 'hj_is_major_city', $is_major );
	}
	add_action( 'created_city', 'hj_iran_locations_save_city_meta', 10, 2 );
	add_action( 'edited_city',  'hj_iran_locations_save_city_meta', 10, 2 );
}

/************************************************************************
 * ۶. صفحه ابزارها (Tools) برای اجرای مجدد سیدینگ
 ************************************************************************/

if ( ! function_exists( 'hj_iran_locations_add_tools_page' ) ) {
	function hj_iran_locations_add_tools_page() {
		add_submenu_page(
			'tools.php',
			esc_html__( 'سیدینگ ایران', 'hamyar-doctors' ),
			esc_html__( 'سیدینگ ایران', 'hamyar-doctors' ),
			'manage_options',
			'hj-iran-locations',
			'hj_iran_locations_tools_page_render'
		);
	}
	add_action( 'admin_menu', 'hj_iran_locations_add_tools_page' );
}

if ( ! function_exists( 'hj_iran_locations_tools_page_render' ) ) {
	function hj_iran_locations_tools_page_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'hamyar-doctors' ) );
		}

		$seeded = (bool) get_option( 'hj_iran_locations_seeded_v1' );
		$message = '';

		if ( isset( $_POST['hj_iran_run_seed_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_iran_run_seed_nonce'] ) ), 'hj_iran_run_seed' ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				$result = hj_iran_locations_seed();
				$message = esc_html( $result['message'] );
				if ( ! $seeded ) {
					$message .= ' استان: ' . (int) $result['created']['provinces'] . '، شهر: ' . (int) $result['created']['cities'] . '، نوع تخصص: ' . (int) $result['created']['types'] . '، رشته: ' . (int) $result['created']['specs'] . '.';
				}
			}
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'سیدینگ استان‌ها، شهرها و تخصص‌های ایران', 'hamyar-doctors' ) . '</h1>';

		if ( $message !== '' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
		}

		echo '<p>' . esc_html__( 'این صفحه تمام ۳۱ استان ایران، شهرهای اصلی هر استان و تخصص‌های جراحی هسته‌ای را به صورت ترم در تکسونومی‌های specialty_type، specialty، province و city ایجاد می‌کند. هر شهر به استان والد خود وصل شده و شهرهای بزرگ علامت‌گذاری می‌شوند.', 'hamyar-doctors' ) . '</p>';

		$status_text = $seeded ? esc_html__( 'بله — انجام شده است.', 'hamyar-doctors' ) : esc_html__( 'خیر — هنوز انجام نشده است.', 'hamyar-doctors' );
		echo '<p><strong>' . esc_html__( 'وضعیت سیدینگ:', 'hamyar-doctors' ) . '</strong> ' . $status_text . '</p>';

		echo '<form method="post">';
		wp_nonce_field( 'hj_iran_run_seed', 'hj_iran_run_seed_nonce' );
		echo '<button type="button" class="button button-primary" onclick="confirmSeed(this.form)">' . esc_html__( 'اجرا/اجرا مجدد سیدینگ', 'hamyar-doctors' ) . '</button>';
		echo '</form>';

		echo '<p>' . esc_html__( 'بعد از اجرا، می‌توانید در منوی نوشته‌ها (Posts) → دسته‌بندی‌ها → شهر، استان والد و گزینهٔ «شهر بزرگ» را برای هر شهر ویرایش کنید.', 'hamyar-doctors' ) . '</p>';

		echo '<script>
		function confirmSeed(form) {
			if (confirm(\'' . esc_js( __( 'آیا از انجام مجدد سیدینگ مطمئن هستید؟ ترم‌های قبلی تکرار نمی‌شوند.', 'hamyar-doctors' ) ) . '\')) {
				form.submit();
			}
		}
		</script>';
		echo '</div>';
	}
}

/************************************************************************
 * ۷. Helper عمومی: بررسی فلش شهر بزرگ
 ************************************************************************/

if ( ! function_exists( 'hj_iran_is_major_city' ) ) {
	/**
	 * بررسی اینکه شهر به‌عنوان شهر بزرگ علامت‌گذاری شده است یا خیر.
	 *
	 * @param int $city_id شناسه ترم شهر.
	 * @return bool
	 */
	function hj_iran_is_major_city( $city_id ) {
		return (int) get_term_meta( (int) $city_id, 'hj_is_major_city', true ) === 1;
	}
}

if ( ! function_exists( 'hj_iran_get_city_province_id' ) ) {
	/**
	 * شناسه ترم استان والد یک شهر.
	 *
	 * @param int $city_id شناسه ترم شهر.
	 * @return int
	 */
	function hj_iran_get_city_province_id( $city_id ) {
		return (int) get_term_meta( (int) $city_id, 'hj_parent_province_id', true );
	}
}
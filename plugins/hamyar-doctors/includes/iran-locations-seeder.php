<?php
/**
 * بخش ۱۴: سیدر استان‌ها، شهرستان‌ها و تخصص‌های جراحی ایران.
 *
 * نسخهٔ بازنویسی‌شدهٔ ۰.۳.۱ — داده‌های ساختگی نسخهٔ قبل حذف و با نام‌های
 * واقعی شهرستان‌ها جایگزین شده است.
 *
 * - ۳۱ استان رسمی ایران
 * - شهرستان‌های شناخته‌شدهٔ هر استان با نگاشت به استان والد
 * - شهرهای بزرگ (کلان‌شهر) با متای hj_is_major_city = ۱ برای سقف جایگاه جراحان
 * - درخت تخصص‌های جراحی (specialty_type → specialty)
 * - اجرای یک‌باره از طریق آپشن hj_iran_locations_seeded_v1
 * - دکمهٔ اجرای مجدد در ابزارهای مدیریت (Tools → سیدینگ ایران)
 *
 * نکتهٔ سازگاری: هر شهر هم با کلید تازهٔ hj_province_id (که archive-render و
 * dashboard می‌خوانند) و هم با کلید قدیمی hj_parent_province_id (که
 * tech-navigator نسخهٔ قبل می‌خواند) به استان خود وصل می‌شود.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * ۱. ثبت متای ترم
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_register_term_meta' ) ) {
	/**
	 * ثبت کلیدهای متای ترم برای شهر.
	 *
	 * hj_province_id در includes/taxonomies.php ثبت شده است و اینجا تکرار
	 * نمی‌شود؛ فقط کلید قدیمی و فلش شهر بزرگ اینجا ثبت می‌شوند.
	 */
	function hj_iran_register_term_meta() {
		register_meta(
			'term',
			'hj_parent_province_id',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function () {
					return current_user_can( 'manage_options' );
				},
				'show_in_rest'      => false,
			)
		);

		register_meta(
			'term',
			'hj_is_major_city',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function () {
					return current_user_can( 'manage_options' );
				},
				'show_in_rest'      => false,
			)
		);
	}
	add_action( 'init', 'hj_iran_register_term_meta' );
}

/* -------------------------------------------------------------------------
 * ۲. دادهٔ مرجع: استان‌ها، شهرستان‌ها و تخصص‌ها
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_data' ) ) {
	/**
	 * دادهٔ کامل استان‌ها، شهرستان‌ها و تخصص‌های جراحی.
	 *
	 * @return array{provinces:array,major_cities:array,specialty_types:array,specialties:array}
	 */
	function hj_iran_locations_data() {
		$provinces = array(
			array(
				'name'   => 'تهران',
				'slug'   => 'tehran',
				'cities' => array( 'تهران', 'ری', 'شمیرانات', 'اسلامشهر', 'شهریار', 'رباط‌کریم', 'ورامین', 'پاکدشت', 'قرچک', 'پردیس', 'دماوند', 'فیروزکوه', 'ملارد', 'قدس', 'بهارستان' ),
			),
			array(
				'name'   => 'اصفهان',
				'slug'   => 'isfahan',
				'cities' => array( 'اصفهان', 'کاشان', 'نجف‌آباد', 'خمینی‌شهر', 'شهرضا', 'گلپایگان', 'خوانسار', 'نطنز', 'سمیرم', 'فلاورجان', 'لنجان', 'مبارکه', 'آران و بیدگل', 'اردستان', 'نائین' ),
			),
			array(
				'name'   => 'آذربایجان شرقی',
				'slug'   => 'east-azerbaijan',
				'cities' => array( 'تبریز', 'مراغه', 'مرند', 'اهر', 'سراب', 'میانه', 'بناب', 'آذرشهر', 'جلفا', 'کلیبر', 'ملکان', 'هشترود', 'بستان‌آباد', 'اسکو', 'شبستر' ),
			),
			array(
				'name'   => 'آذربایجان غربی',
				'slug'   => 'west-azerbaijan',
				'cities' => array( 'ارومیه', 'خوی', 'مهاباد', 'میاندوآب', 'بوکان', 'سلماس', 'نقده', 'پیرانشهر', 'سردشت', 'ماکو', 'شاهیندژ', 'تکاب', 'چالدران', 'اشنویه' ),
			),
			array(
				'name'   => 'اردبیل',
				'slug'   => 'ardabil',
				'cities' => array( 'اردبیل', 'مشگین‌شهر', 'پارس‌آباد', 'خلخال', 'گرمی', 'بیله‌سوار', 'نیر', 'کوثر', 'سرعین', 'نمین' ),
			),
			array(
				'name'   => 'ایلام',
				'slug'   => 'ilam',
				'cities' => array( 'ایلام', 'دهلران', 'آبدانان', 'دره‌شهر', 'ملکشاهی', 'چرداول', 'ایوان', 'مهران', 'بدره', 'سیروان' ),
			),
			array(
				'name'   => 'بوشهر',
				'slug'   => 'bushehr',
				'cities' => array( 'بوشهر', 'برازجان', 'گناوه', 'دیلم', 'کنگان', 'دیر', 'جم', 'عسلویه', 'تنگستان', 'دشتی' ),
			),
			array(
				'name'   => 'چهارمحال و بختیاری',
				'slug'   => 'chaharmahal-bakhtiari',
				'cities' => array( 'شهرکرد', 'بروجن', 'لردگان', 'فارسان', 'اردل', 'کیار', 'کوهرنگ', 'سامان', 'بن', 'خانمیرزا' ),
			),
			array(
				'name'   => 'خراسان شمالی',
				'slug'   => 'north-khorasan',
				'cities' => array( 'بجنورد', 'شیروان', 'اسفراین', 'جاجرم', 'مانه و سملقان', 'گرمه', 'راز و جرگلان', 'فاروج' ),
			),
			array(
				'name'   => 'خراسان رضوی',
				'slug'   => 'razavi-khorasan',
				'cities' => array( 'مشهد', 'نیشابور', 'سبزوار', 'تربت حیدریه', 'تربت جام', 'کاشمر', 'قوچان', 'گناباد', 'چناران', 'درگز', 'خواف', 'بردسکن', 'سرخس', 'فریمان', 'جغتای' ),
			),
			array(
				'name'   => 'خراسان جنوبی',
				'slug'   => 'south-khorasan',
				'cities' => array( 'بیرجند', 'قائن', 'نهبندان', 'سربیشه', 'درمیان', 'بشرویه', 'فردوس', 'طبس', 'خوسف' ),
			),
			array(
				'name'   => 'خوزستان',
				'slug'   => 'khuzestan',
				'cities' => array( 'اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'مسجدسلیمان', 'بهبهان', 'شوشتر', 'شوش', 'اندیمشک', 'ایذه', 'باغملک', 'رامهرمز', 'ماهشهر', 'امیدیه', 'هندیجان' ),
			),
			array(
				'name'   => 'زنجان',
				'slug'   => 'zanjan',
				'cities' => array( 'زنجان', 'ابهر', 'خدابنده', 'خرمدره', 'ایجرود', 'طارم', 'ماهنشان', 'سلطانیه' ),
			),
			array(
				'name'   => 'سمنان',
				'slug'   => 'semnan',
				'cities' => array( 'سمنان', 'شاهرود', 'دامغان', 'گرمسار', 'مهدی‌شهر', 'میامی', 'آرادان' ),
			),
			array(
				'name'   => 'سیستان و بلوچستان',
				'slug'   => 'sistan-baluchestan',
				'cities' => array( 'زاهدان', 'چابهار', 'ایرانشهر', 'نیک‌شهر', 'سراوان', 'زابل', 'خاش', 'سرباز', 'کنارک', 'میرجاوه', 'هیرمند' ),
			),
			array(
				'name'   => 'فارس',
				'slug'   => 'fars',
				'cities' => array( 'شیراز', 'مرودشت', 'جهرم', 'فیروزآباد', 'کازرون', 'داراب', 'لار', 'آباده', 'نی‌ریز', 'صفاشهر', 'قیر و کارزین', 'استهبان', 'اقلید' ),
			),
			array(
				'name'   => 'قزوین',
				'slug'   => 'qazvin',
				'cities' => array( 'قزوین', 'تاکستان', 'آبیک', 'بوئین‌زهرا', 'آوج', 'البرز', 'محمدیه' ),
			),
			array(
				'name'   => 'قم',
				'slug'   => 'qom',
				'cities' => array( 'قم', 'کهک', 'سلفچگان', 'دستجرد', 'جعفریه' ),
			),
			array(
				'name'   => 'کردستان',
				'slug'   => 'kurdistan',
				'cities' => array( 'سنندج', 'بانه', 'سقز', 'مریوان', 'دیواندره', 'قروه', 'بیجار', 'کامیاران', 'سروآباد', 'دهگلان' ),
			),
			array(
				'name'   => 'کرمان',
				'slug'   => 'kerman',
				'cities' => array( 'کرمان', 'بم', 'جیرفت', 'رفسنجان', 'سیرجان', 'بافت', 'کهنوج', 'بردسیر', 'زرند', 'شهربابک', 'راور', 'رودبار جنوب' ),
			),
			array(
				'name'   => 'کرمانشاه',
				'slug'   => 'kermanshah',
				'cities' => array( 'کرمانشاه', 'اسلام‌آباد غرب', 'سنقر', 'پاوه', 'روانسر', 'جوانرود', 'کنگاور', 'هرسین', 'صحنه', 'قصر شیرین', 'گیلانغرب', 'سرپل ذهاب' ),
			),
			array(
				'name'   => 'کهگیلویه و بویراحمد',
				'slug'   => 'kohgiluyeh-boyer-ahmad',
				'cities' => array( 'یاسوج', 'دهدشت', 'گچساران', 'لنده', 'سی‌سخت', 'باشت', 'چرام', 'بهمئی' ),
			),
			array(
				'name'   => 'گلستان',
				'slug'   => 'golestan',
				'cities' => array( 'گرگان', 'گنبد کاووس', 'بندر ترکمن', 'آق‌قلا', 'علی‌آباد کتول', 'کردکوی', 'بندر گز', 'مینودشت', 'آزادشهر', 'گمیشان', 'رامیان', 'کلاله', 'مراوه‌تپه' ),
			),
			array(
				'name'   => 'گیلان',
				'slug'   => 'gilan',
				'cities' => array( 'رشت', 'لاهیجان', 'بندر انزلی', 'تالش', 'آستارا', 'رودبار', 'فومن', 'صومعه‌سرا', 'آستانه اشرفیه', 'لنگرود', 'رودسر', 'ماسال', 'شفت', 'سیاهکل' ),
			),
			array(
				'name'   => 'لرستان',
				'slug'   => 'lorestan',
				'cities' => array( 'خرم‌آباد', 'بروجرد', 'دورود', 'ازنا', 'الیگودرز', 'کوهدشت', 'پل‌دختر', 'نورآباد', 'الشتر', 'چگنی' ),
			),
			array(
				'name'   => 'مازندران',
				'slug'   => 'mazandaran',
				'cities' => array( 'ساری', 'بابل', 'قائم‌شهر', 'آمل', 'نوشهر', 'چالوس', 'بهشهر', 'رامسر', 'تنکابن', 'نور', 'فریدونکنار', 'جویبار', 'محمودآباد', 'گلوگاه' ),
			),
			array(
				'name'   => 'مرکزی',
				'slug'   => 'markazi',
				'cities' => array( 'اراک', 'ساوه', 'خمین', 'محلات', 'فراهان', 'شازند', 'تفرش', 'آشتیان', 'زرندیه', 'دلیجان', 'کمیجان', 'خنداب' ),
			),
			array(
				'name'   => 'هرمزگان',
				'slug'   => 'hormozgan',
				'cities' => array( 'بندرعباس', 'قشم', 'بندر لنگه', 'میناب', 'بستک', 'پارسیان', 'حاجی‌آباد', 'رودان', 'جاسک', 'بندر خمیر' ),
			),
			array(
				'name'   => 'همدان',
				'slug'   => 'hamedan',
				'cities' => array( 'همدان', 'ملایر', 'نهاوند', 'اسدآباد', 'تویسرکان', 'کبودراهنگ', 'بهار', 'رزن', 'فامنین' ),
			),
			array(
				'name'   => 'یزد',
				'slug'   => 'yazd',
				'cities' => array( 'یزد', 'میبد', 'اردکان', 'تفت', 'مهریز', 'بافق', 'ابرکوه', 'خاتم', 'اشکذر', 'بهاباد' ),
			),
			array(
				'name'   => 'البرز',
				'slug'   => 'alborz',
				'cities' => array( 'کرج', 'فردیس', 'نظرآباد', 'هشتگرد', 'اشتهارد', 'طالقان', 'چهارباغ', 'محمدشهر' ),
			),
		);

		// کلان‌شهرها: سقف جایگاه در این شهرها بالاتر است (hj_get_city_slot_limit).
		$major_cities = array(
			'تهران',
			'مشهد',
			'اصفهان',
			'شیراز',
			'تبریز',
			'کرج',
			'اهواز',
			'قم',
			'کرمانشاه',
			'ارومیه',
			'رشت',
			'ساری',
		);

		$specialty_types = array(
			'جراحی عمومی',
			'جراحی قلب و عروق',
			'جراحی مغز و اعصاب',
			'جراحی استخوان و مفاصل',
			'جراحی زیبایی و ترمیمی',
			'جراحی کودکان',
			'جراحی اورولوژی',
			'جراحی گوش، حلق و بینی',
			'جراحی چشم',
			'جراحی زنان و زایمان',
		);

		$specialties = array(
			'جراحی عمومی'                 => array( 'جراحی عمومی', 'جراحی سینه', 'جراحی گوارش', 'جراحی کبد و صفرا', 'جراحی تیروئید' ),
			'جراحی قلب و عروق'            => array( 'جراحی قلب باز', 'جراحی عروق کرونر', 'جراحی دریچهٔ قلب', 'جراحی آئورت', 'جراحی عروق محیطی' ),
			'جراحی مغز و اعصاب'           => array( 'جراحی مغز', 'جراحی ستون فقرات', 'جراحی قاعدهٔ جمجمه', 'جراحی صرع', 'جراحی اعصاب محیطی' ),
			'جراحی استخوان و مفاصل'       => array( 'جراحی زانو', 'جراحی لگن', 'جراحی شانه', 'جراحی مچ پا', 'جراحی دست' ),
			'جراحی زیبایی و ترمیمی'       => array( 'جراحی بینی', 'جراحی پلک', 'جراحی لیفت صورت', 'جراحی سینهٔ زیبایی', 'جراحی ترمیمی سوختگی' ),
			'جراحی کودکان'                => array( 'جراحی نوزادان', 'جراحی اطفال', 'جراحی اورولوژی کودکان', 'جراحی گوارش کودکان' ),
			'جراحی اورولوژی'              => array( 'جراحی کلیه', 'جراحی پروستات', 'جراحی مثانه', 'جراحی سنگ کلیه', 'جراحی ناباروری مردان' ),
			'جراحی گوش، حلق و بینی'       => array( 'جراحی سینوس', 'جراحی لوزه', 'جراحی گوش میانی', 'جراحی سر و گردن' ),
			'جراحی چشم'                   => array( 'جراحی آب مروارید', 'جراحی شبکیه', 'جراحی لیزیک', 'جراحی گلوکوم' ),
			'جراحی زنان و زایمان'         => array( 'جراحی هیسترکتومی', 'جراحی لاپاراسکوپی زنان', 'جراحی ناباروری', 'جراحی سرطان زنان' ),
		);

		return array(
			'provinces'       => $provinces,
			'major_cities'    => $major_cities,
			'specialty_types' => $specialty_types,
			'specialties'     => $specialties,
		);
	}
}

/* -------------------------------------------------------------------------
 * ۳. اسلاگ‌ساز فارسی (لاتین‌نویسی) برای URLهای خوانا
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_slugify' ) ) {
	/**
	 * تبدیل نام فارسی به اسلاگ لاتین.
	 *
	 * sanitize_title() روی متن فارسی اسلاگ درصدیِ زشت می‌سازد؛ این تابع
	 * آوانگاری ساده می‌دهد تا نشانی‌هایی مثل /city/tehran/ ساخته شود.
	 *
	 * @param string $name     نام فارسی.
	 * @param string $fallback پیشوند جایگزین اگر نتیجه خالی بود.
	 * @return string
	 */
	function hj_iran_slugify( $name, $fallback = 'item' ) {
		$name = (string) $name;

		$map = array(
			'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ٱ' => 'a',
			'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's', 'ج' => 'j',
			'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z',
			'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh',
			'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
			'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k',
			'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v',
			'ؤ' => 'o', 'ه' => 'h', 'ة' => 'h', 'ی' => 'y', 'ي' => 'y',
			'ئ' => 'y', 'ء' => '', 'ى' => 'y', 'ٔ' => '', '‌' => '-',
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);

		$slug = strtr( $name, $map );
		$slug = strtolower( $slug );
		$slug = preg_replace( '/[\x{200C}\x{200D}]/u', '-', $slug ); // نیم‌فاصله و اتصال‌دهنده
		$slug = preg_replace( '/[^a-z0-9]+/u', '-', $slug );
		$slug = trim( (string) $slug, '-' );

		if ( '' === $slug ) {
			$slug = $fallback;
		}

		return $slug;
	}
}

/* -------------------------------------------------------------------------
 * ۴. ابزارهای درج ترم
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_ensure_term' ) ) {
	/**
	 * مطمئن می‌شود یک ترم با این اسلاگ وجود دارد.
	 *
	 * @param string $name        نام ترم.
	 * @param string $taxonomy    نام تاکسونومی.
	 * @param string $slug        اسلاگ لاتین.
	 * @param string $description توضیح.
	 * @return array{term_id:int,created:bool}
	 */
	function hj_iran_ensure_term( $name, $taxonomy, $slug, $description = '' ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return array(
				'term_id' => 0,
				'created' => false,
			);
		}

		$existing = term_exists( $slug, $taxonomy );
		if ( $existing ) {
			$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;

			return array(
				'term_id' => $term_id,
				'created' => false,
			);
		}

		$result = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'slug'        => $slug,
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			// نام تکراری با اسلاگ دیگر: همان ترم موجود را برمی‌گردانیم.
			$by_name = get_term_by( 'name', $name, $taxonomy );
			if ( $by_name instanceof WP_Term ) {
				return array(
					'term_id' => (int) $by_name->term_id,
					'created' => false,
				);
			}

			return array(
				'term_id' => 0,
				'created' => false,
			);
		}

		return array(
			'term_id' => isset( $result['term_id'] ) ? (int) $result['term_id'] : 0,
			'created' => true,
		);
	}
}

if ( ! function_exists( 'hj_iran_ensure_city_term' ) ) {
	/**
	 * درج/بازیابی ترم شهر با اسلاگ یکتا در محدوده همان استان.
	 *
	 * اگر شهر هم‌نامی در استانی دیگر با همین اسلاگ وجود داشته باشد، پسوند
	 * استان به اسلاگ اضافه می‌شود تا ترم‌ها قاطی نشوند.
	 *
	 * @param string $name        نام شهر.
	 * @param int    $province_id شناسهٔ ترم استان.
	 * @param string $prov_slug   اسلاگ استان.
	 * @return array{term_id:int,created:bool}
	 */
	function hj_iran_ensure_city_term( $name, $province_id, $prov_slug ) {
		$base = hj_iran_slugify( $name, 'city' );
		$slug = $base;
		$try  = 0;

		while ( $try < 30 ) {
			$existing = term_exists( $slug, 'city' );
			if ( ! $existing ) {
				break;
			}

			$existing_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
			$owner       = hj_iran_get_city_province_id( $existing_id );

			// همان ترم این استان (یا ترمی که هنوز به استانی وصل نشده) → استفادهٔ مجدد.
			if ( 0 === $owner || (int) $owner === (int) $province_id ) {
				return array(
					'term_id' => $existing_id,
					'created' => false,
				);
			}

			$try++;
			$slug = $base . '-' . $prov_slug;
			if ( $try > 1 ) {
				$slug = $base . '-' . $prov_slug . '-' . $try;
			}
		}

		return hj_iran_ensure_term( $name, 'city', $slug, 'شهر ' . $name );
	}
}

/* -------------------------------------------------------------------------
 * ۵. سیدر (یک‌باره و قابل تکرار)
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_seed' ) ) {
	/**
	 * ایجاد ترم‌های استان، شهر، نوع تخصص و رشته اگر وجود نداشته باشند.
	 *
	 * تکرارپذیر است: ترم‌های موجود بازاستفاده می‌شوند و متای آن‌ها
	 * به‌روزرسانی می‌شود.
	 *
	 * @return array{ok:bool,message:string,created:array}
	 */
	function hj_iran_locations_seed() {
		$data      = hj_iran_locations_data();
		$provinces = $data['provinces'];
		$major     = $data['major_cities'];
		$types     = $data['specialty_types'];
		$specs     = $data['specialties'];

		$created = array(
			'provinces' => 0,
			'cities'    => 0,
			'types'     => 0,
			'specs'     => 0,
		);

		// --- استان‌ها ---
		$province_ids = array();

		foreach ( $provinces as $province ) {
			$term = hj_iran_ensure_term(
				$province['name'],
				'province',
				$province['slug'],
				'استان ' . $province['name']
			);

			if ( $term['created'] ) {
				$created['provinces']++;
			}

			if ( $term['term_id'] > 0 ) {
				$province_ids[ $province['slug'] ] = (int) $term['term_id'];
			}
		}

		// --- شهرستان‌ها ---
		foreach ( $provinces as $province ) {
			$province_id = isset( $province_ids[ $province['slug'] ] ) ? (int) $province_ids[ $province['slug'] ] : 0;

			if ( $province_id <= 0 ) {
				continue;
			}

			foreach ( $province['cities'] as $city_name ) {
				$city = hj_iran_ensure_city_term( $city_name, $province_id, $province['slug'] );

				if ( $city['created'] ) {
					$created['cities']++;
				}

				$city_id = (int) $city['term_id'];
				if ( $city_id <= 0 ) {
					continue;
				}

				// هر دو کلید نوشته می‌شود: کلید تازه برای archive/dashboard و
				// کلید قدیمی برای سازگاری با نسخهٔ پیشین tech-navigator.
				update_term_meta( $city_id, 'hj_province_id', $province_id );
				update_term_meta( $city_id, 'hj_parent_province_id', $province_id );

				$is_major = in_array( $city_name, $major, true ) ? 1 : 0;
				update_term_meta( $city_id, 'hj_is_major_city', $is_major );
			}
		}

		// --- انواع تخصص ---
		$type_ids = array();

		foreach ( $types as $index => $type_name ) {
			$term = hj_iran_ensure_term(
				$type_name,
				'specialty_type',
				hj_iran_slugify( $type_name, 'specialty-type-' . ( $index + 1 ) ),
				'نوع تخصص: ' . $type_name
			);

			if ( $term['created'] ) {
				$created['types']++;
			}

			if ( $term['term_id'] > 0 ) {
				$type_ids[ $type_name ] = (int) $term['term_id'];
			}
		}

		// --- رشته‌ها (تخصص‌های جراحی) ---
		$order = 0;

		foreach ( $specs as $type_name => $spec_list ) {
			$type_id = isset( $type_ids[ $type_name ] ) ? (int) $type_ids[ $type_name ] : 0;

			foreach ( (array) $spec_list as $spec_name ) {
				$order++;

				$term = hj_iran_ensure_term(
					$spec_name,
					'specialty',
					hj_iran_slugify( $spec_name, 'specialty-' . $order ),
					'رشته: ' . $spec_name . ( '' !== $type_name ? ' — گروه ' . $type_name : '' )
				);

				if ( $term['created'] ) {
					$created['specs']++;
				}

				$spec_id = (int) $term['term_id'];
				if ( $spec_id <= 0 ) {
					continue;
				}

				if ( $type_id > 0 ) {
					update_term_meta( $spec_id, 'hj_specialty_type_id', $type_id );
				}

				update_term_meta( $spec_id, 'hj_spec_order', $order );
			}
		}

		update_option( 'hj_iran_locations_seeded_v1', 1 );
		delete_option( 'hj_iran_locations_last_seed' );
		update_option(
			'hj_iran_locations_last_seed',
			array(
				'time'    => time(),
				'created' => $created,
			),
			false
		);

		return array(
			'ok'      => true,
			'message' => 'سیدینگ استان‌ها، شهرستان‌ها و تخصص‌ها با موفقیت انجام شد.',
			'created' => $created,
		);
	}
}

/* -------------------------------------------------------------------------
 * ۶. اجرای خودکار یک‌باره + اعلان ادمین
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_auto_seed' ) ) {
	/**
	 * اجرای خودکار سیدینگ یک‌بار برای مدیر.
	 */
	function hj_iran_locations_auto_seed() {
		if ( get_option( 'hj_iran_locations_seeded_v1' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result = hj_iran_locations_seed();

		// پیام برای نمایش در admin_notices؛ در admin_init چاپ نمی‌کنیم.
		set_transient( 'hj_iran_locations_notice', (string) $result['message'], 300 );
	}
	add_action( 'admin_init', 'hj_iran_locations_auto_seed' );
}

if ( ! function_exists( 'hj_iran_locations_add_admin_notice' ) ) {
	/**
	 * چاپ اعلان موفقیت سیدینگ (فقط مدیر، فقط یک‌بار).
	 */
	function hj_iran_locations_add_admin_notice() {
		$message = get_transient( 'hj_iran_locations_notice' );

		if ( ! $message || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_transient( 'hj_iran_locations_notice' );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( (string) $message ) . '</p></div>';
	}
	add_action( 'admin_notices', 'hj_iran_locations_add_admin_notice' );
}

/* -------------------------------------------------------------------------
 * ۷. فیلدهای ادمین شهر (تاکسونومی city)
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_city_add_form_fields' ) ) {
	/**
	 * فیلدهای استان والد و کلان‌شهر در فرم افزودن شهر.
	 */
	function hj_iran_locations_city_add_form_fields() {
		$provinces = get_terms(
			array(
				'taxonomy'   => 'province',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		wp_nonce_field( 'hj_iran_city_meta', 'hj_iran_city_meta_nonce' );

		echo '<div class="form-field">';
		echo '<label for="hj_parent_province_id">' . esc_html__( 'استان والد', 'hamyar-doctors' ) . '</label>';
		echo '<select name="hj_parent_province_id" id="hj_parent_province_id">';
		echo '<option value="0">' . esc_html__( 'انتخاب نشده', 'hamyar-doctors' ) . '</option>';
		if ( ! is_wp_error( $provinces ) ) {
			foreach ( $provinces as $province ) {
				echo '<option value="' . esc_attr( (int) $province->term_id ) . '">' . esc_html( $province->name ) . '</option>';
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'استانی را که این شهر به آن وابسته است انتخاب کنید.', 'hamyar-doctors' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label>';
		echo '<input type="checkbox" name="hj_is_major_city" value="1"> ';
		echo esc_html__( 'کلان‌شهر (سقف جایگاه بالاتر از ۲ جراح در هر تخصص)', 'hamyar-doctors' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'اگر تیک شود، محدودیت ۲ نفرهٔ هر تخصص برای این شهر برداشته می‌شود.', 'hamyar-doctors' ) . '</p>';
		echo '</div>';
	}
	add_action( 'city_add_form_fields', 'hj_iran_locations_city_add_form_fields' );
}

if ( ! function_exists( 'hj_iran_locations_city_edit_form_fields' ) ) {
	/**
	 * فیلدهای استان والد و کلان‌شهر در فرم ویرایش شهر.
	 *
	 * @param WP_Term $term ترم در حال ویرایش.
	 */
	function hj_iran_locations_city_edit_form_fields( $term ) {
		$term_id   = (int) $term->term_id;
		$parent_id = hj_iran_get_city_province_id( $term_id );
		$is_major  = hj_iran_is_major_city( $term_id );

		$provinces = get_terms(
			array(
				'taxonomy'   => 'province',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		wp_nonce_field( 'hj_iran_city_meta', 'hj_iran_city_meta_nonce' );

		echo '<tr class="form-field">';
		echo '<th scope="row"><label for="hj_parent_province_id">' . esc_html__( 'استان والد', 'hamyar-doctors' ) . '</label></th>';
		echo '<td>';
		echo '<select name="hj_parent_province_id" id="hj_parent_province_id">';
		echo '<option value="0" ' . selected( 0, $parent_id, false ) . '>' . esc_html__( 'انتخاب نشده', 'hamyar-doctors' ) . '</option>';
		if ( ! is_wp_error( $provinces ) ) {
			foreach ( $provinces as $province ) {
				echo '<option value="' . esc_attr( (int) $province->term_id ) . '" ' . selected( (int) $province->term_id, $parent_id, false ) . '>' . esc_html( $province->name ) . '</option>';
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'استانی را که این شهر به آن وابسته است انتخاب کنید.', 'hamyar-doctors' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr class="form-field">';
		echo '<th scope="row">' . esc_html__( 'کلان‌شهر', 'hamyar-doctors' ) . '</th>';
		echo '<td>';
		echo '<label>';
		echo '<input type="checkbox" name="hj_is_major_city" value="1" ' . checked( 1, (int) $is_major, false ) . '> ';
		echo esc_html__( 'کلان‌شهر (سقف جایگاه بالاتر از ۲ جراح در هر تخصص)', 'hamyar-doctors' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'اگر تیک شود، محدودیت ۲ نفرهٔ هر تخصص برای این شهر برداشته می‌شود.', 'hamyar-doctors' ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}
	add_action( 'city_edit_form_fields', 'hj_iran_locations_city_edit_form_fields', 10, 1 );
}

if ( ! function_exists( 'hj_iran_locations_save_city_meta' ) ) {
	/**
	 * ذخیرهٔ متای شهر هنگام ایجاد/ویرایش.
	 *
	 * @param int    $term_id شناسهٔ ترم.
	 * @param string $tt_id   اسلاگ تاکسونومی.
	 */
	function hj_iran_locations_save_city_meta( $term_id, $tt_id ) {
		unset( $tt_id ); // قلاب created_city/edited_city خودش مخصوص تاکسونومی city است.

		if ( ! isset( $_POST['hj_iran_city_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['hj_iran_city_meta_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'hj_iran_city_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$term_id = (int) $term_id;

		if ( isset( $_POST['hj_parent_province_id'] ) ) {
			$province_id = absint( wp_unslash( $_POST['hj_parent_province_id'] ) );
			update_term_meta( $term_id, 'hj_province_id', $province_id );
			update_term_meta( $term_id, 'hj_parent_province_id', $province_id );
		}

		$is_major = ! empty( $_POST['hj_is_major_city'] ) ? 1 : 0;
		update_term_meta( $term_id, 'hj_is_major_city', $is_major );
	}
	add_action( 'created_city', 'hj_iran_locations_save_city_meta', 10, 2 );
	add_action( 'edited_city', 'hj_iran_locations_save_city_meta', 10, 2 );
}

/* -------------------------------------------------------------------------
 * ۸. صفحهٔ ابزارها (Tools) برای اجرای مجدد سیدینگ
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_add_tools_page' ) ) {
	/**
	 * افزودن صفحهٔ ابزار سیدینگ ایران.
	 */
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
	/**
	 * رندر صفحهٔ ابزار سیدینگ.
	 */
	function hj_iran_locations_tools_page_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'hamyar-doctors' ) );
		}

		$message = '';
		$result  = null;

		if ( isset( $_POST['hj_iran_run_seed_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['hj_iran_run_seed_nonce'] ) );

			if ( wp_verify_nonce( $nonce, 'hj_iran_run_seed' ) ) {
				$result  = hj_iran_locations_seed();
				$message = 'استان: ' . (int) $result['created']['provinces']
					. '، شهر: ' . (int) $result['created']['cities']
					. '، نوع تخصص: ' . (int) $result['created']['types']
					. '، رشته: ' . (int) $result['created']['specs'] . '.';
			}
		}

		$last_seed = get_option( 'hj_iran_locations_last_seed', array() );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'سیدینگ استان‌ها، شهرستان‌ها و تخصص‌های ایران', 'hamyar-doctors' ) . '</h1>';

		if ( '' !== $message && is_array( $result ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result['message'] . ' ' . $message ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'این صفحه ۳۱ استان ایران، شهرستان‌های هر استان و درخت تخصص‌های جراحی را به‌صورت ترم در تاکسونومی‌های specialty_type، specialty، province و city ایجاد می‌کند. هر شهر به استان والد وصل شده و کلان‌شهرها علامت‌گذاری می‌شوند.', 'hamyar-doctors' ) . '</p>';

		$seeded      = (bool) get_option( 'hj_iran_locations_seeded_v1' );
		$status_text = $seeded
			? esc_html__( 'بله — انجام شده است.', 'hamyar-doctors' )
			: esc_html__( 'خیر — هنوز انجام نشده است.', 'hamyar-doctors' );

		echo '<p><strong>' . esc_html__( 'وضعیت سیدینگ:', 'hamyar-doctors' ) . '</strong> ' . $status_text . '</p>';

		if ( is_array( $last_seed ) && ! empty( $last_seed['time'] ) ) {
			echo '<p><strong>' . esc_html__( 'آخرین اجرا:', 'hamyar-doctors' ) . '</strong> '
				. esc_html( date_i18n( 'Y/m/d H:i', (int) $last_seed['time'] ) ) . '</p>';
		}

		echo '<form method="post">';
		wp_nonce_field( 'hj_iran_run_seed', 'hj_iran_run_seed_nonce' );
		echo '<button type="submit" class="button button-primary" onclick="return confirm('
			. esc_attr( wp_json_encode( __( 'اجرای مجدد سیدینگ؟ ترم‌های موجود تکرار نمی‌شوند.', 'hamyar-doctors' ) ) )
			. ');">' . esc_html__( 'اجرا / اجرای مجدد سیدینگ', 'hamyar-doctors' ) . '</button>';
		echo '</form>';

		echo '<p>' . esc_html__( 'بعد از اجرا می‌توانید در «پزشک‌ها → شهر» استان والد و علامت کلان‌شهر را برای هر شهر ویرایش کنید.', 'hamyar-doctors' ) . '</p>';

		/**
		 * قلاب افزودن بخش‌های تکمیلی به صفحهٔ ابزار سیدینگ.
		 *
		 * @param bool $seeded وضعیت سیدینگ.
		 */
		do_action( 'hj_iran_tools_extra', $seeded );

		echo '</div>';
	}
}

/* -------------------------------------------------------------------------
 * ۹. توابع کمکی عمومی
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_is_major_city' ) ) {
	/**
	 * آیا این شهر به‌عنوان کلان‌شهر علامت‌گذاری شده است؟
	 *
	 * @param int $city_id شناسهٔ ترم شهر.
	 * @return bool
	 */
	function hj_iran_is_major_city( $city_id ) {
		return 1 === (int) get_term_meta( (int) $city_id, 'hj_is_major_city', true );
	}
}

if ( ! function_exists( 'hj_iran_get_city_province_id' ) ) {
	/**
	 * شناسهٔ ترم استان والد یک شهر.
	 *
	 * کلید تازهٔ hj_province_id اولویت دارد و در صورت خالی بودن، کلید قدیمی
	 * hj_parent_province_id خوانده می‌شود.
	 *
	 * @param int $city_id شناسهٔ ترم شهر.
	 * @return int
	 */
	function hj_iran_get_city_province_id( $city_id ) {
		$city_id = (int) $city_id;

		$province_id = (int) get_term_meta( $city_id, 'hj_province_id', true );
		if ( $province_id > 0 ) {
			return $province_id;
		}

		return (int) get_term_meta( $city_id, 'hj_parent_province_id', true );
	}
}

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
 * - وضعیت اجرای سیدر در آپشن hj_iran_locations_seeded_v1 نگهداری می‌شود.
 * - مسیرهای داخلی اجرا و اصلاح از طریق ابزارهای دستی مدیر باقی می‌مانند.
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

if ( ! function_exists( 'hj_iran_slug_overrides' ) ) {
	/**
	 * آوانگاری دستی شهرهای شناخته‌شده.
	 *
	 * خط فارسی واکه‌های کوتاه را نمی‌نویسد، پس تبدیل حرف‌به‌حرف نام‌هایی مثل
	 * «تهران» را به «thran» تبدیل می‌کند. این جدول برای شهرهای بزرگ و
	 * پرشمارترین نام‌ها، اسلاگ درست می‌دهد؛ بقیه با تبدیل خودکار ساخته
	 * می‌شوند.
	 *
	 * @return array<string,string>
	 */
	function hj_iran_slug_overrides() {
		return array(
			// مراکز استان‌ها.
			'تهران' => 'tehran', 'اصفهان' => 'isfahan', 'تبریز' => 'tabriz', 'ارومیه' => 'urmia',
			'اردبیل' => 'ardabil', 'ایلام' => 'ilam', 'بوشهر' => 'bushehr', 'شهرکرد' => 'shahrekord',
			'بجنورد' => 'bojnurd', 'مشهد' => 'mashhad', 'بیرجند' => 'birjand', 'اهواز' => 'ahvaz',
			'زنجان' => 'zanjan', 'سمنان' => 'semnan', 'زاهدان' => 'zahedan', 'شیراز' => 'shiraz',
			'قزوین' => 'qazvin', 'قم' => 'qom', 'سنندج' => 'sanandaj', 'کرمان' => 'kerman',
			'کرمانشاه' => 'kermanshah', 'یاسوج' => 'yasuj', 'گرگان' => 'gorgan', 'رشت' => 'rasht',
			'خرم‌آباد' => 'khorramabad', 'ساری' => 'sari', 'اراک' => 'arak', 'بندرعباس' => 'bandar-abbas',
			'همدان' => 'hamedan', 'یزد' => 'yazd', 'کرج' => 'karaj',

			// شهرهای بزرگ و پرجست‌وجو.
			'کاشان' => 'kashan', 'نجف‌آباد' => 'najafabad', 'خمینی‌شهر' => 'khomeini-shahr',
			'شهرضا' => 'shahreza', 'گلپایگان' => 'golpayegan', 'مراغه' => 'maragheh', 'مرند' => 'marand',
			'اهر' => 'ahar', 'سراب' => 'sarab', 'میانه' => 'miyaneh', 'خوی' => 'khoy', 'مهاباد' => 'mahabad',
			'میاندوآب' => 'miandoab', 'بوکان' => 'bokan', 'مشگین‌شهر' => 'meshgin-shahr',
			'پارس‌آباد' => 'parsabad', 'خلخال' => 'khalkhal', 'دهلران' => 'dehloran', 'ملکشاهی' => 'malekshahi',
			'آبدانان' => 'abdanan', 'دره‌شهر' => 'darehshahr', 'برازجان' => 'borazjan', 'گناوه' => 'genaveh',
			'کنگان' => 'kangan', 'دیر' => 'dayyer', 'لردگان' => 'lordegan', 'بروجن' => 'borujen',
			'فارسان' => 'farsan', 'شیروان' => 'shirvan', 'اسفراین' => 'esfarayen', 'نیشابور' => 'neyshabur',
			'سبزوار' => 'sabzevar', 'تربت حیدریه' => 'torbat-e-heydarieh', 'تربت جام' => 'torbat-e-jam',
			'کاشمر' => 'kashmar', 'قوچان' => 'ghochan', 'گناباد' => 'gonabad', 'قائن' => 'ghaen',
			'طبس' => 'tabas', 'فردوس' => 'ferdows', 'آبادان' => 'abadan', 'خرمشهر' => 'khorramshahr',
			'دزفول' => 'dezful', 'مسجدسلیمان' => 'masjed-soleyman', 'بهبهان' => 'behbehan',
			'شوشتر' => 'shushtar', 'اندیمشک' => 'andimeshk', 'ایذه' => 'izeh', 'رامهرمز' => 'ramhormoz',
			'ماهشهر' => 'mahshahr', 'ابهر' => 'abhar', 'خدابنده' => 'khodabandeh', 'خرمدره' => 'khorramdareh',
			'شاهرود' => 'shahroud', 'دامغان' => 'damghan', 'گرمسار' => 'garmsar', 'چابهار' => 'chabahar',
			'ایرانشهر' => 'iranshahr', 'نیک‌شهر' => 'nikshahr', 'سراوان' => 'saravan', 'مرودشت' => 'marvdasht',
			'جهرم' => 'jahrom', 'فیروزآباد' => 'firuzabad', 'کازرون' => 'kazerun', 'داراب' => 'darab',
			'لار' => 'lar', 'آباده' => 'abadeh', 'تاکستان' => 'takestan', 'آبیک' => 'abyek',
			'بوئین‌زهرا' => 'buinzahra', 'بانه' => 'baneh', 'سقز' => 'saghez', 'مریوان' => 'marivan',
			'قروه' => 'ghorveh', 'بیجار' => 'bijar', 'بم' => 'bam', 'جیرفت' => 'jiroft',
			'رفسنجان' => 'rafsanjan', 'سیرجان' => 'sirjan', 'کهنوج' => 'kahnooj', 'زرند' => 'zarand',
			'اسلام‌آباد غرب' => 'eslamabad-e-gharb', 'پاوه' => 'paveh', 'کنگاور' => 'kangavar',
			'هرسین' => 'hersin', 'قصر شیرین' => 'ghasr-e-shirin', 'دهدشت' => 'dehdasht',
			'گچساران' => 'gachsaran', 'گنبد کاووس' => 'gonbad-e-kavous', 'بندر ترکمن' => 'bandar-torkaman',
			'علی‌آباد کتول' => 'aliabad-e-katoul', 'کردکوی' => 'kordkuy', 'لاهیجان' => 'lahijan',
			'بندر انزلی' => 'bandar-anzali', 'تالش' => 'talash', 'آستارا' => 'astara', 'رودبار' => 'rudbar',
			'فومن' => 'fuman', 'صومعه‌سرا' => 'someh-sara', 'لنگرود' => 'langarud', 'رودسر' => 'rudsar',
			'بروجرد' => 'borujerd', 'دورود' => 'dorud', 'الیگودرز' => 'aligudarz', 'کوهدشت' => 'kuhdasht',
			'بابل' => 'babol', 'آمل' => 'amol', 'قائم‌شهر' => 'ghaem-shahr', 'نوشهر' => 'nowshahr',
			'چالوس' => 'chalus', 'بهشهر' => 'behshahr', 'رامسر' => 'ramsar', 'تنکابن' => 'tonekabon',
			'نور' => 'nur', 'فریدونکنار' => 'fereydunkenar', 'ساوه' => 'saveh', 'خمین' => 'khomein',
			'محلات' => 'mahallat', 'شازند' => 'shazand', 'تفرش' => 'tafresh', 'دلیجان' => 'delijan',
			'قشم' => 'gheshm', 'بندر لنگه' => 'bandar-lengeh', 'میناب' => 'minab', 'بستک' => 'bastak',
			'ملایر' => 'malayer', 'نهاوند' => 'nahavand', 'اسدآباد' => 'asadabad', 'تویسرکان' => 'tuyserkan',
			'کبودراهنگ' => 'kabudarahang', 'میبد' => 'meybod', 'اردکان' => 'ardakan', 'تفت' => 'taft',
			'مهریز' => 'mehriz', 'بافق' => 'bafgh', 'ابرکوه' => 'abarkuh', 'فردیس' => 'fardis',
			'نظرآباد' => 'nazarabad', 'هشتگرد' => 'hashtgerd', 'اشتهارد' => 'eshtehard', 'طالقان' => 'taleghan',
		);
	}
}

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

		$overrides = hj_iran_slug_overrides();
		if ( isset( $overrides[ $name ] ) ) {
			return $overrides[ $name ];
		}

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
 * ۵.۵ اصلاح ترم‌های مخدوش (مهاجرت از سیدر معیوب نسخهٔ ۰٫۳٫۰)
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_city_index' ) ) {
	/**
	 * نمایهٔ نام شهرهای معتبر → استان آن‌ها.
	 *
	 * @return array<string,array{province:string,slug:string}>
	 */
	function hj_iran_locations_city_index() {
		static $index = null;

		if ( null !== $index ) {
			return $index;
		}

		$index = array();
		$data  = hj_iran_locations_data();

		foreach ( $data['provinces'] as $province ) {
			foreach ( $province['cities'] as $city_name ) {
				if ( ! isset( $index[ $city_name ] ) ) {
					$index[ $city_name ] = array(
						'province' => $province['name'],
						'slug'     => $province['slug'],
					);
				}
			}
		}

		return $index;
	}
}

if ( ! function_exists( 'hj_iran_locations_corrupt_city_map' ) ) {
	/**
	 * نگاشت نام‌های مخدوش ساخته‌شده توسط سیدر معیوب به نام درست.
	 *
	 * مقدار خالی یعنی معادل مطمئنی وجود ندارد و ترم (اگر نوشته‌ای نداشته
	 * باشد) حذف می‌شود.
	 *
	 * @return array<string,string>
	 */
	function hj_iran_locations_corrupt_city_map() {
		return array(
			'داییر'      => 'دیر',
			'گنانوه'     => 'گناوه',
			'دلوان'      => 'دهلران',
			'نکشهر'      => 'نیک‌شهر',
			'تکستان'     => 'تاکستان',
			'مهلت'       => 'محلات',
			'کبیراحنگ'   => 'کبودراهنگ',
			'هشتجرد'     => 'هشتگرد',
			'ملكشاهی'    => 'ملکشاهی',
			'ج_method'   => '',
			'رAbove'     => '',
			'خرمناq'     => '',
			'دردان'      => '',
			'جرمزی'      => '',
			'صائقین'     => '',
			'بوجهنور'    => '',
			'صباسان'     => '',
			'استرگاه'    => '',
		);
	}
}

if ( ! function_exists( 'hj_iran_locations_is_corrupt_name' ) ) {
	/**
	 * آیا نام ترم نشانهٔ خرابی دارد؟ (حروف لاتین، سیریلیک، چینی، ژاپنی…)
	 *
	 * @param string $name نام ترم.
	 * @return bool
	 */
	function hj_iran_locations_is_corrupt_name( $name ) {
		$name = (string) $name;

		if ( '' === $name ) {
			return false;
		}

		// حروف لاتین یا زیرخط داخل نام فارسی.
		if ( preg_match( '/[A-Za-z_]/', $name ) ) {
			return true;
		}

		// خطوط غیرفارسی.
		return (bool) preg_match(
			'/[\x{0400}-\x{04FF}\x{0900}-\x{097F}\x{0E00}-\x{0E7F}\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u',
			$name
		);
	}
}

if ( ! function_exists( 'hj_iran_unique_slug' ) ) {
	/**
	 * اسلاگ یکتا در یک تاکسونومی، با نادیده‌گرفتن ترم جاری.
	 *
	 * @param string $slug      اسلاگ پیشنهادی.
	 * @param string $taxonomy  تاکسونومی.
	 * @param int    $ignore_id شناسهٔ ترمی که نباید تصادم حساب شود.
	 * @return string
	 */
	function hj_iran_unique_slug( $slug, $taxonomy, $ignore_id = 0 ) {
		$base = sanitize_title( (string) $slug );
		if ( '' === $base ) {
			$base = 'term';
		}

		$slug  = $base;
		$limit = 0;

		while ( $limit < 50 ) {
			$existing = term_exists( $slug, $taxonomy );

			if ( ! $existing ) {
				return $slug;
			}

			$existing_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;

			if ( (int) $ignore_id > 0 && $existing_id === (int) $ignore_id ) {
				return $slug;
			}

			$limit++;
			$slug = $base . '-' . $limit;
		}

		return $base . '-' . wp_rand( 1000, 9999 );
	}
}

if ( ! function_exists( 'hj_iran_locations_move_posts' ) ) {
	/**
	 * انتقال نوشته‌ها از یک ترم شهر به ترم دیگر.
	 *
	 * @param int $from_id شناسهٔ ترم مبدأ.
	 * @param int $to_id   شناسهٔ ترم مقصد.
	 * @return int تعداد نوشته‌های منتقل‌شده.
	 */
	function hj_iran_locations_move_posts( $from_id, $to_id ) {
		$from_id = (int) $from_id;
		$to_id   = (int) $to_id;

		if ( $from_id <= 0 || $to_id <= 0 || $from_id === $to_id ) {
			return 0;
		}

		$object_ids = get_objects_in_term( $from_id, 'city' );

		if ( is_wp_error( $object_ids ) || empty( $object_ids ) ) {
			return 0;
		}

		$moved = 0;

		foreach ( $object_ids as $object_id ) {
			$result = wp_set_object_terms( (int) $object_id, array( $to_id ), 'city', false );
			if ( ! is_wp_error( $result ) ) {
				$moved++;
			}
		}

		return $moved;
	}
}

if ( ! function_exists( 'hj_iran_locations_set_city_meta' ) ) {
	/**
	 * نوشتن متای استان و کلان‌شهر برای یک شهر.
	 *
	 * @param int    $city_id     شناسهٔ ترم شهر.
	 * @param int    $province_id شناسهٔ ترم استان.
	 * @param bool   $is_major    کلان‌شهر است؟
	 * @return bool آیا مقداری تغییر کرد؟
	 */
	function hj_iran_locations_set_city_meta( $city_id, $province_id, $is_major ) {
		$city_id     = (int) $city_id;
		$province_id = (int) $province_id;
		$is_major    = $is_major ? 1 : 0;

		if ( $city_id <= 0 || $province_id <= 0 ) {
			return false;
		}

		$current_province = (int) get_term_meta( $city_id, 'hj_province_id', true );
		$current_alias    = (int) get_term_meta( $city_id, 'hj_parent_province_id', true );
		$current_major    = (int) get_term_meta( $city_id, 'hj_is_major_city', true );

		$changed = ( $current_province !== $province_id || $current_alias !== $province_id || $current_major !== $is_major );

		update_term_meta( $city_id, 'hj_province_id', $province_id );
		update_term_meta( $city_id, 'hj_parent_province_id', $province_id );
		update_term_meta( $city_id, 'hj_is_major_city', $is_major );

		return $changed;
	}
}

if ( ! function_exists( 'hj_iran_locations_repair_terms' ) ) {
	/**
	 * اصلاح ترم‌های مخدوش ساخته‌شده توسط سیدر معیوب.
	 *
	 * سه کار انجام می‌دهد:
	 * ۱) نام استان‌ها را با نام رسمی کانونیکال هم‌راست می‌کند.
	 * ۲) شهرهای مخدوش را تغییر نام می‌دهد یا (در صورت وجود معادل) در ترم
	 *    درست ادغام می‌کند؛ ترم مخدوشِ بدون نوشته حذف می‌شود.
	 * ۳) متای استان والد و کلان‌شهر را برای همهٔ شهرهای کانونیکال بازمی‌نویسد.
	 *
	 * هیچ ترمی که نوشته دارد بدون انتقال نوشته‌ها حذف نمی‌شود.
	 *
	 * @return array<string,mixed>
	 */
	function hj_iran_locations_repair_terms() {
		$data  = hj_iran_locations_data();
		$index = hj_iran_locations_city_index();
		$map   = hj_iran_locations_corrupt_city_map();

		$report = array(
			'provinces_renamed' => 0,
			'cities_renamed'    => 0,
			'cities_merged'     => 0,
			'cities_deleted'    => 0,
			'cities_meta_fixed' => 0,
			'specs_deleted'     => 0,
			'posts_moved'       => 0,
			'left_for_review'   => array(),
		);

		// --- ۱) نام استان‌ها ---
		$province_ids = array();

		foreach ( $data['provinces'] as $province ) {
			$term = get_term_by( 'slug', $province['slug'], 'province' );

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$province_ids[ $province['slug'] ] = (int) $term->term_id;

			if ( $term->name !== $province['name'] ) {
				$updated = wp_update_term( (int) $term->term_id, 'province', array( 'name' => $province['name'] ) );
				if ( ! is_wp_error( $updated ) ) {
					$report['provinces_renamed']++;
				}
			}
		}

		// --- ۲) شهرها ---
		$cities = get_terms(
			array(
				'taxonomy'   => 'city',
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( ! is_wp_error( $cities ) ) {
			foreach ( $cities as $city ) {
				$term_id = (int) $city->term_id;
				$name    = (string) $city->name;

				$is_known_corrupt = isset( $map[ $name ] );
				$looks_corrupt    = hj_iran_locations_is_corrupt_name( $name );

				if ( ! $is_known_corrupt && ! $looks_corrupt ) {
					continue;
				}

				$target = $is_known_corrupt ? (string) $map[ $name ] : '';

				// معادل باید داخل دادهٔ کانونیکال باشد.
				if ( '' !== $target && ! isset( $index[ $target ] ) ) {
					$target = '';
				}

				if ( '' !== $target ) {
					$existing = get_term_by( 'name', $target, 'city' );

					if ( $existing instanceof WP_Term && (int) $existing->term_id !== $term_id ) {
						$report['posts_moved'] += hj_iran_locations_move_posts( $term_id, (int) $existing->term_id );
						$deleted                = wp_delete_term( $term_id, 'city' );
						if ( ! is_wp_error( $deleted ) && $deleted ) {
							$report['cities_merged']++;
						}
						continue;
					}

					$new_slug = hj_iran_unique_slug( hj_iran_slugify( $target, 'city' ), 'city', $term_id );
					$updated  = wp_update_term(
						$term_id,
						'city',
						array(
							'name' => $target,
							'slug' => $new_slug,
						)
					);

					if ( ! is_wp_error( $updated ) ) {
						$report['cities_renamed']++;
						$name = $target;
					}
				} else {
					// بدون معادل: فقط ترم بدون نوشته حذف می‌شود.
					if ( 0 === (int) $city->count ) {
						$deleted = wp_delete_term( $term_id, 'city' );
						if ( ! is_wp_error( $deleted ) && $deleted ) {
							$report['cities_deleted']++;
						}
					} else {
						$report['left_for_review'][] = $name . ' (term ' . $term_id . ')';
					}
					continue;
				}

				// متای شهر اصلاح‌شده.
				if ( isset( $index[ $name ] ) ) {
					$prov_slug   = $index[ $name ]['slug'];
					$province_id = isset( $province_ids[ $prov_slug ] ) ? (int) $province_ids[ $prov_slug ] : 0;

					if ( $province_id <= 0 ) {
						$prov_term = get_term_by( 'slug', $prov_slug, 'province' );
						$province_id = $prov_term instanceof WP_Term ? (int) $prov_term->term_id : 0;
					}

					$is_major = in_array( $name, $data['major_cities'], true );
					if ( hj_iran_locations_set_city_meta( $term_id, $province_id, $is_major ) ) {
						$report['cities_meta_fixed']++;
					}
				}
			}
		}

		// --- ۳) هم‌راست‌سازی متای همهٔ شهرهای کانونیکال ---
		foreach ( $index as $city_name => $info ) {
			$province_id = 0;

			if ( isset( $province_ids[ $info['slug'] ] ) ) {
				$province_id = (int) $province_ids[ $info['slug'] ];
			}

			if ( $province_id <= 0 ) {
				continue;
			}

			$slug = hj_iran_slugify( $city_name, 'city' );
			$term = get_term_by( 'slug', $slug, 'city' );

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$is_major = in_array( $city_name, $data['major_cities'], true );

			if ( hj_iran_locations_set_city_meta( (int) $term->term_id, $province_id, $is_major ) ) {
				$report['cities_meta_fixed']++;
			}
		}

		// --- ۴) تخصص‌های مخدوش ---
		$specs = get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		if ( ! is_wp_error( $specs ) ) {
			foreach ( $specs as $spec ) {
				if ( ! hj_iran_locations_is_corrupt_name( $spec->name ) ) {
					continue;
				}

				if ( 0 === (int) $spec->count ) {
					$deleted = wp_delete_term( (int) $spec->term_id, 'specialty' );
					if ( ! is_wp_error( $deleted ) && $deleted ) {
						$report['specs_deleted']++;
					}
				} else {
					$report['left_for_review'][] = $spec->name . ' (specialty ' . (int) $spec->term_id . ')';
				}
			}
		}

		update_option( 'hj_iran_locations_repaired_v2', 1, false );
		update_option( 'hj_iran_locations_last_repair', array( 'time' => time(), 'report' => $report ), false );

		return $report;
	}
}

if ( ! function_exists( 'hj_iran_locations_auto_repair' ) ) {
	/**
	 * تابع قدیمی اصلاح خودکار؛ دیگر به admin_init متصل نیست.
	 * برای سازگاری باقی مانده و همچنان می‌تواند مستقیماً فراخوانی شود.
	 */
	function hj_iran_locations_auto_repair() {
		if ( get_option( 'hj_iran_locations_repaired_v2' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'hj_iran_locations_seeded_v1' ) ) {
			return; // اول باید سیدینگ انجام شود.
		}

		$report = hj_iran_locations_repair_terms();

		set_transient(
			'hj_iran_locations_notice',
			sprintf(
				'اصلاح ترم‌ها: %d استان تغییر نام، %d شهر اصلاح، %d شهر ادغام، %d ترم مخدوش حذف شد.',
				(int) $report['provinces_renamed'],
				(int) $report['cities_renamed'],
				(int) $report['cities_merged'],
				(int) $report['cities_deleted'] + (int) $report['specs_deleted']
			),
			300
		);
	}
	// Repair remains an explicit administrator action in the tools screen.
	// Do not run it implicitly when an administrator opens wp-admin.
}

/* -------------------------------------------------------------------------
 * ۶. تابع قدیمی اجرای خودکار (بدون قلاب) + اعلان ادمین
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'hj_iran_locations_auto_seed' ) ) {
	/**
	 * تابع قدیمی اجرای خودکار سیدینگ؛ دیگر به admin_init متصل نیست.
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
	// Do not register automatic seeding on ordinary admin requests.
	// The existing explicit administrator tools remain available.
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

		$message       = '';
		$result        = null;
		$repair_report = null;

		if ( isset( $_POST['hj_iran_repair_nonce'] ) ) {
			$repair_nonce = sanitize_text_field( wp_unslash( $_POST['hj_iran_repair_nonce'] ) );

			if ( wp_verify_nonce( $repair_nonce, 'hj_iran_repair' ) ) {
				$repair_report = hj_iran_locations_repair_terms();
			}
		}

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

		// --- اصلاح ترم‌های مخدوش ---
		echo '<hr />';
		echo '<h2>' . esc_html__( 'اصلاح ترم‌های مخدوش', 'hamyar-doctors' ) . '</h2>';
		echo '<p>' . esc_html__( 'اگر نسخهٔ ۰٫۳٫۰ روی همین سایت اجرا شده باشد، ممکن است ترم‌هایی با نام‌های مخدوش (حروف لاتین یا خطوط دیگر داخل نام) ساخته شده باشند. این ابزار آن‌ها را به نام درست تغییر می‌دهد یا در ترم صحیح ادغام می‌کند و متای استان والد را بازنویسی می‌کند. هیچ ترمی که نوشته دارد بدون انتقال نوشته‌ها حذف نمی‌شود.', 'hamyar-doctors' ) . '</p>';

		if ( is_array( $repair_report ) ) {
			$summary = sprintf(
				'%d استان تغییر نام، %d شهر اصلاح، %d شهر ادغام، %d شهر حذف، %d تخصص حذف، %d نوشته منتقل، %d متا اصلاح شد.',
				(int) $repair_report['provinces_renamed'],
				(int) $repair_report['cities_renamed'],
				(int) $repair_report['cities_merged'],
				(int) $repair_report['cities_deleted'],
				(int) $repair_report['specs_deleted'],
				(int) $repair_report['posts_moved'],
				(int) $repair_report['cities_meta_fixed']
			);

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $summary ) . '</p></div>';

			if ( ! empty( $repair_report['left_for_review'] ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'این ترم‌ها نوشته دارند و معادل مطمئنی برایشان پیدا نشد؛ دستی بررسی کنید:', 'hamyar-doctors' ) . '</p><ul style="list-style:disc;margin-right:20px;">';
				foreach ( $repair_report['left_for_review'] as $left_item ) {
					echo '<li>' . esc_html( (string) $left_item ) . '</li>';
				}
				echo '</ul></div>';
			}
		}

		$last_repair = get_option( 'hj_iran_locations_last_repair', array() );
		if ( is_array( $last_repair ) && ! empty( $last_repair['time'] ) ) {
			echo '<p><strong>' . esc_html__( 'آخرین اصلاح:', 'hamyar-doctors' ) . '</strong> '
				. esc_html( date_i18n( 'Y/m/d H:i', (int) $last_repair['time'] ) ) . '</p>';
		}

		echo '<form method="post">';
		wp_nonce_field( 'hj_iran_repair', 'hj_iran_repair_nonce' );
		echo '<button type="submit" class="button button-secondary" onclick="return confirm('
			. esc_attr( wp_json_encode( __( 'ترم‌های مخدوش اصلاح شوند؟', 'hamyar-doctors' ) ) )
			. ');">' . esc_html__( 'اصلاح ترم‌های مخدوش', 'hamyar-doctors' ) . '</button>';
		echo '</form>';

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

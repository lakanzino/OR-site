<?php
/**
 * دادهٔ نمونه: پنج جراح آزمایشی (۲ VIP، ۱ ستاره‌دار، ۲ عادی).
 *
 * این ماژول برای آزمون ناوبر تکنولوژیک، سقف جایگاه و قالب پروفایل ساخته شده
 * و به‌صورت یک‌باره برای مدیر اجرا می‌شود. هیچ دادهٔ موجودی را بازنویسی
 * نمی‌کند: هر رکورد با متای _hj_demo_doctor علامت‌گذاری و با کلید یکتا
 * شناسایی می‌شود، پس اجرای دوباره رکورد تکراری نمی‌سازد.
 *
 * حذف کامل: ابزارها → سیدینگ ایران → دکمهٔ «حذف دادهٔ نمونه».
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * شناسهٔ نسخهٔ دادهٔ نمونه؛ با تغییر آن سیدینگ دوباره اجرا می‌شود.
 */
define( 'HJ_DEMO_DOCTORS_VERSION', '1' );

if ( ! function_exists( 'hj_demo_doctors_data' ) ) {
	/**
	 * تعریف پنج جراح نمونه با اطلاعات کامل.
	 *
	 * @return array[]
	 */
	function hj_demo_doctors_data() {
		return array(
			array(
				'key'              => 'demo-alireza-mohammadi',
				'title'            => 'دکتر علیرضا محمدی',
				'name_fa'          => 'دکتر علیرضا محمدی',
				'name_en'          => 'Dr. Alireza Mohammadi',
				'specialty_fa'     => 'جراح قلب و عروق',
				'specialty_en'     => 'Cardiovascular Surgeon',
				'tier'             => 'vip',
				'badge'            => 'جراح ممتاز قلب و عروق',
				'license'          => '۱۲۳۴۵۶',
				'experience'       => '۱۸',
				'surgeries_stat'   => '۲۴۰۰',
				'rating_avg'       => 4.8,
				'rating_count'     => 126,
				'province'         => array( 'تهران', 'tehran' ),
				'city'             => array( 'تهران', 'tehran' ),
				'type'             => array( 'جراحی قلب و عروق', 'jarahi-qalb-va-oruq' ),
				'specialty'        => array( 'جراحی قلب باز', 'jarahi-qalb-baz' ),
				'phone_clinic'     => '۰۲۱-۸۸۷۷۶۶۵۵',
				'phone_mobile'     => '۰۹۱۲۱۲۳۴۵۶۷',
				'address_clinic'   => 'تهران، خیابان ولیعصر، بالاتر از پارک ساعی، ساختمان پزشکان ساعی، طبقهٔ ۵',
				'address_hospital' => 'بیمارستان قلب شهید رجایی، بخش جراحی قلب باز',
				'map_link'         => 'https://maps.google.com/?q=Tehran+Valiasr',
				'booking_url'      => 'https://wa.me/989121234567',
				'expires_days'     => 240,
				'bio'              => 'متخصص جراحی قلب باز و عروق کرونر با بیش از ۱۸ سال سابقهٔ جراحی در بیمارستان‌های دانشگاهی تهران. تمرکز ویژه بر عمل بای‌پس عروق کرونر و ترمیم دریچهٔ قلب با روش‌های کم‌تهاجمی.',
				'education'        => "دکترای پزشکی — دانشگاه علوم پزشکی تهران\nتخصص جراحی عمومی — دانشگاه علوم پزشکی شهید بهشتی\nفوق تخصص جراحی قلب و عروق — دانشگاه علوم پزشکی ایران\nبورد تخصصی جراحی قلب و عروق",
				'surgeries'        => "بای‌پس عروق کرونر (CABG)\nتعویض و ترمیم دریچهٔ قلب\nجراحی آئورت صعودی\nجراحی قلب کم‌تهاجمی",
				'reviews'          => array(
					array( 5, 'عمل بای‌پس پدرم را ایشان انجام دادند؛ برخورد بسیار محترمانه و توضیحات کامل قبل از جراحی. خدا خیرشان بدهد.' ),
					array( 5, 'پس از جراحی دریچه، روند بهبودی بسیار خوب بود. پیگیری بعد از عمل واقعاً دقیق است.' ),
					array( 4, 'پزشک دلسوز و باتجربه‌ای هستند. فقط زمان انتظار در مطب کمی طولانی بود.' ),
				),
			),
			array(
				'key'              => 'demo-sara-hosseini',
				'title'            => 'دکتر سارا حسینی',
				'name_fa'          => 'دکتر سارا حسینی',
				'name_en'          => 'Dr. Sara Hosseini',
				'specialty_fa'     => 'جراح مغز و اعصاب',
				'specialty_en'     => 'Neurosurgeon',
				'tier'             => 'vip',
				'badge'            => 'جراح برجستهٔ ستون فقرات',
				'license'          => '۲۳۴۵۶۷',
				'experience'       => '۱۴',
				'surgeries_stat'   => '۱۸۵۰',
				'rating_avg'       => 4.9,
				'rating_count'     => 98,
				'province'         => array( 'خراسان رضوی', 'razavi-khorasan' ),
				'city'             => array( 'مشهد', 'mashhad' ),
				'type'             => array( 'جراحی مغز و اعصاب', 'jarahi-maghz-va-asab' ),
				'specialty'        => array( 'جراحی ستون فقرات', 'jarahi-sotoon-feqarat' ),
				'phone_clinic'     => '۰۵۱-۳۷۶۶۵۵۴۴',
				'phone_mobile'     => '۰۹۱۵۲۲۳۳۴۴۵',
				'address_clinic'   => 'مشهد، بلوار وکیل‌آباد، خیابان هنرستان، ساختمان پزشکان رضوان، طبقهٔ ۳',
				'address_hospital' => 'بیمارستان قائم (عج)، بخش جراحی اعصاب',
				'map_link'         => 'https://maps.google.com/?q=Mashhad+Vakilabad',
				'booking_url'      => 'https://wa.me/989152233445',
				'expires_days'     => 300,
				'bio'              => 'فوق تخصص جراحی ستون فقرات و جراحی مغز با تمرکز بر دیسک کمر و گردن به روش میکروسکوپی. از سال ۱۳۹۰ در بیمارستان قائم مشهد مشغول به فعالیت بالینی و آموزشی است.',
				'education'        => "دکترای پزشکی — دانشگاه علوم پزشکی مشهد\nتخصص جراحی مغز و اعصاب — دانشگاه علوم پزشکی تهران\nفلوشیپ جراحی ستون فقرات — دانشگاه علوم پزشکی ایران",
				'surgeries'        => "میکرودیسککتومی کمر و گردن\nفیوژن ستون فقرات\nجراحی تومور مغزی\nجراحی قاعدهٔ جمجمه",
				'reviews'          => array(
					array( 5, 'عمل دیسک کمر همسرم را انجام دادند؛ بعد از دو هفته بدون درد راه می‌رود. بسیار مسلط و آرام.' ),
					array( 5, 'توضیح دادن قبل از جراحی و پاسخ دادن به همهٔ سؤال‌ها باعث شد استرس‌مان کم شود.' ),
					array( 5, ' پیگیری تلفنی بعد از عمل خیلی خوب بود. یکی از بهترین جراحان مشهد.' ),
				),
			),
			array(
				'key'              => 'demo-mohammad-kazemi',
				'title'            => 'دکتر محمد کاظمی',
				'name_fa'          => 'دکتر محمد کاظمی',
				'name_en'          => 'Dr. Mohammad Kazemi',
				'specialty_fa'     => 'جراح استخوان و مفاصل',
				'specialty_en'     => 'Orthopedic Surgeon',
				'tier'             => 'starred',
				'badge'            => 'جراح زانو و آرتروسکوپی',
				'license'          => '۳۴۵۶۷۸',
				'experience'       => '۱۱',
				'surgeries_stat'   => '۱۳۲۰',
				'rating_avg'       => 4.6,
				'rating_count'     => 74,
				'province'         => array( 'اصفهان', 'isfahan' ),
				'city'             => array( 'اصفهان', 'isfahan' ),
				'type'             => array( 'جراحی استخوان و مفاصل', 'jarahi-ostokhan-va-mafasel' ),
				'specialty'        => array( 'جراحی زانو', 'jarahi-zanoo' ),
				'phone_clinic'     => '۰۳۱-۳۶۲۶۵۵۴۴',
				'phone_mobile'     => '۰۹۱۳۳۳۴۴۵۵۶',
				'address_clinic'   => 'اصفهان، خیابان شمس آبادی، ساختمان پزشکان نور، طبقهٔ ۲',
				'address_hospital' => 'بیمارستان الزهرا، بخش ارتوپدی',
				'map_link'         => 'https://maps.google.com/?q=Isfahan+ShamsAbadi',
				'booking_url'      => 'https://wa.me/989133344556',
				'expires_days'     => 180,
				'bio'              => 'جراح ارتوپدی با تمرکز بر آرتروسکوپی زانو، بازسازی رباط صلیبی و تعویض مفصل زانو. همکاری مستمر با تیم پزشکی ورزشی استان اصفهان.',
				'education'        => "دکترای پزشکی — دانشگاه علوم پزشکی اصفهان\nتخصص جراحی استخوان و مفاصل — دانشگاه علوم پزشکی تهران\nدورهٔ تکمیلی آرتروسکوپی — آلمان",
				'surgeries'        => "آرتروسکوپی زانو\nبازسازی رباط صلیبی (ACL)\nتعویض مفصل زانو\nجراحی منیسک",
				'reviews'          => array(
					array( 5, 'پارگی رباط صلیبی‌ام را جراحی کردند؛ بعد از فیزیوتراپی به فوتبال برگشتم.' ),
					array( 4, 'بسیار دقیق و منظم. مطب شلوغ است ولی ارزشش را دارد.' ),
				),
			),
			array(
				'key'              => 'demo-negar-rezaei',
				'title'            => 'دکتر نگار رضایی',
				'name_fa'          => 'دکتر نگار رضایی',
				'name_en'          => 'Dr. Negar Rezaei',
				'specialty_fa'     => 'جراح زیبایی و ترمیمی',
				'specialty_en'     => 'Plastic Surgeon',
				'tier'             => 'basic',
				'badge'            => '',
				'license'          => '۴۵۶۷۸۹',
				'experience'       => '۹',
				'surgeries_stat'   => '۹۸۰',
				'rating_avg'       => 4.4,
				'rating_count'     => 41,
				'province'         => array( 'فارس', 'fars' ),
				'city'             => array( 'شیراز', 'shiraz' ),
				'type'             => array( 'جراحی زیبایی و ترمیمی', 'jarahi-zibaee-va-tarmimi' ),
				'specialty'        => array( 'جراحی بینی', 'jarahi-bini' ),
				'phone_clinic'     => '۰۷۱-۳۲۳۳۴۴۵۵',
				'phone_mobile'     => '۰۹۱۷۴۴۵۵۶۶۷',
				'address_clinic'   => 'شیراز، بلوار زند، ساختمان پزشکان زند، طبقهٔ ۴',
				'address_hospital' => 'بیمارستان نمازی، بخش جراحی ترمیمی',
				'map_link'         => 'https://maps.google.com/?q=Shiraz+Zand',
				'booking_url'      => '',
				'expires_days'     => 0,
				'bio'              => 'جراح زیبایی و ترمیمی با تمرکز بر رینوپلاستی و جراحی ترمیمی سوختگی. رویکرد محافظه‌کارانه و تأکید بر نتیجهٔ طبیعی.',
				'education'        => "دکترای پزشکی — دانشگاه علوم پزشکی شیراز\nتخصص جراحی زیبایی و ترمیمی — دانشگاه علوم پزشکی تهران",
				'surgeries'        => "رینوپلاستی\nجراحی پلک\nلیفت صورت\nترمیم سوختگی",
				'reviews'          => array(
					array( 5, 'عمل بینی من طبیعی و بدون اغراق شد؛ دقیقاً همان چیزی که خواسته بودم.' ),
					array( 4, 'برخورد خوب و صادقانه. مشاورهٔ اولیه کامل بود.' ),
				),
			),
			array(
				'key'              => 'demo-omid-karimi',
				'title'            => 'دکتر امید کریمی',
				'name_fa'          => 'دکتر امید کریمی',
				'name_en'          => 'Dr. Omid Karimi',
				'specialty_fa'     => 'جراح عمومی',
				'specialty_en'     => 'General Surgeon',
				'tier'             => 'basic',
				'badge'            => '',
				'license'          => '۵۶۷۸۹۰',
				'experience'       => '۶',
				'surgeries_stat'   => '۶۴۰',
				'rating_avg'       => 4.2,
				'rating_count'     => 23,
				'province'         => array( 'آذربایجان شرقی', 'east-azerbaijan' ),
				'city'             => array( 'تبریز', 'tabriz' ),
				'type'             => array( 'جراحی عمومی', 'jarahi-omomi' ),
				'specialty'        => array( 'جراحی گوارش', 'jarahi-govaresh' ),
				'phone_clinic'     => '۰۴۱-۳۳۴۴۵۵۶۶',
				'phone_mobile'     => '۰۹۱۴۵۵۶۶۷۷۸',
				'address_clinic'   => 'تبریز، خیابان آبرسان، ساختمان پزشکان آبرسان، طبقهٔ ۲',
				'address_hospital' => 'بیمارستان امام رضا، بخش جراحی عمومی',
				'map_link'         => 'https://maps.google.com/?q=Tabriz+Abrasan',
				'booking_url'      => '',
				'expires_days'     => 0,
				'bio'              => 'جراح عمومی با تمرکز بر جراحی‌های گوارشی و لاپاراسکوپی. ارائهٔ خدمات جراحی در بیمارستان امام رضا تبریز.',
				'education'        => "دکترای پزشکی — دانشگاه علوم پزشکی تبریز\nتخصص جراحی عمومی — دانشگاه علوم پزشکی تبریز",
				'surgeries'        => "لاپاراسکوپی کیسهٔ صفرا\nجراحی فتق\nآپاندکتومی\nجراحی هموروئید",
				'reviews'          => array(
					array( 4, 'عمل کیسهٔ صفرا با لاپاراسکوپی انجام شد و دو روزه مرخص شدم.' ),
					array( 4, 'پزشک خوش‌برخورد و وقت‌شناسی بودند.' ),
				),
			),
		);
	}
}

if ( ! function_exists( 'hj_demo_ensure_term_id' ) ) {
	/**
	 * یافتن یا ساختن ترم لازم برای رکورد نمونه.
	 *
	 * @param string $name     نام ترم.
	 * @param string $slug     اسلاگ لاتین.
	 * @param string $taxonomy تاکسونومی.
	 * @return int
	 */
	function hj_demo_ensure_term_id( $name, $slug, $taxonomy ) {
		if ( function_exists( 'hj_iran_ensure_term' ) ) {
			$term = hj_iran_ensure_term( $name, $taxonomy, $slug );
			return isset( $term['term_id'] ) ? (int) $term['term_id'] : 0;
		}

		$existing = term_exists( $slug, $taxonomy );
		if ( $existing ) {
			return is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
		}

		$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );

		return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
	}
}

if ( ! function_exists( 'hj_demo_insert_review' ) ) {
	/**
	 * درج یک نظر تأییدشده در جدول hj_doctor_votes.
	 *
	 * ستون‌ها با SHOW COLUMNS تشخیص داده می‌شوند و اگر جدول یا ستون‌های
	 * لازم نبودند، بی‌صدا رد می‌شود (هیچ خطای SQL روی سایت نمی‌نشیند).
	 *
	 * @param int    $doctor_id شناسهٔ پزشک.
	 * @param int    $rating    امتیاز ۱ تا ۵.
	 * @param string $comment   متن نظر.
	 * @return bool
	 */
	function hj_demo_insert_review( $doctor_id, $rating, $comment ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hj_doctor_votes';

		$prev   = $wpdb->suppress_errors( true );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			$wpdb->suppress_errors( $prev );
			return false;
		}

		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$wpdb->suppress_errors( $prev );

		if ( ! is_array( $columns ) || empty( $columns ) ) {
			return false;
		}

		$types = array();
		foreach ( $columns as $column ) {
			if ( isset( $column['Field'] ) ) {
				$types[ (string) $column['Field'] ] = isset( $column['Type'] ) ? strtolower( (string) $column['Type'] ) : '';
			}
		}

		if ( ! isset( $types['doctor_id'] ) || ! isset( $types['rating'] ) ) {
			return false;
		}

		$data   = array(
			'doctor_id' => (int) $doctor_id,
			'rating'    => (int) $rating,
		);
		$format = array( '%d', '%d' );

		if ( isset( $types['comment'] ) ) {
			$data['comment'] = (string) $comment;
			$format[]        = '%s';
		}

		if ( isset( $types['status'] ) ) {
			// ستون وضعیت بسته به نصب، متنی یا عددی است.
			$data['status'] = ( false !== strpos( $types['status'], 'int' ) ) ? 1 : 'approved';
			$format[]       = ( false !== strpos( $types['status'], 'int' ) ) ? '%d' : '%s';
		}

		if ( isset( $types['created_at'] ) ) {
			$is_timestamp    = ( false !== strpos( $types['created_at'], 'int' ) );
			$data['created_at'] = $is_timestamp ? time() : current_time( 'mysql' );
			$format[]           = $is_timestamp ? '%d' : '%s';
		}

		$prev = $wpdb->suppress_errors( true );
		$ok   = $wpdb->insert( $table, $data, $format );
		$wpdb->suppress_errors( $prev );

		return false !== $ok;
	}
}

if ( ! function_exists( 'hj_demo_doctors_seed' ) ) {
	/**
	 * ساخت پنج جراح نمونه.
	 *
	 * @return array{ok:bool,created:int,skipped:int,message:string}
	 */
	function hj_demo_doctors_seed() {
		$created = 0;
		$skipped = 0;

		foreach ( hj_demo_doctors_data() as $doctor ) {
			// اگر قبلاً ساخته شده، دست نمی‌زنیم.
			$existing = get_posts(
				array(
					'post_type'        => HJ_DOCTORS_CPT,
					'post_status'      => 'any',
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'meta_key'         => '_hj_demo_doctor',
					'meta_value'       => $doctor['key'],
					'suppress_filters' => true,
				)
			);

			if ( ! empty( $existing ) ) {
				$skipped++;
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => HJ_DOCTORS_CPT,
					'post_status'  => 'publish',
					'post_title'   => $doctor['title'],
					'post_content' => $doctor['bio'],
					'post_excerpt' => mb_substr( $doctor['bio'], 0, 160 ),
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			$post_id = (int) $post_id;

			$meta = array(
				'name_fa'            => $doctor['name_fa'],
				'name_en'            => $doctor['name_en'],
				'specialty_fa'       => $doctor['specialty_fa'],
				'specialty_en'       => $doctor['specialty_en'],
				'bio_fa'             => $doctor['bio'],
				'medical_license'    => $doctor['license'],
				'experience_years'   => $doctor['experience'],
				'education'          => $doctor['education'],
				'surgeries_performed' => $doctor['surgeries'],
				'phone_clinic'       => $doctor['phone_clinic'],
				'phone_mobile'       => $doctor['phone_mobile'],
				'address_clinic'     => $doctor['address_clinic'],
				'address_hospital'   => $doctor['address_hospital'],
				'map_link'           => $doctor['map_link'],
				// آمار نمایشی.
				'surgeries_count'    => $doctor['surgeries_stat'],
				'hj_rating_avg'      => $doctor['rating_avg'],
				'hj_rating_count'    => $doctor['rating_count'],
				// وضعیت انتشار برای ناوبر.
				'hj_profile_status'  => 'published',
				// لایهٔ تجاری.
				'hj_vip_tier'        => $doctor['tier'],
				'hj_priority_score'  => ( 'vip' === $doctor['tier'] ) ? 100 : ( ( 'starred' === $doctor['tier'] ) ? 50 : 10 ),
				'hj_custom_badge'    => $doctor['badge'],
				'hj_booking_url'     => $doctor['booking_url'],
				// برچسب دادهٔ نمونه برای حذف آسان.
				'_hj_demo_doctor'    => $doctor['key'],
			);

			if ( $doctor['expires_days'] > 0 ) {
				$meta['hj_vip_expires_at'] = gmdate( 'Y-m-d', time() + ( (int) $doctor['expires_days'] * DAY_IN_SECONDS ) );
			}

			foreach ( $meta as $meta_key => $meta_value ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}

			// --- تاکسونومی‌ها ---
			$province_id = hj_demo_ensure_term_id( $doctor['province'][0], $doctor['province'][1], 'province' );
			$city_id     = hj_demo_ensure_term_id( $doctor['city'][0], $doctor['city'][1], 'city' );
			$type_id     = hj_demo_ensure_term_id( $doctor['type'][0], $doctor['type'][1], 'specialty_type' );
			$spec_id     = hj_demo_ensure_term_id( $doctor['specialty'][0], $doctor['specialty'][1], 'specialty' );

			if ( $city_id > 0 && $province_id > 0 ) {
				update_term_meta( $city_id, 'hj_province_id', $province_id );
				update_term_meta( $city_id, 'hj_parent_province_id', $province_id );
			}

			if ( $spec_id > 0 && $type_id > 0 ) {
				update_term_meta( $spec_id, 'hj_specialty_type_id', $type_id );
			}

			wp_set_object_terms( $post_id, array( $province_id ), 'province' );
			wp_set_object_terms( $post_id, array( $city_id ), 'city' );
			wp_set_object_terms( $post_id, array( $type_id ), 'specialty_type' );
			wp_set_object_terms( $post_id, array( $spec_id ), 'specialty' );

			// --- نظرات بیماران ---
			foreach ( $doctor['reviews'] as $review ) {
				hj_demo_insert_review( $post_id, (int) $review[0], (string) $review[1] );
			}

			$created++;
		}

		update_option( 'hj_demo_doctors_seeded_v1', HJ_DEMO_DOCTORS_VERSION, false );

		return array(
			'ok'      => true,
			'created' => $created,
			'skipped' => $skipped,
			'message' => sprintf(
				'دادهٔ نمونه: %d پزشک ساخته شد، %d مورد از قبل موجود بود.',
				$created,
				$skipped
			),
		);
	}
}

if ( ! function_exists( 'hj_demo_doctors_delete' ) ) {
	/**
	 * حذف کامل پنج جراح نمونه (فقط رکوردهای علامت‌گذاری‌شده).
	 *
	 * @return int تعداد حذف‌شده.
	 */
	function hj_demo_doctors_delete() {
		$ids = get_posts(
			array(
				'post_type'        => HJ_DOCTORS_CPT,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_hj_demo_doctor',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				$deleted++;
			}
		}

		delete_option( 'hj_demo_doctors_seeded_v1' );

		return $deleted;
	}
}

if ( ! function_exists( 'hj_demo_doctors_auto_seed' ) ) {
	/**
	 * تابع قدیمی اجرای خودکار؛ دیگر به admin_init متصل نیست.
	 * خود تابع برای سازگاری باقی مانده و همچنان می‌تواند مستقیماً فراخوانی شود.
	 */
	function hj_demo_doctors_auto_seed() {
		if ( HJ_DEMO_DOCTORS_VERSION === (string) get_option( 'hj_demo_doctors_seeded_v1', '' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result = hj_demo_doctors_seed();

		set_transient( 'hj_demo_doctors_notice', (string) $result['message'], 300 );
	}
	// Do not register this automatic callback on ordinary admin requests.
	// The existing explicit administrator action remains available.
}

if ( ! function_exists( 'hj_demo_doctors_admin_notice' ) ) {
	/**
	 * اعلان نتیجهٔ ساخت دادهٔ نمونه.
	 */
	function hj_demo_doctors_admin_notice() {
		$message = get_transient( 'hj_demo_doctors_notice' );

		if ( ! $message || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		delete_transient( 'hj_demo_doctors_notice' );

		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( (string) $message ) . '</p></div>';
	}
	add_action( 'admin_notices', 'hj_demo_doctors_admin_notice' );
}

if ( ! function_exists( 'hj_demo_doctors_handle_action' ) ) {
	/**
	 * پردازش دکمه‌های ساخت/حذف دادهٔ نمونه از صفحهٔ ابزار.
	 */
	function hj_demo_doctors_handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'hamyar-doctors' ) );
		}

		$action = isset( $_POST['hj_demo_action'] ) ? sanitize_key( wp_unslash( $_POST['hj_demo_action'] ) ) : '';
		check_admin_referer( 'hj_demo_doctors_action' );

		$redirect = add_query_arg(
			array( 'page' => 'hj-iran-locations' ),
			admin_url( 'tools.php' )
		);

		if ( 'seed' === $action ) {
			$result = hj_demo_doctors_seed();
			$redirect = add_query_arg( 'hj_demo_done', rawurlencode( $result['message'] ), $redirect );
		} elseif ( 'delete' === $action ) {
			$deleted  = hj_demo_doctors_delete();
			$redirect = add_query_arg(
				'hj_demo_done',
				rawurlencode( sprintf( '%d پزشک نمونه حذف شد.', $deleted ) ),
				$redirect
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}
	add_action( 'admin_post_hj_demo_doctors_action', 'hj_demo_doctors_handle_action' );
}

if ( ! function_exists( 'hj_demo_doctors_tools_section' ) ) {
	/**
	 * بخش دادهٔ نمونه در صفحهٔ ابزار سیدینگ ایران.
	 *
	 * @param bool $seeded وضعیت سیدینگ استان‌ها.
	 */
	function hj_demo_doctors_tools_section( $seeded = false ) {
		unset( $seeded );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$done = isset( $_GET['hj_demo_done'] ) ? sanitize_text_field( wp_unslash( $_GET['hj_demo_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<hr />';
		echo '<h2>' . esc_html__( 'دادهٔ نمونه (۵ جراح)', 'hamyar-doctors' ) . '</h2>';

		if ( '' !== $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $done ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'این ابزار به‌صورت دستی پنج پروفایل نمونه و نظرهای نمونه ایجاد می‌کند. حذف، پست‌های علامت‌گذاری‌شده را برای همیشه پاک می‌کند؛ پاک‌شدن رأی‌های مرتبط یا ترم‌ها تضمین نمی‌شود. پیش از هر اقدام، نسخهٔ پشتیبان تهیه و اثر آن را بررسی کنید.', 'hamyar-doctors' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-left:8px;">';
		echo '<input type="hidden" name="action" value="hj_demo_doctors_action" />';
		echo '<input type="hidden" name="hj_demo_action" value="seed" />';
		wp_nonce_field( 'hj_demo_doctors_action' );
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'ساخت / تکمیل دادهٔ نمونه', 'hamyar-doctors' ) . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;" onsubmit="return confirm('
			. esc_attr( wp_json_encode( __( 'همهٔ پزشکان نمونه حذف شوند؟', 'hamyar-doctors' ) ) )
			. ');">';
		echo '<input type="hidden" name="action" value="hj_demo_doctors_action" />';
		echo '<input type="hidden" name="hj_demo_action" value="delete" />';
		wp_nonce_field( 'hj_demo_doctors_action' );
		echo '<button type="submit" class="button button-link-delete">' . esc_html__( 'حذف دادهٔ نمونه', 'hamyar-doctors' ) . '</button>';
		echo '</form>';
	}
	add_action( 'hj_iran_tools_extra', 'hj_demo_doctors_tools_section' );
}

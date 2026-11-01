<?php
/**
 * بخش ۲ (فایل ۱): taxonomyهای «تخصص» و «شهر» برای نوع محتوای پزشک.
 *
 * کلیدها با پیشوند doctor_ هستند تا با taxonomyهای قالب فرزند
 * (guide_stage، disease_group، glossary_group و…) برخورد نکنند.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فهرست taxonomyهای افزونه؛ تنها منبع حقیقت.
 *
 * @return array[]
 */
function hj_doctors_taxonomy_map() {
	return array(
		'doctor_specialty' => array(
			'singular' => 'تخصص',
			'plural'   => 'تخصص‌ها',
			'slug'     => 'doctor-specialty',
		),
		'doctor_city'      => array(
			'singular' => 'شهر',
			'plural'   => 'شهرها',
			'slug'     => 'doctor-city',
		),
	);
}

/**
 * برچسب‌های فارسی یک taxonomy.
 *
 * @param string $singular نام مفرد.
 * @param string $plural   نام جمع.
 * @return array
 */
function hj_doctors_taxonomy_labels( $singular, $plural ) {
	return array(
		'name'                       => $plural,
		'singular_name'              => $singular,
		'menu_name'                  => $plural,
		'search_items'               => 'جستجوی ' . $plural,
		'popular_items'              => $plural . ' پرکاربرد',
		'all_items'                  => 'همهٔ ' . $plural,
		'edit_item'                  => 'ویرایش ' . $singular,
		'update_item'                => 'به‌روزرسانی ' . $singular,
		'add_new_item'               => 'افزودن ' . $singular . ' تازه',
		'new_item_name'              => 'نام ' . $singular . ' تازه',
		'not_found'                  => 'موردی یافت نشد',
		'back_to_items'              => 'بازگشت به ' . $plural,
	);
}

/**
 * ثبت taxonomyها روی init.
 *
 * اگر کلیدی از قبل ثبت شده باشد (افزونهٔ دیگر یا نسخهٔ کهنهٔ همین
 * افزونه) بی‌صدا رد می‌شود تا هیچ notice وارد debug.log نشود.
 */
function hj_doctors_register_taxonomies() {
	foreach ( hj_doctors_taxonomy_map() as $key => $def ) {
		if ( taxonomy_exists( $key ) ) {
			continue;
		}

		register_taxonomy(
			$key,
			HJ_DOCTORS_CPT,
			array(
				'labels'            => hj_doctors_taxonomy_labels( $def['singular'], $def['plural'] ),
				'hierarchical'      => true, // زیردسته مثل «جراحی عمومی ← لاپاراسکوپی».
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'         => $def['slug'],
					'hierarchical' => true,
					'with_front'   => false,
				),
			)
		);
	}
}
add_action( 'init', 'hj_doctors_register_taxonomies' );

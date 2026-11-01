<?php
/**
 * بخش ۲ (فایل ۱): چهار taxonomy پزشک‌ها طبق طرح واقعی نشست قدیمی.
 *
 * specialty_type (نوع تخصص) ← specialty (رشته‌ها، با متای hj_specialty_type_id)
 * province (استان) ← city (شهر، با متای hj_province_id)
 *
 * داشبورد بخش ۶ و ابزار ورود دادهٔ بخش ۱۴ همین کلیدها را می‌خوانند؛
 * طرح اولیهٔ doctor_specialty/doctor_city با این یکی جایگزین شد.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فهرست taxonomyها؛ تنها منبع حقیقت.
 *
 * @return array[]
 */
function hj_doctors_taxonomy_map() {
	return array(
		'specialty_type' => array(
			'singular' => 'نوع تخصص',
			'plural'   => 'انواع تخصص',
			'slug'     => 'specialty-type',
		),
		'specialty'      => array(
			'singular' => 'رشته',
			'plural'   => 'رشته‌ها',
			'slug'     => 'specialty',
		),
		'province'       => array(
			'singular' => 'استان',
			'plural'   => 'استان‌ها',
			'slug'     => 'province',
		),
		'city'           => array(
			'singular' => 'شهر',
			'plural'   => 'شهرها',
			'slug'     => 'city',
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
		'name'          => $plural,
		'singular_name' => $singular,
		'menu_name'     => $plural,
		'search_items'  => 'جستجوی ' . $plural,
		'popular_items' => $plural . ' پرکاربرد',
		'all_items'     => 'همهٔ ' . $plural,
		'edit_item'     => 'ویرایش ' . $singular,
		'update_item'   => 'به‌روزرسانی ' . $singular,
		'add_new_item'  => 'افزودن ' . $singular . ' تازه',
		'new_item_name' => 'نام ' . $singular . ' تازه',
		'not_found'     => 'موردی یافت نشد',
		'back_to_items' => 'بازگشت به ' . $plural,
	);
}

/**
 * ثبت taxonomyها روی init.
 *
 * اگر کلیدی از قبل ثبت شده باشد بی‌صدا رد می‌شود تا هیچ notice وارد
 * debug.log نشود.
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
				'hierarchical'      => true,
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

/**
 * متای ارتباطی ترم‌ها: رشته→نوع تخصص و شهر→استان.
 */
function hj_doctors_register_term_meta() {
	register_meta(
		'term',
		'hj_specialty_type_id',
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
	register_meta(
		'term',
		'hj_province_id',
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
add_action( 'init', 'hj_doctors_register_term_meta' );

if ( ! function_exists( 'hj_get_city_province_id' ) ) {
	/**
	 * شناسهٔ ترم استانی که شهر به آن وصل است (متای hj_province_id).
	 *
	 * @param int $city_id شناسهٔ ترم شهر.
	 * @return int
	 */
	function hj_get_city_province_id( $city_id ) {
		return (int) get_term_meta( (int) $city_id, 'hj_province_id', true );
	}
}

if ( ! function_exists( 'hj_doctors_sync_province_from_city' ) ) {
	/**
	 * ترم استان نوشتهٔ پزشک را از متای شهرش همگام می‌کند.
	 *
	 * @param int $post_id شناسهٔ نوشتهٔ پزشک.
	 */
	function hj_doctors_sync_province_from_city( $post_id ) {
		$cities = wp_get_object_terms( (int) $post_id, 'city', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $cities ) || ! $cities ) {
			return;
		}
		$prov = hj_get_city_province_id( (int) $cities[0] );
		if ( $prov && ! is_wp_error( $prov ) ) {
			wp_set_object_terms( (int) $post_id, array( $prov ), 'province' );
		}
	}
}

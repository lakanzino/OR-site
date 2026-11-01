<?php
/**
 * بخش ۲ (فایل ۲): نوع محتوای «پزشک».
 *
 * map_meta_cap روشن است تا بخش ۴ (includes/roles.php) بتواند دسترسی نقش
 * پزشک را فقط به نمایهٔ خودش محدود کند؛ capability_type سفارشی عمداً
 * تعریف نمی‌شود تا هیچ cap اضافه‌ای در نقش‌های موجود نوشته نشود.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * برچسب‌های فارسی نوع محتوای پزشک.
 *
 * @return array
 */
function hj_doctors_post_type_labels() {
	return array(
		'name'               => 'پزشک‌ها',
		'singular_name'      => 'پزشک',
		'menu_name'          => 'پزشک‌ها',
		'add_new'            => 'افزودن پزشک',
		'add_new_item'       => 'افزودن پزشک تازه',
		'new_item'           => 'پزشک تازه',
		'edit_item'          => 'ویرایش پزشک',
		'view_item'          => 'دیدن پزشک',
		'view_items'         => 'دیدن پزشک‌ها',
		'search_items'       => 'جستجوی پزشک‌ها',
		'not_found'          => 'پزشکی یافت نشد',
		'not_found_in_trash' => 'پزشکی در زباله‌دان نیست',
		'all_items'          => 'همهٔ پزشک‌ها',
		'archives'           => 'بایگانی پزشک‌ها',
		'attributes'         => 'ویژگی‌های پزشک',
		'featured_image'     => 'تصویر پزشک',
		'set_featured_image' => 'انتخاب تصویر پزشک',
		'remove_featured_image' => 'حذف تصویر پزشک',
		'use_featured_image' => 'استفاده به‌عنوان تصویر پزشک',
	);
}

/**
 * ثبت نوع محتوای پزشک (بدون قلاب؛ برای فعال‌سازی هم صدا زده می‌شود).
 */
function hj_doctors_register_post_type() {
	if ( post_type_exists( HJ_DOCTORS_CPT ) ) {
		return;
	}

	register_post_type(
		HJ_DOCTORS_CPT,
		array(
			'labels'        => hj_doctors_post_type_labels(),
			'public'        => true,
			'has_archive'   => 'doctors',
			'rewrite'       => array( 'slug' => 'doctor', 'with_front' => false ),
			'show_in_rest'  => true, // ویرایشگر بلوکی + REST.
			'menu_icon'     => 'dashicons-admin-users',
			'menu_position' => 26,
			'taxonomies'    => array_keys( hj_doctors_taxonomy_map() ),
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
			// بخش ۴: دسترسی‌های hj_* و مالکیت نمایه در includes/roles.php مدیریت
			// می‌شود (map_meta_cap). capability_type سفارشی عمداً نیست تا چیزی
			// در نقش مدیر/سردبیر نوشته نشود و core caps همان‌ها کار کند.
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'hj_doctors_register_post_type' );

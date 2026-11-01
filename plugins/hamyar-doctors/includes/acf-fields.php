<?php
/**
 * بخش ۳ (فایل ۱): زمینه‌های نمایهٔ پزشک با ACF.
 *
 * خودِ گروه زمینه فقط به‌صورت JSON در acf-json/ زندگی می‌کند و ACF آن را
 * خودکار بارگذاری می‌کند؛ Sync دستی لازم نیست. این فایل فقط مسیر ذخیره/
 * بارگذاری JSON را به داخل افزونه نشانه می‌گیرد، روی ذخیره سانیتایز
 * می‌کند و چند getter بی‌خطر برای قالب می‌دهد. بدون ACF همه‌چیز بی‌صدا
 * غیرفعال می‌ماند و حتی یک خط به debug.log نوشته نمی‌شود.
 *
 * Rollback: حذف/rename همین فایل + پوشهٔ acf-json کافی است.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پوشهٔ JSON محلی ACF داخل افزونه.
 *
 * @return string
 */
function hj_doctors_acf_json_dir() {
	return hj_doctors_path( 'acf-json' );
}

/**
 * ذخیرهٔ خروجی Sync فقط داخل افزونه (نه قالب فرزند).
 *
 * @param string $path مسیر پیش‌فرض ACF.
 * @return string
 */
function hj_doctors_acf_save_json_path( $path ) {
	return hj_doctors_acf_json_dir();
}
add_filter( 'acf/settings/save_json', 'hj_doctors_acf_save_json_path' );

/**
 * افزودن پوشهٔ افزونه به مسیرهای بارگذاری JSON محلی.
 *
 * @param array $paths مسیرهای پیش‌فرض ACF.
 * @return array
 */
function hj_doctors_acf_load_json_paths( $paths ) {
	if ( ! is_array( $paths ) ) {
		$paths = array();
	}
	$paths[] = hj_doctors_acf_json_dir();

	return $paths;
}
add_filter( 'acf/settings/load_json', 'hj_doctors_acf_load_json_paths' );

/**
 * سانیتایز زمینه‌ها بعد از ذخیرهٔ ACF.
 *
 * ارقام فارسی/عربی کد نظام پزشکی و شماره تماس به لاتین تبدیل می‌شوند
 * (hj_to_en_digits) و نشانی وب با esc_url_raw نرمال می‌شود. فقط روی
 * نوشتهٔ نوع پزشک و فقط برای کاربری که واقعاً_cap ویرایش آن را دارد.
 *
 * @param int|string $post_id شناسهٔ نوشته یا 'options' و مشابه آن.
 */
function hj_doctors_sanitize_fields( $post_id ) {
	if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || HJ_DOCTORS_CPT !== $post->post_type ) {
		return; // گزینه‌ها، ترم‌ها و کاربرها اینجا کاری ندارند.
	}
	if ( ! current_user_can( 'edit_doctor', $post->ID ) ) {
		return;
	}

	$digit_fields = array( 'hj_medical_code', 'hj_phone' );
	foreach ( $digit_fields as $field ) {
		$raw = get_field( $field, $post->ID );
		if ( null === $raw || '' === $raw || is_array( $raw ) ) {
			continue;
		}
		$clean = trim( sanitize_text_field( hj_to_en_digits( wp_strip_all_tags( (string) $raw ) ) ) );
		if ( $clean !== (string) $raw ) {
			update_field( $field, $clean, $post->ID );
		}
	}

	$url = get_field( 'hj_profile_url', $post->ID );
	if ( is_string( $url ) && '' !== $url ) {
		$clean = esc_url_raw( trim( $url ) );
		if ( $clean !== $url ) {
			update_field( 'hj_profile_url', $clean, $post->ID );
		}
	}
}
add_action( 'acf/save_post', 'hj_doctors_sanitize_fields', 20 );

if ( ! function_exists( 'hj_doctors_field' ) ) {
	/**
	 * خواندن یک زمینهٔ نمایه بدون وابستگی سخت به ACF.
	 *
	 * @param string  $selector  نام زمینه مانند 'hj_phone'.
	 * @param int     $post_id   شناسهٔ نوشته؛ ۰ یعنی نوشتهٔ جاری.
	 * @param mixed   $default   مقدار پیش‌فرض وقتی زمینه خالی است.
	 * @return mixed
	 */
	function hj_doctors_field( $selector, $post_id = 0, $default = '' ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		if ( ! $post_id ) {
			return $default;
		}
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $selector, $post_id );
			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		return $default;
	}
}

if ( ! function_exists( 'hj_doctors_photo_url' ) ) {
	/**
	 * تصویر شاخص پزشک یا عکس پیش‌فرض بسته.
	 *
	 * @param int $post_id شناسهٔ نوشته؛ ۰ یعنی نوشته جاری.
	 * @return string
	 */
	function hj_doctors_photo_url( $post_id = 0 ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$url = get_the_post_thumbnail_url( $post_id, 'medium' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return hj_doctors_default_photo_url();
	}
}

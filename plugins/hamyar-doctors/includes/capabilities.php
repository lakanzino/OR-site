<?php
/**
 * بخش ۴ (فایل ۱): نقش «پزشک» و capabilityهای نوع محتوای پزشک.
 *
 * نصب نقش/capabilityها روی فعال‌سازی (و هنگام ارتقای نسخه) انجام می‌شود؛
 * حذف آن‌ها فقط با hj_doctors_uninstall_roles() است تا غیرفعال‌سازی ساده
 * هیچ دسترسی‌ای را از مدیران نگیرد.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * همهٔ capabilityهای نوع محتوای پزشک (capability_type سفارشی).
 *
 * @return string[]
 */
function hj_doctors_post_type_caps() {
	return array(
		'edit_doctor',
		'edit_doctors',
		'edit_others_doctors',
		'edit_published_doctors',
		'edit_private_doctors',
		'publish_doctors',
		'read_private_doctors',
		'delete_doctor',
		'delete_doctors',
		'delete_others_doctors',
		'delete_published_doctors',
		'delete_private_doctors',
	);
}

/**
 * نگاشت نقش‌های موجود به capabilityهای پزشک‌ها.
 *
 * مدیر سایت همه‌چیز را می‌گیرد؛ سردبیر می‌بیند و ویرایش می‌کند ولی
 * چیزی را برای همیشه حذف نمی‌کند (حذف دیگران ندارد).
 *
 * @return array[]
 */
function hj_doctors_role_caps_map() {
	return array(
		'administrator' => hj_doctors_post_type_caps(),
		'editor'        => array(
			'edit_doctors',
			'edit_others_doctors',
			'edit_published_doctors',
			'edit_private_doctors',
			'publish_doctors',
			'read_private_doctors',
			'delete_doctor',
			'delete_doctors',
			'delete_published_doctors',
		),
	);
}

/**
 * capabilityهای خودِ نقش پزشک.
 *
 * فقط نمایهٔ خودش: edit_others_doctors عمدی غایب است تا هستهٔ وردپرس
 * (و نگهبان security.php) ویرایش پزشک‌های دیگر را ببندد.
 *
 * @return array
 */
function hj_doctors_doctor_role_caps() {
	return array(
		'read'                 => true,
		'upload_files'         => true,
		'edit_doctor'          => true,
		'edit_doctors'         => true,
		'edit_published_doctors' => true,
		'delete_doctor'        => true,
		'delete_published_doctors' => true,
	);
}

/**
 * ساخت/همگام‌سازی نقش و capabilityها؛ بار دوم بی‌اثر است.
 */
function hj_doctors_install_roles() {
	if ( ! function_exists( 'get_role' ) ) {
		return; // بیرون از محیط وردپرس (مثلاً php-check) کاری نکن.
	}

	foreach ( hj_doctors_role_caps_map() as $role_key => $caps ) {
		$role = get_role( $role_key );
		if ( ! $role ) {
			continue;
		}
		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	if ( ! get_role( HJ_DOCTORS_ROLE ) ) {
		add_role( HJ_DOCTORS_ROLE, 'پزشک', array( 'read' => true ) );
	}

	$doctor = get_role( HJ_DOCTORS_ROLE );
	if ( $doctor ) {
		foreach ( hj_doctors_doctor_role_caps() as $cap => $grant ) {
			if ( $grant && ! $doctor->has_cap( $cap ) ) {
				$doctor->add_cap( $cap );
			}
		}
	}
}

/**
 * برداشتن نقش و capabilityها (برای پاک‌سازی دستی/حذف افزونه).
 */
function hj_doctors_uninstall_roles() {
	if ( ! function_exists( 'get_role' ) ) {
		return;
	}

	foreach ( hj_doctors_role_caps_map() as $role_key => $caps ) {
		$role = get_role( $role_key );
		if ( ! $role ) {
			continue;
		}
		foreach ( $caps as $cap ) {
			$role->remove_cap( $cap );
		}
	}

	remove_role( HJ_DOCTORS_ROLE );
}

if ( ! function_exists( 'hj_doctors_user_can' ) ) {
	/**
	 * بررسی دسترسی با cap سفارشی این افزونه.
	 *
	 * @param string $cap     نام capability مانند 'edit_doctor'.
	 * @param int    $post_id شناسهٔ نوشته برای capهای سطح نوشته.
	 * @return bool
	 */
	function hj_doctors_user_can( $cap, $post_id = 0 ) {
		if ( $post_id ) {
			return current_user_can( $cap, (int) $post_id );
		}

		return current_user_can( $cap );
	}
}

<?php
/**
 * بخش ۴ (فایل ۲): primitiveهای امنیتی که بقیهٔ بخش‌ها صدا می‌زنند.
 *
 * کلاس HJ_Doctors_Roles (فایل roles.php) برای سقف حجم و rate limit
 * بارگذاری نقش پزشک به همین دو تابع تکیه می‌کند. هر دو مستقل، نگهبان‌دار
 * و بی‌صدا هستند: هیچ‌چیز به debug.log نوشته نمی‌شود.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hj_sec_max_upload_bytes' ) ) {
	/**
	 * سقف حجم بارگذاری برای نقش پزشک (بایت؛ پیش‌فرض ۲ مگابایت).
	 *
	 * @return int
	 */
	function hj_sec_max_upload_bytes() {
		$max = (int) apply_filters( 'hj_sec_max_upload_bytes', 2 * 1024 * 1024 );

		return $max > 0 ? $max : 2 * 1024 * 1024;
	}
}

if ( ! function_exists( 'hj_sec_rate_limit' ) ) {
	/**
	 * rate limit ساده با transient به ازای کاربر واردشده یا IP مهمان.
	 *
	 * شمارش با هر صدا زدن زیاد می‌شود و پس از پایان بازه خودکار صفر می‌شود
	 * (انقضای transient). هیچ جدولی ساخته نمی‌شود و چیزی لاگ نمی‌شود.
	 *
	 * @param string $action   نام عمل مانند 'doctor_upload'.
	 * @param int    $limit    حداکثر تعداد مجاز در بازه.
	 * @param int    $window   بازه به ثانیه مانند HOUR_IN_SECONDS.
	 * @param bool   $by_ip    حتی اگر کاربر لاگین است، کلید را IP بگذار
	 *                         (برای ثبت‌نام که کاربر همین الان لاگین می‌شود).
	 * @return bool true یعنی هنوز جا هست؛ false یعنی سقف رد شده.
	 */
	function hj_sec_rate_limit( $action, $limit, $window, $by_ip = false ) {
		$limit  = max( 1, (int) $limit );
		$window = max( 1, (int) $window );

		$user_id = (int) get_current_user_id();
		if ( $user_id && ! $by_ip ) {
			$who = 'u' . $user_id;
		} else {
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
			$who = 'ip' . md5( $ip );
		}

		$key  = 'hj_sec_rl_' . sanitize_key( (string) $action ) . '_' . $who;
		$hits = (int) get_transient( $key );

		if ( $hits >= $limit ) {
			return false;
		}

		set_transient( $key, $hits + 1, $window );

		return true;
	}
}

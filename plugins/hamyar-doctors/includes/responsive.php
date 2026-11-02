<?php
/**
 * Hamyar Doctors — Section 13: site-wide responsive layer.
 *
 * یک شیت سراسری که آخر از همه بارگذاری می‌شود و همهٔ بخش‌های سایت را در
 * موبایل قابل استفاده نگه می‌دارد: سربرگ و ناوبری، جدول‌ها، فرم‌ها،
 * کارت‌های صفحهٔ نخست، پروفایل پزشک، راهنمای جراحی و داشبورد.
 *
 * این فایل از داخل افزونه بارگذاری می‌شود تا اصلاح موبایل با «به‌روزرسانی
 * افزونه» روی سایت بنشیند و نیازی به تعویض قالب نباشد. اگر قالب فرزند
 * نسخهٔ تازهٔ خودش را داشته باشد (handle: lax-theme-responsive)، همان
 * اولویت می‌گیرد و این فایل چیزی را دوباره بار نمی‌کند.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hj_site_responsive_enqueue' ) ) {
	/**
	 * شیت ریسپانسیو سراسری را با آخرین اولویت صف می‌کند.
	 *
	 * @return void
	 */
	function hj_site_responsive_enqueue() {
		// قالب فرزندِ به‌روز خودش این شیت را دارد؛ دوباره بار نکن.
		if ( wp_style_is( 'lax-theme-responsive', 'enqueued' ) || wp_style_is( 'lax-theme-responsive', 'done' ) ) {
			return;
		}

		$relative = 'assets/css/site-responsive.css';
		$path     = hj_doctors_path( $relative );

		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style(
			'hj-site-responsive',
			hj_doctors_url( $relative ),
			array(),
			HJ_DOCTORS_VERSION
		);
	}
	add_action( 'wp_enqueue_scripts', 'hj_site_responsive_enqueue', 999 );
}

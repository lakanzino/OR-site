<?php
/**
 * شروع ناحیهٔ پزشک (/my-profile/): ساخت صفحه، شورت‌کد موقت و redirect ورود.
 *
 * فایل roles.php پیشخوان را به روی کاربرِ فقط-پزشک می‌بندد و او را به
 * hj_doctors_dashboard_url() می‌فرستد؛ این فایل تضمین می‌کند آن نشانی هرگز
 * ۴۰۴ نباشد تا فرم کامل ویرایش نمایه در بخش بعدی روی همین صفحه سوار شود.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * صفحهٔ /my-profile/ را برمی‌گرداند و در صورت نبودن می‌سازد.
 *
 * @return int شناسهٔ صفحه یا ۰ اگر ساختن ممکن نشد.
 */
function hj_doctors_ensure_dashboard_page() {
	$existing = get_page_by_path( 'my-profile' );
	if ( $existing instanceof WP_Post ) {
		update_option( 'hj_doctors_dashboard_page', (int) $existing->ID, false );
		return (int) $existing->ID;
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'ناحیهٔ پزشک',
			'post_name'    => 'my-profile',
			'post_content' => '[hj_doctors_dashboard]',
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}

	update_option( 'hj_doctors_dashboard_page', (int) $id, false );

	return (int) $id;
}

/**
 * کارت سادهٔ ناحیه پزشک؛ جای فرم کامل ویرایش در بخش بعدی خالی می‌ماند.
 *
 * @return string HTML بی‌خطر (همهٔ خروجی‌ها escape می‌شوند).
 */
function hj_doctors_dashboard_shortcode() {
	if ( ! is_user_logged_in() ) {
		$login = wp_login_url( hj_doctors_dashboard_url() );

		return '<div class="hj-doctors-dashboard"><p>'
			. esc_html__( 'برای دیدن ناحیهٔ پزشک وارد شوید.', 'hamyar-doctors' )
			. '</p><p><a class="button" href="' . esc_url( $login ) . '">'
			. esc_html__( 'ورود', 'hamyar-doctors' )
			. '</a></p></div>';
	}

	if ( ! hj_doctors_user_can_access_dashboard() ) {
		return '<div class="hj-doctors-dashboard"><p>'
			. esc_html__( 'شما دسترسی به ناحیهٔ پزشک ندارید.', 'hamyar-doctors' )
			. '</p></div>';
	}

	$post_id = hj_doctors_get_profile_id();
	$html    = '<div class="hj-doctors-dashboard">';

	if ( ! $post_id ) {
		$html .= '<p>' . esc_html__( 'هنوز نمایهٔ پزشکی به حساب شما وصل نشده؛ مدیر سایت باید آن را بسازد و به حساب شما پیوند دهد.', 'hamyar-doctors' ) . '</p>';
	} else {
		$status = get_post_status( $post_id );
		$photo  = hj_doctors_photo_url( $post_id );
		$code   = hj_doctors_field( 'hj_medical_code', $post_id );

		$html .= '<p><img src="' . esc_url( $photo ) . '" alt="" width="96" height="96" style="border-radius:50%;" /></p>';
		$html .= '<h2>' . esc_html( get_the_title( $post_id ) ) . '</h2>';
		$html .= '<p>' . esc_html( sprintf( __( 'وضعیت نمایه: %s', 'hamyar-doctors' ), $status ? $status : '—' ) ) . '</p>';
		if ( '' !== $code && ! is_array( $code ) ) {
			$html .= '<p>' . esc_html( sprintf( __( 'کد نظام پزشکی: %s', 'hamyar-doctors' ), (string) $code ) ) . '</p>';
		}
		$html .= '<p>' . esc_html__( 'فرم کامل ویرایش نمایه در بخش بعدی همین‌جا می‌آید؛ تا آن زمان تغییرات با مدیر سایت است.', 'hamyar-doctors' ) . '</p>';
		$html .= '<p><a href="' . esc_url( (string) get_permalink( $post_id ) ) . '">' . esc_html__( 'دیدن نمایهٔ عمومی', 'hamyar-doctors' ) . '</a></p>';
	}

	if ( hj_doctors_user_can_manage( 'doctors' ) ) {
		$html .= '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=' . HJ_DOCTORS_CPT ) ) . '">'
			. esc_html__( 'مدیریت پزشک‌ها در پیشخوان', 'hamyar-doctors' )
			. '</a></p>';
	}

	$html .= '</div>';

	return $html;
}
add_shortcode( 'hj_doctors_dashboard', 'hj_doctors_dashboard_shortcode' );

/**
 * کاربر با نقش پزشک بعد از ورود مستقیم به ناحیهٔ خودش می‌رود.
 *
 * @param string           $redirect_to نشانی پیشنهادی وردپرس.
 * @param string           $requested   نشانی درخواست‌شده.
 * @param WP_User|WP_Error $user        کاربر واردشده.
 * @return string
 */
function hj_doctors_login_redirect( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && hj_doctors_is_doctor_user( $user ) ) {
		return hj_doctors_dashboard_url();
	}

	return $redirect_to;
}
add_filter( 'login_redirect', 'hj_doctors_login_redirect', 10, 3 );

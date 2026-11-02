<?php
/**
 * قالب کامل و اختیاری «تک‌پزشک» برای ماژول رندر پروفایل.
 *
 * این فایل فقط وقتی استفاده می‌شود که فیلتر
 * `hj_profile_use_full_page_template` مقدار true برگرداند؛ در حالت
 * پیش‌فرض قالب خود سایت (مثلاً single-doctor.php قالب فرزند) صفحه را
 * می‌سازد. هدر و فوتر قالب اینجا صدا زده می‌شوند تا صفحه کامل باشد.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$hj_profile_post_id = (int) get_queried_object_id();

if ( $hj_profile_post_id > 0 && function_exists( 'hj_render_doctor_profile' ) ) {
	echo '<main id="primary" class="container orsite-article-wrap hj-public-doctor" tabindex="-1">';
	// خروجی render_profile از داخل افزونه escape شده است.
	echo hj_render_doctor_profile( $hj_profile_post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</main>';
}

get_footer();

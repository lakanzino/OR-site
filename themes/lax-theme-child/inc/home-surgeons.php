<?php
/** Public homepage showcase. Only administrator-published doctor profiles. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'hj_home_surgeons', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 3 ), $atts, 'hj_home_surgeons' );
	$count = min( 4, max( 1, absint( $atts['count'] ) ) );
	$archive = get_post_type_archive_link( 'doctor' );
	$archive = $archive ? $archive : home_url( '/doctors/' );
	if ( ! post_type_exists( 'doctor' ) ) {
		return '<p class="hj-surgeons-empty">' . esc_html__( 'در حال حاضر پروفایلی برای نمایش وجود ندارد.', 'lax-theme-child' ) . '</p>';
	}
	$query = new WP_Query( array(
		'post_type' => 'doctor', 'post_status' => 'publish', 'posts_per_page' => $count,
		'fields' => 'ids', 'no_found_rows' => true, 'ignore_sticky_posts' => true,
		'meta_key' => 'hj_profile_status', 'meta_value' => 'published',
		'orderby' => 'date', 'order' => 'DESC',
	) );
	if ( ! $query->posts ) {
		return '<p class="hj-surgeons-empty">' . esc_html__( 'هنوز پروفایل تأییدشده‌ای برای نمایش وجود ندارد.', 'lax-theme-child' ) . ' <a href="' . esc_url( $archive ) . '">' . esc_html__( 'مشاهده فهرست جراحان', 'lax-theme-child' ) . '</a></p>';
	}
	$html = '<div class="hj-home-surgeons">';
	$shown = 0;
	foreach ( $query->posts as $id ) {
		// Defend against query filters that might broaden the result set.
		if ( 'doctor' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || 'published' !== get_post_meta( $id, 'hj_profile_status', true ) ) { continue; }
		$name = get_the_title( $id );
		$specialty = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( 'specialty_fa', $id ) : get_post_meta( $id, 'specialty_fa', true );
		$specialty = is_scalar( $specialty ) ? (string) $specialty : '';
		$photo = get_the_post_thumbnail_url( $id, 'medium' );
		if ( ! $photo ) {
			$attachment = (int) get_post_meta( $id, 'profile_photo', true );
			$photo = $attachment ? wp_get_attachment_image_url( $attachment, 'medium' ) : false;
		}
		$license = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( 'medical_license', $id ) : get_post_meta( $id, 'medical_license', true );
		$education = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( 'education', $id ) : get_post_meta( $id, 'education', true );
		$license = is_scalar( $license ) ? trim( wp_strip_all_tags( (string) $license ) ) : '';
		$education = is_scalar( $education ) ? trim( wp_strip_all_tags( (string) $education ) ) : '';
		$education_lines = preg_split( '/\R/u', $education, -1, PREG_SPLIT_NO_EMPTY );
		$education = $education_lines ? trim( $education_lines[0] ) : '';
		$stats = apply_filters( 'hj_doctor_rating_stats', null, $id );
		// Match the public profile's displayed average; no independent calculation or invented score.
		$rating = is_array( $stats ) && isset( $stats['avg'] ) && is_numeric( $stats['avg'] ) ? (float) $stats['avg'] : 0;
		$rating = $rating > 0 && $rating <= 5 ? $rating : 0;
		$url = get_permalink( $id );
		++$shown;
		$html .= '<article class="hj-surgeon-card">';
		$html .= '<div class="hj-surgeon-card__avatar">';
		if ( $photo ) { $html .= '<img class="hj-surgeon-card__photo" src="' . esc_url( $photo ) . '" alt="" loading="lazy" decoding="async">'; }
		else { $html .= '<span class="hj-surgeon-card__placeholder" aria-hidden="true">✚</span>'; }
		$html .= '</div><div class="hj-surgeon-card__details"><h3>' . esc_html( $name ) . '</h3>';
		if ( '' !== $specialty ) { $html .= '<p class="hj-surgeon-card__specialty">' . esc_html( $specialty ) . '</p>'; }
		if ( '' !== $education ) { $html .= '<p class="hj-surgeon-card__education">' . esc_html( $education ) . '</p>'; }
		if ( '' !== $license ) { $html .= '<p class="hj-surgeon-card__license">' . esc_html__( 'شماره نظام پزشکی:', 'lax-theme-child' ) . ' <bdi>' . esc_html( $license ) . '</bdi></p>'; }
		if ( $rating > 0 ) {
			$html .= '<p class="hj-surgeon-card__rating"><span aria-hidden="true">★</span> ' . esc_html( number_format_i18n( $rating, 1 ) ) . ' ' . esc_html__( 'از ۵', 'lax-theme-child' ) . '</p>';
		} else { $html .= '<p class="hj-surgeon-card__rating-empty">' . esc_html__( 'هنوز امتیازی برای نمایش نیست', 'lax-theme-child' ) . '</p>'; }
		$html .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهده پروفایل', 'lax-theme-child' ) . ' ←</a></div></article>';
	}
	if ( ! $shown ) { return '<p class="hj-surgeons-empty">' . esc_html__( 'هنوز پروفایل تأییدشده‌ای برای نمایش وجود ندارد.', 'lax-theme-child' ) . '</p>'; }
	$html .= '</div><p class="hj-surgeons-more"><a href="' . esc_url( $archive ) . '">' . esc_html__( 'مشاهده همه جراحان', 'lax-theme-child' ) . ' ←</a></p>';
	return $html;
} );

/** Show the doctor's signed-intent Google entry only when its plugin is active. */
function hj_home_google_shortcode() {
	return shortcode_exists( 'hj_google_login' ) ? do_shortcode( '[hj_google_login label="ثبت‌نام جراح با گوگل"]' ) : '';
}
add_shortcode( 'hj_home_google', 'hj_home_google_shortcode' );

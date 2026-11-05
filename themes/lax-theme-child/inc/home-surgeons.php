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
		$url = get_permalink( $id );
		++$shown;
		$html .= '<article class="hj-surgeon-card">';
		if ( $photo ) { $html .= '<img class="hj-surgeon-card__photo" src="' . esc_url( $photo ) . '" alt="" loading="lazy" decoding="async">'; }
		else { $html .= '<span class="hj-surgeon-card__placeholder" aria-hidden="true">✚</span>'; }
		$html .= '<h3>' . esc_html( $name ) . '</h3>';
		if ( '' !== $specialty ) { $html .= '<p>' . esc_html( $specialty ) . '</p>'; }
		$html .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهده پروفایل', 'lax-theme-child' ) . ' ←</a></article>';
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

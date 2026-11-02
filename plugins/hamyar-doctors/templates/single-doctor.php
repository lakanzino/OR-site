<?php
/**
 * قالب تک‌جراح (Single Surgeon) — نسخهٔ ۰.۳.۱.
 *
 * فقط زمانی استفاده می‌شود که قالب فعال خودش single-doctor.php نداشته باشد
 * (انتخاب قالب در HJ_Profile_Render::filter_single_doctor_template انجام می‌شود).
 *
 * برخلاف نسخهٔ قبلی که با echo و exit روی template_redirect کار می‌کرد، این
 * قالب هدر/فوتر، wp_head و wp_footer را حفظ می‌کند و منوی سایت را نمی‌شکند.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$hj_profile_id = (int) get_the_ID();

		if ( class_exists( 'HJ_Profile_Render' ) ) {
			// خروجی در render_profile کامل escape شده است.
			echo HJ_Profile_Render::get_instance()->render_profile( $hj_profile_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			?>
			<article class="hj-doctor-fallback" dir="rtl">
				<h1 class="hj-doctor-fallback__title"><?php the_title(); ?></h1>
				<div class="hj-doctor-fallback__content"><?php the_content(); ?></div>
			</article>
			<?php
		}

	endwhile;
else :
	?>
	<div class="hj-doctor-fallback" dir="rtl">
		<p><?php echo esc_html__( 'پروفایلی یافت نشد.', 'hamyar-doctors' ); ?></p>
	</div>
	<?php
endif;

get_footer();

<?php
/**
 * قالب تک‌جراح — نسخهٔ ۰.۳.۳.
 *
 * فقط زمانی استفاده می‌شود که قالب فعال خودش single-doctor.php نداشته باشد
 * (انتخاب قالب در HJ_Profile_Render::filter_single_doctor_template انجام می‌شود).
 *
 * هدر/فوتر، wp_head و wp_footer حفظ می‌شوند؛ طراحی صفحه در
 * HJ_Profile_Page::render() ساخته می‌شود تا قالب سایت و افزونه یک خروجی واحد
 * داشته باشند.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="hj-doc-page" tabindex="-1">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();

			$hj_profile_id = (int) get_the_ID();

			if ( class_exists( 'HJ_Profile_Page' ) ) {
				// خروجی render کامل escape شده است.
				echo HJ_Profile_Page::render( $hj_profile_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( class_exists( 'HJ_Profile_Render' ) ) {
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
	?>
</main>

<?php
get_footer();

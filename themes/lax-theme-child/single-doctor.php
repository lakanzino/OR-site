<?php
/**
 * صفحهٔ عمومی پروفایل پزشک.
 *
 * طراحیِ این صفحه در افزونهٔ «همیار پزشک‌ها» ساخته می‌شود
 * (HJ_Profile_Page::render) تا قالب سایت و افزونه یک خروجی واحد داشته باشند و
 * همان صفحه روی سایت‌هایی که فقط افزونه را دارند هم کار کند.
 *
 * اگر افزونه غیرفعال باشد، همین قالب یک نسخهٔ سادهٔ قابل خواندن از فیلدهای
 * ACF نشان می‌دهد تا صفحه سفید نشود.
 *
 * @package Lax_Theme_Child
 */

get_header();
?>
<main id="primary" class="hj-doc-page" tabindex="-1">
<?php
if ( ! have_posts() ) {
	echo '<div class="container"><p>پروفایلی یافت نشد.</p></div>';
} else {
	while ( have_posts() ) :
		the_post();
		$doctor_id = (int) get_the_ID();

		if ( class_exists( 'HJ_Profile_Page' ) ) {
			// خروجی render کامل escape شده است.
			echo HJ_Profile_Page::render( $doctor_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			continue;
		}

		$get = function ( $key ) use ( $doctor_id ) {
			$value = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( $key, $doctor_id ) : get_post_meta( $doctor_id, $key, true );
			return is_scalar( $value ) ? (string) $value : '';
		};
		?>
		<div class="container orsite-article-wrap hj-public-doctor">
			<article <?php post_class( 'orsite-article' ); ?>>
				<header class="orsite-title-box hj-doctor-heading">
					<?php
					if ( has_post_thumbnail() ) {
						the_post_thumbnail( 'medium', array( 'class' => 'hj-doctor-photo' ) );
					}
					?>
					<div>
						<?php the_title( '<h1 class="orsite-article__title">', '</h1>' ); ?>
						<?php if ( $get( 'specialty_fa' ) ) : ?>
							<p><?php echo esc_html( $get( 'specialty_fa' ) ); ?></p>
						<?php endif; ?>
						<?php if ( $get( 'medical_license' ) ) : ?>
							<p>شمارهٔ نظام پزشکی: <?php echo esc_html( $get( 'medical_license' ) ); ?></p>
						<?php endif; ?>
					</div>
				</header>

				<?php
				foreach ( array( 'bio_fa' => 'درباره جراح', 'education' => 'تحصیلات و دوره‌ها', 'surgeries_performed' => 'جراحی‌های انجام‌شده' ) as $key => $label ) :
					$value = $get( $key );
					if ( '' === $value ) {
						continue;
					}
					?>
					<section class="hj-doctor-section">
						<h2><?php echo esc_html( $label ); ?></h2>
						<p><?php echo nl2br( esc_html( $value ) ); ?></p>
					</section>
				<?php endforeach; ?>

				<div class="entry-content hj-doctor-section"><?php the_content(); ?></div>
			</article>
		</div>
		<?php
	endwhile;
}
?>
</main>
<?php
get_footer();

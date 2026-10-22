<?php
/**
 * Archive view (child override): neumorphic card grid.
 *
 * @package Lax_Theme_Child
 * @since   0.2.0
 */

get_header();
?>

<div class="container orsite-archive-wrap">
	<main id="primary" class="orsite-archive">
		<?php if ( have_posts() ) : ?>
			<header class="orsite-archive__header">
				<?php
				the_archive_title( '<h1 class="orsite-archive__title">', '</h1>' );
				the_archive_description( '<div class="orsite-archive__description">', '</div>' );
				?>
			</header>

			<div class="orsite-card-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/article', 'card' );
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'prev_text' => esc_html__( 'قبلی', 'lax-theme-child' ),
					'next_text' => esc_html__( 'بعدی', 'lax-theme-child' ),
				)
			);
			?>
		<?php else : ?>
			<div class="orsite-empty">
				<h1><?php esc_html_e( 'هنوز مقاله‌ای در این بخش منتشر نشده است', 'lax-theme-child' ); ?></h1>
				<p><?php esc_html_e( 'محتوای همیار جراحی پس از بازبینی و کنترل پیش از انتشار، مرحله‌ای منتشر می‌شود. برای شروع می‌توانید از راهنمای بیمار یا جستجوی سایت استفاده کنید.', 'lax-theme-child' ); ?></p>
				<p class="hj-empty__actions">
					<a class="hj-button" href="<?php echo esc_url( hj_archive_url( 'patient_guide' ) ); ?>"><?php esc_html_e( 'راهنمای بیمار', 'lax-theme-child' ); ?></a>
					<a class="hj-button hj-button--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'بازگشت به خانه', 'lax-theme-child' ); ?></a>
				</p>
				<div class="orsite-hero__search"><?php get_search_form(); ?></div>
			</div>
		<?php endif; ?>
	</main>
</div><!-- .orsite-archive-wrap -->

<?php
get_footer();

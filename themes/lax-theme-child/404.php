<?php
/**
 * 404 view (child override): navy boxed message with search.
 *
 * @package Lax_Theme_Child
 * @since   0.3.0
 */

get_header();
?>

<div class="container orsite-archive-wrap">
	<main id="primary" class="orsite-archive">
		<section class="orsite-empty orsite-empty--navy">
			<h1><?php esc_html_e( 'این صفحه پیدا نشد', 'lax-theme-child' ); ?></h1>
			<p><?php esc_html_e( 'ممکن است نشانی تغییر کرده یا صفحه هنوز منتشر نشده باشد. از جستجو استفاده کنید یا از راهنمای بیمار و بخش‌های اصلی مجله شروع کنید.', 'lax-theme-child' ); ?></p>
			<p class="hj-empty__actions">
				<a class="hj-button hj-button--light" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'بازگشت به خانه', 'lax-theme-child' ); ?></a>
				<a class="hj-button hj-button--light" href="<?php echo esc_url( hj_archive_url( 'patient_guide' ) ); ?>"><?php esc_html_e( 'راهنمای بیمار', 'lax-theme-child' ); ?></a>
			</p>
			<div class="orsite-hero__search"><?php get_search_form(); ?></div>
		</section>
	</main>
</div><!-- .orsite-archive-wrap -->

<?php
get_footer();

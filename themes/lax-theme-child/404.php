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
			<h1><?php esc_html_e( 'Page not found', 'lax-theme-child' ); ?></h1>
			<p><?php esc_html_e( '[متن نمونه — توسط تیم محتوا جایگزین شود]', 'lax-theme-child' ); ?></p>
			<div class="orsite-hero__search"><?php get_search_form(); ?></div>
		</section>
	</main>
</div><!-- .orsite-archive-wrap -->

<?php
get_footer();

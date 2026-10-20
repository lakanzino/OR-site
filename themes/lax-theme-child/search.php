<?php
/**
 * Search results (child override): boxed header + article cards.
 *
 * @package Lax_Theme_Child
 * @since   0.3.0
 */

get_header();
?>

<div class="container orsite-archive-wrap">
	<main id="primary" class="orsite-archive">
		<header class="orsite-archive__header">
			<h1 class="orsite-archive__title">
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Search results for: %s', 'lax-theme-child' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>
			<div class="orsite-hero__search"><?php get_search_form(); ?></div>
		</header>

		<?php if ( have_posts() ) : ?>
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
					'prev_text' => esc_html__( 'Previous', 'lax-theme-child' ),
					'next_text' => esc_html__( 'Next', 'lax-theme-child' ),
				)
			);
			?>
		<?php else : ?>
			<div class="orsite-empty">
				<h2><?php esc_html_e( 'Nothing found', 'lax-theme-child' ); ?></h2>
				<p><?php esc_html_e( '[متن نمونه — توسط تیم محتوا جایگزین شود]', 'lax-theme-child' ); ?></p>
			</div>
		<?php endif; ?>
	</main>
</div><!-- .orsite-archive-wrap -->

<?php
get_footer();

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
					'prev_text' => esc_html__( 'Previous', 'lax-theme-child' ),
					'next_text' => esc_html__( 'Next', 'lax-theme-child' ),
				)
			);
			?>
		<?php else : ?>
			<div class="orsite-empty">
				<h1><?php esc_html_e( 'Nothing found', 'lax-theme-child' ); ?></h1>
				<p><?php esc_html_e( '[متن نمونه — توسط تیم محتوا جایگزین شود]', 'lax-theme-child' ); ?></p>
			</div>
		<?php endif; ?>
	</main>
</div><!-- .orsite-archive-wrap -->

<?php
get_footer();

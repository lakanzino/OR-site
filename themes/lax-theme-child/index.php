<?php
/**
 * Home / fallback listing (child override): neumorphic hero + card grid.
 *
 * @package Lax_Theme_Child
 * @since   0.2.0
 */

get_header();
$is_home_listing = is_home() || is_front_page();
?>

<div class="container orsite-archive-wrap">
	<main id="primary" class="orsite-archive">
		<?php if ( $is_home_listing ) : ?>
			<section class="orsite-hero">
				<h1 class="orsite-hero__title"><?php bloginfo( 'name' ); ?></h1>
				<?php $description = get_bloginfo( 'description', 'display' ); ?>
				<?php if ( $description ) : ?>
					<p class="orsite-hero__text"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
				<div class="orsite-hero__search"><?php get_search_form(); ?></div>
			</section>
		<?php endif; ?>

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
				<h1><?php esc_html_e( 'Nothing found', 'lax-theme-child' ); ?></h1>
				<p><?php esc_html_e( '[متن نمونه — توسط تیم محتوا جایگزین شود]', 'lax-theme-child' ); ?></p>
			</div>
		<?php endif; ?>
	</main>
</div><!-- .orsite-archive-wrap -->

<?php
get_footer();

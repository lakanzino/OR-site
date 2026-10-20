<?php
/**
 * Static page view (child override): boxed title and content.
 *
 * @package Lax_Theme_Child
 * @since   0.3.0
 */

get_header();
?>

<div class="container orsite-article-wrap">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'orsite-article' ); ?>>
			<header class="orsite-title-box">
				<?php the_title( '<h1 class="orsite-article__title">', '</h1>' ); ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="orsite-article__media">
					<?php the_post_thumbnail( 'orsite-featured', array( 'loading' => 'eager', 'decoding' => 'async' ) ); ?>
					<?php $caption = get_the_post_thumbnail_caption(); ?>
					<?php if ( $caption ) : ?>
						<figcaption><?php echo esc_html( $caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="entry-content orsite-article__content">
				<?php the_content(); ?>
			</div>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	<?php endwhile; ?>
</div><!-- .orsite-article-wrap -->

<?php
get_footer();

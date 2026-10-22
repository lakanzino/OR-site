<?php
/**
 * Neumorphic article card used in archives and related lists.
 *
 * 0.9.0: deliberately minimal — featured image and a bold single-line
 * title only. Badges, excerpts and date/reading meta were removed by
 * editorial decision; the full context lives on the article itself.
 *
 * @package Lax_Theme_Child
 * @since   0.2.0
 */

$post_id       = get_the_ID();
$heading_level = ( isset( $args['heading_level'] ) && in_array( $args['heading_level'], array( 'h2', 'h3', 'h4' ), true ) )
	? $args['heading_level']
	: 'h2';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'orsite-card-article' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="orsite-card-article__media" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php
			the_post_thumbnail(
				'orsite-card',
				array(
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => '',
				)
			);
			?>
		</a>
	<?php else : ?>
		<a class="orsite-card-article__media orsite-card-article__media--placeholder" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<span><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></span>
		</a>
	<?php endif; ?>

	<div class="orsite-card-article__body">
		<?php the_title( '<' . $heading_level . ' class="orsite-card-article__title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></' . $heading_level . '>' ); ?>
	</div>
</article>

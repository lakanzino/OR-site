<?php
/**
 * Neumorphic article card used in archives and related lists.
 *
 * @package Lax_Theme_Child
 * @since   0.2.0
 */

$post_id = get_the_ID();
$term    = orsite_primary_term( $post_id, get_post_type() );
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
		<?php if ( $term ) : ?>
			<?php $term_link = get_term_link( $term ); ?>
			<?php if ( ! is_wp_error( $term_link ) ) : ?>
				<a class="orsite-badge" href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term->name ); ?></a>
			<?php endif; ?>
		<?php endif; ?>

		<?php the_title( '<h2 class="orsite-card-article__title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>

		<div class="orsite-card-article__excerpt"><?php the_excerpt(); ?></div>

		<div class="orsite-card-article__meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<span><?php echo esc_html( orsite_reading_time( get_the_content() ) ); ?> <?php esc_html_e( 'min read', 'lax-theme-child' ); ?></span>
		</div>
	</div>
</article>

<?php
/**
 * Single article view (child override): neumorphic article layout with
 * featured image, automatic table of contents and related articles.
 * No reviewer/trust bar is rendered by design.
 *
 * @package Lax_Theme_Child
 * @since   0.2.0
 */

get_header();
?>

<div class="container orsite-article-wrap">
	<?php
	while ( have_posts() ) :
		the_post();
		$article_content = orsite_render_content_buffered();
		$toc_items       = orsite_get_toc_items();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'orsite-article' ); ?>>
			<?php orsite_article_breadcrumb(); ?>

			<header class="orsite-article__header orsite-title-box">
				<?php orsite_article_badges(); ?>
				<?php the_title( '<h1 class="orsite-article__title">', '</h1>' ); ?>
				<?php if ( has_excerpt() ) : ?>
					<div class="orsite-article__lead"><?php the_excerpt(); ?></div>
				<?php endif; ?>
				<div class="orsite-article__meta">
					<?php
					$hj_author_label = function_exists( 'hj_get_clean_author_label' ) ? hj_get_clean_author_label( get_the_ID() ) : get_the_author();
					$hj_pub_jalali   = function_exists( 'hj_get_jalali_post_date' ) ? hj_get_jalali_post_date( get_the_ID() ) : get_the_date();
					$hj_mod_jalali   = function_exists( 'hj_get_jalali_post_modified_date' ) ? hj_get_jalali_post_modified_date( get_the_ID() ) : get_the_modified_date();
					?>
					<span class="orsite-article__author"><?php echo esc_html( $hj_author_label ); ?></span>
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( $hj_pub_jalali ); ?></time>
					<?php if ( get_the_modified_time( 'U' ) > get_the_time( 'U' ) && $hj_mod_jalali !== $hj_pub_jalali ) : ?>
						<time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C ) ); ?>"><?php echo esc_html( 'به‌روزرسانی: ' . $hj_mod_jalali ); ?></time>
					<?php endif; ?>
					<span class="orsite-article__reading"><?php echo esc_html( orsite_reading_time( $article_content ) ); ?> <?php esc_html_e( 'دقیقه مطالعه', 'lax-theme-child' ); ?></span>
				</div>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="orsite-article__media">
					<?php
					the_post_thumbnail(
						'orsite-featured',
						array(
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'decoding'      => 'async',
						)
					);
					?>
					<?php $caption = get_the_post_thumbnail_caption(); ?>
					<?php if ( $caption ) : ?>
						<figcaption><?php echo esc_html( $caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="orsite-article__layout">
				<div class="entry-content orsite-article__content">
					<?php echo $article_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered post content. ?>
					<?php
					wp_link_pages(
						array(
							'before' => '<div class="page-links">' . esc_html__( 'صفحه‌ها:', 'lax-theme-child' ),
							'after'  => '</div>',
						)
					);
					?>
				</div>

				<?php if ( count( $toc_items ) >= 2 ) : ?>
					<aside class="orsite-toc" aria-label="<?php esc_attr_e( 'فهرست مطالب', 'lax-theme-child' ); ?>">
						<details open data-orsite-toc>
							<summary><?php esc_html_e( 'فهرست مطالب', 'lax-theme-child' ); ?></summary>
							<ol>
								<?php foreach ( $toc_items as $item ) : ?>
									<li class="orsite-toc__item orsite-toc__item--level-<?php echo esc_attr( (string) $item['level'] ); ?>">
										<a href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a>
									</li>
								<?php endforeach; ?>
							</ol>
						</details>
					</aside>
				<?php endif; ?>
			</div>
		</article>

		<?php $related = orsite_related_articles( get_the_ID() ); ?>
		<?php if ( $related ) : ?>
			<section class="orsite-related" aria-label="<?php esc_attr_e( 'مقاله‌های مرتبط', 'lax-theme-child' ); ?>">
				<h2 class="orsite-related__title"><?php esc_html_e( 'مقاله‌های مرتبط', 'lax-theme-child' ); ?></h2>
				<div class="orsite-card-grid">
					<?php
					foreach ( $related as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						setup_postdata( $post );
						get_template_part( 'template-parts/article', 'card' );
					endforeach;
					wp_reset_postdata();
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		the_post_navigation(
			array(
				'prev_text' => esc_html__( 'مقاله‌ی قبلی', 'lax-theme-child' ),
				'next_text' => esc_html__( 'مقاله‌ی بعدی', 'lax-theme-child' ),
			)
		);

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	<?php endwhile; ?>
</div><!-- .orsite-article-wrap -->

<?php
get_footer();

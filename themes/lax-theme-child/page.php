<?php
/**
 * Static page view (child override): boxed title and content.
 * Seeded essential pages also show a last-updated line and a related
 * trust-page navigation after the content.
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
				<?php if ( has_excerpt() ) : ?>
					<div class="orsite-article__lead"><?php the_excerpt(); ?></div>
				<?php endif; ?>
				<?php if ( function_exists( 'hj_is_seeded_page' ) && hj_is_seeded_page( get_the_ID() ) ) : ?>
					<?php
					$hj_page_author = function_exists( 'hj_get_clean_author_label' ) ? hj_get_clean_author_label( get_the_ID() ) : 'تحریریه‌ی همیار جراحی';
					$hj_page_mod    = function_exists( 'hj_get_jalali_post_modified_date' ) ? hj_get_jalali_post_modified_date( get_the_ID() ) : get_the_modified_date();
					?>
					<div class="orsite-article__meta">
						<span class="orsite-article__author"><?php echo esc_html( $hj_page_author ); ?></span>
						<time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C ) ); ?>"><?php echo esc_html( 'به‌روزرسانی: ' . $hj_page_mod ); ?></time>
					</div>
				<?php endif; ?>
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

			<?php if ( function_exists( 'hj_is_seeded_page' ) && hj_is_seeded_page( get_the_ID() ) ) : ?>
				<?php
				$hj_current_slug = get_post_field( 'post_name', get_the_ID() );
				$hj_trust_links  = array(
					'about'   => 'درباره ما',
					'faq'     => 'پرسش‌های متداول',
					'privacy' => 'حریم خصوصی',
					'terms'   => 'شرایط استفاده',
					'contact' => 'تماس با ما',
				);
				?>
				<nav class="hj-page-links" aria-label="<?php esc_attr_e( 'صفحه‌های اعتماد و راهنما', 'lax-theme-child' ); ?>">
					<h2 class="hj-page-links__title"><?php esc_html_e( 'صفحه‌های مرتبط', 'lax-theme-child' ); ?></h2>
					<ul>
						<?php foreach ( $hj_trust_links as $hj_slug => $hj_label ) : ?>
							<?php if ( $hj_slug !== $hj_current_slug ) : ?>
								<li><a href="<?php echo esc_url( hj_page_url( $hj_slug ) ); ?>"><?php echo esc_html( $hj_label ); ?></a></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>
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

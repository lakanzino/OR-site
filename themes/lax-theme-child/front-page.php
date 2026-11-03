<?php
/** The static front page is ordinary page content, editable in the block editor. */
get_header();
?>
<main id="primary" class="hj-static-home" tabindex="-1">
	<?php if ( ( 'page' === get_option( 'show_on_front' ) || is_preview() ) && have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<div class="entry-content"><?php the_content(); ?></div>
			<?php wp_link_pages(); ?>
		<?php endwhile; ?>
	<?php else : ?>
		<div class="hj-home-setup-message">
			<h1><?php esc_html_e( 'به همیار جراحی خوش آمدید', 'lax-theme-child' ); ?></h1>
			<p><?php esc_html_e( 'صفحه نخست در حال آماده‌سازی است.', 'lax-theme-child' ); ?></p>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<p><a href="<?php echo esc_url( admin_url( 'themes.php?page=hj-static-home' ) ); ?>"><?php esc_html_e( 'ساخت صفحه نخست در پیشخوان', 'lax-theme-child' ); ?></a></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</main>
<?php get_footer(); ?>

<?php
/** The static front page is ordinary page content, editable in the block editor. */
get_header();
?>
<main id="primary" class="hj-static-home" tabindex="-1">
	<?php if ( ( 'page' === get_option( 'show_on_front' ) || is_preview() ) && have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<?php if ( shortcode_exists( 'hj_member_google' ) && ! has_shortcode( get_the_content(), 'hj_member_google' ) ) : ?>
				<section class="hj-member-home-entry" aria-label="<?php esc_attr_e( 'ورود اعضا', 'lax-theme-child' ); ?>">
					<p><?php esc_html_e( 'برای پیگیری نظرها و پیشنهادهای خود به جمع اعضای همیار جراحی بپیوندید.', 'lax-theme-child' ); ?></p>
					<?php echo do_shortcode( '[hj_member_google]' ); ?>
				</section>
			<?php endif; ?>
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

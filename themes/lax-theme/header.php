<?php
/**
 * The header for Lax Theme.
 *
 * @package Lax_Theme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'lax-theme' ); ?></a>

<header id="masthead" class="site-header">
	<div class="container site-header__inner">

		<div class="site-branding">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				?>
				<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="brand__mark" aria-hidden="true">L</span>
					<span class="brand__text">
						<span class="site-title"><?php bloginfo( 'name' ); ?></span>
						<?php
						$lax_theme_description = get_bloginfo( 'description', 'display' );
						if ( $lax_theme_description || is_customize_preview() ) :
							?>
							<span class="site-description"><?php echo esc_html( $lax_theme_description ); ?></span>
						<?php endif; ?>
					</span>
				</a>
				<?php
			}
			?>
		</div><!-- .site-branding -->

		<nav id="site-navigation" class="main-navigation" aria-label="<?php esc_attr_e( 'Primary Menu', 'lax-theme' ); ?>">
			<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'lax-theme' ); ?></span>
				<span class="menu-toggle__icon" aria-hidden="true"><span></span><span></span><span></span></span>
			</button>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'container'      => false,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav><!-- #site-navigation -->

		<div class="site-header__actions">
			<button class="search-toggle" aria-expanded="false" aria-controls="header-search" aria-label="<?php esc_attr_e( 'Open search', 'lax-theme' ); ?>">
				<svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			</button>
		</div>

	</div><!-- .container -->

	<div id="header-search" class="header-search" hidden>
		<div class="container">
			<?php get_search_form(); ?>
		</div>
	</div>
</header><!-- #masthead -->

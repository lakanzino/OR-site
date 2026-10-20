<?php
/**
 * Custom search form.
 *
 * @package Lax_Theme
 * @since   1.0.0
 */
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text"><?php echo esc_html_x( 'Search for:', 'label', 'lax-theme' ); ?></span>
		<input type="search" class="search-field" placeholder="<?php echo esc_attr_x( 'Search &hellip;', 'placeholder', 'lax-theme' ); ?>" value="<?php echo esc_attr( get_search_query( false ) ); ?>" name="s" />
	</label>
	<button type="submit" class="search-submit">
		<?php echo esc_html_x( 'Search', 'submit button', 'lax-theme' ); ?>
	</button>
</form>
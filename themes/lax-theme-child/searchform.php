<?php
/**
 * The parent's search form, with a home-page-specific visual hint.
 * The search action, query parameter, label and result URLs are unchanged.
 *
 * @package Lax_Theme_Child
 */

$hj_search_placeholder = is_front_page()
	? __( 'جستجوی نام جراحی…', 'lax-theme-child' )
	: _x( 'Search &hellip;', 'placeholder', 'lax-theme' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text"><?php echo esc_html_x( 'Search for:', 'label', 'lax-theme' ); ?></span>
		<input type="search" class="search-field" placeholder="<?php echo esc_attr( $hj_search_placeholder ); ?>" value="<?php echo esc_attr( get_search_query( false ) ); ?>" name="s" />
	</label>
	<button type="submit" class="search-submit">
		<?php echo esc_html_x( 'Search', 'submit button', 'lax-theme' ); ?>
	</button>
</form>

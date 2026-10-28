<?php
/**
 * Nav menu locations of the child theme (child 0.20.2).
 *
 * The parent theme already registers `primary` (desktop header) and `footer`
 * and the child theme renders no header of its own — so creating *new*
 * locations such as `menu-1` or a second `mobile-menu` would show checkboxes
 * in «نمایش → فهرست‌ها» that nothing on the site prints. The patient-facing
 * result: an editor assigns a menu and sees nothing change.
 *
 * What this file does instead:
 *
 *  1. renames the two existing locations into clear Persian labels, so the
 *     dashboard reads «منوی اصلی سربرگ (دسکتاپ)» and «منوی پابرگ»;
 *  2. documents (and wires) the mobile menu as the *same* `primary` location:
 *     the parent header collapses that menu behind the hamburger under 768px,
 *     so a separate mobile menu would mean two lists to keep in sync;
 *  3. adds `hj_nav_menu_location_alias` so a site that insists on the name
 *     `menu-1` can map it onto `primary` without a dead location.
 *
 * @package Lax_Theme_Child
 * @since   0.20.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persian labels for the locations that actually render.
 *
 * Runs after the parent (`after_setup_theme` 10) so the descriptions win
 * without touching the parent theme. Re-registering the same key keeps every
 * menu already assigned to it.
 *
 * @return void
 */
function hj_register_nav_menu_labels(): void {
	$locations = array(
		'primary' => 'منوی اصلی سربرگ (دسکتاپ و موبایل)',
		'footer'  => 'منوی پابرگ',
	);

	/**
	 * Filters the location labels the child theme registers.
	 *
	 * @param array<string,string> $locations Location key => Persian label.
	 */
	$locations = (array) apply_filters( 'hj_nav_menu_locations', $locations );

	if ( $locations ) {
		register_nav_menus( $locations );
	}
}
add_action( 'after_setup_theme', 'hj_register_nav_menu_labels', 20 );

/**
 * Print the location summary in the menus screen.
 *
 * Without this, an editor who was told to look for "menu-1" or "mobile-menu"
 * has no way to know that the desktop and mobile menu are the same list.
 * Shown only on the menus screen and only to people who can edit menus.
 *
 * @return void
 */
function hj_nav_menu_notice(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'nav-menus' !== $screen->id ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong>منوی سربرگ و موبایل یکی است.</strong>
			فهرستی که به «منوی اصلی سربرگ (دسکتاپ و موبایل)» وصل کنید، در دسکتاپ کنار لوگو و در موبایل داخل دکمهٔ همبرگری همان هدر نمایش داده می‌شود؛ ساختن فهرست دوم لازم نیست.
			منوی «پابرگ» فقط در فوتر دیده می‌شود.
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'hj_nav_menu_notice' );

/**
 * Optional alias: map a menu assigned to `menu-1` onto `primary`.
 *
 * Some setups (and the older documentation) use the name `menu-1`. Rather
 * than registering a location nothing prints, the alias reuses the menu that
 * was assigned there. Enabled with:
 *
 *     add_filter( 'hj_nav_menu_location_alias', fn() => array( 'menu-1' => 'primary' ) );
 *
 * @param array<string,mixed> $args wp_nav_menu() arguments.
 * @return array<string,mixed>
 */
function hj_nav_menu_alias( $args ) {
	if ( empty( $args['theme_location'] ) || 'primary' !== $args['theme_location'] ) {
		return $args;
	}

	$aliases = (array) apply_filters( 'hj_nav_menu_location_alias', array() );
	$current = get_nav_menu_locations();

	if ( ! empty( $current['primary'] ) || ! $aliases ) {
		return $args;
	}

	foreach ( $aliases as $alias => $target ) {
		if ( 'primary' !== $target || empty( $current[ $alias ] ) ) {
			continue;
		}
		$args['menu'] = (int) $current[ $alias ];
		break;
	}

	return $args;
}
add_filter( 'wp_nav_menu_args', 'hj_nav_menu_alias' );

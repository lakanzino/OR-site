<?php
/**
 * Child menu locations and presentation helpers.
 * The existing primary location renders the complete editable hamburger menu;
 * five archive shortcuts in the desktop bar are separate presentation links.
 * The footer location and all stored menus remain unchanged.
 *
 * @package Lax_Theme_Child
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
		'primary' => 'فهرست کامل منوی همبرگری',
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
			فهرست کامل را به «فهرست کامل منوی همبرگری» اختصاص دهید؛ در دسکتاپ پنج پیوند اصلی به‌صورت جداگانه نمایش داده می‌شوند.
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

/**
 * Compare menu destinations without changing stored menu items. Relative URLs
 * on this site are supported; query strings and fragments must also match so a
 * link to a homepage section or search is not mistaken for the homepage.
 *
 * @param string $candidate Menu item URL.
 * @param string $target    Site URL being compared.
 * @return bool
 */
function hj_nav_same_destination( $candidate, $target ) {
	$candidate = trim( html_entity_decode( (string) $candidate, ENT_QUOTES, 'UTF-8' ) );
	if ( '' === $candidate || ! $target ) {
		return false;
	}
	$item = wp_parse_url( $candidate );
	$url  = wp_parse_url( (string) $target );
	if ( ! is_array( $item ) || ! is_array( $url ) ) {
		return false;
	}
	// An external host or a non-HTTP scheme must never be matched by path alone.
	if ( isset( $item['scheme'] ) && ! in_array( strtolower( $item['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}
	if ( ! empty( $item['host'] ) && ( empty( $url['host'] ) || strcasecmp( $item['host'], $url['host'] ) ) ) {
		return false;
	}
	if ( isset( $item['port'] ) && (int) $item['port'] !== (int) ( $url['port'] ?? 0 ) ) {
		return false;
	}
	$item_path = untrailingslashit( '/' . ltrim( rawurldecode( $item['path'] ?? '' ), '/' ) );
	$url_path  = untrailingslashit( '/' . ltrim( rawurldecode( $url['path'] ?? '' ), '/' ) );
	parse_str( $item['query'] ?? '', $item_query );
	parse_str( $url['query'] ?? '', $url_query );
	return $item_path === $url_path && $item_query === $url_query &&
		( $item['fragment'] ?? '' ) === ( $url['fragment'] ?? '' );
}

/**
 * Hide the requested two entries only while rendering the primary header menu.
 * The menu database, footer, pages and any children of those entries are kept.
 * Children of a hidden item are reparented in the output rather than lost.
 *
 * @param array  $items Resolved WordPress menu objects.
 * @param object $args  wp_nav_menu arguments.
 * @return array
 */
function hj_filter_primary_header_items( $items, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $items;
	}

	$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
	$archive  = get_post_type_archive_link( 'or_environment' );
	$removed  = array();

	foreach ( $items as $item ) {
		$title = html_entity_decode( wp_strip_all_tags( (string) $item->title ), ENT_QUOTES, 'UTF-8' );
		$title = strtr( trim( $title ), array( 'ي' => 'ی', 'ك' => 'ک' ) );
		$title = preg_replace( '/\s+/u', ' ', $title );
		$is_front_page = $front_id && 'post_type' === $item->type && 'page' === $item->object && (int) $item->object_id === $front_id;
		$is_or_archive = 'post_type_archive' === $item->type && 'or_environment' === $item->object;
		if (
			in_array( $title, array( 'خانه', 'اتاق عمل و تیم' ), true ) ||
			$is_front_page || $is_or_archive ||
			hj_nav_same_destination( $item->url, home_url( '/' ) ) ||
			( $archive && hj_nav_same_destination( $item->url, $archive ) )
		) {
			$removed[ (string) $item->ID ] = (string) $item->menu_item_parent;
		}
	}

	if ( ! $removed ) {
		return $items;
	}

	$visible = array();
	foreach ( $items as $item ) {
		if ( isset( $removed[ (string) $item->ID ] ) ) {
			continue;
		}
		$parent = (string) $item->menu_item_parent;
		$seen   = array();
		while ( isset( $removed[ $parent ] ) && ! isset( $seen[ $parent ] ) ) {
			$seen[ $parent ] = true;
			$parent          = $removed[ $parent ];
		}
		if ( isset( $seen[ $parent ] ) ) {
			$parent = '0';
		}
		if ( $parent !== (string) $item->menu_item_parent ) {
			$item                   = clone $item;
			$item->menu_item_parent = $parent;
		}
		$visible[] = $item;
	}
	return $visible;
}
// The full editable primary menu must remain intact inside the hamburger.
// The former presentation filter is retained for compatibility, but no longer hooked.

/**
 * Keep navigation available when scripts are disabled. The parent's toggle
 * cannot run then, so expose the same menu in normal document flow instead.
 *
 * @return void
 */
function hj_header_no_script_fallback() {
	?>
	<noscript><style>
	.site-header__inner { flex-wrap: wrap !important; }
	.site-header__inner .main-navigation { flex: 0 0 100% !important; order: 3; }
	.site-header__inner .menu-toggle { display: none !important; }
	.site-header__inner .main-navigation > #primary-menu {
		position: static !important; display: flex !important; width: 100% !important;
		max-block-size: none !important; overflow: visible !important; box-shadow: none !important;
	}
	</style></noscript>
	<?php
}
add_action( 'wp_head', 'hj_header_no_script_fallback', 100 );

/** The five requested desktop shortcuts; links resolve from registered archives. */
function hj_header_primary_links() {
	return array(
		array( 'label' => 'خانه', 'url' => home_url( '/' ) ),
		array( 'label' => 'راهنمایی بیماران', 'url' => get_post_type_archive_link( 'patient_guide' ) ?: home_url( '/patient-guide/' ) ),
		array( 'label' => 'جراحی‌ها', 'url' => get_post_type_archive_link( 'surgery' ) ?: home_url( '/surgery/' ) ),
		array( 'label' => 'واژه‌نامه', 'url' => get_post_type_archive_link( 'glossary' ) ?: home_url( '/glossary/' ) ),
		array( 'label' => 'دانشنامه بیماری‌ها', 'url' => get_post_type_archive_link( 'disease' ) ?: home_url( '/diseases/' ) ),
	);
}

/** A useful menu if no primary WordPress menu has yet been assigned. */
function hj_header_menu_fallback() {
	$links = hj_header_primary_links();
	$doctor_archive = get_post_type_archive_link( 'doctor' );
	if ( $doctor_archive ) { $links[] = array( 'label' => 'جراحان', 'url' => $doctor_archive ); }
	$links[] = array( 'label' => 'درباره ما', 'url' => hj_page_url( 'about' ) );
	$links[] = array( 'label' => 'تماس با ما', 'url' => hj_page_url( 'contact' ) );
	$links[] = array( 'label' => 'پرسش‌های متداول', 'url' => hj_page_url( 'faq' ) );
	echo '<ul id="primary-menu" class="menu"><li class="hj-menu-title" role="presentation">فهرست</li>';
	foreach ( $links as $link ) { echo '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></li>'; }
	echo '</ul>';
}

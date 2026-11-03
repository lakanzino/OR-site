<?php
// Run only in a disposable WordPress installation: this creates a menu.
require '/wordpress/wp-load.php';
if ( has_filter( 'wp_nav_menu_objects', 'hj_filter_primary_header_items' ) ) throw new Exception( 'Legacy menu filter is still active.' );
$menu_id = wp_create_nav_menu( 'Header test ' . uniqid() );
if ( is_wp_error( $menu_id ) ) { throw new Exception( $menu_id->get_error_message() ); }
$items = array(
 array( 'title' => 'خانه', 'url' => home_url( '/' ) ),
 array( 'title' => 'اتاق عمل و تیم', 'url' => home_url( '/or-environment/' ) ),
 array( 'title' => 'دانشجویان', 'url' => home_url( '/student/' ) ),
);
foreach ( $items as $item ) {
 $id = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $item['title'], 'menu-item-url' => $item['url'], 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
 if ( is_wp_error( $id ) ) throw new Exception( 'Failed to set menu item.' );
 if ( 'اتاق عمل و تیم' === $item['title'] ) $parent = $id;
}
wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title'=>'زیرمنو آزمون','menu-item-url'=>home_url('/test/'),'menu-item-status'=>'publish','menu-item-type'=>'custom','menu-item-parent-id'=>$parent ) );
$locations = get_theme_mod( 'nav_menu_locations', array() ); $locations['primary'] = $menu_id; set_theme_mod( 'nav_menu_locations', $locations );
ob_start(); require get_stylesheet_directory() . '/header.php'; $html = ob_get_clean();
foreach ( array( 'راهنمایی بیماران', 'دانشنامه بیماری‌ها', 'فهرست', 'خانه', 'اتاق عمل و تیم', 'دانشجویان', 'زیرمنو آزمون', 'class="sub-menu"', 'id="primary-menu"' ) as $str ) if ( false === strpos( $html, $str ) ) throw new Exception( 'Missing ' . $str );
$dom=new DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html); $xpath = new DOMXPath($dom);
if ( 5 !== $xpath->query('//nav[contains(concat(" ",normalize-space(@class)," ")," hj-desktop-nav ")]/ul/li')->length ) throw new Exception( 'Desktop nav does not have exactly five links.' );
file_put_contents('/test/header.html',$html . '</body></html>');
$locations['primary'] = 0; set_theme_mod( 'nav_menu_locations', $locations );
ob_start(); require get_stylesheet_directory() . '/header.php'; $fallback = ob_get_clean();
foreach ( array( 'فهرست', 'خانه', 'اتاق عمل، تیم و تجهیزات', 'ویژه دانشجویان', 'جراحی‌ها', 'واژه‌نامه', 'دانشنامه بیماری‌ها' ) as $str ) {
 if ( false === strpos( $fallback, $str ) ) throw new Exception( 'Fallback missing ' . $str );
}
file_put_contents('/test/result.txt','PASS real WordPress assigned menu, five desktop links, nested item and no-menu fallback');

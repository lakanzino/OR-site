<?php
/**
 * Standalone presentation regression tests; no WordPress installation required.
 * Run: php themes/lax-theme-child/tests/care-navigation.test.php
 */
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
$GLOBALS['qa_show_on_front'] = 'page';
function get_option( $name ) {
	if ( 'page_on_front' === $name ) { return 87; }
	if ( 'show_on_front' === $name ) { return $GLOBALS['qa_show_on_front']; }
	return false;
}
function get_post_type_archive_link( $type ) { return 'or_environment' === $type ? home_url( '/or-environment/' ) : false; }
require_once __DIR__ . '/../inc/care-tool.php';
require_once __DIR__ . '/../inc/nav-menus.php';

function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
	echo "PASS: $message\n";
}

$labels = array(
	'جراحی معده: توضیح کامل' => 'جراحی معده',
	'عمل：توضیح بیشتر'       => 'عمل',
	'روزهای ۳ تا ۷ — پیگیری' => 'روزهای ۳ تا ۷',
	'بیش از دو هفته — بازگشت' => 'بیش از دو هفته',
	'روز ۱ - ۲'              => 'روز ۱ - ۲',
	'جراحی آرنج-دست'         => 'جراحی آرنج-دست',
	'روز ۳ – ۷'              => 'روز ۳ – ۷',
	'عنوان بدون توضیح'       => 'عنوان بدون توضیح',
	': بدون عنوان'           => ': بدون عنوان',
);
foreach ( $labels as $full => $short ) {
	check( hj_care_option_label( $full ) === $short, 'care label: ' . $full );
}

function menu_item( $id, $title, $url, $parent = 0, $type = 'custom', $object = 'custom', $object_id = 0 ) {
	return (object) array(
		'ID'               => $id,
		'title'            => $title,
		'url'              => $url,
		'menu_item_parent' => (string) $parent,
		'type'             => $type,
		'object'           => $object,
		'object_id'        => $object_id,
	);
}
$items = array(
	menu_item( 1, 'خانه', home_url( '/' ) ),
	menu_item( 2, 'اتاق عمل و تيم', home_url( '/or-environment/' ) ),
	menu_item( 3, 'فرزند حفظ‌شده', home_url( '/child/' ), 2 ),
	menu_item( 4, 'پیوند دیگر', home_url( '/article/' ) ),
	menu_item( 5, 'پیوند بیرونی', 'https://other.test/' ),
	menu_item( 6, 'بخش خانه', home_url( '/#care' ) ),
	menu_item( 7, 'جست‌وجو', home_url( '/?s=care' ) ),
	menu_item( 8, 'برگه نخست', home_url( '/front-page/' ), 0, 'post_type', 'page', 87 ),
	menu_item( 9, 'نام متفاوت برای آرشیو', home_url( '/different/' ), 0, 'post_type_archive', 'or_environment' ),
	menu_item( 10, 'پیوند نسبی آرشیو', '/or-environment/' ),
);
$footer = hj_filter_primary_header_items( $items, (object) array( 'theme_location' => 'footer' ) );
check( $footer === $items, 'footer menu is untouched' );
$filtered = hj_filter_primary_header_items( $items, (object) array( 'theme_location' => 'primary' ) );
check( array_map( function ( $item ) { return $item->ID; }, $filtered ) === array( 3, 4, 5, 6, 7 ), 'only the requested primary destinations are hidden' );
check( '0' === $filtered[0]->menu_item_parent, 'child of hidden entry is reparented' );
check( '2' === $items[2]->menu_item_parent, 'original menu item objects are not mutated' );
$GLOBALS['qa_show_on_front'] = 'posts';
$posts_front = hj_filter_primary_header_items( $items, (object) array( 'theme_location' => 'primary' ) );
check( in_array( 8, array_map( function ( $item ) { return $item->ID; }, $posts_front ), true ), 'a stale page_on_front setting does not hide an ordinary page' );
check( hj_nav_same_destination( '/or-environment/', home_url( '/or-environment/' ) ), 'relative archive URL matches' );
check( ! hj_nav_same_destination( 'https://other.test/or-environment/', home_url( '/or-environment/' ) ), 'external host never matches' );
check( ! hj_nav_same_destination( home_url( '/#care' ), home_url( '/' ) ), 'homepage fragment remains distinct' );
check( ! hj_nav_same_destination( home_url( '/?s=care' ), home_url( '/' ) ), 'homepage search remains distinct' );
echo "All care/navigation tests passed.\n";

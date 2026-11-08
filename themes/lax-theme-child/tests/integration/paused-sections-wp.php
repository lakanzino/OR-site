<?php
/** Disposable CI-only WordPress/MariaDB fixture; never use on a live site. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require_once dirname( __DIR__, 2 ) . '/inc/nav-menus.php';
require_once dirname( __DIR__, 2 ) . '/inc/paused-sections.php';
function pause_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
register_post_type( 'or_environment', array( 'public' => true, 'has_archive' => 'or-environment' ) );
register_post_type( 'student_content', array( 'public' => true, 'has_archive' => 'student' ) );
$src = file_get_contents( dirname( __DIR__, 2 ) . '/homepage/starter.html' );
$src = str_replace( array( '{{OR}}', '{{STUDENTS}}', '{{BEFORE}}', '{{GUIDES}}' ), array( home_url( '/or-environment/' ), home_url( '/student/' ), home_url( '/guide-stage/before/' ), home_url( '/patient-guide/' ) ), $src );
$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'fixture', 'post_content' => $src ) ), true );
pause_assert( ! is_wp_error( $id ), 'create fixture' );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id );
global $wp_query; $wp_query->is_home = false; $wp_query->is_page = true; $wp_query->is_singular = true; $wp_query->queried_object = get_post( $id ); $wp_query->queried_object_id = $id;
$html = do_blocks( get_post( $id )->post_content );
pause_assert( strpos( $html, 'ویژه دانشجویان اتاق عمل' ) === false && strpos( $html, 'اتاق عمل، تیم و تجهیزات' ) === false, 'target cards not rendered on front page' );
pause_assert( strpos( $html, 'قبل از عمل' ) !== false && strpos( $html, 'دانشنامه بیماری‌ها' ) !== false && strpos( $html, '[hj_home_google]' ) !== false, 'patient, other content, shortcode retained' );
pause_assert( get_post( $id )->post_content === $src, 'no stored block mutation' );
$nonfront = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'other' ) );
$wp_query->queried_object = get_post( $nonfront ); $wp_query->queried_object_id = $nonfront;
pause_assert( strpos( do_blocks( $src ), 'ویژه دانشجویان اتاق عمل' ) !== false, 'other page untouched' );
$wp_query->queried_object = get_post( $id ); $wp_query->queried_object_id = $id;
$card = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'hj-landing-card hj-card-mint' ) );
$external = '<div><a href="https://other.invalid/student/">external</a></div>';
pause_assert( hj_pause_home_card( $external, $card ) === $external, 'external not hidden' );
$multi = '<div><a href="/student/">student</a><a href="/patient-guide/">guide</a></div>';
pause_assert( hj_pause_home_card( $multi, $card ) === $multi, 'ambiguous card preserved' );
pause_assert( ! hj_paused_section_url( '/student/child/' ) && ! hj_paused_section_url( '/student/?x=1' ), 'no descendants or query matching' );
function item( $id, $url, $parent = 0 ) { return (object) array( 'ID' => $id, 'url' => $url, 'menu_item_parent' => (string) $parent, 'type' => 'custom', 'object' => 'custom' ); }
$parent = item( 1, home_url( '/student/' ) ); $child = item( 2, home_url( '/patient-guide/' ), 1 ); $other = item( 3, 'https://other.invalid/student/' );
$menu = hj_pause_primary_menu_items( array( $parent, $child, $other ), (object) array( 'theme_location' => 'primary' ) );
pause_assert( count( $menu ) === 2 && (int) $menu[0]->ID === 2 && $menu[0]->menu_item_parent === '0' && $child->menu_item_parent === '1', 'menu hides exact URL, reparents without modifying original' );
pause_assert( count( hj_pause_primary_menu_items( array( $parent ), (object) array( 'theme_location' => 'other' ) ) ) === 1, 'other menus unchanged' );
echo "PASS paused sections integration\n";

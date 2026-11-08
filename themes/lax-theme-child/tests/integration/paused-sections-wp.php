<?php
/** Disposable CI-only WordPress/MariaDB fixture; never use on a live site. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require_once dirname( __DIR__, 2 ) . '/inc/nav-menus.php';
require_once dirname( __DIR__, 2 ) . '/inc/paused-sections.php';
require_once dirname( __DIR__, 2 ) . '/inc/surgeon-display-labels.php';
function pause_assert( $value, $message ) { if ( ! $value ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
register_post_type( 'or_environment', array( 'public' => true, 'has_archive' => 'or-environment' ) );
register_post_type( 'student_content', array( 'public' => true, 'has_archive' => 'student' ) );
$src = file_get_contents( dirname( __DIR__, 2 ) . '/homepage/starter.html' );
$src = str_replace( array( '{{OR}}', '{{STUDENTS}}', '{{BEFORE}}', '{{GUIDES}}' ), array( home_url( '/or-environment/' ), home_url( '/student/' ), home_url( '/guide-stage/before/' ), home_url( '/patient-guide/' ) ), $src );
// Reproduce the currently observed older editable page, not the new starter.
$src = str_replace( array( 'جراح هستید؟', 'جراحان و متخصصان', 'ورود به پنل جراح' ), array( 'پزشک هستید؟', 'پزشکان و متخصصان', 'ورود به پنل پزشک' ), $src );
$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'fixture', 'post_content' => $src ) ), true );
pause_assert( ! is_wp_error( $id ), 'create fixture' );
$stored_before = get_post( $id )->post_content;
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id );
global $wp_query; $wp_query->is_home = false; $wp_query->is_page = true; $wp_query->is_singular = true; $wp_query->queried_object = get_post( $id ); $wp_query->queried_object_id = $id;
$html = do_blocks( get_post( $id )->post_content );
pause_assert( strpos( $html, 'ویژه دانشجویان اتاق عمل' ) === false && strpos( $html, 'اتاق عمل، تیم و تجهیزات' ) === false, 'target cards not rendered on front page' );
pause_assert( strpos( $html, 'قبل از عمل' ) !== false && strpos( $html, 'دانشنامه بیماری‌ها' ) !== false && strpos( $html, '[hj_home_google]' ) !== false, 'patient, other content, shortcode retained' );
pause_assert( strpos( $html, 'جراح هستید؟' ) !== false && strpos( $html, 'جراحان و متخصصان' ) !== false && strpos( $html, 'ورود به پنل جراح' ) !== false, 'three legacy public surgeon labels updated' );
pause_assert( strpos( $html, 'پزشک هستید؟' ) === false && strpos( $html, 'پزشکان و متخصصان' ) === false && strpos( $html, 'ورود به پنل پزشک' ) === false, 'old labels not rendered' );
pause_assert( strpos( $html, 'جایگزین مشورت با تیم درمان نیست' ) !== false && strpos( $html, 'نظام پزشکی' ) === false, 'known disclaimer updated without changing meaning' );
pause_assert( get_post( $id )->post_content === $stored_before && strpos( $stored_before, 'پزشک هستید؟' ) !== false, 'no stored block mutation' );
$unknown_label = '<p class="other">پزشک هستید؟</p>';
pause_assert( hj_surgeon_home_labels( $unknown_label, array( 'blockName' => 'core/paragraph', 'attrs' => array( 'className' => 'other' ) ) ) === $unknown_label, 'unknown text not rewritten' );
$medical_name = '<p class="hj-landing-eyebrow">نظام پزشکی</p>';
pause_assert( hj_surgeon_home_labels( $medical_name, array( 'blockName' => 'core/paragraph', 'attrs' => array( 'className' => 'hj-landing-eyebrow' ) ) ) === $medical_name, 'official term not rewritten' );
$attr_label = '<p class="hj-landing-note" title="پزشک هستید؟">متن دیگر</p>';
pause_assert( hj_surgeon_home_labels( $attr_label, array( 'blockName' => 'core/paragraph', 'attrs' => array( 'className' => 'hj-landing-note' ) ) ) === $attr_label, 'attributes not rewritten' );
$nonfront = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'other' ) );
$wp_query->queried_object = get_post( $nonfront ); $wp_query->queried_object_id = $nonfront;
$other_html = do_blocks( $src );
pause_assert( strpos( $other_html, 'ویژه دانشجویان اتاق عمل' ) !== false && strpos( $other_html, 'پزشک هستید؟' ) !== false, 'other page untouched' );
$dashboard = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'ناحیهٔ پزشک', 'post_name' => 'my-profile' ) );
$wp_query->queried_object = get_post( $dashboard ); $wp_query->queried_object_id = $dashboard;
pause_assert( get_the_title( $dashboard ) === 'ناحیهٔ جراح' && get_post( $dashboard )->post_title === 'ناحیهٔ پزشک', 'dashboard title is display-only' );
$parts = hj_surgeon_document_title( array( 'title' => 'ناحیهٔ پزشک', 'site' => 'site' ) );
pause_assert( $parts['title'] === 'ناحیهٔ جراح' && $parts['site'] === 'site', 'dashboard document title' );
register_post_type( 'doctor', array( 'public' => true, 'has_archive' => 'doctors' ) );
$wp_query->is_page = false; $wp_query->is_singular = false; $wp_query->is_post_type_archive = true; $wp_query->queried_object = get_post_type_object( 'doctor' ); $wp_query->queried_object_id = 0;
$parts = hj_surgeon_document_title( array( 'title' => 'پزشک‌ها', 'site' => 'site' ) );
pause_assert( $parts['title'] === 'جراحان' && $parts['site'] === 'site', 'archive document title' );
$wp_query->is_page = true; $wp_query->is_singular = true; $wp_query->is_post_type_archive = false;
$wp_query->queried_object = get_post( $id ); $wp_query->queried_object_id = $id;
$card = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'hj-landing-card hj-card-mint' ) );
$external = '<div><a href="https://other.invalid/student/">external</a></div>';
pause_assert( hj_pause_home_card( $external, $card ) === $external, 'external not hidden' );
$multi = '<div><a href="/student/">student</a><a href="/patient-guide/">guide</a></div>';
pause_assert( hj_pause_home_card( $multi, $card ) === $multi, 'ambiguous card preserved' );
pause_assert( ! hj_paused_section_url( '/student/child/' ) && ! hj_paused_section_url( '/student/?x=1' ), 'no descendants or query matching' );
pause_assert( hj_paused_section_url( '/student/' ) && hj_paused_section_url( '/or-environment/' ), 'both root-relative archive destinations match' );
pause_assert( ! hj_paused_section_url( '/patient-guide/' ) && ! hj_paused_section_url( '/guide-stage/during/' ) && ! hj_paused_section_url( '/surgery/' ), 'patient and surgery destinations are not paused' );
pause_assert( ! hj_paused_section_url( 'https://other.invalid/student/' ) && ! hj_paused_section_url( '/student/#section' ), 'other hosts and fragments do not match' );
$unrelated_card = '<div><a href="/patient-guide/">patient guide</a></div>';
pause_assert( hj_pause_home_card( $unrelated_card, $card ) === $unrelated_card, 'patient card unchanged' );
$unknown_card = array( 'blockName' => 'core/group', 'attrs' => array( 'className' => 'other' ) );
$single_paused = '<div><a href="/student/">student</a></div>';
pause_assert( hj_pause_home_card( $single_paused, $unknown_card ) === $single_paused, 'unknown card class unchanged' );
pause_assert( hj_pause_home_card( $single_paused, $card ) === '', 'recognized single-link card hidden' );
function item( $id, $url, $parent = 0 ) { return (object) array( 'ID' => $id, 'url' => $url, 'menu_item_parent' => (string) $parent, 'type' => 'custom', 'object' => 'custom' ); }
$parent = item( 1, home_url( '/student/' ) ); $child = item( 2, home_url( '/patient-guide/' ), 1 ); $other = item( 3, 'https://other.invalid/student/' );
$menu = hj_pause_primary_menu_items( array( $parent, $child, $other ), (object) array( 'theme_location' => 'primary' ) );
pause_assert( count( $menu ) === 2 && (int) $menu[0]->ID === 2 && $menu[0]->menu_item_parent === '0' && $child->menu_item_parent === '1', 'menu hides exact URL, reparents without modifying original' );
pause_assert( count( hj_pause_primary_menu_items( array( $parent ), (object) array( 'theme_location' => 'other' ) ) ) === 1, 'other menus unchanged' );
echo "PASS paused sections integration\n";

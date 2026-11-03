<?php
/** Run in disposable WordPress with lax-theme-child active. */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'hj_create_neo_home_draft' ) ) { throw new RuntimeException( 'Child theme required.' ); }
function hj_neo_assert( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$front = get_option( 'page_on_front' );
$show = get_option( 'show_on_front' );
$id = hj_create_neo_home_draft();
hj_neo_assert( ! is_wp_error( $id ) && $id, 'Could not create draft.' );
try {
	$page = get_post( $id );
	hj_neo_assert( 'draft' === $page->post_status, 'Must be a draft.' );
	hj_neo_assert( $front === get_option( 'page_on_front' ) && $show === get_option( 'show_on_front' ), 'Changed the selected homepage.' );
	hj_neo_assert( false === strpos( $page->post_content, '{{' ), 'Unresolved URL.' );
	hj_neo_assert( 1 === (int) get_post_meta( $id, '_hj_magazine_section_added', true ), 'Newsletter migration guard missing.' );
	foreach ( array( '[hj_home_surgeons]', '[hj_home_google]', '[hj_magazine_subscribe]', '[hj_neo_search]' ) as $shortcode ) {
		hj_neo_assert( false !== strpos( $page->post_content, $shortcode ), 'Missing shortcode: ' . $shortcode );
	}
	$blocks = parse_blocks( $page->post_content );
	hj_neo_assert( 'core/group' === $blocks[0]['blockName'], 'Not an editable group.' );
	$search = do_shortcode( '[hj_neo_search]' );
	hj_neo_assert( false !== strpos( $search, 'name="s"' ) && false !== strpos( $search, esc_url( home_url( '/' ) ) ), 'Search is not WordPress search.' );
	echo "Neo-Clay draft, homepage preservation, blocks and search: OK\n";
} finally { wp_delete_post( $id, true ); }

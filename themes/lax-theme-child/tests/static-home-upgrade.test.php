<?php
/** Run with PHP: php themes/lax-theme-child/tests/static-home-upgrade.test.php */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array( 'show_on_front' => 'page', 'page_on_front' => 45 );
$GLOBALS['pages'] = array();
$GLOBALS['can_manage'] = true;
$GLOBALS['theme_path'] = dirname( __DIR__ );
function add_action( ...$args ) {}
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value, ...$rest ) { if ( array_key_exists( $key, $GLOBALS['options'] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function update_option( $key, $value, ...$rest ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function get_stylesheet_directory() { return $GLOBALS['theme_path']; }
function get_stylesheet_directory_uri() { return 'https://example.test/theme'; }
function get_post_type_archive_link( $post_type ) { return 'https://example.test/archive/' . $post_type . '/'; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function site_url( $path = '' ) { return home_url( '/' . $path ); }
function hj_term_url( $tax, $term, $fallback ) { return home_url( '/stage/' . $term . '/' ); }
function hj_page_url( $slug ) { return home_url( '/' . $slug . '/' ); }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
function esc_url( $value ) { return $value; }
function wp_slash( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { public function __construct( ...$args ) {} }
function get_permalink( $post ) { return home_url( '/symptom-checker/' ); }
function get_posts( $args ) {
	if ( isset( $args['name'] ) ) { return ! empty( $GLOBALS['symptom_exists'] ) ? array( (object) array( 'ID' => 99 ) ) : array(); }
	$ids = array();
	foreach ( $GLOBALS['pages'] as $id => $page ) { if ( isset( $page['meta_input']['_hj_static_home_revision'] ) && '0.33.2' === $page['meta_input']['_hj_static_home_revision'] ) { $ids[] = $id; } }
	return $ids;
}
function wp_insert_post( $data, $error ) { $id = 100 + count( $GLOBALS['pages'] ); $GLOBALS['pages'][ $id ] = $data; return $id; }
require_once dirname( __DIR__ ) . '/inc/static-home.php';
function check_home( $condition, $label ) { if ( ! $condition ) { throw new Exception( 'FAIL: ' . $label ); } echo 'PASS: ' . $label . "\n"; }

$GLOBALS['can_manage'] = false;
hj_static_home_upgrade_0332();
check_home( empty( $GLOBALS['pages'] ), 'non-manager does not run upgrade' );
$GLOBALS['can_manage'] = true;
$GLOBALS['theme_path'] = '/missing-theme';
hj_static_home_upgrade_0332();
check_home( 45 === get_option( 'page_on_front' ) && ! get_option( 'hj_static_home_0332_done' ) && get_option( 'hj_static_home_0332_error' ), 'failed creation preserves original selection and permits retry' );
$GLOBALS['theme_path'] = dirname( __DIR__ );
$GLOBALS['symptom_exists'] = true;
hj_static_home_upgrade_0332();
check_home( 1 === count( $GLOBALS['pages'] ) && 100 === get_option( 'page_on_front' ) && 'page' === get_option( 'show_on_front' ), 'upgrade creates and selects new page' );
check_home( 45 === get_option( 'hj_static_home_previous' )['page_on_front'], 'previous selection saved' );
$content = $GLOBALS['pages'][100]['post_content'];
check_home( false === strpos( $content, '{{' ) && false !== strpos( $content, '[hj_home_surgeons]' ) && false !== strpos( $content, 'اتاق عمل، تیم و تجهیزات' ), 'template placeholders resolve and approved-doctor showcase is present' );
update_option( 'page_on_front', 45 );
hj_static_home_upgrade_0332();
check_home( 45 === get_option( 'page_on_front' ) && 1 === count( $GLOBALS['pages'] ), 'one-time upgrade respects later manual changes' );
// A request interrupted after creating the marked page reuses it.
delete_option( 'hj_static_home_0332_done' );
delete_option( 'hj_static_home_0332_lock' );
hj_static_home_upgrade_0332();
check_home( 1 === count( $GLOBALS['pages'] ) && 100 === get_option( 'page_on_front' ) && 45 === get_option( 'hj_static_home_previous' )['page_on_front'], 'interrupted upgrade resumes without duplication or replacing backup' );
$GLOBALS['symptom_exists'] = false;
$id = hj_static_home_create_page();
check_home( false === strpos( $GLOBALS['pages'][$id]['post_content'], 'ارزیابی علائم ←' ), 'unused symptom tool is not linked' );
echo "All static homepage upgrade checks passed.\n";

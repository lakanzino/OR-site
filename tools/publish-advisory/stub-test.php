<?php
/** Isolated hook and UI checks under real PHP; not WordPress integration. */
define( 'ABSPATH', '/fixture/' );
$GLOBALS['hooks'] = array();
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function apply_filters( $name, $value ) { return $value; }
function strip_shortcodes( $s ) { return $s; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function get_post_meta( $id, $key, $single ) { return ''; }
function get_post_thumbnail_id( $id ) { return 0; }
function get_the_terms( $id, $tax ) { return false; }
function is_wp_error( $v ) { return false; }
function hj_source_summary( $content ) { return array( 'loaded' => true, 'approved_count' => 0, 'min' => 3, 'approved' => array(), 'tier1_count' => 0, 'ok' => false ); }
function esc_html( $v ) { return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_html__( $v, $domain ) { return esc_html( $v ); }
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } echo "PASS $message\n"; }
require '/repo/themes/lax-theme-child/inc/publish-gate.php';
check( empty( $GLOBALS['hooks']['wp_insert_post_data'] ), 'no status-changing insertion hook' );
check( empty( $GLOBALS['hooks']['admin_notices'] ), 'no stale blocked-publish notice' );
$result = hj_gate_checks( '<p>متن کوتاه</p>', 17, 'patient_guide' );
check( $result['fails'] >= 4, 'missing sources, image, length and warning are detected' );
ob_start(); hj_gate_render_meta_box( (object) array( 'ID' => 17, 'post_content' => '<p>متن کوتاه</p>', 'post_type' => 'patient_guide' ) ); $html = ob_get_clean();
check( false !== strpos( $html, 'مانع انتشار نمی‌شوند' ) && false !== strpos( $html, 'منابع علمی معتبر' ), 'checklist shows non-blocking warnings' );
check( false === strpos( $html, 'hj_gate_override' ) && false === strpos( $html, 'بازدارنده' ), 'no override checkbox or blocking label' );

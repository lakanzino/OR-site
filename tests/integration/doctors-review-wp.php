<?php
/** Run with wp eval-file on a disposable WP install with the plugin active. */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'hj_review_fields' ) ) { throw new RuntimeException( 'Active hamyar-doctors required.' ); }
function hj_review_test( $condition, $label ) { if ( ! $condition ) { throw new RuntimeException( $label ); } }
$old_user = get_current_user_id();
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
hj_review_test( ! empty( $admin ), 'Admin required' );
wp_set_current_user( $admin[0]->ID );
$mail = array();
$capture = function ( $result, $atts ) use ( &$mail ) { $mail[] = $atts; return true; };
add_filter( 'pre_wp_mail', $capture, 10, 2 );
$id = 0;
try {
	hj_review_test( hj_member_account_url() !== hj_doctors_dashboard_url(), 'Account and doctor URLs are the same' );
	$id = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'draft', 'post_title' => 'Doctor integration test' ), true );
	hj_review_test( ! is_wp_error( $id ) && $id, 'Doctor post creation failed' );
	update_post_meta( $id, 'specialty_fa', 'Test specialty' );
	hj_review_test( hj_doctors_field( 'specialty_fa', $id ) === 'Test specialty', 'Meta fallback failed' );
	if ( ! function_exists( 'acf_get_field_group' ) ) {
		ob_start(); hj_review_render_fields( get_post( $id ) ); $editor = ob_get_clean();
		hj_review_test( false !== strpos( $editor, 'Test specialty' ) && false !== strpos( $editor, 'hj_hours_present' ), 'Native editor did not show fields' );
		$_POST['hj_review_nonce'] = wp_create_nonce( 'hj_review_fields_' . $id );
		$_POST['hj_review'] = array( 'specialty_fa' => 'Edited specialty' );
		$_POST['hj_hours_present'] = '1'; $_POST['hj_hours'] = array( 'saturday' => array( 'start' => '09:00', 'end' => '12:30' ) );
		do_action( 'save_post_doctor', $id, get_post( $id ), true );
		hj_review_test( get_post_meta( $id, 'specialty_fa', true ) === 'Edited specialty', 'Native field save failed' );
		hj_review_test( get_post_meta( $id, 'working_hours', true )[0]['start_time'] === '09:00:00', 'Native hours save failed' );
		unset( $_POST['hj_review_nonce'], $_POST['hj_review'], $_POST['hj_hours_present'], $_POST['hj_hours'] );
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
	hj_review_test( get_post_meta( $id, 'hj_profile_status', true ) === 'pending' && hj_review_pending_count() >= 1, 'Pending queue failed' );
	hj_review_test( count( $mail ) === 1 && strpos( $mail[0]['subject'], 'پروفایل پزشک' ) !== false, 'Email notification missing' );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
	hj_review_test( count( $mail ) === 1, 'Duplicate notification for same status' );
	ob_start(); do_action( 'admin_notices' ); $notices = ob_get_clean();
	hj_review_test( false !== strpos( $notices, 'post_status=pending' ), 'Admin queue notice missing' );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	hj_review_test( get_post_meta( $id, 'hj_profile_status', true ) === 'published', 'Native publish not synced' );
	hj_review_test( false !== strpos( do_shortcode( '[hj_magazine_subscribe]' ), 'name="consent"' ), 'Subscription consent missing' );
} finally {
	remove_filter( 'pre_wp_mail', $capture, 10 );
	if ( $id && ! is_wp_error( $id ) ) { wp_delete_post( $id, true ); }
	wp_set_current_user( $old_user );
}
echo "Doctor review and member integration checks passed.\n";

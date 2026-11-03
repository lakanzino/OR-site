<?php
/** Integration test for a disposable WordPress install only. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST ) { exit; }
require '/wordpress/wp-load.php';
function hj_assert_test( $value, $message ) { if ( ! $value ) { throw new Exception( $message ); } echo "PASS $message\n"; }
class NextendSocialLogin {
	public static function isProviderEnabled( $id ) { return 'google' === $id; }
	public static function getLoginUrl() { return site_url( 'wp-login.php' ); }
}
$unique = uniqid();
$one = wp_create_user( 'member1' . $unique, 'testPass!', 'member1' . $unique . '@example.test' );
$two = wp_create_user( 'member2' . $unique, 'testPass!', 'member2' . $unique . '@example.test' );
$editor = wp_create_user( 'editor' . $unique, 'testPass!', 'editor' . $unique . '@example.test' );
$admin = wp_create_user( 'admin' . $unique, 'testPass!', 'admin' . $unique . '@example.test' );
(new WP_User( $editor ))->set_role( 'editor' ); (new WP_User( $admin ))->set_role( 'administrator' );
$post = wp_insert_post( array( 'post_title' => 'Comment test', 'post_type' => 'post', 'post_status' => 'publish' ) );
foreach ( array( array( $one, 'member one comment', 1 ), array( $one, 'member one pending', 0 ), array( $two, 'private other member comment', 0 ), array( $one, 'trash hidden comment', 'trash' ) ) as $entry ) {
	wp_insert_comment( array( 'comment_post_ID' => $post, 'user_id' => $entry[0], 'comment_content' => $entry[1], 'comment_approved' => $entry[2], 'comment_type' => 'comment' ) );
}
$sug1 = wp_insert_post( array( 'post_type' => 'hj_suggestion', 'post_status' => 'pending', 'post_title' => 'one', 'post_content' => 'member one suggestion', 'post_author' => $one ) );
$sug2 = wp_insert_post( array( 'post_type' => 'hj_suggestion', 'post_status' => 'pending', 'post_title' => 'two', 'post_content' => 'other private suggestion', 'post_author' => $two ) );
wp_set_current_user( $one ); $markup = do_shortcode( '[hj_member_account]' );
hj_assert_test( false !== strpos( $markup, 'member one comment' ) && false !== strpos( $markup, 'member one pending' ), 'own approved and pending comments visible' );
hj_assert_test( false !== strpos( $markup, 'member one suggestion' ), 'own private suggestion visible' );
hj_assert_test( false === strpos( $markup, 'other private suggestion' ) && false === strpos( $markup, 'private other member comment' ) && false === strpos( $markup, 'trash hidden comment' ), 'other users and trash excluded' );
hj_assert_test( ! current_user_can( 'edit_post', $sug1 ) && ! current_user_can( 'delete_post', $sug1 ) && ! current_user_can( 'read_post', $sug2 ), 'members cannot modify or access suggestions via post capabilities' );
hj_assert_test( ! post_type_supports( 'hj_suggestion', 'comments' ) && ! get_post_type_object( 'hj_suggestion' )->publicly_queryable && ! get_post_type_object( 'hj_suggestion' )->show_in_rest, 'suggestions not publicly queryable or in REST' );
$comments = get_comments( array( 'post_id' => $post, 'user_id' => $one, 'number' => 1 ) ); $comment_id = $comments[0]->comment_ID;
wp_set_current_user( $editor ); hj_assert_test( ! current_user_can( 'edit_comment', $comment_id ) && ! current_user_can( 'edit_post', $sug1 ), 'editor cannot edit or delete user records' );
wp_set_current_user( $admin ); hj_assert_test( current_user_can( 'edit_comment', $comment_id ) && current_user_can( 'edit_post', $sug1 ), 'administrator can edit records' );
wp_set_current_user( 0 ); $guest = do_shortcode( '[hj_member_account]' );
hj_assert_test( false !== strpos( $guest, 'ورود یا ثبت‌نام با گوگل' ) && false === strpos( $guest, 'member one suggestion' ), 'guest sees public Google entry without private data' );
hj_assert_test( false !== strpos( do_shortcode( '[hj_member_google]' ), 'hj_member_google=1' ), 'public entry uses member flow instead of doctor flow' );
update_user_meta( $two, 'hj_role_pending', '1' ); $ts=time(); $_COOKIE['hj_member_intent'] = $ts . '|' . hash_hmac('sha256','hj_member_intent|' . $ts, wp_salt('auth'));
do_action( 'nsl_login', $two, 'google' );
hj_assert_test( ! in_array( 'doctor', get_userdata( $two )->roles, true ) && get_user_meta( $two, 'hj_role_pending', true ), 'member login never consumes pending doctor intent' );
unset( $_COOKIE['hj_member_intent'] );
file_put_contents('/test/result.txt', 'PASS integration');

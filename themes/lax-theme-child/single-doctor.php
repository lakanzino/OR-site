<?php
/**
 * Single doctor: bridge to the hamyar-doctors public profile (section 7).
 *
 * - Plugin inactive  → the generic single template renders exactly as before.
 * - Non-public doctor (draft/pending/hidden) → real 404 for visitors; the
 *   profile owner and admins get a preview with noindex (plugin wp_robots)
 *   and no cache.
 * - Public doctor → the plugin's Medical Premium profile inside child chrome.
 *
 * Instant rollback: delete this file; the generic single takes over again.
 *
 * @package Lax_Theme_Child
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hj_pr_is_viewable' ) || ! function_exists( 'hj_doctor_profile_html' ) ) {
	locate_template( array( 'single.php' ), true );
	return;
}

$hj_pr_id = get_queried_object_id();

if ( ! $hj_pr_id || ! hj_pr_is_viewable( $hj_pr_id ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	locate_template( array( '404.php' ), true );
	return;
}

if ( 'publish' !== get_post_status( $hj_pr_id ) ) {
	nocache_headers(); // Owner/admin preview must never be cached.
}

get_header();
?>

<div class="hj-pr-wrap">
	<?php echo hj_doctor_profile_html( $hj_pr_id ); // Escaped inside the plugin. ?>
</div>

<?php
get_footer();

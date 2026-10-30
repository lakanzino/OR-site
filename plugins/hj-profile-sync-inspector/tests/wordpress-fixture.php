<?php
require '/wordpress/wp-load.php';
require '/mnt/inspector/hj-profile-sync-inspector.php';
$old = "update_post_meta( \$post_id, '_hj_disease_profile', wp_json_encode( \$profile, JSON_UNESCAPED_UNICODE ) );";
$fixed = "update_post_meta( \$post_id, '_hj_disease_profile', wp_slash( wp_json_encode( \$profile, JSON_UNESCAPED_UNICODE ) ) );";
$path = '/wordpress/hjsi-test.txt';
function assert_hjsi( $condition, $msg ) { if ( ! $condition ) { throw new RuntimeException( $msg ); } }
file_put_contents( $path, "<?php\n" . $old );
assert_hjsi( 'original_line_custom_file' === hjsi_inspect_file( $path )['status'], 'old line' );
file_put_contents( $path, "<?php\n" . $fixed );
assert_hjsi( 'fixed_line_found' === hjsi_inspect_file( $path )['status'], 'fixed line' );
file_put_contents( $path, "<?php\n" . $old . "\n" . $old );
assert_hjsi( 'unrecognized_or_ambiguous' === hjsi_inspect_file( $path )['status'], 'duplicate' );
file_put_contents( $path, "<?php\n" . $old . "\n" . $fixed );
assert_hjsi( 'unrecognized_or_ambiguous' === hjsi_inspect_file( $path )['status'], 'mixed' );
assert_hjsi( 'unreadable' === hjsi_inspect_file( '/wordpress/nonexistent-hjsi' )['status'], 'missing' );
$tag = hjsi_inspect_file( '/mnt/tag/disease-directory.php' );
assert_hjsi( 'matches_original_tag' === $tag['status'], 'exact tag' );
assert_hjsi( false !== has_action( 'admin_menu' ), 'admin menu action' );
mkdir( '/wordpress/hjsi-theme/inc', 0777, true );
copy( '/mnt/tag/disease-directory.php', '/wordpress/hjsi-theme/inc/disease-directory.php' );
add_filter( 'stylesheet_directory', function () { return '/wordpress/hjsi-theme'; } );
wp_set_current_user( 1 );
$before_hash = hash_file( 'sha256', '/wordpress/hjsi-theme/inc/disease-directory.php' );
ob_start(); hjsi_page(); $html = ob_get_clean();
assert_hjsi( false !== strpos( $html, 'matches_original_tag' ), 'admin page diagnosis' );
assert_hjsi( $before_hash === hash_file( 'sha256', '/wordpress/hjsi-theme/inc/disease-directory.php' ), 'diagnostic changed file' );
file_put_contents( '/wordpress/hjsi-results.json', wp_json_encode( array(
    'old_line' => true, 'fixed_line' => true, 'ambiguous' => true,
    'missing_file' => true, 'exact_tag' => true, 'admin_action' => true, 'admin_page_read_only' => true,
) ) );

<?php
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
require '/mnt/theme/inc/disease-directory.php';
hj_register_disease_directory();
add_filter( 'stylesheet_directory', function () { return '/mnt/theme'; } );
update_option( 'hj_disease_profile_version', '0', false );
delete_transient( 'hj_disease_profile_lock' );
hj_sync_disease_profiles();
$reference_items = json_decode( file_get_contents( '/mnt/theme/data/disease-profiles.json' ), true );
$references = array();
foreach ( $reference_items as $item ) { $references[ $item['diseaseId'] ] = $item['profile']; }
$posts = get_posts( array( 'post_type' => 'disease', 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$matched = 0; $problems = array(); $app_id = null;
foreach ( $posts as $id ) {
    $key = get_post_meta( $id, '_hj_disease_seed_id', true );
    if ( ! isset( $references[$key] ) ) { $problems[] = 'unexpected_id'; continue; }
    if ( 'app' === $key ) { $app_id = $id; }
    $stored = json_decode( get_post_meta( $id, '_hj_disease_profile', true ), true );
    if ( ! is_array( $stored ) ) { $problems[] = $key . ':invalid_json'; continue; }
    foreach ( $references[$key] as $field => $value ) {
        if ( ! array_key_exists( $field, $stored ) || $stored[$field] !== $value ) { $problems[] = $key . ':' . $field; }
    }
    $matched++;
}
if ( ! $app_id ) { throw new RuntimeException( 'expected appendicitis fixture post' ); }
// A normal second run at the same version must not overwrite a later editor change.
$edited = wp_json_encode( array( 'note' => "custom\nedit" ), JSON_UNESCAPED_UNICODE );
update_post_meta( $app_id, '_hj_disease_profile', wp_slash( $edited ) );
hj_sync_disease_profiles();
$repeat_preserves_edits = get_post_meta( $app_id, '_hj_disease_profile', true ) === $edited;
file_put_contents( '/wordpress/profile-sync-test.json', wp_json_encode( array(
    'wp_version' => get_bloginfo( 'version' ), 'php_version' => PHP_VERSION,
    'profiles_expected' => count( $references ), 'profiles_checked' => $matched,
    'fields_equal' => ! $problems, 'different_fields' => $problems,
    'repeat_preserves_edits' => $repeat_preserves_edits,
) ) );

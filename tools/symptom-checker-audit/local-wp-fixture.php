<?php
/** Disposable test data only: this is NOT the production mapping implementation. */
require '/wordpress/wp-load.php';
register_post_type( 'disease', array( 'public' => true ) );

function hj_fixture_disease( $name, $status, $key ) {
    $id = wp_insert_post( array( 'post_type' => 'disease', 'post_status' => $status, 'post_title' => $name ), true );
    if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Fixture creation failed' ); }
    update_post_meta( $id, '_hj_disease_seed_id', $key );
    return (int) $id;
}

function hj_fixture_lookup( $key ) {
    $ids = get_posts( array(
        'post_type' => 'disease', 'post_status' => 'publish', 'meta_key' => '_hj_disease_seed_id',
        'meta_value' => $key, 'posts_per_page' => 2, 'fields' => 'ids', 'no_found_rows' => true,
    ) );
    return 1 === count( $ids ) ? (int) $ids[0] : null;
}

$published = hj_fixture_disease( 'Published test', 'publish', 'published_test' );
hj_fixture_disease( 'Draft test', 'draft', 'draft_test' );
hj_fixture_disease( 'Private test', 'private', 'private_test' );
hj_fixture_disease( 'Duplicate one', 'publish', 'duplicate_test' );
hj_fixture_disease( 'Duplicate two', 'publish', 'duplicate_test' );
$results = array(
    'wp_version' => get_bloginfo( 'version' ),
    'php_version' => PHP_VERSION,
    'site_installed' => is_blog_installed(),
    'sqlite_class' => class_exists( 'WP_SQLite_Driver' ),
    'published_unique' => $published === hj_fixture_lookup( 'published_test' ),
    'draft_excluded' => null === hj_fixture_lookup( 'draft_test' ),
    'private_excluded' => null === hj_fixture_lookup( 'private_test' ),
    'duplicate_excluded' => null === hj_fixture_lookup( 'duplicate_test' ),
    'missing_excluded' => null === hj_fixture_lookup( 'missing_test' ),
);
file_put_contents( '/wordpress/hj-environment-check.json', wp_json_encode( $results ) );

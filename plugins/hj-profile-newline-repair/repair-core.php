<?php
/** Internal repair logic. No direct access and no automatic write. */
defined( 'ABSPATH' ) || exit;
const HJNR_PROFILE_META = '_hj_disease_profile';
const HJNR_ID_META = '_hj_disease_seed_id';
const HJNR_BACKUP_META = '_hj_profile_newline_backup_v1';
const HJNR_LOCK = 'hjnr_repair_lock_v1';

/** Packaged, immutable reference; fail closed on invalid or duplicate IDs. */
function hjnr_reference() {
    static $index = null;
    if ( null !== $index ) { return $index; }
    $path = __DIR__ . '/data/disease-profiles.json';
    $items = is_readable( $path ) ? json_decode( file_get_contents( $path ), true ) : null;
    if ( ! is_array( $items ) || ! $items ) { throw new RuntimeException( 'reference_invalid' ); }
    $index = array();
    foreach ( $items as $item ) {
        if ( empty( $item['diseaseId'] ) || ! is_string( $item['diseaseId'] ) || ! isset( $item['profile'] ) || ! is_array( $item['profile'] ) || isset( $index[ $item['diseaseId'] ] ) ) {
            $index = null; throw new RuntimeException( 'reference_invalid' );
        }
        $index[ $item['diseaseId'] ] = $item['profile'];
    }
    return $index;
}

/** Only reference-identical profiles with the precise newline-to-n damage qualify. */
function hjnr_plan( $current, $reference ) {
    if ( ! is_array( $current ) || ! is_array( $reference ) || count( $current ) !== count( $reference ) ) { return null; }
    $next = $current; $changed = array();
    foreach ( $reference as $key => $expected ) {
        if ( ! array_key_exists( $key, $current ) ) { return null; }
        $actual = $current[ $key ];
        if ( $actual === $expected ) { continue; }
        if ( is_string( $expected ) && is_string( $actual ) && false !== strpos( $expected, "\n" ) && $actual === str_replace( "\n", 'n', $expected ) ) {
            $next[ $key ] = $expected; $changed[] = $key;
        } else { return null; }
    }
    return $changed ? array( 'profile' => $next, 'fields' => $changed ) : null;
}

/** Reject duplicate internal IDs including non-public disease posts. */
function hjnr_unique_id( $post_id, $id ) {
    $posts = get_posts( array(
        'post_type' => 'disease', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
        'meta_key' => HJNR_ID_META, 'meta_value' => $id, 'posts_per_page' => 2, 'fields' => 'ids', 'no_found_rows' => true,
    ) );
    return count( $posts ) === 1 && (int) $posts[0] === (int) $post_id;
}

function hjnr_candidate( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || 'disease' !== $post->post_type || 'publish' !== $post->post_status ) { return null; }
    if ( metadata_exists( 'post', $post_id, HJNR_BACKUP_META ) ) { return null; }
    // Duplicate metadata rows are ambiguous; never update multiple rows accidentally.
    if ( 1 !== count( get_post_meta( $post_id, HJNR_ID_META, false ) ) || 1 !== count( get_post_meta( $post_id, HJNR_PROFILE_META, false ) ) ) { return null; }
    $id = get_post_meta( $post_id, HJNR_ID_META, true );
    if ( ! is_string( $id ) || '' === $id || ! hjnr_unique_id( $post_id, $id ) ) { return null; }
    $reference = hjnr_reference();
    if ( ! isset( $reference[ $id ] ) ) { return null; }
    $raw = get_post_meta( $post_id, HJNR_PROFILE_META, true );
    if ( ! is_string( $raw ) || '' === $raw ) { return null; }
    $current = json_decode( $raw, true );
    $plan = hjnr_plan( $current, $reference[ $id ] );
    if ( ! $plan ) { return null; }
    $after = wp_json_encode( $plan['profile'], JSON_UNESCAPED_UNICODE );
    if ( ! is_string( $after ) || '' === $after ) { return null; }
    return array( 'id' => $id, 'before' => $raw, 'after' => $after, 'fields' => $plan['fields'] );
}

/** Backup before write and use compare-and-swap; a failure retains the backup. */
function hjnr_apply( $post_id, $expected_hash = null ) {
    $candidate = hjnr_candidate( $post_id );
    if ( ! $candidate ) { return 'skipped'; }
    if ( null !== $expected_hash && ( ! is_string( $expected_hash ) || ! hash_equals( hash( 'sha256', $candidate['before'] . "\0" . $candidate['after'] ), $expected_hash ) ) ) { return 'conflict'; }
    $backup = wp_json_encode( array( 'before' => $candidate['before'], 'after' => $candidate['after'], 'internal_id' => $candidate['id'], 'fields' => $candidate['fields'], 'created_utc' => gmdate( 'c' ) ), JSON_UNESCAPED_UNICODE );
    if ( ! is_string( $backup ) || ! add_post_meta( $post_id, HJNR_BACKUP_META, wp_slash( $backup ), true ) ) { return 'backup_failed'; }
    // Verify the stored backup before touching the original profile.
    if ( get_post_meta( $post_id, HJNR_BACKUP_META, true ) !== $backup ) { return 'backup_failed'; }
    if ( ! update_post_meta( $post_id, HJNR_PROFILE_META, wp_slash( $candidate['after'] ), $candidate['before'] ) ) { return 'conflict'; }
    return get_post_meta( $post_id, HJNR_PROFILE_META, true ) === $candidate['after'] ? 'repaired' : 'verify_failed';
}

/** Restore only if profile still exactly equals our own repaired value. */
function hjnr_restore( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || 'disease' !== $post->post_type ) { return 'skipped'; }
    $raw = get_post_meta( $post_id, HJNR_BACKUP_META, true );
    $backup = is_string( $raw ) ? json_decode( $raw, true ) : null;
    if ( ! is_array( $backup ) || ! isset( $backup['before'], $backup['after'], $backup['internal_id'] ) || ! is_string( $backup['before'] ) || ! is_string( $backup['after'] ) || ! is_string( $backup['internal_id'] ) ) { return 'no_backup'; }
    if ( 1 !== count( get_post_meta( $post_id, HJNR_PROFILE_META, false ) ) || 1 !== count( get_post_meta( $post_id, HJNR_BACKUP_META, false ) ) || 1 !== count( get_post_meta( $post_id, HJNR_ID_META, false ) ) || get_post_meta( $post_id, HJNR_ID_META, true ) !== $backup['internal_id'] ) { return 'conflict'; }
    $current = get_post_meta( $post_id, HJNR_PROFILE_META, true );
    if ( $current !== $backup['after'] ) { return 'conflict'; }
    if ( ! update_post_meta( $post_id, HJNR_PROFILE_META, wp_slash( $backup['before'] ), $backup['after'] ) ) { return 'conflict'; }
    // Retain the backup as an audit trail. Subsequent automatic repair remains disabled for this post.
    return get_post_meta( $post_id, HJNR_PROFILE_META, true ) === $backup['before'] ? 'restored' : 'verify_failed';
}

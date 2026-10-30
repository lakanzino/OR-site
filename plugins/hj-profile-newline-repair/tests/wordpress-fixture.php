<?php
require '/wordpress/wp-load.php';
require '/mnt/hjnr/hj-profile-newline-repair.php';
register_post_type( 'disease', array( 'public' => true ) );
function fixture_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function fixture_post( $reference_id, $status = 'publish', $change = false ) {
    $profiles = hjnr_reference(); $profile = $profiles[ $reference_id ];
    foreach ( $profile as $k => $v ) { if ( is_string( $v ) ) { $profile[$k] = str_replace( "\n", 'n', $v ); } }
    if ( $change ) { $profile['intro'] .= ' ویرایش مستقل کاربر'; }
    $id = wp_insert_post( array( 'post_type' => 'disease', 'post_status' => $status, 'post_title' => 'Synthetic fixture' ), true );
    fixture_assert( ! is_wp_error( $id ) && $id, 'post creation' );
    update_post_meta( $id, HJNR_ID_META, $reference_id );
    update_post_meta( $id, HJNR_PROFILE_META, wp_slash( wp_json_encode( $profile, JSON_UNESCAPED_UNICODE ) ) );
    return $id;
}
$report = array();
$p = fixture_post( 'app' );
$before = get_post_meta( $p, HJNR_PROFILE_META, true );
$candidate = hjnr_candidate( $p );
fixture_assert( $candidate && count( $candidate['fields'] ) > 0, 'valid candidate not detected' );
fixture_assert( 'conflict' === hjnr_apply( $p, str_repeat( '0', 64 ) ), 'stale preview must fail' );
fixture_assert( ! metadata_exists( 'post', $p, HJNR_BACKUP_META ), 'stale preview should not create backup' );
$preview_hash = hash( 'sha256', $candidate['before'] . "\0" . $candidate['after'] );
fixture_assert( 'repaired' === hjnr_apply( $p, $preview_hash ), 'apply failed' );
$fixed = json_decode( get_post_meta( $p, HJNR_PROFILE_META, true ), true );
fixture_assert( $fixed === hjnr_reference()['app'], 'repair did not restore exact profile' );
fixture_assert( metadata_exists( 'post', $p, HJNR_BACKUP_META ), 'backup absent' );
fixture_assert( null === hjnr_candidate( $p ), 'backup should prevent reapplication' );
fixture_assert( 'restored' === hjnr_restore( $p ), 'restore failed' );
fixture_assert( get_post_meta( $p, HJNR_PROFILE_META, true ) === $before, 'restore not exact' );
$report['repair_and_restore'] = true;
$edited = fixture_post( 'kidney_stone', 'publish', true );
$edited_before = get_post_meta( $edited, HJNR_PROFILE_META, true );
fixture_assert( null === hjnr_candidate( $edited ) && 'skipped' === hjnr_apply( $edited ), 'edited profile must be skipped' );
fixture_assert( $edited_before === get_post_meta( $edited, HJNR_PROFILE_META, true ), 'edited profile changed' );
$report['edited_profiles_untouched'] = true;
$private = fixture_post( 'acne', 'private' );
fixture_assert( null === hjnr_candidate( $private ), 'private post must be excluded' );
$report['private_excluded'] = true;
$duplicate_a = fixture_post( 'migraine' ); $duplicate_b = fixture_post( 'migraine', 'draft' );
fixture_assert( null === hjnr_candidate( $duplicate_a ), 'duplicate internal identifier must be excluded' );
$report['duplicate_ids_excluded'] = true;
$multi = fixture_post( 'diabetes' ); add_post_meta( $multi, HJNR_PROFILE_META, 'extra profile row' );
fixture_assert( null === hjnr_candidate( $multi ), 'duplicate meta rows must be excluded' );
$report['duplicate_meta_excluded'] = true;
$changed = fixture_post( 'flu' ); fixture_assert( 'repaired' === hjnr_apply( $changed ), 'second repair failed' );
$fixed_profile = get_post_meta( $changed, HJNR_PROFILE_META, true );
update_post_meta( $changed, HJNR_PROFILE_META, wp_slash( $fixed_profile . ' user edit' ) );
fixture_assert( 'conflict' === hjnr_restore( $changed ), 'restore must reject later user edit' );
fixture_assert( get_post_meta( $changed, HJNR_PROFILE_META, true ) === $fixed_profile . ' user edit', 'later edit was overwritten' );
$report['restore_conflict_preserves_edits'] = true;
$id_changed = fixture_post( 'kidney_infection' );
fixture_assert( 'repaired' === hjnr_apply( $id_changed ), 'third repair failed' );
update_post_meta( $id_changed, HJNR_ID_META, 'changed_identifier' );
fixture_assert( 'conflict' === hjnr_restore( $id_changed ), 'restore must reject changed identifier' );
$report['restore_conflict_on_changed_identifier'] = true;
fixture_assert( false !== has_action( 'admin_post_hjnr_apply' ) && false !== has_action( 'admin_post_hjnr_restore' ), 'admin actions not registered' );
$report['admin_actions_registered'] = true;
if ( is_readable( '/mnt/snapshot/snapshot.json' ) ) {
    $snapshot = json_decode( file_get_contents( '/mnt/snapshot/snapshot.json' ), true );
    $matched = 0; $examined = 0; $refs = hjnr_reference();
    foreach ( $snapshot['articles'] as $row ) {
        if ( 'disease' !== $row['post_type'] || 'publish' !== $row['status'] ) { continue; }
        $examined++;
        $id = $row['meta']['_hj_disease_seed_id'] ?? '';
        $current = json_decode( $row['meta']['_hj_disease_profile'] ?? '', true );
        if ( isset( $refs[$id] ) && hjnr_plan( $current, $refs[$id] ) ) { $matched++; }
    }
    $report['snapshot_examined'] = $examined;
    $report['snapshot_exact_candidates'] = $matched;
}
file_put_contents( '/wordpress/hjnr-test-result.json', wp_json_encode( $report ) );

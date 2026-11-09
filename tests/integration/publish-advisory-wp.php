<?php
/** Real WordPress/MariaDB test in disposable GitHub Actions fixture only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 1 ); }
require_once getenv( 'GITHUB_WORKSPACE' ) . '/themes/lax-theme-child/inc/publish-gate.php';
require_once getenv( 'GITHUB_WORKSPACE' ) . '/themes/lax-theme-child/inc/article-importer.php';
foreach ( array( 'patient_guide', 'surgery' ) as $type ) {
    register_post_type( $type, array( 'public' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ) ) );
}
wp_set_current_user( 1 );
function advisory_assert( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } echo "PASS $message\n"; }
foreach ( array( 'patient_guide', 'surgery' ) as $type ) {
    $draft = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Fixture', 'post_content' => 'Short' ), true );
    advisory_assert( ! is_wp_error( $draft ) && 'draft' === get_post_status( $draft ), "$type draft stays draft" );
    $result = hj_gate_checks( 'Short', $draft, $type );
    advisory_assert( $result['fails'] >= 3, "$type missing requirements detected" );
    $updated = wp_update_post( array( 'ID' => $draft, 'post_status' => 'publish' ), true );
    advisory_assert( ! is_wp_error( $updated ) && 'publish' === get_post_status( $draft ), "$type draft publishes without override" );
    wp_update_post( array( 'ID' => $draft, 'post_title' => 'Updated', 'post_status' => 'publish' ) );
    advisory_assert( 'publish' === get_post_status( $draft ), "$type published update stays published" );
    $new = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Direct', 'post_content' => 'Short' ), true );
    advisory_assert( ! is_wp_error( $new ) && 'publish' === get_post_status( $new ), "$type direct publish" );
    $scheduled = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'post_title' => 'Scheduled', 'post_content' => 'Short' ), true );
    wp_publish_post( $scheduled );
    advisory_assert( 'publish' === get_post_status( $scheduled ), "$type scheduled publish" );
    $request = new WP_REST_Request( 'POST', '/wp/v2/' . $type );
    $request->set_body_params( array( 'title' => 'REST', 'content' => 'Short', 'status' => 'publish' ) );
    $response = rest_get_server()->dispatch( $request );
    advisory_assert( 201 === $response->get_status() && 'publish' === $response->get_data()['status'], "$type REST publish" );
}
$zip_path = tempnam( sys_get_temp_dir(), 'advisory-' );
$zip = new ZipArchive(); $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
$zip->addFromString( 'article.json', wp_json_encode( array( 'slug' => 'advisory-fixture', 'title' => 'Fixture', 'post_type' => 'patient_guide', 'post_status' => 'publish' ) ) );
$zip->addFromString( 'content.html', '<p>Short</p>' ); $zip->close();
update_option( 'hj_importer_default_options', array( 'status_mode' => 'publish', 'keep_existing_status' => 0, 'draft_on_deficiency' => 1 ) );
$options = array( 'status_mode' => 'publish', 'draft_on_deficiency' => true, 'purge_cache' => false );
$preview = hj_importer_import_package( $zip_path, 'test', array_merge( $options, array( 'dry_run' => true ) ) );
advisory_assert( ! is_wp_error( $preview ) && 'publish' === $preview['post_status'] && ! empty( $preview['audit']['critical'] ), 'import preview: deficiencies warn, old shield ignored' );
$import = hj_importer_import_package( $zip_path, 'test', $options );
advisory_assert( ! is_wp_error( $import ) && 'publish' === get_post_status( $import['post_id'] ) && ! empty( $import['audit']['critical'] ), 'import publish: deficiencies warn, old shield ignored' );
ob_start(); hj_gate_render_meta_box( get_post( $import['post_id'] ) ); $markup = ob_get_clean();
advisory_assert( false !== strpos( $markup, 'مانع انتشار نمی‌شوند' ) && false === strpos( $markup, 'hj_gate_override' ), 'warning box is advisory without override checkbox' );
@unlink( $zip_path );

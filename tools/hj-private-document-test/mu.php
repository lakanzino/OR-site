<?php
/** CI-only test adapter; this file is never distributed as part of the plugin. */
if ( getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' || DB_NAME !== 'hj_fixture_db' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
$mode = getenv('HJ_DOC_FIXTURE_MODE');
if (in_array($mode,array('verified','unverified','inside','symlink','profilelink','disabled-read'),true)) {
    $base = $mode === 'inside' ? ABSPATH . 'wp-content/uploads/hj-private-test' : getenv('RUNNER_TEMP') . ($mode === 'symlink' ? '/hj-doc-fixture-symlink' : '/hj-doc-fixture-private');
    define('HJ_PRIVATE_DOCUMENT_DIR',$base);
    if (!in_array($mode,array('unverified','disabled-read'),true)) { define('HJ_PRIVATE_DOCUMENT_STORAGE_VERIFIED',true); }
}
add_action('wp_ajax_hj_ci_doc_info',function() {
    $pid=(int)($_POST['pid']??0);
    if (!hj_doctors_user_can_edit_profile($pid) && !hj_doctors_user_can_manage('approve')) { wp_send_json_error(array(),403); }
    wp_send_json_success(array('nonce'=>wp_create_nonce('hj_dash_nonce'),'docs'=>get_post_meta($pid,'hj_private_docs',true)));
});
add_action('wp_ajax_hj_ci_doc_nonce',function() {
    $pid=(int)($_POST['pid']??0); $id=sanitize_key(wp_unslash($_POST['doc']??''));
    wp_send_json_success(array('nonce'=>wp_create_nonce('hj_doc_dl_'.$pid.'_'.$id)));
});
add_action('wp_ajax_hj_ci_dash_nonce',function() { wp_send_json_success(array('nonce'=>wp_create_nonce('hj_dash_nonce'))); });
// Deliberately fail only this synthetic request's metadata write.
add_filter('update_post_metadata',function($check,$object_id,$key) {
    return $key==='hj_private_docs' && isset($_POST['hj_ci_fail_meta']) ? false : $check;
},10,3);

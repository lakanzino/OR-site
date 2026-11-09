<?php
/** Disposable research only. Atomically publishes ONE scalar meta field, not a profile. */
if (!defined('ABSPATH') || !defined('HJ_PROFILE_REVISION_SPIKE') || HJ_PROFILE_REVISION_SPIKE !== true) { exit; }
final class HJ_Profile_Spike_Publish_One {
    private static function database() {
        global $wpdb;
        if (get_class($wpdb)!=='wpdb') { return null; }
        foreach (array($wpdb->posts,$wpdb->postmeta,$wpdb->options) as $table) { if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) { return null; } }
        $db=new HJ_Profile_Spike_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true);
        foreach (array($wpdb->posts,$wpdb->postmeta,$wpdb->options) as $table) {
            $engine=$db->get_var($db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
            if ($db->last_error || !is_string($engine) || strcasecmp($engine,'InnoDB')!==0) { $db->close(); return null; }
        }
        if ($db->query('SET SESSION innodb_lock_wait_timeout = 3')===false) { $db->close(); return null; }
        return $db;
    }
    private static function snapshot($db,$pid,$lock) {
        global $wpdb;
        $suffix=$lock ? ' FOR UPDATE' : '';
        $post=$db->get_row($db->prepare("SELECT ID,post_title,post_status,post_type FROM `{$wpdb->posts}` WHERE ID=%d".$suffix,$pid),ARRAY_A);
        if ($db->last_error || !is_array($post) || $post['post_type']!=='doctor' || $post['post_status']!=='publish') { return null; }
        $meta=$db->get_results($db->prepare("SELECT meta_id,meta_value FROM `{$wpdb->postmeta}` WHERE post_id=%d AND meta_key=%s ORDER BY meta_id".$suffix,$pid,'name_fa'),ARRAY_A);
        if ($db->last_error || !is_array($meta) || count($meta)!==1 || !is_string($meta[0]['meta_value'])) { return null; }
        $json=wp_json_encode(array('schema'=>1,'id'=>(int)$post['ID'],'title'=>$post['post_title'],'status'=>$post['post_status'],'meta_id'=>(int)$meta[0]['meta_id'],'name_fa'=>$meta[0]['meta_value']),JSON_UNESCAPED_UNICODE);
        return is_string($json) ? array('hash'=>hash('sha256',$json),'meta_id'=>(int)$meta[0]['meta_id'],'name'=>$meta[0]['meta_value']) : null;
    }
    public static function baseline($pid) {
        if (!is_user_logged_in() || !(hj_doctors_user_can_edit_profile($pid) || hj_doctors_user_can_manage('approve'))) { return array('status'=>'forbidden'); }
        $db=self::database(); if (!$db) { return array('status'=>'unavailable'); }
        try { $snap=self::snapshot($db,(int)$pid,false); return $snap ? array('status'=>'ok','hash'=>$snap['hash']) : array('status'=>'unavailable'); }
        finally { $db->close(); }
    }
    public static function approve($pid,$generation,$atomic_rest_version=false) {
        global $wpdb;
        $pid=(int)$pid;
        if (!is_user_logged_in() || !hj_doctors_user_can_manage('approve')) { return array('status'=>'forbidden'); }
        if (!is_string($generation) || !preg_match('/^[a-f0-9]{32}$/D',$generation)) { return array('status'=>'invalid'); }
        $db=self::database(); if (!$db) { return array('status'=>'unavailable'); } $active=false;
        try {
            if ($db->query('START TRANSACTION')===false) { return array('status'=>'unavailable'); } $active=true;
            $key='hj_profile_spike_'.$pid;
            do_action('hj_spike_publish_before_proposal_lock',$pid,$db);
            $raw=$db->get_var($db->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s FOR UPDATE",$key));
            if ($db->last_error || !is_string($raw)) { return array('status'=>'unavailable'); }
            $draft=json_decode($raw,true);
            if (!is_array($draft) || ($draft['schema']??null)!==1 || ($draft['state']??null)!=='submitted' || ($draft['generation']??null)!==$generation || !is_string($draft['base_hash']??null) || !preg_match('/^[a-f0-9]{64}$/D',$draft['base_hash']) || !is_string($draft['fields']['name_fa']??null)) { return array('status'=>'conflict'); }
            $name=$draft['fields']['name_fa'];
            if ($name==='' || strlen($name)>320 || sanitize_text_field($name)!==$name) { return array('status'=>'invalid'); }
            $snap=self::snapshot($db,$pid,true); if (!$snap) { return array('status'=>'unavailable'); }
            if (!hash_equals($draft['base_hash'],$snap['hash'])) { return array('status'=>'public_changed'); }
            do_action('hj_spike_publish_locked',$pid,$db);
            if ($name!==$snap['name']) {
                $changed=$db->query($db->prepare("UPDATE `{$wpdb->postmeta}` SET meta_value=%s WHERE meta_id=%d",$name,$snap['meta_id']));
                if ($changed!==1) { return array('status'=>'unknown'); }
            }
            $draft['state']='applied'; $draft['generation']=bin2hex(random_bytes(16));
            $new=wp_json_encode($draft,JSON_UNESCAPED_UNICODE); if (!is_string($new)) { return array('status'=>'invalid'); }
            $changed=$db->query($db->prepare("UPDATE `{$wpdb->options}` SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$new,$key,$raw));
            if ($changed!==1) { return array('status'=>'unknown'); }
            // Alternative experiment only: bump REST version *within* the same transaction.
            // This still bypasses WP hooks and cannot guarantee persistent cache coherence.
            if ($atomic_rest_version) {
                if (!class_exists('HJ_Doctors_REST')) { return array('status'=>'unavailable'); }
                $ver_key=HJ_Doctors_REST::VER_OPTION;
                $old=$db->get_var($db->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s FOR UPDATE",$ver_key));
                if ($db->last_error || !is_string($old) || $old==='') { return array('status'=>'unavailable'); }
                $ver=bin2hex(random_bytes(16));
                if ($db->query($db->prepare("UPDATE `{$wpdb->options}` SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$ver,$ver_key,$old))!==1) { return array('status'=>'unknown'); }
            }
            do_action('hj_spike_publish_before_commit',$pid,$db);
            if ($db->query('COMMIT')===false) { $active=false; return array('status'=>'unknown'); }
            $active=false;
            if ($atomic_rest_version) { wp_cache_delete('alloptions','options'); wp_cache_delete(HJ_Doctors_REST::VER_OPTION,'options'); }
            wp_cache_delete($pid,'post_meta');
            if (function_exists('acf_flush_value_cache')) { acf_flush_value_cache($pid,'name_fa'); }
            return array('status'=>'applied');
        } finally { if ($active) { $db->query('ROLLBACK'); } $db->close(); }
    }
}

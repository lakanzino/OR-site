<?php
/** CI-only role-row lock experiment, NOT production authorization. */
if (!defined('ABSPATH') || !defined('HJ_PROFILE_REVISION_SPIKE') || HJ_PROFILE_REVISION_SPIKE !== true) { exit; }
function hj_spike_guarded_role_write($uid,$option) {
    global $wpdb;
    $uid=(int)$uid;
    if ($uid!==get_current_user_id() || !hj_doctors_is_doctor_user() || !preg_match('/^hj_spike_guard_[0-9]+$/D',$option)) { return array('status'=>'forbidden'); }
    if (get_class($wpdb)!=='wpdb' || !preg_match('/^[A-Za-z0-9_]+$/D',$wpdb->usermeta) || !preg_match('/^[A-Za-z0-9_]+$/D',$wpdb->options)) { return array('status'=>'unavailable'); }
    $db=new HJ_Profile_Spike_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $active=false;
    try {
        foreach (array($wpdb->usermeta,$wpdb->options) as $table) {
            $engine=$db->get_var($db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
            if ($db->last_error || !is_string($engine) || strcasecmp($engine,'InnoDB')!==0) { return array('status'=>'unavailable'); }
        }
        if ($db->query('SET SESSION innodb_lock_wait_timeout = 3')===false || $db->query('START TRANSACTION')===false) { return array('status'=>'unavailable'); }
        $active=true;
        do_action('hj_spike_role_before_lock',$db);
        $rows=$db->get_results($db->prepare("SELECT umeta_id,meta_value FROM `{$wpdb->usermeta}` WHERE user_id=%d AND meta_key=%s FOR UPDATE",$uid,$wpdb->prefix.'capabilities'),ARRAY_A);
        if ($db->last_error || !is_array($rows) || count($rows)!==1) { return array('status'=>'unavailable'); }
        $roles=maybe_unserialize($rows[0]['meta_value']);
        if (!is_array($roles) || empty($roles['doctor'])) { return array('status'=>'forbidden'); }
        do_action('hj_spike_role_after_lock',$db);
        $changed=$db->query($db->prepare("UPDATE `{$wpdb->options}` SET option_value=%s WHERE option_name=%s AND option_value=%s",'saved',$option,'old'));
        if ($changed!==1) { return array('status'=>$changed===false?'unknown':'conflict'); }
        if ($db->query('COMMIT')===false) { $active=false; return array('status'=>'unknown'); }
        $active=false; return array('status'=>'saved');
    } finally {
        if ($active) { $db->query('ROLLBACK'); }
        $db->close();
    }
}

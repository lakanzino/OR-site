<?php
/** Test-only comparison. Never publish on the strength of this preflight. */
if (!defined('ABSPATH') || !defined('HJ_PROFILE_REVISION_SPIKE') || HJ_PROFILE_REVISION_SPIKE !== true) { exit; }
final class HJ_Profile_Spike_Preflight {
    public static function inspect($pid,$generation) {
        $pid=(int)$pid;
        if (!is_user_logged_in() || !hj_doctors_user_can_manage('approve')) { return array('status'=>'forbidden'); }
        if (!is_string($generation) || !preg_match('/^[a-f0-9]{32}$/D',$generation)) { return array('status'=>'invalid'); }
        $draft=HJ_Profile_Spike_Store::read($pid);
        if ($draft['status']!=='ok') { return array('status'=>$draft['status']); }
        if ($draft['data']['state']!=='submitted' || $draft['data']['generation']!==$generation) { return array('status'=>'conflict'); }
        $now=HJ_Profile_Spike_Snapshot::fingerprint($pid);
        if ($now['status']!=='ok') { return array('status'=>$now['status']); }
        if (!hash_equals($draft['data']['base_hash'],$now['hash'])) { return array('status'=>'public_changed'); }
        // Not permission to publish: the current state can change immediately.
        return array('status'=>'matches_partial_snapshot');
    }
}

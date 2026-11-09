<?php
/**
 * CI-only partial fingerprint experiment. Not an authorization boundary for
 * production and not a substitute for a lock. Omits schedules and many other
 * public writer paths. No endpoint and no plugin integration.
 */
if (!defined('ABSPATH') || !defined('HJ_PROFILE_REVISION_SPIKE') || HJ_PROFILE_REVISION_SPIKE !== true) { exit; }
final class HJ_Profile_Spike_Snapshot {
    public static function fingerprint($pid) {
        $pid=(int)$pid;
        if (!is_user_logged_in() || get_post_type($pid)!=='doctor' || get_post_status($pid)!=='publish') { return array('status'=>'forbidden'); }
        $owner=hj_doctors_is_doctor_user() && hj_doctors_user_can_edit_profile($pid);
        if (!$owner && !hj_doctors_user_can_manage('approve')) { return array('status'=>'forbidden'); }
        $post=get_post($pid);
        if (!$post instanceof WP_Post) { return array('status'=>'unavailable'); }
        $fields=array('name_fa','name_en','specialty_fa','specialty_en','bio_fa','bio_en','medical_license','experience_years','education','surgeries_performed','phone_clinic','phone_mobile','address_clinic','address_hospital','map_link','profile_photo');
        $snapshot=array('schema'=>1,'pid'=>$pid,'title'=>$post->post_title,'status'=>$post->post_status,'fields'=>array(),'thumbnail'=>(int)get_post_thumbnail_id($pid),'terms'=>array());
        foreach ($fields as $key) {
            // An ACF-formatted view can differ from raw post meta. Include both so
            // a raw change is not hidden by that difference in this experiment.
            $raw=get_post_meta($pid,$key,true);
            $value=function_exists('get_field') ? get_field($key,$pid) : false;
            if ($value===false || $value===null) { $value=$raw; }
            if ((!is_scalar($value) && $value!==null) || (!is_scalar($raw) && $raw!==null)) { return array('status'=>'unavailable'); }
            $snapshot['fields'][$key]=array('raw'=>(string)$raw,'view'=>(string)$value);
        }
        foreach (array('specialty_type','specialty','province','city') as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) { return array('status'=>'unavailable'); }
            $ids=wp_get_object_terms($pid,$taxonomy,array('fields'=>'ids'));
            if (is_wp_error($ids) || !is_array($ids)) { return array('status'=>'unavailable'); }
            $ids=array_map('intval',$ids); sort($ids,SORT_NUMERIC); $snapshot['terms'][$taxonomy]=$ids;
        }
        $encoded=wp_json_encode($snapshot,JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) { return array('status'=>'unavailable'); }
        return array('status'=>'ok','hash'=>hash('sha256',$encoded));
    }
}

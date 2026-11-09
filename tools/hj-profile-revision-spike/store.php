<?php
/**
 * DISPOSABLE RESEARCH ONLY. Not loaded by the plugin, not a release candidate.
 * Atomic single-row draft store; does not publish, handle photos, schedules,
 * taxonomy, moderation, HTTP, CSRF, or validate complete profile input.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'HJ_PROFILE_REVISION_SPIKE' ) || HJ_PROFILE_REVISION_SPIKE !== true ) { exit; }

class HJ_Profile_Spike_DB extends wpdb {
    public function check_connection( $allow_bail = true ) { return false; }
}

final class HJ_Profile_Spike_Store {
    private static function authorized( $pid, $write ) {
        if ( ! is_user_logged_in() || get_post_type( $pid ) !== 'doctor' || get_post_status( $pid ) !== 'publish' ) { return false; }
        $owner = hj_doctors_is_doctor_user() && hj_doctors_user_can_edit_profile( $pid );
        return $write ? $owner : ( $owner || hj_doctors_user_can_manage( 'approve' ) );
    }
    private static function db() {
        global $wpdb;
        if ( get_class( $wpdb ) !== 'wpdb' || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $wpdb->options ) ) { return null; }
        $db = new HJ_Profile_Spike_DB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
        $db->suppress_errors( true );
        $engine = $db->get_var( $db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options ) );
        if ( $db->last_error || ! is_string( $engine ) || strcasecmp( $engine, 'InnoDB' ) !== 0 ) { $db->close(); return null; }
        $indexes = $db->get_results( "SHOW INDEX FROM `{$wpdb->options}`", ARRAY_A );
        if ( $db->last_error || ! is_array( $indexes ) ) { $db->close(); return null; }
        $keys = array();
        foreach ( $indexes as $index ) { if ( (int) $index['Non_unique'] === 0 ) { $keys[$index['Key_name']][] = $index; } }
        $unique = false;
        foreach ( $keys as $parts ) { if ( count($parts) === 1 && $parts[0]['Column_name'] === 'option_name' && $parts[0]['Sub_part'] === null ) { $unique = true; break; } }
        if ( ! $unique || false === $db->query('SET autocommit = 1') || false === $db->query('SET SESSION innodb_lock_wait_timeout = 3') ) { $db->close(); return null; }
        return $db;
    }
    private static function key( $pid ) { return 'hj_profile_spike_' . (int) $pid; }
    private static function text_payload( $fields ) {
        $allowed = array('name_fa','name_en','specialty_fa','specialty_en','bio_fa','bio_en','medical_license','experience_years','education','surgeries_performed','phone_clinic','phone_mobile','address_clinic','address_hospital','map_link');
        if ( ! is_array($fields) || array_diff(array_keys($fields), $allowed) || count($fields) !== count($allowed) ) { return false; }
        $out = array();
        foreach ($allowed as $key) {
            if ( ! array_key_exists($key,$fields) || ! is_string($fields[$key]) || strlen($fields[$key]) > 28000 || ! preg_match('//u', $fields[$key]) ) { return false; }
            $out[$key] = $fields[$key];
        }
        $encoded = wp_json_encode($out, JSON_UNESCAPED_UNICODE);
        return is_string($encoded) && strlen($encoded) <= 65536 ? $out : false;
    }
    private static function raw( $db, $pid ) {
        global $wpdb;
        $raw = $db->get_var( $db->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s", self::key($pid)) );
        if ( $db->last_error ) { return array('status'=>'unknown'); }
        if ( $raw === null ) { return array('status'=>'missing'); }
        $data = json_decode($raw, true);
        if ( ! is_array($data) || ($data['schema'] ?? null) !== 1 || ! in_array($data['state'] ?? '', array('draft','submitted','rejected','applied'), true) || ! is_string($data['generation'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $data['generation']) || ! is_string($data['base_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $data['base_hash']) || self::text_payload($data['fields'] ?? null) === false || ( isset($data['review_note']) && (!is_string($data['review_note']) || strlen($data['review_note'])>2000) ) ) { return array('status'=>'corrupt'); }
        return array('status'=>'ok','raw'=>$raw,'data'=>$data);
    }
    public static function read( $pid ) {
        $pid = (int) $pid;
        if (! self::authorized($pid, false)) { return array('status'=>'forbidden'); }
        $db=self::db(); if (!$db) { return array('status'=>'unavailable'); }
        try { $r=self::raw($db,$pid); unset($r['raw']); return $r; } finally { $db->close(); }
    }
    public static function create( $pid, $base_hash, $fields ) {
        global $wpdb;
        $pid=(int)$pid;
        if (! self::authorized($pid,true)) { return array('status'=>'forbidden'); }
        $fields=self::text_payload($fields);
        if ($fields === false || ! is_string($base_hash) || ! preg_match('/^[a-f0-9]{64}$/D',$base_hash)) { return array('status'=>'invalid'); }
        $data=array('schema'=>1,'generation'=>bin2hex(random_bytes(16)),'state'=>'draft','base_hash'=>$base_hash,'fields'=>$fields);
        $json=wp_json_encode($data,JSON_UNESCAPED_UNICODE); if (! is_string($json)) { return array('status'=>'invalid'); }
        $db=self::db(); if (!$db) { return array('status'=>'unavailable'); }
        try {
            $n=$db->query($db->prepare("INSERT IGNORE INTO `{$wpdb->options}` (option_name,option_value,autoload) VALUES (%s,%s,'no')",self::key($pid),$json));
            if ($n === false) { return array('status'=>'unknown'); }
            return $n === 1 ? array('status'=>'saved','data'=>$data) : array('status'=>'conflict');
        } finally { $db->close(); }
    }
    /** Reject only the exact submitted generation; does not publish anything. */
    public static function reject( $pid, $expected, $note ) {
        global $wpdb;
        $pid=(int)$pid;
        if ( ! is_user_logged_in() || get_post_type($pid)!=='doctor' || get_post_status($pid)!=='publish' || ! hj_doctors_user_can_manage('approve') ) { return array('status'=>'forbidden'); }
        if ( ! is_string($expected) || ! preg_match('/^[a-f0-9]{32}$/D',$expected) || ! is_string($note) || strlen($note)>2000 || ! preg_match('//u',$note) ) { return array('status'=>'invalid'); }
        $db=self::db(); if (!$db) { return array('status'=>'unavailable'); }
        try {
            $current=self::raw($db,$pid); if ($current['status']!=='ok') { return array('status'=>$current['status']); }
            $data=$current['data'];
            if ($data['generation']!==$expected || $data['state']!=='submitted') { return array('status'=>'conflict'); }
            $data['generation']=bin2hex(random_bytes(16)); $data['state']='rejected'; $data['review_note']=sanitize_textarea_field($note);
            $json=wp_json_encode($data,JSON_UNESCAPED_UNICODE); if (!is_string($json)) { return array('status'=>'invalid'); }
            do_action('hj_profile_spike_before_cas',$pid,$db);
            $n=$db->query($db->prepare("UPDATE `{$wpdb->options}` SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$json,self::key($pid),$current['raw']));
            if ($n===false) { return array('status'=>'unknown'); }
            if ($n===1) { do_action('hj_profile_spike_after_cas',$pid,$db); }
            return $n===1 ? array('status'=>'saved','data'=>$data) : array('status'=>'conflict');
        } finally { $db->close(); }
    }
    /**
     * Research-only receipt experiment for a text edit, not submission or approval.
     * Receipts are never silently evicted; capacity exhaustion fails closed.
     */
    public static function change_once( $pid, $expected, $fields, $operation ) {
        global $wpdb;
        $pid=(int)$pid;
        if (!self::authorized($pid,true)) { return array('status'=>'forbidden'); }
        if (!is_string($expected) || !preg_match('/^[a-f0-9]{32}$/D',$expected) || !is_string($operation) || !preg_match('/^[a-f0-9]{32}$/D',$operation)) { return array('status'=>'invalid'); }
        $fields=self::text_payload($fields); if ($fields===false) { return array('status'=>'invalid'); }
        $sig=hash('sha256',wp_json_encode(array('edit',get_current_user_id(),$pid,$expected,$fields),JSON_UNESCAPED_UNICODE));
        $key=hash('sha256',$operation);
        $db=self::db(); if (!$db) { return array('status'=>'unavailable'); }
        try {
            $current=self::raw($db,$pid); if ($current['status']!=='ok') { return array('status'=>$current['status']); }
            $data=$current['data']; $receipts=$data['receipts'] ?? array();
            if (!is_array($receipts) || count($receipts)>32) { return array('status'=>'corrupt'); }
            foreach ($receipts as $receipt) {
                if (!is_array($receipt) || !is_string($receipt['key'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$receipt['key']) || !is_string($receipt['signature'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$receipt['signature']) || !is_string($receipt['generation'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D',$receipt['generation'])) { return array('status'=>'corrupt'); }
                if (hash_equals($receipt['key'],$key)) { return hash_equals($receipt['signature'],$sig) ? array('status'=>'replayed','generation'=>$receipt['generation']) : array('status'=>'key_conflict'); }
            }
            if (count($receipts)===32) { return array('status'=>'receipt_capacity'); }
            if ($data['generation']!==$expected || !in_array($data['state'],array('draft','rejected'),true)) { return array('status'=>'conflict'); }
            $data['fields']=$fields; $data['state']='draft'; unset($data['review_note']);
            $data['generation']=bin2hex(random_bytes(16));
            $data['receipts'][]=array('key'=>$key,'signature'=>$sig,'generation'=>$data['generation']);
            $json=wp_json_encode($data,JSON_UNESCAPED_UNICODE); if (!is_string($json)) { return array('status'=>'invalid'); }
            do_action('hj_profile_spike_before_cas',$pid,$db);
            $n=$db->query($db->prepare("UPDATE `{$wpdb->options}` SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$json,self::key($pid),$current['raw']));
            if ($n===false) { return array('status'=>'unknown'); }
            if ($n===1) { do_action('hj_profile_spike_after_cas',$pid,$db); return array('status'=>'saved','generation'=>$data['generation']); }
            // A concurrent identical request may have just stored this receipt.
            $current=self::raw($db,$pid); if ($current['status']!=='ok') { return array('status'=>$current['status']); }
            $receipts=$current['data']['receipts'] ?? array();
            if (!is_array($receipts)) { return array('status'=>'corrupt'); }
            foreach ($receipts as $receipt) {
                if (!is_array($receipt) || !is_string($receipt['key'] ?? null) || !is_string($receipt['signature'] ?? null) || !is_string($receipt['generation'] ?? null)) { return array('status'=>'corrupt'); }
                if (hash_equals($receipt['key'],$key)) { return hash_equals($receipt['signature'],$sig) ? array('status'=>'replayed','generation'=>$receipt['generation']) : array('status'=>'key_conflict'); }
            }
            return array('status'=>'conflict');
        } finally { $db->close(); }
    }
    public static function change( $pid, $expected, $fields, $submit = false ) {
        global $wpdb;
        $pid=(int)$pid;
        if (! self::authorized($pid,true)) { return array('status'=>'forbidden'); }
        if (! is_string($expected) || ! preg_match('/^[a-f0-9]{32}$/D',$expected) || ! is_bool($submit)) { return array('status'=>'invalid'); }
        if (!$submit) { $fields=self::text_payload($fields); if ($fields===false) { return array('status'=>'invalid'); } }
        $db=self::db(); if (!$db) { return array('status'=>'unavailable'); }
        try {
            $current=self::raw($db,$pid); if ($current['status'] !== 'ok') { return array('status'=>$current['status']); }
            $data=$current['data'];
            if ($data['generation'] !== $expected || ! in_array($data['state'],array('draft','rejected'),true)) { return array('status'=>'conflict'); }
            if ($submit) { $data['state']='submitted'; } else { $data['fields']=$fields; $data['state']='draft'; }
            unset($data['review_note']);
            $data['generation']=bin2hex(random_bytes(16));
            $json=wp_json_encode($data,JSON_UNESCAPED_UNICODE); if (! is_string($json)) { return array('status'=>'invalid'); }
            // The hook exists only in this isolated spike, for a deterministic race barrier.
            do_action('hj_profile_spike_before_cas',$pid,$db);
            $n=$db->query($db->prepare("UPDATE `{$wpdb->options}` SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$json,self::key($pid),$current['raw']));
            if ($n===false) { return array('status'=>'unknown'); }
            if ($n===1) { do_action('hj_profile_spike_after_cas',$pid,$db); }
            return $n===1 ? array('status'=>'saved','data'=>$data) : array('status'=>'conflict');
        } finally { $db->close(); }
    }
}

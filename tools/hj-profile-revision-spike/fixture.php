<?php
/** Only a disposable GitHub Actions fixture with synthetic data. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
define('HJ_PROFILE_REVISION_SPIKE', true);
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_' || HJ_DOCTORS_VERSION !== (getenv('HJ_SPIKE_VERSION') ?: '0.3.22') ) { exit(1); }
require getenv('GITHUB_WORKSPACE') . '/tools/hj-profile-revision-spike/store.php';
function spike_assert($condition,$message) { if (!$condition) { throw new RuntimeException($message); } }
function spike_fields($name) {
    return array('name_fa'=>$name,'name_en'=>'','specialty_fa'=>'جراحی نمونه','specialty_en'=>'','bio_fa'=>'','bio_en'=>'','medical_license'=>'','experience_years'=>'','education'=>'','surgeries_performed'=>'','phone_clinic'=>'','phone_mobile'=>'','address_clinic'=>'','address_hospital'=>'','map_link'=>'');
}
$mode=$argv[1] ?? '';
if ($mode === 'setup') {
    $uid=get_user_by('login','surgeon_fixture')->ID; $other=get_user_by('login','normal_fixture')->ID; $admin=get_user_by('login','fixture_admin')->ID;
    $pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'جراح آزمایشی','post_author'=>$uid),true);
    spike_assert(!is_wp_error($pid) && $pid>0,'fixture profile creation failed');
    update_post_meta($pid,'name_fa','نام عمومی');
    wp_set_current_user($uid);
    $f=spike_fields('نسخه آزمایشی'); $base=str_repeat('a',64);
    spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='missing','initial draft missing');
    spike_assert(HJ_Profile_Spike_Store::create($pid,$base,array('name_fa'=>'x'))['status']==='invalid','partial fields rejected');
    $r=HJ_Profile_Spike_Store::create($pid,$base,$f); spike_assert($r['status']==='saved','create synthetic draft');
    $token=$r['data']['generation'];
    spike_assert(HJ_Profile_Spike_Store::create($pid,$base,$f)['status']==='conflict','duplicate draft rejected');
    spike_assert(get_post_meta($pid,'name_fa',true)==='نام عمومی' && get_post_status($pid)==='publish','public data remains untouched');
    wp_set_current_user($other);
    spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='forbidden','another surgeon cannot read');
    spike_assert(HJ_Profile_Spike_Store::change($pid,$token,$f)['status']==='forbidden','another surgeon cannot write');
    wp_set_current_user(0); spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='forbidden','guest cannot read');
    wp_set_current_user($admin); spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='ok','approver may read');
    spike_assert(HJ_Profile_Spike_Store::create($pid,$base,$f)['status']==='forbidden','admin is not author of proposal');
    wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::change($pid,$token,spike_fields('ویرایش شده')); spike_assert($r['status']==='saved','edit draft');
    spike_assert(HJ_Profile_Spike_Store::change($pid,$token,$f)['status']==='conflict','stale writer rejected');
    $token=$r['data']['generation'];
    echo json_encode(array('pid'=>$pid,'uid'=>$uid,'token'=>$token),JSON_UNESCAPED_UNICODE),"\n"; exit;
}
if ($mode === 'worker') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $token=$argv[4]; $dir=$argv[5]; $label=$argv[6];
    if (!preg_match('/^[ab]$/D',$label) || !is_dir($dir)) { exit(1); }
    wp_set_current_user($uid);
    add_action('hj_profile_spike_before_cas',function() use ($dir,$label) {
        file_put_contents($dir.'/ready-'.$label,'1');
        $deadline=microtime(true)+20;
        while (!file_exists($dir.'/release')) { if (microtime(true)>$deadline) { throw new RuntimeException('race barrier timeout'); } usleep(10000); }
    });
    echo json_encode(HJ_Profile_Spike_Store::change($pid,$token,spike_fields('رقابت '.$label)),JSON_UNESCAPED_UNICODE),"\n"; exit;
}
if ($mode === 'lost-worker') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $path=$argv[4]; wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::read($pid); spike_assert($r['status']==='ok' && $r['data']['state']==='draft','lost-response fixture needs draft');
    file_put_contents($path,$r['data']['generation']);
    // Test-only: drop the response after the database has accepted the update.
    add_action('hj_profile_spike_after_cas',function() { exit(0); });
    HJ_Profile_Spike_Store::change($pid,$r['data']['generation'],spike_fields('پاسخ ساختگی گم شده'));
    throw new RuntimeException('lost-response hook not reached');
}
if ($mode === 'lost-verify') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $path=$argv[4]; wp_set_current_user($uid);
    $old=file_get_contents($path); $r=HJ_Profile_Spike_Store::read($pid);
    spike_assert($r['status']==='ok' && $r['data']['generation']!==$old && $r['data']['fields']['name_fa']==='پاسخ ساختگی گم شده','write committed despite missing response');
    spike_assert(HJ_Profile_Spike_Store::change($pid,$old,spike_fields('پاسخ ساختگی گم شده'))['status']==='conflict','retry cannot establish its own outcome: open idempotency debt');
    echo "PASS counterexample: committed update, lost response, retry conflicts; idempotency unresolved\n"; exit;
}
if ($mode === 'receipt-worker') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $path=$argv[4]; wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::read($pid); spike_assert($r['status']==='ok' && $r['data']['state']==='draft','receipt fixture needs draft');
    file_put_contents($path,$r['data']['generation']);
    add_action('hj_profile_spike_after_cas',function() { exit(0); });
    HJ_Profile_Spike_Store::change_once($pid,$r['data']['generation'],spike_fields('ویرایش دارای رسید'),str_repeat('b',32));
    throw new RuntimeException('receipt response-loss hook not reached');
}
if ($mode === 'receipt-verify') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $path=$argv[4]; wp_set_current_user($uid);
    $old=file_get_contents($path); $r=HJ_Profile_Spike_Store::read($pid);
    spike_assert($r['status']==='ok' && $r['data']['fields']['name_fa']==='ویرایش دارای رسید','receipt edit persisted');
    $replay=HJ_Profile_Spike_Store::change_once($pid,$old,spike_fields('ویرایش دارای رسید'),str_repeat('b',32));
    spike_assert($replay['status']==='replayed' && $replay['generation']===$r['data']['generation'],'same key and payload recovers outcome');
    spike_assert(HJ_Profile_Spike_Store::change_once($pid,$old,spike_fields('بار متفاوت'),str_repeat('b',32))['status']==='key_conflict','same key different payload rejected');
    spike_assert(HJ_Profile_Spike_Store::change_once($pid,$old,spike_fields('ویرایش دارای رسید'),str_repeat('c',32))['status']==='conflict','new key cannot reuse stale generation');
    spike_assert(HJ_Profile_Spike_Store::read($pid)['data']['generation']===$r['data']['generation'],'replays do not mutate state');
    echo "PASS one synthetic text edit recovers lost response with bounded non-evicting receipt\n"; exit;
}
if ($mode === 'verify') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::read($pid); spike_assert($r['status']==='ok' && in_array($r['data']['fields']['name_fa'],array('رقابت a','رقابت b','پاسخ ساختگی گم شده','ویرایش دارای رسید'),true),'one winner persisted');
    spike_assert(get_post_meta($pid,'name_fa',true)==='نام عمومی' && get_post_status($pid)==='publish','race did not change published profile');
    $gen=$r['data']['generation'];
    $cut = function ($post_id, $session) {
        global $wpdb;
        $connection=(int)$session->get_var('SELECT CONNECTION_ID()');
        spike_assert($connection>0 && $wpdb->query('KILL '. $connection)!==false,'kill dedicated write connection');
    };
    add_action('hj_profile_spike_before_cas',$cut,10,2);
    $lost=HJ_Profile_Spike_Store::change($pid,$gen,spike_fields('این تغییر باید متوقف شود'));
    remove_action('hj_profile_spike_before_cas',$cut,10);
    spike_assert($lost['status']==='unknown','interrupted connection must not report success');
    $after=HJ_Profile_Spike_Store::read($pid);
    spike_assert($after['status']==='ok' && $after['data']['generation']===$gen && $after['data']['fields']===$r['data']['fields'],'pre-write disconnect leaves row unchanged');
    $submitted=HJ_Profile_Spike_Store::change($pid,$gen,null,true); spike_assert($submitted['status']==='saved' && $submitted['data']['state']==='submitted','submission freezes snapshot');
    spike_assert(HJ_Profile_Spike_Store::change($pid,$submitted['data']['generation'],spike_fields('دستکاری'))['status']==='conflict','submitted snapshot immutable');
    spike_assert(HJ_Profile_Spike_Store::reject($pid,$submitted['data']['generation'],'اصلاح شود')['status']==='forbidden','owner cannot reject');
    wp_set_current_user(get_user_by('login','fixture_admin')->ID);
    $rejected=HJ_Profile_Spike_Store::reject($pid,$submitted['data']['generation'],'اصلاح شود');
    spike_assert($rejected['status']==='saved' && $rejected['data']['state']==='rejected' && $rejected['data']['review_note']==='اصلاح شود','admin rejects exact generation');
    spike_assert(HJ_Profile_Spike_Store::reject($pid,$submitted['data']['generation'],'بار دوم')['status']==='conflict','stale moderation rejected');
    wp_set_current_user($uid);
    $updated=HJ_Profile_Spike_Store::change($pid,$rejected['data']['generation'],spike_fields('اصلاح شده'));
    spike_assert($updated['status']==='saved' && $updated['data']['state']==='draft' && !isset($updated['data']['review_note']),'rejected draft can be corrected');
    spike_assert(HJ_Profile_Spike_Store::change($pid,$updated['data']['generation'],null,true)['data']['state']==='submitted','corrected draft can be resubmitted');
    spike_assert(get_post_meta($pid,'name_fa',true)==='نام عمومی' && get_post_status($pid)==='publish','reject and correction never alter public profile');
    global $wpdb;
    $key='hj_profile_spike_'.$pid;
    $original=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key));
    $broken=wp_json_encode(array('schema'=>1,'state'=>'draft','generation'=>array('not a string'),'base_hash'=>array(),'fields'=>spike_fields('bad')));
    spike_assert($wpdb->update($wpdb->options,array('option_value'=>$broken),array('option_name'=>$key))===1,'inject malformed test row');
    spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='corrupt','malformed row fails closed without PHP type error');
    spike_assert($wpdb->update($wpdb->options,array('option_value'=>$original),array('option_name'=>$key))===1,'restore synthetic test row');
    spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='ok','restored test row readable');
    echo "PASS synthetic draft CAS, owner isolation, stale writers, submission and public data unchanged\n"; exit;
}
exit(1);

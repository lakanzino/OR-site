<?php
/** Only a disposable GitHub Actions fixture with synthetic data. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
define('HJ_PROFILE_REVISION_SPIKE', true);
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' || $GLOBALS['wpdb']->prefix !== 'hjt_' || HJ_DOCTORS_VERSION !== (getenv('HJ_SPIKE_VERSION') ?: '0.3.22') ) { exit(1); }
require getenv('GITHUB_WORKSPACE') . '/tools/hj-profile-revision-spike/store.php';
require getenv('GITHUB_WORKSPACE') . '/tools/hj-profile-revision-spike/snapshot.php';
require getenv('GITHUB_WORKSPACE') . '/tools/hj-profile-revision-spike/preflight.php';
require getenv('GITHUB_WORKSPACE') . '/tools/hj-profile-revision-spike/role-lock.php';
function spike_assert($condition,$message) { if (!$condition) { throw new RuntimeException($message); } }
function spike_fields($name) {
    return array('name_fa'=>$name,'name_en'=>'','specialty_fa'=>'جراحی نمونه','specialty_en'=>'','bio_fa'=>'','bio_en'=>'','medical_license'=>'','experience_years'=>'','education'=>'','surgeries_performed'=>'','phone_clinic'=>'','phone_mobile'=>'','address_clinic'=>'','address_hospital'=>'','map_link'=>'');
}
$mode=$argv[1] ?? '';
if ($mode === 'publication-probe') {
    // Counterexample: WordPress callbacks and request-local cache are outside DB rollback.
    global $wpdb;
    foreach (array($wpdb->posts,$wpdb->postmeta) as $table) {
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
        spike_assert(is_string($engine) && strcasecmp($engine,'InnoDB')===0,'transactional fixture tables required');
    }
    $uid=get_user_by('login','surgeon_fixture')->ID;
    $pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Transaction fixture','post_author'=>$uid),true);
    spike_assert(!is_wp_error($pid) && $pid>0,'publication probe fixture');
    update_post_meta($pid,'name_fa','نسخه عمومی قبلی');
    $marker=tempnam(sys_get_temp_dir(),'hj-profile-hook-'); spike_assert(is_string($marker),'marker file'); unlink($marker);
    $hook=function($meta_id,$object_id,$key,$value) use($pid,$marker) {
        if ((int)$object_id===$pid && $key==='name_fa') { file_put_contents($marker,'synthetic external side effect'); }
    };
    $reader=new HJ_Profile_Spike_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $reader->suppress_errors(true);
    add_action('updated_post_meta',$hook,10,4);
    try {
        spike_assert($wpdb->query('START TRANSACTION')!==false,'start transaction');
        spike_assert(update_post_meta($pid,'name_fa','پیشنهاد ناتمام')!==false,'WP metadata update');
        spike_assert(get_post_meta($pid,'name_fa',true)==='پیشنهاد ناتمام','request cached uncommitted value');
        $outside=$reader->get_var($reader->prepare("SELECT meta_value FROM `{$wpdb->postmeta}` WHERE post_id=%d AND meta_key=%s",$pid,'name_fa'));
        spike_assert($outside==='نسخه عمومی قبلی','other connection sees committed value');
        spike_assert(is_file($marker),'callback side effect already occurred');
        spike_assert($wpdb->query('ROLLBACK')!==false,'rollback transaction');
        $actual=$reader->get_var($reader->prepare("SELECT meta_value FROM `{$wpdb->postmeta}` WHERE post_id=%d AND meta_key=%s",$pid,'name_fa'));
        spike_assert($actual==='نسخه عمومی قبلی','rollback restored database');
        spike_assert(get_post_meta($pid,'name_fa',true)==='پیشنهاد ناتمام','request cache still has rolled-back value');
        spike_assert(is_file($marker),'rollback did not undo callback side effect');
        wp_cache_delete($pid,'post_meta');
        spike_assert(get_post_meta($pid,'name_fa',true)==='نسخه عمومی قبلی','cache invalidation restores correct read');
        echo "PASS counterexample: WP hook side effect and request cache outlive DB rollback\n";
    } finally {
        $wpdb->query('ROLLBACK'); remove_action('updated_post_meta',$hook,10); $reader->close(); if (is_file($marker)) { unlink($marker); }
    }
    exit;
}
if ($mode === 'snapshot-probe') {
    $uid=get_user_by('login','surgeon_fixture')->ID; $other=get_user_by('login','normal_fixture')->ID; $admin=get_user_by('login','fixture_admin')->ID;
    $pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic snapshot','post_author'=>$uid),true);
    spike_assert(!is_wp_error($pid) && $pid>0,'snapshot fixture');
    update_post_meta($pid,'name_fa','نام نخست');
    wp_set_current_user($uid); $first=HJ_Profile_Spike_Snapshot::fingerprint($pid);
    spike_assert($first['status']==='ok' && $first===HJ_Profile_Spike_Snapshot::fingerprint($pid),'stable fingerprint');
    wp_set_current_user($other); spike_assert(HJ_Profile_Spike_Snapshot::fingerprint($pid)['status']==='forbidden','other surgeon cannot read fingerprint');
    wp_set_current_user(0); spike_assert(HJ_Profile_Spike_Snapshot::fingerprint($pid)['status']==='forbidden','guest cannot read fingerprint');
    wp_set_current_user($admin); spike_assert(HJ_Profile_Spike_Snapshot::fingerprint($pid)===$first,'approver sees same fingerprint');
    update_post_meta($pid,'name_fa','نام تغییرکرده'); $second=HJ_Profile_Spike_Snapshot::fingerprint($pid);
    spike_assert($second['status']==='ok' && $second['hash']!==$first['hash'],'published name change detected');
    wp_update_post(array('ID'=>$pid,'post_title'=>'Changed synthetic title'));
    $third=HJ_Profile_Spike_Snapshot::fingerprint($pid); spike_assert($third['hash']!==$second['hash'],'title change detected');
    $city=wp_insert_term('شهر ساختگی اثر انگشت','city',array('slug'=>'spike-snapshot-city'));
    spike_assert(!is_wp_error($city),'synthetic city');
    spike_assert(!is_wp_error(wp_set_object_terms($pid,array((int)$city['term_id']),'city')),'assign synthetic city');
    $fourth=HJ_Profile_Spike_Snapshot::fingerprint($pid); spike_assert($fourth['hash']!==$third['hash'],'term change detected');
    update_post_meta($pid,'profile_photo',12345); $fifth=HJ_Profile_Spike_Snapshot::fingerprint($pid);
    spike_assert($fifth['hash']!==$fourth['hash'],'photo reference change detected');
    update_post_meta($pid,'working_hours',array('unreviewed synthetic value'));
    spike_assert(HJ_Profile_Spike_Snapshot::fingerprint($pid)===$fifth,'known limitation: schedule is outside this partial fingerprint');
    update_post_meta($pid,'name_fa',array('malformed fixture'));
    spike_assert(HJ_Profile_Spike_Snapshot::fingerprint($pid)['status']==='unavailable','non-scalar fields fail closed');
    echo "PASS partial server-side fingerprint detects tested text, title, terms and photo reference changes; schedule excluded\n"; exit;
}
if ($mode === 'acf-probe') {
    if (getenv('HJ_SPIKE_ACF')!=='free') { echo "SKIP ACF-specific probe: ACF disabled\n"; exit; }
    spike_assert(function_exists('acf_get_field') && is_array(acf_get_field('field_hj_name_fa')),'local ACF field available');
    $uid=get_user_by('login','surgeon_fixture')->ID; $pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'ACF synthetic','post_author'=>$uid),true);
    spike_assert(!is_wp_error($pid) && $pid>0,'ACF test profile'); wp_set_current_user($uid);
    update_field('field_hj_name_fa','ACF first',$pid);
    $first=get_field('name_fa',$pid); $before=HJ_Profile_Spike_Snapshot::fingerprint($pid);
    spike_assert($first==='ACF first' && $before['status']==='ok','ACF initial value');
    update_post_meta($pid,'name_fa','raw second');
    $raw=get_post_meta($pid,'name_fa',true); $formatted=get_field('name_fa',$pid); $after=HJ_Profile_Spike_Snapshot::fingerprint($pid);
    spike_assert($raw==='raw second' && $after['status']==='ok' && $before['hash']!==$after['hash'],'partial fingerprint includes raw update');
    $behavior=$formatted==='ACF first' ? 'formatted view remains old in same request' : ($formatted==='raw second' ? 'formatted view reflects raw change' : 'formatted view differs');
    echo '::notice title=Synthetic ACF direct meta update::'.$behavior."\n"; exit;
}
if ($mode === 'preflight-probe') {
    $uid=get_user_by('login','surgeon_fixture')->ID; $admin=get_user_by('login','fixture_admin')->ID;
    $pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Preflight synthetic','post_author'=>$uid),true);
    spike_assert(!is_wp_error($pid) && $pid>0,'preflight profile'); update_post_meta($pid,'name_fa','نام عمومی');
    wp_set_current_user($uid); $base=HJ_Profile_Spike_Snapshot::fingerprint($pid);
    $draft=HJ_Profile_Spike_Store::create($pid,$base['hash'],spike_fields('نام پیشنهادی'));
    spike_assert($draft['status']==='saved','preflight draft');
    $sent=HJ_Profile_Spike_Store::change($pid,$draft['data']['generation'],null,true);
    $token=$sent['data']['generation'];
    spike_assert(HJ_Profile_Spike_Preflight::inspect($pid,$token)['status']==='forbidden','surgeon cannot preflight admin approval');
    wp_set_current_user($admin);
    spike_assert(HJ_Profile_Spike_Preflight::inspect($pid,$token)['status']==='matches_partial_snapshot','unchanged snapshot matches');
    spike_assert(HJ_Profile_Spike_Preflight::inspect($pid,str_repeat('0',32))['status']==='conflict','wrong generation rejected');
    update_post_meta($pid,'name_fa','ویرایش مدیر');
    spike_assert(HJ_Profile_Spike_Preflight::inspect($pid,$token)['status']==='public_changed','admin edit detected');
    update_post_meta($pid,'name_fa','نام عمومی');
    spike_assert(HJ_Profile_Spike_Preflight::inspect($pid,$token)['status']==='matches_partial_snapshot','matching text restored');
    // A separate writer can change public data immediately after preflight.
    update_post_meta($pid,'name_fa','ویرایش پس از بررسی');
    spike_assert(HJ_Profile_Spike_Preflight::inspect($pid,$token)['status']==='public_changed','preflight alone cannot lock out another writer');
    echo "PASS partial preflight detects tested external edits; no lock or publication is implied\n"; exit;
}
if ($mode === 'guard-setup') {
    $login=($argv[2] ?? '')==='reverse' ? 'spike_guarded_reverse' : 'spike_guarded_surgeon';
    $uid=wp_create_user($login,'fixture-only',$login.'@example.invalid');
    spike_assert(!is_wp_error($uid) && hj_doctors_assign_doctor_role($uid),'guarded fixture user');
    $option='hj_spike_guard_'.$uid; spike_assert(add_option($option,'old','','no'),'guarded fixture option');
    echo json_encode(array('uid'=>$uid,'option'=>$option)),"\n"; exit;
}
if ($mode === 'guard-worker') {
    $uid=(int)$argv[2]; $option=$argv[3]; $dir=$argv[4]; wp_set_current_user($uid);
    add_action('hj_spike_role_after_lock',function() use($dir) {
        file_put_contents($dir.'/locked','1'); $until=microtime(true)+20;
        while (!file_exists($dir.'/release')) { if (microtime(true)>$until) { throw new RuntimeException('guard barrier timeout'); } usleep(10000); }
    });
    echo json_encode(hj_spike_guarded_role_write($uid,$option)),"\n"; exit;
}
if ($mode === 'guard-reverse-worker') {
    $uid=(int)$argv[2]; $option=$argv[3]; $dir=$argv[4]; wp_set_current_user($uid);
    add_action('hj_spike_role_before_lock',function() use($dir) {
        file_put_contents($dir.'/ready','1'); $until=microtime(true)+20;
        while (!file_exists($dir.'/release')) { if (microtime(true)>$until) { throw new RuntimeException('reverse guard barrier timeout'); } usleep(10000); }
    });
    echo json_encode(hj_spike_guarded_role_write($uid,$option)),"\n"; exit;
}
if ($mode === 'guard-reverse-verify') {
    $uid=(int)$argv[2]; $option=$argv[3]; $user=get_userdata($uid);
    spike_assert($user instanceof WP_User && $user->user_login==='spike_guarded_reverse' && !hj_doctors_is_doctor_user($uid),'reverse fixture role revoked');
    spike_assert(get_option($option)==='old','write refused after prior revocation');
    echo "PASS prior stock WP role removal blocks test write after fresh locked role read\n"; exit;
}
if ($mode === 'guard-revoke') {
    $uid=(int)$argv[2]; $dir=$argv[3]; $user=get_userdata($uid);
    spike_assert($user instanceof WP_User && in_array($user->user_login,array('spike_guarded_surgeon','spike_guarded_reverse'),true),'refuse nonfixture user');
    global $wpdb; $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); spike_assert($connection>0,'revocation connection');
    file_put_contents($dir.'/revoke-ready',(string)$connection); $user->set_role('subscriber'); echo "revoked\n"; exit;
}
if ($mode === 'guard-check') {
    global $wpdb; $connection=(int)$argv[2];
    $info=$wpdb->get_var($wpdb->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID=%d',$connection));
    echo is_string($info) && preg_match('/^UPDATE\s+`?'.preg_quote($wpdb->usermeta,'/').'`?\s+/i',$info) ? "waiting\n" : "not-waiting\n"; exit;
}
if ($mode === 'guard-verify') {
    $uid=(int)$argv[2]; $option=$argv[3]; $user=get_userdata($uid);
    spike_assert($user instanceof WP_User && $user->user_login==='spike_guarded_surgeon' && !hj_doctors_is_doctor_user($uid),'synthetic role revoked');
    spike_assert(get_option($option)==='saved','first writer completed before revocation');
    wp_set_current_user($uid); spike_assert(hj_spike_guarded_role_write($uid,$option)['status']==='forbidden','subsequent writer denied');
    echo "PASS stock WP role removal serialized with prototype locked role write; no universal authorization claim\n"; exit;
}
if ($mode === 'revoke-setup') {
    $uid=wp_create_user('spike_revoked_surgeon','fixture-only','revoked-spike@example.invalid');
    spike_assert(!is_wp_error($uid) && hj_doctors_assign_doctor_role($uid),'revocation fixture user');
    $pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Revocation fixture','post_author'=>$uid),true);
    spike_assert(!is_wp_error($pid) && $pid>0,'revocation fixture profile'); wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::create($pid,str_repeat('a',64),spike_fields('نسخه قبلی'));
    spike_assert($r['status']==='saved','revocation fixture draft');
    echo json_encode(array('uid'=>$uid,'pid'=>$pid,'token'=>$r['data']['generation'])),"\n"; exit;
}
if ($mode === 'revoke-worker') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $token=$argv[4]; $dir=$argv[5]; wp_set_current_user($uid);
    add_action('hj_profile_spike_before_cas',function() use($dir) {
        file_put_contents($dir.'/ready','1'); $deadline=microtime(true)+20;
        while (!file_exists($dir.'/release')) { if (microtime(true)>$deadline) { throw new RuntimeException('revocation barrier timeout'); } usleep(10000); }
    });
    echo json_encode(HJ_Profile_Spike_Store::change($pid,$token,spike_fields('پس از قطع دسترسی'))),"\n"; exit;
}
if ($mode === 'revoke-role') {
    $uid=(int)$argv[2]; $user=get_userdata($uid); spike_assert($user instanceof WP_User && $user->user_login==='spike_revoked_surgeon','refuse nonfixture account');
    $user->set_role('subscriber'); spike_assert(!hj_doctors_is_doctor_user($uid),'synthetic role removed'); echo "revoked\n"; exit;
}
if ($mode === 'revoke-verify') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3];
    wp_set_current_user($uid); spike_assert(HJ_Profile_Spike_Store::read($pid)['status']==='forbidden','new request denies revoked user');
    wp_set_current_user(get_user_by('login','fixture_admin')->ID); $r=HJ_Profile_Spike_Store::read($pid);
    spike_assert($r['status']==='ok' && $r['data']['fields']['name_fa']==='پس از قطع دسترسی','in-flight test write was not revoked');
    echo "PASS counterexample: revoked in-flight writer can still modify spike draft; coordination required\n"; exit;
}
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
if ($mode === 'receipt-race-token') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::read($pid); spike_assert($r['status']==='ok' && $r['data']['state']==='draft','receipt race requires draft');
    echo $r['data']['generation'],"\n"; exit;
}
if ($mode === 'receipt-race-worker') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; $token=$argv[4]; $dir=$argv[5]; $label=$argv[6]; wp_set_current_user($uid);
    if (!preg_match('/^[ab]$/D',$label) || !is_dir($dir)) { exit(1); }
    add_action('hj_profile_spike_before_cas',function() use ($dir,$label) {
        file_put_contents($dir.'/ready-'.$label,'1'); $deadline=microtime(true)+20;
        while (!file_exists($dir.'/release')) { if (microtime(true)>$deadline) { throw new RuntimeException('receipt race barrier timeout'); } usleep(10000); }
    });
    echo json_encode(HJ_Profile_Spike_Store::change_once($pid,$token,spike_fields('درخواست همزمان یکسان'),str_repeat('d',32))),"\n"; exit;
}
if ($mode === 'verify') {
    $pid=(int)$argv[2]; $uid=(int)$argv[3]; wp_set_current_user($uid);
    $r=HJ_Profile_Spike_Store::read($pid); spike_assert($r['status']==='ok' && in_array($r['data']['fields']['name_fa'],array('رقابت a','رقابت b','پاسخ ساختگی گم شده','ویرایش دارای رسید','درخواست همزمان یکسان'),true),'one winner persisted');
    spike_assert(get_post_meta($pid,'name_fa',true)==='نام عمومی' && get_post_status($pid)==='publish','race did not change published profile');
    $gen=$r['data']['generation'];
    // Capacity is deliberately fail-closed; production needs a safe retention design.
    $first=null;
    for ($i=0; $i<30; $i++) {
        $key=sprintf('%032x',100+$i); $fields=spike_fields('آزمایش ظرفیت '.$i);
        if ($i===0) { $first=array($gen,$key,$fields); }
        $saved=HJ_Profile_Spike_Store::change_once($pid,$gen,$fields,$key);
        spike_assert($saved['status']==='saved','receipt capacity fixture save'); $gen=$saved['generation'];
    }
    spike_assert(HJ_Profile_Spike_Store::change_once($pid,$gen,spike_fields('بیش از ظرفیت'),str_repeat('e',32))['status']==='receipt_capacity','full ledger fails closed');
    spike_assert(HJ_Profile_Spike_Store::change_once($pid,$first[0],$first[2],$first[1])['status']==='replayed','existing receipt survives full ledger');
    $r=HJ_Profile_Spike_Store::read($pid); spike_assert($r['status']==='ok' && $r['data']['generation']===$gen,'receipt capacity leaves draft intact');
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

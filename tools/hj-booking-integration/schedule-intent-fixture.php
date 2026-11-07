<?php
/** CI-only experiment: durable pre-intent and recovery. NOT an endpoint or plugin. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306 || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb;
$admin=get_user_by('login','fixture_admin'); $owner=get_user_by('login','synthetic_doctor_a'); $outsider=get_user_by('login','synthetic_race_0');
if (!$admin || !$owner || !$outsider || !hj_booking_enabled()) { exit(1); }
wp_set_current_user($admin->ID);
$table=$wpdb->prefix.'ci_schedule_intents'; $meta=getenv('RUNNER_TEMP').'/hj-ci-schedule-intent.json'; $prefix=getenv('RUNNER_TEMP').'/hj-ci-schedule-intent'; $mode=$argv[1] ?? '';
function intent_check($condition,$label) { if (!$condition) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function intent_key($n) { return str_pad(dechex($n),32,'0',STR_PAD_LEFT); }
function intent_rows($start) { return array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>$start.':00','end_time'=>'20:00:00')); }
function intent_snapshot($doctor) { global $wpdb; $s=hj_booking_table('schedules'); return $wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$doctor),ARRAY_A); }
function intent_valid_key($key) { return is_string($key) && preg_match('/^[a-f0-9]{32}$/D',$key); }
function intent_hash($doctor,$actor,$revision,$rows) { return hash_hmac('sha256',wp_json_encode(array('intent-v1',$doctor,$actor,$revision,$rows)),wp_salt('auth')); }
/** One active intent per doctor; completed history retained forever in this experiment. */
function ci_intent_prepare($doctor,$key,$revision,$rows) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key) || !is_int($revision) || $revision<1 || ($rows=hj_booking_normalize_schedule($rows))===false) { return array('status'=>'invalid'); }
 $actor=get_current_user_id(); $hash=intent_hash($doctor,$actor,$revision,$rows);
 return hj_booking_transaction($doctor,'ci_intent_prepare',function($db) use($doctor,$key,$actor,$hash,$revision,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $old=hj_booking_row($db,$db->prepare("SELECT actor_id,payload_hash,state,result_revision FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if ($old) {
   if ((int)$old['actor_id']!==$actor || !hash_equals($old['payload_hash'],$hash)) { return array('status'=>'blocked'); }
   return array('status'=>$old['state'],'revision'=>(int)$old['result_revision']);
  }
  $active=hj_booking_row($db,$db->prepare("SELECT request_key FROM `$table` WHERE doctor_id=%d AND active=1",$doctor));
  if ($active) { return array('status'=>'blocked'); }
  $s=hj_booking_table('schedules'); $current=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  if (!$current || hj_booking_legacy_snapshot_rows($current)===false) { return array('status'=>'invalid_schedule'); }
  if ((int)$current['revision']!==$revision) { return array('status'=>'revision_conflict'); }
  if (hj_booking_query($db,$db->prepare("INSERT INTO `$table` (doctor_id,request_key,actor_id,payload_hash,state,active,result_revision) VALUES (%d,%s,%d,%s,'prepared',1,0)",$doctor,$key,$actor,$hash))!==1) { throw new RuntimeException('intent insert failed'); }
  return array('status'=>'prepared','revision'=>0);
 });
}
function ci_intent_apply($doctor,$key,$revision,$rows) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key) || !is_int($revision) || $revision<1 || $revision===PHP_INT_MAX || ($rows=hj_booking_normalize_schedule($rows))===false) { return array('status'=>'invalid'); }
 $actor=get_current_user_id(); $hash=intent_hash($doctor,$actor,$revision,$rows); $json=wp_json_encode($rows);
 return hj_booking_transaction($doctor,'ci_intent_apply',function($db) use($doctor,$key,$actor,$revision,$hash,$json,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $intent=hj_booking_row($db,$db->prepare("SELECT actor_id,payload_hash,state,result_revision,active FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if (!$intent || (int)$intent['actor_id']!==$actor || !hash_equals($intent['payload_hash'],$hash)) { return array('status'=>'blocked'); }
  if (in_array($intent['state'],array('completed','acknowledged'),true)) { return array('status'=>'replayed','revision'=>(int)$intent['result_revision']); }
  if ($intent['state']!=='prepared' || (int)$intent['active']!==1) { return array('status'=>'blocked'); }
  $s=hj_booking_table('schedules'); $old=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  if (!$old || hj_booking_legacy_snapshot_rows($old)===false) { return array('status'=>'invalid_schedule'); }
  if ((int)$old['revision']!==$revision) { return array('status'=>'revision_conflict'); }
  $next=$revision+1; $digest=hash('sha256',$json);
  if (hj_booking_query($db,$db->prepare("UPDATE `$s` SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d",$next,$json,$digest,$doctor))!==1 || hj_booking_query($db,$db->prepare("UPDATE `$table` SET state='completed',result_revision=%d WHERE doctor_id=%d AND request_key=%s AND state='prepared' AND active=1",$next,$doctor,$key))!==1) { throw new RuntimeException('synthetic intent apply failed'); }
  return array('status'=>'saved','revision'=>$next);
 });
}
/** Lookup without a browser key, scoped to the actor and doctor. */
function ci_intent_recover($doctor) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 $actor=get_current_user_id();
 return hj_booking_transaction($doctor,'ci_intent_recover',function($db) use($doctor,$actor,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $row=hj_booking_row($db,$db->prepare("SELECT request_key,state,result_revision FROM `$table` WHERE doctor_id=%d AND actor_id=%d AND active=1",$doctor,$actor));
  if (!$row || !in_array($row['state'],array('prepared','completed'),true) || !intent_valid_key($row['request_key'])) { return array('status'=>'unknown'); }
  return array('status'=>$row['state'],'key'=>$row['request_key'],'revision'=>(int)$row['result_revision']);
 });
}
/** Completed record only; requires a fresh exact revision and retains replay tombstone. */
function ci_intent_ack($doctor,$key,$observed_revision) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key) || !is_int($observed_revision) || $observed_revision<1) { return array('status'=>'invalid'); }
 $actor=get_current_user_id();
 return hj_booking_transaction($doctor,'ci_intent_ack',function($db) use($doctor,$key,$observed_revision,$actor,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $row=hj_booking_row($db,$db->prepare("SELECT actor_id,state,result_revision,active FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if (!$row || (int)$row['actor_id']!==$actor) { return array('status'=>'blocked'); }
  if ($row['state']==='acknowledged') { return array('status'=>'acknowledged'); }
  if ($row['state']!=='completed' || (int)$row['active']!==1) { return array('status'=>'blocked'); }
  $s=hj_booking_table('schedules'); $current=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  if (!$current || hj_booking_legacy_snapshot_rows($current)===false || (int)$current['revision']!==$observed_revision || $observed_revision<(int)$row['result_revision']) { return array('status'=>'refresh_required'); }
  if (hj_booking_query($db,$db->prepare("UPDATE `$table` SET state='acknowledged',active=NULL WHERE doctor_id=%d AND request_key=%s AND state='completed' AND active=1",$doctor,$key))!==1) { throw new RuntimeException('ack failed'); }
  return array('status'=>'acknowledged');
 });
}
if ($mode==='setup') {
 intent_check(!is_file($meta) && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) && hj_booking_install_schema(),'clean fixture and schema');
 $doctor=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_author'=>$owner->ID,'post_title'=>'Synthetic intent doctor'),true);
 if (is_wp_error($doctor) || !$doctor) { throw new RuntimeException('doctor failed'); }
 $owned=false;
 try {
  intent_check(hj_booking_configure((int)$doctor,30,5,true)['status']==='configured' && hj_booking_save_schedule((int)$doctor,intent_rows('09:00'),0)['status']==='saved','seed synthetic v1 snapshot');
  intent_check($wpdb->query("CREATE TABLE `$table` (doctor_id bigint unsigned NOT NULL,request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id bigint unsigned NOT NULL,payload_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,state varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,active tinyint unsigned NULL,result_revision bigint unsigned NOT NULL,PRIMARY KEY (doctor_id,request_key),UNIQUE KEY one_active (doctor_id,active)) ENGINE=InnoDB")!==false,'create CI-only intent table'); $owned=true;
  if (file_put_contents($meta,wp_json_encode(array('doctor'=>(int)$doctor)))===false) { throw new RuntimeException('metadata failed'); }
 } catch(Throwable $e) { if ($owned) { $wpdb->query("DROP TABLE `$table`"); } $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>(int)$doctor)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>(int)$doctor)); wp_delete_post((int)$doctor,true); throw $e; }
 exit(0);
}
$data=json_decode(@file_get_contents($meta),true); if (!is_array($data) || empty($data['doctor'])) { throw new RuntimeException('missing metadata'); } $doctor=(int)$data['doctor'];
if ($mode==='prepare-lost') {
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix) { if ($ctx['operation']==='ci_intent_prepare' && $event==='after_commit' && ($ctx['result']['status'] ?? '')==='prepared') { file_put_contents($prefix.'-prepared','1'); $end=microtime(true)+20; while(microtime(true)<$end) { usleep(10000); } throw new RuntimeException('not interrupted'); } },10,3);
 ci_intent_prepare($doctor,intent_key(1),1,intent_rows('10:00')); exit(1);
}
if ($mode==='check-prepared') {
 $found=ci_intent_recover($doctor); intent_check($found===array('status'=>'prepared','key'=>intent_key(1),'revision'=>0),'actor recovers prepared intent with no local key after lost prepare response');
 intent_check(ci_intent_prepare($doctor,intent_key(2),1,intent_rows('11:00'))['status']==='blocked','new key and changed input blocked');
 intent_check(ci_intent_prepare($doctor,intent_key(1),1,intent_rows('11:00'))['status']==='blocked','same key with altered payload blocked');
 intent_check(ci_intent_ack($doctor,intent_key(1),1)['status']==='blocked','prepared intent cannot be acknowledged');
 wp_set_current_user($owner->ID); intent_check(hj_booking_can_configure($doctor) && ci_intent_recover($doctor)['status']==='unknown' && ci_intent_apply($doctor,intent_key(1),1,intent_rows('10:00'))['status']==='blocked' && ci_intent_prepare($doctor,intent_key(2),1,intent_rows('11:00'))['status']==='blocked','other authorized actor cannot inspect, apply or bypass active intent');
 wp_set_current_user($outsider->ID); intent_check(ci_intent_recover($doctor)['status']==='forbidden' && ci_intent_prepare($doctor,intent_key(2),1,intent_rows('11:00'))['status']==='forbidden','unrelated actor forbidden');
 wp_set_current_user($admin->ID); intent_check(ci_intent_prepare($doctor,intent_key(1),1,intent_rows('10:00'))['status']==='prepared','same prepare is idempotent');
 $other=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic different doctor'),true); if (is_wp_error($other) || !$other) { throw new RuntimeException('other doctor failed'); }
 try { intent_check(ci_intent_recover((int)$other)['status']==='unknown' && ci_intent_apply((int)$other,intent_key(1),1,intent_rows('10:00'))['status']==='blocked','authorized actor cannot recover or apply another doctor intent in wrong scope'); }
 finally { $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>(int)$other)); wp_delete_post((int)$other,true); }
 exit(0);
}
if ($mode==='apply-lost') {
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix) { if ($ctx['operation']==='ci_intent_apply' && $event==='after_commit' && ($ctx['result']['status'] ?? '')==='saved') { file_put_contents($prefix.'-applied','1'); $end=microtime(true)+20; while(microtime(true)<$end) { usleep(10000); } throw new RuntimeException('not interrupted'); } },10,3);
 ci_intent_apply($doctor,intent_key(1),1,intent_rows('10:00')); exit(1);
}
if ($mode==='check-completed') {
 $found=ci_intent_recover($doctor); intent_check($found===array('status'=>'completed','key'=>intent_key(1),'revision'=>2) && (int)intent_snapshot($doctor)['revision']===2,'recover committed result without local key');
 intent_check(ci_intent_apply($doctor,intent_key(1),1,intent_rows('10:00'))===array('status'=>'replayed','revision'=>2),'same request replays without writing');
 intent_check(ci_intent_ack($doctor,intent_key(1),1)['status']==='refresh_required' && ci_intent_prepare($doctor,intent_key(2),2,intent_rows('11:00'))['status']==='blocked','stale read cannot release active intent');
 intent_check(hj_booking_save_schedule($doctor,intent_rows('10:30'),2)['status']==='saved','legacy writer can bypass experimental intent guard');
 intent_check(ci_intent_ack($doctor,intent_key(1),2)['status']==='refresh_required' && ci_intent_ack($doctor,intent_key(1),3)['status']==='acknowledged','newer snapshot must be read before acknowledgement');
 intent_check(ci_intent_recover($doctor)['status']==='unknown','absent active record is unknown, not proof of failure');
 intent_check(ci_intent_prepare($doctor,intent_key(2),3,intent_rows('11:00'))['status']==='prepared','new intent after acknowledgement');
 intent_check(ci_intent_prepare($doctor,intent_key(1),1,intent_rows('10:00'))['status']==='acknowledged' && ci_intent_apply($doctor,intent_key(1),1,intent_rows('10:00'))===array('status'=>'replayed','revision'=>2) && ci_intent_ack($doctor,intent_key(1),3)['status']==='acknowledged','old key retained and historical retry cannot affect new intent');
 intent_check(ci_intent_recover($doctor)===array('status'=>'prepared','key'=>intent_key(2),'revision'=>0) && (int)intent_snapshot($doctor)['revision']===3,'new intent remains active and snapshot unchanged');
 intent_check(ci_intent_apply($doctor,intent_key(2),3,intent_rows('11:00'))===array('status'=>'saved','revision'=>4),'second intent saves once');
 intent_check(ci_intent_ack($doctor,intent_key(2),4)['status']==='acknowledged','second operation acknowledged');
 exit(0);
}
if ($mode==='race-prepare') {
 $id=getenv('HJ_INTENT_WORKER'); if (!is_string($id) || !preg_match('/^[0-8]$/D',$id)) { exit(1); }
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$id) {
  if ($ctx['operation']!=='ci_intent_prepare') { return; }
  if ($event==='before_lock' && $id!=='0') { file_put_contents($prefix.'-before-'.$id,(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($event==='after_lock' && $id==='0') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $result=$id==='8' ? ci_intent_prepare($doctor,intent_key(4),4,intent_rows('13:00')) : ci_intent_prepare($doctor,intent_key(3),4,intent_rows('12:00'));
 echo $result['status']; exit(in_array($result['status'],array('prepared','blocked'),true)?0:1);
}
if ($mode==='waiter') {
 $db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $end=microtime(true)+2;
 do { $ids=array(); foreach(glob($prefix.'-before-*') as $f) { $ids[]=(int)file_get_contents($f); } foreach(hj_booking_rows($db,'SHOW FULL PROCESSLIST') as $row) { if (in_array((int)$row['Id'],$ids,true) && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`')!==false) { echo 'WAITING'; $db->close(); exit(0); } } usleep(10000); clearstatcache(); } while(microtime(true)<$end); $db->close(); exit(1);
}
if ($mode==='check-race') {
 $before=intent_snapshot($doctor);
 intent_check(ci_intent_recover($doctor)===array('status'=>'prepared','key'=>intent_key(3),'revision'=>0),'one active intent after concurrent prepare');
 $trigger=$wpdb->prefix.'ci_intent_fail'; intent_check($wpdb->query("CREATE TRIGGER `$trigger` BEFORE UPDATE ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")!==false,'inject completion update failure');
 try { intent_check(ci_intent_apply($doctor,intent_key(3),4,intent_rows('12:00'))['status']==='error' && intent_snapshot($doctor)===$before && ci_intent_recover($doctor)['status']==='prepared','completion failure rolls back snapshot and leaves intent prepared'); }
 finally { $wpdb->query("DROP TRIGGER `$trigger`"); }
 $killed=false;
 $hook=function($event,$db,$ctx) use(&$killed) { if (!$killed && $ctx['operation']==='ci_intent_apply' && $event==='before_commit') { $killed=true; $id=(int)$db->get_var('SELECT CONNECTION_ID()'); $killer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $killer->suppress_errors(true); if (!$id || $killer->query('KILL CONNECTION '.$id)===false) { throw new RuntimeException('fixture kill failed'); } $killer->close(); } };
 add_action('hj_booking_test_event',$hook,10,3); $lost=ci_intent_apply($doctor,intent_key(3),4,intent_rows('12:00')); remove_action('hj_booking_test_event',$hook,10);
 intent_check($killed && $lost['status']==='unknown' && intent_snapshot($doctor)===$before && ci_intent_recover($doctor)['status']==='prepared','connection kill before commit leaves recoverable intent and unchanged snapshot');
 intent_check(ci_intent_apply($doctor,intent_key(3),4,intent_rows('12:00'))===array('status'=>'saved','revision'=>5) && ci_intent_apply($doctor,intent_key(3),4,intent_rows('12:00'))===array('status'=>'replayed','revision'=>5),'retry after rollback makes one save and historical replay'); exit(0);
}
if ($mode==='cleanup') { $wpdb->query("DROP TABLE IF EXISTS `$table`"); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doctor)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$doctor)); wp_delete_post($doctor,true); unlink($meta); exit(0); }
exit(1);

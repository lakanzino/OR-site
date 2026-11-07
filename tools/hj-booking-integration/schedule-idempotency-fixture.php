<?php
/** Experimental v1 schedule idempotency, synthetic CI only; NOT product code. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb;
$admin=get_user_by('login','fixture_admin'); $owner=get_user_by('login','synthetic_doctor_a'); $outsider=get_user_by('login','synthetic_race_0');
if (!$admin || !$owner || !$outsider || !hj_booking_enabled()) { exit(1); }
$meta=getenv('RUNNER_TEMP').'/hj-ci-schedule-idempotency.json'; $prefix=getenv('RUNNER_TEMP').'/hj-ci-schedule-idem';
$table=$wpdb->prefix.'ci_schedule_requests'; $trigger=$wpdb->prefix.'ci_schedule_requests_fail'; $mode=$argv[1] ?? '';
function idem_check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function idem_rows($start) { return array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>$start.':00','end_time'=>'20:00:00')); }
function idem_key($n) { return str_pad(dechex($n),32,'0',STR_PAD_LEFT); }
function idem_snapshot($doctor) { global $wpdb; $t=hj_booking_table('schedules'); return $wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$t` WHERE doctor_id=%d",$doctor),ARRAY_A); }
/** This miniature writer exercises the existing mutex, not the live dashboard.
 * Only successful writes get a durable receipt; rejected operations are not
 * recorded. Receipt replay is historical and MUST NOT imply current state.
 */
function ci_save_schedule_once($doctor,$rows,$revision,$key) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!is_int($revision) || $revision<0 || !is_string($key) || !preg_match('/^[a-f0-9]{32}$/D',$key)) { return array('status'=>'invalid'); }
 $rows=hj_booking_normalize_schedule($rows); if ($rows===false) { return array('status'=>'invalid'); }
 $json=wp_json_encode($rows); if (!is_string($json)) { return array('status'=>'invalid'); }
 $actor=get_current_user_id(); $hash=hash_hmac('sha256',wp_json_encode(array('v1',$doctor,$actor,$revision,$rows)),wp_salt('auth'));
 return hj_booking_transaction($doctor,'ci_idempotent_schedule',function($db) use($doctor,$revision,$key,$json,$hash,$actor) {
  if (!hj_booking_can_configure($doctor) || get_current_user_id()!==$actor) { return array('status'=>'forbidden'); }
  $requests=$GLOBALS['table']; $s=hj_booking_table('schedules');
  $record=hj_booking_row($db,$db->prepare("SELECT actor_id,payload_hash,result_revision FROM `$requests` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if ($record) {
   if ((int)$record['actor_id']!==$actor || !hash_equals($record['payload_hash'],$hash)) { return array('status'=>'idempotency_conflict'); }
   return array('status'=>'replayed','revision'=>(int)$record['result_revision']);
  }
  $old=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  $current=$old ? (int)$old['revision'] : 0;
  if ($current!==$revision) { return array('status'=>'revision_conflict'); }
  if ($old && hj_booking_legacy_snapshot_rows($old)===false) { return array('status'=>'invalid_schedule'); }
  if ($current===PHP_INT_MAX) { return array('status'=>'invalid'); }
  $next=$current+1; $digest=hash('sha256',$json);
  if ($old) { $n=hj_booking_query($db,$db->prepare("UPDATE `$s` SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d",$next,$json,$digest,$doctor)); }
  else { $n=hj_booking_query($db,$db->prepare("INSERT INTO `$s` (doctor_id,revision,rows_json,rows_hash) VALUES (%d,%d,%s,%s)",$doctor,$next,$json,$digest)); }
  if ($n!==1 || hj_booking_query($db,$db->prepare("INSERT INTO `$requests` (doctor_id,request_key,actor_id,payload_hash,result_revision) VALUES (%d,%s,%d,%s,%d)",$doctor,$key,$actor,$hash,$next))!==1) { throw new RuntimeException('synthetic save failed'); }
  return array('status'=>'saved','revision'=>$next);
 });
}
wp_set_current_user($admin->ID);
if ($mode==='setup') {
 idem_check(!is_file($meta) && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))),'fixture request table and metadata absent');
 idem_check(hj_booking_install_schema(),'fixture v1 schema ready');
 $doctor=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_author'=>$owner->ID,'post_title'=>'Synthetic schedule idempotency doctor'),true);
 if (is_wp_error($doctor) || !$doctor) { throw new RuntimeException('fixture doctor failed'); }
 $owned=false;
 try {
  idem_check(hj_booking_configure((int)$doctor,30,5,true)['status']==='configured' && hj_booking_save_schedule((int)$doctor,idem_rows('09:00'),0)['status']==='saved','initial v1 schedule seeded');
  $before=idem_snapshot((int)$doctor);
  // Missing table must fail closed before any snapshot write.
  idem_check(ci_save_schedule_once((int)$doctor,idem_rows('10:00'),1,idem_key(1))['status']==='error' && idem_snapshot((int)$doctor)===$before,'missing receipt table cannot write snapshot');
  idem_check($wpdb->query("CREATE TABLE `$table` (doctor_id bigint unsigned NOT NULL,request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id bigint unsigned NOT NULL,payload_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,result_revision bigint unsigned NOT NULL,PRIMARY KEY (doctor_id,request_key)) ENGINE=InnoDB")!==false,'create CI-only receipt table'); $owned=true;
  if (file_put_contents($meta,wp_json_encode(array('doctor'=>(int)$doctor)))===false) { throw new RuntimeException('metadata failed'); }
 } catch(Throwable $e) { if ($owned) { $wpdb->query("DROP TABLE `$table`"); } $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>(int)$doctor)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>(int)$doctor)); wp_delete_post((int)$doctor,true); throw $e; }
 exit(0);
}
$data=json_decode(@file_get_contents($meta),true); if (!is_array($data) || empty($data['doctor'])) { throw new RuntimeException('fixture metadata missing'); } $doctor=(int)$data['doctor'];
if ($mode==='worker') {
 $id=getenv('HJ_IDEM_WORKER'); if (!is_string($id) || !preg_match('/^[0-7]$/D',$id)) { exit(1); }
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$id) {
  if ($ctx['operation']!=='ci_idempotent_schedule') { return; }
  if ($event==='before_lock' && $id!=='0') { file_put_contents($prefix.'-before-'.$id,(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($event==='after_lock' && $id==='0') {
   file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15;
   while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); }
   if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); }
  }
 },10,3);
 $result=ci_save_schedule_once($doctor,idem_rows('10:00'),1,idem_key(1)); echo $result['status']; exit(in_array($result['status'],array('saved','replayed'),true)?0:1);
}
if ($mode==='waiter') {
 $db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $end=microtime(true)+2;
 do { $ids=array(); foreach(glob($prefix.'-before-*') as $file) { $ids[]=(int)file_get_contents($file); }
  foreach(hj_booking_rows($db,'SHOW FULL PROCESSLIST') as $row) { if (in_array((int)$row['Id'],$ids,true) && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`')!==false) { echo 'WAITING'; $db->close(); exit(0); } }
  usleep(10000); clearstatcache();
 } while(microtime(true)<$end); $db->close(); exit(1);
}
if ($mode==='assert-race') {
 $snapshot=idem_snapshot($doctor);
 idem_check((int)$snapshot['revision']===2 && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")===1,'eight identical requests have one revision and one receipt');
 idem_check(ci_save_schedule_once($doctor,idem_rows('11:00'),1,idem_key(1))['status']==='idempotency_conflict' && idem_snapshot($doctor)===$snapshot,'same key with changed rows is a conflict');
 wp_set_current_user($outsider->ID); idem_check(ci_save_schedule_once($doctor,idem_rows('10:00'),1,idem_key(1))['status']==='forbidden','unauthorized user cannot read receipt');
 wp_set_current_user($owner->ID); idem_check(hj_booking_can_configure($doctor) && ci_save_schedule_once($doctor,idem_rows('10:00'),1,idem_key(1))['status']==='idempotency_conflict','another authorized actor cannot replay receipt'); wp_set_current_user($admin->ID);
 // Actual DB insert failure after snapshot UPDATE must roll back both writes.
 idem_check($wpdb->query("CREATE TRIGGER `$trigger` BEFORE INSERT ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")!==false,'fixture injects receipt insert failure');
 try { idem_check(ci_save_schedule_once($doctor,idem_rows('11:00'),2,idem_key(2))['status']==='error' && idem_snapshot($doctor)===$snapshot && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")===1,'receipt failure rolls back snapshot update'); }
 finally { $wpdb->query("DROP TRIGGER `$trigger`"); }
 idem_check(ci_save_schedule_once($doctor,idem_rows('11:00'),2,idem_key(2))===array('status'=>'saved','revision'=>3),'new key saves subsequent revision');
 $latest=idem_snapshot($doctor); $historical=ci_save_schedule_once($doctor,idem_rows('10:00'),1,idem_key(1));
 idem_check($historical===array('status'=>'replayed','revision'=>2) && idem_snapshot($doctor)===$latest && (int)$latest['revision']===3,'old replay is historical and cannot overwrite a newer revision');
 idem_check(ci_save_schedule_once($doctor,idem_rows('12:00'),1,idem_key(3))['status']==='revision_conflict' && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")===2,'failed revision does not create receipt');
 idem_check(ci_save_schedule_once($doctor,idem_rows('12:00'),3,idem_key(3))===array('status'=>'saved','revision'=>4),'rejected request key can be used after correction because no receipt was written');
 exit(0);
}
if ($mode==='before-commit-kill') {
 $killed=false;
 $hook=function($event,$db,$ctx) use(&$killed) {
  if (!$killed && $ctx['operation']==='ci_idempotent_schedule' && $event==='before_commit') {
   $killed=true; $id=(int)$db->get_var('SELECT CONNECTION_ID()'); $killer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $killer->suppress_errors(true);
   if (!$id || $killer->query('KILL CONNECTION '.$id)===false) { throw new RuntimeException('fixture kill failed'); } $killer->close();
  }
 };
 add_action('hj_booking_test_event',$hook,10,3);
 $first=ci_save_schedule_once($doctor,idem_rows('13:00'),4,idem_key(4)); remove_action('hj_booking_test_event',$hook,10);
 idem_check($killed && $first['status']==='unknown' && (int)idem_snapshot($doctor)['revision']===4 && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")===3,'connection killed before commit reports unknown; neither write survived');
 idem_check(ci_save_schedule_once($doctor,idem_rows('13:00'),4,idem_key(4))===array('status'=>'saved','revision'=>5),'same key after rolled-back unknown safely saves once'); exit(0);
}
if ($mode==='lost-response') {
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix) {
  if ($ctx['operation']==='ci_idempotent_schedule' && $event==='after_commit' && ($ctx['result']['status'] ?? '')==='saved') {
   file_put_contents($prefix.'-committed','committed'); $until=microtime(true)+20;
   while (microtime(true)<$until) { usleep(10000); }
   throw new RuntimeException('client was not interrupted');
  }
 },10,3);
 $result=ci_save_schedule_once($doctor,idem_rows('14:00'),5,idem_key(5)); echo $result['status']; exit(1);
}
if ($mode==='assert-lost') {
 $snapshot=idem_snapshot($doctor);
 idem_check((int)$snapshot['revision']===6 && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")===5,'successful commit persisted snapshot and receipt despite lost response');
 idem_check(ci_save_schedule_once($doctor,idem_rows('14:00'),5,idem_key(5))===array('status'=>'replayed','revision'=>6) && idem_snapshot($doctor)===$snapshot && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")===5,'same key resolves committed lost response without another write'); exit(0);
}
if ($mode==='cleanup') {
 $wpdb->query("DROP TRIGGER IF EXISTS `$trigger`"); $wpdb->query("DROP TABLE IF EXISTS `$table`");
 $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doctor)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$doctor)); wp_delete_post($doctor,true); unlink($meta); exit(0);
}
exit(1);

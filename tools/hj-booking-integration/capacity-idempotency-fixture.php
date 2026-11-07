<?php
/** Capacity idempotency prototype ONLY in disposable CI. Not product code. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb;
$admin=get_user_by('login','fixture_admin'); $owner=get_user_by('login','synthetic_doctor_a'); $patient=get_user_by('login','synthetic_race_8');
if (!$admin || !$owner || !$patient || !hj_booking_enabled()) { exit(1); } wp_set_current_user($admin->ID);
$mode=$argv[1] ?? ''; $meta=getenv('RUNNER_TEMP').'/hj-ci-capacity-idem.json'; $prefix=getenv('RUNNER_TEMP').'/hj-ci-capacity-idem';
$receipts=$wpdb->prefix.'ci_capacity_requests'; $trigger=$wpdb->prefix.'ci_capacity_receipt_fail';
function capidem_check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function capidem_key($n) { return str_pad(dechex($n),32,'0',STR_PAD_LEFT); }
function capidem_rule($weekday,$capacity) { return array(array('day_of_week'=>$weekday,'is_closed'=>0,'start_time'=>'17:00','end_time'=>'21:00','duration_minutes'=>60,'buffer_minutes'=>0,'capacity'=>$capacity)); }
function capidem_state($doctor) {
 global $wpdb; $d=hj_booking_table('doctors'); $s=hj_booking_table('schedules');
 return array('config'=>$wpdb->get_row($wpdb->prepare("SELECT duration_minutes,buffer_minutes,enabled,activation_generation FROM `$d` WHERE doctor_id=%d",$doctor),ARRAY_A), 'snapshot'=>$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$doctor),ARRAY_A));
}
function capidem_expected($state) { $c=$state['config']; return array('duration'=>(int)$c['duration_minutes'],'buffer'=>(int)$c['buffer_minutes'],'enabled'=>(bool)(int)$c['enabled'],'generation'=>(int)$c['activation_generation']); }
function capidem_count() { global $wpdb,$receipts; return (int)$wpdb->get_var("SELECT COUNT(*) FROM `$receipts`"); }
/** Separate experimental writer; mirrors current v2 policy only for comparison.
 * Successful receipts are historical, not a claim that state remains current.
 */
function ci_capacity_save_once($doctor,$rows,$revision,$enabled,$expected,$key) {
 if (!hj_booking_capacity_v2_ready() || !hj_booking_ready()) { return array('status'=>'unavailable'); }
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!is_int($revision) || $revision<0 || !is_bool($enabled) || !is_array($expected) || count($expected)!==4 || !isset($expected['duration'],$expected['buffer'],$expected['enabled'],$expected['generation']) || !is_int($expected['duration']) || !is_int($expected['buffer']) || !is_bool($expected['enabled']) || !is_int($expected['generation']) || $expected['generation']<0 || !is_string($key) || !preg_match('/^[a-f0-9]{32}$/D',$key)) { return array('status'=>'invalid'); }
 $rows=hj_booking_normalize_capacity_week($rows); if ($rows===false) { return array('status'=>'invalid'); }
 $json=wp_json_encode(array('version'=>2,'rows'=>$rows)); if (!is_string($json)) { return array('status'=>'invalid'); }
 $actor=get_current_user_id(); $hash=hash_hmac('sha256',wp_json_encode(array('v2',$doctor,$actor,$revision,$enabled,$expected,$rows)),wp_salt('auth'));
 return hj_booking_transaction($doctor,'ci_idempotent_capacity',function($db,$config) use($doctor,$revision,$enabled,$expected,$key,$json,$hash,$actor) {
  if (!hj_booking_can_configure($doctor) || get_current_user_id()!==$actor) { return array('status'=>'forbidden'); }
  $requests=$GLOBALS['receipts']; $s=hj_booking_table('schedules'); $a=hj_booking_table('appointments');
  $record=hj_booking_row($db,$db->prepare("SELECT actor_id,payload_hash,result_revision FROM `$requests` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if ($record) {
   if ((int)$record['actor_id']!==$actor || !hash_equals($record['payload_hash'],$hash)) { return array('status'=>'idempotency_conflict'); }
   return array('status'=>'replayed','revision'=>(int)$record['result_revision']);
  }
  if ((int)$config['duration_minutes']!==$expected['duration'] || (int)$config['buffer_minutes']!==$expected['buffer'] || (bool)(int)$config['enabled']!==$expected['enabled']) { return array('status'=>'revision_conflict'); }
  $generation=hj_booking_activation_generation($config);
  if ($generation===false || !in_array((int)$config['enabled'],array(0,1),true)) { return array('status'=>'unavailable'); }
  if ($generation!==$expected['generation']) { return array('status'=>'revision_conflict'); }
  $changed=(bool)(int)$config['enabled']!==$enabled;
  if ($changed && $generation===PHP_INT_MAX) { return array('status'=>'unavailable'); }
  $old=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  $current=$old ? (int)$old['revision'] : 0;
  if ($current!==$revision) { return array('status'=>'revision_conflict'); }
  if ($old && hj_booking_decode_snapshot($old)===false) { return array('status'=>'invalid_schedule'); }
  if ($current===PHP_INT_MAX) { return array('status'=>'invalid'); }
  if (hj_booking_row($db,$db->prepare("SELECT id FROM `$a` WHERE doctor_id=%d AND status<>'cancelled' AND blocked_until>%s LIMIT 1",$doctor,gmdate('Y-m-d H:i:s')))) { return array('status'=>'has_future_bookings'); }
  $next=$current+1; $digest=hash('sha256',$json);
  if ($old) { $n=hj_booking_query($db,$db->prepare("UPDATE `$s` SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d",$next,$json,$digest,$doctor)); }
  else { $n=hj_booking_query($db,$db->prepare("INSERT INTO `$s` (doctor_id,revision,rows_json,rows_hash) VALUES (%d,%d,%s,%s)",$doctor,$next,$json,$digest)); }
  if ($n!==1) { throw new RuntimeException('synthetic snapshot failed'); }
  $doctors=hj_booking_table('doctors'); $inc=$changed ? ',activation_generation=activation_generation+1' : '';
  hj_booking_query($db,$db->prepare("UPDATE `$doctors` SET enabled=%d$inc WHERE doctor_id=%d",$enabled ? 1 : 0,$doctor));
  if (hj_booking_query($db,$db->prepare("INSERT INTO `$requests` (doctor_id,request_key,actor_id,payload_hash,result_revision) VALUES (%d,%s,%d,%s,%d)",$doctor,$key,$actor,$hash,$next))!==1) { throw new RuntimeException('synthetic receipt failed'); }
  return array('status'=>'saved','revision'=>$next);
 });
}
if ($mode==='setup') {
 capidem_check(!is_file($meta) && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($receipts))) && hj_booking_install_schema(),'clean v1 fixture before CI-only v2 setup');
 $d=hj_booking_table('doctors'); $cols=$wpdb->get_col("SHOW COLUMNS FROM `$d`",0); capidem_check(!in_array('activation_generation',$cols,true),'activation test column initially absent');
 $doctors=array(); $added=false; $owned=false; $booked=array();
 try {
  foreach(array('reference','receipt') as $label) { $post=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic capacity idem '.$label,'post_author'=>$owner->ID),true); if (is_wp_error($post) || !$post) { throw new RuntimeException('fixture doctor failed'); } $doctors[]=(int)$post; }
  capidem_check($wpdb->query("ALTER TABLE `$d` ADD COLUMN activation_generation bigint unsigned NOT NULL DEFAULT 0")!==false,'CI-only activation column created'); $added=true;
  update_option('hj_booking_schema_version',2,false); hj_booking_schema_clear_option_cache();
  capidem_check(hj_booking_capacity_v2_ready() && hj_booking_ready(),'fixture-only v2 gate active');
  capidem_check($wpdb->query("CREATE TABLE `$receipts` (doctor_id bigint unsigned NOT NULL,request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id bigint unsigned NOT NULL,payload_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,result_revision bigint unsigned NOT NULL,PRIMARY KEY (doctor_id,request_key)) ENGINE=InnoDB")!==false,'CI-only capacity receipt table created'); $owned=true;
  $date=(new DateTimeImmutable('now',wp_timezone()))->modify('+2 days')->format('Y-m-d'); $weekday=strtolower((new DateTimeImmutable($date,new DateTimeZone('UTC')))->format('l'));
  foreach($doctors as $doc) { capidem_check(hj_booking_save_capacity_week($doc,capidem_rule($weekday,1),0,true)['status']==='saved','seed v2 snapshot and activation'); }
  wp_set_current_user($patient->ID);
  foreach($doctors as $i=>$doc) { $slots=hj_booking_public_slots($doc,$date); if (($slots['status'] ?? '')!=='ok' || !$slots['slots']) { throw new RuntimeException('synthetic slots missing'); }
   $result=hj_booking_create($doc,$slots['slots'][0],capidem_key(901+$i),'09123456789','1234567891',1);
   capidem_check($result['status']==='created','seed future booking before comparison'); $booked[]=(int)$result['id'];
  }
  wp_set_current_user($admin->ID);
  if (file_put_contents($meta,wp_json_encode(array('reference'=>$doctors[0],'doctor'=>$doctors[1],'weekday'=>$weekday,'bookings'=>$booked)))===false) { throw new RuntimeException('fixture metadata failed'); }
 } catch(Throwable $e) {
  wp_set_current_user($admin->ID); $a=hj_booking_table('appointments'); $p=hj_booking_table('details');
  foreach($doctors as $doc) { foreach($wpdb->get_col($wpdb->prepare("SELECT id FROM `$a` WHERE doctor_id=%d",$doc)) as $id) { $wpdb->delete($p,array('appointment_id'=>(int)$id)); } $wpdb->delete($a,array('doctor_id'=>$doc)); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doc)); $wpdb->delete($d,array('doctor_id'=>$doc)); wp_delete_post($doc,true); }
  if ($owned) { $wpdb->query("DROP TABLE `$receipts`"); } update_option('hj_booking_schema_version',1,false); hj_booking_schema_clear_option_cache(); if ($added) { $wpdb->query("ALTER TABLE `$d` DROP COLUMN activation_generation"); } throw $e;
 }
 exit(0);
}
$data=json_decode(@file_get_contents($meta),true); if (!is_array($data) || empty($data['doctor']) || empty($data['reference'])) { throw new RuntimeException('fixture metadata missing'); }
$doctor=(int)$data['doctor']; $reference=(int)$data['reference']; $weekday=$data['weekday'];
if ($mode==='policy') {
 $r=capidem_state($reference); $t=capidem_state($doctor); $expected=capidem_expected($t); $rule=capidem_rule($weekday,2);
 capidem_check(capidem_expected($r)===$expected && $r['snapshot']===$t['snapshot'],'reference and prototype begin with identical policies');
 $actual=hj_booking_save_capacity_week($reference,$rule,1,true,capidem_expected($r)); $test=ci_capacity_save_once($doctor,$rule,1,true,$expected,capidem_key(1));
 capidem_check($actual['status']==='has_future_bookings' && $test['status']===$actual['status'] && capidem_count()===0 && capidem_state($reference)===$r && capidem_state($doctor)===$t,'future booking blocks both writers and creates no receipt');
 capidem_check(hj_booking_pause_capacity($reference)['status']==='paused' && hj_booking_pause_capacity($doctor)['status']==='paused','pause both synthetic offices');
 $r2=capidem_state($reference); $t2=capidem_state($doctor);
 capidem_check(hj_booking_save_capacity_week($reference,$rule,1,true,$expected)['status']==='revision_conflict' && ci_capacity_save_once($doctor,$rule,1,true,$expected,capidem_key(1))['status']==='revision_conflict' && capidem_count()===0,'stale pre-pause form is rejected by both writers');
 capidem_check(hj_booking_save_capacity_week($reference,$rule,1,true,capidem_expected($r2))['status']==='has_future_bookings' && ci_capacity_save_once($doctor,$rule,1,true,capidem_expected($t2),capidem_key(1))['status']==='has_future_bookings','fresh paused form cannot bypass future booking');
 wp_set_current_user($patient->ID); capidem_check(hj_booking_patient_cancel($reference,(int)$data['bookings'][0])['status']==='updated' && hj_booking_patient_cancel($doctor,(int)$data['bookings'][1])['status']==='updated','cancel only synthetic future bookings'); wp_set_current_user($admin->ID);
 capidem_check(hj_booking_resume_capacity($reference,1,(int)$r2['config']['activation_generation'])['status']==='resumed' && hj_booking_resume_capacity($doctor,1,(int)$t2['config']['activation_generation'])['status']==='resumed','both offices resumed without editing snapshot');
 capidem_check(hj_booking_pause_capacity($reference)['status']==='paused' && hj_booking_pause_capacity($doctor)['status']==='paused','both offices paused again');
 $r3=capidem_state($reference); $t3=capidem_state($doctor);
 capidem_check(hj_booking_save_capacity_week($reference,$rule,1,true,capidem_expected($r2))['status']==='revision_conflict' && ci_capacity_save_once($doctor,$rule,1,true,capidem_expected($t2),capidem_key(1))['status']==='revision_conflict' && capidem_state($reference)===$r3 && capidem_state($doctor)===$t3,'ABA stale form rejected even with no future booking');
 capidem_check(capidem_expected($r3)===capidem_expected($t3) && $r3['snapshot']===$t3['snapshot'],'policy comparison leaves same paused state');
 $data['race_expected']=capidem_expected($t3); file_put_contents($meta,wp_json_encode($data)); exit(0);
}
if ($mode==='worker') {
 $worker=getenv('HJ_CAPIDEM_WORKER'); if (!is_string($worker) || !preg_match('/^[0-7]$/D',$worker)) { exit(1); }
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$worker) {
  if ($ctx['operation']!=='ci_idempotent_capacity') { return; }
  if ($event==='before_lock' && $worker!=='0') { file_put_contents($prefix.'-before-'.$worker,(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($event==='after_lock' && $worker==='0') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $result=ci_capacity_save_once($doctor,capidem_rule($weekday,2),1,true,$data['race_expected'],capidem_key(1)); echo $result['status']; exit(in_array($result['status'],array('saved','replayed'),true)?0:1);
}
if ($mode==='waiter') {
 $db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $end=microtime(true)+2;
 do { $ids=array(); foreach(glob($prefix.'-before-*') as $f) { $ids[]=(int)file_get_contents($f); } foreach(hj_booking_rows($db,'SHOW FULL PROCESSLIST') as $row) { if (in_array((int)$row['Id'],$ids,true) && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`')!==false) { echo 'WAITING'; $db->close(); exit(0); } } usleep(10000); clearstatcache(); } while(microtime(true)<$end); $db->close(); exit(1);
}
if ($mode==='after-race') {
 $state=capidem_state($doctor);
 capidem_check((int)$state['snapshot']['revision']===2 && (int)$state['config']['enabled']===1 && (int)$state['config']['activation_generation']===$data['race_expected']['generation']+1 && capidem_count()===1,'one revision, activation transition and receipt persisted');
 $reference_before=capidem_state($reference);
 $actual=hj_booking_save_capacity_week($reference,capidem_rule($weekday,2),1,true,capidem_expected($reference_before));
 capidem_check($actual['status']==='saved' && capidem_state($reference)===$state,'prototype successful result matches real v2 writer');
 capidem_check(ci_capacity_save_once($doctor,capidem_rule($weekday,3),1,true,$data['race_expected'],capidem_key(1))['status']==='idempotency_conflict','same key with changed capacity conflicts');
 capidem_check(hj_booking_pause_capacity($doctor)['status']==='paused','pause after stored save'); $paused=capidem_state($doctor);
 capidem_check(ci_capacity_save_once($doctor,capidem_rule($weekday,2),1,true,$data['race_expected'],capidem_key(1))===array('status'=>'replayed','revision'=>2) && capidem_state($doctor)===$paused,'historical replay never undoes later pause');
 wp_set_current_user($patient->ID); capidem_check(ci_capacity_save_once($doctor,capidem_rule($weekday,2),1,true,$data['race_expected'],capidem_key(1))['status']==='forbidden','unrelated patient cannot inspect receipt');
 wp_set_current_user($owner->ID); capidem_check(hj_booking_can_configure($doctor) && ci_capacity_save_once($doctor,capidem_rule($weekday,2),1,true,$data['race_expected'],capidem_key(1))['status']==='idempotency_conflict','different authorized actor cannot replay original receipt'); wp_set_current_user($admin->ID);
 capidem_check($wpdb->query("CREATE TRIGGER `$trigger` BEFORE INSERT ON `$receipts` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")!==false,'inject capacity receipt insert failure');
 try { capidem_check(ci_capacity_save_once($doctor,capidem_rule($weekday,3),2,true,capidem_expected($paused),capidem_key(2))['status']==='error' && capidem_state($doctor)===$paused && capidem_count()===1,'receipt insert failure rolls back snapshot and activation'); }
 finally { $wpdb->query("DROP TRIGGER `$trigger`"); }
 $data['second_expected']=capidem_expected($paused); file_put_contents($meta,wp_json_encode($data)); exit(0);
}
if ($mode==='precommit') {
 $killed=false;
 $hook=function($event,$db,$ctx) use(&$killed) { if (!$killed && $ctx['operation']==='ci_idempotent_capacity' && $event==='before_commit') { $killed=true; $id=(int)$db->get_var('SELECT CONNECTION_ID()'); $killer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $killer->suppress_errors(true); if (!$id || $killer->query('KILL CONNECTION '.$id)===false) { throw new RuntimeException('fixture kill failed'); } $killer->close(); } };
 $before=capidem_state($doctor); add_action('hj_booking_test_event',$hook,10,3);
 $result=ci_capacity_save_once($doctor,capidem_rule($weekday,3),2,true,$data['second_expected'],capidem_key(2)); remove_action('hj_booking_test_event',$hook,10);
 capidem_check($killed && $result['status']==='unknown' && capidem_state($doctor)===$before && capidem_count()===1,'precommit connection kill rolls back snapshot, activation and receipt');
 capidem_check(ci_capacity_save_once($doctor,capidem_rule($weekday,3),2,true,$data['second_expected'],capidem_key(2))===array('status'=>'saved','revision'=>3),'same key safely saves after rolled-back unknown');
 capidem_check(hj_booking_pause_capacity($doctor)['status']==='paused','pause before next lost-response test'); $data['third_expected']=capidem_expected(capidem_state($doctor)); file_put_contents($meta,wp_json_encode($data)); exit(0);
}
if ($mode==='lost') {
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix) { if ($ctx['operation']==='ci_idempotent_capacity' && $event==='after_commit' && ($ctx['result']['status'] ?? '')==='saved') { file_put_contents($prefix.'-committed','1'); $end=microtime(true)+20; while(microtime(true)<$end) { usleep(10000); } throw new RuntimeException('client was not interrupted'); } },10,3);
 $result=ci_capacity_save_once($doctor,capidem_rule($weekday,4),3,true,$data['third_expected'],capidem_key(3)); echo $result['status']; exit(1);
}
if ($mode==='after-lost') {
 $committed=capidem_state($doctor); capidem_check((int)$committed['snapshot']['revision']===4 && (int)$committed['config']['enabled']===1 && capidem_count()===3,'lost response followed a committed snapshot, activation and receipt');
 capidem_check(hj_booking_pause_capacity($doctor)['status']==='paused','newer pause recorded after lost response'); $paused=capidem_state($doctor);
 capidem_check(ci_capacity_save_once($doctor,capidem_rule($weekday,4),3,true,$data['third_expected'],capidem_key(3))===array('status'=>'replayed','revision'=>4) && capidem_state($doctor)===$paused && capidem_count()===3,'replayed lost response stays historical and cannot reverse newer pause'); exit(0);
}
if ($mode==='cleanup') {
 $a=hj_booking_table('appointments'); $p=hj_booking_table('details'); $d=hj_booking_table('doctors');
 foreach(array($reference,$doctor) as $doc) { foreach($wpdb->get_col($wpdb->prepare("SELECT id FROM `$a` WHERE doctor_id=%d",$doc)) as $id) { $wpdb->delete($p,array('appointment_id'=>(int)$id)); } $wpdb->delete($a,array('doctor_id'=>$doc)); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doc)); $wpdb->delete($d,array('doctor_id'=>$doc)); wp_delete_post($doc,true); }
 $wpdb->query("DROP TRIGGER IF EXISTS `$trigger`"); $wpdb->query("DROP TABLE `$receipts`"); update_option('hj_booking_schema_version',1,false); hj_booking_schema_clear_option_cache(); $wpdb->query("ALTER TABLE `$d` DROP COLUMN activation_generation"); unlink($meta); exit(0);
}
exit(1);

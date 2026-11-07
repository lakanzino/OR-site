<?php
/** CI-only experiment, never load in production or as a WordPress endpoint. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
$root=realpath(getenv('RUNNER_TEMP')); $plugin=realpath(getenv('HJ_V1_PAUSE_PLUGIN'));
if (!$root || !$plugin || strpos($plugin,$root.'/hj-v1-pause-')!==0 || !is_file($plugin.'/hamyar-doctors.php')) { exit(1); }
require_once $plugin.'/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb;
$admin=get_user_by('login','fixture_admin'); $patient=get_user_by('login','synthetic_race_5');
if (!$admin || !$patient || !hj_booking_enabled() || hj_booking_capacity_v2_ready()) { exit(1); }
wp_set_current_user($admin->ID);
$table=$wpdb->prefix.'ci_v1_pause_guard'; $meta=getenv('RUNNER_TEMP').'/hj-ci-v1-pause.json'; $prefix=getenv('RUNNER_TEMP').'/hj-ci-v1-pause'; $mode=$argv[1] ?? '';
function pause_check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function pause_rows() { return array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00','end_time'=>'12:00')); }
function pause_guard_valid($row) {
 if (!is_array($row) || !isset($row['paused'],$row['intent_active'],$row['epoch'])) { return false; }
 foreach(array('paused','intent_active') as $k) { if (!in_array((string)$row[$k],array('0','1'),true)) { return false; } }
 $n=(string)$row['epoch']; return preg_match('/^(0|[1-9][0-9]*)$/D',$n) && strlen($n)<=strlen((string)PHP_INT_MAX) && (string)(int)$n===$n;
}
function pause_state($doctor) {
 global $wpdb,$table; $d=hj_booking_table('doctors'); $s=hj_booking_table('schedules');
 return array('config'=>$wpdb->get_row($wpdb->prepare("SELECT enabled,duration_minutes,buffer_minutes FROM `$d` WHERE doctor_id=%d",$doctor),ARRAY_A), 'snapshot'=>$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$doctor),ARRAY_A), 'guard'=>$wpdb->get_row($wpdb->prepare("SELECT paused,intent_active,epoch FROM `$table` WHERE doctor_id=%d",$doctor),ARRAY_A));
}
/** Normal stop writes doctor state and guard atomically. */
function ci_v1_pause($doctor) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 return hj_booking_transaction($doctor,'ci_v1_pause',function($db,$config) use($doctor,$table) {
  if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  if (!in_array((string)$config['enabled'],array('0','1'),true)) { return array('status'=>'invalid_config'); }
  $row=hj_booking_row($db,$db->prepare("SELECT paused,intent_active,epoch FROM `$table` WHERE doctor_id=%d",$doctor));
  if (!pause_guard_valid($row) || (int)$row['epoch']===PHP_INT_MAX) { return array('status'=>'guard_unavailable'); }
  $epoch=(int)$row['epoch']; $d=hj_booking_table('doctors');
  hj_booking_query($db,$db->prepare("UPDATE `$d` SET enabled=0 WHERE doctor_id=%d",$doctor));
  if (hj_booking_query($db,$db->prepare("UPDATE `$table` SET paused=1,epoch=%d WHERE doctor_id=%d",$epoch+1,$doctor))!==1) { throw new RuntimeException('pause guard write failed'); }
  return array('status'=>'paused','epoch'=>$epoch+1);
 });
}
/** Separate fresh transaction: disables only, with NO durable fencing guarantee. */
function ci_v1_emergency_disable($doctor) {
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 return hj_booking_transaction($doctor,'ci_v1_emergency_disable',function($db,$config) use($doctor) {
  if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  if (!in_array((string)$config['enabled'],array('0','1'),true)) { return array('status'=>'invalid_config'); }
  $d=hj_booking_table('doctors'); hj_booking_query($db,$db->prepare("UPDATE `$d` SET enabled=0 WHERE doctor_id=%d",$doctor));
  return array('status'=>'disabled_without_latch');
 });
}
/** Experimental resume: matching snapshot and epoch, with no active intent. */
function ci_v1_resume($doctor,$expected_revision,$expected_epoch) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!is_int($expected_revision) || $expected_revision<1 || !is_int($expected_epoch) || $expected_epoch<0) { return array('status'=>'invalid'); }
 return hj_booking_transaction($doctor,'ci_v1_resume',function($db,$config) use($doctor,$expected_revision,$expected_epoch,$table) {
  if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $row=hj_booking_row($db,$db->prepare("SELECT paused,intent_active,epoch FROM `$table` WHERE doctor_id=%d",$doctor));
  if (!pause_guard_valid($row)) { return array('status'=>'guard_unavailable'); }
  if ((int)$row['intent_active']===1) { return array('status'=>'intent_pending'); }
  if ((int)$row['paused']!==1 || (int)$config['enabled']!==0 || (int)$row['epoch']!==$expected_epoch || $expected_epoch===PHP_INT_MAX) { return array('status'=>'stale'); }
  $s=hj_booking_table('schedules'); $snapshot=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  if (!$snapshot || hj_booking_legacy_snapshot_rows($snapshot)===false || (int)$snapshot['revision']!==$expected_revision) { return array('status'=>'stale'); }
  $d=hj_booking_table('doctors');
  if (hj_booking_query($db,$db->prepare("UPDATE `$d` SET enabled=1 WHERE doctor_id=%d",$doctor))!==1 || hj_booking_query($db,$db->prepare("UPDATE `$table` SET paused=0,epoch=%d WHERE doctor_id=%d",$expected_epoch+1,$doctor))!==1) { throw new RuntimeException('resume failed'); }
  return array('status'=>'resumed','epoch'=>$expected_epoch+1);
 });
}
if ($mode==='setup') {
 pause_check(!is_file($meta) && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) && hj_booking_install_schema(),'clean v1 database');
 $d=hj_booking_table('doctors'); $doctors=array(); $owned=false;
 try {
  pause_check($wpdb->query("CREATE TABLE `$table` (doctor_id bigint unsigned NOT NULL PRIMARY KEY,paused tinyint unsigned NOT NULL,intent_active tinyint unsigned NOT NULL,epoch bigint unsigned NOT NULL) ENGINE=InnoDB")!==false,'create synthetic v1 pause guard'); $owned=true;
  foreach(array('manual','pause_first','resume_first','missing','lost','fault','corrupt','stop_booking','booking_stop','fallback') as $name) {
   $id=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic v1 pause '.$name,'post_author'=>$admin->ID),true);
   if (is_wp_error($id) || !$id) { throw new RuntimeException('fixture doctor failed'); }
   $doctors[$name]=(int)$id;
   // Test bootstrap only: creating production guard rows requires a real migration.
   pause_check($wpdb->insert($table,array('doctor_id'=>(int)$id,'paused'=>0,'intent_active'=>0,'epoch'=>0))===1,'seed synthetic guard '.$name);
   // Bootstrap the old config before the injected guard enforces consistency.
   pause_check($wpdb->insert($d,array('doctor_id'=>(int)$id,'duration_minutes'=>30,'buffer_minutes'=>5,'enabled'=>1))===1 && hj_booking_save_schedule((int)$id,pause_rows(),0)['status']==='saved','seed v1 schedule '.$name);
  }
  if (file_put_contents($meta,wp_json_encode($doctors))===false) { throw new RuntimeException('metadata failed'); }
 } catch(Throwable $e) {
  foreach($doctors as $id) { $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$id)); $wpdb->delete($d,array('doctor_id'=>$id)); wp_delete_post($id,true); }
  if ($owned) { $wpdb->query("DROP TABLE `$table`"); } throw $e;
 }
 exit(0);
}
$data=json_decode(@file_get_contents($meta),true); if (!is_array($data) || count($data)!==10) { throw new RuntimeException('missing metadata'); }
if ($mode==='manual') {
 $id=(int)$data['manual']; $date=(new DateTimeImmutable('now',wp_timezone()))->modify('next monday')->format('Y-m-d'); $slots=hj_booking_public_slots($id,$date);
 pause_check($slots['status']==='ok' && !empty($slots['slots']),'available v1 slots before stop');
 wp_set_current_user($patient->ID); $created=hj_booking_create($id,$slots['slots'][0],str_repeat('c',31).'5','09123456789','1234567891',1); $appointment=(int)($created['id'] ?? 0);
 pause_check($created['status']==='created' && $appointment>0,'synthetic future booking created');
 try {
  pause_check(ci_v1_pause($id)['status']==='forbidden','patient cannot pause'); wp_set_current_user($admin->ID);
  $before=pause_state($id); pause_check($wpdb->update($table,array('intent_active'=>1),array('doctor_id'=>$id))!==false,'mark synthetic active intent');
  $stopped=ci_v1_pause($id); $after=pause_state($id);
  pause_check($stopped===array('status'=>'paused','epoch'=>1) && (int)$after['config']['enabled']===0 && $before['snapshot']===$after['snapshot'] && $before['config']['duration_minutes']===$after['config']['duration_minutes'] && $before['config']['buffer_minutes']===$after['config']['buffer_minutes'],'pause preserves schedule, duration and buffer while active intent remains');
  $a=hj_booking_table('appointments'); pause_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$a` WHERE id=%d AND doctor_id=%d",$appointment,$id))===1,'existing future booking preserved');
  pause_check(ci_v1_resume($id,1,1)['status']==='intent_pending' && hj_booking_configure($id,30,5,true)['status']==='coordination_blocked' && hj_booking_save_schedule($id,pause_rows(),1)['status']==='coordination_blocked','active intent prevents resume or old writers');
  pause_check(hj_booking_public_slots($id,$date)['status']==='unavailable','pause closes new bookings');
  $probe=hj_booking_transaction($id,'ci_v1_intent_probe',function($db,$config) use($id,$table) { $r=hj_booking_row($db,$db->prepare("SELECT paused,intent_active,epoch FROM `$table` WHERE doctor_id=%d",$id)); return array('status'=>pause_guard_valid($r) && (int)$r['intent_active']===1 && (int)$r['epoch']===0 && (int)$config['enabled']===1 ? 'current' : 'stale'); });
  pause_check($probe['status']==='stale','pre-pause prepared operation token invalidated');
  wp_set_current_user($patient->ID); pause_check(hj_booking_patient_cancel($id,$appointment)['status']==='updated','patient cancellation remains available while paused');
 } finally {
  wp_set_current_user($admin->ID); if ($appointment) { $wpdb->delete(hj_booking_table('details'),array('appointment_id'=>$appointment)); $wpdb->delete(hj_booking_table('appointments'),array('id'=>$appointment)); }
 }
 exit(0);
}
if ($mode==='seed-races') { foreach(array('pause_first','resume_first') as $name) { pause_check(ci_v1_pause((int)$data[$name])===array('status'=>'paused','epoch'=>1),'initial pause '.$name); } exit(0); }
if ($mode==='worker') {
 $case=getenv('HJ_V1_PAUSE_CASE'); $role=getenv('HJ_V1_PAUSE_ROLE'); if (!in_array($case,array('pause_first','resume_first'),true) || !in_array($role,array('holder','waiter'),true)) { exit(1); }
 $id=(int)$data[$case]; $stop=($role==='holder')===($case==='pause_first'); $operation=$stop ? 'ci_v1_pause' : 'ci_v1_resume';
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$role,$operation) {
  if ($ctx['operation']!==$operation) { return; }
  if ($role==='waiter' && $event==='before_lock') { file_put_contents($prefix.'-before',(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($role==='holder' && $event==='after_lock') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $result=$stop ? ci_v1_pause($id) : ci_v1_resume($id,1,1); echo $result['status']; exit(in_array($result['status'],array('paused','resumed','stale'),true)?0:1);
}
if ($mode==='waiter') {
 $id=(int)@file_get_contents($prefix.'-before'); $db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $end=microtime(true)+2;
 do { foreach(hj_booking_rows($db,'SHOW FULL PROCESSLIST') as $row) { if ((int)$row['Id']===$id && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`')!==false) { echo 'WAITING'; $db->close(); exit(0); } } usleep(10000); } while(microtime(true)<$end); $db->close(); exit(1);
}
if ($mode==='check-race') {
 $case=getenv('HJ_V1_PAUSE_CASE'); if (!in_array($case,array('pause_first','resume_first'),true)) { exit(1); }
 $state=pause_state((int)$data[$case]); $epoch=$case==='pause_first'?2:3;
 pause_check((int)$state['config']['enabled']===0 && (int)$state['guard']['paused']===1 && (int)$state['guard']['epoch']===$epoch && (int)$state['snapshot']['revision']===1,'racing stop leaves doctor paused with fresh epoch '.$case); exit(0);
}
if ($mode==='booking-worker') {
 $case=getenv('HJ_V1_PAUSE_CASE'); $role=getenv('HJ_V1_PAUSE_ROLE'); if (!in_array($case,array('stop_booking','booking_stop'),true) || !in_array($role,array('holder','waiter'),true)) { exit(1); }
 $id=(int)$data[$case]; $stop=($role==='holder')===($case==='stop_booking'); $operation=$stop ? 'ci_v1_pause' : 'create';
 $date=(new DateTimeImmutable('now',wp_timezone()))->modify('next monday')->format('Y-m-d'); $start=(new DateTimeImmutable($date.' 09:00:00',wp_timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
 if (!$stop) { wp_set_current_user($patient->ID); }
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$role,$operation) {
  if ($ctx['operation']!==$operation) { return; }
  if ($role==='waiter' && $event==='before_lock') { file_put_contents($prefix.'-before',(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($role==='holder' && $event==='after_lock') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $key=$case==='stop_booking' ? str_repeat('e',31).'1' : str_repeat('e',31).'2';
 $result=$stop ? ci_v1_pause($id) : hj_booking_create($id,$start,$key,'09123456789','1234567891',1);
 echo $result['status']; exit(in_array($result['status'],array('paused','created','unavailable'),true)?0:1);
}
if ($mode==='check-booking-race') {
 $case=getenv('HJ_V1_PAUSE_CASE'); if (!in_array($case,array('stop_booking','booking_stop'),true)) { exit(1); }
 $id=(int)$data[$case]; $state=pause_state($id); $a=hj_booking_table('appointments');
 $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$a` WHERE doctor_id=%d",$id)); $expected=$case==='stop_booking'?0:1;
 pause_check(count($ids)===$expected && (int)$state['config']['enabled']===0 && (int)$state['guard']['epoch']===1 && (int)$state['snapshot']['revision']===1,'pause versus booking race preserves correct booking count '.$case);
 foreach($ids as $appointment) { $wpdb->delete(hj_booking_table('details'),array('appointment_id'=>(int)$appointment)); $wpdb->delete($a,array('id'=>(int)$appointment)); }
 exit(0);
}
if ($mode==='lost') {
 $id=(int)$data['lost'];
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix) { if ($ctx['operation']==='ci_v1_pause' && $event==='after_commit' && ($ctx['result']['status'] ?? '')==='paused') { file_put_contents($prefix.'-committed','1'); $end=microtime(true)+20; while(microtime(true)<$end) { usleep(10000); } throw new RuntimeException('not interrupted'); } },10,3);
 ci_v1_pause($id); exit(1);
}
if ($mode==='after-lost') {
 $id=(int)$data['lost']; $state=pause_state($id);
 pause_check((int)$state['config']['enabled']===0 && (int)$state['guard']['epoch']===1,'lost response followed committed pause');
 pause_check(ci_v1_pause($id)===array('status'=>'paused','epoch'=>2) && ci_v1_resume($id,1,1)['status']==='stale','retry stop preserves pause and invalidates old resume token'); exit(0);
}
if ($mode==='fault') {
 $id=(int)$data['fault']; $before=pause_state($id); $trigger=$wpdb->prefix.'ci_pause_fail';
 pause_check($wpdb->query("CREATE TRIGGER `$trigger` BEFORE UPDATE ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")!==false,'inject guard update failure');
 try { pause_check(ci_v1_pause($id)['status']==='error' && pause_state($id)===$before,'failed guard write rolls back pause and reports error'); }
 finally { $wpdb->query("DROP TRIGGER `$trigger`"); }
 $id=(int)$data['corrupt']; pause_check($wpdb->update($table,array('paused'=>2),array('doctor_id'=>$id))===1,'inject invalid guard value');
 pause_check(ci_v1_pause($id)['status']==='guard_unavailable' && (int)pause_state($id)['config']['enabled']===1,'normal stop refuses invalid guard without claiming pause');
 pause_check(ci_v1_emergency_disable($id)['status']==='disabled_without_latch' && (int)pause_state($id)['config']['enabled']===0,'separate fallback disables without claiming a durable latch');
 pause_check(ci_v1_resume($id,1,0)['status']==='guard_unavailable' && hj_booking_configure($id,30,5,true)['status']==='guard_unavailable','invalid guard prevents coordinated reactivation');
 $id=(int)$data['fallback']; pause_check($wpdb->update($table,array('paused'=>1,'epoch'=>2),array('doctor_id'=>$id))===1,'inject inconsistent synthetic latch');
 pause_check(ci_v1_emergency_disable($id)['status']==='disabled_without_latch' && ci_v1_resume($id,1,2)['status']==='resumed','counterexample: unlatched fallback alone cannot block stale resume'); exit(0);
}
if ($mode==='missing') {
 $id=(int)$data['missing']; $before=pause_state($id); $d=hj_booking_table('doctors');
 pause_check($wpdb->query("DROP TABLE `$table`")!==false,'remove only synthetic guard table');
 pause_check(ci_v1_pause($id)['status']==='error','normal stop reports guard read failure');
 pause_check(ci_v1_emergency_disable($id)['status']==='disabled_without_latch','fresh fallback transaction attempts disable when guard table is absent');
 $config=$wpdb->get_row($wpdb->prepare("SELECT enabled,duration_minutes,buffer_minutes FROM `$d` WHERE doctor_id=%d",$id),ARRAY_A);
 pause_check((int)$config['enabled']===0 && $config['duration_minutes']===$before['config']['duration_minutes'] && $config['buffer_minutes']===$before['config']['buffer_minutes'],'unlatched stop leaves config disabled without changing settings');
 pause_check(hj_booking_configure($id,30,5,true)['status']==='error' && hj_booking_save_schedule($id,pause_rows(),1)['status']==='error' && ci_v1_resume($id,1,0)['status']==='error','coordinated writers and resume fail closed with guard missing'); exit(0);
}
if ($mode==='cleanup') {
 $wpdb->query("DROP TABLE IF EXISTS `$table`"); foreach($data as $id) { $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>(int)$id)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>(int)$id)); wp_delete_post((int)$id,true); } unlink($meta); exit(0);
}
exit(1);

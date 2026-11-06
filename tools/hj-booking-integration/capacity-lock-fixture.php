<?php
/** Synthetic integration of the production lock and pure capacity function only. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
$admin=get_user_by('login','fixture_admin'); if (!$admin) { throw new RuntimeException('Missing disposable admin'); } wp_set_current_user($admin->ID);
$prefix=getenv('RUNNER_TEMP').'/hj-capacity-real-lock'; $meta=$prefix.'-meta.json'; $mode=$argv[1] ?? '';
function cap_assert($ok,$msg) { if (!$ok) { throw new RuntimeException($msg); } echo "PASS: $msg\n"; }
if ($mode==='setup') {
 update_option('timezone_string','Asia/Tehran');
 cap_assert(hj_booking_install_schema(), 'real schema ready for synthetic capacity transaction');
 $id=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic capacity mutex doctor','post_author'=>$admin->ID),true);
 if (is_wp_error($id) || !$id) { throw new RuntimeException('Failed doctor fixture'); }
 $date=(new DateTimeImmutable('now',wp_timezone()))->modify('+2 days')->format('Y-m-d');
 $weekday=strtolower((new DateTimeImmutable($date,new DateTimeZone('UTC')))->format('l'));
 cap_assert(hj_booking_configure((int)$id,60,0,true)['status']==='configured','real doctor mutex configured');
 cap_assert(hj_booking_save_schedule((int)$id,array(array('day_of_week'=>$weekday,'is_closed'=>0,'start_time'=>'17:00','end_time'=>'21:00')),0)['status']==='saved','real hashed schedule snapshot created');
 file_put_contents($meta,wp_json_encode(array('doctor'=>(int)$id,'date'=>$date)));
 exit(0);
}
$data=json_decode(file_get_contents($meta),true); if (!is_array($data) || empty($data['doctor']) || empty($data['date'])) { throw new RuntimeException('Missing fixture metadata'); }
$id=(int)$data['doctor'];
if ($mode==='waiter') {
 global $wpdb; $until=microtime(true)+1.5;
 do {
  $ids=array(); foreach (glob($prefix.'-before-*') as $path) { $ids[]=(int)file_get_contents($path); }
  $processes=$wpdb->get_results('SHOW FULL PROCESSLIST',ARRAY_A);
  foreach ($processes as $row) {
   if (in_array((int)$row['Id'],$ids,true) && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`') !== false) { echo "WAITING"; exit(0); }
  }
  usleep(10000);
 } while (microtime(true)<$until);
 exit(1);
}
if ($mode==='worker' || $mode==='retry') {
 $worker=getenv('HJ_CAP_REAL_WORKER'); if (!is_string($worker) || !preg_match('/^(?:[0-9]|1[0-2])$/D',$worker)) { exit(1); }
 if ($mode==='worker') {
  file_put_contents($prefix.'-ready-'.$worker,'ready'); $until=microtime(true)+25;
  while (!is_file($prefix.'-start-'.($worker==='0'?'holder':'others')) && microtime(true)<$until) { usleep(10000); clearstatcache(); }
  if (!is_file($prefix.'-start-'.($worker==='0'?'holder':'others'))) { throw new RuntimeException('Start barrier timed out'); }
 }
 add_action('hj_booking_test_event',function($event,$db,$context) use($prefix,$worker,$mode) {
  if ($mode!=='worker' || $context['operation']!=='capacity_fixture') { return; }
  if ($event==='before_lock' && $worker!=='0') { file_put_contents($prefix.'-before-'.$worker,(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($event==='after_lock' && $worker==='0') {
   file_put_contents($prefix.'-locked','locked'); $until=microtime(true)+10;
   while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); }
   if (!is_file($prefix.'-release')) { throw new RuntimeException('Lock release timed out'); }
  }
 },10,3);
 $result=hj_booking_transaction($id,'capacity_fixture',function($db,$config) use($id,$data,$worker) {
  if (!(int)$config['enabled']) { return array('status'=>'disabled'); }
  $snapshot=hj_booking_snapshot_locked($db,$id); if (!$snapshot || count($snapshot['rows'])!==1) { return array('status'=>'invalid_snapshot'); }
  $row=$snapshot['rows'][0]; $rule=array('start_time'=>substr($row['start_time'],0,5),'end_time'=>substr($row['end_time'],0,5),'duration_minutes'=>(int)$config['duration_minutes'],'buffer_minutes'=>(int)$config['buffer_minutes'],'capacity'=>3);
  $table=hj_booking_table('appointments');
  $occupied=hj_booking_rows($db,$db->prepare("SELECT starts_at,blocked_until,status FROM `$table` WHERE doctor_id=%d AND status IN ('pending','confirmed','completed','no_show')",$id));
  $day=hj_booking_compute_capacity_day($data['date'],$rule,$occupied,time(),14,wp_timezone());
  if ($day===false || !$day['slots']) { return array('status'=>'invalid_calculation'); }
  $slot=$day['slots'][0]; if ($slot['remaining']<1) { return array('status'=>'full'); }
  $key=substr(hash('sha256','test-only-capacity-'.$worker),0,32); $hash=hash('sha256','test-only-payload-'.$worker);
  $n=hj_booking_query($db,$db->prepare("INSERT INTO `$table` (doctor_id,patient_id,request_key,request_hash,starts_at,ends_at,blocked_until,schedule_revision,status) VALUES (%d,%d,%s,%s,%s,%s,%s,%d,'pending')",$id,700000+(int)$worker,$key,$hash,$slot['start'],$slot['end'],$slot['blocked_until'],$snapshot['revision']));
  return array('status'=>$n===1?'created':'error');
 });
 echo $result['status']; exit(0);
}
if ($mode==='cancel') {
 global $wpdb; $table=hj_booking_table('appointments'); $appt=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE doctor_id=%d AND status='pending' ORDER BY id LIMIT 1",$id));
 cap_assert($appt>0 && hj_booking_change_status($id,$appt,'cancelled')['status']==='updated','actual status writer cancels one synthetic reservation under doctor mutex'); exit(0);
}
if ($mode==='verify' || $mode==='cleanup') {
 global $wpdb; $table=hj_booking_table('appointments');
 if ($mode==='verify') {
  $rows=$wpdb->get_results($wpdb->prepare("SELECT status,COUNT(*) AS n FROM `$table` WHERE doctor_id=%d GROUP BY status",$id),OBJECT_K);
  cap_assert(isset($rows['pending'],$rows['cancelled']) && (int)$rows['pending']->n===3 && (int)$rows['cancelled']->n===1,'final real schema has three active reservations and one cancelled'); exit(0);
 }
 // Dispose of this fixture only; no uninstall/production cleanup behavior tested.
 $wpdb->delete($table,array('doctor_id'=>$id)); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$id)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$id)); wp_delete_post($id,true); unlink($meta); exit(0);
}
exit(1);

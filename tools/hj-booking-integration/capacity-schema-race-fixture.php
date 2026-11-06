<?php
/** Destructive multi-process fixture. Library refuses anything but disposable CI. */
require __DIR__ . '/capacity-schema-experiment-lib.php';
$path=getenv('RUNNER_TEMP').'/hj-ci-schema-race'; $mode=$argv[1] ?? '';
$s=hj_booking_table('schedules'); $a=hj_booking_table('appointments'); $p=hj_booking_table('details');
function race_hash($db,$d) { return hash('sha256',wp_json_encode(legacy_rows($db,$d))); }
if ($mode==='setup') {
 schema_check(hj_booking_ready() && !schema_exists($db,$extra) && !isset(schema_columns($db,$d)['activation_generation']) && !is_file($path.'-baseline'),'race starts with clean verified v1');
 foreach(array($d,$s,$a) as $table) { schema_check(!hj_booking_row($db,"SELECT 1 AS present FROM `$table` WHERE doctor_id=998701 LIMIT 1"),'race synthetic doctor ID unused'); }
 $owned=false;
 try {
  schema_check($wpdb->insert($d,array('doctor_id'=>998701,'duration_minutes'=>7,'buffer_minutes'=>5,'enabled'=>1))===1,'race legacy doctor seeded'); $owned=true;
  $json=wp_json_encode(array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00:30','end_time'=>'10:00:30')));
  schema_check($wpdb->insert($s,array('doctor_id'=>998701,'revision'=>7,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json)))===1,'race snapshot seeded');
  foreach(array('pending','confirmed','cancelled') as $i=>$status) {
   schema_check($wpdb->insert($a,array('doctor_id'=>998701,'patient_id'=>998702+$i,'request_key'=>str_repeat((string)($i+4),32),'request_hash'=>str_repeat((string)($i+4),64),'starts_at'=>'2030-01-07 09:00:30','ends_at'=>'2030-01-07 09:07:30','blocked_until'=>'2030-01-07 09:12:30','schedule_revision'=>7,'status'=>$status))===1,'race booking seeded');
   schema_check($wpdb->insert($p,array('appointment_id'=>(int)$wpdb->insert_id,'phone'=>'09123456789','national_code'=>'1234567891'))===1,'race booking details seeded');
  }
  if (file_put_contents($path.'-baseline',race_hash($db,$d))!==64) { throw new RuntimeException('baseline file failed'); }
 } catch (Throwable $e) {
  if ($owned) { foreach($wpdb->get_col("SELECT id FROM `$a` WHERE doctor_id=998701") as $id) { $wpdb->delete($p,array('appointment_id'=>(int)$id)); } $wpdb->delete($a,array('doctor_id'=>998701)); $wpdb->delete($s,array('doctor_id'=>998701)); $wpdb->delete($d,array('doctor_id'=>998701)); }
  throw $e;
 }
 exit(0);
}
if (!is_file($path.'-baseline')) { throw new RuntimeException('race baseline missing'); }
if ($mode==='worker') {
 $actor=getenv('HJ_SCHEMA_ACTOR'); $checkpoint=getenv('HJ_SCHEMA_CHECKPOINT');
 if (!in_array($actor,array('holder','waiter1','waiter2','waiter3'),true) || !in_array($checkpoint,array('column','table'),true)) { exit(1); }
 $id=hj_booking_row($db,'SELECT CONNECTION_ID() AS id'); file_put_contents($path.'-before-'.$actor,(string)$id['id']);
 $callback=function($event,$connection) use($actor,$checkpoint,$path) {
  if ($actor==='holder' && $event===$checkpoint) {
   file_put_contents($path.'-holder-locked',$checkpoint);
   // Coordinator kills us while the connection owns the advisory lock.
   $deadline=microtime(true)+20;
   while (microtime(true)<$deadline) { usleep(10000); }
   throw new RuntimeException('holder was not terminated');
  }
 };
 $result=schema_prototype($db,$d,$extra,'',$callback); echo $result; $db->close(); exit($result==='prepared' ? 0 : 1);
}
if ($mode==='waiter-check') {
 $end=microtime(true)+2;
 do {
  $ids=array(); foreach(glob($path.'-before-waiter*') as $file) { $ids[]=(int)file_get_contents($file); }
  foreach(hj_booking_rows($db,'SHOW FULL PROCESSLIST') as $row) {
   if (in_array((int)$row['Id'],$ids,true) && stripos((string)($row['Info'] ?? ''),'GET_LOCK')!==false) { echo 'WAITING'; exit(0); }
  }
  usleep(10000); clearstatcache();
 } while(microtime(true)<$end);
 exit(1);
}
if ($mode==='check') {
 schema_check(schema_doctors_valid($db,$d) && isset(schema_columns($db,$d)['activation_generation']) && schema_extra_valid($db,$extra),'concurrent reruns leave compatible schema');
 schema_check(race_hash($db,$d)===trim(file_get_contents($path.'-baseline')),'legacy schedule, bookings, patient details and secretary rows unchanged');
 schema_check((string)get_option('hj_booking_schema_version')==='1' && (string)get_option('hj_booking_schema_verified')==='1' && !hj_booking_capacity_v2_ready(),'race does not activate v2');
 $lock=hj_booking_row($db,"SELECT IS_FREE_LOCK('hj-ci-schema-experiment') AS available");
 schema_check((int)$lock['available']===1,'advisory lock released after killed holder and all waiters');
 exit(0);
}
if ($mode==='reset') {
 // Only objects created during these disposable rounds are removed.
 if (schema_exists($db,$extra)) { hj_booking_query($db,"DROP TABLE `$extra`"); }
 if (isset(schema_columns($db,$d)['activation_generation'])) { hj_booking_query($db,"ALTER TABLE `$d` DROP COLUMN activation_generation"); }
 schema_check(race_hash($db,$d)===trim(file_get_contents($path.'-baseline')),'reset keeps every synthetic legacy row'); exit(0);
}
if ($mode==='cleanup') {
 if (schema_exists($db,$extra)) { hj_booking_query($db,"DROP TABLE `$extra`"); }
 if (isset(schema_columns($db,$d)['activation_generation'])) { hj_booking_query($db,"ALTER TABLE `$d` DROP COLUMN activation_generation"); }
 foreach($wpdb->get_col("SELECT id FROM `$a` WHERE doctor_id=998701") as $id) { $wpdb->delete($p,array('appointment_id'=>(int)$id)); }
 $wpdb->delete($a,array('doctor_id'=>998701)); $wpdb->delete($s,array('doctor_id'=>998701)); $wpdb->delete($d,array('doctor_id'=>998701));
 @unlink($path.'-baseline'); exit(0);
}
exit(1);

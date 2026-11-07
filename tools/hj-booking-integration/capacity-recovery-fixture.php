<?php
/** Destructive, synthetic recovery experiment; never an installer or restore tool. */
require __DIR__.'/capacity-schema-experiment-lib.php';
$mode=$argv[1] ?? ''; $path=getenv('RUNNER_TEMP').'/hj-ci-recovery';
$names=array('doctors','schedules','appointments','details','secretaries','options');
function recovery_tables($kind) {
 global $wpdb,$names;
 $tables=array(); foreach($names as $name) {
  $tables[$name]=$kind==='source' ? ($name==='options' ? $wpdb->options : hj_booking_table($name)) : $wpdb->prefix.'ci_recovery_'.$kind.'_'.$name;
  if (!preg_match('/^[A-Za-z0-9_]+$/D',$tables[$name])) { throw new RuntimeException('invalid fixture table'); }
 }
 return $tables;
}
function recovery_columns($db,$table) {
 $rows=hj_booking_rows($db,"SHOW FULL COLUMNS FROM `$table`");
 return array_map(function($r){return array($r['Field'],$r['Type'],$r['Collation'],$r['Null'],$r['Default'],$r['Extra']);},$rows);
}
function recovery_indexes($db,$table) {
 $out=array(); foreach(hj_booking_rows($db,"SHOW INDEX FROM `$table`") as $r) { $out[]=array($r['Key_name'],(int)$r['Non_unique'],(int)$r['Seq_in_index'],$r['Column_name'],$r['Sub_part'],$r['Index_type']); }
 usort($out,function($a,$b){return strcmp($a[0],$b[0]) ?: ($a[2]<=>$b[2]);}); return $out;
}
function recovery_compare_schema($db,$left,$right) {
 return schema_innodb($db,$left) && schema_innodb($db,$right) && recovery_columns($db,$left)===recovery_columns($db,$right) && recovery_indexes($db,$left)===recovery_indexes($db,$right);
}
function recovery_order($name) { return array('doctors'=>'doctor_id','schedules'=>'doctor_id','appointments'=>'id','details'=>'appointment_id','secretaries'=>'user_id,doctor_id','options'=>'option_name')[$name]; }
function recovery_rows($db,$table,$name) {
 $where=$name==='options' ? " WHERE option_name IN ('hj_booking_schema_version','hj_booking_schema_verified')" : '';
 return hj_booking_rows($db,"SELECT * FROM `$table`$where ORDER BY ".recovery_order($name));
}
function recovery_fingerprint($db,$tables) {
 $data=array(); foreach($tables as $name=>$table) { $data[$name]=recovery_rows($db,$table,$name); }
 return hash('sha256',wp_json_encode($data));
}
function recovery_empty($db,$tables) { foreach($tables as $table) { if (hj_booking_row($db,"SELECT 1 AS present FROM `$table` LIMIT 1")) { return false; } } return true; }
function recovery_copy($db,$from,$to) {
 foreach($from as $name=>$table) {
  $where=$name==='options' ? " WHERE option_name IN ('hj_booking_schema_version','hj_booking_schema_verified')" : '';
  hj_booking_query($db,"INSERT INTO `{$to[$name]}` SELECT * FROM `$table`$where");
 }
}
function recovery_owned_drop($db,$sets) { foreach($sets as $tables) { foreach($tables as $table) { if (schema_exists($db,$table)) { $db->query("DROP TABLE `$table`"); } } } }
function recovery_remove_seed($wpdb,$a,$p,$s,$d,$m) {
 foreach($wpdb->get_col("SELECT id FROM `$a` WHERE doctor_id=998501") as $id) { $wpdb->delete($p,array('appointment_id'=>(int)$id)); }
 $wpdb->delete($a,array('doctor_id'=>998501)); $wpdb->delete($s,array('doctor_id'=>998501)); $wpdb->delete($m,array('doctor_id'=>998501)); $wpdb->delete($d,array('doctor_id'=>998501));
}
$source=recovery_tables('source'); $backup=recovery_tables('backup'); $target=recovery_tables('target');
$a=$source['appointments']; $p=$source['details']; $s=$source['schedules']; $d=$source['doctors']; $m=$source['secretaries'];
if ($mode==='setup') {
 schema_check(hj_booking_ready() && !is_file($path.'-manifest'),'recovery starts from verified synthetic v1');
 foreach(array($backup,$target) as $tables) { foreach($tables as $table) { schema_check(!schema_exists($db,$table),'recovery table name unused'); } }
 foreach(array($d,$s,$a,$m) as $table) { schema_check(!hj_booking_row($db,"SELECT 1 AS present FROM `$table` WHERE doctor_id=998501 LIMIT 1"),'recovery fixture doctor ID unused'); }
 $created=array(); $seeded=false;
 try {
  schema_check($wpdb->insert($d,array('doctor_id'=>998501,'duration_minutes'=>7,'buffer_minutes'=>5,'enabled'=>1))===1,'synthetic recovery doctor seeded'); $seeded=true;
  $json=wp_json_encode(array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00:30','end_time'=>'10:00:30')));
  schema_check($wpdb->insert($s,array('doctor_id'=>998501,'revision'=>7,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json)))===1,'synthetic recovery schedule seeded');
  schema_check($wpdb->insert($m,array('user_id'=>998504,'doctor_id'=>998501))===1,'synthetic recovery secretary seeded');
  foreach(array('pending','confirmed','cancelled') as $i=>$status) {
   schema_check($wpdb->insert($a,array('doctor_id'=>998501,'patient_id'=>998502+$i,'request_key'=>str_repeat((string)($i+1),31).'f','request_hash'=>str_repeat((string)($i+1),64),'starts_at'=>'2030-01-07 09:00:30','ends_at'=>'2030-01-07 09:07:30','blocked_until'=>'2030-01-07 09:12:30','schedule_revision'=>7,'status'=>$status))===1,'synthetic recovery appointment seeded');
   schema_check($wpdb->insert($p,array('appointment_id'=>(int)$wpdb->insert_id,'phone'=>'09123456789','national_code'=>'1234567891'))===1,'synthetic recovery detail seeded');
  }
  foreach(array($backup,$target) as $tables) { foreach($tables as $name=>$table) { hj_booking_query($db,"CREATE TABLE `$table` LIKE `{$source[$name]}`"); $created[]=$table; schema_check(recovery_compare_schema($db,$source[$name],$table),'fixture table matches source columns, indexes and InnoDB'); } }
  // Quiescent CI fixture only; these table copies are not a real site backup.
  hj_booking_query($db,'START TRANSACTION'); recovery_copy($db,$source,$backup); hj_booking_query($db,'COMMIT');
  $baseline=recovery_fingerprint($db,$source);
  schema_check(recovery_fingerprint($db,$backup)===$baseline && recovery_empty($db,$target),'synthetic copy preserves rows and empty restore target');
  if (file_put_contents($path.'-manifest',$baseline)!==64) { throw new RuntimeException('manifest write failed'); }
 } catch(Throwable $e) { foreach($created as $table) { $db->query("DROP TABLE `$table`"); } if ($seeded) { recovery_remove_seed($wpdb,$a,$p,$s,$d,$m); } throw $e; }
 exit(0);
}
if (!is_file($path.'-manifest')) { throw new RuntimeException('recovery manifest missing'); }
$expected=trim(file_get_contents($path.'-manifest'));
if (!preg_match('/^[a-f0-9]{64}$/D',$expected)) { throw new RuntimeException('bad manifest'); }
if ($mode==='tamper') { schema_check($wpdb->delete($backup['doctors'],array('doctor_id'=>998501))===1,'simulate incomplete synthetic copy'); exit(0); }
if ($mode==='repair-fixture') {
 hj_booking_query($db,"INSERT INTO `{$backup['doctors']}` SELECT * FROM `$d` WHERE doctor_id=998501");
 schema_check(recovery_fingerprint($db,$backup)===$expected && recovery_empty($db,$target),'fixture restores intentionally removed synthetic row'); exit(0);
}
if ($mode==='bad-schema') { hj_booking_query($db,"ALTER TABLE `{$backup['schedules']}` ADD COLUMN ci_bad int NOT NULL DEFAULT 0"); exit(0); }
if ($mode==='repair-schema') { hj_booking_query($db,"ALTER TABLE `{$backup['schedules']}` DROP COLUMN ci_bad"); schema_check(recovery_fingerprint($db,$backup)===$expected && recovery_empty($db,$target),'fixture repairs synthetic schema for further testing'); exit(0); }
if ($mode==='dirty-target') { hj_booking_query($db,"INSERT INTO `{$target['doctors']}` SELECT * FROM `{$backup['doctors']}` WHERE doctor_id=998501"); exit(0); }
if ($mode==='clean-target') { schema_check($wpdb->delete($target['doctors'],array('doctor_id'=>998501))===1 && recovery_empty($db,$target),'fixture removes its own target sentinel'); exit(0); }
if ($mode==='seed-later') {
 schema_check($wpdb->insert($a,array('doctor_id'=>998501,'patient_id'=>998505,'request_key'=>str_repeat('9',31).'f','request_hash'=>str_repeat('9',64),'starts_at'=>'2030-01-08 09:00:00','ends_at'=>'2030-01-08 09:07:00','blocked_until'=>'2030-01-08 09:12:00','schedule_revision'=>7,'status'=>'pending'))===1,'synthetic appointment committed after backup');
 schema_check($wpdb->insert($p,array('appointment_id'=>(int)$wpdb->insert_id,'phone'=>'09123456789','national_code'=>'1234567891'))===1,'synthetic later detail committed'); exit(0);
}
if ($mode==='worker') {
 $test=getenv('HJ_RECOVERY_TEST'); if (!in_array($test,array('interrupt','commit','reject'),true)) { exit(1); }
 // All checks are experiment-only; checksum is not authenticated and does not
 // certify backup completeness, consistency, provenance or production safety.
 if (recovery_fingerprint($db,$backup)!==$expected || !recovery_empty($db,$target)) { echo 'REJECTED'; exit($test==='reject' ? 0 : 1); }
 foreach($source as $name=>$table) { if (!recovery_compare_schema($db,$table,$backup[$name]) || !recovery_compare_schema($db,$table,$target[$name])) { echo 'REJECTED'; exit($test==='reject' ? 0 : 1); } }
 if ($test==='reject') { echo 'UNEXPECTEDLY_ACCEPTED'; exit(1); }
 hj_booking_query($db,'START TRANSACTION');
 try {
  recovery_copy($db,$backup,$target);
  if ($test==='interrupt') {
   file_put_contents($path.'-ready','all-copy-statements-complete-before-commit');
   $end=microtime(true)+20; while(microtime(true)<$end) { usleep(10000); }
   throw new RuntimeException('worker was not terminated');
  }
  hj_booking_query($db,'COMMIT'); echo 'COMMITTED';
 } finally { $db->query('ROLLBACK'); }
 exit(0);
}
if ($mode==='check-empty') { schema_check(recovery_empty($db,$target) && recovery_fingerprint($db,$backup)===$expected,'no target rows after killed transaction; backup intact'); exit(0); }
if ($mode==='check') {
 schema_check(recovery_fingerprint($db,$target)===$expected && recovery_fingerprint($db,$backup)===$expected,'isolated restored rows match backup including options and legacy details');
 foreach($source as $name=>$table) { schema_check(recovery_compare_schema($db,$table,$target[$name]),'restored fixture preserves columns, indexes and transactional engine'); }
 schema_check(recovery_fingerprint($db,$source)!==$expected,'backup predates later committed appointment and cannot recover it');
 schema_check(hj_booking_ready() && (int)get_option('hj_booking_schema_version')===1,'test leaves source schema version unchanged and does not activate v2'); exit(0);
}
if ($mode==='cleanup') {
 recovery_owned_drop($db,array($backup,$target)); recovery_remove_seed($wpdb,$a,$p,$s,$d,$m); unlink($path.'-manifest'); exit(0);
}
exit(1);

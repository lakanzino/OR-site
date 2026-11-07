<?php
/** CI-only patched plugin copy: NOT a production guard or migration. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
$root=realpath(getenv('RUNNER_TEMP')); $plugin=realpath(getenv('HJ_COORD_PLUGIN'));
if (!$root || !$plugin || strpos($plugin,$root.'/hj-coordination-')!==0 || !is_file($plugin.'/hamyar-doctors.php')) { exit(1); }
require_once $plugin.'/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb;
$admin=get_user_by('login','fixture_admin'); if (!$admin || !hj_booking_enabled()) { exit(1); } wp_set_current_user($admin->ID);
$table=$wpdb->prefix.'ci_coordination_intents'; $meta=getenv('RUNNER_TEMP').'/hj-ci-coordination.json'; $prefix=getenv('RUNNER_TEMP').'/hj-ci-coordination'; $mode=$argv[1] ?? '';
function coord_check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function coord_rule($type,$capacity=1) {
 $row=array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00','end_time'=>'12:00');
 return $type==='v1' ? array($row) : array($row+array('duration_minutes'=>30,'buffer_minutes'=>0,'capacity'=>$capacity));
}
function coord_state($doctor) {
 global $wpdb; $d=hj_booking_table('doctors'); $s=hj_booking_table('schedules');
 return array('config'=>$wpdb->get_row($wpdb->prepare("SELECT duration_minutes,buffer_minutes,enabled,activation_generation FROM `$d` WHERE doctor_id=%d",$doctor),ARRAY_A),'snapshot'=>$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$doctor),ARRAY_A));
}
function coord_expected($state) { $c=$state['config']; return array('duration'=>(int)$c['duration_minutes'],'buffer'=>(int)$c['buffer_minutes'],'enabled'=>(bool)(int)$c['enabled'],'generation'=>(int)$c['activation_generation']); }
/** A tiny preparation sentinel, NOT the durable-intent implementation. */
function ci_coord_prepare($doctor,$revision,$expected) {
 global $table;
 return hj_booking_transaction($doctor,'ci_coord_prepare',function($db,$config) use($doctor,$revision,$expected,$table) {
  if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $s=hj_booking_table('schedules'); $row=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$doctor));
  if (!$row || hj_booking_decode_snapshot($row)===false) { return array('status'=>'invalid_schedule'); }
  if ((int)$row['revision']!==$revision || (int)$config['activation_generation']!==$expected['generation'] || (int)$config['enabled']!==(int)$expected['enabled'] || (int)$config['duration_minutes']!==$expected['duration'] || (int)$config['buffer_minutes']!==$expected['buffer']) { return array('status'=>'stale'); }
  $old=hj_booking_row($db,$db->prepare("SELECT doctor_id FROM `$table` WHERE doctor_id=%d AND active=1",$doctor));
  if ($old) { return array('status'=>'intent_pending'); }
  if (hj_booking_query($db,$db->prepare("INSERT INTO `$table` (doctor_id,active) VALUES (%d,1)",$doctor))!==1) { throw new RuntimeException('sentinel insert failed'); }
  return array('status'=>'prepared');
 });
}
if ($mode==='setup') {
 coord_check(!is_file($meta) && !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) && hj_booking_install_schema(),'clean v1 fixture');
 $d=hj_booking_table('doctors'); $cols=$wpdb->get_col("SHOW COLUMNS FROM `$d`",0); coord_check(!in_array('activation_generation',$cols,true),'test generation column absent');
 $doctors=array(); $added=false; $owned=false;
 try {
  coord_check($wpdb->query("ALTER TABLE `$d` ADD COLUMN activation_generation bigint unsigned NOT NULL DEFAULT 0")!==false,'create test-only generation column'); $added=true;
  update_option('hj_booking_schema_version',2,false); hj_booking_schema_clear_option_cache(); coord_check(hj_booking_capacity_v2_ready() && hj_booking_ready(),'test-only v2 active');
  coord_check($wpdb->query("CREATE TABLE `$table` (doctor_id bigint unsigned NOT NULL,active tinyint unsigned NOT NULL,PRIMARY KEY (doctor_id)) ENGINE=InnoDB")!==false,'create disposable guard table'); $owned=true;
  foreach(array('manual','v1_prepare','v1_writer','v2_prepare','v2_writer') as $name) {
   $doc=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic coordination '.$name,'post_author'=>$admin->ID),true);
   if (is_wp_error($doc) || !$doc) { throw new RuntimeException('synthetic doctor failed'); }
   $doctors[$name]=(int)$doc;
   coord_check(hj_booking_configure((int)$doc,30,5,true)['status']==='configured','seed config '.$name);
   $type=str_starts_with($name,'v1_') ? 'v1' : 'v2';
   $r=$type==='v1' ? hj_booking_save_schedule((int)$doc,coord_rule('v1'),0) : hj_booking_save_capacity_week((int)$doc,coord_rule('v2'),0);
   coord_check($r['status']==='saved','seed snapshot '.$name);
  }
  if (file_put_contents($meta,wp_json_encode($doctors))===false) { throw new RuntimeException('fixture metadata failed'); }
 } catch(Throwable $e) {
  foreach($doctors as $doc) { $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doc)); $wpdb->delete($d,array('doctor_id'=>$doc)); wp_delete_post($doc,true); }
  if ($owned) { $wpdb->query("DROP TABLE `$table`"); }
  update_option('hj_booking_schema_version',1,false); hj_booking_schema_clear_option_cache(); if ($added) { $wpdb->query("ALTER TABLE `$d` DROP COLUMN activation_generation"); } throw $e;
 }
 exit(0);
}
$data=json_decode(@file_get_contents($meta),true); if (!is_array($data) || count($data)!==5) { throw new RuntimeException('fixture metadata missing'); }
if ($mode==='manual') {
 $doc=(int)$data['manual']; $before=coord_state($doc); $expected=coord_expected($before);
 coord_check(ci_coord_prepare($doc,1,$expected)['status']==='prepared','sentinel prepared');
 coord_check(hj_booking_save_schedule($doc,coord_rule('v1'),1)['status']==='intent_pending' && hj_booking_save_capacity_week($doc,coord_rule('v2',2),1,true,$expected)['status']==='intent_pending','legacy and capacity saves blocked');
 coord_check(hj_booking_configure($doc,60,0,true)['status']==='intent_pending' && hj_booking_resume_capacity($doc,1,$expected['generation'])['status']==='intent_pending','configuration and resume blocked');
 coord_check(hj_booking_capacity_calendar($doc)['status']==='ok','public read remains available while prepared');
 coord_check(hj_booking_pause_capacity($doc)['status']==='paused','emergency pause remains available'); $after=coord_state($doc);
 coord_check($before['snapshot']===$after['snapshot'] && (int)$after['config']['enabled']===0 && (int)$after['config']['activation_generation']===$expected['generation']+1,'pause changes generation without replacing snapshot');
 coord_check(hj_booking_resume_capacity($doc,1,(int)$after['config']['activation_generation'])['status']==='intent_pending','resume remains blocked after pause');
 $probe=hj_booking_transaction($doc,'ci_coord_probe',function($db,$config) use($expected) { return array('status'=>((int)$config['activation_generation']===$expected['generation'] && (bool)(int)$config['enabled']===$expected['enabled']) ? 'current' : 'stale'); });
 coord_check($probe['status']==='stale','pause invalidates old generation even though schedule revision is unchanged'); exit(0);
}
if ($mode==='worker') {
 $case=getenv('HJ_COORD_CASE'); $role=getenv('HJ_COORD_ROLE');
 if (!in_array($case,array('v1_prepare','v1_writer','v2_prepare','v2_writer'),true) || !in_array($role,array('holder','waiter'),true)) { exit(1); }
 $doc=(int)$data[$case]; $kind=str_starts_with($case,'v1_') ? 'v1' : 'v2'; $prepare_first=str_ends_with($case,'_prepare');
 $is_prepare=($role==='holder')===$prepare_first; $operation=$is_prepare ? 'ci_coord_prepare' : ($kind==='v1' ? 'schedule' : 'capacity_schedule');
 $state=coord_state($doc); $expected=coord_expected($state);
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$role,$operation) {
  if ($ctx['operation']!==$operation) { return; }
  if ($role==='waiter' && $event==='before_lock') { file_put_contents($prefix.'-before',(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($role==='holder' && $event==='after_lock') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $result=$is_prepare ? ci_coord_prepare($doc,1,$expected) : ($kind==='v1' ? hj_booking_save_schedule($doc,array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'10:00','end_time'=>'12:00')),1) : hj_booking_save_capacity_week($doc,coord_rule('v2',2),1,true,$expected));
 echo $result['status']; exit(in_array($result['status'],array('prepared','intent_pending','saved','stale'),true)?0:1);
}
if ($mode==='waiter') {
 $id=(int)@file_get_contents($prefix.'-before'); $db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $end=microtime(true)+2;
 do { foreach(hj_booking_rows($db,'SHOW FULL PROCESSLIST') as $row) { if ((int)$row['Id']===$id && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`')!==false) { echo 'WAITING'; $db->close(); exit(0); } } usleep(10000); } while(microtime(true)<$end); $db->close(); exit(1);
}
if ($mode==='check') {
 $case=getenv('HJ_COORD_CASE'); if (!in_array($case,array('v1_prepare','v1_writer','v2_prepare','v2_writer'),true)) { exit(1); }
 $doc=(int)$data[$case]; $state=coord_state($doc); $prepared=str_ends_with($case,'_prepare');
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table` WHERE doctor_id=%d AND active=1",$doc));
 coord_check((int)$state['snapshot']['revision']===($prepared?1:2) && $count===($prepared?1:0) && hj_booking_decode_snapshot($state['snapshot'])!==false,'serialized result has expected snapshot and sentinel '.$case); exit(0);
}
if ($mode==='patient') {
 $doc=(int)$data['v1_prepare']; $patient=get_user_by('login','synthetic_race_7'); if (!$patient) { throw new RuntimeException('synthetic patient missing'); }
 $date=(new DateTimeImmutable('now',wp_timezone()))->modify('next monday')->format('Y-m-d'); $slots=hj_booking_public_slots($doc,$date);
 coord_check($slots['status']==='ok' && !empty($slots['slots']),'availability remains readable with pending intent');
 wp_set_current_user($patient->ID); $id=0;
 try {
  $result=hj_booking_create($doc,$slots['slots'][0],str_repeat('a',31).'7','09123456789','1234567891',1);
  coord_check($result['status']==='created','patient can book while schedule intent is pending'); $id=(int)$result['id'];
  coord_check(hj_booking_patient_cancel($doc,$id)['status']==='updated','patient can cancel while intent is pending');
 } finally {
  wp_set_current_user($admin->ID);
  if ($id) { $wpdb->delete(hj_booking_table('details'),array('appointment_id'=>$id)); $wpdb->delete(hj_booking_table('appointments'),array('id'=>$id)); }
 }
 exit(0);
}
if ($mode==='patient-v2') {
 $doc=(int)$data['v2_prepare']; $patient=get_user_by('login','synthetic_race_7'); if (!$patient) { throw new RuntimeException('synthetic patient missing'); }
 $date=(new DateTimeImmutable('now',wp_timezone()))->modify('next monday')->format('Y-m-d'); $slots=hj_booking_public_slots($doc,$date); $state=coord_state($doc); $expected=coord_expected($state);
 coord_check($slots['status']==='ok' && !empty($slots['slots']),'v2 slots available with intent active');
 wp_set_current_user($patient->ID); $id=0; $removed=false;
 try {
  $result=hj_booking_create($doc,$slots['slots'][0],str_repeat('b',31).'7','09123456789','1234567891',1);
  coord_check($result['status']==='created','patient can book v2 while intent is pending'); $id=(int)$result['id']; wp_set_current_user($admin->ID);
  coord_check(hj_booking_save_capacity_week($doc,coord_rule('v2',2),1,true,$expected)['status']==='intent_pending','capacity writer blocked while intent remains');
  // Fixture-only deletion models testing the existing v2 booking check independently;
  // this is NOT a safe release mechanism for a real intent.
  coord_check($wpdb->delete($table,array('doctor_id'=>$doc))===1,'remove only synthetic sentinel to expose real booking check'); $removed=true;
  coord_check(hj_booking_save_capacity_week($doc,coord_rule('v2',2),1,true,$expected)['status']==='has_future_bookings' && coord_state($doc)===$state,'real v2 writer refuses schedule change while booking exists');
 } finally {
  wp_set_current_user($admin->ID);
  if ($id) { $wpdb->delete(hj_booking_table('details'),array('appointment_id'=>$id)); $wpdb->delete(hj_booking_table('appointments'),array('id'=>$id)); }
  if ($removed && $wpdb->insert($table,array('doctor_id'=>$doc,'active'=>1))!==1) { throw new RuntimeException('fixture sentinel restore failed'); }
 }
 $legacy=(int)$data['v1_prepare']; coord_check(hj_booking_configure($legacy,30,5,false)['status']==='intent_pending','counterexample: v1 configure-based stop is blocked');
 exit(0);
}
if ($mode==='missing-table') {
 $v1=(int)$data['v1_writer']; $v2=(int)$data['v2_writer']; $before1=coord_state($v1); $before2=coord_state($v2); $expected=coord_expected($before2);
 coord_check($wpdb->query("DROP TABLE `$table`")!==false,'remove experimental guard table');
 coord_check(hj_booking_save_schedule($v1,coord_rule('v1'),2)['status']==='error' && hj_booking_save_capacity_week($v2,coord_rule('v2'),2,true,$expected)['status']==='error','missing table fails closed for both writers');
 coord_check(coord_state($v1)===$before1 && coord_state($v2)===$before2,'failed guard cannot modify snapshots or config');
 coord_check(hj_booking_pause_capacity($v2)['status']==='paused' && (int)coord_state($v2)['config']['enabled']===0,'emergency pause remains possible when guard table is missing'); exit(0);
}
if ($mode==='cleanup') {
 $wpdb->query("DROP TABLE IF EXISTS `$table`"); foreach($data as $doc) { $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>(int)$doc)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>(int)$doc)); wp_delete_post((int)$doc,true); }
 update_option('hj_booking_schema_version',1,false); hj_booking_schema_clear_option_cache(); $wpdb->query('ALTER TABLE `'.hj_booking_table('doctors').'` DROP COLUMN activation_generation'); unlink($meta); exit(0);
}
exit(1);

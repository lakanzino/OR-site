<?php
/** Exercise the real v2 create path, synthetic data and disposable WP only. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
$admin=get_user_by('login','fixture_admin'); if (!$admin) { throw new RuntimeException('Missing fixture admin'); } wp_set_current_user($admin->ID);
$prefix=getenv('RUNNER_TEMP').'/hj-capacity-v2'; $meta=$prefix.'-meta.json'; $mode=$argv[1] ?? '';
function v2_check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
if ($mode==='setup') {
 update_option('timezone_string','Asia/Tehran'); v2_check(hj_booking_install_schema(),'v1 schema exists before synthetic v2 experiment');
 $doctor=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic actual v2 doctor','post_author'=>$admin->ID),true);
 if (is_wp_error($doctor) || !$doctor) { throw new RuntimeException('doctor fixture failed'); }
 $date=(new DateTimeImmutable('now',wp_timezone()))->modify('+2 days')->format('Y-m-d'); $weekday=strtolower((new DateTimeImmutable($date,new DateTimeZone('UTC')))->format('l'));
 $legacy=array(array('day_of_week'=>$weekday,'is_closed'=>0,'start_time'=>'17:00','end_time'=>'21:00'));
 v2_check(hj_booking_configure((int)$doctor,30,5,true)['status']==='configured' && hj_booking_save_schedule((int)$doctor,$legacy,0)['status']==='saved','legacy snapshot and config created');
 global $wpdb; $table=hj_booking_table('schedules'); $before=$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d",$doctor),ARRAY_A);
 $v1=hj_booking_public_slots((int)$doctor,$date);
 // Deliberate fixture-only option change: NOT a migration and NOT safe on a real site.
 update_option('hj_booking_schema_version',2,false); hj_booking_schema_clear_option_cache();
 $v2=hj_booking_public_slots((int)$doctor,$date); $after=$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d",$doctor),ARRAY_A);
 v2_check(hj_booking_capacity_v2_ready() && hj_booking_ready() && $v1===$v2 && $before===$after,'legacy schedule read in v2 preserves data, old duration and buffer');
 v2_check(hj_booking_capacity_calendar((int)$doctor)['status']==='legacy_schedule','14-day capacity calendar refuses to guess capacity on a legacy snapshot');
 $calc=function($remaining,$states) { return array('day_remaining'=>$remaining,'slots'=>array_map(function($state){return array('state'=>$state);},$states)); };
 v2_check(hj_booking_calendar_day_summary(true,null)===array('remaining'=>0,'state'=>'closed','color'=>'blue') && hj_booking_calendar_day_summary(false,$calc(0,array('past')))['state']==='past' && hj_booking_calendar_day_summary(false,$calc(0,array('past','full')))['state']==='full' && hj_booking_calendar_day_summary(false,$calc(0,array()))['state']==='unavailable' && hj_booking_calendar_day_summary(false,$calc(1,array()))['color']==='yellow' && hj_booking_calendar_day_summary(false,$calc(3,array()))['color']==='yellow' && hj_booking_calendar_day_summary(false,$calc(4,array()))['color']==='green' && hj_booking_calendar_day_summary(false,false)===false,'calendar labels distinguish closed, full and past; colors cover threshold boundaries');
 $rule=array(array('day_of_week'=>$weekday,'is_closed'=>0,'start_time'=>'17:00','end_time'=>'21:00','duration_minutes'=>60,'buffer_minutes'=>0,'capacity'=>3));
 $bad=$rule; $bad[0]['capacity']=0; v2_check(hj_booking_save_capacity_week((int)$doctor,$bad,1)['status']==='invalid','invalid capacity rejected');
 $patient=get_user_by('login','synthetic_race_0'); if (!$patient) { throw new RuntimeException('synthetic user missing'); }
 wp_set_current_user($patient->ID); v2_check(hj_booking_save_capacity_week((int)$doctor,$rule,1)['status']==='forbidden','unrelated patient cannot configure schedule'); wp_set_current_user($admin->ID);
 v2_check(hj_booking_save_capacity_week((int)$doctor,$rule,0)['status']==='revision_conflict','stale capacity revision rejected');
 $saved=hj_booking_save_capacity_week((int)$doctor,$rule,1); v2_check($saved['status']==='saved' && $saved['revision']===2,'explicit v2 weekly schedule saved under mutex');
 $v2snapshot=$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d",$doctor),ARRAY_A);
 v2_check(hj_booking_editor_schedule((int)$doctor)['status']==='invalid_schedule' && hj_booking_save_schedule((int)$doctor,$legacy,2)['status']==='invalid_schedule' && $v2snapshot===$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d",$doctor),ARRAY_A),'old editor cannot discard v2 capacity');
 $slots=hj_booking_public_slots((int)$doctor,$date);
 v2_check($slots['status']==='ok' && $slots['revision']===2 && count($slots['slots'])===4 && !isset($slots['intervals']),'public slot contract remains simple and exposes no internal data');
 $calendar=hj_booking_capacity_calendar((int)$doctor); $today=(new DateTimeImmutable('now',wp_timezone()))->setTime(0,0);
 v2_check($calendar['status']==='ok' && $calendar['revision']===2 && count($calendar['days'])===14 && $calendar['days'][0]['date']===$today->format('Y-m-d') && $calendar['days'][13]['date']===$today->modify('+13 days')->format('Y-m-d'),'calendar has exactly 14 consecutive local dates and current revision');
 v2_check($calendar['days'][2]===array('date'=>$date,'remaining'=>12,'state'=>'available','color'=>'green') && $calendar['days'][9]['remaining']===12 && $calendar['days'][1]['state']==='closed','capacity three across four one-hour slots gives twelve places on scheduled days');
 foreach ($calendar['days'] as $day) { v2_check(array_keys($day)===array('date','remaining','state','color'),'calendar returns only public aggregate fields'); }

 file_put_contents($meta,wp_json_encode(array('doctor'=>(int)$doctor,'date'=>$date,'start'=>$slots['slots'][0],'rule'=>$rule,'revision'=>2)));
 exit(0);
}
if ($mode==='offcheck') { v2_check(!hj_booking_capacity_v2_ready() && !hj_booking_ready(),'v2 is unavailable without CI-only explicit gate'); exit(0); }
$data=json_decode(file_get_contents($meta),true); if (!is_array($data)) { throw new RuntimeException('missing test metadata'); } $id=(int)$data['doctor'];
if ($mode==='patient') {
 global $wpdb; $appointments=hj_booking_table('appointments'); $details=hj_booking_table('details'); $extra=hj_booking_patient_details_v2_table();
 $patient=get_user_by('login','synthetic_race_0'); $other=get_user_by('login','synthetic_race_1'); wp_set_current_user($patient->ID);
 $profile=array('first_name'=>'نام <b>آزمایشی</b>','last_name'=>'بیمار تست','service'=>'visit','note'=>'توضیح آزمایشی');
 $key=str_repeat('a',31).'f'; $start=$data['start']; $revision=(int)$data['revision']; $id2=0; $owned=false; $keep=false;
 $create=function($national,$p,$k=null,$rev=null) use($id,$start,$key,$revision) { return hj_booking_create($id,$start,$k ?? $key,'09123456789',$national,$rev ?? $revision,$p); };
 try {
  v2_check(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($extra))),'experimental patient table absent before fixture setup');
  v2_check(hj_booking_create($id,$start,$key,'09123456789','',$revision)['status']==='invalid','v1 create still requires national code');
  v2_check($create('',$profile)['status']==='unavailable','missing experimental table fails closed');
  v2_check($wpdb->query("CREATE TABLE `$extra` (appointment_id bigint unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB")!==false,'synthetic incomplete table created'); $owned=true;
  $before_details=(int)$wpdb->get_var("SELECT COUNT(*) FROM `$details`");
  v2_check($create('',$profile)['status']==='error' && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$details`")===$before_details && (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$appointments` WHERE request_key=%s",$key))===0 && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$extra`")===0,'details insert failure rolls back appointment and existing detail atomically');
  $wpdb->query("DROP TABLE `$extra`"); $owned=false;
  v2_check($wpdb->query("CREATE TABLE `$extra` (appointment_id bigint unsigned NOT NULL PRIMARY KEY,first_name varchar(80) NOT NULL,last_name varchar(80) NOT NULL,service varchar(20) NOT NULL,note varchar(500) NOT NULL,tracking_code char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,UNIQUE KEY tracking_code (tracking_code)) ENGINE=InnoDB ".$wpdb->get_charset_collate())!==false,'synthetic extra fields table installed for test only'); $owned=true;
  $invalid=$profile; $invalid['first_name']=''; v2_check($create('',$invalid)['status']==='invalid','empty independent patient name rejected');
  $invalid=$profile; $invalid['last_name']=str_repeat('x',81); v2_check($create('',$invalid)['status']==='invalid','overlong patient surname rejected');
  $invalid=$profile; $invalid['note']=str_repeat('x',501); v2_check($create('',$invalid)['status']==='invalid','overlong description rejected');
  $invalid=$profile; $invalid['service']='unknown'; v2_check($create('',$invalid)['status']==='invalid','unrecognized service rejected');
  v2_check($create('1234567890',$profile)['status']==='invalid' && $create('',$profile,null,1)['status']==='revision_conflict','invalid optional national code and stale schedule revision rejected');
  $created=$create('',$profile); $id2=(int)($created['id'] ?? 0);
  v2_check($created['status']==='created' && $id2>0,'independent patient details accepted without national code');
  $row=$wpdb->get_row($wpdb->prepare("SELECT x.*,d.phone,d.national_code FROM `$extra` x INNER JOIN `$details` d ON d.appointment_id=x.appointment_id WHERE x.appointment_id=%d",$id2),ARRAY_A);
  v2_check($row['first_name']==='نام آزمایشی' && $row['last_name']==='بیمار تست' && $row['service']==='visit' && $row['note']==='توضیح آزمایشی' && $row['national_code']==='' && $row['phone']==='09123456789','normalized details persisted separately from account with optional national code empty');
  $again=$create('',$profile); $changed=$profile; $changed['note']='متن دیگر';
  v2_check($again['status']==='replayed' && (int)$again['id']===$id2 && preg_match('/^[A-F0-9]{32}$/D',$created['tracking_code']) && $row['tracking_code']===$created['tracking_code'] && $again['tracking_code']===$created['tracking_code'] && $create('',$changed)['status']==='idempotency_conflict' && $create('1234567891',$profile)['status']==='idempotency_conflict','replay retains identity and changed private details or national code conflict');
  wp_set_current_user($other->ID); v2_check($create('',$profile)['status']==='idempotency_conflict','different account cannot replay another patient request');
  wp_set_current_user($patient->ID); $list=hj_booking_management_list('patient');
  v2_check(is_array($list) && strpos(wp_json_encode($list,JSON_UNESCAPED_UNICODE),'بیمار تست')===false && strpos(wp_json_encode(hj_booking_capacity_calendar($id),JSON_UNESCAPED_UNICODE),'بیمار تست')===false,'patient list query and public calendar do not expose experimental private fields');
  $keep=true; // Keep the synthetic InnoDB table for concurrent workers; cleanup owns it.
 } finally {
  if ($id2) { $wpdb->delete($details,array('appointment_id'=>$id2)); $wpdb->delete($appointments,array('id'=>$id2)); }
  if ($owned && !$keep) { $wpdb->query("DROP TABLE `$extra`"); }
 }
 exit(0);
}
if ($mode==='waiter') {
 global $wpdb; $until=microtime(true)+1.5;
 do { $ids=array(); foreach(glob($prefix.'-before-*') as $path) { $ids[]=(int)file_get_contents($path); }
  foreach($wpdb->get_results('SHOW FULL PROCESSLIST',ARRAY_A) as $row) { if(in_array((int)$row['Id'],$ids,true) && stripos((string)($row['Info'] ?? ''),'INSERT INTO `'.hj_booking_table('doctors').'`')!==false) { echo 'WAITING'; exit(0); } }
  usleep(10000);
 } while(microtime(true)<$until); exit(1);
}
if ($mode==='worker' || $mode==='retry' || $mode==='replay') {
 $worker=getenv('HJ_V2_WORKER'); if (!is_string($worker) || !preg_match('/^(?:[0-9]|1[0-2])$/D',$worker)) { exit(1); }
 $patient=get_user_by('login','synthetic_race_'.((int)$worker % 12)); if(!$patient) { throw new RuntimeException('synthetic patient missing'); } wp_set_current_user($patient->ID);
 if ($mode==='worker') {
  file_put_contents($prefix.'-ready-'.$worker,'ready'); $until=microtime(true)+25; $start=$prefix.'-start-'.($worker==='0'?'holder':'others');
  while(!is_file($start) && microtime(true)<$until) { usleep(10000); clearstatcache(); }
  if(!is_file($start)) { throw new RuntimeException('start timeout'); }
 }
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$worker,$mode) {
  if($mode!=='worker' || $ctx['operation']!=='create') { return; }
  if($event==='before_lock' && $worker!=='0') { file_put_contents($prefix.'-before-'.$worker,(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if($event==='after_lock' && $worker==='0') {
   file_put_contents($prefix.'-locked','locked'); $until=microtime(true)+10;
   while(!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); }
   if(!is_file($prefix.'-release')) { throw new RuntimeException('lock hold timed out'); }
  }
 },10,3);
 $key=substr(hash('sha256','v2-real-create-fixture-'.$worker),0,32);
 $profile=array('first_name'=>'نمونه','last_name'=>'بیمار','service'=>'visit','note'=>'');
 $result=hj_booking_create($id,$data['start'],$key,'09123456789','',(int)$data['revision'],$profile);
 echo $result['status']; exit(0);
}
if ($mode==='cancel') {
 global $wpdb; $table=hj_booking_table('appointments'); $first=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE doctor_id=%d AND status='pending' ORDER BY id LIMIT 1",$id));
 v2_check($first>0 && hj_booking_change_status($id,$first,'cancelled')['status']==='updated','actual doctor cancels a v2 booking'); exit(0);
}
if ($mode==='verify') {
 global $wpdb; $table=hj_booking_table('appointments'); $details=hj_booking_table('details'); $extra=hj_booking_patient_details_v2_table(); $s=hj_booking_table('schedules');
 $rows=$wpdb->get_results($wpdb->prepare("SELECT a.id,a.status,a.starts_at,a.ends_at,a.blocked_until,a.schedule_revision,d.appointment_id AS detail,x.appointment_id AS extra,x.tracking_code AS tracking FROM `$table` a LEFT JOIN `$details` d ON d.appointment_id=a.id LEFT JOIN `$extra` x ON x.appointment_id=a.id WHERE a.doctor_id=%d ORDER BY a.id",$id),ARRAY_A);
 $active=array_values(array_filter($rows,function($r){return $r['status']==='pending';})); $cancelled=array_values(array_filter($rows,function($r){return $r['status']==='cancelled';}));
 v2_check(count($active)===3 && count($cancelled)===1,'real create and cancellation leave three active and one cancelled');
 $trackings=array_column($rows,'tracking'); v2_check(count(array_unique($trackings))===4 && count(array_filter($trackings,function($t){return is_string($t) && preg_match('/^[A-F0-9]{32}$/D',$t);}))===4,'concurrent successful bookings each persist a unique random tracking code');
 foreach($rows as $row) { v2_check((int)$row['detail']===(int)$row['id'] && (int)$row['extra']===(int)$row['id'] && (int)$row['schedule_revision']===2 && strtotime($row['ends_at'].' UTC')-strtotime($row['starts_at'].' UTC')===3600 && $row['ends_at']===$row['blocked_until'],'each booking keeps atomic details and v2 duration without old buffer'); }
 $snapshot=$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$id),ARRAY_A);
 $rule=$data['rule']; $rule[0]['capacity']=1;
 v2_check(hj_booking_save_capacity_week($id,$rule,2)['status']==='has_future_bookings' && $snapshot===$wpdb->get_row($wpdb->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d",$id),ARRAY_A),'capacity reduction blocked without changing existing appointments or schedule');
 $slots=hj_booking_public_slots($id,$data['date']); v2_check(!in_array($data['start'],$slots['slots'],true),'full v2 interval is no longer publicly selectable');
 $patient=get_user_by('login','synthetic_race_1'); wp_set_current_user($patient->ID);
 v2_check(hj_booking_create($id,$data['start'],str_repeat('e',32),'09123456789','1234567891',1)['status']==='revision_conflict','v2 create rejects a stale schedule revision');
 $calendar=hj_booking_capacity_calendar($id);
 $day=array_values(array_filter($calendar['days'],function($day) use($data){return $day['date']===$data['date'];}));
 v2_check(count($day)===1 && $day[0]['remaining']===9 && $day[0]['color']==='green','calendar accounts for three concurrent active bookings while cancelled booking releases capacity');
 wp_set_current_user($admin->ID); v2_check(hj_booking_pause_capacity($id)['status']==='paused' && hj_booking_capacity_calendar($id)['status']==='unavailable','paused doctor cannot expose calendar as available');
 exit(0);
}
if ($mode==='cleanup') {
 global $wpdb; $table=hj_booking_table('appointments'); $details=hj_booking_table('details'); $extra=hj_booking_patient_details_v2_table();
 $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$table` WHERE doctor_id=%d",$id)); foreach($ids as $appt) { if ($extra && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($extra)))) { $wpdb->delete($extra,array('appointment_id'=>(int)$appt)); } $wpdb->delete($details,array('appointment_id'=>(int)$appt)); }
 if ($extra && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($extra)))) { $wpdb->query("DROP TABLE `$extra`"); }
 $wpdb->delete($table,array('doctor_id'=>$id)); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$id)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$id)); wp_delete_post($id,true);
 update_option('hj_booking_schema_version',1,false); hj_booking_schema_clear_option_cache(); unlink($meta); exit(0);
}
exit(1);

<?php
/** CI-only experiment: durable pre-intent and recovery. NOT an endpoint or plugin. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
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
/** Do not interpret malformed state, activity or revision as a valid outcome. */
function intent_record_valid($row) {
 if (!is_array($row) || !array_key_exists('state',$row) || !array_key_exists('active',$row) || !array_key_exists('result_revision',$row)) { return false; }
 $raw=(string)$row['result_revision'];
 if (!preg_match('/^(0|[1-9][0-9]*)$/D',$raw) || strlen($raw)>strlen((string)PHP_INT_MAX) || (string)(int)$raw!==$raw) { return false; }
 if ($row['state']==='prepared') { return (string)$row['active']==='1' && (int)$raw===0; }
 if ($row['state']==='cancelled') { return $row['active']===null && (int)$raw===0; }
 if ($row['state']==='completed') { return (string)$row['active']==='1' && (int)$raw>0; }
 if ($row['state']==='acknowledged') { return $row['active']===null && (int)$raw>0; }
 return false;
}
/** One active intent per doctor; completed history retained forever in this experiment. */
function ci_intent_prepare($doctor,$key,$revision,$rows) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key) || !is_int($revision) || $revision<1 || ($rows=hj_booking_normalize_schedule($rows))===false) { return array('status'=>'invalid'); }
 $actor=get_current_user_id(); $hash=intent_hash($doctor,$actor,$revision,$rows);
 return hj_booking_transaction($doctor,'ci_intent_prepare',function($db) use($doctor,$key,$actor,$hash,$revision,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $old=hj_booking_row($db,$db->prepare("SELECT actor_id,payload_hash,state,active,result_revision FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if ($old) {
   if ((int)$old['actor_id']!==$actor || !hash_equals($old['payload_hash'],$hash)) { return array('status'=>'blocked'); }
   if (!intent_record_valid($old)) { return array('status'=>'unknown'); }
   return array('status'=>$old['state'],'revision'=>(int)$old['result_revision']);
  }
  // A malformed row must not look like the absence of an active operation.
  // CI-only scan; production needs validated schema, bounds and operational indexing.
  $bad=hj_booking_row($db,$db->prepare("SELECT request_key FROM `$table` WHERE doctor_id=%d AND COALESCE((state='prepared' AND active=1 AND result_revision=0) OR (state='cancelled' AND active IS NULL AND result_revision=0) OR (state='completed' AND active=1 AND result_revision BETWEEN 1 AND %d) OR (state='acknowledged' AND active IS NULL AND result_revision BETWEEN 1 AND %d),0)=0 LIMIT 1",$doctor,PHP_INT_MAX,PHP_INT_MAX));
  if ($bad) { return array('status'=>'unknown'); }
  $active=hj_booking_row($db,$db->prepare("SELECT request_key FROM `$table` WHERE doctor_id=%d AND active=1",$doctor));
  if ($active) { return array('status'=>'blocked'); }
  $s=hj_booking_table('schedules'); $current=hj_booking_row($db,$db->prepare("SELECT revision,rows_json,rows_hash FROM `$s` WHERE doctor_id=%d FOR UPDATE",$doctor));
  if (!$current || hj_booking_legacy_snapshot_rows($current)===false) { return array('status'=>'invalid_schedule'); }
  if ((int)$current['revision']!==$revision) { return array('status'=>'revision_conflict'); }
  if (hj_booking_query($db,$db->prepare("INSERT INTO `$table` (doctor_id,request_key,actor_id,payload_hash,state,active,result_revision,created_utc) VALUES (%d,%s,%d,%s,'prepared',1,0,UTC_TIMESTAMP())",$doctor,$key,$actor,$hash))!==1) { throw new RuntimeException('intent insert failed'); }
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
  if (!intent_record_valid($intent)) { return array('status'=>'unknown'); }
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
  $row=hj_booking_row($db,$db->prepare("SELECT request_key,state,active,result_revision FROM `$table` WHERE doctor_id=%d AND actor_id=%d AND active=1",$doctor,$actor));
  if (!$row || !intent_record_valid($row) || !in_array($row['state'],array('prepared','completed'),true) || !intent_valid_key($row['request_key'])) { return array('status'=>'unknown'); }
  return array('status'=>$row['state'],'key'=>$row['request_key'],'revision'=>(int)$row['result_revision']);
 });
}
/** Actor-scoped cancellation: retain tombstone so a delayed apply cannot run. */
function ci_intent_cancel($doctor,$key) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key)) { return array('status'=>'invalid'); }
 $actor=get_current_user_id();
 return hj_booking_transaction($doctor,'ci_intent_cancel',function($db) use($doctor,$key,$actor,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $row=hj_booking_row($db,$db->prepare("SELECT actor_id,state,active,result_revision FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if (!$row || (int)$row['actor_id']!==$actor) { return array('status'=>'unknown'); }
  if (!intent_record_valid($row)) { return array('status'=>'unknown'); }
  if ($row['state']==='cancelled' && $row['active']===null && (int)$row['result_revision']===0) { return array('status'=>'cancelled'); }
  if ((($row['state']==='completed' && (string)$row['active']==='1') || ($row['state']==='acknowledged' && $row['active']===null)) && (int)$row['result_revision']>0) { return array('status'=>'already_completed','revision'=>(int)$row['result_revision']); }
  if ($row['state']!=='prepared' || (string)$row['active']!=='1' || (int)$row['result_revision']!==0) { return array('status'=>'unknown'); }
  if (hj_booking_query($db,$db->prepare("UPDATE `$table` SET state='cancelled',active=NULL WHERE doctor_id=%d AND request_key=%s AND actor_id=%d AND state='prepared' AND active=1 AND result_revision=0",$doctor,$key,$actor))!==1) { throw new RuntimeException('intent cancel failed'); }
  return array('status'=>'cancelled');
 });
}
/** Lookup an already-known key; no keyless history or real endpoint exists. */
function ci_intent_find($doctor,$key) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key)) { return array('status'=>'invalid'); }
 $actor=get_current_user_id();
 return hj_booking_transaction($doctor,'ci_intent_find',function($db) use($doctor,$key,$actor,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $row=hj_booking_row($db,$db->prepare("SELECT actor_id,state,active,result_revision FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if (!$row || (int)$row['actor_id']!==$actor) { return array('status'=>'unknown'); }
  if (!intent_record_valid($row)) { return array('status'=>'unknown'); }
  if ($row['state']==='prepared' && (string)$row['active']==='1' && (int)$row['result_revision']===0) { return array('status'=>'prepared'); }
  if ($row['state']==='cancelled' && $row['active']===null && (int)$row['result_revision']===0) { return array('status'=>'cancelled'); }
  if ((($row['state']==='completed' && (string)$row['active']==='1') || ($row['state']==='acknowledged' && $row['active']===null)) && (int)$row['result_revision']>0) { return array('status'=>$row['state'],'revision'=>(int)$row['result_revision']); }
  return array('status'=>'unknown');
 });
}
/** Actor-scoped, bounded listing prototype; cursor is signed, not encrypted. */
function intent_history_number($value) {
 if (!is_string($value) && !is_int($value)) { return false; }
 $s=(string)$value;
 if (!preg_match('/^[1-9][0-9]*$/D',$s) || strlen($s)>strlen((string)PHP_INT_MAX) || (string)(int)$s!==$s) { return false; }
 return (int)$s;
}
function intent_history_mac($doctor,$actor,$upper,$before) {
 return hash_hmac('sha256','intent-history-v1|'.$doctor.'|'.$actor.'|'.$upper.'|'.$before,wp_salt('auth'));
}
function ci_intent_history($doctor,$cursor=null) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 $actor=get_current_user_id(); $upper=null; $before=null;
 if ($cursor!==null) {
  if (!is_string($cursor) || strlen($cursor)>128 || !preg_match('/^([1-9][0-9]*)\.([1-9][0-9]*)\.([a-f0-9]{64})$/D',$cursor,$m)) { return array('status'=>'invalid'); }
  $upper=intent_history_number($m[1]); $before=intent_history_number($m[2]);
  if ($upper===false || $before===false || $before>$upper || !hash_equals(intent_history_mac($doctor,$actor,$upper,$before),$m[3])) { return array('status'=>'invalid'); }
 }
 return hj_booking_transaction($doctor,'ci_intent_history',function($db) use($doctor,$actor,$upper,$before,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  if ($upper===null) {
   $max=hj_booking_row($db,$db->prepare("SELECT MAX(intent_seq) AS n FROM `$table` WHERE doctor_id=%d AND actor_id=%d",$doctor,$actor));
   if (!$max || $max['n']===null || ($upper=intent_history_number($max['n']))===false) { return array('status'=>'unknown'); }
  }
  $where="doctor_id=%d AND actor_id=%d AND intent_seq<=%d"; $args=array($doctor,$actor,$upper);
  if ($before!==null) { $where.=" AND intent_seq<%d"; $args[]=$before; }
  // Fetch one extra to see whether another page exists; never expose the extra row.
  $rows=hj_booking_rows($db,$db->prepare("SELECT intent_seq,request_key,state,active,result_revision,created_utc FROM `$table` WHERE $where ORDER BY intent_seq DESC LIMIT 4",$args));
  if (!$rows) { return array('status'=>'unknown'); }
  foreach ($rows as $r) {
   if (!intent_record_valid($r) || !intent_valid_key($r['request_key']) || intent_history_number($r['intent_seq'])===false || !is_string($r['created_utc']) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$r['created_utc'])) { return array('status'=>'unknown'); }
  }
  $more=count($rows)>3; $rows=array_slice($rows,0,3); $items=array();
  foreach($rows as $r) { $items[]=array('key'=>$r['request_key'],'status'=>$r['state'],'revision'=>(int)$r['result_revision'],'created_utc'=>$r['created_utc']); }
  $last=intent_history_number($rows[count($rows)-1]['intent_seq']);
  $next=$more ? $upper.'.'.$last.'.'.intent_history_mac($doctor,$actor,$upper,$last) : null;
  return array('status'=>'ok','items'=>$items,'next'=>$next);
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
  if (!intent_record_valid($row)) { return array('status'=>'unknown'); }
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
  intent_check($wpdb->query("CREATE TABLE `$table` (intent_seq bigint unsigned NOT NULL AUTO_INCREMENT,doctor_id bigint unsigned NOT NULL,request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_id bigint unsigned NOT NULL,payload_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,state varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,active tinyint unsigned NULL,result_revision bigint unsigned NOT NULL,created_utc datetime NOT NULL,PRIMARY KEY (doctor_id,request_key),UNIQUE KEY intent_sequence (intent_seq),UNIQUE KEY one_active (doctor_id,active),KEY actor_history (doctor_id,actor_id,intent_seq)) ENGINE=InnoDB")!==false,'create CI-only intent table'); $owned=true;
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
 try { intent_check(ci_intent_recover((int)$other)['status']==='unknown' && ci_intent_find((int)$other,intent_key(1))['status']==='unknown' && ci_intent_cancel((int)$other,intent_key(1))['status']==='unknown' && ci_intent_apply((int)$other,intent_key(1),1,intent_rows('10:00'))['status']==='blocked','authorized actor cannot inspect, cancel or apply another doctor intent in wrong scope'); }
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
 exit(0);
}
if ($mode==='race-apply') {
 $id=getenv('HJ_INTENT_WORKER'); if (!is_string($id) || !preg_match('/^[0-7]$/D',$id)) { exit(1); }
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$id) {
  if ($ctx['operation']!=='ci_intent_apply') { return; }
  if ($event==='before_lock' && $id!=='0') { file_put_contents($prefix.'-before-'.$id,(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($event==='after_lock' && $id==='0') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $result=ci_intent_apply($doctor,intent_key(3),4,intent_rows('12:00'));
 echo $result['status']; exit(in_array($result['status'],array('saved','replayed'),true)?0:1);
}
if ($mode==='check-apply-race') {
 intent_check(ci_intent_recover($doctor)===array('status'=>'completed','key'=>intent_key(3),'revision'=>5) && (int)intent_snapshot($doctor)['revision']===5,'concurrent retry has one completed revision and recoverable result');
 intent_check(ci_intent_apply($doctor,intent_key(3),4,intent_rows('12:00'))===array('status'=>'replayed','revision'=>5),'old execution remains replayable'); exit(0);
}
if ($mode==='cancel-seed') {
 intent_check(ci_intent_ack($doctor,intent_key(3),5)['status']==='acknowledged' && ci_intent_prepare($doctor,intent_key(4),5,intent_rows('13:00'))['status']==='prepared','prepare cancel race with current revision');
 wp_set_current_user($owner->ID); intent_check(hj_booking_can_configure($doctor) && ci_intent_cancel($doctor,intent_key(4))['status']==='unknown' && ci_intent_find($doctor,intent_key(4))['status']==='unknown','other authorized actor cannot cancel or inspect intent');
 wp_set_current_user($outsider->ID); intent_check(ci_intent_cancel($doctor,intent_key(4))['status']==='forbidden' && ci_intent_find($doctor,intent_key(4))['status']==='forbidden','unauthorized actor cannot cancel or inspect'); wp_set_current_user($admin->ID);
 intent_check(ci_intent_cancel($doctor,intent_key(999))['status']==='unknown' && ci_intent_find($doctor,intent_key(999))['status']==='unknown','absent key does not imply safe cancellation');
 intent_check($wpdb->update($table,array('active'=>2),array('doctor_id'=>$doctor,'request_key'=>intent_key(4)))===1,'inject inconsistent active marker');
 intent_check(ci_intent_cancel($doctor,intent_key(4))['status']==='unknown' && ci_intent_find($doctor,intent_key(4))['status']==='unknown' && ci_intent_prepare($doctor,intent_key(4),5,intent_rows('13:00'))['status']==='unknown' && ci_intent_prepare($doctor,intent_key(8),5,intent_rows('16:00'))['status']==='unknown' && ci_intent_apply($doctor,intent_key(4),5,intent_rows('13:00'))['status']==='unknown' && (int)intent_snapshot($doctor)['revision']===5,'inconsistent record cannot cancel, replay or apply');
 intent_check($wpdb->update($table,array('active'=>1),array('doctor_id'=>$doctor,'request_key'=>intent_key(4)))===1,'restore only synthetic marker');
 $trigger=$wpdb->prefix.'ci_intent_cancel_fail'; intent_check($wpdb->query("CREATE TRIGGER `$trigger` BEFORE UPDATE ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'")!==false,'inject cancel write failure');
 try { intent_check(ci_intent_cancel($doctor,intent_key(4))['status']==='error' && ci_intent_find($doctor,intent_key(4))['status']==='prepared' && (int)intent_snapshot($doctor)['revision']===5,'failed cancel retains prepared intent and snapshot'); }
 finally { $wpdb->query("DROP TRIGGER `$trigger`"); }
 $killed=false;
 $hook=function($event,$db,$ctx) use(&$killed) { if (!$killed && $ctx['operation']==='ci_intent_cancel' && $event==='before_commit') { $killed=true; $id=(int)$db->get_var('SELECT CONNECTION_ID()'); $killer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $killer->suppress_errors(true); if (!$id || $killer->query('KILL CONNECTION '.$id)===false) { throw new RuntimeException('fixture kill failed'); } $killer->close(); } };
 add_action('hj_booking_test_event',$hook,10,3); $lost=ci_intent_cancel($doctor,intent_key(4)); remove_action('hj_booking_test_event',$hook,10);
 intent_check($killed && $lost['status']==='unknown' && ci_intent_find($doctor,intent_key(4))['status']==='prepared' && (int)intent_snapshot($doctor)['revision']===5,'cancel connection kill before commit leaves active prepared intent'); exit(0);
}
if ($mode==='cancel-worker') {
 $case=getenv('HJ_CANCEL_CASE'); $role=getenv('HJ_CANCEL_ROLE');
 if (!in_array($case,array('cancel_first','apply_first'),true) || !in_array($role,array('holder','waiter'),true)) { exit(1); }
 $cancel=($role==='holder')===($case==='cancel_first'); $operation=$cancel ? 'ci_intent_cancel' : 'ci_intent_apply';
 $key=$case==='cancel_first' ? intent_key(4) : intent_key(6); $rows=intent_rows($case==='cancel_first' ? '13:00' : '14:00');
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix,$role,$operation) {
  if ($ctx['operation']!==$operation) { return; }
  if ($role==='waiter' && $event==='before_lock') { file_put_contents($prefix.'-before-1',(string)$db->get_var('SELECT CONNECTION_ID()')); }
  if ($role==='holder' && $event==='after_lock') { file_put_contents($prefix.'-locked','1'); $until=microtime(true)+15; while (!is_file($prefix.'-release') && microtime(true)<$until) { usleep(10000); clearstatcache(); } if (!is_file($prefix.'-release')) { throw new RuntimeException('barrier timeout'); } }
 },10,3);
 $result=$cancel ? ci_intent_cancel($doctor,$key) : ci_intent_apply($doctor,$key,5,$rows);
 echo $result['status']; exit(in_array($result['status'],array('cancelled','blocked','saved','already_completed'),true)?0:1);
}
if ($mode==='check-cancel-first') {
 intent_check(ci_intent_find($doctor,intent_key(4))['status']==='cancelled' && (int)intent_snapshot($doctor)['revision']===5,'cancel-first leaves durable tombstone and no save');
 intent_check(ci_intent_prepare($doctor,intent_key(4),5,intent_rows('13:00'))['status']==='cancelled' && ci_intent_apply($doctor,intent_key(4),5,intent_rows('13:00'))['status']==='blocked' && ci_intent_cancel($doctor,intent_key(4))['status']==='cancelled','old prepare, apply and cancel cannot revive cancelled key');
 intent_check(ci_intent_prepare($doctor,intent_key(5),5,intent_rows('13:30'))['status']==='prepared' && ci_intent_cancel($doctor,intent_key(4))['status']==='cancelled' && ci_intent_recover($doctor)['key']===intent_key(5),'replayed old cancellation does not clear a newer intent');
 intent_check(ci_intent_cancel($doctor,intent_key(5))['status']==='cancelled' && ci_intent_prepare($doctor,intent_key(6),5,intent_rows('14:00'))['status']==='prepared','prepare apply-first race'); exit(0);
}
if ($mode==='check-apply-first') {
 intent_check(ci_intent_find($doctor,intent_key(6))===array('status'=>'completed','revision'=>6) && ci_intent_cancel($doctor,intent_key(6))===array('status'=>'already_completed','revision'=>6) && (int)intent_snapshot($doctor)['revision']===6,'committed save is never reported as cancelled');
 intent_check(ci_intent_prepare($doctor,intent_key(7),6,intent_rows('15:00'))['status']==='blocked' && ci_intent_ack($doctor,intent_key(6),5)['status']==='refresh_required' && ci_intent_ack($doctor,intent_key(6),6)['status']==='acknowledged','completed intent remains active until fresh acknowledgement');
 intent_check(ci_intent_cancel($doctor,intent_key(6))===array('status'=>'already_completed','revision'=>6) && ci_intent_prepare($doctor,intent_key(7),6,intent_rows('15:00'))['status']==='prepared','historical result retained and new intent prepared'); exit(0);
}
if ($mode==='cancel-lost') {
 add_action('hj_booking_test_event',function($event,$db,$ctx) use($prefix) { if ($ctx['operation']==='ci_intent_cancel' && $event==='after_commit' && ($ctx['result']['status'] ?? '')==='cancelled') { file_put_contents($prefix.'-cancelled','1'); $end=microtime(true)+20; while(microtime(true)<$end) { usleep(10000); } throw new RuntimeException('not interrupted'); } },10,3);
 ci_intent_cancel($doctor,intent_key(7)); exit(1);
}
if ($mode==='check-cancel-lost') {
 intent_check(ci_intent_recover($doctor)['status']==='unknown','keyless active lookup after cancellation remains ambiguous');
 intent_check(ci_intent_find($doctor,intent_key(7))['status']==='cancelled' && ci_intent_cancel($doctor,intent_key(7))['status']==='cancelled' && ci_intent_apply($doctor,intent_key(7),6,intent_rows('15:00'))['status']==='blocked' && (int)intent_snapshot($doctor)['revision']===6,'known key resolves lost cancellation without late apply'); exit(0);
}
if ($mode==='history') {
 // The most recent cancelled key was recovered with no local browser key.
 intent_check(ci_intent_recover($doctor)['status']==='unknown','active-only lookup remains ambiguous after lost cancel response');
 wp_set_current_user($owner->ID); intent_check(hj_booking_can_configure($doctor) && ci_intent_history($doctor)['status']==='unknown','different authorized actor has no history before own operations');
 wp_set_current_user($admin->ID);
 intent_check(ci_intent_prepare($doctor,intent_key(8),6,intent_rows('16:00'))['status']==='prepared','seed active synthetic operation for history');
 $first=ci_intent_history($doctor); $keys=array_column($first['items'] ?? array(),'key');
 intent_check(($first['status'] ?? '')==='ok' && $keys===array(intent_key(8),intent_key(7),intent_key(6)) && $first['items'][0]['status']==='prepared' && $first['items'][1]['status']==='cancelled' && is_string($first['next'] ?? null),'keyless history includes lost cancelled operation with bounded page');
 foreach($first['items'] as $item) { intent_check(array_keys($item)===array('key','status','revision','created_utc'),'history returns no payload, actor id or fingerprint'); }
 intent_check(ci_intent_find($doctor,$keys[1])['status']==='cancelled','selected known key can be rechecked');
 $cursor=$first['next']; $tampered=$cursor; $tampered[strlen($tampered)-1]=$tampered[strlen($tampered)-1]==='0' ? '1' : '0';
 intent_check(ci_intent_history($doctor,$tampered)['status']==='invalid' && ci_intent_history($doctor,'1.2.'.str_repeat('a',64))['status']==='invalid' && ci_intent_history($doctor,str_repeat('x',129))['status']==='invalid','modified, impossible and oversized cursors rejected');
 wp_set_current_user($owner->ID); intent_check(ci_intent_history($doctor,$cursor)['status']==='invalid','cursor cannot cross actors');
 wp_set_current_user($outsider->ID); intent_check(ci_intent_history($doctor)['status']==='forbidden','unrelated actor cannot list history');
 wp_set_current_user($admin->ID); $other=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic different history doctor'),true); if (is_wp_error($other) || !$other) { throw new RuntimeException('other doctor failed'); }
 try { intent_check(ci_intent_history((int)$other,$cursor)['status']==='invalid' && ci_intent_history((int)$other)['status']==='unknown','cursor and listing scoped to doctor'); }
 finally { $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>(int)$other)); wp_delete_post((int)$other,true); }
 intent_check(ci_intent_cancel($doctor,intent_key(8))['status']==='cancelled' && ci_intent_find($doctor,intent_key(8))['status']==='cancelled' && $first['items'][0]['status']==='prepared','listing is historical, not a live or immutable snapshot');
 wp_set_current_user($owner->ID);
 intent_check(ci_intent_prepare($doctor,intent_key(9),6,intent_rows('16:30'))['status']==='prepared' && ci_intent_cancel($doctor,intent_key(9))['status']==='cancelled','seed other actor history');
 $own=ci_intent_history($doctor); intent_check(($own['status'] ?? '')==='ok' && array_column($own['items'],'key')===array(intent_key(9)) && $own['next']===null,'other authorized actor sees only own history');
 wp_set_current_user($admin->ID); intent_check(ci_intent_prepare($doctor,intent_key(10),6,intent_rows('17:00'))['status']==='prepared' && ci_intent_cancel($doctor,intent_key(10))['status']==='cancelled','append later history after first page');
 $page2=ci_intent_history($doctor,$cursor); $page3=ci_intent_history($doctor,$page2['next'] ?? null);
 intent_check(($page2['status'] ?? '')==='ok' && ($page3['status'] ?? '')==='ok' && array_column($page2['items'],'key')===array(intent_key(5),intent_key(4),intent_key(3)) && array_column($page3['items'],'key')===array(intent_key(2),intent_key(1)) && $page3['next']===null,'pagination is bounded, stable against later inserts and actor-scoped');
 $fresh=ci_intent_history($doctor); intent_check(($fresh['status'] ?? '')==='ok' && $fresh['items'][0]['key']===intent_key(10),'fresh listing sees later operations');
 intent_check($wpdb->update($table,array('state'=>'broken'),array('doctor_id'=>$doctor,'request_key'=>intent_key(10)))===1,'inject invalid history row');
 try { intent_check(ci_intent_history($doctor)===array('status'=>'unknown'),'invalid row does not return a partial or misleading page'); }
 finally { $wpdb->update($table,array('state'=>'cancelled'),array('doctor_id'=>$doctor,'request_key'=>intent_key(10))); }
 exit(0);
}
if ($mode==='cleanup') { $wpdb->query("DROP TABLE IF EXISTS `$table`"); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doctor)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$doctor)); wp_delete_post($doctor,true); unlink($meta); exit(0); }
exit(1);

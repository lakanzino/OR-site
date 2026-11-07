<?php
/** Shared only by disposable CI fixture and its loopback HTTP adapter; never product code. */
if (!in_array(PHP_SAPI,array('cli','cli-server'),true) || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1' || !defined('DB_NAME') || DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
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
  if (hj_booking_query($db,$db->prepare("INSERT INTO `$table` (doctor_id,request_key,actor_id,payload_hash,state,active,result_revision,base_revision,created_utc) VALUES (%d,%s,%d,%s,'prepared',1,0,%d,UTC_TIMESTAMP())",$doctor,$key,$actor,$hash,$revision))!==1) { throw new RuntimeException('intent insert failed'); }
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
/** Compare re-entered rows against one explicitly selected record, read-only. */
function ci_intent_compare($doctor,$key,$rows) {
 global $table;
 if (!hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 if (!intent_valid_key($key) || ($rows=hj_booking_normalize_schedule($rows))===false) { return array('status'=>'invalid'); }
 $actor=get_current_user_id();
 return hj_booking_transaction($doctor,'ci_intent_compare',function($db) use($doctor,$key,$rows,$actor,$table) {
  if (get_current_user_id()!==$actor || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
  $row=hj_booking_row($db,$db->prepare("SELECT actor_id,payload_hash,base_revision,state,active,result_revision FROM `$table` WHERE doctor_id=%d AND request_key=%s",$doctor,$key));
  if (!$row || (int)$row['actor_id']!==$actor || !intent_record_valid($row)) { return array('status'=>'unknown'); }
  $base=intent_history_number($row['base_revision']);
  if ($base===false || !is_string($row['payload_hash']) || !preg_match('/^[a-f0-9]{64}$/D',$row['payload_hash'])) { return array('status'=>'unknown'); }
  $expected=intent_hash($doctor,$actor,$base,$rows);
  return array('status'=>hash_equals($row['payload_hash'],$expected) ? 'matched' : 'different');
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

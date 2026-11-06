<?php
/** Synthetic capacity prototype; DO NOT run on real installations. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
define('HJ_BOOKING_DISPOSABLE_FIXTURE',true);
require getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
require __DIR__.'/interval-engine.php'; require __DIR__.'/capacity-engine.php';
function capacity_expect($v,$label) { if (!$v) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function capacity_key($n) { return str_pad(dechex($n),32,'0',STR_PAD_LEFT); }
update_option('timezone_string','Asia/Tehran');
$rule=array('start'=>'17:00','end'=>'21:00','minutes'=>60,'capacity'=>3);
$day='2030-01-02'; $now=strtotime('2030-01-01 00:00:00 UTC');
$mode=$argv[1] ?? '';
if ($mode === 'unit') {
 $r=hj_capacity_spike_slots($day,$rule,array(),$now,14);
 capacity_expect(count($r['slots']) === 4 && array_sum(array_column($r['slots'],'remaining')) === 12 && $r['slots'][0]['start'] === '2030-01-02 13:30:00', 'four one-hour blocks of capacity three yield twelve Tehran places');
 $slot=$r['slots'][0]; $used=array(array('starts_at'=>$slot['start'],'blocked_until'=>$slot['end'],'status'=>'pending'),array('starts_at'=>$slot['start'],'blocked_until'=>$slot['end'],'status'=>'confirmed'),array('starts_at'=>$slot['start'],'blocked_until'=>$slot['end'],'status'=>'cancelled'));
 $s=hj_capacity_spike_slots($day,$rule,$used,$now,14);
 capacity_expect($s['slots'][0]['remaining'] === 1 && $s['day_active'] === 2, 'active appointments consume places; cancelled does not');
 $used[]=array('starts_at'=>$slot['start'],'blocked_until'=>$slot['end'],'status'=>'pending');
 capacity_expect(hj_capacity_spike_slots($day,$rule,$used,$now,14)['slots'][0]['state'] === 'full', 'last place occupied blocks full slot');
 $s=hj_capacity_spike_slots($day,$rule,array(array('starts_at'=>'2030-01-02 14:00:00','blocked_until'=>'2030-01-02 15:00:00','status'=>'confirmed')),$now,14);
 capacity_expect($s['slots'][0]['remaining'] === 2 && $s['slots'][1]['remaining'] === 2 && $s['slots'][2]['remaining'] === 3, 'overlapping old intervals conservatively consume each overlapping block');
 $break=$rule+array('break_start'=>'19:00','break_end'=>'19:15');
 capacity_expect(count(hj_capacity_spike_slots($day,$break,array(),$now,14)['slots']) === 3, 'break blocks an intersecting whole slot');
 $cap=$rule+array('daily_cap'=>2);
 capacity_expect(hj_capacity_spike_slots($day,$cap,$used,$now,14)['slots'][1]['state'] === 'full', 'daily cap applies across different slots');
 $half=array('start'=>'17:00','end'=>'18:00','minutes'=>30,'capacity'=>1,'buffer'=>0);
 $s=hj_capacity_spike_slots($day,$half,array(),$now,14);
 capacity_expect(count($s['slots'])===2 && $s['slots'][1]['start']==='2030-01-02 14:00:00', 'half-hour default grid has no added buffer');
 capacity_expect(hj_capacity_spike_slots($day,$rule,array(array('status'=>'pending','starts_at'=>'broken','blocked_until'=>$slot['end'])),$now,14) === false, 'malformed active record fails closed');
 capacity_expect(hj_capacity_spike_slots('2030-02-30',$rule,array(),$now,14) === false && hj_capacity_spike_slots($day,array_merge($rule,array('capacity'=>0)),array(),$now,14) === false, 'invalid dates and capacity fail closed');
 capacity_expect(hj_capacity_spike_slots($day,$rule,array(),strtotime('2030-01-03 UTC'),14)['slots'][0]['state'] === 'past', 'past slots disabled');
 capacity_expect(hj_capacity_spike_slots($day,$rule,array(),strtotime('2029-12-01 UTC'),14)['slots'][0]['state'] === 'beyond', 'slots beyond horizon disabled');
} elseif ($mode === 'setup') {
 $db=hj_interval_connection();
 try { hj_interval_query($db,'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)','iiiss',32001,60,0,'2030-01-01 00:00:00','2030-01-10 00:00:00'); }
 finally { $db->close(); }
} elseif ($mode === 'race') {
 $worker=getenv('HJ_CAP_WORKER'); if (!is_string($worker) || !preg_match('/^[0-9]{1,2}$/D',$worker)) { exit(1); }
 $prefix=getenv('RUNNER_TEMP').'/hj-capacity-spike'; touch($prefix.'-ready-'.$worker); $until=microtime(true)+30;
 while (!is_file($prefix.'-go') && microtime(true)<$until) { usleep(10000); clearstatcache(); }
 if (!is_file($prefix.'-go')) { exit(1); }
 $i=(int)$worker;
 $r=hj_interval_transaction(32001,function($db) use($rule,$day,$now,$i) {
  $rows=hj_interval_query($db,"SELECT starts_at,blocked_until,status FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed')",'i',32001);
  $result=hj_capacity_spike_slots($day,$rule,$rows,$now,14);
  if ($result===false || !$result['slots']) { return array('status'=>'invalid'); }
  $slot=$result['slots'][0]; if ($slot['remaining']<1) { return array('status'=>'full'); }
  $key=capacity_key(80000+$i); $hash=hash('sha256',$key);
  hj_interval_query($db,"INSERT INTO hjt_hjb_spike_appointments (doctor_id,patient_id,request_key,request_hash,starts_at,ends_at,blocked_until,status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",'iisssss',32001,40000+$i,$key,$hash,$slot['start'],$slot['end'],$slot['end']);
  return array('status'=>'created');
 });
 echo $r['status'];
} elseif ($mode === 'verify') {
 $db=hj_interval_connection();
 try { $rows=hj_interval_query($db,"SELECT COUNT(*) AS n FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND status IN ('pending','confirmed')",'i',32001); capacity_expect((int)$rows[0]['n']===3,'concurrent capacity-three race leaves exactly three active bookings'); }
 finally { $db->close(); }
} else { exit(1); }

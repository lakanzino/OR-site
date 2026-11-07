<?php
/** Pure, disposable-CI-only proposal, NOT a migration or a production tool.
 * Reads no DB rows and writes nothing; callers provide synthetic data only.
 */
if (PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE') . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors();

/** Propose a v2 equivalent of a strictly valid v1 policy. This is not a
 * safe automatic deployment: callers must independently prove old workers
 * are drained, handle mixed versions, appointments, revision and recovery.
 */
function ci_legacy_capacity_proposal($snapshot, $duration, $buffer) {
 if (!is_array($snapshot) || !isset($snapshot['revision']) || !is_scalar($snapshot['revision'])) { return false; }
 $revision=(string)$snapshot['revision'];
 if (!preg_match('/^[1-9][0-9]*$/D',$revision) || (string)(int)$revision!==$revision || !is_int($duration) || $duration<1 || $duration>240 || !is_int($buffer) || $buffer<0 || $buffer>120) { return false; }
 $old=hj_booking_legacy_snapshot_rows($snapshot);
 if ($old===false) { return false; }
 $rows=array();
 foreach ($old as $row) { $rows[]=$row+array('duration_minutes'=>$duration,'buffer_minutes'=>$buffer,'capacity'=>1); }
 $rows=hj_booking_normalize_capacity_week($rows);
 if ($rows===false) { return false; }
 return array('source_revision'=>(int)$revision,'rows'=>$rows);
}
function proposal_assert($ok,$name) { if (!$ok) { throw new RuntimeException($name); } echo "PASS: $name\n"; }
function snap($rows,$revision='7') { $json=wp_json_encode($rows); return array('revision'=>$revision,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json)); }
function available_v2($day) { return array_values(array_map(function($x){return $x['start'];},array_filter($day['slots'],function($x){return $x['state']==='available';}))); }

// Includes seconds, a closed day and intentionally absent days; no real data.
$legacy=array(
 array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00:30','end_time'=>'10:10:30'),
 array('day_of_week'=>'tuesday','is_closed'=>1,'start_time'=>'','end_time'=>''),
);
$input=snap($legacy); $copy=$input; $proposal=ci_legacy_capacity_proposal($input,7,5);
proposal_assert($proposal!==false && $proposal['source_revision']===7 && count($proposal['rows'])===2 && $proposal['rows'][0]['start_time']==='09:00:30' && $proposal['rows'][0]['duration_minutes']===7 && $proposal['rows'][0]['buffer_minutes']===5 && $proposal['rows'][0]['capacity']===1 && $proposal['rows'][1]['is_closed']===1 && $input===$copy,'proposal preserves revision, short duration, buffer, seconds, closure and missing days without mutating input');
proposal_assert(ci_legacy_capacity_proposal(snap(array()),30,0)['rows']===array(),'empty legacy schedule remains empty');
$bad=$input; $bad['rows_hash']=str_repeat('0',64);
proposal_assert(ci_legacy_capacity_proposal($bad,7,5)===false,'corrupt hash rejected');
$bad=snap(array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00','end_time'=>'10:00','capacity'=>3)));
proposal_assert(ci_legacy_capacity_proposal($bad,7,5)===false,'unknown legacy fields rejected instead of losing capacity');
$bad=snap($legacy); $bad['rows_json']='{"version":2,"rows":[]}'; $bad['rows_hash']=hash('sha256',$bad['rows_json']);
proposal_assert(ci_legacy_capacity_proposal($bad,7,5)===false,'v2 envelope rejected rather than interpreted as legacy');
foreach (array(array(0,5),array(241,5),array(7,-1),array(7,121),array('7',5)) as $config) { proposal_assert(ci_legacy_capacity_proposal($input,$config[0],$config[1])===false,'invalid configuration rejected'); }
foreach (array('0','01','-1','999999999999999999999999999999') as $revision) { $bad=$input; $bad['revision']=$revision; proposal_assert(ci_legacy_capacity_proposal($bad,7,5)===false,'invalid revision rejected'); }
$bad=snap(array($legacy[0],$legacy[0])); proposal_assert(ci_legacy_capacity_proposal($bad,7,5)===false,'duplicate weekday rejected');

// Compare permitted starts in a stable timezone, before and after occupied rows.
add_filter('pre_option_timezone_string',function(){return 'Asia/Tehran';});
$date='2030-01-07'; // Monday; synthetic time, not based on wall-clock execution.
$now=strtotime('2030-01-06 00:00:00 UTC');
$old=hj_booking_legacy_snapshot_rows($input); $rule=$proposal['rows'][0];
$baseline=hj_booking_compute_slots($date,$old,7,5,array(),$now,14);
$capacity=hj_booking_compute_capacity_day($date,$rule,array(),$now,14,wp_timezone());
proposal_assert($capacity!==false && count($baseline)>2 && $baseline===available_v2($capacity),'capacity one matches legacy available starts for seconds and short intervals');
$occupied=array(
 array('starts_at'=>$baseline[0],'blocked_until'=>gmdate('Y-m-d H:i:s',strtotime($baseline[0].' UTC')+12*60),'status'=>'pending'),
 array('starts_at'=>$baseline[1],'blocked_until'=>gmdate('Y-m-d H:i:s',strtotime($baseline[1].' UTC')+12*60),'status'=>'confirmed'),
 array('starts_at'=>$baseline[2],'blocked_until'=>gmdate('Y-m-d H:i:s',strtotime($baseline[2].' UTC')+12*60),'status'=>'cancelled'),
);
$expected=hj_booking_compute_slots($date,$old,7,5,$occupied,$now,14);
$capacity=hj_booking_compute_capacity_day($date,$rule,$occupied,$now,14,wp_timezone());
proposal_assert($capacity!==false && $expected===available_v2($capacity) && !in_array($baseline[0],$expected,true) && !in_array($baseline[1],$expected,true) && in_array($baseline[2],$expected,true),'pending and confirmed block while cancelled does not, preserving available starts');
$occupied[0]['status']='completed';
proposal_assert(hj_booking_compute_capacity_day($date,$rule,$occupied,$now,14,wp_timezone())===false,'future terminal status fails closed rather than claiming full behavioral equivalence');
proposal_assert(hj_booking_compute_slots('2030-01-08',$old,7,5,array(),$now,14)===array() && hj_booking_compute_slots('2030-01-09',$old,7,5,array(),$now,14)===array(),'closed and absent weekdays never become available');

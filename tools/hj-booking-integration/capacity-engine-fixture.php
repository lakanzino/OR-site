<?php
/** Test the shipped pure capacity foundation in disposable WordPress only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
require getenv('GITHUB_WORKSPACE') . '/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
function capacity_check( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function capacity_row( $start, $end, $status = 'pending' ) { return array( 'starts_at'=>gmdate('Y-m-d H:i:s',$start), 'blocked_until'=>gmdate('Y-m-d H:i:s',$end), 'status'=>$status ); }
$tz = new DateTimeZone('Asia/Tehran'); $date='2030-01-02'; $now=strtotime('2030-01-01 00:00:00 UTC');
$rule=array('start_time'=>'17:00','end_time'=>'21:00','duration_minutes'=>60,'buffer_minutes'=>0,'capacity'=>3);
$r=hj_booking_compute_capacity_day($date,$rule,array(),$now,14,$tz);
capacity_check(count($r['slots'])===4 && $r['day_remaining']===12 && $r['slots'][0]['start']==='2030-01-02 13:30:00', 'four one-hour blocks capacity three equal twelve Tehran places');
$a=strtotime($r['slots'][0]['start'].' UTC');
$rows=array(capacity_row($a,$a+1800),capacity_row($a+1800,$a+3600,'confirmed'));
$r=hj_booking_compute_capacity_day($date,$rule,$rows,$now,14,$tz);
capacity_check($r['slots'][0]['remaining']===2, 'nonconcurrent legacy half-slots count by peak, not by total intersections');
$rows[]=capacity_row($a+900,$a+2700); $rows[]=capacity_row($a,$a+3600,'cancelled');
$r=hj_booking_compute_capacity_day($date,$rule,$rows,$now,14,$tz);
capacity_check($r['slots'][0]['remaining']===1 && $r['day_count']===3, 'overlapping active intervals count and cancellation does not');
$rows[]=capacity_row($a+1200,$a+2400);
capacity_check(hj_booking_compute_capacity_day($date,$rule,$rows,$now,14,$tz)['slots'][0]['state']==='full', 'full slot disabled');
$half=array('start_time'=>'17:00','end_time'=>'18:00','duration_minutes'=>30,'buffer_minutes'=>0,'capacity'=>1);
$r=hj_booking_compute_capacity_day($date,$half,array(),$now,14,$tz);
capacity_check(count($r['slots'])===2 && $r['slots'][1]['start']==='2030-01-02 14:00:00', 'half-hour default has no buffer');
$cap=$rule; $cap['daily_cap']=3;
$r=hj_booking_compute_capacity_day($date,$cap,array(capacity_row($a,$a+3600)), $now,14,$tz);
capacity_check($r['day_remaining']===2 && $r['slots'][1]['remaining']===2, 'daily summary does not sum the same daily cap repeatedly');
$r=hj_booking_compute_capacity_day($date,$cap,array(capacity_row($a,$a+3600,'completed'),capacity_row($a,$a+3600,'no_show'),capacity_row($a,$a+3600,'cancelled')), $a+3600,14,$tz);
capacity_check($r['day_count']===2 && $r['day_remaining']===1 && $r['slots'][2]['remaining']===1, 'completed and no-show visits count against daily cap; cancelled does not');
capacity_check(hj_booking_compute_capacity_day($date,$cap,array(capacity_row($a,$a+3600,'completed')),$now,14,$tz)===false, 'future terminal record fails closed');
$r=hj_booking_compute_capacity_day($date,$rule,array(capacity_row($a,$a+5400,'completed')),$a+1800,14,$tz);
capacity_check($r['slots'][1]['remaining']===2, 'terminal interval continues blocking until its recorded end');
$break=$rule+array('break_start'=>'19:00','break_end'=>'19:15');
capacity_check(count(hj_booking_compute_capacity_day($date,$break,array(),$now,14,$tz)['slots'])===3, 'break blocks intersecting interval');
$buffer=array('start_time'=>'17:00','end_time'=>'18:15','duration_minutes'=>30,'buffer_minutes'=>15,'capacity'=>1,'break_start'=>'17:35','break_end'=>'17:40');
capacity_check(count(hj_booking_compute_capacity_day($date,$buffer,array(),$now,14,$tz)['slots'])===1, 'buffer intersecting break blocks slot');
capacity_check(hj_booking_compute_capacity_day($date,$rule,array(capacity_row($a,$a+3600,'strange')),$now,14,$tz)===false && hj_booking_compute_capacity_day($date,$rule,array(array('status'=>'pending','starts_at'=>array(),'blocked_until'=>'x')),$now,14,$tz)===false, 'unknown status and malformed active data fail closed');
capacity_check(hj_booking_compute_capacity_day('2030-02-30',$rule,array(),$now,14,$tz)===false && hj_booking_compute_capacity_day($date,array_merge($rule,array('capacity'=>0)),array(),$now,14,$tz)===false, 'invalid date and capacity fail closed');
capacity_check(hj_booking_compute_capacity_day($date,$rule,array(),$a+1,14,$tz)['slots'][0]['state']==='past', 'past interval disabled');
capacity_check(hj_booking_compute_capacity_day($date,$rule,array(),$now,1,$tz)['slots'][0]['state']==='beyond', 'outside horizon disabled');
capacity_check(hj_booking_compute_capacity_day($date,$rule,array(),$a-3600,14,$tz,7200)['slots'][0]['state']==='lead_time', 'minimum lead time disabled');
$berlin=new DateTimeZone('Europe/Berlin'); $dst=array('start_time'=>'01:00','end_time'=>'04:00','duration_minutes'=>30,'buffer_minutes'=>0,'capacity'=>1);
$r=hj_booking_compute_capacity_day('2026-03-29',$dst,array(),strtotime('2026-03-01 UTC'),60,$berlin);
capacity_check(array_column($r['slots'],'start')===array('2026-03-29 00:00:00','2026-03-29 01:00:00','2026-03-29 01:30:00'), 'nonexistent DST local times skipped');
$dst=array('start_time'=>'01:30','end_time'=>'03:30','duration_minutes'=>30,'buffer_minutes'=>30,'capacity'=>1);
$r=hj_booking_compute_capacity_day('2026-10-25',$dst,array(),strtotime('2026-10-01 UTC'),60,$berlin);
capacity_check(count($r['slots'])===0, 'ambiguous DST instant in buffer fails closed');
// Independent brute-force oracle over 5-minute cells, including touching ends.
mt_srand(6321);
for ($case=0;$case<300;$case++) {
 $items=array(); $expected=0; $occupied_grid=array_fill(0,12,0);
 for ($i=0,$n=mt_rand(0,10);$i<$n;$i++) {
  $from=mt_rand(-6,17); $to=$from+mt_rand(1,9); $items[]=capacity_row($a+$from*300,$a+$to*300);
  for ($j=max(0,$from);$j<min(12,$to);$j++) { $occupied_grid[$j]++; }
 }
 $expected=max(0,3-max($occupied_grid));
 $actual=hj_booking_compute_capacity_day($date,$rule,$items,$now,14,$tz)['slots'][0]['remaining'];
 if ($actual!==$expected) { throw new RuntimeException('Peak-overlap oracle mismatch at case '.$case); }
}
echo "PASS: 300 deterministic interval configurations match independent peak-overlap oracle\n";
// New single-capacity calculations must agree with the old product engine.
update_option('timezone_string','Asia/Tehran');
$week=array(array('day_of_week'=>'wednesday','is_closed'=>0,'start_time'=>'17:00:00','end_time'=>'21:00:00'));
for ($case=0;$case<200;$case++) {
 $duration=array(30,60,90)[mt_rand(0,2)]; $buffer=array(0,5,10)[mt_rand(0,2)]; $items=array();
 for ($i=0,$n=mt_rand(0,6);$i<$n;$i++) { $from=mt_rand(-6,48); $items[]=capacity_row($a+$from*300,$a+($from+mt_rand(1,9))*300); }
 $legacy=hj_booking_compute_slots($date,$week,$duration,$buffer,$items,$now,14);
 $rule_one=array('start_time'=>'17:00','end_time'=>'21:00','duration_minutes'=>$duration,'buffer_minutes'=>$buffer,'capacity'=>1);
 $detailed=hj_booking_compute_capacity_day($date,$rule_one,$items,$now,14,$tz);
 $current=array_column(array_values(array_filter($detailed['slots'],function($s){return $s['state']==='available';})),'start');
 if ($legacy!==$current) { throw new RuntimeException('Legacy single-capacity mismatch at case '.$case); }
}
echo "PASS: 200 single-capacity configurations match the existing product engine\n";
// Old schema legally stores seconds and duration below ten minutes; do not
// round or reset those when the new representation is eventually deployed.
$week=array(array('day_of_week'=>'wednesday','is_closed'=>0,'start_time'=>'17:00:30','end_time'=>'17:10:30'));
foreach (array(1,5,9) as $duration) {
 $legacy=hj_booking_compute_slots($date,$week,$duration,0,array(),$now,14);
 $rule_one=array('start_time'=>'17:00:30','end_time'=>'17:10:30','duration_minutes'=>$duration,'buffer_minutes'=>0,'capacity'=>1);
 $detail=hj_booking_compute_capacity_day($date,$rule_one,array(),$now,14,$tz);
 $current=array_column($detail['slots'],'start');
 if ($legacy!==$current) { throw new RuntimeException('Legacy seconds/short-duration mismatch'); }
}
echo "PASS: legacy second precision and one-to-nine-minute durations are preserved\n";

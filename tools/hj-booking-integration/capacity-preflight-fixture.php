<?php
/** Mutates only deliberately synthetic rows to test the read-only inventory. */
require __DIR__.'/capacity-preflight.php';
global $wpdb;
$db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true);
$d=hj_booking_table('doctors'); $s=hj_booking_table('schedules'); $a=hj_booking_table('appointments'); $p=hj_booking_table('details');
function preflight_check($ok,$label) { if (!$ok) { throw new RuntimeException($label); } echo "PASS: $label\n"; }
function preflight_fingerprint($db) {
 $out=array(); foreach(array('doctors','schedules','appointments','details','secretaries') as $name) { $t=hj_booking_table($name); $out[]=hj_booking_rows($db,"SELECT * FROM `$t` ORDER BY 1"); }
 $out[]=hj_booking_rows($db,"SELECT option_name,option_value FROM `{$GLOBALS['wpdb']->options}` WHERE option_name IN ('hj_booking_schema_version','hj_booking_schema_verified') ORDER BY option_name");
 return hash('sha256',wp_json_encode($out));
}
$owned=false; $added_column=false;
try {
 preflight_check(hj_booking_enabled() && (int)get_option('hj_booking_schema_version')===1,'preflight fixture begins at v1');
 foreach(array($d,$s,$a) as $table) { preflight_check(!hj_booking_row($db,"SELECT 1 AS found FROM `$table` WHERE doctor_id IN (998601,998602) LIMIT 1"),'synthetic doctor IDs unused'); }
 preflight_check(!hj_booking_row($db,"SELECT 1 AS found FROM `$p` WHERE appointment_id=998603 LIMIT 1"),'synthetic orphan detail ID unused');
 $before=preflight_fingerprint($db); $report=ci_capacity_preflight($db);
 preflight_check($report['decision']==='NO_GO' && $report['status']==='inventory' && in_array('workers_not_drained_or_proven',$report['blockers'],true) && preflight_fingerprint($db)===$before,'valid schema inventory is read-only and never grants deployment');
 preflight_check(!preg_match('/09[0-9]{9}|1234567891/',wp_json_encode($report)),'aggregate report contains no synthetic phone or national code');
 $owned=true;
 preflight_check($wpdb->insert($d,array('doctor_id'=>998601,'duration_minutes'=>0,'buffer_minutes'=>5,'enabled'=>2))===1,'seed invalid synthetic config');
 $json='[]';
 preflight_check($wpdb->insert($s,array('doctor_id'=>998601,'revision'=>7,'rows_json'=>$json,'rows_hash'=>str_repeat('0',64)))===1,'seed corrupt synthetic hash');
 preflight_check($wpdb->insert($s,array('doctor_id'=>998602,'revision'=>8,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json)))===1,'seed orphan schedule');
 preflight_check($wpdb->insert($a,array('doctor_id'=>998602,'patient_id'=>998604,'request_key'=>str_repeat('8',32),'request_hash'=>str_repeat('e',64),'starts_at'=>'2030-01-01 09:00:00','ends_at'=>'2030-01-01 09:30:00','blocked_until'=>'2030-01-01 09:35:00','schedule_revision'=>8,'status'=>'unknown'))===1,'seed invalid synthetic appointment without details');
 preflight_check($wpdb->insert($a,array('doctor_id'=>998601,'patient_id'=>998605,'request_key'=>str_repeat('7',32),'request_hash'=>str_repeat('f',64),'starts_at'=>'2030-01-01 09:00:00','ends_at'=>'2030-01-01 09:30:00','blocked_until'=>'2030-01-01 09:35:00','schedule_revision'=>7,'status'=>'Pending'))===1,'seed case-mismatched synthetic status');
 preflight_check($wpdb->insert($p,array('appointment_id'=>998603,'phone'=>'09123456789','national_code'=>'1234567891'))===1,'seed orphan synthetic details');
 $bad_before=preflight_fingerprint($db); $bad=ci_capacity_preflight($db);
 foreach(array('invalid_configs','unreadable_snapshots','orphan_schedules','orphan_appointments','missing_details','orphan_details','invalid_appointments') as $key) { preflight_check(($bad['counts'][$key] ?? 0)>0 && in_array($key,$bad['blockers'],true),'inventory reports '.$key); }
 preflight_check($bad['counts']['invalid_appointments']>=2,'status validation is case sensitive despite database collation');
 preflight_check($bad['decision']==='NO_GO' && preflight_fingerprint($db)===$bad_before && !preg_match('/09[0-9]{9}|1234567891/',wp_json_encode($bad)),'invalid inventory remains read-only and aggregate-only');
 hj_booking_query($db,"ALTER TABLE `$s` ADD COLUMN ci_unknown int NOT NULL DEFAULT 0"); $added_column=true;
 $invalid=ci_capacity_preflight($db);
 preflight_check($invalid['decision']==='NO_GO' && $invalid['status']==='invalid_schema' && $invalid['counts']===array(),'unknown schema fails closed without partial inventory');
} finally {
 if ($added_column) { $db->query("ALTER TABLE `$s` DROP COLUMN ci_unknown"); }
 if ($owned) { $wpdb->delete($p,array('appointment_id'=>998603)); $wpdb->delete($a,array('doctor_id'=>998602)); $wpdb->delete($a,array('doctor_id'=>998601)); $wpdb->delete($s,array('doctor_id'=>998602)); $wpdb->delete($s,array('doctor_id'=>998601)); $wpdb->delete($d,array('doctor_id'=>998601)); }
 $db->close();
}

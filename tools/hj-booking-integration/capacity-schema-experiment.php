<?php
/** Destructive fixture ONLY; all helpers hard-gated to disposable CI. */
require __DIR__ . '/capacity-schema-experiment-lib.php';
$owns_layout=false; $seeded=false;
try {
 schema_check(hj_booking_ready() && !schema_exists($db,$extra) && !schema_exists($db,$probe) && !isset(schema_columns($db,$d)['activation_generation']),'fixture starts as verified v1 without experimental schema');
 schema_check(!hj_booking_row($db,"SELECT 1 AS present FROM `$d` WHERE doctor_id=998801"),'synthetic doctor ID is unoccupied');
 $owns_layout=true;
 // Seed additional data without touching the sentinel rows created by the earlier fixture.
 $s=hj_booking_table('schedules'); $a=hj_booking_table('appointments'); $p=hj_booking_table('details');
 schema_check($wpdb->insert($d,array('doctor_id'=>998801,'duration_minutes'=>7,'buffer_minutes'=>5,'enabled'=>1))===1,'insert synthetic legacy doctor');
 $seeded=true;
 $json=wp_json_encode(array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00:30','end_time'=>'10:00:30')));
 schema_check($wpdb->insert($s,array('doctor_id'=>998801,'revision'=>7,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json)))===1,'insert synthetic legacy snapshot');
 foreach(array('pending','confirmed','cancelled') as $i=>$status) {
  schema_check($wpdb->insert($a,array('doctor_id'=>998801,'patient_id'=>998802+$i,'request_key'=>str_repeat((string)($i+1),32),'request_hash'=>str_repeat((string)($i+1),64),'starts_at'=>'2030-01-07 09:00:30','ends_at'=>'2030-01-07 09:07:30','blocked_until'=>'2030-01-07 09:12:30','schedule_revision'=>7,'status'=>$status))===1,'insert synthetic appointment status');
  schema_check($wpdb->insert($p,array('appointment_id'=>(int)$wpdb->insert_id,'phone'=>'09123456789','national_code'=>'1234567891'))===1,'insert synthetic private detail');
 }
 $before=legacy_rows($db,$d);
 // Wrong pre-existing objects must be detected before touching either object.
 hj_booking_query($db,"CREATE TABLE `$extra` (appointment_id bigint unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB");
 schema_check(schema_prototype($db,$d,$extra)==='invalid' && !isset(schema_columns($db,$d)['activation_generation']) && legacy_rows($db,$d)===$before,'incompatible extra table fails before any schema or data mutation');
 hj_booking_query($db,"DROP TABLE `$extra`");
 hj_booking_query($db,"ALTER TABLE `$d` ADD COLUMN activation_generation int NOT NULL DEFAULT 0");
 schema_check(schema_prototype($db,$d,$extra)==='invalid' && !schema_exists($db,$extra) && legacy_rows($db,$d)===$before,'incompatible existing generation column fails closed');
 hj_booking_query($db,"ALTER TABLE `$d` DROP COLUMN activation_generation");
 schema_check(schema_prototype($db,$d,$extra,'column')==='interrupted' && isset(schema_columns($db,$d)['activation_generation']) && !schema_exists($db,$extra) && legacy_rows($db,$d)===$before,'interruption after column leaves original rows unchanged');
 schema_check(schema_prototype($db,$d,$extra,'table')==='interrupted' && schema_extra_valid($db,$extra) && legacy_rows($db,$d)===$before,'rerun resumes after column and interruption after table preserves old rows');
 $ddl=hj_booking_row($db,"SHOW CREATE TABLE `$extra`");
 schema_check(schema_prototype($db,$d,$extra)==='prepared' && schema_prototype($db,$d,$extra)==='prepared' && hj_booking_row($db,"SHOW CREATE TABLE `$extra`")===$ddl && legacy_rows($db,$d)===$before,'repeated schema preparation is idempotent for empty new table and preserves all legacy data');
 schema_check((int)get_option('hj_booking_schema_version')===1 && (int)get_option('hj_booking_schema_verified')===1 && !hj_booking_capacity_v2_ready(),'preparation does not activate or upgrade v2');
 // Populated new table is not silently adopted or cleared.
 schema_check($wpdb->insert($extra,array('appointment_id'=>888001,'first_name'=>'تست','last_name'=>'آزمایش','service'=>'visit','note'=>'','tracking_code'=>str_repeat('A',32)))===1,'seed synthetic extra record');
 schema_check(schema_prototype($db,$d,$extra)==='invalid' && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$extra`")===1 && legacy_rows($db,$d)===$before,'populated extra table fails closed without deletion');
 // MariaDB DDL is not rolled back like application data.
 hj_booking_query($db,"CREATE TABLE `$probe` (id int NOT NULL PRIMARY KEY) ENGINE=InnoDB");
 hj_booking_query($db,'START TRANSACTION'); hj_booking_query($db,"ALTER TABLE `$probe` ADD COLUMN marker int NOT NULL DEFAULT 0"); hj_booking_query($db,'ROLLBACK');
 schema_check(isset(schema_columns($db,$probe)['marker']),'DDL auto-commits and cannot be assumed transactional');
} finally {
 // Cleanup is destructive ONLY inside the guarded disposable fixture.
 if ($owns_layout && schema_exists($db,$probe)) { $db->query("DROP TABLE `$probe`"); }
 if ($owns_layout && schema_exists($db,$extra)) { $db->query("DROP TABLE `$extra`"); }
 if ($owns_layout && schema_exists($db,$d) && isset(schema_columns($db,$d)['activation_generation'])) { $db->query("ALTER TABLE `$d` DROP COLUMN activation_generation"); }
 if ($seeded && isset($a,$p,$s)) {
  foreach($wpdb->get_col("SELECT id FROM `$a` WHERE doctor_id=998801") as $id) { $wpdb->delete($p,array('appointment_id'=>(int)$id)); }
  $wpdb->delete($a,array('doctor_id'=>998801)); $wpdb->delete($s,array('doctor_id'=>998801)); $wpdb->delete($d,array('doctor_id'=>998801));
 }
 $db->close();
}

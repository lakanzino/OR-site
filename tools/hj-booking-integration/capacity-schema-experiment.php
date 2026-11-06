<?php
/** Destructive schema experiment ONLY for the disposable CI database.
 * NOT an installer, not a product migration, and never shipped in the plugin.
 * DDL auto-commits: old workers must be drained independently before any real upgrade.
 */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
$admin=get_user_by('login','fixture_admin'); if (!$admin || !hj_booking_enabled()) { exit(1); } wp_set_current_user($admin->ID);
global $wpdb;
$db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true);
$d=hj_booking_table('doctors'); $extra=$wpdb->prefix.'hj_booking_patient_extra_test'; $probe=$wpdb->prefix.'hj_booking_ddl_probe';
function schema_check($v,$name) { if (!$v) { throw new RuntimeException($name); } echo "PASS: $name\n"; }
function schema_exists($db,$name) { return (bool)hj_booking_row($db,$db->prepare('SHOW TABLE STATUS WHERE Name = %s',$name)); }
function schema_innodb($db,$name) { $row=hj_booking_row($db,$db->prepare('SHOW TABLE STATUS WHERE Name = %s',$name)); return $row && strtoupper($row['Engine'] ?? '')==='INNODB'; }
function schema_columns($db,$name) { $out=array(); foreach(hj_booking_rows($db,"SHOW FULL COLUMNS FROM `$name`") as $c) { $out[$c['Field']]=$c; } return $out; }
function schema_type($c) { return preg_replace('/^(bigint|int|tinyint)\([0-9]+\)/','$1',strtolower($c['Type'])); }
function schema_indices($db,$name) {
 $out=array(); foreach(hj_booking_rows($db,"SHOW INDEX FROM `$name`") as $i) { $k=$i['Key_name']; if (!isset($out[$k])) { $out[$k]=array('unique'=>(int)$i['Non_unique']===0,'cols'=>array(),'whole'=>true); } $out[$k]['cols'][(int)$i['Seq_in_index']]=$i['Column_name']; if ($i['Sub_part']!==null) { $out[$k]['whole']=false; } }
 foreach($out as &$i) { ksort($i['cols']); $i['cols']=array_values($i['cols']); } unset($i); ksort($out); return $out;
}
function schema_doctors_valid($db,$name) {
 if (!schema_innodb($db,$name)) { return false; }
 $c=schema_columns($db,$name); $names=array_keys($c); sort($names); $base=array('doctor_id','duration_minutes','buffer_minutes','enabled'); if (isset($c['activation_generation'])) { $base[]='activation_generation'; } sort($base);
 if ($names!==$base) { return false; }
 foreach(array('doctor_id'=>'bigint unsigned','duration_minutes'=>'int','buffer_minutes'=>'int','enabled'=>'tinyint') as $field=>$type) { if (schema_type($c[$field])!==$type || $c[$field]['Null']!=='NO' || $c[$field]['Extra']!=='') { return false; } }
 foreach(array('duration_minutes'=>'30','buffer_minutes'=>'5','enabled'=>'0') as $field=>$value) { if ((string)$c[$field]['Default']!==$value) { return false; } }
 if (isset($c['activation_generation']) && (schema_type($c['activation_generation'])!=='bigint unsigned' || $c['activation_generation']['Null']!=='NO' || (string)$c['activation_generation']['Default']!=='0' || $c['activation_generation']['Extra']!=='')) { return false; }
 return schema_indices($db,$name)===array('PRIMARY'=>array('unique'=>true,'cols'=>array('doctor_id'),'whole'=>true));
}
function schema_extra_valid($db,$name) {
 if (!schema_innodb($db,$name)) { return false; }
 $c=schema_columns($db,$name); $types=array('appointment_id'=>'bigint unsigned','first_name'=>'varchar(80)','last_name'=>'varchar(80)','service'=>'varchar(20)','note'=>'varchar(500)','tracking_code'=>'char(32)');
 $keys=array_keys($c); sort($keys); $want=array_keys($types); sort($want); if ($keys!==$want) { return false; }
 foreach($types as $field=>$type) { if (schema_type($c[$field])!==$type || $c[$field]['Null']!=='NO' || $c[$field]['Extra']!=='') { return false; } if ($field!=='appointment_id' && strpos((string)$c[$field]['Collation'],'utf8mb4_')!==0) { return false; } }
 if ($c['tracking_code']['Collation']!=='utf8mb4_bin') { return false; }
 return schema_indices($db,$name)===array('PRIMARY'=>array('unique'=>true,'cols'=>array('appointment_id'),'whole'=>true),'tracking_code'=>array('unique'=>true,'cols'=>array('tracking_code'),'whole'=>true));
}
/** Sequential DDL, deliberately does not update schema version or enable v2.
 * stop_after simulates interruption after a committed DDL, not during COMMIT.
 * Test assumes isolation; GET_LOCK is NOT a worker-drain mechanism.
 */
function schema_prototype($db,$d,$extra,$stop_after='') {
 $lock='hj-ci-schema-experiment'; $held=false;
 try {
  $got=hj_booking_row($db,$db->prepare('SELECT GET_LOCK(%s,5) AS acquired',$lock)); if (!$got || (int)$got['acquired']!==1) { return 'busy'; } $held=true;
  hj_booking_schema_clear_option_cache();
  if ((string)get_option('hj_booking_schema_version','')!=='1' || (string)get_option('hj_booking_schema_verified','')!=='1') { return 'invalid'; }
  if (!schema_doctors_valid($db,$d)) { return 'invalid'; }
  foreach(array('schedules','appointments','details','secretaries') as $name) { if (!hj_booking_schema_valid_table($db,hj_booking_table($name))) { return 'invalid'; } }
  if (schema_exists($db,$extra) && (!schema_extra_valid($db,$extra) || hj_booking_row($db,"SELECT 1 AS present FROM `$extra` LIMIT 1"))) { return 'invalid'; }
  $columns=schema_columns($db,$d);
  if (!isset($columns['activation_generation'])) { hj_booking_query($db,"ALTER TABLE `$d` ADD COLUMN activation_generation bigint unsigned NOT NULL DEFAULT 0"); }
  if (!schema_doctors_valid($db,$d)) { return 'invalid'; }
  if ($stop_after==='column') { return 'interrupted'; }
  if (!schema_exists($db,$extra)) { hj_booking_query($db,"CREATE TABLE `$extra` (appointment_id bigint unsigned NOT NULL PRIMARY KEY,first_name varchar(80) NOT NULL,last_name varchar(80) NOT NULL,service varchar(20) NOT NULL,note varchar(500) NOT NULL,tracking_code char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,UNIQUE KEY tracking_code (tracking_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
  if (!schema_extra_valid($db,$extra)) { return 'invalid'; }
  if ($stop_after==='table') { return 'interrupted'; }
  return 'prepared';
 } catch (Throwable $e) { return 'error'; }
 finally { if ($held) { $db->get_var($db->prepare('SELECT RELEASE_LOCK(%s)',$lock)); } }
}
function legacy_rows($db,$d) {
 $out=array(); foreach(array('doctors','schedules','appointments','details','secretaries') as $n) { $table=hj_booking_table($n); $fields=$table===$d ? 'doctor_id,duration_minutes,buffer_minutes,enabled' : '*'; $out[$n]=hj_booking_rows($db,"SELECT $fields FROM `$table` ORDER BY 1"); } return $out;
}
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

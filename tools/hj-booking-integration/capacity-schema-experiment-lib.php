<?php
/** Destructive schema experiment library ONLY for the disposable CI database.
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
function schema_prototype($db,$d,$extra,$stop_after='', $test_event=null) {
 $lock='hj-ci-schema-experiment'; $held=false;
 try {
  $got=hj_booking_row($db,$db->prepare('SELECT GET_LOCK(%s,5) AS acquired',$lock)); if (!$got || (int)$got['acquired']!==1) { return 'busy'; } $held=true;
  if (is_callable($test_event)) { $test_event('locked',$db); }
  hj_booking_schema_clear_option_cache();
  if ((string)get_option('hj_booking_schema_version','')!=='1' || (string)get_option('hj_booking_schema_verified','')!=='1') { return 'invalid'; }
  if (!schema_doctors_valid($db,$d)) { return 'invalid'; }
  foreach(array('schedules','appointments','details','secretaries') as $name) { if (!hj_booking_schema_valid_table($db,hj_booking_table($name))) { return 'invalid'; } }
  if (schema_exists($db,$extra) && (!schema_extra_valid($db,$extra) || hj_booking_row($db,"SELECT 1 AS present FROM `$extra` LIMIT 1"))) { return 'invalid'; }
  $columns=schema_columns($db,$d);
  if (!isset($columns['activation_generation'])) { hj_booking_query($db,"ALTER TABLE `$d` ADD COLUMN activation_generation bigint unsigned NOT NULL DEFAULT 0"); }
  if (!schema_doctors_valid($db,$d)) { return 'invalid'; }
  if (is_callable($test_event)) { $test_event('column',$db); }
  if ($stop_after==='column') { return 'interrupted'; }
  if (!schema_exists($db,$extra)) { hj_booking_query($db,"CREATE TABLE `$extra` (appointment_id bigint unsigned NOT NULL PRIMARY KEY,first_name varchar(80) NOT NULL,last_name varchar(80) NOT NULL,service varchar(20) NOT NULL,note varchar(500) NOT NULL,tracking_code char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,UNIQUE KEY tracking_code (tracking_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
  if (!schema_extra_valid($db,$extra)) { return 'invalid'; }
  if (is_callable($test_event)) { $test_event('table',$db); }
  if ($stop_after==='table') { return 'interrupted'; }
  return 'prepared';
 } catch (Throwable $e) { return 'error'; }
 finally { if ($held) { $db->get_var($db->prepare('SELECT RELEASE_LOCK(%s)',$lock)); } }
}
function legacy_rows($db,$d) {
 $out=array(); foreach(array('doctors','schedules','appointments','details','secretaries') as $n) { $table=hj_booking_table($n); $fields=$table===$d ? 'doctor_id,duration_minutes,buffer_minutes,enabled' : '*'; $out[$n]=hj_booking_rows($db,"SELECT $fields FROM `$table` ORDER BY 1"); } return $out;
}

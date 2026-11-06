<?php
/** Read-only inventory prototype, ONLY for synthetic disposable CI fixtures.
 * Not shipped in the plugin, not a live-site tool, not an authorization to deploy.
 */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require_once '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
/** Always NO-GO: this isolated inventory cannot prove backup, worker draining,
 * legacy writers, cache refresh, deployment topology, or real-site conditions.
 * Results contain aggregate counts only, never a booking or patient identifier.
 */
function ci_capacity_preflight($db) {
 global $wpdb;
 $report=array('decision'=>'NO_GO','status'=>'unavailable','counts'=>array(),'blockers'=>array('production_not_assessed','workers_not_drained_or_proven','external_writers_not_assessed','restore_not_verified','deployment_and_cache_not_verified'));
 $started=false;
 try {
  if (get_class($wpdb)!=='wpdb' || get_class($db)!=='HJ_Booking_Transaction_DB') { throw new RuntimeException('unsupported database'); }
  // A consistent read-only transaction does not lock out DDL and is NOT a
  // substitute for a maintenance window or proof of a stable deployment.
  hj_booking_query($db,'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  hj_booking_query($db,'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY'); $started=true;
  $option_table=$wpdb->options;
  if (!preg_match('/^[A-Za-z0-9_]+$/D',$option_table)) { throw new RuntimeException('invalid options table'); }
  $options=hj_booking_rows($db,"SELECT option_name,option_value FROM `$option_table` WHERE option_name IN ('hj_booking_schema_version','hj_booking_schema_verified')");
  $values=array(); foreach($options as $row) { $values[$row['option_name']]=$row['option_value']; }
  if (($values['hj_booking_schema_version'] ?? '')!=='1' || ($values['hj_booking_schema_verified'] ?? '')!=='1') { $report['status']='invalid_schema'; return $report; }
  foreach(array('doctors','schedules','appointments','details','secretaries') as $name) {
   if (!hj_booking_schema_valid_table($db,hj_booking_table($name))) { $report['status']='invalid_schema'; return $report; }
  }
  $users=$wpdb->users;
  if (!preg_match('/^[A-Za-z0-9_]+$/D',$users)) { throw new RuntimeException('invalid users table'); }
  $status=hj_booking_row($db,$db->prepare('SHOW TABLE STATUS WHERE Name = %s',$users));
  if (!$status || strtoupper($status['Engine'] ?? '')!=='INNODB') { $report['status']='invalid_schema'; return $report; }
  $d=hj_booking_table('doctors'); $s=hj_booking_table('schedules'); $a=hj_booking_table('appointments'); $p=hj_booking_table('details');
  $counts=array('doctors'=>0,'schedules'=>0,'legacy_valid'=>0,'unreadable_snapshots'=>0,'orphan_schedules'=>0,'invalid_configs'=>0,'enabled_without_schedule'=>0,'appointments'=>0,'orphan_appointments'=>0,'missing_details'=>0,'orphan_details'=>0,'invalid_appointments'=>0);
  $cfg=hj_booking_rows($db,"SELECT d.duration_minutes,d.buffer_minutes,d.enabled,s.doctor_id AS scheduled FROM `$d` d LEFT JOIN `$s` s ON s.doctor_id=d.doctor_id");
  foreach($cfg as $row) {
   $counts['doctors']++;
   if ((int)$row['duration_minutes']<1 || (int)$row['duration_minutes']>240 || (int)$row['buffer_minutes']<0 || (int)$row['buffer_minutes']>120 || !in_array((int)$row['enabled'],array(0,1),true)) { $counts['invalid_configs']++; }
   if ((int)$row['enabled']===1 && $row['scheduled']===null) { $counts['enabled_without_schedule']++; }
  }
  // Load no appointment/contact rows; schedule snapshots are inspected only in memory.
  foreach(hj_booking_rows($db,"SELECT s.revision,s.rows_json,s.rows_hash,d.doctor_id AS configured FROM `$s` s LEFT JOIN `$d` d ON d.doctor_id=s.doctor_id") as $row) {
   $counts['schedules']++;
   if ($row['configured']===null) { $counts['orphan_schedules']++; }
   if (hj_booking_legacy_snapshot_rows($row)===false) { $counts['unreadable_snapshots']++; } else { $counts['legacy_valid']++; }
  }
  $counts['appointments']=(int)hj_booking_row($db,"SELECT COUNT(*) AS n FROM `$a`")['n'];
  $counts['orphan_appointments']=(int)hj_booking_row($db,"SELECT COUNT(*) AS n FROM `$a` a LEFT JOIN `$d` d ON d.doctor_id=a.doctor_id WHERE d.doctor_id IS NULL")['n'];
  $counts['missing_details']=(int)hj_booking_row($db,"SELECT COUNT(*) AS n FROM `$a` a LEFT JOIN `$p` p ON p.appointment_id=a.id WHERE p.appointment_id IS NULL")['n'];
  $counts['orphan_details']=(int)hj_booking_row($db,"SELECT COUNT(*) AS n FROM `$p` p LEFT JOIN `$a` a ON a.id=p.appointment_id WHERE a.id IS NULL")['n'];
  $counts['invalid_appointments']=(int)hj_booking_row($db,"SELECT COUNT(*) AS n FROM `$a` WHERE status NOT IN ('pending','confirmed','cancelled','completed','no_show') OR starts_at>=ends_at OR ends_at>blocked_until")['n'];
  $report['status']='inventory'; $report['counts']=$counts;
  foreach(array('unreadable_snapshots','orphan_schedules','invalid_configs','enabled_without_schedule','orphan_appointments','missing_details','orphan_details','invalid_appointments') as $name) { if ($counts[$name]>0) { $report['blockers'][]=$name; } }
  return $report;
 } catch (Throwable $e) {
  // Never emit query text, SQL error, or private rows in a report.
  $report['status']='error'; $report['counts']=array(); return $report;
 } finally { if ($started) { $db->query('ROLLBACK'); } }
}

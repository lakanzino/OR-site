<?php
/** Synthetic counterexamples only: no production backup or deletion tool. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb; $table=$wpdb->prefix.'ci_schedule_intents';
require __DIR__.'/schedule-intent-functions.php'; require __DIR__.'/schedule-retention-report.php';
$admin=get_user_by('login','fixture_admin'); $owner=get_user_by('login','synthetic_doctor_a'); $patient=get_user_by('login','synthetic_race_0');
$meta=json_decode(@file_get_contents(getenv('RUNNER_TEMP').'/hj-ci-schedule-intent.json'),true);
if (!$admin || !$owner || !$patient || !is_array($meta) || empty($meta['doctor'])) { throw new RuntimeException('fixture prerequisites'); }
$doctor=(int)$meta['doctor']; $backup=$wpdb->prefix.'ci_retention_stale_intents';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($backup)))) { throw new RuntimeException('backup fixture name occupied'); }
wp_set_current_user($admin->ID); $created=false;
function retention_digest($doctor) { global $wpdb,$table; $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` WHERE doctor_id=%d ORDER BY intent_seq",$doctor),ARRAY_A); if ($wpdb->last_error || !is_array($rows)) { throw new RuntimeException('read failed'); } return hash('sha256',wp_json_encode(array($rows,intent_snapshot($doctor)))); }
try {
 $before=retention_digest($doctor); $empty=ci_retention_inventory($doctor);
 intent_check($empty['status']==='ok' && $empty['counts']['total']===0 && $empty['policy']==='retain_all' && retention_digest($doctor)===$before,'empty inventory is read-only, not proof of no prior request');
 wp_set_current_user($owner->ID); intent_check(hj_booking_can_configure($doctor) && ci_retention_inventory($doctor)['status']==='forbidden','owner is not a support administrator');
 wp_set_current_user($patient->ID); intent_check(ci_retention_inventory($doctor)['status']==='forbidden','unrelated user cannot inventory');
 wp_set_current_user($admin->ID);
 $key=intent_key(101); $rows=intent_rows('15:00');
 intent_check(ci_intent_prepare($doctor,$key,1,$rows)['status']==='prepared','prepare synthetic operation before stale copy');
 intent_check($wpdb->query("CREATE TABLE `$backup` LIKE `$table`")!==false,'create isolated stale-copy table'); $created=true;
 intent_check($wpdb->query($wpdb->prepare("INSERT INTO `$backup` SELECT * FROM `$table` WHERE doctor_id=%d",$doctor))===1,'copy prepared row only in quiescent fixture');
 intent_check(ci_intent_cancel($doctor,$key)['status']==='cancelled' && ci_intent_prepare($doctor,$key,1,$rows)['status']==='cancelled' && ci_intent_apply($doctor,$key,1,$rows)['status']==='blocked' && (int)intent_snapshot($doctor)['revision']===1,'retained cancellation blocks delayed prepare and apply');
 // Intent table alone is restored; deliberately unsafe, with no other workers.
 intent_check($wpdb->delete($table,array('doctor_id'=>$doctor))===1 && $wpdb->query($wpdb->prepare("INSERT INTO `$table` SELECT * FROM `$backup` WHERE doctor_id=%d",$doctor))===1,'simulate stale intent-only restore');
 intent_check(ci_intent_find($doctor,$key)['status']==='prepared' && ci_intent_apply($doctor,$key,1,$rows)['status']==='saved' && (int)intent_snapshot($doctor)['revision']===2,'stale restore resurrects prepared operation after cancellation');
 intent_check(ci_intent_ack($doctor,$key,2)['status']==='acknowledged','release restored synthetic result');
 $key2=intent_key(102); $rows2=intent_rows('16:00');
 intent_check(ci_intent_prepare($doctor,$key2,2,$rows2)['status']==='prepared' && ci_intent_cancel($doctor,$key2)['status']==='cancelled','cancel second operation');
 // Deliberate deletion is a counterexample, never a cleanup recommendation.
 intent_check($wpdb->delete($table,array('doctor_id'=>$doctor,'request_key'=>$key2))===1,'simulate unsafe tombstone deletion');
 intent_check(ci_intent_prepare($doctor,$key2,2,$rows2)['status']==='prepared' && ci_intent_apply($doctor,$key2,2,$rows2)['status']==='saved' && (int)intent_snapshot($doctor)['revision']===3,'delayed prepare plus apply can resurrect after deletion');
 $completed=ci_retention_inventory($doctor); intent_check($completed['status']==='ok' && $completed['counts']['completed']===1 && $completed['counts']['acknowledged']===1 && $completed['policy']==='retain_all','completed operation remains retained');
 $key3=intent_key(103);
 intent_check(ci_intent_ack($doctor,$key2,3)['status']==='acknowledged' && ci_intent_prepare($doctor,$key3,3,intent_rows('17:00'))['status']==='prepared' && ci_intent_cancel($doctor,$key3)['status']==='cancelled' && ci_intent_prepare($doctor,intent_key(104),3,intent_rows('18:00'))['status']==='prepared','seed different historical states');
 $before=retention_digest($doctor); $report=ci_retention_inventory($doctor);
 intent_check($report===array('status'=>'ok','counts'=>array('total'=>4,'prepared'=>1,'cancelled'=>1,'completed'=>0,'acknowledged'=>2),'policy'=>'retain_all') && retention_digest($doctor)===$before,'bounded admin inventory returns counts only and retains everything');
 intent_check($wpdb->update($table,array('base_revision'=>0),array('doctor_id'=>$doctor,'request_key'=>$key3))===1,'inject invalid intent metadata');
 intent_check(ci_retention_inventory($doctor)===array('status'=>'unknown'),'corrupt intent fails closed without returning partial counts');
 intent_check($wpdb->update($table,array('base_revision'=>3),array('doctor_id'=>$doctor,'request_key'=>$key3))===1,'repair synthetic metadata');
 $unavailable=$wpdb->prefix.'ci_retention_unavailable';
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($unavailable)))) { throw new RuntimeException('unavailable fixture name occupied'); }
 intent_check($wpdb->query("RENAME TABLE `$table` TO `$unavailable`")!==false,'simulate missing intent table');
 try { intent_check(ci_retention_inventory($doctor)===array('status'=>'unknown'),'missing table fails closed'); }
 finally { if ($wpdb->query("RENAME TABLE `$unavailable` TO `$table`")===false) { throw new RuntimeException('failed to restore fixture table name'); } }
} finally { if ($created) { $wpdb->query("DROP TABLE IF EXISTS `$backup`"); } }

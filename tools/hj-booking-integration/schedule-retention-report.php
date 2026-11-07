<?php
/** Minimal, read-only inventory of synthetic intent states for CI only. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1' || !defined('DB_NAME') || DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
/** Counts are informational and never authorize deletion, retry, or recovery. */
function ci_retention_inventory($doctor) {
 global $wpdb,$table;
 if (!is_int($doctor) || !current_user_can('manage_options') || !hj_booking_can_configure($doctor)) { return array('status'=>'forbidden'); }
 // One bounded-result aggregate over a doctor prefix; never return keys or payloads.
 $valid="actor_id>0 AND base_revision BETWEEN 1 AND ".PHP_INT_MAX." AND request_key REGEXP '^[0-9a-f]{32}$' AND payload_hash REGEXP '^[0-9a-f]{64}$' AND created_utc <> '0000-00-00 00:00:00' AND ((state='prepared' AND active=1 AND result_revision=0) OR (state='cancelled' AND active IS NULL AND result_revision=0) OR (state='completed' AND active=1 AND result_revision BETWEEN 1 AND ".PHP_INT_MAX.") OR (state='acknowledged' AND active IS NULL AND result_revision BETWEEN 1 AND ".PHP_INT_MAX."))";
 $query=$wpdb->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN COALESCE(($valid),0)=0 THEN 1 ELSE 0 END),0) AS invalid, COALESCE(SUM(state='prepared'),0) AS prepared, COALESCE(SUM(state='cancelled'),0) AS cancelled, COALESCE(SUM(state='completed'),0) AS completed, COALESCE(SUM(state='acknowledged'),0) AS acknowledged FROM `$table` WHERE doctor_id=%d",$doctor);
 $previous=$wpdb->suppress_errors(true);
 try { $row=$wpdb->get_row($query,ARRAY_A); if ($wpdb->last_error || !is_array($row)) { return array('status'=>'unknown'); } }
 finally { $wpdb->suppress_errors($previous); }
 foreach(array('total','invalid','prepared','cancelled','completed','acknowledged') as $key) {
  $n=(string)($row[$key] ?? '');
  if (!preg_match('/^(0|[1-9][0-9]*)$/D',$n) || strlen($n)>strlen((string)PHP_INT_MAX) || (string)(int)$n!==$n) { return array('status'=>'unknown'); }
  $row[$key]=(int)$n;
 }
 if ($row['invalid']!==0 || $row['prepared']+$row['cancelled']+$row['completed']+$row['acknowledged']!==$row['total']) { return array('status'=>'unknown'); }
 unset($row['invalid']);
 return array('status'=>'ok','counts'=>$row,'policy'=>'retain_all');
}

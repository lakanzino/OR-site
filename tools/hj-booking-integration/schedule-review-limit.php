<?php
/** Experimental CI-only fixed-window limiter, not suitable for installation. */
if (!in_array(PHP_SAPI,array('cli','cli-server'),true) || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1' || !defined('DB_NAME') || DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
/** Commit the quota BEFORE comparing; failures are not refunded. */
function ci_review_take($doctor,$test_bucket=null) {
 if (!hj_booking_can_configure($doctor)) { return 'forbidden'; }
 if ($test_bucket!==null && (PHP_SAPI!=='cli' || !is_int($test_bucket) || $test_bucket<1)) { return 'unavailable'; }
 $actor=get_current_user_id(); $table=$GLOBALS['wpdb']->prefix.'ci_review_rate';
 $db=new HJ_Booking_Transaction_DB(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST); $db->suppress_errors(true); $begun=false; $committing=false;
 try {
  hj_booking_query($db,'SET SESSION innodb_lock_wait_timeout = 10');
  hj_booking_query($db,'START TRANSACTION'); $begun=true;
  if (!hj_booking_can_configure($doctor) || get_current_user_id()!==$actor) { return 'forbidden'; }
  $bucket=$test_bucket;
  // Explicit CI process configuration for deterministic browser tests, not client input.
  if ($bucket===null && PHP_SAPI==='cli-server' && getenv('HJ_REVIEW_FIXED_BUCKET')==='60000000') { $bucket=60000000; }
  if ($bucket===null) { $row=hj_booking_row($db,'SELECT FLOOR(UNIX_TIMESTAMP()/60) AS bucket'); $bucket=intent_history_number($row['bucket'] ?? null); }
  if (!is_int($bucket) || $bucket<1) { throw new RuntimeException('bad bucket'); }
  // Always lock doctor before actor, independent of request order.
  foreach (array(array('d',$doctor),array('a',$actor)) as $scope) {
   hj_booking_query($db,$db->prepare("INSERT INTO `$table` (kind,subject,bucket,used) VALUES (%s,%d,%d,0) ON DUPLICATE KEY UPDATE subject=subject",$scope[0],$scope[1],$bucket));
  }
  $d=hj_booking_row($db,$db->prepare("SELECT used FROM `$table` WHERE kind='d' AND subject=%d AND bucket=%d FOR UPDATE",$doctor,$bucket));
  $a=hj_booking_row($db,$db->prepare("SELECT used FROM `$table` WHERE kind='a' AND subject=%d AND bucket=%d FOR UPDATE",$actor,$bucket));
  $dv=(string)($d['used'] ?? ''); $av=(string)($a['used'] ?? '');
  if (!preg_match('/^(0|[1-9][0-9]*)$/D',$dv) || !preg_match('/^(0|[1-9][0-9]*)$/D',$av) || (int)$dv>12 || (int)$av>6) { throw new RuntimeException('invalid counter'); }
  $result='limited';
  if ((int)$dv<12 && (int)$av<6) {
   foreach (array(array('d',$doctor),array('a',$actor)) as $scope) {
    if (hj_booking_query($db,$db->prepare("UPDATE `$table` SET used=used+1 WHERE kind=%s AND subject=%d AND bucket=%d",$scope[0],$scope[1],$bucket))!==1) { throw new RuntimeException('counter update'); }
   }
   $result='allowed';
  }
  $committing=true; if (false===$db->query('COMMIT')) { return 'unavailable'; } $begun=false; return $result;
 } catch (Throwable $e) { return 'unavailable'; }
 finally { if ($begun && !$committing) { $db->query('ROLLBACK'); } $db->close(); }
}

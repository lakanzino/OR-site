<?php
/** Disposable synthetic sentinel only; never run against any real installation. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
require_once getenv('GITHUB_WORKSPACE') . '/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors();
$admin=get_user_by('login','fixture_admin'); if (!$admin || !hj_booking_enabled()) { exit(1); } wp_set_current_user($admin->ID);
if (!hj_booking_ready()) { exit(1); }
global $wpdb;
$d=hj_booking_table('doctors'); $s=hj_booking_table('schedules'); $a=hj_booking_table('appointments'); $p=hj_booking_table('details'); $m=hj_booking_table('secretaries');
if (($argv[1] ?? '') === 'seed') {
 $json='[]';
 if ($wpdb->insert($d,array('doctor_id'=>999001,'duration_minutes'=>45,'buffer_minutes'=>10,'enabled'=>1)) !== 1 ||
     $wpdb->insert($s,array('doctor_id'=>999001,'revision'=>9,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json))) !== 1 ||
     $wpdb->insert($a,array('doctor_id'=>999001,'patient_id'=>999002,'request_key'=>str_repeat('c',32),'request_hash'=>str_repeat('d',64),'starts_at'=>'2030-01-01 09:00:00','ends_at'=>'2030-01-01 09:30:00','blocked_until'=>'2030-01-01 09:35:00','schedule_revision'=>9,'status'=>'pending')) !== 1 ) { exit(1); }
 $id=(int)$wpdb->insert_id;
 if ($wpdb->insert($p,array('appointment_id'=>$id,'phone'=>'09123456789','national_code'=>'1234567891')) !== 1 || $wpdb->insert($m,array('user_id'=>999003,'doctor_id'=>999001)) !== 1) { exit(1); }
}
$out=array();
foreach (array($d,$s,$a,$p,$m) as $table) {
 $ddl=$wpdb->get_row("SHOW CREATE TABLE `$table`",ARRAY_N);
 $rows=$wpdb->get_results("SELECT * FROM `$table`",ARRAY_A);
 if (!$ddl || $wpdb->last_error || !is_array($rows)) { exit(1); }
 $out[]=array($ddl[1],$rows);
}
echo hash('sha256',wp_json_encode($out));

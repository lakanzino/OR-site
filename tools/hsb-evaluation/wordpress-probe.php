<?php
/** HSB candidate evaluation. Disposable GitHub Actions WordPress/MariaDB only. */
if (PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HSB_DISPOSABLE_FIXTURE') !== '1') { exit(1); }
require '/wordpress/wp-load.php';
global $wpdb;
if (DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $wpdb->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid') { exit(2); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result = activate_plugin('hamyar-surgery-booking/hamyar-surgery-booking.php');
if (is_wp_error($result)) { throw new RuntimeException('Plugin activation failed: ' . $result->get_error_code()); }
foreach (array('schedules','overrides','appointments') as $name) {
    $table=HSB_Activator::table($name);
    $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($table)));
    if (!$status || $status->Engine !== 'InnoDB') { throw new RuntimeException('Table absent or not InnoDB: ' . $name); }
}
echo "PASS: activated candidate and found three InnoDB tables.\n";
register_post_type('doctor', array('public'=>true));
$doctor=wp_insert_post(array('post_type'=>'doctor','post_title'=>'Fixture Doctor','post_status'=>'publish'),true);
$patient_a=wp_insert_user(array('user_login'=>'fixture_patient_a','user_pass'=>'fixture-only','user_email'=>'a@example.invalid'));
$patient_b=wp_insert_user(array('user_login'=>'fixture_patient_b','user_pass'=>'fixture-only','user_email'=>'b@example.invalid'));
foreach (array($doctor,$patient_a,$patient_b) as $id) { if (is_wp_error($id) || !$id) { throw new RuntimeException('Failed to create synthetic user or doctor'); } }
$settings=HSB_Settings::defaults(); $settings['doctor_provider']='cpt'; $settings['doctor_post_type']='doctor'; HSB_Settings::update($settings);
$date=wp_date('Y-m-d',current_datetime()->modify('+1 day')->getTimestamp());
$weekday=HSB_Availability::weekday_of($date);
$table=HSB_Activator::table('schedules');
$ok=$wpdb->insert($table,array('doctor_id'=>$doctor,'weekday'=>$weekday,'start_time'=>'09:00:00','end_time'=>'12:00:00','slot_length'=>30,'capacity'=>4,'is_active'=>1,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
if (!$ok) { throw new RuntimeException('Cannot seed schedule: '.$wpdb->last_error); }
$body=array('doctor_id'=>$doctor,'date'=>$date,'time'=>'10:00','patient_name'=>'Synthetic Patient Alpha','patient_mobile'=>'09120000000','patient_national_id'=>'','service_type'=>'visit','description'=>'');
rest_get_server();
function request_hsb($user,$key,$body) {
    wp_set_current_user($user);
    $r=new WP_REST_Request('POST','/hsb/v1/appointments');
    $r->set_header('Content-Type','application/json');
    $r->set_header('X-HSB-Idempotency-Key',$key);
    $r->set_body(wp_json_encode($body));
    $res=rest_do_request($r);
    return array($res->get_status(),$res->get_data());
}
$key='abcdef12-3456-4789-abcd-abcdef123456';
list($status,$first)=request_hsb($patient_a,$key,$body);
if ($status !== 200 || empty($first['ok']) || empty($first['appointment']['id'])) { throw new RuntimeException('First synthetic booking failed: '.wp_json_encode(array($status,$first['error']??null))); }
echo "PASS: synthetic authenticated patient booked through WordPress REST dispatch.\n";
list($status,$same)=request_hsb($patient_a,$key,$body);
if ($status !== 200 || empty($same['duplicate']) || ($same['appointment']['id']??null)!==$first['appointment']['id']) { throw new RuntimeException('Same-user replay did not return original booking.'); }
echo "PASS: same key replayed one request for the same user.\n";
$changed=$body; $changed['patient_name']='Synthetic Patient Changed';
list($status,$other)=request_hsb($patient_b,$key,$changed);
if ($status===200 && !empty($other['ok']) && ($other['appointment']['patient_name']??'')==='Synthetic Patient Alpha') {
    echo "CONFIRMED DEFECT: second user with the same key receives first patient's synthetic name and appointment summary.\n";
} else { throw new RuntimeException('Expected cross-user counterexample not reproduced; inspect response.'); }
list($status,$changed_same)=request_hsb($patient_a,$key,$changed);
if ($status===200 && !empty($changed_same['duplicate'])) { echo "CONFIRMED DEFECT: same key with changed payload is accepted as a replay.\n"; }
else { throw new RuntimeException('Expected changed-payload counterexample not reproduced.'); }
$fallback='hsb-1700000000000-abcdef1234567890';
if (HSB_Validator::idempotency_key($fallback)!=='') { throw new RuntimeException('Expected JS fallback rejection not reproduced.'); }
list($s1,$r1)=request_hsb($patient_a,$fallback,$body);
list($s2,$r2)=request_hsb($patient_a,$fallback,$body);
if ($s1===200 && $s2===200 && !empty($r1['ok']) && !empty($r2['ok']) && $r1['appointment']['id']!==$r2['appointment']['id']) {
    echo "CONFIRMED DEFECT: repeated fallback-style key creates two appointments when capacity permits.\n";
} else { throw new RuntimeException('Expected invalid-key duplicate counterexample not reproduced: '.wp_json_encode(array($s1,$s2,$r1['error']??null,$r2['error']??null))); }
$appt_table=HSB_Activator::table('appointments');
$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM $appt_table");
if ($count!==3) { throw new RuntimeException('Unexpected synthetic row count: '.$count); }
echo "DONE: 3 synthetic appointments; defects reproduced in-process, not HTTP or browser. Candidate NO-GO.\n";

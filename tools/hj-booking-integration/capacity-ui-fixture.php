<?php
/** Synthetic WordPress setup/cleanup only. */
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { exit(1); }
require_once WP_PLUGIN_DIR.'/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
$meta=getenv('RUNNER_TEMP').'/hj-capacity-ui-meta.json'; $mode=$argv[1] ?? '';
$admin=get_user_by('login','fixture_admin'); $owner=get_user_by('login','synthetic_doctor_a'); $other=get_user_by('login','synthetic_doctor_b'); $patient=get_user_by('login','synthetic_race_0'); $patient2=get_user_by('login','synthetic_race_1'); $secretary=get_user_by('login','synthetic_secretary');
if (!$admin || !$owner || !$other || !$patient || !$patient2 || !$secretary) { throw new RuntimeException('synthetic users missing'); }
if ($mode==='setup') {
 wp_set_current_user($admin->ID); update_option('timezone_string','Asia/Tehran');
 if(!hj_booking_install_schema()) { throw new RuntimeException('v1 schema setup failed'); }
 $old_meta=get_user_meta($owner->ID,'hj_doctor_post_id',true); $old_dashboard=get_option('hj_doctors_dashboard_page',0); $old_account=get_option('hj_member_account_page',0);
 $old_theme=get_option('stylesheet'); $old_template=get_option('template'); switch_theme('twentytwentyfour');
 $doc=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic capacity UI doctor','post_author'=>$owner->ID),true);
 $page=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic capacity settings','post_content'=>'[hj_capacity_settings]'),true);
 $dashboard=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic dashboard','post_content'=>'[hj_doctors_dashboard]'),true);
 $account=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic member account','post_content'=>'[hj_member_account]'),true);
 $combined=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Synthetic combined account','post_content'=>'[hj_member_account] [hj_patient_bookings]'),true);
 if(is_wp_error($doc) || is_wp_error($page) || is_wp_error($dashboard) || is_wp_error($account) || is_wp_error($combined) || !$doc || !$page || !$dashboard || !$account || !$combined) { throw new RuntimeException('fixture content failed'); }
 update_option('hj_doctors_dashboard_page',(int)$dashboard,false); update_option('hj_member_account_page',(int)$account,false);
 update_user_meta($owner->ID,'hj_doctor_post_id',$doc);
 update_post_meta($doc,'working_hours',array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00:00','end_time'=>'11:00:00')));
 $legacy=wp_insert_post(array('post_type'=>'doctor','post_status'=>'publish','post_title'=>'Synthetic legacy office','post_author'=>$owner->ID),true);
 if (is_wp_error($legacy) || !$legacy || hj_booking_configure((int)$legacy,30,0,true)['status']!=='configured' || hj_booking_save_schedule((int)$legacy,array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'12:00:30','end_time'=>'13:00:00')),0)['status']!=='saved') { throw new RuntimeException('legacy office setup failed'); }
 update_post_meta($legacy,'working_hours',array(array('day_of_week'=>'monday','is_closed'=>0,'start_time'=>'09:00:00','end_time'=>'11:00:00')));
 $old_secretary_roles=$secretary->roles; if (!get_role('hj_booking_secretary')) { throw new RuntimeException('secretary role missing'); }
 $secretary->set_role('hj_booking_secretary'); wp_set_password('disposable-test-password',$secretary->ID); wp_set_password('disposable-test-password',$patient2->ID);
 wp_set_password('disposable-test-password',$admin->ID); wp_set_password('disposable-test-password',$owner->ID); wp_set_password('disposable-test-password',$other->ID); wp_set_password('disposable-test-password',$patient->ID);
 $date=(new DateTimeImmutable('now',wp_timezone()))->modify('+2 days')->format('Y-m-d');
 $weekday=strtolower((new DateTimeImmutable($date,new DateTimeZone('UTC')))->format('l'));
 global $wpdb; $activation_table=hj_booking_table('doctors');
 if ($wpdb->query("ALTER TABLE `$activation_table` ADD COLUMN activation_generation bigint unsigned NOT NULL DEFAULT 0")===false) { throw new RuntimeException('synthetic activation generation setup failed'); }
 update_option('hj_booking_schema_version',2,false); hj_booking_schema_clear_option_cache();
 if (!hj_booking_capacity_v2_ready()) { throw new RuntimeException('fixture v2 gate unavailable'); }
 global $wpdb; $extra=hj_booking_patient_details_v2_table();
 if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($extra))) || $wpdb->query("CREATE TABLE `$extra` (appointment_id bigint unsigned NOT NULL PRIMARY KEY,first_name varchar(80) NOT NULL,last_name varchar(80) NOT NULL,service varchar(20) NOT NULL,note varchar(500) NOT NULL,tracking_code char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,UNIQUE KEY tracking_code (tracking_code)) ENGINE=InnoDB ".$wpdb->get_charset_collate())===false) { throw new RuntimeException('experimental patient table setup failed'); }
 update_option('active_plugins',array('hamyar-doctors/hamyar-doctors.php'));
 file_put_contents($meta,wp_json_encode(array('doctor'=>(int)$doc,'page'=>(int)$page,'date'=>$date,'weekday'=>$weekday,'old_meta'=>$old_meta,'dashboard'=>(int)$dashboard,'old_dashboard'=>$old_dashboard,'legacy'=>(int)$legacy,'secretary'=>(int)$secretary->ID,'old_secretary_roles'=>$old_secretary_roles,'account'=>(int)$account,'combined'=>(int)$combined,'old_account'=>$old_account,'old_theme'=>$old_theme,'old_template'=>$old_template)));
 echo "PASS: synthetic capacity UI setup\n"; exit(0);
}
if ($mode==='cleanup') {
 $data=json_decode(file_get_contents($meta),true); if(!is_array($data)) { exit(1); }
 global $wpdb; $doc=(int)$data['doctor']; $a=hj_booking_table('appointments'); $p=hj_booking_table('details');
 $extra=hj_booking_patient_details_v2_table();
 $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM `$a` WHERE doctor_id=%d",$doc));
 foreach($ids as $id) { $wpdb->delete($extra,array('appointment_id'=>(int)$id)); $wpdb->delete($p,array('appointment_id'=>(int)$id)); }
 $wpdb->query("DROP TABLE `$extra`");
 $wpdb->delete(hj_booking_table('secretaries'),array('doctor_id'=>$doc));
 $secretary->set_role(''); foreach ($data['old_secretary_roles'] as $role) { $secretary->add_role($role); }
 $wpdb->delete($a,array('doctor_id'=>$doc)); $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$doc)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$doc));
 $legacy=(int)$data['legacy']; $wpdb->delete(hj_booking_table('schedules'),array('doctor_id'=>$legacy)); $wpdb->delete(hj_booking_table('doctors'),array('doctor_id'=>$legacy)); wp_delete_post($legacy,true);
 wp_delete_post($doc,true); wp_delete_post((int)$data['page'],true); wp_delete_post((int)$data['dashboard'],true); update_option('hj_doctors_dashboard_page',$data['old_dashboard'],false);
 wp_delete_post((int)$data['account'],true); wp_delete_post((int)$data['combined'],true); update_option('hj_member_account_page',$data['old_account'],false); update_option('stylesheet',$data['old_theme']); update_option('template',$data['old_template']);
 if ($data['old_meta']==='') { delete_user_meta($owner->ID,'hj_doctor_post_id'); } else { update_user_meta($owner->ID,'hj_doctor_post_id',$data['old_meta']); }
 update_option('hj_booking_schema_version',1,false); hj_booking_schema_clear_option_cache();
 $activation_table=hj_booking_table('doctors'); if ($wpdb->query("ALTER TABLE `$activation_table` DROP COLUMN activation_generation")===false) { throw new RuntimeException('synthetic activation generation cleanup failed'); } update_option('active_plugins',array()); unlink($meta); exit(0);
}
exit(1);

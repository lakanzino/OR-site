<?php
if (PHP_SAPI!=='cli' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1') { exit(1); }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || WP_HOME!=='http://example.invalid' || $GLOBALS['wpdb']->prefix!=='hjt_' || HJ_DOCTORS_VERSION!=='0.3.50') { exit(1); }
$uid=get_user_by('login','surgeon_fixture')->ID; $other=get_user_by('login','normal_fixture')->ID;
wp_set_password('private-fixture-password',$uid); wp_set_password('private-fixture-password',$other);
$admin=get_user_by('login','fixture_admin'); wp_set_password('private-fixture-password',$admin->ID);
$pid=wp_insert_post(array('post_type'=>'doctor','post_status'=>'draft','post_title'=>'Synthetic private test','post_author'=>$uid),true);
if (is_wp_error($pid) || !$pid) { throw new RuntimeException('synthetic doc profile'); }
update_user_meta($uid,'hj_doctor_post_id',$pid);
$legacy=hj_sec_private_dir(); $dir=$legacy.'/p'.$pid;
if (!wp_mkdir_p($dir)) { throw new RuntimeException('legacy dir'); }
$file='p'.$pid.'/legacy-synthetic.pdf';
if (file_put_contents($legacy.'/'.$file,"%PDF-1.4\nsynthetic legacy only\n%%EOF\n")===false) { throw new RuntimeException('legacy file'); }
$doc=array('id'=>'abc123','name'=>'legacy.pdf','file'=>$file,'mime'=>'application/pdf','size'=>39,'time'=>time());
if (!update_post_meta($pid,'hj_private_docs',array($doc))) { throw new RuntimeException('legacy metadata'); }
echo json_encode(array('pid'=>$pid,'legacy'=>$file)),"\n";

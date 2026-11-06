<?php
/** Destructive fixture ONLY in the disposable CI database. */
if ( PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') !== '1' ) { exit(1); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit(1); }
require_once getenv('GITHUB_WORKSPACE') . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
$admin = get_user_by('login','fixture_admin'); if (!$admin) { throw new RuntimeException('Fixture admin missing'); }
wp_set_current_user($admin->ID);
global $wpdb;
function expect_migration($v,$name) { if (!$v) { throw new RuntimeException($name); } echo "PASS: $name\n"; }
function migration_reset_fixture() {
 global $wpdb;
 foreach (array('details','appointments','schedules','secretaries','doctors') as $name) { $wpdb->query('DROP TABLE IF EXISTS `' . hj_booking_table($name) . '`'); }
 delete_option('hj_booking_schema_version'); delete_option('hj_booking_schema_verified');
}
function migration_table_exists($table) { global $wpdb; return (bool) $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s',$table)); }
function migration_snapshot() {
 global $wpdb; $out=array();
 foreach (array('doctors','schedules','appointments','details','secretaries') as $name) {
  $table=hj_booking_table($name); $ddl=$wpdb->get_row("SHOW CREATE TABLE `$table`",ARRAY_N);
  $out[$name]=array('ddl'=>$ddl[1], 'rows'=>$wpdb->get_results("SELECT * FROM `$table`",ARRAY_A));
 }
 return $out;
}
if (($argv[1] ?? '') === 'off') {
 expect_migration(!hj_booking_enabled(), 'booking feature flag defaults off');
 expect_migration(!hj_booking_install_schema(), 'disabled feature refuses explicit schema installation');
 HJ_Doctors::activate(); do_action('admin_init');
 foreach (array('doctors','schedules','appointments','details','secretaries') as $name) { expect_migration(!migration_table_exists(hj_booking_table($name)), 'activation and upgrade with flag off do not create ' . $name); }
 exit(0);
}
if (!hj_booking_enabled()) { throw new RuntimeException('Test needs feature flag'); }
try {
 migration_reset_fixture();
 HJ_Doctors::activate();
 expect_migration(hj_booking_ready(), 'explicit feature flag and admin activation initialize schema');
 $d=hj_booking_table('doctors'); $s=hj_booking_table('schedules'); $a=hj_booking_table('appointments'); $p=hj_booking_table('details'); $m=hj_booking_table('secretaries');
 $json='[]';
 expect_migration($wpdb->insert($d,array('doctor_id'=>999901,'duration_minutes'=>45,'buffer_minutes'=>10,'enabled'=>1)) === 1, 'seed synthetic doctor mutex row');
 expect_migration($wpdb->insert($s,array('doctor_id'=>999901,'revision'=>7,'rows_json'=>$json,'rows_hash'=>hash('sha256',$json))) === 1, 'seed synthetic schedule revision');
 expect_migration($wpdb->insert($a,array('doctor_id'=>999901,'patient_id'=>999902,'request_key'=>str_repeat('a',32),'request_hash'=>str_repeat('b',64),'starts_at'=>'2030-01-01 09:00:00','ends_at'=>'2030-01-01 09:30:00','blocked_until'=>'2030-01-01 09:35:00','schedule_revision'=>7,'status'=>'pending')) === 1, 'seed synthetic appointment');
 $id=(int)$wpdb->insert_id;
 expect_migration($wpdb->insert($p,array('appointment_id'=>$id,'phone'=>'09123456789','national_code'=>'1234567891')) === 1, 'seed synthetic private details');
 expect_migration($wpdb->insert($m,array('user_id'=>999903,'doctor_id'=>999901)) === 1, 'seed synthetic secretary assignment');
 $snapshot=migration_snapshot();
 expect_migration(hj_booking_install_schema() && hj_booking_install_schema(), 'schema migration is repeatable');
 expect_migration(migration_snapshot() === $snapshot, 'repeat keeps schema, indexes and all five tables byte-for-byte logically identical');
 delete_option('hj_booking_schema_version'); delete_option('hj_booking_schema_verified');
 do_action('admin_init');
 expect_migration(hj_booking_ready() && migration_snapshot() === $snapshot, 'upgrade hook recognizes complete old schema and preserves data');
 $subscriber=get_user_by('login','synthetic_patient'); wp_set_current_user($subscriber->ID);
 expect_migration(!hj_booking_install_schema() && migration_snapshot() === $snapshot, 'nonadministrator cannot migrate');
 wp_set_current_user($admin->ID);
 $wpdb->query("ALTER TABLE `$s` ADD COLUMN unexpected_column int NOT NULL DEFAULT 0");
 $changed=migration_snapshot();
 expect_migration(!hj_booking_install_schema() && !hj_booking_ready() && migration_snapshot() === $changed, 'incompatible table fails closed without mutation');
 $wpdb->query("ALTER TABLE `$s` DROP COLUMN unexpected_column");
 expect_migration(hj_booking_install_schema() && migration_snapshot() === $snapshot, 'after fixture repairs schema, migration validates again');
 $wpdb->query("ALTER TABLE `$a` DROP INDEX request_key");
 $changed=migration_snapshot();
 expect_migration(!hj_booking_install_schema() && !hj_booking_ready() && migration_snapshot() === $changed, 'missing idempotency uniqueness fails closed without alteration');
 $wpdb->query("ALTER TABLE `$a` ADD UNIQUE KEY request_key (request_key)");
 expect_migration(hj_booking_install_schema() && migration_snapshot() === $snapshot, 'fixture restores required idempotency index');
 $wpdb->query("ALTER TABLE `$a` ADD UNIQUE KEY unexpected_doctor (doctor_id)");
 $changed=migration_snapshot();
 expect_migration(!hj_booking_install_schema() && !hj_booking_ready() && migration_snapshot() === $changed, 'unexpected restrictive unique key fails closed');
 $wpdb->query("ALTER TABLE `$a` DROP INDEX unexpected_doctor");
 expect_migration(hj_booking_install_schema() && migration_snapshot() === $snapshot, 'fixture removes unsupported unique index');
 $wpdb->query("ALTER TABLE `$s` ENGINE=MyISAM");
 $changed=migration_snapshot();
 expect_migration(!hj_booking_install_schema() && !hj_booking_ready() && migration_snapshot() === $changed, 'non-InnoDB table fails closed without conversion');
 $wpdb->query("ALTER TABLE `$s` ENGINE=InnoDB");
 expect_migration(hj_booking_install_schema() && migration_snapshot() === $snapshot, 'fixture restores transactional engine');
 update_option('hj_booking_schema_version',2,false);
 expect_migration(!hj_booking_install_schema() && (int)get_option('hj_booking_schema_version') === 2 && !hj_booking_ready() && migration_snapshot() === $snapshot, 'newer schema is not downgraded');
 update_option('hj_booking_schema_version',1,false);
 expect_migration(hj_booking_install_schema(), 'schema recovers after fixture restores version');
 $wpdb->query("DROP TABLE `$m`");
 expect_migration(!hj_booking_install_schema() && !migration_table_exists($m) && !hj_booking_ready(), 'missing existing-version table fails closed without silently replacing data');
 migration_reset_fixture();
 $defs=hj_booking_schema_definitions($wpdb->get_charset_collate());
 expect_migration($wpdb->query($defs[$d]) !== false, 'seed empty partial fresh schema');
 expect_migration(hj_booking_install_schema() && hj_booking_ready(), 'empty interrupted fresh installation resumes safely');
 migration_reset_fixture();
 expect_migration($wpdb->query($defs[$d]) !== false && $wpdb->insert($d,array('doctor_id'=>999901)) === 1, 'seed nonempty partial schema');
 expect_migration(!hj_booking_install_schema() && !migration_table_exists($s) && (int)$wpdb->get_var("SELECT COUNT(*) FROM `$d`") === 1, 'nonempty partial schema fails closed without changes');
} finally { migration_reset_fixture(); }

<?php
/** DESTRUCTIVE tests for fresh disposable GitHub Actions MariaDB only. NEVER use a site/backup. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || ! HJ_DISPOSABLE_TEST || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' ) { exit( 'Disposable CI only.' ); }
global $wpdb;
if ( $wpdb->prefix !== 'hjt_' ) { exit( 'Unexpected prefix.' ); }
require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-vote-lab/hj-vote-lab.php';
function hj_lab_assert( $value, $message ) { if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); } echo 'PASS: ' . $message . "\n"; }
function hj_lab_reset_bridge() { foreach ( array( 'schema_cache' => null, 'stats_mem' => array() ) as $name => $value ) { $p = new ReflectionProperty( 'HJ_Doctors_Rating_Bridge', $name ); $p->setAccessible( true ); $p->setValue( null, $value ); } }
$table = $wpdb->prefix . 'hj_doctor_votes';
$all = $wpdb->get_col( 'SHOW TABLES' );
if ( ! is_array( $all ) || $wpdb->last_error !== '' || in_array( $table, $all, true ) ) { throw new RuntimeException( 'Refusing preexisting table.' ); }
hj_lab_assert( false !== $wpdb->query( "CREATE TABLE `{$table}` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, post_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL DEFAULT 0, rating TINYINT NOT NULL, status VARCHAR(20) NOT NULL, comment TEXT NOT NULL, ip_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB" ), 'create synthetic vote table' );
$admin = get_user_by( 'login', 'fixture_admin' ); hj_lab_assert( (bool) $admin, 'admin exists' ); wp_set_current_user( $admin->ID );
$doctor = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic physician' ), true );
$other = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Unmarked physician' ), true );
hj_lab_assert( ! is_wp_error( $doctor ) && ! is_wp_error( $other ) && $doctor && $other, 'create synthetic profiles' );
update_post_meta( $doctor, '_hj_demo_doctor', 'fixture-demo' );
$subscriber = wp_create_user( 'lab-fixture-subscriber', 'disposable-password', 'lab@example.invalid' ); hj_lab_assert( ! is_wp_error( $subscriber ), 'create synthetic subscriber' );
$batch = wp_generate_uuid4();
$rows = static function () use ( $wpdb, $table ) { return $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A ); };
wp_set_current_user( 0 ); hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '5 | demo', $batch ) ), 'anonymous denied' );
wp_set_current_user( $subscriber ); hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '5 | demo', $batch ) ) && is_wp_error( HJ_Vote_Lab::report( $doctor ) ), 'subscriber denied' );
wp_set_current_user( $admin->ID );
class HJ_Lab_Test_Die extends Exception { public $status; public function __construct( $status ) { parent::__construct(); $this->status = $status; } }
$die_filter = static function () { return static function ( $message, $title = '', $args = array() ) { throw new HJ_Lab_Test_Die( $args['response'] ?? 500 ); }; };
add_filter( 'wp_die_handler', $die_filter, 99 );
$_SERVER['REQUEST_METHOD'] = 'GET'; $status = null;
try { HJ_Vote_Lab::handle_import(); } catch ( HJ_Lab_Test_Die $e ) { $status = $e->status; }
hj_lab_assert( $status === 405, 'GET import endpoint denied' );
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array(); $_REQUEST = array(); $status = null;
try { HJ_Vote_Lab::handle_import(); } catch ( HJ_Lab_Test_Die $e ) { $status = $e->status; }
hj_lab_assert( $status === 403, 'POST without nonce denied' );
wp_set_current_user( $subscriber ); $_SERVER['REQUEST_METHOD'] = 'GET'; $status = null;
try { HJ_Vote_Lab::handle_report(); } catch ( HJ_Lab_Test_Die $e ) { $status = $e->status; }
hj_lab_assert( $status === 403, 'report endpoint denied to subscriber' );
wp_set_current_user( $admin->ID ); remove_filter( 'wp_die_handler', $die_filter, 99 );
hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $other, 'approved', '5 | demo', $batch ) ), 'unmarked profile denied' );
hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '6 | demo', $batch ) ) && is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '5 | ', $batch ) ) && is_wp_error( HJ_Vote_Lab::import( $doctor, 'invalid', '5 | demo', $batch ) ) && is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '5 | demo', 'invalid' ) ), 'invalid input denied' );
hj_lab_assert( count( $rows() ) === 0, 'denied requests did not insert' );
$r = HJ_Vote_Lab::import( $doctor, 'pending', "5 | Synthetic private text one\n4 | Synthetic private text two", $batch );
hj_lab_assert( $r === 2, 'insert two pending demo votes' );
$before = $rows(); hj_lab_assert( count( $before ) === 2 && $before[0]['status'] === 'pending' && strpos( $before[0]['comment'], '[نظر آزمایشی HJ-' ) === 0 && $before[0]['ip_hash'] !== $before[1]['ip_hash'] && (int) $before[0]['user_id'] === 0, 'rows labelled, no actual voter, distinct synthetic hashes' );
hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $doctor, 'pending', '5 | duplicate', $batch ) ) && $before === $rows(), 'replayed batch rejected' );
hj_lab_reset_bridge(); $report = HJ_Vote_Lab::report( $doctor ); $json = wp_json_encode( $report );
hj_lab_assert( ! is_wp_error( $report ) && $report['schema']['primary_key_columns'] === array( 'id' ) && $report['schema']['mapping']['id'] === 'id' && $report['vote_table']['all_rows_count'] === 2 && $report['vote_table']['lab_marker_count'] === 2 && $report['vote_table']['status_groups']['pending'] === 2 && $report['bridge']['approved'] === 0 && $report['bridge']['pending'] === 2 && $report['public_review_reader']['returned_comments_count'] === 0, 'pending aggregate report is consistent' );
hj_lab_assert( strpos( $json, 'Synthetic private text' ) === false && strpos( $json, $before[0]['ip_hash'] ) === false && strpos( $json, $table ) === false && strpos( $json, 'Synthetic physician' ) === false, 'report omits comment, IP hash, full table and name' );
$r = HJ_Vote_Lab::import( $doctor, 'approved', "5 | Synthetic approved one\n4 | Synthetic approved two", wp_generate_uuid4() ); hj_lab_assert( $r === 2, 'insert approved demo votes' );
hj_lab_reset_bridge(); $report = HJ_Vote_Lab::report( $doctor );
hj_lab_assert( $report['vote_table']['all_rows_count'] === 4 && $report['vote_table']['status_groups']['approved'] === 2 && $report['bridge']['approved'] === 2 && $report['bridge']['pending'] === 2 && $report['public_review_reader']['returned_comments_count'] === 2, 'approved reports and public reader' );
$csv = HJ_Vote_Lab::csv_to_lines( "\xEF\xBB\xBFrating,comment\n۵,\"Synthetic, quoted\"\n2,another\n" );
hj_lab_assert( is_string( $csv ) && HJ_Vote_Lab::import( $doctor, 'approved', $csv, wp_generate_uuid4() ) === 2, 'UTF-8 CSV and Persian digit imported' );
hj_lab_assert( is_wp_error( HJ_Vote_Lab::csv_to_lines( "rating,comment\n1,good,extra" ) ) && is_wp_error( HJ_Vote_Lab::csv_to_lines( str_repeat( 'x', 12001 ) ) ), 'malformed or oversized CSV denied' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD CONSTRAINT fixture_rating_min CHECK (rating >= 2)" ), 'create synthetic failing constraint' );
$before = $rows();
$r = HJ_Vote_Lab::import( $doctor, 'approved', "5 | first row\n1 | forced failure", wp_generate_uuid4() );
hj_lab_assert( is_wp_error( $r ) && $before === $rows(), 'failure on second insert rolls back entire batch' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN status ENUM('pending','approved') NOT NULL" ), 'change fixture status to enum' );
hj_lab_reset_bridge(); hj_lab_assert( HJ_Vote_Lab::import( $doctor, 'approved', '3 | enum sample', wp_generate_uuid4() ) === 1, 'approved enum accepted' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN status VARCHAR(20) NOT NULL" ) && false !== $wpdb->query( "UPDATE `{$table}` SET status = CASE WHEN status = 'approved' THEN '1' ELSE '0' END" ) && false !== $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN status TINYINT NOT NULL" ), 'switch fixture status to numeric' );
hj_lab_reset_bridge(); hj_lab_assert( HJ_Vote_Lab::import( $doctor, 'pending', '3 | numeric pending', wp_generate_uuid4() ) === 1 && HJ_Vote_Lab::import( $doctor, 'approved', '4 | numeric approved', wp_generate_uuid4() ) === 1, 'numeric pending and approved accepted' );
hj_lab_reset_bridge(); $numeric_report = HJ_Vote_Lab::report( $doctor ); hj_lab_assert( $numeric_report['vote_table']['status_groups']['pending'] === 3 && $numeric_report['vote_table']['status_groups']['approved'] === 6 && $numeric_report['bridge']['approved'] === 6, 'numeric statuses grouped correctly' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` ENGINE=MyISAM" ), 'switch synthetic engine to nontransactional' );
$before = $rows(); hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '3 | reject nontransactional', wp_generate_uuid4() ) ) && $before === $rows(), 'non-InnoDB refused without writes' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` ENGINE=InnoDB" ), 'restore fixture engine' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN unknown_required INT NOT NULL" ), 'add unsupported required column' );
hj_lab_reset_bridge(); $before = $rows(); hj_lab_assert( is_wp_error( HJ_Vote_Lab::import( $doctor, 'approved', '3 | missing column', wp_generate_uuid4() ) ) && $before === $rows(), 'unknown required column refuses insert' );
hj_lab_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN comment" ), 'remove synthetic comment column' );
hj_lab_reset_bridge(); $fallback = HJ_Vote_Lab::report( $doctor );
hj_lab_assert( is_array( $fallback ) && $fallback['import_compatible'] === false && $fallback['vote_table']['all_rows_count'] === count( $before ) && is_array( $fallback['schema']['columns'] ), 'unsupported import still offers useful report' );
echo "DONE: disposable vote lab MariaDB fixture.\n";

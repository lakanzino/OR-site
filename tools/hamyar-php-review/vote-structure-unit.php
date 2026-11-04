<?php
/** Metadata-only stubs. Never load this file in WordPress or on a server. */
if ( ! defined( 'HJ_ISOLATED_VOTE_TEST' ) || true !== HJ_ISOLATED_VOTE_TEST || defined( 'ABSPATH' ) ) { exit( 'Isolated test only.' ); }
define( 'ABSPATH', '/isolated/' );
define( 'ARRAY_A', 'ARRAY_A' );
function add_action( ...$args ) {}
class HJ_Test_Metadata_DB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $suppressed = false;
    public $rows = array();
    public $throw = false;
    public $queries = array();
    public function suppress_errors( $value ) { $prev = $this->suppressed; $this->suppressed = $value; return $prev; }
    public function get_results( $query, $output ) {
        $this->queries[] = $query;
        if ( $this->throw ) { throw new Exception( 'synthetic error' ); }
        return $this->rows;
    }
}
$wpdb = new HJ_Test_Metadata_DB();
require '/repo/plugins/hamyar-doctors/includes/admin-data-status.php';
function hj_metadata_check( $condition, $message ) { if ( ! $condition ) { echo 'FAIL: ' . $message . "\n"; exit( 1 ); } echo 'PASS: ' . $message . "\n"; }
function hj_test_column( $name, $key, $type ) { return array( 'Field' => $name, 'Key' => $key, 'Type' => $type ); }
$schema = array( 'table' => 'wp_hj_doctor_votes', 'cols' => array( 'id', 'status' ), 'map' => array( 'id' => 'id', 'status' => 'status' ) );
$wpdb->rows = array( hj_test_column( 'id', 'PRI', 'bigint unsigned' ), hj_test_column( 'status', '', "enum('pending','approved')" ) );
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'بله' && $d['status_type'] === 'متنی یا فهرستی' && $d['profile_table'] === 'نام جدول یکسان است', 'single primary key, enum, same table' );
hj_metadata_check( ! $wpdb->suppressed && count( $wpdb->queries ) === 1 && $wpdb->queries[0] === 'SHOW COLUMNS FROM `wp_hj_doctor_votes`', 'only validated metadata query; suppression restored' );
$wpdb->rows[1] = hj_test_column( 'status', 'PRI', 'tinyint(1)' );
$schema['table'] = 'wp_other_votes';
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'خیر یا کلید مرکب' && $d['status_type'] === 'عددی یا بولی' && $d['profile_table'] === 'نام جدول متفاوت است', 'composite primary key, numeric, other table' );
$wpdb->rows = array( hj_test_column( 'id', '', 'int' ), hj_test_column( 'status', '', 'json' ) );
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'خیر یا کلید مرکب' && $d['status_type'] === 'نوع دیگر', 'no primary key and other column type' );
$wpdb->rows = array( array( 'Field' => 'id', 'Type' => 'int' ) );
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'نامعلوم' && $d['status_type'] === 'نامعلوم', 'incomplete metadata remains unknown' );
$wpdb->throw = true; $wpdb->suppressed = true;
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'نامعلوم' && $wpdb->suppressed, 'exception becomes unknown and restores previous error mode' );
$wpdb->throw = false; $wpdb->last_error = 'synthetic database error'; $wpdb->rows = array( hj_test_column( 'id', 'PRI', 'int' ) );
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'نامعلوم', 'database error does not yield a positive result' );
$before = count( $wpdb->queries );
$schema['table'] = 'bad`table';
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['profile_table'] === 'نامعلوم' && count( $wpdb->queries ) === $before, 'invalid table rejected before querying' );
$wpdb->last_error = ''; $schema['table'] = 'wp_hj_doctor_votes'; $schema['map']['id'] = 'unknown'; $schema['map']['status'] = 'unknown';
$d = hj_doctors_vote_structure_details( $schema );
hj_metadata_check( $d['primary_id'] === 'نامعلوم' && $d['status_type'] === 'نامعلوم', 'invalid column mapping remains unknown' );
echo "DONE: vote metadata unit fixture.\n";

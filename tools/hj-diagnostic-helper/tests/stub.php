<?php
/** Isolated stub fixture only, never include in WordPress. */
if ( ! defined( 'HJ_HELPER_ISOLATED_TEST' ) || true !== HJ_HELPER_ISOLATED_TEST || defined( 'ABSPATH' ) ) { exit( 'Isolated test only.' ); }
define( 'ABSPATH', '/isolated/' ); define( 'ARRAY_A', 'ARRAY_A' );
function add_action( ...$args ) {}
function wp_check_invalid_utf8( $str ) { return $str; }
class HJ_Doctors_Rating_Bridge {
    public static $fixture = null;
    public static $throw = false;
    public static function schema() { if ( self::$throw ) { throw new Exception( 'secret details' ); } return self::$fixture; }
}
class HJ_Helper_Test_DB {
    public $prefix = 'wp_'; public $last_error = ''; public $suppressed = false; public $calls = array(); public $fail_index = false; public $throw = false;
    public function suppress_errors( $flag ) { $old = $this->suppressed; $this->suppressed = $flag; return $old; }
    public function get_results( $query, $output ) {
        $this->calls[] = $query;
        if ( $this->throw ) { throw new Exception( 'secret database details' ); }
        if ( str_starts_with( $query, 'SHOW COLUMNS' ) ) { $this->last_error = ''; return array(
            array( 'Field' => 'id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => 'PRIVATE_DEFAULT' ),
            array( 'Field' => 'state', 'Type' => "enum('pending','PRIVATE_LITERAL')", 'Null' => 'YES', 'Key' => '', 'Default' => 'PRIVATE_DEFAULT' ),
        ); }
        if ( str_starts_with( $query, 'SHOW INDEX' ) ) {
            if ( $this->fail_index ) { $this->last_error = 'private database error'; return null; }
            $this->last_error = '';
            return array( array( 'Key_name' => 'PRIMARY', 'Seq_in_index' => 2, 'Column_name' => 'state' ), array( 'Key_name' => 'PRIMARY', 'Seq_in_index' => 1, 'Column_name' => 'id' ) );
        }
        throw new Exception( 'Unexpected non-metadata SQL' );
    }
}
$wpdb = new HJ_Helper_Test_DB();
require '/repo/tools/hj-diagnostic-helper/hj-diagnostic-helper.php';
function hj_helper_check( $value, $message ) { if ( ! $value ) { echo "FAIL: $message\n"; exit( 1 ); } echo "PASS: $message\n"; }
$r = HJ_Diagnostic_Helper_Read_Only::report();
hj_helper_check( $r['table'] === 'unknown' && !$wpdb->calls, 'missing schema reports unknown without query' );
HJ_Doctors_Rating_Bridge::$fixture = array( 'table' => 'wp_hj_doctor_votes', 'ok' => true, 'map' => array( 'id' => 'id', 'status' => 'state' ) );
$r = HJ_Diagnostic_Helper_Read_Only::report();
$s = json_encode( $r );
hj_helper_check( $r['primary_key_columns'] === array( 'id', 'state' ) && $r['bridge_mapping']['status'] === 'state', 'composite key is ordered and mapped' );
hj_helper_check( strpos( $s, 'PRIVATE_LITERAL' ) === false && strpos( $s, 'PRIVATE_DEFAULT' ) === false && strpos( $s, 'wp_hj_doctor_votes' ) === false, 'literals, defaults and full table name omitted' );
hj_helper_check( $r['columns'][1]['type'] === 'enum (values omitted)' && ! $wpdb->suppressed, 'enum normalized and DB error mode restored' );
$wpdb->fail_index = true; $r = HJ_Diagnostic_Helper_Read_Only::report();
hj_helper_check( $r['primary_key_columns'] === null && strpos( json_encode( $r ), 'private database error' ) === false, 'index error returns unknown without error details' );
$wpdb->throw = true; $wpdb->suppressed = true; $r = HJ_Diagnostic_Helper_Read_Only::report();
hj_helper_check( $r['columns'] === null && $wpdb->suppressed, 'exception restores original suppression state' );
$wpdb->throw = false; HJ_Doctors_Rating_Bridge::$throw = true; $r = HJ_Diagnostic_Helper_Read_Only::report();
hj_helper_check( $r['table'] === 'unknown' && $wpdb->suppressed && strpos( json_encode( $r ), 'secret details' ) === false, 'bridge exception hidden and prior suppression restored' );
HJ_Doctors_Rating_Bridge::$throw = false; HJ_Doctors_Rating_Bridge::$fixture['table'] = 'bad`table'; $before = count( $wpdb->calls ); $r = HJ_Diagnostic_Helper_Read_Only::report();
hj_helper_check( $r['table'] === 'unknown' && count( $wpdb->calls ) === $before, 'invalid table name does not reach SQL' );
echo "DONE: isolated diagnostic helper stub fixture.\n";

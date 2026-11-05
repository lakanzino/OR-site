<?php
/** Destructive fixture; run only against a brand-new disposable DB, NEVER a real site. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST || ! defined( 'ABSPATH' ) ) { exit( 'Disposable WordPress only.' ); }
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
require_once dirname( __DIR__ ) . '/hj-diagnostic-helper.php';
function hj_diagnostic_assert( $value, $message ) {
	if ( ! $value ) { throw new Exception( 'FAIL: ' . $message ); }
	echo 'PASS: ' . $message . "\n";
}
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes';
if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) { throw new Exception( 'Invalid test table' ); }
// Fixture is expected to be created by the vote-schema-wp.php test first.
hj_diagnostic_assert( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table, 'vote fixture exists' );
hj_diagnostic_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN comment TEXT" ), 'add synthetic comment column' );
hj_diagnostic_assert( false !== $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `fixture<svg>` VARCHAR(20)" ), 'add synthetic hostile-looking column name' );
hj_diagnostic_assert( false !== $wpdb->update( $table, array( 'comment' => 'SYNTHETIC_PRIVATE_SECRET' ), array( 'id' => 12345 ) ), 'add synthetic private content' );
$before = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
$queries = array();
$recorder = function ( $query ) use ( &$queries ) { $queries[] = $query; return $query; };
add_filter( 'query', $recorder );
$report = HJ_Diagnostic_Helper_Read_Only::report();
remove_filter( 'query', $recorder );
$after = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id", ARRAY_A );
hj_diagnostic_assert( $before === $after, 'diagnostic report did not change vote rows' );
foreach ( $queries as $query ) {
	hj_diagnostic_assert( ! preg_match( '/\A\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query ), 'diagnostic issued no write SQL' );
	hj_diagnostic_assert( ! preg_match( '/\bSELECT\b.*\bFROM\s+`?' . preg_quote( $table, '/' ) . '\b/is', $query ), 'diagnostic issued no vote-row SELECT' );
}
hj_diagnostic_assert( 'default_prefixed_vote_table' === $report['table'] && true === $report['bridge_usable'], 'bridge table and usability are reported' );
hj_diagnostic_assert( array( 'id' => 'id', 'doctor_id' => 'doctor_id', 'rating' => 'rating', 'status' => 'status' ) === $report['bridge_mapping'], 'mapped column roles match fixture schema' );
hj_diagnostic_assert( is_array( $report['columns'] ) && in_array( 'comment', array_column( $report['columns'], 'name' ), true ), 'column metadata present' );
if ( ! class_exists( 'WP_SQLite_Driver' ) ) { hj_diagnostic_assert( array( 'id' ) === $report['primary_key_columns'], 'MariaDB primary key metadata correct' ); }
$json = wp_json_encode( $report );
hj_diagnostic_assert( false === strpos( $json, 'SYNTHETIC_PRIVATE_SECRET' ) && false === strpos( $json, '424242' ) && false === strpos( $json, '12345' ) && false === strpos( $json, $table ), 'no synthetic row content, IDs or full table name exported' );
class HJ_Diagnostic_Test_Die extends Exception { public $http; public function __construct( $http ) { parent::__construct(); $this->http = $http; } }
$handler = function () { return function ( $message, $title = '', $args = array() ) { throw new HJ_Diagnostic_Test_Die( $args['response'] ?? 500 ); }; };
add_filter( 'wp_die_handler', $handler, 99 );
$subscriber = wp_create_user( 'helper-sub-' . wp_generate_password( 8, false ), 'fixture-test-only' );
hj_diagnostic_assert( ! is_wp_error( $subscriber ), 'create subscriber' );
foreach ( array( 0, $subscriber ) as $user ) {
	wp_set_current_user( $user ); $_SERVER['REQUEST_METHOD'] = 'GET'; $status = null;
	try { HJ_Diagnostic_Helper_Read_Only::page(); } catch ( HJ_Diagnostic_Test_Die $e ) { $status = $e->http; }
	hj_diagnostic_assert( 403 === $status, 'anonymous/subscriber denied' );
}
( new WP_User( $subscriber ) )->set_role( 'doctor' );
wp_set_current_user( $subscriber ); $status = null;
try { HJ_Diagnostic_Helper_Read_Only::page(); } catch ( HJ_Diagnostic_Test_Die $e ) { $status = $e->http; }
hj_diagnostic_assert( 403 === $status, 'doctor denied' );
wp_set_current_user( 1 ); $_SERVER['REQUEST_METHOD'] = 'POST'; $status = null;
try { HJ_Diagnostic_Helper_Read_Only::page(); } catch ( HJ_Diagnostic_Test_Die $e ) { $status = $e->http; }
hj_diagnostic_assert( 405 === $status, 'POST denied even for administrator' );
$_SERVER['REQUEST_METHOD'] = 'GET'; ob_start(); HJ_Diagnostic_Helper_Read_Only::page(); $html = ob_get_clean();
hj_diagnostic_assert( false !== strpos( $html, '<textarea' ) && false === stripos( $html, '<form' ) && false === strpos( $html, 'SYNTHETIC_PRIVATE_SECRET' ), 'admin receives read-only copyable JSON without private row content or forms' );
hj_diagnostic_assert( false !== strpos( $html, 'fixture&lt;svg&gt;' ) && false === strpos( $html, 'fixture<svg>' ), 'hostile-looking metadata is escaped in HTML' );
remove_filter( 'wp_die_handler', $handler, 99 );
$reflection = new ReflectionMethod( 'HJ_Diagnostic_Helper_Read_Only', 'safe_type' );
$reflection->setAccessible( true );
hj_diagnostic_assert( 'enum (values omitted)' === $reflection->invoke( null, "enum('pending','SECRET_VALUE')" ), 'enum literal values omitted' );
hj_diagnostic_assert( 'bigint(20) unsigned' === $reflection->invoke( null, 'bigint(20) unsigned' ), 'safe numeric type preserved' );
echo "DONE: disposable diagnostic helper fixture.\n";

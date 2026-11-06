<?php
/** Optional booking backend. All migration and runtime routes are feature-flagged. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/appointment-schedule.php';

/** Transactions must never use wpdb's normal reconnect-and-replay behaviour. */
class HJ_Booking_Transaction_DB extends wpdb {
	public function check_connection( $allow_bail = true ) { return false; }
	public function booking_errno() { return $this->dbh instanceof mysqli ? mysqli_errno( $this->dbh ) : 0; }
}
function hj_booking_enabled() { return defined( 'HJ_BOOKING_ENABLED' ) && HJ_BOOKING_ENABLED === true; }
function hj_booking_table( $name ) {
	global $wpdb;
	$allowed = array( 'doctors', 'schedules', 'appointments', 'details', 'secretaries' );
	if ( ! in_array( $name, $allowed, true ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', $wpdb->prefix ) ) { throw new RuntimeException( 'Invalid booking table' ); }
	return $wpdb->prefix . 'hj_booking_' . $name;
}
/**
 * Create-only, versioned migration. All schema work requires explicit opt-in
 * and an administrator. Existing tables are verified, never ALTERed or dropped.
 */
function hj_booking_schema_definitions( $charset ) {
 $d = hj_booking_table( 'doctors' ); $s = hj_booking_table( 'schedules' ); $a = hj_booking_table( 'appointments' ); $p = hj_booking_table( 'details' ); $m = hj_booking_table( 'secretaries' );
 return array(
  $d => "CREATE TABLE IF NOT EXISTS `$d` (doctor_id bigint(20) unsigned NOT NULL, duration_minutes int NOT NULL DEFAULT 30, buffer_minutes int NOT NULL DEFAULT 5, enabled tinyint NOT NULL DEFAULT 0, PRIMARY KEY (doctor_id)) ENGINE=InnoDB $charset",
  $s => "CREATE TABLE IF NOT EXISTS `$s` (doctor_id bigint(20) unsigned NOT NULL, revision bigint(20) unsigned NOT NULL, rows_json longtext NOT NULL, rows_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, PRIMARY KEY (doctor_id)) ENGINE=InnoDB $charset",
  $a => "CREATE TABLE IF NOT EXISTS `$a` (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, doctor_id bigint(20) unsigned NOT NULL, patient_id bigint(20) unsigned NOT NULL, request_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, starts_at datetime NOT NULL, ends_at datetime NOT NULL, blocked_until datetime NOT NULL, schedule_revision bigint(20) unsigned NOT NULL, status varchar(20) NOT NULL, PRIMARY KEY (id), UNIQUE KEY request_key (request_key), KEY doctor_start (doctor_id,starts_at), KEY patient_start (patient_id,starts_at)) ENGINE=InnoDB $charset",
  $p => "CREATE TABLE IF NOT EXISTS `$p` (appointment_id bigint(20) unsigned NOT NULL, phone varchar(24) NOT NULL, national_code char(10) NOT NULL, PRIMARY KEY (appointment_id)) ENGINE=InnoDB $charset",
  $m => "CREATE TABLE IF NOT EXISTS `$m` (user_id bigint(20) unsigned NOT NULL, doctor_id bigint(20) unsigned NOT NULL, PRIMARY KEY (user_id,doctor_id)) ENGINE=InnoDB $charset",
 );
}
/** Verify every field and critical index; unknown/incompatible structures fail closed. */
function hj_booking_schema_valid_table( $db, $name ) {
 $big = 'bigint unsigned';
 $specs = array(
  hj_booking_table( 'doctors' ) => array( 'doctor_id'=>$big, 'duration_minutes'=>'int', 'buffer_minutes'=>'int', 'enabled'=>'tinyint' ),
  hj_booking_table( 'schedules' ) => array( 'doctor_id'=>$big, 'revision'=>$big, 'rows_json'=>'longtext', 'rows_hash'=>'char(64)' ),
  hj_booking_table( 'appointments' ) => array( 'id'=>$big, 'doctor_id'=>$big, 'patient_id'=>$big, 'request_key'=>'char(32)', 'request_hash'=>'char(64)', 'starts_at'=>'datetime', 'ends_at'=>'datetime', 'blocked_until'=>'datetime', 'schedule_revision'=>$big, 'status'=>'varchar(20)' ),
  hj_booking_table( 'details' ) => array( 'appointment_id'=>$big, 'phone'=>'varchar(24)', 'national_code'=>'char(10)' ),
  hj_booking_table( 'secretaries' ) => array( 'user_id'=>$big, 'doctor_id'=>$big ),
 );
 $indexes = array(
  hj_booking_table( 'doctors' ) => array( 'PRIMARY'=>array( true, array('doctor_id') ) ),
  hj_booking_table( 'schedules' ) => array( 'PRIMARY'=>array( true, array('doctor_id') ) ),
  hj_booking_table( 'appointments' ) => array( 'PRIMARY'=>array( true, array('id') ), 'request_key'=>array( true, array('request_key') ), 'doctor_start'=>array( false, array('doctor_id','starts_at') ), 'patient_start'=>array( false, array('patient_id','starts_at') ) ),
  hj_booking_table( 'details' ) => array( 'PRIMARY'=>array( true, array('appointment_id') ) ),
  hj_booking_table( 'secretaries' ) => array( 'PRIMARY'=>array( true, array('user_id','doctor_id') ) ),
 );
 if ( ! isset( $specs[$name] ) ) { return false; }
 $info = hj_booking_row( $db, $db->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $name ) );
 if ( ! $info || strtoupper( $info['Engine'] ?? '' ) !== 'INNODB' ) { return false; }
 $columns = hj_booking_rows( $db, "SHOW FULL COLUMNS FROM `$name`" ); $seen = array();
 foreach ( $columns as $column ) {
  $field = $column['Field'];
  $type = preg_replace( '/^(bigint|int|tinyint)\([0-9]+\)/', '$1', strtolower( $column['Type'] ) );
  if ( ! isset( $specs[$name][$field] ) || $type !== $specs[$name][$field] || $column['Null'] !== 'NO' ) { return false; }
  if ( in_array( $field, array( 'rows_hash', 'request_key', 'request_hash' ), true ) && ( $column['Collation'] ?? '' ) !== 'ascii_bin' ) { return false; }
  if ( $name === hj_booking_table( 'appointments' ) && $field === 'id' && strpos( $column['Extra'], 'auto_increment' ) === false ) { return false; }
  if ( $name === hj_booking_table( 'doctors' ) && isset( array( 'duration_minutes'=>1, 'buffer_minutes'=>1, 'enabled'=>1 )[$field] ) ) {
   $defaults = array( 'duration_minutes'=>'30', 'buffer_minutes'=>'5', 'enabled'=>'0' );
   if ( (string) $column['Default'] !== $defaults[$field] ) { return false; }
  }
  $seen[$field] = true;
 }
 if ( count( $seen ) !== count( $specs[$name] ) ) { return false; }
 $actual = array();
 foreach ( hj_booking_rows( $db, "SHOW INDEX FROM `$name`" ) as $index ) {
  $key = $index['Key_name']; $seq = (int) $index['Seq_in_index'];
  if ( ! isset( $actual[$key] ) ) { $actual[$key] = array( 'unique' => (int) $index['Non_unique'] === 0, 'cols' => array(), 'whole' => true ); }
  $actual[$key]['cols'][$seq] = $index['Column_name'];
  if ( $index['Sub_part'] !== null ) { $actual[$key]['whole'] = false; }
 }
 foreach ( $actual as $key => $index ) {
  // An unknown UNIQUE constraint can reject otherwise valid booking writes.
  if ( $index['unique'] && ! isset( $indexes[$name][$key] ) ) { return false; }
 }
 foreach ( $indexes[$name] as $key => $expected ) {
  if ( ! isset( $actual[$key] ) || ! $actual[$key]['whole'] || $actual[$key]['unique'] !== $expected[0] ) { return false; }
  ksort( $actual[$key]['cols'] );
  if ( array_values( $actual[$key]['cols'] ) !== $expected[1] ) { return false; }
 }
 return true;
}
function hj_booking_schema_clear_option_cache() {
 wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' );
 wp_cache_delete( 'hj_booking_schema_version', 'options' ); wp_cache_delete( 'hj_booking_schema_verified', 'options' );
}
function hj_booking_install_schema() {
 global $wpdb;
 if ( ! hj_booking_enabled() || ! current_user_can( 'manage_options' ) || get_class( $wpdb ) !== 'wpdb' ) { return false; }
 $db = new HJ_Booking_Transaction_DB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
 $db->suppress_errors( true ); $locked = false; $ok = false;
 $lock = 'hj-booking-migration-' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->prefix ), 0, 40 );
 try {
  $got = hj_booking_row( $db, $db->prepare( 'SELECT GET_LOCK(%s, 5) AS acquired', $lock ) );
  if ( ! $got || (int) $got['acquired'] !== 1 ) { return false; }
  $locked = true; hj_booking_schema_clear_option_cache();
  $raw_version = get_option( 'hj_booking_schema_version', 0 );
  if ( ! is_scalar( $raw_version ) || ! in_array( (string) $raw_version, array( '0', '1' ), true ) ) { return false; }
  $version = (int) $raw_version;
  $definitions = hj_booking_schema_definitions( $wpdb->get_charset_collate() );
  $existing = array(); $missing = array();
  foreach ( $definitions as $table => $sql ) {
   $info = hj_booking_row( $db, $db->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ) );
   if ( ! $info ) { $missing[$table] = $sql; continue; }
   if ( ! hj_booking_schema_valid_table( $db, $table ) ) { return false; }
   $existing[] = $table;
  }
  // A missing table in an already-installed schema is not an empty install.
  if ( $version === 1 && $missing ) { return false; }
  // Resume a partial fresh install only when existing partial tables are empty.
  if ( $missing ) {
   foreach ( $existing as $table ) { if ( hj_booking_row( $db, "SELECT 1 AS present FROM `$table` LIMIT 1" ) ) { return false; } }
  }
  foreach ( $missing as $sql ) { hj_booking_query( $db, $sql ); }
  foreach ( $definitions as $table => $sql ) { if ( ! hj_booking_schema_valid_table( $db, $table ) ) { return false; } }
  hj_booking_schema_clear_option_cache();
  update_option( 'hj_booking_schema_version', 1, false );
  update_option( 'hj_booking_schema_verified', 1, false );
  hj_booking_schema_clear_option_cache();
  $ok = (int) get_option( 'hj_booking_schema_version', 0 ) === 1 && (int) get_option( 'hj_booking_schema_verified', 0 ) === 1;
  return $ok;
 } catch ( Throwable $e ) { return false; }
 finally {
  if ( $locked && ! $ok ) { update_option( 'hj_booking_schema_verified', 0, false ); }
  if ( $locked ) { $db->get_var( $db->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
  $db->close();
 }
}
// Version 2 is CI-only: no migration or production activation is supplied.
function hj_booking_capacity_v2_ready() {
 global $wpdb;
 return defined( 'HJ_BOOKING_TESTING' ) && HJ_BOOKING_TESTING === true && defined( 'HJ_BOOKING_CAPACITY_V2_TEST_ONLY' ) && HJ_BOOKING_CAPACITY_V2_TEST_ONLY === true && getenv( 'GITHUB_ACTIONS' ) === 'true' && getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) === '1' && defined( 'DB_NAME' ) && DB_NAME === 'hj_fixture_db' && defined( 'DB_HOST' ) && DB_HOST === '127.0.0.1:3306' && defined( 'WP_HOME' ) && WP_HOME === 'http://example.invalid' && isset( $wpdb->prefix ) && $wpdb->prefix === 'hjt_' && (int) get_option( 'hj_booking_schema_version', 0 ) === 2 && (int) get_option( 'hj_booking_schema_verified', 0 ) === 1;
}
function hj_booking_ready() {
 global $wpdb;
 $version = (int) get_option( 'hj_booking_schema_version', 0 );
 return hj_booking_enabled() && ( $version === 1 || ( $version === 2 && hj_booking_capacity_v2_ready() ) ) && (int) get_option( 'hj_booking_schema_verified', 0 ) === 1 && get_class( $wpdb ) === 'wpdb';
}
/** Read-only display gate, not an authorization or a guarantee of open slots.
 * The actual booking path always rechecks the state under the doctor mutex.
 */
function hj_booking_doctor_has_valid_schedule( $doctor ) {
 global $wpdb;
 if ( ! hj_booking_ready() || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return false; }
 $doctors = hj_booking_table( 'doctors' ); $schedules = hj_booking_table( 'schedules' );
 $previous = $wpdb->suppress_errors( true );
 try {
  $row = $wpdb->get_row( $wpdb->prepare( "SELECT d.enabled,s.revision,s.rows_json,s.rows_hash FROM `$doctors` d INNER JOIN `$schedules` s ON s.doctor_id=d.doctor_id WHERE d.doctor_id=%d", $doctor ), ARRAY_A );
  if ( $wpdb->last_error || ! $row || (int) $row['enabled'] !== 1 ) { return false; }
  return hj_booking_decode_snapshot( $row ) !== false;
 } catch ( Throwable $e ) { return false; }
 finally { $wpdb->suppress_errors( $previous ); }
}
/** Activation and upgrades remain inert unless the explicit booking flag is true. */
function hj_booking_maybe_install_schema() {
 if ( hj_booking_enabled() && current_user_can( 'manage_options' ) && ! hj_booking_ready() ) { hj_booking_install_schema(); }
}
add_action( 'admin_init', 'hj_booking_maybe_install_schema', 2 );
add_action( 'admin_notices', function () {
 if ( hj_booking_enabled() && current_user_can( 'manage_options' ) && ! hj_booking_ready() ) { echo '<div class="notice notice-error"><p>ساختار نوبت‌دهی تأیید نشد؛ قابلیت غیرفعال است. پشتیبان و وضعیت جداول را بررسی کنید.</p></div>'; }
} );
function hj_booking_can_configure( $doctor ) {
	return is_user_logged_in() && get_post_type( $doctor ) === 'doctor' && function_exists( 'hj_doctors_user_can_edit_profile' ) && hj_doctors_user_can_edit_profile( $doctor );
}
function hj_booking_can_manage( $doctor ) {
	global $wpdb;
	if ( ! is_user_logged_in() || get_post_type( $doctor ) !== 'doctor' ) { return false; }
	if ( current_user_can( 'manage_options' ) ) { return true; }
	if ( current_user_can( HJ_Doctors_Roles::CAP_EDIT_OWN ) && hj_doctors_user_owns_profile( get_current_user_id(), $doctor ) ) { return true; }
	if ( ! current_user_can( 'hj_manage_appointments' ) ) { return false; }
	$table = hj_booking_table( 'secretaries' );
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `$table` WHERE user_id=%d AND doctor_id=%d", get_current_user_id(), $doctor ) );
}
function hj_booking_query( $db, $sql ) {
	$r = $db->query( $sql );
	if ( $r === false ) { throw new RuntimeException( 'Booking query failed', $db->booking_errno() ); }
	return $r;
}
function hj_booking_row( $db, $sql ) {
	$r = $db->get_row( $sql, ARRAY_A );
	if ( $db->last_error ) { throw new RuntimeException( 'Booking query failed', $db->booking_errno() ); }
	return $r;
}
function hj_booking_rows( $db, $sql ) {
	$r = $db->get_results( $sql, ARRAY_A );
	if ( $db->last_error || ! is_array( $r ) ) { throw new RuntimeException( 'Booking query failed', $db->booking_errno() ); }
	return $r;
}
/** Test callbacks are only available when a server-side test constant is enabled. */
function hj_booking_test_event( $event, $db, $context ) {
	if ( defined( 'HJ_BOOKING_TESTING' ) && HJ_BOOKING_TESTING === true ) { do_action( 'hj_booking_test_event', $event, $db, $context ); }
}
/** Callback only performs DB operations; retries are allowed only before COMMIT. */
function hj_booking_transaction( $doctor, $operation, $callback ) {
	if ( ! hj_booking_ready() ) { return array( 'status' => 'unavailable' ); }
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$db = new HJ_Booking_Transaction_DB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->suppress_errors( true ); $begun = false; $committing = false;
		try {
			hj_booking_query( $db, 'SET SESSION innodb_lock_wait_timeout = 3' );
			hj_booking_query( $db, 'START TRANSACTION' ); $begun = true;
			$table = hj_booking_table( 'doctors' );
			hj_booking_test_event( 'before_lock', $db, array( 'operation' => $operation, 'doctor' => $doctor ) );
			// This insert itself is transactional and serializes initial creation.
			hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id) VALUES (%d) ON DUPLICATE KEY UPDATE doctor_id=doctor_id", $doctor ) );
			$config = hj_booking_row( $db, $db->prepare( "SELECT * FROM `$table` WHERE doctor_id=%d FOR UPDATE", $doctor ) );
			if ( ! $config ) { throw new RuntimeException( 'Doctor mutex missing' ); }
			hj_booking_test_event( 'after_lock', $db, array( 'operation' => $operation, 'doctor' => $doctor ) );
			$result = $callback( $db, $config );
			if ( ! is_array( $result ) || ! isset( $result['status'] ) ) { throw new RuntimeException( 'Invalid transaction result' ); }
			hj_booking_test_event( 'before_commit', $db, array( 'operation' => $operation, 'doctor' => $doctor, 'result' => $result ) );
			$committing = true;
			if ( false === $db->query( 'COMMIT' ) ) { return array( 'status' => 'unknown' ); }
			$begun = false;
			hj_booking_test_event( 'after_commit', $db, array( 'operation' => $operation, 'doctor' => $doctor, 'result' => $result ) );
			return $result;
		} catch ( Throwable $e ) {
			if ( $committing ) { return array( 'status' => 'unknown' ); }
			if ( ! in_array( $e->getCode(), array( 1205, 1213 ), true ) ) { return array( 'status' => 'error' ); }
		} finally {
			if ( $begun && ! $committing ) { $db->query( 'ROLLBACK' ); }
			$db->close();
		}
		if ( $attempt < 2 ) { usleep( 20000 * ( $attempt + 1 ) ); }
	}
	return array( 'status' => 'busy' );
}
function hj_booking_normalize_schedule( $input ) {
	if ( ! is_array( $input ) || count( $input ) > 7 ) { return false; }
	$days = array( 'saturday','sunday','monday','tuesday','wednesday','thursday','friday' ); $seen = array(); $result = array();
	foreach ( $input as $row ) {
		// Unknown fields must not be silently dropped by an old-format writer.
		if ( ! is_array( $row ) || count( $row ) !== 4 || ! isset( $row['day_of_week'], $row['is_closed'], $row['start_time'], $row['end_time'] ) ) { return false; }
		$day = $row['day_of_week']; $closed = $row['is_closed']; $start = $row['start_time']; $end = $row['end_time'];
		if ( ! is_string( $day ) || ! in_array( $day, $days, true ) || isset( $seen[$day] ) || ! in_array( $closed, array( 0,1,'0','1',false,true ), true ) || ! is_string( $start ) || ! is_string( $end ) ) { return false; }
		$seen[$day] = true; $closed = (int) $closed;
		if ( ! $closed ) {
			$pattern = '/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D';
			if ( ! preg_match( $pattern, $start ) || ! preg_match( $pattern, $end ) ) { return false; }
			if ( strlen( $start ) === 5 ) { $start .= ':00'; } if ( strlen( $end ) === 5 ) { $end .= ':00'; }
			if ( $start >= $end ) { return false; }
		} else { $start = ''; $end = ''; }
		$result[] = array( 'day_of_week' => $day, 'is_closed' => $closed, 'start_time' => $start, 'end_time' => $end );
	}
	return $result;
}
/** Validate the complete old-format snapshot before reading OR replacing it. */
function hj_booking_legacy_snapshot_rows( $row ) {
	if ( ! is_array( $row ) || ! isset( $row['revision'], $row['rows_json'], $row['rows_hash'] ) || (int) $row['revision'] < 1 || ! is_string( $row['rows_json'] ) || ! is_string( $row['rows_hash'] ) || ! hash_equals( $row['rows_hash'], hash( 'sha256', $row['rows_json'] ) ) ) { return false; }
	// An envelope/object (even {}) is not the old array format.
	if ( ! is_array( json_decode( $row['rows_json'] ) ) ) { return false; }
	return hj_booking_normalize_schedule( json_decode( $row['rows_json'], true ) );
}
/** Weekly v2 policies only: no date exceptions, daily cap, or break UI yet. */
function hj_booking_normalize_capacity_week( $input ) {
	if ( ! is_array( $input ) || count( $input ) > 7 ) { return false; }
	$base = array(); $policies = array();
	foreach ( $input as $row ) {
		if ( ! is_array( $row ) || count( $row ) !== 7 || ! isset( $row['day_of_week'], $row['is_closed'], $row['start_time'], $row['end_time'], $row['duration_minutes'], $row['buffer_minutes'], $row['capacity'] ) || ! is_int( $row['duration_minutes'] ) || $row['duration_minutes'] < 1 || $row['duration_minutes'] > 240 || ! is_int( $row['buffer_minutes'] ) || $row['buffer_minutes'] < 0 || $row['buffer_minutes'] > 120 || ! is_int( $row['capacity'] ) || $row['capacity'] < 1 || $row['capacity'] > 10 ) { return false; }
		$base[] = array( 'day_of_week'=>$row['day_of_week'], 'is_closed'=>$row['is_closed'], 'start_time'=>$row['start_time'], 'end_time'=>$row['end_time'] );
		$policies[] = array( 'duration_minutes'=>$row['duration_minutes'], 'buffer_minutes'=>$row['buffer_minutes'], 'capacity'=>$row['capacity'] );
	}
	$base = hj_booking_normalize_schedule( $base );
	if ( $base === false ) { return false; }
	foreach ( $base as $i => $row ) { $base[$i] = $row + $policies[$i]; }
	return $base;
}
/** Decode v1 without rewriting it; v2 is accepted only behind its explicit gate. */
function hj_booking_decode_snapshot( $row ) {
	$legacy = hj_booking_legacy_snapshot_rows( $row );
	if ( $legacy !== false ) { return array( 'format'=>1, 'rows'=>$legacy, 'revision'=>(int)$row['revision'] ); }
	if ( ! hj_booking_capacity_v2_ready() || ! is_array( $row ) || ! isset( $row['revision'], $row['rows_json'], $row['rows_hash'] ) || (int)$row['revision'] < 1 || ! is_string( $row['rows_json'] ) || ! is_string( $row['rows_hash'] ) || ! hash_equals( $row['rows_hash'], hash( 'sha256', $row['rows_json'] ) ) ) { return false; }
	$shape = json_decode( $row['rows_json'] );
	if ( ! is_object( $shape ) || ! isset( $shape->version, $shape->rows ) || $shape->version !== 2 || ! is_array( $shape->rows ) ) { return false; }
	$data = json_decode( $row['rows_json'], true );
	if ( ! is_array( $data ) || count( $data ) !== 2 ) { return false; }
	$rows = hj_booking_normalize_capacity_week( $data['rows'] );
	return $rows === false ? false : array( 'format'=>2, 'rows'=>$rows, 'revision'=>(int)$row['revision'] );
}
/** Called by dashboard after its existing nonce/ownership and form validation. */
function hj_booking_save_schedule( $doctor, $rows, $expected_revision ) {
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! is_int( $expected_revision ) || $expected_revision < 0 ) { return array( 'status' => 'invalid' ); }
	$normalized = hj_booking_normalize_schedule( $rows );
	if ( $normalized === false ) { return array( 'status' => 'invalid' ); }
	$json = wp_json_encode( $normalized ); if ( ! is_string( $json ) ) { return array( 'status' => 'invalid' ); }
	$hash = hash( 'sha256', $json );
	return hj_booking_transaction( $doctor, 'schedule', function ( $db ) use ( $doctor, $json, $hash, $expected_revision ) {
		$table = hj_booking_table( 'schedules' );
		$old = hj_booking_row( $db, $db->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d FOR UPDATE", $doctor ) );
		$current = $old ? (int) $old['revision'] : 0;
		if ( $current !== $expected_revision ) { return array( 'status' => 'revision_conflict' ); }
		// A matching revision is not permission to destroy an unreadable or newer snapshot.
		if ( $old && hj_booking_legacy_snapshot_rows( $old ) === false ) { return array( 'status' => 'invalid_schedule' ); }
		if ( $current === PHP_INT_MAX ) { return array( 'status' => 'invalid' ); }
		$rev = $current + 1;
		if ( $old ) { $n = hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d", $rev, $json, $hash, $doctor ) ); }
		else { $n = hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id,revision,rows_json,rows_hash) VALUES (%d,%d,%s,%s)", $doctor, $rev, $json, $hash ) ); }
		if ( $n !== 1 ) { throw new RuntimeException( 'Schedule save failed' ); }
		return array( 'status' => 'saved', 'revision' => $rev );
	} );
}
/** Experimental v2 writer; no endpoint/UI or schema migration is provided yet. */
function hj_booking_save_capacity_week( $doctor, $rows, $expected_revision ) {
	if ( ! hj_booking_capacity_v2_ready() || ! hj_booking_ready() ) { return array( 'status'=>'unavailable' ); }
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status'=>'forbidden' ); }
	if ( ! is_int( $expected_revision ) || $expected_revision < 0 ) { return array( 'status'=>'invalid' ); }
	$normalized = hj_booking_normalize_capacity_week( $rows );
	if ( $normalized === false ) { return array( 'status'=>'invalid' ); }
	$json = wp_json_encode( array( 'version'=>2, 'rows'=>$normalized ) );
	if ( ! is_string( $json ) ) { return array( 'status'=>'invalid' ); }
	$hash = hash( 'sha256', $json );
	return hj_booking_transaction( $doctor, 'capacity_schedule', function ( $db ) use ( $doctor, $json, $hash, $expected_revision ) {
		$table = hj_booking_table( 'schedules' ); $appointments = hj_booking_table( 'appointments' );
		$old = hj_booking_row( $db, $db->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d FOR UPDATE", $doctor ) );
		$current = $old ? (int)$old['revision'] : 0;
		if ( $current !== $expected_revision ) { return array( 'status'=>'revision_conflict' ); }
		if ( $old && hj_booking_decode_snapshot( $old ) === false ) { return array( 'status'=>'invalid_schedule' ); }
		if ( $current === PHP_INT_MAX ) { return array( 'status'=>'invalid' ); }
		// Simple safe policy: no schedule mutation while a non-cancelled future or
		// ongoing reservation exists. Never delete or silently move reservations.
		$busy = hj_booking_row( $db, $db->prepare( "SELECT id FROM `$appointments` WHERE doctor_id=%d AND status<>'cancelled' AND blocked_until>%s LIMIT 1", $doctor, gmdate( 'Y-m-d H:i:s' ) ) );
		if ( $busy ) { return array( 'status'=>'has_future_bookings' ); }
		$revision = $current + 1;
		if ( $old ) { $n = hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET revision=%d,rows_json=%s,rows_hash=%s WHERE doctor_id=%d", $revision, $json, $hash, $doctor ) ); }
		else { $n = hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id,revision,rows_json,rows_hash) VALUES (%d,%d,%s,%s)", $doctor, $revision, $json, $hash ) ); }
		if ( $n !== 1 ) { throw new RuntimeException( 'Capacity schedule save failed' ); }
		return array( 'status'=>'saved', 'revision'=>$revision );
	} );
}
/** Explicit doctor configuration, disabled until set. */
function hj_booking_configure( $doctor, $duration, $buffer, $enabled ) {
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! is_int( $duration ) || $duration < 1 || $duration > 240 || ! is_int( $buffer ) || $buffer < 0 || $buffer > 120 || ! is_bool( $enabled ) ) { return array( 'status' => 'invalid' ); }
	return hj_booking_transaction( $doctor, 'configure', function ( $db ) use ( $doctor, $duration, $buffer, $enabled ) {
		$table = hj_booking_table( 'doctors' );
		hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET duration_minutes=%d,buffer_minutes=%d,enabled=%d WHERE doctor_id=%d", $duration, $buffer, $enabled ? 1 : 0, $doctor ) );
		return array( 'status' => 'configured' );
	} );
}
/** Editor must read rows and revision together from the canonical snapshot. */
function hj_booking_editor_schedule( $doctor ) {
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	return hj_booking_transaction( $doctor, 'schedule_read', function ( $db ) use ( $doctor ) {
		$table = hj_booking_table( 'schedules' );
		$row = hj_booking_row( $db, $db->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d", $doctor ) );
		if ( ! $row ) { return array( 'status' => 'ok', 'revision' => 0, 'rows' => array(), 'missing' => true ); }
		$rows = hj_booking_legacy_snapshot_rows( $row );
		if ( $rows === false ) { return array( 'status' => 'invalid_schedule' ); }
		return array( 'status' => 'ok', 'revision' => (int) $row['revision'], 'rows' => $rows, 'missing' => false );
	} );
}
function hj_booking_snapshot_locked( $db, $doctor ) {
	$table = hj_booking_table( 'schedules' );
	$row = hj_booking_row( $db, $db->prepare( "SELECT revision,rows_json,rows_hash FROM `$table` WHERE doctor_id=%d", $doctor ) );
	return hj_booking_decode_snapshot( $row );
}
function hj_booking_slots_locked( $db, $doctor, $config, $date ) {
	$snapshot = hj_booking_snapshot_locked( $db, $doctor );
	if ( $snapshot === false ) { return false; }
	$table = hj_booking_table( 'appointments' );
	if ( $snapshot['format'] === 1 ) {
		$occupied = hj_booking_rows( $db, $db->prepare( "SELECT starts_at,blocked_until,status FROM `$table` WHERE doctor_id=%d AND status IN ('pending','confirmed')", $doctor ) );
		return array( 'slots' => hj_booking_compute_slots( $date, $snapshot['rows'], (int) $config['duration_minutes'], (int) $config['buffer_minutes'], $occupied, time(), 30 ), 'revision' => $snapshot['revision'] );
	}
	// Terminal records still block until their recorded interval ends; unknown
	// future states fail closed in the capacity calculator rather than vanish.
	$occupied = hj_booking_rows( $db, $db->prepare( "SELECT starts_at,blocked_until,status FROM `$table` WHERE doctor_id=%d AND status<>'cancelled' AND blocked_until>%s", $doctor, gmdate( 'Y-m-d H:i:s' ) ) );
	$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
	if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) { return false; }
	$weekday = strtolower( $day->format( 'l' ) ); $rule = null;
	foreach ( $snapshot['rows'] as $row ) { if ( $row['day_of_week'] === $weekday ) { $rule = $row; break; } }
	if ( ! $rule || $rule['is_closed'] ) { return array( 'slots'=>array(), 'intervals'=>array(), 'revision'=>$snapshot['revision'] ); }
	$day_slots = hj_booking_compute_capacity_day( $date, $rule, $occupied, time(), 30, wp_timezone() );
	if ( $day_slots === false ) { return false; }
	$starts = array(); $intervals = array();
	foreach ( $day_slots['slots'] as $slot ) {
		if ( $slot['state'] === 'available' ) { $starts[] = $slot['start']; $intervals[$slot['start']] = array( 'end'=>$slot['end'], 'blocked_until'=>$slot['blocked_until'] ); }
	}
	return array( 'slots'=>$starts, 'intervals'=>$intervals, 'revision'=>$snapshot['revision'] );
}
function hj_booking_slots( $doctor, $date ) {
	if ( ! is_user_logged_in() ) { return array( 'status' => 'forbidden' ); }
	return hj_booking_public_slots( $doctor, $date );
}
/** Public availability contains no reservation or patient records. */
function hj_booking_public_slots( $doctor, $date ) {
	if ( get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) { return array( 'status' => 'invalid' ); }
	return hj_booking_transaction( $doctor, 'slots', function ( $db, $config ) use ( $doctor, $date ) {
		if ( ! (int) $config['enabled'] ) { return array( 'status' => 'unavailable' ); }
		$r = hj_booking_slots_locked( $db, $doctor, $config, $date );
		if ( $r === false ) { return array( 'status' => 'invalid_schedule' ); }
		unset( $r['intervals'] ); // Internal selected-slot details are not a public contract.
		return array( 'status' => 'ok' ) + $r;
	} );
}
function hj_booking_phone( $value ) {
	if ( ! is_string( $value ) ) { return false; }
	$s = function_exists( 'hj_to_en_digits' ) ? hj_to_en_digits( $value ) : $value;
	if ( str_starts_with( $s, '+98' ) ) { $s = '0' . substr( $s, 3 ); } elseif ( str_starts_with( $s, '0098' ) ) { $s = '0' . substr( $s, 4 ); }
	return preg_match( '/^09[0-9]{9}$/D', $s ) ? $s : false;
}
function hj_booking_national_code( $value ) {
	if ( ! is_string( $value ) ) { return false; }
	$s = function_exists( 'hj_to_en_digits' ) ? hj_to_en_digits( $value ) : $value;
	if ( ! preg_match( '/^[0-9]{10}$/D', $s ) || preg_match( '/^(\d)\1{9}$/D', $s ) ) { return false; }
	$sum = 0; for ( $i = 0; $i < 9; $i++ ) { $sum += (int) $s[$i] * ( 10 - $i ); }
	$r = $sum % 11; return (int) $s[9] === ( $r < 2 ? $r : 11 - $r ) ? $s : false;
}
/** Request key is random and stable across retries; caller must retain it after unknown. */
function hj_booking_create( $doctor, $start, $key, $phone, $national, $expected_revision = null ) {
	$patient = get_current_user_id();
	if ( ! $patient || get_post_type( $doctor ) !== 'doctor' || get_post_status( $doctor ) !== 'publish' ) { return array( 'status' => 'forbidden' ); }
	$phone = hj_booking_phone( $phone ); $national = hj_booking_national_code( $national );
	if ( $phone === false || $national === false || ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{32}$/D', $key ) || ! is_string( $start ) || ( $expected_revision !== null && ( ! is_int( $expected_revision ) || $expected_revision < 1 ) ) ) { return array( 'status' => 'invalid' ); }
	$utc = new DateTimeZone( 'UTC' ); $when = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start, $utc );
	if ( ! $when || $when->format( 'Y-m-d H:i:s' ) !== $start ) { return array( 'status' => 'invalid' ); }
	$date = $when->setTimezone( wp_timezone() )->format( 'Y-m-d' );
	$payload = array( $doctor, $patient, $start, $phone, $national );
	if ( $expected_revision !== null ) { $payload[] = $expected_revision; }
	$hash = hash_hmac( 'sha256', wp_json_encode( $payload ), wp_salt( 'auth' ) );
	global $wpdb;
	$users = $wpdb->users;
	if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $users ) ) { return array( 'status' => 'unavailable' ); }
	return hj_booking_transaction( $doctor, 'create', function ( $db, $config ) use ( $doctor, $patient, $start, $when, $key, $phone, $national, $hash, $date, $expected_revision, $users ) {
		$table = hj_booking_table( 'appointments' ); $details = hj_booking_table( 'details' );
		// Serialize this patient's pending limit across different doctors as well.
		// The user row must be transactional; never silently enforce an unsafe limit.
		$info = hj_booking_row( $db, $db->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $users ) );
		if ( ! $info || strtoupper( $info['Engine'] ?? '' ) !== 'INNODB' ) { return array( 'status' => 'unavailable' ); }
		$user = hj_booking_row( $db, $db->prepare( "SELECT ID FROM `$users` WHERE ID=%d FOR UPDATE", $patient ) );
		if ( ! $user ) { return array( 'status' => 'forbidden' ); }
		$existing = hj_booking_row( $db, $db->prepare( "SELECT id,request_hash FROM `$table` WHERE request_key=%s", $key ) );
		if ( $existing ) { return hash_equals( $existing['request_hash'], $hash ) ? array( 'status' => 'replayed', 'id' => (int) $existing['id'] ) : array( 'status' => 'idempotency_conflict' ); }
		if ( ! (int) $config['enabled'] ) { return array( 'status' => 'unavailable' ); }
		$available = hj_booking_slots_locked( $db, $doctor, $config, $date );
		if ( $available === false ) { return array( 'status' => 'invalid_schedule' ); }
		if ( $expected_revision !== null && $available['revision'] !== $expected_revision ) { return array( 'status' => 'revision_conflict' ); }
		if ( ! in_array( $start, $available['slots'], true ) ) { return array( 'status' => 'invalid_slot' ); }
		$pending = hj_booking_row( $db, $db->prepare( "SELECT COUNT(*) AS n FROM `$table` WHERE patient_id=%d AND status='pending'", $patient ) );
		if ( ! $pending ) { throw new RuntimeException( 'Pending count failed' ); }
		if ( (int) $pending['n'] >= 3 ) { return array( 'status' => 'pending_limit' ); }
		if ( isset( $available['intervals'] ) ) {
			if ( ! isset( $available['intervals'][$start]['end'], $available['intervals'][$start]['blocked_until'] ) ) { return array( 'status'=>'invalid_schedule' ); }
			$end = $available['intervals'][$start]['end']; $blocked = $available['intervals'][$start]['blocked_until'];
		} else {
			$end = gmdate( 'Y-m-d H:i:s', $when->getTimestamp() + (int) $config['duration_minutes'] * 60 );
			$blocked = gmdate( 'Y-m-d H:i:s', $when->getTimestamp() + ( (int) $config['duration_minutes'] + (int) $config['buffer_minutes'] ) * 60 );
		}
		$n = hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (doctor_id,patient_id,request_key,request_hash,starts_at,ends_at,blocked_until,schedule_revision,status) VALUES (%d,%d,%s,%s,%s,%s,%s,%d,'pending')", $doctor, $patient, $key, $hash, $start, $end, $blocked, $available['revision'] ) );
		if ( $n !== 1 ) { throw new RuntimeException( 'Booking insert failed' ); }
		$id = (int) $db->insert_id;
		if ( 1 !== hj_booking_query( $db, $db->prepare( "INSERT INTO `$details` (appointment_id,phone,national_code) VALUES (%d,%s,%s)", $id, $phone, $national ) ) ) { throw new RuntimeException( 'Booking details insert failed' ); }
		return array( 'status' => 'created', 'id' => $id, 'revision' => $available['revision'] );
	} );
}
function hj_booking_change_status( $doctor, $id, $status ) {
	return hj_booking_transition_status( $doctor, $id, $status, false );
}
function hj_booking_patient_cancel( $doctor, $id ) {
	return hj_booking_transition_status( $doctor, $id, 'cancelled', true );
}
/** All capacity-changing transitions and authorization rechecks share the doctor's mutex. */
function hj_booking_transition_status( $doctor, $id, $status, $patient_cancel ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'read' ) || get_post_type( $doctor ) !== 'doctor' ) { return array( 'status' => 'forbidden' ); }
	if ( ! $patient_cancel && ! hj_booking_can_manage( $doctor ) ) { return array( 'status' => 'forbidden' ); }
	if ( ! in_array( $status, array( 'confirmed', 'cancelled', 'completed', 'no_show' ), true ) || ! is_int( $id ) || $id < 1 ) { return array( 'status' => 'invalid' ); }
	$actor = get_current_user_id();
	return hj_booking_transaction( $doctor, 'status', function ( $db ) use ( $doctor, $id, $status, $patient_cancel, $actor ) {
		$table = hj_booking_table( 'appointments' );
		if ( ! $patient_cancel && ! current_user_can( 'manage_options' ) && ! ( current_user_can( HJ_Doctors_Roles::CAP_EDIT_OWN ) && hj_doctors_user_owns_profile( $actor, $doctor ) ) ) {
			$assignments = hj_booking_table( 'secretaries' );
			$mapping = hj_booking_row( $db, $db->prepare( "SELECT user_id FROM `$assignments` WHERE user_id=%d AND doctor_id=%d FOR UPDATE", $actor, $doctor ) );
			if ( ! current_user_can( 'hj_manage_appointments' ) || ! $mapping ) { return array( 'status' => 'forbidden' ); }
		}
		$row = hj_booking_row( $db, $db->prepare( "SELECT patient_id,status,starts_at FROM `$table` WHERE doctor_id=%d AND id=%d FOR UPDATE", $doctor, $id ) );
		if ( ! $row || ( $patient_cancel && (int) $row['patient_id'] !== $actor ) ) { return array( 'status' => 'forbidden' ); }
		if ( $row['status'] === $status ) { return array( 'status' => 'updated' ); }
		if ( ! in_array( $row['status'], array( 'pending', 'confirmed' ), true ) ) { return array( 'status' => 'invalid_transition' ); }
		// Completed/no-show must not silently release a future appointment.
		if ( in_array( $status, array( 'completed', 'no_show' ), true ) && $row['starts_at'] > gmdate( 'Y-m-d H:i:s' ) ) { return array( 'status' => 'too_early' ); }
		$n = hj_booking_query( $db, $db->prepare( "UPDATE `$table` SET status=%s WHERE doctor_id=%d AND id=%d", $status, $doctor, $id ) );
		return array( 'status' => $n === 1 ? 'updated' : 'error' );
	} );
}

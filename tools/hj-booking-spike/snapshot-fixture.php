<?php
/** Canonical snapshot experiment and divergence counterexample; disposable CI only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
function get_field( $name, $id ) { return $name === 'working_hours' ? get_post_meta( $id, 'fixture_acf_result', true ) : false; }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/policy-validation.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/schedule-reader-experiment.php'; require __DIR__ . '/schedule-write-experiment.php'; require __DIR__ . '/snapshot-experiment.php';
function snapshot_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
$doctor = get_user_by( 'login', 'synthetic_doctor_a' ); $patient = get_user_by( 'login', 'synthetic_patient' );
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic snapshot', 'post_author' => $doctor->ID ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( 'Synthetic profile failed' ); }
update_option( 'timezone_string', 'Asia/Tehran' );
$raw = array( array( 'day_of_week' => 'wednesday', 'is_closed' => '0', 'start_time' => '09:00', 'end_time' => '12:00' ) );
update_post_meta( $doc, 'fixture_acf_result', $raw );
$db = hj_interval_connection();
try { hj_interval_query( $db, 'INSERT INTO hjt_hjb_spike_doctors VALUES (?, ?, ?, ?, ?)', 'iiiss', $doc, 30, 5, '2030-01-01 00:00:00', '2030-01-10 00:00:00' ); } finally { $db->close(); }
$now = strtotime( '2030-01-01 UTC' ); $key = static function ( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); };
wp_set_current_user( $patient->ID );
snapshot_expect( hj_spike_publish_snapshot( $doc, $raw, 0 )['status'] === 'forbidden', 'patient cannot publish snapshot' );
snapshot_expect( hj_spike_snapshot_create( $doc, '2030-01-02 05:30:00', $key( 15001 ), '09123456789', '1234567891', $now )['status'] === 'schedule_unsupported', 'missing snapshot fails closed' );
wp_set_current_user( $doctor->ID );
$read = hj_spike_read_schedule( $doc );
snapshot_expect( $read['status'] === 'ok' && hj_spike_publish_snapshot( $doc, $read['rows'], 0 )['status'] === 'published', 'publish normalized mocked ACF snapshot under doctor mutex' );
snapshot_expect( hj_spike_publish_snapshot( $doc, $raw, 0 )['status'] === 'revision_conflict', 'stale schedule revision rejected' );
wp_set_current_user( $patient->ID );
snapshot_expect( hj_spike_snapshot_create( $doc, '2030-01-02 05:30:00', $key( 15001 ), '09123456789', '1234567891', $now )['status'] === 'created', 'booking from committed snapshot' );
// Expected limitation: an independent legacy writer changes the mocked ACF
// source, but cannot automatically publish/invalidate the canonical snapshot.
update_post_meta( $doc, 'fixture_acf_result', array( array( 'day_of_week' => 'wednesday', 'is_closed' => '1', 'start_time' => '', 'end_time' => '' ) ) );
snapshot_expect( hj_spike_snapshot_create( $doc, '2030-01-02 06:05:00', $key( 15002 ), '09123456789', '1234567891', $now )['status'] === 'created', 'EXPECTED LIMITATION: independent ACF write leaves canonical snapshot stale' );
wp_set_current_user( $doctor->ID );
$read = hj_spike_read_schedule( $doc );
snapshot_expect( hj_spike_publish_snapshot( $doc, $read['rows'], 1 )['status'] === 'published', 'coordinated publish advances revision' );
wp_set_current_user( $patient->ID );
snapshot_expect( hj_spike_snapshot_create( $doc, '2030-01-02 06:40:00', $key( 15003 ), '09123456789', '1234567891', $now )['status'] === 'invalid_slot', 'published closed schedule rejects booking' );
$db = hj_interval_connection();
try { hj_interval_query( $db, "UPDATE hjt_hjb_spike_schedules SET rows_json='{}' WHERE doctor_id=?", 'i', $doc ); } finally { $db->close(); }
snapshot_expect( hj_spike_snapshot_create( $doc, '2030-01-02 07:15:00', $key( 15004 ), '09123456789', '1234567891', $now )['status'] === 'schedule_unsupported', 'tampered snapshot fails closed' );

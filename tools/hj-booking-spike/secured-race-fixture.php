<?php
/** Integrated race on synthetic users in the disposable CI fixture only. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
define( 'HJ_BOOKING_DISPOSABLE_FIXTURE', true );
require getenv( 'GITHUB_WORKSPACE' ) . '/plugins/hamyar-doctors/hamyar-doctors.php';
hj_doctors(); do_action( 'init' );
require __DIR__ . '/interval-engine.php'; require __DIR__ . '/policy-validation.php'; require __DIR__ . '/schedule-engine.php'; require __DIR__ . '/secured-experiment.php'; require __DIR__ . '/schedule-write-experiment.php';
function race_expect( $v, $label ) { if ( ! $v ) { throw new RuntimeException( $label ); } echo "PASS: $label\n"; }
function race_uid( $login ) { $u = get_user_by( 'login', $login ); if ( ! $u ) { throw new RuntimeException( 'Missing synthetic user' ); } return (int) $u->ID; }
$doctor = race_uid( 'synthetic_doctor_a' );
$profile = (int) get_posts( array( 'post_type' => 'doctor', 'author' => $doctor, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 1 ) )[0];
$key = static function ( $n ) { return str_pad( dechex( $n ), 32, '0', STR_PAD_LEFT ); };
$now = strtotime( '2030-01-01 UTC' ); $mode = $argv[1] ?? '';
if ( $mode === 'setup' ) {
	for ( $i = 0; $i < 12; $i++ ) { $uid = wp_insert_user( array( 'user_login' => 'synthetic_race_' . $i, 'user_pass' => 'synthetic-only', 'role' => 'subscriber' ) ); if ( is_wp_error( $uid ) ) { throw new RuntimeException( 'Cannot create synthetic user' ); } }
	wp_set_current_user( $doctor );
	$open = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) );
	race_expect( hj_spike_set_hours( $profile, $open )['status'] === 'updated', 'doctor updates fallback hours under mutex' );
	$slots = hj_schedule_slots( '2030-01-02', hj_doctors_field( 'working_hours', $profile ), 30, 0, array(), $now, 30 );
	race_expect( in_array( '2030-01-02 06:00:00', $slots, true ), 'generate real WordPress hours before race' );
} elseif ( $mode === 'worker' ) {
	$i = (int) ( $argv[2] ?? -1 ); if ( $i < 0 || $i > 11 ) { exit( 1 ); }
	wp_set_current_user( race_uid( 'synthetic_race_' . $i ) );
	touch( '/tmp/hj-secured-ready-' . $i ); $until = microtime( true ) + 30;
	while ( ! file_exists( '/tmp/hj-secured-go' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( '/tmp/hj-secured-go' ) ) { exit( 2 ); }
	$r = hj_secured_create( $profile, '2030-01-02 06:00:00', $key( 10000 + $i ), '09123456789', '1234567891', $now );
	echo $r['status'];
} elseif ( $mode === 'metadata-lock' ) {
	$r = hj_interval_transaction( $profile, function ( $db ) use ( $profile ) {
		$rows = hj_interval_query( $db, "SELECT meta_id FROM hjt_postmeta WHERE post_id=? AND meta_key='working_hours' FOR UPDATE", 'i', $profile );
		if ( count( $rows ) !== 1 ) { throw new RuntimeException( 'Expected one fallback metadata row' ); }
		touch( '/tmp/hj-meta-held' ); $until = microtime( true ) + 15;
		while ( ! file_exists( '/tmp/hj-meta-release' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
		if ( ! file_exists( '/tmp/hj-meta-release' ) ) { throw new RuntimeException( 'Metadata lock test timed out' ); }
		return array( 'status' => 'released' );
	} );
	echo $r['status'];
} elseif ( $mode === 'metadata-write' ) {
	$closed = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ) );
	update_post_meta( $profile, 'working_hours', $closed );
	echo 'wrote';
} elseif ( $mode === 'uncoordinated-setup' ) {
	wp_set_current_user( $doctor );
	$open = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) );
	race_expect( hj_spike_set_hours( $profile, $open )['status'] === 'updated' && hj_spike_set_duration( $profile, 15, 0 )['status'] === 'updated', 'prepare uncoordinated-writer counterexample' );
} elseif ( $mode === 'uncoordinated-lock' ) {
	// Reproduce validation followed by a competing legacy WP metadata writer.
	$validator = function ( $db, $config, $start ) use ( $profile, $now ) {
		clean_post_cache( $profile );
		$hours = hj_doctors_field( 'working_hours', $profile );
		$valid = in_array( $start, hj_schedule_slots( '2030-01-02', $hours, (int) $config['duration_minutes'], (int) $config['buffer_minutes'], array(), $now, 30 ), true );
		touch( '/tmp/hj-uncoordinated-held' );
		$until = microtime( true ) + 15;
		while ( ! file_exists( '/tmp/hj-uncoordinated-done' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
		if ( ! file_exists( '/tmp/hj-uncoordinated-done' ) ) { throw new RuntimeException( 'Counterexample writer timed out' ); }
		return $valid;
	};
	$r = hj_interval_create( $profile, race_uid( 'synthetic_race_0' ), '2030-01-02 07:30:00', $key( 14000 ), '', $validator );
	echo $r['status'];
} elseif ( $mode === 'uncoordinated-write' ) {
	$closed = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ) );
	update_post_meta( $profile, 'working_hours', $closed );
	touch( '/tmp/hj-uncoordinated-done' );
	echo 'wrote';
} elseif ( $mode === 'uncoordinated-verify' ) {
	clean_post_cache( $profile ); $hours = hj_doctors_field( 'working_hours', $profile );
	$db = hj_interval_connection();
	try { $rows = hj_interval_query( $db, "SELECT id FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND starts_at='2030-01-02 07:30:00' AND status='pending'", 'i', $profile ); } finally { $db->close(); }
	race_expect( ! empty( $hours[0]['is_closed'] ) && count( $rows ) === 1, 'EXPECTED COUNTEREXAMPLE: existing metadata writer bypasses lock and permits stale-hours booking' );
} elseif ( $mode === 'mutation-setup' ) {
	wp_set_current_user( $doctor );
	$open = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 0, 'start_time' => '09:00:00', 'end_time' => '12:00:00' ) );
	race_expect( hj_spike_set_hours( $profile, $open )['status'] === 'updated' && hj_spike_set_duration( $profile, 30, 0 )['status'] === 'updated', 'prepare coordinated mutation race' );
	wp_set_current_user( race_uid( 'synthetic_secretary' ) );
	race_expect( hj_spike_set_hours( $profile, $open )['status'] === 'forbidden' && hj_spike_set_duration( $profile, 15, 0 )['status'] === 'forbidden', 'secretary cannot change settings' );
} elseif ( $mode === 'mutation-worker' || $mode === 'mutation-writer' ) {
	$i = $mode === 'mutation-writer' ? 12 : (int) ( $argv[2] ?? -1 );
	if ( $i < 0 || $i > 12 ) { exit( 1 ); }
	wp_set_current_user( $i === 12 ? $doctor : race_uid( 'synthetic_race_' . $i ) );
	touch( '/tmp/hj-mutation-ready-' . $i ); $until = microtime( true ) + 30;
	while ( ! file_exists( '/tmp/hj-mutation-go' ) && microtime( true ) < $until ) { usleep( 10000 ); clearstatcache(); }
	if ( ! file_exists( '/tmp/hj-mutation-go' ) ) { exit( 2 ); }
	if ( $i === 12 ) {
		$a = hj_spike_set_duration( $profile, 15, 0 );
		$closed = array( array( 'day_of_week' => 'wednesday', 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ) );
		$b = hj_spike_set_hours( $profile, $closed );
		echo $a['status'] === 'updated' && $b['status'] === 'updated' ? 'updated' : 'failed';
	} else {
		$start = $i % 2 ? '2030-01-02 06:45:00' : '2030-01-02 06:30:00';
		echo hj_secured_create( $profile, $start, $key( 12000 + $i ), '09123456789', '1234567891', $now )['status'];
	}
} elseif ( $mode === 'mutation-verify' ) {
	$db = hj_interval_connection();
	try {
		$conflicts = hj_interval_query( $db, "SELECT a.id FROM hjt_hjb_spike_appointments a JOIN hjt_hjb_spike_appointments b ON a.doctor_id=b.doctor_id AND a.id<b.id AND a.starts_at < b.blocked_until AND a.blocked_until > b.starts_at WHERE a.doctor_id=? AND a.status IN ('pending','confirmed') AND b.status IN ('pending','confirmed')", 'i', $profile );
		race_expect( ! $conflicts, 'no active overlaps after concurrent duration and hours edits' );
	} finally { $db->close(); }
	wp_set_current_user( race_uid( 'synthetic_race_0' ) );
	race_expect( hj_secured_create( $profile, '2030-01-02 07:30:00', $key( 13000 ), '09123456789', '1234567891', $now )['status'] === 'invalid_slot', 'closed day blocks new requests after edit' );
} elseif ( $mode === 'finish' ) {
	$db = hj_interval_connection();
	try { $rows = hj_interval_query( $db, "SELECT id FROM hjt_hjb_spike_appointments WHERE doctor_id=? AND starts_at='2030-01-02 06:00:00' AND status='pending'", 'i', $profile ); } finally { $db->close(); }
	race_expect( count( $rows ) === 1, 'exactly one actual scoped booking from concurrent race' );
	wp_set_current_user( race_uid( 'synthetic_secretary' ) );
	race_expect( hj_secured_change_status( $profile, (int) $rows[0]['id'], 'cancelled' )['status'] === 'updated', 'secretary cancels winner' );
	wp_set_current_user( race_uid( 'synthetic_race_0' ) );
	$r = hj_secured_create( $profile, '2030-01-02 06:00:00', $key( 11000 ), '09123456789', '1234567891', $now );
	race_expect( $r['status'] === 'created', 'patient rebooks same slot after cancellation' );
	wp_set_current_user( $doctor ); race_expect( hj_secured_change_status( $profile, $r['id'], 'confirmed' )['status'] === 'updated', 'doctor confirms rebooking' );
	wp_set_current_user( race_uid( 'synthetic_secretary' ) ); race_expect( hj_secured_change_status( $profile, $r['id'], 'completed' )['status'] === 'updated', 'secretary completes rebooking' );
	wp_set_current_user( race_uid( 'fixture_admin' ) ); $found = hj_secured_read( 'admin', $profile, 1, 50, 'completed', '2030-01-02 06:00:00', '2030-01-02 06:00:01' );
	race_expect( count( $found['rows'] ) === 1, 'admin sees completed rebooking with filters' );
} else { exit( 1 ); }

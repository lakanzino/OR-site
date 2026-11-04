<?php
/**
 * EXPERIMENTAL FIXTURE ONLY: synthetic tables in an empty disposable WordPress.
 * Not a plugin, endpoint, migration, or implementation for the real vote table.
 * Never run against a real site, staging copy, or any real-site backup.
 */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST || ! defined( 'ABSPATH' ) || '/wordpress/' !== ABSPATH ) {
	exit( 'Disposable WordPress fixture only.' );
}
global $wpdb;
$is_sqlite = class_exists( 'WP_SQLite_Driver' );
$is_ci_mariadb = getenv( 'GITHUB_ACTIONS' ) === 'true' && getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) === '1'
	&& DB_NAME === 'hj_fixture_db' && DB_HOST === '127.0.0.1:3306' && $wpdb->prefix === 'hjt_' && WP_HOME === 'http://example.invalid';
if ( ! $is_sqlite && ! $is_ci_mariadb ) { exit( 'Unexpected fixture environment.' ); }

function hj_mod_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	echo 'PASS: ' . $message . "\n";
}

// Deliberately unrelated to any production vote table.
$hj_mod_votes = $wpdb->prefix . 'hj_mod_fixture_votes';
$hj_mod_audit = $wpdb->prefix . 'hj_mod_fixture_audit';
$fixture_tables = $wpdb->get_col( 'SHOW TABLES' );
if ( $wpdb->last_error !== '' || ! is_array( $fixture_tables ) ) { throw new RuntimeException( 'Cannot verify fixture tables.' ); }
foreach ( array( $hj_mod_votes, $hj_mod_audit ) as $table ) {
	if ( ! preg_match( '/\A[A-Za-z0-9_]+\z/', $table ) || in_array( $table, $fixture_tables, true ) ) {
		throw new RuntimeException( 'Fixture table exists, is invalid, or cannot be checked.' );
	}
}
$engine = $is_ci_mariadb ? ' ENGINE=InnoDB' : '';
hj_mod_assert( false !== $wpdb->query( "CREATE TABLE `{$hj_mod_votes}` (id INTEGER PRIMARY KEY, status VARCHAR(20) NOT NULL, rating INTEGER NOT NULL, revision INTEGER NOT NULL)" . $engine ), 'create synthetic votes' );
hj_mod_assert( false !== $wpdb->query( "CREATE TABLE `{$hj_mod_audit}` (vote_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, old_status VARCHAR(20) NOT NULL, new_status VARCHAR(20) NOT NULL, old_rating INTEGER NOT NULL, new_rating INTEGER NOT NULL, old_revision INTEGER NOT NULL, occurred_utc VARCHAR(20) NOT NULL, reason VARCHAR(1000) NOT NULL, CHECK (reason <> 'simulate_audit_failure'))" . $engine ), 'create synthetic audit' );
hj_mod_assert( 1 === $wpdb->insert( $hj_mod_votes, array( 'id' => 7, 'status' => 'pending', 'rating' => 3, 'revision' => 1 ) ), 'create one synthetic vote' );

/** Prototype contract for the fixture only; no WordPress action is registered. */
function hj_mod_fixture_apply( $request ) {
	global $wpdb, $hj_mod_votes, $hj_mod_audit;
	if ( ! current_user_can( 'manage_options' ) ) { return 'denied'; }
	if ( ! is_array( $request ) || ( $request['method'] ?? '' ) !== 'POST' ) { return 'method'; }
	$id = $request['id'] ?? null; $rev = $request['revision'] ?? null;
	if ( ! is_int( $id ) || $id < 1 || ! is_int( $rev ) || $rev < 1 ) { return 'invalid'; }
	$nonce = $request['nonce'] ?? '';
	if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'hj_mod_fixture_' . $id . '_' . $rev ) ) { return 'nonce'; }
	$status = $request['status'] ?? null; $rating = $request['rating'] ?? null; $reason = $request['reason'] ?? null;
	if ( ! is_string( $status ) || ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) || ! is_int( $rating ) || $rating < 1 || $rating > 5 || ! is_string( $reason ) ) { return 'invalid'; }
	$reason = trim( sanitize_textarea_field( $reason ) );
	if ( $reason === '' || strlen( $reason ) > 1000 ) { return 'invalid'; }
	$old = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, rating, revision FROM `{$hj_mod_votes}` WHERE id = %d", $id ), ARRAY_A );
	if ( ! is_array( $old ) || (int) $old['revision'] !== $rev ) { return 'conflict'; }
	if ( $old['status'] === $status && (int) $old['rating'] === $rating ) { return 'unchanged'; }
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return 'transaction_error'; }
	try {
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE `{$hj_mod_votes}` SET status = %s, rating = %d, revision = revision + 1 WHERE id = %d AND revision = %d", $status, $rating, $id, $rev ) );
		if ( 1 !== $updated ) { $wpdb->query( 'ROLLBACK' ); return false === $updated ? 'write_error' : 'conflict'; }
		$audit = array( 'vote_id' => $id, 'actor_id' => get_current_user_id(), 'old_status' => $old['status'], 'new_status' => $status, 'old_rating' => (int) $old['rating'], 'new_rating' => $rating, 'old_revision' => $rev, 'occurred_utc' => gmdate( 'Y-m-d H:i:s' ), 'reason' => $reason );
		if ( 1 !== $wpdb->insert( $hj_mod_audit, $audit ) ) { $wpdb->query( 'ROLLBACK' ); return 'audit_error'; }
		if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return 'commit_unknown'; }
		return 'ok';
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		return 'transaction_error';
	}
}
function hj_mod_fixture_snapshot() {
	global $wpdb, $hj_mod_votes, $hj_mod_audit;
	return array( $wpdb->get_results( "SELECT id, status, rating, revision FROM `{$hj_mod_votes}` ORDER BY id", ARRAY_A ), $wpdb->get_results( "SELECT vote_id, actor_id, old_status, new_status, old_rating, new_rating, old_revision, occurred_utc, reason FROM `{$hj_mod_audit}` ORDER BY old_revision", ARRAY_A ) );
}
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
hj_mod_assert( count( $admins ) === 1, 'fixture administrator exists' );
$admin = (int) $admins[0]->ID;
$subscriber = wp_create_user( 'synthetic_moderation_subscriber', 'not-a-real-password', 'moderation-subscriber@example.invalid' );
hj_mod_assert( ! is_wp_error( $subscriber ), 'create synthetic subscriber' );
if ( ! get_role( 'doctor' ) ) { add_role( 'doctor', 'Fixture doctor', array( 'read' => true ) ); }
$doctor = wp_create_user( 'synthetic_moderation_doctor', 'not-a-real-password', 'moderation-doctor@example.invalid' );
hj_mod_assert( ! is_wp_error( $doctor ), 'create synthetic doctor' );
( new WP_User( $doctor ) )->set_role( 'doctor' );
wp_set_current_user( $admin );
$request = array( 'method' => 'POST', 'id' => 7, 'revision' => 1, 'nonce' => wp_create_nonce( 'hj_mod_fixture_7_1' ), 'status' => 'approved', 'rating' => 4, 'reason' => 'Synthetic moderation reason' );
$initial = hj_mod_fixture_snapshot();
wp_set_current_user( 0 ); hj_mod_assert( hj_mod_fixture_apply( $request ) === 'denied', 'anonymous denied' );
wp_set_current_user( $subscriber ); hj_mod_assert( hj_mod_fixture_apply( $request ) === 'denied', 'subscriber denied even with admin nonce' );
wp_set_current_user( $doctor ); hj_mod_assert( hj_mod_fixture_apply( $request ) === 'denied', 'doctor denied even with admin nonce' );
wp_set_current_user( $admin );
foreach ( array(
	array( array( 'method' => 'GET' ), 'method' ),
	array( array( 'nonce' => 'wrong' ), 'nonce' ),
	array( array( 'id' => '7' ), 'invalid' ),
	array( array( 'status' => 'deleted' ), 'invalid' ),
	array( array( 'rating' => 0 ), 'invalid' ),
	array( array( 'rating' => 6 ), 'invalid' ),
	array( array( 'reason' => '  ' ), 'invalid' ),
	array( array( 'reason' => str_repeat( 'x', 1001 ) ), 'invalid' ),
	array( array( 'status' => 'pending', 'rating' => 3 ), 'unchanged' ),
) as $case ) { hj_mod_assert( hj_mod_fixture_apply( array_merge( $request, $case[0] ) ) === $case[1], 'reject ' . $case[1] ); }
hj_mod_assert( $initial === hj_mod_fixture_snapshot(), 'denied and unchanged requests leave votes and audit untouched' );
$previous = $wpdb->suppress_errors( true );
try { $failed = hj_mod_fixture_apply( array_merge( $request, array( 'reason' => 'simulate_audit_failure' ) ) ); } finally { $wpdb->suppress_errors( $previous ); }
hj_mod_assert( $failed === 'audit_error' && $initial === hj_mod_fixture_snapshot(), 'audit failure rolls back vote change' );
hj_mod_assert( hj_mod_fixture_apply( $request ) === 'ok', 'admin approves synthetic vote and changes rating' );
$after = hj_mod_fixture_snapshot();
hj_mod_assert( $after[0][0]['status'] === 'approved' && (int) $after[0][0]['rating'] === 4 && (int) $after[0][0]['revision'] === 2, 'new vote state and revision' );
hj_mod_assert( count( $after[1] ) === 1 && (int) $after[1][0]['actor_id'] === $admin && $after[1][0]['reason'] === 'Synthetic moderation reason' && (bool) preg_match( '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $after[1][0]['occurred_utc'] ) && $after[1][0]['old_status'] === 'pending' && $after[1][0]['new_status'] === 'approved' && (int) $after[1][0]['old_rating'] === 3 && (int) $after[1][0]['new_rating'] === 4, 'audit contains actor, reason, old and new values' );
hj_mod_assert( hj_mod_fixture_apply( $request ) === 'conflict' && $after === hj_mod_fixture_snapshot(), 'stale revision rejected without another audit' );
$request['revision'] = 2; $request['nonce'] = wp_create_nonce( 'hj_mod_fixture_7_2' ); $request['status'] = 'rejected'; $request['reason'] = 'Synthetic rejection';
hj_mod_assert( hj_mod_fixture_apply( $request ) === 'ok', 'admin rejects synthetic vote' );
$last = hj_mod_fixture_snapshot();
hj_mod_assert( $last[0][0]['status'] === 'rejected' && (int) $last[0][0]['revision'] === 3 && count( $last[1] ) === 2, 'second change audited once' );
echo "DONE: synthetic moderation prototype only.\n";

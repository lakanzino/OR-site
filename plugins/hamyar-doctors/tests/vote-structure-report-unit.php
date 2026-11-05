<?php
/**
 * آزمون واحد تجمیع ساختار رأی — فقط شبیه‌ساز، هرگز روی وردپرس واقعی اجرا نمی‌شود.
 *
 * این آزمون به وردپرس، پایگاه داده یا شبکه وصل نمی‌شود؛ یک $wpdb ساختگی با
 * پاسخ‌های تجویزشده می‌سازد و هم منطق تجمیع و هم مرز ایمنی کوئری‌ها را می‌سنجد.
 */
if ( ! defined( 'HJ_VOTE_STRUCTURE_UNIT_TEST' ) || HJ_VOTE_STRUCTURE_UNIT_TEST !== true || defined( 'ABSPATH' ) ) {
	exit( "This test must run in isolation.\n" );
}
define( 'ABSPATH', '/isolated/' );
define( 'ARRAY_A', 'ARRAY_A' );
function apply_filters( $hook, $value ) { return $hook === 'hj_rb_structure_max_rows' && isset( $GLOBALS['limit'] ) ? $GLOBALS['limit'] : $value; }

class HJ_Fake_WPDB {
	public $last_error = '';
	public $queries = array();
	public $current_suppress = false;
	public $restore_calls = 0;
	private $queue = array();

	public function queue( array $items ) { $this->queue = $items; }

	public function suppress_errors( $value = null ) {
		$prev = $this->current_suppress;
		if ( null !== $value ) {
			if ( false === (bool) $value ) { ++$this->restore_calls; }
			$this->current_suppress = (bool) $value;
		}
		return $prev;
	}

	private function pop( $sql ) {
		$this->queries[] = $sql;
		$next = array_shift( $this->queue );
		if ( ! is_array( $next ) ) { return null; }
		if ( ! empty( $next['error'] ) ) { $this->last_error = 'simulated probe failure'; return null; }
		return array_key_exists( 'value', $next ) ? $next['value'] : null;
	}

	public function get_var( $sql = null ) { return $this->pop( $sql ); }
	public function get_row( $sql = null, $output = null ) { return $this->pop( $sql ); }
	public function get_results( $sql = null, $output = null ) { return $this->pop( $sql ); }
}

require_once '/plugin/includes/vote-structure-report.php';

$GLOBALS['failures'] = 0;
function hj_unit_assert( $condition, $message ) {
	if ( $condition ) {
		echo 'PASS: ' . $message . "\n";
		return;
	}
	++$GLOBALS['failures'];
	echo 'FAIL: ' . $message . "\n";
}

function hj_unit_schema( array $overrides = array() ) {
	$schema = array(
		'ok' => true,
		'table' => 'wp_hj_doctor_votes',
		'cols' => array( 'id', 'doctor_id', 'rating', 'status' ),
		'map' => array( 'id' => 'id', 'doctor_id' => 'doctor_id', 'rating' => 'rating', 'status' => 'status' ),
		'status_numeric' => false,
		'probe_error' => false,
	);
	foreach ( $overrides as $key => $value ) { $schema[ $key ] = $value; }
	return $schema;
}

/** هیچ کوئری نوشتنی یا خواندن ردیف/شناسه مجاز نیست. */
function hj_unit_assert_read_only( array $queries, $context ) {
	$bad = array();
	foreach ( $queries as $sql ) {
		if ( ! preg_match( '/^SELECT\s/i', $sql ) ) { $bad[] = $sql; continue; }
		if ( preg_match( '/\b(INSERT|UPDATE|DELETE|REPLACE|DROP|ALTER|TRUNCATE|CREATE|GRANT)\b/i', $sql ) ) { $bad[] = $sql; continue; }
		if ( preg_match( '/\bSELECT\s+\*/i', $sql ) ) { $bad[] = $sql; continue; }
		if ( preg_match( '/\b(ip_hash|user_ip|voter_ip|user_id|doctor_id)\b/i', $sql ) ) { $bad[] = $sql; }
	}
	hj_unit_assert( array() === $bad, $context . ': every query is an aggregate SELECT without row or identity columns' );
}

// ۱) جدول یافت نشد.
$wpdb = new HJ_Fake_WPDB();
$state = hj_doctors_vote_structure_state( array( 'ok' => false, 'table' => '', 'probe_error' => false ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, array( 'ok' => false, 'table' => '', 'probe_error' => false ) );
hj_unit_assert( 'missing' === $state['table'], 'absent table is reported as missing, not as an error' );
hj_unit_assert( null === $counts['rows'] && 'unknown' === $counts['rows_state'], 'missing table leaves the row count unknown' );
hj_unit_assert( array() === $wpdb->queries, 'missing table runs no query at all' );

// ۲) خطای بررسی با نبود جدول یکی نیست.
$state = hj_doctors_vote_structure_state( array( 'ok' => false, 'table' => '', 'probe_error' => true ) );
hj_unit_assert( 'error' === $state['table'], 'failed probe is reported as an error, not as a missing table' );

// ۳) شمار ردیف و نبود ستون‌ها.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '7' ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema( array( 'map' => array( 'id' => '', 'doctor_id' => 'doctor_id', 'rating' => 'rating', 'status' => '' ) ) ) );
hj_unit_assert( 7 === $counts['rows'] && 'measured' === $counts['rows_state'], 'row count is measured from COUNT(*)' );
hj_unit_assert( 'absent' === $counts['id_unique'], 'absent id column is not claimed unique' );
hj_unit_assert( 'absent' === $counts['status_state'], 'absent status column is reported as absent' );
hj_unit_assert( 1 === count( $wpdb->queries ), 'no aggregates run beyond the row count when no column is mapped' );
hj_unit_assert_read_only( $wpdb->queries, 'no column mapped' );
hj_unit_assert( false === $wpdb->current_suppress, 'error suppression is restored after the aggregate' );

// ۴) شناسه یکتا.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '5' ), array( 'value' => array( 'total_rows' => '5', 'distinct_ids' => '5', 'null_ids' => '0' ) ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 'unique' === $counts['id_unique'], 'equal total and distinct counts are reported as unique' );

// ۵) شناسه تکراری.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '5' ), array( 'value' => array( 'total_rows' => '5', 'distinct_ids' => '4', 'null_ids' => '0' ) ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 'duplicates' === $counts['id_unique'], 'fewer distinct ids than rows are reported as duplicates' );

// ۶) شناسه با مقدار خالی.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '5' ), array( 'value' => array( 'total_rows' => '5', 'distinct_ids' => '5', 'null_ids' => '2' ) ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 'nulls' === $counts['id_unique'], 'a nullable id column is not reported as unique' );

// ۷) خطای کوئری شناسه → نامعلوم، نه یکتا.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '5' ), array( 'error' => true ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 'unknown' === $counts['id_unique'], 'a failed id query leaves uniqueness unknown' );

// ۸) توزیع وضعیت.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array(
	array( 'value' => '9' ),
	array( 'value' => array( 'total_rows' => '9', 'distinct_ids' => '9', 'null_ids' => '0' ) ),
	array( 'value' => array(
		array( 'vote_status' => 'approved', 'vote_count' => '6' ),
		array( 'vote_status' => 'pending', 'vote_count' => '3' ),
	) ),
) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 'measured' === $counts['status_state'] && 2 === count( $counts['status_values'] ), 'status distribution is measured when the column exists' );
hj_unit_assert( 'approved' === $counts['status_values'][0]['label'] && 6 === $counts['status_values'][0]['count'], 'status labels and counts keep the query order' );
hj_unit_assert( false === $counts['status_more'], 'a short distribution is not marked truncated' );
hj_unit_assert_read_only( $wpdb->queries, 'status distribution' );

// ۹) سقف ۱۲ مقدار.
$many = array();
for ( $i = 0; $i < 13; $i++ ) { $many[] = array( 'vote_status' => 'v' . $i, 'vote_count' => '1' ); }
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '13' ), array( 'value' => array( 'total_rows' => '13', 'distinct_ids' => '13', 'null_ids' => '0' ) ), array( 'value' => $many ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( true === $counts['status_more'] && 12 === count( $counts['status_values'] ), 'more than 12 status values are truncated and flagged' );

// ۱۰) سقف ردیف: تجمیع اجرا نمی‌شود.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '500' ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema(), 100 );
hj_unit_assert( 'measured_skipped' === $counts['rows_state'], 'a table above the row cap is counted but not aggregated' );
hj_unit_assert( 1 === count( $wpdb->queries ), 'the row cap stops further queries' );
$GLOBALS['limit'] = 10;
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '50' ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 'measured_skipped' === $counts['rows_state'], 'the row cap can be lowered through the filter' );
unset( $GLOBALS['limit'] );

// ۱۱) نام جدول ناامن → هیچ کوئری.
$wpdb = new HJ_Fake_WPDB();
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema( array( 'table' => 'wp_votes`; DROP TABLE x; --' ) ) );
hj_unit_assert( array() === $wpdb->queries && null === $counts['rows'], 'an unsafe table name runs no query' );

// ۱۲) خطای شمار کل.
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'error' => true ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( null === $counts['rows'] && 'unknown' === $counts['rows_state'], 'a failed count is unknown, not zero' );

// ۱۳) مقدار وضعیت بلند کوتاه و پاک می‌شود.
$long = str_repeat( 'x', 60 );
$wpdb = new HJ_Fake_WPDB();
$wpdb->queue( array( array( 'value' => '1' ), array( 'value' => array( 'total_rows' => '1', 'distinct_ids' => '1', 'null_ids' => '0' ) ), array( 'value' => array( array( 'vote_status' => $long, 'vote_count' => '1' ) ) ) ) );
$counts = hj_doctors_vote_structure_counts( $wpdb, hj_unit_schema() );
hj_unit_assert( 40 === strlen( substr( $counts['status_values'][0]['label'], 0, 40 ) ) && '…' === substr( $counts['status_values'][0]['label'], -3 ), 'a long status value is cut at 40 bytes and ends with an ellipsis' );

// ۱۴) برچسب‌های فارسی.
$lines = hj_doctors_vote_structure_lines( array( 'table' => 'error', 'columns' => 'none' ), array( 'rows' => null, 'rows_state' => 'unknown', 'id_unique' => 'unknown', 'status_state' => 'absent', 'status_values' => array(), 'status_more' => false ) );
hj_unit_assert( 'بررسی ناموفق بود' === $lines[0][1], 'the error state has its own Persian label' );
hj_unit_assert( 'شناسایی نشد؛ نمایش عمومی بدون فیلتر تأیید است' === $lines[4][1], 'an absent status column says the public list is unfiltered' );
$lines = hj_doctors_vote_structure_lines( array( 'table' => 'found', 'columns' => 'detected' ), array( 'rows' => 9, 'rows_state' => 'measured', 'id_unique' => 'unique', 'status_state' => 'measured', 'status_values' => array( array( 'label' => 'approved', 'count' => 6 ) ), 'status_more' => true ) );
hj_unit_assert( '9' === $lines[2][1] && 'یکتا' === $lines[3][1], 'measured rows and uniqueness use plain numbers and labels' );
hj_unit_assert( false !== strpos( $lines[5][1], 'approved: 6' ) && false !== strpos( $lines[5][1], 'بیشتر' ), 'the distribution line lists values and marks truncation' );

if ( $GLOBALS['failures'] > 0 ) {
	throw new Exception( $GLOBALS['failures'] . ' assertion(s) failed' );
}
echo "DONE: vote structure aggregate unit test.\n";

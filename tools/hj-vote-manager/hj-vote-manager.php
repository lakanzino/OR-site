<?php
/**
 * Plugin Name: HJ Vote Manager (Demo Only)
 * Description: Administrator-only moderation of votes for marked demo doctors, with an audit trail.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Hamyar Jarahi
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'HJ_Vote_Manager_Demo' ) ) {
final class HJ_Vote_Manager_Demo {
	const VERSION = '0.1.0';
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_hj_vote_manager_save', array( __CLASS__, 'handle_save' ) );
	}
	public static function menu() {
		$hook = add_management_page( 'مدیریت رأی جراحان نمونه', 'مدیریت رأی نمونه', 'manage_options', 'hj-vote-manager', array( __CLASS__, 'page' ) );
		if ( $hook ) { add_action( 'load-' . $hook, 'nocache_headers' ); }
	}
	private static function problem( $message ) { return new WP_Error( 'hj_vote_manager', $message ); }
	private static function valid_identifier( $name ) { return is_string( $name ) && preg_match( '/\A[A-Za-z0-9_]+\z/', $name ); }
	private static function demo_doctor( $doctor ) { return $doctor > 0 && get_post_type( $doctor ) === 'doctor' && get_post_status( $doctor ) !== 'trash' && (string) get_post_meta( $doctor, '_hj_demo_doctor', true ) !== ''; }
	private static function pick( $columns, $candidates ) { foreach ( $candidates as $c ) { if ( isset( $columns[ $c ] ) ) { return $c; } } return ''; }
	private static function engine( $table ) {
		global $wpdb;
		if ( ! self::valid_identifier( $table ) ) { return ''; }
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		return $wpdb->last_error === '' ? strtoupper( (string) $value ) : '';
	}
	/** Require a single-column primary key and matching profile/bridge mappings. */
	public static function inspect() {
		global $wpdb;
		if ( ! is_callable( array( 'HJ_Doctors_Rating_Bridge', 'schema' ) ) ) { return self::problem( 'افزونه همیار پزشک‌ها یا پل رأی در دسترس نیست.' ); }
		$old = $wpdb->suppress_errors( true );
		try {
			$schema = HJ_Doctors_Rating_Bridge::schema(); $table = $wpdb->prefix . 'hj_doctor_votes';
			if ( ! self::valid_identifier( $table ) || ! is_array( $schema ) || empty( $schema['ok'] ) || ( $schema['table'] ?? '' ) !== $table || ! is_array( $schema['map'] ?? null ) ) { return self::problem( 'جدول رأی پل و پروفایل سازگار نیست.' ); }
			$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
			if ( $wpdb->last_error !== '' || ! is_array( $rows ) || ! $rows ) { return self::problem( 'ساختار جدول رأی خوانده نشد.' ); }
			$columns = array();
			foreach ( $rows as $row ) {
				$name = $row['Field'] ?? null;
				if ( ! self::valid_identifier( $name ) || ! is_string( $row['Type'] ?? null ) || ! is_string( $row['Null'] ?? null ) ) { return self::problem( 'ساختار جدول پشتیبانی نمی‌شود.' ); }
				$columns[ $name ] = $row;
			}
			$id = $schema['map']['id'] ?? ''; $doctor = self::pick( $columns, array( 'doctor_id', 'post_id', 'doctor' ) );
			$rating = self::pick( $columns, array( 'rating', 'stars', 'score', 'vote', 'value' ) );
			$status = self::pick( $columns, array( 'status', 'is_approved', 'approved', 'state' ) );
			$comment = self::pick( $columns, array( 'comment', 'review', 'content', 'body', 'text' ) );
			if ( ! self::valid_identifier( $id ) || ! isset( $columns[ $id ] ) || ! $doctor || ! $rating || ! $status || ! $comment || ( $schema['map']['doctor_id'] ?? '' ) !== $doctor || ( $schema['map']['rating'] ?? '' ) !== $rating || ( $schema['map']['status'] ?? '' ) !== $status ) { return self::problem( 'شناسه، نظر، وضعیت یا نگاشت‌های لازم پیدا نشد.' ); }
			$integer = '/\A(?:tinyint|smallint|mediumint|int|integer|bigint)(?:\(\d+\))?(?: unsigned)?\z/i';
			foreach ( array( $id, $doctor, $rating ) as $col ) { if ( ! preg_match( $integer, $columns[ $col ]['Type'] ) ) { return self::problem( 'نوع شناسه یا امتیاز پشتیبانی نمی‌شود.' ); } }
			$status_type = strtolower( trim( $columns[ $status ]['Type'] ) );
			if ( ! preg_match( '/\A(?:char|varchar)\((\d+)\)\z/', $status_type, $sm ) || (int) $sm[1] < 8 ) { return self::problem( 'این نسخه به ستون وضعیت متنی سازگار نیاز دارد.' ); }
			$comment_type = strtolower( trim( $columns[ $comment ]['Type'] ) );
			if ( ! preg_match( '/\A(?:varchar\((\d+)\)|char\((\d+)\)|tinytext|text|mediumtext|longtext)\z/', $comment_type, $cm ) ) { return self::problem( 'نوع ستون نظر پشتیبانی نمی‌شود.' ); }
			$limit = ! empty( $cm[1] ) ? (int) $cm[1] : ( ! empty( $cm[2] ) ? (int) $cm[2] : ( $comment_type === 'tinytext' ? 255 : 65535 ) );
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
			if ( $wpdb->last_error !== '' || ! is_array( $indexes ) ) { return self::problem( 'کلید اصلی جدول قابل بررسی نیست.' ); }
			$pk = array(); foreach ( $indexes as $index ) { if ( strtoupper( (string) ( $index['Key_name'] ?? '' ) ) === 'PRIMARY' ) { $pk[ (int) ( $index['Seq_in_index'] ?? 0 ) ] = $index['Column_name'] ?? ''; } }
			ksort( $pk, SORT_NUMERIC );
			if ( array_keys( $pk ) !== array( 1 ) || array_values( $pk ) !== array( $id ) ) { return self::problem( 'کلید اصلی تکی و سازگار برای شناسه رأی پیدا نشد.' ); }
			return array( 'table' => $table, 'id' => $id, 'doctor' => $doctor, 'rating' => $rating, 'status' => $status, 'comment' => $comment, 'comment_limit' => $limit );
		} catch ( Throwable $e ) { return self::problem( 'بررسی ساختار جدول ناموفق بود.' ); }
		finally { $wpdb->suppress_errors( $old ); }
	}
	private static function snapshot( $row ) {
		$payload = wp_json_encode( array( (string) $row['vote_id'], (string) $row['doctor_id'], (string) $row['rating'], (string) $row['status'], $row['comment'] === null ? null : (string) $row['comment'] ), JSON_UNESCAPED_UNICODE );
		return hash_hmac( 'sha256', (string) $payload, wp_salt( 'auth' ) );
	}
	/** Synthetic lab batch IDs stay stored; show only the useful text in the form. */
	private static function split_marker( $comment ) {
		$comment = (string) $comment;
		if ( preg_match( '/\A(\[نظر آزمایشی HJ-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\] )(.*)\z/us', $comment, $m ) ) { return array( $m[1], $m[2] ); }
		return array( '', $comment );
	}
	private static function row( $schema, $doctor, $vote, $for_update = false ) {
		global $wpdb;
		$sql = "SELECT `{$schema['id']}` AS vote_id, `{$schema['doctor']}` AS doctor_id, `{$schema['rating']}` AS rating, `{$schema['status']}` AS status, `{$schema['comment']}` AS comment FROM `{$schema['table']}` WHERE `{$schema['id']}` = %d AND `{$schema['doctor']}` = %d" . ( $for_update ? ' FOR UPDATE' : '' );
		return $wpdb->get_row( $wpdb->prepare( $sql, $vote, $doctor ), ARRAY_A );
	}
	private static function audit_table() { global $wpdb; return $wpdb->prefix . 'hj_vote_manager_audit_v1'; }
	/** Create audit on first save, never on activation or viewing. */
	private static function ensure_audit() {
		global $wpdb; $table = self::audit_table();
		if ( ! self::valid_identifier( $table ) ) { return self::problem( 'نام جدول گزارش تغییرات نامعتبر است.' ); }
		$existing = $wpdb->get_col( 'SHOW TABLES' );
		if ( $wpdb->last_error !== '' || ! is_array( $existing ) ) { return self::problem( 'بررسی جدول گزارش تغییرات ناموفق بود.' ); }
		if ( ! in_array( $table, $existing, true ) ) {
			$charset = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE `{$table}` (log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, vote_id BIGINT UNSIGNED NOT NULL, doctor_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, old_status VARCHAR(32) NOT NULL, new_status VARCHAR(32) NOT NULL, old_rating INT NOT NULL, new_rating INT NOT NULL, old_comment LONGTEXT NULL, new_comment LONGTEXT NULL, reason TEXT NOT NULL, occurred_utc DATETIME NOT NULL) ENGINE=InnoDB {$charset}";
			if ( false === $wpdb->query( $sql ) ) { return self::problem( 'ساخت جدول گزارش تغییرات ناموفق بود؛ هیچ رأیی ویرایش نشد.' ); }
		}
		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		if ( $wpdb->last_error !== '' || ! is_array( $rows ) || self::engine( $table ) !== 'INNODB' ) { return self::problem( 'جدول گزارش تغییرات سازگار نیست؛ هیچ رأیی ویرایش نشد.' ); }
		$names = array_column( $rows, 'Field' );
		$expected = array( 'log_id', 'vote_id', 'doctor_id', 'actor_id', 'old_status', 'new_status', 'old_rating', 'new_rating', 'old_comment', 'new_comment', 'reason', 'occurred_utc' );
		if ( $names !== $expected ) { return self::problem( 'ساختار جدول گزارش تغییرات متفاوت است؛ هیچ رأیی ویرایش نشد.' ); }
		$types = array( '/^bigint(?:\(\d+\))? unsigned$/i', '/^bigint(?:\(\d+\))? unsigned$/i', '/^bigint(?:\(\d+\))? unsigned$/i', '/^bigint(?:\(\d+\))? unsigned$/i', '/^varchar\(32\)$/i', '/^varchar\(32\)$/i', '/^int(?:\(\d+\))?$/i', '/^int(?:\(\d+\))?$/i', '/^longtext$/i', '/^longtext$/i', '/^text$/i', '/^datetime$/i' );
		foreach ( $rows as $i => $r ) {
			$nullable = in_array( $i, array( 8, 9 ), true ) ? 'YES' : 'NO';
			$extra = $i === 0 ? 'auto_increment' : '';
			if ( ! preg_match( $types[ $i ], (string) ( $r['Type'] ?? '' ) ) || ( $r['Null'] ?? '' ) !== $nullable || strtolower( (string) ( $r['Extra'] ?? '' ) ) !== $extra ) { return self::problem( 'نوع یا محدودیت ستون گزارش تغییرات سازگار نیست.' ); }
		}
		$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); $primary = array();
		if ( ! is_array( $indexes ) || $wpdb->last_error !== '' ) { return self::problem( 'کلید گزارش تغییرات قابل بررسی نیست.' ); }
		foreach ( $indexes as $index ) { if ( strtoupper( (string) ( $index['Key_name'] ?? '' ) ) === 'PRIMARY' ) { $primary[ (int) $index['Seq_in_index'] ] = $index['Column_name']; } }
		if ( $primary !== array( 1 => 'log_id' ) ) { return self::problem( 'کلید گزارش تغییرات سازگار نیست.' ); }
		return $table;
	}
	/** Mutate only after explicit administrator action. No physical deletion. */
	public static function save( $doctor, $vote, $expected, $state, $rating, $body, $reason ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! self::demo_doctor( $doctor ) ) { return self::problem( 'دسترسی یا جراح نمونه نامعتبر است.' ); }
		if ( ! is_int( $vote ) || $vote < 1 || ! is_string( $expected ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $expected ) || ! in_array( $state, array( 'approved', 'pending', 'rejected' ), true ) || ! is_int( $rating ) || $rating < 1 || $rating > 5 || ! is_string( $body ) || ! is_string( $reason ) ) { return self::problem( 'مقادیر واردشده نامعتبر است.' ); }
		$body = trim( sanitize_textarea_field( $body ) ); $reason = trim( sanitize_textarea_field( $reason ) );
		if ( strlen( $body ) > 1200 || $reason === '' || strlen( $reason ) > 1000 ) { return self::problem( 'دلیل لازم است و متن‌ها نباید طولانی باشند.' ); }
		$schema = self::inspect(); if ( is_wp_error( $schema ) ) { return $schema; }
		$old = $wpdb->suppress_errors( true ); $locked = false; $transaction = false;
		try {
			if ( self::engine( $schema['table'] ) !== 'INNODB' ) { return self::problem( 'جدول رأی تراکنشی InnoDB نیست.' ); }
			$lock = 'hj_vote_lab_' . substr( hash( 'sha256', $schema['table'] ), 0, 40 );
			$locked = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) ) === '1';
			if ( ! $locked || $wpdb->last_error !== '' ) { return self::problem( 'قفل عملیات برقرار نشد.' ); }
			$connection = $wpdb->get_var( 'SELECT CONNECTION_ID()' );
			if ( ! is_numeric( $connection ) || $wpdb->last_error !== '' ) { return self::problem( 'اتصال دیتابیس قابل تأیید نیست.' ); }
			$row = self::row( $schema, $doctor, $vote );
			if ( $wpdb->last_error !== '' || ! is_array( $row ) || ! hash_equals( self::snapshot( $row ), $expected ) ) { return self::problem( 'رأی تغییر کرده یا پیدا نشد؛ صفحه را تازه کنید.' ); }
			list( $marker ) = self::split_marker( $row['comment'] );
			if ( $marker !== '' && $body === '' ) { return self::problem( 'برای نظر نمونه علامت‌گذاری‌شده، متن نظر را خالی نکنید؛ برای پنهان‌سازی آن را رد کنید.' ); }
			$comment = $marker . $body;
			if ( strlen( $comment ) > $schema['comment_limit'] ) { return self::problem( 'متن نظر برای این ستون طولانی است.' ); }
			if ( $row['status'] === $state && (int) $row['rating'] === $rating && (string) $row['comment'] === $comment ) { return self::problem( 'تغییری نسبت به وضعیت قبلی انتخاب نشده است.' ); }
			$audit = self::ensure_audit(); if ( is_wp_error( $audit ) ) { return $audit; }
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::problem( 'تراکنش شروع نشد.' ); }
			$transaction = true;
			if ( (string) $wpdb->get_var( 'SELECT CONNECTION_ID()' ) !== (string) $connection || $wpdb->last_error !== '' ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'اتصال تغییر کرد؛ وضعیت را پیش از تکرار بررسی کنید.' ); }
			$locked_row = self::row( $schema, $doctor, $vote, true );
			if ( $wpdb->last_error !== '' || ! is_array( $locked_row ) || ! hash_equals( self::snapshot( $locked_row ), $expected ) ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'رأی هم‌زمان تغییر کرده است؛ صفحه را تازه کنید.' ); }
			// Compare all visible old values as well as ID; other writers need not know our advisory lock.
			$update = $wpdb->prepare( "UPDATE `{$schema['table']}` SET `{$schema['status']}` = %s, `{$schema['rating']}` = %d, `{$schema['comment']}` = %s WHERE `{$schema['id']}` = %d AND `{$schema['doctor']}` = %d AND `{$schema['status']}` <=> %s AND `{$schema['rating']}` = %d AND `{$schema['comment']}` <=> %s", $state, $rating, $comment, $vote, $doctor, $locked_row['status'], (int) $locked_row['rating'], $locked_row['comment'] );
			// WordPress prepare converts null to an empty string; use explicit IS NULL for a null comment.
			if ( $locked_row['comment'] === null ) { $update = $wpdb->prepare( "UPDATE `{$schema['table']}` SET `{$schema['status']}` = %s, `{$schema['rating']}` = %d, `{$schema['comment']}` = %s WHERE `{$schema['id']}` = %d AND `{$schema['doctor']}` = %d AND `{$schema['status']}` <=> %s AND `{$schema['rating']}` = %d AND `{$schema['comment']}` IS NULL", $state, $rating, $comment, $vote, $doctor, $locked_row['status'], (int) $locked_row['rating'] ); }
			$updated = $wpdb->query( $update );
			if ( (string) $wpdb->get_var( 'SELECT CONNECTION_ID()' ) !== (string) $connection || $wpdb->last_error !== '' ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'اتصال تغییر کرد؛ نتیجه نامشخص است و باید بررسی شود.' ); }
			if ( $updated !== 1 ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'ویرایش انجام نشد؛ داده یا محدودیت جدول را بررسی کنید.' ); }
			$entry = array( 'vote_id' => $vote, 'doctor_id' => $doctor, 'actor_id' => get_current_user_id(), 'old_status' => (string) $locked_row['status'], 'new_status' => $state, 'old_rating' => (int) $locked_row['rating'], 'new_rating' => $rating, 'old_comment' => $locked_row['comment'], 'new_comment' => $comment, 'reason' => $reason, 'occurred_utc' => gmdate( 'Y-m-d H:i:s' ) );
			$recorded = $wpdb->insert( $audit, $entry );
			if ( (string) $wpdb->get_var( 'SELECT CONNECTION_ID()' ) !== (string) $connection || $wpdb->last_error !== '' ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'اتصال تغییر کرد؛ نتیجه نامشخص است و باید بررسی شود.' ); }
			if ( $recorded !== 1 ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'ثبت گزارش تغییر ناموفق بود؛ بازگردانی درخواست شد.' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'نتیجه نهایی نامشخص است؛ پیش از تکرار بررسی کنید.' ); }
			$transaction = false;
			if ( (string) $wpdb->get_var( 'SELECT CONNECTION_ID()' ) !== (string) $connection || $wpdb->last_error !== '' ) { return self::problem( 'اتصال هنگام ثبت نهایی تغییر کرد؛ نتیجه نامشخص است و باید بررسی شود.' ); }
			return true;
		} catch ( Throwable $e ) { if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); } return self::problem( 'خطای مدیریت رأی؛ نتیجه را پیش از تکرار بررسی کنید.' ); }
		finally { if ( $locked ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); } $wpdb->suppress_errors( $old ); }
	}
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'روش نامعتبر.', '', array( 'response' => 405 ) ); }
		$vote = isset( $_POST['vote'] ) && is_scalar( $_POST['vote'] ) ? absint( wp_unslash( $_POST['vote'] ) ) : 0;
		check_admin_referer( 'hj_vote_manager_save_' . $vote );
		$doctor = isset( $_POST['doctor'] ) && is_scalar( $_POST['doctor'] ) ? absint( wp_unslash( $_POST['doctor'] ) ) : 0;
		$rating = isset( $_POST['rating'] ) && is_scalar( $_POST['rating'] ) ? absint( wp_unslash( $_POST['rating'] ) ) : 0;
		$state = isset( $_POST['state'] ) && is_string( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';
		$expected = isset( $_POST['expected'] ) && is_string( $_POST['expected'] ) ? wp_unslash( $_POST['expected'] ) : '';
		$body = isset( $_POST['body'] ) && is_string( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';
		$reason = isset( $_POST['reason'] ) && is_string( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : '';
		$result = self::save( $doctor, $vote, $expected, $state, $rating, $body, $reason );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 'مدیریت رأی', array( 'response' => 400, 'back_link' => true ) ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'hj-vote-manager', 'doctor' => $doctor, 'saved' => 1 ), admin_url( 'tools.php' ) ) ); exit;
	}
	public static function page() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		$doctors = get_posts( array( 'post_type' => 'doctor', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'meta_key' => '_hj_demo_doctor', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
		$doctor = isset( $_GET['doctor'] ) && is_scalar( $_GET['doctor'] ) ? absint( wp_unslash( $_GET['doctor'] ) ) : 0;
		$page_number = isset( $_GET['vote_page'] ) && is_scalar( $_GET['vote_page'] ) ? max( 1, min( 100000, absint( wp_unslash( $_GET['vote_page'] ) ) ) ) : 1;
		$schema = self::inspect(); $rows = array(); $history = array(); $error = ''; $has_more = false;
		if ( $doctor && self::demo_doctor( $doctor ) && ! is_wp_error( $schema ) ) {
			$old = $wpdb->suppress_errors( true );
			try {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `{$schema['id']}` AS vote_id, `{$schema['doctor']}` AS doctor_id, `{$schema['rating']}` AS rating, `{$schema['status']}` AS status, `{$schema['comment']}` AS comment FROM `{$schema['table']}` WHERE `{$schema['doctor']}` = %d ORDER BY `{$schema['id']}` DESC LIMIT 51 OFFSET %d", $doctor, ( $page_number - 1 ) * 50 ), ARRAY_A );
				if ( $wpdb->last_error !== '' || ! is_array( $rows ) ) { $rows = array(); $error = 'خواندن رأی‌ها ناموفق بود.'; }
				if ( count( $rows ) > 50 ) { array_pop( $rows ); $has_more = true; }
				$audit = self::audit_table(); $tables = $wpdb->get_col( 'SHOW TABLES' );
				if ( is_array( $tables ) && $wpdb->last_error === '' && in_array( $audit, $tables, true ) && self::valid_identifier( $audit ) ) {
					$history = $wpdb->get_results( $wpdb->prepare( "SELECT vote_id, actor_id, old_status, new_status, old_rating, new_rating, reason, occurred_utc FROM `{$audit}` WHERE doctor_id = %d ORDER BY log_id DESC LIMIT 30", $doctor ), ARRAY_A );
					if ( ! is_array( $history ) || $wpdb->last_error !== '' ) { $history = array(); }
				}
			} finally { $wpdb->suppress_errors( $old ); }
		}
		?>
		<div class="wrap"><h1>مدیریت رأی جراحان نمونه</h1>
		<p>فقط رأی‌های جراحان نشان‌دار نمونه. تأیید/رد، امتیاز و متن قابل تغییر است؛ با هر تغییر یک گزارش ذخیره می‌شود. رد، حذف فیزیکی نیست. صفحه و کش عمومی ممکن است با تأخیر تغییر کنند.</p>
		<?php if ( is_wp_error( $schema ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( $schema->get_error_message() ); ?></p></div><?php endif; ?>
		<?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?><div class="notice notice-success"><p>تغییر رأی و گزارش آن ذخیره شد.</p></div><?php endif; ?>
		<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>"><input type="hidden" name="page" value="hj-vote-manager"><label for="hj-vm-doctor">جراح نمونه:</label> <select id="hj-vm-doctor" name="doctor" required><option value="">انتخاب کنید</option><?php foreach ( $doctors as $d ) : if ( ! self::demo_doctor( $d->ID ) ) { continue; } ?><option value="<?php echo esc_attr( $d->ID ); ?>" <?php selected( $doctor, $d->ID ); ?>><?php echo esc_html( get_the_title( $d ) ); ?></option><?php endforeach; ?></select> <?php submit_button( 'نمایش رأی‌ها', 'secondary', 'submit', false ); ?></form>
		<?php if ( $doctor && ! self::demo_doctor( $doctor ) ) : ?><p>جراح نمونه پیدا نشد.</p><?php endif; ?>
		<?php if ( $doctor && self::demo_doctor( $doctor ) && ! is_wp_error( $schema ) ) : ?><h2>رأی‌های نمونه (۵۰ مورد در هر صفحه)</h2><?php if ( ! $rows && ! $error ) : ?><p>رأیی پیدا نشد.</p><?php endif; ?>
		<?php foreach ( $rows as $row ) : list( $marker, $body ) = self::split_marker( $row['comment'] ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #ddd;padding:16px;margin:16px 0;max-width:800px">
		<h3>رأی شماره <?php echo esc_html( $row['vote_id'] ); ?></h3><input type="hidden" name="action" value="hj_vote_manager_save"><input type="hidden" name="doctor" value="<?php echo esc_attr( $doctor ); ?>"><input type="hidden" name="vote" value="<?php echo esc_attr( $row['vote_id'] ); ?>"><input type="hidden" name="expected" value="<?php echo esc_attr( self::snapshot( $row ) ); ?>"><?php wp_nonce_field( 'hj_vote_manager_save_' . $row['vote_id'] ); ?>
		<p><label>وضعیت: <select name="state" required><?php if ( ! in_array( $row['status'], array( 'pending', 'approved', 'rejected' ), true ) ) : ?><option value="" selected disabled>یک وضعیت انتخاب کنید</option><?php endif; ?><option value="pending" <?php selected( $row['status'], 'pending' ); ?>>در انتظار</option><option value="approved" <?php selected( $row['status'], 'approved' ); ?>>تأییدشده</option><option value="rejected" <?php selected( $row['status'], 'rejected' ); ?>>ردشده</option></select></label> <small>وضعیت فعلی: <?php echo esc_html( $row['status'] ); ?></small></p>
		<p><label>امتیاز: <select name="rating" required><?php if ( (int) $row['rating'] < 1 || (int) $row['rating'] > 5 ) : ?><option value="" selected disabled>یک امتیاز انتخاب کنید</option><?php endif; ?><?php for ( $i = 1; $i <= 5; $i++ ) : ?><option value="<?php echo esc_attr( $i ); ?>" <?php selected( (int) $row['rating'], $i ); ?>><?php echo esc_html( $i ); ?></option><?php endfor; ?></select></label></p>
		<p><label>متن نظر:<br><textarea name="body" class="large-text" rows="3"><?php echo esc_textarea( $body ); ?></textarea></label><?php if ( $marker ) : ?><small> نشان ردیابی آزمایش در ذخیره حفظ می‌شود.</small><?php endif; ?></p>
		<p><label>دلیل تغییر (در گزارش مدیر ثبت می‌شود):<br><textarea name="reason" class="large-text" rows="2" required></textarea></label></p><?php submit_button( 'ذخیره تغییر', 'primary' ); ?></form>
		<?php endforeach; ?>
		<p>صفحه <?php echo esc_html( $page_number ); ?>
		<?php if ( $page_number > 1 ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'hj-vote-manager', 'doctor' => $doctor, 'vote_page' => $page_number - 1 ), admin_url( 'tools.php' ) ) ); ?>">صفحه قبل</a><?php endif; ?>
		<?php if ( $has_more ) : ?><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'hj-vote-manager', 'doctor' => $doctor, 'vote_page' => $page_number + 1 ), admin_url( 'tools.php' ) ) ); ?>">صفحه بعد</a><?php endif; ?></p>
		<h2>تاریخچه تغییرات (حداکثر ۳۰ مورد)</h2><?php if ( ! $history ) : ?><p>گزارشی ثبت نشده یا در دسترس نیست.</p><?php else : ?><ul><?php foreach ( $history as $event ) : ?><li><?php echo esc_html( 'رأی ' . $event['vote_id'] . ' | مدیر ' . $event['actor_id'] . ' | ' . $event['occurred_utc'] . ' UTC | وضعیت ' . $event['old_status'] . ' ← ' . $event['new_status'] . ' | امتیاز ' . $event['old_rating'] . ' ← ' . $event['new_rating'] . ' | دلیل: ' . $event['reason'] ); ?></li><?php endforeach; ?></ul><?php endif; ?>
		<?php endif; ?></div><?php
	}
}
HJ_Vote_Manager_Demo::init();
}

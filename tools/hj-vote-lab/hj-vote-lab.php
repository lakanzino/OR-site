<?php
/**
 * Plugin Name: HJ Vote Lab (Demo Only)
 * Description: Admin-only synthetic vote import and aggregate diagnostic download for marked demo doctors.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Hamyar Jarahi
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'HJ_Vote_Lab' ) ) {
final class HJ_Vote_Lab {
	const VERSION = '0.1.0';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_hj_vote_lab_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_hj_vote_lab_report', array( __CLASS__, 'handle_report' ) );
	}
	public static function menu() {
		$hook = add_management_page( 'آزمایش رأی و نظر نمونه', 'آزمایش رأی نمونه', 'manage_options', 'hj-vote-lab', array( __CLASS__, 'page' ) );
		if ( $hook ) { add_action( 'load-' . $hook, 'nocache_headers' ); }
	}
	private static function doctor_is_demo( $id ) {
		return $id > 0 && get_post_type( $id ) === 'doctor' && get_post_status( $id ) !== 'trash' && (string) get_post_meta( $id, '_hj_demo_doctor', true ) !== '';
	}
	private static function pick( $names, $columns ) {
		foreach ( $names as $name ) { if ( isset( $columns[ $name ] ) ) { return $name; } }
		return '';
	}
	private static function sql_identifier( $value ) { return is_string( $value ) && preg_match( '/\A[A-Za-z0-9_]+\z/', $value ); }
	private static function safe_type( $type ) {
		$type = strtolower( trim( (string) $type ) );
		if ( preg_match( '/\A(enum|set)\s*\(/', $type, $m ) ) { return $m[1] . ' (values omitted)'; }
		return preg_match( '/\A[a-z]+(?:\(\d+(?:,\d+)?\))?(?: unsigned)?\z/', $type ) ? $type : 'other';
	}
	private static function problem( $message ) { return new WP_Error( 'hj_vote_lab', $message ); }

	/** Verify the bridge and profile agree before ever writing to the live table. */
	public static function inspect() {
		global $wpdb;
		if ( ! is_callable( array( 'HJ_Doctors_Rating_Bridge', 'schema' ) ) ) { return self::problem( 'افزونه همیار پزشک‌ها یا پل رأی در دسترس نیست.' ); }
		$old = $wpdb->suppress_errors( true );
		try {
			$schema = HJ_Doctors_Rating_Bridge::schema();
			$table = $wpdb->prefix . 'hj_doctor_votes';
			if ( ! self::sql_identifier( $table ) || ! is_array( $schema ) || empty( $schema['ok'] ) || ( $schema['table'] ?? null ) !== $table || ! is_array( $schema['map'] ?? null ) ) {
				return self::problem( 'جدول پل رأی با جدول پیش‌فرض پروفایل سازگار نیست.' );
			}
			$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
			if ( $wpdb->last_error !== '' || ! is_array( $rows ) || ! $rows ) { return self::problem( 'ساختار جدول رأی قابل خواندن نیست.' ); }
			$columns = array();
			foreach ( $rows as $row ) {
				$name = $row['Field'] ?? null;
				if ( ! self::sql_identifier( $name ) || ! is_string( $row['Type'] ?? null ) || ! isset( $row['Null'], $row['Extra'] ) ) { return self::problem( 'ساختار جدول به شکل پشتیبانی‌شده نیست.' ); }
				$columns[ $name ] = $row;
			}
			$doctor = self::pick( array( 'doctor_id', 'post_id', 'doctor' ), $columns );
			$rating = self::pick( array( 'rating', 'stars', 'score', 'vote', 'value' ), $columns );
			$comment = self::pick( array( 'comment', 'review', 'content', 'body', 'text' ), $columns );
			$status = self::pick( array( 'status', 'is_approved', 'approved', 'state' ), $columns );
			if ( ! $doctor || ! $rating || ! $comment || ! $status || ( $schema['map']['doctor_id'] ?? '' ) !== $doctor || ( $schema['map']['rating'] ?? '' ) !== $rating || ( $schema['map']['status'] ?? '' ) !== $status ) {
				return self::problem( 'ستون نظر/امتیاز/وضعیت یا نگاشت یکسان پل و پروفایل موجود نیست؛ هیچ نظری درج نشد.' );
			}
			foreach ( array( $doctor, $rating ) as $column ) {
				if ( ! preg_match( '/\A(?:tinyint|smallint|mediumint|int|integer|bigint)(?:\(\d+\))?(?: unsigned)?\z/i', $columns[ $column ]['Type'] ) ) { return self::problem( 'نوع ستون شناسه یا امتیاز پشتیبانی نمی‌شود.' ); }
			}
			if ( ! preg_match( '/\A(?:varchar\((\d+)\)|char\((\d+)\)|tinytext|text|mediumtext|longtext)\z/i', $columns[ $comment ]['Type'], $cm ) ) { return self::problem( 'نوع ستون متن نظر پشتیبانی نمی‌شود.' ); }
			$limit = ! empty( $cm[1] ) ? (int) $cm[1] : ( ! empty( $cm[2] ) ? (int) $cm[2] : ( stripos( $columns[ $comment ]['Type'], 'tinytext' ) === 0 ? 255 : 65535 ) );
			return array( 'table' => $table, 'columns' => $columns, 'schema' => $schema, 'doctor' => $doctor, 'rating' => $rating, 'comment' => $comment, 'status' => $status, 'comment_limit' => $limit );
		} catch ( Throwable $e ) { return self::problem( 'بررسی ساختار ناموفق بود.' ); }
		finally { $wpdb->suppress_errors( $old ); }
	}
	private static function state_value( $details, $state ) {
		$type = strtolower( trim( $details['columns'][ $details['status'] ]['Type'] ) );
		if ( preg_match( '/\A(?:tinyint|smallint|mediumint|int|integer|bigint|bit|bool|boolean)(?:\(\d+\))?(?: unsigned)?\z/', $type ) ) { return $state === 'approved' ? 1 : 0; }
		if ( preg_match( '/\A(?:varchar|char)\((\d+)\)\z/', $type, $m ) && (int) $m[1] >= strlen( $state ) ) { return $state; }
		if ( in_array( $type, array( 'tinytext', 'text', 'mediumtext', 'longtext' ), true ) ) { return $state; }
		if ( preg_match( '/\Aenum\((.*)\)\z/', $type, $m ) && preg_match( '/(?:\A|,)\s*\x27' . preg_quote( $state, '/' ) . '\x27\s*(?:,|\z)/', $m[1] ) ) { return $state; }
		return self::problem( 'ستون وضعیت مقدار انتخاب‌شده را به شکل شناخته‌شده نمی‌پذیرد.' );
	}
	/** Unknown required columns are never guessed. */
	private static function required_values( $details, $values, $batch, $index ) {
		$schema = $details['schema'];
		foreach ( $details['columns'] as $name => $column ) {
			if ( array_key_exists( $name, $values ) || strtoupper( (string) $column['Null'] ) === 'YES' || $column['Default'] !== null || stripos( (string) $column['Extra'], 'auto_increment' ) !== false || stripos( (string) $column['Extra'], 'generated' ) !== false ) { continue; }
			$type = strtolower( (string) $column['Type'] );
			if ( $name === ( $schema['map']['user_id'] ?? '' ) && preg_match( '/\A(?:tinyint|smallint|mediumint|int|integer|bigint)(?:\(\d+\))?(?: unsigned)?\z/', $type ) ) { $values[ $name ] = 0; continue; }
			if ( $name === ( $schema['map']['ip_hash'] ?? '' ) && preg_match( '/\A(?:char|varchar)\((\d+)\)\z/', $type, $m ) && (int) $m[1] >= 32 ) { $values[ $name ] = substr( hash( 'sha256', $batch . ':' . $index ), 0, min( 64, (int) $m[1] ) ); continue; }
			if ( $name === ( $schema['map']['created_at'] ?? '' ) ) {
				if ( preg_match( '/\A(?:int|integer|bigint)(?:\(\d+\))?(?: unsigned)?\z/', $type ) ) { $values[ $name ] = time(); continue; }
				if ( preg_match( '/\A(?:datetime|timestamp)(?:\(\d+\))?\z/', $type ) ) { $values[ $name ] = current_time( 'mysql' ); continue; }
			}
			return self::problem( 'ستون اجباری ناشناخته «' . $name . '» وجود دارد؛ از درج خودکار صرف‌نظر شد.' );
		}
		return $values;
	}
	private static function parse_lines( $text, $batch, $limit ) {
		if ( ! is_string( $text ) || strlen( $text ) > 12000 ) { return self::problem( 'متن ورودی نامعتبر یا بیش از حد طولانی است.' ); }
		$lines = preg_split( '/\r\n|\r|\n/', trim( $text ) );
		if ( ! is_array( $lines ) || count( $lines ) < 1 || count( $lines ) > 10 ) { return self::problem( 'در هر بار ۱ تا ۱۰ خط وارد کنید.' ); }
		$items = array();
		foreach ( $lines as $line ) {
			if ( ! preg_match( '/\A\s*([1-5۱-۵١-٥])\s*\|\s*(.+)\s*\z/u', $line, $m ) ) { return self::problem( 'هر خط باید به شکل 5 | متن نظر نمونه باشد.' ); }
			$comment = trim( sanitize_text_field( $m[2] ) );
			if ( $comment === '' ) { return self::problem( 'متن نظر نمی‌تواند خالی باشد.' ); }
			$comment = '[نظر آزمایشی HJ-' . $batch . '] ' . $comment;
			if ( strlen( $comment ) > min( $limit, 1200 ) ) { return self::problem( 'متن نظر برای ستون موجود بیش از حد طولانی است.' ); }
			$digit = strtr( $m[1], array( '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5' ) );
			$items[] = array( 'rating' => (int) $digit, 'comment' => $comment );
		}
		return $items;
	}
	/** Parse a small UTF-8 CSV: optional rating,comment header; never execute or persist the uploaded file. */
	public static function csv_to_lines( $content ) {
		if ( ! is_string( $content ) || strlen( $content ) > 12000 ) { return self::problem( 'فایل CSV نامعتبر یا بیش از حد بزرگ است.' ); }
		$content = preg_replace( '/\A\xEF\xBB\xBF/', '', $content );
		if ( ! seems_utf8( $content ) ) { return self::problem( 'فایل باید UTF-8 باشد.' ); }
		$stream = fopen( 'php://temp', 'w+' );
		if ( ! $stream ) { return self::problem( 'خواندن فایل ناموفق بود.' ); }
		fwrite( $stream, $content ); rewind( $stream ); $lines = array(); $first = true;
		try {
			while ( ( $row = fgetcsv( $stream, 0, ',', '"', '' ) ) !== false ) {
				if ( $row === array( null ) ) { continue; }
				if ( count( $row ) !== 2 ) { return self::problem( 'CSV باید دقیقاً دو ستون rating و comment داشته باشد.' ); }
				if ( $first && strtolower( trim( $row[0] ) ) === 'rating' && strtolower( trim( $row[1] ) ) === 'comment' ) { $first = false; continue; }
				$first = false;
				$lines[] = trim( $row[0] ) . ' | ' . sanitize_text_field( $row[1] );
				if ( count( $lines ) > 10 ) { return self::problem( 'در هر فایل حداکثر ۱۰ نظر مجاز است.' ); }
			}
		} finally { fclose( $stream ); }
		return implode( "\n", $lines );
	}
	/** Only for an explicitly selected, marker-tagged demo. Uses atomic InnoDB writes. */
	public static function import( $doctor, $state, $text, $batch ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) { return self::problem( 'دسترسی غیرمجاز.' ); }
		if ( ! self::doctor_is_demo( $doctor ) ) { return self::problem( 'فقط جراح دارای نشان داده نمونه مجاز است.' ); }
		if ( ! in_array( $state, array( 'pending', 'approved' ), true ) || ! is_string( $batch ) || ! preg_match( '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $batch ) ) { return self::problem( 'درخواست نامعتبر است.' ); }
		$details = self::inspect(); if ( is_wp_error( $details ) ) { return $details; }
		$value = self::state_value( $details, $state ); if ( is_wp_error( $value ) ) { return $value; }
		$items = self::parse_lines( $text, $batch, $details['comment_limit'] ); if ( is_wp_error( $items ) ) { return $items; }
		$data = array();
		foreach ( $items as $i => $item ) {
			$row = array( $details['doctor'] => $doctor, $details['rating'] => $item['rating'], $details['status'] => $value, $details['comment'] => $item['comment'] );
			$row = self::required_values( $details, $row, $batch, $i ); if ( is_wp_error( $row ) ) { return $row; }
			$data[] = $row;
		}
		$old = $wpdb->suppress_errors( true ); $locked = false; $transaction = false;
		try {
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $details['table'] ) );
			if ( $wpdb->last_error !== '' || strtoupper( (string) $engine ) !== 'INNODB' ) { return self::problem( 'موتور تراکنشی InnoDB تأیید نشد؛ هیچ نظری درج نشد.' ); }
			$lock = 'hj_vote_lab_' . substr( hash( 'sha256', $details['table'] . ':' . $batch ), 0, 40 );
			$locked = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) ) === '1';
			if ( ! $locked || $wpdb->last_error !== '' ) { return self::problem( 'قفل درخواست برقرار نشد؛ دوباره امتحان کنید.' ); }
			$marker = '[نظر آزمایشی HJ-' . $batch . ']';
			$like = '%' . $wpdb->esc_like( $marker ) . '%';
			$found = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$details['table']}` WHERE `{$details['doctor']}` = %d AND `{$details['comment']}` LIKE %s", $doctor, $like ) );
			if ( $wpdb->last_error !== '' || $found === null ) { return self::problem( 'بررسی تکراری بودن درخواست ناموفق بود.' ); }
			if ( (int) $found > 0 ) { return self::problem( 'این درخواست قبلاً ثبت شده است؛ صفحه را تازه کنید.' ); }
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::problem( 'تراکنش شروع نشد.' ); }
			$transaction = true;
			foreach ( $data as $row ) { if ( 1 !== $wpdb->insert( $details['table'], $row ) ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'درج ناموفق بود؛ تراکنش بازگردانده شد. ساختار یا محدودیت جدول نیاز به بررسی دارد.' ); } }
			if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); $transaction = false; return self::problem( 'نتیجه نهایی تراکنش نامشخص است؛ پیش از تکرار گزارش را بررسی کنید.' ); }
			$transaction = false;
			return count( $data );
		} catch ( Throwable $e ) { if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); } return self::problem( 'درج ناموفق بود؛ نتیجه را پیش از تکرار بررسی کنید.' ); }
		finally { if ( $locked ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); } $wpdb->suppress_errors( $old ); }
	}
	/** Provide useful metadata even when insertion cannot be safely configured. */
	private static function unsupported_report( $doctor, $error ) {
		global $wpdb;
		$out = array( 'report_version' => self::VERSION, 'generated_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'selected_doctor' => array( 'demo_marker_present' => true, 'post_status' => get_post_status( $doctor ) ), 'import_compatible' => false, 'reason' => $error->get_error_message(), 'schema' => null, 'vote_table' => null, 'bridge' => null, 'public_review_reader' => null, 'notes' => array( 'No review text, vote IDs, voter IDs, IPs or doctor names are exported.', 'A null field is unavailable, not zero. No insertion occurred.' ) );
		if ( ! is_callable( array( 'HJ_Doctors_Rating_Bridge', 'schema' ) ) ) { return $out; }
		$old = $wpdb->suppress_errors( true );
		try {
			$schema = HJ_Doctors_Rating_Bridge::schema();
			if ( ! is_array( $schema ) || empty( $schema['table'] ) || ! self::sql_identifier( $schema['table'] ) ) { return $out; }
			$names = array();
			foreach ( (array) ( $schema['cols'] ?? array() ) as $name ) { if ( is_string( $name ) && self::sql_identifier( $name ) ) { $names[] = array( 'name' => $name, 'type' => self::safe_type( $schema['types'][ $name ] ?? '' ) ); } }
			$map = array();
			foreach ( array( 'id', 'doctor_id', 'rating', 'status' ) as $key ) { $v = $schema['map'][ $key ] ?? ''; $map[ $key ] = is_string( $v ) && in_array( $v, array_column( $names, 'name' ), true ) ? $v : null; }
			$out['schema'] = array( 'table' => $schema['table'] === $wpdb->prefix . 'hj_doctor_votes' ? 'default_prefixed_vote_table' : 'other_table_used_by_bridge', 'columns' => $names, 'mapping' => $map );
			if ( $map['doctor_id'] && $map['rating'] ) {
				$total = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, AVG(`{$map['rating']}`) AS average FROM `{$schema['table']}` WHERE `{$map['doctor_id']}` = %d", $doctor ), ARRAY_A );
				if ( $wpdb->last_error === '' && is_array( $total ) ) { $out['vote_table'] = array( 'all_rows_count' => (int) $total['total'], 'all_rows_average' => $total['average'] === null ? null : (float) $total['average'] ); }
			}
			if ( ! empty( $schema['ok'] ) && is_callable( array( 'HJ_Doctors_Rating_Bridge', 'stats' ) ) ) { $stats = HJ_Doctors_Rating_Bridge::stats( $doctor ); if ( is_array( $stats ) ) { $out['bridge'] = array_intersect_key( $stats, array_flip( array( 'ok', 'approved', 'pending', 'public_avg', 'avg', 'display', 'min_votes', 'force_show' ) ) ); } }
			if ( is_callable( array( 'HJ_Profile_Render', 'public_reviews' ) ) ) { $p = HJ_Profile_Render::public_reviews( $doctor ); if ( is_array( $p ) ) { $out['public_review_reader'] = array( 'returned_comments_count' => count( $p ), 'max_returned' => 10 ); } }
		} catch ( Throwable $e ) { /* Keep unknown fields null, without disclosing errors. */ }
		finally { $wpdb->suppress_errors( $old ); }
		return $out;
	}
	public static function report( $doctor ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! self::doctor_is_demo( $doctor ) ) { return self::problem( 'پزشک نمونه معتبر نیست یا دسترسی ندارید.' ); }
		$details = self::inspect(); if ( is_wp_error( $details ) ) { return self::unsupported_report( $doctor, $details ); }
		$schema_report = array();
		foreach ( $details['columns'] as $name => $col ) { $schema_report[] = array( 'name' => $name, 'type' => self::safe_type( $col['Type'] ), 'nullable' => strtoupper( $col['Null'] ) === 'YES', 'key' => in_array( $col['Key'] ?? '', array( '', 'PRI', 'UNI', 'MUL' ), true ) ? $col['Key'] : null ); }
		$old = $wpdb->suppress_errors( true );
		try {
			$total = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, AVG(`{$details['rating']}`) AS average FROM `{$details['table']}` WHERE `{$details['doctor']}` = %d", $doctor ), ARRAY_A );
			if ( $wpdb->last_error !== '' || ! is_array( $total ) ) { return self::problem( 'خواندن آمار کلی ناموفق بود.' ); }
			$marker = '%' . $wpdb->esc_like( '[نظر آزمایشی HJ-' ) . '%';
			$marked = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$details['table']}` WHERE `{$details['doctor']}` = %d AND `{$details['comment']}` LIKE %s", $doctor, $marker ) );
			if ( $wpdb->last_error !== '' || $marked === null ) { return self::problem( 'خواندن شمار نظرهای آزمایشی ناموفق بود.' ); }
		} catch ( Throwable $e ) { return self::problem( 'خواندن آمار ناموفق بود.' ); }
		finally { $wpdb->suppress_errors( $old ); }
		$stats = HJ_Doctors_Rating_Bridge::stats( $doctor );
		$public = is_callable( array( 'HJ_Profile_Render', 'public_reviews' ) ) ? HJ_Profile_Render::public_reviews( $doctor ) : null;
		return array(
			'report_version' => self::VERSION, 'generated_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'import_compatible' => true,
			'selected_doctor' => array( 'demo_marker_present' => true, 'post_status' => get_post_status( $doctor ) ),
			'schema' => array( 'table' => 'default_prefixed_vote_table', 'columns' => $schema_report, 'mapping' => array( 'doctor_id' => $details['doctor'], 'rating' => $details['rating'], 'comment' => $details['comment'], 'status' => $details['status'] ) ),
			'vote_table' => array( 'all_rows_count' => (int) $total['total'], 'all_rows_average' => $total['average'] === null ? null : (float) $total['average'], 'lab_marker_count' => (int) $marked ),
			'bridge' => is_array( $stats ) ? array_intersect_key( $stats, array_flip( array( 'ok', 'approved', 'pending', 'public_avg', 'avg', 'display', 'min_votes', 'force_show' ) ) ) : null,
			'public_review_reader' => is_array( $public ) ? array( 'returned_comments_count' => count( $public ), 'max_returned' => 10 ) : null,
			'notes' => array( 'No review text, vote IDs, voter IDs, IPs or doctor names are exported.', 'Counts include all rows for the selected demo doctor. Bridge pending means not approved, not necessarily pending; NULL may be excluded.', 'Public comment reader is limited to ten; page cache or rendering can differ. Average includes all statuses unless labelled bridge.', 'Lab markers can affect public output and sorting. No cleanup, moderation, cache refresh or appointment simulation is performed.' ),
		);
	}
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'روش نامعتبر.', '', array( 'response' => 405 ) ); }
		check_admin_referer( 'hj_vote_lab_import' );
		$doctor = isset( $_POST['doctor'] ) && is_scalar( $_POST['doctor'] ) ? absint( wp_unslash( $_POST['doctor'] ) ) : 0;
		$state = isset( $_POST['state'] ) && is_string( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';
		$batch = isset( $_POST['batch'] ) && is_string( $_POST['batch'] ) ? wp_unslash( $_POST['batch'] ) : '';
		$text = isset( $_POST['reviews'] ) && is_string( $_POST['reviews'] ) ? wp_unslash( $_POST['reviews'] ) : '';
		$file = $_FILES['csv'] ?? null;
		if ( is_array( $file ) && isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_NO_FILE ) {
			if ( $file['error'] !== UPLOAD_ERR_OK || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || ! is_numeric( $file['size'] ?? null ) || (int) $file['size'] > 12000 ) { wp_die( 'بارگذاری CSV ناموفق یا فایل بزرگ‌تر از حد مجاز است.', '', array( 'response' => 400 ) ); }
			$content = file_get_contents( $file['tmp_name'], false, null, 0, 12001 );
			$text = self::csv_to_lines( $content );
			if ( is_wp_error( $text ) ) { wp_die( esc_html( $text->get_error_message() ), '', array( 'response' => 400 ) ); }
		}
		if ( ( $_POST['confirm_demo'] ?? '' ) !== '1' ) { wp_die( 'تأیید دادهٔ نمونه لازم است.', '', array( 'response' => 400 ) ); }
		$result = self::import( $doctor, $state, $text, $batch );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 'آزمایش رأی', array( 'response' => 400, 'back_link' => true ) ); }
		$url = add_query_arg( array( 'page' => 'hj-vote-lab', 'selected' => $doctor, 'result' => 'ok', 'count' => $result ), admin_url( 'tools.php' ) );
		wp_safe_redirect( $url ); exit;
	}
	public static function handle_report() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) { wp_die( 'روش نامعتبر.', '', array( 'response' => 405 ) ); }
		$doctor = isset( $_GET['doctor'] ) && is_scalar( $_GET['doctor'] ) ? absint( wp_unslash( $_GET['doctor'] ) ) : 0;
		check_admin_referer( 'hj_vote_lab_report_' . $doctor );
		$report = self::report( $doctor );
		if ( is_wp_error( $report ) ) { wp_die( esc_html( $report->get_error_message() ), '', array( 'response' => 400 ) ); }
		$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) { wp_die( 'ساخت گزارش ناموفق بود.', '', array( 'response' => 500 ) ); }
		nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="hj-vote-lab-report.json"' ); header( 'X-Content-Type-Options: nosniff' );
		echo $json; exit;
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		$doctors = get_posts( array( 'post_type' => 'doctor', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'meta_key' => '_hj_demo_doctor', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
		$selected = isset( $_GET['selected'] ) && is_scalar( $_GET['selected'] ) ? absint( wp_unslash( $_GET['selected'] ) ) : 0;
		$details = self::inspect();
		?>
		<div class="wrap"><h1>آزمایش رأی و نظر نمونه</h1>
		<p>فقط برای جراحان دارای نشان دادهٔ نمونه. نظرات با برچسب «نظر آزمایشی» در جدول واقعی ذخیره می‌شوند؛ در صورت تأیید ممکن است در صفحه عمومی دیده و در آمار شمرده شوند. پشتیبان بگیرید.</p>
		<?php if ( is_wp_error( $details ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( $details->get_error_message() ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['result'], $_GET['count'] ) && $_GET['result'] === 'ok' && is_scalar( $_GET['count'] ) ) : ?><div class="notice notice-success"><p>درخواست ثبت شد. تعداد اعلام‌شده: <?php echo esc_html( absint( wp_unslash( $_GET['count'] ) ) ); ?>. گزارش را دانلود کنید؛ این پیام اثبات نمایش عمومی نیست.</p></div><?php endif; ?>
		<?php if ( ! $doctors ) : ?><p>جراح دارای نشان نمونه پیدا نشد؛ هیچ داده‌ای درج نشد.</p><?php else : ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="hj_vote_lab_import"><input type="hidden" name="batch" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'hj_vote_lab_import' ); ?>
		<p><label for="hj-lab-doctor">جراح نمونه:</label> <select id="hj-lab-doctor" name="doctor" required><?php foreach ( $doctors as $doctor ) : if ( ! self::doctor_is_demo( $doctor->ID ) ) { continue; } ?><option value="<?php echo esc_attr( $doctor->ID ); ?>" <?php selected( $selected, $doctor->ID ); ?>><?php echo esc_html( get_the_title( $doctor ) ); ?></option><?php endforeach; ?></select></p>
		<p><label for="hj-lab-state">وضعیت:</label> <select id="hj-lab-state" name="state"><option value="pending">در انتظار تأیید</option><option value="approved">تأییدشده (ممکن است عمومی شود)</option></select></p>
		<p><label for="hj-lab-reviews">هر خط: امتیاز ۱ تا ۵، علامت | و متن. حداکثر ۱۰ خط:</label><br><textarea class="large-text code" dir="auto" rows="6" id="hj-lab-reviews" name="reviews">5 | متن آزمایشی برای بررسی نمایش نظر
4 | متن آزمایشی برای بررسی امتیاز</textarea></p>
		<p><label for="hj-lab-csv">یا فایل CSV با دو ستون rating,comment و حداکثر ۱۰ نظر (UTF-8، تا ۱۲ کیلوبایت):</label> <input id="hj-lab-csv" type="file" name="csv" accept=".csv,text/csv"> انتخاب فایل، محتوای کادر بالا را نادیده می‌گیرد.</p>
		<p><label><input type="checkbox" name="confirm_demo" value="1" required> تأیید می‌کنم این جراح و نظرات، آزمایشی هستند و از درج در جدول سایت آگاه هستم.</label></p>
		<?php submit_button( 'درج نظرهای آزمایشی', 'primary', 'submit', true, is_wp_error( $details ) ? array( 'disabled' => 'disabled' ) : array() ); ?></form>
		<h2>دانلود گزارش برای مشاور</h2><p>گزارش شامل ساختار و آمار تجمیعی جراح نمونه انتخاب‌شده است، نه متن نظر یا شناسه رأی‌دهنده. پیش از ارسال، فایل را بررسی کنید.</p>
		<ul><?php foreach ( $doctors as $doctor ) : if ( ! self::doctor_is_demo( $doctor->ID ) ) { continue; } $url = wp_nonce_url( add_query_arg( array( 'action' => 'hj_vote_lab_report', 'doctor' => $doctor->ID ), admin_url( 'admin-post.php' ) ), 'hj_vote_lab_report_' . $doctor->ID ); ?><li><a href="<?php echo esc_url( $url ); ?>">دانلود گزارش <?php echo esc_html( get_the_title( $doctor ) ); ?></a></li><?php endforeach; ?></ul>
		<?php endif; ?></div>
		<?php
	}
}
HJ_Vote_Lab::init();
}

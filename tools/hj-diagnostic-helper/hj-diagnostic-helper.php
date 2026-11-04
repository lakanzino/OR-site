<?php
/**
 * Plugin Name: HJ Diagnostic Helper (Read-only)
 * Description: Admin-only, temporary vote schema metadata report. Does not read vote records.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Hamyar Jarahi
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'HJ_Diagnostic_Helper_Read_Only' ) ) {
	final class HJ_Diagnostic_Helper_Read_Only {
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		}

		public static function menu() {
			$hook = add_management_page( 'گزارش تشخیصی ساختار رأی', 'گزارش تشخیصی رأی', 'manage_options', 'hj-diagnostic-helper', array( __CLASS__, 'page' ) );
			if ( $hook ) { add_action( 'load-' . $hook, 'nocache_headers' ); }
		}

		/** Normalize type metadata without exposing ENUM/SET literals or defaults. */
		private static function safe_type( $type ) {
			$type = strtolower( trim( (string) $type ) );
			if ( preg_match( '/\A(enum|set)\s*\(/', $type, $matches ) ) { return $matches[1] . ' (values omitted)'; }
			if ( preg_match( '/\A(?:tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|bit|boolean|bool|char|varchar|tinytext|text|mediumtext|longtext|date|datetime|timestamp|time|year|binary|varbinary|tinyblob|blob|mediumblob|longblob|json)(?:\(\d+(?:,\d+)?\))?(?: unsigned)?\z/', $type ) ) { return $type; }
			return 'other or unavailable';
		}

		/** Metadata only: no SELECT from the vote table and no writes. */
		public static function report() {
			$report = array(
				'report_version' => '0.1.0',
				'generated_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'table' => 'unknown',
				'bridge_usable' => null,
				'columns' => null,
				'bridge_mapping' => null,
				'primary_key_columns' => null,
				'notes' => array( 'No vote rows or voter information are read by this report.', 'ENUM/SET values, defaults, comments and table prefix are omitted.', 'A primary key and status column do not prove safe moderation or status semantics.' ),
			);
			if ( ! is_callable( array( 'HJ_Doctors_Rating_Bridge', 'schema' ) ) ) { return $report; }
			global $wpdb;
			$previous = $wpdb->suppress_errors( true );
			try {
				$schema = HJ_Doctors_Rating_Bridge::schema();
				if ( ! is_array( $schema ) || empty( $schema['table'] ) || ! is_string( $schema['table'] ) || ! preg_match( '/\A[A-Za-z0-9_]+\z/', $schema['table'] ) ) { return $report; }
				$table = $schema['table'];
				$report['table'] = $table === $wpdb->prefix . 'hj_doctor_votes' ? 'default_prefixed_vote_table' : 'other_table_used_by_bridge';
				$report['bridge_usable'] = isset( $schema['ok'] ) ? (bool) $schema['ok'] : null;
				$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- strict identifier allowlist.
				if ( '' !== $wpdb->last_error || ! is_array( $rows ) || ! $rows ) { return $report; }
				$columns = array();
				$names = array();
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['Field'], $row['Type'], $row['Null'], $row['Key'] ) || ! is_string( $row['Field'] ) || ! is_string( $row['Type'] ) || ! is_string( $row['Null'] ) || ! is_string( $row['Key'] ) ) { return $report; }
					$name = wp_check_invalid_utf8( $row['Field'] );
					if ( '' === $name || $name !== $row['Field'] ) { return $report; }
					$names[] = $name;
					$nullable = strtoupper( $row['Null'] );
					$key = strtoupper( $row['Key'] );
					$columns[] = array( 'name' => $name, 'type' => self::safe_type( $row['Type'] ), 'nullable' => in_array( $nullable, array( 'YES', 'NO' ), true ) ? 'YES' === $nullable : null, 'key_marker' => in_array( $key, array( '', 'PRI', 'UNI', 'MUL' ), true ) ? $key : null );
				}
				$report['columns'] = $columns;
				$mapping = array();
				foreach ( array( 'id', 'doctor_id', 'rating', 'status' ) as $role ) {
					$value = isset( $schema['map'][ $role ] ) && is_string( $schema['map'][ $role ] ) ? $schema['map'][ $role ] : '';
					$mapping[ $role ] = '' !== $value && in_array( $value, $names, true ) ? $value : null;
				}
				$report['bridge_mapping'] = $mapping;
				$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- strict identifier allowlist.
				if ( '' !== $wpdb->last_error || ! is_array( $indexes ) ) { return $report; }
				$primary = array();
				foreach ( $indexes as $index ) {
					if ( ! is_array( $index ) || ! isset( $index['Key_name'] ) || ! is_string( $index['Key_name'] ) ) { return $report; }
					if ( 'PRIMARY' !== strtoupper( $index['Key_name'] ) ) { continue; }
					if ( ! isset( $index['Seq_in_index'], $index['Column_name'] ) || ! is_numeric( $index['Seq_in_index'] ) || ! is_string( $index['Column_name'] ) || ! in_array( $index['Column_name'], $names, true ) ) { return $report; }
					$seq = (int) $index['Seq_in_index'];
					if ( $seq < 1 || isset( $primary[ $seq ] ) ) { return $report; }
					$primary[ $seq ] = $index['Column_name'];
				}
				ksort( $primary, SORT_NUMERIC );
				if ( $primary && array_keys( $primary ) !== range( 1, count( $primary ) ) ) { return $report; }
				$report['primary_key_columns'] = array_values( $primary );
			} catch ( Throwable $error ) {
				// Keep unavailable portions null; do not disclose database errors.
			} finally {
				$wpdb->suppress_errors( $previous );
			}
			return $report;
		}

		public static function page() {
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'دسترسی غیرمجاز.', 'hj-diagnostic-helper' ), '', array( 'response' => 403 ) ); }
			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
			if ( 'GET' !== $method ) { wp_die( esc_html__( 'این صفحه فقط برای مشاهده است.', 'hj-diagnostic-helper' ), '', array( 'response' => 405 ) ); }
			nocache_headers();
			$json = wp_json_encode( self::report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( false === $json ) { $json = '{"error":"report_unavailable"}'; }
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'گزارش تشخیصی ساختار رأی', 'hj-diagnostic-helper' ); ?></h1>
				<p><?php echo esc_html__( 'این گزارش فقط ساختار جدول شناسایی‌شده را نشان می‌دهد و ردیف رأی، نام رأی‌دهنده، IP یا اطلاعات پزشکی را نمی‌خواند. قبل از ارسال خروجی برای دیگران، نام ستون‌ها را بررسی کنید. افزونه‌های دیگر و خود وردپرس ممکن است در همین درخواست فعالیت داشته باشند.', 'hj-diagnostic-helper' ); ?></p>
				<p><?php echo esc_html__( 'خروجی را انتخاب و کپی کنید. مقدار null یعنی نامعلوم یا خطای بررسی؛ این ابزار قابلیت درج، ویرایش یا حذف داده ندارد.', 'hj-diagnostic-helper' ); ?></p>
				<textarea readonly dir="ltr" rows="24" class="large-text code" aria-label="<?php echo esc_attr__( 'خروجی JSON ساختار رأی', 'hj-diagnostic-helper' ); ?>"><?php echo esc_textarea( $json ); ?></textarea>
			</div>
			<?php
		}
	}
	HJ_Diagnostic_Helper_Read_Only::init();
}

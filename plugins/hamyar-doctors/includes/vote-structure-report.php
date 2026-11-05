<?php
/**
 * Read-only, aggregate-only view of the vote table structure.
 *
 * این فایل هیچ ردیف رأی، شناسه پزشک، شناسه رأی‌دهنده یا نشانی IP را نمی‌خواند.
 * فقط شمار کل، یکتایی ستون شناسه و توزیع ستون وضعیت (سقف‌دار) سنجیده می‌شود.
 * هیچ کوئری نوشتنی در این فایل نیست و هر شکست به «نامعلوم» تبدیل می‌شود، نه حدس.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** سقف ردیف برای اجرای تجمیع‌ها؛ با فیلتر قابل تغییر است. */
function hj_doctors_vote_structure_limit() {
	$limit = apply_filters( 'hj_rb_structure_max_rows', 100000 );
	if ( ! is_numeric( $limit ) ) { return 100000; }
	$limit = (int) $limit;
	return $limit < 0 ? 0 : $limit;
}

/** وضعیت پل: یافت‌شده، نبود جدول یا شکست بررسی. */
function hj_doctors_vote_structure_state( $schema ) {
	$state = array( 'table' => 'missing', 'columns' => 'none' );
	if ( ! is_array( $schema ) ) { return $state; }
	$table = isset( $schema['table'] ) && is_string( $schema['table'] ) ? $schema['table'] : '';
	if ( '' === $table ) {
		$state['table'] = ! empty( $schema['probe_error'] ) ? 'error' : 'missing';
		return $state;
	}
	$state['table'] = 'found';
	$detected = 0;
	if ( isset( $schema['map'] ) && is_array( $schema['map'] ) ) {
		foreach ( array( 'id', 'doctor_id', 'rating', 'status' ) as $key ) {
			if ( isset( $schema['map'][ $key ] ) && is_string( $schema['map'][ $key ] ) && '' !== $schema['map'][ $key ] ) {
				++$detected;
			}
		}
	}
	$state['columns'] = 0 === $detected ? 'none' : ( 4 === $detected ? 'detected' : 'partial' );
	return $state;
}

/** ستون‌های مجاز؛ فقط از فهرست سفید خودِ پل و با نام امن. */
function hj_doctors_vote_structure_column( $schema, $key ) {
	if ( ! is_array( $schema ) || ! isset( $schema['map'][ $key ], $schema['cols'] ) ) { return ''; }
	$column = $schema['map'][ $key ];
	if ( ! is_string( $column ) || ! preg_match( '/^[A-Za-z0-9_]{1,64}$/', $column ) ) { return ''; }
	return in_array( $column, (array) $schema['cols'], true ) ? $column : '';
}

/**
 * تجمیع‌های فقط‌خواندنی روی جدول رأی.
 *
 * @param object     $wpdb  شیء پایگاه داده وردپرس (یا شبیه‌ساز آزمون).
 * @param array      $schema خروجی HJ_Doctors_Rating_Bridge::schema().
 * @param int|null   $limit  سقف ردیف؛ null یعنی مقدار پیش‌فرض فیلتر.
 * @return array
 */
function hj_doctors_vote_structure_counts( $wpdb, $schema, $limit = null ) {
	$out = array(
		'rows' => null,
		'rows_state' => 'unknown',
		'id_unique' => 'unknown',
		'status_state' => 'absent',
		'status_values' => array(),
		'status_more' => false,
	);
	$table = is_array( $schema ) && isset( $schema['table'] ) && is_string( $schema['table'] ) ? $schema['table'] : '';
	if ( ! is_object( $wpdb ) || ! preg_match( '/^[A-Za-z0-9_]{1,64}$/', $table ) ) { return $out; }
	$max = null === $limit ? hj_doctors_vote_structure_limit() : (int) $limit;

	$prev_suppress = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
	try {
		$wpdb->last_error = '';
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore -- شناسه از فهرست سفید و الگوی امن آمده است.
		if ( '' !== (string) $wpdb->last_error || null === $total || ! is_numeric( $total ) ) { return $out; }
		$out['rows'] = (int) $total;
		$out['rows_state'] = 'measured';
		if ( $max > 0 && $out['rows'] > $max ) {
			$out['rows_state'] = 'measured_skipped';
			return $out;
		}

		$id = hj_doctors_vote_structure_column( $schema, 'id' );
		$out['id_unique'] = '' === $id ? 'absent' : 'unknown';
		if ( '' !== $id ) {
			$wpdb->last_error = '';
			$row = $wpdb->get_row( "SELECT COUNT(*) AS total_rows, COUNT(DISTINCT `{$id}`) AS distinct_ids, SUM(CASE WHEN `{$id}` IS NULL THEN 1 ELSE 0 END) AS null_ids FROM `{$table}`", ARRAY_A ); // phpcs:ignore -- همان شناسهٔ سفید.
			if ( '' === (string) $wpdb->last_error && is_array( $row ) && isset( $row['total_rows'], $row['distinct_ids'], $row['null_ids'] ) ) {
				$nulls = (int) $row['null_ids'];
				$distinct = (int) $row['distinct_ids'];
				$counted = (int) $row['total_rows'];
				if ( $nulls > 0 ) {
					$out['id_unique'] = 'nulls';
				} elseif ( $distinct === $counted ) {
					$out['id_unique'] = 'unique';
				} else {
					$out['id_unique'] = 'duplicates';
				}
			}
		}

		$status = hj_doctors_vote_structure_column( $schema, 'status' );
		if ( '' === $status ) { return $out; }
		$out['status_state'] = 'unknown';
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( "SELECT `{$status}` AS vote_status, COUNT(*) AS vote_count FROM `{$table}` GROUP BY `{$status}` ORDER BY vote_count DESC LIMIT 13", ARRAY_A ); // phpcs:ignore -- همان شناسهٔ سفید، فقط شمار.
		if ( '' !== (string) $wpdb->last_error || ! is_array( $rows ) ) { return $out; }
		$out['status_state'] = 'measured';
		if ( count( $rows ) > 12 ) {
			$out['status_more'] = true;
			$rows = array_slice( $rows, 0, 12 );
		}
		foreach ( $rows as $row ) {
			if ( ! isset( $row['vote_count'] ) || ! is_numeric( $row['vote_count'] ) ) { continue; }
			$value = array_key_exists( 'vote_status', $row ) ? $row['vote_status'] : null;
			$label = 'بدون مقدار';
			if ( is_scalar( $value ) ) {
				$label = trim( preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $value ) );
				if ( '' === $label ) { $label = 'خالی'; }
				elseif ( strlen( $label ) > 40 ) { $label = substr( $label, 0, 40 ) . '…'; }
			}
			$out['status_values'][] = array( 'label' => $label, 'count' => (int) $row['vote_count'] );
		}
		return $out;
	} finally {
		if ( null !== $prev_suppress && method_exists( $wpdb, 'suppress_errors' ) ) {
			$wpdb->suppress_errors( $prev_suppress );
			$wpdb->last_error = '';
		}
	}
}

/** برچسب فارسی هر حالت؛ جدا از منطق تا آزمون‌پذیر باشد. */
function hj_doctors_vote_structure_lines( $state, $counts ) {
	$state = is_array( $state ) ? $state : array();
	$counts = is_array( $counts ) ? $counts : array();
	$table_labels = array( 'found' => 'توسط پل رأی شناسایی شد', 'missing' => 'یافت نشد', 'error' => 'بررسی ناموفق بود' );
	$columns_labels = array( 'detected' => 'چهار ستون اصلی شناسایی شد', 'partial' => 'بخشی از ستون‌ها شناسایی شد', 'none' => 'ستونی شناسایی نشد' );
	$id_labels = array( 'unique' => 'یکتا', 'duplicates' => 'تکراری یا نامعلوم', 'nulls' => 'مقدار خالی دارد', 'absent' => 'ستون شناسه شناسایی نشد', 'unknown' => 'نامعلوم' );

	$rows = isset( $counts['rows_state'] ) ? $counts['rows_state'] : 'unknown';
	$rows_text = 'نامعلوم';
	if ( 'measured' === $rows ) { $rows_text = (string) (int) $counts['rows']; }
	elseif ( 'measured_skipped' === $rows ) { $rows_text = 'بیش از سقف بررسی؛ تجمیع اجرا نشد'; }

	$lines = array(
		array( 'جدول رأی', isset( $table_labels[ $state['table'] ?? '' ] ) ? $table_labels[ $state['table'] ] : 'نامعلوم' ),
		array( 'ستون‌ها', isset( $columns_labels[ $state['columns'] ?? '' ] ) ? $columns_labels[ $state['columns'] ] : 'نامعلوم' ),
		array( 'تعداد ردیف رأی', $rows_text ),
		array( 'یکتایی ستون شناسه', isset( $id_labels[ $counts['id_unique'] ?? '' ] ) ? $id_labels[ $counts['id_unique'] ] : 'نامعلوم' ),
	);

	$status_state = isset( $counts['status_state'] ) ? $counts['status_state'] : 'absent';
	if ( 'absent' === $status_state ) {
		$lines[] = array( 'ستون وضعیت', 'شناسایی نشد؛ نمایش عمومی بدون فیلتر تأیید است' );
		$lines[] = array( 'توزیع وضعیت', 'اندازه‌گیری نشد' );
		return $lines;
	}
	$lines[] = array( 'ستون وضعیت', 'شناسایی شد' );
	if ( 'measured' !== $status_state ) {
		$lines[] = array( 'توزیع وضعیت', 'نامعلوم' );
		return $lines;
	}
	$parts = array();
	foreach ( (array) $counts['status_values'] as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['label'], $item['count'] ) ) { continue; }
		$parts[] = (string) $item['label'] . ': ' . (string) (int) $item['count'];
	}
	if ( ! empty( $counts['status_more'] ) ) { $parts[] = 'و مقادیر بیشتر'; }
	$lines[] = array( 'توزیع وضعیت', $parts ? implode( '؛ ', $parts ) : 'هیچ ردیفی نیست' );
	return $lines;
}

/** هشدارهای ثابتی که همراه همین گزارش نمایش داده می‌شود. */
function hj_doctors_vote_structure_notes() {
	return array(
		'این گزارش فقط تجمیع می‌خواند: هیچ ردیف، شناسه پزشک، شناسه رأی‌دهنده یا نشانی IP خوانده یا نمایش داده نمی‌شود.',
		'«بررسی ناموفق» با «جدول یافت نشد» یکی نیست؛ اولی یعنی کوئری خطا داد و ساختار همچنان نامعلوم است.',
		'یکتایی شناسه فقط از شمار کل و شمار یکتا نتیجه می‌شود؛ کلید اصلی بودن، نوع ستون و رفتار هم‌زمانی را ثابت نمی‌کند.',
		'ستون وضعیت اگر شناسایی نشود، مدیریت تأیید رأی بدون تصمیم مالک جدول ساخته نمی‌شود.',
	);
}

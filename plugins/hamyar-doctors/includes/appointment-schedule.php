<?php
/** Pure schedule computation for the optional booking backend. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Convert one local wall time only when it has exactly one valid UTC instant. */
function hj_booking_local_time( $wall, DateTimeZone $tz ) {
	$utc = new DateTimeZone( 'UTC' );
	$naive = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $wall, $utc );
	if ( ! $naive || $naive->format( 'Y-m-d H:i:s' ) !== $wall ) { return false; }
	$stamp = $naive->getTimestamp();
	$transitions = $tz->getTransitions( $stamp - 172800, $stamp + 172800 );
	$offsets = array();
	if ( $transitions === false ) { $offsets[] = $tz->getOffset( $naive ); }
	else { foreach ( $transitions as $transition ) { $offsets[] = $transition['offset']; } }
	$candidates = array();
	foreach ( array_unique( $offsets ) as $offset ) {
		$instant = $stamp - $offset;
		$local = ( new DateTimeImmutable( '@' . $instant ) )->setTimezone( $tz );
		if ( $local->format( 'Y-m-d H:i:s' ) === $wall ) { $candidates[$instant] = true; }
	}
	return count( $candidates ) === 1 ? (int) array_key_first( $candidates ) : false;
}
/** Pure computation. Existing occupied rows must already be read consistently. */
function hj_booking_compute_slots( $date, $rows, $duration, $buffer, $occupied, $now, $horizon_days ) {
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || ! is_array( $rows ) || ! is_array( $occupied ) || ! is_int( $duration ) || $duration < 1 || $duration > 240 || ! is_int( $buffer ) || $buffer < 0 || $buffer > 120 || ! is_int( $now ) || ! is_int( $horizon_days ) || $horizon_days < 1 || $horizon_days > 365 ) { return array(); }
	$utc = new DateTimeZone( 'UTC' );
	$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $utc );
	if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) { return array(); }
	$weekday = strtolower( $day->format( 'l' ) ); $matched = array();
	foreach ( $rows as $row ) { if ( is_array( $row ) && ( $row['day_of_week'] ?? '' ) === $weekday ) { $matched[] = $row; } }
	if ( count( $matched ) !== 1 || ! empty( $matched[0]['is_closed'] ) ) { return array(); }
	$row = $matched[0];
	$start = $row['start_time'] ?? ''; $end = $row['end_time'] ?? '';
	$time_pattern = '/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D';
	if ( ! is_string( $start ) || ! is_string( $end ) || ! preg_match( $time_pattern, $start ) || ! preg_match( $time_pattern, $end ) ) { return array(); }
	if ( strlen( $start ) === 5 ) { $start .= ':00'; }
	if ( strlen( $end ) === 5 ) { $end .= ':00'; }
	$start_naive = hj_booking_local_time( $date . ' ' . $start, $utc );
	$end_naive = hj_booking_local_time( $date . ' ' . $end, $utc );
	if ( $start_naive === false || $end_naive === false || $start_naive >= $end_naive ) { return array(); }
	$tz = wp_timezone(); $result = array();
	for ( $wall = $start_naive; $wall + $duration * 60 <= $end_naive; $wall += ( $duration + $buffer ) * 60 ) {
		$local_start = gmdate( 'Y-m-d H:i:s', $wall );
		$local_end = gmdate( 'Y-m-d H:i:s', $wall + $duration * 60 );
		$a = hj_booking_local_time( $local_start, $tz );
		$b = hj_booking_local_time( $local_end, $tz );
		if ( $a === false || $b === false || $b - $a !== $duration * 60 || $a <= $now || $a > $now + $horizon_days * DAY_IN_SECONDS ) { continue; }
		$blocked = $b + $buffer * 60; $available = true;
		foreach ( $occupied as $taken ) {
			if ( ! is_array( $taken ) || ! in_array( $taken['status'] ?? '', array( 'pending', 'confirmed' ), true ) ) { continue; }
			$x = hj_booking_local_time( $taken['starts_at'] ?? '', $utc );
			$y = hj_booking_local_time( $taken['blocked_until'] ?? '', $utc );
			// Fail closed on malformed active reservations.
			if ( $x === false || $y === false || $x >= $y ) { return array(); }
			if ( $x < $blocked && $y > $a ) { $available = false; break; }
		}
		if ( $available ) { $result[] = gmdate( 'Y-m-d H:i:s', $a ); }
	}
	return $result;
}

/**
 * Calculate capacity for one local calendar day without reading or writing DB.
 * This is a foundation for a future versioned schedule: existing booking paths
 * do not use it yet. A caller making reservations MUST read the current policy
 * and every relevant appointment and re-evaluate inside the doctor mutex.
 *
 * Intervals are half-open [start, blocked_until). Capacity means the maximum
 * number of active appointments overlapping at any instant. A new booking
 * occupies its entire interval including buffer. Completed/no-show records
 * count toward the daily cap and block until their interval has elapsed.
 *
 * Returns false for invalid input; otherwise slots include disabled states and
 * day_remaining is capped globally (not the naive sum when a daily cap exists).
 */
function hj_booking_compute_capacity_day( $date, $rule, $occupied, $now, $horizon_days, DateTimeZone $tz, $min_lead = 0 ) {
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || ! is_array( $rule ) || ! is_array( $occupied ) || ! is_int( $now ) || ! is_int( $horizon_days ) || $horizon_days < 1 || $horizon_days > 365 || ! is_int( $min_lead ) || $min_lead < 0 || $min_lead > 365 * DAY_IN_SECONDS ) { return false; }
	$utc = new DateTimeZone( 'UTC' );
	$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $utc );
	if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) { return false; }
	$start = $rule['start_time'] ?? null; $end = $rule['end_time'] ?? null;
	$duration = $rule['duration_minutes'] ?? null; $buffer = $rule['buffer_minutes'] ?? null;
	$capacity = $rule['capacity'] ?? null; $daily_cap = $rule['daily_cap'] ?? null;
	$time_pattern = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/D';
	if ( ! is_string( $start ) || ! is_string( $end ) || ! preg_match( $time_pattern, $start ) || ! preg_match( $time_pattern, $end ) || $start >= $end || ! is_int( $duration ) || $duration < 10 || $duration > 240 || ! is_int( $buffer ) || $buffer < 0 || $buffer > 120 || ! is_int( $capacity ) || $capacity < 1 || $capacity > 10 || ( $daily_cap !== null && ( ! is_int( $daily_cap ) || $daily_cap < 1 || $daily_cap > 1000 ) ) ) { return false; }
	$wall_start = hj_booking_local_time( $date . ' ' . $start . ':00', $utc );
	$wall_end = hj_booking_local_time( $date . ' ' . $end . ':00', $utc );
	if ( $wall_start === false || $wall_end === false ) { return false; }
	$break_start = null; $break_end = null;
	if ( array_key_exists( 'break_start', $rule ) || array_key_exists( 'break_end', $rule ) ) {
		$bs = $rule['break_start'] ?? null; $be = $rule['break_end'] ?? null;
		if ( ! is_string( $bs ) || ! is_string( $be ) || ! preg_match( $time_pattern, $bs ) || ! preg_match( $time_pattern, $be ) || $bs >= $be || $bs < $start || $be > $end ) { return false; }
		$break_start = hj_booking_local_time( $date . ' ' . $bs . ':00', $utc );
		$break_end = hj_booking_local_time( $date . ' ' . $be . ':00', $utc );
	}
	$active = array(); $day_count = 0;
	foreach ( $occupied as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['status'] ) || ! in_array( $row['status'], array( 'pending', 'confirmed', 'cancelled', 'completed', 'no_show' ), true ) ) { return false; }
		if ( $row['status'] === 'cancelled' ) { continue; }
		if ( ! isset( $row['starts_at'], $row['blocked_until'] ) || ! is_string( $row['starts_at'] ) || ! is_string( $row['blocked_until'] ) ) { return false; }
		$a = hj_booking_local_time( $row['starts_at'], $utc );
		$b = hj_booking_local_time( $row['blocked_until'], $utc );
		if ( $a === false || $b === false || $a >= $b ) { return false; }
		// Future terminal visits indicate an inconsistent writer: do not release capacity.
		if ( in_array( $row['status'], array( 'completed', 'no_show' ), true ) && $a > $now ) { return false; }
		if ( ( new DateTimeImmutable( '@' . $a ) )->setTimezone( $tz )->format( 'Y-m-d' ) === $date ) { $day_count++; }
		if ( in_array( $row['status'], array( 'pending', 'confirmed' ), true ) || $b > $now ) { $active[] = array( $a, $b ); }
	}
	$slots = array(); $sum = 0;
	for ( $wall = $wall_start; $wall + $duration * 60 <= $wall_end; $wall += ( $duration + $buffer ) * 60 ) {
		$wall_finish = $wall + $duration * 60;
		if ( $break_start !== null && $wall < $break_end && $wall_finish + $buffer * 60 > $break_start ) { continue; }
		$a = hj_booking_local_time( gmdate( 'Y-m-d H:i:s', $wall ), $tz );
		$b = hj_booking_local_time( gmdate( 'Y-m-d H:i:s', $wall_finish ), $tz );
		$blocked = hj_booking_local_time( gmdate( 'Y-m-d H:i:s', $wall_finish + $buffer * 60 ), $tz );
		// Never guess an ambiguous instant or cross a DST change, including buffer.
		if ( $a === false || $b === false || $blocked === false || $b - $a !== $duration * 60 || $blocked - $b !== $buffer * 60 ) { continue; }
		$events = array();
		foreach ( $active as $interval ) {
			if ( $interval[0] >= $blocked || $interval[1] <= $a ) { continue; }
			$x = max( $a, $interval[0] ); $y = min( $blocked, $interval[1] );
			$events[$x] = ( $events[$x] ?? 0 ) + 1;
			$events[$y] = ( $events[$y] ?? 0 ) - 1;
		}
		ksort( $events, SORT_NUMERIC ); $current = 0; $peak = 0;
		foreach ( $events as $delta ) { $current += $delta; $peak = max( $peak, $current ); }
		$remaining = max( 0, $capacity - $peak );
		if ( $daily_cap !== null ) { $remaining = min( $remaining, max( 0, $daily_cap - $day_count ) ); }
		$state = $a <= $now ? 'past' : ( $a > $now + $horizon_days * DAY_IN_SECONDS ? 'beyond' : ( $a < $now + $min_lead ? 'lead_time' : ( $remaining === 0 ? 'full' : 'available' ) ) );
		if ( $state !== 'available' ) { $remaining = 0; }
		$sum += $remaining;
		$slots[] = array( 'start' => gmdate( 'Y-m-d H:i:s', $a ), 'end' => gmdate( 'Y-m-d H:i:s', $b ), 'blocked_until' => gmdate( 'Y-m-d H:i:s', $blocked ), 'remaining' => $remaining, 'state' => $state );
	}
	if ( $daily_cap !== null ) { $sum = min( $sum, max( 0, $daily_cap - $day_count ) ); }
	return array( 'slots' => $slots, 'day_count' => $day_count, 'day_remaining' => $sum );
}

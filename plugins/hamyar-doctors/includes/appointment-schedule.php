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

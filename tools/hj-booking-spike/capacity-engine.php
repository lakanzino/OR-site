<?php
/** Disposable prototype only; NOT loaded by the plugin or used on a live site. */
if ( ! defined( 'HJ_BOOKING_DISPOSABLE_FIXTURE' ) || HJ_BOOKING_DISPOSABLE_FIXTURE !== true || PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' ) { exit( 'Disposable CI only.' ); }

/**
 * Compute conservative per-interval capacity for ONE date. Caller must read the
 * schedule and active rows under the same doctor mutex before a write. This
 * function does not lock, persist, authorize, or implement an API/UI.
 * Any overlapping active old interval consumes one place, even when old
 * intervals do not overlap each other; this can underbook, never overbook.
 */
function hj_capacity_spike_slots( $date, $rule, $occupied, $now, $horizon ) {
 if ( ! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || ! is_array($rule) || ! is_array($occupied) || ! is_int($now) || ! is_int($horizon) || $horizon < 1 || $horizon > 365 ) { return false; }
 $utc=new DateTimeZone('UTC'); $day=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$utc);
 if (!$day || $day->format('Y-m-d') !== $date) { return false; }
 $start=$rule['start'] ?? null; $end=$rule['end'] ?? null; $minutes=$rule['minutes'] ?? null; $capacity=$rule['capacity'] ?? null; $buffer=$rule['buffer'] ?? 0; $daily_cap=$rule['daily_cap'] ?? null;
 if (!is_string($start) || !is_string($end) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$start) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$end) || $start >= $end || !is_int($minutes) || $minutes < 10 || $minutes > 240 || !is_int($capacity) || $capacity < 1 || $capacity > 10 || !is_int($buffer) || $buffer < 0 || $buffer > 120 || ($daily_cap !== null && (!is_int($daily_cap) || $daily_cap < 1 || $daily_cap > 1000))) { return false; }
 $wall_start=hj_booking_local_time($date.' '.$start.':00',$utc); $wall_end=hj_booking_local_time($date.' '.$end.':00',$utc); $tz=wp_timezone();
 if ($wall_start === false || $wall_end === false) { return false; }
 $break_start=null; $break_end=null;
 if (isset($rule['break_start']) || isset($rule['break_end'])) {
  $bs=$rule['break_start'] ?? null; $be=$rule['break_end'] ?? null;
  if (!is_string($bs) || !is_string($be) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$bs) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D',$be) || $bs >= $be || $bs < $start || $be > $end) { return false; }
  $break_start=hj_booking_local_time($date.' '.$bs.':00',$utc); $break_end=hj_booking_local_time($date.' '.$be.':00',$utc);
 }
 $active=array(); $day_count=0;
 foreach ($occupied as $row) {
  if (!is_array($row) || !isset($row['status']) || !in_array($row['status'],array('pending','confirmed','cancelled','completed','no_show'),true)) { return false; }
  if (!in_array($row['status'],array('pending','confirmed'),true)) { continue; }
  $a=hj_booking_local_time($row['starts_at'] ?? '',$utc); $b=hj_booking_local_time($row['blocked_until'] ?? '',$utc);
  if ($a === false || $b === false || $a >= $b) { return false; }
  $active[]=array($a,$b);
  if ((new DateTimeImmutable('@'.$a))->setTimezone($tz)->format('Y-m-d') === $date) { $day_count++; }
 }
 $slots=array();
 for ($wall=$wall_start; $wall+$minutes*60 <= $wall_end; $wall+=($minutes+$buffer)*60) {
  $wall_finish=$wall+$minutes*60;
  if ($break_start !== null && $wall < $break_end && $wall_finish > $break_start) { continue; }
  $a=hj_booking_local_time(gmdate('Y-m-d H:i:s',$wall),$tz); $b=hj_booking_local_time(gmdate('Y-m-d H:i:s',$wall_finish),$tz);
  // Skip nonexistent/ambiguous DST times: never guess an instant.
  if ($a === false || $b === false || $b-$a !== $minutes*60) { continue; }
  $blocked=$b+$buffer*60; $count=0;
  foreach ($active as $interval) { if ($interval[0] < $blocked && $interval[1] > $a) { $count++; } }
  $remaining=max(0,$capacity-$count);
  if ($daily_cap !== null) { $remaining=min($remaining,max(0,$daily_cap-$day_count)); }
  $state=$a <= $now ? 'past' : ($a > $now+$horizon*DAY_IN_SECONDS ? 'beyond' : ($remaining === 0 ? 'full' : 'available'));
  $slots[]=array('start'=>gmdate('Y-m-d H:i:s',$a),'end'=>gmdate('Y-m-d H:i:s',$b),'remaining'=>$state === 'available' ? $remaining : 0,'state'=>$state);
 }
 return array('slots'=>$slots,'day_active'=>$day_count);
}

<?php

/**

 * Hamyar Doctors — includes/hooks.php (Section 15)

 *

 * Future online-appointment extension points. NO booking logic here.

 * - default OFF: with no add-on installed, nothing is printed anywhere

 * - bridges: hj_doctor_after_schedule -> hj_appt_profile_slot (action)

 * hj_doctor_dashboard_appointments -> hj_appt_dashboard_panel (action)

 * - helpers: hj_appointments_enabled(), hj_appointments_enabled_for($id),

 * hj_doctor_schedule_for_booking($id), hj_doctor_booking_context($id)

 * - status: /wp-admin/admin-post.php?action=hj_hooks_status

 *

 * Constant: HJ_APPOINTMENTS_ENABLED

 * Filters : hj_appointments_enabled, hj_appointments_doctor_enabled,

 * hj_doctor_schedule_for_booking, hj_doctor_booking_context, hj_hooks_registry

 */

if ( ! defined( 'ABSPATH' ) ) { exit; }



/* ---------------------------------------------------------------------

 * Public helpers (global, safe to call from a future add-on)

 * ------------------------------------------------------------------- */

if ( ! function_exists( 'hj_appointments_enabled' ) ) {

 function hj_appointments_enabled() {

  $base = defined( 'HJ_APPOINTMENTS_ENABLED' ) ? (bool) HJ_APPOINTMENTS_ENABLED : false;

  return (bool) apply_filters( 'hj_appointments_enabled', $base );

 }

}



if ( ! function_exists( 'hj_appointments_enabled_for' ) ) {

 function hj_appointments_enabled_for( $doctor_id ) {

  $id = (int) $doctor_id;

  if ( $id <= 0 || ! hj_appointments_enabled() ) { return false; }

  $public = function_exists( 'hj_dp_is_public' ) ? (bool) hj_dp_is_public( $id ) : ( 'publish' === get_post_status( $id ) );

  if ( ! $public ) { return false; }

  return (bool) apply_filters( 'hj_appointments_doctor_enabled', true, $id );

 }

}



if ( ! function_exists( 'hj_doctor_schedule_for_booking' ) ) {

 /** Read-only copy of the doctor's working hours (section 3 data). Never writes. */

 function hj_doctor_schedule_for_booking( $doctor_id ) {

  $id = (int) $doctor_id;

  $out = array();

  if ( $id > 0 && function_exists( 'hj_dp_working_hours' ) ) {

   try {

    $r = hj_dp_working_hours( $id );

    if ( is_array( $r ) ) { $out = $r; }

   } catch ( Throwable $e ) {

    $out = array();

   }

  }

  return (array) apply_filters( 'hj_doctor_schedule_for_booking', $out, $id );

 }

}



if ( ! function_exists( 'hj_doctor_booking_context' ) ) {

 /** Public-only data about a doctor (same allow-list as the REST API). Empty array if not public. */

 function hj_doctor_booking_context( $doctor_id ) {

  $id = (int) $doctor_id;

  $ctx = array();

  if ( $id > 0 && class_exists( 'HJ_Doctors_REST' ) && method_exists( 'HJ_Doctors_REST', 'is_public' ) && HJ_Doctors_REST::is_public( $id ) ) {

   try {

    $r = HJ_Doctors_REST::build_item( $id, true );

    if ( is_array( $r ) ) { $ctx = $r; }

   } catch ( Throwable $e ) {

    $ctx = array();

   }

  }

  return (array) apply_filters( 'hj_doctor_booking_context', $ctx, $id );

 }

}



if ( ! class_exists( 'HJ_Doctors_Hooks' ) ) :



class HJ_Doctors_Hooks {



 private static $inst = null;



 public static function boot() {

  if ( ! self::$inst ) { self::$inst = new self(); }

  return self::$inst;

 }



 private function __construct() {

  add_action( 'hj_doctor_after_schedule', array( $this, 'bridge_profile' ), 20, 3 );

  add_action( 'hj_doctor_dashboard_appointments', array( $this, 'bridge_dashboard' ), 20, 3 );

  add_action( 'admin_post_hj_hooks_status', array( $this, 'status_page' ) );

 }



 /* =====================================================================

  * Registry — documentation + status data (single source of truth)

  * type: action | filter | auto (status page detects it from the code)

  * ===================================================================== */

 public static function registry() {

  $r = array(

   // Emitted by earlier sections

   'hj_doctor_after_schedule' => array( 'action', 'پروفایل (بخش ۷)', 'بعد از ساعات کاری؛ نقطهٔ اتصال دکمهٔ نوبت‌گیری' ),

   'hj_doctor_rating_box' => array( 'action', 'پروفایل (بخش ۷)', 'جعبهٔ امتیاز' ),

   'hj_doctor_embed_box' => array( 'action', 'پروفایل (بخش ۷ و ۱۲)', 'جعبهٔ کد جاسازی' ),

   'hj_doctor_dashboard_appointments' => array( 'action', 'داشبورد (بخش ۶)', 'جایگاه مدیریت نوبت‌ها' ),

   'hj_doctor_dashboard_widgets' => array( 'action', 'داشبورد (بخش ۶)', 'ویجت‌های اضافهٔ داشبورد' ),

   'hj_doctor_archive_filters' => array( 'action', 'آرشیو (بخش ۸)', 'فیلترهای اضافه' ),

   'hj_doctor_archive_after_results' => array( 'action', 'آرشیو (بخش ۸)', 'بعد از نتایج (برای FacetWP؛ نیازمند اصلاح یک‌خطی)' ),

   'hj_doctor_card_extra_html' => array( 'auto', 'کارت پزشک (بخش ۸)', 'افزودن HTML به کارت' ),

   'hj_doctor_header_search' => array( 'action', 'هدر (بخش ۱۰)', 'فرم جست‌وجوی پزشک' ),

   'hj_doctor_rating_stats' => array( 'filter', 'امتیاز (بخش ۹)', 'آمار امتیاز عمومی' ),

   'hj_doctor_vote_recorded' => array( 'action', 'امتیاز (بخش ۹)', 'بعد از ثبت رأی' ),

   'hj_doctors_loaded' => array( 'action', 'لودر (بخش ۱)', 'بعد از بارگذاری همهٔ ماژول‌ها' ),

   'hj_doctors_google_user_ready' => array( 'action', 'ورود (بخش ۵)', 'کاربر گوگل آماده شد' ),

   'hj_seed_imported' => array( 'action', 'ورود داده (بخش ۱۴)', 'بعد از ورود موفق استان/شهر' ),

   // New in section 15

   'hj_appt_profile_slot' => array( 'action', 'پل بخش ۱۵ ← پروفایل', 'افزونهٔ نوبت‌دهی اینجا دکمه/فرم را چاپ می‌کند. آرگومان: شناسهٔ پزشک' ),

   'hj_appt_dashboard_panel' => array( 'action', 'پل بخش ۱۵ ← داشبورد', 'افزونهٔ نوبت‌دهی اینجا پنل نوبت‌ها را چاپ می‌کند. آرگومان: شناسهٔ پزشک' ),

   'hj_appointments_enabled' => array( 'filter', 'کلید کلی', 'افزونهٔ آینده true برمی‌گرداند' ),

   'hj_appointments_doctor_enabled' => array( 'filter', 'کلید هر پزشک', 'فعال/غیرفعال بودن نوبت‌دهی برای یک پزشک' ),

   'hj_doctor_schedule_for_booking' => array( 'filter', 'منبع ساعات کاری', 'تنظیم خروجی ساعات کاری برای تولید اسلات' ),

   'hj_doctor_booking_context' => array( 'filter', 'اطلاعات عمومی پزشک', 'تنظیم داده‌های عمومی پزشک' ),

  );

  return (array) apply_filters( 'hj_hooks_registry', $r );

 }



 /** Names reserved for the future add-on (documentation + conflict check). */

 public static function reserved() {

  return array(

   'db_prefix' => 'hj_appt_',

   'rest' => 'hj/v1/appointments',

   'caps' => array( 'hj_manage_own_appointments', 'hj_manage_all_appointments' ),

   'meta' => 'hj_appt_* (روی پست doctor)',

   'options' => 'hj_appt_*',

   'css_prefix' => 'hj-appt-',

   'plugin' => 'hamyar-appointments (افزونهٔ جدا)',

  );

 }



 /* =====================================================================

  * Bridges

  * ===================================================================== */

 private function resolve_id( $args, $dashboard = false ) {

  foreach ( (array) $args as $a ) {

   if ( is_object( $a ) && isset( $a->ID ) && (int) $a->ID > 0 ) { return (int) $a->ID; }

   if ( is_numeric( $a ) && (int) $a > 0 ) { return (int) $a; }

  }

  if ( $dashboard ) {

   if ( function_exists( 'hj_doctors_get_profile_id' ) && is_user_logged_in() ) {

    return (int) hj_doctors_get_profile_id( get_current_user_id() );

   }

   return 0;

  }

  return (int) get_the_ID();

 }



 /** Runs an add-on action inside a buffer; returns '' if nothing was printed or on any error. */

 private function render_slot( $action, $id, $kind ) {

  $level = ob_get_level();

  ob_start();

  try {

   do_action( $action, (int) $id );

   $html = (string) ob_get_clean();

  } catch ( Throwable $e ) {

   while ( ob_get_level() > $level ) { ob_end_clean(); }

   if ( function_exists( 'hj_doctors_log' ) ) { hj_doctors_log( 'hooks: ' . $action . ' failed: ' . $e->getMessage() ); }

   return '';

  }

  if ( '' === trim( $html ) ) { return ''; }

  return '<div class="hj-appt-slot hj-appt-' . esc_attr( $kind ) . '" data-hj-doctor="' . (int) $id . '">' . $html . '</div>';

 }



 public function profile_html( $id ) {

  if ( ! hj_appointments_enabled_for( $id ) ) { return ''; }

  return $this->render_slot( 'hj_appt_profile_slot', $id, 'profile' );

 }



 public function dashboard_html( $id ) {

  $id = (int) $id;

  if ( $id <= 0 || ! hj_appointments_enabled() ) { return ''; }

  $can = function_exists( 'hj_doctors_user_can_edit_profile' ) ? (bool) hj_doctors_user_can_edit_profile( $id ) : current_user_can( 'manage_options' );

  if ( ! $can ) { return ''; }

  return $this->render_slot( 'hj_appt_dashboard_panel', $id, 'dashboard' );

 }



 public function bridge_profile() {

  $id = $this->resolve_id( func_get_args(), false );

  echo $this->profile_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- add-on markup, wrapper escaped

 }



 public function bridge_dashboard() {

  $id = $this->resolve_id( func_get_args(), true );

  echo $this->dashboard_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- add-on markup, wrapper escaped

 }



 /* =====================================================================

  * Status page

  * ===================================================================== */

 private function source_files() {

  $files = array();

  $base = defined( 'HJ_DOCTORS_PATH' ) ? trailingslashit( HJ_DOCTORS_PATH ) : trailingslashit( dirname( dirname( __FILE__ ) ) );

  $main = $base . 'hamyar-doctors.php';

  if ( file_exists( $main ) ) { $files[] = $main; }

  foreach ( (array) glob( $base . 'includes/*.php' ) as $f ) { $files[] = $f; }

  $child = trailingslashit( get_stylesheet_directory() );

  foreach ( (array) glob( $child . '*.php' ) as $f ) { $files[] = $f; }

  return $files;

 }



 private function listeners( $name ) {

  global $wp_filter;

  $n = 0;

  if ( isset( $wp_filter[ $name ] ) && is_object( $wp_filter[ $name ] ) && isset( $wp_filter[ $name ]->callbacks ) ) {

   foreach ( (array) $wp_filter[ $name ]->callbacks as $cbs ) { $n += count( (array) $cbs ); }

  }

  return $n;

 }



 private function self_test() {

  $t = array();

  $ids = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'posts_per_page' => 5, 'fields' => 'ids', 'no_found_rows' => true ) );

  $id = 0;

  foreach ( (array) $ids as $i ) {

   if ( ! function_exists( 'hj_dp_is_public' ) || hj_dp_is_public( (int) $i ) ) { $id = (int) $i; break; }

  }

  if ( ! $id ) { return array( array( 'پزشک عمومی برای تست پیدا نشد', null ) ); }



  if ( ! hj_appointments_enabled() ) {

   $t[] = array( 'حالت پیش‌فرض (خاموش): خروجی پروفایل خالی است', '' === $this->profile_html( $id ) );

  } else {

   $t[] = array( 'نوبت‌دهی روشن است (افزونه فعال)؛ تست حالت خاموش رد شد', null );

  }



  $cb = function ( $d ) { echo '<i>HJMARK</i>'; };

  add_filter( 'hj_appointments_enabled', '__return_true', 9999 );

  add_action( 'hj_appt_profile_slot', $cb );

  $h = $this->profile_html( $id );

  $t[] = array( 'روشن + شنونده: خروجی داخل hj-appt-slot با data-hj-doctor درست', false !== strpos( $h, 'HJMARK' ) && false !== strpos( $h, 'hj-appt-slot' ) && false !== strpos( $h, 'data-hj-doctor="' . $id . '"' ) );

  $t[] = array( 'شناسهٔ نامعتبر: خروجی خالی', '' === $this->profile_html( 0 ) );

  remove_action( 'hj_appt_profile_slot', $cb );



  $lvl = ob_get_level();

  $bad = function ( $d ) { echo 'x'; throw new Exception( 'test' ); };

  add_action( 'hj_appt_profile_slot', $bad );

  $h2 = $this->profile_html( $id );

  remove_action( 'hj_appt_profile_slot', $bad );

  $t[] = array( 'شنوندهٔ دارای استثنا: خروجی خالی، بدون Fatal و بدون بافر باز', '' === $h2 && ob_get_level() === $lvl );



  $h3 = $this->profile_html( $id );

  $t[] = array( 'بدون شنونده: هیچ wrapper خالی چاپ نمی‌شود', '' === $h3 );

  remove_filter( 'hj_appointments_enabled', '__return_true', 9999 );

  return $t;

 }



 public function status_page() {

  if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }

  nocache_headers();

  global $wpdb;

  $e = 'esc_html';



  $sources = array();

  foreach ( $this->source_files() as $f ) {

   $sz = @filesize( $f );

   if ( false === $sz || $sz > 800000 ) { continue; }

   $c = @file_get_contents( $f );

   if ( false !== $c ) { $sources[ basename( $f ) ] = $c; }

  }



  $o = '<h1>hj_hooks_status</h1>';

  $o .= '<h2>کلید نوبت‌دهی</h2><ul>';

  $o .= '<li>hj_appointments_enabled(): <strong>' . ( hj_appointments_enabled() ? 'روشن' : 'خاموش (پیش‌فرض)' ) . '</strong></li>';

  $o .= '<li>ثابت HJ_APPOINTMENTS_ENABLED: ' . ( defined( 'HJ_APPOINTMENTS_ENABLED' ) ? ( HJ_APPOINTMENTS_ENABLED ? 'true' : 'false' ) : 'تعریف نشده' ) . '</li>';

  $o .= '<li>پل پروفایل: ' . ( has_action( 'hj_doctor_after_schedule', array( $this, 'bridge_profile' ) ) ? 'وصل' : 'وصل نیست' ) . '</li>';

  $o .= '<li>پل داشبورد: ' . ( has_action( 'hj_doctor_dashboard_appointments', array( $this, 'bridge_dashboard' ) ) ? 'وصل' : 'وصل نیست' ) . '</li></ul>';



  $o .= '<h2>تست خودکار پل‌ها</h2><ul>';

  foreach ( $this->self_test() as $r ) {

   $mark = null === $r[1] ? '—' : ( $r[1] ? 'موفق' : '<strong>ناموفق</strong>' );

   $o .= '<li>' . $e( $r[0] ) . ': ' . $mark . '</li>';

  }

  $o .= '</ul>';



  $o .= '<h2>ثبت‌نامهٔ قلاب‌ها</h2><p>«منتشر می‌شود» از روی اسکن واقعی فایل‌های افزونه و قالب فرزند آمده است. «شنونده‌ها» شامل پل‌های همین بخش هم می‌شود.</p>';

  $o .= '<table border="1" cellpadding="6" style="border-collapse:collapse"><tr><th>نام</th><th>نوع (اعلامی / واقعی)</th><th>جایگاه</th><th>منتشر می‌شود در</th><th>شنونده‌ها</th><th>توضیح</th></tr>';

  foreach ( self::registry() as $name => $m ) {

   $where = array(); $real = array();

   $re = '/\b(do_action|apply_filters)(?:_ref_array)?\s*\(\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]/';

   foreach ( $sources as $file => $code ) {

    if ( preg_match( $re, $code, $mm ) ) { $where[] = $file; $real[ $mm[1] ] = 1; }

   }

   $real_t = $real ? ( isset( $real['do_action'] ) ? 'action' : 'filter' ) : '—';

   $o .= '<tr><td>' . $e( $name ) . '</td><td>' . $e( $m[0] ) . ' / ' . $e( $real_t ) . '</td><td>' . $e( $m[1] ) . '</td><td>'

    . ( $where ? $e( implode( '، ', array_unique( $where ) ) ) : 'پیدا نشد' ) . '</td><td>' . (int) $this->listeners( $name ) . '</td><td>' . $e( $m[2] ) . '</td></tr>';

  }

  $o .= '</table>';



  $o .= '<h2>نام‌های رزروشده برای نوبت‌دهی</h2><ul>';

  foreach ( self::reserved() as $k => $v ) {

   $o .= '<li>' . $e( $k ) . ': ' . $e( is_array( $v ) ? implode( ' ، ', $v ) : $v ) . '</li>';

  }

  $like = $wpdb->esc_like( $wpdb->prefix . 'hj_appt_' ) . '%';

  $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

  $o .= '<li>جدول‌های hj_appt_ موجود: ' . ( $tables ? $e( implode( '، ', $tables ) ) : 'هیچ (انتظار می‌رود)' ) . '</li>';

  $o .= '<li>جدول آرا ( hj_doctor_votes ) در این بخش دست‌نخورده است.</li></ul>';



  echo '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>hj_hooks_status</title></head><body style="font-family:Tahoma,sans-serif;max-width:1000px;margin:20px auto;line-height:1.9">' . $o . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput

  exit;

 }

}



endif;



HJ_Doctors_Hooks::boot();

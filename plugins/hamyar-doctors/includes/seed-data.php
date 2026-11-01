<?php

/**

 * Hamyar Doctors — includes/seed-data.php (Section 14)

 *

 * 1) Admin importer (CSV / JSON) for province + city (relation via term meta hj_province_id)

 * 2) Specialties seeder (only missing terms)

 * 3) Default operating-room photo fallback

 * 4) Status page: /wp-admin/admin-post.php?action=hj_seed_status

 *

 * Filters: hj_seed_aliases, hj_seed_specialties, hj_seed_max_rows, hj_seed_max_file_bytes,

 * hj_seed_expected, hj_seed_fallback_enabled

 * Action : hj_seed_imported

 */

if ( ! defined( 'ABSPATH' ) ) { exit; }



if ( ! class_exists( 'HJ_Doctors_Seed' ) ) :



class HJ_Doctors_Seed {



 const PAGE = 'hj-seed';

 const NONCE = 'hj_seed';

 const OPT_IMG = 'hj_default_photo_id';



 private static $inst = null;

 private static $fb_cache = null;



 private $dry = true;

 private $upd = true;

 private $fake = 0;

 private $P_slug = array();

 private $P_name = array();

 private $C_key = array();

 private $C_slug = array();

 private $C_pid = array();

 private $slugs = array( 'province' => array(), 'city' => array() );

 private $seen_p = array();

 private $seen_c = array();

 private $rep = array();



 public static function boot() {

  if ( ! self::$inst ) { self::$inst = new self(); }

  return self::$inst;

 }



 private function __construct() {

  add_action( 'admin_menu', array( $this, 'menu' ) );

  add_action( 'admin_post_hj_seed_status', array( $this, 'status_page' ) );

  add_action( 'admin_post_hj_seed_template', array( $this, 'template_csv' ) );

  add_action( 'init', array( $this, 'maybe_placeholder' ), 1 );

  add_filter( 'post_thumbnail_html', array( $this, 'fallback_thumb_html' ), 20, 5 );

  add_filter( 'post_thumbnail_url', array( $this, 'fallback_thumb_url' ), 20, 3 );

  add_filter( 'hj_doctors_default_photo_url', array( $this, 'filter_default_url' ), 20 );

 }



 /* =====================================================================

  * Specialties (fixed list from the project spec — not geographic data)

  * ===================================================================== */

 public static function specialties() {

  return apply_filters( 'hj_seed_specialties', array(

   'orthopedics' => array( 'ارتوپدی', 'Orthopedics' ),

   'urology' => array( 'اورولوژی', 'Urology' ),

   'ophthalmology' => array( 'چشم‌پزشکی', 'Ophthalmology' ),

   'cardiology' => array( 'قلب و عروق', 'Cardiology' ),

   'neurosurgery' => array( 'جراحی مغز و اعصاب', 'Neurosurgery' ),

   'gynecology' => array( 'زنان و زایمان', 'Gynecology' ),

   'ent' => array( 'گوش، حلق و بینی', 'ENT' ),

   'dermatology' => array( 'پوست و مو', 'Dermatology' ),

   'general-surgery' => array( 'جراحی عمومی', 'General Surgery' ),

   'cosmetic' => array( 'جراحی زیبایی', 'Cosmetic Surgery' ),

   'anesthesiology' => array( 'بیهوشی', 'Anesthesiology' ),

  ) );

 }



 /* =====================================================================

  * Default photo fallback

  * ===================================================================== */

 public static function fallback_url() {

  if ( null !== self::$fb_cache ) { return self::$fb_cache; }

  $url = '';



  $att = (int) get_option( self::OPT_IMG, 0 );

  if ( $att > 0 ) {

   $u = wp_get_attachment_image_url( $att, 'large' );

   if ( $u ) { $url = $u; }

  }



  if ( '' === $url && defined( 'HJ_DOCTORS_FILE' ) ) {

   $dir = plugin_dir_path( HJ_DOCTORS_FILE );

   $base = plugin_dir_url( HJ_DOCTORS_FILE );

   foreach ( array( 'jpg', 'webp', 'png' ) as $ext ) {

    $rel = 'assets/img/operating-room.' . $ext;

    if ( file_exists( $dir . $rel ) ) {

     $url = $base . $rel . '?v=' . (int) @filemtime( $dir . $rel );

     break;

    }

   }

  }



  if ( '' === $url ) { $url = home_url( '/?hj_ph=1' ); }



  self::$fb_cache = (string) apply_filters( 'hj_default_photo_url', $url );

  return self::$fb_cache;

 }



 public function filter_default_url( $url ) {

  return self::fallback_url();

 }



 public function fallback_thumb_html( $html, $post_id, $thumb_id, $size, $attr ) {

  if ( '' !== (string) $html ) { return $html; }

  if ( 'doctor' !== get_post_type( $post_id ) ) { return $html; }

  if ( ! apply_filters( 'hj_seed_fallback_enabled', true, $post_id ) ) { return $html; }

  $cls = 'hj-photo-fallback';

  if ( is_array( $attr ) && ! empty( $attr['class'] ) ) { $cls .= ' ' . $attr['class']; }

  return '<img src="' . esc_url( self::fallback_url() ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" class="' . esc_attr( $cls ) . '" loading="lazy" decoding="async" style="object-fit:cover" />';

 }



 public function fallback_thumb_url( $url, $post = null, $size = '' ) {

  if ( $url ) { return $url; }

  if ( 'doctor' !== get_post_type( $post ) ) { return $url; }

  if ( ! apply_filters( 'hj_seed_fallback_enabled', true, $post ) ) { return $url; }

  return self::fallback_url();

 }



 public function maybe_placeholder() {

  if ( ! isset( $_GET['hj_ph'] ) || '1' !== $_GET['hj_ph'] ) { return; }

  $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#061A2E"/><stop offset="1" stop-color="#0F3556"/></linearGradient></defs><rect width="400" height="400" fill="url(#g)"/><circle cx="200" cy="170" r="56" fill="none" stroke="#14C8C0" stroke-width="8"/><path d="M200 142v56M172 170h56" stroke="#14C8C0" stroke-width="8" stroke-linecap="round"/><rect x="120" y="262" width="160" height="10" rx="5" fill="#14C8C0" opacity=".5"/></svg>';

  header( 'Content-Type: image/svg+xml; charset=utf-8' );

  header( 'Cache-Control: public, max-age=86400' );

  header( 'X-Content-Type-Options: nosniff' );

  echo $svg; // static markup, no user input

  exit;

 }



 /* =====================================================================

  * Text helpers

  * ===================================================================== */

 private function fa( $v ) {

  if ( function_exists( 'hj_digits' ) ) { return hj_digits( (string) $v, 'fa' ); }

  return strtr( (string) $v, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );

 }



 private function fix_fa( $s ) {

  $s = (string) $s;

  $s = str_replace( array( "\xEF\xBB\xBF", "\xD9\x80" ), '', $s ); // BOM, tatweel

  $s = strtr( $s, array( "\xD9\x8A" => "\xDB\x8C", "\xD9\x89" => "\xDB\x8C", "\xD9\x83" => "\xDA\xA9" ) ); // ي ى ك → ی ی ک

  $s = preg_replace( '/\s+/u', ' ', $s );

  return trim( (string) $s );

 }



 private function key_of( $s, $type ) {

  $s = $this->fix_fa( $s );

  $s = str_replace( "\xE2\x80\x8C", '', $s ); // ZWNJ

  $re = ( 'province' === $type ) ? '/^(استان)\s+/u' : '/^(شهرستان|شهر)\s+/u';

  $s2 = preg_replace( $re, '', $s );

  if ( is_string( $s2 ) && '' !== $s2 ) { $s = $s2; }

  return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );

 }



 private function clean_cell( $v ) {

  if ( ! is_scalar( $v ) ) { return ''; }

  return sanitize_text_field( $this->fix_fa( (string) $v ) );

 }



 private function slug_in( $v ) {

  $v = is_scalar( $v ) ? trim( wp_strip_all_tags( (string) $v ) ) : '';

  return '' === $v ? '' : sanitize_title( $v );

 }



 private function norm_header( $h ) {

  $h = $this->fix_fa( $h );

  $h = str_replace( "\xE2\x80\x8C", '_', $h );

  $h = function_exists( 'mb_strtolower' ) ? mb_strtolower( $h, 'UTF-8' ) : strtolower( $h );

  $h = preg_replace( '/[\s\-\.\/]+/u', '_', $h );

  return trim( (string) $h, '_' );

 }



 private function aliases() {

  return apply_filters( 'hj_seed_aliases', array(

   'province' => array( 'province', 'province_name', 'province_fa', 'provincename', 'ostan', 'state', 'استان', 'نام_استان', 'استان_فارسی' ),

   'province_slug' => array( 'province_slug', 'ostan_slug', 'slug_province', 'اسلاگ_استان' ),

   'province_en' => array( 'province_en', 'province_name_en', 'ostan_en', 'استان_انگلیسی', 'نام_انگلیسی_استان' ),

   'city' => array( 'city', 'city_name', 'city_fa', 'county', 'county_name', 'shahrestan', 'شهرستان', 'شهر', 'نام_شهرستان', 'نام_شهر' ),

   'city_slug' => array( 'city_slug', 'county_slug', 'shahrestan_slug', 'اسلاگ_شهرستان', 'اسلاگ_شهر' ),

   'city_en' => array( 'city_en', 'city_name_en', 'county_en', 'شهرستان_انگلیسی', 'نام_انگلیسی_شهرستان' ),

   'g_name' => array( 'name', 'title', 'نام', 'name_fa', 'fa', 'label' ),

   'g_slug' => array( 'slug' ),

   'g_en' => array( 'name_en', 'en', 'english' ),

   'children' => array( 'cities', 'counties', 'children', 'items', 'شهرستان_ها', 'شهرها', 'شهر_ها', 'شهرستانها' ),

  ) );

 }



 private function canon( $h ) {

  $k = $this->norm_header( $h );

  if ( '' === $k ) { return ''; }

  foreach ( $this->aliases() as $c => $list ) {

   if ( in_array( $k, $list, true ) ) { return $c; }

  }

  return '';

 }



 private function canon_arr( $arr ) {

  $out = array();

  foreach ( (array) $arr as $k => $v ) {

   $c = $this->canon( (string) $k );

   if ( '' !== $c && ! isset( $out[ $c ] ) ) { $out[ $c ] = $v; }

  }

  return $out;

 }



 private function first( $arr, $keys ) {

  foreach ( $keys as $k ) {

   if ( isset( $arr[ $k ] ) && is_scalar( $arr[ $k ] ) && '' !== trim( (string) $arr[ $k ] ) ) { return (string) $arr[ $k ]; }

  }

  return '';

 }



 private function is_list( $a ) {

  if ( ! is_array( $a ) ) { return false; }

  return array_keys( $a ) === range( 0, count( $a ) - 1 ) || empty( $a );

 }



 /* =====================================================================

  * File reading / parsing

  * ===================================================================== */

 private function read_upload( $file, &$err ) {

  $err = '';

  if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {

   $err = 'فایل آپلود نشد (کد خطا: ' . ( isset( $file['error'] ) ? (int) $file['error'] : '-' ) . '). فایل را دوباره انتخاب کنید.';

   return '';

  }

  $max = (int) apply_filters( 'hj_seed_max_file_bytes', 3 * MB_IN_BYTES );

  if ( (int) $file['size'] > $max ) { $err = 'حجم فایل بیش از حد مجاز است.'; return ''; }

  $ext = strtolower( pathinfo( sanitize_file_name( (string) $file['name'] ), PATHINFO_EXTENSION ) );

  if ( ! in_array( $ext, array( 'csv', 'json', 'txt' ), true ) ) { $err = 'فقط فایل csv یا json پذیرفته می‌شود.'; return ''; }

  if ( ! is_uploaded_file( $file['tmp_name'] ) ) { $err = 'فایل معتبر نیست.'; return ''; }



  $raw = file_get_contents( $file['tmp_name'] );

  if ( false === $raw || '' === $raw ) { $err = 'فایل خالی است.'; return ''; }



  $utf16 = false;

  if ( "\xFF\xFE" === substr( $raw, 0, 2 ) && function_exists( 'mb_convert_encoding' ) ) {

   $raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16LE' ); $utf16 = true;

  } elseif ( "\xFE\xFF" === substr( $raw, 0, 2 ) && function_exists( 'mb_convert_encoding' ) ) {

   $raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' ); $utf16 = true;

  }



  if ( ! $utf16 ) {

   if ( false !== strpos( $raw, "\0" ) ) { $err = 'فایل متنی نیست (احتمالاً xlsx است). آن را با «Save as → CSV UTF-8» ذخیره کنید.'; return ''; }

   if ( function_exists( 'finfo_open' ) ) {

    $fi = finfo_open( FILEINFO_MIME_TYPE );

    $m = $fi ? (string) finfo_file( $fi, $file['tmp_name'] ) : '';

    if ( $fi ) { finfo_close( $fi ); }

    if ( '' !== $m && ! preg_match( '#^(text/|application/(json|csv|vnd\.ms-excel))#', $m ) ) { $err = 'نوع فایل پذیرفته نمی‌شود (' . $m . ').'; return ''; }

   }

  }



  if ( "\xEF\xBB\xBF" === substr( $raw, 0, 3 ) ) { $raw = substr( $raw, 3 ); }



  if ( ! preg_match( '//u', $raw ) ) { // not valid UTF-8 → try Windows-1256 (Persian Excel)

   if ( function_exists( 'mb_convert_encoding' ) ) { $raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1256' ); }

   elseif ( function_exists( 'iconv' ) ) { $raw = (string) iconv( 'Windows-1256', 'UTF-8//IGNORE', $raw ); }

  }

  return $raw;

 }



 private function parse_csv( $raw, &$err ) {

  $first = strtok( $raw, "\n" );

  $first = false === $first ? '' : $first;

  $cnt = array( ',' => substr_count( $first, ',' ), ';' => substr_count( $first, ';' ), "\t" => substr_count( $first, "\t" ), '|' => substr_count( $first, '|' ) );

  arsort( $cnt );

  $d = key( $cnt );



  $fh = fopen( 'php://temp', 'r+' );

  if ( ! $fh ) { $err = 'خواندن فایل ممکن نشد.'; return array(); }

  fwrite( $fh, $raw );

  rewind( $fh );



  $head = null; $rows = array(); $line = 0;

  while ( false !== ( $r = fgetcsv( $fh, 0, $d, '"', '\\' ) ) ) {

   $line++;

   if ( null === $r || ( 1 === count( $r ) && '' === trim( (string) $r[0] ) ) ) { continue; }

   if ( null === $head ) {

    $head = array();

    foreach ( $r as $i => $h ) { $head[ $i ] = $this->canon( (string) $h ); }

    if ( ! in_array( 'province', $head, true ) ) {

     $err = 'ستون «استان» پیدا نشد. ستون‌های خوانده‌شده: ' . implode( ' | ', array_map( 'sanitize_text_field', $r ) );

     fclose( $fh );

     return array();

    }

    continue;

   }

   $row = array( 'line' => $line );

   foreach ( $head as $i => $c ) {

    if ( '' === $c ) { continue; }

    $row[ $c ] = isset( $r[ $i ] ) ? $r[ $i ] : '';

   }

   $rows[] = $row;

  }

  fclose( $fh );

  return $rows;

 }



 private function push_prov( &$rows, $pn, $ps, $pe, $children, $line ) {

  $base = array( 'line' => $line, 'province' => (string) $pn, 'province_slug' => (string) $ps, 'province_en' => (string) $pe );

  if ( ! is_array( $children ) || empty( $children ) ) {

   $rows[] = $base + array( 'city' => '', 'city_slug' => '', 'city_en' => '' );

   return;

  }

  foreach ( $children as $ch ) {

   $cn = $cs = $ce = '';

   if ( is_scalar( $ch ) ) {

    $cn = (string) $ch;

   } elseif ( is_array( $ch ) ) {

    $cc = $this->canon_arr( $ch );

    $cn = $this->first( $cc, array( 'city', 'g_name' ) );

    $cs = $this->first( $cc, array( 'city_slug', 'g_slug' ) );

    $ce = $this->first( $cc, array( 'city_en', 'g_en' ) );

   }

   $rows[] = $base + array( 'city' => $cn, 'city_slug' => $cs, 'city_en' => $ce );

  }

 }



 private function rows_from_json( $raw, &$err ) {

  $data = json_decode( $raw, true );

  if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) { $err = 'JSON نامعتبر: ' . json_last_error_msg(); return array(); }

  if ( ! is_array( $data ) ) { $err = 'ساختار JSON قابل‌فهم نیست.'; return array(); }

  foreach ( array( 'provinces', 'data', 'items', 'rows' ) as $k ) {

   if ( isset( $data[ $k ] ) && is_array( $data[ $k ] ) ) { $data = $data[ $k ]; break; }

  }

  $rows = array(); $n = 0;

  foreach ( $data as $k => $v ) {

   $n++;

   if ( is_string( $k ) && is_array( $v ) && $this->is_list( $v ) ) { // { "استان": ["شهر", ...] }

    $this->push_prov( $rows, $k, '', '', $v, $n );

    continue;

   }

   if ( ! is_array( $v ) ) { continue; }

   $c = $this->canon_arr( $v );

   if ( isset( $c['children'] ) && is_array( $c['children'] ) ) { // nested province object

    $this->push_prov( $rows, $this->first( $c, array( 'province', 'g_name' ) ), $this->first( $c, array( 'province_slug', 'g_slug' ) ), $this->first( $c, array( 'province_en', 'g_en' ) ), $c['children'], $n );

   } else { // flat row object

    $row = array( 'line' => $n );

    foreach ( array( 'province', 'province_slug', 'province_en', 'city', 'city_slug', 'city_en' ) as $f ) {

     $row[ $f ] = ( isset( $c[ $f ] ) && is_scalar( $c[ $f ] ) ) ? $c[ $f ] : '';

    }

    $rows[] = $row;

   }

  }

  return $rows;

 }



 /* =====================================================================

  * Import engine

  * ===================================================================== */

 private function load_maps() {

  $this->P_slug = $this->P_name = $this->C_key = $this->C_slug = $this->C_pid = array();

  $this->slugs = array( 'province' => array(), 'city' => array() );

  $this->seen_p = $this->seen_c = array();



  $pt = get_terms( array( 'taxonomy' => 'province', 'hide_empty' => false, 'number' => 0 ) );

  if ( is_wp_error( $pt ) ) { return $pt->get_error_message(); }

  foreach ( $pt as $t ) {

   $this->P_slug[ $t->slug ] = $t;

   $this->P_name[ $this->key_of( $t->name, 'province' ) ] = $t;

   $this->slugs['province'][ $t->slug ] = 1;

  }

  $ct = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => false, 'number' => 0 ) );

  if ( is_wp_error( $ct ) ) { return $ct->get_error_message(); }

  foreach ( $ct as $t ) {

   $pid = (int) get_term_meta( $t->term_id, 'hj_province_id', true );

   $this->C_pid[ $t->term_id ] = $pid;

   $this->C_slug[ $t->slug ] = $t;

   $this->C_key[ $pid . '|' . $this->key_of( $t->name, 'city' ) ] = $t;

   $this->slugs['city'][ $t->slug ] = 1;

  }

  return true;

 }



 private function unique_slug( $tax, $base, $extras = array() ) {

  $base = '' === $base ? $tax : $base;

  $cands = array( $base );

  foreach ( $extras as $e ) { if ( '' !== $e ) { $cands[] = $base . '-' . $e; } }

  foreach ( $cands as $c ) {

   if ( ! isset( $this->slugs[ $tax ][ $c ] ) ) { return $c; }

  }

  $n = 2;

  while ( isset( $this->slugs[ $tax ][ $base . '-' . $n ] ) ) { $n++; }

  return $base . '-' . $n;

 }



 private function err( $line, $msg ) {

  if ( count( $this->rep['err'] ) < 200 ) { $this->rep['err'][] = array( $line, $msg ); }

 }

 private function skip( $line, $msg ) {

  if ( count( $this->rep['skip'] ) < 200 ) { $this->rep['skip'][] = array( $line, $msg ); }

 }

 private function prev( $line, $p, $c, $act ) {

  if ( count( $this->rep['prev'] ) < 30 ) { $this->rep['prev'][] = array( $line, $p, $c, $act ); }

 }



 private function upsert_province( $name, $slug_in, $en, $line ) {

  $key = $this->key_of( $name, 'province' );

  if ( isset( $this->seen_p[ $key ] ) ) {

   $r = $this->seen_p[ $key ]; $r['act'] = 'seen'; return $r;

  }

  $term = null;

  if ( '' !== $slug_in && isset( $this->P_slug[ $slug_in ] ) ) { $term = $this->P_slug[ $slug_in ]; }

  elseif ( isset( $this->P_name[ $key ] ) ) { $term = $this->P_name[ $key ]; }



  if ( $term ) {

   $changed = false;

   if ( $this->upd && $term->name !== $name ) {

    if ( $this->dry ) { $changed = true; }

    else {

     $r = wp_update_term( (int) $term->term_id, 'province', array( 'name' => $name, 'slug' => $term->slug ) );

     if ( is_wp_error( $r ) ) { $this->err( $line, 'به‌روزرسانی استان «' . $name . '»: ' . $r->get_error_message() ); }

     else { $changed = true; }

    }

   }

   if ( '' !== $en && (string) get_term_meta( $term->term_id, 'hj_name_en', true ) !== $en ) {

    if ( ! $this->dry ) { update_term_meta( (int) $term->term_id, 'hj_name_en', $en ); }

    $changed = true;

   }

   $act = $changed ? 'updated' : 'same';

   $this->rep['p'][ $act ]++;

   $out = array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'act' => $act );

  } else {

   $slug = $this->unique_slug( 'province', '' !== $slug_in ? $slug_in : sanitize_title( $name ) );

   if ( $this->dry ) {

    $id = --$this->fake;

   } else {

    $r = wp_insert_term( $name, 'province', array( 'slug' => $slug ) );

    if ( is_wp_error( $r ) ) { $this->err( $line, 'ساخت استان «' . $name . '»: ' . $r->get_error_message() ); return null; }

    $id = (int) $r['term_id'];

    if ( '' !== $en ) { update_term_meta( $id, 'hj_name_en', $en ); }

   }

   $obj = (object) array( 'term_id' => $id, 'name' => $name, 'slug' => $slug );

   $this->P_slug[ $slug ] = $obj;

   $this->P_name[ $key ] = $obj;

   $this->slugs['province'][ $slug ] = 1;

   $this->rep['p']['created']++;

   $out = array( 'id' => $id, 'slug' => $slug, 'act' => 'created' );

  }

  $this->seen_p[ $key ] = array( 'id' => $out['id'], 'slug' => $out['slug'] );

  return $out;

 }



 private function upsert_city( $name, $slug_in, $en, $p, $line, $pname ) {

  $pid = (int) $p['id'];

  $nkey = $this->key_of( $name, 'city' );

  $key = $pid . '|' . $nkey;

  if ( isset( $this->seen_c[ $key ] ) ) { $this->skip( $line, 'شهرستان تکراری در فایل: ' . $name ); return; }

  $this->seen_c[ $key ] = 1;



  $term = null; $adopt = false;

  if ( isset( $this->C_key[ $key ] ) ) {

   $term = $this->C_key[ $key ];

  } elseif ( '' !== $slug_in && isset( $this->C_slug[ $slug_in ] ) && in_array( (int) $this->C_pid[ $this->C_slug[ $slug_in ]->term_id ], array( $pid, 0 ), true ) ) {

   $term = $this->C_slug[ $slug_in ];

   $adopt = ( 0 === (int) $this->C_pid[ $term->term_id ] );

  } elseif ( isset( $this->C_key[ '0|' . $nkey ] ) ) {

   $term = $this->C_key[ '0|' . $nkey ];

   $adopt = true;

  }



  if ( $term ) {

   $tid = (int) $term->term_id; $changed = false;

   if ( $adopt ) {

    if ( ! $this->dry ) { update_term_meta( $tid, 'hj_province_id', $pid ); }

    $this->C_pid[ $tid ] = $pid;

    $this->C_key[ $key ] = $term;

    $this->rep['c']['adopted']++;

    $changed = true;

   }

   if ( $this->upd && $term->name !== $name ) {

    if ( $this->dry ) { $changed = true; }

    else {

     $r = wp_update_term( $tid, 'city', array( 'name' => $name, 'slug' => $term->slug ) );

     if ( is_wp_error( $r ) ) { $this->err( $line, 'به‌روزرسانی شهرستان «' . $name . '»: ' . $r->get_error_message() ); }

     else { $changed = true; }

    }

   }

   if ( '' !== $en && (string) get_term_meta( $tid, 'hj_name_en', true ) !== $en ) {

    if ( ! $this->dry ) { update_term_meta( $tid, 'hj_name_en', $en ); }

    $changed = true;

   }

   if ( $adopt ) { $act = 'adopted'; }

   else { $act = $changed ? 'updated' : 'same'; $this->rep['c'][ $act ]++; }

   $this->prev( $line, $pname, $name, $act );

   return;

  }



  $slug = $this->unique_slug( 'city', '' !== $slug_in ? $slug_in : sanitize_title( $name ), array( $p['slug'] ) );

  if ( $this->dry ) {

   $id = --$this->fake;

  } else {

   $r = wp_insert_term( $name, 'city', array( 'slug' => $slug ) );

   if ( is_wp_error( $r ) ) { $this->err( $line, 'ساخت شهرستان «' . $name . '» (' . $pname . '): ' . $r->get_error_message() ); return; }

   $id = (int) $r['term_id'];

   update_term_meta( $id, 'hj_province_id', $pid );

   if ( '' !== $en ) { update_term_meta( $id, 'hj_name_en', $en ); }

  }

  $obj = (object) array( 'term_id' => $id, 'name' => $name, 'slug' => $slug );

  $this->C_pid[ $id ] = $pid;

  $this->C_key[ $key ] = $obj;

  $this->C_slug[ $slug ] = $obj;

  $this->slugs['city'][ $slug ] = 1;

  $this->rep['c']['created']++;

  $this->prev( $line, $pname, $name, 'created' );

 }



 private function import( array $rows, $dry, $upd ) {

  $this->dry = (bool) $dry; $this->upd = (bool) $upd; $this->fake = 0;

  $this->rep = array(

   'dry' => $this->dry, 'rows' => count( $rows ),

   'p' => array( 'created' => 0, 'updated' => 0, 'same' => 0 ),

   'c' => array( 'created' => 0, 'updated' => 0, 'same' => 0, 'adopted' => 0 ),

   'skip' => array(), 'err' => array(), 'prev' => array(), 'uP' => 0, 'uC' => 0,

  );



  if ( ! taxonomy_exists( 'province' ) || ! taxonomy_exists( 'city' ) ) {

   return array( 'kind' => 'msg', 'text' => 'تاکسونومی‌های province و city ثبت نشده‌اند (بخش ۲ فعال است؟ حالت امن روشن نیست؟).' );

  }

  $max = (int) apply_filters( 'hj_seed_max_rows', 5000 );

  if ( count( $rows ) > $max ) {

   return array( 'kind' => 'msg', 'text' => 'تعداد سطرها بیش از سقف مجاز (' . $this->fa( $max ) . ') است.' );

  }

  $ok = $this->load_maps();

  if ( true !== $ok ) { return array( 'kind' => 'msg', 'text' => 'خواندن ترم‌های موجود ممکن نشد: ' . $ok ); }



  if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 180 ); }

  if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }

  if ( ! $this->dry ) { wp_defer_term_counting( true ); }



  foreach ( $rows as $row ) {

   $line = (int) ( isset( $row['line'] ) ? $row['line'] : 0 );

   $pn = $this->clean_cell( isset( $row['province'] ) ? $row['province'] : '' );

   $cn = $this->clean_cell( isset( $row['city'] ) ? $row['city'] : '' );

   if ( '' === $pn ) { $this->skip( $line, 'نام استان خالی است' ); continue; }



   $p = $this->upsert_province(

    $pn,

    $this->slug_in( isset( $row['province_slug'] ) ? $row['province_slug'] : '' ),

    $this->clean_cell( isset( $row['province_en'] ) ? $row['province_en'] : '' ),

    $line

   );

   if ( ! $p ) { continue; }

   if ( '' === $cn ) {

    if ( 'seen' !== $p['act'] ) { $this->prev( $line, $pn, '', $p['act'] ); }

    continue;

   }

   $this->upsert_city(

    $cn,

    $this->slug_in( isset( $row['city_slug'] ) ? $row['city_slug'] : '' ),

    $this->clean_cell( isset( $row['city_en'] ) ? $row['city_en'] : '' ),

    $p, $line, $pn

   );

  }



  if ( ! $this->dry ) {

   wp_defer_term_counting( false );

   delete_transient( 'hj_sf_index_v1' ); // smart-search index uses term names

   do_action( 'hj_seed_imported', $this->rep );

  }

  $this->rep['uP'] = count( $this->seen_p );

  $this->rep['uC'] = count( $this->seen_c );

  return array( 'kind' => 'import', 'data' => $this->rep );

 }



 private function ensure_specialties() {

  if ( ! taxonomy_exists( 'specialty' ) ) { return array( 'kind' => 'msg', 'text' => 'تاکسونومی specialty ثبت نشده است.' ); }

  $o = array( 'created' => 0, 'filled' => 0, 'exists' => 0, 'err' => array() );

  foreach ( self::specialties() as $slug => $n ) {

   $t = get_term_by( 'slug', $slug, 'specialty' );

   if ( ! $t ) {

    $r = wp_insert_term( $n[0], 'specialty', array( 'slug' => $slug ) );

    if ( is_wp_error( $r ) ) { $o['err'][] = $slug . ': ' . $r->get_error_message(); continue; }

    update_term_meta( (int) $r['term_id'], 'hj_name_en', $n[1] );

    $o['created']++;

   } else {

    $o['exists']++;

    if ( ! get_term_meta( $t->term_id, 'hj_name_en', true ) ) {

     update_term_meta( (int) $t->term_id, 'hj_name_en', $n[1] );

     $o['filled']++;

    }

   }

  }

  delete_transient( 'hj_sf_index_v1' );

  return array( 'kind' => 'spec', 'data' => $o );

 }



 private function save_image() {

  $f = isset( $_FILES['hj_seed_img'] ) ? $_FILES['hj_seed_img'] : array();

  if ( empty( $f['name'] ) || ! isset( $f['error'] ) || UPLOAD_ERR_OK !== (int) $f['error'] ) { return array( 'kind' => 'msg', 'text' => 'تصویری انتخاب نشد یا آپلود ناموفق بود.' ); }

  if ( (int) $f['size'] > 2 * MB_IN_BYTES ) { return array( 'kind' => 'msg', 'text' => 'حجم تصویر بیش از ۲ مگابایت است.' ); }

  $mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );

  $chk = wp_check_filetype_and_ext( $f['tmp_name'], sanitize_file_name( $f['name'] ), $mimes );

  if ( empty( $chk['type'] ) || ! in_array( $chk['type'], $mimes, true ) ) { return array( 'kind' => 'msg', 'text' => 'فقط JPG، PNG یا WebP مجاز است.' ); }

  if ( ! @getimagesize( $f['tmp_name'] ) ) { return array( 'kind' => 'msg', 'text' => 'فایل تصویر معتبر نیست.' ); }

  require_once ABSPATH . 'wp-admin/includes/file.php';

  require_once ABSPATH . 'wp-admin/includes/image.php';

  require_once ABSPATH . 'wp-admin/includes/media.php';

  $id = media_handle_upload( 'hj_seed_img', 0, array(), array( 'test_form' => false, 'mimes' => $mimes ) );

  if ( is_wp_error( $id ) ) { return array( 'kind' => 'msg', 'text' => 'ذخیره تصویر ناموفق بود: ' . $id->get_error_message() ); }

  update_option( self::OPT_IMG, (int) $id, false );

  self::$fb_cache = null;

  return array( 'kind' => 'msg', 'text' => 'تصویر پیش‌فرض ذخیره شد.' );

 }



 /* =====================================================================

  * Admin page

  * ===================================================================== */

 public function menu() {

  add_management_page( 'ورود داده‌های همیار', 'ورود داده‌ها (همیار)', 'manage_options', self::PAGE, array( $this, 'render_page' ) );

 }



 private function handle_post() {

  if ( ! current_user_can( 'manage_options' ) ) { return array( 'kind' => 'msg', 'text' => 'دسترسی کافی ندارید.' ); }

  $n = isset( $_POST['_hjn'] ) ? sanitize_text_field( wp_unslash( $_POST['_hjn'] ) ) : '';

  if ( ! wp_verify_nonce( $n, self::NONCE ) ) { return array( 'kind' => 'msg', 'text' => 'نشست منقضی شده است؛ صفحه را تازه کنید و دوباره تلاش کنید.' ); }

  if ( function_exists( 'hj_sec_rate_limit' ) && ! hj_sec_rate_limit( 'seed', 30, HOUR_IN_SECONDS, (string) get_current_user_id() ) ) {

   return array( 'kind' => 'msg', 'text' => 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.' );

  }

  $do = isset( $_POST['hj_seed_do'] ) ? sanitize_key( wp_unslash( $_POST['hj_seed_do'] ) ) : '';

  try {

   switch ( $do ) {

    case 'import':

     $err = '';

     $raw = $this->read_upload( isset( $_FILES['hj_seed_file'] ) ? $_FILES['hj_seed_file'] : array(), $err );

     if ( '' !== $err ) { return array( 'kind' => 'msg', 'text' => $err ); }

     $c = substr( ltrim( $raw ), 0, 1 );

     $rows = ( '{' === $c || '[' === $c ) ? $this->rows_from_json( $raw, $err ) : $this->parse_csv( $raw, $err );

     if ( '' !== $err ) { return array( 'kind' => 'msg', 'text' => $err ); }

     if ( empty( $rows ) ) { return array( 'kind' => 'msg', 'text' => 'هیچ سطری در فایل پیدا نشد.' ); }

     return $this->import( $rows, ! empty( $_POST['hj_dry'] ), ! empty( $_POST['hj_upd'] ) );

    case 'specialties':

     return $this->ensure_specialties();

    case 'image':

     return $this->save_image();

    case 'image_reset':

     delete_option( self::OPT_IMG );

     self::$fb_cache = null;

     return array( 'kind' => 'msg', 'text' => 'تصویر آپلودی حذف شد؛ فایل پوشهٔ assets (یا SVG) استفاده می‌شود.' );

   }

  } catch ( Exception $e ) {

   return array( 'kind' => 'msg', 'text' => 'خطای غیرمنتظره: ' . $e->getMessage() );

  } catch ( Throwable $e ) {

   return array( 'kind' => 'msg', 'text' => 'خطای غیرمنتظره: ' . $e->getMessage() );

  }

  return array( 'kind' => 'msg', 'text' => 'درخواست نامشخص.' );

 }



 private function cnt( $tax ) {

  if ( ! taxonomy_exists( $tax ) ) { return '—'; }

  $c = wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );

  return is_wp_error( $c ) ? '—' : $this->fa( (int) $c );

 }



 private function report_html( $res ) {

  if ( 'msg' === $res['kind'] ) {

   return '<div class="hj-seed-card"><p>' . esc_html( $res['text'] ) . '</p></div>';

  }

  if ( 'spec' === $res['kind'] ) {

   $d = $res['data'];

   $o = '<div class="hj-seed-card"><h2>تخصص‌ها</h2><p>ساخته‌شده: ' . $this->fa( $d['created'] ) . ' — از قبل موجود: ' . $this->fa( $d['exists'] ) . ' — نام انگلیسی تکمیل‌شده: ' . $this->fa( $d['filled'] ) . '</p>';

   foreach ( $d['err'] as $e ) { $o .= '<p class="hj-seed-muted">' . esc_html( $e ) . '</p>'; }

   return $o . '</div>';

  }

  $d = $res['data'];

  $exp = (array) apply_filters( 'hj_seed_expected', array( 31, 483 ) );

  $ep = isset( $exp[0] ) ? (int) $exp[0] : 0;

  $ec = isset( $exp[1] ) ? (int) $exp[1] : 0;

  $lab = array( 'created' => 'ایجاد', 'updated' => 'به‌روزرسانی', 'same' => 'بدون تغییر', 'adopted' => 'اتصال به استان', 'seen' => '—' );



  $o = '<div class="hj-seed-card"><h2>' . ( $d['dry'] ? 'پیش‌نمایش — چیزی ذخیره نشد' : 'ورود انجام شد' ) . '</h2>';

  $o .= '<p>سطرهای خوانده‌شده: ' . $this->fa( $d['rows'] ) . '</p>';

  $o .= '<p>در فایل: <strong>' . $this->fa( $d['uP'] ) . '</strong> استان' . ( $ep ? ' (انتظار: ' . $this->fa( $ep ) . ( $d['uP'] === $ep ? ' ✓' : ' ≠' ) . ')' : '' )

   . ' و <strong>' . $this->fa( $d['uC'] ) . '</strong> شهرستان' . ( $ec ? ' (انتظار: ' . $this->fa( $ec ) . ( $d['uC'] === $ec ? ' ✓' : ' ≠' ) . ')' : '' ) . '</p>';

  $o .= '<table class="hj-seed-t"><tr><th></th><th>ایجاد</th><th>به‌روزرسانی</th><th>بدون تغییر</th><th>اتصال به استان</th></tr>';

  $o .= '<tr><td>استان</td><td>' . $this->fa( $d['p']['created'] ) . '</td><td>' . $this->fa( $d['p']['updated'] ) . '</td><td>' . $this->fa( $d['p']['same'] ) . '</td><td>—</td></tr>';

  $o .= '<tr><td>شهرستان</td><td>' . $this->fa( $d['c']['created'] ) . '</td><td>' . $this->fa( $d['c']['updated'] ) . '</td><td>' . $this->fa( $d['c']['same'] ) . '</td><td>' . $this->fa( $d['c']['adopted'] ) . '</td></tr></table>';



  if ( ! empty( $d['err'] ) ) {

   $o .= '<h3>خطاها</h3><ul>';

   foreach ( $d['err'] as $e ) { $o .= '<li>سطر ' . $this->fa( $e[0] ) . ': ' . esc_html( $e[1] ) . '</li>'; }

   $o .= '</ul>';

  }

  if ( ! empty( $d['skip'] ) ) {

   $o .= '<h3>ردشده‌ها</h3><ul>';

   foreach ( array_slice( $d['skip'], 0, 50 ) as $e ) { $o .= '<li>سطر ' . $this->fa( $e[0] ) . ': ' . esc_html( $e[1] ) . '</li>'; }

   $o .= '</ul>';

  }

  if ( ! empty( $d['prev'] ) ) {

   $o .= '<h3>نمونهٔ سطرها (حداکثر ۳۰)</h3><table class="hj-seed-t"><tr><th>سطر</th><th>استان</th><th>شهرستان</th><th>عمل</th></tr>';

   foreach ( $d['prev'] as $r ) {

    $o .= '<tr><td>' . $this->fa( $r[0] ) . '</td><td>' . esc_html( $r[1] ) . '</td><td>' . esc_html( $r[2] ) . '</td><td>' . esc_html( isset( $lab[ $r[3] ] ) ? $lab[ $r[3] ] : $r[3] ) . '</td></tr>';

   }

   $o .= '</table>';

  }

  if ( $d['dry'] ) { $o .= '<p class="hj-seed-muted">اگر اعداد درست بود، تیک «فقط پیش‌نمایش» را بردارید و دوباره همان فایل را بفرستید.</p>'; }

  return $o . '</div>';

 }



 public function render_page() {

  if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Forbidden', 'hamyar-doctors' ), 403 ); }



  $report = '';

  if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['hj_seed_do'] ) ) {

   $report = $this->report_html( $this->handle_post() );

  }

  $att = (int) get_option( self::OPT_IMG, 0 );

  $fb = self::fallback_url();

  $nonce = wp_nonce_field( self::NONCE, '_hjn', true, false );

  $tpl = admin_url( 'admin-post.php?action=hj_seed_template' );

  $st = admin_url( 'admin-post.php?action=hj_seed_status' );

  ?>

  <div class="wrap hj-seed" dir="rtl">

   <style>

    .hj-seed{max-width:860px;font-family:Vazirmatn,Tahoma,sans-serif}

    .hj-seed h1{color:#061A2E}

    .hj-seed-card{background:#fff;border:1px solid #dfe7ef;border-radius:12px;padding:18px 20px;margin:0 0 16px}

    .hj-seed-card h2{margin:0 0 10px;color:#0F3556;font-size:16px}

    .hj-seed-card h3{margin:14px 0 6px;color:#0F3556;font-size:14px}

    .hj-seed-btn{background:#0EA5A0;border:0;color:#fff;border-radius:8px;padding:8px 18px;cursor:pointer;font-family:inherit}

    .hj-seed-btn:hover{background:#14C8C0;color:#061A2E}

    .hj-seed-btn-alt{background:#eef3f8;color:#0F3556}

    .hj-seed-muted{color:#6B7C8F}

    .hj-seed-t{border-collapse:collapse;width:100%;margin:8px 0}

    .hj-seed-t th,.hj-seed-t td{border:1px solid #e3eaf1;padding:6px 10px;text-align:right}

    .hj-seed-t th{background:#f4f8fb;color:#0F3556}

    .hj-seed-row{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin:10px 0}

    .hj-seed-img{width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid #dfe7ef}

   </style>



   <h1>ورود داده‌های همیار</h1>

   <p class="hj-seed-muted">استان و شهرستان از فایل شما خوانده می‌شود؛ هیچ داده‌ای در کد نیست. <a href="<?php echo esc_url( $st ); ?>" target="_blank" rel="noopener">صفحهٔ وضعیت</a></p>



   <?php echo $report; // built with esc_html above ?>



   <div class="hj-seed-card">

    <h2>وضعیت فعلی</h2>

    <p>استان‌ها: <strong><?php echo esc_html( $this->cnt( 'province' ) ); ?></strong> —

       شهرستان‌ها: <strong><?php echo esc_html( $this->cnt( 'city' ) ); ?></strong> —

       تخصص‌ها: <strong><?php echo esc_html( $this->cnt( 'specialty' ) ); ?></strong></p>

   </div>



   <form method="post" enctype="multipart/form-data" class="hj-seed-card">

    <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput ?>

    <input type="hidden" name="hj_seed_do" value="import" />

    <h2>۱) استان‌ها و شهرستان‌ها (CSV یا JSON)</h2>

    <div class="hj-seed-row"><input type="file" name="hj_seed_file" accept=".csv,.json,.txt" required /></div>

    <div class="hj-seed-row">

     <label><input type="checkbox" name="hj_dry" value="1" checked /> فقط پیش‌نمایش (ذخیره نشود)</label>

     <label><input type="checkbox" name="hj_upd" value="1" checked /> نام موارد موجود به‌روز شود (slug هرگز تغییر نمی‌کند)</label>

    </div>

    <div class="hj-seed-row">

     <button class="hj-seed-btn" type="submit">اجرا</button>

     <a href="<?php echo esc_url( $tpl ); ?>">دانلود قالب CSV خالی</a>

    </div>

   </form>



   <form method="post" class="hj-seed-card">

    <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput ?>

    <input type="hidden" name="hj_seed_do" value="specialties" />

    <h2>۲) تخصص‌ها</h2>

    <p class="hj-seed-muted">فقط تخصص‌های ناموجود ساخته می‌شود؛ موارد موجود تغییر نمی‌کنند.</p>

    <button class="hj-seed-btn" type="submit">ساخت تخصص‌های ناموجود</button>

   </form>



   <form method="post" enctype="multipart/form-data" class="hj-seed-card">

    <?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput ?>

    <h2>۳) تصویر پیش‌فرض اتاق عمل</h2>

    <div class="hj-seed-row">

     <img class="hj-seed-img" src="<?php echo esc_url( $fb ); ?>" alt="" />

     <div>

      <p class="hj-seed-muted">منبع فعلی: <?php echo $att > 0 ? 'تصویر آپلودشده از همین صفحه' : 'فایل assets/img یا SVG پیش‌فرض'; ?></p>

      <input type="file" name="hj_seed_img" accept="image/jpeg,image/png,image/webp" />

     </div>

    </div>

    <div class="hj-seed-row">

     <button class="hj-seed-btn" type="submit" name="hj_seed_do" value="image">آپلود و جایگزینی</button>

     <?php if ( $att > 0 ) : ?>

      <button class="hj-seed-btn hj-seed-btn-alt" type="submit" name="hj_seed_do" value="image_reset" formnovalidate>بازگشت به فایل پوشهٔ assets</button>

     <?php endif; ?>

    </div>

   </form>

  </div>

  <?php

 }



 /* =====================================================================

  * CSV template + status page

  * ===================================================================== */

 public function template_csv() {

  if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }

  nocache_headers();

  header( 'Content-Type: text/csv; charset=utf-8' );

  header( 'Content-Disposition: attachment; filename="hj-import-template.csv"' );

  echo "\xEF\xBB\xBF" . "province,province_slug,province_en,city,city_slug,city_en\r\n";

  exit;

 }



 public function status_page() {

  if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }

  nocache_headers();

  $e = 'esc_html';

  $o = '<h1>hj_seed_status</h1>';



  $o .= '<h2>تاکسونومی‌ها</h2><ul>';

  foreach ( array( 'province', 'city', 'specialty', 'specialty_type' ) as $tx ) {

   $o .= '<li>' . $e( $tx ) . ': ' . ( taxonomy_exists( $tx ) ? 'ثبت‌شده — ' . $e( $this->cnt( $tx ) ) . ' ترم' : 'ثبت نشده' ) . '</li>';

  }

  $o .= '</ul>';



  if ( taxonomy_exists( 'province' ) && taxonomy_exists( 'city' ) ) {

   $P = get_terms( array( 'taxonomy' => 'province', 'hide_empty' => false, 'number' => 0 ) );

   $C = get_terms( array( 'taxonomy' => 'city', 'hide_empty' => false, 'number' => 0 ) );

   $P = is_wp_error( $P ) ? array() : $P;

   $C = is_wp_error( $C ) ? array() : $C;

   $per = array(); $names = array(); $pids = array();

   foreach ( $P as $t ) { $pids[ $t->term_id ] = 1; }

   $bad = 0;

   foreach ( $C as $t ) {

    $pid = (int) get_term_meta( $t->term_id, 'hj_province_id', true );

    $per[ $pid ] = ( isset( $per[ $pid ] ) ? $per[ $pid ] : 0 ) + 1;

    if ( $pid > 0 && ! isset( $pids[ $pid ] ) ) { $bad++; }

    $names[ $this->key_of( $t->name, 'city' ) ][] = $t->term_id;

   }

   $dups = 0; foreach ( $names as $ids ) { if ( count( $ids ) > 1 ) { $dups++; } }

   $o .= '<h2>رابطه استان←شهرستان</h2><ul>';

   $o .= '<li>شهرستان بدون استان: ' . $this->fa( isset( $per[0] ) ? $per[0] : 0 ) . '</li>';

   $o .= '<li>شهرستان با شناسهٔ استان نامعتبر: ' . $this->fa( $bad ) . '</li>';

   $o .= '<li>نام شهرستانِ تکراری بین استان‌ها (طبیعی است): ' . $this->fa( $dups ) . '</li></ul>';

   $o .= '<table border="1" cellpadding="6" style="border-collapse:collapse"><tr><th>استان</th><th>slug</th><th>شهرستان‌ها</th></tr>';

   foreach ( $P as $t ) {

    $n = isset( $per[ $t->term_id ] ) ? $per[ $t->term_id ] : 0;

    $o .= '<tr><td>' . $e( $t->name ) . '</td><td>' . $e( urldecode( $t->slug ) ) . '</td><td>' . $this->fa( $n ) . '</td></tr>';

   }

   $o .= '</table>';

  }



  $o .= '<h2>تخصص‌ها</h2><ul>';

  foreach ( self::specialties() as $slug => $n ) {

   $has = taxonomy_exists( 'specialty' ) && get_term_by( 'slug', $slug, 'specialty' );

   $o .= '<li>' . $e( $slug ) . ': ' . ( $has ? 'هست' : 'نیست' ) . '</li>';

  }

  $o .= '</ul>';



  $o .= '<h2>تصویر پیش‌فرض</h2><ul>';

  $fb = self::fallback_url();

  $o .= '<li>URL نهایی: ' . $e( $fb ) . '</li>';

  if ( defined( 'HJ_DOCTORS_FILE' ) ) {

   $dir = plugin_dir_path( HJ_DOCTORS_FILE );

   foreach ( array( 'jpg', 'webp', 'png' ) as $x ) {

    $o .= '<li>assets/img/operating-room.' . $x . ': ' . ( file_exists( $dir . 'assets/img/operating-room.' . $x ) ? 'هست' : 'نیست' ) . '</li>';

   }

  }

  $o .= '<li>تصویر آپلودی (option): ' . $this->fa( (int) get_option( self::OPT_IMG, 0 ) ) . '</li>';

  if ( function_exists( 'hj_doctors_default_photo_url' ) ) {

   $u1 = (string) hj_doctors_default_photo_url();

   $same = strtok( $u1, '?' ) === strtok( $fb, '?' );

   $o .= '<li>hj_doctors_default_photo_url(): ' . $e( $u1 ) . ' — ' . ( $same ? 'هم‌سو با سیستم جدید' : 'متفاوت؛ اصلاح یک‌خطی «نکتهٔ ایجنت» لازم است' ) . '</li>';

  } else {

   $o .= '<li>hj_doctors_default_photo_url(): تعریف نشده</li>';

  }

  $o .= '</ul>';



  $ids = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'posts_per_page' => 3, 'fields' => 'ids', 'no_found_rows' => true ) );

  if ( $ids && function_exists( 'hj_dp_photo_url' ) ) {

   $o .= '<h2>نمونه پزشکان (hj_dp_photo_url)</h2><ul>';

   foreach ( $ids as $id ) { $o .= '<li>#' . (int) $id . ' ' . $e( get_the_title( $id ) ) . ' → ' . $e( (string) hj_dp_photo_url( $id ) ) . '</li>'; }

   $o .= '</ul>';

  }



  echo '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>hj_seed_status</title></head><body style="font-family:Tahoma,sans-serif;max-width:900px;margin:20px auto;line-height:1.9">' . $o . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput

  exit;

 }

}



endif;



if ( ! function_exists( 'hj_doctor_fallback_photo_url' ) ) {

 function hj_doctor_fallback_photo_url() {

  return HJ_Doctors_Seed::fallback_url();

 }

}



HJ_Doctors_Seed::boot();

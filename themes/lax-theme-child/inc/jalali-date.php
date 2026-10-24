<?php
/**
 * Native Persian Solar Hijri (Jalali / شمسی) Date Formatter & Unified Byline Helper.
 *
 * - Converts all human-facing post/page/archive/admin dates to Persian Jalali
 *   with Persian digits (e.g. «۳۰ مهر ۱۴۰۵» or «۱۴۰۵/۰۷/۳۰») without requiring
 *   any external plugin.
 * - Preserves machine-readable ISO-8601 / Unix formats (DATE_W3C, 'c', 'U',
 *   'Y-m-d\TH:i:sP', XML sitemaps, feeds, JSON-LD schema) in Gregorian.
 * - Converts stray inline Gregorian dates inside Persian body text (such as
 *   «۲۰۲۶/۱۰/۲۰» or «۲۰۲۶-۱۰-۲۰» or «اکتبر 22, 2026») to Jalali while never
 *   touching HTML attributes or URLs.
 * - Normalizes author bylines into a single clean label.
 *
 * @package Lax_Theme_Child
 * @since   0.11.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert ASCII and Arabic-Indic digits to Persian digits.
 *
 * @param string|int $input Raw string or number.
 * @return string
 */
function hj_fa_digits( $input ): string {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return str_replace( $en, $fa, (string) $input );
}

/**
 * Convert Persian and Arabic-Indic digits to ASCII digits.
 *
 * @param string $input Raw string.
 * @return string
 */
function hj_en_digits( string $input ): string {
	$non_en = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$en     = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	return str_replace( $non_en, $en, $input );
}

/**
 * Standard Gregorian to Solar Hijri (Jalali) conversion.
 *
 * @param int $gy Gregorian year.
 * @param int $gm Gregorian month (1-12).
 * @param int $gd Gregorian day (1-31).
 * @return array{0:int,1:int,2:int} [ $jy, $jm, $jd ]
 */
function hj_gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	if ( $gm < 1 || $gm > 12 ) {
		$gm = 1;
	}
	$gy2  = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy   = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy  += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/**
 * Persian month name for a 1-based Jalali month.
 *
 * @param int $jm Month 1..12.
 * @return string
 */
function hj_jalali_month_name( int $jm ): string {
	$months = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);
	return isset( $months[ $jm ] ) ? $months[ $jm ] : '';
}

/**
 * Format a Unix timestamp into Persian Jalali in the site's WordPress timezone.
 *
 * Styles:
 *   - 'long'     -> «۳۰ مهر ۱۴۰۵»
 *   - 'numeric'  -> «۱۴۰۵/۰۷/۳۰»
 *   - 'datetime' -> «۱۴۰۵/۰۷/۳۰ — ۱۴:۲۰»
 *
 * @param int    $timestamp Unix timestamp (UTC).
 * @param string $style     Output format style.
 * @return string
 */
function hj_format_jalali_timestamp( int $timestamp, string $style = 'long' ): string {
	if ( $timestamp <= 0 ) {
		return '';
	}
	try {
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Asia/Tehran' );
		$dt = new DateTimeImmutable( '@' . $timestamp );
		$dt = $dt->setTimezone( $tz );
	} catch ( \Throwable $e ) {
		$dt = new DateTimeImmutable( '@' . $timestamp );
	}

	$gy = (int) $dt->format( 'Y' );
	$gm = (int) $dt->format( 'n' );
	$gd = (int) $dt->format( 'j' );
	list( $jy, $jm, $jd ) = hj_gregorian_to_jalali( $gy, $gm, $gd );

	if ( 'numeric' === $style ) {
		return hj_fa_digits( sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) );
	}
	if ( 'datetime' === $style ) {
		return hj_fa_digits( sprintf( '%04d/%02d/%02d — %s', $jy, $jm, $jd, $dt->format( 'H:i' ) ) );
	}

	return hj_fa_digits( $jd . ' ' . hj_jalali_month_name( $jm ) . ' ' . $jy );
}

/**
 * Format a YYYY-MM-DD (or YYYY/MM/DD) Gregorian string directly into Jalali.
 *
 * @param string $date_str Gregorian date string.
 * @param string $style    'long' or 'numeric'.
 * @return string
 */
function hj_format_jalali_ymd( string $date_str, string $style = 'long' ): string {
	$ascii = trim( hj_en_digits( $date_str ) );
	if ( ! preg_match( '/^(19\d\d|20\d\d)[-\/\.](0?[1-9]|1[0-2])[-\/\.](0?[1-9]|[12]\d|3[01])(?:\s+(\d{1,2}:\d{2}))?$/', $ascii, $m ) ) {
		return $date_str;
	}
	list( $jy, $jm, $jd ) = hj_gregorian_to_jalali( (int) $m[1], (int) $m[2], (int) $m[3] );
	if ( 'datetime' === $style && ! empty( $m[4] ) ) {
		return hj_fa_digits( sprintf( '%04d/%02d/%02d — %s', $jy, $jm, $jd, $m[4] ) );
	}
	if ( 'numeric' === $style ) {
		return hj_fa_digits( sprintf( '%04d/%02d/%02d', $jy, $jm, $jd ) );
	}
	return hj_fa_digits( $jd . ' ' . hj_jalali_month_name( $jm ) . ' ' . $jy );
}

/**
 * Get the Jalali published date of a post.
 *
 * @param int|\WP_Post|null $post  Post ID or object.
 * @param string            $style 'long' | 'numeric' | 'datetime'.
 * @return string
 */
function hj_get_jalali_post_date( $post = null, string $style = 'long' ): string {
	$ts = (int) get_post_time( 'U', true, $post );
	if ( $ts <= 0 ) {
		$ts = (int) get_post_time( 'U', false, $post );
	}
	return hj_format_jalali_timestamp( $ts, $style );
}

/**
 * Get the Jalali modified date of a post.
 *
 * @param int|\WP_Post|null $post  Post ID or object.
 * @param string            $style 'long' | 'numeric' | 'datetime'.
 * @return string
 */
function hj_get_jalali_post_modified_date( $post = null, string $style = 'long' ): string {
	$ts = (int) get_post_modified_time( 'U', true, $post );
	if ( $ts <= 0 ) {
		$ts = (int) get_post_modified_time( 'U', false, $post );
	}
	return hj_format_jalali_timestamp( $ts, $style );
}

/**
 * Determine whether a human-facing WordPress date filter call should be converted
 * to Jalali (while leaving machine/ISO/feed/sitemap formats untouched).
 *
 * @param string $format Requested date format.
 * @return bool
 */
function hj_is_human_date_format( string $format ): bool {
	if ( function_exists( 'is_feed' ) && is_feed() ) {
		return false;
	}
	if ( '' === $format ) {
		return true;
	}
	// Never touch machine-readable formats (ISO-8601, RFC, Unix timestamps, SQL dates).
	$machine_formats = array(
		DATE_W3C,
		DATE_ATOM,
		DATE_RFC2822,
		DATE_RFC3339,
		'c',
		'r',
		'U',
		'G',
		'Y-m-d',
		'Y-m-d H:i',
		'Y-m-d H:i:s',
		'Y-m-d\TH:i:sO',
		'Y-m-d\TH:i:sP',
		'Y-m-d\TH:i:s\Z',
		'Y',
		'm',
		'd',
		'H:i',
		'H:i:s',
	);
	if ( in_array( $format, $machine_formats, true ) || false !== strpos( $format, 'T' ) || false !== strpos( $format, 'P' ) ) {
		return false;
	}
	return true;
}

/**
 * Filter `get_the_date` for human-facing front-end & admin displays.
 *
 * @param string|int   $the_date Formatted date string.
 * @param string       $format   Format string (empty = site option `date_format`).
 * @param \WP_Post|int $post     Post object or ID.
 * @return string|int
 */
function hj_filter_get_the_date( $the_date, $format, $post ) {
	// Skip if an external Persian date plugin already localized the string to a Jalali 13xx/14xx year.
	$ascii_check = hj_en_digits( (string) $the_date );
	if ( preg_match( '/\b1[34]\d{2}\b/', $ascii_check ) ) {
		return $the_date;
	}
	if ( ! hj_is_human_date_format( (string) $format ) ) {
		return $the_date;
	}
	$jalali = hj_get_jalali_post_date( $post, 'long' );
	return '' !== $jalali ? $jalali : $the_date;
}
add_filter( 'get_the_date', 'hj_filter_get_the_date', 20, 3 );

/**
 * Filter `get_the_modified_date` for human-facing front-end & admin displays.
 *
 * @param string|int|false $the_time Formatted modified date.
 * @param string           $format   Format string.
 * @param \WP_Post|null    $post     Post object.
 * @return string|int|false
 */
function hj_filter_get_the_modified_date( $the_time, $format, $post ) {
	if ( false === $the_time ) {
		return $the_time;
	}
	$ascii_check = hj_en_digits( (string) $the_time );
	if ( preg_match( '/\b1[34]\d{2}\b/', $ascii_check ) ) {
		return $the_time;
	}
	if ( ! hj_is_human_date_format( (string) $format ) ) {
		return $the_time;
	}
	$jalali = hj_get_jalali_post_modified_date( $post, 'long' );
	return '' !== $jalali ? $jalali : $the_time;
}
add_filter( 'get_the_modified_date', 'hj_filter_get_the_modified_date', 20, 3 );

/**
 * Return a single, concise, non-duplicated author label for a post or page.
 *
 * Eliminates crowded combinations such as:
 * «محمدرضا لک — تکنیسین اتاق عمل، سردبیر مجله‌ی همیار جراحی / تیم نشریه‌ی همیار جراحی».
 *
 * @param int|\WP_Post|null $post Post ID or object.
 * @return string
 */
function hj_get_clean_author_label( $post = null ): string {
	$post_obj = get_post( $post );
	if ( ! $post_obj ) {
		return 'تحریریه‌ی همیار جراحی';
	}

	// Institutional static pages: keep one concise identity.
	if ( 'page' === $post_obj->post_type && function_exists( 'hj_is_seeded_page' ) && hj_is_seeded_page( $post_obj->ID ) ) {
		if ( 'about' === $post_obj->post_name ) {
			return 'محمدرضا لک';
		}
		return 'تحریریه‌ی همیار جراحی';
	}

	$raw = trim( (string) get_post_meta( $post_obj->ID, '_hj_author_name', true ) );
	if ( '' === $raw ) {
		$raw = trim( (string) get_the_author_meta( 'display_name', (int) $post_obj->post_author ) );
	}

	if ( '' === $raw ) {
		return 'تحریریه‌ی همیار جراحی';
	}

	// If both personal name and team/editorial title were concatenated, keep just the primary author name.
	if ( false !== strpos( $raw, 'محمدرضا لک' ) ) {
		return 'محمدرضا لک';
	}
	if ( false !== strpos( $raw, 'تیم نشری' ) || false !== strpos( $raw, 'تحریری' ) ) {
		return 'تحریریه‌ی همیار جراحی';
	}

	// Strip long em-dash / pipe role suffixes in compact meta bar.
	$parts = preg_split( '/\s*(?:—|–|\||،)\s*/u', $raw );
	if ( is_array( $parts ) && ! empty( $parts[0] ) ) {
		return trim( $parts[0] );
	}

	return $raw;
}

/**
 * Convert stray Gregorian dates inside HTML text nodes (e.g. «۲۰۲۶/۱۰/۲۰»,
 * «۲۰۲۶-۱۰-۲۰», «2026-10-20», «اکتبر 22, 2026») into Persian Jalali dates
 * without touching HTML tags, attributes, or URLs.
 *
 * @param string $html HTML content.
 * @return string
 */
function hj_convert_inline_gregorian_dates( string $html ): string {
	if ( '' === $html ) {
		return $html;
	}

	$greg_months = array(
		'ژانویه'  => 1,
		'فوریه'   => 2,
		'مارس'    => 3,
		'آوریل'   => 4,
		'مه'      => 5,
		'می'      => 5,
		'ژوئن'    => 6,
		'ژوئیه'   => 7,
		'جولای'   => 7,
		'اوت'     => 8,
		'آگوست'   => 8,
		'سپتامبر' => 9,
		'اکتبر'   => 10,
		'نوامبر'  => 11,
		'دسامبر'  => 12,
	);
	$month_regex = implode( '|', array_keys( $greg_months ) );

	$tokens = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $tokens ) ) {
		return $html;
	}

	foreach ( $tokens as $i => $tok ) {
		if ( '' === $tok || '<' === $tok[0] ) {
			continue;
		}

		// Protect plain-text URLs inside text nodes so paths like /2026/10/ are never altered.
		$protected = array();
		$tok_safe  = preg_replace_callback(
			'#https?://[^\s<>"]+#i',
			static function ( $m ) use ( &$protected ) {
				$key               = '__HJ_URL_' . count( $protected ) . '__';
				$protected[ $key ] = $m[0];
				return $key;
			},
			$tok
		);
		if ( ! is_string( $tok_safe ) ) {
			$tok_safe = $tok;
		}

		// 1) YYYY/MM/DD or YYYY-MM-DD (in Persian, Arabic, or ASCII digits).
		$tok_safe = (string) preg_replace_callback(
			'/(?<![0-9۰-۹\/\-])((?:202[0-9]|۲۰۲[۰-۹]))([\/\-])([01۰۱]?[0-9۰-۹])\2([0-3۰-۳]?[0-9۰-۹])(?![0-9۰-۹\/\-])/u',
			static function ( $m ) {
				$gy = (int) hj_en_digits( $m[1] );
				$gm = (int) hj_en_digits( $m[3] );
				$gd = (int) hj_en_digits( $m[4] );
				if ( $gm < 1 || $gm > 12 || $gd < 1 || $gd > 31 ) {
					return $m[0];
				}
				list( $jy, $jm, $jd ) = hj_gregorian_to_jalali( $gy, $gm, $gd );
				return hj_fa_digits( $jd . ' ' . hj_jalali_month_name( $jm ) . ' ' . $jy );
			},
			$tok_safe
		);

		// 2) WordPress fa_IR Gregorian month format: «اکتبر 22, 2026»
		$tok_safe = (string) preg_replace_callback(
			'/(' . $month_regex . ')\s+([0-3۰-۳]?[0-9۰-۹])\s*[,،]\s*((?:202[0-9]|۲۰۲[۰-۹]))/u',
			static function ( $m ) use ( $greg_months ) {
				$gm = $greg_months[ $m[1] ] ?? 0;
				$gd = (int) hj_en_digits( $m[2] );
				$gy = (int) hj_en_digits( $m[3] );
				if ( $gm < 1 || $gd < 1 || $gd > 31 ) {
					return $m[0];
				}
				list( $jy, $jm, $jd ) = hj_gregorian_to_jalali( $gy, $gm, $gd );
				return hj_fa_digits( $jd . ' ' . hj_jalali_month_name( $jm ) . ' ' . $jy );
			},
			$tok_safe
		);

		// 3) Day + Gregorian month + Year: «22 اکتبر 2026»
		$tok_safe = (string) preg_replace_callback(
			'/([0-3۰-۳]?[0-9۰-۹])\s+(' . $month_regex . ')\s+((?:202[0-9]|۲۰۲[۰-۹]))/u',
			static function ( $m ) use ( $greg_months ) {
				$gd = (int) hj_en_digits( $m[1] );
				$gm = $greg_months[ $m[2] ] ?? 0;
				$gy = (int) hj_en_digits( $m[3] );
				if ( $gm < 1 || $gd < 1 || $gd > 31 ) {
					return $m[0];
				}
				list( $jy, $jm, $jd ) = hj_gregorian_to_jalali( $gy, $gm, $gd );
				return hj_fa_digits( $jd . ' ' . hj_jalali_month_name( $jm ) . ' ' . $jy );
			},
			$tok_safe
		);

		if ( ! empty( $protected ) ) {
			$tok_safe = str_replace( array_keys( $protected ), array_values( $protected ), $tok_safe );
		}

		$tokens[ $i ] = $tok_safe;
	}

	return implode( '', $tokens );
}

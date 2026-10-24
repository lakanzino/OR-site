<?php
/**
 * WP Admin Glossary Manager & Bulk 5,000+ Term Importer.
 *
 * Features:
 *   1. Tab «بانک واژگان»: Quick single-term add/edit + searchable/filterable
 *      paginated table for 5,000–50,000+ terms + bulk delete / category move / toggle highlight.
 *   2. Tab «ورود انبوه ۵۰۰۰+ واژه»: Supports direct Excel `.xlsx`, `.csv` (UTF-8 & Windows-1256),
 *      `.tsv`, `.json` (array of objects OR key:value map), `.txt`, and direct Copy-Paste
 *      from Excel/Word with interactive column mapping, 5-row preview, and zero-timeout
 *      chunked AJAX progress bar (400 terms per batch).
 *   3. Tab «موضوعات و دسته‌بندی‌ها»: Create/manage `glossary_group` categories + view counts.
 *   4. Tab «تنظیمات هایلایت سبز»: Control auto-highlight behavior, green style variant,
 *      frequency per article, post types, and 1-click compiled index rebuild.
 *   5. 1-click CSV (UTF-8 BOM for Excel) & JSON sample template download + full backup export.
 *
 * @package Lax_Theme_Child
 * @since   0.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. File Parsers (.xlsx, .csv, .tsv, .json, .txt) for 5,000+ Bulk Import
 * ====================================================================== */

/**
 * Convert Excel column letters (e.g. "A" -> 0, "C" -> 2, "AA" -> 26) to 0-based index.
 *
 * @param string $col_ref Cell reference such as "B14" or "AA2".
 * @return int
 */
function hj_glossary_excel_col_index( string $col_ref ): int {
	if ( ! preg_match( '/^([A-Z]+)/i', $col_ref, $m ) ) {
		return -1;
	}
	$letters = strtoupper( $m[1] );
	$idx     = 0;
	$len     = strlen( $letters );
	for ( $i = 0; $i < $len; ++$i ) {
		$idx = ( $idx * 26 ) + ( ord( $letters[ $i ] ) - 64 );
	}
	return $idx - 1;
}

/**
 * Parse an Excel `.xlsx` file natively (zero external libraries) into a 2D array of rows.
 *
 * @param string $xlsx_path Path to uploaded `.xlsx` file.
 * @return array<int,array<int,string>>|\WP_Error
 */
function hj_glossary_parse_xlsx_file( string $xlsx_path ) {
	$entries = array();

	if ( class_exists( 'ZipArchive' ) ) {
		$za = new ZipArchive();
		if ( true !== $za->open( $xlsx_path ) ) {
			return new WP_Error( 'xlsx_open', 'فایل اکسل (.xlsx) قابل باز شدن نیست.' );
		}
		for ( $i = 0; $i < $za->numFiles; ++$i ) {
			$name = (string) $za->getNameIndex( $i );
			if ( 'xl/sharedStrings.xml' === $name || 0 === strpos( $name, 'xl/worksheets/sheet' ) ) {
				$entries[ $name ] = (string) $za->getFromIndex( $i );
			}
		}
		$za->close();
	} else {
		if ( ! class_exists( 'PclZip' ) && file_exists( ABSPATH . 'wp-admin/includes/class-pclzip.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}
		if ( ! class_exists( 'PclZip' ) ) {
			return new WP_Error( 'no_zip', 'ماژول Zip در سرور فعال نیست؛ لطفاً فایل را با فرمت CSV یا کپی-پیست وارد کنید.' );
		}
		$pcl  = new PclZip( $xlsx_path );
		$list = $pcl->extract( PCLZIP_OPT_EXTRACT_AS_STRING );
		if ( ! is_array( $list ) ) {
			return new WP_Error( 'xlsx_extract', 'استخراج محتوای فایل اکسل ناموفق بود.' );
		}
		foreach ( $list as $item ) {
			$fn = (string) ( $item['filename'] ?? '' );
			if ( 'xl/sharedStrings.xml' === $fn || 0 === strpos( $fn, 'xl/worksheets/sheet' ) ) {
				$entries[ $fn ] = (string) ( $item['content'] ?? '' );
			}
		}
	}

	// Find first worksheet XML (`sheet1.xml` preferred).
	$sheet_xml = $entries['xl/worksheets/sheet1.xml'] ?? '';
	if ( '' === $sheet_xml ) {
		foreach ( $entries as $k => $v ) {
			if ( 0 === strpos( $k, 'xl/worksheets/sheet' ) && substr( $k, -4 ) === '.xml' ) {
				$sheet_xml = $v;
				break;
			}
		}
	}
	if ( '' === $sheet_xml ) {
		return new WP_Error( 'xlsx_empty', 'برگه‌ی داده در فایل اکسل پیدا نشد.' );
	}

	// Parse sharedStrings.xml (concatenating <t> runs inside each <si>).
	$shared = array();
	if ( ! empty( $entries['xl/sharedStrings.xml'] ) ) {
		$ss_xml = $entries['xl/sharedStrings.xml'];
		if ( preg_match_all( '/<si\b[^>]*>(.*?)<\/si>/su', $ss_xml, $si_matches ) ) {
			foreach ( $si_matches[1] as $si_inner ) {
				if ( preg_match_all( '/<t(?:\s[^>]*)?>(.*?)<\/t>/su', $si_inner, $t_matches ) ) {
					$val      = implode( '', $t_matches[1] );
					$shared[] = html_entity_decode( $val, ENT_QUOTES | ENT_XML1, 'UTF-8' );
				} else {
					$shared[] = '';
				}
			}
		}
	}

	// Parse rows & cells from sheet1.xml.
	$rows = array();
	if ( preg_match_all( '/<row\b[^>]*>(.*?)<\/row>/su', $sheet_xml, $row_matches ) ) {
		foreach ( $row_matches[1] as $row_inner ) {
			$row_cells = array();
			$max_col   = -1;
			$seq_col   = 0;

			if ( preg_match_all( '/<c\b([^>]*)(?:\/>|>(.*?)<\/c>)/su', $row_inner, $cell_matches, PREG_SET_ORDER ) ) {
				foreach ( $cell_matches as $cm ) {
					$attrs = $cm[1];
					$inner = $cm[2] ?? '';

					$col_idx = $seq_col;
					if ( preg_match( '/\br="([A-Z]+\d+)"/i', $attrs, $rm ) ) {
						$parsed_idx = hj_glossary_excel_col_index( $rm[1] );
						if ( $parsed_idx >= 0 ) {
							$col_idx = $parsed_idx;
						}
					}
					$seq_col = $col_idx + 1;

					$type = '';
					if ( preg_match( '/\bt="([^"]+)"/', $attrs, $tm ) ) {
						$type = $tm[1];
					}

					$cell_val = '';
					if ( 'inlineStr' === $type ) {
						if ( preg_match_all( '/<t(?:\s[^>]*)?>(.*?)<\/t>/su', $inner, $itm ) ) {
							$cell_val = html_entity_decode( implode( '', $itm[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
						}
					} elseif ( preg_match( '/<v(?:\s[^>]*)?>(.*?)<\/v>/su', $inner, $vm ) ) {
						$raw_v = html_entity_decode( $vm[1], ENT_QUOTES | ENT_XML1, 'UTF-8' );
						if ( 's' === $type ) {
							$s_idx    = (int) $raw_v;
							$cell_val = $shared[ $s_idx ] ?? '';
						} else {
							$cell_val = $raw_v;
						}
					}

					$cell_val              = trim( $cell_val );
					$row_cells[ $col_idx ] = $cell_val;
					if ( $col_idx > $max_col ) {
						$max_col = $col_idx;
					}
				}
			}

			if ( $max_col >= 0 ) {
				$dense = array();
				$non_empty = false;
				for ( $c = 0; $c <= $max_col; ++$c ) {
					$v = $row_cells[ $c ] ?? '';
					if ( '' !== $v ) {
						$non_empty = true;
					}
					$dense[] = $v;
				}
				if ( $non_empty ) {
					$rows[] = $dense;
				}
			}
		}
	}

	return $rows;
}

/**
 * Ensure raw text uploaded from CSV/TXT is clean UTF-8 (auto-converting Windows-1256 Persian CSVs!).
 *
 * @param string $raw Raw file bytes.
 * @return string
 */
function hj_glossary_ensure_utf8( string $raw ): string {
	// Strip UTF-8 BOM if present.
	if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
		return substr( $raw, 3 );
	}
	// Strip UTF-16LE / UTF-16BE BOM and convert if needed.
	if ( 0 === strpos( $raw, "\xFF\xFE" ) && function_exists( 'iconv' ) ) {
		$conv = @iconv( 'UTF-16LE', 'UTF-8//IGNORE', substr( $raw, 2 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_string( $conv ) && '' !== $conv ) {
			return $conv;
		}
	}
	if ( function_exists( 'seems_utf8' ) && seems_utf8( $raw ) ) {
		return $raw;
	}
	if ( function_exists( 'iconv' ) ) {
		$conv = @iconv( 'Windows-1256', 'UTF-8//IGNORE', $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_string( $conv ) && '' !== $conv ) {
			return $conv;
		}
	}
	return $raw;
}

/**
 * Parse CSV / TSV / pipe-delimited / colon-delimited text into a 2D array of rows.
 *
 * @param string $text Raw UTF-8 text.
 * @return array<int,array<int,string>>
 */
function hj_glossary_parse_delimited_text( string $text ): array {
	$text = str_replace( array( "\r\n", "\r" ), "\n", trim( $text ) );
	if ( '' === $text ) {
		return array();
	}

	// Sample first 15 non-empty lines to detect delimiter accurately.
	$sample_lines = array_slice( array_values( array_filter( explode( "\n", $text ), 'strlen' ) ), 0, 15 );
	$candidates   = array( "\t", ',', ';', '|' );
	$best_delim   = ',';
	$best_score   = -1;

	foreach ( $candidates as $delim ) {
		$counts = array();
		foreach ( $sample_lines as $sl ) {
			$parsed = str_getcsv( $sl, $delim );
			$counts[] = count( $parsed );
		}
		$min_cols = ! empty( $counts ) ? min( $counts ) : 0;
		$avg_cols = ! empty( $counts ) ? ( array_sum( $counts ) / count( $counts ) ) : 0;
		$score    = ( $min_cols >= 2 ? 100 : 0 ) + $avg_cols;
		if ( $score > $best_score && $avg_cols >= 1.8 ) {
			$best_score = $score;
			$best_delim = $delim;
		}
	}

	// If no CSV/Tab/Pipe delimiter matched >= 2 cols, support «واژه : معنی» or «واژه — معنی» plain lines!
	if ( $best_score < 0 ) {
		$rows = array();
		foreach ( explode( "\n", $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^([^:：—–\-]{1,120})\s*(?::|：|—|–|\s-\s)\s*(.+)$/u', $line, $m ) ) {
				$rows[] = array( trim( $m[1] ), trim( $m[2] ) );
			}
		}
		return $rows;
	}

	// Full multi-line-aware CSV parse via php://temp stream.
	$rows = array();
	$fp   = fopen( 'php://temp', 'r+' );
	if ( false === $fp ) {
		foreach ( explode( "\n", $text ) as $line ) {
			if ( '' !== trim( $line ) ) {
				$rows[] = array_map( 'trim', str_getcsv( $line, $best_delim ) );
			}
		}
		return $rows;
	}

	fwrite( $fp, $text );
	rewind( $fp );
	while ( false !== ( $data = fgetcsv( $fp, 0, $best_delim ) ) ) {
		$clean = array_map(
			static function ( $v ) {
				return trim( (string) $v );
			},
			$data
		);
		if ( '' !== implode( '', $clean ) ) {
			$rows[] = $clean;
		}
	}
	fclose( $fp );

	return $rows;
}

/**
 * Parse a JSON glossary payload (supports array of objects, `{terms:[...]}`, OR `{"واژه":"معنی"}` map).
 *
 * @param string $json_str Raw JSON text.
 * @return array<int,array<int,string>>|\WP_Error
 */
function hj_glossary_parse_json_text( string $json_str ) {
	$decoded = json_decode( trim( $json_str ), true );
	if ( ! is_array( $decoded ) ) {
		return new WP_Error( 'bad_json', 'ساختار فایل JSON معتبر نیست.' );
	}

	if ( isset( $decoded['terms'] ) && is_array( $decoded['terms'] ) ) {
		$decoded = $decoded['terms'];
	} elseif ( isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
		$decoded = $decoded['items'];
	}

	$rows = array(
		array( 'واژه', 'تعریف کوتاه', 'دسته‌بندی موضوعی', 'معادل انگلیسی', 'مترادف‌ها' ),
	);

	// Check if associative map `{"اسکراب": "شست‌وشوی استریل...", ...}`.
	$is_list = array_keys( $decoded ) === range( 0, count( $decoded ) - 1 );
	if ( ! $is_list ) {
		foreach ( $decoded as $k => $v ) {
			if ( is_string( $v ) ) {
				$rows[] = array( trim( (string) $k ), trim( $v ), '', '', '' );
			} elseif ( is_array( $v ) ) {
				$rows[] = array(
					trim( (string) ( $v['term'] ?? $v['title'] ?? $k ) ),
					trim( (string) ( $v['definition'] ?? $v['meaning'] ?? $v['desc'] ?? '' ) ),
					trim( (string) ( $v['group'] ?? $v['category'] ?? $v['topic'] ?? '' ) ),
					trim( (string) ( $v['en_term'] ?? $v['en'] ?? $v['english'] ?? '' ) ),
					is_array( $v['aliases'] ?? null ) ? implode( '، ', $v['aliases'] ) : trim( (string) ( $v['aliases'] ?? $v['synonyms'] ?? '' ) ),
				);
			}
		}
		return $rows;
	}

	foreach ( $decoded as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		// Numeric tuple `["واژه", "معنی", "دسته"]` vs associative object.
		if ( isset( $item[0], $item[1] ) && ! isset( $item['term'] ) && ! isset( $item['واژه'] ) ) {
			$rows[] = array(
				trim( (string) ( $item[0] ?? '' ) ),
				trim( (string) ( $item[1] ?? '' ) ),
				trim( (string) ( $item[2] ?? '' ) ),
				trim( (string) ( $item[3] ?? '' ) ),
				trim( (string) ( $item[4] ?? '' ) ),
			);
			continue;
		}

		$term = $item['term'] ?? $item['title'] ?? $item['word'] ?? $item['name'] ?? $item['واژه'] ?? $item['اصطلاح'] ?? $item['کلمه'] ?? '';
		$def  = $item['definition'] ?? $item['meaning'] ?? $item['desc'] ?? $item['description'] ?? $item['معنی'] ?? $item['تعریف'] ?? $item['توضیح'] ?? '';
		$grp  = $item['group'] ?? $item['category'] ?? $item['topic'] ?? $item['subject'] ?? $item['دسته'] ?? $item['دسته‌بندی'] ?? $item['موضوع'] ?? $item['گروه'] ?? '';
		$en   = $item['en_term'] ?? $item['english'] ?? $item['en'] ?? $item['انگلیسی'] ?? $item['معادل انگلیسی'] ?? '';
		$ali  = $item['aliases'] ?? $item['synonyms'] ?? $item['مترادف'] ?? $item['مترادف‌ها'] ?? '';
		if ( is_array( $ali ) ) {
			$ali = implode( '، ', $ali );
		}

		$rows[] = array(
			trim( (string) $term ),
			trim( (string) $def ),
			trim( (string) $grp ),
			trim( (string) $en ),
			trim( (string) $ali ),
		);
	}

	return $rows;
}

/**
 * Auto-detect column mapping & whether row 0 is a header row.
 *
 * @param array<int,array<int,string>> $rows Parsed 2D rows.
 * @return array{has_header:bool,cols:array{term:int,definition:int,group:int,en_term:int,aliases:int}}
 */
function hj_glossary_detect_columns( array $rows ): array {
	$default = array(
		'has_header' => false,
		'cols'       => array(
			'term'       => 0,
			'definition' => 1,
			'group'      => -1,
			'en_term'    => -1,
			'aliases'    => -1,
		),
	);

	if ( empty( $rows ) ) {
		return $default;
	}

	$first = $rows[0];
	$col_count = count( $first );
	if ( $col_count >= 3 ) {
		$default['cols']['group'] = 2;
	}
	if ( $col_count >= 4 ) {
		$default['cols']['en_term'] = 3;
	}
	if ( $col_count >= 5 ) {
		$default['cols']['aliases'] = 4;
	}

	$term_headers = array( 'term', 'word', 'title', 'name', 'واژه', 'اصطلاح', 'کلمه', 'نام واژه', 'عنوان', 'عبارت' );
	$def_headers  = array( 'definition', 'meaning', 'desc', 'description', 'معنی', 'تعریف', 'تعریف کوتاه', 'توضیح', 'معنا', 'شرح' );
	$grp_headers  = array( 'group', 'category', 'topic', 'subject', 'دسته', 'دسته بندی', 'دسته‌بندی', 'دسته‌بندی موضوعی', 'موضوع', 'گروه', 'بخش' );
	$en_headers   = array( 'en', 'en_term', 'english', 'latin', 'انگلیسی', 'معادل انگلیسی', 'لاتین' );
	$ali_headers  = array( 'aliases', 'alias', 'synonyms', 'synonym', 'مترادف', 'مترادف ها', 'مترادف‌ها', 'هم ارز', 'هم‌ارز' );

	$detected = array(
		'term'       => -1,
		'definition' => -1,
		'group'      => -1,
		'en_term'    => -1,
		'aliases'    => -1,
	);
	$header_hits = 0;

	foreach ( $first as $idx => $cell ) {
		$norm = hj_glossary_normalize_text( $cell, true );
		if ( in_array( $norm, $term_headers, true ) ) {
			$detected['term'] = $idx;
			++$header_hits;
		} elseif ( in_array( $norm, $def_headers, true ) ) {
			$detected['definition'] = $idx;
			++$header_hits;
		} elseif ( in_array( $norm, $grp_headers, true ) ) {
			$detected['group'] = $idx;
			++$header_hits;
		} elseif ( in_array( $norm, $en_headers, true ) ) {
			$detected['en_term'] = $idx;
			++$header_hits;
		} elseif ( in_array( $norm, $ali_headers, true ) ) {
			$detected['aliases'] = $idx;
			++$header_hits;
		}
	}

	if ( $header_hits >= 1 ) {
		if ( $detected['term'] < 0 ) {
			$detected['term'] = 0;
		}
		if ( $detected['definition'] < 0 ) {
			$detected['definition'] = ( 0 === $detected['term'] ) ? 1 : 0;
		}
		return array(
			'has_header' => true,
			'cols'       => $detected,
		);
	}

	return $default;
}

/* =========================================================================
 * 2. AJAX Endpoints for Bulk File Upload/Staging + Chunked Batch Execution
 * ====================================================================== */

/**
 * Return staging directory inside `wp-content/uploads/hj-cache/`.
 *
 * @return string
 */
function hj_glossary_staging_dir(): string {
	$uploads = wp_upload_dir( null, false );
	$basedir = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
	$dir     = trailingslashit( $basedir ) . 'hj-cache/glossary-staging';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

/**
 * Step 1 AJAX: Parse uploaded file (`.xlsx`, `.csv`, `.tsv`, `.json`, `.txt`) or pasted text,
 * stage normalized rows on disk, and return column auto-detection + 8-row live preview.
 *
 * @return void
 */
function hj_glossary_ajax_stage_import() {
	check_ajax_referer( 'hj_glossary_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
	}

	$rows = array();

	if ( ! empty( $_FILES['glossary_file']['tmp_name'] ) && is_uploaded_file( $_FILES['glossary_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$tmp_path = $_FILES['glossary_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$orig_fn  = sanitize_file_name( wp_unslash( $_FILES['glossary_file']['name'] ?? 'upload.csv' ) );
		$ext      = strtolower( pathinfo( $orig_fn, PATHINFO_EXTENSION ) );

		if ( 'xlsx' === $ext ) {
			$parsed = hj_glossary_parse_xlsx_file( $tmp_path );
			if ( is_wp_error( $parsed ) ) {
				wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
			}
			$rows = $parsed;
		} elseif ( 'json' === $ext ) {
			$raw    = hj_glossary_ensure_utf8( (string) file_get_contents( $tmp_path ) );
			$parsed = hj_glossary_parse_json_text( $raw );
			if ( is_wp_error( $parsed ) ) {
				wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
			}
			$rows = $parsed;
		} else {
			$raw  = hj_glossary_ensure_utf8( (string) file_get_contents( $tmp_path ) );
			$rows = hj_glossary_parse_delimited_text( $raw );
		}
	} elseif ( ! empty( $_POST['pasted_text'] ) ) {
		$pasted = trim( wp_unslash( $_POST['pasted_text'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $pasted && ( '[' === $pasted[0] || '{' === $pasted[0] ) ) {
			$parsed = hj_glossary_parse_json_text( $pasted );
			if ( ! is_wp_error( $parsed ) ) {
				$rows = $parsed;
			}
		}
		if ( empty( $rows ) ) {
			$rows = hj_glossary_parse_delimited_text( $pasted );
		}
	} else {
		wp_send_json_error( array( 'message' => 'لطفاً یک فایل انتخاب کنید یا متن واژگان را در کادر جای‌گذاری کنید.' ) );
	}

	if ( empty( $rows ) ) {
		wp_send_json_error( array( 'message' => 'هیچ ردیف معتبری در ورودی پیدا نشد. مطمئن شوید هر خط شامل واژه و معنی است.' ) );
	}

	$detection  = hj_glossary_detect_columns( $rows );
	$max_cols   = 0;
	foreach ( array_slice( $rows, 0, 25 ) as $r ) {
		if ( count( $r ) > $max_cols ) {
			$max_cols = count( $r );
		}
	}

	$token      = 'imp_' . strtolower( wp_generate_password( 16, false, false ) );
	$stage_file = trailingslashit( hj_glossary_staging_dir() ) . $token . '.json';
	$encoded    = wp_json_encode( $rows, JSON_UNESCAPED_UNICODE );

	if ( false === @file_put_contents( $stage_file, $encoded, LOCK_EX ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		wp_send_json_error( array( 'message' => 'خطا در ذخیره‌ی موقت فایل در پوشه‌ی uploads.' ) );
	}

	set_transient( 'hj_glossary_stage_' . $token, $stage_file, 2 * HOUR_IN_SECONDS );

	wp_send_json_success(
		array(
			'token'      => $token,
			'total_rows' => count( $rows ),
			'max_cols'   => $max_cols,
			'has_header' => $detection['has_header'],
			'cols'       => $detection['cols'],
			'preview'    => array_slice( $rows, 0, 8 ),
		)
	);
}
add_action( 'wp_ajax_hj_glossary_stage_import', 'hj_glossary_ajax_stage_import' );

/**
 * Step 2 AJAX: Import one chunk of rows (e.g. 400 rows per call) from staged JSON,
 * updating progress smoothly and rebuilding the compiled index on the final chunk!
 *
 * @return void
 */
function hj_glossary_ajax_process_chunk() {
	check_ajax_referer( 'hj_glossary_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
	}

	$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) ) : '';
	$stage_file = $token ? (string) get_transient( 'hj_glossary_stage_' . $token ) : '';

	if ( '' === $stage_file || ! is_readable( $stage_file ) ) {
		wp_send_json_error( array( 'message' => 'نشست ایمپورت منقضی شده یا فایل موقت پیدا نشد. لطفاً دوباره فایل را انتخاب کنید.' ) );
	}

	$rows = json_decode( (string) file_get_contents( $stage_file ), true );
	if ( ! is_array( $rows ) ) {
		wp_send_json_error( array( 'message' => 'خطا در خواندن داده‌های مرحله‌ی ایمپورت.' ) );
	}

	$offset         = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
	$batch_size     = isset( $_POST['batch_size'] ) ? max( 50, min( 800, (int) $_POST['batch_size'] ) ) : 400;
	$has_header     = ! empty( $_POST['has_header'] );
	$duplicate_mode = ( isset( $_POST['duplicate_mode'] ) && 'skip' === $_POST['duplicate_mode'] ) ? 'skip' : 'update';
	$default_group  = isset( $_POST['default_group'] ) ? sanitize_text_field( wp_unslash( $_POST['default_group'] ) ) : '';

	$col_term = isset( $_POST['col_term'] ) ? (int) $_POST['col_term'] : 0;
	$col_def  = isset( $_POST['col_def'] ) ? (int) $_POST['col_def'] : 1;
	$col_grp  = isset( $_POST['col_grp'] ) ? (int) $_POST['col_grp'] : -1;
	$col_en   = isset( $_POST['col_en'] ) ? (int) $_POST['col_en'] : -1;
	$col_ali  = isset( $_POST['col_ali'] ) ? (int) $_POST['col_ali'] : -1;

	if ( 0 === $offset && $has_header ) {
		$offset = 1;
	}

	$total_rows = count( $rows );
	$slice      = array_slice( $rows, $offset, $batch_size );

	$inserted = 0;
	$updated  = 0;
	$skipped  = 0;
	$invalid  = 0;

	$default_group_id = '' !== $default_group ? hj_glossary_resolve_group_id( $default_group, true ) : 0;

	if ( function_exists( 'wp_defer_term_counting' ) ) {
		wp_defer_term_counting( true );
	}

	foreach ( $slice as $row ) {
		if ( ! is_array( $row ) ) {
			++$invalid;
			continue;
		}
		$term_val = ( $col_term >= 0 && isset( $row[ $col_term ] ) ) ? trim( (string) $row[ $col_term ] ) : '';
		$def_val  = ( $col_def >= 0 && isset( $row[ $col_def ] ) ) ? trim( (string) $row[ $col_def ] ) : '';
		$grp_val  = ( $col_grp >= 0 && isset( $row[ $col_grp ] ) ) ? trim( (string) $row[ $col_grp ] ) : '';
		$en_val   = ( $col_en >= 0 && isset( $row[ $col_en ] ) ) ? trim( (string) $row[ $col_en ] ) : '';
		$ali_val  = ( $col_ali >= 0 && isset( $row[ $col_ali ] ) ) ? trim( (string) $row[ $col_ali ] ) : '';

		if ( '' === $term_val || '' === $def_val ) {
			++$invalid;
			continue;
		}

		$group_input = '' !== $grp_val ? $grp_val : $default_group_id;

		$res = hj_glossary_upsert_term(
			array(
				'term'              => $term_val,
				'definition'        => $def_val,
				'group'             => $group_input,
				'en_term'           => $en_val,
				'aliases'           => $ali_val,
				'highlight_enabled' => 1,
				'status'            => 'publish',
			),
			$duplicate_mode,
			false // Rebuild compiled index once at the very end!
		);

		if ( ! $res['ok'] ) {
			++$invalid;
		} elseif ( 'inserted' === $res['action'] ) {
			++$inserted;
		} elseif ( 'updated' === $res['action'] ) {
			++$updated;
		} else {
			++$skipped;
		}
	}

	if ( function_exists( 'wp_defer_term_counting' ) ) {
		wp_defer_term_counting( false );
	}

	$next_offset = $offset + count( $slice );
	$done        = ( $next_offset >= $total_rows );
	$index_stats = null;

	if ( $done ) {
		$idx_built   = hj_glossary_rebuild_index();
		$index_stats = array(
			'term_count'   => $idx_built['term_count'],
			'phrase_count' => $idx_built['phrase_count'],
		);
		@unlink( $stage_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		delete_transient( 'hj_glossary_stage_' . $token );
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
	}

	wp_send_json_success(
		array(
			'done'        => $done,
			'next_offset' => $next_offset,
			'total_rows'  => $total_rows,
			'inserted'    => $inserted,
			'updated'     => $updated,
			'skipped'     => $skipped,
			'invalid'     => $invalid,
			'index_stats' => $index_stats,
		)
	);
}
add_action( 'wp_ajax_hj_glossary_process_chunk', 'hj_glossary_ajax_process_chunk' );

/* =========================================================================
 * 3. Sample Template Downloads & Full CSV / JSON Export
 * ====================================================================== */

/**
 * Stream downloadable sample templates or full glossary backup exports.
 *
 * @return void
 */
function hj_glossary_handle_downloads_and_actions() {
	if ( ! is_admin() || ! isset( $_GET['hj_glossary_download'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	check_admin_referer( 'hj_glossary_download_nonce' );

	$kind = sanitize_key( wp_unslash( $_GET['hj_glossary_download'] ) );

	if ( 'sample_csv' === $kind ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="glossary-sample-template.csv"' );
		echo "\xEF\xBB\xBF"; // UTF-8 BOM so Microsoft Excel opens Persian columns cleanly!
		$fp = fopen( 'php://output', 'w' );
		fputcsv( $fp, array( 'واژه', 'تعریف کوتاه', 'دسته‌بندی موضوعی', 'معادل انگلیسی', 'مترادف‌ها' ) );
		fputcsv( $fp, array( 'پالس اکسیمتر', 'حسگر گیره‌ای بدون درد روی نوک انگشت که درصد اکسیژن خون و ضربان قلب را لحظه‌ای نشان می‌دهد.', 'ابزار و دستگاه', 'Pulse Oximeter', 'پالس‌اکسیمتر، اکسیمتر نبضی' ) );
		fputcsv( $fp, array( 'بیهوشی عمومی', 'حالتی کنترل‌شده از خواب عمیق دارویی که در آن بیمار هیچ دردی حس نمی‌کند و از عمل آگاه نیست.', 'اصطلاحات بیهوشی', 'General Anesthesia', 'بیهوشی کامل' ) );
		fputcsv( $fp, array( 'میدان استریل', 'محدوده‌ی کاملاً عاری از میکروب اطراف محل جراحی که فقط با دستکش و ابزار استریل لمس می‌شود.', 'اصطلاحات استریل', 'Sterile Field', 'ناحیه استریل' ) );
		fputcsv( $fp, array( 'ناشتایی قبل از عمل', 'پرهیز از خوردن و آشامیدن پیش از بیهوشی برای پیشگیری از برگشت محتویات معده به ریه.', 'اصطلاحات عمومی جراحی', 'NPO', 'ناشتا بودن قبل از عمل' ) );
		fclose( $fp );
		exit;
	}

	if ( 'sample_json' === $kind ) {
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="glossary-sample-template.json"' );
		$sample = array(
			array(
				'term'       => 'پالس اکسیمتر',
				'definition' => 'حسگر گیره‌ای بدون درد روی نوک انگشت که درصد اکسیژن خون و ضربان قلب را لحظه‌ای نشان می‌دهد.',
				'group'      => 'ابزار و دستگاه',
				'en_term'    => 'Pulse Oximeter',
				'aliases'    => 'پالس‌اکسیمتر، اکسیمتر نبضی',
			),
			array(
				'term'       => 'بیهوشی عمومی',
				'definition' => 'حالتی کنترل‌شده از خواب عمیق دارویی که در آن بیمار هیچ دردی حس نمی‌کند و از عمل آگاه نیست.',
				'group'      => 'اصطلاحات بیهوشی',
				'en_term'    => 'General Anesthesia',
				'aliases'    => 'بیهوشی کامل',
			),
		);
		echo wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	if ( 'export_csv' === $kind || 'export_json' === $kind ) {
		hj_glossary_ensure_table();
		global $wpdb;
		$table  = hj_glossary_table_name();
		$groups = hj_glossary_get_groups_with_counts( false );
		$rows   = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY term ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 'export_csv' === $kind ) {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="hamyarjarahi-glossary-backup-' . gmdate( 'Y-m-d' ) . '.csv"' );
			echo "\xEF\xBB\xBF";
			$fp = fopen( 'php://output', 'w' );
			fputcsv( $fp, array( 'واژه', 'تعریف کوتاه', 'دسته‌بندی موضوعی', 'معادل انگلیسی', 'مترادف‌ها', 'وضعیت هایلایت' ) );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $r ) {
					$gname = isset( $groups[ (int) $r['group_id'] ] ) ? $groups[ (int) $r['group_id'] ]['name'] : '';
					fputcsv(
						$fp,
						array(
							$r['term'],
							$r['definition'],
							$gname,
							$r['en_term'],
							$r['aliases'],
							(int) $r['highlight_enabled'] ? '1' : '0',
						)
					);
				}
			}
			fclose( $fp );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="hamyarjarahi-glossary-backup-' . gmdate( 'Y-m-d' ) . '.json"' );
		$export = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$gname    = isset( $groups[ (int) $r['group_id'] ] ) ? $groups[ (int) $r['group_id'] ]['name'] : '';
				$export[] = array(
					'term'              => $r['term'],
					'definition'        => $r['definition'],
					'group'             => $gname,
					'en_term'           => $r['en_term'],
					'aliases'           => $r['aliases'],
					'highlight_enabled' => (int) $r['highlight_enabled'],
				);
			}
		}
		echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}
}
add_action( 'admin_init', 'hj_glossary_handle_downloads_and_actions' );

/* =========================================================================
 * 4. Admin Menu Registration & POST Form Handlers
 * ====================================================================== */

/**
 * Helper URL to the Glossary Admin screen.
 *
 * @param string $tab Optional tab (`terms`, `import`, `groups`, `settings`).
 * @return string
 */
function hj_glossary_admin_url( string $tab = 'terms' ): string {
	$args = array(
		'post_type' => 'glossary',
		'page'      => 'hj-glossary-manager',
	);
	if ( '' !== $tab && 'terms' !== $tab ) {
		$args['tab'] = $tab;
	}
	return add_query_arg( $args, admin_url( 'edit.php' ) );
}

/**
 * Register submenu items under `واژه‌نامه` and `همیار جراحی`.
 *
 * @return void
 */
function hj_glossary_register_admin_menus() {
	add_submenu_page(
		'edit.php?post_type=glossary',
		'بانک واژگان و هایلایت خودکار',
		'بانک واژگان (۵۰۰۰+)',
		'edit_posts',
		'hj-glossary-manager',
		'hj_glossary_render_admin_page',
		1
	);

	add_submenu_page(
		'hj-content-dashboard',
		'واژه‌نامه و هایلایت سبز مقالات',
		'واژه‌نامه و هایلایت سبز',
		'edit_posts',
		'hj-glossary-hub-shortcut',
		'hj_glossary_render_admin_page'
	);
}
add_action( 'admin_menu', 'hj_glossary_register_admin_menus', 15 );

/**
 * Show a helpful shortcut banner on the default `edit.php?post_type=glossary` screen
 * guiding editors directly to the 5,000+ Bulk Glossary Manager.
 *
 * @return void
 */
function hj_glossary_cpt_list_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-glossary' !== $screen->id ) {
		return;
	}
	$stats = hj_glossary_get_stats();
	?>
	<div class="notice notice-info" style="border-right-color:#059669;padding:12px 16px;margin-top:14px;">
		<p style="margin:0;font-size:13.5px;line-height:1.8;">
			<strong style="color:#065f46;">✨ موتور واژه‌نامه‌ی سریع و هایلایت سبز خودکار فعال است</strong>
			(واژگان فعال در بانک سریع: <strong><?php echo esc_html( function_exists( 'hj_fa_digits' ) ? hj_fa_digits( number_format_i18n( $stats['published'] ) ) : (string) $stats['published'] ); ?></strong> واژه در <strong><?php echo esc_html( function_exists( 'hj_fa_digits' ) ? hj_fa_digits( $stats['groups_nonempty'] ) : (string) $stats['groups_nonempty'] ); ?></strong> موضوع).
			برای <strong>ورود یک‌جای ۵۰۰۰+ واژه</strong> از اکسل/CSV/JSON یا مدیریت تعاریف یک‌خطی، از بخش زیر استفاده کنید:
			<a href="<?php echo esc_url( hj_glossary_admin_url( 'import' ) ); ?>" class="button button-primary" style="background:#059669;border-color:#047857;margin-right:8px;">ورود انبوه واژگان (۵۰۰۰+)</a>
			<a href="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>" class="button" style="margin-right:4px;">مدیریت بانک واژگان</a>
			<a href="<?php echo esc_url( hj_archive_url( 'glossary' ) ); ?>" class="button" target="_blank" rel="noopener" style="margin-right:4px;">مشاهده‌ی صفحه‌ی واژه‌نامه ↗</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'hj_glossary_cpt_list_notice' );

/**
 * Process synchronous admin forms (add/edit single term, bulk table actions, create topic, save settings, rebuild index).
 *
 * @return array{type:string,text:string}|null
 */
function hj_glossary_handle_post_forms(): ?array {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['hj_glossary_action'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return array( 'type' => 'error', 'text' => 'دسترسی مجاز نیست.' );
	}
	check_admin_referer( 'hj_glossary_form_nonce', 'hj_glossary_nonce' );

	$action = sanitize_key( wp_unslash( $_POST['hj_glossary_action'] ) );
	global $wpdb;
	$table = hj_glossary_table_name();

	// 1. Save single term (add or edit).
	if ( 'save_term' === $action ) {
		$new_group_name = isset( $_POST['new_group_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['new_group_name'] ) ) ) : '';
		$group_input    = '' !== $new_group_name ? $new_group_name : ( isset( $_POST['group_id'] ) ? (int) $_POST['group_id'] : 0 );

		$res = hj_glossary_upsert_term(
			array(
				'id'                => isset( $_POST['term_id'] ) ? (int) $_POST['term_id'] : 0,
				'term'              => isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '',
				'definition'        => isset( $_POST['definition'] ) ? sanitize_textarea_field( wp_unslash( $_POST['definition'] ) ) : '',
				'group'             => $group_input,
				'en_term'           => isset( $_POST['en_term'] ) ? sanitize_text_field( wp_unslash( $_POST['en_term'] ) ) : '',
				'aliases'           => isset( $_POST['aliases'] ) ? sanitize_text_field( wp_unslash( $_POST['aliases'] ) ) : '',
				'highlight_enabled' => ! empty( $_POST['highlight_enabled'] ) ? 1 : 0,
				'status'            => 'publish',
			),
			'update',
			true
		);

		return array(
			'type' => $res['ok'] ? 'success' : 'error',
			'text' => $res['message'],
		);
	}

	// 2. Delete single or bulk terms / bulk move category / bulk toggle highlight.
	if ( 'bulk_terms' === $action ) {
		$ids = isset( $_POST['term_ids'] ) && is_array( $_POST['term_ids'] ) ? array_map( 'intval', $_POST['term_ids'] ) : array();
		$ids = array_values( array_filter( $ids ) );
		$op  = isset( $_POST['bulk_op'] ) ? sanitize_key( wp_unslash( $_POST['bulk_op'] ) ) : '';

		if ( empty( $ids ) ) {
			return array( 'type' => 'error', 'text' => 'هیچ واژه‌ای انتخاب نشده است.' );
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'delete' === $op ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$id_placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			hj_glossary_rebuild_index();
			return array( 'type' => 'success', 'text' => sprintf( '%d واژه حذف شد و ایندکس هایلایت به‌روز گردید.', count( $ids ) ) );
		}
		if ( 'enable_hl' === $op || 'disable_hl' === $op ) {
			$val = ( 'enable_hl' === $op ) ? 1 : 0;
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET highlight_enabled = %d WHERE id IN ({$id_placeholders})", array_merge( array( $val ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			hj_glossary_rebuild_index();
			return array( 'type' => 'success', 'text' => 'وضعیت هایلایت واژگان انتخاب‌شده به‌روزرسانی شد.' );
		}
		if ( 'move_group' === $op ) {
			$target_gid = isset( $_POST['bulk_target_group'] ) ? (int) $_POST['bulk_target_group'] : 0;
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET group_id = %d WHERE id IN ({$id_placeholders})", array_merge( array( $target_gid ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			hj_glossary_rebuild_index();
			return array( 'type' => 'success', 'text' => 'دسته‌بندی موضوعی واژگان انتخاب‌شده تغییر کرد.' );
		}
	}

	// 3. Create a new topic (`glossary_group`).
	if ( 'create_group' === $action ) {
		$gname = isset( $_POST['group_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['group_name'] ) ) ) : '';
		$gslug = isset( $_POST['group_slug'] ) ? sanitize_title( wp_unslash( $_POST['group_slug'] ) ) : '';
		$gdesc = isset( $_POST['group_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['group_desc'] ) ) : '';
		if ( '' === $gname ) {
			return array( 'type' => 'error', 'text' => 'نام موضوع الزامی است.' );
		}
		$args = array( 'description' => $gdesc );
		if ( '' !== $gslug ) {
			$args['slug'] = $gslug;
		}
		$created = wp_insert_term( $gname, 'glossary_group', $args );
		if ( is_wp_error( $created ) ) {
			return array( 'type' => 'error', 'text' => $created->get_error_message() );
		}
		return array( 'type' => 'success', 'text' => 'دسته‌بندی موضوعی «' . $gname . '» با موفقیت ساخته شد.' );
	}

	// 4. Save highlight & display settings.
	if ( 'save_settings' === $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'type' => 'error', 'text' => 'فقط مدیر سایت مجاز به تغییر تنظیمات سراسری است.' );
		}
		$allowed_cpts = array( 'patient_guide', 'or_environment', 'surgery', 'student_content', 'post', 'page' );
		$posted_cpts  = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_values( array_intersect( $allowed_cpts, array_map( 'sanitize_key', $_POST['post_types'] ) ) )
			: array();

		$hl_style = isset( $_POST['highlight_style'] ) ? sanitize_key( wp_unslash( $_POST['highlight_style'] ) ) : 'soft-green';
		if ( ! in_array( $hl_style, array( 'soft-green', 'vivid-green', 'underline-green' ), true ) ) {
			$hl_style = 'soft-green';
		}

		$new_settings = array(
			'enabled'            => ! empty( $_POST['enabled'] ) ? 1 : 0,
			'post_types'         => $posted_cpts,
			'max_per_term'       => isset( $_POST['max_per_term'] ) ? max( 0, min( 10, (int) $_POST['max_per_term'] ) ) : 1,
			'max_total_per_post' => isset( $_POST['max_total_per_post'] ) ? max( 5, min( 300, (int) $_POST['max_total_per_post'] ) ) : 70,
			'highlight_style'    => $hl_style,
			'match_english'      => ! empty( $_POST['match_english'] ) ? 1 : 0,
			'match_plurals'      => ! empty( $_POST['match_plurals'] ) ? 1 : 0,
			'show_glossary_link' => ! empty( $_POST['show_glossary_link'] ) ? 1 : 0,
			'per_page'           => isset( $_POST['per_page'] ) ? max( 20, min( 200, (int) $_POST['per_page'] ) ) : 60,
		);

		update_option( 'hj_glossary_settings', $new_settings, true );
		hj_glossary_rebuild_index();
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		return array( 'type' => 'success', 'text' => 'تنظیمات هایلایت سبز و باکس تعاریف ذخیره شد و کش ایندکس بازسازی گردید.' );
	}

	// 5. Force rebuild compiled index.
	if ( 'rebuild_index' === $action ) {
		$idx = hj_glossary_rebuild_index();
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		return array(
			'type' => 'success',
			'text' => sprintf( 'ایندکس سریع واژه‌نامه بازسازی شد (%d واژه‌ی فعال، %d کلیدواژه و مترادف).', $idx['term_count'], $idx['phrase_count'] ),
		);
	}

	return null;
}

/* =========================================================================
 * 5. Render Complete Admin Interface
 * ====================================================================== */

/**
 * Render the 4-tab Glossary Admin interface.
 *
 * @return void
 */
function hj_glossary_render_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'دسترسی مجاز نیست.' );
	}

	hj_glossary_ensure_table();
	$flash = hj_glossary_handle_post_forms();

	// Handle quick GET single-row delete safely with nonce.
	if ( isset( $_GET['del_term'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'hj_del_term_' . (int) $_GET['del_term'] ) ) {
		global $wpdb;
		$wpdb->delete( hj_glossary_table_name(), array( 'id' => (int) $_GET['del_term'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		hj_glossary_rebuild_index();
		$flash = array( 'type' => 'success', 'text' => 'واژه حذف شد.' );
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'terms'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $active_tab, array( 'terms', 'import', 'groups', 'settings' ), true ) ) {
		$active_tab = 'terms';
	}

	$stats      = hj_glossary_get_stats();
	$groups     = hj_glossary_get_groups_with_counts( false );
	$settings   = hj_glossary_get_settings();
	$index_meta = get_option( 'hj_glossary_index_meta', array() );
	$ajax_nonce = wp_create_nonce( 'hj_glossary_admin_nonce' );
	$dl_nonce   = wp_create_nonce( 'hj_glossary_download_nonce' );

	$fa = static function ( $val ): string {
		return function_exists( 'hj_fa_digits' ) ? hj_fa_digits( (string) $val ) : (string) $val;
	};
	?>
	<div class="wrap hj-gl-admin" dir="rtl">
		<style>
			.hj-gl-admin { max-width: 1260px; font-family: Vazirmatn, Tahoma, sans-serif; }
			.hj-gl-hero { background: linear-gradient(135deg, #06265C 0%, #064e3b 100%); color: #fff; border-radius: 14px; padding: 20px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin: 14px 0 18px; box-shadow: 0 8px 24px rgba(6,38,92,0.14); }
			.hj-gl-hero h1 { color: #fff; margin: 0 0 6px; font-size: 21px; font-weight: 800; padding: 0; }
			.hj-gl-hero p { margin: 0; color: rgba(255,255,255,0.88); font-size: 13.5px; }
			.hj-gl-kpis { display: flex; flex-wrap: wrap; gap: 10px; }
			.hj-gl-kpi { background: rgba(255,255,255,0.13); border: 1px solid rgba(255,255,255,0.22); border-radius: 10px; padding: 8px 14px; text-align: center; min-width: 105px; }
			.hj-gl-kpi strong { display: block; font-size: 19px; color: #6ee7b7; line-height: 1.3; }
			.hj-gl-kpi span { font-size: 11.5px; color: rgba(255,255,255,0.9); }
			.hj-gl-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
			.hj-gl-tab { text-decoration: none; padding: 9px 16px; border-radius: 9px; font-weight: 700; font-size: 13.5px; color: #334155; background: #fff; border: 1px solid #cbd5e1; transition: all .15s ease; }
			.hj-gl-tab:hover { border-color: #059669; color: #065f46; }
			.hj-gl-tab.is-active { background: #059669; border-color: #047857; color: #fff; box-shadow: 0 4px 12px rgba(5,150,105,0.22); }
			.hj-gl-card { background: #fff; border: 1px solid #dbe3ee; border-radius: 12px; padding: 20px 22px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(15,23,42,0.04); }
			.hj-gl-card h2 { margin-top: 0; font-size: 16px; color: #06265C; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
			.hj-gl-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
			.hj-gl-field { margin-bottom: 12px; }
			.hj-gl-field label { display: block; font-weight: 700; margin-bottom: 5px; color: #1e293b; font-size: 13px; }
			.hj-gl-field input[type="text"], .hj-gl-field input[type="number"], .hj-gl-field select, .hj-gl-field textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 13.5px; }
			.hj-gl-btn-green { background: #059669 !important; border-color: #047857 !important; color: #fff !important; border-radius: 8px !important; padding: 4px 16px !important; font-weight: 700 !important; }
			.hj-gl-btn-green:hover { background: #047857 !important; }
			.hj-gl-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
			.hj-gl-badge--muted { background: #f1f5f9; color: #64748b; border-color: #cbd5e1; }
			.hj-gl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
			.hj-gl-table th, .hj-gl-table td { padding: 10px 10px; border-bottom: 1px solid #e2e8f0; text-align: right; vertical-align: top; }
			.hj-gl-table th { background: #f8fafc; color: #06265C; font-weight: 700; }
			.hj-gl-progress-wrap { background: #e2e8f0; border-radius: 999px; height: 22px; overflow: hidden; margin: 12px 0; display: none; }
			.hj-gl-progress-bar { height: 100%; width: 0%; background: linear-gradient(90deg, #059669, #10b981); color: #fff; font-size: 12px; font-weight: 700; text-align: center; line-height: 22px; transition: width .2s ease; }
			.hj-gl-sample-pill { display: inline-block; background: rgba(16,185,129,0.16); color: #065f46; border-bottom: 1.5px dashed #059669; padding: 1px 7px; border-radius: 5px; font-weight: 600; }
		</style>

		<div class="hj-gl-hero">
			<div>
				<h1>واژه‌نامه‌ی تخصصی و هایلایت سبز خودکار مقالات</h1>
				<p>پشتیبانی بدون افت سرعت از <strong>۵,۰۰۰ تا ۵۰,۰۰۰+ واژه</strong> · هایلایت <span class="hj-gl-sample-pill" style="background:rgba(110,231,183,0.22);color:#ecfdf5;border-bottom-color:#6ee7b7;">سبز ظریف</span> درون مقالات با باکس تعریف ریسپانسیو · صفحه‌ی مستقل دسته‌بندی موضوعی</p>
			</div>
			<div class="hj-gl-kpis">
				<div class="hj-gl-kpi">
					<strong><?php echo esc_html( $fa( number_format_i18n( $stats['published'] ) ) ); ?></strong>
					<span>واژه‌ی فعال</span>
				</div>
				<div class="hj-gl-kpi">
					<strong><?php echo esc_html( $fa( number_format_i18n( $stats['highlighted'] ) ) ); ?></strong>
					<span>هایلایت خودکار</span>
				</div>
				<div class="hj-gl-kpi">
					<strong><?php echo esc_html( $fa( $stats['groups_total'] ) ); ?></strong>
					<span>موضوع / دسته</span>
				</div>
				<div class="hj-gl-kpi">
					<a href="<?php echo esc_url( hj_archive_url( 'glossary' ) ); ?>" target="_blank" rel="noopener" style="color:#a7f3d0;text-decoration:none;font-weight:700;display:block;padding-top:6px;">مشاهده‌ی صفحه ↗</a>
					<span>/glossary/</span>
				</div>
			</div>
		</div>

		<?php if ( $flash ) : ?>
			<div class="notice notice-<?php echo 'success' === $flash['type'] ? 'success' : 'error'; ?> is-dismissible">
				<p><strong><?php echo esc_html( $flash['text'] ); ?></strong></p>
			</div>
		<?php endif; ?>

		<nav class="hj-gl-tabs" aria-label="بخش‌های مدیریت واژه‌نامه">
			<a class="hj-gl-tab <?php echo 'terms' === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>">📚 بانک واژگان و افزودن سریع</a>
			<a class="hj-gl-tab <?php echo 'import' === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( hj_glossary_admin_url( 'import' ) ); ?>">🚀 ورود انبوه ۵۰۰۰+ واژه (اکسل / CSV / کپی-پیست)</a>
			<a class="hj-gl-tab <?php echo 'groups' === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( hj_glossary_admin_url( 'groups' ) ); ?>">🗂️ دسته‌بندی موضوعات (<?php echo esc_html( $fa( count( $groups ) ) ); ?>)</a>
			<a class="hj-gl-tab <?php echo 'settings' === $active_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( hj_glossary_admin_url( 'settings' ) ); ?>">⚙️ تنظیمات هایلایت سبز و باکس تعریف</a>
		</nav>

		<?php if ( 'terms' === $active_tab ) : ?>
			<?php
			$edit_row = null;
			if ( ! empty( $_GET['edit_term'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				global $wpdb;
				$edit_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . hj_glossary_table_name() . '` WHERE id = %d LIMIT 1', (int) $_GET['edit_term'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			}
			$q_search = isset( $_GET['s_term'] ) ? sanitize_text_field( wp_unslash( $_GET['s_term'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q_group  = isset( $_GET['f_group'] ) ? (int) $_GET['f_group'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q_letter = isset( $_GET['f_letter'] ) ? sanitize_text_field( wp_unslash( $_GET['f_letter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q_page   = isset( $_GET['tpage'] ) ? max( 1, (int) $_GET['tpage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$list = hj_glossary_query_terms(
				array(
					'status'   => 'all',
					'search'   => $q_search,
					'group_id' => $q_group,
					'letter'   => $q_letter,
					'page'     => $q_page,
					'per_page' => 50,
				)
			);
			?>

			<div class="hj-gl-card">
				<h2><?php echo $edit_row ? '✏️ ویرایش واژه: ' . esc_html( $edit_row['term'] ) : '➕ افزودن سریع واژه‌ی جدید'; ?></h2>
				<form method="post" action="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>">
					<?php wp_nonce_field( 'hj_glossary_form_nonce', 'hj_glossary_nonce' ); ?>
					<input type="hidden" name="hj_glossary_action" value="save_term" />
					<input type="hidden" name="term_id" value="<?php echo $edit_row ? (int) $edit_row['id'] : 0; ?>" />

					<div class="hj-gl-grid-2">
						<div class="hj-gl-field">
							<label for="hj-term-input">نام واژه / اصطلاح تخصصی *</label>
							<input type="text" id="hj-term-input" name="term" required placeholder="مثال: پالس اکسیمتر" value="<?php echo $edit_row ? esc_attr( $edit_row['term'] ) : ''; ?>" />
						</div>
						<div class="hj-gl-field">
							<label for="hj-group-select">دسته‌بندی موضوعی</label>
							<div style="display:flex;gap:8px;">
								<select id="hj-group-select" name="group_id" style="flex:1;">
									<option value="0">— انتخاب موضوع —</option>
									<?php foreach ( $groups as $g ) : ?>
										<option value="<?php echo (int) $g['term_id']; ?>" <?php selected( $edit_row ? (int) $edit_row['group_id'] : 0, (int) $g['term_id'] ); ?>>
											<?php echo esc_html( $g['name'] . ' (' . $fa( $g['count'] ) . ')' ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<input type="text" name="new_group_name" placeholder="یا نام دسته‌ی جدید..." style="flex:1;" />
							</div>
						</div>
					</div>

					<div class="hj-gl-field">
						<label for="hj-def-input">تعریف کوتاه و یک‌خطی (نمایش در کادر شناور روی واژه و صفحه‌ی واژه‌نامه) *</label>
						<textarea id="hj-def-input" name="definition" rows="2" required placeholder="مثال: حسگر گیره‌ای بدون درد روی نوک انگشت که درصد اکسیژن خون و ضربان قلب را لحظه‌ای نشان می‌دهد."><?php echo $edit_row ? esc_textarea( $edit_row['definition'] ) : ''; ?></textarea>
					</div>

					<div class="hj-gl-grid-2">
						<div class="hj-gl-field">
							<label for="hj-en-input">معادل انگلیسی (اختیاری)</label>
							<input type="text" id="hj-en-input" name="en_term" dir="ltr" placeholder="e.g. Pulse Oximeter" value="<?php echo $edit_row ? esc_attr( $edit_row['en_term'] ) : ''; ?>" />
						</div>
						<div class="hj-gl-field">
							<label for="hj-aliases-input">مترادف‌ها یا املاهای دیگر (با ویرگول جدا کنید — اختیاری)</label>
							<input type="text" id="hj-aliases-input" name="aliases" placeholder="مثال: پالس‌اکسیمتر، اکسیمتر نبضی" value="<?php echo $edit_row ? esc_attr( $edit_row['aliases'] ) : ''; ?>" />
						</div>
					</div>

					<p style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:8px 0 0;">
						<label style="font-weight:600;cursor:pointer;">
							<input type="checkbox" name="highlight_enabled" value="1" <?php checked( $edit_row ? (int) $edit_row['highlight_enabled'] : 1, 1 ); ?> />
							فعال بودن هایلایت سبز خودکار این واژه در مقالات سایت
						</label>
						<span>
							<?php if ( $edit_row ) : ?>
								<a href="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>" class="button">انصراف از ویرایش</a>
							<?php endif; ?>
							<button type="submit" class="button button-primary hj-gl-btn-green"><?php echo $edit_row ? 'ذخیره‌ی تغییرات واژه' : 'ثبت واژه در بانک'; ?></button>
						</span>
					</p>
				</form>
			</div>

			<div class="hj-gl-card">
				<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
					<h2 style="margin:0;border:0;padding:0;">فهرست واژگان ثبت‌شده (<?php echo esc_html( $fa( number_format_i18n( $list['total'] ) ) ); ?> مورد)</h2>
					<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
						<input type="hidden" name="post_type" value="glossary" />
						<input type="hidden" name="page" value="hj-glossary-manager" />
						<input type="search" name="s_term" value="<?php echo esc_attr( $q_search ); ?>" placeholder="جستجوی واژه یا معنی..." style="min-width:200px;" />
						<select name="f_group">
							<option value="0">همه‌ی موضوعات</option>
							<?php foreach ( $groups as $g ) : ?>
								<option value="<?php echo (int) $g['term_id']; ?>" <?php selected( $q_group, (int) $g['term_id'] ); ?>><?php echo esc_html( $g['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button">فیلتر</button>
						<?php if ( '' !== $q_search || $q_group > 0 || '' !== $q_letter ) : ?>
							<a href="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>" class="button">پاک‌سازی</a>
						<?php endif; ?>
					</form>
				</div>

				<?php if ( empty( $list['items'] ) ) : ?>
					<div style="text-align:center;padding:34px 16px;background:#f8fafc;border-radius:10px;border:1px dashed #cbd5e1;">
						<p style="font-size:15px;color:#334155;margin:0 0 12px;">هنوز واژه‌ای در این بخش ثبت نشده است. واژگان آماده را همین حالا یک‌جا وارد کنید:</p>
						<a href="<?php echo esc_url( hj_glossary_admin_url( 'import' ) ); ?>" class="button button-primary hj-gl-btn-green">🚀 رفتن به ورود انبوه ۵۰۰۰+ واژه</a>
					</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>">
						<?php wp_nonce_field( 'hj_glossary_form_nonce', 'hj_glossary_nonce' ); ?>
						<input type="hidden" name="hj_glossary_action" value="bulk_terms" />

						<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;">
							<select name="bulk_op">
								<option value="">— عملیات گروهی —</option>
								<option value="enable_hl">فعال‌سازی هایلایت سبز</option>
								<option value="disable_hl">غیرفعال‌سازی هایلایت سبز</option>
								<option value="move_group">انتقال به موضوع انتخابی روبه‌رو ←</option>
								<option value="delete">حذف واژگان انتخاب‌شده</option>
							</select>
							<select name="bulk_target_group">
								<option value="0">— موضوع مقصد (برای انتقال) —</option>
								<?php foreach ( $groups as $g ) : ?>
									<option value="<?php echo (int) $g['term_id']; ?>"><?php echo esc_html( $g['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="button" onclick="return confirm('عملیات گروهی روی موارد انتخاب‌شده انجام شود؟');">اجرا</button>
						</div>

						<div style="overflow-x:auto;">
							<table class="hj-gl-table">
								<thead>
									<tr>
										<th style="width:28px;"><input type="checkbox" onclick="document.querySelectorAll('.hj-gl-row-cb').forEach(c=>c.checked=this.checked);" /></th>
										<th style="width:180px;">واژه</th>
										<th>تعریف یک‌خطی</th>
										<th style="width:150px;">موضوع</th>
										<th style="width:95px;">هایلایت</th>
										<th style="width:110px;">عملیات</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $list['items'] as $item ) : ?>
										<tr>
											<td><input type="checkbox" class="hj-gl-row-cb" name="term_ids[]" value="<?php echo (int) $item['id']; ?>" /></td>
											<td>
												<strong style="color:#06265C;font-size:13.5px;"><?php echo esc_html( $item['term'] ); ?></strong>
												<?php if ( '' !== $item['en_term'] ) : ?>
													<div dir="ltr" style="font-size:11.5px;color:#64748b;text-align:right;"><?php echo esc_html( $item['en_term'] ); ?></div>
												<?php endif; ?>
											</td>
											<td style="color:#334155;line-height:1.7;"><?php echo esc_html( $item['definition'] ); ?></td>
											<td>
												<?php if ( '' !== $item['group_name'] ) : ?>
													<span class="hj-gl-badge"><?php echo esc_html( $item['group_name'] ); ?></span>
												<?php else : ?>
													<span class="hj-gl-badge hj-gl-badge--muted">بدون دسته</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $item['highlight_enabled'] ) : ?>
													<span class="hj-gl-badge">سبز فعال</span>
												<?php else : ?>
													<span class="hj-gl-badge hj-gl-badge--muted">غیرفعال</span>
												<?php endif; ?>
											</td>
											<td>
												<a href="<?php echo esc_url( add_query_arg( 'edit_term', (int) $item['id'], hj_glossary_admin_url( 'terms' ) ) ); ?>">ویرایش</a>
												|
												<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'del_term', (int) $item['id'], hj_glossary_admin_url( 'terms' ) ), 'hj_del_term_' . (int) $item['id'] ) ); ?>" style="color:#dc2626;" onclick="return confirm('این واژه حذف شود؟');">حذف</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</form>

					<?php if ( $list['pages'] > 1 ) : ?>
						<div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:center;">
							<?php
							for ( $p = 1; $p <= $list['pages']; ++$p ) :
								if ( $p > 3 && $p < $list['pages'] - 2 && abs( $p - $list['page'] ) > 2 ) {
									if ( abs( $p - $list['page'] ) === 3 ) {
										echo '<span style="padding:4px 6px;color:#64748b;">…</span>';
									}
									continue;
								}
								$p_url = add_query_arg(
									array(
										's_term'  => $q_search,
										'f_group' => $q_group,
										'tpage'   => $p,
									),
									hj_glossary_admin_url( 'terms' )
								);
								?>
								<a class="button <?php echo $p === $list['page'] ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $p_url ); ?>"><?php echo esc_html( $fa( $p ) ); ?></a>
							<?php endfor; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

		<?php elseif ( 'import' === $active_tab ) : ?>
			<div class="hj-gl-card">
				<h2>🚀 ورود انبوه ۵,۰۰۰+ واژه و تعریف کوتاه (بدون محدودیت حجم و بدون تایم‌اوت)</h2>
				<p style="color:#475569;line-height:1.8;margin-top:0;">
					فایل آماده‌ی واژگان خود را با هر فرمتی که دارید (<strong>فایل اکسل <code>.xlsx</code></strong>، <strong>فایل <code>.csv</code> / <code>.tsv</code></strong>، <strong>فایل <code>.json</code></strong> یا <strong>کپی و پیست مستقیم از اکسل/ورد</strong>) بارگذاری کنید.
					سامانه خودکار ستون‌ها را شناسایی کرده، پیش‌نمایش نشان می‌دهد و در دسته‌های ۴۰۰تایی با نوار پیشرفت همه‌ی ۵۰۰۰+ واژه و دسته‌بندی موضوعی آن‌ها را در چند ثانیه ثبت می‌کند.
				</p>

				<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;padding:10px 14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;align-items:center;justify-content:space-between;">
					<span style="font-size:13px;color:#334155;">📥 <strong>قالب‌های نمونه و پشتیبان:</strong> (داشتن فقط ۲ ستون «واژه» و «معنی» هم کافی است؛ ستون «موضوع»، «انگلیسی» و «مترادف» اختیاری‌اند)</span>
					<span style="display:flex;flex-wrap:wrap;gap:6px;">
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'hj_glossary_download' => 'sample_csv', '_wpnonce' => $dl_nonce ), admin_url( 'admin.php' ) ) ); ?>">دانلود نمونه‌ی اکسل (CSV)</a>
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'hj_glossary_download' => 'sample_json', '_wpnonce' => $dl_nonce ), admin_url( 'admin.php' ) ) ); ?>">دانلود نمونه‌ی JSON</a>
						<?php if ( $stats['published'] > 0 ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'hj_glossary_download' => 'export_csv', '_wpnonce' => $dl_nonce ), admin_url( 'admin.php' ) ) ); ?>">خروجی پشتیبان کل واژگان (CSV)</a>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'hj_glossary_download' => 'export_json', '_wpnonce' => $dl_nonce ), admin_url( 'admin.php' ) ) ); ?>">خروجی پشتیبان (JSON)</a>
						<?php endif; ?>
					</span>
				</div>

				<div class="hj-gl-grid-2">
					<div class="hj-gl-field" style="background:#f0fdf4;border:1px dashed #34d399;border-radius:10px;padding:16px;">
						<label for="hj-imp-file" style="color:#065f46;font-size:14px;">روش ۱: انتخاب فایل واژگان (<code>.xlsx</code>، <code>.csv</code>، <code>.tsv</code>، <code>.json</code>، <code>.txt</code>)</label>
						<input type="file" id="hj-imp-file" accept=".xlsx,.csv,.tsv,.txt,.json" style="margin-top:8px;" />
						<p style="font-size:12px;color:#047857;margin:8px 0 0;">پشتیبانی کامل از فایل‌های خروجی اکسل فارسی (UTF-8 و Windows-1256) و فایل مستقیم <code>.xlsx</code>.</p>
					</div>

					<div class="hj-gl-field">
						<label for="hj-imp-paste">روش ۲: یا کپی و پیست مستقیم از اکسل / ورد / نوت‌پد</label>
						<textarea id="hj-imp-paste" rows="4" placeholder="پالس اکسیمتر	حسگر سنجش اکسیژن خون روی انگشت	ابزار و دستگاه&#10;بیهوشی عمومی : خواب دارویی کنترل‌شده بدون حس درد"></textarea>
					</div>
				</div>

				<p style="margin:6px 0 0;">
					<button type="button" id="hj-btn-stage" class="button button-primary hj-gl-btn-green">۱. بررسی فایل و نمایش پیش‌نمایش ستون‌ها</button>
					<span id="hj-stage-msg" style="margin-right:10px;font-weight:600;color:#06265C;"></span>
				</p>

				<div id="hj-mapping-box" style="display:none;margin-top:20px;padding-top:18px;border-top:2px solid #e2e8f0;">
					<h3 style="margin-top:0;color:#06265C;">۲. تطبیق ستون‌ها و شروع ثبت خودکار (<span id="hj-total-detected">0</span> ردیف شناسایی شد)</h3>

					<div class="hj-gl-grid-2" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
						<div class="hj-gl-field">
							<label>ستون «نام واژه» *</label>
							<select id="hj-col-term"></select>
						</div>
						<div class="hj-gl-field">
							<label>ستون «تعریف کوتاه / معنی» *</label>
							<select id="hj-col-def"></select>
						</div>
						<div class="hj-gl-field">
							<label>ستون «دسته‌بندی موضوعی» (اختیاری)</label>
							<select id="hj-col-grp"></select>
						</div>
						<div class="hj-gl-field">
							<label>ستون «معادل انگلیسی» (اختیاری)</label>
							<select id="hj-col-en"></select>
						</div>
						<div class="hj-gl-field">
							<label>ستون «مترادف‌ها» (اختیاری)</label>
							<select id="hj-col-ali"></select>
						</div>
					</div>

					<div class="hj-gl-grid-2" style="margin-top:6px;">
						<div class="hj-gl-field">
							<label>موضوع پیش‌فرض (برای ردیف‌هایی که ستون موضوع ندارند)</label>
							<select id="hj-imp-default-group">
								<option value="">— بدون دسته‌ی پیش‌فرض —</option>
								<?php foreach ( $groups as $g ) : ?>
									<option value="<?php echo esc_attr( $g['name'] ); ?>"><?php echo esc_html( $g['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="hj-gl-field">
							<label>برخورد با واژگان تکراری</label>
							<select id="hj-imp-dup-mode">
								<option value="update">به‌روزرسانی تعریف و دسته‌بندی واژگان تکراری (پیشنهادی)</option>
								<option value="skip">رد کردن واژگان تکراری و حفظ قبلی</option>
							</select>
						</div>
					</div>

					<p style="margin:4px 0 12px;">
						<label style="font-weight:600;cursor:pointer;">
							<input type="checkbox" id="hj-imp-has-header" />
							ردیف اول فایل، عنوان ستون‌ها (Header) است و نباید به‌عنوان واژه ثبت شود
						</label>
					</p>

					<div style="overflow-x:auto;margin-bottom:14px;">
						<table class="hj-gl-table" id="hj-preview-table"></table>
					</div>

					<button type="button" id="hj-btn-run-import" class="button button-primary hj-gl-btn-green" style="font-size:14px;padding:6px 22px !important;">🚀 شروع ورود انبوه همه‌ی واژگان</button>

					<div class="hj-gl-progress-wrap" id="hj-imp-progress-wrap">
						<div class="hj-gl-progress-bar" id="hj-imp-progress-bar">0%</div>
					</div>
					<div id="hj-imp-result" style="font-size:13.5px;line-height:1.8;margin-top:8px;"></div>
				</div>
			</div>

			<script>
			(function(){
				const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				const nonce   = <?php echo wp_json_encode( $ajax_nonce ); ?>;
				let stagedToken = '';
				let stagedTotal = 0;

				const btnStage = document.getElementById('hj-btn-stage');
				const msgStage = document.getElementById('hj-stage-msg');
				const mapBox   = document.getElementById('hj-mapping-box');
				const btnRun   = document.getElementById('hj-btn-run-import');

				function fillSelect(id, maxCols, selectedVal, allowNone) {
					const sel = document.getElementById(id);
					sel.innerHTML = '';
					if (allowNone) {
						const opt = document.createElement('option');
						opt.value = '-1';
						opt.textContent = '— ندارد —';
						sel.appendChild(opt);
					}
					for (let c = 0; c < maxCols; c++) {
						const opt = document.createElement('option');
						opt.value = String(c);
						opt.textContent = 'ستون ' + (c + 1);
						if (c === selectedVal) opt.selected = true;
						sel.appendChild(opt);
					}
					if (allowNone && selectedVal < 0) sel.value = '-1';
				}

				btnStage.addEventListener('click', function(){
					const fileInput = document.getElementById('hj-imp-file');
					const pasteInput = document.getElementById('hj-imp-paste');
					const fd = new FormData();
					fd.append('action', 'hj_glossary_stage_import');
					fd.append('nonce', nonce);

					if (fileInput.files && fileInput.files[0]) {
						fd.append('glossary_file', fileInput.files[0]);
					} else if (pasteInput.value.trim() !== '') {
						fd.append('pasted_text', pasteInput.value);
					} else {
						msgStage.style.color = '#dc2626';
						msgStage.textContent = 'لطفاً یک فایل انتخاب کنید یا متن واژگان را جای‌گذاری نمایید.';
						return;
					}

					btnStage.disabled = true;
					msgStage.style.color = '#06265C';
					msgStage.textContent = 'در حال تحلیل و آماده‌سازی ردیف‌ها...';

					fetch(ajaxUrl, { method: 'POST', body: fd })
						.then(r => r.json())
						.then(res => {
							btnStage.disabled = false;
							if (!res || !res.success) {
								msgStage.style.color = '#dc2626';
								msgStage.textContent = (res && res.data && res.data.message) ? res.data.message : 'خطا در خواندن ورودی.';
								return;
							}
							const d = res.data;
							stagedToken = d.token;
							stagedTotal = d.total_rows;
							msgStage.style.color = '#059669';
							msgStage.textContent = '✓ فایل آماده شد؛ ستون‌ها را بررسی و دکمه‌ی شروع را بزنید.';
							document.getElementById('hj-total-detected').textContent = d.total_rows.toLocaleString('fa-IR');
							document.getElementById('hj-imp-has-header').checked = !!d.has_header;

							const maxC = Math.max(2, d.max_cols || 2);
							fillSelect('hj-col-term', maxC, d.cols.term, false);
							fillSelect('hj-col-def',  maxC, d.cols.definition, false);
							fillSelect('hj-col-grp',  maxC, d.cols.group, true);
							fillSelect('hj-col-en',   maxC, d.cols.en_term, true);
							fillSelect('hj-col-ali',  maxC, d.cols.aliases, true);

							// Render preview table
							const tbl = document.getElementById('hj-preview-table');
							let html = '<thead><tr>';
							for (let c = 0; c < maxC; c++) {
								html += '<th>ستون ' + (c + 1) + '</th>';
							}
							html += '</tr></thead><tbody>';
							(d.preview || []).forEach((row, rIdx) => {
								html += '<tr' + (rIdx === 0 && d.has_header ? ' style="background:#fef9c3;font-weight:700;"' : '') + '>';
								for (let c = 0; c < maxC; c++) {
									const cell = (row[c] !== undefined && row[c] !== null) ? String(row[c]) : '';
									const esc = cell.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
									html += '<td>' + (esc.length > 90 ? esc.slice(0, 90) + '…' : esc) + '</td>';
								}
								html += '</tr>';
							});
							html += '</tbody>';
							tbl.innerHTML = html;
							mapBox.style.display = 'block';
						})
						.catch(() => {
							btnStage.disabled = false;
							msgStage.style.color = '#dc2626';
							msgStage.textContent = 'خطای ارتباط شبکه هنگام بارگذاری فایل.';
						});
				});

				btnRun.addEventListener('click', function(){
					if (!stagedToken) return;
					btnRun.disabled = true;
					const pWrap = document.getElementById('hj-imp-progress-wrap');
					const pBar  = document.getElementById('hj-imp-progress-bar');
					const resEl = document.getElementById('hj-imp-result');
					pWrap.style.display = 'block';
					pBar.style.width = '2%';
					pBar.textContent = '0%';

					let accInserted = 0, accUpdated = 0, accSkipped = 0, accInvalid = 0;

					function runChunk(offset) {
						const fd = new FormData();
						fd.append('action', 'hj_glossary_process_chunk');
						fd.append('nonce', nonce);
						fd.append('token', stagedToken);
						fd.append('offset', String(offset));
						fd.append('batch_size', '400');
						fd.append('has_header', document.getElementById('hj-imp-has-header').checked ? '1' : '');
						fd.append('duplicate_mode', document.getElementById('hj-imp-dup-mode').value);
						fd.append('default_group', document.getElementById('hj-imp-default-group').value);
						fd.append('col_term', document.getElementById('hj-col-term').value);
						fd.append('col_def',  document.getElementById('hj-col-def').value);
						fd.append('col_grp',  document.getElementById('hj-col-grp').value);
						fd.append('col_en',   document.getElementById('hj-col-en').value);
						fd.append('col_ali',  document.getElementById('hj-col-ali').value);

						fetch(ajaxUrl, { method: 'POST', body: fd })
							.then(r => r.json())
							.then(res => {
								if (!res || !res.success) {
									btnRun.disabled = false;
									resEl.innerHTML = '<span style="color:#dc2626;font-weight:700;">خطا: ' + ((res && res.data && res.data.message) ? res.data.message : 'توقف غیرمنتظره') + '</span>';
									return;
								}
								const d = res.data;
								accInserted += d.inserted;
								accUpdated  += d.updated;
								accSkipped  += d.skipped;
								accInvalid  += d.invalid;

								const pct = Math.min(100, Math.round((d.next_offset / Math.max(1, d.total_rows)) * 100));
								pBar.style.width = pct + '%';
								pBar.textContent = pct.toLocaleString('fa-IR') + '٪';

								resEl.innerHTML = 'در حال پردازش: <strong>' + d.next_offset.toLocaleString('fa-IR') + '</strong> از <strong>' + d.total_rows.toLocaleString('fa-IR') + '</strong> — افزوده‌شده: <strong style="color:#059669;">' + accInserted.toLocaleString('fa-IR') + '</strong> · به‌روزشده: <strong>' + accUpdated.toLocaleString('fa-IR') + '</strong> · تکراری ردشده: ' + accSkipped.toLocaleString('fa-IR');

								if (!d.done) {
									runChunk(d.next_offset);
								} else {
									btnRun.disabled = false;
									resEl.innerHTML = '<div style="padding:12px 16px;background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;color:#065f46;margin-top:8px;">' +
										'<strong>🎉 عملیات ورود انبوه با موفقیت کامل شد و ایندکس هایلایت خودکار مقالات ساخته شد!</strong><br>' +
										'واژگان جدید ثبت‌شده: <strong>' + accInserted.toLocaleString('fa-IR') + '</strong> · به‌روزرسانی‌شده: <strong>' + accUpdated.toLocaleString('fa-IR') + '</strong> · ردشده: ' + accSkipped.toLocaleString('fa-IR') +
										(d.index_stats ? ' · کل کلیدواژه‌های فعال در موتور هایلایت: <strong>' + d.index_stats.phrase_count.toLocaleString('fa-IR') + '</strong>' : '') +
										' — <a href="<?php echo esc_url( hj_glossary_admin_url( 'terms' ) ); ?>" style="font-weight:700;color:#047857;">مشاهده‌ی بانک واژگان ←</a>' +
										'</div>';
								}
							})
							.catch(() => {
								btnRun.disabled = false;
								resEl.innerHTML = '<span style="color:#dc2626;font-weight:700;">خطای شبکه در حین ارسال بسته. دوباره تلاش کنید.</span>';
							});
					}

					runChunk(0);
				});
			})();
			</script>

		<?php elseif ( 'groups' === $active_tab ) : ?>
			<div class="hj-gl-grid-2">
				<div class="hj-gl-card">
					<h2>➕ افزودن موضوع / دسته‌بندی جدید واژگان</h2>
					<p style="font-size:13px;color:#475569;">توجه: هنگام «ورود انبوه واژگان» هم اگر ستون موضوع در فایل اکسل/CSV شما وجود داشته باشد، دسته‌بندی‌های جدید به‌طور خودکار ساخته می‌شوند.</p>
					<form method="post" action="<?php echo esc_url( hj_glossary_admin_url( 'groups' ) ); ?>">
						<?php wp_nonce_field( 'hj_glossary_form_nonce', 'hj_glossary_nonce' ); ?>
						<input type="hidden" name="hj_glossary_action" value="create_group" />
						<div class="hj-gl-field">
							<label>نام دسته‌بندی موضوعی *</label>
							<input type="text" name="group_name" required placeholder="مثال: داروها و سرم‌های اتاق عمل" />
						</div>
						<div class="hj-gl-field">
							<label>نامک لاتین URL (اختیاری)</label>
							<input type="text" name="group_slug" dir="ltr" placeholder="e.g. or-medications" />
						</div>
						<div class="hj-gl-field">
							<label>توضیح کوتاه موضوع (اختیاری)</label>
							<textarea name="group_desc" rows="2" placeholder="توضیح یک‌خطی برای بالای صفحه‌ی این دسته..."></textarea>
						</div>
						<button type="submit" class="button button-primary hj-gl-btn-green">ایجاد دسته‌بندی موضوعی</button>
						<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=glossary_group&post_type=glossary' ) ); ?>" class="button">ویرایش پیشرفته در تاکسونومی وردپرس</a>
					</form>
				</div>

				<div class="hj-gl-card">
					<h2>🗂️ دسته‌بندی‌های موضوعی موجود</h2>
					<table class="hj-gl-table">
						<thead>
							<tr>
								<th>نام موضوع</th>
								<th>تعداد واژه</th>
								<th>مشاهده</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $groups as $g ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $g['name'] ); ?></strong></td>
									<td><span class="hj-gl-badge"><?php echo esc_html( $fa( number_format_i18n( $g['count'] ) ) ); ?> واژه</span></td>
									<td><a href="<?php echo esc_url( $g['url'] ); ?>" target="_blank" rel="noopener">صفحه‌ی موضوع ↗</a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

		<?php elseif ( 'settings' === $active_tab ) : ?>
			<div class="hj-gl-card">
				<h2>⚙️ تنظیمات هایلایت سبز خودکار و کادر نمایش معنی در مقالات</h2>
				<form method="post" action="<?php echo esc_url( hj_glossary_admin_url( 'settings' ) ); ?>">
					<?php wp_nonce_field( 'hj_glossary_form_nonce', 'hj_glossary_nonce' ); ?>
					<input type="hidden" name="hj_glossary_action" value="save_settings" />

					<p style="margin-bottom:16px;">
						<label style="font-size:14px;font-weight:700;cursor:pointer;color:#065f46;">
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
							فعال بودن هایلایت سبز خودکار واژگان و کادر شناور تعریف در متن مقالات
						</label>
					</p>

					<div class="hj-gl-grid-2">
						<div class="hj-gl-field">
							<label>ظاهر هایلایت سبز در متن مقالات</label>
							<select name="highlight_style">
								<option value="soft-green" <?php selected( $settings['highlight_style'], 'soft-green' ); ?>>هایلایت سبز ملایم + خط‌چین ظریف زیر واژه (پیشنهادی)</option>
								<option value="vivid-green" <?php selected( $settings['highlight_style'], 'vivid-green' ); ?>>هایلایت سبز مشخص‌تر</option>
								<option value="underline-green" <?php selected( $settings['highlight_style'], 'underline-green' ); ?>>فقط خط‌چین سبز ظریف (هایلایت سبز هنگام رفتن موس)</option>
							</select>
						</div>

						<div class="hj-gl-field">
							<label>تعداد دفعات هایلایت هر واژه‌ی یکسان در یک مقاله</label>
							<select name="max_per_term">
								<option value="1" <?php selected( (int) $settings['max_per_term'], 1 ); ?>>فقط اولین بار در هر مقاله (تمیزترین حالت مطالعه — پیشنهادی)</option>
								<option value="2" <?php selected( (int) $settings['max_per_term'], 2 ); ?>>حداکثر ۲ بار در هر مقاله</option>
								<option value="3" <?php selected( (int) $settings['max_per_term'], 3 ); ?>>حداکثر ۳ بار در هر مقاله</option>
								<option value="0" <?php selected( (int) $settings['max_per_term'], 0 ); ?>>همه‌ی تکرارهای واژه در مقاله</option>
							</select>
						</div>

						<div class="hj-gl-field">
							<label>سقف کل واژگان هایلایت‌شده در هر مقاله</label>
							<input type="number" name="max_total_per_post" min="5" max="300" value="<?php echo (int) $settings['max_total_per_post']; ?>" />
						</div>

						<div class="hj-gl-field">
							<label>تعداد واژه در هر صفحه‌ی `/glossary/`</label>
							<input type="number" name="per_page" min="20" max="200" value="<?php echo (int) $settings['per_page']; ?>" />
						</div>
					</div>

					<div class="hj-gl-field" style="margin-top:8px;">
						<label>بخش‌هایی که واژگان در متن آن‌ها هایلایت شوند:</label>
						<?php
						$cpt_labels = array(
							'patient_guide'   => 'راهنمای بیمار',
							'or_environment'  => 'اتاق عمل، تیم و تجهیزات',
							'surgery'         => 'جراحی‌ها',
							'student_content' => 'ویژه‌ی دانشجویان',
							'post'            => 'نوشته‌های وبلاگ',
							'page'            => 'برگه‌های ثابت',
						);
						foreach ( $cpt_labels as $cpt_slug => $cpt_label ) :
							?>
							<label style="display:inline-block;margin-left:18px;font-weight:500;cursor:pointer;">
								<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $cpt_slug ); ?>" <?php checked( in_array( $cpt_slug, (array) $settings['post_types'], true ) ); ?> />
								<?php echo esc_html( $cpt_label ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<p style="margin:12px 0;">
						<label style="display:block;margin-bottom:6px;cursor:pointer;">
							<input type="checkbox" name="match_plurals" value="1" <?php checked( ! empty( $settings['match_plurals'] ) ); ?> />
							شناسایی هوشمند جمع فارسی واژگان (مانند «بخیه‌ها»، «کاتترها»، «مانیتورها»)
						</label>
						<label style="display:block;margin-bottom:6px;cursor:pointer;">
							<input type="checkbox" name="match_english" value="1" <?php checked( ! empty( $settings['match_english'] ) ); ?> />
							شناسایی معادل انگلیسی واژگان در صورت آمدن در متن مقاله (مثال: <code>NPO</code>)
						</label>
						<label style="display:block;cursor:pointer;">
							<input type="checkbox" name="show_glossary_link" value="1" <?php checked( ! empty( $settings['show_glossary_link'] ) ); ?> />
							نمایش لینک ظریف «مشاهده در واژه‌نامه ←» در پایین باکس شناور تعریف
						</label>
					</p>

					<button type="submit" class="button button-primary hj-gl-btn-green">ذخیره‌ی تنظیمات</button>
				</form>
			</div>

			<div class="hj-gl-card">
				<h2>⚡ وضعیت کش ایندکس سریع واژگان</h2>
				<p style="color:#475569;margin-top:0;">
					تعداد واژگان کامپایل‌شده در ایندکس حافظه:
					<strong><?php echo esc_html( $fa( number_format_i18n( (int) ( $index_meta['term_count'] ?? 0 ) ) ) ); ?></strong> واژه
					(<strong><?php echo esc_html( $fa( number_format_i18n( (int) ( $index_meta['phrase_count'] ?? 0 ) ) ) ); ?></strong> کلیدواژه و مترادف).
					<?php if ( ! empty( $index_meta['built_at'] ) && function_exists( 'hj_format_jalali_timestamp' ) ) : ?>
						آخرین ساخت: <strong><?php echo esc_html( hj_format_jalali_timestamp( (int) $index_meta['built_at'], 'datetime' ) ); ?></strong>.
					<?php endif; ?>
				</p>
				<form method="post" action="<?php echo esc_url( hj_glossary_admin_url( 'settings' ) ); ?>">
					<?php wp_nonce_field( 'hj_glossary_form_nonce', 'hj_glossary_nonce' ); ?>
					<input type="hidden" name="hj_glossary_action" value="rebuild_index" />
					<button type="submit" class="button">🔄 بازسازی دستی کش ایندکس واژگان</button>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

<?php
/**
 * Smart Multi-Group Article & Glossary Package Importer (v2.0).
 *
 * Supports all 5 content groups (patient_guide, or_environment, surgery,
 * student_content, glossary) with:
 *   - Submenu entry under every CPT + central Hamyar Jarahi Importer Hub
 *   - Explicit Draft / Publish / Manifest status controls + saved defaults
 *   - Duplicate detection with 3 modes (warn & skip, smart update with full
 *     field-by-field diff report, or create new draft copy)
 *   - Dry-run preview & inspection mode without touching the database
 *   - Comprehensive Image & SEO Deficiency Inspector (dimensions, WebP, size,
 *     alt text, meta description length, focus keyword, FAQ schema, headings,
 *     internal links, emergency notice, and quality score 0-100)
 *   - Multi-taxonomy support (including surgery_specialty + surgery_stage)
 *   - Import history log
 *
 * @package Lax_Theme_Child
 * @since   0.8.0
 * @version 0.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HJ_IMPORTER_CAP' ) ) {
	define( 'HJ_IMPORTER_CAP', 'manage_options' );
}

/**
 * Safe UTF-8 character counter without hard dependency on ext-mbstring.
 *
 * @param string $text Input UTF-8 string.
 * @return int
 */
function hj_importer_utf8_len( string $text ): int {
	if ( '' === $text ) {
		return 0;
	}
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) mb_strlen( $text, 'UTF-8' );
	}
	$count = preg_match_all( '/./us', $text );
	return false === $count ? strlen( $text ) : (int) $count;
}

/**
 * Safe UTF-8 substring preview.
 *
 * @param string $text  Input UTF-8 string.
 * @param int    $limit Max characters.
 * @return string
 */
function hj_importer_utf8_preview( string $text, int $limit = 90 ): string {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( '' === $text ) {
		return '— (خالی)';
	}
	if ( hj_importer_utf8_len( $text ) <= $limit ) {
		return $text;
	}
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $text, 0, $limit, 'UTF-8' ) . '…';
	}
	return substr( $text, 0, $limit * 2 ) . '…';
}

/**
 * Count approximate words in Persian/English HTML content.
 *
 * @param string $html HTML content.
 * @return int
 */
function hj_importer_count_words( string $html ): int {
	$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
	if ( '' === $plain ) {
		return 0;
	}
	$parts = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $parts ) ? count( $parts ) : 0;
}

/**
 * Allowed post types for package import with Persian metadata.
 *
 * @return array<string,array<string,string>>
 */
function hj_importer_post_type_map(): array {
	return array(
		'patient_guide'   => array(
			'label' => 'راهنمای بیمار',
			'color' => '#06265C',
			'icon'  => '💙',
			'tax'   => 'guide_stage',
		),
		'or_environment'  => array(
			'label' => 'اتاق عمل، تیم و تجهیزات',
			'color' => '#7C3AED',
			'icon'  => '🏥',
			'tax'   => 'env_section',
		),
		'surgery'         => array(
			'label' => 'جراحی‌ها',
			'color' => '#E11D48',
			'icon'  => '🩹',
			'tax'   => 'surgery_stage',
		),
		'student_content' => array(
			'label' => 'ویژه‌ی دانشجویان',
			'color' => '#0F766E',
			'icon'  => '🎓',
			'tax'   => 'student_topic',
		),
		'glossary'        => array(
			'label' => 'واژه‌نامه',
			'color' => '#2563EB',
			'icon'  => '📖',
			'tax'   => 'glossary_group',
		),
	);
}

/**
 * Allowed post types list (backward-compatible).
 *
 * @return string[]
 */
function hj_importer_allowed_post_types() {
	return array_keys( hj_importer_post_type_map() );
}

/**
 * Allowed image extensions inside a package.
 *
 * @return string[]
 */
function hj_importer_allowed_image_ext() {
	return array( 'webp', 'jpg', 'jpeg', 'png' );
}

/**
 * Saved default importer preferences.
 *
 * @return array<string,mixed>
 */
function hj_importer_get_defaults(): array {
	$saved = get_option( 'hj_importer_default_options', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args(
		$saved,
		array(
			'status_mode'          => 'draft',        // draft | publish | manifest.
			'keep_existing_status' => 1,              // Preserve publish status when updating a live post.
			'draft_on_deficiency'  => 0,              // Force draft if critical image/SEO deficiency exists.
			'duplicate_mode'       => 'smart_update', // smart_update | warn_skip | create_copy.
			'update_image'         => 1,              // Replace featured image if SHA-256 differs.
			'normalize_content'    => 1,              // Normalize 0.10.2 single-sentence disclaimer & tables.
			'purge_cache'          => 1,              // Flush WP/LiteSpeed cache after import.
		)
	);
}

/**
 * Register the «ایمپورت مقاله» submenu under ALL 5 CPT menus.
 *
 * @return void
 */
function hj_importer_admin_menu() {
	$map = hj_importer_post_type_map();
	foreach ( $map as $cpt => $meta ) {
		$menu_slug  = ( 'patient_guide' === $cpt ) ? 'hj-article-importer' : 'hj-importer-' . $cpt;
		$menu_title = ( 'glossary' === $cpt ) ? 'ایمپورت واژه‌نامه (ZIP)' : 'ایمپورت مقاله (ZIP)';
		add_submenu_page(
			'edit.php?post_type=' . $cpt,
			'ایمپورت بسته — ' . $meta['label'],
			$menu_title,
			HJ_IMPORTER_CAP,
			$menu_slug,
			'hj_importer_render_page'
		);
	}
}
add_action( 'admin_menu', 'hj_importer_admin_menu', 20 );

/**
 * Detect which CPT context the current importer page was opened from.
 *
 * @return string Empty string for hub/auto, or one of the 5 CPT keys.
 */
function hj_importer_current_cpt_context(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['target_cpt'] ) ) {
		$cand = sanitize_key( (string) wp_unslash( $_GET['target_cpt'] ) );
		if ( in_array( $cand, hj_importer_allowed_post_types(), true ) ) {
			return $cand;
		}
	}
	if ( ! empty( $_GET['page'] ) ) {
		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) );
		if ( 'hj-article-importer' === $page && ! empty( $_GET['post_type'] ) ) {
			$pt = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) );
			if ( in_array( $pt, hj_importer_allowed_post_types(), true ) ) {
				return $pt;
			}
		}
		foreach ( hj_importer_allowed_post_types() as $cpt ) {
			if ( 'hj-importer-' . $cpt === $page ) {
				return $cpt;
			}
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return '';
}

/**
 * Build the admin URL back to the importer screen in a given CPT context.
 *
 * @param string $cpt Optional CPT key.
 * @return string
 */
function hj_importer_admin_url( string $cpt = '' ): string {
	if ( '' !== $cpt && in_array( $cpt, hj_importer_allowed_post_types(), true ) ) {
		$slug = ( 'patient_guide' === $cpt ) ? 'hj-article-importer' : 'hj-importer-' . $cpt;
		return add_query_arg(
			array(
				'post_type' => $cpt,
				'page'      => $slug,
			),
			admin_url( 'edit.php' )
		);
	}
	return add_query_arg( array( 'page' => 'hj-importer-hub' ), admin_url( 'admin.php' ) );
}

/**
 * Read the article.json manifest straight from a ZIP without extracting.
 *
 * @param string $zip_path Absolute ZIP path.
 * @return array|\WP_Error
 */
function hj_importer_read_manifest( $zip_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'no_zip', 'افزونه‌ی ZipArchive روی این سرور فعال نیست؛ با پشتیبانی هاست هماهنگ کنید.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return new WP_Error( 'bad_zip', 'فایل ZIP باز نشد؛ ممکن است فایل ناقص یا خراب باشد.' );
	}
	$raw = $zip->getFromName( 'article.json' );
	if ( false === $raw ) {
		// Allow single top-level folder inside ZIP if user zipped a folder.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = (string) $zip->getNameIndex( $i );
			if ( 'article.json' === basename( $entry ) && substr_count( trim( $entry, '/' ), '/' ) <= 1 ) {
				$raw = $zip->getFromIndex( $i );
				break;
			}
		}
	}
	$zip->close();
	if ( false === $raw || '' === $raw ) {
		return new WP_Error( 'no_manifest', 'فایل article.json داخل بسته‌ی ZIP پیدا نشد.' );
	}
	$manifest = json_decode( $raw, true );
	if ( ! is_array( $manifest ) ) {
		return new WP_Error( 'bad_manifest', 'فرمت JSON داخل فایل article.json معتبر نیست.' );
	}
	return $manifest;
}

/**
 * Extract a package ZIP into a unique temp directory with Zip-Slip protection.
 * If all files were wrapped inside a single subfolder, returns that subfolder.
 *
 * @param string $zip_path Absolute ZIP path.
 * @return array{root:string,work:string}|\WP_Error
 */
function hj_importer_extract_paths( string $zip_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'no_zip', 'افزونه‌ی ZipArchive فعال نیست.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return new WP_Error( 'bad_zip', 'فایل ZIP باز نشد.' );
	}
	$upload = wp_upload_dir();
	$target = trailingslashit( $upload['basedir'] ) . 'hj-import-tmp/' . wp_generate_uuid4();
	if ( ! wp_mkdir_p( $target ) ) {
		$zip->close();
		return new WP_Error( 'no_tmp', 'پوشه‌ی موقت روی سرور ساخته نشد.' );
	}
	$real_target = (string) realpath( $target );
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$entry = $zip->getNameIndex( $i );
		if ( false === $entry || '' === $entry ) {
			continue;
		}
		$clean = ltrim( str_replace( '\\', '/', $entry ), '/' );
		if ( false !== strpos( $clean, '..' ) || preg_match( '#^[A-Za-z]:#', $clean ) ) {
			$zip->close();
			hj_importer_rmdir( $real_target );
			return new WP_Error( 'unsafe_path', 'بسته شامل مسیر غیرمجاز است و به دلایل امنیتی رد شد.' );
		}
	}
	$ok = $zip->extractTo( $real_target );
	$zip->close();
	if ( ! $ok ) {
		hj_importer_rmdir( $real_target );
		return new WP_Error( 'extract_failed', 'استخراج بسته‌ی ZIP شکست خورد.' );
	}

	$work_dir = $real_target;
	if ( ! is_file( $work_dir . '/article.json' ) ) {
		$sub = glob( $work_dir . '/*/article.json' );
		if ( ! empty( $sub[0] ) ) {
			$work_dir = dirname( $sub[0] );
		}
	}

	return array(
		'root' => $real_target,
		'work' => $work_dir,
	);
}

/**
 * Backward-compatible wrapper returning extracted working directory.
 *
 * @param string $zip_path Absolute ZIP path.
 * @return string|\WP_Error
 */
function hj_importer_extract( $zip_path ) {
	$res = hj_importer_extract_paths( (string) $zip_path );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return $res['work'];
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory path.
 * @return void
 */
function hj_importer_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = @scandir( $dir );
	if ( is_array( $items ) ) {
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? hj_importer_rmdir( $path ) : wp_delete_file( $path );
		}
	}
	@rmdir( $dir );
}

/**
 * Extract normalized taxonomy assignments from manifest + optional manual overrides.
 *
 * Supports:
 *   - "taxonomy": { "taxonomy": "guide_stage", "term": "before" }
 *   - "taxonomies": [ { "taxonomy": "surgery_specialty", "term": "appendectomy" }, ... ]
 *   - Manual taxonomy override from importer form
 *
 * @param array  $manifest       Decoded article.json.
 * @param string $post_type      Resolved target CPT.
 * @param array  $manual_tax_map Optional [ taxonomy => term_slug ].
 * @return array<int,array{taxonomy:string,term:string}>
 */
function hj_importer_collect_taxonomies( array $manifest, string $post_type, array $manual_tax_map = array() ): array {
	$pairs = array();

	if ( ! empty( $manifest['taxonomy']['taxonomy'] ) && ! empty( $manifest['taxonomy']['term'] ) ) {
		$pairs[] = array(
			'taxonomy' => sanitize_key( (string) $manifest['taxonomy']['taxonomy'] ),
			'term'     => sanitize_title( (string) $manifest['taxonomy']['term'] ),
		);
	}

	if ( ! empty( $manifest['taxonomies'] ) && is_array( $manifest['taxonomies'] ) ) {
		foreach ( $manifest['taxonomies'] as $tx ) {
			if ( ! empty( $tx['taxonomy'] ) && ! empty( $tx['term'] ) ) {
				$pairs[] = array(
					'taxonomy' => sanitize_key( (string) $tx['taxonomy'] ),
					'term'     => sanitize_title( (string) $tx['term'] ),
				);
			}
		}
	}

	// Convenience keys for surgery packages: surgery_specialty & surgery_stage.
	foreach ( array( 'surgery_specialty', 'surgery_stage', 'guide_stage', 'env_section', 'student_topic', 'glossary_group' ) as $shortcut ) {
		if ( ! empty( $manifest[ $shortcut ] ) && is_string( $manifest[ $shortcut ] ) ) {
			$pairs[] = array(
				'taxonomy' => $shortcut,
				'term'     => sanitize_title( $manifest[ $shortcut ] ),
			);
		}
	}

	// Apply manual overrides/fallbacks chosen in the UI for taxonomies belonging to $post_type.
	$valid_tax_for_cpt = get_object_taxonomies( $post_type );
	foreach ( $manual_tax_map as $tax_key => $term_val ) {
		$tax_key  = sanitize_key( (string) $tax_key );
		$term_val = sanitize_title( (string) $term_val );
		if ( '' === $term_val || ! in_array( $tax_key, $valid_tax_for_cpt, true ) ) {
			continue;
		}
		// Replace existing entry for same taxonomy or add it.
		$replaced = false;
		foreach ( $pairs as &$p ) {
			if ( $p['taxonomy'] === $tax_key ) {
				$p['term'] = $term_val;
				$replaced  = true;
			}
		}
		unset( $p );
		if ( ! $replaced ) {
			$pairs[] = array(
				'taxonomy' => $tax_key,
				'term'     => $term_val,
			);
		}
	}

	// Filter only taxonomies registered on target post type & deduplicate.
	$unique = array();
	$seen   = array();
	foreach ( $pairs as $p ) {
		if ( ! in_array( $p['taxonomy'], $valid_tax_for_cpt, true ) ) {
			continue;
		}
		$k = $p['taxonomy'] . ':' . $p['term'];
		if ( ! isset( $seen[ $k ] ) ) {
			$seen[ $k ] = true;
			$unique[]   = $p;
		}
	}

	return $unique;
}

/**
 * Inspect image file inside extracted package directory (dimensions, format, size, hash).
 *
 * @param string $dir      Extracted package directory.
 * @param array  $manifest Decoded manifest.
 * @return array<string,mixed>
 */
function hj_importer_inspect_package_image( string $dir, array $manifest ): array {
	$file = isset( $manifest['featured_image']['file'] ) ? basename( (string) $manifest['featured_image']['file'] ) : '';
	$alt  = isset( $manifest['featured_image']['alt'] ) ? trim( sanitize_text_field( (string) $manifest['featured_image']['alt'] ) ) : '';

	// Auto-detect featured.webp/jpg/png if omitted in manifest.
	if ( '' === $file ) {
		foreach ( hj_importer_allowed_image_ext() as $cand_ext ) {
			if ( is_file( $dir . DIRECTORY_SEPARATOR . 'featured.' . $cand_ext ) ) {
				$file = 'featured.' . $cand_ext;
				break;
			}
		}
	}

	$info = array(
		'declared_file' => $file,
		'exists'        => false,
		'path'          => '',
		'ext'           => '' !== $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '',
		'allowed_ext'   => false,
		'size_bytes'    => 0,
		'size_kb'       => 0.0,
		'width'         => 0,
		'height'        => 0,
		'mime'          => '',
		'sha256'        => '',
		'alt'           => $alt,
		'alt_len'       => hj_importer_utf8_len( $alt ),
	);

	if ( '' === $file ) {
		return $info;
	}

	$info['allowed_ext'] = in_array( $info['ext'], hj_importer_allowed_image_ext(), true );
	$src                 = $dir . DIRECTORY_SEPARATOR . $file;
	if ( ! is_file( $src ) ) {
		return $info;
	}

	$info['exists']     = true;
	$info['path']       = $src;
	$info['size_bytes'] = (int) filesize( $src );
	$info['size_kb']    = round( $info['size_bytes'] / 1024, 1 );
	$info['sha256']     = (string) hash_file( 'sha256', $src );

	$dims = @getimagesize( $src );
	if ( is_array( $dims ) ) {
		$info['width']  = isset( $dims[0] ) ? (int) $dims[0] : 0;
		$info['height'] = isset( $dims[1] ) ? (int) $dims[1] : 0;
		$info['mime']   = isset( $dims['mime'] ) ? (string) $dims['mime'] : '';
	}

	return $info;
}

/**
 * Read a list field (`ask_doctor`, `terms`) from a manifest.
 *
 * Accepts an array (one entry per line, or `term | explanation` pairs) or a
 * single string with newlines. Values are sanitised as plain text.
 *
 * @param array  $manifest Decoded manifest.
 * @param string $key      Manifest key.
 * @return array<int,string>
 */
function hj_importer_list_field( array $manifest, string $key ): array {
	if ( empty( $manifest[ $key ] ) ) {
		return array();
	}

	$raw   = $manifest[ $key ];
	$lines = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );
	$out   = array();

	foreach ( (array) $lines as $line ) {
		if ( is_array( $line ) ) {
			continue;
		}
		$line = trim( sanitize_text_field( (string) $line ) );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * Audit SEO, Featured Image, and Content structure of a package and return
 * deficiencies, warnings, passed checks, metrics, and a 0-100 quality score.
 *
 * @param array  $manifest   Decoded manifest.
 * @param string $html       Cleaned HTML body.
 * @param array  $image_info Output of hj_importer_inspect_package_image().
 * @param string $post_type  Target post type.
 * @param array  $tax_pairs  Collected taxonomy pairs.
 * @return array<string,mixed>
 */
function hj_importer_audit_package( array $manifest, string $html, array $image_info, string $post_type, array $tax_pairs ): array {
	$critical = array();
	$warnings = array();
	$passed   = array();
	$score    = 100;

	$is_glossary = ( 'glossary' === $post_type );
	$title       = isset( $manifest['title'] ) ? trim( (string) $manifest['title'] ) : '';
	$slug        = isset( $manifest['slug'] ) ? trim( (string) $manifest['slug'] ) : '';
	$meta_desc   = '';
	if ( ! empty( $manifest['seo']['meta_description'] ) ) {
		$meta_desc = trim( (string) $manifest['seo']['meta_description'] );
	} elseif ( ! empty( $manifest['excerpt'] ) ) {
		$meta_desc = trim( (string) $manifest['excerpt'] );
	}
	$focus_kw      = isset( $manifest['seo']['focus_keyword'] ) ? trim( (string) $manifest['seo']['focus_keyword'] ) : '';
	$search_intent = isset( $manifest['seo']['search_intent'] ) ? trim( (string) $manifest['seo']['search_intent'] ) : '';
	$keywords      = ( isset( $manifest['seo']['keywords'] ) && is_array( $manifest['seo']['keywords'] ) ) ? array_filter( array_map( 'trim', $manifest['seo']['keywords'] ) ) : array();
	$faqs          = array();
	if ( ! empty( $manifest['faq'] ) && is_array( $manifest['faq'] ) ) {
		foreach ( $manifest['faq'] as $f ) {
			if ( ! empty( $f['q'] ) && ! empty( $f['a'] ) ) {
				$faqs[] = $f;
			}
		}
	}

	$word_count    = hj_importer_count_words( $html );
	$read_minutes  = max( 1, (int) ceil( $word_count / 190 ) );
	$h2_count      = (int) preg_match_all( '/<h2\b[^>]*>/i', $html );
	$h3_count      = (int) preg_match_all( '/<h3\b[^>]*>/i', $html );
	$table_count   = (int) preg_match_all( '/<table\b[^>]*>/i', $html );
	$img_in_body   = (int) preg_match_all( '/<img\b[^>]*>/i', $html );
	$internal_lnks = (int) preg_match_all( '/<a\b[^>]*href=["\'](\/[^"\']*|https?:\/\/(?:www\.)?hamyarjarahi\.ir[^"\']*)["\']/i', $html );
	$external_lnks = max( 0, (int) preg_match_all( '/<a\b[^>]*href=["\']https?:\/\//i', $html ) - (int) preg_match_all( '/<a\b[^>]*href=["\']https?:\/\/(?:www\.)?hamyarjarahi\.ir/i', $html ) );
	$has_115       = ( false !== strpos( $html, '۱۱۵' ) || false !== strpos( $html, '115' ) );
	$has_disc      = ( 1 === preg_match( '/سلب\s*مسئولیت/u', $html ) );

	// --- 1. Image Audit ---
	if ( ! $image_info['exists'] ) {
		if ( $is_glossary ) {
			$warnings[] = 'مدخل واژه‌نامه بدون تصویر شاخص است (برای واژه‌نامه اختیاری است).';
		} else {
			$critical[] = 'تصویر شاخص در بسته وجود ندارد (فایل featured.webp یافت نشد).';
			$score     -= 25;
		}
	} else {
		if ( ! $image_info['allowed_ext'] ) {
			$critical[] = 'فرمت تصویر شاخص مجاز نیست (' . $image_info['ext'] . '). فقط WebP/JPG/PNG مجاز است.';
			$score     -= 20;
		} elseif ( 'webp' !== $image_info['ext'] ) {
			$warnings[] = 'فرمت تصویر شاخص «' . strtoupper( $image_info['ext'] ) . '» است؛ استاندارد سرعت سایت فرمت WebP است.';
			$score     -= 5;
		} else {
			$passed[] = 'فرمت تصویر شاخص WebP استاندارد است.';
		}

		if ( $image_info['width'] > 0 && $image_info['height'] > 0 ) {
			if ( 1200 === $image_info['width'] && 675 === $image_info['height'] ) {
				$passed[] = 'ابعاد تصویر شاخص دقیقاً استاندارد ۱۲۰۰×۶۷۵ (نسبت ۱۶:۹) است.';
			} else {
				$warnings[] = sprintf(
					'ابعاد تصویر شاخص %d×%d پیکسل است (ابعاد استاندارد سایت: ۱۲۰۰×۶۷۵ پیکسل).',
					$image_info['width'],
					$image_info['height']
				);
				$score     -= 6;
			}
		}

		if ( $image_info['size_kb'] > 180 ) {
			$warnings[] = 'حجم تصویر شاخص (' . $image_info['size_kb'] . ' KB) بالاتر از سقف پیشنهادی ۱۵۰ KB است.';
			$score     -= 4;
		} else {
			$passed[] = 'حجم تصویر شاخص بهینه است (' . $image_info['size_kb'] . ' KB).';
		}

		if ( '' === $image_info['alt'] ) {
			$critical[] = 'متن جایگزین (alt) تصویر شاخص خالی است (کسری مهم سئوی تصاویر و دسترس‌پذیری).';
			$score     -= 12;
		} elseif ( $image_info['alt_len'] < 15 ) {
			$warnings[] = 'متن alt تصویر شاخص خیلی کوتاه است (' . $image_info['alt_len'] . ' کاراکتر؛ توصیفی‌تر بنویسید).';
			$score     -= 4;
		} elseif ( $image_info['alt'] === $title ) {
			$warnings[] = 'متن alt تصویر شاخص دقیقاً کپی عنوان مقاله است؛ توصیف صحنه‌ی تصویر بهتر است.';
			$score     -= 3;
		} else {
			$passed[] = 'متن جایگزین (alt) فارسی و توصیفی برای تصویر شاخص ثبت شده است.';
		}
	}

	// --- 2. SEO Metadata Audit ---
	$title_len = hj_importer_utf8_len( $title );
	if ( $title_len < 18 ) {
		$warnings[] = 'عنوان مقاله کوتاه است (' . $title_len . ' کاراکتر).';
		$score     -= 3;
	} elseif ( $title_len > 70 ) {
		$warnings[] = 'عنوان مقاله طولانی است (' . $title_len . ' کاراکتر؛ در نتایج گوگل ممکن است بالای ۶۵ کاراکتر برش بخورد).';
		$score     -= 3;
	} else {
		$passed[] = 'طول عنوان مقاله برای سئو مناسب است (' . $title_len . ' کاراکتر).';
	}

	if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
		$warnings[] = 'نامک (slug) بهتر است فقط حروف انگلیسی کوچک و خط تیره باشد: ' . $slug;
		$score     -= 4;
	} else {
		$passed[] = 'نامک (slug) انگلیسی و استاندارد است.';
	}

	$meta_len = hj_importer_utf8_len( $meta_desc );
	if ( 0 === $meta_len ) {
		$critical[] = 'توضیحات متای سئو (meta_description / excerpt) در بسته خالی است.';
		$score     -= 15;
	} elseif ( $meta_len < 95 ) {
		$warnings[] = 'توضیحات متای سئو کوتاه است (' . $meta_len . ' کاراکتر؛ بازه‌ی بهینه ۱۲۰ تا ۱۶۵ کاراکتر).';
		$score     -= 4;
	} elseif ( $meta_len > 175 ) {
		$warnings[] = 'توضیحات متای سئو طولانی است (' . $meta_len . ' کاراکتر؛ بازه‌ی بهینه ۱۲۰ تا ۱۶۵ کاراکتر).';
		$score     -= 3;
	} else {
		$passed[] = 'توضیحات متای سئو با طول استاندارد ثبت شده است (' . $meta_len . ' کاراکتر).';
	}

	if ( '' === $focus_kw ) {
		$warnings[] = 'کلیدواژه‌ی کانونی (focus_keyword) در article.json خالی است.';
		$score     -= 4;
	} else {
		$passed[] = 'کلیدواژه‌ی کانونی ثبت شده است («' . $focus_kw . '»).';
		if ( false === strpos( $title, $focus_kw ) && false === strpos( $meta_desc, $focus_kw ) ) {
			$warnings[] = 'کلیدواژه‌ی کانونی در عنوان یا توضیحات متا دیده نشد.';
			$score     -= 3;
		}
	}

	if ( empty( $keywords ) ) {
		$warnings[] = 'فهرست کلیدواژه‌های فرعی (seo.keywords) در بسته خالی است.';
		$score     -= 2;
	} else {
		$passed[] = count( $keywords ) . ' کلیدواژه‌ی فرعی ثبت شده است.';
	}

	if ( '' === $search_intent ) {
		$warnings[] = 'نیت جستجو (seo.search_intent) در بسته ثبت نشده است.';
		$score     -= 2;
	} else {
		$passed[] = 'نیت جستجوی کاربر ثبت شده است.';
	}

	if ( ! $is_glossary ) {
		if ( empty( $faqs ) ) {
			$warnings[] = 'پرسش‌های متداول ( اسکیمای FAQPage ) در بسته ثبت نشده است.';
			$score     -= 6;
		} elseif ( count( $faqs ) < 3 ) {
			$warnings[] = 'تعداد پرسش‌های متداول کم است (' . count( $faqs ) . ' پرسش؛ حداقل ۳ تا ۴ پرسش پیشنهاد می‌شود).';
			$score     -= 2;
		} else {
			$passed[] = 'اسکیمای FAQPage فعال است (' . count( $faqs ) . ' پرسش و پاسخ).';
		}
	}

	// --- Patient-need lists (child 0.15.0) ---
	if ( in_array( $post_type, array( 'surgery', 'patient_guide' ), true ) ) {
		$ask_count   = count( hj_importer_list_field( $manifest, 'ask_doctor' ) );
		$terms_count = count( hj_importer_list_field( $manifest, 'terms' ) );

		if ( $ask_count >= 4 ) {
			$passed[] = 'فهرست سؤال‌های پیش از عمل ثبت شده است (' . $ask_count . ' سؤال).';
		} else {
			$warnings[] = 'فهرست «سؤال‌هایی که بیمار قبل از عمل بپرسید» (ask_doctor) کمتر از ۴ مورد است؛ قالب ۰٫۱۵ فهرست عمومی پیش‌فرض را نشان می‌دهد.';
			$score     -= 3;
		}

		if ( $terms_count ) {
			$passed[] = 'واژه‌های ساده‌شده ثبت شده است (' . $terms_count . ' واژه).';
		} else {
			$warnings[] = 'واژه‌های ساده‌شده (terms) خالی است؛ بخش «واژه‌های این صفحه» در این مقاله نمایش داده نمی‌شود.';
			$score     -= 2;
		}
	}

	// --- 3. Taxonomy & Content Structure Audit ---
	if ( empty( $tax_pairs ) ) {
		$warnings[] = 'دسته‌بندی / تاکسونومی معتبری برای این بخش در بسته مشخص نشده است.';
		$score     -= 6;
	} else {
		$passed[] = 'دسته‌بندی بخش به‌درستی مشخص شده است.';
	}

	if ( 'surgery' === $post_type && count( $tax_pairs ) < 2 ) {
		$warnings[] = 'برای مقالات گروه «جراحی‌ها» بهتر است هر دو دسته‌ی «نوع عمل (surgery_specialty)» و «مرحله مقاله (surgery_stage)» تعیین شوند.';
		$score     -= 3;
	}

	if ( ! $is_glossary ) {
		if ( $word_count < 300 ) {
			$critical[] = 'متن مقاله بسیار کوتاه است (' . $word_count . ' کلمه).';
			$score     -= 15;
		} else {
			$passed[] = 'حجم محتوای متنی مناسب است (' . number_format_i18n( $word_count ) . ' کلمه · حدود ' . number_format_i18n( $read_minutes ) . ' دقیقه مطالعه).';
		}

		if ( $h2_count < 3 ) {
			$warnings[] = 'تعداد زیرتیترهای H2 کم است (' . $h2_count . ' مورد؛ حداقل ۳ زیرتیتر برای فهرست مطالب توصیه می‌شود).';
			$score     -= 4;
		} else {
			$passed[] = $h2_count . ' زیرتیتر H2 برای فهرست مطالب شناسایی شد.';
		}

		if ( 0 === $internal_lnks ) {
			$warnings[] = 'هیچ لینک داخلی به ستون‌ها یا مقالات مرتبط در متن مقاله وجود ندارد (توصیه سئو: حداقل ۲ تا ۳ لینک داخلی).';
			$score     -= 4;
		} else {
			$passed[] = $internal_lnks . ' لینک داخلی در متن مقاله وجود دارد.';
		}

		if ( ! $has_115 && in_array( $post_type, array( 'patient_guide', 'surgery' ), true ) ) {
			$warnings[] = 'اشاره به اورژانس ۱۱۵ / علائم هشدار در متن دیده نشد.';
			$score     -= 2;
		}

		if ( $has_disc ) {
			$passed[] = 'بخش استاندارد «سلب مسئولیت پزشکی» در انتهای مقاله موجود است.';
		} else {
			$warnings[] = 'بخش «سلب مسئولیت پزشکی» در انتهای متن مقاله دیده نشد.';
			$score     -= 3;
		}
	}

	$score = max( 0, min( 100, $score ) );

	return array(
		'score'    => $score,
		'critical' => $critical,
		'warnings' => $warnings,
		'passed'   => $passed,
		'metrics'  => array(
			'word_count'     => $word_count,
			'read_minutes'   => $read_minutes,
			'h2_count'       => $h2_count,
			'h3_count'       => $h3_count,
			'table_count'    => $table_count,
			'body_images'    => $img_in_body,
			'internal_links' => $internal_lnks,
			'external_links' => $external_lnks,
			'faq_count'      => count( $faqs ),
			'title_len'      => $title_len,
			'meta_len'       => $meta_len,
			'focus_keyword'  => $focus_kw,
			'search_intent'  => $search_intent,
			'keywords_count' => count( $keywords ),
			'has_115'        => $has_115,
			'has_disclaimer' => $has_disc,
		),
	);
}

/**
 * Snapshot an existing post before update so exact field-by-field diffs can be reported.
 *
 * @param \WP_Post $post Existing post object.
 * @return array<string,mixed>
 */
function hj_importer_snapshot_post( WP_Post $post ): array {
	$post_id   = (int) $post->ID;
	$meta_desc = (string) get_post_meta( $post_id, '_hj_meta_description', true );
	if ( '' === $meta_desc ) {
		$meta_desc = (string) $post->post_excerpt;
	}
	$focus_kw      = (string) get_post_meta( $post_id, '_hj_focus_keyword', true );
	$search_intent = (string) get_post_meta( $post_id, '_hj_search_intent', true );
	$keywords      = get_post_meta( $post_id, '_hj_keywords', true );
	$keywords      = is_array( $keywords ) ? array_values( array_map( 'strval', $keywords ) ) : array();
	$faq           = get_post_meta( $post_id, '_hj_faq', true );
	$faq           = is_array( $faq ) ? $faq : array();

	$tax_names = get_object_taxonomies( $post->post_type );
	$terms_map = array();
	foreach ( $tax_names as $tx ) {
		$t_objs = wp_get_object_terms( $post_id, $tx );
		if ( ! is_wp_error( $t_objs ) && is_array( $t_objs ) && ! empty( $t_objs ) ) {
			$slugs = wp_list_pluck( $t_objs, 'slug' );
			sort( $slugs );
			$terms_map[ $tx ] = $slugs;
		}
	}

	$thumb_id   = (int) get_post_thumbnail_id( $post_id );
	$thumb_alt  = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
	$thumb_hash = $thumb_id ? (string) get_post_meta( $thumb_id, '_hj_image_sha256', true ) : '';
	if ( $thumb_id && '' === $thumb_hash ) {
		$att_path = get_attached_file( $thumb_id );
		if ( $att_path && is_file( $att_path ) ) {
			$thumb_hash = (string) hash_file( 'sha256', $att_path );
		}
	}

	$norm_content = trim( str_replace( "\r\n", "\n", (string) $post->post_content ) );

	return array(
		'id'            => $post_id,
		'title'         => (string) $post->post_title,
		'slug'          => urldecode( (string) $post->post_name ),
		'post_type'     => (string) $post->post_type,
		'post_status'   => (string) $post->post_status,
		'content'       => $norm_content,
		'content_hash'  => hash( 'sha256', $norm_content ),
		'word_count'    => hj_importer_count_words( $norm_content ),
		'h2_count'      => (int) preg_match_all( '/<h2\b[^>]*>/i', $norm_content ),
		'table_count'   => (int) preg_match_all( '/<table\b[^>]*>/i', $norm_content ),
		'meta_desc'     => trim( $meta_desc ),
		'focus_keyword' => trim( $focus_kw ),
		'search_intent' => trim( $search_intent ),
		'keywords'      => $keywords,
		'faq'           => $faq,
		'faq_hash'      => hash( 'sha256', wp_json_encode( $faq ) ),
		'terms_map'     => $terms_map,
		'thumb_id'      => $thumb_id,
		'thumb_alt'     => trim( $thumb_alt ),
		'thumb_hash'    => $thumb_hash,
		'modified'      => function_exists( 'hj_get_jalali_post_modified_date' )
			? hj_get_jalali_post_modified_date( $post->ID, 'datetime' )
			: get_post_modified_time( 'Y-m-d H:i', false, $post ),
	);
}

/**
 * Compute detailed field-by-field diff between old post snapshot and incoming package.
 *
 * @param array  $old          Snapshot from hj_importer_snapshot_post().
 * @param array  $incoming     Normalized incoming payload.
 * @param bool   $update_image Whether image replacement is enabled.
 * @return array<int,array{field:string,before:string,after:string}>
 */
function hj_importer_compute_diff( array $old, array $incoming, bool $update_image = true ): array {
	$diffs       = array();
	$status_lbls = array(
		'publish' => 'منتشرشده (Publish)',
		'draft'   => 'پیش‌نویس (Draft)',
		'pending' => 'در انتظار بازبینی',
		'future'  => 'زمان‌بندی‌شده',
		'private' => 'خصوصی',
	);
	$cpt_map     = hj_importer_post_type_map();

	if ( $old['title'] !== $incoming['title'] ) {
		$diffs[] = array(
			'field'  => 'عنوان مقاله',
			'before' => $old['title'],
			'after'  => $incoming['title'],
		);
	}

	if ( $old['post_type'] !== $incoming['post_type'] ) {
		$diffs[] = array(
			'field'  => 'گروه / نوع نوشته',
			'before' => isset( $cpt_map[ $old['post_type'] ] ) ? $cpt_map[ $old['post_type'] ]['label'] : $old['post_type'],
			'after'  => isset( $cpt_map[ $incoming['post_type'] ] ) ? $cpt_map[ $incoming['post_type'] ]['label'] : $incoming['post_type'],
		);
	}

	if ( $old['post_status'] !== $incoming['post_status'] ) {
		$diffs[] = array(
			'field'  => 'وضعیت انتشار',
			'before' => isset( $status_lbls[ $old['post_status'] ] ) ? $status_lbls[ $old['post_status'] ] : $old['post_status'],
			'after'  => isset( $status_lbls[ $incoming['post_status'] ] ) ? $status_lbls[ $incoming['post_status'] ] : $incoming['post_status'],
		);
	}

	$new_norm_content = trim( str_replace( "\r\n", "\n", (string) $incoming['html'] ) );
	if ( hash( 'sha256', $new_norm_content ) !== $old['content_hash'] ) {
		$new_words = hj_importer_count_words( $new_norm_content );
		$new_h2    = (int) preg_match_all( '/<h2\b[^>]*>/i', $new_norm_content );
		$new_tbl   = (int) preg_match_all( '/<table\b[^>]*>/i', $new_norm_content );
		$delta     = $new_words - (int) $old['word_count'];
		$delta_str = $delta > 0 ? ( '+' . number_format_i18n( $delta ) . ' کلمه' ) : ( 0 === $delta ? 'ویرایش عبارات/تگ‌ها بدون تغییر شمار کلمات' : number_format_i18n( $delta ) . ' کلمه' );
		$diffs[]   = array(
			'field'  => 'متن و محتوای مقاله',
			'before' => sprintf( '%s کلمه · %d تیتر H2 · %d جدول', number_format_i18n( $old['word_count'] ), $old['h2_count'], $old['table_count'] ),
			'after'  => sprintf( '%s کلمه · %d تیتر H2 · %d جدول (%s)', number_format_i18n( $new_words ), $new_h2, $new_tbl, $delta_str ),
		);
	}

	if ( $old['meta_desc'] !== $incoming['meta_desc'] && '' !== $incoming['meta_desc'] ) {
		$diffs[] = array(
			'field'  => 'توضیحات متای سئو (Excerpt)',
			'before' => hj_importer_utf8_preview( $old['meta_desc'], 85 ),
			'after'  => hj_importer_utf8_preview( $incoming['meta_desc'], 85 ),
		);
	}

	if ( '' !== $incoming['focus_keyword'] && $old['focus_keyword'] !== $incoming['focus_keyword'] ) {
		$diffs[] = array(
			'field'  => 'کلیدواژه‌ی کانونی',
			'before' => '' !== $old['focus_keyword'] ? $old['focus_keyword'] : '— (خالی)',
			'after'  => $incoming['focus_keyword'],
		);
	}

	if ( '' !== $incoming['search_intent'] && $old['search_intent'] !== $incoming['search_intent'] ) {
		$diffs[] = array(
			'field'  => 'نیت جستجو (Search Intent)',
			'before' => hj_importer_utf8_preview( $old['search_intent'], 70 ),
			'after'  => hj_importer_utf8_preview( $incoming['search_intent'], 70 ),
		);
	}

	if ( ! empty( $incoming['keywords'] ) && $old['keywords'] !== $incoming['keywords'] ) {
		$diffs[] = array(
			'field'  => 'کلیدواژه‌های فرعی',
			'before' => ! empty( $old['keywords'] ) ? implode( '، ', $old['keywords'] ) : '— (خالی)',
			'after'  => implode( '، ', $incoming['keywords'] ),
		);
	}

	$new_faq_hash = hash( 'sha256', wp_json_encode( $incoming['faq'] ) );
	if ( ! empty( $incoming['faq'] ) && $new_faq_hash !== $old['faq_hash'] ) {
		$diffs[] = array(
			'field'  => 'پرسش‌های متداول (اسکیمای FAQ)',
			'before' => count( $old['faq'] ) . ' پرسش و پاسخ',
			'after'  => count( $incoming['faq'] ) . ' پرسش و پاسخ (محتوای پرسش‌ها/پاسخ‌ها به‌روز شد)',
		);
	}

	if ( ! empty( $incoming['tax_pairs'] ) ) {
		$new_map = array();
		foreach ( $incoming['tax_pairs'] as $tp ) {
			$new_map[ $tp['taxonomy'] ][] = $tp['term'];
		}
		foreach ( $new_map as $tx => &$sl ) {
			sort( $sl );
		}
		unset( $sl );
		foreach ( $new_map as $tx => $new_slugs ) {
			$old_slugs = isset( $old['terms_map'][ $tx ] ) ? $old['terms_map'][ $tx ] : array();
			if ( $old_slugs !== $new_slugs ) {
				$diffs[] = array(
					'field'  => 'دسته‌بندی (' . $tx . ')',
					'before' => ! empty( $old_slugs ) ? implode( ', ', $old_slugs ) : '— (بدون دسته)',
					'after'  => implode( ', ', $new_slugs ),
				);
			}
		}
	}

	$img = $incoming['image_info'];
	if ( $img['exists'] ) {
		if ( 0 === $old['thumb_id'] ) {
			$diffs[] = array(
				'field'  => 'تصویر شاخص',
				'before' => '— (بدون تصویر شاخص)',
				'after'  => sprintf( 'افزودن تصویر شاخص جدید (%d×%d · %s KB)', $img['width'], $img['height'], $img['size_kb'] ),
			);
		} elseif ( $update_image && '' !== $img['sha256'] && $img['sha256'] !== $old['thumb_hash'] ) {
			$diffs[] = array(
				'field'  => 'تصویر شاخص',
				'before' => 'تصویر شاخص قبلی (ID #' . $old['thumb_id'] . ')',
				'after'  => sprintf( 'جایگزینی با فایل تصویر جدید (%d×%d · %s KB)', $img['width'], $img['height'], $img['size_kb'] ),
			);
		}
		if ( '' !== $img['alt'] && $img['alt'] !== $old['thumb_alt'] ) {
			$diffs[] = array(
				'field'  => 'متن جایگزین (alt) تصویر شاخص',
				'before' => '' !== $old['thumb_alt'] ? $old['thumb_alt'] : '— (خالی)',
				'after'  => $img['alt'],
			);
		}
	}

	return $diffs;
}

/**
 * Import (or update) the featured image and attach it to the post.
 * Replaces image when $force_replace is true and SHA-256 differs from existing.
 *
 * @param int    $post_id       Post ID.
 * @param string $dir           Extracted package directory.
 * @param array  $image         Image manifest entry (file + alt).
 * @param bool   $force_replace Replace attachment when file bytes changed.
 * @return int|\WP_Error Attachment ID.
 */
function hj_importer_import_image( $post_id, $dir, array $image, bool $force_replace = true ) {
	$file = isset( $image['file'] ) ? basename( (string) $image['file'] ) : '';
	if ( '' === $file ) {
		return new WP_Error( 'no_image_file', 'نام تصویر شاخص در article.json ثبت نشده است.' );
	}
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, hj_importer_allowed_image_ext(), true ) ) {
		return new WP_Error( 'bad_image_ext', 'فرمت تصویر مجاز نیست (فقط webp/jpg/png).' );
	}
	$src = $dir . DIRECTORY_SEPARATOR . $file;
	if ( ! is_file( $src ) ) {
		return new WP_Error( 'missing_image', 'فایل تصویر شاخص داخل بسته پیدا نشد: ' . $file );
	}
	$alt      = isset( $image['alt'] ) ? sanitize_text_field( (string) $image['alt'] ) : '';
	$new_hash = (string) hash_file( 'sha256', $src );
	$slug_key = (string) get_post_field( 'post_name', $post_id );

	// Check existing thumbnail on post first, then package slug lookup.
	$existing_att_id = (int) get_post_thumbnail_id( $post_id );
	if ( ! $existing_att_id ) {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_key'       => '_hj_package_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $slug_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $existing[0] ) ) {
			$existing_att_id = (int) $existing[0];
		}
	}

	if ( $existing_att_id ) {
		$old_hash = (string) get_post_meta( $existing_att_id, '_hj_image_sha256', true );
		if ( '' === $old_hash ) {
			$old_file = get_attached_file( $existing_att_id );
			if ( $old_file && is_file( $old_file ) ) {
				$old_hash = (string) hash_file( 'sha256', $old_file );
				update_post_meta( $existing_att_id, '_hj_image_sha256', $old_hash );
			}
		}

		// Reuse existing attachment if identical bytes or image replacement disabled.
		if ( ! $force_replace || ( '' !== $old_hash && $old_hash === $new_hash ) ) {
			if ( '' !== $alt ) {
				update_post_meta( $existing_att_id, '_wp_attachment_image_alt', $alt );
			}
			set_post_thumbnail( $post_id, $existing_att_id );
			return $existing_att_id;
		}
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$bits       = file_get_contents( $src );
	$upload_dir = wp_upload_dir();
	$preferred  = sanitize_title( $slug_key ) . '-featured.' . $ext;
	$unique     = wp_unique_filename( $upload_dir['path'], $preferred );
	$upload     = wp_upload_bits( $unique, null, $bits );
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'upload_failed', 'آپلود تصویر شاخص شکست خورد: ' . $upload['error'] );
	}
	$filetype = wp_check_filetype( $upload['file'], null );
	$att_id   = wp_insert_attachment(
		array(
			'guid'           => $upload['url'],
			'post_mime_type' => $filetype['type'],
			'post_title'     => $slug_key . ' — تصویر شاخص',
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		),
		$upload['file'],
		$post_id
	);
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		return new WP_Error( 'attachment_failed', 'ساخت رکورد رسانه در کتابخانه شکست خورد.' );
	}
	$meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
	wp_update_attachment_metadata( $att_id, $meta );
	if ( '' !== $alt ) {
		update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
	}
	update_post_meta( $att_id, '_hj_package_slug', $slug_key );
	update_post_meta( $att_id, '_hj_package_image', $file );
	update_post_meta( $att_id, '_hj_image_sha256', $new_hash );
	set_post_thumbnail( $post_id, $att_id );
	return (int) $att_id;
}

/**
 * Find any existing post matching slug across the target CPT (or across all allowed CPTs).
 *
 * @param string $slug      Sanitized slug.
 * @param string $post_type Target CPT.
 * @return \WP_Post|null
 */
function hj_importer_find_existing_post( string $slug, string $post_type ) {
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	if ( ! empty( $found[0] ) && $found[0] instanceof WP_Post ) {
		return $found[0];
	}
	// Check if same slug exists in another allowed CPT to warn/prevent cross-CPT duplicates.
	$cross = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => hj_importer_allowed_post_types(),
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	return ( ! empty( $cross[0] ) && $cross[0] instanceof WP_Post ) ? $cross[0] : null;
}

/**
 * Append an entry to the rolling import history log (max 35 entries).
 *
 * @param array $entry Result summary.
 * @return void
 */
function hj_importer_record_history( array $entry ): void {
	if ( ! empty( $entry['dry_run'] ) ) {
		return;
	}
	$log = get_option( 'hj_importer_history_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$user = wp_get_current_user();
	array_unshift(
		$log,
		array(
			'time'         => function_exists( 'hj_format_jalali_timestamp' )
				? hj_format_jalali_timestamp( time(), 'datetime' )
				: current_time( 'Y-m-d H:i' ),
			'user'         => $user && $user->exists() ? $user->display_name : 'مدیر',
			'slug'         => isset( $entry['slug'] ) ? (string) $entry['slug'] : '',
			'title'        => isset( $entry['title'] ) ? (string) $entry['title'] : '',
			'post_type'    => isset( $entry['post_type'] ) ? (string) $entry['post_type'] : '',
			'post_status'  => isset( $entry['post_status'] ) ? (string) $entry['post_status'] : '',
			'action'       => isset( $entry['action'] ) ? (string) $entry['action'] : '',
			'diff_count'   => isset( $entry['diffs'] ) && is_array( $entry['diffs'] ) ? count( $entry['diffs'] ) : 0,
			'audit_score'  => isset( $entry['audit']['score'] ) ? (int) $entry['audit']['score'] : 0,
			'crit_count'   => isset( $entry['audit']['critical'] ) ? count( $entry['audit']['critical'] ) : 0,
			'warn_count'   => isset( $entry['audit']['warnings'] ) ? count( $entry['audit']['warnings'] ) : 0,
			'edit_link'    => isset( $entry['edit_link'] ) ? (string) $entry['edit_link'] : '',
			'permalink'    => isset( $entry['permalink'] ) ? (string) $entry['permalink'] : '',
		)
	);
	if ( count( $log ) > 35 ) {
		$log = array_slice( $log, 0, 35 );
	}
	update_option( 'hj_importer_history_log', $log, false );
}

/**
 * Core routine: inspect and import/update one package ZIP with full options.
 *
 * @param string $zip_path Absolute ZIP path.
 * @param string $source   Human label of the source for the log.
 * @param array  $options  Import options (status_mode, duplicate_mode, target_post_type, dry_run, etc.).
 * @return array|\WP_Error Rich result descriptor.
 */
function hj_importer_import_package( $zip_path, $source = 'upload', array $options = array() ) {
	try {
		$defaults = hj_importer_get_defaults();
		$opts     = wp_parse_args(
			$options,
			array(
				'status_mode'          => $defaults['status_mode'],
				'keep_existing_status' => ! empty( $defaults['keep_existing_status'] ),
				'draft_on_deficiency'  => ! empty( $defaults['draft_on_deficiency'] ),
				'duplicate_mode'       => $defaults['duplicate_mode'],
				'target_post_type'     => 'auto', // auto | specific CPT.
				'default_cpt_fallback' => 'patient_guide',
				'manual_tax'           => array(),
				'update_image'         => ! empty( $defaults['update_image'] ),
				'normalize_content'    => ! empty( $defaults['normalize_content'] ),
				'purge_cache'          => ! empty( $defaults['purge_cache'] ),
				'dry_run'              => false,
				'file_label'           => basename( (string) $zip_path ),
			)
		);

		$manifest = hj_importer_read_manifest( $zip_path );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		$slug  = isset( $manifest['slug'] ) ? sanitize_title( (string) $manifest['slug'] ) : '';
		$title = isset( $manifest['title'] ) ? sanitize_text_field( (string) $manifest['title'] ) : '';
		if ( '' === $slug || '' === $title ) {
			return new WP_Error( 'bad_manifest', 'فیلد slug یا title در فایل article.json خالی است (' . esc_html( $opts['file_label'] ) . ').' );
		}

		// Resolve post type: explicit override > manifest > current menu fallback > patient_guide.
		$allowed_cpts = hj_importer_allowed_post_types();
		if ( 'auto' !== $opts['target_post_type'] && in_array( $opts['target_post_type'], $allowed_cpts, true ) ) {
			$post_type = $opts['target_post_type'];
		} elseif ( ! empty( $manifest['post_type'] ) && in_array( sanitize_key( (string) $manifest['post_type'] ), $allowed_cpts, true ) ) {
			$post_type = sanitize_key( (string) $manifest['post_type'] );
		} elseif ( in_array( $opts['default_cpt_fallback'], $allowed_cpts, true ) ) {
			$post_type = $opts['default_cpt_fallback'];
		} else {
			$post_type = 'patient_guide';
		}
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'patient_guide';
		}

		$extracted = hj_importer_extract_paths( (string) $zip_path );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$root_tmp = $extracted['root'];
		$dir      = $extracted['work'];

		$content_file = isset( $manifest['content_file'] ) ? basename( (string) $manifest['content_file'] ) : 'content.html';
		$html_path    = $dir . DIRECTORY_SEPARATOR . $content_file;
		if ( ! is_file( $html_path ) ) {
			hj_importer_rmdir( $root_tmp );
			return new WP_Error( 'no_content', 'فایل محتوای «' . $content_file . '» داخل بسته‌ی ZIP پیدا نشد.' );
		}

		$html = (string) file_get_contents( $html_path );
		if ( ! empty( $opts['normalize_content'] ) && function_exists( 'orsite_clean_content_disclaimers_and_tables' ) ) {
			$html = orsite_clean_content_disclaimers_and_tables( $html, 'glossary' !== $post_type );
		}
		$html = wp_kses_post( $html );

		// Extract SEO & FAQ fields.
		$meta_desc = '';
		if ( isset( $manifest['seo']['meta_description'] ) ) {
			$meta_desc = sanitize_text_field( (string) $manifest['seo']['meta_description'] );
		} elseif ( isset( $manifest['excerpt'] ) ) {
			$meta_desc = sanitize_text_field( (string) $manifest['excerpt'] );
		}
		$focus_kw      = ! empty( $manifest['seo']['focus_keyword'] ) ? sanitize_text_field( (string) $manifest['seo']['focus_keyword'] ) : '';
		$search_intent = ! empty( $manifest['seo']['search_intent'] ) ? sanitize_text_field( (string) $manifest['seo']['search_intent'] ) : '';
		$keywords      = ( ! empty( $manifest['seo']['keywords'] ) && is_array( $manifest['seo']['keywords'] ) )
			? array_values( array_filter( array_map( 'sanitize_text_field', $manifest['seo']['keywords'] ) ) )
			: array();

		$faq = array();
		if ( ! empty( $manifest['faq'] ) && is_array( $manifest['faq'] ) ) {
			foreach ( $manifest['faq'] as $item ) {
				if ( ! empty( $item['q'] ) && ! empty( $item['a'] ) ) {
					$faq[] = array(
						'q' => wp_kses_post( (string) $item['q'] ),
						'a' => wp_kses_post( (string) $item['a'] ),
					);
				}
			}
		}

		// Patient-need lists (child 0.15.0): printable questions and plain words.
		$ask_doctor  = hj_importer_list_field( $manifest, 'ask_doctor' );
		$plain_terms = hj_importer_list_field( $manifest, 'terms' );

		$tax_pairs  = hj_importer_collect_taxonomies( $manifest, $post_type, is_array( $opts['manual_tax'] ) ? $opts['manual_tax'] : array() );
		$image_info = hj_importer_inspect_package_image( $dir, $manifest );
		$audit      = hj_importer_audit_package( $manifest, $html, $image_info, $post_type, $tax_pairs );

		// Look up existing post (duplicate check).
		$existing      = hj_importer_find_existing_post( $slug, $post_type );
		$is_duplicate  = ( $existing instanceof WP_Post );
		$old_snapshot  = $is_duplicate ? hj_importer_snapshot_post( $existing ) : array();

		// Determine desired post_status.
		if ( 'publish' === $opts['status_mode'] ) {
			$desired_status = 'publish';
		} elseif ( 'manifest' === $opts['status_mode'] ) {
			$desired_status = ( isset( $manifest['post_status'] ) && 'draft' === $manifest['post_status'] ) ? 'draft' : 'publish';
		} else {
			$desired_status = 'draft';
		}

		if ( $is_duplicate && ! empty( $opts['keep_existing_status'] ) && 'create_copy' !== $opts['duplicate_mode'] ) {
			$desired_status = $existing->post_status;
		}

		$forced_draft_reason = '';
		if ( 'publish' === $desired_status && ! empty( $opts['draft_on_deficiency'] ) && ! empty( $audit['critical'] ) ) {
			$desired_status      = 'draft';
			$forced_draft_reason = 'به دلیل وجود کمبود جدی در تصویر شاخص یا متای سئو و فعال بودن «سپر کیفیت»، مقاله به‌صورت پیش‌نویس ذخیره شد.';
		}

		// Handle create_copy mode when duplicate exists.
		if ( $is_duplicate && 'create_copy' === $opts['duplicate_mode'] ) {
			$slug          = wp_unique_post_slug( $slug . '-copy', 0, 'draft', $post_type, 0 );
			$desired_status = 'draft';
		}

		$incoming_payload = array(
			'title'         => $title,
			'slug'          => $slug,
			'post_type'     => $post_type,
			'post_status'   => $desired_status,
			'html'          => $html,
			'meta_desc'     => $meta_desc,
			'focus_keyword' => $focus_kw,
			'search_intent' => $search_intent,
			'keywords'      => $keywords,
			'faq'           => $faq,
			'tax_pairs'     => $tax_pairs,
			'image_info'    => $image_info,
		);

		$diffs = ( $is_duplicate && 'create_copy' !== $opts['duplicate_mode'] )
			? hj_importer_compute_diff( $old_snapshot, $incoming_payload, ! empty( $opts['update_image'] ) )
			: array();

		// DRY-RUN PREVIEW: do not write anything to DB.
		if ( ! empty( $opts['dry_run'] ) ) {
			hj_importer_rmdir( $root_tmp );
			return array(
				'dry_run'             => true,
				'file_label'          => $opts['file_label'],
				'slug'                => $slug,
				'title'               => $title,
				'post_type'           => $post_type,
				'post_status'         => $desired_status,
				'is_duplicate'        => $is_duplicate,
				'existing_post'       => $old_snapshot,
				'action'              => 'preview',
				'diffs'               => $diffs,
				'audit'               => $audit,
				'image_info'          => $image_info,
				'tax_pairs'           => $tax_pairs,
				'dates'               => isset( $manifest['dates'] ) && is_array( $manifest['dates'] ) ? $manifest['dates'] : array(),
				'forced_draft_reason' => $forced_draft_reason,
				'post_id'             => $is_duplicate ? (int) $existing->ID : 0,
				'edit_link'           => $is_duplicate ? (string) get_edit_post_link( $existing->ID, 'raw' ) : '',
				'permalink'           => $is_duplicate ? (string) get_permalink( $existing->ID ) : '',
			);
		}

		// DUPLICATE MODE 1: Warn & Skip without overwriting.
		if ( $is_duplicate && 'warn_skip' === $opts['duplicate_mode'] ) {
			hj_importer_rmdir( $root_tmp );
			$res = array(
				'dry_run'             => false,
				'file_label'          => $opts['file_label'],
				'slug'                => $slug,
				'title'               => $title,
				'post_type'           => $existing->post_type,
				'post_status'         => $existing->post_status,
				'is_duplicate'        => true,
				'existing_post'       => $old_snapshot,
				'action'              => 'skipped_duplicate',
				'diffs'               => $diffs,
				'audit'               => $audit,
				'image_info'          => $image_info,
				'tax_pairs'           => $tax_pairs,
				'dates'               => isset( $manifest['dates'] ) && is_array( $manifest['dates'] ) ? $manifest['dates'] : array(),
				'forced_draft_reason' => '',
				'post_id'             => (int) $existing->ID,
				'image'               => 'بدون تغییر (رد شد)',
				'edit_link'           => (string) get_edit_post_link( $existing->ID, 'raw' ),
				'permalink'           => (string) get_permalink( $existing->ID ),
			);
			hj_importer_record_history( $res );
			return $res;
		}

		// DUPLICATE MODE 2 (smart_update) when ZERO changes exist: warn identical & skip redundant DB write!
		if ( $is_duplicate && 'smart_update' === $opts['duplicate_mode'] && empty( $diffs ) ) {
			hj_importer_rmdir( $root_tmp );
			$res = array(
				'dry_run'             => false,
				'file_label'          => $opts['file_label'],
				'slug'                => $slug,
				'title'               => $title,
				'post_type'           => $existing->post_type,
				'post_status'         => $existing->post_status,
				'is_duplicate'        => true,
				'existing_post'       => $old_snapshot,
				'action'              => 'identical_duplicate',
				'diffs'               => array(),
				'audit'               => $audit,
				'image_info'          => $image_info,
				'tax_pairs'           => $tax_pairs,
				'dates'               => isset( $manifest['dates'] ) && is_array( $manifest['dates'] ) ? $manifest['dates'] : array(),
				'forced_draft_reason' => '',
				'post_id'             => (int) $existing->ID,
				'image'               => $old_snapshot['thumb_id'] ? 'موجود و بدون تغییر' : 'ندارد',
				'edit_link'           => (string) get_edit_post_link( $existing->ID, 'raw' ),
				'permalink'           => (string) get_permalink( $existing->ID ),
			);
			hj_importer_record_history( $res );
			return $res;
		}

		// Perform Create or Update (or Create Copy).
		$updating_existing = ( $is_duplicate && 'create_copy' !== $opts['duplicate_mode'] );
		$postarr           = array(
			'post_type'    => $post_type,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $html,
			'post_status'  => $desired_status,
			'post_excerpt' => $meta_desc,
		);
		if ( $updating_existing ) {
			$postarr['ID'] = (int) $existing->ID;
		}

		$saved = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $saved ) ) {
			hj_importer_rmdir( $root_tmp );
			return $saved;
		}
		$post_id = (int) $saved;

		if ( '' !== $meta_desc ) {
			update_post_meta( $post_id, '_hj_meta_description', $meta_desc );
		}
		if ( '' !== $focus_kw ) {
			update_post_meta( $post_id, '_hj_focus_keyword', $focus_kw );
		}
		if ( ! empty( $keywords ) ) {
			update_post_meta( $post_id, '_hj_keywords', $keywords );
		}
		if ( '' !== $search_intent ) {
			update_post_meta( $post_id, '_hj_search_intent', $search_intent );
		}
		if ( ! empty( $faq ) ) {
			update_post_meta( $post_id, '_hj_faq', $faq );
		}
		if ( ! empty( $ask_doctor ) ) {
			update_post_meta( $post_id, '_hj_ask_doctor', $ask_doctor );
		}
		if ( ! empty( $plain_terms ) ) {
			update_post_meta( $post_id, '_hj_terms', $plain_terms );
		}
		if ( ! empty( $manifest['roadmap_id'] ) ) {
			update_post_meta( $post_id, '_hj_roadmap_id', sanitize_text_field( (string) $manifest['roadmap_id'] ) );
		}

		// Assign all collected taxonomies (supports multiple taxonomies like surgery_specialty + surgery_stage).
		$by_tax = array();
		foreach ( $tax_pairs as $tp ) {
			$by_tax[ $tp['taxonomy'] ][] = $tp['term'];
		}
		foreach ( $by_tax as $tax => $term_slugs ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$term_ids = array();
			foreach ( $term_slugs as $t_slug ) {
				$term = term_exists( $t_slug, $tax );
				if ( ! $term ) {
					$term = wp_insert_term( $t_slug, $tax, array( 'slug' => $t_slug ) );
				}
				if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
					$term_ids[] = (int) $term['term_id'];
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, $tax, false );
			}
		}

		// Featured image import/update.
		$image_status = 'بدون تصویر در بسته';
		if ( $image_info['exists'] && $image_info['allowed_ext'] ) {
			$img_entry = array(
				'file' => $image_info['declared_file'],
				'alt'  => $image_info['alt'],
			);
			$att_res = hj_importer_import_image( $post_id, $dir, $img_entry, ! empty( $opts['update_image'] ) );
			if ( is_wp_error( $att_res ) ) {
				$image_status = 'خطا: ' . $att_res->get_error_message();
			} else {
				$image_status = 'ثبت شد (ID #' . (int) $att_res . ')';
			}
		}

		// Provenance metadata.
		update_post_meta( $post_id, '_hj_import_source', sanitize_text_field( $source ) );
		update_post_meta( $post_id, '_hj_import_package', $slug );
		update_post_meta( $post_id, '_hj_imported_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_hj_audit_score', (int) $audit['score'] );
		if ( ! empty( $manifest['package_version'] ) ) {
			update_post_meta( $post_id, '_hj_import_version', (int) $manifest['package_version'] );
		}
		if ( ! empty( $manifest['dates']['updated_at'] ) ) {
			update_post_meta( $post_id, '_hj_content_updated_at', sanitize_text_field( (string) $manifest['dates']['updated_at'] ) );
		}

		hj_importer_rmdir( $root_tmp );

		if ( ! empty( $opts['purge_cache'] ) ) {
			clean_post_cache( $post_id );
			if ( has_action( 'litespeed_purge_all' ) ) {
				do_action( 'litespeed_purge_all' );
			}
		}

		$action_code = $updating_existing ? 'updated' : ( $is_duplicate ? 'created_copy' : 'created' );

		// The publish gate (child 0.16.0) may have demoted a new publish to
		// draft; report what the database actually holds, not what we asked for.
		$actual_status = (string) get_post_status( $post_id );
		if ( '' !== $actual_status && $actual_status !== $desired_status ) {
			$forced_draft_reason = trim(
				$forced_draft_reason . ' دروازهٔ انتشار (قالب ۰٫۱۶) انتشار را نپذیرفت و مقاله پیش‌نویس ماند؛ باکس «دروازهٔ انتشار» در ویرایشگر دلیل را نشان می‌دهد.'
			);
			$desired_status      = $actual_status;
		}

		$res         = array(
			'dry_run'             => false,
			'file_label'          => $opts['file_label'],
			'slug'                => (string) get_post_field( 'post_name', $post_id ),
			'title'               => $title,
			'post_type'           => $post_type,
			'post_status'         => $desired_status,
			'is_duplicate'        => $is_duplicate,
			'existing_post'       => $old_snapshot,
			'post_id'             => $post_id,
			'action'              => $action_code,
			'diffs'               => $diffs,
			'audit'               => $audit,
			'image_info'          => $image_info,
			'tax_pairs'           => $tax_pairs,
			'dates'               => isset( $manifest['dates'] ) && is_array( $manifest['dates'] ) ? $manifest['dates'] : array(),
			'forced_draft_reason' => $forced_draft_reason,
			'image'               => $image_status,
			'edit_link'           => (string) get_edit_post_link( $post_id, 'raw' ),
			'permalink'           => (string) get_permalink( $post_id ),
		);
		hj_importer_record_history( $res );
		return $res;
	} catch ( \Throwable $e ) {
		return new WP_Error( 'importer_exception', 'خطای غیرمنتظره هنگام پردازش بسته: ' . $e->getMessage() );
	}
}

/**
 * Output FAQPage JSON-LD from the imported FAQ payload.
 *
 * @return void
 */
function hj_importer_faq_schema() {
	if ( ! is_singular() ) {
		return;
	}
	$faq = get_post_meta( get_queried_object_id(), '_hj_faq', true );
	if ( ! is_array( $faq ) || empty( $faq ) ) {
		return;
	}
	$entities = array();
	foreach ( $faq as $item ) {
		if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
			continue;
		}
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $item['q'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item['a'] ),
			),
		);
	}
	if ( ! $entities ) {
		return;
	}
	$data = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}
add_action( 'wp_head', 'hj_importer_faq_schema', 25 );

/**
 * Handle importer POST actions (upload, preview, bundled, save_defaults, clear_log).
 *
 * @return void
 */
function hj_importer_handle_request() {
	if ( ! is_admin() || empty( $_POST['hj_importer_action'] ) ) {
		return;
	}
	if ( ! current_user_can( HJ_IMPORTER_CAP ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ) );
	}
	check_admin_referer( 'hj_importer' );

	$action     = sanitize_key( (string) wp_unslash( $_POST['hj_importer_action'] ) );
	$return_cpt = ! empty( $_POST['return_cpt'] ) ? sanitize_key( (string) wp_unslash( $_POST['return_cpt'] ) ) : '';
	if ( ! in_array( $return_cpt, hj_importer_allowed_post_types(), true ) ) {
		$return_cpt = '';
	}
	$redirect_url = hj_importer_admin_url( $return_cpt );

	if ( 'clear_log' === $action ) {
		delete_option( 'hj_importer_history_log' );
		wp_safe_redirect( add_query_arg( 'log_cleared', 1, $redirect_url ) );
		exit;
	}

	$status_mode = ! empty( $_POST['status_mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['status_mode'] ) ) : 'draft';
	if ( ! in_array( $status_mode, array( 'draft', 'publish', 'manifest' ), true ) ) {
		$status_mode = 'draft';
	}

	$duplicate_mode = ! empty( $_POST['duplicate_mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['duplicate_mode'] ) ) : 'smart_update';
	if ( ! in_array( $duplicate_mode, array( 'smart_update', 'warn_skip', 'create_copy' ), true ) ) {
		$duplicate_mode = 'smart_update';
	}

	$target_post_type = ! empty( $_POST['target_post_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['target_post_type'] ) ) : 'auto';
	if ( 'auto' !== $target_post_type && ! in_array( $target_post_type, hj_importer_allowed_post_types(), true ) ) {
		$target_post_type = 'auto';
	}

	$keep_existing_status = ! empty( $_POST['keep_existing_status'] ) ? 1 : 0;
	$draft_on_deficiency  = ! empty( $_POST['draft_on_deficiency'] ) ? 1 : 0;
	$update_image         = ! empty( $_POST['update_image'] ) ? 1 : 0;
	$normalize_content    = ! empty( $_POST['normalize_content'] ) ? 1 : 0;
	$purge_cache          = ! empty( $_POST['purge_cache'] ) ? 1 : 0;
	$is_dry_run           = ! empty( $_POST['hj_dry_run_submit'] ) || ! empty( $_POST['dry_run'] );

	if ( 'save_defaults' === $action || ! empty( $_POST['save_as_default'] ) ) {
		update_option(
			'hj_importer_default_options',
			array(
				'status_mode'          => $status_mode,
				'keep_existing_status' => $keep_existing_status,
				'draft_on_deficiency'  => $draft_on_deficiency,
				'duplicate_mode'       => $duplicate_mode,
				'update_image'         => $update_image,
				'normalize_content'    => $normalize_content,
				'purge_cache'          => $purge_cache,
			),
			false
		);
		if ( 'save_defaults' === $action ) {
			wp_safe_redirect( add_query_arg( 'saved_defaults', 1, $redirect_url ) );
			exit;
		}
	}

	$manual_tax = array();
	if ( ! empty( $_POST['manual_tax'] ) && is_array( $_POST['manual_tax'] ) ) {
		foreach ( wp_unslash( $_POST['manual_tax'] ) as $tx_k => $tx_v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$tx_k = sanitize_key( (string) $tx_k );
			$tx_v = sanitize_title( (string) $tx_v );
			if ( '' !== $tx_v ) {
				$manual_tax[ $tx_k ] = $tx_v;
			}
		}
	}

	$import_opts = array(
		'status_mode'          => $status_mode,
		'keep_existing_status' => (bool) $keep_existing_status,
		'draft_on_deficiency'  => (bool) $draft_on_deficiency,
		'duplicate_mode'       => $duplicate_mode,
		'target_post_type'     => $target_post_type,
		'default_cpt_fallback' => '' !== $return_cpt ? $return_cpt : 'patient_guide',
		'manual_tax'           => $manual_tax,
		'update_image'         => (bool) $update_image,
		'normalize_content'    => (bool) $normalize_content,
		'purge_cache'          => (bool) $purge_cache,
		'dry_run'              => $is_dry_run,
	);

	$results = array();

	if ( 'bundled' === $action && ! empty( $_POST['package'] ) ) {
		$name = basename( sanitize_file_name( (string) wp_unslash( $_POST['package'] ) ) );
		if ( ! preg_match( '/^article-package-[a-z0-9-]+\.zip$/', $name ) ) {
			$results[] = new WP_Error( 'bad_name', 'نام بسته‌ی همراه نامعتبر است.' );
		} else {
			$path = get_stylesheet_directory() . '/content-imports/' . $name;
			if ( ! is_file( $path ) ) {
				$results[] = new WP_Error( 'missing', 'بسته‌ی همراه قالب پیدا نشد: ' . $name );
			} else {
				$import_opts['file_label'] = $name;
				$results[]                 = hj_importer_import_package( $path, 'bundled-theme', $import_opts );
			}
		}
	}

	if ( 'upload' === $action && ! empty( $_FILES['hj_packages']['name'] ) ) {
		$files = $_FILES['hj_packages']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$total = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
		for ( $i = 0; $i < $total; $i++ ) {
			if ( empty( $files['name'][ $i ] ) ) {
				continue;
			}
			if ( UPLOAD_ERR_OK !== (int) $files['error'][ $i ] ) {
				$results[] = new WP_Error( 'upload_err', 'آپلود فایل «' . sanitize_file_name( (string) $files['name'][ $i ] ) . '» ناموفق بود (کد خطا: ' . (int) $files['error'][ $i ] . ').' );
				continue;
			}
			$name = sanitize_file_name( (string) $files['name'][ $i ] );
			if ( 'zip' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				$results[] = new WP_Error( 'ext', 'فقط فایل با پسوند .zip پذیرفته می‌شود: ' . $name );
				continue;
			}
			$import_opts['file_label'] = $name;
			$results[]                 = hj_importer_import_package( (string) $files['tmp_name'][ $i ], 'manual-upload', $import_opts );
		}
	}

	set_transient( 'hj_importer_results_' . get_current_user_id(), $results, 300 );
	wp_safe_redirect( add_query_arg( 'done', 1, $redirect_url ) );
	exit;
}
add_action( 'admin_post_hj_article_import', 'hj_importer_handle_request' );

/**
 * Order in which bundled packages are presented in the admin table.
 *
 * @return string[]
 */
function hj_importer_bundled_order() {
	return array(
		'what-is-operating-room',
		'surgical-care-team',
		'patient-journey',
		'glossary-surgical-terms',
		'operating-room-moment-by-moment',
	);
}

/**
 * List bundled packages with their manifest titles and live status.
 *
 * @return array<int,array<string,mixed>>
 */
function hj_importer_bundled_list() {
	$dir = get_stylesheet_directory() . '/content-imports';
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$files = glob( $dir . '/article-package-*.zip' );
	if ( ! is_array( $files ) ) {
		return $out;
	}
	foreach ( $files as $path ) {
		$manifest = hj_importer_read_manifest( $path );
		$slug     = is_wp_error( $manifest ) ? '' : (string) $manifest['slug'];
		$existing = $slug ? hj_importer_find_existing_post( $slug, 'patient_guide' ) : null;
		$out[]    = array(
			'file'        => basename( $path ),
			'slug'        => $slug,
			'title'       => is_wp_error( $manifest ) ? '(' . $manifest->get_error_message() . ')' : (string) $manifest['title'],
			'published'   => ( $existing && 'publish' === $existing->post_status ),
			'post_status' => $existing ? $existing->post_status : '',
			'edit_link'   => $existing ? (string) get_edit_post_link( $existing->ID, 'raw' ) : '',
			'order'       => array_search( $slug, hj_importer_bundled_order(), true ),
		);
	}
	usort(
		$out,
		static function ( $a, $b ) {
			$oa = false === $a['order'] ? 999 : $a['order'];
			$ob = false === $b['order'] ? 999 : $b['order'];
			return $oa === $ob ? strcmp( $a['slug'], $b['slug'] ) : $oa - $ob;
		}
	);
	return $out;
}

/**
 * Render rich result report card for a single imported/previewed package.
 *
 * @param array|\WP_Error $res Import result item.
 * @return void
 */
function hj_importer_render_result_card( $res ): void {
	if ( is_wp_error( $res ) ) {
		?>
		<div class="hj-imp-card hj-imp-card--error">
			<div class="hj-imp-banner hj-imp-banner--error">
				<strong>❌ خطا در پردازش بسته:</strong> <?php echo esc_html( $res->get_error_message() ); ?>
			</div>
		</div>
		<?php
		return;
	}

	$cpt_map     = hj_importer_post_type_map();
	$cpt_label   = isset( $cpt_map[ $res['post_type'] ] ) ? $cpt_map[ $res['post_type'] ]['label'] : $res['post_type'];
	$cpt_color   = isset( $cpt_map[ $res['post_type'] ] ) ? $cpt_map[ $res['post_type'] ]['color'] : '#06265C';
	$status_text = ( 'publish' === $res['post_status'] ) ? '🟢 منتشرشده (Publish)' : '📝 پیش‌نویس (Draft)';
	$action      = $res['action'];
	$audit       = $res['audit'];
	$metrics     = $audit['metrics'];
	$img         = $res['image_info'];
	$diffs       = isset( $res['diffs'] ) && is_array( $res['diffs'] ) ? $res['diffs'] : array();

	$banner_class = 'hj-imp-banner--success';
	$banner_title = '';
	if ( 'preview' === $action ) {
		$banner_class = 'hj-imp-banner--info';
		$banner_title = '🔍 پیش‌نمایش و ممیزی بسته (بدون ذخیره در دیتابیس)';
	} elseif ( 'skipped_duplicate' === $action ) {
		$banner_class = 'hj-imp-banner--warn';
		$banner_title = '⚠️ هشدار تکراری بودن: این مقاله از قبل در سایت وجود دارد و طبق تنظیم شما بدون تغییر رد شد.';
	} elseif ( 'identical_duplicate' === $action ) {
		$banner_class = 'hj-imp-banner--warn';
		$banner_title = '⚠️ هشدار تکراری بدون تغییر: این بسته قبلاً ایمپورت شده و تمام محتوای متن، سئو، FAQ و تصویر شاخص آن ۱۰۰٪ با نوشته‌ی فعلی یکسان است.';
	} elseif ( 'updated' === $action ) {
		$banner_class = 'hj-imp-banner--update';
		$banner_title = sprintf( '🔄 مقاله‌ی تکراری شناسایی و با موفقیت آپدیت شد (%s مورد تغییر در نوشته اعمال شد)', number_format_i18n( count( $diffs ) ) );
	} elseif ( 'created_copy' === $action ) {
		$banner_class = 'hj-imp-banner--success';
		$banner_title = '📑 مقاله تکراری بود؛ نوشته‌ی قبلی حفظ شد و یک نسخه‌ی پیش‌نویس جداگانه ساخته شد.';
	} else {
		$banner_class = 'hj-imp-banner--success';
		$banner_title = '✅ مقاله‌ی جدید با موفقیت ایمپورت شد.';
	}

	$score_color = $audit['score'] >= 85 ? '#15803D' : ( $audit['score'] >= 65 ? '#B45309' : '#B91C1C' );
	?>
	<div class="hj-imp-card">
		<div class="hj-imp-banner <?php echo esc_attr( $banner_class ); ?>">
			<div class="hj-imp-banner__main">
				<div class="hj-imp-banner__title"><?php echo esc_html( $banner_title ); ?></div>
				<div class="hj-imp-banner__sub">
					<strong><?php echo esc_html( $res['title'] ); ?></strong>
					<code><?php echo esc_html( $res['slug'] ); ?></code>
					<span class="hj-imp-pill" style="background:<?php echo esc_attr( $cpt_color ); ?>;color:#fff"><?php echo esc_html( $cpt_label ); ?></span>
					<span class="hj-imp-pill hj-imp-pill--light"><?php echo esc_html( $status_text ); ?></span>
					<?php if ( ! empty( $res['file_label'] ) ) : ?>
						<span class="hj-imp-pill hj-imp-pill--muted">📦 <?php echo esc_html( $res['file_label'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $res['forced_draft_reason'] ) ) : ?>
					<div class="hj-imp-forced-note">🛡️ <?php echo esc_html( $res['forced_draft_reason'] ); ?></div>
				<?php endif; ?>
			</div>
			<div class="hj-imp-banner__actions">
				<div class="hj-imp-score" style="border-color:<?php echo esc_attr( $score_color ); ?>;color:<?php echo esc_attr( $score_color ); ?>">
					<span>امتیاز سئو و تصویر</span>
					<strong><?php echo esc_html( number_format_i18n( $audit['score'] ) ); ?>٪</strong>
				</div>
				<?php if ( ! empty( $res['edit_link'] ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $res['edit_link'] ); ?>">✏️ ویرایش نوشته</a>
				<?php endif; ?>
				<?php if ( ! empty( $res['permalink'] ) ) : ?>
					<a class="button" href="<?php echo esc_url( $res['permalink'] ); ?>" target="_blank" rel="noopener">👁️ مشاهده</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $res['is_duplicate'] ) && ! empty( $res['existing_post'] ) && in_array( $action, array( 'skipped_duplicate', 'preview' ), true ) ) : ?>
			<div class="hj-imp-dup-box">
				<strong>📌 اطلاعات نوشته‌ی از پیش موجود در سایت:</strong>
				عنوان فعلی: «<?php echo esc_html( $res['existing_post']['title'] ); ?>» |
				شناسه: <code>#<?php echo (int) $res['existing_post']['id']; ?></code> |
				وضعیت فعلی: <strong><?php echo esc_html( $res['existing_post']['post_status'] ); ?></strong> |
				آخرین ویرایش: <?php echo esc_html( $res['existing_post']['modified'] ); ?> |
				<?php echo empty( $diffs ) ? '✅ محتوای بسته کاملاً با نوشته‌ی موجود یکسان است.' : '🔄 این بسته در ' . esc_html( number_format_i18n( count( $diffs ) ) ) . ' بخش با نوشته‌ی فعلی تفاوت دارد.'; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $diffs ) ) : ?>
			<div class="hj-imp-section">
				<h4>🔄 جزئیات تغییرات شناسایی‌شده نسبت به نسخه‌ی قبلی (<?php echo esc_html( number_format_i18n( count( $diffs ) ) ); ?> مورد)</h4>
				<table class="widefat striped hj-imp-diff-table">
					<thead>
						<tr>
							<th style="width:22%">بخش تغییریافته</th>
							<th style="width:39%">مقدار قبلی در سایت</th>
							<th style="width:39%">مقدار جدید در بسته</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $diffs as $d ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $d['field'] ); ?></strong></td>
								<td class="hj-diff-old"><?php echo esc_html( $d['before'] ); ?></td>
								<td class="hj-diff-new"><?php echo esc_html( $d['after'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $audit['critical'] ) || ! empty( $audit['warnings'] ) ) : ?>
			<div class="hj-imp-section">
				<h4>🔎 اعلام کمبودها و نکات بهبود تصویر و سئو</h4>
				<ul class="hj-imp-audit-list">
					<?php foreach ( $audit['critical'] as $msg ) : ?>
						<li class="hj-audit-item hj-audit-item--crit">❌ <strong>کمبود مهم:</strong> <?php echo esc_html( $msg ); ?></li>
					<?php endforeach; ?>
					<?php foreach ( $audit['warnings'] as $msg ) : ?>
						<li class="hj-audit-item hj-audit-item--warn">⚠️ <strong>هشدار سئو/تصویر:</strong> <?php echo esc_html( $msg ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php else : ?>
			<div class="hj-imp-section">
				<div class="hj-audit-perfect">🌟 عالی! هیچ کمبودی در تصویر شاخص، متای سئو، اسکیمای FAQ و ساختار تیترهای این بسته دیده نشد.</div>
			</div>
		<?php endif; ?>

		<details class="hj-imp-details">
			<summary>📊 نمایش شناسنامه‌ی کامل بسته، آمار محتوا، جزئیات تصویر شاخص و چک‌لیست‌های موفق (<?php echo esc_html( number_format_i18n( count( $audit['passed'] ) ) ); ?> مورد تأییدشده)</summary>
			<div class="hj-imp-meta-grid">
				<div class="hj-imp-meta-box">
					<h5>📝 آمار محتوا و ساختار</h5>
					<ul>
						<li><strong>شمار کلمات:</strong> <?php echo esc_html( number_format_i18n( $metrics['word_count'] ) ); ?> کلمه (~<?php echo esc_html( number_format_i18n( $metrics['read_minutes'] ) ); ?> دقیقه مطالعه)</li>
						<li><strong>تیترها:</strong> <?php echo (int) $metrics['h2_count']; ?> زیرتیتر H2 · <?php echo (int) $metrics['h3_count']; ?> زیرتیتر H3</li>
						<li><strong>جدول‌های HTML:</strong> <?php echo (int) $metrics['table_count']; ?> جدول</li>
						<li><strong>لینک‌ها:</strong> <?php echo (int) $metrics['internal_links']; ?> لینک داخلی · <?php echo (int) $metrics['external_links']; ?> لینک خارجی</li>
						<li><strong>پرسش‌های متداول (FAQ):</strong> <?php echo (int) $metrics['faq_count']; ?> پرسش</li>
						<li><strong>دسته‌بندی‌ها:</strong>
							<?php
							if ( empty( $res['tax_pairs'] ) ) {
								echo '— ثبت نشده';
							} else {
								$tp_labels = array();
								foreach ( $res['tax_pairs'] as $tp ) {
									$tp_labels[] = $tp['taxonomy'] . '/' . $tp['term'];
								}
								echo '<code>' . esc_html( implode( ' , ', $tp_labels ) ) . '</code>';
							}
							?>
						</li>
					</ul>
				</div>
				<div class="hj-imp-meta-box">
					<h5>🖼️ مشخصات تصویر شاخص و سئو</h5>
					<ul>
						<li><strong>فایل تصویر:</strong> <?php echo $img['exists'] ? '<code>' . esc_html( $img['declared_file'] ) . '</code> (' . esc_html( strtoupper( $img['ext'] ) ) . ')' : '❌ موجود نیست'; ?></li>
						<li><strong>ابعاد و حجم:</strong> <?php echo $img['exists'] ? esc_html( $img['width'] . '×' . $img['height'] . ' پیکسل · ' . $img['size_kb'] . ' KB' ) : '—'; ?></li>
						<li><strong>متن alt تصویر:</strong> <?php echo '' !== $img['alt'] ? esc_html( $img['alt'] ) : '❌ خالی'; ?></li>
						<li><strong>طول عنوان / متا:</strong> عنوان <?php echo (int) $metrics['title_len']; ?> کاراکتر · متا <?php echo (int) $metrics['meta_len']; ?> کاراکتر</li>
						<li><strong>کلیدواژه کانونی:</strong> <?php echo '' !== $metrics['focus_keyword'] ? esc_html( $metrics['focus_keyword'] ) : '— ثبت نشده'; ?></li>
						<li><strong>نیت جستجو:</strong> <?php echo '' !== $metrics['search_intent'] ? esc_html( $metrics['search_intent'] ) : '— ثبت نشده'; ?></li>
					</ul>
				</div>
			</div>
			<?php if ( ! empty( $audit['passed'] ) ) : ?>
				<div class="hj-imp-passed-wrap">
					<?php foreach ( $audit['passed'] as $ok_msg ) : ?>
						<span class="hj-passed-chip">✓ <?php echo esc_html( $ok_msg ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</details>
	</div>
	<?php
}

/**
 * Render the upgraded multi-CPT admin importer page.
 *
 * @return void
 */
function hj_importer_render_page() {
	if ( ! current_user_can( HJ_IMPORTER_CAP ) ) {
		return;
	}

	$cpt_map     = hj_importer_post_type_map();
	$current_cpt = hj_importer_current_cpt_context();
	$defaults    = hj_importer_get_defaults();
	$action_url  = admin_url( 'admin-post.php' );
	$dash_url    = add_query_arg( array( 'page' => 'hj-content-dashboard' ), admin_url( 'admin.php' ) );

	$results = get_transient( 'hj_importer_results_' . get_current_user_id() );
	if ( false !== $results ) {
		delete_transient( 'hj_importer_results_' . get_current_user_id() );
	}

	$page_heading = 'مرکز هوشمند ایمپورت مقالات و واژه‌نامه (ZIP)';
	if ( '' !== $current_cpt && isset( $cpt_map[ $current_cpt ] ) ) {
		$page_heading = $cpt_map[ $current_cpt ]['icon'] . ' ایمپورت بسته‌ی ZIP — بخش «' . $cpt_map[ $current_cpt ]['label'] . '»';
	}

	// Preload taxonomy terms for optional manual assignment when a specific CPT is active.
	$context_taxonomies = array();
	if ( '' !== $current_cpt ) {
		$tax_objects = get_object_taxonomies( $current_cpt, 'objects' );
		foreach ( $tax_objects as $tx_slug => $tx_obj ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tx_slug,
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$context_taxonomies[ $tx_slug ] = array(
					'label' => $tx_obj->labels->singular_name,
					'terms' => $terms,
				);
			}
		}
	}

	$history = get_option( 'hj_importer_history_log', array() );
	if ( ! is_array( $history ) ) {
		$history = array();
	}
	?>
	<style>
		.hj-imp-wrap { max-width: 1180px; direction: rtl; font-family: Tahoma, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.hj-imp-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; background:#fff; border:1px solid #dcdcde; border-right:5px solid #06265C; border-radius:10px; padding:16px 20px; margin:16px 0; box-shadow:0 2px 6px rgba(6,38,92,.06); }
		.hj-imp-header h1 { margin:0 0 6px; font-size:20px; color:#06265C; padding:0; }
		.hj-imp-header p { margin:0; color:#4b5563; font-size:13px; }
		.hj-imp-tabs { display:flex; flex-wrap:wrap; gap:8px; margin:14px 0 18px; }
		.hj-imp-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; background:#fff; border:1px solid #d1d5db; color:#1f2937; text-decoration:none; font-weight:600; font-size:13px; transition:all .15s ease; }
		.hj-imp-tab:hover { border-color:#06265C; color:#06265C; }
		.hj-imp-tab.is-active { background:#06265C; border-color:#06265C; color:#fff; box-shadow:0 2px 6px rgba(6,38,92,.2); }
		.hj-imp-tab-count { background:rgba(0,0,0,.08); padding:1px 7px; border-radius:999px; font-size:11px; }
		.hj-imp-tab.is-active .hj-imp-tab-count { background:rgba(255,255,255,.22); color:#fff; }
		.hj-imp-panel { background:#fff; border:1px solid #dcdcde; border-radius:10px; padding:20px 22px; margin-bottom:20px; box-shadow:0 2px 6px rgba(6,38,92,.05); }
		.hj-imp-panel h2 { margin-top:0; font-size:16px; color:#06265C; border-bottom:1px solid #eef2f7; padding-bottom:10px; }
		.hj-imp-grid-2 { display:grid; grid-template-columns:repeat(auto-fit, minmax(310px, 1fr)); gap:16px; margin-bottom:16px; }
		.hj-imp-opt-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; }
		.hj-imp-opt-box h3 { margin:0 0 10px; font-size:14px; color:#0f172a; }
		.hj-imp-radio { display:block; margin:8px 0; font-size:13px; line-height:1.6; cursor:pointer; }
		.hj-imp-radio small { display:block; color:#64748b; margin-right:22px; font-size:12px; }
		.hj-imp-check { display:block; margin:8px 0; font-size:13px; cursor:pointer; }
		.hj-imp-dropzone { border:2px dashed #94a3b8; background:#f8fafc; border-radius:10px; padding:18px; text-align:center; margin-bottom:16px; }
		.hj-imp-dropzone:hover { border-color:#06265C; background:#f1f5f9; }
		.hj-imp-actions-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding-top:8px; border-top:1px solid #eef2f7; }
		.hj-imp-card { background:#fff; border:1px solid #cbd5e1; border-radius:10px; margin-bottom:18px; overflow:hidden; box-shadow:0 3px 10px rgba(15,23,42,.06); }
		.hj-imp-card--error { border-color:#ef4444; }
		.hj-imp-banner { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; padding:14px 18px; border-bottom:1px solid #e2e8f0; }
		.hj-imp-banner--success { background:#ecfdf5; border-right:5px solid #16a34a; }
		.hj-imp-banner--update { background:#eff6ff; border-right:5px solid #2563eb; }
		.hj-imp-banner--warn { background:#fffbeb; border-right:5px solid #d97706; }
		.hj-imp-banner--info { background:#f0f9ff; border-right:5px solid #0284c7; }
		.hj-imp-banner--error { background:#fef2f2; border-right:5px solid #dc2626; color:#991b1b; }
		.hj-imp-banner__title { font-size:14px; font-weight:700; color:#0f172a; margin-bottom:6px; }
		.hj-imp-banner__sub { display:flex; flex-wrap:wrap; align-items:center; gap:8px; font-size:13px; }
		.hj-imp-banner__actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
		.hj-imp-pill { display:inline-block; padding:2px 9px; border-radius:999px; font-size:11.5px; font-weight:600; }
		.hj-imp-pill--light { background:#fff; border:1px solid #cbd5e1; color:#1e293b; }
		.hj-imp-pill--muted { background:#e2e8f0; color:#334155; }
		.hj-imp-score { background:#fff; border:2px solid #15803d; border-radius:8px; padding:4px 10px; text-align:center; line-height:1.2; }
		.hj-imp-score span { display:block; font-size:10px; color:#64748b; }
		.hj-imp-score strong { font-size:15px; }
		.hj-imp-forced-note { margin-top:8px; background:#fef3c7; color:#92400e; padding:6px 10px; border-radius:6px; font-size:12px; font-weight:600; }
		.hj-imp-dup-box { background:#fff7ed; border-bottom:1px solid #fed7aa; padding:10px 18px; font-size:12.5px; color:#7c2d12; }
		.hj-imp-section { padding:14px 18px; border-bottom:1px solid #f1f5f9; }
		.hj-imp-section h4 { margin:0 0 10px; font-size:13.5px; color:#06265C; }
		.hj-diff-old { color:#991b1b; background:#fef2f2 !important; }
		.hj-diff-new { color:#166534; background:#f0fdf4 !important; font-weight:600; }
		.hj-imp-audit-list { margin:0; padding:0; list-style:none; display:grid; gap:6px; }
		.hj-audit-item { padding:7px 11px; border-radius:6px; font-size:12.5px; }
		.hj-audit-item--crit { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
		.hj-audit-item--warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
		.hj-audit-perfect { background:#ecfdf5; border:1px solid #bbf7d0; color:#166534; padding:9px 12px; border-radius:6px; font-weight:600; font-size:13px; }
		.hj-imp-details { padding:10px 18px; background:#f8fafc; }
		.hj-imp-details summary { cursor:pointer; font-weight:600; font-size:12.5px; color:#06265C; }
		.hj-imp-meta-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px; margin-top:12px; }
		.hj-imp-meta-box { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; }
		.hj-imp-meta-box h5 { margin:0 0 8px; font-size:13px; color:#06265C; }
		.hj-imp-meta-box ul { margin:0; font-size:12px; line-height:1.8; }
		.hj-imp-passed-wrap { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
		.hj-passed-chip { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:999px; font-size:11.5px; }
	</style>

	<div class="wrap hj-imp-wrap">
		<div class="hj-imp-header">
			<div>
				<h1><?php echo esc_html( $page_heading ); ?></h1>
				<p>پشتیبانی کامل از هر ۵ بخش سایت با کنترل انتشار/پیش‌نویس، تشخیص مقالات تکراری، گزارش دقیق تغییرات آپدیت و ممیزی خودکار تصویر شاخص و سئو.</p>
			</div>
			<div>
				<a class="button button-secondary" href="<?php echo esc_url( $dash_url ); ?>">📊 مشاهده‌ی داشبورد اهداف و درصد پیشرفت</a>
			</div>
		</div>

		<?php // Group switcher tabs across all 5 CPTs + Central Hub. ?>
		<nav class="hj-imp-tabs" aria-label="بخش‌های ایمپورت">
			<a class="hj-imp-tab <?php echo '' === $current_cpt ? 'is-active' : ''; ?>" href="<?php echo esc_url( hj_importer_admin_url( '' ) ); ?>">
				🌐 همه‌ی بخش‌ها (مرکز یکپارچه)
			</a>
			<?php foreach ( $cpt_map as $cpt_slug => $meta ) : ?>
				<?php
				$cnt_obj = wp_count_posts( $cpt_slug );
				$pub_cnt = isset( $cnt_obj->publish ) ? (int) $cnt_obj->publish : 0;
				$drf_cnt = isset( $cnt_obj->draft ) ? (int) $cnt_obj->draft : 0;
				?>
				<a class="hj-imp-tab <?php echo $current_cpt === $cpt_slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( hj_importer_admin_url( $cpt_slug ) ); ?>">
					<span><?php echo esc_html( $meta['icon'] . ' ' . $meta['label'] ); ?></span>
					<span class="hj-imp-tab-count"><?php echo esc_html( number_format_i18n( $pub_cnt ) ); ?> منتشر<?php echo $drf_cnt > 0 ? ' + ' . esc_html( number_format_i18n( $drf_cnt ) ) . ' پیش‌نویس' : ''; ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( ! empty( $_GET['saved_defaults'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>✅ تنظیمات پیش‌فرض ایمپورت با موفقیت ذخیره شد.</p></div>
		<?php endif; ?>
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( ! empty( $_GET['log_cleared'] ) ) : ?>
			<div class="notice notice-info is-dismissible"><p>تاریخچه‌ی گزارش ایمپورت‌ها پاک‌سازی شد.</p></div>
		<?php endif; ?>

		<?php if ( is_array( $results ) && ! empty( $results ) ) : ?>
			<div style="margin:16px 0">
				<?php foreach ( $results as $res_item ) : ?>
					<?php hj_importer_render_result_card( $res_item ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="hj-imp-panel">
			<h2>📤 بارگذاری و ایمپورت بسته‌ی مقاله یا واژه‌نامه (ZIP)</h2>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="hj_article_import">
				<input type="hidden" name="hj_importer_action" value="upload">
				<input type="hidden" name="return_cpt" value="<?php echo esc_attr( $current_cpt ); ?>">
				<?php wp_nonce_field( 'hj_importer' ); ?>

				<div class="hj-imp-dropzone">
					<p style="margin:0 0 10px;font-size:14px;font-weight:700;color:#06265C">فایل یا فایل‌های ZIP بسته را انتخاب کنید (امکان انتخاب هم‌زمان چند فایل ZIP)</p>
					<input type="file" id="hj_packages" name="hj_packages[]" accept=".zip" multiple required style="font-size:13px">
					<p class="description" style="margin:8px 0 0">ساختار استاندارد هر ZIP: <code>article.json</code> + <code>content.html</code> + <code>featured.webp</code> (۱۲۰۰×۶۷۵ پیکسل)</p>
				</div>

				<div class="hj-imp-grid-2">
					<div class="hj-imp-opt-box">
						<h3>۱) وضعیت انتشار مقاله هنگام ایمپورت</h3>
						<label class="hj-imp-radio">
							<input type="radio" name="status_mode" value="draft" <?php checked( $defaults['status_mode'], 'draft' ); ?>>
							<strong>📝 فقط ذخیره در پیش‌نویس (Draft — پیشنهادی)</strong>
							<small>مقاله منتشر نمی‌شود و ابتدا برای بازبینی در پیش‌نویس‌های بخش قرار می‌گیرد.</small>
						</label>
						<label class="hj-imp-radio">
							<input type="radio" name="status_mode" value="publish" <?php checked( $defaults['status_mode'], 'publish' ); ?>>
							<strong>🟢 انتشار فوری در سایت زنده (Publish)</strong>
							<small>مقاله بلافاصله پس از ایمپورت برای مخاطبان قابل مشاهده می‌شود.</small>
						</label>
						<label class="hj-imp-radio">
							<input type="radio" name="status_mode" value="manifest" <?php checked( $defaults['status_mode'], 'manifest' ); ?>>
							<strong>📄 مطابق وضعیت ثبت‌شده در فایل <code>article.json</code></strong>
							<small>از مقدار <code>post_status</code> داخل خود بسته پیروی می‌کند.</small>
						</label>
						<hr style="border:0;border-top:1px solid #e2e8f0;margin:10px 0">
						<label class="hj-imp-check">
							<input type="checkbox" name="keep_existing_status" value="1" <?php checked( ! empty( $defaults['keep_existing_status'] ) ); ?>>
							در صورت آپدیت مقاله‌ی از پیش موجود، وضعیت فعلی آن (منتشرشده/پیش‌نویس) تغییر نکند
						</label>
						<label class="hj-imp-check">
							<input type="checkbox" name="draft_on_deficiency" value="1" <?php checked( ! empty( $defaults['draft_on_deficiency'] ) ); ?>>
							<strong>سپر کیفیت:</strong> اگر تصویر شاخص یا متای سئو کسری مهم داشت، خودکار پیش‌نویس شود
						</label>
					</div>

					<div class="hj-imp-opt-box">
						<h3>۲) رفتار با مقاله‌های تکراری (هم‌نامک)</h3>
						<label class="hj-imp-radio">
							<input type="radio" name="duplicate_mode" value="smart_update" <?php checked( $defaults['duplicate_mode'], 'smart_update' ); ?>>
							<strong>🔄 هشدار تکراری + آپدیت هوشمند با گزارش دقیق تغییرات</strong>
							<small>اگر بدون تغییر بود اطلاع می‌دهد؛ و اگر تغییری داشت آپدیت کرده و جدول تغییرات (قبل ← بعد) را اعلام می‌کند.</small>
						</label>
						<label class="hj-imp-radio">
							<input type="radio" name="duplicate_mode" value="warn_skip" <?php checked( $defaults['duplicate_mode'], 'warn_skip' ); ?>>
							<strong>⚠️ فقط نمایش هشدار تکراری بودن و عدم بازنویسی</strong>
							<small>در صورت وجود قبلی، هشدار تکراری بودن می‌دهد و نوشته‌ی موجود را دست‌نخورده نگه می‌دارد.</small>
						</label>
						<label class="hj-imp-radio">
							<input type="radio" name="duplicate_mode" value="create_copy" <?php checked( $defaults['duplicate_mode'], 'create_copy' ); ?>>
							<strong>📑 حفظ نوشته‌ی قبلی و ساخت پیش‌نویس جدید جداگانه</strong>
							<small>بدون دست‌زدن به مقاله‌ی قبلی، یک پیش‌نویس جدید با نامک متمایز (<code>-copy</code>) می‌سازد.</small>
						</label>
					</div>
				</div>

				<div class="hj-imp-grid-2">
					<div class="hj-imp-opt-box">
						<h3>۳) بخش (گروه) مقصد و دسته‌بندی</h3>
						<p style="margin:0 0 8px">
							<label for="hj_target_post_type" style="font-weight:600;display:block;margin-bottom:4px">گروه مقصد مقاله:</label>
							<select id="hj_target_post_type" name="target_post_type" style="width:100%;max-width:380px">
								<option value="auto" <?php selected( true ); ?>>
									تشخیص خودکار از article.json<?php echo '' !== $current_cpt && isset( $cpt_map[ $current_cpt ] ) ? ' (پیش‌فرض فعلی: ' . esc_html( $cpt_map[ $current_cpt ]['label'] ) . ')' : ''; ?>
								</option>
								<?php foreach ( $cpt_map as $cpt_k => $cpt_m ) : ?>
									<option value="<?php echo esc_attr( $cpt_k ); ?>">
										اجبار به قرارگیری در بخش: <?php echo esc_html( $cpt_m['label'] ); ?> (<?php echo esc_html( $cpt_k ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</p>
						<?php if ( ! empty( $context_taxonomies ) ) : ?>
							<?php foreach ( $context_taxonomies as $tx_slug => $tx_data ) : ?>
								<p style="margin:8px 0 0">
									<label style="font-size:12.5px;font-weight:600;display:block;margin-bottom:3px">
										دسته‌بندی «<?php echo esc_html( $tx_data['label'] ); ?>» (اختیاری — جایگزین یا مکمل بسته):
									</label>
									<select name="manual_tax[<?php echo esc_attr( $tx_slug ); ?>]" style="width:100%;max-width:380px">
										<option value="">— خواندن خودکار از داخل بسته‌ی ZIP —</option>
										<?php foreach ( $tx_data['terms'] as $term_obj ) : ?>
											<option value="<?php echo esc_attr( $term_obj->slug ); ?>">
												<?php echo esc_html( $term_obj->name . ' (' . $term_obj->slug . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</p>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="description" style="margin:6px 0 0">اگر وارد زیرمنوی ایمپورت یک بخش خاص (مثلاً جراحی‌ها یا دانشجویان) شوید، منوی انتخاب دستی زیرشاخه‌های همان بخش نیز اینجا نمایش داده می‌شود.</p>
						<?php endif; ?>
					</div>

					<div class="hj-imp-opt-box">
						<h3>۴) گزینه‌های بررسی، تصویر و کش</h3>
						<label class="hj-imp-check">
							<input type="checkbox" name="update_image" value="1" <?php checked( ! empty( $defaults['update_image'] ) ); ?>>
							جایگزینی خودکار تصویر شاخص در صورتی که فایل تصویر داخل ZIP تغییر کرده باشد
						</label>
						<label class="hj-imp-check">
							<input type="checkbox" name="normalize_content" value="1" <?php checked( ! empty( $defaults['normalize_content'] ) ); ?>>
							استانداردسازی خودکار تک‌جمله‌ای «سلب مسئولیت پزشکی» و جدول‌های HTML
						</label>
						<label class="hj-imp-check">
							<input type="checkbox" name="purge_cache" value="1" <?php checked( ! empty( $defaults['purge_cache'] ) ); ?>>
							پاک‌سازی خودکار کش سایت (LiteSpeed / Object Cache) پس از ایمپورت
						</label>
						<hr style="border:0;border-top:1px solid #e2e8f0;margin:10px 0">
						<label class="hj-imp-check">
							<input type="checkbox" name="save_as_default" value="1">
							<strong>💾 ذخیره‌ی همین گزینه‌ها به‌عنوان پیش‌فرض همیشگی من</strong>
						</label>
					</div>
				</div>

				<div class="hj-imp-actions-bar">
					<button type="submit" name="hj_execute_submit" value="1" class="button button-primary button-hero">
						🚀 شروع ایمپورت با تنظیمات انتخاب‌شده
					</button>
					<button type="submit" name="hj_dry_run_submit" value="1" class="button button-secondary button-hero">
						🔍 فقط پیش‌نمایش و ممیزی بسته (تست سئو، تصویر و تغییرات بدون ذخیره)
					</button>
				</div>
			</form>
		</div>

		<?php if ( '' === $current_cpt || 'patient_guide' === $current_cpt ) : ?>
			<div class="hj-imp-panel">
				<h2>📦 بسته‌های بذر همراه قالب (ستون‌های پایه)</h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:50px">ردیف</th>
							<th>عنوان مقاله</th>
							<th>نامک</th>
							<th>وضعیت فعلی در سایت</th>
							<th style="width:240px">عملیات سریع</th>
						</tr>
					</thead>
					<tbody>
					<?php $bundled = hj_importer_bundled_list(); ?>
					<?php if ( ! $bundled ) : ?>
						<tr><td colspan="5">بسته‌ای در پوشه‌ی <code>content-imports</code> قالب پیدا نشد.</td></tr>
					<?php endif; ?>
					<?php foreach ( $bundled as $i => $row ) : ?>
						<tr>
							<td><?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></td>
							<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
							<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
							<td>
								<?php if ( $row['published'] ) : ?>
									<span style="color:#15803d;font-weight:700">🟢 منتشرشده در سایت</span>
								<?php elseif ( 'draft' === $row['post_status'] ) : ?>
									<span style="color:#b45309;font-weight:700">📝 موجود در پیش‌نویس</span>
								<?php else : ?>
									<span style="color:#64748b">⚪ هنوز وارد نشده</span>
								<?php endif; ?>
							</td>
							<td style="display:flex;gap:6px;align-items:center">
								<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin:0">
									<input type="hidden" name="action" value="hj_article_import">
									<input type="hidden" name="hj_importer_action" value="bundled">
									<input type="hidden" name="return_cpt" value="<?php echo esc_attr( $current_cpt ); ?>">
									<input type="hidden" name="package" value="<?php echo esc_attr( $row['file'] ); ?>">
									<input type="hidden" name="status_mode" value="<?php echo esc_attr( $defaults['status_mode'] ); ?>">
									<input type="hidden" name="duplicate_mode" value="<?php echo esc_attr( $defaults['duplicate_mode'] ); ?>">
									<input type="hidden" name="keep_existing_status" value="1">
									<input type="hidden" name="normalize_content" value="1">
									<?php wp_nonce_field( 'hj_importer' ); ?>
									<button type="submit" class="button"><?php echo $row['post_status'] ? 'بررسی / آپدیت' : 'ایمپورت بسته'; ?></button>
								</form>
								<?php if ( ! empty( $row['edit_link'] ) ) : ?>
									<a class="button button-link" href="<?php echo esc_url( $row['edit_link'] ); ?>">ویرایش</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $history ) ) : ?>
			<div class="hj-imp-panel">
				<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
					<h2 style="border:0;margin:0;padding:0">🕒 تاریخچه‌ی آخرین عملیات‌های ایمپورت</h2>
					<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin:0">
						<input type="hidden" name="action" value="hj_article_import">
						<input type="hidden" name="hj_importer_action" value="clear_log">
						<input type="hidden" name="return_cpt" value="<?php echo esc_attr( $current_cpt ); ?>">
						<?php wp_nonce_field( 'hj_importer' ); ?>
						<button type="submit" class="button button-small">پاک‌سازی تاریخچه</button>
					</form>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>زمان</th>
							<th>عنوان و نامک</th>
							<th>بخش</th>
							<th>نتیجه‌ی عملیات</th>
							<th>وضعیت</th>
							<th>امتیاز سئو/تصویر</th>
							<th>لینک</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$act_labels = array(
							'created'             => '🆕 ایجاد مقاله جدید',
							'created_copy'        => '📑 ایجاد رونوشت پیش‌نویس',
							'updated'             => '🔄 آپدیت با تغییر',
							'identical_duplicate' => '⚠️ تکراری (بدون تغییر)',
							'skipped_duplicate'   => '⚠️ تکراری (رد شده)',
						);
						?>
						<?php foreach ( array_slice( $history, 0, 15 ) as $h ) : ?>
							<tr>
								<td style="direction:ltr;text-align:right"><?php echo esc_html( $h['time'] ); ?></td>
								<td>
									<strong><?php echo esc_html( $h['title'] ); ?></strong><br>
									<code style="font-size:11px"><?php echo esc_html( $h['slug'] ); ?></code>
								</td>
								<td><?php echo esc_html( isset( $cpt_map[ $h['post_type'] ] ) ? $cpt_map[ $h['post_type'] ]['label'] : $h['post_type'] ); ?></td>
								<td>
									<?php echo esc_html( isset( $act_labels[ $h['action'] ] ) ? $act_labels[ $h['action'] ] : $h['action'] ); ?>
									<?php if ( ! empty( $h['diff_count'] ) && 'updated' === $h['action'] ) : ?>
										<small>(<?php echo (int) $h['diff_count']; ?> تغییر)</small>
									<?php endif; ?>
								</td>
								<td><?php echo 'publish' === $h['post_status'] ? '🟢 منتشرشده' : '📝 پیش‌نویس'; ?></td>
								<td>
									<strong><?php echo (int) $h['audit_score']; ?>٪</strong>
									<?php if ( ! empty( $h['crit_count'] ) ) : ?>
										<span style="color:#dc2626;font-size:11px">(<?php echo (int) $h['crit_count']; ?> کسری مهم)</span>
									<?php elseif ( ! empty( $h['warn_count'] ) ) : ?>
										<span style="color:#d97706;font-size:11px">(<?php echo (int) $h['warn_count']; ?> نکته)</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $h['edit_link'] ) ) : ?>
										<a href="<?php echo esc_url( $h['edit_link'] ); ?>">ویرایش</a>
									<?php endif; ?>
									<?php if ( ! empty( $h['permalink'] ) ) : ?>
										| <a href="<?php echo esc_url( $h['permalink'] ); ?>" target="_blank" rel="noopener">مشاهده</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

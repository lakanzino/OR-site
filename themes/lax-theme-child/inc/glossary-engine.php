<?php
/**
 * High-Performance Medical Glossary & Auto-Tooltip Engine (5,000–50,000+ terms).
 *
 * Provides:
 *   1. Dedicated indexed SQL table `{$wpdb->prefix}hj_glossary_terms` paired with
 *      the `glossary_group` taxonomy so 5,000+ short definitions never bloat
 *      `wp_posts`, homepage article grids, or XML sitemaps with thin pages.
 *   2. Compiled word-prefix dictionary index (`O(N)` single-pass scanner, ~3–5 ms
 *      per article even with 10,000+ terms) with Persian ZWNJ/Arabic-character
 *      normalization and grammatical plural tolerance.
 *   3. Subtle green in-article highlights + inline JSON payload of matched terms
 *      only (zero extra HTTP requests on hover/tap).
 *   4. Fast AJAX search, topic filtering, alphabet filtering, and pagination
 *      for the `/glossary/` hub, `/glossary-group/{slug}/` archives, and
 *      `[hj_glossary]` shortcode.
 *
 * @package Lax_Theme_Child
 * @since   0.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HJ_GLOSSARY_DB_VERSION    = '1.0.0';
const HJ_GLOSSARY_INDEX_VERSION = '1.0.0';

/* =========================================================================
 * 1. UTF-8 & Persian Normalization Helpers (safe even without mbstring)
 * ====================================================================== */

/**
 * Count UTF-8 characters safely without requiring ext-mbstring.
 *
 * @param string $str Input string.
 * @return int
 */
function hj_glossary_strlen( string $str ): int {
	if ( '' === $str ) {
		return 0;
	}
	if ( function_exists( 'mb_strlen' ) ) {
		return (int) mb_strlen( $str, 'UTF-8' );
	}
	$count = preg_match_all( '/./us', $str, $m );
	return false === $count ? strlen( $str ) : (int) $count;
}

/**
 * UTF-8 safe lowercase without requiring ext-mbstring.
 *
 * @param string $str Input string.
 * @return string
 */
function hj_glossary_strtolower( string $str ): string {
	if ( function_exists( 'mb_strtolower' ) ) {
		return (string) mb_strtolower( $str, 'UTF-8' );
	}
	return strtolower( $str );
}

/**
 * Normalize Persian/Arabic characters, digits, and diacritics for canonical matching.
 *
 * @param string $text           Input text.
 * @param bool   $collapse_zwnj  When true, replaces ZWNJ with a single space for canonical key generation.
 * @return string
 */
function hj_glossary_normalize_text( string $text, bool $collapse_zwnj = true ): string {
	if ( '' === $text ) {
		return '';
	}

	$map = array(
		'ي' => 'ی',
		'ى' => 'ی',
		'ئ' => 'ی',
		'ك' => 'ک',
		'ة' => 'ه',
		'ۀ' => 'ه',
		'ؤ' => 'و',
		'إ' => 'ا',
		'أ' => 'ا',
		'ٱ' => 'ا',
		'۰' => '0',
		'۱' => '1',
		'۲' => '2',
		'۳' => '3',
		'۴' => '4',
		'۵' => '5',
		'۶' => '6',
		'۷' => '7',
		'۸' => '8',
		'۹' => '9',
		'٠' => '0',
		'١' => '1',
		'٢' => '2',
		'٣' => '3',
		'٤' => '4',
		'٥' => '5',
		'٦' => '6',
		'٧' => '7',
		'٨' => '8',
		'٩' => '9',
	);

	$norm = strtr( $text, $map );
	// Strip Arabic harakat / tatweel / BOM / zero-width spaces other than ZWNJ.
	$stripped = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{FEFF}\x{200B}\x{200D}\x{200E}\x{200F}]/u', '', $norm );
	if ( is_string( $stripped ) ) {
		$norm = $stripped;
	}

	$norm = hj_glossary_strtolower( $norm );

	if ( $collapse_zwnj ) {
		$norm = str_replace( "\xE2\x80\x8C", ' ', $norm ); // U+200C ZWNJ -> space.
	}

	$collapsed = preg_replace( '/\s+/u', ' ', trim( $norm ) );
	return is_string( $collapsed ) ? $collapsed : trim( $norm );
}

/**
 * Extract the canonical Persian or English starting letter bucket for alphabet navigation.
 *
 * @param string $term Term title.
 * @return string
 */
function hj_glossary_extract_first_letter( string $term ): string {
	$clean = ltrim( trim( $term ), "«»\"'()[]{} \t\n\r\0\x0B" );
	if ( '' === $clean ) {
		return '#';
	}
	if ( ! preg_match( '/^(\X)/u', $clean, $m ) ) {
		return '#';
	}
	$ch = $m[1];

	// Preserve «آ» distinctly from «ا» for natural Persian dictionary order.
	if ( 'آ' === $ch ) {
		return 'آ';
	}

	$char_map = array(
		'أ' => 'ا',
		'إ' => 'ا',
		'ٱ' => 'ا',
		'ي' => 'ی',
		'ى' => 'ی',
		'ئ' => 'ی',
		'ك' => 'ک',
		'ة' => 'ه',
		'ۀ' => 'ه',
		'ؤ' => 'و',
	);
	$ch = strtr( $ch, $char_map );

	$persian_letters = hj_glossary_persian_alphabet();
	if ( in_array( $ch, $persian_letters, true ) ) {
		return $ch;
	}

	$upper = strtoupper( $ch );
	if ( preg_match( '/^[A-Z]$/', $upper ) ) {
		return 'EN';
	}

	return '#';
}

/**
 * Canonical 33-bucket Persian + English alphabet list for the Glossary UI.
 *
 * @return string[]
 */
function hj_glossary_persian_alphabet(): array {
	return array(
		'آ', 'ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ',
		'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط',
		'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن',
		'و', 'ه', 'ی',
	);
}

/**
 * Generate a URL-safe slug for a glossary term (preserving clean UTF-8 Persian or ASCII).
 *
 * @param string $term Raw term title.
 * @return string
 */
function hj_glossary_make_slug( string $term ): string {
	$slug = sanitize_title( $term );
	if ( '' === $slug ) {
		$slug = 'term-' . substr( md5( $term ), 0, 10 );
	}
	if ( strlen( $slug ) > 180 ) {
		$slug = substr( $slug, 0, 180 );
	}
	return $slug;
}

/* =========================================================================
 * 2. Database Table & Settings Management
 * ====================================================================== */

/**
 * Return the full SQL table name for glossary terms.
 *
 * @return string
 */
function hj_glossary_table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'hj_glossary_terms';
}

/**
 * Ensure the high-speed glossary SQL table exists.
 *
 * Uses direct `CREATE TABLE IF NOT EXISTS` with `VARCHAR(191)` indexes so it
 * succeeds cleanly on all MySQL 5.7+/8.0+ and MariaDB hosts without loading
 * `wp-admin/includes/upgrade.php` on front-end requests.
 *
 * @param bool $force Force check even if version option matches.
 * @return bool
 */
function hj_glossary_ensure_table( bool $force = false ): bool {
	static $ensured = false;
	if ( $ensured && ! $force ) {
		return true;
	}

	if ( ! $force && HJ_GLOSSARY_DB_VERSION === get_option( 'hj_glossary_db_version' ) ) {
		$ensured = true;
		return true;
	}

	global $wpdb;
	$table   = hj_glossary_table_name();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
		`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		`term` VARCHAR(191) NOT NULL,
		`term_norm` VARCHAR(191) NOT NULL,
		`slug` VARCHAR(191) NOT NULL,
		`en_term` VARCHAR(191) NOT NULL DEFAULT '',
		`aliases` TEXT NULL,
		`definition` TEXT NOT NULL,
		`group_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
		`first_letter` VARCHAR(16) NOT NULL DEFAULT '#',
		`highlight_enabled` TINYINT(1) NOT NULL DEFAULT 1,
		`status` VARCHAR(20) NOT NULL DEFAULT 'publish',
		`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `idx_term_norm` (`term_norm`),
		KEY `idx_slug` (`slug`),
		KEY `idx_status_group` (`status`, `group_id`),
		KEY `idx_status_letter` (`status`, `first_letter`),
		KEY `idx_status_highlight` (`status`, `highlight_enabled`)
	) {$charset};";

	$res = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	if ( false !== $res ) {
		update_option( 'hj_glossary_db_version', HJ_GLOSSARY_DB_VERSION, true );
		$ensured = true;
		return true;
	}

	return false;
}
add_action( 'init', 'hj_glossary_ensure_table', 5 );

/**
 * Retrieve glossary highlight & display settings with sensible defaults.
 *
 * @return array<string,mixed>
 */
function hj_glossary_get_settings(): array {
	$defaults = array(
		'enabled'              => 1,
		'post_types'           => array( 'patient_guide', 'or_environment', 'surgery', 'student_content', 'post' ),
		'max_per_term'         => 1,     // Highlight only the first occurrence of each term per article (1, 2, 3, or 0 = unlimited).
		'max_total_per_post'   => 70,    // Cap total highlights in one article so long guides stay calm & readable.
		'highlight_style'      => 'soft-green', // 'soft-green' | 'vivid-green' | 'underline-green'.
		'match_english'        => 1,     // Also highlight English equivalents (>= 3 chars) when they appear in articles.
		'match_plurals'        => 1,     // Also match safe Persian plural suffixes («ها» / «‌ها»).
		'show_glossary_link'   => 1,     // Show subtle «مشاهده در واژه‌نامه ←» footer inside tooltip popover.
		'per_page'             => 60,    // Terms per page on `/glossary/`.
	);

	$saved = get_option( 'hj_glossary_settings', array() );
	if ( ! is_array( $saved ) ) {
		return $defaults;
	}

	return array_merge( $defaults, $saved );
}

/* =========================================================================
 * 3. CRUD & Topic (`glossary_group`) Helpers
 * ====================================================================== */

/**
 * Resolve or create a `glossary_group` taxonomy term by name, slug, or ID.
 *
 * @param string|int $group_input Category name, slug, or term_id.
 * @param bool       $auto_create Create the category if it does not exist yet.
 * @return int Resolved `term_id` (0 if none).
 */
function hj_glossary_resolve_group_id( $group_input, bool $auto_create = true ): int {
	static $memo = array();

	if ( is_numeric( $group_input ) ) {
		$tid = (int) $group_input;
		if ( $tid <= 0 ) {
			return 0;
		}
		$t = get_term( $tid, 'glossary_group' );
		return ( $t && ! is_wp_error( $t ) ) ? (int) $t->term_id : 0;
	}

	$raw = trim( (string) $group_input );
	if ( '' === $raw ) {
		return 0;
	}

	$memo_key = hj_glossary_normalize_text( $raw );
	if ( isset( $memo[ $memo_key ] ) ) {
		return $memo[ $memo_key ];
	}

	$by_name = get_term_by( 'name', $raw, 'glossary_group' );
	if ( $by_name && ! is_wp_error( $by_name ) ) {
		$memo[ $memo_key ] = (int) $by_name->term_id;
		return $memo[ $memo_key ];
	}

	$slug    = sanitize_title( $raw );
	$by_slug = '' !== $slug ? get_term_by( 'slug', $slug, 'glossary_group' ) : false;
	if ( $by_slug && ! is_wp_error( $by_slug ) ) {
		$memo[ $memo_key ] = (int) $by_slug->term_id;
		return $memo[ $memo_key ];
	}

	if ( ! $auto_create ) {
		return 0;
	}

	$created = wp_insert_term( $raw, 'glossary_group', '' !== $slug ? array( 'slug' => $slug ) : array() );
	if ( is_wp_error( $created ) ) {
		$existing_id = (int) $created->get_error_data( 'term_exists' );
		if ( $existing_id > 0 ) {
			$memo[ $memo_key ] = $existing_id;
			return $existing_id;
		}
		return 0;
	}

	$memo[ $memo_key ] = (int) $created['term_id'];
	return $memo[ $memo_key ];
}

/**
 * Return all `glossary_group` categories coupled with their exact active term counts
 * from `hj_glossary_terms`.
 *
 * @param bool $hide_empty Only return groups that have >= 1 published term.
 * @return array<int,array{term_id:int,name:string,slug:string,description:string,url:string,count:int}>
 */
function hj_glossary_get_groups_with_counts( bool $hide_empty = false ): array {
	hj_glossary_ensure_table();
	global $wpdb;
	$table = hj_glossary_table_name();

	$counts_raw = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT group_id, COUNT(*) AS cnt FROM `{$table}` WHERE status = 'publish' GROUP BY group_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	$count_map = array();
	if ( is_array( $counts_raw ) ) {
		foreach ( $counts_raw as $row ) {
			$count_map[ (int) $row['group_id'] ] = (int) $row['cnt'];
		}
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'glossary_group',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}

	$out = array();
	foreach ( $terms as $t ) {
		$cnt = isset( $count_map[ (int) $t->term_id ] ) ? $count_map[ (int) $t->term_id ] : 0;
		if ( $hide_empty && $cnt <= 0 ) {
			continue;
		}
		$link = get_term_link( $t, 'glossary_group' );
		$out[ (int) $t->term_id ] = array(
			'term_id'     => (int) $t->term_id,
			'name'        => (string) $t->name,
			'slug'        => (string) $t->slug,
			'description' => (string) $t->description,
			'url'         => is_wp_error( $link ) ? add_query_arg( 'gcat', $t->slug, hj_archive_url( 'glossary' ) ) : (string) $link,
			'count'       => $cnt,
		);
	}

	return $out;
}

/**
 * Insert or update a single glossary term cleanly.
 *
 * @param array<string,mixed> $data          Term fields (`id`, `term`, `definition`, `group`, `en_term`, `aliases`, `highlight_enabled`, `status`).
 * @param string              $duplicate_mode 'update' | 'skip'.
 * @param bool                $rebuild_index  Whether to invalidate/rebuild the matcher index immediately (set false during bulk loops).
 * @return array{ok:bool,action:string,id:int,message:string}
 */
function hj_glossary_upsert_term( array $data, string $duplicate_mode = 'update', bool $rebuild_index = true ): array {
	hj_glossary_ensure_table();
	global $wpdb;
	$table = hj_glossary_table_name();

	$term = trim( wp_strip_all_tags( (string) ( $data['term'] ?? '' ) ) );
	$def  = trim( wp_kses_post( (string) ( $data['definition'] ?? '' ) ) );
	// Flatten multi-paragraph definitions to clean single-line text for crisp tooltips.
	$def  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $def ) ) ?? $def );

	if ( '' === $term || '' === $def ) {
		return array(
			'ok'      => false,
			'action'  => 'invalid',
			'id'      => 0,
			'message' => 'نام واژه و تعریف کوتاه هر دو الزامی هستند.',
		);
	}

	if ( hj_glossary_strlen( $term ) > 185 ) {
		$term = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 185, 'UTF-8' ) : substr( $term, 0, 185 );
	}

	$term_norm = hj_glossary_normalize_text( $term, true );
	if ( '' === $term_norm ) {
		return array(
			'ok'      => false,
			'action'  => 'invalid',
			'id'      => 0,
			'message' => 'واژه‌ی واردشده معتبر نیست.',
		);
	}

	$en_term   = trim( wp_strip_all_tags( (string) ( $data['en_term'] ?? '' ) ) );
	$aliases   = trim( wp_strip_all_tags( (string) ( $data['aliases'] ?? '' ) ) );
	$group_raw = $data['group_id'] ?? ( $data['group'] ?? '' );
	$group_id  = hj_glossary_resolve_group_id( $group_raw, true );
	$letter    = hj_glossary_extract_first_letter( $term );
	$highlight = isset( $data['highlight_enabled'] ) ? ( (int) (bool) $data['highlight_enabled'] ) : 1;
	$status    = in_array( ( $data['status'] ?? 'publish' ), array( 'publish', 'draft' ), true ) ? (string) $data['status'] : 'publish';
	$slug      = ! empty( $data['slug'] ) ? hj_glossary_make_slug( (string) $data['slug'] ) : hj_glossary_make_slug( $term );
	$now       = current_time( 'mysql' );

	$edit_id = isset( $data['id'] ) ? (int) $data['id'] : 0;
	if ( $edit_id > 0 ) {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $edit_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	} else {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE term_norm = %s LIMIT 1", $term_norm ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	if ( $existing ) {
		$existing_id = (int) $existing['id'];
		if ( 0 === $edit_id && 'skip' === $duplicate_mode ) {
			return array(
				'ok'      => true,
				'action'  => 'skipped',
				'id'      => $existing_id,
				'message' => 'واژه‌ی تکراری نادیده گرفته شد.',
			);
		}

		// Preserve existing group/en_term/aliases during bulk update if the incoming row left them blank.
		if ( 0 === $edit_id ) {
			if ( 0 === $group_id && (int) $existing['group_id'] > 0 ) {
				$group_id = (int) $existing['group_id'];
			}
			if ( '' === $en_term && '' !== (string) $existing['en_term'] ) {
				$en_term = (string) $existing['en_term'];
			}
			if ( '' === $aliases && '' !== (string) $existing['aliases'] ) {
				$aliases = (string) $existing['aliases'];
			}
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'term'              => $term,
				'term_norm'         => $term_norm,
				'slug'              => $slug,
				'en_term'           => $en_term,
				'aliases'           => $aliases,
				'definition'        => $def,
				'group_id'          => $group_id,
				'first_letter'      => $letter,
				'highlight_enabled' => $highlight,
				'status'            => $status,
				'updated_at'        => $now,
			),
			array( 'id' => $existing_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( $rebuild_index ) {
			hj_glossary_rebuild_index();
		}

		return array(
			'ok'      => true,
			'action'  => 'updated',
			'id'      => $existing_id,
			'message' => 'واژه با موفقیت به‌روزرسانی شد.',
		);
	}

	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'term'              => $term,
			'term_norm'         => $term_norm,
			'slug'              => $slug,
			'en_term'           => $en_term,
			'aliases'           => $aliases,
			'definition'        => $def,
			'group_id'          => $group_id,
			'first_letter'      => $letter,
			'highlight_enabled' => $highlight,
			'status'            => $status,
			'created_at'        => $now,
			'updated_at'        => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		return array(
			'ok'      => false,
			'action'  => 'db_error',
			'id'      => 0,
			'message' => 'خطا در ذخیره‌ی واژه در پایگاه‌داده.',
		);
	}

	$new_id = (int) $wpdb->insert_id;
	if ( $rebuild_index ) {
		hj_glossary_rebuild_index();
	}

	return array(
		'ok'      => true,
		'action'  => 'inserted',
		'id'      => $new_id,
		'message' => 'واژه با موفقیت افزوده شد.',
	);
}

/**
 * Sync any `glossary` CPT post saved via classic WP Editor or ZIP Importer
 * into `hj_glossary_terms` automatically.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post object.
 * @return void
 */
function hj_glossary_sync_cpt_post( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! $post || 'glossary' !== $post->post_type ) {
		return;
	}
	if ( ! in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
		return;
	}
	$title = trim( (string) $post->post_title );
	$raw   = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
	$def   = wp_trim_words( wp_strip_all_tags( (string) $raw ), 45, '…' );
	if ( '' === $title || '' === $def ) {
		return;
	}
	$terms    = get_the_terms( $post_id, 'glossary_group' );
	$group_id = ( is_array( $terms ) && ! empty( $terms ) ) ? (int) $terms[0]->term_id : 0;

	hj_glossary_upsert_term(
		array(
			'term'       => $title,
			'slug'       => $post->post_name ? $post->post_name : hj_glossary_make_slug( $title ),
			'definition' => $def,
			'group_id'   => $group_id,
			'status'     => $post->post_status,
		),
		'update',
		true
	);
}
add_action( 'save_post_glossary', 'hj_glossary_sync_cpt_post', 20, 2 );

/**
 * Get high-level glossary statistics for Admin Dashboard & Glossary Hub.
 *
 * @return array{published:int,draft:int,highlighted:int,groups_total:int,groups_nonempty:int,letters:array<string,int>}
 */
function hj_glossary_get_stats(): array {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	hj_glossary_ensure_table();
	global $wpdb;
	$table = hj_glossary_table_name();

	$totals = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT
			SUM(CASE WHEN status = 'publish' THEN 1 ELSE 0 END) AS pub_cnt,
			SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_cnt,
			SUM(CASE WHEN status = 'publish' AND highlight_enabled = 1 THEN 1 ELSE 0 END) AS hl_cnt
		FROM `{$table}`", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	$letter_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT first_letter, COUNT(*) AS cnt FROM `{$table}` WHERE status = 'publish' GROUP BY first_letter", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	$letters = array();
	if ( is_array( $letter_rows ) ) {
		foreach ( $letter_rows as $r ) {
			$letters[ (string) $r['first_letter'] ] = (int) $r['cnt'];
		}
	}

	$groups          = hj_glossary_get_groups_with_counts( false );
	$groups_nonempty = 0;
	foreach ( $groups as $g ) {
		if ( $g['count'] > 0 ) {
			++$groups_nonempty;
		}
	}

	$cached = array(
		'published'       => isset( $totals['pub_cnt'] ) ? (int) $totals['pub_cnt'] : 0,
		'draft'           => isset( $totals['draft_cnt'] ) ? (int) $totals['draft_cnt'] : 0,
		'highlighted'     => isset( $totals['hl_cnt'] ) ? (int) $totals['hl_cnt'] : 0,
		'groups_total'    => count( $groups ),
		'groups_nonempty' => $groups_nonempty,
		'letters'         => $letters,
	);

	return $cached;
}

/**
 * Query glossary terms with fast indexed filtering + pagination.
 *
 * @param array<string,mixed> $args Query arguments (`search`, `group_id`, `group_slug`, `letter`, `status`, `page`, `per_page`).
 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int,per_page:int}
 */
function hj_glossary_query_terms( array $args = array() ): array {
	hj_glossary_ensure_table();
	global $wpdb;
	$table = hj_glossary_table_name();

	$status   = isset( $args['status'] ) ? (string) $args['status'] : 'publish';
	$search   = trim( (string) ( $args['search'] ?? '' ) );
	$letter   = trim( (string) ( $args['letter'] ?? '' ) );
	$group_id = isset( $args['group_id'] ) ? (int) $args['group_id'] : 0;

	if ( 0 === $group_id && ! empty( $args['group_slug'] ) ) {
		$t = get_term_by( 'slug', sanitize_title( (string) $args['group_slug'] ), 'glossary_group' );
		if ( $t && ! is_wp_error( $t ) ) {
			$group_id = (int) $t->term_id;
		}
	}

	$per_page = isset( $args['per_page'] ) ? max( 1, min( 250, (int) $args['per_page'] ) ) : 60;
	$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
	$offset   = ( $page - 1 ) * $per_page;

	$where  = array();
	$params = array();

	if ( 'all' !== $status ) {
		$where[]  = 'status = %s';
		$params[] = $status;
	}
	if ( $group_id > 0 ) {
		$where[]  = 'group_id = %d';
		$params[] = $group_id;
	}
	if ( '' !== $letter && 'all' !== $letter ) {
		$where[]  = 'first_letter = %s';
		$params[] = $letter;
	}
	if ( '' !== $search ) {
		$norm_q   = hj_glossary_normalize_text( $search, true );
		$like_raw = '%' . $wpdb->esc_like( $search ) . '%';
		$like_nrm = '%' . $wpdb->esc_like( $norm_q ) . '%';
		$where[]  = '(term_norm LIKE %s OR term LIKE %s OR en_term LIKE %s OR aliases LIKE %s OR definition LIKE %s)';
		$params[] = $like_nrm;
		$params[] = $like_raw;
		$params[] = $like_raw;
		$params[] = $like_raw;
		$params[] = $like_raw;
	}

	$where_sql = ! empty( $where ) ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

	$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where_sql}";
	if ( ! empty( $params ) ) {
		$count_sql = $wpdb->prepare( $count_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

	$pages = max( 1, (int) ceil( $total / $per_page ) );
	if ( $page > $pages ) {
		$page   = $pages;
		$offset = ( $page - 1 ) * $per_page;
	}

	// If searching, rank exact/prefix matches first!
	$order_sql = 'ORDER BY term ASC, id ASC';
	$row_params = $params;
	if ( '' !== $search ) {
		$norm_q       = hj_glossary_normalize_text( $search, true );
		$prefix_like  = $wpdb->esc_like( $norm_q ) . '%';
		$order_sql    = 'ORDER BY (term_norm = %s) DESC, (term_norm LIKE %s) DESC, CHAR_LENGTH(term) ASC, term ASC';
		$row_params[] = $norm_q;
		$row_params[] = $prefix_like;
	}

	$row_params[] = $per_page;
	$row_params[] = $offset;

	$rows_sql = $wpdb->prepare(
		"SELECT * FROM `{$table}` {$where_sql} {$order_sql} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row_params
	);
	$rows = $wpdb->get_results( $rows_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

	$groups = hj_glossary_get_groups_with_counts( false );
	$items  = array();
	if ( is_array( $rows ) ) {
		foreach ( $rows as $r ) {
			$gid  = (int) $r['group_id'];
			$gobj = $groups[ $gid ] ?? null;
			$items[] = array(
				'id'                => (int) $r['id'],
				'term'              => (string) $r['term'],
				'slug'              => (string) $r['slug'],
				'en_term'           => (string) $r['en_term'],
				'aliases'           => (string) $r['aliases'],
				'definition'        => (string) $r['definition'],
				'group_id'          => $gid,
				'group_name'        => $gobj ? $gobj['name'] : '',
				'group_slug'        => $gobj ? $gobj['slug'] : '',
				'group_url'         => $gobj ? $gobj['url'] : '',
				'first_letter'      => (string) $r['first_letter'],
				'highlight_enabled' => (int) $r['highlight_enabled'],
				'status'            => (string) $r['status'],
				'url'               => hj_glossary_term_url( (string) $r['slug'], (string) $r['term'] ),
			);
		}
	}

	return array(
		'items'    => $items,
		'total'    => $total,
		'pages'    => $pages,
		'page'     => $page,
		'per_page' => $per_page,
	);
}

/**
 * Build canonical link to view a term inside the Glossary Hub.
 *
 * @param string $slug Term slug.
 * @param string $term Term title.
 * @return string
 */
function hj_glossary_term_url( string $slug, string $term = '' ): string {
	$base = function_exists( 'hj_archive_url' ) ? hj_archive_url( 'glossary' ) : home_url( '/glossary/' );
	if ( '' !== $term ) {
		return add_query_arg( 'gq', $term, $base );
	}
	return '' !== $slug ? trailingslashit( $base ) . rawurlencode( $slug ) . '/' : $base;
}

/**
 * Fetch a small sample of published terms for the homepage glossary strip.
 *
 * @param int $limit Max terms to fetch.
 * @return array<int,array{term:string,definition:string,url:string}>
 */
function hj_glossary_get_featured_terms( int $limit = 10 ): array {
	hj_glossary_ensure_table();
	global $wpdb;
	$table = hj_glossary_table_name();

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT id, term, slug, definition FROM `{$table}` WHERE status = 'publish' ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			max( 1, $limit )
		),
		ARRAY_A
	);
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}

	$out = array();
	foreach ( $rows as $r ) {
		$out[] = array(
			'id'         => (int) $r['id'],
			'term'       => (string) $r['term'],
			'definition' => (string) $r['definition'],
			'url'        => hj_glossary_term_url( (string) $r['slug'], (string) $r['term'] ),
		);
	}
	return $out;
}

/* =========================================================================
 * 4. Compiled O(N) Word-Prefix Index & In-Article Green Highlighter
 * ====================================================================== */

/**
 * Return filesystem path for the compiled PHP OPcache-friendly glossary index.
 *
 * @return string
 */
function hj_glossary_index_file_path(): string {
	$uploads = wp_upload_dir( null, false );
	$basedir = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
	return trailingslashit( $basedir ) . 'hj-cache/glossary-index-v1.php';
}

/**
 * Tokenize a normalized phrase into clean word units (splitting on whitespace & ZWNJ).
 *
 * @param string $surface Raw term or alias.
 * @return string[]
 */
function hj_glossary_phrase_tokens( string $surface ): array {
	$norm = hj_glossary_normalize_text( $surface, true );
	if ( '' === $norm ) {
		return array();
	}
	if ( ! preg_match_all( '/[\p{L}\p{N}]+/u', $norm, $m ) ) {
		return array();
	}
	return $m[0];
}

/**
 * Compile all active glossary terms into an O(1) first-word prefix map + compact term dictionary.
 *
 * Scales effortlessly to 5,000–25,000+ terms:
 *   - Built ONLY when terms or settings change (saved as pure PHP array in `uploads/hj-cache/`).
 *   - Loaded via `include` (cached in PHP OPcache shared RAM -> < 0.5 ms on article views!).
 *
 * @return array{map:array<string,array<int,array{0:array<int,string>,1:int}>>,terms:array<int,array<string,string>>,built_at:int,term_count:int,phrase_count:int}
 */
function hj_glossary_rebuild_index(): array {
	hj_glossary_ensure_table();
	global $wpdb;
	$table    = hj_glossary_table_name();
	$settings = hj_glossary_get_settings();
	$match_en = ! empty( $settings['match_english'] );

	$groups = hj_glossary_get_groups_with_counts( false );

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT id, term, slug, en_term, aliases, definition, group_id FROM `{$table}` WHERE status = 'publish' AND highlight_enabled = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	$map          = array();
	$terms_dict   = array();
	$seen_phrases = array();
	$phrase_count = 0;

	if ( is_array( $rows ) ) {
		foreach ( $rows as $r ) {
			$tid  = (int) $r['id'];
			$term = trim( (string) $r['term'] );
			$def  = trim( (string) $r['definition'] );
			if ( '' === $term || '' === $def ) {
				continue;
			}

			$gid  = (int) $r['group_id'];
			$gobj = $groups[ $gid ] ?? null;

			$terms_dict[ $tid ] = array(
				't'  => $term,
				'e'  => trim( (string) $r['en_term'] ),
				'd'  => $def,
				'c'  => $gobj ? $gobj['name'] : '',
				'cu' => $gobj ? $gobj['url'] : '',
				'u'  => hj_glossary_term_url( (string) $r['slug'], $term ),
			);

			// Collect surface forms: main term + aliases + optional English equivalent + ZWNJ-joined form.
			$surfaces = array( $term );

			// If term contains ZWNJ (e.g. «بی‌هوشی»), also index joined form («بیهوشی») so both spellings match!
			if ( false !== strpos( $term, "\xE2\x80\x8C" ) ) {
				$surfaces[] = str_replace( "\xE2\x80\x8C", '', $term );
			}

			$aliases_raw = trim( (string) $r['aliases'] );
			if ( '' !== $aliases_raw ) {
				$parts = preg_split( '/[،,|؛;\n\r]+/u', $aliases_raw );
				if ( is_array( $parts ) ) {
					foreach ( $parts as $p ) {
						$p = trim( $p );
						if ( '' !== $p ) {
							$surfaces[] = $p;
							if ( false !== strpos( $p, "\xE2\x80\x8C" ) ) {
								$surfaces[] = str_replace( "\xE2\x80\x8C", '', $p );
							}
						}
					}
				}
			}

			if ( $match_en && '' !== trim( (string) $r['en_term'] ) ) {
				$en_clean = trim( (string) $r['en_term'] );
				if ( hj_glossary_strlen( $en_clean ) >= 3 ) {
					$surfaces[] = $en_clean;
				}
			}

			foreach ( $surfaces as $sf ) {
				$w_tokens = hj_glossary_phrase_tokens( $sf );
				if ( empty( $w_tokens ) ) {
					continue;
				}
				// Skip single-character tokens to prevent noisy false positives.
				if ( 1 === count( $w_tokens ) && hj_glossary_strlen( $w_tokens[0] ) < 2 ) {
					continue;
				}
				$phrase_key = implode( ' ', $w_tokens );
				if ( isset( $seen_phrases[ $phrase_key ] ) ) {
					continue;
				}
				$seen_phrases[ $phrase_key ] = $tid;
				$first                       = $w_tokens[0];
				$map[ $first ][]             = array( $w_tokens, $tid );
				++$phrase_count;
			}
		}
	}

	// Sort candidate phrases under each first word LONGEST-FIRST (by token count, then char length).
	foreach ( $map as $fw => &$list ) {
		usort(
			$list,
			static function ( $a, $b ) {
				$ca = count( $a[0] );
				$cb = count( $b[0] );
				if ( $ca !== $cb ) {
					return $cb <=> $ca;
				}
				return strlen( implode( '', $b[0] ) ) <=> strlen( implode( '', $a[0] ) );
			}
		);
	}
	unset( $list );

	$index = array(
		'version'      => HJ_GLOSSARY_INDEX_VERSION,
		'built_at'     => time(),
		'term_count'   => count( $terms_dict ),
		'phrase_count' => $phrase_count,
		'map'          => $map,
		'terms'        => $terms_dict,
	);

	// Persist compiled PHP cache file atomically if uploads dir is writable.
	try {
		$path = hj_glossary_index_file_path();
		$dir  = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( is_dir( $dir ) && is_writable( $dir ) ) {
			$tmp     = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
			$exported = "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\nreturn " . var_export( $index, true ) . ";\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			if ( false !== @file_put_contents( $tmp, $exported, LOCK_EX ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@rename( $tmp, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( function_exists( 'opcache_invalidate' ) ) {
					@opcache_invalidate( $path, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
	} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Safe fallback: wp_cache / runtime memory still serves the index.
	}

	wp_cache_set( 'hj_glossary_compiled_index', $index, 'hj_glossary', 3600 );
	update_option(
		'hj_glossary_index_meta',
		array(
			'built_at'     => $index['built_at'],
			'term_count'   => $index['term_count'],
			'phrase_count' => $index['phrase_count'],
		),
		true
	);

	return $index;
}

/**
 * Load the compiled glossary index in < 1 ms (static memory -> object cache -> PHP file -> rebuild).
 *
 * @param bool $force_reload Bypass static memory cache.
 * @return array<string,mixed>
 */
function hj_glossary_get_compiled_index( bool $force_reload = false ): array {
	static $mem_index = null;
	if ( null !== $mem_index && ! $force_reload ) {
		return $mem_index;
	}

	if ( ! $force_reload ) {
		$cached = wp_cache_get( 'hj_glossary_compiled_index', 'hj_glossary' );
		if ( is_array( $cached ) && isset( $cached['map'], $cached['terms'] ) ) {
			$mem_index = $cached;
			return $mem_index;
		}
	}

	$path = hj_glossary_index_file_path();
	if ( ! $force_reload && is_readable( $path ) ) {
		try {
			$loaded = include $path;
			if ( is_array( $loaded ) && ( $loaded['version'] ?? '' ) === HJ_GLOSSARY_INDEX_VERSION && isset( $loaded['map'], $loaded['terms'] ) ) {
				$mem_index = $loaded;
				wp_cache_set( 'hj_glossary_compiled_index', $loaded, 'hj_glossary', 3600 );
				return $mem_index;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Rebuild below if corrupted.
		}
	}

	$mem_index = hj_glossary_rebuild_index();
	return $mem_index;
}

/**
 * Check whether a normalized word matches a dictionary word (including safe attached «ها» plural).
 *
 * @param string $article_word Normalized word token from article.
 * @param string $dict_word    Normalized word token from dictionary.
 * @param bool   $allow_plural Whether plural matching is enabled.
 * @return bool
 */
function hj_glossary_word_matches( string $article_word, string $dict_word, bool $allow_plural ): bool {
	if ( $article_word === $dict_word ) {
		return true;
	}
	if ( $allow_plural && hj_glossary_strlen( $dict_word ) >= 4 && substr( $article_word, -4 ) === 'ها' ) {
		$stem = substr( $article_word, 0, -4 ); // 'ها' is 4 bytes in UTF-8.
		if ( $stem === $dict_word ) {
			return true;
		}
	}
	return false;
}

/**
 * Highlight glossary terms inside article HTML in a single O(N) pass and attach
 * a compact JSON payload of ONLY the matched terms on this page.
 *
 * @param string $content Rendered post HTML.
 * @return string
 */
function hj_glossary_highlight_article_content( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}
	if ( function_exists( 'doing_filter' ) && doing_filter( 'get_the_excerpt' ) ) {
		return $content;
	}
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$settings = hj_glossary_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return $content;
	}

	$allowed_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array();
	$current_type  = (string) get_post_type();
	if ( 'glossary' === $current_type || ! in_array( $current_type, $allowed_types, true ) ) {
		return $content;
	}

	$index = hj_glossary_get_compiled_index();
	if ( empty( $index['map'] ) || empty( $index['terms'] ) ) {
		return $content;
	}

	$map                = $index['map'];
	$terms_dict         = $index['terms'];
	$max_per_term       = (int) ( $settings['max_per_term'] ?? 1 );
	$max_total          = max( 1, (int) ( $settings['max_total_per_post'] ?? 70 ) );
	$allow_plurals      = ! empty( $settings['match_plurals'] );
	$style_variant      = (string) ( $settings['highlight_style'] ?? 'soft-green' );
	$style_class        = 'hj-glossary-term--' . sanitize_html_class( $style_variant );

	// Safe ZWNJ-attached grammatical suffixes in Persian («بخیه‌ها», «بخیهٔ/بخیه‌ای», «کاتترهای», ...).
	$zwnj_suffixes = array(
		'ها'   => true,
		'های'  => true,
		'هایی' => true,
		'ای'   => true,
		'ام'   => true,
		'ات'   => true,
		'اش'   => true,
		'مان'  => true,
		'تان'  => true,
		'شان'  => true,
		'تر'   => true,
		'ترین' => true,
		'ی'    => true,
	);

	// Protected HTML tags inside which we NEVER inject glossary highlights.
	$skip_tags = array(
		'h1'         => true,
		'h2'         => true,
		'h3'         => true,
		'h4'         => true,
		'h5'         => true,
		'h6'         => true,
		'a'          => true,
		'button'     => true,
		'code'       => true,
		'pre'        => true,
		'script'     => true,
		'style'      => true,
		'svg'        => true,
		'figcaption' => true,
		'summary'    => true,
		'label'      => true,
		'textarea'   => true,
		'select'     => true,
		'option'     => true,
		'time'       => true,
	);

	$html_parts = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $html_parts ) ) {
		return $content;
	}

	$skip_stack     = array();
	$term_hit_count = array();
	$matched_terms  = array();
	$total_hits     = 0;

	foreach ( $html_parts as $part_idx => $part ) {
		if ( $total_hits >= $max_total ) {
			break;
		}
		if ( '' === $part ) {
			continue;
		}

		// Track open/close HTML tags to avoid highlighting inside headings, links, etc.
		if ( '<' === $part[0] ) {
			if ( preg_match( '/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $part, $tm ) ) {
				$is_close = ( '/' === $tm[1] );
				$tag_name = strtolower( $tm[2] );
				$is_self  = ( substr( rtrim( $part ), -2 ) === '/>' ) || in_array( $tag_name, array( 'br', 'hr', 'img', 'input', 'meta', 'link', 'source' ), true );

				if ( ! $is_self ) {
					if ( ! $is_close ) {
						if ( isset( $skip_tags[ $tag_name ] ) || false !== strpos( $part, 'hj-glossary-term' ) || false !== strpos( $part, 'orsite-toc' ) ) {
							$skip_stack[] = $tag_name;
						}
					} elseif ( ! empty( $skip_stack ) ) {
						// Pop matching tag from stack.
						for ( $s = count( $skip_stack ) - 1; $s >= 0; --$s ) {
							if ( $skip_stack[ $s ] === $tag_name ) {
								array_splice( $skip_stack, $s );
								break;
							}
						}
					}
				}
			}
			continue;
		}

		if ( ! empty( $skip_stack ) ) {
			continue;
		}

		// Split text node into alternating WORD tokens and NON-WORD separators.
		if ( ! preg_match_all( '/([\p{L}\p{N}]+|[^\p{L}\p{N}]+)/u', $part, $tok_matches ) ) {
			continue;
		}
		$tokens = $tok_matches[0];
		$t_len  = count( $tokens );
		if ( $t_len < 1 ) {
			continue;
		}

		// Pre-classify each token as word (normalized) or separator.
		$is_word   = array();
		$norm_toks = array();
		for ( $i = 0; $i < $t_len; ++$i ) {
			if ( preg_match( '/^[\p{L}\p{N}]+$/u', $tokens[ $i ] ) ) {
				$is_word[ $i ]   = true;
				$norm_toks[ $i ] = hj_glossary_normalize_text( $tokens[ $i ], true );
			} else {
				$is_word[ $i ]   = false;
				$norm_toks[ $i ] = '';
			}
		}

		$out_buf = '';
		$changed = false;
		$i       = 0;

		while ( $i < $t_len ) {
			if ( ! $is_word[ $i ] || $total_hits >= $max_total ) {
				$out_buf .= $tokens[ $i ];
				++$i;
				continue;
			}

			// Never start a match immediately after a ZWNJ (middle of a compound word like «بخیه‌ها»).
			if ( $i > 0 && "\xE2\x80\x8C" === $tokens[ $i - 1 ] ) {
				$out_buf .= $tokens[ $i ];
				++$i;
				continue;
			}

			$w_norm = $norm_toks[ $i ];
			// Find candidate bucket: exact first word, OR stem without attached «ها» for single-word plural.
			$candidates = $map[ $w_norm ] ?? null;
			if ( $allow_plurals && hj_glossary_strlen( $w_norm ) >= 6 && substr( $w_norm, -4 ) === 'ها' ) {
				$stem = substr( $w_norm, 0, -4 );
				if ( isset( $map[ $stem ] ) ) {
					$candidates = null !== $candidates ? array_merge( $candidates, $map[ $stem ] ) : $map[ $stem ];
				}
			}

			if ( empty( $candidates ) ) {
				$out_buf .= $tokens[ $i ];
				++$i;
				continue;
			}

			$matched_end_idx = -1;
			$matched_tid     = 0;

			foreach ( $candidates as $cand ) {
				$phrase_words = $cand[0];
				$tid          = $cand[1];

				if ( $max_per_term > 0 && ( $term_hit_count[ $tid ] ?? 0 ) >= $max_per_term ) {
					continue;
				}

				$p_count = count( $phrase_words );
				$cursor  = $i;
				$ok      = true;

				for ( $pw = 0; $pw < $p_count; ++$pw ) {
					if ( $cursor >= $t_len || ! $is_word[ $cursor ] ) {
						$ok = false;
						break;
					}
					$allow_pl_here = ( $allow_plurals && $pw === $p_count - 1 );
					if ( ! hj_glossary_word_matches( $norm_toks[ $cursor ], $phrase_words[ $pw ], $allow_pl_here ) ) {
						$ok = false;
						break;
					}
					if ( $pw < $p_count - 1 ) {
						// Next token MUST be pure whitespace or ZWNJ (never punctuation/tags).
						$sep_idx = $cursor + 1;
						if ( $sep_idx >= $t_len || $is_word[ $sep_idx ] || ! preg_match( '/^[\s\x{200C}]+$/u', $tokens[ $sep_idx ] ) ) {
							$ok = false;
							break;
						}
						$cursor += 2;
					}
				}

				if ( ! $ok ) {
					continue;
				}

				// Check what follows `$cursor`: if ZWNJ + word follows, ONLY allow if it's a grammatical suffix (and absorb it!).
				if ( $cursor + 2 < $t_len && "\xE2\x80\x8C" === $tokens[ $cursor + 1 ] && $is_word[ $cursor + 2 ] ) {
					$sfx = $norm_toks[ $cursor + 2 ];
					if ( $allow_plurals && isset( $zwnj_suffixes[ $sfx ] ) ) {
						$cursor += 2; // Absorb «‌ها» / «‌های» / «‌ای» cleanly into the highlight!
					} else {
						// Part of a larger compound word (e.g. «گاز‌گرفتگی») -> reject match!
						continue;
					}
				}

				$matched_end_idx = $cursor;
				$matched_tid     = $tid;
				break;
			}

			if ( $matched_end_idx >= $i && $matched_tid > 0 && isset( $terms_dict[ $matched_tid ] ) ) {
				$surface_str = '';
				for ( $k = $i; $k <= $matched_end_idx; ++$k ) {
					$surface_str .= $tokens[ $k ];
				}

				$out_buf .= '<span class="hj-glossary-term ' . esc_attr( $style_class ) . '" tabindex="0" role="button" aria-expanded="false" data-hj-term="' . (int) $matched_tid . '">' . $surface_str . '</span>';

				$term_hit_count[ $matched_tid ] = ( $term_hit_count[ $matched_tid ] ?? 0 ) + 1;
				$matched_terms[ $matched_tid ]  = $terms_dict[ $matched_tid ];
				++$total_hits;
				$changed = true;
				$i       = $matched_end_idx + 1;
			} else {
				$out_buf .= $tokens[ $i ];
				++$i;
			}
		}

		if ( $changed ) {
			$html_parts[ $part_idx ] = $out_buf;
		}
	}

	if ( empty( $matched_terms ) ) {
		return $content;
	}

	$payload = array(
		'showLink' => ! empty( $settings['show_glossary_link'] ) ? 1 : 0,
		'hubUrl'   => function_exists( 'hj_archive_url' ) ? hj_archive_url( 'glossary' ) : home_url( '/glossary/' ),
		'terms'    => $matched_terms,
	);

	$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$script_tag = "\n" . '<script type="application/json" id="hj-glossary-article-data">' . $json . '</script>' . "\n";

	return implode( '', $html_parts ) . $script_tag;
}
add_filter( 'the_content', 'hj_glossary_highlight_article_content', 14 );

/* =========================================================================
 * 5. Front-End Routing, AJAX Live Filter & `[hj_glossary]` Shortcode
 * ====================================================================== */

/**
 * Prevent WordPress from running expensive/404 main queries on `/glossary/`
 * and resolve `/glossary/{term-slug}/` directly from `hj_glossary_terms`.
 *
 * @param \WP_Query $query Main query object.
 * @return void
 */
function hj_glossary_optimize_main_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->is_post_type_archive( 'glossary' ) || $query->is_tax( 'glossary_group' ) ) {
		$query->set( 'posts_per_page', 1 );
		$query->set( 'no_found_rows', true );
	}
}
add_action( 'pre_get_posts', 'hj_glossary_optimize_main_query' );

/**
 * Resolve direct single-term URLs (`/glossary/{slug}/`) stored in `hj_glossary_terms`
 * even when no `wp_posts` row exists for that term.
 *
 * @return void
 */
function hj_glossary_resolve_single_term_route() {
	if ( is_admin() || ! is_404() ) {
		return;
	}

	$req_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
	if ( ! is_string( $req_path ) || ! preg_match( '#/glossary/([^/]+)/?$#', $req_path, $m ) ) {
		return;
	}

	$raw_slug = rawurldecode( $m[1] );
	$slug     = sanitize_title( $raw_slug );
	if ( '' === $slug ) {
		return;
	}

	hj_glossary_ensure_table();
	global $wpdb, $wp_query;
	$table = hj_glossary_table_name();

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE status = 'publish' AND (slug = %s OR slug = %s OR term_norm = %s) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug,
			$raw_slug,
			hj_glossary_normalize_text( $raw_slug, true )
		),
		ARRAY_A
	);

	if ( ! $row ) {
		return;
	}

	$groups                         = hj_glossary_get_groups_with_counts( false );
	$gid                            = (int) $row['group_id'];
	$gobj                           = $groups[ $gid ] ?? null;
	$GLOBALS['hj_single_term_data'] = array(
		'id'         => (int) $row['id'],
		'term'       => (string) $row['term'],
		'slug'       => (string) $row['slug'],
		'en_term'    => (string) $row['en_term'],
		'aliases'    => (string) $row['aliases'],
		'definition' => (string) $row['definition'],
		'group_id'   => $gid,
		'group_name' => $gobj ? $gobj['name'] : '',
		'group_url'  => $gobj ? $gobj['url'] : '',
	);

	status_header( 200 );
	$wp_query->is_404     = false;
	$wp_query->is_single  = false;
	$wp_query->is_archive = false;

	$tpl = locate_template( 'single-glossary.php' );
	if ( $tpl ) {
		include $tpl;
		exit;
	}
}
add_action( 'template_redirect', 'hj_glossary_resolve_single_term_route', 5 );

/**
 * Exclude thin `glossary` CPT posts (if any) from `wp-sitemap.xml` so only the
 * rich `/glossary/` hub and `/glossary-group/` categories get indexed.
 *
 * @param array<string,\WP_Post_Type> $post_types Sitemap post types.
 * @return array<string,\WP_Post_Type>
 */
function hj_glossary_filter_sitemap_post_types( $post_types ) {
	if ( is_array( $post_types ) && isset( $post_types['glossary'] ) ) {
		unset( $post_types['glossary'] );
	}
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'hj_glossary_filter_sitemap_post_types' );

/**
 * Public AJAX endpoint (`hj_glossary_filter`) for instant search, topic & alphabet filtering on `/glossary/`.
 *
 * @return void
 */
function hj_glossary_ajax_filter() {
	$search     = isset( $_GET['gq'] ) ? sanitize_text_field( wp_unslash( $_GET['gq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$group_slug = isset( $_GET['gcat'] ) ? sanitize_title( wp_unslash( $_GET['gcat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$letter     = isset( $_GET['gletter'] ) ? sanitize_text_field( wp_unslash( $_GET['gletter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page       = isset( $_GET['gpage'] ) ? max( 1, (int) $_GET['gpage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$settings = hj_glossary_get_settings();
	$per_page = (int) ( $settings['per_page'] ?? 60 );

	$result = hj_glossary_query_terms(
		array(
			'status'     => 'publish',
			'search'     => $search,
			'group_slug' => $group_slug,
			'letter'     => $letter,
			'page'       => $page,
			'per_page'   => $per_page,
		)
	);

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_hj_glossary_filter', 'hj_glossary_ajax_filter' );
add_action( 'wp_ajax_nopriv_hj_glossary_filter', 'hj_glossary_ajax_filter' );

/**
 * Shortcode `[hj_glossary]` so the interactive glossary directory can also be
 * embedded inside any WordPress page if desired.
 *
 * @param array<string,string> $atts Shortcode attributes.
 * @return string
 */
function hj_glossary_shortcode_render( $atts = array() ): string {
	$atts = shortcode_atts(
		array(
			'group' => '',
		),
		$atts,
		'hj_glossary'
	);
	ob_start();
	$GLOBALS['hj_glossary_forced_group_slug'] = sanitize_title( (string) $atts['group'] );
	get_template_part( 'template-parts/glossary', 'directory' );
	unset( $GLOBALS['hj_glossary_forced_group_slug'] );
	return (string) ob_get_clean();
}
add_shortcode( 'hj_glossary', 'hj_glossary_shortcode_render' );

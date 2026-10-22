<?php
/**
 * Plugin Name: پاکسازی و استانداردسازی هوشمند واژه‌نامه همیار جراحی (HJ Glossary Cleanup)
 * Plugin URI:  https://hamyarjarahi.ir/glossary/
 * Description: پاکسازی خودکار، حذف موارد تکراری و نامربوط، اصلاح علمی و املایی و بازگردانی اصطلاحات خط‌تیره‌دار جاافتاده در دو دسته‌ی «استریلیزاسیون و کنترل عفونت (sterile-terms)» و «ابزار و تجهیزات جراحی (device-terms)» به محض فعال‌سازی افزونه — بدون هیچ‌گونه تغییر در سایر دسته‌های واژه‌نامه (اصطلاحات عمومی جراحی و اصطلاحات بیهوشی) و همراه با پشتیبان‌گیری خودکار و بازسازی کش هایلایت سبز.
 * Version:     1.0.0
 * Author:      Hamyar Jarahi Engineering
 * Author URI:  https://hamyarjarahi.ir/
 * License:     GPL-2.0-or-later
 * Text Domain: hj-glossary-cleanup
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HJ_GLOSSARY_CLEANUP_VERSION', '1.0.0' );
define( 'HJ_GLOSSARY_CLEANUP_DIR', plugin_dir_path( __FILE__ ) );

final class HJ_Glossary_Cleanup {

	private const OPTION_STATE   = 'hj_glossary_cleanup_state_v1';
	private const OPTION_NOTICE  = 'hj_glossary_cleanup_flash_notice';
	private const LOCK_TRANSIENT = 'hj_glossary_cleanup_running_lock';

	/**
	 * Wire WordPress lifecycle hooks.
	 */
	public static function boot(): void {
		register_activation_hook( __FILE__, array( __CLASS__, 'on_activate' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_autorun_on_admin' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'admin_post_hj_glossary_cleanup_rerun', array( __CLASS__, 'handle_manual_rerun' ) );
		add_action( 'admin_post_hj_glossary_cleanup_rollback', array( __CLASS__, 'handle_manual_rollback' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'plugin_action_links' ) );
	}

	/**
	 * Run full cleanup automatically immediately upon plugin activation.
	 */
	public static function on_activate(): void {
		$report = self::run_cleanup( false );
		update_option( self::OPTION_NOTICE, $report, false );
	}

	/**
	 * Fallback auto-run on first admin request if activated via WP-CLI / direct unzip,
	 * plus ensure compiled green-highlighter index is freshly rebuilt once child theme functions load.
	 */
	public static function maybe_autorun_on_admin(): void {
		$state = get_option( self::OPTION_STATE, array() );
		if ( empty( $state['completed_version'] ) || HJ_GLOSSARY_CLEANUP_VERSION !== $state['completed_version'] ) {
			$report = self::run_cleanup( false );
			update_option( self::OPTION_NOTICE, $report, false );
			return;
		}

		// Ensure theme's compiled O(1) green highlighter file is rebuilt after theme functions load.
		if ( empty( $state['highlighter_rebuilt'] ) && function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'glossary_group' ) && function_exists( 'hj_glossary_rebuild_index' ) ) {
			hj_glossary_rebuild_index();
			$state['highlighter_rebuilt'] = 1;
			update_option( self::OPTION_STATE, $state, false );
		}
	}

	/**
	 * Add quick "گزارش پاکسازی" link directly on the WordPress Plugins row.
	 *
	 * @param array<int,string> $links Existing action links.
	 * @return array<int,string>
	 */
	public static function plugin_action_links( array $links ): array {
		$url   = admin_url( 'tools.php?page=hj-glossary-cleanup' );
		$label = '<a href="' . esc_url( $url ) . '" style="font-weight:700;color:#059669;">گزارش پاکسازی واژه‌نامه</a>';
		array_unshift( $links, $label );
		return $links;
	}

	/**
	 * Register status, report & 1-click rollback page under Tools -> پاکسازی واژه‌نامه.
	 */
	public static function register_admin_menu(): void {
		add_management_page(
			'پاکسازی خودکار واژه‌نامه پزشکی',
			'پاکسازی واژه‌نامه',
			'manage_options',
			'hj-glossary-cleanup',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Normalize text identically to `hj_glossary_normalize_text( $text, true )` in `lax-theme-child`.
	 */
	public static function normalize_text( string $text, bool $collapse_zwnj = true ): string {
		if ( function_exists( 'hj_glossary_normalize_text' ) ) {
			return hj_glossary_normalize_text( $text, $collapse_zwnj );
		}

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

		$norm     = strtr( $text, $map );
		$stripped = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{FEFF}\x{200B}\x{200D}\x{200E}\x{200F}]/u', '', $norm );
		if ( is_string( $stripped ) ) {
			$norm = $stripped;
		}

		$norm = function_exists( 'mb_strtolower' ) ? mb_strtolower( $norm, 'UTF-8' ) : strtolower( $norm );

		if ( $collapse_zwnj ) {
			$norm = str_replace( "\xE2\x80\x8C", ' ', $norm );
		}

		$collapsed = preg_replace( '/\s+/u', ' ', trim( $norm ) );
		return is_string( $collapsed ) ? $collapsed : trim( $norm );
	}

	/**
	 * Extract canonical Persian/English starting letter identically to `hj_glossary_extract_first_letter()`.
	 */
	public static function extract_first_letter( string $term ): string {
		if ( function_exists( 'hj_glossary_extract_first_letter' ) ) {
			return hj_glossary_extract_first_letter( $term );
		}

		$clean = ltrim( trim( $term ), "«»\"'()[]{} \t\n\r\0\x0B" );
		if ( '' === $clean || ! preg_match( '/^(\X)/u', $clean, $m ) ) {
			return '#';
		}

		$ch = $m[1];
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

		$persian = array(
			'آ', 'ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ',
			'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط',
			'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن',
			'و', 'ه', 'ی',
		);
		if ( in_array( $ch, $persian, true ) ) {
			return $ch;
		}

		if ( preg_match( '/^[A-Z]$/', strtoupper( $ch ) ) ) {
			return 'EN';
		}

		return '#';
	}

	/**
	 * Build clean URL slug identically to `hj_glossary_make_slug()`.
	 */
	public static function make_slug( string $term ): string {
		if ( function_exists( 'hj_glossary_make_slug' ) ) {
			return hj_glossary_make_slug( $term );
		}

		$slug = sanitize_title( $term );
		if ( '' === $slug ) {
			$slug = 'term-' . substr( md5( $term ), 0, 10 );
		}
		if ( strlen( $slug ) > 180 ) {
			$slug = substr( $slug, 0, 180 );
		}
		return $slug;
	}

	/**
	 * Resolve `sterile-terms` and `device-terms` group IDs directly from SQL taxonomy tables
	 * so resolution succeeds even during `register_activation_hook` before `init` fires.
	 *
	 * Explicitly blocks touching `general-terms` or `anesthesia-terms` under all circumstances.
	 *
	 * @return array{ok:bool,sterile_id:int,device_id:int,protected_ids:int[],message:string}
	 */
	public static function resolve_target_groups(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT t.term_id, t.slug, t.name
			 FROM `{$wpdb->terms}` AS t
			 INNER JOIN `{$wpdb->term_taxonomy}` AS tt ON tt.term_id = t.term_id
			 WHERE tt.taxonomy = 'glossary_group'",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sterile_id    = 0;
		$device_id     = 0;
		$protected_ids = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$tid  = (int) $r['term_id'];
				$slug = rawurldecode( strtolower( trim( (string) $r['slug'] ) ) );
				if ( 'sterile-terms' === $slug ) {
					$sterile_id = $tid;
				} elseif ( 'device-terms' === $slug ) {
					$device_id = $tid;
				} else {
					$protected_ids[] = $tid;
				}
			}
		}

		if ( $sterile_id <= 0 || $device_id <= 0 || $sterile_id === $device_id ) {
			return array(
				'ok'            => false,
				'sterile_id'    => $sterile_id,
				'device_id'     => $device_id,
				'protected_ids' => $protected_ids,
				'message'       => 'دسته‌بندی‌های sterile-terms یا device-terms در جدول taxonomy واژه‌نامه یافت نشدند.',
			);
		}

		if ( in_array( $sterile_id, $protected_ids, true ) || in_array( $device_id, $protected_ids, true ) ) {
			return array(
				'ok'            => false,
				'sterile_id'    => 0,
				'device_id'     => 0,
				'protected_ids' => $protected_ids,
				'message'       => 'تداخل شناسه دسته‌بندی شناسایی شد؛ عملیات برای حفاظت از سایر دسته‌ها متوقف شد.',
			);
		}

		return array(
			'ok'            => true,
			'sterile_id'    => $sterile_id,
			'device_id'     => $device_id,
			'protected_ids' => $protected_ids,
			'message'       => 'OK',
		);
	}

	/**
	 * Return secure cache/backup directory path (`wp-content/uploads/hj-cache/`) and harden it against direct listing.
	 */
	private static function ensure_secure_backup_dir(): string {
		$uploads = wp_upload_dir( null, false );
		$basedir = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$dir     = trailingslashit( $basedir ) . 'hj-cache';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( is_dir( $dir ) && is_writable( $dir ) ) {
			$idx = $dir . '/index.php';
			if ( ! file_exists( $idx ) ) {
				@file_put_contents( $idx, "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			$hta = $dir . '/.htaccess';
			if ( ! file_exists( $hta ) ) {
				@file_put_contents( $hta, "<FilesMatch \"\\.json$\">\nOrder allow,deny\nDeny from all\n</FilesMatch>\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		return $dir;
	}

	/**
	 * Execute the full atomic, idempotent cleanup on `sterile-terms` and `device-terms` ONLY.
	 *
	 * @param bool $force Force re-run even if already executed.
	 * @return array<string,mixed>
	 */
	public static function run_cleanup( bool $force = false ): array {
		global $wpdb;

		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return array(
				'ok'      => false,
				'message' => 'عملیات پاکسازی هم‌اکنون در درخواست دیگری در حال اجراست.',
			);
		}
		set_transient( self::LOCK_TRANSIENT, 1, 120 );

		try {
			$started_at = microtime( true );
			$table      = $wpdb->prefix . 'hj_glossary_terms';

			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $exists !== $table ) {
				delete_transient( self::LOCK_TRANSIENT );
				return array(
					'ok'      => false,
					'message' => sprintf( 'جدول %s در پایگاه‌داده یافت نشد.', $table ),
				);
			}

			$groups = self::resolve_target_groups();
			if ( empty( $groups['ok'] ) ) {
				delete_transient( self::LOCK_TRANSIENT );
				return array(
					'ok'      => false,
					'message' => $groups['message'],
				);
			}

			$sterile_id = (int) $groups['sterile_id'];
			$device_id  = (int) $groups['device_id'];

			// Snapshot untouched groups row count BEFORE cleanup to prove 0 changes on general/anesthesia!
			$untouched_before = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE group_id NOT IN (%d, %d)",
					$sterile_id,
					$device_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Load pre-cleanup rows for target groups ONLY.
			$existing_target_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE group_id IN (%d, %d) ORDER BY id ASC",
					$sterile_id,
					$device_id
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( ! is_array( $existing_target_rows ) ) {
				$existing_target_rows = array();
			}

			// Save lossless pre-cleanup backup ONCE before first modification so original data is never lost!
			$prev_state  = get_option( self::OPTION_STATE, array() );
			$backup_path = ! empty( $prev_state['backup_file'] ) ? (string) $prev_state['backup_file'] : '';
			if ( '' === $backup_path || ! file_exists( $backup_path ) ) {
				$dir         = self::ensure_secure_backup_dir();
				$candidate   = $dir . '/glossary-backup-pre-cleanup-' . wp_generate_password( 12, false, false ) . '.json';
				$backup_json = wp_json_encode(
					array(
						'created_at' => current_time( 'mysql' ),
						'sterile_id' => $sterile_id,
						'device_id'  => $device_id,
						'row_count'  => count( $existing_target_rows ),
						'rows'       => $existing_target_rows,
					),
					JSON_UNESCAPED_UNICODE
				);
				if ( is_string( $backup_json ) && false !== @file_put_contents( $candidate, $backup_json, LOCK_EX ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$backup_path = $candidate;
				}
			}

			// Collect ALL `term_norm` values currently occupied by UNTOUCHED groups (e.g. general-terms & anesthesia-terms)
			// so `UNIQUE KEY idx_term_norm` can NEVER collide with another category under any circumstance!
			$protected_rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_norm, slug FROM `{$table}` WHERE group_id NOT IN (%d, %d)",
					$sterile_id,
					$device_id
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$used_norms = array();
			$used_slugs = array();
			if ( is_array( $protected_rows ) ) {
				foreach ( $protected_rows as $pr ) {
					if ( ! empty( $pr['term_norm'] ) ) {
						$used_norms[ (string) $pr['term_norm'] ] = true;
					}
					if ( ! empty( $pr['slug'] ) ) {
						$used_slugs[ (string) $pr['slug'] ] = true;
					}
				}
			}

			$datasets = array(
				'sterile' => array(
					'group_id' => $sterile_id,
					'label'    => 'استریلیزاسیون و کنترل عفونت (sterile-terms)',
					'suffix'   => ' (استریلیزاسیون)',
					'spec'     => require HJ_GLOSSARY_CLEANUP_DIR . 'data/sterile-dataset.php',
				),
				'device'  => array(
					'group_id' => $device_id,
					'label'    => 'ابزار و تجهیزات جراحی (device-terms)',
					'suffix'   => ' (ابزار جراحی)',
					'spec'     => require HJ_GLOSSARY_CLEANUP_DIR . 'data/device-dataset.php',
				),
			);

			$stats = array();
			$now   = current_time( 'mysql' );

			foreach ( $datasets as $key => $cfg ) {
				$gid  = (int) $cfg['group_id'];
				$spec = $cfg['spec'];

				$group_rows = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM `{$table}` WHERE group_id = %d ORDER BY id ASC", $gid ),
					ARRAY_A
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( ! is_array( $group_rows ) ) {
					$group_rows = array();
				}

				$before_count  = count( $group_rows );
				$deleted_ids   = array();
				$by_raw_norm   = array();
				$by_clean_norm = array();

				foreach ( $group_rows as $row ) {
					$rid = (int) $row['id'];
					$rn  = self::normalize_text( (string) $row['term'], true );
					if ( isset( $spec['delete_raw_norms'][ $rn ] ) ) {
						$deleted_ids[] = $rid;
						continue;
					}
					$by_raw_norm[ $rn ]   = $row;
					$by_clean_norm[ $rn ] = $row;
				}

				// Stage 1: Delete off-topic, non-OR, and duplicate rows strictly within this `group_id`.
				if ( ! empty( $deleted_ids ) ) {
					foreach ( array_chunk( $deleted_ids, 200 ) as $id_chunk ) {
						$placeholders = implode( ',', array_fill( 0, count( $id_chunk ), '%d' ) );
						$params       = array_merge( array( $gid ), $id_chunk );
						$wpdb->query(
							$wpdb->prepare(
								"DELETE FROM `{$table}` WHERE group_id = %d AND id IN ({$placeholders})",
								$params
							)
						); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					}
				}

				// Stage 1.5: Park all surviving rows of THIS group under temporary unique `term_norm` tokens
				// (`__hj_tmp_{id}`) before writing target `term_norm` values so even simultaneous in-place swaps
				// (such as #110 Steam Generator <-> #111 Boiler) can NEVER hit transient MySQL index collisions!
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$table}` SET term_norm = CONCAT('__hj_tmp_', id) WHERE group_id = %d",
						$gid
					)
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				// Helper to allocate conflict-free `(term, term_norm, slug)` across the entire table.
				$allocate_identity = static function ( string $base_term ) use ( &$used_norms, &$used_slugs, $cfg ): array {
					$term      = trim( $base_term );
					$term_norm = HJ_Glossary_Cleanup::normalize_text( $term, true );
					if ( isset( $used_norms[ $term_norm ] ) ) {
						$term      = $term . $cfg['suffix'];
						$term_norm = HJ_Glossary_Cleanup::normalize_text( $term, true );
						$attempt   = 2;
						while ( isset( $used_norms[ $term_norm ] ) ) {
							$term      = trim( $base_term ) . $cfg['suffix'] . ' ' . $attempt;
							$term_norm = HJ_Glossary_Cleanup::normalize_text( $term, true );
							++$attempt;
						}
					}
					$used_norms[ $term_norm ] = true;

					$base_slug = HJ_Glossary_Cleanup::make_slug( $term );
					$slug      = $base_slug;
					$s_idx     = 2;
					while ( isset( $used_slugs[ $slug ] ) ) {
						$slug = substr( $base_slug, 0, 172 ) . '-' . $s_idx;
						++$s_idx;
					}
					$used_slugs[ $slug ] = true;

					return array(
						'term'      => $term,
						'term_norm' => $term_norm,
						'slug'      => $slug,
						'letter'    => HJ_Glossary_Cleanup::extract_first_letter( $term ),
					);
				};

				// Stage 2: Update all curated kept rows (supports both fresh run via `src_raw_norm` AND idempotent re-run via cleaned `term_norm`).
				$updated_count  = 0;
				$inserted_count = 0;
				$claimed_ids    = array();

				foreach ( $spec['updates'] as $src_norm => $u ) {
					$target_clean_norm = self::normalize_text( (string) $u['term'], true );
					$disambig_norm     = self::normalize_text( (string) $u['term'] . $cfg['suffix'], true );
					$matched_row       = null;

					if ( isset( $by_raw_norm[ $src_norm ] ) && empty( $claimed_ids[ (int) $by_raw_norm[ $src_norm ]['id'] ] ) ) {
						$matched_row = $by_raw_norm[ $src_norm ];
					} elseif ( isset( $by_clean_norm[ $target_clean_norm ] ) && empty( $claimed_ids[ (int) $by_clean_norm[ $target_clean_norm ]['id'] ] ) ) {
						$matched_row = $by_clean_norm[ $target_clean_norm ];
					} elseif ( isset( $by_clean_norm[ $disambig_norm ] ) && empty( $claimed_ids[ (int) $by_clean_norm[ $disambig_norm ]['id'] ] ) ) {
						$matched_row = $by_clean_norm[ $disambig_norm ];
					}

					$ident = $allocate_identity( (string) $u['term'] );

					if ( null !== $matched_row ) {
						$rid                 = (int) $matched_row['id'];
						$claimed_ids[ $rid ] = true;
						$wpdb->update(
							$table,
							array(
								'term'         => $ident['term'],
								'term_norm'    => $ident['term_norm'],
								'slug'         => $ident['slug'],
								'en_term'      => (string) $u['en_term'],
								'aliases'      => (string) $u['aliases'],
								'definition'   => (string) $u['definition'],
								'first_letter' => $ident['letter'],
								'updated_at'   => $now,
							),
							array(
								'id'       => $rid,
								'group_id' => $gid,
							),
							array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
							array( '%d', '%d' )
						); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						++$updated_count;
					} else {
						$wpdb->insert(
							$table,
							array(
								'term'              => $ident['term'],
								'term_norm'         => $ident['term_norm'],
								'slug'              => $ident['slug'],
								'en_term'           => (string) $u['en_term'],
								'aliases'           => (string) $u['aliases'],
								'definition'        => (string) $u['definition'],
								'group_id'          => $gid,
								'first_letter'      => $ident['letter'],
								'highlight_enabled' => 1,
								'status'            => 'publish',
								'created_at'        => $now,
								'updated_at'        => $now,
							),
							array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
						); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						++$inserted_count;
					}
				}

				// Stage 3: Restore valid clinical hyphenated entries that were skipped by `.txt` importer regex (or update in place on re-run).
				foreach ( $spec['inserts'] as $ins ) {
					$target_clean_norm = self::normalize_text( (string) $ins['term'], true );
					$disambig_norm     = self::normalize_text( (string) $ins['term'] . $cfg['suffix'], true );
					$ident             = $allocate_identity( (string) $ins['term'] );
					$matched_ins       = null;

					if ( isset( $by_clean_norm[ $target_clean_norm ] ) && empty( $claimed_ids[ (int) $by_clean_norm[ $target_clean_norm ]['id'] ] ) ) {
						$matched_ins = $by_clean_norm[ $target_clean_norm ];
					} elseif ( isset( $by_clean_norm[ $disambig_norm ] ) && empty( $claimed_ids[ (int) $by_clean_norm[ $disambig_norm ]['id'] ] ) ) {
						$matched_ins = $by_clean_norm[ $disambig_norm ];
					}

					if ( null !== $matched_ins ) {
						$rid                 = (int) $matched_ins['id'];
						$claimed_ids[ $rid ] = true;
						$wpdb->update(
							$table,
							array(
								'term'         => $ident['term'],
								'term_norm'    => $ident['term_norm'],
								'slug'         => $ident['slug'],
								'en_term'      => (string) $ins['en_term'],
								'aliases'      => (string) $ins['aliases'],
								'definition'   => (string) $ins['definition'],
								'first_letter' => $ident['letter'],
								'updated_at'   => $now,
							),
							array(
								'id'       => $rid,
								'group_id' => $gid,
							),
							array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
							array( '%d', '%d' )
						); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						++$updated_count;
					} else {
						$wpdb->insert(
							$table,
							array(
								'term'              => $ident['term'],
								'term_norm'         => $ident['term_norm'],
								'slug'              => $ident['slug'],
								'en_term'           => (string) $ins['en_term'],
								'aliases'           => (string) $ins['aliases'],
								'definition'        => (string) $ins['definition'],
								'group_id'          => $gid,
								'first_letter'      => $ident['letter'],
								'highlight_enabled' => 1,
								'status'            => 'publish',
								'created_at'        => $now,
								'updated_at'        => $now,
							),
							array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
						); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						++$inserted_count;
					}
				}

				// Restore normal `term_norm` for any custom admin-added rows in this group that weren't in original bulk import.
				foreach ( $group_rows as $row ) {
					$rid = (int) $row['id'];
					if ( in_array( $rid, $deleted_ids, true ) || ! empty( $claimed_ids[ $rid ] ) ) {
						continue;
					}
					$ident = $allocate_identity( (string) $row['term'] );
					$wpdb->update(
						$table,
						array(
							'term'         => $ident['term'],
							'term_norm'    => $ident['term_norm'],
							'slug'         => $ident['slug'],
							'first_letter' => $ident['letter'],
						),
						array(
							'id'       => $rid,
							'group_id' => $gid,
						),
						array( '%s', '%s', '%s', '%s' ),
						array( '%d', '%d' )
					); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}

				// Sync custom table publish count into `wp_term_taxonomy.count` for this `glossary_group`.
				$after_count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM `{$table}` WHERE group_id = %d AND status = 'publish'",
						$gid
					)
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				$wpdb->update(
					$wpdb->term_taxonomy,
					array( 'count' => $after_count ),
					array(
						'term_id'  => $gid,
						'taxonomy' => 'glossary_group',
					),
					array( '%d' ),
					array( '%d', '%s' )
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

				$stats[ $key ] = array(
					'label'    => $cfg['label'],
					'group_id' => $gid,
					'before'   => $before_count,
					'deleted'  => count( $deleted_ids ),
					'updated'  => $updated_count,
					'inserted' => $inserted_count,
					'after'    => $after_count,
				);
			}

			// Verify untouched groups have identical row count after cleanup.
			$untouched_after = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE group_id NOT IN (%d, %d)",
					$sterile_id,
					$device_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$rebuilt_highlighter = self::flush_all_glossary_caches();
			$elapsed_ms          = (int) round( ( microtime( true ) - $started_at ) * 1000 );

			$report = array(
				'ok'                  => true,
				'completed_version'   => HJ_GLOSSARY_CLEANUP_VERSION,
				'highlighter_rebuilt' => $rebuilt_highlighter ? 1 : 0,
				'executed_at'         => current_time( 'mysql' ),
				'elapsed_ms'          => $elapsed_ms,
				'backup_file'         => $backup_path,
				'untouched_before'    => $untouched_before,
				'untouched_after'     => $untouched_after,
				'stats'               => $stats,
			);

			update_option( self::OPTION_STATE, $report, false );
			delete_transient( self::LOCK_TRANSIENT );
			return $report;
		} catch ( \Throwable $e ) {
			delete_transient( self::LOCK_TRANSIENT );
			return array(
				'ok'      => false,
				'message' => 'خطای غیرمنتظره حین پاکسازی: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Restore exact pre-cleanup database snapshot for `sterile-terms` and `device-terms` if requested.
	 *
	 * @return array<string,mixed>
	 */
	public static function rollback_from_backup(): array {
		global $wpdb;

		$state       = get_option( self::OPTION_STATE, array() );
		$backup_path = ! empty( $state['backup_file'] ) ? (string) $state['backup_file'] : '';
		if ( '' === $backup_path || ! is_readable( $backup_path ) ) {
			return array(
				'ok'      => false,
				'message' => 'فایل پشتیبان پیش از پاکسازی یافت نشد.',
			);
		}

		$raw  = @file_get_contents( $backup_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['rows'], $data['sterile_id'], $data['device_id'] ) || ! is_array( $data['rows'] ) ) {
			return array(
				'ok'      => false,
				'message' => 'ساختار فایل پشتیبان معتبر نیست.',
			);
		}

		$table      = $wpdb->prefix . 'hj_glossary_terms';
		$sterile_id = (int) $data['sterile_id'];
		$device_id  = (int) $data['device_id'];

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE group_id IN (%d, %d)",
				$sterile_id,
				$device_id
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$restored = 0;
		foreach ( $data['rows'] as $row ) {
			$wpdb->insert(
				$table,
				array(
					'id'                => (int) $row['id'],
					'term'              => (string) $row['term'],
					'term_norm'         => (string) $row['term_norm'],
					'slug'              => (string) $row['slug'],
					'en_term'           => (string) $row['en_term'],
					'aliases'           => (string) ( $row['aliases'] ?? '' ),
					'definition'        => (string) $row['definition'],
					'group_id'          => (int) $row['group_id'],
					'first_letter'      => (string) $row['first_letter'],
					'highlight_enabled' => (int) $row['highlight_enabled'],
					'status'            => (string) $row['status'],
					'created_at'        => (string) $row['created_at'],
					'updated_at'        => (string) $row['updated_at'],
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			++$restored;
		}

		foreach ( array( $sterile_id, $device_id ) as $gid ) {
			$cnt = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE group_id = %d AND status = 'publish'", $gid )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update(
				$wpdb->term_taxonomy,
				array( 'count' => $cnt ),
				array(
					'term_id'  => $gid,
					'taxonomy' => 'glossary_group',
				),
				array( '%d' ),
				array( '%d', '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		self::flush_all_glossary_caches();

		return array(
			'ok'       => true,
			'restored' => $restored,
			'message'  => sprintf( 'بازگردانی نسخه پشتیبان با موفقیت انجام شد (%s رکورد اولیه بازیابی شد).', number_format_i18n( $restored ) ),
		);
	}

	/**
	 * Rebuild green article highlighter cache & purge glossary transients / page caches.
	 */
	public static function flush_all_glossary_caches(): bool {
		global $wpdb;

		// Remove stale compiled PHP highlighter file first so even if child theme loads on next request, it compiles clean.
		$uploads    = wp_upload_dir( null, false );
		$basedir    = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$index_file = trailingslashit( $basedir ) . 'hj-cache/glossary-index-v1.php';
		if ( file_exists( $index_file ) ) {
			@unlink( $index_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( function_exists( 'opcache_invalidate' ) ) {
				@opcache_invalidate( $index_file, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		// Delete all glossary transients.
		$wpdb->query(
			"DELETE FROM `{$wpdb->options}`
			 WHERE option_name LIKE '\_transient\_hj\_glossary%'
			    OR option_name LIKE '\_transient\_timeout\_hj\_glossary%'"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( function_exists( 'hj_glossary_bump_cache' ) ) {
			hj_glossary_bump_cache();
		}

		$rebuilt = false;
		if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'glossary_group' ) && function_exists( 'hj_glossary_rebuild_index' ) ) {
			hj_glossary_rebuild_index();
			$rebuilt = true;
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Purge popular WordPress page caching plugins (LiteSpeed, WP Rocket, W3TC, WP Super Cache).
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		return $rebuilt;
	}

	/**
	 * Handle manual 1-click re-run action from Tools -> پاکسازی واژه‌نامه.
	 */
	public static function handle_manual_rerun(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'hj_glossary_cleanup_rerun_nonce' );
		$report = self::run_cleanup( true );
		update_option( self::OPTION_NOTICE, $report, false );
		wp_safe_redirect( admin_url( 'tools.php?page=hj-glossary-cleanup' ) );
		exit;
	}

	/**
	 * Handle manual 1-click backup restore from Tools -> پاکسازی واژه‌نامه.
	 */
	public static function handle_manual_rollback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'hj_glossary_cleanup_rollback_nonce' );
		$res = self::rollback_from_backup();
		update_option( self::OPTION_NOTICE, $res, false );
		wp_safe_redirect( admin_url( 'tools.php?page=hj-glossary-cleanup' ) );
		exit;
	}

	/**
	 * Render immediate Persian flash banner right after plugin activation.
	 */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = get_option( self::OPTION_NOTICE, null );
		if ( empty( $report ) || ! is_array( $report ) ) {
			return;
		}
		delete_option( self::OPTION_NOTICE );

		if ( empty( $report['ok'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p><strong>پاکسازی واژه‌نامه همیار جراحی:</strong> %s</p></div>',
				esc_html( (string) ( $report['message'] ?? 'خطا در اجرای عملیات.' ) )
			);
			return;
		}

		if ( isset( $report['restored'] ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>پاکسازی واژه‌نامه همیار جراحی:</strong> %s</p></div>',
				esc_html( (string) $report['message'] )
			);
			return;
		}

		$s = $report['stats']['sterile'] ?? array();
		$d = $report['stats']['device'] ?? array();
		?>
		<div class="notice notice-success is-dismissible" dir="rtl" style="padding:14px 18px;border-right-width:5px;">
			<p style="margin:0 0 8px;font-size:14px;">
				<strong>✅ پاکسازی خودکار واژه‌نامه همیار جراحی با موفقیت انجام شد (در <?php echo esc_html( number_format_i18n( (int) ( $report['elapsed_ms'] ?? 0 ) ) ); ?> میلی‌ثانیه):</strong>
			</p>
			<ul style="margin:0 0 8px 18px;list-style:disc;">
				<li>
					<strong>استریلیزاسیون و کنترل عفونت (`sterile-terms`):</strong>
					از <strong><?php echo esc_html( number_format_i18n( (int) ( $s['before'] ?? 0 ) ) ); ?></strong> مورد اولیه ←
					<strong><?php echo esc_html( number_format_i18n( (int) ( $s['deleted'] ?? 0 ) ) ); ?></strong> مورد نامربوط/تکراری حذف شد،
					<strong><?php echo esc_html( number_format_i18n( (int) ( $s['updated'] ?? 0 ) ) ); ?></strong> مورد اصلاح و شماره‌زدایی شد و
					<strong><?php echo esc_html( number_format_i18n( (int) ( $s['inserted'] ?? 0 ) ) ); ?></strong> اصطلاح خط‌تیره‌دار جاافتاده اضافه شد
					← مجموع نهایی: <strong><?php echo esc_html( number_format_i18n( (int) ( $s['after'] ?? 0 ) ) ); ?> اصطلاح استاندارد</strong>.
				</li>
				<li>
					<strong>ابزار و تجهیزات جراحی (`device-terms`):</strong>
					از <strong><?php echo esc_html( number_format_i18n( (int) ( $d['before'] ?? 0 ) ) ); ?></strong> مورد اولیه ←
					<strong><?php echo esc_html( number_format_i18n( (int) ( $d['deleted'] ?? 0 ) ) ); ?></strong> مورد غیرمرتبط با اتاق عمل و تکراری حذف شد،
					<strong><?php echo esc_html( number_format_i18n( (int) ( $d['updated'] ?? 0 ) ) ); ?></strong> مورد اصلاح علمی/املایی و شماره‌زدایی شد و
					<strong><?php echo esc_html( number_format_i18n( (int) ( $d['inserted'] ?? 0 ) ) ); ?></strong> ابزار خط‌تیره‌دار جاافتاده بازگردانی شد
					← مجموع نهایی: <strong><?php echo esc_html( number_format_i18n( (int) ( $d['after'] ?? 0 ) ) ); ?> ابزار استاندارد</strong>.
				</li>
				<li>
					<strong>حفاظت کامل از سایر دسته‌ها:</strong>
					دسته‌های «اصطلاحات عمومی جراحی» و «اصطلاحات بیهوشی» بدون حتی یک تغییر دست‌نخورده باقی ماندند (<strong><?php echo esc_html( number_format_i18n( (int) ( $report['untouched_after'] ?? 0 ) ) ); ?></strong> واژه دست‌نخورده).
				</li>
			</ul>
			<p style="margin:0;color:#475569;font-size:12.5px;">
				نسخه پشتیبان پیش از پاکسازی به‌طور خودکار ذخیره شد و کش هایلایت سبز مقالات بازسازی گردید.
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=hj-glossary-cleanup' ) ); ?>" style="font-weight:700;margin-right:8px;">مشاهده گزارش کامل و ابزار بازگردانی ←</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render full management & verification dashboard under Tools -> پاکسازی واژه‌نامه.
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		$state = get_option( self::OPTION_STATE, array() );
		$s     = $state['stats']['sterile'] ?? null;
		$d     = $state['stats']['device'] ?? null;
		?>
		<div class="wrap" dir="rtl" style="max-width:980px;font-family:Tahoma,system-ui,sans-serif;">
			<h1 style="margin-bottom:16px;">پاکسازی و استانداردسازی خودکار واژه‌نامه همیار جراحی</h1>

			<div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:22px 24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
				<h2 style="margin-top:0;color:#0f172a;">وضعیت اجرای پاکسازی پایگاه‌داده</h2>
				<?php if ( $s && $d ) : ?>
					<p style="color:#047857;font-weight:700;font-size:14px;">
						✅ آخرین اجرا با موفقیت در تاریخ <span dir="ltr"><?php echo esc_html( (string) ( $state['executed_at'] ?? '' ) ); ?></span> طی <?php echo esc_html( number_format_i18n( (int) ( $state['elapsed_ms'] ?? 0 ) ) ); ?> میلی‌ثانیه تکمیل شده است.
					</p>
					<table class="widefat striped" style="margin:16px 0;">
						<thead>
							<tr>
								<th>دسته‌بندی هدف</th>
								<th>پیش از پاکسازی</th>
								<th>حذف‌شده (تکراری / نامربوط)</th>
								<th>اصلاح و استانداردشده</th>
								<th>بازگردانی‌شده (خط‌تیره‌دار)</th>
								<th>موجودی نهایی</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php echo esc_html( $s['label'] ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( (int) $s['before'] ) ); ?></td>
								<td style="color:#b91c1c;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $s['deleted'] ) ); ?>-</td>
								<td style="color:#0369a1;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $s['updated'] ) ); ?></td>
								<td style="color:#047857;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $s['inserted'] ) ); ?>+</td>
								<td><strong><?php echo esc_html( number_format_i18n( (int) $s['after'] ) ); ?></strong></td>
							</tr>
							<tr>
								<td><strong><?php echo esc_html( $d['label'] ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( (int) $d['before'] ) ); ?></td>
								<td style="color:#b91c1c;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $d['deleted'] ) ); ?>-</td>
								<td style="color:#0369a1;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $d['updated'] ) ); ?></td>
								<td style="color:#047857;font-weight:700;"><?php echo esc_html( number_format_i18n( (int) $d['inserted'] ) ); ?>+</td>
								<td><strong><?php echo esc_html( number_format_i18n( (int) $d['after'] ) ); ?></strong></td>
							</tr>
							<tr>
								<td><strong>سایر دسته‌ها (اصطلاحات عمومی جراحی + اصطلاحات بیهوشی)</strong></td>
								<td><?php echo esc_html( number_format_i18n( (int) ( $state['untouched_before'] ?? 0 ) ) ); ?></td>
								<td>۰ (کاملاً دست‌نخورده)</td>
								<td>۰ (کاملاً دست‌نخورده)</td>
								<td>۰</td>
								<td><strong><?php echo esc_html( number_format_i18n( (int) ( $state['untouched_after'] ?? 0 ) ) ); ?></strong></td>
							</tr>
						</tbody>
					</table>
					<?php if ( ! empty( $state['backup_file'] ) && file_exists( (string) $state['backup_file'] ) ) : ?>
						<p style="font-size:12.5px;color:#475569;">
							📦 <strong>فایل پشتیبان خودکار پیش از پاکسازی:</strong>
							<code dir="ltr"><?php echo esc_html( basename( (string) $state['backup_file'] ) ); ?></code>
							(ذخیره‌شده در پوشه محافظت‌شده <code dir="ltr">wp-content/uploads/hj-cache/</code>)
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p>هنوز گزارشی ثبت نشده است. با کلیک روی دکمه زیر می‌توانید پاکسازی را هم‌اکنون اجرا کنید.</p>
				<?php endif; ?>

				<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="hj_glossary_cleanup_rerun" />
						<?php wp_nonce_field( 'hj_glossary_cleanup_rerun_nonce' ); ?>
						<button type="submit" class="button button-primary button-large">اجرای مجدد پاکسازی و بازسازی کش هایلایت سبز</button>
					</form>

					<?php if ( ! empty( $state['backup_file'] ) && file_exists( (string) $state['backup_file'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('آیا از بازگردانی کامل داده‌های دو دسته به وضعیت اولیه پیش از پاکسازی مطمئن هستید؟');">
							<input type="hidden" name="action" value="hj_glossary_cleanup_rollback" />
							<?php wp_nonce_field( 'hj_glossary_cleanup_rollback_nonce' ); ?>
							<button type="submit" class="button button-secondary button-large">بازگردانی نسخه پشتیبان پیش از پاکسازی</button>
						</form>
					<?php endif; ?>

					<a class="button button-large" target="_blank" href="<?php echo esc_url( home_url( '/glossary-group/sterile-terms/' ) ); ?>">مشاهده دسته استریلیزاسیون ↗</a>
					<a class="button button-large" target="_blank" href="<?php echo esc_url( home_url( '/glossary-group/device-terms/' ) ); ?>">مشاهده دسته ابزار جراحی ↗</a>
				</div>

				<p style="margin-top:16px;padding:10px 14px;background:#f8fafc;border-radius:8px;color:#334155;font-size:12.5px;">
					💡 <strong>نکته:</strong> تمام تغییرات به‌طور دائمی در جدول پایگاه‌داده (<code><?php echo esc_html( $GLOBALS['wpdb']->prefix . 'hj_glossary_terms' ); ?></code>) ذخیره شده‌اند؛ بنابراین پس از مشاهده و اطمینان از نتیجه، در صورت تمایل حتی می‌توانید این افزونه کمکی را غیرفعال یا حذف کنید و داده‌های تمیز شما برای همیشه باقی می‌مانند.
				</p>
			</div>
		</div>
		<?php
	}
}

HJ_Glossary_Cleanup::boot();

<?php
/**
 * Glossary data repair: the "1." that ended up inside the term itself.
 *
 * The bulk import numbered every row, and the number was written into the
 * `term` column instead of being used as a list position. So the database
 * holds «1. بیهوشی», «10. بیهوشی وریدی», «100. سوگامادکس» — and because the
 * hub sorts by `term`, 100 came before 11 and every group restarted at 1.
 *
 * This file strips the prefix once, in place, and refuses to write it back
 * on future imports.
 *
 * @package Lax_Theme_Child
 * @since   0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove a leading "12. " / "12) " / "12 - " numbering prefix.
 *
 * Handles ASCII, Persian and Arabic-Indic digits.
 *
 * @param string $term Raw term name.
 * @return string
 */
function hj_glossary_strip_number_prefix( $term ) {
	$term = (string) $term;

	$cleaned = preg_replace(
		'/^[\s\x{200E}\x{200F}]*[0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]{1,4}\s*(?:[\.\):]|[–—-](?=\s))\s*/u',
		'',
		$term
	);

	if ( ! is_string( $cleaned ) || '' === trim( $cleaned ) ) {
		return trim( $term );
	}

	return trim( $cleaned );
}

/**
 * Does this term start with a list number?
 *
 * @param string $term Term name.
 * @return bool
 */
function hj_glossary_has_number_prefix( $term ) {
	return hj_glossary_strip_number_prefix( $term ) !== trim( (string) $term );
}

/**
 * Sort rows by definition length, longest first.
 *
 * @param array $a Row.
 * @param array $b Row.
 * @return int
 */
function hj_glossary_repair_cmp( $a, $b ) {
	$len_a = strlen( (string) $a['definition'] );
	$len_b = strlen( (string) $b['definition'] );

	if ( $len_a === $len_b ) {
		return 0;
	}

	return ( $len_a > $len_b ) ? -1 : 1;
}

/**
 * Run the repair once, then remember that it happened.
 *
 * @return void
 */
function hj_glossary_number_repair() {
	if ( ! is_admin() || wp_installing() ) {
		return;
	}
	if ( get_option( 'hj_glossary_number_repair_v1' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = hj_glossary_number_repair_run();
	update_option( 'hj_glossary_number_repair_v1', $result, false );

	if ( ! empty( $result['fixed'] ) ) {
		set_transient( 'hj_glossary_number_repair_notice', $result, 120 );
	}
}
add_action( 'admin_init', 'hj_glossary_number_repair', 20 );

/**
 * The repair itself.
 *
 * @return array{scanned:int,fixed:int,duplicates:int,error:string}
 */
function hj_glossary_number_repair_run() {
	$out = array(
		'scanned'    => 0,
		'fixed'      => 0,
		'duplicates' => 0,
		'error'      => '',
	);

	if ( ! function_exists( 'hj_glossary_table_name' ) ) {
		$out['error'] = 'glossary engine missing';
		return $out;
	}

	hj_glossary_ensure_table();

	global $wpdb;
	$table = hj_glossary_table_name();

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT id, term, term_norm, slug, definition, group_id, status FROM `{$table}`",
		ARRAY_A
	);

	if ( ! is_array( $rows ) ) {
		$out['error'] = (string) $wpdb->last_error;
		return $out;
	}

	$out['scanned'] = count( $rows );

	// Slugs that already belong to a clean, numbered-prefix-free term.
	$taken = array();
	$todo  = array();

	foreach ( $rows as $row ) {
		$term = (string) $row['term'];
		if ( hj_glossary_has_number_prefix( $term ) ) {
			$todo[ (int) $row['id'] ] = $row;
			continue;
		}
		$norm = (string) $row['term_norm'];
		if ( '' !== $norm ) {
			$taken[ $norm ] = (int) $row['id'];
		}
	}

	if ( empty( $todo ) ) {
		return $out;
	}

	// Longest definition wins a name collision: it is the better page.
	uasort( $todo, 'hj_glossary_repair_cmp' );

	foreach ( $todo as $id => $row ) {
		$clean = hj_glossary_strip_number_prefix( (string) $row['term'] );
		if ( '' === $clean ) {
			continue;
		}

		$norm  = hj_glossary_normalize_text( $clean, true );
		$slug  = hj_glossary_make_slug( $clean );
		$data  = array(
			'term'         => $clean,
			'term_norm'    => $norm,
			'slug'         => $slug,
			'first_letter' => hj_glossary_extract_first_letter( $clean ),
		);
		$is_dup = isset( $taken[ $norm ] ) && (int) $taken[ $norm ] !== $id;

		if ( '' === $norm || $is_dup ) {
			// Keep it unique and out of the hub, but do not delete the row:
			// the definition may still be merged by an editor later.
			$data['term_norm'] = $norm . '-dup-' . $id;
			$data['slug']      = $slug . '-dup-' . $id;
			$data['status']    = 'draft';
			++$out['duplicates'];
		} else {
			$taken[ $norm ] = $id;
		}

		$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		++$out['fixed'];
	}

	if ( function_exists( 'hj_glossary_rebuild_index' ) ) {
		hj_glossary_rebuild_index();
	}

	return $out;
}

/**
 * Tell the editor what the repair did, once.
 *
 * @return void
 */
function hj_glossary_number_repair_notice() {
	$result = get_transient( 'hj_glossary_number_repair_notice' );
	if ( ! is_array( $result ) ) {
		return;
	}
	delete_transient( 'hj_glossary_number_repair_notice' );

	echo '<div class="notice notice-success is-dismissible"><p>';
	echo esc_html(
		sprintf(
			/* translators: 1: fixed, 2: scanned, 3: duplicates */
			__( 'واژه‌نامه پاک‌سازی شد: شماره‌ی ابتدای %1$d واژه از %2$d واژه حذف شد. %3$d واژه تکراری کنار گذاشته شد و دیگر در صفحه‌ی واژه‌نامه دیده نمی‌شود.', 'lax-theme-child' ),
			(int) $result['fixed'],
			(int) $result['scanned'],
			(int) $result['duplicates']
		)
	);
	echo '</p></div>';
}
add_action( 'admin_notices', 'hj_glossary_number_repair_notice' );

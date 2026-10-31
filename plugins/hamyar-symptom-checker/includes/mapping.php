<?php
/**
 * Mapping between the knowledge bundle conditions and published articles.
 *
 * Rules (deliberate, see docs/symptom-checker/decision-log.fa.md):
 * - only posts of the `disease` type with status `publish` can be linked;
 * - the match must be exact (post slug) and unique — zero or several matches
 *   never produce a link and are reported through an action for debugging;
 * - a missing or ambiguous mapping is never guessed from a similar title.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and cache the knowledge bundle shipped with the plugin.
 *
 * @return array<string,mixed>
 */
function hj_sc_bundle() {
	static $bundle = null;
	if ( null !== $bundle ) {
		return $bundle;
	}

	$bundle = array();
	$path   = HJ_SC_DIR . 'assets/data/knowledge-bundle.v1.json';
	if ( ! is_readable( $path ) ) {
		return $bundle;
	}

	if ( function_exists( 'wp_json_file_decode' ) ) {
		$decoded = wp_json_file_decode( $path, array( 'associative' => true ) );
	} else {
		$raw     = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
	}

	if ( is_array( $decoded ) ) {
		$bundle = $decoded;
	}

	return $bundle;
}

/**
 * Bundle version, used for cache busting and for the preview notice.
 */
function hj_sc_bundle_version() {
	$bundle = hj_sc_bundle();
	return isset( $bundle['bundle_version'] ) ? (string) $bundle['bundle_version'] : '';
}

/**
 * Conditions declared by the bundle, keyed by condition id.
 *
 * @return array<string,array<string,mixed>>
 */
function hj_sc_bundle_conditions() {
	$conditions = array();
	$bundle     = hj_sc_bundle();
	if ( empty( $bundle['conditions'] ) || ! is_array( $bundle['conditions'] ) ) {
		return $conditions;
	}
	foreach ( $bundle['conditions'] as $condition ) {
		if ( is_array( $condition ) && ! empty( $condition['id'] ) ) {
			$conditions[ (string) $condition['id'] ] = $condition;
		}
	}
	return $conditions;
}

/**
 * Condition id → article slug. The bundle slug is the default; the team can pin
 * or override any slug explicitly with the `hj_sc_topic_slugs` filter.
 *
 * @return array<string,string>
 */
function hj_sc_topic_slugs() {
	$slugs = array();
	foreach ( hj_sc_bundle_conditions() as $condition_id => $condition ) {
		if ( ! empty( $condition['article_slug'] ) ) {
			$slugs[ $condition_id ] = (string) $condition['article_slug'];
		}
	}
	/** @var array<string,string> $slugs */
	return (array) apply_filters( 'hj_sc_topic_slugs', $slugs );
}

/**
 * Resolve condition id → permalink of the one published article that matches.
 *
 * @return array<string,string>
 */
function hj_sc_topic_links() {
	static $links = null;
	if ( null !== $links ) {
		return $links;
	}

	$links = array();
	if ( ! post_type_exists( 'disease' ) ) {
		return $links;
	}

	foreach ( hj_sc_topic_slugs() as $condition_id => $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			continue;
		}

		$args = array(
			'post_type'              => 'disease',
			'post_status'            => 'publish',
			'name'                   => $slug,
			'posts_per_page'         => 2,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);

		/** @var array<string,mixed> $args */
		$args  = (array) apply_filters( 'hj_sc_topic_query_args', $args, $condition_id, $slug );
		$posts = get_posts( $args );
		$count = is_array( $posts ) ? count( $posts ) : 0;

		// Zero or several matches: never guess. The UI then shows no link at all.
		if ( 1 !== $count ) {
			do_action( 'hj_sc_topic_link_unresolved', $condition_id, $slug, $count );
			continue;
		}

		$url = get_permalink( (int) $posts[0] );
		if ( is_string( $url ) && '' !== $url ) {
			$links[ $condition_id ] = $url;
		}
	}

	/** @var array<string,string> $links */
	return (array) apply_filters( 'hj_sc_topic_links', $links );
}

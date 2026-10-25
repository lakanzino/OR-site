<?php
/**
 * Index policy: keep empty and thin pages out of Google.
 *
 * The rule is deliberately mechanical and self-healing: a page is indexable
 * only while it has enough real content behind it. The moment the third
 * article of a section is published, its archive returns to the index with
 * no action from anybody.
 *
 * Applies to:
 *   - post type archives with fewer than N published items,
 *   - taxonomy terms with fewer than N items,
 *   - author archives with fewer than N posts,
 *   - glossary term pages whose definition is still a one-liner.
 *
 * Never applies to:
 *   - the glossary hub itself (it is a navigation page, not an article list),
 *   - singular posts and static pages,
 *   - the front page.
 *
 * @package Lax_Theme_Child
 * @since   0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimum number of items a listing view needs before it is indexable.
 *
 * @return int
 */
function hj_index_min_items(): int {
	return max( 1, (int) apply_filters( 'hj_index_min_items', 3 ) );
}

/**
 * Post types that get the thin-archive check.
 *
 * @return string[]
 */
function hj_index_checked_post_types(): array {
	$types = array();

	foreach ( get_post_types( array( 'publicly_queryable' => true ), 'names' ) as $name ) {
		$object = get_post_type_object( $name );
		if ( ! $object || empty( $object->has_archive ) ) {
			continue;
		}
		// The glossary archive is a directory, not a stream of articles.
		if ( 'glossary' === $name ) {
			continue;
		}
		$types[] = $name;
	}

	return $types;
}

/**
 * Why the current view should stay out of search results.
 *
 * Returns an empty array when the view may be indexed.
 *
 * @return string[] Machine-readable reasons.
 */
function hj_index_blocking_reasons(): array {
	$reasons = array();
	$min     = hj_index_min_items();

	if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
		return $reasons;
	}

	if ( is_search() ) {
		$reasons[] = 'search';
	}
	if ( is_404() ) {
		$reasons[] = 'not-found';
	}

	// A single post or a static page is judged by the editor, not by a counter.
	if ( is_singular() ) {
		return $reasons;
	}

	if ( is_post_type_archive() ) {
		$object = get_queried_object();
		$name   = ( $object && isset( $object->name ) ) ? (string) $object->name : '';
		if ( $name && in_array( $name, hj_index_checked_post_types(), true ) ) {
			$counts = wp_count_posts( $name );
			$live   = isset( $counts->publish ) ? (int) $counts->publish : 0;
			if ( $live < $min ) {
				$reasons[] = 'thin-archive';
			}
		}
		return $reasons;
	}

	if ( is_tax() || is_category() || is_tag() ) {
		$term  = get_queried_object();
		$count = ( $term && isset( $term->count ) ) ? (int) $term->count : 0;
		if ( $count < $min ) {
			$reasons[] = 'thin-term';
		}
		return $reasons;
	}

	if ( is_author() ) {
		$author = get_queried_object();
		if ( $author && isset( $author->ID ) ) {
			$types = array_values( array_diff( hj_index_checked_post_types(), array( 'page' ) ) );
			$count = 0;
			foreach ( $types as $type ) {
				$count += (int) count_user_posts( (int) $author->ID, $type, true );
			}
			if ( $count < $min ) {
				$reasons[] = 'thin-author';
			}
		}
		return $reasons;
	}

	if ( is_date() && ! is_year() ) {
		// Daily and monthly archives almost never hold enough posts on a young site.
		global $wp_query;
		if ( (int) $wp_query->found_posts < $min ) {
			$reasons[] = 'thin-date';
		}
	}

	return $reasons;
}

/**
 * Is the current glossary term page strong enough to be indexed?
 *
 * A term earns its own indexed page when the definition is a real paragraph
 * and at least one foreign-language name or alias exists. Everything else
 * stays reachable and useful for readers, but out of the index.
 *
 * @return bool
 */
function hj_glossary_term_is_indexable(): bool {
	$data = isset( $GLOBALS['hj_single_term_data'] ) ? $GLOBALS['hj_single_term_data'] : null;
	if ( ! is_array( $data ) ) {
		return true;
	}

	$definition = trim( (string) ( $data['definition'] ?? '' ) );
	$length     = function_exists( 'mb_strlen' ) ? mb_strlen( $definition, 'UTF-8' ) : strlen( $definition );
	$min_length = (int) apply_filters( 'hj_glossary_index_min_length', 200 );

	if ( $length < $min_length ) {
		return false;
	}

	$has_english = '' !== trim( (string) ( $data['en_term'] ?? '' ) );
	$has_alias   = '' !== trim( (string) ( $data['aliases'] ?? '' ) );

	return $has_english || $has_alias;
}

/**
 * Apply the policy to the robots directives WordPress prints.
 *
 * @param array<string,bool|string> $robots Robots directives.
 * @return array<string,bool|string>
 */
function hj_index_robots( $robots ) {
	$reasons = hj_index_blocking_reasons();

	if ( ! hj_glossary_term_is_indexable() ) {
		$reasons[] = 'thin-glossary-term';
	}

	if ( empty( $reasons ) ) {
		return $robots;
	}

	// noindex keeps the page out of results; follow still passes link value.
	$robots['noindex'] = true;
	$robots['follow']  = true;
	unset( $robots['index'] );

	return $robots;
}
add_filter( 'wp_robots', 'hj_index_robots', 20 );

/**
 * Same policy for the most common SEO plugins, so installing one later does
 * not silently re-open the empty pages.
 *
 * @param array<string,mixed>|string $robots Robots value.
 * @return array<string,mixed>|string
 */
function hj_index_robots_plugin_filter( $robots ) {
	if ( hj_index_blocking_reasons() ) {
		return array(
			'index'  => 'noindex',
			'follow' => 'follow',
		);
	}
	return $robots;
}
add_filter( 'wpseo_robots_array', 'hj_index_robots_plugin_filter', 20 );
add_filter( 'rank_math/frontend/robots', 'hj_index_robots_plugin_filter', 20 );

/**
 * Seeded trust pages are never thin; make sure they are never caught by
 * accident through the taxonomy rules above.
 *
 * @param array<string,bool|string> $robots Robots directives.
 * @return array<string,bool|string>
 */
function hj_index_protect_pages( $robots ) {
	if ( is_page() && function_exists( 'hj_is_seeded_page' ) && hj_is_seeded_page( get_queried_object_id() ) ) {
		unset( $robots['noindex'] );
		$robots['index']  = true;
		$robots['follow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'hj_index_protect_pages', 30 );

/**
 * Canonical URL for glossary term pages.
 *
 * They are resolved after a 404, so WordPress cannot build one on its own.
 *
 * @return void
 */
function hj_index_term_canonical() {
	$data = isset( $GLOBALS['hj_single_term_data'] ) ? $GLOBALS['hj_single_term_data'] : null;
	if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
		return;
	}
	if ( ! hj_glossary_term_is_indexable() ) {
		return;
	}

	$base = function_exists( 'hj_archive_url' ) ? hj_archive_url( 'glossary' ) : home_url( '/glossary/' );
	$url  = trailingslashit( $base ) . rawurlencode( (string) $data['slug'] ) . '/';

	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_trim_words( (string) ( $data['definition'] ?? '' ), 30, '…' ) ) );
}
add_action( 'wp_head', 'hj_index_term_canonical', 4 );

/**
 * Keep noindexed glossary term routes out of the core sitemap listing.
 *
 * Term pages are routed on the fly and never appear in `wp-sitemap.xml`,
 * so this is only a guard for the (currently unused) `glossary` CPT.
 *
 * @param array<string,\WP_Post_Type> $post_types Sitemap post types.
 * @return array<string,\WP_Post_Type>
 */
function hj_index_sitemap_types( $post_types ) {
	if ( is_array( $post_types ) ) {
		unset( $post_types['glossary'] );
	}
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'hj_index_sitemap_types' );

/**
 * Human-readable summary for the dashboard: which sections are still hidden.
 *
 * @return array<int,array{name:string,url:string,count:int,min:int}>
 */
function hj_index_hidden_sections(): array {
	$out = array();
	$min = hj_index_min_items();

	foreach ( hj_index_checked_post_types() as $type ) {
		$object = get_post_type_object( $type );
		if ( ! $object ) {
			continue;
		}
		$counts = wp_count_posts( $type );
		$live   = isset( $counts->publish ) ? (int) $counts->publish : 0;
		if ( $live >= $min ) {
			continue;
		}
		$out[] = array(
			'name'  => (string) $object->labels->name,
			'url'   => (string) get_post_type_archive_link( $type ),
			'count' => $live,
			'min'   => $min,
		);
	}

	return $out;
}

/**
 * Dashboard notice: which sections are currently kept out of the index.
 *
 * @return void
 */
function hj_index_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}

	$hidden = hj_index_hidden_sections();
	if ( empty( $hidden ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>';
	esc_html_e( 'این بخش‌ها فعلاً از گوگل پنهان‌اند:', 'lax-theme-child' );
	echo '</strong> ';
	$names = array();
	foreach ( $hidden as $section ) {
		$names[] = sprintf(
			/* translators: 1: section name, 2: articles, 3: minimum */
			__( '%1$s (%2$d مقاله از حداقل %3$d)', 'lax-theme-child' ),
			$section['name'],
			(int) $section['count'],
			(int) $section['min']
		);
	}
	echo esc_html( implode( '، ', $names ) );
	echo '. ';
	esc_html_e( 'به‌محض رسیدن هر بخش به حد نصاب، خودکار به ایندکس برمی‌گردد و کاری لازم نیست.', 'lax-theme-child' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'hj_index_admin_notice' );

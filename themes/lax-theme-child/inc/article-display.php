<?php
/**
 * Article display helpers for the child theme.
 *
 * Builds the table of contents from H2/H3 headings, estimates reading
 * time and resolves the editorial taxonomy used for badges. No reviewer
 * or "trust bar" is rendered anywhere, per the design decision.
 *
 * @package Lax_Theme_Child
 * @since   0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy map per post type (badges and related articles).
 *
 * @return array<string,string>
 */
function orsite_article_taxonomy_map() {
	return array(
		'patient_guide'  => 'guide_stage',
		'or_environment' => 'env_section',
		'surgery'        => 'surgery_specialty',
		'student_content' => 'student_topic',
		'glossary'       => 'glossary_group',
		'post'           => 'category',
	);
}

/**
 * Add stable anchor ids to H2/H3 headings and collect the TOC.
 *
 * Runs on `the_content` before wpautop so raw headings are still plain.
 *
 * @param string $content Post content.
 * @return string
 */
function orsite_add_heading_anchors( $content ) {
	if ( ! is_string( $content ) || false === stripos( $content, '<h' ) ) {
		return $content;
	}

	$GLOBALS['orsite_toc_items'] = array();
	$index                       = 0;

	$content = preg_replace_callback(
		'/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/is',
		function ( $matches ) use ( &$index ) {
			$level = (int) $matches[1];
			$attrs = isset( $matches[2] ) ? $matches[2] : '';
			$text  = trim( wp_strip_all_tags( $matches[3] ) );

			if ( '' === $text ) {
				return $matches[0];
			}

			$index++;
			$id = 'section-' . $index;

			if ( preg_match( '/\bid\s*=\s*"([^"]+)"/i', $attrs, $id_match ) ) {
				$id = $id_match[1];
			} else {
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}

			$GLOBALS['orsite_toc_items'][] = array(
				'id'    => $id,
				'text'  => $text,
				'level' => $level,
			);

			return '<h' . $level . $attrs . '>' . $matches[3] . '</h' . $level . '>';
		},
		$content
	);

	return is_string( $content ) ? $content : '';
}
add_filter( 'the_content', 'orsite_add_heading_anchors', 8 );

/**
 * Render the_content() into a variable so the TOC (built while the
 * content filter runs) can be printed before the content itself.
 *
 * @return string
 */
function orsite_render_content_buffered() {
	ob_start();
	the_content();
	return (string) ob_get_clean();
}

/**
 * Collected TOC items for the current article.
 *
 * @return array<int,array<string,mixed>>
 */
function orsite_get_toc_items() {
	return isset( $GLOBALS['orsite_toc_items'] ) && is_array( $GLOBALS['orsite_toc_items'] )
		? $GLOBALS['orsite_toc_items']
		: array();
}

/**
 * Estimated reading time in minutes (200 words per minute, minimum 1).
 *
 * @param string $html Rendered content HTML.
 * @return int
 */
function orsite_reading_time( $html ) {
	$text  = wp_strip_all_tags( $html );
	$count = preg_match_all( '/[\p{L}\p{N}]+/u', $text, $unused );
	return max( 1, (int) ceil( ( $count ? $count : 0 ) / 200 ) );
}

/**
 * Primary editorial term for a post (first term of its mapped taxonomy).
 *
 * @param int    $post_id   Post ID.
 * @param string $post_type Post type.
 * @return WP_Term|null
 */
function orsite_primary_term( $post_id, $post_type ) {
	$map = orsite_article_taxonomy_map();
	if ( empty( $map[ $post_type ] ) ) {
		return null;
	}

	$terms = get_the_terms( $post_id, $map[ $post_type ] );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}

	return $terms[0];
}

/**
 * Print taxonomy badges (stage/specialty) for the current post.
 */
function orsite_article_badges() {
	$post_type = get_post_type();
	$map       = orsite_article_taxonomy_map();
	$taxes     = array();

	if ( 'surgery' === $post_type ) {
		$taxes = array( 'surgery_specialty', 'surgery_stage' );
	} elseif ( ! empty( $map[ $post_type ] ) ) {
		$taxes = array( $map[ $post_type ] );
	}

	$printed = false;
	foreach ( $taxes as $taxonomy ) {
		$terms = get_the_terms( get_the_ID(), $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			if ( ! $printed ) {
				echo '<div class="orsite-badges">';
				$printed = true;
			}
			printf( '<a class="orsite-badge" href="%s">%s</a>', esc_url( $link ), esc_html( $term->name ) );
		}
	}
	if ( $printed ) {
		echo '</div>';
	}
}

/**
 * Print a simple breadcrumb: Home / Archive / Primary term / Title.
 */
function orsite_article_breadcrumb() {
	echo '<nav class="orsite-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'lax-theme-child' ) . '">';
	printf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html__( 'Home', 'lax-theme-child' ) );

	$post_type = get_post_type();
	$archive   = get_post_type_archive_link( $post_type );
	$object    = get_post_type_object( $post_type );
	if ( $archive && $object ) {
		printf( '<span aria-hidden="true">/</span><a href="%s">%s</a>', esc_url( $archive ), esc_html( $object->labels->name ) );
	}

	$term = orsite_primary_term( get_the_ID(), $post_type );
	if ( $term ) {
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			printf( '<span aria-hidden="true">/</span><a href="%s">%s</a>', esc_url( $link ), esc_html( $term->name ) );
		}
	}

	printf( '<span aria-hidden="true">/</span><span class="orsite-breadcrumb__current" aria-current="page">%s</span>', esc_html( get_the_title() ) );
	echo '</nav>';
}

/**
 * Related published articles: same post type, sharing the primary term.
 *
 * @param int $post_id Current post ID.
 * @return WP_Post[]
 */
function orsite_related_articles( $post_id ) {
	$post_type = get_post_type( $post_id );
	$map       = orsite_article_taxonomy_map();
	$args      = array(
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
	);

	$term = isset( $map[ $post_type ] ) ? orsite_primary_term( $post_id, $post_type ) : null;
	if ( $term ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => $term->taxonomy,
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			),
		);
	}

	return get_posts( $args );
}

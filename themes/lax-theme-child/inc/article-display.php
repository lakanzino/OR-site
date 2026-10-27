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
 * Clean Persian archive titles without the generic "Archives:" prefix.
 * Keeps the visible H1 and the document title aligned with the section
 * name a patient actually chose.
 *
 * @param string $title Default archive title.
 * @return string
 */
function orsite_archive_title( $title ) {
	if ( is_post_type_archive() ) {
		$object = get_queried_object();
		if ( $object && isset( $object->labels->name ) ) {
			return $object->labels->name;
		}
		$post_type = get_query_var( 'post_type' );
		if ( is_string( $post_type ) && $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( $object ) {
				return $object->labels->name;
			}
		}
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->name ) ) {
			return $term->name;
		}
	}
	if ( is_author() ) {
		$author = get_queried_object();
		if ( $author && isset( $author->display_name ) ) {
			return $author->display_name;
		}
	}
	return $title;
}
add_filter( 'get_the_archive_title', 'orsite_archive_title' );

if ( ! defined( 'ORSITE_CANONICAL_DISCLAIMER_HTML' ) ) {
	/*
	 * One disclaimer per article, and only this one (child 0.21.0): a single
	 * compact warning box instead of an <h2> section. The live site had two
	 * (one in the body, one appended) and they pushed normal content around.
	 */
	define(
		'ORSITE_CANONICAL_DISCLAIMER_HTML',
		'<aside class="hj-box hj-box--warning hj-disclaimer-box" role="note">'
		. '<p><strong>یادآوری:</strong> این مقاله آموزش عمومی است و جایگزین معاینه و تصمیم پزشک نیست؛ تشخیص و درمان فقط با تیم درمانی است که شما را معاینه کرده است.</p>'
		. '</aside>'
	);
}

/**
 * Normalize or strip medical-disclaimer sections in post/page HTML, unwrap
 * stale links to the retired `/medical-disclaimer/` page, and clean forced
 * `text-align: left` inline styles on table cells.
 *
 * When `$append_canonical` is true (all article post types), every article
 * ends with the exact single-sentence `ORSITE_CANONICAL_DISCLAIMER_HTML`
 * section and nothing after it.
 *
 * @param string $content          Post content HTML.
 * @param bool   $append_canonical Whether to append the canonical 2-line disclaimer.
 * @return string
 */
function orsite_clean_content_disclaimers_and_tables( $content, $append_canonical = false ) {
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return $content;
	}

	// 1. Strip any existing <h2>/<h3> "سلب مسئولیت..." section (supports articles with any number of headings).
	if ( false !== strpos( $content, 'سلب' ) ) {
		$offset = 0;
		$guard  = 0;
		while ( $guard < 500 && preg_match( '/<h([23])\b[^>]*>(.*?)<\/h\1>/isu', $content, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			$guard++;
			$h_start = (int) $m[0][1];
			$h_end   = $h_start + strlen( $m[0][0] );
			$h_text  = wp_strip_all_tags( $m[2][0] );

			if ( preg_match( '/سلب[\s\x{200C}]*مس[ئؤیي]ولی[تی]/u', $h_text ) ) {
				if ( preg_match( '/<h[1-6]\b/i', $content, $next, PREG_OFFSET_CAPTURE, $h_end ) ) {
					$cut_end = (int) $next[0][1];
				} else {
					$cut_end = strlen( $content );
				}
				$before  = rtrim( substr( $content, 0, $h_start ) );
				$after   = ltrim( substr( $content, $cut_end ) );
				$content = ( '' !== $after ) ? ( $before . "\n" . $after ) : $before;
				$offset  = strlen( $before );
			} else {
				$offset = $h_end;
			}
		}
	}

	// 2. Strip any trailing editorial sign-off `<hr /><p><em>ویرایش و انتشار...</em></p>` at the end of articles.
	if ( false !== strpos( $content, 'ویرایش و انتشار' ) ) {
		$cleaned = preg_replace(
			'/\s*<hr\b[^>]*>\s*<p[^>]*>\s*<em>[^<]*ویرایش\s+و\s+انتشار[^<]*<\/em>\s*<\/p>\s*$/isu',
			'',
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}
	}

	// 2b. Strip paragraph-form disclaimers that were pasted into the body.
	if ( false !== strpos( $content, 'جایگزین' ) ) {
		$cleaned = preg_replace(
			'/\s*<p\b[^>]*>(?:(?!<\/p>).)*?جایگزین(?:\s|&nbsp;)*(?:تشخیص|مشاوره|درمان|معاینه)(?:(?!<\/p>).)*?<\/p>\s*/isu',
			"\n",
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}
	}

	// 2c. Strip an already-rendered box from an earlier render pass.
	if ( false !== strpos( $content, 'hj-disclaimer-box' ) ) {
		$cleaned = preg_replace(
			'/\s*<aside\b[^>]*\bhj-disclaimer-box\b[^>]*>.*?<\/aside>\s*/isu',
			"\n",
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}
	}

	// 3. Strip any legacy `.orsite-disclaimer` div block if embedded in HTML.
	if ( false !== stripos( $content, 'orsite-disclaimer' ) ) {
		$cleaned = preg_replace(
			'/\s*<div\b[^>]*\borsite-disclaimer\b[^>]*>.*?<\/div>\s*/isu',
			"\n",
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}
	}

	// 4. Unwrap any stale link to `/medical-disclaimer/` so no 404 link remains.
	if ( false !== stripos( $content, 'medical-disclaimer' ) ) {
		$cleaned = preg_replace(
			'/<a\b[^>]*\bhref\s*=\s*(["\'])[^"\']*medical-disclaimer[^"\']*\1[^>]*>(.*?)<\/a>/isu',
			'$2',
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}
	}

	// 5. Normalize forced left-align inline styles on table cells generated by Markdown exporters.
	if ( false !== stripos( $content, 'text-align' ) ) {
		$cleaned = preg_replace(
			'/(<(?:th|td)\b[^>]*?)\s+style\s*=\s*(["\'])\s*text-align\s*:\s*left\s*;?\s*\2/i',
			'$1',
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = $cleaned;
		}
	}

	// 6. Strip redundant in-body duplicate byline block (`.hj-page-byline` or leading dual author/date paragraph).
	if ( false !== stripos( $content, 'hj-page-byline' ) ) {
		$cleaned = preg_replace(
			'/\s*<p\b[^>]*\bhj-page-byline\b[^>]*>.*?<\/p>\s*/isu',
			"\n",
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = ltrim( $cleaned );
		}
	}
	if ( false !== strpos( $content, 'تاریخ نگارش' ) && false !== strpos( $content, 'نویسنده' ) ) {
		$cleaned = preg_replace(
			'/^\s*<p\b[^>]*>\s*<strong>\s*نویسنده\s*:?\s*<\/strong>.*?تاریخ\s+نگارش.*?<\/p>\s*/isu',
			'',
			$content
		);
		if ( is_string( $cleaned ) ) {
			$content = ltrim( $cleaned );
		}
	}

	// 7. Convert any stray inline Gregorian dates in Persian text to Solar Hijri (Jalali).
	if ( function_exists( 'hj_convert_inline_gregorian_dates' ) ) {
		$content = hj_convert_inline_gregorian_dates( $content );
	}

	if ( $append_canonical ) {
		$content = orsite_insert_disclaimer_box( $content );
	}

	return $content;
}

/**
 * Place the single disclaimer box before a trailing sources list.
 *
 * The patient asked for the references to be the last block of an article, so
 * the disclaimer goes right before the «منابع/مراجع/شواهد» heading when the
 * body ends with one; otherwise it is simply appended.
 *
 * @param string $content Cleaned article HTML.
 * @return string
 */
function orsite_insert_disclaimer_box( $content ) {
	$content = rtrim( (string) $content );
	$box     = ORSITE_CANONICAL_DISCLAIMER_HTML;

	$offset = 0;
	$found  = -1;
	while ( preg_match( '/<h2\\b[^>]*>(.*?)<\\/h2>/isu', $content, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
		$text = wp_strip_all_tags( $m[1][0] );
		if ( preg_match( '/منابع|مراجع|شواهد|کتاب‌شناسی/u', $text ) ) {
			$found = (int) $m[0][1];
		}
		$offset = (int) $m[0][1] + strlen( $m[0][0] );
	}

	if ( $found > 0 ) {
		return rtrim( substr( $content, 0, $found ) ) . "\n" . $box . "\n" . substr( $content, $found );
	}

	return $content . "\n" . $box . "\n";
}

/**
 * Strip disclaimer sections/links without appending (for static pages).
 *
 * @param string $content HTML content.
 * @return string
 */
function orsite_strip_disclaimer_section( $content ) {
	return orsite_clean_content_disclaimers_and_tables( $content, false );
}

/**
 * Filter `the_content` at priority 6 (before `orsite_add_heading_anchors` at 8):
 * articles always end with the canonical single-sentence medical disclaimer;
 * static pages remain free of disclaimer sections and broken links.
 *
 * @param string $content Post content HTML.
 * @return string
 */
function orsite_filter_the_content_disclaimer( $content ) {
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return $content;
	}
	if ( function_exists( 'doing_filter' ) && doing_filter( 'get_the_excerpt' ) ) {
		return orsite_clean_content_disclaimers_and_tables( $content, false );
	}
	$post_type    = get_post_type();
	$article_cpts = array( 'patient_guide', 'or_environment', 'surgery', 'student_content', 'glossary', 'post' );
	$is_article   = $post_type && in_array( $post_type, $article_cpts, true );

	return orsite_clean_content_disclaimers_and_tables( $content, $is_article );
}
add_filter( 'the_content', 'orsite_filter_the_content_disclaimer', 6 );

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
	echo '<nav class="orsite-breadcrumb" aria-label="' . esc_attr__( 'مسیر صفحه', 'lax-theme-child' ) . '">';
	printf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html__( 'خانه', 'lax-theme-child' ) );

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

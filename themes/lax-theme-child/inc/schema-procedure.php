<?php
/**
 * SurgicalProcedure structured data for `surgery` articles (child 0.16.0).
 *
 * The base Article/MedicalWebPage node lives in `inc/seo-basics.php`. This
 * file adds one extra node for surgery articles, and fills it **only** with
 * text that already exists on the same page: the answer under a heading such
 * as «آمادگی»، «بعد از عمل» or «مراحل». Nothing is generated, nothing is
 * paraphrased, and if a heading is missing the property is simply not
 * emitted — an incomplete but truthful node beats a complete invention.
 *
 * Google does not require this markup, so it is a bonus, not a gate. It steps
 * aside when a dedicated SEO plugin is active (that plugin owns the graph).
 *
 * @package Lax_Theme_Child
 * @since   0.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Split article HTML into heading/answer pairs.
 *
 * @param string $html Post content.
 * @return array<int,array{title:string,text:string}>
 */
function hj_proc_sections( string $html ): array {
	$sections = array();
	if ( '' === trim( $html ) ) {
		return $sections;
	}

	if ( ! preg_match_all( '/<h2\b[^>]*>(.*?)<\/h2>(.*?)(?=<h2\b|$)/isu', $html, $m, PREG_SET_ORDER ) ) {
		return $sections;
	}

	foreach ( $m as $row ) {
		$title = trim( wp_strip_all_tags( (string) $row[1] ) );
		$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $row[2] ) ) );
		if ( '' === $title || '' === $text ) {
			continue;
		}
		$sections[] = array(
			'title' => $title,
			'text'  => $text,
		);
	}

	return $sections;
}

/**
 * First section whose heading matches one of the keywords.
 *
 * @param array<int,array{title:string,text:string}> $sections Sections.
 * @param array<int,string>                          $keywords Heading keywords.
 * @param int                                        $max      Max characters.
 * @return string
 */
function hj_proc_find( array $sections, array $keywords, int $max = 400 ): string {
	foreach ( $sections as $section ) {
		foreach ( $keywords as $keyword ) {
			if ( false !== mb_strpos( $section['title'], $keyword ) ) {
				$text = $section['text'];
				if ( mb_strlen( $text ) > $max ) {
					$text = mb_substr( $text, 0, $max );
					$text = preg_replace( '/\s+\S*$/u', '', $text ) . '…';
				}
				return $text;
			}
		}
	}
	return '';
}

/**
 * Print the SurgicalProcedure node for a surgery article.
 *
 * @return void
 */
function hj_procedure_schema(): void {
	// A dedicated SEO plugin owns the graph; stay silent then.
	if ( function_exists( 'hj_seo_plugin_active' ) && hj_seo_plugin_active() ) {
		return;
	}
	if ( ! is_singular( 'surgery' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	$sections = hj_proc_sections( (string) $post->post_content );
	if ( ! $sections ) {
		return;
	}

	$url  = get_permalink( $post_id );
	$node = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'SurgicalProcedure',
		'@id'             => $url . '#procedure',
		'name'            => get_the_title( $post_id ),
		'url'             => $url,
		'mainEntityOfPage' => $url,
		'inLanguage'      => get_bloginfo( 'language' ),
	);

	$map = array(
		'preparation'  => array( 'آمادگی', 'قبل از عمل', 'پیش از عمل' ),
		'howPerformed' => array( 'مراحل', 'چگونه انجام' ),
		'followup'     => array( 'بعد از عمل', 'مراقبت', 'پیگیری', 'ریکاوری' ),
	);

	$found = 0;
	foreach ( $map as $property => $keywords ) {
		$text = hj_proc_find( $sections, $keywords );
		if ( '' !== $text ) {
			$node[ $property ] = $text;
			++$found;
		}
	}

	if ( $found < 2 ) {
		// One section alone is not worth a node of its own.
		return;
	}

	echo '<script type="application/ld+json">'
		. wp_json_encode( $node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		. "</script>\n";
}
add_action( 'wp_head', 'hj_procedure_schema', 26 );

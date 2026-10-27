<?php
/**
 * Clinical references box for the end of every article (child 0.18.0).
 *
 * The article body already ends with a "منابع و چارچوب مرجع" heading and a
 * plain list of links. A plain list reads like a footnote dump; the request
 * was a block that reads like the bibliography of a real paper.
 *
 * So the markup is rebuilt at render time — not stored in the post:
 *
 *  - numbering is a real ordered list, so it survives copy/paste and print;
 *  - every item shows the publisher, the citation text, a tier badge and the
 *    domain as the link label (a raw URL in a sentence is unreadable);
 *  - the tier comes from `hj_source_host_tier()` — the same whitelist the
 *    publish gate counts — so the badge can never claim more than the gate
 *    verified;
 *  - nothing is hard-coded per article: 44 packages and 64 live articles get
 *    the new design without touching their content;
 *  - saved posts that already carry the box (`hj-refs`) are left alone.
 *
 * Rejected on purpose: hand-written `<section>` blocks with example citations
 * (editors would publish placeholder references), `rel="nofollow"` on
 * authoritative sources (it tells search engines the citation is not
 * endorsed, the opposite of the goal), and a global `prefers-color-scheme`
 * dark palette (the site is light-only, so a dark box would appear on a light
 * page for anyone whose OS is dark).
 *
 * @package Lax_Theme_Child
 * @since   0.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hj_source_summary' ) ) {
	require_once __DIR__ . '/source-whitelist.php';
}

/**
 * Persian digits for counts (the site never shows Latin numerals in prose).
 *
 * @param int $number Count.
 * @return string
 */
function hj_refs_num( int $number ): string {
	return str_replace(
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		(string) max( 0, $number )
	);
}

/**
 * Human label for a whitelist tier.
 *
 * @param string $tier 'tier1' | 'tier2' | ''.
 * @return string
 */
function hj_refs_tier_label( string $tier ): string {
	if ( 'tier1' === $tier ) {
		return 'مرجع بالادست';
	}
	if ( 'tier2' === $tier ) {
		return 'انجمن تخصصی';
	}
	return 'منبع مکمل';
}

/**
 * Turn one list item into a citation line.
 *
 * @param string $heading Original heading text (kept as the section title).
 * @param string $list    Raw `<ul>` / `<ol>` markup of the old section.
 * @return string Empty string when the list cannot be parsed safely.
 */
function hj_refs_build_section( string $heading, string $list ): string {
	if ( ! preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $list, $items ) ) {
		return '';
	}

	$rows = array();
	foreach ( $items[1] as $raw ) {
		if ( ! preg_match( '/<a\b[^>]*href=[\"\']([^\"\']+)[\"\'][^>]*>(.*?)<\/a>/is', $raw, $link ) ) {
			return ''; // A citation without a link is not a citation.
		}
		$url   = html_entity_decode( trim( $link[1] ), ENT_QUOTES, 'UTF-8' );
		$host  = (string) wp_parse_url( $url, PHP_URL_HOST );
		$tier  = hj_source_host_tier( $host );
		$text  = preg_replace( '/<a\b[^>]*>.*?<\/a>/is', '', $raw );
		$text  = wp_strip_all_tags( (string) $text );
		$text  = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text  = trim( preg_replace( '/\s+/u', ' ', $text ) );
		$text  = trim( $text, " \t\n\r\0\x0B—–-·|،," );

		$rows[] = array(
			'url'  => $url,
			'host' => hj_source_normalize_host( $host ),
			'tier' => $tier,
			'text' => '' === $text ? hj_refs_tier_label( $tier ) : $text,
		);
	}

	if ( ! $rows ) {
		return '';
	}

	$summary = hj_source_summary( $list );
	$count   = count( $rows );
	$note    = sprintf(
		'%s منبع در این فهرست · %s دامنهٔ معتبر',
		hj_refs_num( $count ),
		hj_refs_num( (int) $summary['approved_count'] )
	);

	$out  = '<section class="hj-refs" aria-labelledby="hj-refs-title">';
	$out .= '<div class="hj-refs__head">';
	$out .= '<span class="hj-refs__icon" aria-hidden="true"></span>';
	$out .= '<h2 class="hj-refs__title" id="hj-refs-title">' . esc_html( $heading ) . '</h2>';
	$out .= '<span class="hj-refs__kicker" aria-hidden="true">References</span>';
	$out .= '</div>';
	$out .= '<p class="hj-refs__meta-line">' . esc_html( $note ) . '</p>';
	$out .= '<ol class="hj-refs__list">';

	foreach ( $rows as $row ) {
		$badge = 'hj-refs__badge--' . ( '' === $row['tier'] ? 'other' : $row['tier'] );
		$out  .= '<li class="hj-refs__item">';
		$out  .= '<span class="hj-refs__num" aria-hidden="true"></span>';
		$out  .= '<span class="hj-refs__body">';
		$out  .= '<span class="hj-refs__text">' . esc_html( $row['text'] ) . '</span>';
		$out  .= '<span class="hj-refs__row">';
		$out  .= '<span class="hj-refs__badge ' . esc_attr( $badge ) . '">' . esc_html( hj_refs_tier_label( $row['tier'] ) ) . '</span>';
		$out  .= '<a class="hj-refs__link" href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener">';
		$out  .= esc_html( '' !== $row['host'] ? $row['host'] : 'مشاهدهٔ منبع' );
		$out  .= '<span class="screen-reader-text"> (در پنجرهٔ تازه)</span>';
		$out  .= '</a>';
		$out  .= '</span>';
		$out  .= '</li>';
	}

	$out .= '</ol>';
	$out .= '<p class="hj-refs__note">این فهرست از نشانی‌های همین مقاله ساخته می‌شود و هر پیوند به صفحهٔ مشخص منبع می‌رود؛ دستور پزشک و پروتکل مرکز درمانی شما بر هر متن عمومی مقدم است.</p>';
	$out .= '</section>';

	return $out;
}

/**
 * Replace the plain "منابع" list with the reference block.
 *
 * Idempotent: content that already carries a `hj-refs` block is returned
 * untouched, and any list that cannot be parsed safely stays as it was.
 *
 * @param string $html Post content.
 * @return string
 */
function hj_refs_upgrade_content( string $html ): string {
	if ( '' === trim( $html ) || false !== strpos( $html, 'hj-refs' ) ) {
		return $html;
	}

	$pattern = '#(<h([23])\b[^>]*>)([^<]*?(?:منابع|مراجع|شواهد)[^<]*?)(</h\2>)\s*<(ul|ol)\b[^>]*>(.*?)</\5>#isu';

	return (string) preg_replace_callback(
		$pattern,
		static function ( $m ) {
			$block = hj_refs_build_section( trim( wp_strip_all_tags( $m[3] ) ), $m[6] );
			return '' === $block ? $m[0] : $block;
		},
		$html
	);
}
add_filter( 'the_content', 'hj_refs_upgrade_content', 7 );

/**
 * `[hjrefs]` — render the reference block anywhere in the article.
 *
 * Uses the article's own links, so it can never introduce a citation that the
 * publish gate has not counted.
 *
 * @return string
 */
function hj_refs_shortcode(): string {
	$post = get_post();
	if ( ! $post ) {
		return '';
	}

	$html   = (string) $post->post_content;
	$links  = hj_source_links( $html );
	$seen   = array();
	$markup = '';

	foreach ( $links as $link ) {
		if ( '' === $link['tier'] || isset( $seen[ $link['url'] ] ) ) {
			continue;
		}
		$seen[ $link['url'] ] = true;
		$markup .= '<li><a href="' . esc_url( $link['url'] ) . '" rel="noopener" target="_blank">' . esc_html( $link['host'] ) . '</a></li>';
	}

	if ( '' === $markup ) {
		return '';
	}

	return hj_refs_build_section( 'منابع و شواهد بالینی', '<ul>' . $markup . '</ul>' );
}
add_shortcode( 'hjrefs', 'hj_refs_shortcode' );

/**
 * Add the verified citations to the Article schema.
 *
 * Only approved domains are exported, capped at eight URLs, and only when an
 * SEO plugin does not own the graph.
 *
 * @param array $article Article node.
 * @param int   $post_id Post ID.
 * @return array
 */
function hj_refs_article_citation( $article, $post_id ) {
	if ( function_exists( 'hj_seo_plugin_active' ) && hj_seo_plugin_active() ) {
		return $article;
	}
	if ( ! is_array( $article ) ) {
		return $article;
	}

	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return $article;
	}

	$urls = array();
	foreach ( hj_source_links( (string) $post->post_content ) as $link ) {
		if ( '' !== $link['tier'] && ! in_array( $link['url'], $urls, true ) ) {
			$urls[] = $link['url'];
		}
		if ( count( $urls ) >= 8 ) {
			break;
		}
	}

	if ( $urls ) {
		$article['citation'] = $urls;
	}

	return $article;
}
add_filter( 'hj_seo_article_node', 'hj_refs_article_citation', 10, 2 );

/**
 * Register the `[hjrefs]` / block pattern so editors can drop the box in.
 *
 * @return void
 */
function hj_refs_register_pattern(): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'orsite-medical',
			array( 'label' => __( 'Clinical blocks', 'lax-theme-child' ) )
		);
	}

	$item = static function ( string $publisher, string $citation, string $badge, string $url ) {
		return '<!-- wp:html --><li class="hj-refs__item"><span class="hj-refs__num" aria-hidden="true"></span><span class="hj-refs__body"><span class="hj-refs__text">'
			. $publisher . ' — ' . $citation
			. '</span><span class="hj-refs__row"><span class="hj-refs__badge ' . $badge . '">'
			. ( 'hj-refs__badge--tier1' === $badge ? 'مرجع بالادست' : 'انجمن تخصصی' )
			. '</span><a class="hj-refs__link" href="' . $url . '" target="_blank" rel="noopener">'
			. wp_parse_url( $url, PHP_URL_HOST ) . '</a></span></span></li><!-- /wp:html -->';
	};

	register_block_pattern(
		'orsite/medical-references',
		array(
			'title'       => __( 'باکس منابع و شواهد بالینی (پایان مقاله)', 'lax-theme-child' ),
			'description' => __( 'فهرست شماره‌دار منابع با نشان ردهٔ اعتبار؛ نشانی‌ها را با صفحهٔ مشخص منبع جایگزین کنید.', 'lax-theme-child' ),
			'categories'  => array( 'orsite-medical' ),
			'keywords'    => array( 'منابع', 'مراجع', 'رفرنس', 'references', 'sources' ),
			'content'     => '<!-- wp:html -->
<section class="hj-refs" aria-labelledby="hj-refs-title">
<div class="hj-refs__head"><span class="hj-refs__icon" aria-hidden="true"></span><h2 class="hj-refs__title" id="hj-refs-title">منابع و شواهد بالینی</h2><span class="hj-refs__kicker" aria-hidden="true">References</span></div>
<p class="hj-refs__meta-line">۳ منبع در این فهرست · ۳ دامنهٔ معتبر</p>
<ol class="hj-refs__list">
' . $item( 'MedlinePlus (کتابخانهٔ ملی پزشکی آمریکا)', 'صفحهٔ مشخص موضوع', 'hj-refs__badge--tier1', 'https://medlineplus.gov/' ) . '
' . $item( 'Mayo Clinic', 'صفحهٔ مشخص روش یا مراقبت', 'hj-refs__badge--tier1', 'https://www.mayoclinic.org/' ) . '
' . $item( 'انجمن تخصصی جراحی', 'راهنمای آموزش بیمار', 'hj-refs__badge--tier2', 'https://www.facs.org/' ) . '
</ol>
<p class="hj-refs__note">هر پیوند را به صفحهٔ مشخص منبع (نه صفحهٔ اصلی) تغییر دهید؛ فهرست خودکار از نشانی‌های مقاله ساخته می‌شود.</p>
</section>
<!-- /wp:html -->',
		)
	);
}
add_action( 'init', 'hj_refs_register_pattern' );

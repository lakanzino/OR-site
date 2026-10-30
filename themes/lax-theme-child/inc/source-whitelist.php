<?php
/**
 * Medical source whitelist (child 0.17.0).
 *
 * One list, two consumers: the publish gate inside WordPress and
 * `tools/content-lint.py` for article packages. Both read
 * `data/source-whitelist.json`, so the gate and the offline linter cannot
 * disagree about what "an approved source" means.
 *
 * A source counts only when its host really belongs to an approved domain:
 * the test is "host equals the domain, or ends with a dot followed by the
 * domain". A substring test would accept `notnih.gov` or `evil-who.int.example`;
 * the suffix test never does.
 *
 * Two tiers, both counted:
 *  - tier 1: federal/national bodies and hospital systems (NIH, NLM/MedlinePlus,
 *            CDC, WHO, Cochrane, Mayo, UpToDate, NHS, NICE, MSD Manuals,
 *            Iranian MoH and the medical universities under `ac.ir`)
 *  - tier 2: recognised specialty societies and their patient-education arms
 *            (ACS/FACS, SAGES, ASA, ACOG, AAO, ATA, AUA, AAP, …)
 *
 * The default rule is three distinct approved domains *and* at least one from
 * tier 1. That keeps the reference list from collapsing into a single
 * publisher's patient-education pages.
 *
 * @package Lax_Theme_Child
 * @since   0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approved domains, loaded from data/source-whitelist.json.
 *
 * @param bool $reload Drop the memoised copy.
 * @return array{loaded:bool,version:string,min_domains:int,min_tier1:int,tier1:array<int,string>,tier2:array<int,string>}
 */
function hj_source_whitelist( bool $reload = false ): array {
	static $list = null;

	if ( null !== $list && ! $reload ) {
		return $list;
	}

	$empty = array(
		'loaded'      => false,
		'version'     => '',
		'min_domains' => 1,
		'min_tier1'   => 0,
		'tier1'       => array(),
		'tier2'       => array(),
	);

	$path = get_theme_file_path( 'data/source-whitelist.json' );
	if ( ! $path || ! is_readable( $path ) ) {
		$list = $empty;
		return $list;
	}

	$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local theme file.
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) || empty( $data['tier1'] ) ) {
		$list = $empty;
		return $list;
	}

	$clean = static function ( $domains ): array {
		$out = array();
		foreach ( (array) $domains as $domain ) {
			$domain = strtolower( ltrim( trim( (string) $domain ), '.' ) );
			if ( '' !== $domain ) {
				$out[] = $domain;
			}
		}
		return array_values( array_unique( $out ) );
	};

	$list = array(
		'loaded'      => true,
		'version'     => isset( $data['version'] ) ? (string) $data['version'] : '',
		'min_domains' => isset( $data['min_domains'] ) ? max( 1, (int) $data['min_domains'] ) : 1,
		'min_tier1'   => isset( $data['min_tier1'] ) ? max( 0, (int) $data['min_tier1'] ) : 0,
		'tier1'       => $clean( $data['tier1'] ),
		'tier2'       => $clean( isset( $data['tier2'] ) ? $data['tier2'] : array() ),
	);

	/**
	 * Filter the whitelist so the site owner can add a national society
	 * without editing the JSON file or the gate logic.
	 *
	 * @param array $list Whitelist as loaded from data/source-whitelist.json.
	 */
	return apply_filters( 'hj_source_whitelist', $list );
}

/**
 * Normalise a host: lower-case, no port, no leading `www.`.
 *
 * @param string $host Raw host.
 * @return string
 */
function hj_source_normalize_host( string $host ): string {
	$host = strtolower( trim( $host ) );
	$host = preg_replace( '/:\d+$/', '', $host );
	$host = preg_replace( '/^www\./', '', (string) $host );
	return trim( (string) $host, '.' );
}

/**
 * Tier of a host, or an empty string when the host is not approved.
 *
 * @param string $host Host name (no scheme, no path).
 * @return string 'tier1' | 'tier2' | ''
 */
function hj_source_host_tier( string $host ): string {
	$host = hj_source_normalize_host( $host );
	if ( '' === $host ) {
		return '';
	}

	$list = hj_source_whitelist();
	foreach ( array( 'tier1', 'tier2' ) as $tier ) {
		foreach ( $list[ $tier ] as $domain ) {
			$len = strlen( $domain );
			if ( $host === $domain ) {
				return $tier;
			}
			if ( strlen( $host ) > $len && '.' === substr( $host, -( $len + 1 ), 1 ) && substr( $host, -$len ) === $domain ) {
				return $tier;
			}
		}
	}

	return '';
}

/**
 * Every outbound link in a piece of HTML, with its host and tier.
 *
 * Absolute links only: relative links point inside this site and never count
 * as an external source.
 *
 * @param string $html Post content.
 * @return array<int,array{url:string,host:string,tier:string}>
 */
function hj_source_links( string $html ): array {
	$links = array();
	if ( ! preg_match_all( '/<a\b[^>]*\bhref=[\"\']([^\"\']+)[\"\']/i', $html, $matches ) ) {
		return $links;
	}

	foreach ( $matches[1] as $url ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			continue;
		}
		$host    = hj_source_normalize_host( (string) $host );
		$links[] = array(
			'url'  => $url,
			'host' => $host,
			'tier' => hj_source_host_tier( $host ),
		);
	}

	return $links;
}

/**
 * Counting summary used by the gate, the meta box and the importer audit.
 *
 * @param string $html Post content.
 * @return array{loaded:bool,approved:array<int,string>,tier1:array<int,string>,tier2:array<int,string>,other:array<int,string>,approved_count:int,tier1_count:int,min:int,ok:bool}
 */
function hj_source_summary( string $html ): array {
	$list   = hj_source_whitelist();
	$tier1  = array();
	$tier2  = array();
	$other  = array();

	foreach ( hj_source_links( $html ) as $link ) {
		if ( 'tier1' === $link['tier'] ) {
			$tier1[ $link['host'] ] = true;
		} elseif ( 'tier2' === $link['tier'] ) {
			$tier2[ $link['host'] ] = true;
		} elseif ( '' !== $link['host'] ) {
			$other[ $link['host'] ] = true;
		}
	}

	$approved = array_keys( $tier1 + $tier2 );
	sort( $approved );

	$min = (int) apply_filters( 'hj_gate_min_sources', (int) $list['min_domains'] );

	return array(
		'loaded'         => (bool) $list['loaded'],
		'approved'       => $approved,
		'tier1'          => array_keys( $tier1 ),
		'tier2'          => array_keys( $tier2 ),
		'other'          => array_keys( $other ),
		'approved_count' => count( $approved ),
		'tier1_count'    => count( $tier1 ),
		'min'            => max( 1, $min ),
		'ok'             => ( (bool) $list['loaded']
			&& count( $approved ) >= max( 1, $min )
			&& count( $tier1 ) >= (int) $list['min_tier1'] ),
	);
}

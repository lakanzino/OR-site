<?php
/**
 * Baseline technical SEO for Hamyar Jarahi (child theme 0.6.0).
 *
 * Emits title/description/canonical/Open Graph and truthful JSON-LD
 * only when no dedicated SEO plugin is active. If Rank Math, Yoast,
 * AIOSEO, SEOPress or The SEO Framework is installed later, this file
 * steps aside completely so nothing is duplicated.
 *
 * Standards canon: docs/HOMEPAGE-AND-SEO-PLAYBOOK.md
 *
 * @package Lax_Theme_Child
 * @since   0.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect a dedicated SEO plugin (then this whole file stays silent).
 *
 * @return bool
 */
function hj_seo_plugin_active() {
	return defined( 'RANK_MATH_VERSION' )
		|| defined( 'WPSEO_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
		|| class_exists( 'RankMath' )
		|| class_exists( 'WPSEO_Options' );
}

if ( ! hj_seo_plugin_active() ) :

	remove_action( 'wp_head', 'rel_canonical' );

	/**
	 * Site name used across SEO output.
	 *
	 * @return string
	 */
	function hj_seo_site_name() {
		$name = get_bloginfo( 'name' );
		return $name ? $name : 'همیار جراحی';
	}

	/**
	 * Meta description for the current view.
	 *
	 * @return string
	 */
	function hj_seo_description() {
		if ( is_front_page() ) {
			return 'همیار جراحی مجله‌ی آموزشی بیمار و همراه در مسیر جراحی است: مسیر بیمار، اتاق عمل، تیم جراحی، جراحی‌ها و واژه‌نامه، با زبان ساده و منابع رسمی در ایران.';
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$custom  = (string) get_post_meta( $post_id, '_hj_meta_description', true );
			if ( '' !== trim( $custom ) ) {
				return trim( $custom );
			}
			$post = get_post( $post_id );
			if ( $post ) {
				$raw = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
				return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $raw ) ), 32, '…' );
			}
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->description ) ) {
				return wp_trim_words( wp_strip_all_tags( (string) $term->description ), 32, '…' );
			}
			if ( $term ) {
				return 'مقاله‌ها و راهنماهای آموزشی بخش «' . $term->name . '» در همیار جراحی؛ توضیح ساده برای بیمار و همراه، با منابع رسمی، اصطلاح‌های روشن و پیوند به گام بعدی مسیر.';
			}
		}

		if ( is_post_type_archive() ) {
			$object = get_queried_object();
			$label  = ( $object && isset( $object->labels->name ) ) ? $object->labels->name : '';
			return 'بخش «' . $label . '» در همیار جراحی؛ راهنمای آموزشی بیمار و همراه در مسیر جراحی، با زبان ساده، نگاه تکنسین اتاق عمل، منابع رسمی و پیوند روشن به گام بعدی مسیر.';
		}

		$description = get_bloginfo( 'description' );
		return $description ? $description : 'مجله‌ی آموزشی همیار جراحی برای بیمار و همراه در مسیر جراحی.';
	}

	/**
	 * Document title for the current view.
	 *
	 * @param string $title Default title.
	 * @return string
	 */
	function hj_seo_document_title( $title ) {
		$site = hj_seo_site_name();

		if ( is_front_page() ) {
			return 'همیار جراحی | راهنمای بیمار و همراه در مسیر جراحی و اتاق عمل';
		}
		if ( is_404() ) {
			return 'صفحه پیدا نشد | ' . $site;
		}
		if ( is_search() ) {
			return 'نتایج جستجو برای «' . get_search_query() . '» | ' . $site;
		}
		if ( is_singular() ) {
			$post_title = get_the_title( get_queried_object_id() );
			if ( $post_title ) {
				return ( false === strpos( $post_title, 'همیار جراحی' ) )
					? $post_title . ' | ' . $site
					: $post_title;
			}
		}
		if ( is_archive() ) {
			$archive = wp_strip_all_tags( get_the_archive_title() );
			if ( $archive ) {
				return $archive . ' | ' . $site;
			}
		}
		return $title;
	}
	add_filter( 'pre_get_document_title', 'hj_seo_document_title', 20 );

	/**
	 * Canonical URL for indexable views (empty for search/404).
	 *
	 * @return string
	 */
	function hj_seo_canonical_url() {
		if ( is_404() || is_search() ) {
			return '';
		}
		if ( is_singular() ) {
			$url = get_permalink( get_queried_object_id() );
			return $url ? $url : '';
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
			return $url ? $url : home_url( '/' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term ) {
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? '' : $link;
			}
		}
		if ( is_post_type_archive() ) {
			$object = get_queried_object();
			if ( $object && isset( $object->name ) ) {
				$link = get_post_type_archive_link( $object->name );
				return $link ? $link : '';
			}
		}
		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author ) {
				$link = get_author_posts_url( (int) $author->ID );
				return $link ? $link : '';
			}
		}
		return '';
	}

	/**
	 * Featured/logo image URL for social tags.
	 *
	 * @return string
	 */
	function hj_seo_image_url() {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( has_post_thumbnail( $post_id ) ) {
				$url = get_the_post_thumbnail_url( $post_id, 'full' );
				if ( $url ) {
					return $url;
				}
			}
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * Print description, canonical and Open Graph/Twitter tags.
	 *
	 * @return void
	 */
	function hj_seo_head_tags() {
		if ( is_feed() ) {
			return;
		}
		$description = hj_seo_description();
		$canonical   = hj_seo_canonical_url();
		$image       = hj_seo_image_url();
		$title       = wp_get_document_title();
		$og_type     = is_singular() && ! is_page() ? 'article' : 'website';
		$locale      = str_replace( '-', '_', get_locale() );

		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}
		if ( $canonical ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
		}

		printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( $locale ) );
		printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $og_type ) );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( hj_seo_site_name() ) );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		if ( $description ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
		}
		if ( $canonical ) {
			printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $canonical ) );
		}
		if ( $image ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		}

		printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );
		printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
		if ( $description ) {
			printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );
		}
		if ( $image ) {
			printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );
		}
	}
	add_action( 'wp_head', 'hj_seo_head_tags', 5 );

	/**
	 * Search and 404 views are noindex,follow.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	function hj_seo_robots( $robots ) {
		if ( is_search() || is_404() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}
	add_filter( 'wp_robots', 'hj_seo_robots' );

	/**
	 * Truthful author node for schema. Never invents a person:
	 * explicit post meta wins, seeded pages use the declared editor,
	 * everything else uses the real WordPress author record.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	function hj_seo_author_node( $post_id ) {
		$name = trim( (string) get_post_meta( $post_id, '_hj_author_name', true ) );
		$job  = trim( (string) get_post_meta( $post_id, '_hj_author_job', true ) );

		if ( '' === $name && function_exists( 'hj_is_seeded_page' ) && hj_is_seeded_page( $post_id ) ) {
			// مقالات seeded هویت سازمانی می‌گیرند؛ نام سردبیر فقط روی صفحه‌ی درباره ما می‌آید.
			return array( '@id' => home_url( '/#organization' ) );
		}
		if ( '' === $name ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
			}
		}
		if ( '' === $name ) {
			return array( '@id' => home_url( '/#organization' ) );
		}
		$node = array(
			'@type' => 'Person',
			'name'  => $name,
		);
		if ( $job ) {
			$node['jobTitle'] = $job;
		}
		return $node;
	}

	/**
	 * Breadcrumb items for schema, mirroring the visible breadcrumb.
	 *
	 * @return array<int,array<string,string>>
	 */
	function hj_seo_breadcrumb_items() {
		$items = array(
			array( 'name' => 'خانه', 'item' => home_url( '/' ) ),
		);

		if ( is_singular() ) {
			$post_id   = get_queried_object_id();
			$post_type = get_post_type( $post_id );
			$archive   = ( $post_type && 'page' !== $post_type ) ? get_post_type_archive_link( $post_type ) : '';
			$object    = $post_type ? get_post_type_object( $post_type ) : null;
			if ( $archive && $object ) {
				$items[] = array( 'name' => $object->labels->name, 'item' => $archive );
			}
			if ( function_exists( 'orsite_primary_term' ) && $post_type ) {
				$term = orsite_primary_term( $post_id, $post_type );
				if ( $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) ) {
						$items[] = array( 'name' => $term->name, 'item' => $link );
					}
				}
			}
			$items[] = array( 'name' => get_the_title( $post_id ), 'item' => get_permalink( $post_id ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term ) {
				$link    = get_term_link( $term );
				$items[] = array( 'name' => $term->name, 'item' => is_wp_error( $link ) ? '' : $link );
			}
		} elseif ( is_post_type_archive() ) {
			$object = get_queried_object();
			if ( $object && isset( $object->labels->name ) ) {
				$items[] = array(
					'name' => $object->labels->name,
					'item' => get_post_type_archive_link( $object->name ),
				);
			}
		}
		return $items;
	}

	/**
	 * Print JSON-LD: Organization + WebSite everywhere, plus
	 * Article/MedicalWebPage and BreadcrumbList where truthful.
	 *
	 * @return void
	 */
	function hj_seo_json_ld() {
		if ( is_feed() || is_404() || is_search() ) {
			return;
		}
		$home  = home_url( '/' );
		$org   = array(
			'@type' => 'Organization',
			'@id'   => $home . '#organization',
			'name'  => hj_seo_site_name(),
			'url'   => $home,
			'email' => 'mail@hamyarjarahi.ir',
		);
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$org['logo'] = $logo_url;
			}
		}

		$graph = array(
			$org,
			array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => hj_seo_site_name(),
				'inLanguage'      => get_bloginfo( 'language' ),
				'publisher'       => array( '@id' => $home . '#organization' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}' ),
					),
					'query-input' => 'required name=search_term_string',
				),
			),
		);

		if ( is_front_page() ) {
			$graph[] = array(
				'@type'       => 'WebPage',
				'@id'         => $home . '#webpage',
				'url'         => $home,
				'name'        => wp_get_document_title(),
				'description' => hj_seo_description(),
				'inLanguage'  => get_bloginfo( 'language' ),
				'isPartOf'    => array( '@id' => $home . '#website' ),
				'about'       => array( '@id' => $home . '#organization' ),
			);
		}

		if ( is_singular() ) {
			$post_id   = get_queried_object_id();
			$post      = get_post( $post_id );
			$post_type = $post ? $post->post_type : '';
			if ( $post ) {
				$canonical = get_permalink( $post_id );
				if ( 'page' === $post_type ) {
					$page_type = 'WebPage';
					if ( 'about' === $post->post_name ) {
						$page_type = 'AboutPage';
					} elseif ( 'contact' === $post->post_name ) {
						$page_type = 'ContactPage';
					}
					$graph[] = array(
						'@type'       => $page_type,
						'@id'         => $canonical . '#webpage',
						'url'         => $canonical,
						'name'        => get_the_title( $post_id ),
						'description' => hj_seo_description(),
						'inLanguage'  => get_bloginfo( 'language' ),
						'isPartOf'    => array( '@id' => $home . '#website' ),
						'dateModified' => get_post_modified_time( DATE_W3C, false, $post ),
					);
				} else {
					$article = array(
						'@type'            => array( 'Article', 'MedicalWebPage' ),
						'@id'              => $canonical . '#article',
						'url'              => $canonical,
						'mainEntityOfPage' => $canonical,
						'headline'         => get_the_title( $post_id ),
						'description'      => hj_seo_description(),
						'inLanguage'       => get_bloginfo( 'language' ),
						'author'           => hj_seo_author_node( $post_id ),
						'publisher'        => array( '@id' => $home . '#organization' ),
						'datePublished'    => get_post_time( DATE_W3C, false, $post ),
						'dateModified'     => get_post_modified_time( DATE_W3C, false, $post ),
					);

					if ( function_exists( 'orsite_primary_term' ) ) {
						$term = orsite_primary_term( $post_id, $post_type );
						if ( $term ) {
							$article['articleSection'] = $term->name;
						}
					}

					$thumbnail_id = get_post_thumbnail_id( $post_id );
					if ( $thumbnail_id ) {
						$src = wp_get_attachment_image_src( $thumbnail_id, 'full' );
						if ( $src ) {
							$article['image'] = array(
								'@type'  => 'ImageObject',
								'url'    => $src[0],
								'width'  => (int) $src[1],
								'height' => (int) $src[2],
							);
						}
					}
					/**
					 * Filter the Article node before it enters the graph
					 * (the references box adds `citation` URLs here).
					 *
					 * @param array $article Article node.
					 * @param int   $post_id Current post ID.
					 */
					$graph[] = apply_filters( 'hj_seo_article_node', $article, $post_id );
				}
			}
		}

		$crumbs = hj_seo_breadcrumb_items();
		if ( count( $crumbs ) > 1 ) {
			$list = array();
			foreach ( $crumbs as $i => $crumb ) {
				$entry = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb['name'],
				);
				if ( ! empty( $crumb['item'] ) ) {
					$entry['item'] = $crumb['item'];
				}
				$list[] = $entry;
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $list,
			);
		}

		$payload = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}
	add_action( 'wp_head', 'hj_seo_json_ld', 20 );

	/**
	 * Point robots.txt at the core sitemap (SEO plugins take over later).
	 *
	 * @param string $output robots.txt output.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	function hj_seo_robots_txt( $output, $public ) {
		if ( $public ) {
			$sitemap = home_url( '/wp-sitemap.xml' );
			if ( false === strpos( (string) $output, 'Sitemap:' ) ) {
				$output .= "\nSitemap: " . $sitemap . "\n";
			}
		}
		return $output;
	}
	add_filter( 'robots_txt', 'hj_seo_robots_txt', 10, 2 );

	/**
	 * Keep explicitly noindexed posts out of the automatic core sitemap.
	 * A post is excluded only when an editor/importer sets `_hj_noindex` = 1.
	 *
	 * @param array  $args      WP_Query arguments for the sitemap provider.
	 * @param string $post_type Post type being listed.
	 * @return array
	 */
	function hj_seo_sitemap_query_args( $args, $post_type ) {
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_hj_noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_hj_noindex',
				'value'   => '1',
				'compare' => '!=',
			),
		);
		return $args;
	}
	add_filter( 'wp_sitemaps_posts_query_args', 'hj_seo_sitemap_query_args', 10, 2 );

endif;

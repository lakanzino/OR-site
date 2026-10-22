<?php
/**
 * Essential static pages: one-time, create-if-missing seeding.
 *
 * The page copy lives in content/pages/*.md in the repository and is
 * compiled into inc/essential-pages-data.php. On the first request after
 * this theme version is installed, any missing page is created as a
 * published WordPress page. Existing pages are never overwritten, so
 * editor changes in wp-admin always win.
 *
 * @package Lax_Theme_Child
 * @since   0.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HJ_ESSENTIAL_PAGES_VERSION' ) ) {
	define( 'HJ_ESSENTIAL_PAGES_VERSION', '1.0.0' );
}

/**
 * Resolve an essential page ID by slug (0 when it does not exist yet).
 *
 * @param string $slug Page slug.
 * @return int
 */
function hj_page_id( $slug ) {
	static $cache = array();
	$slug         = sanitize_title( (string) $slug );

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$page           = get_page_by_path( $slug, OBJECT, 'page' );
	$cache[ $slug ] = $page ? (int) $page->ID : 0;
	return $cache[ $slug ];
}

/**
 * URL of an essential page. Uses the real permalink so the theme also
 * works while WordPress is still on plain (?page_id=) permalinks.
 *
 * @param string $slug Page slug.
 * @return string
 */
function hj_page_url( $slug ) {
	$id = hj_page_id( $slug );
	if ( $id ) {
		$url = get_permalink( $id );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/' . sanitize_title( (string) $slug ) . '/' );
}

/**
 * Archive URL for a project post type (home as a safe fallback).
 *
 * @param string $post_type Post type key.
 * @return string
 */
function hj_archive_url( $post_type ) {
	$url = get_post_type_archive_link( $post_type );
	return $url ? $url : home_url( '/' );
}

/**
 * Term URL with a safe fallback to the post-type archive.
 *
 * @param string $taxonomy    Taxonomy key.
 * @param string $term_slug   Term slug.
 * @param string $fallback_pt Post type whose archive is the fallback.
 * @return string
 */
function hj_term_url( $taxonomy, $term_slug, $fallback_pt = '' ) {
	$term = get_term_by( 'slug', $term_slug, $taxonomy );
	if ( $term ) {
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return $fallback_pt ? hj_archive_url( $fallback_pt ) : home_url( '/' );
}

/**
 * Whether a post was seeded from the bundled essential-page packages.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function hj_is_seeded_page( $post_id ) {
	return 'hamyarjarahi-essential-pages' === (string) get_post_meta( (int) $post_id, '_hj_page_source', true );
}

/**
 * First administrator user ID (author for seeded pages).
 *
 * @return int
 */
function hj_seed_author_id() {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ID',
		)
	);
	if ( ! empty( $admins ) ) {
		return (int) $admins[0];
	}
	$current = get_current_user_id();
	return $current ? (int) $current : 1;
}

/**
 * Add one menu item unless an item for the same object/URL exists.
 *
 * @param int    $menu_id       Menu term ID.
 * @param string $title         Menu item title.
 * @param array  $args          wp_update_nav_menu_item() arguments.
 * @param array  $existing_keys Keys of items already in the menu.
 * @param int    $position      Menu order.
 * @return void
 */
function hj_add_menu_item_once( $menu_id, $title, $args, $existing_keys, $position ) {
	$key = '';
	if ( ! empty( $args['menu-item-object-id'] ) ) {
		$key = 'object:' . (int) $args['menu-item-object-id'];
	} elseif ( ! empty( $args['menu-item-url'] ) ) {
		$key = 'url:' . untrailingslashit( (string) $args['menu-item-url'] );
	}
	if ( $key && isset( $existing_keys[ $key ] ) ) {
		return;
	}

	$defaults = array(
		'menu-item-title'     => $title,
		'menu-item-status'    => 'publish',
		'menu-item-position'  => $position,
		'menu-item-type'      => 'custom',
		'menu-item-object'    => 'custom',
		'menu-item-object-id' => 0,
		'menu-item-url'       => '',
	);
	wp_update_nav_menu_item( $menu_id, 0, array_merge( $defaults, $args ) );
}

/**
 * Create the primary/footer menus only when their location is empty.
 *
 * @param array $page_ids Map of slug => page ID.
 * @return void
 */
function hj_seed_menus( $page_ids ) {
	$locations = get_theme_mod( 'nav_menu_locations' );
	$locations = is_array( $locations ) ? $locations : array();
	$changed   = false;

	$defs = array(
		'primary' => array(
			'name'  => 'منوی اصلی همیار جراحی',
			'items' => array(
				array( 'title' => 'خانه', 'url' => home_url( '/' ) ),
				array( 'title' => 'راهنمای بیمار', 'url' => hj_archive_url( 'patient_guide' ) ),
				array( 'title' => 'اتاق عمل و تیم', 'url' => hj_archive_url( 'or_environment' ) ),
				array( 'title' => 'جراحی‌ها', 'url' => hj_archive_url( 'surgery' ) ),
				array( 'title' => 'واژه‌نامه', 'url' => hj_archive_url( 'glossary' ) ),
				array( 'title' => 'درباره ما', 'page' => 'about' ),
				array( 'title' => 'تماس با ما', 'page' => 'contact' ),
			),
		),
		'footer'  => array(
			'name'  => 'منوی پانوشت همیار جراحی',
			'items' => array(
				array( 'title' => 'درباره ما', 'page' => 'about' ),
				array( 'title' => 'تماس با ما', 'page' => 'contact' ),
				array( 'title' => 'پرسش‌های متداول', 'page' => 'faq' ),
				array( 'title' => 'سلب مسئولیت پزشکی', 'page' => 'medical-disclaimer' ),
				array( 'title' => 'حریم خصوصی', 'page' => 'privacy' ),
				array( 'title' => 'شرایط استفاده', 'page' => 'terms' ),
			),
		),
	);

	foreach ( $defs as $location => $def ) {
		if ( ! empty( $locations[ $location ] ) ) {
			continue; // An editor-assigned menu always wins.
		}

		$menu = wp_get_nav_menu_object( $def['name'] );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $def['name'] );
			if ( is_wp_error( $menu_id ) ) {
				continue;
			}
			$menu = wp_get_nav_menu_object( $menu_id );
		}
		if ( ! $menu ) {
			continue;
		}

		$existing = array();
		$items    = wp_get_nav_menu_items( $menu->term_id );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( ! empty( $item->object_id ) && 'page' === $item->object ) {
					$existing[ 'object:' . (int) $item->object_id ] = true;
				}
				if ( ! empty( $item->url ) ) {
					$existing[ 'url:' . untrailingslashit( (string) $item->url ) ] = true;
				}
			}
		}

		$position = 1;
		foreach ( $def['items'] as $item ) {
			if ( ! empty( $item['page'] ) && ! empty( $page_ids[ $item['page'] ] ) ) {
				hj_add_menu_item_once(
					(int) $menu->term_id,
					$item['title'],
					array(
						'menu-item-type'      => 'post_type',
						'menu-item-object'    => 'page',
						'menu-item-object-id' => (int) $page_ids[ $item['page'] ],
					),
					$existing,
					$position
				);
			} elseif ( ! empty( $item['url'] ) ) {
				hj_add_menu_item_once(
					(int) $menu->term_id,
					$item['title'],
					array( 'menu-item-url' => $item['url'] ),
					$existing,
					$position
				);
			}
			$position++;
		}

		$locations[ $location ] = (int) $menu->term_id;
		$changed                = true;
	}

	if ( $changed ) {
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}

/**
 * Create any missing essential page (published), once per data version.
 *
 * Existing pages (matched by slug) are left completely untouched.
 *
 * @return void
 */
function hj_seed_essential_pages() {
	if ( wp_installing() || ! function_exists( 'hj_essential_pages_data' ) ) {
		return;
	}
	if ( get_option( 'hj_essential_pages_version' ) === HJ_ESSENTIAL_PAGES_VERSION ) {
		return;
	}

	$pages = hj_essential_pages_data();
	if ( ! is_array( $pages ) || ! $pages ) {
		return;
	}

	$author_id = hj_seed_author_id();
	$page_ids  = array();
	$created   = array();
	$existing  = array();
	$failed    = array();

	foreach ( $pages as $page ) {
		if ( empty( $page['slug'] ) || empty( $page['title'] ) ) {
			continue;
		}
		$slug   = sanitize_title( (string) $page['slug'] );
		$found  = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $found ) {
			$page_ids[ $slug ] = (int) $found->ID;
			$existing[]        = $slug;
			continue;
		}

		$new_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_name'      => $slug,
				'post_title'     => (string) $page['title'],
				'post_content'   => isset( $page['content_html'] ) ? (string) $page['content_html'] : '',
				'post_excerpt'   => isset( $page['excerpt'] ) ? (string) $page['excerpt'] : '',
				'post_author'    => $author_id,
				'menu_order'     => isset( $page['menu_order'] ) ? (int) $page['menu_order'] : 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			$failed[] = $slug;
			continue;
		}

		update_post_meta( (int) $new_id, '_hj_page_source', 'hamyarjarahi-essential-pages' );
		update_post_meta( (int) $new_id, '_hj_page_source_version', HJ_ESSENTIAL_PAGES_VERSION );
		if ( ! empty( $page['meta_description'] ) ) {
			update_post_meta( (int) $new_id, '_hj_meta_description', (string) $page['meta_description'] );
		}
		if ( ! empty( $page['updated_at'] ) ) {
			update_post_meta( (int) $new_id, '_hj_page_updated_at', (string) $page['updated_at'] );
		}
		$page_ids[ $slug ] = (int) $new_id;
		$created[]         = $slug;
	}

	update_option(
		'hj_essential_pages_report',
		array(
			'at'       => current_time( 'mysql' ),
			'created'  => $created,
			'existing' => $existing,
			'failed'   => $failed,
		)
	);

	if ( $failed ) {
		return; // Leave the version unset so a later request retries.
	}

	if ( ! empty( $page_ids['privacy'] ) ) {
		$privacy_id   = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy_post = $privacy_id ? get_post( $privacy_id ) : null;
		// A published editor-chosen policy wins; WordPress' stock draft
		// (or a missing/trashed page) is replaced by our published page.
		if ( ! $privacy_post || 'publish' !== $privacy_post->post_status ) {
			update_option( 'wp_page_for_privacy_policy', (int) $page_ids['privacy'] );
		}
	}

	hj_seed_menus( $page_ids );
	flush_rewrite_rules( false );
	update_option( 'hj_essential_pages_version', HJ_ESSENTIAL_PAGES_VERSION );
}
add_action( 'init', 'hj_seed_essential_pages', 98 );

/**
 * Rewrite the bundled root-relative page links to real permalinks.
 *
 * Keeps internal links correct under plain permalinks and after any
 * permalink-structure change.
 *
 * @param string $content Post content.
 * @return string
 */
function hj_rewrite_essential_page_links( $content ) {
	if ( ! is_string( $content ) || false === strpos( $content, 'href=' ) ) {
		return $content;
	}
	if ( ! function_exists( 'hj_essential_pages_data' ) ) {
		return $content;
	}
	foreach ( hj_essential_pages_data() as $page ) {
		if ( empty( $page['slug'] ) ) {
			continue;
		}
		$slug = sanitize_title( (string) $page['slug'] );
		$url  = hj_page_url( $slug );
		$content = str_replace(
			array( 'href="/' . $slug . '/"', "href='/" . $slug . "/'", 'href="' . $slug . '.md"', "href='" . $slug . ".md'" ),
			array( 'href="' . esc_url( $url ) . '"', 'href="' . esc_url( $url ) . '"', 'href="' . esc_url( $url ) . '"', 'href="' . esc_url( $url ) . '"' ),
			$content
		);
	}
	return $content;
}
add_filter( 'the_content', 'hj_rewrite_essential_page_links', 9 );

/**
 * Seeded trust/legal pages stay comment-free.
 *
 * @param bool $open    Whether comments are open.
 * @param int  $post_id Post ID.
 * @return bool
 */
function hj_close_comments_on_seeded_pages( $open, $post_id ) {
	if ( $open && 'page' === get_post_type( $post_id ) && hj_is_seeded_page( $post_id ) ) {
		return false;
	}
	return $open;
}
add_filter( 'comments_open', 'hj_close_comments_on_seeded_pages', 10, 2 );
add_filter( 'pings_open', 'hj_close_comments_on_seeded_pages', 10, 2 );

/**
 * One-time 0.9.0 cleanup: retire the editorial-policy page.
 *
 * The editorial decision was to remove the standalone editorial policy
 * page. This routine (runs once, gated by an option flag):
 *   1. strips the known sentences/links pointing at /editorial-policy/
 *      from the other seeded trust pages (editor content otherwise
 *      stays untouched),
 *   2. removes every nav menu item linking to that slug,
 *   3. trashes the page itself so its URL no longer resolves.
 *
 * @return void
 */
function hj_v090_retire_editorial_policy() {
	if ( wp_installing() || get_option( 'hj_v090_cleanup_done' ) ) {
		return;
	}

	// A migration must never white-screen the site: any runtime failure is
	// logged and swallowed, and the missing flag makes it retry later.
	try {
		hj_v090_retire_editorial_policy_run();
	} catch ( \Throwable $hj_e ) {
		if ( function_exists( 'error_log' ) ) {
			error_log( 'hj_v090 cleanup failed: ' . $hj_e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

/**
 * Actual 0.9.0 cleanup worker, wrapped by hj_v090_retire_editorial_policy().
 *
 * @return void
 */
function hj_v090_retire_editorial_policy_run() {
	// 1. Clean known clauses inside seeded pages. The href may be stored
	// root-relative (as seeded) or as a full permalink, so match loosely.
	// Each item is [pattern, replacement]. Patterns that consume the
	// sentence's own full stop replace with nothing; the contact clause
	// sits after a semicolon, so it keeps one closing full stop.
	$hj_clause_rules = array(
		array(
			'#جزئیات این روند در <a[^>]*editorial-policy[^>]*>سیاست نشریه همیار جراحی</a> آمده و #u',
			'',
		),
		array(
			'#؛ سازوکار کامل در <a[^>]*editorial-policy[^>]*>سیاست نشریه</a> آمده است\.#u',
			'.',
		),
		array(
			'# روند کامل در <a[^>]*editorial-policy[^>]*>سیاست نشریه</a> منتشر شده است\.#u',
			'',
		),
		array(
			'# روند کامل در <a[^>]*editorial-policy[^>]*>سیاست نشریه</a> آمده است\.#u',
			'',
		),
	);

	foreach ( array( 'about', 'contact', 'faq', 'medical-disclaimer' ) as $hj_slug ) {
		$hj_page = get_page_by_path( $hj_slug, OBJECT, 'page' );
		if ( ! $hj_page || ! hj_is_seeded_page( (int) $hj_page->ID ) ) {
			continue;
		}

		$hj_content = (string) $hj_page->post_content;
		$hj_before  = $hj_content;

		foreach ( $hj_clause_rules as $hj_rule ) {
			$hj_content = (string) preg_replace( $hj_rule[0], $hj_rule[1], $hj_content );
		}

		// Safety sweep: any surviving dead anchor becomes plain text.
		$hj_content = (string) preg_replace(
			'#<a[^>]*editorial-policy[^>]*>([^<]*)</a>#u',
			'$1',
			$hj_content
		);

		if ( $hj_content !== $hj_before ) {
			wp_update_post(
				array(
					'ID'           => (int) $hj_page->ID,
					'post_content' => $hj_content,
				)
			);
		}
	}

	// 2. Remove nav menu items that point at the retired page.
	// NOTE: wp_delete_nav_menu_item() lives in wp-admin/includes and is
	// not loaded on front-end requests, so the always-available
	// wp_delete_post() is used instead (menu items are nav_menu_item posts).
	$hj_menus = wp_get_nav_menus();
	if ( is_wp_error( $hj_menus ) ) {
		$hj_menus = array();
	}
	foreach ( $hj_menus as $hj_menu ) {
		$hj_items = wp_get_nav_menu_items( $hj_menu->term_id );
		if ( ! is_array( $hj_items ) ) {
			continue;
		}
		foreach ( $hj_items as $hj_item ) {
			if ( strpos( (string) $hj_item->url, 'editorial-policy' ) !== false ) {
				wp_delete_post( (int) $hj_item->ID, true );
			}
		}
	}

	// 3. Trash the retired page itself.
	$hj_policy_page = get_page_by_path( 'editorial-policy', OBJECT, 'page' );
	if ( $hj_policy_page ) {
		wp_trash_post( (int) $hj_policy_page->ID );
	}

	update_option( 'hj_v090_cleanup_done', '0.9.1' );
}
add_action( 'init', 'hj_v090_retire_editorial_policy', 99 );

/**
 * Launch checks for administrators. The theme never changes permalink
 * or search-engine-visibility settings silently; it only warns here.
 *
 * @return void
 */
function hj_launch_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && ! in_array( $screen->base, array( 'dashboard', 'themes', 'options-general', 'options-permalink', 'options-reading', 'edit' ), true ) ) {
		return;
	}

	$warnings = array();
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		$warnings[] = 'پیوندهای یکتا هنوز روی حالت «ساده» است؛ برای نشانی خوانا و سئو از تنظیمات ← پیوندهای یکتا، «نام نوشته» را انتخاب کنید.';
	}
	if ( ! (bool) get_option( 'blog_public' ) ) {
		$warnings[] = 'گزینه‌ی «ممانعت از موتورهای جستجو» فعال است؛ تا وقتی این گزینه فعال باشد سایت در گوگل ایندکس نمی‌شود.';
	}

	$sample_post = get_page_by_path( 'سلام-دنیا', OBJECT, 'post' );
	if ( ! $sample_post ) {
		$found = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'name'           => 'hello-world',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $found ) {
			$warnings[] = 'نوشته‌ی پیش‌فرض وردپرس هنوز منتشر است؛ پیش از معرفی سایت به گوگل آن را حذف یا پیش‌نویس کنید.';
		}
	} elseif ( 'publish' === $sample_post->post_status ) {
		$warnings[] = 'نوشته‌ی پیش‌فرض وردپرس هنوز منتشر است؛ پیش از معرفی سایت به گوگل آن را حذف یا پیش‌نویس کنید.';
	}

	if ( ! $warnings ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>چک‌لیست راه‌اندازی همیار جراحی:</strong></p><ul style="margin:0 0 0 1.2em;list-style:disc">';
	foreach ( $warnings as $warning ) {
		echo '<li>' . esc_html( $warning ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'hj_launch_admin_notices' );

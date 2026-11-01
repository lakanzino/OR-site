<?php
/**
 * Doctor directory glue (child 0.32.0): entry links into the doctor section,
 * article → specialty cross-links, legacy section removal (symptom checker
 * and lax-robot, owner decision 2026-11-01 «فعلاً از سایت کامل بردار»), and
 * the three demo surgeons with seed votes.
 *
 *  - creates the «پروفایل من» page (dashboard template) once, so specialists
 *    have a public registration/edit address;
 *  - adds «پزشکان» and «ثبت پروفایل پزشک» to the primary nav menu once;
 *  - articles (post type `post`) about a specialty get a box linking to that
 *    specialty's doctor listing (orthopedics article → all orthopedic
 *    surgeons, urology article → urology surgeons, …);
 *  - single doctor pages link to «همهٔ پزشکان این رشته»;
 *  - drafts legacy symptom-checker / robot pages and removes their menu
 *    items (pages are drafted, never deleted — reversible);
 *  - seeds three fictional demo surgeons (fake names/codes) with random
 *    22–61 seed votes (80% five stars) so the section is reviewable; a
 *    «حذف پزشکان نمونه» button lives on the votes admin page.
 *
 * @package Lax_Theme_Child
 * @since   0.32.0
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Entry points: dashboard page + nav menu items (once)
 * ---------------------------------------------------------------------- */

/**
 * Create the «پروفایل من» page once (public registration/edit address).
 *
 * @return void
 */
function hj_doctor_directory_setup_page(): void {
	if ( get_option( 'hj_doctor_dir_page_v1' ) ) {
		return;
	}
	$existing = get_page_by_path( 'my-profile' );
	if ( $existing instanceof WP_Post ) {
		update_post_meta( $existing->ID, '_wp_page_template', 'page-doctor-dashboard.php' );
		update_option( 'hj_doctor_dir_page_v1', (int) $existing->ID );
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'پروفایل من — پزشکان و جراحان',
			'post_name'   => 'my-profile',
			'post_content' => '',
		),
		true
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-doctor-dashboard.php' );
		update_option( 'hj_doctor_dir_page_v1', (int) $id );
		delete_transient( 'hj_doctor_dashboard_url' );
	}
}

/**
 * Add «پزشکان» + «ثبت پروفایل پزشک» to the primary menu(s), once.
 *
 * @return void
 */
function hj_doctor_directory_setup_menu(): void {
	if ( get_option( 'hj_doctor_dir_menu_v1' ) ) {
		return;
	}
	$locations = get_nav_menu_locations();
	$done      = false;
	foreach ( (array) $locations as $location => $menu_id ) {
		if ( ! $menu_id || ! preg_match( '/primary|main|header|menu-1/', (string) $location ) ) {
			continue;
		}
		$items = wp_get_nav_menu_items( (int) $menu_id );
		$urls  = array();
		foreach ( (array) $items as $item ) {
			$urls[] = (string) $item->url;
		}
		$archive = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( 'doctor' ) : false;
		$dash    = function_exists( 'hj_doctor_dashboard_url' ) ? hj_doctor_dashboard_url() : home_url( '/my-profile/' );
		if ( $archive && ! in_array( $archive, $urls, true ) ) {
			wp_update_nav_menu_item(
				(int) $menu_id,
				0,
				array(
					'menu-item-title'  => 'پزشکان',
					'menu-item-url'    => $archive,
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				)
			);
			$done = true;
		}
		if ( $dash && ! in_array( $dash, $urls, true ) ) {
			wp_update_nav_menu_item(
				(int) $menu_id,
				0,
				array(
					'menu-item-title'  => 'ثبت پروفایل پزشک',
					'menu-item-url'    => $dash,
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				)
			);
			$done = true;
		}
	}
	if ( $done || $locations ) {
		update_option( 'hj_doctor_dir_menu_v1', 1 );
	}
}

/* -------------------------------------------------------------------------
 * 2. Article → specialty cross-links
 * ---------------------------------------------------------------------- */

/**
 * Keyword → specialty slug map (terms must exist to be linked).
 *
 * @return array<string,string>
 */
function hj_doctor_specialty_keywords(): array {
	return array(
		'ارتوپد'      => 'orthopedics',
		'استخوان'     => 'orthopedics',
		'مفصل'        => 'orthopedics',
		'زانو'        => 'orthopedics',
		'لگن'         => 'orthopedics',
		'شکستگی'      => 'orthopedics',
		'ارولوژی'     => 'urology',
		'کلیه'        => 'urology',
		'مثانه'       => 'urology',
		'پروستات'     => 'urology',
		'سنگ کلیه'    => 'urology',
		'چشم'         => 'ophthalmology',
		'شبکیه'       => 'ophthalmology',
		'آب مروارید'  => 'ophthalmology',
		'بینایی'      => 'ophthalmology',
		'قلب'         => 'cardiology',
		'عروق'        => 'cardiology',
		'بای پس'      => 'cardiology',
		'آنژین'       => 'cardiology',
		'مغز و اعصاب' => 'neurosurgery',
		'ستون فقرات'  => 'neurosurgery',
		'دیسک کمر'    => 'neurosurgery',
		'زنان'        => 'gynecology',
		'زایمان'      => 'gynecology',
		'رحم'         => 'gynecology',
		'گوش'         => 'ent',
		'حلق'         => 'ent',
		'بینی'        => 'ent',
		'سینوزیت'     => 'ent',
		'پوست'        => 'dermatology',
		'آکنه'        => 'dermatology',
		'آپاندیس'     => 'general-surgery',
		'فتق'         => 'general-surgery',
		'کیسه صفرا'   => 'general-surgery',
		'جراحی عمومی' => 'general-surgery',
		'زیبایی'      => 'cosmetic',
		'پلاستیک'     => 'cosmetic',
		'رینوپلاستی'  => 'cosmetic',
		'بیهوشی'      => 'anesthesiology',
	);
}

/**
 * Existing specialty terms mentioned by a text.
 *
 * @param string $text Text to scan (title + excerpt is enough).
 * @return WP_Term[]
 */
function hj_doctor_match_specialties( string $text ): array {
	$found = array();
	foreach ( hj_doctor_specialty_keywords() as $keyword => $slug ) {
		if ( isset( $found[ $slug ] ) || false === mb_strpos( $text, $keyword ) ) {
			continue;
		}
		$term = term_exists( $slug, 'specialty' );
		if ( $term && ! is_wp_error( $term ) ) {
			$obj = get_term( (int) $term['term_id'], 'specialty' );
			if ( $obj instanceof WP_Term ) {
				$found[ $slug ] = $obj;
			}
		}
	}
	return array_values( $found );
}

/**
 * Append the «پزشکان این رشته» box to articles.
 *
 * @param string $content Post content.
 * @return string
 */
function hj_doctor_article_crosslinks( string $content ): string {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$post = get_post();
	if ( ! $post ) {
		return $content;
	}
	$terms = hj_doctor_match_specialties( $post->post_title . ' ' . wp_strip_all_tags( (string) $post->post_content ) );
	if ( ! $terms ) {
		return $content;
	}
	$links = '';
	foreach ( $terms as $term ) {
		$count = (int) count(
			get_posts(
				array(
					'post_type'   => 'doctor',
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
					'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'specialty',
							'field'    => 'term_id',
							'terms'    => (int) $term->term_id,
						),
					),
				)
			)
		);
		$links .= '<a class="hj-sp-link" href="' . esc_url( (string) get_term_link( $term ) ) . '">'
			. esc_html( $term->name )
			. ' <span class="hj-sp-count">(' . esc_html( number_format_i18n( $count ) ) . ' پزشک)</span></a>';
	}
	return $content
		. '<aside class="hj-sp-box" aria-label="پزشکان و جراحان این رشته">'
		. '<strong>پزشکان و جراحان این رشته در همیار جراحی:</strong> '
		. $links
		. '<span class="hj-sp-note">پروفایل‌ها با آرای ستاره‌ای کاربران نمایش داده می‌شوند.</span>'
		. '</aside>';
}
add_filter( 'the_content', 'hj_doctor_article_crosslinks', 30 );

/* -------------------------------------------------------------------------
 * 3. Legacy removal: symptom checker + robot pages (draft, never delete)
 * ---------------------------------------------------------------------- */

/**
 * Draft legacy pages and unlink them from menus (once, reversible).
 *
 * @return void
 */
function hj_doctor_directory_cleanup_legacy(): void {
	if ( get_option( 'hj_doctor_dir_cleanup_v1' ) ) {
		return;
	}
	$pages = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => array( 'publish', 'draft', 'pending' ),
			'numberposts' => -1,
		)
	);
	$drafted = array();
	foreach ( (array) $pages as $page ) {
		$tpl  = (string) get_page_template_slug( $page );
		$slug = (string) $page->post_name;
		$hit  = ( 'symptom-checker-test.php' === $tpl )
			|| false !== strpos( $slug, 'symptom' )
			|| false !== strpos( $slug, 'robot' )
			|| false !== strpos( $slug, 'lax-robot' );
		if ( $hit && 'draft' !== $page->post_status ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'draft' ) );
		}
		if ( $hit ) {
			$drafted[] = (int) $page->ID;
		}
	}
	foreach ( $drafted as $page_id ) {
		foreach ( (array) wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( (int) $menu->term_id ) as $item ) {
				if ( 'page' === $item->object && (int) $item->object_id === $page_id ) {
					wp_delete_post( (int) $item->ID, true );
				}
			}
		}
	}
	update_option( 'hj_doctor_dir_cleanup_v1', 1 );
}

/* -------------------------------------------------------------------------
 * 4. Demo surgeons (fictional) + seed votes + removal
 * ---------------------------------------------------------------------- */

/**
 * Seed three fictional demo surgeons with seed votes (once).
 *
 * @return void
 */
function hj_doctor_seed_demo(): void {
	if ( get_option( 'hj_doctor_demo_v1' ) ) {
		return;
	}
	update_option( 'hj_doctor_demo_v1', 1 ); // run once, whatever happens.
	$already = get_posts(
		array(
			'post_type'   => 'doctor',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_hj_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
	if ( $already ) {
		return;
	}

	$specs = array(
		'orthopedics'    => 'جراحی ارتوپدی',
		'urology'        => 'جراحی ارولوژی',
		'general-surgery' => 'جراحی عمومی',
	);
	$term_ids = array();
	foreach ( $specs as $slug => $label ) {
		$existing = term_exists( $slug, 'specialty' );
		if ( $existing && ! is_wp_error( $existing ) ) {
			$term_ids[ $slug ] = (int) $existing['term_id'];
		} else {
			$created = wp_insert_term( $label, 'specialty', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $created ) ) {
				$term_ids[ $slug ] = (int) $created['term_id'];
			}
		}
	}

	$admin_id = (int) get_option( 'admin_user_fallback', 0 );
	if ( ! $admin_id ) {
		$admin    = get_user_by( 'email', (string) get_option( 'admin_email' ) );
		$admin_id = $admin ? (int) $admin->ID : 1;
	}

	$demos = array(
		array(
			'title'    => 'دکتر بردیا شکوهی',
			'slug'     => 'dr-demo-orthopedics',
			'term'     => 'orthopedics',
			'degree'   => 'فلوشیپ جراحی ارتوپدی و ترمیم مفاصل',
			'code'     => '148257',
			'location' => 'دورود، خیابان انقلاب، کلینیک سینا',
			'bio'      => 'پروفایل نمونه (غیرواقعی) برای بررسی سامانه: جراحی زانو و لگن، آسیب‌های ورزشی و شکستگی‌ها با رویکرد کم‌تهاجمی.',
		),
		array(
			'title'    => 'دکتر آذین مهرپویا',
			'slug'     => 'dr-demo-urology',
			'term'     => 'urology',
			'degree'   => 'دانشیار جراحی ارولوژی',
			'code'     => '259314',
			'location' => 'خرم‌آباد، بلوار جمهوری، بیمارستان شهدا',
			'bio'      => 'پروفایل نمونه (غیرواقعی) برای بررسی سامانه: جراحی سنگ کلیه و مجاری ادراری، پروستات و آندورولوژی.',
		),
		array(
			'title'    => 'دکتر سامان نیکنام',
			'slug'     => 'dr-demo-general',
			'term'     => 'general-surgery',
			'degree'   => 'متخصص جراحی عمومی',
			'code'     => '361428',
			'location' => 'دورود، میدان شهدا، مرکز جراحی پارس',
			'bio'      => 'پروفایل نمونه (غیرواقعی) برای بررسی سامانه: جراحی لاپاراسکوپیک کیسه صفرا، فتق و آپاندیس.',
		),
	);

	global $wpdb;
	$table = function_exists( 'hj_doctor_votes_table' ) ? hj_doctor_votes_table() : $wpdb->prefix . 'hj_doctor_votes';
	$keys  = function_exists( 'hj_doctor_meta_keys' ) ? hj_doctor_meta_keys() : array();
	$now   = current_time( 'mysql', true );

	foreach ( $demos as $demo ) {
		$id = wp_insert_post(
			array(
				'post_type'   => 'doctor',
				'post_status' => 'publish',
				'post_title'  => $demo['title'],
				'post_name'   => $demo['slug'],
				'post_author' => $admin_id,
				'post_content' => '',
			),
			true
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_hj_demo', 1 );
		if ( $keys ) {
			update_post_meta( $id, $keys['degree'], $demo['degree'] );
			update_post_meta( $id, $keys['code'], $demo['code'] );
			update_post_meta( $id, $keys['location'], $demo['location'] );
			update_post_meta( $id, $keys['bio'], $demo['bio'] );
		}
		if ( isset( $term_ids[ $demo['term'] ] ) ) {
			wp_set_object_terms( $id, array( $term_ids[ $demo['term'] ] ), 'specialty' );
		}
		$n    = wp_rand( 22, 61 );
		$five = (int) round( $n * 0.8 );
		for ( $i = 0; $i < $n; $i++ ) {
			$rating = $i < $five ? 5 : ( wp_rand( 0, 1 ) ? 3 : 2 );
			$when   = time() - wp_rand( 0, 180 * DAY_IN_SECONDS );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'post_id'    => $id,
					'rating'     => $rating,
					'status'     => 'approved',
					'source'     => 'seed',
					'voted_at'   => gmdate( 'Y-m-d H:i:s', $when ),
					'created_at' => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}
}

/**
 * Remove the demo surgeons and their votes (admin action).
 *
 * @return void
 */
function hj_doctor_demo_remove(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازهٔ دسترسی ندارید.' );
	}
	check_admin_referer( 'hj_doctor_demo_remove' );
	global $wpdb;
	$table = function_exists( 'hj_doctor_votes_table' ) ? hj_doctor_votes_table() : $wpdb->prefix . 'hj_doctor_votes';
	$demos = get_posts(
		array(
			'post_type'   => 'doctor',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_hj_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
	foreach ( (array) $demos as $id ) {
		$wpdb->delete( $table, array( 'post_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $id, true );
	}
	delete_option( 'hj_doctor_demo_v1' );
	$back = wp_get_referer() ? wp_get_referer() : admin_url();
	wp_safe_redirect( add_query_arg( 'hj-notice', rawurlencode( 'demo-removed' ), $back ) );
	exit;
}
add_action( 'admin_post_hj_doctor_demo_remove', 'hj_doctor_demo_remove' );

/**
 * Run all one-time setup steps (front and admin).
 *
 * @return void
 */
function hj_doctor_directory_boot(): void {
	hj_doctor_directory_setup_page();
	hj_doctor_directory_setup_menu();
	hj_doctor_directory_cleanup_legacy();
	hj_doctor_seed_demo();
}
add_action( 'init', 'hj_doctor_directory_boot', 30 );

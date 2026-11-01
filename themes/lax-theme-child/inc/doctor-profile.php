<?php
/**
 * Doctor profiles: editable profile cards, front-end editing by the doctor
 * (or the admin), and embeddable widgets for advertising on other sites.
 *
 * Owner decisions 2026-11-01 — docs/doctor-ratings/profile-rollout.fa.md
 * and docs/doctor-ratings/profile-widgets.fa.md:
 *
 *  - profile fields (degree, medical-council code, location, short bio) live
 *    in post meta and are rendered automatically as a «technology» style card;
 *  - the doctor can create/edit their own profile from the front end
 *    (page template `page-doctor-dashboard.php`); admin-created profiles are
 *    published directly, doctor-created ones go to `pending` for approval;
 *  - the site admin keeps FULL rights over every profile and account
 *    (manage_options bypass here; account deletion stays in wp-admin → Users);
 *  - every profile exposes a ready-made embed URL — `?embed=strip` and
 *    `?embed=full` — that renders a standalone widget, safe to iframe into
 *    other websites (e.g. a city portal listing its local surgeons);
 *  - stars shown in widgets come from Site Reviews when active (guarded);
 *    the average appears only from 5 votes (same threshold as the page).
 *
 * Votes themselves (count, rating value, dates) are managed by the admin in
 * wp-admin → Site Reviews → Reviews; this file never edits reviews.
 *
 * @package Lax_Theme_Child
 * @since   0.27.0
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * 1. Post type + specialty taxonomy (code-registered — CPT UI optional)
 * ---------------------------------------------------------------------- */

/**
 * Register the `doctor` post type and the `specialty` taxonomy.
 *
 * Runs early on `init`; if the CPT UI plugin also defines `doctor`, its
 * later registration simply overrides with equivalent settings.
 *
 * @return void
 */
function hj_doctor_register_types(): void {
	register_post_type(
		'doctor',
		array(
			'labels'            => array(
				'name'          => 'پزشکان',
				'singular_name' => 'پزشک',
				'add_new_item'  => 'افزودن پروفایل پزشک',
				'edit_item'     => 'ویرایش پروفایل پزشک',
				'menu_name'     => 'پزشکان',
			),
			'public'            => true,
			'show_in_rest'      => true,
			'menu_icon'         => 'dashicons-id-alt',
			'supports'          => array( 'title', 'editor', 'thumbnail' ),
			'has_archive'       => true,
			'rewrite'           => array( 'slug' => 'doctor' ),
			'capability_type'   => 'post',
			'map_meta_cap'      => true,
		)
	);

	register_taxonomy(
		'specialty',
		'doctor',
		array(
			'labels'       => array(
				'name'          => 'رشته‌ها',
				'singular_name' => 'رشته',
				'menu_name'     => 'رشته‌ها',
			),
			'hierarchical' => true,
			'public'       => true,
			'show_in_rest' => true,
			'rewrite'      => array( 'slug' => 'specialty' ),
		)
	);
}
add_action( 'init', 'hj_doctor_register_types', 5 );

/* -------------------------------------------------------------------------
 * 2. Profile fields (post meta)
 * ---------------------------------------------------------------------- */

/**
 * Map of profile field keys to meta keys.
 *
 * @return array<string,string>
 */
function hj_doctor_meta_keys(): array {
	return array(
		'degree'   => '_hj_doctor_degree',
		'code'     => '_hj_doctor_code',
		'location' => '_hj_doctor_location',
		'bio'      => '_hj_doctor_bio',
	);
}

/**
 * Human labels for the profile fields (used by the dashboard form).
 *
 * @return array<string,string>
 */
function hj_doctor_field_labels(): array {
	return array(
		'degree'   => 'مدرک تحصیلی',
		'code'     => 'کد نظام پزشکی',
		'location' => 'محل طبابت',
		'bio'      => 'خلاصهٔ کوتاه سوابق',
	);
}

/**
 * Can the given user edit this doctor profile?
 *
 * Admin (manage_options) → full rights over every profile. Doctor → only
 * their own profile (post_author), matching the owner's rule «حق کامل و
 * حذف اکانت توسط مدیر وبسایت».
 *
 * @param int $post_id Post ID.
 * @param int $user_id User ID (0 = current user).
 * @return bool
 */
function hj_doctor_user_can_edit( int $post_id, int $user_id = 0 ): bool {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	$post = get_post( $post_id );
	return $post
		&& 'doctor' === $post->post_type
		&& (int) $post->post_author === $user_id
		&& user_can( $user_id, 'edit_posts' );
}

/**
 * URL of the front-end dashboard page (the page using our template).
 *
 * @return string
 */
function hj_doctor_dashboard_url(): string {
	$cached = get_transient( 'hj_doctor_dashboard_url' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$pages = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'page-doctor-dashboard.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$url = $pages ? (string) get_permalink( (int) $pages[0] ) : home_url( '/' );
	set_transient( 'hj_doctor_dashboard_url', $url, DAY_IN_SECONDS );
	return $url;
}

/* -------------------------------------------------------------------------
 * 3. Front-end save handler (create + edit)
 * ---------------------------------------------------------------------- */

/**
 * Handle the dashboard form (create or edit a profile).
 *
 * Security: nonce + logged-in + per-post permission; admin-created profiles
 * publish immediately, doctor-created ones become `pending` for approval.
 *
 * @return void
 */
function hj_doctor_handle_save(): void {
	if ( ! isset( $_POST['hj_doctor_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['hj_doctor_nonce'] ) ), 'hj_doctor_save' ) ) {
		wp_die( 'نشست شما منقضی شده است؛ لطفاً دوباره تلاش کنید.' );
	}
	if ( ! is_user_logged_in() ) {
		wp_die( 'برای ویرایش پروفایل باید وارد حساب شوید.' );
	}

	$user_id  = get_current_user_id();
	$is_admin = user_can( $user_id, 'manage_options' );

	$title    = isset( $_POST['hj_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_name'] ) ) : '';
	$degree   = isset( $_POST['hj_degree'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_degree'] ) ) : '';
	$code     = isset( $_POST['hj_code'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_code'] ) ) : '';
	$location = isset( $_POST['hj_location'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_location'] ) ) : '';
	$bio      = isset( $_POST['hj_bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hj_bio'] ) ) : '';
	$term_id  = isset( $_POST['hj_specialty'] ) ? absint( $_POST['hj_specialty'] ) : 0;
	$post_id  = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;

	$back = hj_doctor_dashboard_url();
	if ( '' === $title ) {
		wp_safe_redirect( add_query_arg( 'hj-status', 'missing-name', $back ) );
		exit;
	}

	if ( $term_id && ! term_exists( $term_id, 'specialty' ) ) {
		$term_id = 0;
	}

	if ( $post_id > 0 ) {
		// Edit — permission per profile.
		if ( ! hj_doctor_user_can_edit( $post_id, $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'hj-status', 'denied', $back ) );
			exit;
		}
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);
		$status = 'saved';
	} else {
		// Create — doctors may create their own profile; admin approval gate.
		if ( ! user_can( $user_id, 'edit_posts' ) ) {
			wp_safe_redirect( add_query_arg( 'hj-status', 'denied', $back ) );
			exit;
		}
		$post_id = (int) wp_insert_post(
			array(
				'post_type'   => 'doctor',
				'post_title'  => $title,
				'post_status' => $is_admin ? 'publish' : 'pending',
				'post_author' => $user_id,
			),
			true
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'hj-status', 'error', $back ) );
			exit;
		}
		$status = $is_admin ? 'created' : 'pending';
	}

	$keys = hj_doctor_meta_keys();
	update_post_meta( $post_id, $keys['degree'], $degree );
	update_post_meta( $post_id, $keys['code'], $code );
	update_post_meta( $post_id, $keys['location'], $location );
	update_post_meta( $post_id, $keys['bio'], $bio );
	if ( $term_id ) {
		wp_set_object_terms( $post_id, array( $term_id ), 'specialty' );
	}

	wp_safe_redirect( add_query_arg( array( 'hj-status' => $status, 'doctor' => $post_id ), $back ) );
	exit;
}
add_action( 'admin_post_hj_save_doctor_profile', 'hj_doctor_handle_save' );

/**
 * Not logged in → bounce to the login screen, then back to the dashboard.
 *
 * @return void
 */
function hj_doctor_handle_save_nopriv(): void {
	wp_safe_redirect( wp_login_url( hj_doctor_dashboard_url() ) );
	exit;
}
add_action( 'admin_post_nopriv_hj_save_doctor_profile', 'hj_doctor_handle_save_nopriv' );

/* -------------------------------------------------------------------------
 * 4. Widget data + markup (full card and strip widget)
 * ---------------------------------------------------------------------- */

/**
 * Gather everything a widget needs for one profile.
 *
 * @param int $post_id Doctor post ID.
 * @return array<string,mixed>
 */
function hj_doctor_widget_data( int $post_id ): array {
	$keys   = hj_doctor_meta_keys();
	$terms  = get_the_terms( $post_id, 'specialty' );
	$term   = ( is_array( $terms ) && $terms ) ? $terms[0] : null;
	$rating = function_exists( 'glsr_get_rating' ) ? glsr_get_rating( $post_id ) : null;
	$count  = ( is_object( $rating ) && isset( $rating->count ) ) ? (int) $rating->count : 0;
	$avg    = ( is_object( $rating ) && isset( $rating->average ) ) ? (float) $rating->average : 0.0;

	return array(
		'id'       => $post_id,
		'name'     => (string) get_the_title( $post_id ),
		'url'      => (string) get_permalink( $post_id ),
		'photo'    => (string) ( get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '' ),
		'degree'   => (string) get_post_meta( $post_id, $keys['degree'], true ),
		'code'     => (string) get_post_meta( $post_id, $keys['code'], true ),
		'location' => (string) get_post_meta( $post_id, $keys['location'], true ),
		'bio'      => (string) get_post_meta( $post_id, $keys['bio'], true ),
		'sp_slug'  => $term ? (string) $term->slug : '',
		'sp_name'  => $term ? (string) $term->name : '',
		'votes'    => $count,
		'average'  => $avg,
	);
}

/**
 * Render the star row (background stars + accent-colored fill by width).
 *
 * @param array<string,mixed> $d Widget data from hj_doctor_widget_data().
 * @param int                 $min_votes Minimum votes before the average shows.
 * @return string
 */
function hj_doctor_widget_stars( array $d, int $min_votes = 5 ): string {
	if ( $d['votes'] < $min_votes ) {
		return '<span class="hjw-novotes">امتیاز پس از ' . esc_html( number_format_i18n( $min_votes ) ) . ' رأی نمایش داده می‌شود</span>';
	}
	$pct   = max( 0, min( 100, (int) round( $d['average'] / 5 * 100 ) ) );
	$label = 'میانگین ' . number_format_i18n( $d['average'], 1 ) . ' از ' . number_format_i18n( $d['votes'] ) . ' رأی';
	return '<span class="hjw-stars" role="img" aria-label="' . esc_attr( $label ) . '">'
		. '<span class="hjw-stars-bg" aria-hidden="true">★★★★★</span>'
		. '<span class="hjw-stars-fill" aria-hidden="true" style="width:' . (int) $pct . '%">★★★★★</span>'
		. '</span>'
		. '<span class="hjw-count">' . esc_html( $label ) . '</span>';
}

/**
 * Build widget markup.
 *
 * @param int    $post_id Doctor post ID.
 * @param string $style   'full' (large card with details) or 'strip' (compact bar).
 * @return string
 */
function hj_doctor_widget( int $post_id, string $style = 'full' ): string {
	$d       = hj_doctor_widget_data( $post_id );
	$sp      = $d['sp_slug'] ? ' sp-' . sanitize_html_class( $d['sp_slug'] ) : '';
	$initial = function_exists( 'mb_substr' ) ? mb_substr( $d['name'], 0, 1, 'UTF-8' ) : substr( $d['name'], 0, 1 );
	$photo   = $d['photo']
		? '<img class="hjw-photo" src="' . esc_url( $d['photo'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy">'
		: '<span class="hjw-photo hjw-photo-initial" aria-hidden="true">' . esc_html( $initial ) . '</span>';
	$stars   = hj_doctor_widget_stars( $d );
	$brand   = '<a class="hjw-brand" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">همیار جراحی</a>';

	if ( 'strip' === $style ) {
		return '<div class="hjw hjw-strip' . $sp . '">'
			. '<div class="hjw-strip-main">'
			. '<span class="hjw-name">' . esc_html( $d['name'] ) . '</span>'
			. ( $d['code'] ? '<span class="hjw-code">نظام پزشکی ' . esc_html( $d['code'] ) . '</span>' : '' )
			. '<span class="hjw-starsrow">' . $stars . '</span>'
			. '</div>'
			. '<div class="hjw-strip-side">'
			. ( $d['sp_name'] ? '<span class="hjw-chip">' . esc_html( $d['sp_name'] ) . '</span>' : '' )
			. '<a class="hjw-link" href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener">پروفایل</a>'
			. $brand
			. '</div>'
			. '</div>';
	}

	$meta_rows = '';
	foreach ( array( 'degree', 'code', 'location' ) as $field ) {
		if ( '' !== $d[ $field ] ) {
			$labels     = hj_doctor_field_labels();
			$meta_rows .= '<div class="hjw-meta-item"><span class="hjw-meta-label">' . esc_html( $labels[ $field ] ) . '</span>'
				. '<span class="hjw-meta-value' . ( 'code' === $field ? ' hjw-mono' : '' ) . '">' . esc_html( $d[ $field ] ) . '</span></div>';
		}
	}

	return '<div class="hjw hjw-full' . $sp . '">'
		. '<div class="hjw-glow" aria-hidden="true"></div>'
		. '<div class="hjw-head">'
		. $photo
		. '<div class="hjw-head-text">'
		. '<span class="hjw-name">' . esc_html( $d['name'] ) . '</span>'
		. ( $d['sp_name'] ? '<span class="hjw-chip">' . esc_html( $d['sp_name'] ) . '</span>' : '' )
		. '<span class="hjw-starsrow">' . $stars . '</span>'
		. '</div>'
		. '</div>'
		. ( $meta_rows ? '<div class="hjw-meta">' . $meta_rows . '</div>' : '' )
		. ( $d['bio'] ? '<p class="hjw-bio">' . esc_html( $d['bio'] ) . '</p>' : '' )
		. '<div class="hjw-foot">'
		. '<a class="hjw-link" href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener">مشاهدهٔ پروفایل کامل و ثبت رأی</a>'
		. $brand
		. '</div>'
		. '</div>';
}

/* -------------------------------------------------------------------------
 * 5. Standalone embed endpoint (?embed=strip|full) for other websites
 * ---------------------------------------------------------------------- */

/**
 * Absolute URL of the widget stylesheet (also linked from the embed page).
 *
 * @return string
 */
function hj_doctor_widgets_css_url(): string {
	return get_stylesheet_directory_uri() . '/assets/css/doctor-widgets.css?ver=' . rawurlencode( (string) LAX_THEME_CHILD_VERSION );
}

/**
 * Render the bare widget document and stop — used inside iframes on other
 * sites (city portals, the doctor's own website, …).
 *
 * @return void
 */
function hj_doctor_embed_render(): void {
	if ( ! is_singular( 'doctor' ) || ! isset( $_GET['embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only view.
		return;
	}
	$style = sanitize_key( wp_unslash( $_GET['embed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $style, array( 'strip', 'full' ), true ) ) {
		return;
	}
	$post_id = (int) get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	// Never inside a frame-busting context: allow iframing by any host site.
	header( 'Content-Type: text/html; charset=utf-8' );
	header_remove( 'X-Frame-Options' );

	$name = (string) get_the_title( $post_id );
	echo '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<meta name="robots" content="noindex,follow">'
		. '<title>' . esc_html( $name ) . ' — همیار جراحی</title>'
		. '<link rel="stylesheet" href="' . esc_url( hj_doctor_widgets_css_url() ) . '">'
		. '</head><body class="hjw-body hjw-embed hjw-embed-' . esc_attr( $style ) . '">'
		. hj_doctor_widget( $post_id, $style ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped inside the builder.
		. '</body></html>';
	exit;
}
add_action( 'template_redirect', 'hj_doctor_embed_render' );

/**
 * Ready-made embed snippet for one profile/style.
 *
 * @param int    $post_id Doctor post ID.
 * @param string $style   'strip' or 'full'.
 * @return string
 */
function hj_doctor_embed_code( int $post_id, string $style = 'strip' ): string {
	$w = 'strip' === $style ? 340 : 520;
	$h = 'strip' === $style ? 132 : 470;
	return '<iframe src="' . esc_url( add_query_arg( 'embed', $style, (string) get_permalink( $post_id ) ) ) . '"'
		. ' width="' . (int) $w . '" height="' . (int) $h . '" loading="lazy" style="border:0;border-radius:14px"'
		. ' title="پروفایل ' . esc_attr( (string) get_the_title( $post_id ) ) . ' — همیار جراحی"></iframe>';
}

/* -------------------------------------------------------------------------
 * 6. Assets
 * ---------------------------------------------------------------------- */

/**
 * Enqueue the widget stylesheet where it is used.
 *
 * @return void
 */
function hj_doctor_widgets_assets(): void {
	if ( is_singular( 'doctor' ) || ( is_page() && 'page-doctor-dashboard.php' === get_page_template_slug() ) ) {
		wp_enqueue_style( 'hj-doctor-widgets', hj_doctor_widgets_css_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
	}
}
add_action( 'wp_enqueue_scripts', 'hj_doctor_widgets_assets', 25 );

<?php
/**
 * بخش ۴ (فایل ۲): اجرای دسترسی نقش «پزشک» — فقط نمایهٔ خودش.
 *
 * چهار لایهٔ بی‌صدا (بدون هیچ خروجی در debug.log):
 * ۱. نگهبان map_meta_cap: ویرایش/حذف نوشتهٔ پزشکِ دیگر برای نقش پزشک ممنوع است.
 * ۲. محدودکردن پیشخوان: نقش پزشک فقط صفحهٔ نمایهٔ خودش و پروندهٔ شخصی را می‌بیند.
 * ۳. نوار مدیریت: گره‌های بی‌ربط حذف و میان‌بر «ویرایش نمایهٔ پزشک» اضافه می‌شود.
 * ۴. ذخیرهٔ ACF: سانیتایز بخش ۳ فقط وقتی اجرا می‌شود که cap ویرایش هست
 *    (همان‌جا بررسی می‌شود) و اینجا میان‌بر یافتن نوشتهٔ پزشک فراهم است.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا کاربر دقیقاً نقش پزشک دارد (و مدیر سایت نیست)؟
 *
 * @param WP_User|int|null $user کاربر یا شناسه‌اش؛ null یعنی کاربر جاری.
 * @return bool
 */
function hj_doctors_user_is_doctor( $user = null ) {
	if ( $user instanceof WP_User ) {
		$user_obj = $user;
	} else {
		$user_obj = $user ? get_user_by( 'id', (int) $user ) : wp_get_current_user();
	}
	if ( ! $user_obj || ! $user_obj->exists() ) {
		return false;
	}
	if ( in_array( HJ_DOCTORS_ROLE, (array) $user_obj->roles, true ) ) {
		return ! user_can( $user_obj, 'manage_options' );
	}

	return false;
}

/**
 * نگهبان متacap: نقش پزشک هرگز نوشتهٔ پزشکِ دیگر را ویرایش/حذف نمی‌کند.
 *
 * هستهٔ وردپرس خودش با edit_others_doctors این را می‌بندد؛ این لایه برای
 * حالتی است که روزی cap اشتباهی به نقشی داده شود.
 *
 * @param string[] $caps    capabilityهای نگاشته‌شده تا اینجا.
 * @param string   $cap     cap درخواستی.
 * @param int      $user_id شناسهٔ کاربر.
 * @param array    $args    آرگومان‌ها؛ اولی شناسهٔ نوشته است.
 * @return string[]
 */
function hj_doctors_guard_other_profiles( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'edit_doctor', 'delete_doctor' ), true ) ) {
		return $caps;
	}
	if ( ! hj_doctors_user_is_doctor( $user_id ) ) {
		return $caps;
	}

	$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
	$post    = get_post( $post_id );
	if ( ! $post || HJ_DOCTORS_CPT !== $post->post_type ) {
		return $caps;
	}
	if ( (int) $post->post_author !== (int) $user_id ) {
		return array( 'do_not_allow' );
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'hj_doctors_guard_other_profiles', 10, 4 );

/**
 * محدودکردن پیشخوان برای نقش پزشک به چند صفحهٔ لازم.
 *
 * فهرست پزشک‌ها خودش توسط هسته به نوشته‌های خود کاربر محدود می‌شود
 * (چون edit_others_doctors ندارد)، پس post.php فقط نمایهٔ خودش است.
 */
function hj_doctors_restrict_admin_pages() {
	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}
	if ( ! hj_doctors_user_is_doctor() ) {
		return;
	}

	$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
	$allowed = array(
		'profile.php',      // نمایهٔ کاربری خودش.
		'post.php',         // ویرایش نوشتهٔ پزشک خودش (cap جدا بررسی می‌شود).
		'upload.php',       // کتابخانهٔ رسانه برای تصویر نمایه.
		'media-new.php',
		'async-upload.php',
		'admin-ajax.php',
		'admin-post.php',
	);
	if ( in_array( $pagenow, $allowed, true ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'profile.php' ) );
	exit;
}
add_action( 'admin_init', 'hj_doctors_restrict_admin_pages', 20 );

/**
 * نوار مدیریت برای نقش پزشک: فقط میان‌بر نمایهٔ خودش.
 *
 * @param WP_Admin_Bar $wp_admin_bar نوار مدیریت.
 */
function hj_doctors_admin_bar_cleanup( $wp_admin_bar ) {
	if ( ! hj_doctors_user_is_doctor() ) {
		return;
	}

	foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'themes', 'plugins', 'users', 'tools', 'edit' ) as $node ) {
		$wp_admin_bar->remove_node( $node );
	}
}
add_action( 'admin_bar_menu', 'hj_doctors_admin_bar_cleanup', 100 );

/**
 * میان‌بر «ویرایش نمایهٔ پزشک» در نوار مدیریت.
 *
 * @param WP_Admin_Bar $wp_admin_bar نوار مدیریت.
 */
function hj_doctors_admin_bar_edit_profile( $wp_admin_bar ) {
	if ( ! hj_doctors_user_is_doctor() ) {
		return;
	}

	$post_id = hj_doctors_get_user_doctor_post();
	if ( ! $post_id || ! current_user_can( 'edit_doctor', $post_id ) ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'hj-edit-my-doctor-profile',
			'title' => 'ویرایش نمایهٔ پزشک',
			'href'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		)
	);
}
add_action( 'admin_bar_menu', 'hj_doctors_admin_bar_edit_profile', 110 );

if ( ! function_exists( 'hj_doctors_get_user_doctor_post' ) ) {
	/**
	 * شناسهٔ نوشتهٔ پزشکی که به کاربر جاری وصل است (نویسنده = کاربر).
	 *
	 * @param int $user_id شناسهٔ کاربر؛ ۰ یعنی کاربر جاری.
	 * @return int شناسهٔ نوشته یا ۰.
	 */
	function hj_doctors_get_user_doctor_post( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : (int) get_current_user_id();
		if ( ! $user_id ) {
			return 0;
		}

		$cache_key = 'hj_doctors_user_post_' . $user_id;
		$cached    = wp_cache_get( $cache_key, 'hamyar-doctors' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$posts = get_posts(
			array(
				'post_type'      => HJ_DOCTORS_CPT,
				'author'         => $user_id,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$id    = $posts ? (int) $posts[0] : 0;

		wp_cache_set( $cache_key, $id, 'hamyar-doctors', 5 * MINUTE_IN_SECONDS );

		return $id;
	}
}

/**
 * با تغییر نویسندهٔ یک نوشتهٔ پزشک، کش نگاشت کاربر→نوشته پاک می‌شود.
 *
 * @param int     $post_id شناسهٔ نوشته.
 * @param WP_Post $post    نوشته.
 */
function hj_doctors_clear_user_post_cache( $post_id, $post ) {
	if ( ! $post || HJ_DOCTORS_CPT !== $post->post_type ) {
		return;
	}
	wp_cache_delete( 'hj_doctors_user_post_' . (int) $post->post_author, 'hamyar-doctors' );
}
add_action( 'save_post_doctor', 'hj_doctors_clear_user_post_cache', 10, 2 );

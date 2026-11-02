<?php
/** Installation and editing of the block-based static homepage. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_theme_page( 'صفحه نخست ایستا', 'صفحه نخست ایستا', 'manage_options', 'hj-static-home', 'hj_static_home_admin' );
} );

function hj_static_home_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$id = (int) get_option( 'hj_static_home_page_id' );
	?>
	<div class="wrap" dir="rtl">
		<h1>صفحه نخست ایستا</h1>
		<p>برگهٔ جدید هنگام نخستین ورود مدیر پس از به‌روزرسانی ساخته و انتخاب می‌شود. صفحهٔ قبلی حذف نمی‌شود؛ برای بازگشت از تنظیمات ← خواندن استفاده کنید.</p>
		<?php $previous = get_option( 'hj_static_home_previous', array() ); ?>
		<?php if ( ! empty( $previous['page_on_front'] ) && 'page' === $previous['show_on_front'] ) : ?>
			<p>برگهٔ نخست قبلی: <a href="<?php echo esc_url( get_edit_post_link( (int) $previous['page_on_front'], 'raw' ) ); ?>">ویرایش برگه قبلی</a></p>
		<?php endif; ?>
		<p>متن‌ها، دکمه‌ها، پیوندها و تصویر را در ویرایشگر برگه تغییر دهید. سربرگ و فهرست‌ها از بخش نمایش مدیریت می‌شوند.</p>
		<p>ورود گوگل و ثبت‌نام جراحان به فعال بودن افزونه همیار پزشک‌ها و Nextend Social Login و پیکربندی Google OAuth نیاز دارند. پروفایل جراحان در برگهٔ افزونه مدیریت می‌شود.</p>
		<?php if ( $id && get_post_status( $id ) && 'trash' !== get_post_status( $id ) ) : ?>
			<p><a href="<?php echo esc_url( get_edit_post_link( $id, 'raw' ) ); ?>">ویرایش برگه ساخته‌شده</a></p>
			<p>با زدن دکمه، همین برگه بدون بازنویسی محتوا دوباره به عنوان صفحه نخست انتخاب می‌شود.</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hj_install_static_home">
			<?php wp_nonce_field( 'hj_install_static_home' ); ?>
			<?php submit_button( 'ساخت و انتخاب صفحه نخست جدید' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_hj_install_static_home', 'hj_install_static_home' );
function hj_install_static_home() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'دسترسی مجاز نیست.', 'lax-theme-child' ) ); }
	check_admin_referer( 'hj_install_static_home' );
	$id = (int) get_option( 'hj_static_home_page_id' );
	if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) && 'publish' !== get_post_status( $id ) ) {
		wp_die( esc_html__( 'برگه موجود منتشر نشده است؛ ابتدا آن را منتشر کنید.', 'lax-theme-child' ) );
	}
	if ( ! $id || 'page' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
		$id = hj_static_home_create_page();
		if ( is_wp_error( $id ) || ! $id ) { wp_die( esc_html__( 'ساخت برگه ناموفق بود.', 'lax-theme-child' ) ); }
		update_option( 'hj_static_home_page_id', $id );
	}

	update_option( 'page_on_front', $id );
	update_option( 'show_on_front', 'page' );
	wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
	exit;
}

/** Create a new editable page; never write over an existing page. */
function hj_static_home_create_page() {
	$file = get_stylesheet_directory() . '/homepage/starter.html';
	if ( ! is_readable( $file ) ) { return new WP_Error( 'hj_home_missing', 'Homepage starter not available.' ); }
	$starter = file_get_contents( $file );
	if ( false === $starter || false === strpos( $starter, 'hj-home-v0332' ) ) { return new WP_Error( 'hj_home_missing_version', 'Homepage starter is missing or out of date.' ); }
	$guides = get_post_type_archive_link( 'patient_guide' ) ?: home_url( '/patient-guide/' );
	$urls = array(
		'{{GUIDES}}' => $guides,
		'{{SURGERIES}}' => get_post_type_archive_link( 'surgery' ) ?: home_url( '/surgery/' ),
		'{{DOCTORS}}' => get_post_type_archive_link( 'doctor' ) ?: home_url( '/doctors/' ),
		'{{BEFORE}}' => hj_term_url( 'guide_stage', 'before', 'patient_guide' ),
		'{{DURING}}' => hj_term_url( 'guide_stage', 'during', 'patient_guide' ),
		'{{AFTER}}' => hj_term_url( 'guide_stage', 'after', 'patient_guide' ),
		'{{CARE}}' => hj_term_url( 'guide_stage', 'after', 'patient_guide' ),
		'{{OR}}' => get_post_type_archive_link( 'or_environment' ) ?: home_url( '/or-environment/' ),
		'{{DISEASES}}' => get_post_type_archive_link( 'disease' ) ?: home_url( '/diseases/' ),
		'{{GLOSSARY}}' => get_post_type_archive_link( 'glossary' ) ?: home_url( '/glossary/' ),
		'{{STUDENTS}}' => get_post_type_archive_link( 'student_content' ) ?: home_url( '/student/' ),
		'{{FAQ}}' => hj_page_url( 'faq' ),
		'{{CONTACT}}' => hj_page_url( 'contact' ),
		'{{SIGNUP}}' => add_query_arg( 'hj_google_start', '1', home_url( '/' ) ),
		'{{PROFILE}}' => function_exists( 'hj_doctors_dashboard_url' ) ? hj_doctors_dashboard_url() : home_url( '/my-profile/' ),
		'{{GOOGLE}}' => add_query_arg( 'loginSocial', 'google', site_url( 'wp-login.php' ) ),
		'{{IMAGE}}' => get_stylesheet_directory_uri() . '/assets/images/home-medical-tech.png',
	);
	$starter = hj_static_magazine_block() . strtr( $starter, array_map( 'esc_url', $urls ) );
	$slug = defined( 'HJ_SC_PAGE_SLUG' ) ? HJ_SC_PAGE_SLUG : 'symptom-checker';
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'name' => $slug, 'numberposts' => 1 ) );
	$button = '';
	if ( $pages ) {
		$button = '<!-- wp:button {"className":"hj-btn-secondary"} --><div class="wp-block-button hj-btn-secondary"><a class="wp-block-button__link wp-element-button" href="' . esc_url( get_permalink( $pages[0] ) ) . '">ارزیابی علائم ←</a></div><!-- /wp:button -->';
	}
	$starter = str_replace( '{{SYMPTOM_BUTTON}}', $button, $starter );
	return wp_insert_post( array(
		'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'صفحه نخست',
		'post_content' => wp_slash( $starter ), 'meta_input' => array( '_hj_static_home_revision' => '0.33.2', '_hj_magazine_section_added' => 1 ),
	), true );
}

/** One-time upgrade on an administrator request, preserving the previous page. */
add_action( 'admin_init', 'hj_static_home_upgrade_0332' );
function hj_static_home_upgrade_0332() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'hj_static_home_0332_done' ) || wp_doing_ajax() || wp_doing_cron() ) { return; }
	$lock = (int) get_option( 'hj_static_home_0332_lock', 0 );
	if ( $lock && $lock < time() - 300 ) { delete_option( 'hj_static_home_0332_lock' ); }
	if ( ! add_option( 'hj_static_home_0332_lock', time(), '', 'no' ) ) { return; }
	// Recover an already-created page if the previous request stopped before completion.
	$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'meta_key' => '_hj_static_home_revision', 'meta_value' => '0.33.2', 'numberposts' => 1, 'fields' => 'ids' ) );
	$id = $existing ? (int) $existing[0] : hj_static_home_create_page();
	if ( is_wp_error( $id ) || ! $id ) {
		update_option( 'hj_static_home_0332_error', '1', false );
		delete_option( 'hj_static_home_0332_lock' );
		return;
	}
	// Save the prior selection once so interrupted requests do not replace the backup.
	add_option( 'hj_static_home_previous', array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => (int) get_option( 'page_on_front' ) ), '', 'no' );
	update_option( 'hj_static_home_page_id', $id );
	update_option( 'page_on_front', $id );
	update_option( 'show_on_front', 'page' );
	update_option( 'hj_static_home_0332_done', '1', false );
	delete_option( 'hj_static_home_0332_error' );
	delete_option( 'hj_static_home_0332_lock' );
	if ( function_exists( 'orsite_gh_purge_caches' ) ) { orsite_gh_purge_caches(); }
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || ! get_option( 'hj_static_home_0332_error' ) ) { return; }
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ساخت صفحه نخست ناموفق بود. فایل‌های قالب و دسترسی نوشتن وردپرس را بررسی کنید؛ تنظیم صفحه نخست قبلی تغییر نکرده است.', 'lax-theme-child' ) . '</p></div>';
} );

function hj_static_home_styles() {
	wp_enqueue_style( 'hj-static-home', get_stylesheet_directory_uri() . '/assets/css/static-home.css', array(), lax_theme_child_asset_ver( 'assets/css/static-home.css' ) );
}
add_action( 'wp_enqueue_scripts', function () { if ( is_front_page() ) { hj_static_home_styles(); } }, 100 );
add_action( 'enqueue_block_editor_assets', 'hj_static_home_styles' );

/** Add only the new editable section to the selected front page, once. */
function hj_static_magazine_block() {
	return '<!-- wp:group {"className":"hj-magazine-section","layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group hj-magazine-section">' . "\n"
		. '<!-- wp:paragraph {"className":"hj-magazine-eyebrow"} --><p class="hj-magazine-eyebrow">نامه‌ای از همیار جراحی</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">اشتراک رایگان مجله اینترنتی همیارجراحی، تنها با یک جیمیل</h2><!-- /wp:heading -->' . "\n"
		. '<!-- wp:paragraph --><p>برای دریافت خبرهای مجله، ایمیل خود را وارد و اشتراک را تأیید کنید.</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:shortcode -->[hj_magazine_subscribe]<!-- /wp:shortcode -->' . "\n"
		. '<!-- wp:paragraph {"className":"hj-magazine-account"} --><p class="hj-magazine-account">حساب اعضا: [hj_member_account_link] &nbsp; | &nbsp; جراح هستید؟ از بخش ثبت‌نام جراحان وارد شوید.</p><!-- /wp:paragraph -->' . "\n"
		. '</div><!-- /wp:group -->' . "\n";
}
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || wp_doing_cron() || 'page' !== get_option( 'show_on_front' ) ) { return; }
	$id = (int) get_option( 'page_on_front' );
	$page = get_post( $id );
	if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || get_post_meta( $id, '_hj_magazine_section_added', true ) ) { return; }
	if ( has_shortcode( $page->post_content, 'hj_magazine_subscribe' ) ) { update_post_meta( $id, '_hj_magazine_section_added', 1 ); return; }
	$edit_lock = get_post_meta( $id, '_edit_lock', true );
	if ( $edit_lock && (int) $edit_lock > time() - 150 ) { return; }
	if ( function_exists( 'wp_check_post_lock' ) && wp_check_post_lock( $id ) ) { return; }
	// Do not replace the page, its existing blocks or its editorial history.
	$result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => hj_static_magazine_block() . $page->post_content ) ), true );
	if ( ! is_wp_error( $result ) && $result ) {
		update_post_meta( $id, '_hj_magazine_section_added', 1 );
		if ( function_exists( 'orsite_gh_purge_caches' ) ) { orsite_gh_purge_caches(); }
	}
}, 30 );

// Avoid displaying raw shortcodes if the independent plugin has not been installed.
add_action( 'init', function () {
	if ( ! shortcode_exists( 'hj_magazine_subscribe' ) ) { add_shortcode( 'hj_magazine_subscribe', function () { return '<p>فرم اشتراک در حال آماده‌سازی است.</p>'; } ); }
	if ( ! shortcode_exists( 'hj_member_account_link' ) ) { add_shortcode( 'hj_member_account_link', '__return_empty_string' ); }
}, 20 );

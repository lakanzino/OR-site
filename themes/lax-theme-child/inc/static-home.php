<?php
/** Opt-in installation of an editable, block-based static homepage. */
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
		<p>یک برگه از طرح جدید ساخته و به عنوان صفحه نخست تنظیم می‌شود. صفحه یا تنظیمات قبلی حذف نمی‌شوند؛ می‌توانید از تنظیمات ← خواندن به آن‌ها برگردید.</p>
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
		// Never alter an existing page: create a fresh one, even if a similarly named page exists.
		$starter = file_get_contents( get_stylesheet_directory() . '/homepage/starter.html' );
		if ( false === $starter ) { wp_die( esc_html__( 'فایل طرح صفحه نخست پیدا نشد.', 'lax-theme-child' ) ); }
		$urls = array(
			'{{GUIDES}}' => get_post_type_archive_link( 'patient_guide' ) ?: home_url( '/patient-guide/' ),
			'{{SURGERIES}}' => get_post_type_archive_link( 'surgery' ) ?: home_url( '/surgery/' ),
			'{{DOCTORS}}' => get_post_type_archive_link( 'doctor' ) ?: home_url( '/doctors/' ),
			'{{SIGNUP}}' => add_query_arg( 'hj_google_start', '1', home_url( '/' ) ),
			'{{PROFILE}}' => function_exists( 'hj_doctors_dashboard_url' ) ? hj_doctors_dashboard_url() : home_url( '/my-profile/' ),
			'{{GOOGLE}}' => add_query_arg( 'loginSocial', 'google', site_url( 'wp-login.php' ) ),
			'{{IMAGE}}' => get_stylesheet_directory_uri() . '/assets/images/home-medical-tech.png',
		);
		$starter = strtr( $starter, array_map( 'esc_url', $urls ) );
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'صفحه نخست', 'post_content' => wp_slash( $starter ) ), true );
		if ( is_wp_error( $id ) || ! $id ) { wp_die( esc_html__( 'ساخت برگه ناموفق بود.', 'lax-theme-child' ) ); }
		update_option( 'hj_static_home_page_id', $id );
	}
	update_option( 'page_on_front', $id );
	update_option( 'show_on_front', 'page' );
	wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
	exit;
}

function hj_static_home_styles() {
	wp_enqueue_style( 'hj-static-home', get_stylesheet_directory_uri() . '/assets/css/static-home.css', array(), lax_theme_child_asset_ver( 'assets/css/static-home.css' ) );
}
add_action( 'wp_enqueue_scripts', function () { if ( is_front_page() ) { hj_static_home_styles(); } }, 100 );
add_action( 'enqueue_block_editor_assets', 'hj_static_home_styles' );

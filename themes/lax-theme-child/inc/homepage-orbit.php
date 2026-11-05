<?php
/** Limited, manager-initiated edit of the recognized existing homepage. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hj_orbit_plain( $html ) {
	$html = preg_replace( '/<br\s*\/?\s*>/i', ' ', $html );
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}
function hj_orbit_edit_hero( &$blocks, &$counts ) {
	foreach ( $blocks as &$b ) {
		$class = isset( $b['attrs']['className'] ) ? (string) $b['attrs']['className'] : '';
		$classes = preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY );
		$html = $b['innerHTML'];
		if ( $b['blockName'] === 'core/heading' && empty( $b['innerBlocks'] ) && preg_match( '/^\s*<h1(?:\s[^>]*)?>.*<\/h1>\s*$/s', $html ) ) {
			++$counts['headings'];
			$text = hj_orbit_plain( $html );
			if ( ! in_array( $text, array( 'آگاهی بیشتر، آرامش در مسیر جراحی.', 'جراحی در پیش دارید؟ ما همیار شما هستیم.' ), true ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) { continue; }
			$processor = new WP_HTML_Tag_Processor( $html );
			if ( ! $processor->next_tag( 'h1' ) ) { continue; }
			$processor->add_class( 'hj-hero-title' );
			$inner = '<span class="hj-hero-slogan">آرامش در مسیر <span class="hj-hero-surgery">جراحی</span> با <span class="hj-hero-brand">همیار</span><span class="hj-hero-surgery">جراحی</span></span>';
			$updated = preg_replace_callback( '/(<h1(?:\s[^>]*)?>).*?(<\/h1>)/s', function ( $m ) use ( $inner ) { return $m[1] . $inner . $m[2]; }, $processor->get_updated_html(), 1, $n );
			if ( $n === 1 ) {
				if ( ! in_array( 'hj-hero-title', $classes, true ) ) { $classes[] = 'hj-hero-title'; }
				$b['attrs']['className'] = implode( ' ', $classes ); $b['innerHTML'] = $updated; $b['innerContent'] = array( $updated ); ++$counts['changed_heading'];
			}
		} elseif ( $b['blockName'] === 'core/paragraph' && in_array( 'hj-landing-lead', $classes, true ) && empty( $b['innerBlocks'] ) && preg_match( '/^\s*<p(?:\s[^>]*)?>.*<\/p>\s*$/s', $html ) ) {
			++$counts['leads']; $text = hj_orbit_plain( $html );
			if ( ! in_array( $text, array( 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و فضایی برای آشنایی با جراحان.', 'انتخابی که مسیر زندگی‌تان را تغییر می‌دهد؛ برترین جراحان هر شهر و استان، اینجا.' ), true ) ) { continue; }
			$inner = 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و آشنایی با جراحان <span class="hj-hero-emphasis">برتر</span>.';
			$updated = preg_replace_callback( '/(<p(?:\s[^>]*)?>).*?(<\/p>)/s', function ( $m ) use ( $inner ) { return $m[1] . $inner . $m[2]; }, $html, 1, $n );
			if ( $n === 1 ) { $b['innerHTML'] = $updated; $b['innerContent'] = array( $updated ); ++$counts['changed_lead']; }
		}
		if ( ! empty( $b['innerBlocks'] ) ) { hj_orbit_edit_hero( $b['innerBlocks'], $counts ); }
	}
	unset( $b );
}
function hj_orbit_walk( &$blocks, &$hero_count, &$counts ) {
	foreach ( $blocks as &$b ) {
		$class = isset( $b['attrs']['className'] ) ? (string) $b['attrs']['className'] : '';
		$classes = preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY );
		if ( $b['blockName'] === 'core/group' && in_array( 'hj-landing-hero-copy', $classes, true ) ) {
			++$hero_count; hj_orbit_edit_hero( $b['innerBlocks'], $counts );
		} elseif ( ! empty( $b['innerBlocks'] ) ) { hj_orbit_walk( $b['innerBlocks'], $hero_count, $counts ); }
		// Only known exact UI labels; never substitute within arbitrary prose or user data.
		$html = $b['innerHTML']; $replacement = null;
		if ( empty( $b['innerBlocks'] ) && $b['blockName'] === 'core/button' && in_array( 'hj-btn-secondary', $classes, true ) && strpos( $html, '>ورود به پنل پزشک</a>' ) !== false ) {
			$replacement = str_replace( '>ورود به پنل پزشک</a>', '>ورود به پنل جراح</a>', $html );
		} elseif ( empty( $b['innerBlocks'] ) && $b['blockName'] === 'core/paragraph' && in_array( 'hj-landing-eyebrow', $classes, true ) && hj_orbit_plain( $html ) === 'پزشکان و متخصصان' ) {
			$replacement = str_replace( 'پزشکان و متخصصان', 'جراحان و متخصصان', $html );
		} elseif ( empty( $b['innerBlocks'] ) && $b['blockName'] === 'core/paragraph' && in_array( 'hj-landing-note', $classes, true ) && strpos( $html, 'پزشک هستید؟' ) !== false ) {
			$replacement = str_replace( 'پزشک هستید؟', 'جراح هستید؟', $html );
		}
		if ( is_string( $replacement ) ) { $b['innerHTML'] = $replacement; $b['innerContent'] = array( $replacement ); }
	}
	unset( $b );
}
/** Returns an error, rather than changing unrecognized or edited hero content. */
function hj_orbit_build_content( $original ) {
	$blocks = parse_blocks( $original );
	if ( serialize_blocks( $blocks ) !== $original ) { return new WP_Error( 'hj_orbit_format', 'ساختار برگه دقیقاً قابل بازخوانی نیست؛ تغییری انجام نشد.' ); }
	$copy = $blocks; $hero_count = 0;
	$counts = array( 'headings' => 0, 'changed_heading' => 0, 'leads' => 0, 'changed_lead' => 0 );
	hj_orbit_walk( $copy, $hero_count, $counts );
	if ( $hero_count !== 1 || $counts !== array( 'headings' => 1, 'changed_heading' => 1, 'leads' => 1, 'changed_lead' => 1 ) ) {
		return new WP_Error( 'hj_orbit_hero', 'هیروی فعلی با الگوی شناخته‌شده یکسان نیست یا قبلاً ویرایش شده؛ تغییری انجام نشد.' );
	}
	return serialize_blocks( $copy );
}
function hj_orbit_locked( $id ) {
	if ( ! function_exists( 'wp_check_post_lock' ) ) { require_once ABSPATH . 'wp-admin/includes/post.php'; }
	$lock = get_post_meta( $id, '_edit_lock', true );
	return wp_check_post_lock( $id ) || ( $lock && (int) $lock > time() - 150 );
}
function hj_orbit_update_current() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'show_on_front' ) !== 'page' ) { return new WP_Error( 'hj_orbit_access', 'دسترسی یا صفحه نخست ایستا در دسترس نیست.' ); }
	$id = (int) get_option( 'page_on_front' ); $page = get_post( $id );
	if ( ! $page || $page->post_type !== 'page' || $page->post_status !== 'publish' || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'hj_orbit_page', 'برگه نخست منتشرشده یا دسترسی ویرایش آن پیدا نشد.' ); }
	if ( hj_orbit_locked( $id ) ) { return new WP_Error( 'hj_orbit_locked', 'برگه در حال ویرایش است؛ ویرایشگر را ببندید و پس از انقضای قفل دوباره تلاش کنید.' ); }
	$content = hj_orbit_build_content( $page->post_content );
	if ( is_wp_error( $content ) ) { return $content; }
	if ( ! wp_revisions_enabled( $page ) || wp_revisions_to_keep( $page ) === 1 ) { return new WP_Error( 'hj_orbit_revision', 'رونوشت‌های این برگه فعال نیستند؛ تغییر خودکار انجام نشد.' ); }
	global $wpdb;
	// Cooperating requests take an advisory lock. Other editors still use WP locks.
	$lock_name = 'hj_home_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $id );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) ) ) { return new WP_Error( 'hj_orbit_busy', 'درخواست دیگری مشغول است یا قفل پایگاه داده در دسترس نیست.' ); }
	try {
		$latest = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id ) );
		if ( $latest !== $page->post_content || get_option( 'show_on_front' ) !== 'page' || (int) get_option( 'page_on_front' ) !== $id || hj_orbit_locked( $id ) ) { return new WP_Error( 'hj_orbit_changed', 'برگه یا تنظیم صفحه نخست تغییر کرده است؛ عملیات لغو شد.' ); }
		// WordPress normally skips a revision when normalized whitespace matches
		// the previous one. Require an exact fresh snapshot for this operation.
		add_filter( 'wp_save_post_revision_check_for_changes', '__return_false', PHP_INT_MAX );
		try { $revision = wp_save_post_revision( $id ); }
		finally { remove_filter( 'wp_save_post_revision_check_for_changes', '__return_false', PHP_INT_MAX ); }
		if ( is_wp_error( $revision ) ) { return new WP_Error( 'hj_orbit_backup', 'ساخت رونوشت ناموفق بود؛ تغییری انجام نشد.' ); }
		$recent = wp_get_post_revisions( $id, array( 'posts_per_page' => 1, 'orderby' => 'date ID', 'order' => 'DESC' ) );
		$backup = $revision ? get_post( $revision ) : ( $recent ? reset( $recent ) : null );
		if ( ! $backup || $backup->post_content !== $page->post_content ) { return new WP_Error( 'hj_orbit_backup', 'رونوشت قابل بازگشت تأیید نشد؛ تغییری انجام نشد.' ); }
		$latest = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id ) );
		if ( $latest !== $page->post_content || get_option( 'show_on_front' ) !== 'page' || (int) get_option( 'page_on_front' ) !== $id || hj_orbit_locked( $id ) ) { return new WP_Error( 'hj_orbit_changed', 'برگه در زمان بررسی تغییر کرد؛ عملیات لغو شد.' ); }
		$result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ), true );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( function_exists( 'orsite_gh_purge_caches' ) ) { orsite_gh_purge_caches(); }
		return $id;
	} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); }
}
add_action( 'admin_menu', function () {
	add_theme_page( 'اصلاح صفحه نخست فعلی', 'اصلاح صفحه نخست فعلی', 'manage_options', 'hj-orbit-home', 'hj_orbit_admin' );
} );
function hj_orbit_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	?><div class="wrap" dir="rtl"><h1>اصلاح صفحه نخست فعلی</h1><p>با تأیید شما، فقط هیروی شناخته‌شده و چند برچسب ثابت روی برگه نخست فعلی ویرایش می‌شوند. برگه دیگری انتخاب نمی‌شود. پیش از تغییر، رونوشت و قفل بررسی می‌شود؛ در صورت ناشناخته بودن ساختار هیچ تغییری اعمال نمی‌شود.</p>
	<p>پیش از شروع، پشتیبان بگیرید و ویرایشگر برگه را ببندید. اگر ورود گوگل در خود محتوای برگه قرار داشته باشد، مکان آن باید در ویرایشگر بررسی شود.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="hj_orbit_apply"><?php wp_nonce_field( 'hj_orbit_apply' ); submit_button( 'اعمال به برگه نخست فعلی' ); ?></form></div><?php
}
add_action( 'admin_post_hj_orbit_apply', function () {
	if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) { wp_die( 'روش درخواست مجاز نیست.', '', array( 'response' => 405 ) ); }
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_orbit_apply' );
	$result = hj_orbit_update_current();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 409, 'back_link' => true ) ); }
	wp_safe_redirect( add_query_arg( 'hj_orbit_saved', '1', get_edit_post_link( $result, 'url' ) ) ); exit;
} );
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_front_page() ) { return; }
	$css = 'assets/css/homepage-orbit.css'; $js = 'assets/js/homepage-orbit.js';
	wp_enqueue_style( 'hj-homepage-orbit', get_stylesheet_directory_uri() . '/' . $css, array(), lax_theme_child_asset_ver( $css ) );
	wp_enqueue_script( 'hj-homepage-orbit', get_stylesheet_directory_uri() . '/' . $js, array(), lax_theme_child_asset_ver( $js ), true );
}, 1600 );

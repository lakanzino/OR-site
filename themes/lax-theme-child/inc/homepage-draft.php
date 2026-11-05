<?php
/** Manager-initiated preview of changes to the currently selected front page. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Work only on the well-known starter hero block types, never arbitrary HTML. */
function hj_home_draft_hero_parts( &$blocks, &$counts, $archive ) {
	foreach ( $blocks as &$block ) {
		$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
		$classes = preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY );
		$html = $block['innerHTML']; $updated = null;
		if ( $block['blockName'] === 'core/heading' && empty( $block['innerBlocks'] ) && preg_match( '/^\s*<h1(?:\s[^>]*)?>.*<\/h1>\s*$/s', $html ) ) {
			++$counts['heading'];
			$updated = preg_replace( '/(<h1(?:\s[^>]*)?>).*?(<\/h1>)/s', '$1' . esc_html( 'جراحی در پیش دارید؟ ما همیار شما هستیم.' ) . '$2', $html, 1 );
		} elseif ( $block['blockName'] === 'core/paragraph' && empty( $block['innerBlocks'] ) && ( in_array( 'hj-landing-lead', $classes, true ) || in_array( 'neo-lead', $classes, true ) ) && preg_match( '/^\s*<p(?:\s[^>]*)?>.*<\/p>\s*$/s', $html ) ) {
			++$counts['lead'];
			$updated = preg_replace( '/(<p(?:\s[^>]*)?>).*?(<\/p>)/s', '$1' . esc_html( 'انتخابی که مسیر زندگی‌تان را تغییر می‌دهد؛ برترین جراحان هر شهر و استان، اینجا.' ) . '$2', $html, 1 );
		} elseif ( $block['blockName'] === 'core/button' && empty( $block['innerBlocks'] ) && ( in_array( 'hj-btn-primary', $classes, true ) || in_array( 'neo-primary', $classes, true ) ) && class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $html );
			if ( $processor->next_tag( 'a' ) && $processor->get_attribute( 'href' ) !== null ) {
				$processor->set_attribute( 'href', $archive );
				$updated = $processor->get_updated_html();
				$updated = preg_replace( '/(<a\b[^>]*>).*?(<\/a>)/s', '$1' . esc_html( 'جستجوی جراحان' ) . '$2', $updated, 1, $n );
				if ( $n === 1 ) { ++$counts['button']; } else { $updated = null; }
			}
		}
		if ( is_string( $updated ) ) { $block['innerHTML'] = $updated; $block['innerContent'] = array( $updated ); }
		if ( ! empty( $block['innerBlocks'] ) ) { hj_home_draft_hero_parts( $block['innerBlocks'], $counts, $archive ); }
	}
	unset( $block );
}

function hj_home_draft_find_hero( &$blocks, &$count, &$ok, $archive ) {
	foreach ( $blocks as &$block ) {
		$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
		$classes = preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY );
		if ( $block['blockName'] === 'core/group' && ( in_array( 'hj-landing-hero-copy', $classes, true ) || in_array( 'neo-hero-copy', $classes, true ) ) ) {
			++$count; $parts = array( 'heading' => 0, 'lead' => 0, 'button' => 0 );
			hj_home_draft_hero_parts( $block['innerBlocks'], $parts, $archive );
			$ok = $parts === array( 'heading' => 1, 'lead' => 1, 'button' => 1 );
		} elseif ( ! empty( $block['innerBlocks'] ) ) { hj_home_draft_find_hero( $block['innerBlocks'], $count, $ok, $archive ); }
	}
	unset( $block );
}

/** Produce draft-only content; never delete or rewrite unknown blocks. */
function hj_home_draft_build( $content, $archive ) {
	$warnings = array(); $blocks = parse_blocks( $content );
	if ( serialize_blocks( $blocks ) !== $content ) {
		$warnings[] = 'ساختار صفحه بدون تغییر مجدد قابل بازخوانی نبود؛ هیرو را دستی ویرایش کنید.';
	} else {
		$copy = $blocks; $count = 0; $ok = false;
		hj_home_draft_find_hero( $copy, $count, $ok, $archive );
		if ( $count === 1 && $ok ) { $content = serialize_blocks( $copy ); }
		else { $warnings[] = 'هیروی شناخته‌شده یکتا نبود؛ عنوان، زیرعنوان و دکمه جراحان را در پیش‌نویس دستی ویرایش کنید.'; }
		$before_landing = false;
		foreach ( $blocks as $block ) {
			$classes = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
			if ( in_array( 'hj-landing', $classes, true ) || in_array( 'hj-neo', $classes, true ) ) {
				if ( $before_landing ) { $warnings[] = 'بلوک‌هایی پیش از بخش هیرو قرار دارند؛ ترتیب آنها را در پیش‌نویس بررسی کنید.'; }
				break;
			}
			if ( $block['blockName'] !== null || trim( $block['innerHTML'] ) !== '' ) { $before_landing = true; }
		}
	}
	if ( strpos( $content, 'پزشک هستید؟' ) !== false || strpos( $content, 'پزشکان و متخصصان' ) !== false || strpos( $content, 'ورود به پنل پزشک' ) !== false ) {
		$warnings[] = 'برخی برچسب‌های برگه هنوز «پزشک» دارند؛ در پیش‌نویس آنها را بررسی و در صورت نیاز به «جراح» تغییر دهید.';
	}
	if ( strpos( $content, '[hj_member_google' ) !== false || has_shortcode( $content, 'hj_member_account' ) || strpos( $content, '<!-- wp:block' ) !== false ) {
		$warnings[] = 'محتوا ممکن است ورود عمومی یا بلوک همگام‌شده داشته باشد؛ جای ورود را در پیش‌نویس بررسی و در صورت نیاز دستی به انتها منتقل کنید. نسخه دیگری اضافه نشده است.';
	} else {
		$content .= "\n<!-- wp:group {\"className\":\"hj-member-public-login\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group hj-member-public-login\">\n"
			. '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">ورود کاربران سایت</h2><!-- /wp:heading -->' . "\n"
			. '<!-- wp:paragraph --><p>برای ورود به سایت و پیگیری نظرها و پیشنهادهای خود از حساب گوگل استفاده کنید.</p><!-- /wp:paragraph -->' . "\n"
			. '<!-- wp:shortcode -->[hj_member_google]<!-- /wp:shortcode -->' . "\n"
			. "</div><!-- /wp:group -->\n";
	}
	return array( 'content' => $content, 'warnings' => $warnings );
}

function hj_home_draft_create() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'show_on_front' ) !== 'page' ) { return new WP_Error( 'hj_home_unavailable', 'صفحه نخست ایستا در دسترس نیست.' ); }
	$id = (int) get_option( 'page_on_front' ); $page = get_post( $id );
	if ( ! $page || $page->post_type !== 'page' || $page->post_status !== 'publish' || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'hj_home_invalid', 'برگه نخست منتشرشده یا دسترسی ویرایش آن پیدا نشد.' ); }
	if ( ! function_exists( 'wp_check_post_lock' ) ) { require_once ABSPATH . 'wp-admin/includes/post.php'; }
	$lock = get_post_meta( $id, '_edit_lock', true );
	if ( wp_check_post_lock( $id ) || ( $lock && (int) $lock > time() - 150 ) ) { return new WP_Error( 'hj_home_locked', 'برگه نخست در حال ویرایش است. پس از پایان ویرایش و انقضای قفل دوباره تلاش کنید.' ); }
	$archive = get_post_type_archive_link( 'doctor' ) ?: home_url( '/doctors/' );
	$built = hj_home_draft_build( $page->post_content, $archive );
	// The clone includes content, not arbitrary post metadata, authorship or settings.
	$draft = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'پیش‌نویس بررسی صفحه نخست', 'post_content' => $built['content'] ) ), true );
	if ( is_wp_error( $draft ) ) { return $draft; }
	update_post_meta( $draft, '_hj_home_draft_source', $id );
	update_post_meta( $draft, '_hj_home_draft_source_hash', hash( 'sha256', $page->post_content ) );
	update_post_meta( $draft, '_hj_home_draft_warnings', $built['warnings'] );
	return $draft;
}

add_action( 'admin_menu', function () {
	add_theme_page( 'پیش‌نویس صفحه نخست', 'پیش‌نویس صفحه نخست', 'manage_options', 'hj-home-draft', 'hj_home_draft_admin' );
} );
function hj_home_draft_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	?><div class="wrap" dir="rtl"><h1>پیش‌نویس صفحه نخست</h1>
	<p>این دکمه از محتوای صفحه نخست فعلی یک پیش‌نویس می‌سازد؛ صفحه اصلی یا تنظیمات خواندن را تغییر نمی‌دهد. پیش‌نویس را ویرایش و پیش‌نمایش کنید. انتشار آن یا اعمال تغییرات به صفحه اصلی خودکار نیست.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="hj_home_draft_create"><?php wp_nonce_field( 'hj_home_draft_create' ); submit_button( 'ساخت پیش‌نویس از صفحه نخست' ); ?></form></div><?php
}
add_action( 'admin_post_hj_home_draft_create', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_home_draft_create' );
	$draft = hj_home_draft_create();
	if ( is_wp_error( $draft ) ) { wp_die( esc_html( $draft->get_error_message() ), '', array( 'response' => 409 ) ); }
	wp_safe_redirect( get_edit_post_link( $draft, 'url' ) ); exit;
} );
add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $screen || $screen->base !== 'post' || ! $id || ! current_user_can( 'edit_post', $id ) || ! get_post_meta( $id, '_hj_home_draft_source', true ) ) { return; }
	echo '<div class="notice notice-info"><p>' . esc_html__( 'این فقط پیش‌نویس است؛ صفحه نخست تغییر نکرده است. پس از بازبینی، برای مرحله اعمال نهایی هماهنگ کنید.', 'lax-theme-child' ) . '</p></div>';
	foreach ( (array) get_post_meta( $id, '_hj_home_draft_warnings', true ) as $warning ) { echo '<div class="notice notice-warning"><p>' . esc_html( $warning ) . '</p></div>'; }
} );
add_filter( 'template_include', function ( $template ) {
	$post = get_queried_object();
	if ( is_preview() && is_singular( 'page' ) && $post && get_post_meta( $post->ID, '_hj_home_draft_source', true ) && current_user_can( 'edit_post', $post->ID ) ) {
		$file = get_stylesheet_directory() . '/front-page.php'; if ( is_readable( $file ) ) { return $file; }
	}
	return $template;
}, 20 );
add_action( 'wp_enqueue_scripts', function () {
	$post = get_queried_object();
	if ( is_preview() && is_singular( 'page' ) && $post && get_post_meta( $post->ID, '_hj_home_draft_source', true ) && current_user_can( 'edit_post', $post->ID ) ) {
		if ( function_exists( 'hj_static_home_styles' ) && ! wp_style_is( 'hj-static-home', 'enqueued' ) ) { hj_static_home_styles(); }
		if ( function_exists( 'hj_home_rows_styles' ) ) { hj_home_rows_styles(); }
	}
	if ( is_front_page() || ( is_preview() && is_singular( 'page' ) && $post && get_post_meta( $post->ID, '_hj_home_draft_source', true ) && current_user_can( 'edit_post', $post->ID ) ) ) {
		wp_enqueue_style( 'hj-homepage-draft', get_stylesheet_directory_uri() . '/assets/css/homepage-draft.css', array(), lax_theme_child_asset_ver( 'assets/css/homepage-draft.css' ) );
	}
}, 1450 );

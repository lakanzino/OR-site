<?php
/**
 * Repair existing surgery inline images without re-importing article text.
 * Uses the 18 vetted images shipped with the theme; no remote downloads.
 *
 * @package Lax_Theme_Child
 * @since 0.15.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve only known package paths, including same-site browser-expanded URLs. */
function hj_inline_repair_html( string $html, int $post_id ): string {
	$permalink = get_permalink( $post_id );
	$host      = wp_parse_url( $permalink, PHP_URL_HOST );
	$prefix    = trailingslashit( (string) wp_parse_url( $permalink, PHP_URL_PATH ) ) . 'assets/';
	return (string) preg_replace_callback(
		'/(<img\b[^>]*?(?<=\s)src\s*=\s*)([\'"])(.*?)\2/is',
		static function ( $m ) use ( $host, $prefix ) {
			$src  = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
			$path = wp_parse_url( $src, PHP_URL_PATH );
			if ( wp_parse_url( $src, PHP_URL_HOST ) && wp_parse_url( $src, PHP_URL_HOST ) !== $host ) {
				return $m[0]; // Never replace third-party or CDN images by guessing filenames.
			}
			if ( is_string( $path ) && 0 === strpos( $path, $prefix ) ) {
				return $m[1] . $m[2] . 'assets/' . substr( $path, strlen( $prefix ) ) . $m[2];
			}
			return $m[0];
		},
		$html
	);
}

/** Repair one existing post; any media failure leaves its original body intact. */
function hj_inline_repair_post( int $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'surgery' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'repair_post', 'مقالهٔ جراحی یا دسترسی ویرایش پیدا نشد.' );
	}
	$dir  = get_stylesheet_directory() . '/repair-media/' . sanitize_title( $post->post_name );
	$html = hj_inline_repair_html( $post->post_content, $post_id );
	$scan = hj_importer_scan_inline_images( $html, $dir );
	if ( $scan['missing'] ) {
		return new WP_Error( 'repair_missing', 'فایل آمادهٔ این تصویر در قالب نیست: ' . implode( '، ', $scan['missing'] ) );
	}
	if ( ! $scan['local'] ) {
		return array( 'uploaded' => 0, 'reused' => 0, 'changed' => false );
	}
	$result = hj_importer_import_inline_images( $post_id, $dir, $html, $post->post_name );
	if ( $result['missing'] || hj_importer_html_has_local_images( $result['html'] ) ) {
		return new WP_Error( 'repair_upload', 'انتقال تصاویر کامل نشد؛ متن مقاله دست‌نخورده ماند. ' . implode( '، ', $result['missing'] ) );
	}
	// Keep a recovery copy, in addition to the normal WordPress revisions.
	if ( ! metadata_exists( 'post', $post_id, '_hj_inline_repair_original' ) ) {
		update_post_meta( $post_id, '_hj_inline_repair_original', wp_slash( $post->post_content ) );
	}
	$saved = wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $result['html'] ) ), true );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	if ( ! $saved || get_post_field( 'post_content', $post_id, 'raw' ) !== $result['html'] ) {
		return new WP_Error( 'repair_save', 'ذخیرهٔ آدرس تصاویر تأیید نشد؛ نسخهٔ پشتیبان متن نگهداری شده است.' );
	}
	clean_post_cache( $post_id );
	do_action( 'litespeed_purge_post', $post_id );
	$result['changed'] = true;
	return $result;
}

add_action( 'admin_menu', static function () {
	add_submenu_page( 'edit.php?post_type=surgery', 'ترمیم تصاویر داخل مقاله', 'ترمیم تصاویر داخل مقاله', 'manage_options', 'hj-inline-image-repair', 'hj_inline_repair_page' );
} );

add_action( 'admin_post_hj_inline_image_repair', static function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی مجاز نیست.', '', array( 'response' => 403 ) );
	}
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	check_admin_referer( 'hj_inline_image_repair_' . $post_id );
	$result = hj_inline_repair_post( $post_id );
	$message = is_wp_error( $result ) ? $result->get_error_message() : (
		$result['changed'] ? 'تصاویر منتقل و آدرس‌ها ذخیره شدند؛ متن، تصویر شاخص و وضعیت انتشار مقاله تغییر نکردند.' : 'ارجاع محلی شکسته‌ای پیدا نشد؛ تغییری انجام نشد.'
	);
	set_transient( 'hj_inline_repair_' . get_current_user_id(), array( 'error' => is_wp_error( $result ), 'message' => $message ), 120 );
	wp_safe_redirect( admin_url( 'edit.php?post_type=surgery&page=hj-inline-image-repair' ) );
	exit;
} );

/** Read-only preview. Each explicit POST repairs just one article. */
function hj_inline_repair_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$notice = get_transient( 'hj_inline_repair_' . get_current_user_id() );
	delete_transient( 'hj_inline_repair_' . get_current_user_id() );
	?>
	<div class="wrap" dir="rtl">
		<h1>ترمیم تصاویر داخل مقالات جراحی</h1>
		<p>این صفحه فقط آدرس تصاویر داخل متن را به کتابخانهٔ رسانه منتقل می‌کند. متن، عنوان، تصویر شاخص و وضعیت انتشار جایگزین نمی‌شوند. باز کردن صفحه هیچ تغییری ایجاد نمی‌کند.</p>
		<?php if ( $notice ) : ?>
			<div class="notice <?php echo $notice['error'] ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
		<?php endif; ?>
		<table class="widefat striped"><thead><tr><th>مقاله</th><th>تصویر محلی در متن</th><th>عملیات</th></tr></thead><tbody>
		<?php foreach ( glob( get_stylesheet_directory() . '/repair-media/*', GLOB_ONLYDIR ) as $dir ) : ?>
			<?php
			$post = get_page_by_path( basename( $dir ), OBJECT, 'surgery' );
			if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$scan = hj_importer_scan_inline_images( hj_inline_repair_html( $post->post_content, $post->ID ), $dir );
			?>
			<tr><td><?php echo esc_html( $post->post_title ); ?></td><td><?php echo esc_html( (string) $scan['count'] ); ?><?php if ( $scan['missing'] ) : ?> — فایل ناموجود<?php endif; ?></td><td>
				<?php if ( $scan['count'] && ! $scan['missing'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="hj_inline_image_repair">
						<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
						<?php wp_nonce_field( 'hj_inline_image_repair_' . $post->ID ); ?>
						<button class="button button-primary">ترمیم فقط تصاویر این مقاله</button>
					</form>
				<?php else : ?>نیازی به ترمیم خودکار نیست / مسیر ناشناخته<?php endif; ?>
				<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener">مشاهده مقاله</a>
			</td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p>اگر پس از ترمیم هنوز نسخهٔ قدیمی نمایش داده می‌شود، کش LiteSpeed/CDN را پاک کنید. در این حالت آدرس تصویر را از مرورگر بررسی کنید؛ این ابزار خرابی ناشناختهٔ هاست یا URLهای خارجی را حدس نمی‌زند.</p>
	</div>
	<?php
}

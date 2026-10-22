<?php
/**
 * Single-article package importer.
 *
 * A "package" is a ZIP containing three root files:
 *   - article.json   manifest (title, slug, SEO, taxonomy, FAQ, dates)
 *   - content.html   post body, ready for the WordPress code editor
 *   - featured.webp  featured image (1200x675 recommended)
 *
 * Importing the same package again updates the existing post (matched by
 * slug) and reuses the already-imported image instead of duplicating it.
 *
 * @package Lax_Theme_Child
 * @since   0.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'HJ_IMPORTER_CAP' ) ) {
	define( 'HJ_IMPORTER_CAP', 'manage_options' );
}

/**
 * Allowed post types for package import.
 *
 * @return string[]
 */
function hj_importer_allowed_post_types() {
	return array( 'patient_guide', 'or_environment', 'surgery', 'student_content', 'glossary' );
}

/**
 * Allowed image extensions inside a package.
 *
 * @return string[]
 */
function hj_importer_allowed_image_ext() {
	return array( 'webp', 'jpg', 'jpeg', 'png' );
}

/**
 * Register the admin submenu under the patient guide CPT.
 *
 * @return void
 */
function hj_importer_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=patient_guide',
		'ایمپورت تک‌مقاله همیار',
		'ایمپورت مقاله',
		HJ_IMPORTER_CAP,
		'hj-article-importer',
		'hj_importer_render_page'
	);
}
add_action( 'admin_menu', 'hj_importer_admin_menu' );

/**
 * Read the article.json manifest straight from a ZIP without extracting.
 *
 * @param string $zip_path Absolute ZIP path.
 * @return array|\WP_Error
 */
function hj_importer_read_manifest( $zip_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'no_zip', 'افزونه‌ی ZipArchive روی این سرور فعال نیست؛ با پشتیبانی هاست هماهنگ کنید.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return new WP_Error( 'bad_zip', 'فایل ZIP باز نشد؛ ممکن است دانلود ناقص باشد.' );
	}
	$raw = $zip->getFromName( 'article.json' );
	$zip->close();
	if ( false === $raw || '' === $raw ) {
		return new WP_Error( 'no_manifest', 'فایل article.json داخل بسته پیدا نشد.' );
	}
	$manifest = json_decode( $raw, true );
	if ( ! is_array( $manifest ) ) {
		return new WP_Error( 'bad_manifest', 'article.json معتبر نیست.' );
	}
	return $manifest;
}

/**
 * Extract a package ZIP into a unique temp directory with traversal checks.
 *
 * @param string $zip_path Absolute ZIP path.
 * @return string|\WP_Error Extracted directory path.
 */
function hj_importer_extract( $zip_path ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return new WP_Error( 'bad_zip', 'فایل ZIP باز نشد.' );
	}
	$upload = wp_upload_dir();
	$target = trailingslashit( $upload['basedir'] ) . 'hj-import-tmp/' . wp_generate_uuid4();
	if ( ! wp_mkdir_p( $target ) ) {
		$zip->close();
		return new WP_Error( 'no_tmp', 'پوشه‌ی موقت روی سرور ساخته نشد.' );
	}
	$real_target = realpath( $target );
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$entry = $zip->getNameIndex( $i );
		if ( false === $entry || '' === $entry ) {
			continue;
		}
		// Zip-Slip protection: no absolute paths, no parent traversal.
		$clean = ltrim( str_replace( '\\', '/', $entry ), '/' );
		if ( false !== strpos( $clean, '..' ) || preg_match( '#^[A-Za-z]:#', $clean ) ) {
			$zip->close();
			return new WP_Error( 'unsafe_path', 'بسته شامل مسیر غیرمجاز است و رد شد.' );
		}
	}
	$ok = $zip->extractTo( $target );
	$zip->close();
	if ( ! $ok ) {
		return new WP_Error( 'extract_failed', 'استخراج بسته شکست خورد.' );
	}
	return $real_target;
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory path.
 * @return void
 */
function hj_importer_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		is_dir( $path ) ? hj_importer_rmdir( $path ) : wp_delete_file( $path );
	}
	@rmdir( $dir );
}

/**
 * Import (or update) the featured image and attach it to the post.
 *
 * @param int    $post_id Post ID.
 * @param string $dir     Extracted package directory.
 * @param array  $image   Image manifest entry (file + alt).
 * @return int|\WP_Error Attachment ID.
 */
function hj_importer_import_image( $post_id, $dir, array $image ) {
	$file = isset( $image['file'] ) ? basename( (string) $image['file'] ) : '';
	if ( '' === $file ) {
		return new WP_Error( 'no_image_file', 'نام تصویر شاخص در article.json ثبت نشده.' );
	}
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, hj_importer_allowed_image_ext(), true ) ) {
		return new WP_Error( 'bad_image_ext', 'فرمت تصویر مجاز نیست (فقط webp/jpg/png).' );
	}
	$src = $dir . DIRECTORY_SEPARATOR . $file;
	if ( ! is_file( $src ) ) {
		return new WP_Error( 'missing_image', 'فایل تصویر شاخص داخل بسته پیدا نشد: ' . $file );
	}
	$alt = isset( $image['alt'] ) ? sanitize_text_field( (string) $image['alt'] ) : '';

	// Deduplicate: reuse the attachment created earlier for this package.
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'meta_key'       => '_hj_package_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => get_post_field( 'post_name', $post_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
		)
	);
	if ( $existing ) {
		$attachment_id = (int) $existing[0];
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$bits       = file_get_contents( $src );
	$upload_dir = wp_upload_dir();
	// نام یکتای مبتنی بر نامک تا فایل تصویر هیچ بسته‌ای فایل بسته‌ی دیگر را بازنویسی نکند.
	$preferred = sanitize_title( get_post_field( 'post_name', $post_id ) ) . '-featured.' . $ext;
	$unique    = wp_unique_filename( $upload_dir['path'], $preferred );
	$upload    = wp_upload_bits( $unique, null, $bits );
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'upload_failed', 'آپلود تصویر شاخص شکست خورد: ' . $upload['error'] );
	}
	$filetype = wp_check_filetype( $upload['file'], null );
	$att_id   = wp_insert_attachment(
		array(
			'guid'           => $upload['url'],
			'post_mime_type' => $filetype['type'],
			'post_title'     => get_post_field( 'post_name', $post_id ) . ' — تصویر شاخص',
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		),
		$upload['file'],
		$post_id
	);
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		return new WP_Error( 'attachment_failed', 'ساخت رکورد رسانه در کتابخانه شکست خورد.' );
	}
	$meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
	wp_update_attachment_metadata( $att_id, $meta );
	if ( $alt ) {
		update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
	}
	update_post_meta( $att_id, '_hj_package_slug', get_post_field( 'post_name', $post_id ) );
	update_post_meta( $att_id, '_hj_package_image', $file );
	set_post_thumbnail( $post_id, $att_id );
	return $att_id;
}

/**
 * Core routine: import one package ZIP.
 *
 * @param string $zip_path Absolute ZIP path.
 * @param string $source   Human label of the source for the log.
 * @return array|\WP_Error Result descriptor.
 */
function hj_importer_import_package( $zip_path, $source = 'upload' ) {
	$manifest = hj_importer_read_manifest( $zip_path );
	if ( is_wp_error( $manifest ) ) {
		return $manifest;
	}
	$slug  = isset( $manifest['slug'] ) ? sanitize_title( (string) $manifest['slug'] ) : '';
	$title = isset( $manifest['title'] ) ? sanitize_text_field( (string) $manifest['title'] ) : '';
	if ( '' === $slug || '' === $title ) {
		return new WP_Error( 'bad_manifest', 'slug یا title در article.json خالی است.' );
	}
	$post_type = isset( $manifest['post_type'] ) ? sanitize_key( (string) $manifest['post_type'] ) : 'patient_guide';
	if ( ! in_array( $post_type, hj_importer_allowed_post_types(), true ) || ! post_type_exists( $post_type ) ) {
		$post_type = 'patient_guide';
	}
	$status = ( isset( $manifest['post_status'] ) && 'draft' === $manifest['post_status'] ) ? 'draft' : 'publish';

	$dir = hj_importer_extract( $zip_path );
	if ( is_wp_error( $dir ) ) {
		return $dir;
	}
	$content_file = isset( $manifest['content_file'] ) ? basename( (string) $manifest['content_file'] ) : 'content.html';
	$html_path    = $dir . DIRECTORY_SEPARATOR . $content_file;
	if ( ! is_file( $html_path ) ) {
		hj_importer_rmdir( $dir );
		return new WP_Error( 'no_content', 'فایل content.html داخل بسته پیدا نشد.' );
	}
	$html = (string) file_get_contents( $html_path );
	$html = wp_kses_post( $html );

	// Upsert by slug inside the target post type.
	$existing = get_page_by_path( $slug, OBJECT, $post_type );
	$post_id  = $existing ? (int) $existing->ID : 0;
	$postarr  = array(
		'post_type'    => $post_type,
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $html,
		'post_status'  => $status,
	);
	if ( $post_id ) {
		$postarr['ID'] = $post_id;
	}
	$saved = wp_insert_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $saved ) ) {
		hj_importer_rmdir( $dir );
		return $saved;
	}
	$post_id = (int) $saved;
	$action  = $existing ? 'updated' : 'created';

	// Excerpt doubles as the theme's description fallback.
	$meta_desc = '';
	if ( isset( $manifest['seo']['meta_description'] ) ) {
		$meta_desc = sanitize_text_field( (string) $manifest['seo']['meta_description'] );
	} elseif ( isset( $manifest['excerpt'] ) ) {
		$meta_desc = sanitize_text_field( (string) $manifest['excerpt'] );
	}
	if ( $meta_desc ) {
		wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => $meta_desc ) );
		update_post_meta( $post_id, '_hj_meta_description', $meta_desc );
	}

	// SEO metadata.
	if ( ! empty( $manifest['seo']['focus_keyword'] ) ) {
		update_post_meta( $post_id, '_hj_focus_keyword', sanitize_text_field( (string) $manifest['seo']['focus_keyword'] ) );
	}
	if ( ! empty( $manifest['seo']['keywords'] ) && is_array( $manifest['seo']['keywords'] ) ) {
		update_post_meta( $post_id, '_hj_keywords', array_map( 'sanitize_text_field', $manifest['seo']['keywords'] ) );
	}
	if ( ! empty( $manifest['seo']['search_intent'] ) ) {
		update_post_meta( $post_id, '_hj_search_intent', sanitize_text_field( (string) $manifest['seo']['search_intent'] ) );
	}

	// FAQ payload for JSON-LD output.
	if ( ! empty( $manifest['faq'] ) && is_array( $manifest['faq'] ) ) {
		$faq = array();
		foreach ( $manifest['faq'] as $item ) {
			if ( ! empty( $item['q'] ) && ! empty( $item['a'] ) ) {
				$faq[] = array(
					'q' => wp_kses_post( (string) $item['q'] ),
					'a' => wp_kses_post( (string) $item['a'] ),
				);
			}
		}
		if ( $faq ) {
			update_post_meta( $post_id, '_hj_faq', $faq );
		}
	}

	// Taxonomy (e.g. guide_stage: pillars / before / during / after).
	if ( ! empty( $manifest['taxonomy']['taxonomy'] ) && ! empty( $manifest['taxonomy']['term'] ) ) {
		$tax = sanitize_key( (string) $manifest['taxonomy']['taxonomy'] );
		$term_slug = sanitize_title( (string) $manifest['taxonomy']['term'] );
		if ( taxonomy_exists( $tax ) ) {
			$term = term_exists( $term_slug, $tax );
			if ( ! $term ) {
				$term = wp_insert_term( sanitize_text_field( (string) $manifest['taxonomy']['term'] ), $tax, array( 'slug' => $term_slug ) );
			}
			if ( ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, (int) $term['term_id'], $tax );
			}
		}
	}

	// Featured image.
	$image_result = 0;
	if ( ! empty( $manifest['featured_image']['file'] ) ) {
		$image_result = hj_importer_import_image(
			$post_id,
			$dir,
			$manifest['featured_image']
		);
	}

	// Provenance and idempotency bookkeeping.
	update_post_meta( $post_id, '_hj_import_source', sanitize_text_field( $source ) );
	update_post_meta( $post_id, '_hj_import_package', $slug );
	update_post_meta( $post_id, '_hj_imported_at', current_time( 'mysql' ) );
	if ( ! empty( $manifest['package_version'] ) ) {
		update_post_meta( $post_id, '_hj_import_version', (int) $manifest['package_version'] );
	}
	if ( ! empty( $manifest['dates']['updated_at'] ) ) {
		update_post_meta( $post_id, '_hj_content_updated_at', sanitize_text_field( (string) $manifest['dates']['updated_at'] ) );
	}

	hj_importer_rmdir( $dir );

	return array(
		'slug'          => $slug,
		'title'         => $title,
		'post_id'       => $post_id,
		'action'        => $action,
		'image'         => is_wp_error( $image_result ) ? $image_result->get_error_message() : ( $image_result ? 'set' : 'skipped' ),
		'edit_link'     => get_edit_post_link( $post_id, 'raw' ),
		'permalink'     => get_permalink( $post_id ),
	);
}

/**
 * Output FAQPage JSON-LD from the imported FAQ payload.
 *
 * @return void
 */
function hj_importer_faq_schema() {
	if ( ! is_singular() ) {
		return;
	}
	$faq = get_post_meta( get_queried_object_id(), '_hj_faq', true );
	if ( ! is_array( $faq ) || empty( $faq ) ) {
		return;
	}
	$entities = array();
	foreach ( $faq as $item ) {
		if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
			continue;
		}
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $item['q'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item['a'] ),
			),
		);
	}
	if ( ! $entities ) {
		return;
	}
	$data = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}
add_action( 'wp_head', 'hj_importer_faq_schema', 25 );

/**
 * Handle both import POST actions and stash results for the admin notice.
 *
 * @return void
 */
function hj_importer_handle_request() {
	if ( ! is_admin() || empty( $_POST['hj_importer_action'] ) ) {
		return;
	}
	if ( ! current_user_can( HJ_IMPORTER_CAP ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ) );
	}
	check_admin_referer( 'hj_importer' );

	$results = array();
	$action  = sanitize_key( (string) $_POST['hj_importer_action'] );

	if ( 'bundled' === $action && ! empty( $_POST['package'] ) ) {
		$name = basename( (string) $_POST['package'] );
		if ( ! preg_match( '/^article-package-[a-z0-9-]+\.zip$/', $name ) ) {
			$results[] = new WP_Error( 'bad_name', 'نام بسته نامعتبر است.' );
		} else {
			$path = get_stylesheet_directory() . '/content-imports/' . $name;
			if ( ! is_file( $path ) ) {
				$results[] = new WP_Error( 'missing', 'بسته‌ی همراه قالب پیدا نشد: ' . $name );
			} else {
				$results[] = hj_importer_import_package( $path, 'bundled-theme' );
			}
		}
	}

	if ( 'upload' === $action && ! empty( $_FILES['hj_packages']['name'] ) ) {
		$files = $_FILES['hj_packages'];
		$total = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
		for ( $i = 0; $i < $total; $i++ ) {
			if ( UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
				$results[] = new WP_Error( 'upload_err', 'آپلود فایل شماره ' . ( $i + 1 ) . ' موفق نبود.' );
				continue;
			}
			$name = sanitize_file_name( $files['name'][ $i ] );
			if ( 'zip' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				$results[] = new WP_Error( 'ext', 'فقط فایل ZIP پذیرفته می‌شود: ' . $name );
				continue;
			}
			$results[] = hj_importer_import_package( $files['tmp_name'][ $i ], 'manual-upload' );
		}
	}

	set_transient( 'hj_importer_results_' . get_current_user_id(), $results, 120 );
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'patient_guide', 'page' => 'hj-article-importer', 'done' => 1 ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_hj_article_import', 'hj_importer_handle_request' );

/**
 * Order in which bundled packages are presented in the admin table.
 *
 * @return string[] Slugs in the desired row order.
 */
function hj_importer_bundled_order() {
	return array(
		'what-is-operating-room',
		'surgical-care-team',
		'patient-journey',
		'glossary-surgical-terms',
		'operating-room-moment-by-moment',
	);
}

/**
 * List bundled packages with their manifest titles, in a fixed order.
 *
 * @return array Each row: file, slug, title, published (bool).
 */
function hj_importer_bundled_list() {
	$dir = get_stylesheet_directory() . '/content-imports';
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	foreach ( glob( $dir . '/article-package-*.zip' ) as $path ) {
		$manifest = hj_importer_read_manifest( $path );
		$slug     = is_wp_error( $manifest ) ? '' : $manifest['slug'];
		$out[]    = array(
			'file'      => basename( $path ),
			'slug'      => $slug,
			'title'     => is_wp_error( $manifest ) ? '(' . $manifest->get_error_message() . ')' : $manifest['title'],
			'published' => $slug && get_page_by_path( $slug, OBJECT, 'patient_guide' ),
			'order'     => array_search( $slug, hj_importer_bundled_order(), true ),
		);
	}
	usort(
		$out,
		static function ( $a, $b ) {
			$oa = false === $a['order'] ? 999 : $a['order'];
			$ob = false === $b['order'] ? 999 : $b['order'];
			return $oa === $ob ? strcmp( $a['slug'], $b['slug'] ) : $oa - $ob;
		}
	);
	return $out;
}

/**
 * Render the admin page.
 *
 * @return void
 */
function hj_importer_render_page() {
	if ( ! current_user_can( HJ_IMPORTER_CAP ) ) {
		return;
	}
	$results = get_transient( 'hj_importer_results_' . get_current_user_id() );
	if ( $results ) {
		delete_transient( 'hj_importer_results_' . get_current_user_id() );
	}
	$action_url = admin_url( 'admin-post.php' );
	?>
	<div class="wrap">
		<h1>ایمپورت تک‌مقاله همیار</h1>
		<p>هر بسته یک فایل ZIP شامل <code>article.json</code>، <code>content.html</code> و <code>featured.webp</code> است. ایمپورت دوباره‌ی همان بسته، مقاله را به‌روزرسانی می‌کند و تصویر تکراری نمی‌سازد.</p>

		<?php if ( $results ) : ?>
			<?php foreach ( $results as $res ) : ?>
				<?php if ( is_wp_error( $res ) ) : ?>
					<div class="notice notice-error"><p><?php echo esc_html( $res->get_error_message() ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-success">
						<p>
							<?php echo 'updated' === $res['action'] ? 'به‌روزرسانی شد: ' : 'ساخته شد: '; ?>
							<strong><?php echo esc_html( $res['title'] ); ?></strong>
							(<?php echo esc_html( $res['slug'] ); ?>) — تصویر شاخص: <?php echo esc_html( $res['image'] ); ?>
							| <a href="<?php echo esc_url( $res['edit_link'] ); ?>">ویرایش</a>
							| <a href="<?php echo esc_url( $res['permalink'] ); ?>" target="_blank">مشاهده</a>
						</p>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>

		<h2>بسته‌های همراه قالب</h2>
		<table class="widefat striped" style="max-width:1080px">
			<thead><tr><th>ردیف</th><th>بسته</th><th>نامک</th><th>وضعیت</th><th>عملیات</th></tr></thead>
			<tbody>
			<?php $bundled = hj_importer_bundled_list(); ?>
			<?php if ( ! $bundled ) : ?>
				<tr><td colspan="5">بسته‌ای در پوشه‌ی <code>content-imports</code> قالب پیدا نشد.</td></tr>
			<?php endif; ?>
			<?php foreach ( $bundled as $i => $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
					<td><?php echo esc_html( $row['title'] ); ?></td>
					<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
					<td><?php echo $row['published'] ? '<span style="color:#15803d;font-weight:700">منتشرشده — ایمپورت دوباره = به‌روزرسانی</span>' : '<span style="color:#9aa0a6">هنوز منتشر نشده</span>'; ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin:0">
							<input type="hidden" name="action" value="hj_article_import">
							<input type="hidden" name="hj_importer_action" value="bundled">
							<input type="hidden" name="package" value="<?php echo esc_attr( $row['file'] ); ?>">
							<?php wp_nonce_field( 'hj_importer' ); ?>
							<button type="submit" class="button button-primary">ایمپورت این مقاله</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2>ایمپورت از فایل (برای مقاله‌های بعدی)</h2>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>" enctype="multipart/form-data" style="max-width:980px">
			<input type="hidden" name="action" value="hj_article_import">
			<input type="hidden" name="hj_importer_action" value="upload">
			<?php wp_nonce_field( 'hj_importer' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="hj_packages">فایل(های) ZIP بسته‌ی مقاله</label></th>
					<td><input type="file" id="hj_packages" name="hj_packages[]" accept=".zip" multiple required></td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary">شروع ایمپورت</button></p>
			<p class="description">می‌توانید چند بسته را هم‌زمان انتخاب کنید. هر بسته: عنوان و محتوا، متای توضیحات و کلیدواژه‌ها، اسکیمای FAQ، تاکسونومی مرحله و تصویر شاخص با متن alt را یک‌جا وارد می‌کند.</p>
		</form>
	</div>
	<?php
}

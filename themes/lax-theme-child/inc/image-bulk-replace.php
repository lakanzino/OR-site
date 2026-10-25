<?php
/**
 * One-time bulk featured-image replacer.
 *
 * The old featured images had the Persian title printed on them. The clean set
 * (`releases/featured-images-clean.zip`) is named `<slug>.webp`, so the whole
 * replacement can be done in one upload: every file is matched to the post
 * whose slug is the file name and becomes that post's featured image.
 *
 * This is a one-off maintenance screen, not a content feature. It is safe to
 * run more than once: a post that already has the same file becomes a no-op and
 * is reported as «بدون تغییر».
 *
 * @package Lax_Theme_Child
 * @since   0.12.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ORSITE_IMG_SWAP_CAP  = 'edit_others_posts';
const ORSITE_IMG_SWAP_MENU = 'orsite-image-swap';

/**
 * Register the maintenance screen under the first surgery menu.
 */
function orsite_img_swap_menu() {
	add_submenu_page(
		'edit.php?post_type=surgery',
		__( 'جایگزینی گروهی تصویر شاخص', 'lax-theme-child' ),
		__( 'جایگزینی تصویر شاخص', 'lax-theme-child' ),
		ORSITE_IMG_SWAP_CAP,
		ORSITE_IMG_SWAP_MENU,
		'orsite_img_swap_page'
	);
}
add_action( 'admin_menu', 'orsite_img_swap_menu', 21 );

/**
 * Post types a slug may belong to.
 *
 * @return string[]
 */
function orsite_img_swap_post_types() {
	$types = array( 'surgery', 'patient_guide', 'glossary', 'post', 'page' );
	$extra = get_post_types( array( 'public' => true ), 'names' );
	foreach ( (array) $extra as $t ) {
		if ( 'attachment' !== $t && ! in_array( $t, $types, true ) ) {
			$types[] = $t;
		}
	}
	return $types;
}

/**
 * Find a post by its slug, across the public post types.
 *
 * @param string $slug Sanitised file name without extension.
 * @return WP_Post|null
 */
function orsite_img_swap_find_post( $slug ) {
	$q = new WP_Query(
		array(
			'name'                   => $slug,
			'post_type'              => orsite_img_swap_post_types(),
			'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return $q->posts ? $q->posts[0] : null;
}

/**
 * Import one file into the media library and set it as the post thumbnail.
 *
 * @param WP_Post $post     Target post.
 * @param string  $tmp_file Local path of the file just extracted.
 * @param string  $name     Desired file name.
 * @return array{status:string,message:string}
 */
function orsite_img_swap_attach( $post, $tmp_file, $name ) {
	$dest = wp_upload_dir();
	if ( ! empty( $dest['error'] ) ) {
		return array( 'status' => 'error', 'message' => $dest['error'] );
	}

	$filename = wp_unique_filename( $dest['path'], sanitize_file_name( $name ) );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$copy = $dest['path'] . '/' . $filename;
	if ( ! @copy( $tmp_file, $copy ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return array( 'status' => 'error', 'message' => __( 'کپی فایل در پوشه‌ی آپلود انجام نشد.', 'lax-theme-child' ) );
	}

	$filetype = wp_check_filetype( $filename, null );
	$attach   = array(
		'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/webp',
		'post_title'     => $post->post_title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$attach_id = wp_insert_attachment( $attach, $copy, $post->ID, true );
	if ( is_wp_error( $attach_id ) ) {
		@unlink( $copy ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return array( 'status' => 'error', 'message' => $attach_id->get_error_message() );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $copy ) );
	update_post_meta( $attach_id, '_wp_attachment_image_alt', $post->post_title );

	$previous = (int) get_post_thumbnail_id( $post->ID );
	if ( $previous === (int) $attach_id ) {
		return array( 'status' => 'skipped', 'message' => __( 'بدون تغییر', 'lax-theme-child' ) );
	}

	set_post_thumbnail( $post->ID, $attach_id );

	return array(
		'status'  => 'done',
		'message' => $previous
			? sprintf( __( 'جایگزین شد (تصویر قبلی: #%d)', 'lax-theme-child' ), $previous )
			: __( 'تنظیم شد (مقاله قبلاً تصویر شاخص نداشت)', 'lax-theme-child' ),
	);
}

/**
 * Run the whole replacement for one uploaded archive.
 *
 * @param string $zip_path Path of the uploaded ZIP.
 * @param bool   $dry_run  Report only.
 * @return array{rows:array,totals:array}
 */
function orsite_img_swap_run( $zip_path, $dry_run = false ) {
	$rows   = array();
	$totals = array( 'done' => 0, 'skipped' => 0, 'missing' => 0, 'error' => 0 );

	if ( ! class_exists( 'ZipArchive' ) ) {
		$rows[] = array(
			'slug'    => '—',
			'status'  => 'error',
			'message' => __( 'افزونه‌ی ZIP روی این هاست فعال نیست؛ از هاست بخواهید `zip` را فعال کند.', 'lax-theme-child' ),
		);
		return array( 'rows' => $rows, 'totals' => $totals + array( 'error' => 1 ) );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		$rows[] = array( 'slug' => '—', 'status' => 'error', 'message' => __( 'فایل ZIP باز نشد.', 'lax-theme-child' ) );
		$totals['error']++;
		return array( 'rows' => $rows, 'totals' => $totals );
	}

	$tmp_dir = trailingslashit( get_temp_dir() ) . 'orsite-img-swap-' . wp_generate_password( 8, false ) . '/';
	wp_mkdir_p( $tmp_dir );

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$entry = $zip->getNameIndex( $i );
		if ( ! $entry || '/' === substr( $entry, -1 ) ) {
			continue;
		}
		if ( preg_match( '/__MACOSX|\.DS_Store|Thumbs\.db/i', $entry ) ) {
			continue;
		}

		$base = basename( $entry );
		$ext  = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'webp', 'jpg', 'jpeg', 'png' ), true ) ) {
			continue;
		}

		$slug = sanitize_title( pathinfo( $base, PATHINFO_FILENAME ) );
		if ( '' === $slug ) {
			continue;
		}

		$post = orsite_img_swap_find_post( $slug );
		if ( ! $post ) {
			$rows[] = array(
				'slug'    => $slug,
				'status'  => 'missing',
				'message' => __( 'مقاله‌ای با این نامک پیدا نشد (یا هنوز ایمپورت نشده)', 'lax-theme-child' ),
			);
			$totals['missing']++;
			continue;
		}

		if ( $dry_run ) {
			$has  = (bool) get_post_thumbnail_id( $post->ID );
			$rows[] = array(
				'slug'    => $slug,
				'status'  => 'done',
				'message' => sprintf(
					/* translators: 1: post title, 2: post type */
					__( 'پیدا شد: «%1$s» (%2$s)%3$s', 'lax-theme-child' ),
					$post->post_title,
					$post->post_type,
					$has ? '' : __( ' — بدون تصویر فعلی', 'lax-theme-child' )
				),
			);
			$totals['done']++;
			continue;
		}

		$tmp_file = $tmp_dir . $base;
		if ( true !== $zip->extractTo( $tmp_dir, $entry ) || ! file_exists( $tmp_file ) ) {
			$rows[] = array( 'slug' => $slug, 'status' => 'error', 'message' => __( 'استخراج فایل انجام نشد.', 'lax-theme-child' ) );
			$totals['error']++;
			continue;
		}

		$res    = orsite_img_swap_attach( $post, $tmp_file, $slug . '.' . $ext );
		$rows[] = array( 'slug' => $slug, 'status' => $res['status'], 'message' => $res['message'], 'post' => $post->post_title );
		$totals[ $res['status'] ] = ( isset( $totals[ $res['status'] ] ) ? $totals[ $res['status'] ] : 0 ) + 1;
		@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$zip->close();
	orsite_img_swap_rmdir( $tmp_dir );

	return array( 'rows' => $rows, 'totals' => $totals );
}

/**
 * Remove the temporary folder.
 *
 * @param string $dir Directory.
 */
function orsite_img_swap_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( (array) glob( $dir . '*' ) as $file ) {
		if ( is_file( $file ) ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Render the screen.
 */
function orsite_img_swap_page() {
	if ( ! current_user_can( ORSITE_IMG_SWAP_CAP ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'lax-theme-child' ) );
	}

	$result = null;
	$notice = '';

	if ( isset( $_POST['orsite_img_swap_action'] ) ) {
		$nonce = '';
		if ( isset( $_POST['orsite_gh_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['orsite_gh_nonce'] ) );
		} elseif ( isset( $_POST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'orsite_gh_admin' ) ) {
			$notice = __( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.', 'lax-theme-child' );
		} elseif ( empty( $_FILES['orsite_img_zip']['tmp_name'] ) ) {
			$notice = __( 'ابتدا فایل ZIP تصاویر را انتخاب کنید.', 'lax-theme-child' );
		} else {
			$dry     = ! empty( $_POST['orsite_img_dry'] );
			$checked = wp_check_filetype( sanitize_file_name( wp_unslash( $_FILES['orsite_img_zip']['name'] ) ) );
			if ( 'zip' !== strtolower( (string) pathinfo( $checked['ext'], PATHINFO_EXTENSION ) ) && 'zip' !== $checked['ext'] ) {
				$notice = __( 'فقط فایل ZIP پذیرفته می‌شود.', 'lax-theme-child' );
			} else {
				$moved = wp_handle_upload(
					$_FILES['orsite_img_zip'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					array( 'test_form' => false, 'mimes' => array( 'zip' => 'application/zip' ) )
				);
				if ( isset( $moved['error'] ) ) {
					$notice = $moved['error'];
				} else {
					$result = orsite_img_swap_run( $moved['file'], $dry );
					@unlink( $moved['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$notice = $dry
						? __( 'این «پیش‌نمایش» بود؛ هیچ تغییری اعمال نشد.', 'lax-theme-child' )
						: __( 'جایگزینی انجام شد.', 'lax-theme-child' );
				}
			}
		}
	}

	$labels = array(
		'done'    => __( 'انجام شد', 'lax-theme-child' ),
		'skipped' => __( 'بدون تغییر', 'lax-theme-child' ),
		'missing' => __( 'نامک پیدا نشد', 'lax-theme-child' ),
		'error'   => __( 'خطا', 'lax-theme-child' ),
	);
	?>
	<div class="wrap" dir="rtl">
		<h1><?php esc_html_e( 'جایگزینی گروهی تصویر شاخص', 'lax-theme-child' ); ?></h1>

		<p style="max-width:52em;line-height:1.9">
			<?php esc_html_e( 'فایل ZIP تصاویر را انتخاب کنید؛ هر فایل باید نامش دقیقاً نامک انگلیسی مقاله باشد (مثلاً cataract-overview.webp). هر تصویر به مقاله‌ی هم‌نام وصل و به‌عنوان تصویر شاخص تنظیم می‌شود. اگر مقاله‌ای پیدا نشود فقط در گزارش می‌آید و چیزی خراب نمی‌شود.', 'lax-theme-child' ); ?>
		</p>

		<?php if ( $notice ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;max-width:52em">
			<?php wp_nonce_field( 'orsite_gh_admin', 'orsite_gh_nonce' ); ?>
			<p>
				<label for="orsite_img_zip"><strong><?php esc_html_e( 'فایل ZIP تصاویر', 'lax-theme-child' ); ?></strong></label><br>
				<input type="file" name="orsite_img_zip" id="orsite_img_zip" accept=".zip" required>
			</p>
			<p>
				<label><input type="checkbox" name="orsite_img_dry" value="1"> <?php esc_html_e( 'فقط پیش‌نمایش (بدون تغییر واقعی)', 'lax-theme-child' ); ?></label>
			</p>
			<p>
				<button type="submit" name="orsite_img_swap_action" value="run" class="button button-primary">
					<?php esc_html_e( 'اجرا', 'lax-theme-child' ); ?>
				</button>
			</p>
		</form>

		<?php if ( $result ) : ?>
			<h2 style="margin-top:26px"><?php esc_html_e( 'گزارش', 'lax-theme-child' ); ?></h2>
			<p>
				<?php
				foreach ( $labels as $key => $label ) {
					printf(
						'<span style="display:inline-block;margin-inline-end:16px">%s: <strong>%d</strong></span>',
						esc_html( $label ),
						(int) ( isset( $result['totals'][ $key ] ) ? $result['totals'][ $key ] : 0 )
					);
				}
				?>
			</p>
			<table class="widefat striped" style="max-width:52em">
				<thead>
					<tr>
						<th style="width:34%"><?php esc_html_e( 'نامک', 'lax-theme-child' ); ?></th>
						<th style="width:14%"><?php esc_html_e( 'وضعیت', 'lax-theme-child' ); ?></th>
						<th><?php esc_html_e( 'توضیح', 'lax-theme-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $result['rows'] as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
						<td>
							<?php
							$map = array(
								'done'    => '#00a32a',
								'skipped' => '#996800',
								'missing' => '#8c8f94',
								'error'   => '#d63638',
							);
							printf(
								'<span style="color:%s;font-weight:600">%s</span>',
								esc_attr( isset( $map[ $row['status'] ] ) ? $map[ $row['status'] ] : '#8c8f94' ),
								esc_html( isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'] )
							);
							?>
						</td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top:14px">
				<a class="button" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'کتابخانه‌ی رسانه', 'lax-theme-child' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

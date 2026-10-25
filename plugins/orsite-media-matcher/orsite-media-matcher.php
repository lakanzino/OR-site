<?php
/**
 * Plugin Name:       ORSite — اتصال تصاویر رسانه به مقاله‌ها
 * Plugin URI:        https://github.com/lakanzino/OR-site
 * Description:       تصاویر آپلودشده در کتابخانه‌ی رسانه را بر اساس نام فایل به مقاله‌ی هم‌نام وصل می‌کند و به‌عنوان «تصویر شاخص» تنظیم می‌کند؛ همراه با گزارش کامل و حالت پیش‌نمایش.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            OR Site
 * License:           GPL-2.0-or-later
 * Text Domain:       orsite-media-matcher
 *
 * @package ORSite_Media_Matcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OMM_VERSION', '1.1.0' );
define( 'OMM_SLUG', 'orsite-media-matcher' );
define( 'OMM_CAP', 'manage_options' );

/**
 * Pinned copy of the text-free image pack. Pinned to a commit on purpose: a
 * later commit must never change what an admin button downloads.
 */
define( 'OMM_PACK_URL', 'https://github.com/lakanzino/OR-site/raw/5cf3df5a10e27964323be1567d6a1d8d89ae8e8f/releases/featured-images-clean.zip' );

/**
 * Post types the matcher looks at.
 *
 * @return string[]
 */
function omm_post_types() {
	$types = get_post_types(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'names'
	);

	unset( $types['attachment'] );

	// `page` is deliberately excluded: the About/Contact/FAQ/Privacy/Terms
	// pages are site chrome, and listing them as «articles without an image»
	// only produced noise in the report.
	$types[] = 'post';

	return array_values( array_unique( $types ) );
}

/**
 * Normalise a name so «cholecystectomy-overview-1.webp» and
 * «Cholecystectomy_Overview.webp» both become «cholecystectomy-overview».
 *
 * @param string $name Raw file name or slug.
 * @return string
 */
function omm_norm( $name ) {
	$name = (string) $name;
	$name = wp_strip_all_tags( $name );
	$name = strtolower( $name );
	$name = str_replace( array( '_', ' ', '%20' ), '-', $name );
	$name = preg_replace( '/[^a-z0-9-]+/', '-', $name );
	$name = preg_replace( '/-+/', '-', $name );

	// Repeating suffixes, removing one layer per pass so «-featured-1» and
	// «-1-featured» both collapse to the real slug.
	$suffixes = 'featured|image|img|thumb|thumbnail|photo|pic|scaled|rotated|copy|edited|cropped|final|web';

	for ( $pass = 0; $pass < 4; $pass++ ) {
		$before = $name;
		$name   = preg_replace( '/-\d+$/', '', $name );            // WordPress duplicate: -1, -2
		$name   = preg_replace( '/-(' . $suffixes . ')$/', '', $name );
		$name   = preg_replace( '/^(cropped|scaled|copy)-/', '', $name );
		if ( $name === $before ) {
			break;
		}
	}

	return trim( $name, '-' );
}

/**
 * Names that belong to the site chrome, not to an article.
 *
 * @param string $name Raw file name.
 * @return bool
 */
function omm_is_ignorable( $name ) {
	$name = strtolower( (string) $name );
	foreach ( array( 'logo', 'favicon', 'cropped-', 'sprite', 'placeholder' ) as $needle ) {
		if ( false !== strpos( $name, $needle ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Build the slug → post map.
 *
 * @return array<string,WP_Post>
 */
function omm_post_index() {
	$posts = get_posts(
		array(
			'post_type'              => omm_post_types(),
			'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'suppress_filters'       => false,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$index = array();
	foreach ( $posts as $post ) {
		$key = omm_norm( $post->post_name );
		if ( '' === $key ) {
			continue;
		}
		// A published post wins over a draft with the same slug.
		if ( isset( $index[ $key ] ) && 'publish' === $index[ $key ]->post_status && 'publish' !== $post->post_status ) {
			continue;
		}
		$index[ $key ] = $post;
	}

	return $index;
}

/**
 * Build the normalised file name → attachment map.
 *
 * When the same picture was uploaded twice, the newest attachment wins; the
 * report lists every duplicate so nothing is silently ignored.
 *
 * @return array{index:array<string,WP_Post>,dupes:array<string,WP_Post[]>}
 */
function omm_image_index() {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$index = array();
	$dupes = array();

	foreach ( $ids as $id ) {
		$file = get_attached_file( $id );
		$base = $file ? basename( $file ) : get_the_title( $id );
		if ( omm_is_ignorable( $base ) ) {
			continue;
		}
		$key  = omm_norm( pathinfo( (string) $base, PATHINFO_FILENAME ) );
		if ( '' === $key ) {
			continue;
		}

		if ( isset( $index[ $key ] ) ) {
			$dupes[ $key ][] = $index[ $key ];
		} else {
			$dupes[ $key ] = array();
		}

		$index[ $key ] = get_post( $id );
	}

	return array(
		'index' => $index,
		'dupes' => $dupes,
	);
}

/**
 * Work out what the run would do, without changing anything.
 *
 * @param bool $replace Replace a featured image that already exists.
 * @return array{rows:array,missing:array,orphans:array,totals:array}
 */
function omm_plan( $replace = true ) {
	$posts  = omm_post_index();
	$images = omm_image_index();

	$rows     = array();
	$matched  = array();
	$totals   = array(
		'replace' => 0,
		'set'     => 0,
		'same'    => 0,
		'keep'    => 0,
		'orphan'  => 0,
	);

	foreach ( $images['index'] as $key => $att ) {
		if ( ! isset( $posts[ $key ] ) ) {
			$rows[] = array(
				'key'     => $key,
				'post'    => null,
				'att'     => $att,
				'current' => 0,
				'action'  => 'orphan',
			);
			$totals['orphan']++;
			continue;
		}

		$post    = $posts[ $key ];
		$current = (int) get_post_thumbnail_id( $post->ID );
		$same    = $current === (int) $att->ID;

		if ( $same ) {
			$action = 'same';
		} elseif ( ! $current ) {
			$action = 'set';
		} elseif ( $replace ) {
			$action = 'replace';
		} else {
			$action = 'keep';
		}

		$totals[ $action ]++;

		$rows[] = array(
			'key'     => $key,
			'post'    => $post,
			'att'     => $att,
			'current' => $current,
			'action'  => $action,
		);

		$matched[ $key ] = true;
	}

	// Posts that still have no picture at all: the ones the upload missed.
	$missing = array();
	$stale   = array();
	foreach ( $posts as $key => $post ) {
		if ( isset( $matched[ $key ] ) ) {
			continue;
		}
		$current = (int) get_post_thumbnail_id( $post->ID );
		if ( $current ) {
			// Still showing an older picture: the file the user uploaded for it
			// was not found under this slug.
			$stale[] = array(
				'slug'  => $post->post_name,
				'title' => get_the_title( $post ),
				'att'   => $current,
			);
			continue;
		}
		$missing[] = array(
			'slug'  => $post->post_name,
			'title' => get_the_title( $post ),
			'type'  => $post->post_type,
			'state' => get_post_status( $post ),
		);
	}

	usort( $rows, 'omm_cmp_rows' );

	$orphans = array();
	foreach ( $rows as $row ) {
		if ( 'orphan' === $row['action'] ) {
			$orphans[] = $row;
		}
	}

	return array(
		'rows'    => $rows,
		'missing' => $missing,
		'stale'   => $stale,
		'orphans' => $orphans,
		'totals'  => $totals,
		'dupes'   => $images['dupes'],
	);
}

/**
 * Sort rows by slug.
 *
 * @param array $a Row.
 * @param array $b Row.
 * @return int
 */
function omm_cmp_rows( $a, $b ) {
	return strcmp( (string) $a['key'], (string) $b['key'] );
}

/**
 * Apply one row of the plan.
 *
 * @param WP_Post $post     Post.
 * @param WP_Post $att      Attachment.
 * @param string  $action   set|replace|same|keep.
 * @return string Result key for the totals.
 */
function omm_apply_row( $post, $att, $action ) {
	if ( 'same' === $action || 'keep' === $action ) {
		return $action;
	}

	set_post_thumbnail( $post->ID, $att->ID );

	// Alt text matters for SEO and screen readers; only fill it when empty.
	$alt = (string) get_post_meta( $att->ID, '_wp_attachment_image_alt', true );
	if ( '' === $alt ) {
		update_post_meta( $att->ID, '_wp_attachment_image_alt', wp_strip_all_tags( get_the_title( $post ) ) );
	}

	return $action;
}

/**
 * Download the image pack from the repository into a temporary file.
 *
 * @param string $url Remote ZIP.
 * @return string|WP_Error Local path.
 */
function omm_download_pack( $url ) {
	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 90,
			'redirection' => 5,
			'user-agent'  => 'ORSite-Media-Matcher/' . OMM_VERSION . '; ' . home_url(),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error(
			'omm_http',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'دریافت فایل از مخزن انجام نشد (کد %d). اتصال هاست به github.com را بررسی کنید یا از بخش «رسانه» استفاده کنید.', 'orsite-media-matcher' ),
				$code
			)
		);
	}

	$body = wp_remote_retrieve_body( $response );
	if ( strlen( $body ) < 1000 ) {
		return new WP_Error( 'omm_empty', __( 'فایل دریافتی خالی یا ناقص بود.', 'orsite-media-matcher' ) );
	}

	$tmp = wp_tempnam( 'orsite-image-pack.zip' );
	if ( ! $tmp ) {
		return new WP_Error( 'omm_tmp', __( 'ساخت فایل موقت ممکن نشد.', 'orsite-media-matcher' ) );
	}

	// `wp_tempnam()` already gave us a writable path; writing to it directly
	// avoids the credentials prompt some hosts show for WP_Filesystem().
	$written = file_put_contents( $tmp, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	if ( ! $written ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'omm_write', __( 'نوشتن فایل موقت ممکن نشد.', 'orsite-media-matcher' ) );
	}

	return $tmp;
}

/**
 * Import one image file into the media library.
 *
 * @param string $path Local path of the extracted image.
 * @param string $name File name to keep.
 * @param int    $post_id Attach to this post.
 * @return int|WP_Error Attachment ID.
 */
function omm_sideload( $path, $name, $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( $name );
	if ( ! $tmp || ! @copy( $path, $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'omm_copy', __( 'کپی فایل موقت انجام نشد.', 'orsite-media-matcher' ) );
	}

	$file = array(
		'name'     => $name,
		'tmp_name' => $tmp,
	);

	$id = media_handle_sideload( $file, $post_id, null );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $id;
	}

	return (int) $id;
}

/**
 * Download the pack and make sure every post with a matching file in it ends up
 * with that file as its featured image. Existing library files are reused, so
 * running this after a manual upload only fills the gaps.
 *
 * @param bool $replace Replace a different featured image.
 * @return array{rows:array,totals:array}
 */
function omm_run_pack( $replace = true ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$rows   = array();
	$totals = array(
		'added'   => 0,
		'linked'  => 0,
		'replace' => 0,
		'same'    => 0,
		'keep'    => 0,
		'orphan'  => 0,
		'error'   => 0,
	);

	$zip_path = omm_download_pack( OMM_PACK_URL );
	if ( is_wp_error( $zip_path ) ) {
		$rows[] = array( 'key' => '—', 'action' => 'error', 'message' => $zip_path->get_error_message() );
		$totals['error']++;
		return array( 'rows' => $rows, 'totals' => $totals );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		@unlink( $zip_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$rows[] = array( 'key' => '—', 'action' => 'error', 'message' => __( 'افزونه‌ی ZIP روی هاست فعال نیست؛ از هاست بخواهید پسوند zip را فعال کند.', 'orsite-media-matcher' ) );
		$totals['error']++;
		return array( 'rows' => $rows, 'totals' => $totals );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		@unlink( $zip_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$rows[] = array( 'key' => '—', 'action' => 'error', 'message' => __( 'فایل ZIP باز نشد.', 'orsite-media-matcher' ) );
		$totals['error']++;
		return array( 'rows' => $rows, 'totals' => $totals );
	}

	$posts   = omm_post_index();
	$library = omm_image_index();
	$tmp_dir = trailingslashit( get_temp_dir() ) . 'omm-' . wp_generate_password( 6, false ) . '/';
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
		$ext  = strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'webp', 'jpg', 'jpeg', 'png' ), true ) ) {
			continue;
		}

		$key = omm_norm( pathinfo( $base, PATHINFO_FILENAME ) );
		if ( '' === $key ) {
			continue;
		}

		if ( ! isset( $posts[ $key ] ) ) {
			$rows[] = array( 'key' => $key, 'action' => 'orphan', 'message' => __( 'مقاله‌ی هم‌نام پیدا نشد', 'orsite-media-matcher' ) );
			$totals['orphan']++;
			continue;
		}

		$post    = $posts[ $key ];
		$current = (int) get_post_thumbnail_id( $post->ID );
		$att_id  = isset( $library['index'][ $key ] ) ? (int) $library['index'][ $key ]->ID : 0;
		$added   = false;

		if ( ! $att_id ) {
			$local = $tmp_dir . $base;
			if ( true !== $zip->extractTo( $tmp_dir, $entry ) || ! file_exists( $local ) ) {
				$rows[] = array( 'key' => $key, 'action' => 'error', 'message' => __( 'استخراج فایل از بسته انجام نشد.', 'orsite-media-matcher' ) );
				$totals['error']++;
				continue;
			}
			$new = omm_sideload( $local, $key . '.' . $ext, $post->ID );
			@unlink( $local ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_wp_error( $new ) ) {
				$rows[] = array( 'key' => $key, 'action' => 'error', 'message' => $new->get_error_message() );
				$totals['error']++;
				continue;
			}
			$att_id = (int) $new;
			$added  = true;
		}

		if ( $current === $att_id ) {
			$action = 'same';
		} elseif ( ! $current ) {
			$action = $added ? 'added' : 'linked';
		} elseif ( $replace ) {
			$action = 'replace';
		} else {
			$action = 'keep';
		}

		if ( in_array( $action, array( 'added', 'linked', 'replace' ), true ) ) {
			set_post_thumbnail( $post->ID, $att_id );
			$alt = (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true );
			if ( '' === $alt ) {
				update_post_meta( $att_id, '_wp_attachment_image_alt', wp_strip_all_tags( get_the_title( $post ) ) );
			}
		}

		$totals[ $action ]++;

		$rows[] = array(
			'key'     => $key,
			'action'  => $action,
			'message' => get_the_title( $post ),
		);
	}

	$zip->close();
	@unlink( $zip_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( is_dir( $tmp_dir ) ) {
		foreach ( (array) glob( $tmp_dir . '*' ) as $leftover ) {
			@unlink( $leftover ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		@rmdir( $tmp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	return array( 'rows' => $rows, 'totals' => $totals );
}

/**
 * Site-wide picture status: how many articles have a featured image.
 *
 * @return array{total:int,with:int,without:array<int,WP_Post>}
 */
function omm_status() {
	$posts = get_posts(
		array(
			'post_type'              => omm_post_types(),
			'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$with    = 0;
	$without = array();

	foreach ( $posts as $post ) {
		if ( get_post_thumbnail_id( $post->ID ) ) {
			$with++;
		} else {
			$without[] = $post;
		}
	}

	return array(
		'total'   => count( $posts ),
		'with'    => $with,
		'without' => $without,
	);
}

/**
 * Register the screen under the Tools menu.
 */
function omm_admin_menu() {
	add_management_page(
		__( 'اتصال تصاویر به مقاله‌ها', 'orsite-media-matcher' ),
		__( 'اتصال تصاویر به مقاله‌ها', 'orsite-media-matcher' ),
		OMM_CAP,
		OMM_SLUG,
		'omm_render_page'
	);
}
add_action( 'admin_menu', 'omm_admin_menu' );

/**
 * Render the admin screen.
 */
function omm_render_page() {
	if ( ! current_user_can( OMM_CAP ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'orsite-media-matcher' ) );
	}

	$notice = '';
	$plan   = null;
	$pack   = null;
	$ran    = false;

	if ( isset( $_POST['omm_pack_action'] ) ) {
		$nonce = isset( $_POST['omm_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['omm_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'omm_run' ) ) {
			$notice = __( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.', 'orsite-media-matcher' );
		} else {
			$replace = ! empty( $_POST['omm_replace'] );
			$pack    = omm_run_pack( $replace );
			wp_cache_flush();
			$notice  = sprintf(
				/* translators: 1: new files, 2: linked, 3: replaced, 4: errors */
				__( 'از مخزن انجام شد — %1$d تصویر تازه اضافه شد، %2$d تصویر موجود وصل شد، %3$d جایگزین شد، %4$d خطا.', 'orsite-media-matcher' ),
				(int) $pack['totals']['added'],
				(int) $pack['totals']['linked'],
				(int) $pack['totals']['replace'],
				(int) $pack['totals']['error']
			);
		}
	}

	if ( isset( $_POST['omm_action'] ) ) {
		$nonce = isset( $_POST['omm_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['omm_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'omm_run' ) ) {
			$notice = __( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید و یک‌بار دیگر بزنید.', 'orsite-media-matcher' );
		} else {
			$replace = ! empty( $_POST['omm_replace'] );
			// The clicked button carries the intent; a hidden field would make
			// the preview button perform the real run as well.
			$apply   = isset( $_POST['omm_action'] ) && 'apply' === sanitize_key( wp_unslash( $_POST['omm_action'] ) );
			$plan    = omm_plan( $replace );
			$ran     = true;

			if ( $apply ) {
				$done = array(
					'replace' => 0,
					'set'     => 0,
					'same'    => 0,
					'keep'    => 0,
					'orphan'  => 0,
				);
				foreach ( $plan['rows'] as $row ) {
					if ( ! $row['post'] || ! $row['att'] ) {
						continue;
					}
					$key = omm_apply_row( $row['post'], $row['att'], $row['action'] );
					if ( isset( $done[ $key ] ) ) {
						$done[ $key ]++;
					}
				}
				wp_cache_flush();

				$notice = sprintf(
					/* translators: 1: new, 2: replaced, 3: unchanged */
					__( 'انجام شد — %1$d تصویر تازه تنظیم شد، %2$d تصویر قبلی جایگزین شد، %3$d مورد از قبل درست بود.', 'orsite-media-matcher' ),
					(int) $done['set'],
					(int) $done['replace'],
					(int) $done['same']
				);
				$plan = omm_plan( $replace );
			} else {
				$notice = __( 'این فقط پیش‌نمایش بود؛ هیچ تغییری ذخیره نشد.', 'orsite-media-matcher' );
			}
		}
	}

	$labels = array(
		'set'     => __( 'تنظیم می‌شود', 'orsite-media-matcher' ),
		'replace' => __( 'جایگزین می‌شود', 'orsite-media-matcher' ),
		'same'    => __( 'از قبل درست است', 'orsite-media-matcher' ),
		'keep'    => __( 'تصویر فعلی حفظ می‌شود', 'orsite-media-matcher' ),
		'orphan'  => __( 'مقاله‌ی هم‌نام ندارد', 'orsite-media-matcher' ),
	);
	$colors = array(
		'set'     => '#00a32a',
		'replace' => '#2271b1',
		'same'    => '#8c8f94',
		'keep'    => '#996800',
		'orphan'  => '#d63638',
	);
	?>
	<div class="wrap" dir="rtl">
		<h1><?php esc_html_e( 'اتصال تصاویر رسانه به مقاله‌ها', 'orsite-media-matcher' ); ?></h1>

		<p style="max-width:56em;line-height:2">
			<?php esc_html_e( 'این ابزار تصاویری که در «رسانه» آپلود کرده‌اید را بر اساس نام فایل به مقاله‌ی هم‌نام وصل می‌کند و به‌عنوان تصویر شاخص می‌گذارد؛ همان تصویری که در فهرست‌ها، صفحه‌ی اصلی و کارت‌های آرشیو دیده می‌شود. نام فایل باید همان نامک انگلیسی مقاله باشد، مثل cholecystectomy-overview.webp — پسوند و پسوندهای تکراری مثل ‎-1 خودکار نادیده گرفته می‌شوند.', 'orsite-media-matcher' ); ?>
		</p>
		<p style="max-width:56em;line-height:2;color:#50575e">
			<?php esc_html_e( 'بار اول با «پیش‌نمایش» اجرا کنید و جدول را ببینید؛ فقط وقتی گزارش درست بود، دکمه‌ی اجرا را بزنید. اجرای دوباره بی‌خطر است.', 'orsite-media-matcher' ); ?>
		</p>

		<?php if ( $notice ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<?php
		$status = omm_status();
		$done   = ( 0 === count( $status['without'] ) );
		?>
		<div style="max-width:56em;margin:16px 0;padding:14px 18px;border-radius:10px;border:1px solid <?php echo $done ? '#00a32a' : '#dba617'; ?>;background:<?php echo $done ? '#f0fbf1' : '#fdf9e8'; ?>">
			<p style="margin:0;font-size:15px">
				<?php
				printf(
					/* translators: 1: with image, 2: total */
					esc_html__( 'وضعیت سایت: %1$d از %2$d مقاله تصویر شاخص دارند.', 'orsite-media-matcher' ),
					(int) $status['with'],
					(int) $status['total']
				);
				?>
				<strong>
					<?php echo $done
						? esc_html__( 'کار تمام است ✓', 'orsite-media-matcher' )
						: esc_html( sprintf( /* translators: %d */ __( '%d مقاله هنوز تصویر ندارد.', 'orsite-media-matcher' ), count( $status['without'] ) ) );
					?>
				</strong>
			</p>
			<?php if ( ! $done ) : ?>
				<p style="margin:8px 0 0;color:#50575e">
					<?php esc_html_e( 'برای پر کردن همه‌ی جای خالی‌ها، دکمه‌ی «تصاویر را از مخزن بگیر و کامل کن» را بزنید.', 'orsite-media-matcher' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 18px;max-width:56em;margin:18px 0">
			<?php wp_nonce_field( 'omm_run', 'omm_nonce' ); ?>
			<p style="margin-top:0">
				<label>
					<input type="checkbox" name="omm_replace" value="1" <?php checked( true ); ?>>
					<?php esc_html_e( 'اگر مقاله از قبل تصویر شاخص دارد، با تصویر آپلودشده عوض کن', 'orsite-media-matcher' ); ?>
				</label>
			</p>
			<p style="margin-bottom:0">
				<button type="submit" name="omm_action" value="preview" class="button">
					<?php esc_html_e( 'پیش‌نمایش (بدون تغییر)', 'orsite-media-matcher' ); ?>
				</button>
				<button type="submit" name="omm_action" value="apply" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'تصویر شاخص مقاله‌ها تنظیم یا جایگزین شود؟', 'orsite-media-matcher' ) ); ?>')">
					<?php esc_html_e( 'اجرا کن', 'orsite-media-matcher' ); ?>
				</button>
			</p>
		</form>

		<hr style="margin:30px 0 22px;max-width:56em">

		<h2><?php esc_html_e( 'تکمیل خودکار از مخزن (بدون آپلود دستی)', 'orsite-media-matcher' ); ?></h2>
		<p style="max-width:56em;line-height:2">
			<?php esc_html_e( 'این دکمه بستهٔ تصاویر را مستقیم از مخزن پروژه می‌گیرد. هر تصویری که در رسانه نباشد اضافه می‌شود، تصویرهای موجود دوباره آپلود نمی‌شوند و فقط جای خالی‌ها پر می‌شود. یعنی همان دو تصویری که جا افتاد هم خودکار سر جایشان می‌نشینند. اجرای دوباره بی‌خطر است.', 'orsite-media-matcher' ); ?>
		</p>
		<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px 18px;max-width:56em;margin:14px 0 26px">
			<?php wp_nonce_field( 'omm_run', 'omm_nonce' ); ?>
			<p style="margin-top:0">
				<label>
					<input type="checkbox" name="omm_replace" value="1" <?php checked( true ); ?>>
					<?php esc_html_e( 'اگر مقاله تصویر شاخص دیگری دارد، با تصویر این بسته عوض کن', 'orsite-media-matcher' ); ?>
				</label>
			</p>
			<p style="margin-bottom:0">
				<button type="submit" name="omm_pack_action" value="1" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'تصاویر از مخزن گرفته و روی مقاله‌ها تنظیم شوند؟', 'orsite-media-matcher' ) ); ?>')">
					<?php esc_html_e( 'تصاویر را از مخزن بگیر و کامل کن', 'orsite-media-matcher' ); ?>
				</button>
			</p>
		</form>

		<?php if ( $pack ) : ?>
			<h2><?php esc_html_e( 'گزارش تکمیل از مخزن', 'orsite-media-matcher' ); ?></h2>
			<table class="widefat striped" style="max-width:1000px">
				<thead>
					<tr>
						<th style="width:34%"><?php esc_html_e( 'نامک', 'orsite-media-matcher' ); ?></th>
						<th style="width:26%"><?php esc_html_e( 'نتیجه', 'orsite-media-matcher' ); ?></th>
						<th><?php esc_html_e( 'مقاله', 'orsite-media-matcher' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$pack_labels = array(
					'added'   => __( 'تازه اضافه شد', 'orsite-media-matcher' ),
					'linked'  => __( 'وصل شد', 'orsite-media-matcher' ),
					'replace' => __( 'جایگزین شد', 'orsite-media-matcher' ),
					'same'    => __( 'از قبل درست بود', 'orsite-media-matcher' ),
					'keep'    => __( 'بدون تغییر', 'orsite-media-matcher' ),
					'orphan'  => __( 'مقاله‌ی هم‌نام ندارد', 'orsite-media-matcher' ),
					'error'   => __( 'خطا', 'orsite-media-matcher' ),
				);
				foreach ( $pack['rows'] as $row ) :
					?>
					<tr>
						<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
						<td><?php echo esc_html( isset( $pack_labels[ $row['action'] ] ) ? $pack_labels[ $row['action'] ] : $row['action'] ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( $plan ) : ?>
			<h2><?php esc_html_e( 'خلاصه', 'orsite-media-matcher' ); ?></h2>
			<p>
				<?php
				foreach ( $labels as $key => $label ) {
					printf(
						'<span style="display:inline-block;margin-inline-end:18px">%s: <strong style="color:%s">%d</strong></span>',
						esc_html( $label ),
						esc_attr( $colors[ $key ] ),
						(int) $plan['totals'][ $key ]
					);
				}
				?>
			</p>

			<h2><?php esc_html_e( 'جدول تطبیق', 'orsite-media-matcher' ); ?></h2>
			<table class="widefat striped" style="max-width:1000px">
				<thead>
					<tr>
						<th style="width:24%"><?php esc_html_e( 'نامک', 'orsite-media-matcher' ); ?></th>
						<th style="width:26%"><?php esc_html_e( 'مقاله', 'orsite-media-matcher' ); ?></th>
						<th style="width:26%"><?php esc_html_e( 'فایل رسانه', 'orsite-media-matcher' ); ?></th>
						<th><?php esc_html_e( 'نتیجه', 'orsite-media-matcher' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $plan['rows'] as $row ) : ?>
					<?php
					$dup = isset( $plan['dupes'][ $row['key'] ] ) && ! empty( $plan['dupes'][ $row['key'] ] );
					?>
					<tr>
						<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
						<td>
							<?php
							if ( $row['post'] ) {
								printf(
									'%s <span style="color:#8c8f94">(%s)</span>',
									esc_html( get_the_title( $row['post'] ) ),
									esc_html( $row['post']->post_type )
								);
							} else {
								echo '<span style="color:#8c8f94">—</span>';
							}
							?>
						</td>
						<td>
							<?php echo esc_html( $row['att'] ? get_the_title( $row['att'] ) : '—' ); ?>
							<?php if ( $dup ) : ?>
								<br><span style="color:#996800;font-size:12px">
									<?php
									printf(
										/* translators: %d: number of extra copies */
										esc_html__( 'نسخه‌ی تکراری هم هست (%d)؛ جدیدترین انتخاب شد', 'orsite-media-matcher' ),
										count( $plan['dupes'][ $row['key'] ] )
									);
									?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<span style="color:<?php echo esc_attr( $colors[ $row['action'] ] ); ?>;font-weight:600">
								<?php echo esc_html( $labels[ $row['action'] ] ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $plan['missing'] ) ) : ?>
				<h2 style="margin-top:26px"><?php esc_html_e( 'مقاله‌هایی که هنوز تصویر ندارند', 'orsite-media-matcher' ); ?></h2>
				<p style="max-width:56em;line-height:1.9">
					<?php esc_html_e( 'این‌ها مقاله‌هایی هستند که نه تصویر شاخص دارند و نه فایل هم‌نامشان در رسانه پیدا شد — همان‌هایی که هنگام آپلود جا افتادند. فهرست زیر را کپی کنید تا فایل‌هایشان دوباره ساخته شود.', 'orsite-media-matcher' ); ?>
				</p>
				<p><strong><?php echo esc_html( sprintf( /* translators: %d count */ __( '%d مورد', 'orsite-media-matcher' ), count( $plan['missing'] ) ) ); ?></strong></p>
				<table class="widefat striped" style="max-width:1000px">
					<thead>
						<tr>
							<th style="width:30%"><?php esc_html_e( 'نامک', 'orsite-media-matcher' ); ?></th>
							<th><?php esc_html_e( 'عنوان', 'orsite-media-matcher' ); ?></th>
							<th style="width:14%"><?php esc_html_e( 'نوع', 'orsite-media-matcher' ); ?></th>
							<th style="width:14%"><?php esc_html_e( 'وضعیت', 'orsite-media-matcher' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $plan['missing'] as $m ) : ?>
						<tr>
							<td><code><?php echo esc_html( $m['slug'] ); ?></code></td>
							<td><?php echo esc_html( $m['title'] ); ?></td>
							<td><?php echo esc_html( $m['type'] ); ?></td>
							<td><?php echo esc_html( $m['state'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top:10px">
					<textarea readonly rows="6" style="width:100%;max-width:1000px;font-family:monospace;direction:ltr" onclick="this.select()"><?php
						echo esc_textarea( implode( "\n", wp_list_pluck( $plan['missing'], 'slug' ) ) );
					?></textarea>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $plan['stale'] ) ) : ?>
				<h2 style="margin-top:26px"><?php esc_html_e( 'مقاله‌هایی که تصویر دارند ولی فایل تازه‌شان پیدا نشد', 'orsite-media-matcher' ); ?></h2>
				<p style="max-width:56em;line-height:1.9">
					<?php esc_html_e( 'این مقاله‌ها تصویر شاخص دارند، اما هیچ فایل آپلودشده‌ای با نامک آن‌ها پیدا نشد؛ یعنی احتمالاً همان تصویر قدیمی روی‌شان مانده است. اگر تصویرشان باید عوض شود، فایل تازه را با همین نامک در رسانه آپلود کنید و این صفحه را دوباره اجرا کنید.', 'orsite-media-matcher' ); ?>
				</p>
				<table class="widefat striped" style="max-width:1000px">
					<thead>
						<tr>
							<th style="width:30%"><?php esc_html_e( 'نامک', 'orsite-media-matcher' ); ?></th>
							<th><?php esc_html_e( 'عنوان', 'orsite-media-matcher' ); ?></th>
							<th style="width:30%"><?php esc_html_e( 'تصویر فعلی', 'orsite-media-matcher' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $plan['stale'] as $st ) : ?>
						<tr>
							<td><code><?php echo esc_html( $st['slug'] ); ?></code></td>
							<td><?php echo esc_html( $st['title'] ); ?></td>
							<td><?php echo esc_html( get_the_title( $st['att'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top:10px">
					<textarea readonly rows="4" style="width:100%;max-width:1000px;font-family:monospace;direction:ltr" onclick="this.select()"><?php
						echo esc_textarea( implode( "\n", wp_list_pluck( $plan['stale'], 'slug' ) ) );
					?></textarea>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $plan['orphans'] ) ) : ?>
				<h2 style="margin-top:26px"><?php esc_html_e( 'تصاویری که مقاله‌ی هم‌نام ندارند', 'orsite-media-matcher' ); ?></h2>
				<p style="max-width:56em;line-height:1.9">
					<?php esc_html_e( 'این فایل‌ها در رسانه هستند ولی مقاله‌ای با همان نامک پیدا نشد. اگر مقاله‌اش هنوز ایمپورت نشده، بعد از ایمپورت دوباره این صفحه را اجرا کنید. اگر نام فایل اشتباه است، نامش را در رسانه اصلاح کنید.', 'orsite-media-matcher' ); ?>
				</p>
				<ul style="max-width:1000px;columns:3;font-family:monospace;direction:ltr">
					<?php foreach ( $plan['orphans'] as $row ) : ?>
						<li><?php echo esc_html( $row['key'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

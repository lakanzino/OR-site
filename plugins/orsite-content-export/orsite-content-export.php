<?php
/**
 * Plugin Name:       ORSite — خروجی کامل مقاله‌ها
 * Plugin URI:        https://github.com/lakanzino/OR-site
 * Description:       همهٔ مقاله‌ها، برگه‌ها و پیش‌نویس‌های سایت را با عنوان، نامک، لینک، تاریخ، دسته‌ها، برچسب‌ها، تعداد کلمه، متن، تصویر شاخص و متادیتا در یک فایل JSON یا CSV دانلود می‌کند. فقط برای مدیر سایت.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            OR Site
 * License:           GPL-2.0-or-later
 * Text Domain:       orsite-content-export
 *
 * @package ORSite_Content_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OCE_VERSION', '1.0.0' );
define( 'OCE_CAP', 'manage_options' );
define( 'OCE_ACTION', 'oce_export' );
define( 'OCE_PAGE', 'orsite-content-export' );

/**
 * Post types to export. Extend with the `oce_post_types` filter if needed.
 *
 * @return string[]
 */
function oce_post_types() {
	$types = array( 'surgery', 'patient_guide', 'or_environment', 'student_content', 'glossary', 'post', 'page', 'disease' );
	return apply_filters( 'oce_post_types', $types );
}

/**
 * Statuses to export: published, drafts, pending, private and scheduled.
 *
 * @return string[]
 */
function oce_statuses() {
	return array( 'publish', 'draft', 'pending', 'private', 'future' );
}

add_action(
	'admin_menu',
	static function () {
		add_management_page(
			'خروجی کامل مقاله‌ها',
			'خروجی مقاله‌ها',
			OCE_CAP,
			OCE_PAGE,
			'oce_render_page'
		);
	}
);

/**
 * Admin page with the two download buttons.
 */
function oce_render_page() {
	if ( ! current_user_can( OCE_CAP ) ) {
		wp_die( esc_html__( 'دسترسی ندارید.', 'orsite-content-export' ) );
	}
	$json_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . OCE_ACTION . '&format=json' ), OCE_ACTION );
	$csv_url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . OCE_ACTION . '&format=csv' ), OCE_ACTION );
	$count    = oce_count();
	?>
	<div class="wrap" dir="rtl">
		<h1>خروجی کامل مقاله‌ها</h1>
		<p>این ابزار همهٔ نوشته‌های سایت (منتشرشده، پیش‌نویس، در انتظار بازبینی، خصوصی و زمان‌بندی‌شده) را دانلود می‌کند.</p>
		<p><strong>تعداد کل:</strong> <?php echo esc_html( number_format_i18n( $count ) ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $json_url ); ?>">دانلود JSON (کامل: متن، متادیتا، دسته‌ها)</a>
			<a class="button" href="<?php echo esc_url( $csv_url ); ?>">دانلود CSV (فهرست خلاصه، برای Excel)</a>
		</p>
		<p class="description">فایل JSON همان چیزی است که برای بررسی دقیق لازم است؛ فایل CSV برای مرور سریع فهرست عنوان‌ها است.</p>
	</div>
	<?php
}

/**
 * Count of exportable posts.
 *
 * @return int
 */
function oce_count() {
	$total = 0;
	foreach ( oce_post_types() as $type ) {
		$obj = wp_count_posts( $type );
		foreach ( oce_statuses() as $status ) {
			$total += isset( $obj->$status ) ? (int) $obj->$status : 0;
		}
	}
	return $total;
}

/**
 * Build one record per post.
 *
 * @return array[]
 */
function oce_collect() {
	$query = new WP_Query(
		array(
			'post_type'              => oce_post_types(),
			'post_status'            => oce_statuses(),
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		)
	);

	$records = array();
	foreach ( $query->posts as $post ) {
		$records[] = oce_record( $post );
	}
	return $records;
}

/**
 * Full record for one post.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function oce_record( $post ) {
	$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_content ) ) );
	$words = $plain === '' ? 0 : count( preg_split( '/\s+/u', $plain ) );

	$terms = array();
	foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $tax ) {
		$list = get_the_terms( $post->ID, $tax );
		if ( empty( $list ) || is_wp_error( $list ) ) {
			continue;
		}
		$terms[ $tax ] = array_map(
			static function ( $t ) {
				return array( 'name' => $t->name, 'slug' => $t->slug );
			},
			$list
		);
	}

	$meta = array();
	foreach ( get_post_meta( $post->ID ) as $key => $values ) {
		// Skip WordPress internals (lock, edit state, revisions) but keep the theme's own `_hj_*` and other custom meta.
		if ( 0 === strpos( $key, '_edit_' ) || 0 === strpos( $key, '_wp_' ) ) {
			continue;
		}
		$value = maybe_unserialize( $values[0] );
		$meta[ $key ] = $value;
	}

	$author    = get_userdata( $post->post_author );
	$thumb_url = get_the_post_thumbnail_url( $post, 'full' );

	return array(
		'id'            => (int) $post->ID,
		'post_type'     => $post->post_type,
		'status'        => $post->post_status,
		'title'         => get_the_title( $post ),
		'slug'          => $post->post_name,
		'url'           => get_permalink( $post ),
		'date'          => get_post_time( 'c', true, $post ),
		'modified'      => get_post_modified_time( 'c', true, $post ),
		'author'        => $author ? $author->display_name : '',
		'excerpt'       => wp_strip_all_tags( $post->post_excerpt ),
		'word_count'    => $words,
		'featured_image' => $thumb_url ? $thumb_url : '',
		'content_html'  => $post->post_content,
		'content_text'  => $plain,
		'taxonomies'    => $terms,
		'meta'          => $meta,
	);
}

/**
 * Handle the download request.
 */
function oce_handle_export() {
	if ( ! current_user_can( OCE_CAP ) ) {
		wp_die( esc_html__( 'دسترسی ندارید.', 'orsite-content-export' ) );
	}
	check_admin_referer( OCE_ACTION );

	$format  = isset( $_GET['format'] ) && 'csv' === $_GET['format'] ? 'csv' : 'json';
	$records = oce_collect();
	$stamp   = gmdate( 'Y-m-d-His' );

	// Make sure nothing (notices, BOM from other plugins) leaks into the file.
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}

	nocache_headers();

	if ( 'csv' === $format ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="orsite-articles-' . $stamp . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel shows Persian correctly.
		fputcsv( $out, array( 'id', 'post_type', 'status', 'title', 'slug', 'url', 'date', 'modified', 'author', 'word_count', 'featured_image', 'taxonomies', 'excerpt' ) );
		foreach ( $records as $r ) {
			$tax_flat = array();
			foreach ( $r['taxonomies'] as $tax => $list ) {
				foreach ( $list as $t ) {
					$tax_flat[] = $tax . ':' . $t['name'];
				}
			}
			fputcsv(
				$out,
				array(
					$r['id'],
					$r['post_type'],
					$r['status'],
					$r['title'],
					$r['slug'],
					$r['url'],
					$r['date'],
					$r['modified'],
					$r['author'],
					$r['word_count'],
					$r['featured_image'],
					implode( ' | ', $tax_flat ),
					$r['excerpt'],
				)
			);
		}
		fclose( $out );
	} else {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="orsite-articles-' . $stamp . '.json"' );
		$payload = array(
			'exported_at'  => gmdate( 'c' ),
			'site_url'     => home_url( '/' ),
			'plugin'       => 'orsite-content-export ' . OCE_VERSION,
			'count'        => count( $records ),
			'post_types'   => oce_post_types(),
			'articles'     => $records,
		);
		echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE );
	}
	exit;
}
add_action( 'admin_post_' . OCE_ACTION, 'oce_handle_export' );

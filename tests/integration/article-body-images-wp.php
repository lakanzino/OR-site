<?php
/** Disposable WordPress integration test; never run against production. */
require_once '/wordpress/wp-load.php';
require_once '/wordpress/wp-content/themes/lax-theme-child/inc/article-importer.php';
register_post_type( 'patient_guide', array( 'public' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ) ) );
wp_set_current_user( 1 );
$results = array();
function hj_test( $label, $ok, $detail = '' ) {
	global $results;
	$results[] = array( 'label' => $label, 'ok' => (bool) $ok, 'detail' => $detail );
}
function hj_test_package( $name, $manifest, $html, $files = array() ) {
	$path = '/hjt/' . $name . '.zip';
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new Exception( 'zip creation failed' );
	}
	$zip->addFromString( 'article.json', wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE ) );
	$zip->addFromString( 'content.html', $html );
	foreach ( $files as $filename => $data ) { $zip->addFromString( $filename, $data ); }
	$zip->close();
	return $path;
}
function hj_count_attachments() {
	return (int) wp_count_posts( 'attachment' )->inherit;
}
try {
	$webp1 = base64_decode( 'UklGRjQAAABXRUJQVlA4ICgAAACQAQCdASoDAAIAAUAmJZgCdLoAA5gA/u/uY/DlT6dnd/4DdfFsvgAA' );
	$webp2 = base64_decode( 'UklGRjQAAABXRUJQVlA4ICgAAACQAQCdASoDAAEAAUAmJZgCdLoAA5gA/u/uY/DlT6dnd/4DdfFsvgAA' );
	$wrong = base64_decode( 'UklGRjQAAABXRUJQVlA4ICgAAACQAQCdASoBAAEAAUAmJZgCdLoAA5gA/u/uY/DlT6dnd/4DdfFsvgAA' );
	$manifest = array( 'package_version' => 1, 'slug' => 'inline-test', 'title' => 'مقاله نمونه', 'post_type' => 'patient_guide', 'post_status' => 'draft', 'content_file' => 'content.html', 'body_images' => array( array( 'file' => 'infographic-01.webp', 'alt' => 'تصویر آموزشی اول' ), array( 'file' => 'infographic-02.webp', 'alt' => 'تصویر آموزشی دوم' ) ) );
	$html = '<p>قبل از تصویر</p><figure><img src="infographic-01.webp" alt="old" srcset="infographic-01.webp 2x"><figcaption>توضیح اول</figcaption></figure><p>میانه</p><img src="infographic-02.webp" alt="old"><p>پایان</p>';
	$files = array( 'infographic-01.webp' => $webp1, 'infographic-02.webp' => $webp2, 'featured.webp' => $webp1 );
	$manifest['featured_image'] = array( 'file' => 'featured.webp', 'alt' => 'تصویر شاخص آزمایشی' );
	$path = hj_test_package( 'good', $manifest, $html, $files );
	$before = hj_count_attachments();
	$preview = hj_importer_import_package( $path, 'test', array( 'dry_run' => true, 'status_mode' => 'publish' ) );
	hj_test( 'dry-run has no media writes', ! is_wp_error( $preview ) && hj_count_attachments() === $before && ! empty( $preview['dry_run'] ), is_wp_error( $preview ) ? $preview->get_error_message() : '' );
	$res = hj_importer_import_package( $path, 'test', array( 'status_mode' => 'publish', 'purge_cache' => false ) );
	if ( is_wp_error( $res ) ) { throw new Exception( 'import failed: ' . $res->get_error_message() ); }
	$id = $res['post_id'];
	$post = get_post( $id );
	$media = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => $id, 'numberposts' => -1 ) );
	hj_test( 'imports one draft despite publish option', 'draft' === $post->post_status );
	hj_test( 'two inline images and featured attached to draft', 3 === count( $media ) && (bool) get_post_thumbnail_id( $id ), (string) count( $media ) );
	hj_test( 'rewrites only image sources and alt, preserves text', false !== strpos( $post->post_content, '/wp-content/uploads/' ) && false !== strpos( $post->post_content, 'تصویر آموزشی اول' ) && false !== strpos( $post->post_content, 'قبل از تصویر' ) && false !== strpos( $post->post_content, 'توضیح اول' ) && false === strpos( $post->post_content, 'srcset=' ), $post->post_content );
	$body_ids = array();
	foreach ( $media as $item ) {
		if ( get_post_meta( $item->ID, '_hj_body_sha256', true ) ) {
			$body_ids[] = $item->ID;
			$url = wp_get_attachment_url( $item->ID );
			hj_test( 'inline media URL resolves into article and file exists', false !== strpos( $post->post_content, $url ) && is_file( get_attached_file( $item->ID ) ) && 'image/webp' === get_post_mime_type( $item->ID ), $url );
		}
	}
	$prior_count = hj_count_attachments();
	$again = hj_importer_import_package( $path, 'test', array( 'duplicate_mode' => 'smart_update', 'purge_cache' => false ) );
	hj_test( 'reimport reuses media without duplicate uploads', ! is_wp_error( $again ) && (int) $again['post_id'] === $id && hj_count_attachments() === $prior_count, is_wp_error( $again ) ? $again->get_error_message() : '' );
	$changed = $manifest; $changed['slug'] = 'bad-ratio';
	$bad = hj_test_package( 'bad-ratio', $changed, $html, array_merge( $files, array( 'infographic-01.webp' => $wrong ) ) );
	$count = hj_count_attachments();
	$badres = hj_importer_import_package( $bad, 'test', array() );
	hj_test( 'bad image ratio rejected before writes', is_wp_error( $badres ) && 'body_images_ratio' === $badres->get_error_code() && hj_count_attachments() === $count && ! hj_importer_find_existing_post( 'bad-ratio', 'patient_guide' ) );
	$changed['slug'] = 'missing-file';
	$missing = hj_test_package( 'missing', $changed, $html, array( 'infographic-01.webp' => $webp1 ) );
	$r = hj_importer_import_package( $missing, 'test', array() );
	hj_test( 'missing image rejected without media', is_wp_error( $r ) && 'body_images_missing' === $r->get_error_code() && hj_count_attachments() === $count );
	$changed['slug'] = 'bad-html';
	$badhtml = hj_test_package( 'bad-html', $changed, $html . '<img src="https://example.com/outside.webp">', $files );
	$r = hj_importer_import_package( $badhtml, 'test', array() );
	hj_test( 'unlisted image reference rejected', is_wp_error( $r ) && 'body_images_reference' === $r->get_error_code() );
	$changed['slug'] = 'bad-name'; $changed['body_images'][0]['file'] = '../escape.webp';
	$badname = hj_test_package( 'bad-name', $changed, $html, $files );
	$r = hj_importer_import_package( $badname, 'test', array() );
	hj_test( 'path traversal in manifest rejected', is_wp_error( $r ) && 'body_images_name' === $r->get_error_code() );
	$changed = $manifest; $changed['slug'] = 'bad-size';
	$oversize = hj_test_package( 'bad-size', $changed, $html, array_merge( $files, array( 'infographic-01.webp' => $webp1 . str_repeat( 'x', 100000 ) ) ) );
	$r = hj_importer_import_package( $oversize, 'test', array() );
	hj_test( 'image at or above size limit rejected', is_wp_error( $r ) && 'body_images_format' === $r->get_error_code() );
	$changed = $manifest; $changed['slug'] = 'featured-missing';
	$missing_featured = hj_test_package( 'missing-featured', $changed, $html, array( 'infographic-01.webp' => $webp1, 'infographic-02.webp' => $webp2 ) );
	$r = hj_importer_import_package( $missing_featured, 'test', array() );
	hj_test( 'missing featured is rejected before writes', is_wp_error( $r ) && 'body_images_featured' === $r->get_error_code() && hj_count_attachments() === $count && ! hj_importer_find_existing_post( 'featured-missing', 'patient_guide' ) );
	$changed['slug'] = 'featured-upload-failure';
	$featured_fail = hj_test_package( 'featured-fail', $changed, $html, $files );
	$fail_featured = function( $upload ) { return false !== strpos( $upload['name'], '-featured.' ) ? 'injected failure' : $upload; };
	add_filter( 'wp_upload_bits', $fail_featured );
	$r = hj_importer_import_package( $featured_fail, 'test', array( 'purge_cache' => false ) );
	remove_filter( 'wp_upload_bits', $fail_featured );
	hj_test( 'featured upload failure rolls back new draft and media', is_wp_error( $r ) && 'body_images_featured_failed' === $r->get_error_code() && ! hj_importer_find_existing_post( 'featured-upload-failure', 'patient_guide' ) && hj_count_attachments() === $count );
	$old_id = wp_insert_post( array( 'post_type' => 'patient_guide', 'post_name' => 'parent-failure', 'post_title' => 'پیشین', 'post_content' => 'متن اولیه', 'post_status' => 'draft' ) );
	$changed['slug'] = 'parent-failure';
	$parent_fail = hj_test_package( 'parent-fail', $changed, $html, $files );
	$fail_parent = function( $empty, $arr ) { return ! empty( $arr['ID'] ) && 'attachment' === $arr['post_type'] && (int) $arr['post_parent'] > 0 ? true : $empty; };
	add_filter( 'wp_insert_post_empty_content', $fail_parent, 10, 2 );
	$r = hj_importer_import_package( $parent_fail, 'test', array( 'duplicate_mode' => 'smart_update', 'purge_cache' => false ) );
	remove_filter( 'wp_insert_post_empty_content', $fail_parent, 10 );
	hj_test( 'parent failure restores existing draft and removes staged media', is_wp_error( $r ) && 'body_images_parent' === $r->get_error_code() && 'متن اولیه' === get_post_field( 'post_content', $old_id ) && 'پیشین' === get_post_field( 'post_title', $old_id ) && hj_count_attachments() === $count, is_wp_error( $r ) ? $r->get_error_message() : '' );
	$unsafe = hj_test_package( 'unsafe-zip', $manifest, $html, array_merge( $files, array( '../escape.txt' => 'unsafe' ) ) );
	$r = hj_importer_import_package( $unsafe, 'test', array() );
	hj_test( 'ZIP traversal rejected', is_wp_error( $r ) && 'unsafe_path' === $r->get_error_code() );
	$published = wp_insert_post( array( 'post_type' => 'patient_guide', 'post_name' => 'protected-live', 'post_title' => 'موجود', 'post_content' => 'متن قبلی', 'post_status' => 'publish' ) );
	$changed = $manifest; $changed['slug'] = 'protected-live';
	$protected = hj_test_package( 'protected', $changed, $html, $files );
	$r = hj_importer_import_package( $protected, 'test', array( 'duplicate_mode' => 'smart_update' ) );
	hj_test( 'published article protected', is_wp_error( $r ) && 'body_images_protect' === $r->get_error_code() && 'متن قبلی' === get_post_field( 'post_content', $published ) );
	$r = hj_importer_import_package( $protected, 'test', array( 'duplicate_mode' => 'create_copy' ) );
	hj_test( 'copy of existing article remains draft', ! is_wp_error( $r ) && 'draft' === get_post_status( $r['post_id'] ) && (int) $r['post_id'] !== $published );
	$legacy = $manifest; $legacy['slug'] = 'legacy-article'; unset( $legacy['body_images'] );
	$old = hj_test_package( 'legacy', $legacy, '<p>متن بسته قدیمی</p>', array( 'featured.webp' => $webp1 ) );
	$r = hj_importer_import_package( $old, 'test', array() );
	hj_test( 'legacy package without body images still imports', ! is_wp_error( $r ) && false !== strpos( get_post_field( 'post_content', $r['post_id'] ), 'متن بسته قدیمی' ), is_wp_error( $r ) ? $r->get_error_message() : '' );
} catch ( Throwable $e ) {
	hj_test( 'integration exception', false, $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
}
file_put_contents( '/hjt/report.json', wp_json_encode( $results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

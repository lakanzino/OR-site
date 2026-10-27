<?php
/**
 * Executable regression tests with a filesystem-backed WordPress media double.
 * No production site or database is contacted. Run: php tests/inline-images.php
 */
error_reporting( E_ALL );
$repo = dirname( __DIR__ );
$tmp = sys_get_temp_dir() . '/orsite-inline-' . uniqid();
mkdir( $tmp . '/wp-admin/includes', 0777, true );
file_put_contents( $tmp . '/wp-admin/includes/file.php', '<?php' );
file_put_contents( $tmp . '/wp-admin/includes/image.php', '<?php' );
mkdir( $tmp . '/uploads' );
define( 'ABSPATH', $tmp . '/' );
$media = $posts = $meta = array();
$upload_fail = $save_fail = $deny = false;
$checks = 0;
class WP_Error {
	public $code; public $message;
	function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	function get_error_message() { return $this->message; }
}
function add_action( ...$args ) {}
function do_action( ...$args ) {}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '-', $s ) ); }
function sanitize_text_field( $s ) { return strip_tags( $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function esc_url_raw( $s ) { return $s; }
function wp_upload_dir() { global $tmp; return array( 'path' => $tmp . '/uploads', 'error' => false ); }
function wp_unique_filename( $dir, $name ) {
	$base = pathinfo( $name, PATHINFO_FILENAME ); $ext = pathinfo( $name, PATHINFO_EXTENSION ); $i = 0;
	while ( is_file( $dir . '/' . $name ) ) { $name = $base . '-' . ++$i . '.' . $ext; }
	return $name;
}
function wp_upload_bits( $name, $unused, $bits ) {
	global $tmp, $upload_fail;
	if ( $upload_fail ) { return array( 'error' => 'Disk full' ); }
	$file = $tmp . '/uploads/' . $name; file_put_contents( $file, $bits );
	return array( 'file' => $file, 'url' => 'https://example.test/wp-content/uploads/' . $name, 'error' => false );
}
function wp_check_filetype( ...$args ) { return array( 'type' => 'image/webp' ); }
function wp_insert_attachment( $data, $file, $parent ) {
	global $media; $id = 1000 + count( $media );
	$media[$id] = array( 'file' => $file, 'url' => $data['guid'], 'parent' => $parent ); return $id;
}
function wp_generate_attachment_metadata( ...$args ) { return array(); }
function wp_update_attachment_metadata( ...$args ) {}
function update_post_meta( $id, $key, $value ) { global $meta; $meta[$id][$key] = $value; }
function get_post_meta( $id, $key, $single ) { global $meta; return $meta[$id][$key] ?? ''; }
function metadata_exists( $type, $id, $key ) { global $meta; return isset( $meta[$id][$key] ); }
function get_attached_file( $id ) { global $media; return $media[$id]['file'] ?? false; }
function wp_get_attachment_url( $id ) { global $media; return $media[$id]['url'] ?? false; }
function get_posts( $args ) {
	global $media, $meta; $found = array();
	foreach ( array_reverse( array_keys( $media ) ) as $id ) {
		$match = true;
		foreach ( $args['meta_query'] as $q ) { $match = $match && ( $meta[$id][$q['key']] ?? null ) === $q['value']; }
		if ( $match ) { $found[] = (object) array( 'ID' => $id ); break; }
	}
	return $found;
}
function wp_update_post( $data, $error = false ) {
	global $posts, $media, $save_fail;
	if ( isset( $media[$data['ID']] ) ) { return $data['ID']; }
	if ( $save_fail ) { return new WP_Error( 'db', 'Database write failed' ); }
	foreach ( $data as $k => $v ) { $posts[$data['ID']]->$k = $v; } return $data['ID'];
}
function get_post( $id ) { global $posts; return isset( $posts[$id] ) ? clone $posts[$id] : null; }
function get_post_field( $field, $id, $context ) { global $posts; return $posts[$id]->$field; }
function current_user_can( ...$args ) { global $deny; return ! $deny; }
function get_stylesheet_directory() { global $repo; return $repo . '/themes/lax-theme-child'; }
function get_permalink( $id ) { global $posts; return 'https://example.test/surgery/' . $posts[$id]->post_name . '/'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function wp_slash( $v ) { return $v; } // Double stores raw values, unlike WP's slashed API boundary.
function clean_post_cache( ...$args ) {}
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new Exception( 'FAIL: ' . $message ); }
	++$checks;
}
require $repo . '/themes/lax-theme-child/inc/article-importer.php';
require $repo . '/themes/lax-theme-child/inc/inline-image-repair.php';

try {
	$id = 0;
	foreach ( glob( get_stylesheet_directory() . '/repair-media/*', GLOB_ONLYDIR ) as $dir ) {
		$slug = basename( $dir ); $html = file_get_contents( $repo . '/content/drafts/' . $slug . '/content.html' );
		$scan = hj_importer_scan_inline_images( $html, $dir );
		check( 2 === $scan['count'] && ! $scan['missing'], $slug . ': both files available' );
		$posts[++$id] = (object) array( 'ID' => $id, 'post_type' => 'surgery', 'post_name' => $slug, 'post_title' => 'Original title', 'post_content' => $html, 'post_status' => 'publish' );
		$meta[$id]['_thumbnail_id'] = 123;
		$r = hj_inline_repair_post( $id );
		check( ! is_wp_error( $r ) && 2 === $r['uploaded'], $slug . ': uploads two images' );
		check( ! hj_importer_html_has_local_images( $posts[$id]->post_content ), $slug . ': no relative URLs remain' );
		check( $posts[$id]->post_title === 'Original title' && $posts[$id]->post_status === 'publish' && $meta[$id]['_thumbnail_id'] === 123, $slug . ': title/status/featured unchanged' );
		$pattern = '/(<img\b[^>]*(?<=\s)src\s*=\s*)([\'"])(.*?)\2/is';
		$mask = static function ( $m ) { return $m[1] . $m[2] . 'IMAGE' . $m[2]; };
		check( preg_replace_callback( $pattern, $mask, $html ) === preg_replace_callback( $pattern, $mask, $posts[$id]->post_content ), $slug . ': only src changed (alt and captions intact)' );
		check( $meta[$id]['_hj_inline_repair_original'] === $html, $slug . ': rollback copy saved' );
		foreach ( $r['attachments'] as $rel => $att ) {
			check( hash_file( 'sha256', get_attached_file( $att ) ) === hash_file( 'sha256', $dir . '/' . $rel ), $slug . ': bytes intact on disk' );
		}
		$count = count( $media ); $r = hj_inline_repair_post( $id );
		check( ! is_wp_error( $r ) && ! $r['changed'] && count( $media ) === $count, $slug . ': repeat is a no-op' );
		$again = hj_importer_import_inline_images( $id, $dir, $html, $slug );
		check( 2 === $again['reused'] && 0 === $again['uploaded'], $slug . ': package reimport reuses files' );
	}
	check( 9 === $id && 18 === count( $media ), 'nine articles / eighteen images' );
	$dir = get_stylesheet_directory() . '/repair-media/appendectomy-overview';
	$html = file_get_contents( $repo . '/content/drafts/appendectomy-overview/content.html' );
	$posts[99] = (object) array( 'ID' => 99, 'post_type' => 'surgery', 'post_name' => 'appendectomy-overview', 'post_content' => $html );
	$original = $posts[99]->post_content;
	$expanded = str_replace( 'src="assets/', 'src="https://example.test/surgery/appendectomy-overview/assets/', $html );
	check( hj_inline_repair_html( $expanded, 99 ) === $html, 'same-site browser-expanded paths normalize' );
	$external = str_replace( 'src="assets/', 'src="https://external.test/assets/', $html );
	check( hj_inline_repair_html( $external, 99 ) === $external, 'external images untouched' );
	$malicious = hj_importer_scan_inline_images( '<img src="../outside.webp">', $dir );
	check( 1 === count( $malicious['missing'] ) && ! $malicious['local'], 'path traversal refused' );
	$bad_dir = hj_importer_scan_inline_images( '<img src="assets/a.webp">', '/no-such-package' );
	check( 1 === count( $bad_dir['missing'] ) && ! $bad_dir['local'], 'nonexistent package directory cannot resolve filesystem paths' );
	$missing = hj_importer_scan_inline_images( '<img src="assets/no-such-file.webp">', $dir );
	check( 1 === count( $missing['missing'] ), 'missing package file reported' );
	$lazy = '<img data-src="assets/no-such-file.webp" src="https://external.test/ok.webp" alt="safe">';
	check( ! hj_importer_html_has_local_images( $lazy ) && 0 === hj_importer_scan_inline_images( $lazy, $dir )['count'], 'data-src is not mistaken for src' );
	$one = hj_importer_import_inline_images( 99, $dir, $html, 'appendectomy-overview' );
	$att = reset( $one['attachments'] ); unlink( get_attached_file( $att ) );
	$r = hj_importer_import_inline_images( 99, $dir, $html, 'appendectomy-overview' );
	check( 1 === $r['uploaded'] && 1 === $r['reused'], 'stale hash with missing file cannot be reused' );
	$att = reset( $r['attachments'] ); file_put_contents( get_attached_file( $att ), 'CORRUPT' );
	$r = hj_importer_import_inline_images( 99, $dir, $html, 'appendectomy-overview' );
	check( 1 === $r['uploaded'], 'corrupt file with stale hash is replaced' );
	foreach ( $r['attachments'] as $att ) { unlink( get_attached_file( $att ) ); }
	$upload_fail = true; $r = hj_inline_repair_post( 99 );
	check( is_wp_error( $r ) && $posts[99]->post_content === $original, 'upload failure leaves original body intact' );
	$upload_fail = false; $save_fail = true; $r = hj_inline_repair_post( 99 );
	check( is_wp_error( $r ) && $posts[99]->post_content === $original, 'DB save error is not reported as success' );
	$save_fail = false; $deny = true;
	check( is_wp_error( hj_inline_repair_post( 99 ) ), 'edit permission enforced' );
	$deny = false;
	echo "PASS: $checks assertions; 9 surgery articles / 18 actual image files; no live site contacted.\n";
} finally {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
	rmdir( $tmp );
}

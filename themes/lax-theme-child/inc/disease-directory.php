<?php
/** Disease directory: editable posts with a one-time, non-destructive draft import. */
defined( 'ABSPATH' ) || exit;

function hj_register_disease_directory() {
	register_post_type( 'disease', array(
		'labels' => array( 'name' => 'بیماری‌ها', 'singular_name' => 'بیماری', 'add_new_item' => 'افزودن بیماری', 'edit_item' => 'ویرایش بیماری', 'all_items' => 'همه بیماری‌ها' ),
		'public' => true, 'show_in_rest' => true, 'has_archive' => true,
		'rewrite' => array( 'slug' => 'diseases', 'with_front' => false ),
		'menu_icon' => 'dashicons-book-alt', 'supports' => array( 'title', 'editor', 'excerpt', 'revisions' ),
	) );
	register_taxonomy( 'disease_group', array( 'disease' ), array(
		'labels' => array( 'name' => 'دسته‌های بیماری', 'singular_name' => 'دسته بیماری' ),
		'public' => true, 'show_in_rest' => true, 'hierarchical' => true,
		'rewrite' => array( 'slug' => 'disease-group', 'with_front' => false ),
	) );
}
add_action( 'init', 'hj_register_disease_directory' );

/** Import new batches only; never recreate old deleted entries or overwrite edits. */
function hj_seed_disease_directory() {
	if ( ! current_user_can( 'manage_options' ) || version_compare( (string) get_option( 'hj_disease_seed_version', '0' ), '2', '>=' ) || get_transient( 'hj_disease_seed_lock' ) ) {
		return;
	}
	set_transient( 'hj_disease_seed_lock', 1, 60 );
	$path = get_stylesheet_directory() . '/data/disease-seed.json';
	$raw = is_readable( $path ) ? file_get_contents( $path ) : false;
	$items = $raw ? json_decode( $raw, true ) : null;
	if ( ! is_array( $items ) || ! $items ) { delete_transient( 'hj_disease_seed_lock' ); return; }
	$complete = true;
	$previous_batch = (int) get_option( 'hj_disease_seed_version', '0' );
	foreach ( $items as $entry ) {
		if ( (int) ( $entry['seedBatch'] ?? 1 ) <= $previous_batch ) { continue; }
		if ( empty( $entry['diseaseId'] ) || empty( $entry['nameFa'] ) ) { $complete = false; continue; }
		$id = sanitize_key( $entry['diseaseId'] );
		$existing = get_posts( array( 'post_type' => 'disease', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_hj_disease_seed_id', 'meta_value' => $id ) );
		// 'any' excludes trash in WordPress: explicitly check it too.
		$trashed = get_posts( array( 'post_type' => 'disease', 'post_status' => 'trash', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_hj_disease_seed_id', 'meta_value' => $id ) );
		if ( $existing || $trashed ) { continue; }
		$content = '<p><strong>نام انگلیسی:</strong> ' . esc_html( $entry['nameEn'] ) . '</p>';
		foreach ( array( 'cause' => 'علت بیماری', 'history' => 'تاریخچه مختصر', 'mainSymptoms' => 'علائم اصلی', 'commonTreatment' => 'درمان‌های رایج' ) as $key => $label ) {
			$content .= '<h2>' . esc_html( $label ) . '</h2><p>' . esc_html( $entry[ $key ] ?? '' ) . '</p>';
		}
		$content .= '<h2>منابع پزشکی و تاریخی</h2><ul>';
		foreach ( array_merge( $entry['clinicalSources'] ?? array(), $entry['historySources'] ?? array() ) as $source ) {
			if ( empty( $source['url'] ) || empty( $source['text'] ) || 'https' !== wp_parse_url( $source['url'], PHP_URL_SCHEME ) ) { continue; }
			$content .= '<li><a href="' . esc_url( $source['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $source['text'] ) . '</a></li>';
		}
		$content .= '</ul><p>این مطلب آموزشی است و جایگزین تشخیص یا درمان پزشکی نیست. برای بررسی علائم به پزشک مراجعه کنید.</p>';
		$post_id = wp_insert_post( array( 'post_type' => 'disease', 'post_status' => 'draft', 'post_title' => $entry['nameFa'], 'post_name' => $id, 'post_content' => $content, 'meta_input' => array( '_hj_disease_seed_id' => $id, '_hj_disease_en' => sanitize_text_field( $entry['nameEn'] ) ) ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) { $complete = false; continue; }
		if ( ! empty( $entry['category'] ) ) {
			$term = term_exists( $entry['category'], 'disease_group' );
			if ( ! $term ) { $term = wp_insert_term( $entry['category'], 'disease_group' ); }
			if ( ! is_wp_error( $term ) && $term ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				if ( is_wp_error( wp_set_object_terms( $post_id, array( $term_id ), 'disease_group' ) ) ) { $complete = false; }
			} else { $complete = false; }
		}
	}
	if ( $complete ) { update_option( 'hj_disease_seed_version', '2', false ); }
	delete_transient( 'hj_disease_seed_lock' );
}
add_action( 'admin_init', 'hj_seed_disease_directory' );

/** Flush rewrite rules once after deployment on an authenticated admin visit. */
function hj_disease_flush_rewrites() {
	if ( current_user_can( 'manage_options' ) && get_option( 'hj_disease_routes_version' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'hj_disease_routes_version', '1', false );
	}
}
add_action( 'admin_init', 'hj_disease_flush_rewrites' );

/** Allow directory-only category/search filtering without touching site search. */
function hj_disease_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'disease' ) ) { return; }
	$query->set( 'orderby', 'title' ); $query->set( 'order', 'ASC' );
	if ( isset( $_GET['disease_search'] ) ) { $query->set( 's', sanitize_text_field( wp_unslash( $_GET['disease_search'] ) ) ); }
	if ( isset( $_GET['disease_group'] ) ) {
		$group = sanitize_title( wp_unslash( $_GET['disease_group'] ) );
		if ( $group ) { $query->set( 'tax_query', array( array( 'taxonomy' => 'disease_group', 'field' => 'slug', 'terms' => $group ) ) ); }
	}
}
add_action( 'pre_get_posts', 'hj_disease_archive_query' );

/** English title is editable alongside the standard WordPress content editor. */
function hj_disease_add_meta_box() {
	add_meta_box( 'hj_disease_english', 'نام انگلیسی بیماری', 'hj_disease_english_meta_box', 'disease', 'side' );
}
add_action( 'add_meta_boxes_disease', 'hj_disease_add_meta_box' );
function hj_disease_english_meta_box( $post ) {
	wp_nonce_field( 'hj_disease_english_save', 'hj_disease_english_nonce' );
	echo '<label for="hj-disease-en">نام انگلیسی</label><input style="width:100%" dir="ltr" id="hj-disease-en" name="hj_disease_en" value="' . esc_attr( get_post_meta( $post->ID, '_hj_disease_en', true ) ) . '">';
}
function hj_disease_save_english( $post_id ) {
	if ( ! isset( $_POST['hj_disease_english_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_disease_english_nonce'] ) ), 'hj_disease_english_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['hj_disease_en'] ) ) { return; }
	update_post_meta( $post_id, '_hj_disease_en', sanitize_text_field( wp_unslash( $_POST['hj_disease_en'] ) ) );
}
add_action( 'save_post_disease', 'hj_disease_save_english' );

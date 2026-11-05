<?php
/** Only loaded into the disposable CI WordPress installation. */
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || WP_HOME !== 'http://example.invalid' ) { exit; }
add_action( 'admin_post_hj_fixture_revision_debug', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden', '', array( 'response' => 403 ) ); }
	global $wpdb;
	$id = (int) get_option( 'page_on_front' ); $content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_content FROM {$wpdb->posts} WHERE post_parent=%d AND post_type='revision' ORDER BY ID DESC LIMIT 8", $id ) );
	$data = array( 'current' => array( 'id' => $id, 'size' => strlen( $content ), 'sha' => hash( 'sha256', $content ) ), 'expected_found' => false, 'revisions' => array() );
	foreach ( $rows as $r ) {
		if ( function_exists( 'hj_orbit_build_content' ) ) {
			$expected = hj_orbit_build_content( $r->post_content );
			if ( is_string( $expected ) && $expected === $content ) { $data['expected_found'] = true; }
		}
		$diff = strspn( $content ^ $r->post_content, "\0" );
		$data['revisions'][] = array( 'id' => $r->ID, 'name' => $r->post_name, 'size' => strlen( $r->post_content ), 'sha' => hash( 'sha256', $r->post_content ), 'first_diff' => $diff, 'source_fragment' => base64_encode( substr( $content, max(0, $diff-35), 100 ) ), 'revision_fragment' => base64_encode( substr( $r->post_content, max(0, $diff-35), 100 ) ) );
	}
	wp_send_json( $data );
} );

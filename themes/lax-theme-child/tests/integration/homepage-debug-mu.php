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

// Synthetic hook output reflects the booking card in the screenshots.
add_action( 'hj_doctor_dashboard_appointments', function () {
    echo '<div id="hj-bookings"><section class="hj-bookings" dir="rtl"><h2>لیست نوبت‌ها</h2><nav><a href="#">امروز</a><a href="#">آینده</a><a href="#">همه</a></nav><article class="hj-bookings__item"><p>این یک نمونهٔ ساختگی است</p></article></section></div>';
} );

// CI fixture only: never send actual mail; simulate transport failures.
add_filter( 'pre_wp_mail', function ( $result, $atts ) {
    $to = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'];
    if ( $to === 'fixture@example.invalid' ) { update_option( 'hj_fixture_mail', $atts, false ); }
    return false === strpos( $to, 'mail-failure@example.invalid' );
}, 10, 2 );
add_action( 'admin_post_hj_fixture_newsletter_state', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden', '', array( 'response' => 403 ) ); }
    $id = hj_magazine_find( 'fixture@example.invalid' );
    wp_send_json( array( 'status' => $id ? get_post_status( $id ) : 'missing' ) );
} );

add_action( 'admin_post_hj_fixture_confirm_link', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden', '', array( 'response' => 403 ) ); }
    wp_send_json( get_option( 'hj_fixture_mail', array() ) );
} );

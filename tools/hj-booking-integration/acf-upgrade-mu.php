<?php
/** Disposable CI-only authenticated state adapters. */
if ( PHP_SAPI !== 'cli-server' ) { return; }
if ( getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' || DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
add_action( 'wp_ajax_hj_ci_acf_nonce', function () { wp_send_json_success( array( 'nonce'=>wp_create_nonce( 'hj_dash_nonce' ) ) ); } );
add_action( 'wp_ajax_hj_ci_acf_state', function () {
 if ( ! hj_doctors_is_doctor_user() || ! function_exists( 'acf' ) ) { wp_send_json_error( array(), 403 ); }
 $id = hj_doctors_get_profile_id();
 if ( $id && ! current_user_can( 'edit_post', $id ) ) { wp_send_json_error( array(), 403 ); }
 $hours_field = acf_get_field( 'working_hours' );
 wp_send_json_success( array(
  'acf_version'=>acf()->settings['version'], 'plugin_version'=>HJ_DOCTORS_VERSION,
  'repeater_available'=>is_object( acf_get_field_type( 'repeater' ) ),
  'hours_field_type'=>is_array( $hours_field ) ? $hours_field['type'] : null,
  'profile'=>$id, 'name'=>$id ? get_field( 'name_fa', $id ) : null,
  'hours_meta'=>$id ? get_post_meta( $id, 'working_hours', true ) : null,
  'hours_acf'=>$id ? get_field( 'working_hours', $id ) : null,
 ) );
} );
add_action( 'wp_ajax_hj_ci_acf_render', function () { wp_send_json_success( array( 'html'=>HJ_Doctors_Dashboard::shortcode_render() ) ); } );

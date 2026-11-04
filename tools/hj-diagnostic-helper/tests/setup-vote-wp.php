<?php
/** Creates synthetic vote data; run only in a NEW disposable WordPress database. */
if ( ! defined( 'HJ_DISPOSABLE_TEST' ) || true !== HJ_DISPOSABLE_TEST ) { exit( 'Disposable WordPress only.' ); }
require '/wordpress/wp-load.php';
require_once WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
do_action( 'init' );
global $wpdb;
$table = $wpdb->prefix . 'hj_doctor_votes';
if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { throw new Exception( 'Invalid or existing table; refusing test.' ); }
if ( false === $wpdb->query( "CREATE TABLE `{$table}` (id INTEGER PRIMARY KEY, doctor_id INTEGER NOT NULL, rating INTEGER NOT NULL, status VARCHAR(20))" ) ) { throw new Exception( 'Fixture create failed: ' . $wpdb->last_error ); }
if ( 1 !== $wpdb->insert( $table, array( 'id' => 12345, 'doctor_id' => 424242, 'rating' => 3, 'status' => 'pending' ) ) ) { throw new Exception( 'Fixture insert failed.' ); }
echo "PASS: synthetic vote table setup.\n";

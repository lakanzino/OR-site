<?php
/** Disposable CI-only WordPress/MariaDB fixture. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); ini_set( 'display_errors', '1' );
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require_once dirname( __DIR__, 2 ) . '/inc/homepage-orbit.php';
require_once dirname( __DIR__, 2 ) . '/inc/home-surgeons.php';
function orbit_assert( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
wp_set_current_user( 1 );
$source = file_get_contents( dirname( __DIR__, 2 ) . '/homepage/starter.html' );
$new_title = '<!-- wp:heading {"level":1,"className":"hj-hero-title"} --><h1 class="wp-block-heading hj-hero-title"><span class="hj-hero-slogan">آرامش در مسیر <span class="hj-hero-surgery">جراحی</span> با <span class="hj-hero-brand">همیار</span><span class="hj-hero-surgery">جراحی</span></span></h1>';
$old_title = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">آگاهی بیشتر،<br>آرامش در مسیر <mark>جراحی.</mark></h1>';
$source = str_replace( $new_title, $old_title, $source );
$source = str_replace( 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و آشنایی با جراحان <span class="hj-hero-emphasis">برتر</span>.', 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و فضایی برای آشنایی با جراحان.', $source );
$source = str_replace( 'ورود به پنل جراح', 'ورود به پنل پزشک', $source );
$source .= '<!-- wp:paragraph --><p>متن شخصی بدون تغییر</p><!-- /wp:paragraph -->';
$built = hj_orbit_build_content( $source );
orbit_assert( is_string( $built ), 'known hero recognized' );
orbit_assert( strpos( $built, 'hj-hero-slogan' ) !== false && strpos( $built, 'جراحان <span class="hj-hero-emphasis">برتر</span>' ) !== false, 'requested text and classes' );
orbit_assert( strpos( $built, 'ورود به پنل جراح' ) !== false && strpos( $built, 'متن شخصی بدون تغییر' ) !== false, 'label changed and unrelated text retained' );
orbit_assert( strpos( $built, '[hj_home_surgeons]' ) !== false && strpos( $built, '[hj_home_google]' ) !== false, 'shortcodes retained' );
orbit_assert( serialize_blocks( parse_blocks( $built ) ) === $built, 'modified content parses and roundtrips' );
$custom = str_replace( 'آگاهی بیشتر،', 'متن سفارشی من،', $source );
orbit_assert( is_wp_error( hj_orbit_build_content( $custom ) ), 'edited hero rejected' );
orbit_assert( is_wp_error( hj_orbit_build_content( $source . $source ) ), 'ambiguous duplicate hero rejected' );
$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Orbit fixture', 'post_content' => $source ) ), true );
orbit_assert( ! is_wp_error( $id ), 'page created' ); update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id );
$before = get_post( $id )->post_content;
update_post_meta( $id, '_edit_lock', time() . ':1' ); orbit_assert( is_wp_error( hj_orbit_update_current() ), 'own edit lock blocks update' ); delete_post_meta( $id, '_edit_lock' );
add_filter( 'wp_revisions_to_keep', function () { return 1; } ); orbit_assert( is_wp_error( hj_orbit_update_current() ), 'unsafe single revision rejected' ); remove_all_filters( 'wp_revisions_to_keep' );
orbit_assert( get_post( $id )->post_content === $before, 'rejections do not modify source' );
$updated = hj_orbit_update_current(); orbit_assert( ! is_wp_error( $updated ) && (int) $updated === (int) $id, 'in-place update succeeds' );
orbit_assert( (int) get_option( 'page_on_front' ) === (int) $id && get_post( $id )->post_content === $built, 'same page selected, expected content saved' );
$revs = wp_get_post_revisions( $id, array( 'posts_per_page' => -1 ) );
orbit_assert( (bool) array_filter( $revs, function ( $revision ) use ( $before ) { return $revision->post_content === $before; } ), 'original content retained in revision' );
orbit_assert( is_wp_error( hj_orbit_update_current() ), 'repeat application rejected' );
wp_set_current_user( 0 ); orbit_assert( is_wp_error( hj_orbit_update_current() ), 'unauthorized update blocked' ); wp_set_current_user( 1 );
register_post_type( 'doctor', array( 'public' => true, 'has_archive' => 'doctors' ) );
$doctor = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic doctor' ) );
update_post_meta( $doctor, 'hj_profile_status', 'published' ); update_post_meta( $doctor, 'specialty_fa', 'Synthetic specialty' ); update_post_meta( $doctor, 'education', "Degree A\nDegree B" ); update_post_meta( $doctor, 'medical_license', 'M-123' );
add_filter( 'hj_doctor_rating_stats', function ( $stats ) { return array( 'avg' => 4.5, 'approved' => 5 ); } );
$html = do_shortcode( '[hj_home_surgeons]' );
orbit_assert( strpos( $html, 'Synthetic doctor' ) !== false && strpos( $html, 'Degree A' ) !== false && strpos( $html, 'Degree B' ) === false && strpos( $html, 'M-123' ) !== false && strpos( $html, '★' ) !== false, 'card shows stored details and approved score' );
remove_all_filters( 'hj_doctor_rating_stats' );
add_filter( 'hj_doctor_rating_stats', function ( $stats ) { return array( 'avg' => 4.5, 'approved' => 2 ); } );
$html = do_shortcode( '[hj_home_surgeons]' ); orbit_assert( strpos( $html, '★' ) !== false && strpos( $html, 'از ۵' ) !== false, 'small-count average matches public profile' );
remove_all_filters( 'hj_doctor_rating_stats' );
$html = do_shortcode( '[hj_home_surgeons]' ); orbit_assert( strpos( $html, 'هنوز امتیازی برای نمایش نیست' ) !== false && strpos( $html, '★' ) === false, 'no score is not invented' );
echo "PASS homepage orbit integration\n";

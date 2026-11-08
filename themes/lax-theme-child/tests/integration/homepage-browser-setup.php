<?php
/** Set up a synthetic homepage for the disposable CI browser test. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
$active = activate_plugin( 'hamyar-doctors/hamyar-doctors.php' );
if ( is_wp_error( $active ) ) { throw new RuntimeException( $active->get_error_message() ); }
do_action( 'init' );
// The database and address are synthetic; no real mail is sent by the fixture.
$sub = wp_insert_post( array( 'post_type' => 'hj_subscription', 'post_status' => 'publish', 'post_title' => 'approved@example.invalid' ), true );
if ( is_wp_error( $sub ) ) { throw new RuntimeException( 'fixture subscription creation failed' ); }
update_post_meta( $sub, '_hj_email', 'approved@example.invalid' );

$content = file_get_contents( dirname( __DIR__, 2 ) . '/homepage/starter.html' );
$content = str_replace( '<!-- wp:heading {"level":1,"className":"hj-hero-title"} --><h1 class="wp-block-heading hj-hero-title"><span class="hj-hero-slogan">آرامش در مسیر <span class="hj-hero-surgery">جراحی</span> با <span class="hj-hero-brand">همیار</span><span class="hj-hero-surgery">جراحی</span></span></h1>', '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">آگاهی بیشتر،<br>آرامش در مسیر <mark>جراحی.</mark></h1>', $content );
$content = str_replace( 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و آشنایی با جراحان <span class="hj-hero-emphasis">برتر</span>.', 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و فضایی برای آشنایی با جراحان.', $content );
// Reproduce the observed older live labels and distinct destinations; still
// entirely synthetic data and no provider/network calls.
$content = str_replace( array( 'جراح هستید؟', 'جراحان و متخصصان', 'ورود به پنل جراح' ), array( 'پزشک هستید؟', 'پزشکان و متخصصان', 'ورود به پنل پزشک' ), $content );
$content = strtr( $content, array(
	'{{GUIDES}}' => home_url( '/patient-guide/' ), '{{SURGERIES}}' => home_url( '/surgery/' ), '{{DOCTORS}}' => home_url( '/doctors/' ),
	'{{BEFORE}}' => home_url( '/guide-stage/before/' ), '{{DURING}}' => home_url( '/guide-stage/during/' ), '{{AFTER}}' => home_url( '/guide-stage/after/' ),
	'{{OR}}' => home_url( '/or-environment/' ), '{{STUDENTS}}' => home_url( '/student/' ), '{{PROFILE}}' => home_url( '/my-profile/' ),
	'{{DISEASES}}' => home_url( '/diseases/' ), '{{GLOSSARY}}' => home_url( '/glossary/' ), '{{FAQ}}' => home_url( '/faq/' ),
	'{{CONTACT}}' => home_url( '/contact/' ), '{{IMAGE}}' => home_url( '/fixture-image.png' ),
) );
// Synthetic published articles for the welcome email.
for ( $i = 1; $i <= 3; $i++ ) { wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Fixture article ' . $i ) ); }
// Reproduce the live editable newsletter block and its surrounding content.
$magazine = '<!-- wp:group {"className":"hj-magazine-section","layout":{"type":"constrained"}} --><div class="wp-block-group hj-magazine-section">'
    . '<!-- wp:paragraph {"className":"hj-magazine-eyebrow"} --><p class="hj-magazine-eyebrow">نامه‌ای از همیار جراحی</p><!-- /wp:paragraph -->'
    . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">اشتراک رایگان مجله اینترنتی همیارجراحی، تنها با یک جیمیل</h2><!-- /wp:heading -->'
    . '<!-- wp:paragraph --><p>برای دریافت خبرهای مجله، ایمیل خود را وارد و اشتراک را تأیید کنید.</p><!-- /wp:paragraph -->'
    . '<!-- wp:shortcode -->[hj_magazine_subscribe]<!-- /wp:shortcode -->'
    . '<!-- wp:paragraph {"className":"hj-magazine-account"} --><p class="hj-magazine-account">حساب اعضا: [hj_member_account_link] &nbsp; | &nbsp; جراح هستید؟ از بخش ثبت‌نام جراحان وارد شوید.</p><!-- /wp:paragraph -->'
    . '</div><!-- /wp:group -->';
$content = str_replace( '{{MAGAZINE}}', $magazine, $content );
$privacy = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic privacy', 'post_name' => 'fixture-privacy' ) );
update_option( 'wp_page_for_privacy_policy', $privacy );
wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Other newsletter', 'post_name' => 'fixture-other-newsletter', 'post_content' => $magazine ) ) );
$content = preg_replace( '/\{\{[A-Z_]+\}\}/', '', $content );
$page = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic Browser Home', 'post_content' => $content ) ), true );
if ( is_wp_error( $page ) ) { throw new RuntimeException( $page->get_error_message() ); }
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $page );
for ( $i = 1; $i <= 3; $i++ ) {
	$id = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'post_title' => 'Synthetic surgeon ' . $i ) );
	update_post_meta( $id, 'hj_profile_status', 'published' );
	update_post_meta( $id, 'specialty_fa', 'جراح آزمایشی' );
	update_post_meta( $id, 'medical_license', '123456' );
	update_post_meta( $id, 'education', 'مدرک آزمایشی' );
}
switch_theme( 'lax-theme-child' );
$logo = wp_insert_attachment( array( 'post_title' => 'Fixture logo', 'post_mime_type' => 'image/png', 'guid' => home_url( '/fixture-image.png' ) ) );
update_post_meta( $logo, '_wp_attached_file', 'fixture-image.png' );
set_theme_mod( 'custom_logo', $logo );
echo "PASS browser homepage setup\n";

// Disposable surgeon and editable draft: no external identity provider or real data.
$dashboard = hj_doctors_ensure_dashboard_page();
if ( ! $dashboard ) { throw new RuntimeException( 'dashboard page missing' ); }
wp_update_post( array( 'ID' => $dashboard, 'post_content' => '[hj_doctors_dashboard]', 'post_status' => 'publish' ) );
$user_id = wp_create_user( 'responsive_surgeon', 'disposable-password-please-ignore', 'responsive@example.invalid' );
if ( is_wp_error( $user_id ) || ! hj_doctors_assign_doctor_role( $user_id ) ) { throw new RuntimeException( 'fixture surgeon role setup failed' ); }
update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
$doc = wp_insert_post( array( 'post_type' => 'doctor', 'post_status' => 'draft', 'post_author' => $user_id, 'post_title' => 'جراح آزمایشی' ), true );
if ( is_wp_error( $doc ) ) { throw new RuntimeException( $doc->get_error_message() ); }
update_user_meta( $user_id, 'hj_doctor_post_id', $doc );
update_post_meta( $doc, 'name_fa', 'جراح آزمایشی' );
update_post_meta( $doc, 'specialty_fa', 'جراحی آزمایشی' );
// Deliberately long labels reveal overflow without involving real user data.
wp_insert_term( str_repeat( 'رشتهآزمایشی', 10 ), 'specialty', array( 'slug' => 'responsive-long-test' ) );
for ( $i = 1; $i <= 18; $i++ ) { wp_insert_term( 'رشته تست ' . $i, 'specialty', array( 'slug' => 'responsive-test-' . $i ) ); }
echo "PASS browser surgeon setup\n";

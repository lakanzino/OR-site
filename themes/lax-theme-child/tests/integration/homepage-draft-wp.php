<?php
/** Disposable WordPress + MariaDB integration fixture; never run on a live site. */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) { exit( 1 ); }
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
ini_set( 'display_errors', '1' );
require '/wordpress/wp-load.php';
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $GLOBALS['wpdb']->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) { exit( 1 ); }
require_once dirname( __DIR__, 2 ) . '/inc/homepage-draft.php';
function hd_assert( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
wp_set_current_user( 1 );
$starter = file_get_contents( dirname( __DIR__, 2 ) . '/homepage/starter.html' );
$starter = str_replace( array( '{{DOCTORS}}', '{{MAGAZINE}}' ), array( 'https://example.invalid/old-doctors/', '<!-- wp:paragraph --><p>محتوای ویرایشی ویژه</p><!-- /wp:paragraph -->' ), $starter );
// Simulate the previous starter, not just the already-updated template.
$starter = str_replace( array( 'جراحی در پیش دارید؟ ما همیار شما هستیم.', 'انتخابی که مسیر زندگی‌تان را تغییر می‌دهد؛ برترین جراحان هر شهر و استان، اینجا.' ), array( 'آگاهی بیشتر،<br>آرامش در مسیر <mark>جراحی.</mark>', 'از نخستین پرسش تا روزهای پس از عمل، کنار شما هستیم. اطلاعات روشن، راهنمای کاربردی و فضایی برای آشنایی با جراحان.' ), $starter );
$archive = home_url( '/doctors/' );
$built = hj_home_draft_build( $starter, $archive );
hd_assert( strpos( $built['content'], 'جراحی در پیش دارید؟ ما همیار شما هستیم.' ) !== false, 'hero title' );
hd_assert( strpos( $built['content'], 'انتخابی که مسیر زندگی‌تان را تغییر می‌دهد؛ برترین جراحان هر شهر و استان، اینجا.' ) !== false, 'hero lead' );
hd_assert( strpos( $built['content'], 'href="' . $archive . '"' ) !== false, 'archive link' );
hd_assert( substr_count( $built['content'], '[hj_member_google]' ) === 1, 'one public login' );
hd_assert( strpos( $built['content'], '[hj_home_google]' ) !== false, 'surgeon login retained' );
hd_assert( strpos( $built['content'], 'محتوای ویرایشی ویژه' ) !== false, 'editorial content retained' );
hd_assert( serialize_blocks( parse_blocks( $built['content'] ) ) === $built['content'], 'round trip blocks' );
$neo = file_get_contents( dirname( __DIR__, 2 ) . '/homepage/neo-clay.html' );
$neo_result = hj_home_draft_build( str_replace( '{{DOCTORS}}', 'https://example.invalid/previous/', $neo ), $archive );
hd_assert( strpos( $neo_result['content'], 'href="' . $archive . '"' ) !== false, 'neo hero archive link' );
hd_assert( strpos( $neo_result['content'], '[hj_home_google]' ) !== false, 'neo surgeon shortcode retained' );
$nested = $starter . '<!-- wp:group --><div class="wp-block-group"><!-- wp:shortcode -->[hj_member_google]<!-- /wp:shortcode --></div><!-- /wp:group -->';
$nested_result = hj_home_draft_build( $nested, $archive );
hd_assert( substr_count( $nested_result['content'], '[hj_member_google]' ) === 1, 'no duplicate for nested shortcode' );
hd_assert( count( $nested_result['warnings'] ) > 0, 'nested shortcode warning' );
$synced = $starter . '<!-- wp:block {"ref":123} /-->';
hd_assert( strpos( hj_home_draft_build( $synced, $archive )['content'], '[hj_member_google]' ) === false, 'no duplicate when reusable block unknown' );
$unknown = '<!-- wp:paragraph --><p>متن سفارشی</p><!-- /wp:paragraph -->';
$unknown_result = hj_home_draft_build( $unknown, $archive );
hd_assert( strpos( $unknown_result['content'], $unknown ) === 0 && count( $unknown_result['warnings'] ) > 0, 'unknown hero unchanged with warning' );
$source = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Homepage fixture', 'post_content' => $starter ) ), true );
hd_assert( ! is_wp_error( $source ), 'source created' );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $source );
$before = get_post( $source )->post_content;
update_post_meta( $source, '_edit_lock', time() . ':1' );
hd_assert( is_wp_error( hj_home_draft_create() ), 'own edit lock blocks draft' );
delete_post_meta( $source, '_edit_lock' );
$draft = hj_home_draft_create();
hd_assert( ! is_wp_error( $draft ) && get_post_status( $draft ) === 'draft', 'draft created' );
hd_assert( (int) get_option( 'page_on_front' ) === (int) $source && get_post( $source )->post_content === $before, 'live page and selection unchanged' );
hd_assert( get_post_meta( $draft, '_hj_home_draft_source_hash', true ) === hash( 'sha256', $before ), 'source snapshot hash' );
hd_assert( strpos( get_post( $draft )->post_content, '[hj_member_google]' ) !== false, 'draft has public login' );
wp_set_current_user( 0 );
hd_assert( is_wp_error( hj_home_draft_create() ), 'unauthorized cannot create draft' );
echo "PASS homepage draft integration\n";

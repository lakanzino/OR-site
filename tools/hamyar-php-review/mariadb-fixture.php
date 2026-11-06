<?php
/**
 * Creates and tests a WordPress database. GitHub Actions disposable service ONLY.
 * Never run against a real site, backup, staging copy, or nonempty database.
 */
if ( PHP_SAPI !== 'cli' || getenv( 'GITHUB_ACTIONS' ) !== 'true' || getenv( 'HJ_DISPOSABLE_MARIADB_FIXTURE' ) !== '1' ) {
    fwrite( STDERR, "Disposable CI fixture only.\n" ); exit( 1 );
}
$mode = $argv[1] ?? '';
if ( ! in_array( $mode, array( 'install', 'test' ), true ) ) { exit( 1 ); }
if ( 'install' === $mode ) { define( 'WP_INSTALLING', true ); }
require '/wordpress/wp-load.php';
global $wpdb;
if ( DB_NAME !== 'hj_fixture_db' || DB_HOST !== '127.0.0.1:3306' || $wpdb->prefix !== 'hjt_' || WP_HOME !== 'http://example.invalid' ) {
    fwrite( STDERR, "Refusing unexpected DB or site configuration.\n" ); exit( 1 );
}
if ( 'install' === $mode ) {
    $tables = $wpdb->get_col( 'SHOW TABLES' );
    if ( $wpdb->last_error !== '' || ! is_array( $tables ) || $tables ) {
        fwrite( STDERR, "Refusing nonempty or unreadable fixture database.\n" ); exit( 1 );
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $result = wp_install( 'Fixture', 'fixture_admin', 'admin@example.invalid', false, '', 'disposable-password-please-ignore' );
    if ( empty( $result['user_id'] ) ) { throw new RuntimeException( 'Fixture WordPress install failed.' ); }
    echo "PASS: installed WordPress in empty CI MariaDB.\n";
    exit;
}
define( 'HJ_DISPOSABLE_TEST', true );
require WP_PLUGIN_DIR . '/hamyar-doctors/hamyar-doctors.php';
hj_doctors();
do_action( 'init' );
if ( in_array( getenv( 'HJ_TEST_PLUGIN' ), array( 'review-entry-http-0.3.12', 'review-entry-http-current' ), true ) ) {
    $expected = getenv( 'HJ_TEST_PLUGIN' ) === 'review-entry-http-current' ? '0.3.33' : '0.3.12';
    if ( HJ_DOCTORS_VERSION !== $expected ) { throw new RuntimeException( 'Wrong installed baseline.' ); }
    require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-review-entry/tests/setup-wp.php';
    exit;
}
if ( getenv( 'HJ_TEST_PLUGIN' ) === 'review-marker-http-current' ) {
    if ( HJ_DOCTORS_VERSION !== '0.3.33' ) { throw new RuntimeException( 'Wrong plugin version.' ); }
    require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hamyar-php-review/review-marker-setup-wp.php';
    exit;
}
if ( in_array( getenv( 'HJ_TEST_PLUGIN' ), array( 'vote-manager-http-0.3.12', 'vote-manager-http-current' ), true ) ) {
    $expected = getenv( 'HJ_TEST_PLUGIN' ) === 'vote-manager-http-current' ? '0.3.33' : '0.3.12';
    if ( HJ_DOCTORS_VERSION !== $expected ) { throw new RuntimeException( 'Wrong installed baseline.' ); }
    require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-vote-manager/tests/manager-http-setup-wp.php';
    exit;
}
if ( getenv( 'HJ_TEST_PLUGIN' ) === 'vote-lab-http-0.3.12' ) {
    if ( HJ_DOCTORS_VERSION !== '0.3.12' ) { throw new RuntimeException( 'Wrong installed baseline.' ); }
    require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-vote-lab/tests/lab-http-setup-wp.php';
    exit;
}
if ( getenv( 'HJ_TEST_PLUGIN' ) === 'vote-lab-0.3.12' ) {
    if ( HJ_DOCTORS_VERSION !== '0.3.12' ) { throw new RuntimeException( 'Wrong installed baseline.' ); }
    require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-vote-lab/tests/lab-mariadb-wp.php';
    exit;
}
if ( getenv( 'HJ_TEST_PLUGIN' ) === 'installed-0.3.12' ) {
    if ( HJ_DOCTORS_VERSION !== '0.3.12' ) { throw new RuntimeException( 'Wrong installed baseline.' ); }
    require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-diagnostic-helper/tests/setup-vote-wp.php';
} elseif ( getenv( 'HJ_TEST_PLUGIN' ) === 'current' ) {
    require WP_PLUGIN_DIR . '/hamyar-doctors/tests/admin-data-status-wp.php';
    require WP_PLUGIN_DIR . '/hamyar-doctors/tests/vote-schema-wp.php';
} else {
    throw new RuntimeException( 'Unexpected fixture plugin selection.' );
}
require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hj-diagnostic-helper/tests/diagnostic-wp.php';
require getenv( 'GITHUB_WORKSPACE' ) . '/tools/hamyar-php-review/vote-moderation-prototype-wp.php';

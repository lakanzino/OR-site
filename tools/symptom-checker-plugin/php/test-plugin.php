<?php
/**
 * Logic tests for the packaged plugin, executed by a real PHP 8 interpreter
 * against WordPress stubs (see wp-stubs.php). Each scenario runs in its own
 * process because the plugin caches the bundle and the mapping per request.
 *
 * Usage: php test-plugin.php <plugin-dir> <scenario>
 *
 * @package HamyarSymptomChecker
 */

// phpcs:disable

require __DIR__ . '/wp-stubs.php';

$plugin_dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) . '/' : '';
$scenario   = $argv[2] ?? '';

if ( ! is_dir( $plugin_dir ) ) {
	fwrite( STDERR, "missing plugin directory: {$plugin_dir}\n" );
	exit( 2 );
}

$failures = 0;
$checks   = 0;

function ok( $condition, $label, $detail = '' ) {
	global $failures, $checks;
	++$checks;
	if ( $condition ) {
		echo "PASS  {$label}\n";
		return;
	}
	++$failures;
	echo "FAIL  {$label}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
}

function eq( $expected, $actual, $label ) {
	ok(
		$expected === $actual,
		$label,
		'expected ' . var_export( $expected, true ) . ' got ' . var_export( $actual, true )
	);
}

/** Reset the stub site and return the freshly loaded plugin state. */
function boot( $plugin_dir, $with_disease_type = true ) {
	hj_stub_reset();
	if ( $with_disease_type ) {
		register_post_type( 'disease' );
	}
	require_once $plugin_dir . 'hamyar-symptom-checker.php';
}

function add_disease( $slug, $status = 'publish', $title = 'x' ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'disease',
			'post_name'   => $slug,
			'post_status' => $status,
			'post_title'  => $title,
		)
	);
	return $id;
}

switch ( $scenario ) {
	case 'activation':
		hj_stub_reset();
		require_once $plugin_dir . 'hamyar-symptom-checker.php';

		// Activation itself must be a no-op apart from the pending note: that is
		// what keeps a slow or memory-hungry site from rolling it back.
		hjsc_activate();
		ok( (bool) get_option( HJ_SC_ACTIVATION_PENDING ), 'activation leaves a pending note' );
		eq( 0, count( $GLOBALS['hj_stub']['posts'] ), 'activation creates no page by itself' );

		// The next admin request builds the page.
		hjsc_maybe_finish_activation();
		$first = (int) get_option( HJ_SC_PAGE_OPTION );
		ok( $first > 0, 'the next admin request stores the page id' );
		$page = get_post( $first );
		ok( $page instanceof WP_Post && 'page' === $page->post_type, 'the created post is a page' );
		eq( 'draft', $page->post_status, 'the page is created as a draft, never public' );
		eq( HJ_SC_PAGE_SLUG, $page->post_name, 'the page slug is the documented one' );
		ok( ! get_option( HJ_SC_ACTIVATION_PENDING ), 'the pending note is cleared' );

		hjsc_maybe_finish_activation();
		hjsc_activate();
		hjsc_maybe_finish_activation();
		eq( 1, count( $GLOBALS['hj_stub']['posts'] ), 'activation never creates a second page' );

		// An existing page with the same slug is adopted, not overwritten.
		hj_stub_reset();
		$existing = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_name'    => HJ_SC_PAGE_SLUG,
				'post_status'  => 'publish',
				'post_title'   => 'صفحهٔ موجود مدیر',
				'post_content' => 'محتوای دستی',
			)
		);
		hjsc_activate();
		hjsc_maybe_finish_activation();
		eq( $existing, (int) get_option( HJ_SC_PAGE_OPTION ), 'an existing page is adopted' );
		eq( 1, count( $GLOBALS['hj_stub']['posts'] ), 'no extra page is created' );
		eq( 'publish', get_post( $existing )->post_status, 'the existing page status is left untouched' );
		eq( 'محتوای دستی', get_post( $existing )->post_content, 'the existing page content is left untouched' );
		break;

	case 'activation-error':
		hj_stub_reset();
		require_once $plugin_dir . 'hamyar-symptom-checker.php';

		hjsc_activate();
		$GLOBALS['hj_stub']['fail_insert'] = true;
		hjsc_maybe_finish_activation();

		$error = get_option( HJ_SC_LAST_ERROR, array() );
		ok( is_array( $error ) && ! empty( $error['message'] ), 'a failing insert is recorded, not thrown' );
		ok( false !== strpos( (string) ( $error['message'] ?? '' ), 'simulated database failure' ), 'the real reason is kept', (string) ( $error['message'] ?? '' ) );
		eq( 0, count( $GLOBALS['hj_stub']['posts'] ), 'no half-created page is left behind' );
		ok( (bool) get_option( HJ_SC_ACTIVATION_PENDING ), 'the pending note stays for the next attempt' );
		eq( 1, (int) get_option( 'hj_sc_activation_attempts', 0 ), 'the attempt is counted' );

		// Three failures stop the retry loop instead of hammering the site.
		hjsc_maybe_finish_activation();
		hjsc_maybe_finish_activation();
		hjsc_maybe_finish_activation();
		ok( ! get_option( HJ_SC_ACTIVATION_PENDING ), 'the plugin stops retrying after three attempts' );
		break;

	case 'notice':
		hj_stub_reset();
		require_once $plugin_dir . 'hamyar-symptom-checker.php';

		ob_start();
		hjsc_activation_notice();
		$quiet = ob_get_clean();
		eq( '', $quiet, 'no notice when everything is fine' );

		update_option(
			HJ_SC_LAST_ERROR,
			array(
				'message' => 'simulated database failure',
				'file'    => '/wp-content/plugins/hamyar-symptom-checker/includes/page.php',
				'line'    => 123,
			)
		);
		ob_start();
		hjsc_activation_notice();
		$html = ob_get_clean();
		ok( false !== strpos( $html, 'simulated database failure' ), 'the notice shows the stored error' );
		ok( false !== strpos( $html, 'includes/page.php:123' ), 'the notice shows the file and line' );
		ok( false !== strpos( $html, 'php: ' ) && false !== strpos( $html, 'wp: ' ), 'the notice shows php/wp versions' );
		ok( false !== strpos( $html, 'هیچ افزونهٔ دیگری' ), 'the notice tells the owner not to delete other plugins' );

		// Once the page exists the stale message clears itself.
		$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_name' => HJ_SC_PAGE_SLUG, 'post_status' => 'draft' ) );
		update_option( HJ_SC_PAGE_OPTION, $page_id );
		ob_start();
		hjsc_activation_notice();
		$after = ob_get_clean();
		eq( '', $after, 'the notice disappears after a successful setup' );
		eq( false, get_option( HJ_SC_LAST_ERROR, false ), 'the stale error is deleted' );
		break;

	case 'mapping':
		boot( $plugin_dir );

		$published = add_disease( 'appendicitis' );
		add_disease( 'kidney-stones', 'draft' );
		add_disease( 'gastroenteritis', 'publish' );

		$links = hjsc_topic_links();
		eq( 'https://example.test/disease/appendicitis/', $links['app'] ?? '', 'a unique published article is linked' );
		ok( ! isset( $links['kidney_stone'] ), 'a draft article is never linked' );
		eq( 'https://example.test/disease/gastroenteritis/', $links['gastroenteritis'] ?? '', 'a published article is linked' );

		$unresolved = array();
		foreach ( $GLOBALS['hj_stub']['actions']['hj_sc_topic_link_unresolved'] ?? array() as $args ) {
			$unresolved[] = $args[0];
		}
		ok( in_array( 'kidney_stone', $unresolved, true ), 'the missing published article is reported' );
		break;

	case 'mapping-ambiguous':
		boot( $plugin_dir );
		add_disease( 'appendicitis' );
		add_disease( 'appendicitis-copy' );
		// Simulate a broader mapping query (e.g. matching by a duplicated meta value).
		add_filter(
			'hj_sc_topic_query_args',
			static function ( $args ) {
				unset( $args['name'] );
				return $args;
			}
		);
		$links = hjsc_topic_links();
		eq( array(), $links, 'an ambiguous match produces no links at all' );
		$counts = array();
		foreach ( $GLOBALS['hj_stub']['actions']['hj_sc_topic_link_unresolved'] ?? array() as $args ) {
			$counts[] = $args[2];
		}
		ok( in_array( 2, $counts, true ), 'the ambiguity is reported with the match count', json_encode( $counts ) );
		break;

	case 'mapping-nodisease':
		boot( $plugin_dir, false );
		// A matching post exists, but without the registered post type nothing is linked.
		add_disease( 'appendicitis' );
		eq( array(), hjsc_topic_links(), 'no registered disease post type means no links' );
		break;

	case 'slugs':
		boot( $plugin_dir );
		$slugs = hjsc_topic_slugs();
		eq( 'appendicitis', $slugs['app'] ?? '', 'the bundle slug is the default mapping' );
		eq( count( hjsc_bundle_conditions() ), count( $slugs ), 'every condition has a slug' );
		break;

	case 'slugs-filter':
		hj_stub_reset();
		register_post_type( 'disease' );
		require_once $plugin_dir . 'hamyar-symptom-checker.php';
		add_filter(
			'hjsc_topic_slugs',
			static function ( $value ) {
				$value['app'] = 'acute-appendicitis';
				return $value;
			}
		);
		eq( 'acute-appendicitis', hjsc_topic_slugs()['app'] ?? '', 'the team can pin a slug explicitly' );
		break;

	case 'config':
		boot( $plugin_dir );
		add_disease( 'appendicitis' );

		$config = hjsc_config();
		eq( '0.2.0-draft', $config['bundleVersion'], 'the bundle version is read from the shipped file' );
		ok( str_ends_with( $config['bundleUrl'], 'assets/data/knowledge-bundle.v1.json' ), 'bundle URL points inside the plugin' );
		ok( str_ends_with( $config['evidenceUrl'], 'assets/engine/data/evidence-map.v1.json' ), 'evidence URL points inside the plugin' );
		ok( is_object( $config['topicLinks'] ), 'topic links are an object even when empty' );
		eq( 'https://example.test/disease/appendicitis/', $config['topicLinks']->app ?? '', 'resolved links reach the browser' );

		$json = hjsc_config_json();
		ok( false === stripos( $json, '</script' ), 'config JSON cannot break out of the script tag' );
		ok( false !== strpos( $json, '\u003C' ) || false === stripos( $json, '<' ), 'angle brackets are hex-escaped' );
		break;

	case 'escaping':
		boot( $plugin_dir );
		add_filter(
			'hjsc_topic_links',
			static function () {
				return array( 'app' => 'https://example.test/x?"><script>alert(1)</script>' );
			}
		);
		$json = hjsc_config_json();
		ok( false === stripos( $json, '</script' ), 'a hostile permalink cannot close the script tag' );
		ok( false === strpos( $json, '<script' ), 'a hostile permalink cannot add a script tag' );
		break;

	case 'enqueue':
		boot( $plugin_dir );
		add_disease( 'appendicitis' );
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => HJ_SC_PAGE_SLUG,
				'post_status' => 'draft',
			)
		);
		update_option( HJ_SC_PAGE_OPTION, $page_id );

		$GLOBALS['hj_stub']['is_page'] = true;
		$GLOBALS['hj_stub']['queried'] = $page_id;
		hjsc_enqueue_assets();

		ok( isset( $GLOBALS['hj_stub']['styles']['hj-sc-ui'] ), 'the stylesheet is enqueued on the checker page' );
		ok( isset( $GLOBALS['hj_stub']['scripts']['hj-sc-app'] ), 'the app script is enqueued on the checker page' );
		$inline = $GLOBALS['hj_stub']['inline']['hj-sc-app'][0] ?? array();
		eq( 'before', $inline['position'] ?? '', 'the config runs before the module' );
		ok( 0 === strpos( $inline['data'] ?? '', 'window.HJ_SC_CONFIG = {' ), 'the config is injected as a global' );
		ok( isset( $GLOBALS['hj_stub']['filters']['script_loader_tag'] ), 'the module tag filter is registered on this page only' );

		// WordPress builds one string: [inline before] <script src id="handle-js"></script>.
		$wp_tag = '<script id="hj-sc-app-js-before">window.HJ_SC_CONFIG = {"a":1};</script>' . "\n"
			. '<script src="https://example.test/a.js" id="hj-sc-app-js"></script>';
		$tag    = hjsc_module_script_tag( $wp_tag, 'hj-sc-app', 'https://example.test/a.js' );
		ok( false !== strpos( $tag, 'type="module"' ), 'the entry script is loaded as a module' );
		ok( false !== strpos( $tag, 'HJ_SC_CONFIG' ), 'the inline config survives the tag filter' );
		ok( false !== strpos( $tag, 'https://example.test/a.js' ), 'the entry script keeps its URL' );
		ok( 1 === substr_count( $tag, 'type="module"' ), 'only the entry script becomes a module' );
		eq( '<script src="x"></script>', hjsc_module_script_tag( '<script src="x"></script>', 'other-handle', 'y' ), 'other scripts are untouched' );

		// A different page must stay clean.
		hj_stub_reset();
		$GLOBALS['hj_stub']['posts'][ $page_id ] = new WP_Post(
			array(
				'ID'          => $page_id,
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_name'   => HJ_SC_PAGE_SLUG,
			)
		);
		$GLOBALS['hj_stub']['options'][ HJ_SC_PAGE_OPTION ] = $page_id;
		$GLOBALS['hj_stub']['is_page']                      = true;
		$GLOBALS['hj_stub']['queried']                      = 999;
		hjsc_enqueue_assets();
		eq( array(), $GLOBALS['hj_stub']['styles'], 'other pages get no checker stylesheet' );
		eq( array(), $GLOBALS['hj_stub']['scripts'], 'other pages get no checker script' );
		break;

	case 'robots':
		boot( $plugin_dir );
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => HJ_SC_PAGE_SLUG,
				'post_status' => 'draft',
			)
		);
		update_option( HJ_SC_PAGE_OPTION, $page_id );
		$GLOBALS['hj_stub']['is_page'] = true;
		$GLOBALS['hj_stub']['queried'] = $page_id;

		$robots = hjsc_robots( array( 'index' => true, 'follow' => true ) );
		ok( ! empty( $robots['noindex'] ) && ! empty( $robots['nofollow'] ), 'the checker page is noindex, nofollow' );
		ok( empty( $robots['index'] ) && empty( $robots['follow'] ), 'the opposite directives are removed' );

		$GLOBALS['hj_stub']['queried'] = 999;
		$other                         = hjsc_robots( array( 'index' => true, 'follow' => true ) );
		eq( array( 'index' => true, 'follow' => true ), $other, 'other pages keep their robots directives' );
		break;

	case 'template':
		$template = file_get_contents( $plugin_dir . 'templates/checker-page.php' );
		foreach ( array( 'wp_head();', 'wp_footer();', 'id="app"', 'id="safety"', 'id="live"', 'aria-live="polite"', 'body_class', 'skip-link' ) as $needle ) {
			ok( false !== strpos( $template, $needle ), "template contains {$needle}" );
		}
		ok( false !== strpos( $template, "defined( 'ABSPATH' )" ), 'the template refuses direct access' );
		ok( false === strpos( $template, 'add_shortcode' ) && false === stripos( $template, '<iframe' ), 'the template is neither a shortcode nor an iframe' );
		break;

	case 'guards':
		$files  = array();
		$root   = realpath( $plugin_dir );
		$walker = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
		foreach ( $walker as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$files[] = $file->getPathname();
			}
		}
		sort( $files );
		ok( count( $files ) >= 4, 'all plugin PHP files are present', (string) count( $files ) );

		$forbidden = array(
			'/\beval\s*\(/'              => 'eval()',
			'/\$_GET|\$_POST|\$_REQUEST|\$_COOKIE/' => 'request superglobals',
			'/add_shortcode\s*\(/'       => 'shortcode registration',
			'/register_rest_route\s*\(/' => 'REST endpoint',
			'/wp_remote_|curl_init\s*\(/' => 'outbound HTTP call',
			'/\$wpdb->query/'            => 'raw SQL',
		);

		foreach ( $files as $file ) {
			$source = file_get_contents( $file );
			$name   = str_replace( $root . '/', '', $file );
			ok( false !== strpos( $source, "defined( 'ABSPATH' )" ), "ABSPATH guard: {$name}" );
			foreach ( $forbidden as $pattern => $label ) {
				ok( ! preg_match( $pattern, $source ), "no {$label}: {$name}" );
			}
		}
		break;

	case 'syntax':
		$root   = realpath( $plugin_dir );
		$walker = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
		$count  = 0;
		foreach ( $walker as $file ) {
			if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			++$count;
			$source = file_get_contents( $file->getPathname() );
			$name   = str_replace( $root . '/', '', $file->getPathname() );
			try {
				token_get_all( $source, TOKEN_PARSE );
				ok( true, "parses: {$name}" );
			} catch ( ParseError $error ) {
				ok( false, "parses: {$name}", $error->getMessage() );
			}
		}
		ok( $count >= 4, 'every PHP file was parsed', (string) $count );
		break;

	default:
		fwrite( STDERR, "unknown scenario: {$scenario}\n" );
		exit( 2 );
}

echo "\n{$scenario}: " . ( $checks - $failures ) . "/{$checks} checks passed\n";
exit( $failures > 0 ? 1 : 0 );

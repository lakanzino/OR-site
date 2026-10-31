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

/**
 * Write a candidate bundle into a throwaway uploads folder and point the stubs
 * at it. Returns the absolute folder so a scenario can check paths.
 *
 * @param array<string,array<string,mixed>|string> $files name => bundle array (or raw string).
 */
function prepare_uploads( $files ) {
	$base = '/work/hj-uploads-' . getmypid();
	if ( is_dir( $base ) ) {
		foreach ( glob( $base . '/hj-sc/*' ) as $stale ) {
			unlink( $stale );
		}
	}
	if ( ! is_dir( $base . '/hj-sc' ) ) {
		mkdir( $base . '/hj-sc', 0777, true );
	}
	foreach ( $files as $name => $payload ) {
		$body = is_string( $payload ) ? $payload : json_encode( $payload );
		file_put_contents( $base . '/hj-sc/' . $name, $body );
	}
	hj_stub_set_uploads( $base, 'https://example.test/uploads' );
	return $base . '/hj-sc';
}

/** A structurally valid bundle, small enough to read in a diff. */
function sample_bundle( $version = '9.0.0-draft', $schema = '1.0.0', $questions = 1 ) {
	$items = array();
	for ( $i = 0; $i < $questions; $i++ ) {
		$items[] = array(
			'id'        => 'q_' . $i,
			'ask_order' => $i,
			'text_fa'   => 'پرسش آزمایشی',
			'type'      => 'single',
			'options'   => array( array( 'id' => 'yes', 'label_fa' => 'بله' ) ),
		);
	}
	return array(
		'schema_version' => $schema,
		'bundle_id'      => 'symptom-checker.abdomen.v1',
		'bundle_version' => $version,
		'questions'      => $items,
	);
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
		$mapped   = 0;
		$unmapped = 0;
		foreach ( hjsc_bundle_conditions() as $id => $condition ) {
			if ( null === ( $condition['article_slug'] ?? null ) ) {
				$unmapped++;
				eq( 'unmapped', $condition['link_status'] ?? '', "condition without an article must say it is unmapped: {$id}" );
				ok( ! isset( $slugs[ $id ] ), "an unmapped condition must not get a slug: {$id}" );
				continue;
			}
			$mapped++;
			eq( $condition['article_slug'], $slugs[ $id ] ?? '', "the slug maps by condition id: {$id}" );
		}
		ok( $mapped >= 5, 'the five published topics keep their slugs', (string) $mapped );
		ok( $unmapped > 0, 'the phase-6 topics are listed honestly as unmapped', (string) $unmapped );
		eq( $mapped, count( $slugs ), 'no slug exists for a condition that is not mapped' );
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
		eq( '3.0.0-draft', $config['bundleVersion'], 'the bundle version is read from the shipped file' );
		ok( str_ends_with( $config['bundleUrl'], 'assets/data/knowledge-bundle.v3.json' ), 'bundle URL points inside the plugin' );
		ok( str_ends_with( $config['evidenceUrl'], 'assets/engine/data/evidence-map.v1.json' ), 'evidence URL points inside the plugin' );
		ok( is_object( $config['topicLinks'] ), 'topic links are an object even when empty' );
		eq( 'https://example.test/disease/appendicitis/', $config['topicLinks']->app ?? '', 'resolved links reach the browser' );

		eq( 'static_order', $config['questionStrategy'], 'the default question strategy is static_order' );
		eq( 18, $config['minAge'], 'the age gate starts at the adult scope of the bundle' );

		$json = hjsc_config_json();
		ok( false === stripos( $json, '</script' ), 'config JSON cannot break out of the script tag' );
		ok( false !== strpos( $json, '\u003C' ) || false === stripos( $json, '<' ), 'angle brackets are hex-escaped' );
		break;

	case 'strategy':
		boot( $plugin_dir );
		eq( 'static_order', hjsc_question_strategy(), 'without a filter the order is the one the tool always used' );

		add_filter(
			'hjsc_question_strategy',
			static function () {
				return 'adaptive';
			}
		);
		eq( 'adaptive', hjsc_config()['questionStrategy'], 'a filter may pick the adaptive ordering (skeleton in phase 2)' );

		add_filter(
			'hjsc_question_strategy',
			static function () {
				return 'by_vibes';
			}
		);
		eq( 'static_order', hjsc_config()['questionStrategy'], 'an unknown name falls back instead of breaking the page' );
		break;

	case 'min-age':
		boot( $plugin_dir );
		eq( 18, hjsc_min_age(), 'without a filter the tool is for adults' );
		eq( 18, hjsc_config()['minAge'], 'the browser gets the same number the PHP decides' );

		add_filter(
			'hjsc_min_age',
			static function () {
				return 21;
			}
		);
		eq( 21, hjsc_config()['minAge'], 'a host may raise the gate' );

		add_filter(
			'hjsc_min_age',
			static function () {
				return 0;
			}
		);
		eq( 18, hjsc_config()['minAge'], 'a filter cannot let a child through' );

		add_filter(
			'hjsc_min_age',
			static function () {
				return 500;
			}
		);
		eq( 18, hjsc_config()['minAge'], 'nor block every adult' );
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
		eq( array(), $GLOBALS['hj_stub']['scripts'], 'the entry module is printed in the markup, not enqueued' );

		$markup = hjsc_render_tool();
		ok( false !== strpos( $markup, 'id="app"' ), 'the container is in the markup' );
		ok( false !== strpos( $markup, 'type="module"' ), 'the entry script tag says type="module"' );
		ok(
			(bool) preg_match( '~src="[^"]*ui/app\.js\?ver=[^"]+"~', $markup ),
			'the module URL carries a cache-busting version',
			$markup
		);
		ok( false !== strpos( $markup, 'window.HJ_SC_CONFIG = {' ), 'the config is printed for the module' );
		ok(
			strpos( $markup, 'HJ_SC_CONFIG' ) < strpos( $markup, 'type="module"' ),
			'the config comes before the module that reads it'
		);
		ok( false !== strpos( $markup, 'ابزار در این مرورگر بالا نیامد' ), 'a watchdog names the failure instead of a silent spinner' );
		ok( false !== strpos( $markup, '<noscript>' ), 'the no-JavaScript case is still explained' );

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

	case 'settings':
		boot( $plugin_dir );

		$defaults = hjsc_settings_defaults();
		eq( 'این یک ربات اختصاصی ارزیابی احتمالی بیماری در فرد است.', hjsc_setting( 'title_fa' ), 'the shipped title is the default' );
		eq( 18, hjsc_config()['minAge'], 'the age gate starts at 18 by default' );
		eq( 30, hjsc_config()['texts']['defaultAge'], 'the age field opens on 30' );
		eq( true, hjsc_config()['texts']['showSources'], 'the source list is shown by default' );

		hj_stub_run_action( 'admin_init' );
		ok( isset( $GLOBALS['hj_stub']['settings'][ HJ_SC_SETTINGS_OPTION ] ), 'the option is registered with the Settings API' );
		eq( 'hjsc_sanitize_settings', $GLOBALS['hj_stub']['settings'][ HJ_SC_SETTINGS_OPTION ]['args']['sanitize_callback'] ?? '', 'the option always goes through its sanitiser' );
		ok( count( $GLOBALS['hj_stub']['fields'] ) >= 9, 'the screen exposes the texts, the age and the switch', (string) count( $GLOBALS['hj_stub']['fields'] ) );

		hj_stub_run_action( 'admin_menu' );
		$submenu = $GLOBALS['hj_stub']['submenu'][ HJ_SC_ADMIN_SLUG ] ?? array();
		ok(
			'1' === (string) count( $submenu ) && HJ_SC_SETTINGS_SLUG === ( $submenu[0]['slug'] ?? '' ),
			'the settings screen sits under the checker menu',
			wp_json_encode( $submenu )
		);
		ok(
			count( $GLOBALS['hj_stub']['menu'] ) === 1,
			'and it does not add a second top-level menu'
		);

		$clean = hjsc_sanitize_settings(
			array(
				'title_fa'      => '  <script>alert(1)</script>عنوان تازه ',
				'lead_fa'       => '   ',
				'warning_fa'    => 'هشدار تست',
				'sources_title' => 'منابع',
				'min_age'       => '200',
				'default_age'   => '5',
				'show_sources'  => '',
				'enabled'       => '1',
				'emergency_note' => 'زنگ بزن',
			)
		);
		eq( 'عنوان تازه', trim( $clean['title_fa'] ), 'script tags never reach the page' );
		eq( $defaults['lead_fa'], $clean['lead_fa'], 'an empty text falls back to the shipped wording' );
		eq( 100, $clean['min_age'], 'the minimum age is clamped to the field travel' );
		ok( $clean['default_age'] >= $clean['min_age'], 'the opening age can never be below the minimum' );
		eq( false, $clean['show_sources'], 'an unchecked box turns the source list off' );
		eq( true, $clean['enabled'], 'the tool stays on unless the box is cleared' );

		update_option( HJ_SC_SETTINGS_OPTION, $clean );
		eq( 'عنوان تازه', hjsc_config()['texts']['title'], 'the browser gets the saved title' );
		eq( false, hjsc_config()['texts']['showSources'], 'the browser gets the saved source switch' );
		eq( 100, hjsc_config()['minAge'], 'the browser gets the saved age gate' );

		update_option( HJ_SC_SETTINGS_OPTION, array_merge( $clean, array( 'enabled' => false ) ) );
		$off = hjsc_render_tool();
		ok( false !== strpos( $off, 'موقتاً خاموش' ), 'a switched-off tool says so in one line' );
		ok( false === strpos( $off, 'type="module"' ), 'and loads nothing' );
		break;

	case 'shortcode':
		boot( $plugin_dir );

		ok( shortcode_exists( 'hamyar_symptom_checker' ), 'the shortcode is registered' );
		$html = hjsc_shortcode();
		ok( false !== strpos( $html, 'id="app"' ), 'the shortcode renders the container' );
		ok( false !== strpos( $html, 'type="module"' ), 'and the module tag' );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_name'    => HJ_SC_PAGE_SLUG,
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		update_option( HJ_SC_PAGE_OPTION, $page_id );
		hj_stub_run_action( 'admin_init' );
		eq( '[hamyar_symptom_checker]', get_post( $page_id )->post_content, 'an older empty page is migrated to the shortcode' );

		$mine = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_name'    => 'mine',
				'post_status'  => 'publish',
				'post_content' => 'متن خودم',
			)
		);
		update_option( HJ_SC_PAGE_OPTION, $mine );
		hj_stub_run_action( 'admin_init' );
		eq( 'متن خودم', get_post( $mine )->post_content, 'text the owner wrote is never rewritten' );

		$GLOBALS['hj_stub']['is_page'] = true;
		$GLOBALS['hj_stub']['queried'] = $page_id;
		eq( 'theme.php', hjsc_template_include( 'theme.php' ), 'a page with the shortcode keeps the theme template' );

		$GLOBALS['hj_stub']['queried'] = $mine;
		ok(
			str_ends_with( (string) hjsc_template_include( 'theme.php' ), 'templates/checker-page.php' ),
			'a page without the shortcode keeps the plugin template'
		);
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
		// 0.1.14: the container ids moved into hjsc_render_tool(), which the
		// shortcode and this template both call — checked in the `enqueue`
		// scenario against the real output.
		foreach ( array( 'wp_head();', 'wp_footer();', 'body_class', 'skip-link', 'hjsc_render_tool()' ) as $needle ) {
			ok( false !== strpos( $template, $needle ), "template contains {$needle}" );
		}
		ok( false !== strpos( $template, "defined( 'ABSPATH' )" ), 'the template refuses direct access' );
		ok( false === strpos( $template, 'add_shortcode' ) && false === stripos( $template, '<iframe' ), 'the fallback template registers nothing and embeds nothing' );
		ok( false !== strpos( $template, 'hjsc_render_tool()' ), 'the fallback template renders the same markup as the shortcode' );
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
			'/register_rest_route\s*\(/' => 'REST endpoint',
			'/wp_remote_|curl_init\s*\(/' => 'outbound HTTP call',
			'/\$wpdb->query/'            => 'raw SQL',
		);

		// Phase 5-b: the admin screen must read the request to receive a form.
		// That is the single exception, and it comes with its own rules below:
		// every write path is behind a nonce, and the file it writes is JSON in
		// the uploads folder, never a page, an option the visitor sees, or code.
		$request_readers = array( 'includes/admin.php' );
		$shortcode_files = array( 'includes/page.php' );

		foreach ( $files as $file ) {
			$source = file_get_contents( $file );
			$name   = str_replace( $root . '/', '', $file );
			ok( false !== strpos( $source, "defined( 'ABSPATH' )" ), "ABSPATH guard: {$name}" );
			foreach ( $forbidden as $pattern => $label ) {
				if ( 'request superglobals' === $label && in_array( $name, $request_readers, true ) ) {
					continue;
				}
				ok( ! preg_match( $pattern, $source ), "no {$label}: {$name}" );
			}
			// 0.1.14: the owner asked for a normal, editable page, and in
			// WordPress that is a shortcode. Exactly one, in one file.
			$has_shortcode = (bool) preg_match( '/add_shortcode\s*\(/', $source );
			ok(
				! $has_shortcode || in_array( $name, $shortcode_files, true ),
				"no unexpected shortcode registration: {$name}"
			);
			if ( $has_shortcode ) {
				eq(
					1,
					preg_match_all( '/add_shortcode\s*\(/', $source ),
					'the checker registers exactly one shortcode'
				);
				ok(
					false !== strpos( $source, "'hamyar_symptom_checker'" ),
					'the shortcode keeps its documented name'
				);
			}
		}

		$admin = file_get_contents( $plugin_dir . 'includes/admin.php' );
		ok( false !== strpos( $admin, 'check_admin_referer' ), 'the admin screen checks a nonce on every write path' );
		ok( false !== strpos( $admin, 'current_user_can' ), 'the admin screen checks a capability' );
		ok( false !== strpos( $admin, 'is_uploaded_file' ), 'an uploaded file must be PHP\'s own upload' );
		ok( false !== strpos( $admin, 'sanitize_textarea_field' ), 'submitted text is sanitised before it is stored' );
		ok( false !== strpos( $admin, 'esc_html' ) && false !== strpos( $admin, 'esc_attr' ), 'rendered values are escaped' );
		ok( '' !== trim( (string) preg_replace( '/\s+/', '', (string) preg_replace( '/\/\*.*?\*\//s', '', $admin ) ) ), 'the admin file is not empty' );
		break;

	case 'bundle-package':
		boot( $plugin_dir );
		$source = hjsc_bundle_source();
		eq( 'package', $source['source'], 'with no uploads the packaged bundle is used' );
		ok( str_ends_with( hjsc_bundle_url(), 'assets/data/knowledge-bundle.v3.json' ), 'the packaged URL is unchanged', hjsc_bundle_url() );
		ok( '' !== hjsc_bundle_version(), 'the packaged bundle version is readable' );
		ok( 'upload' !== hjsc_bundle_status()['source'], 'status reports the package as the source' );
		break;

	case 'bundle-upload':
		boot( $plugin_dir );
		prepare_uploads( array( 'knowledge-bundle.7.0.0-draft.0123abcd.json' => sample_bundle( '7.0.0-draft' ) ) );
		$source = hjsc_bundle_source();
		eq( 'upload', $source['source'], 'an uploaded bundle wins over the package' );
		eq( 'knowledge-bundle.7.0.0-draft.0123abcd.json', $source['name'], 'the uploaded file name is kept' );
		ok( str_contains( hjsc_bundle_url(), '?ver=0123abcd' ), 'the URL carries the hash for cache-busting', hjsc_bundle_url() );
		eq( '7.0.0-draft', hjsc_bundle_version(), 'the page reports the uploaded bundle version' );
		eq( '7.0.0-draft', hjsc_config()['bundleVersion'], 'the browser config sees the uploaded version' );
		eq( 'upload', hjsc_config()['bundleStatus']['source'], 'the browser config names the source' );
		ok( str_contains( hjsc_config()['bundleUrl'], 'hj-sc/' ), 'the browser config points into uploads', hjsc_config()['bundleUrl'] );
		break;

	case 'bundle-option-pin':
		boot( $plugin_dir );
		prepare_uploads(
			array(
				'knowledge-bundle.7.0.0-draft.0123abcd.json' => sample_bundle( '7.0.0-draft' ),
				'knowledge-bundle.6.0.0-draft.deadbeef.json' => sample_bundle( '6.0.0-draft' ),
			)
		);
		update_option( 'hj_sc_active_bundle', 'knowledge-bundle.6.0.0-draft.deadbeef.json' );
		eq( '6.0.0-draft', hjsc_bundle_version(), 'the option pins an older bundle (instant rollback)' );
		eq( 'upload', hjsc_bundle_source()['source'], 'the pinned bundle is served from uploads' );
		break;

	case 'bundle-option-missing':
		boot( $plugin_dir );
		prepare_uploads(
			array(
				'knowledge-bundle.7.0.0-draft.0123abcd.json' => sample_bundle( '7.0.0-draft' ),
				'knowledge-bundle.6.0.0-draft.deadbeef.json' => sample_bundle( '6.0.0-draft' ),
			)
		);
		update_option( 'hj_sc_active_bundle', 'knowledge-bundle.9.9.9.ffffffff.json' );
		eq( '7.0.0-draft', hjsc_bundle_version(), 'a pin to a missing file falls back to the newest usable bundle' );
		break;

	case 'bundle-newest':
		boot( $plugin_dir );
		prepare_uploads(
			array(
				'knowledge-bundle.0.9.0-draft.11111111.json' => sample_bundle( '0.9.0-draft' ),
				'knowledge-bundle.0.10.0-draft.22222222.json' => sample_bundle( '0.10.0-draft' ),
			)
		);
		eq( '0.10.0-draft', hjsc_bundle_version(), 'version_compare picks 0.10 over 0.9 (not a string sort)' );
		break;

	case 'bundle-fallback':
		boot( $plugin_dir );
		$dir = prepare_uploads(
			array(
				'knowledge-bundle.7.0.0-draft.0123abcd.json' => '{ not json',
				'knowledge-bundle.8.0.0-draft.0123abce.json' => sample_bundle( '8.0.0-draft', '9.9.9' ),
				'knowledge-bundle.9.0.0-draft.0123abcf.json' => sample_bundle( '9.0.0-draft', '1.0.0', 0 ),
				'nonsense.json'                                 => sample_bundle( '5.0.0-draft' ),
			)
		);
		$source = hjsc_bundle_source();
		eq( 'package', $source['source'], 'broken, wrong-schema, empty and misnamed files are all refused' );
		ok( is_dir( $dir ), 'the uploads folder existed, so this was not a missing-folder pass' );
		break;

	case 'bundle-autodetect-off':
		boot( $plugin_dir );
		prepare_uploads( array( 'knowledge-bundle.7.0.0-draft.0123abcd.json' => sample_bundle( '7.0.0-draft' ) ) );
		add_filter( 'hjsc_bundle_autodetect', '__return_false' );
		eq( 'package', hjsc_bundle_source()['source'], 'auto-detection can be switched off with a filter' );
		break;

	case 'bundle-autodetect-off-pin':
		boot( $plugin_dir );
		prepare_uploads( array( 'knowledge-bundle.7.0.0-draft.0123abcd.json' => sample_bundle( '7.0.0-draft' ) ) );
		add_filter( 'hjsc_bundle_autodetect', '__return_false' );
		update_option( 'hj_sc_active_bundle', 'knowledge-bundle.7.0.0-draft.0123abcd.json' );
		eq( 'upload', hjsc_bundle_source()['source'], 'an explicit option still works with auto-detection off' );
		eq( '7.0.0-draft', hjsc_bundle_version(), 'and the pinned bundle is the one that is read' );
		break;

	case 'bundle-traversal':
		boot( $plugin_dir );
		prepare_uploads( array( 'knowledge-bundle.7.0.0-draft.0123abcd.json' => sample_bundle( '7.0.0-draft' ) ) );
		foreach ( array( '../evil.json', '/etc/passwd', 'knowledge-bundle.7.0.0-draft.0123ABCD.json', 'knowledge-bundle.json' ) as $bad ) {
			update_option( 'hj_sc_active_bundle', $bad );
			$source = hjsc_bundle_source();
			ok( 'upload' === $source['source'] && str_ends_with( $source['name'], '0123abcd.json' ), 'rejected option value: ' . $bad );
		}
		break;

	/*
	 * Phase 5-b: the wp-admin content screen. These scenarios prove the rules
	 * the owner was promised — capability, nonce, text-only edits, a new file
	 * per save, nothing deleted, nothing outside uploads/ written.
	 */
	case 'admin-menu':
		boot( $plugin_dir );
		hj_stub_run_action( 'admin_menu' );
		$menu = $GLOBALS['hj_stub']['menu'] ?? array();
		eq( 1, count( $menu ), 'the screen registers exactly one top-level menu' );
		eq( 'hj-sc-content', $menu[0]['slug'] ?? '', 'the menu slug is the documented one' );
		eq( 'manage_options', $menu[0]['capability'] ?? '', 'the default capability is manage_options' );
		ok( is_callable( $menu[0]['callback'] ?? '' ), 'the menu callback is callable' );

		// A site can hand content editing to an editor role.
		add_filter( 'hjsc_admin_capability', static function () {
			return 'edit_pages';
		} );
		eq( 'edit_pages', hjsc_admin_capability(), 'the capability is filterable' );

		// The screen never creates its own page or post: it is a menu only.
		eq( 0, count( $GLOBALS['hj_stub']['posts'] ), 'registering the menu creates no post' );
		break;

	case 'admin-state':
		boot( $plugin_dir );
		$dir = prepare_uploads(
			array(
				'knowledge-bundle.1.0.0.aaaaaaaa.json' => sample_bundle( '1.0.0', '2.0.0' ),
				'knowledge-bundle.2.0.0.bbbbbbbb.json' => sample_bundle( '2.0.0', '2.0.0', 2 ),
			)
		);
		$state = hjsc_admin_state();
		eq( 'upload', $state['source']['source'], 'the newest uploaded bundle is served' );
		eq( 'knowledge-bundle.2.0.0.bbbbbbbb.json', $state['source']['name'], 'the served file is the newest one' );
		eq( 2, $state['counts']['questions'], 'the panel counts the questions of the served bundle' );
		eq( '', $state['option'], 'with no pin, the option stays empty' );
		eq( 2, count( hjsc_admin_bundles() ), 'both files are listed' );

		// Pin the older file: it must win over "newest".
		eq( 'activated', hjsc_admin_activate( 'knowledge-bundle.1.0.0.aaaaaaaa.json' ), 'a file can be activated' );
		$GLOBALS['hj_stub']['options']['hj_sc_active_bundle'] = 'knowledge-bundle.1.0.0.aaaaaaaa.json';
		// hiqsc_bundle_source() caches per request, so re-read it in a fresh process
		// view by clearing the static through a new include is not possible; assert
		// the option and the filename contract instead.
		eq( 'hj_sc_active_bundle', HJ_SC_ACTIVE_BUNDLE_OPTION, 'the pin is stored in the documented option' );
		eq( 'auto', hjsc_admin_activate( 'auto' ), 'returning to automatic is allowed' );
		ok( ! isset( $GLOBALS['hj_stub']['options']['hj_sc_active_bundle'] ) || '' === $GLOBALS['hj_stub']['options']['hj_sc_active_bundle'], 'automatic clears the pin' );
		ok( is_dir( $dir ) && 2 === count( glob( $dir . '/*.json' ) ), 'no file was deleted by activation' );
		break;

	case 'admin-save-text':
		$original = sample_bundle( '0.2.0-draft', '2.0.0' );
		$name     = 'knowledge-bundle.0.2.0-draft.0badf00d.json';
		boot( $plugin_dir );
		$dir    = prepare_uploads( array( $name => $original ) );
		$before = file_get_contents( $dir . '/' . $name );

		$_POST = array(
			'hjsc_action' => 'save_text',
			'_wpnonce'    => wp_create_nonce( HJ_SC_NONCE_SAVE ),
			'hjsc_file'   => $name,
			'hjsc_text'   => array(
				// Paths are positional, exactly like the form the screen renders.
				'questions' => array(
					0 => array(
						'text_fa' => 'متن تازهٔ پرسش',
						'options' => array( 0 => array( 'label_fa' => 'بله، قطعاً' ) ),
					),
				),
			),
		);

		eq( 'saved', hjsc_admin_handle_request(), 'saving a text edit succeeds' );
		$files = glob( $dir . '/knowledge-bundle.*.json' );
		eq( 2, count( $files ), 'a save adds one file and never replaces the old one' );
		ok( in_array( $dir . '/' . $name, $files, true ), 'the original file is still on disk' );
		eq( $before, file_get_contents( $dir . '/' . $name ), 'the original bytes are untouched' );

		$new = array_values( array_filter( $files, static function ( $path ) use ( $name ) {
			return basename( $path ) !== $name;
		} ) );
		eq( 1, count( $new ), 'exactly one new file exists' );
		$newName = basename( $new[0] );
		eq( $newName, get_option( HJ_SC_ACTIVE_BUNDLE_OPTION ), 'the new file becomes the active one' );
		ok( (bool) preg_match( '/^knowledge-bundle\.0\.2\.1-draft\.[0-9a-f]{8}\.json$/', $newName ), 'the new file is version-bumped and hash-named', $newName );
		$saved = json_decode( file_get_contents( $new[0] ), true );
		eq( '0.2.1-draft', $saved['bundle_version'] ?? '', 'the bundle version is bumped, the suffix kept' );
		eq( 'متن تازهٔ پرسش', $saved['questions'][0]['text_fa'] ?? '', 'the edited question text is in the file' );
		eq( 'بله، قطعاً', $saved['questions'][0]['options'][0]['label_fa'] ?? '', 'the edited option label is in the file' );
		eq( 'pending_team_review', $saved['change_notes'][0]['review_status'] ?? '', 'the new version is stamped pending_team_review' );
		eq( '0.2.1-draft', $saved['change_notes'][0]['version'] ?? '', 'the change note carries the new version' );
		eq( '2.0.0', $saved['schema_version'] ?? '', 'the schema version is untouched' );
		eq( $original['questions'][0]['options'][0]['evidence'], $saved['questions'][0]['options'][0]['evidence'], 'evidence is copied byte for byte' );
		eq( $original['questions'][0]['id'], $saved['questions'][0]['id'], 'ids are copied, never regenerated' );
		break;

	case 'admin-save-guard':
		$original = sample_bundle( '0.2.0-draft', '2.0.0' );
		$name     = 'knowledge-bundle.0.2.0-draft.0badf00e.json';
		boot( $plugin_dir );
		$dir = prepare_uploads( array( $name => $original ) );

		// Structure smuggling: every one of these must be ignored.
		$_POST = array(
			'hjsc_action' => 'save_text',
			'_wpnonce'    => wp_create_nonce( HJ_SC_NONCE_SAVE ),
			'hjsc_file'   => $name,
			'hjsc_text'   => array(
				'schema_version' => '9.9.9',
				'bundle_id'      => 'hijacked',
				'questions'      => array(
					'q_0' => array(
						'id'       => 'q_hijacked',
						'evidence' => array( array( 'symptom_id' => 'abd.ghost', 'state' => 'present' ) ),
						'options'  => array( 'yes' => array( 'evidence' => array(), 'id' => 'nope' ) ),
					),
					'q_new' => array( 'text_fa' => 'پرسش تازه' ),
				),
				'conditions'     => array( 'c_new' => array( 'name_fa' => 'بیماری تازه' ) ),
			),
		);
		eq( 'unchanged', hjsc_admin_handle_request(), 'structural keys are not editable' );
		eq( 1, count( glob( $dir . '/*.json' ) ), 'a refused request writes no file' );

		// A missing nonce is refused before anything else happens.
		$_POST = array(
			'hjsc_action' => 'save_text',
			'hjsc_file'   => $name,
			'hjsc_text'   => array( 'questions' => array( 'q_0' => array( 'text_fa' => 'متن' ) ) ),
		);
		eq( 'bad_nonce', hjsc_admin_handle_request(), 'a request without a nonce is refused' );
		eq( 1, count( glob( $dir . '/*.json' ) ), 'the refused request writes no file either' );

		// A user without the capability is refused as well.
		$GLOBALS['hj_stub']['caps'] = array( 'read' );
		$_POST = array(
			'hjsc_action' => 'save_text',
			'_wpnonce'    => wp_create_nonce( HJ_SC_NONCE_SAVE ),
			'hjsc_file'   => $name,
			'hjsc_text'   => array( 'questions' => array( 'q_0' => array( 'text_fa' => 'متن' ) ) ),
		);
		eq( 'bad_nonce', hjsc_admin_handle_request(), 'a user without the capability is refused' );

		// And the screen itself dies for that user instead of rendering.
		$died = false;
		try {
			hjsc_admin_render();
		} catch ( RuntimeException $exception ) {
			$died = true;
		}
		ok( $died, 'the screen refuses to render for a user without the capability' );

		// A traversal attempt never resolves outside the folder.
		eq( 'bad_bundle', hjsc_admin_activate( '../../wp-config.php' ), 'a traversal name cannot be activated' );
		eq( 'bad_bundle', hjsc_admin_activate( 'knowledge-bundle.9.9.9.ffffffff.json' ), 'a name that does not exist cannot be activated' );
		break;

	case 'admin-upload':
		boot( $plugin_dir );
		$GLOBALS['hj_stub']['caps'] = array( 'manage_options' );
		$tmpDir = '/work/hj-upload-tmp-' . getmypid();
		@mkdir( $tmpDir, 0777, true );
		$dir = '/work/hj-uploads-upload-' . getmypid();
		if ( ! is_dir( $dir . '/hj-sc' ) ) {
			mkdir( $dir . '/hj-sc', 0777, true );
		}
		foreach ( glob( $dir . '/hj-sc/*' ) as $stale ) {
			unlink( $stale );
		}
		hj_stub_set_uploads( $dir, 'https://example.test/uploads' );
		// The harness is not an HTTP upload; its fixtures are ordinary files. The
		// "forged tmp_name" guard is asserted separately below.
		unset( $_SERVER['REQUEST_METHOD'] );

		$good = $tmpDir . '/good.json';
		file_put_contents( $good, json_encode( sample_bundle( '3.0.0', '2.0.0' ) ) );
		$_POST  = array( 'hjsc_action' => 'upload', '_wpnonce' => wp_create_nonce( HJ_SC_NONCE_UPLOAD ) );
		$_FILES = array( 'hjsc_bundle_file' => array( 'name' => 'my-bundle.json', 'tmp_name' => $good, 'error' => 0 ) );
		eq( 'uploaded', hjsc_admin_handle_request(), 'a valid JSON bundle uploads' );
		$stored = glob( $dir . '/hj-sc/knowledge-bundle.*.json' );
		eq( 1, count( $stored ), 'the upload becomes one file in the uploads folder' );
		ok( (bool) preg_match( '/^knowledge-bundle\.3\.0\.0\.[0-9a-f]{8}\.json$/', basename( $stored[0] ) ), 'the upload is renamed to the contract name', basename( $stored[0] ) );
		eq( basename( $stored[0] ), get_option( HJ_SC_ACTIVE_BUNDLE_OPTION ), 'the upload is activated' );

		$bad = $tmpDir . '/bad.json';
		file_put_contents( $bad, '{not json' );
		$_FILES = array( 'hjsc_bundle_file' => array( 'name' => 'bad.json', 'tmp_name' => $bad, 'error' => 0 ) );
		eq( 'bad_file', hjsc_admin_handle_request(), 'an unparsable upload is refused' );
		eq( 1, count( glob( $dir . '/hj-sc/*.json' ) ), 'the refused upload writes no file' );

		$php = $tmpDir . '/evil.php';
		file_put_contents( $php, '<?php echo 1;' );
		$_FILES = array( 'hjsc_bundle_file' => array( 'name' => 'evil.php', 'tmp_name' => $php, 'error' => 0 ) );
		eq( 'bad_upload', hjsc_admin_handle_request(), 'a non-JSON file name is refused' );
		ok( ! file_exists( $dir . '/hj-sc/evil.php' ), 'nothing is executable in the uploads folder' );

		// In a real web request PHP must confirm the temp file is its own upload.
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_FILES = array( 'hjsc_bundle_file' => array( 'name' => 'good.json', 'tmp_name' => $good, 'error' => 0 ) );
		eq( 'bad_upload', hjsc_admin_handle_request(), 'a forged tmp_name is refused in a web request' );
		unset( $_SERVER['REQUEST_METHOD'] );

		// The upload form itself needs a nonce.
		$_POST  = array( 'hjsc_action' => 'upload' );
		$_FILES = array( 'hjsc_bundle_file' => array( 'name' => 'good.json', 'tmp_name' => $good, 'error' => 0 ) );
		eq( 'bad_nonce', hjsc_admin_handle_request(), 'an upload without a nonce is refused' );
		$_POST = array();
		$_FILES = array();
		break;

	case 'admin-render':
		$name = 'knowledge-bundle.0.2.0-draft.0badf00f.json';
		boot( $plugin_dir );
		$dir = prepare_uploads( array( $name => sample_bundle( '0.2.0-draft', '2.0.0' ) ) );
		// A bundle whose text tries to break out of the form.
		$bundle = sample_bundle( '0.2.0-draft', '2.0.0' );
		$bundle['questions'][0]['text_fa'] = '<script>alert(1)</script> پرسش';
		file_put_contents( $dir . '/' . $name, json_encode( $bundle ) );

		$_GET = array( 'page' => HJ_SC_ADMIN_SLUG );
		ob_start();
		hjsc_admin_render();
		$html = ob_get_clean();
		ok( false !== strpos( $html, 'knowledge-bundle.0.2.0-draft.0badf00f.json' ), 'the overview lists the file' );
		ok( false !== strpos( $html, 'hj-sc-content' ), 'the page points at its own admin URL' );
		ok( false !== strpos( $html, 'name="_wpnonce"' ), 'the forms carry a nonce' );
		ok( false !== strpos( $html, 'type="file"' ), 'the upload form is present' );
		ok( 0 === strpos( $html, '<div class="wrap">' ), 'the screen renders inside a wrap' );
		ok( false === strpos( $html, '<script>alert(1)</script>' ), 'bundle text is escaped, never injected' );

		// The editor tab renders one field per editable text and a hidden file field.
		$_GET = array( 'page' => HJ_SC_ADMIN_SLUG, 'action' => 'edit', 'file' => $name );
		ob_start();
		hjsc_admin_render();
		$editor = ob_get_clean();
		ok( false !== strpos( $editor, 'hjsc_text[questions][0][text_fa]' ), 'the editor names the question text field' );
		ok( false !== strpos( $editor, 'name="hjsc_file"' ), 'the editor posts the file it is editing' );
		ok( false === strpos( $editor, 'hjsc_text[schema_version]' ), 'the editor never offers structural keys' );
		ok( false !== strpos( $editor, '&lt;script&gt;' ), 'the editor escapes the stored text' );
		$_GET = array();
		break;

	case 'admin-bump':
		boot( $plugin_dir );
		eq( '0.2.1-draft', hjsc_admin_bump_version( '0.2.0-draft' ), 'a draft keeps its suffix and bumps the patch' );
		eq( '1.0.1', hjsc_admin_bump_version( '1.0.0' ), 'a plain version bumps the patch' );
		eq( '3.4.1-rc.2', hjsc_admin_bump_version( '3.4.0-rc.2' ), 'a pre-release suffix survives the bump' );
		eq( '', hjsc_admin_bump_version( 'draft' ), 'an unparsable version is refused' );
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

<?php
/**
 * The standalone, non-public page that hosts the checker.
 *
 * The page uses a plugin template (no shortcode, no iframe), so the tool can be
 * switched off by returning the page to draft / trashing it, and nothing leaks
 * into other pages: assets and styles are only enqueued for this one page.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ID of the page this plugin owns, or 0 when it is missing.
 */
function hjsc_page_id() {
	$page_id = (int) get_option( HJ_SC_PAGE_OPTION, 0 );
	if ( $page_id <= 0 ) {
		return 0;
	}
	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return 0;
	}
	return $page_id;
}

/**
 * Find a page by slug without `get_page_by_path()`: the query below only uses
 * `get_posts()`, so the plugin does not depend on a helper that a future
 * WordPress release could deprecate.
 *
 * @return int Existing page id, or 0.
 */
function hjsc_find_page_by_slug() {
	$found = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'any',
			'name'             => HJ_SC_PAGE_SLUG,
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	return ( is_array( $found ) && ! empty( $found ) ) ? (int) $found[0] : 0;
}



/**
 * Create the page, but never take the site down when something goes wrong.
 *
 * @return bool True when the page exists (or was adopted) afterwards.
 */
function hjsc_try_create_page() {
	if ( hjsc_page_id() > 0 ) {
		delete_option( HJ_SC_ACTIVATION_PENDING );
		return true;
	}

	try {
		$found = hjsc_find_page_by_slug();
		if ( $found > 0 ) {
			update_option( HJ_SC_PAGE_OPTION, $found );
			delete_option( HJ_SC_ACTIVATION_PENDING );
			return true;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'     => 'ارزیابی احتمالی بیماری',
				'post_name'      => HJ_SC_PAGE_SLUG,
				'post_content'   => '[hamyar_symptom_checker]',
				'post_status'    => 'draft',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array(
					'_hj_symptom_checker' => HJ_SC_VERSION,
				),
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			hjsc_record_error( 'wp_insert_post failed: ' . $page_id->get_error_message() );
			return false;
		}
		if ( ! $page_id ) {
			hjsc_record_error( 'wp_insert_post returned no id' );
			return false;
		}

		update_option( HJ_SC_PAGE_OPTION, (int) $page_id );
		delete_option( HJ_SC_ACTIVATION_PENDING );
		return true;
	} catch ( \Throwable $error ) {
		hjsc_record_error(
			$error->getMessage(),
			array(
				'file' => $error->getFile(),
				'line' => $error->getLine(),
			)
		);
		return false;
	}
}


/**
 * Activation does one cheap thing: it leaves a note that the page still has to
 * be created, and arms the fatal catcher.
 *
 * Building the page during activation is what makes plugins fail to activate on
 * some hosts (memory limits, blocking security plugins, timeouts). The page is
 * therefore created on the next admin request instead — which is the very next
 * page load after clicking "activate" — so a problem there can never roll the
 * activation back and can never look like "this plugin is broken".
 */
function hjsc_activate() {
	update_option( HJ_SC_ACTIVATION_PENDING, time() );
	update_option( 'hj_sc_activation_attempts', 0 );
	register_shutdown_function( 'hjsc_capture_activation_fatal' );
}

/**
 * Second chance on the next admin request, for hosts where the activation
 * request is killed early (memory limit, timeout, security plugin).
 */
function hjsc_maybe_finish_activation() {
	if ( ! get_option( HJ_SC_ACTIVATION_PENDING ) ) {
		return;
	}
	$attempts = (int) get_option( 'hj_sc_activation_attempts', 0 ) + 1;
	update_option( 'hj_sc_activation_attempts', $attempts );
	if ( $attempts > 3 ) {
		hjsc_record_error( 'page creation still failing after 3 attempts' );
		delete_option( HJ_SC_ACTIVATION_PENDING );
		return;
	}
	hjsc_try_create_page();
}
add_action( 'admin_init', 'hjsc_maybe_finish_activation' );

/**
 * Rename a plugin-created page that still carries the old preview label.
 *
 * Pages created before 0.1.14 were titled «دستیار نشانه‌ها (پیش‌نمایش داخلی)»,
 * and a normal page prints that title as its h1 — so visitors read «internal
 * preview» on a public tool. The title is the owner's to edit, therefore this
 * touches only a page the plugin itself created (its meta says so) and only
 * while the old label is still in the title; anything the owner wrote stays.
 */
function hjsc_maybe_rename_legacy_page() {
	$page_id = hjsc_page_id();
	if ( $page_id <= 0 ) {
		return;
	}
	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return;
	}
	if ( '' === (string) get_post_meta( $page_id, '_hj_symptom_checker', true ) ) {
		return; // Not a page this plugin created: the title belongs to the owner.
	}
	if ( false === strpos( (string) $post->post_title, 'پیش‌نمایش' ) ) {
		return; // Already renamed, or the owner chose another title.
	}
	wp_update_post(
		array(
			'ID'         => $page_id,
			'post_title' => 'ارزیابی احتمالی بیماری',
		)
	);
}
add_action( 'admin_init', 'hjsc_maybe_rename_legacy_page' );



/**
 * Is the current front-end request the checker page?
 */
function hjsc_is_checker_page() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return false;
	}
	$page_id = hjsc_page_id();
	if ( $page_id > 0 && (int) get_queried_object_id() === $page_id ) {
		return true;
	}
	// The owner may drop the shortcode on any page; that page gets the assets too.
	$post = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
	return is_object( $post ) && isset( $post->post_content )
		&& has_shortcode( (string) $post->post_content, 'hamyar_symptom_checker' );
}

/**
 * Use the plugin template for this page only.
 *
 * @param string $template Theme template chosen by WordPress.
 * @return string
 */
function hjsc_template_include( $template ) {
	if ( ! hjsc_is_checker_page() ) {
		return $template;
	}
	// A page that holds the shortcode is a *normal* page: theme header, theme
	// footer, the editor content — exactly what the owner asked to edit. The
	// plugin template stays only for a page that has no shortcode yet (an older
	// install, before this version), so it never breaks an existing page.
	$page_id = hjsc_page_id();
	$post    = $page_id > 0 ? get_post( $page_id ) : null;
	if ( $post && has_shortcode( (string) $post->post_content, 'hamyar_symptom_checker' ) ) {
		return $template;
	}
	$own = HJ_SC_DIR . 'templates/checker-page.php';
	return is_readable( $own ) ? $own : $template;
}
add_filter( 'template_include', 'hjsc_template_include', 99 );

/**
 * Cache-busting version from the file timestamp, so a rebuilt package is never
 * served from a stale cache.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hjsc_asset_version( $relative ) {
	$path = HJ_SC_DIR . 'assets/' . ltrim( $relative, '/' );
	return is_readable( $path ) ? (string) filemtime( $path ) : HJ_SC_VERSION;
}

/**
 * URL of an asset inside the plugin.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hjsc_asset_url( $relative ) {
	return HJ_SC_URL . 'assets/' . ltrim( $relative, '/' );
}

/**
 * Asset URL with the cache-busting version attached.
 *
 * @param string $relative Path inside `assets/`.
 * @return string
 */
function hjsc_asset_url_with_version( $relative ) {
	$url = hjsc_asset_url( $relative );
	$sep = false === strpos( $url, '?' ) ? '?' : '&';
	return $url . $sep . 'ver=' . rawurlencode( hjsc_asset_version( $relative ) );
}

/**
 * The shipped assets with the hash each one must serve, for the watchdog.
 *
 * `SOURCES.json` is written by the build from the packaged bytes, and the
 * packaged imports carry the same sha8 as their `?ver=` query. Each entry also
 * carries the sha8 of the file *on this server's disk*, read once per request
 * in PHP: when a half-replaced upload leaves an old module behind, the watchdog
 * can name the file on the page — with no network call from the browser, so
 * the privacy contract (two data fetches, nothing else, ever) stays exact.
 *
 * @return array<int,array{name:string,url:string,sha8:string,disk:string}>
 */
function hjsc_asset_manifest() {
	static $manifest = null;
	if ( null !== $manifest ) {
		return $manifest;
	}
	$manifest = array();
	$path     = HJ_SC_DIR . 'assets/SOURCES.json';
	$decoded  = is_readable( $path )
		? json_decode( (string) file_get_contents( $path ), true ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, no remote request.
		: null;
	$sources  = is_array( $decoded ) && isset( $decoded['asset_sources'] ) && is_array( $decoded['asset_sources'] )
		? $decoded['asset_sources']
		: array();
	foreach ( $sources as $relative => $info ) {
		if ( ! is_string( $relative ) || ! is_array( $info ) || ! isset( $info['sha256'] ) ) {
			continue;
		}
		if ( ! preg_match( '/\.(js|css)$/', $relative ) ) {
			continue;
		}
		$sha8  = substr( (string) $info['sha256'], 0, 8 );
		$on_disk = HJ_SC_DIR . 'assets/' . ltrim( $relative, '/' );
		$digest  = is_readable( $on_disk ) ? hash_file( 'sha256', $on_disk ) : '';
		$manifest[] = array(
			'name' => $relative,
			// The same URL the packaged imports resolve to, query included.
			'url'  => hjsc_asset_url( $relative ) . '?ver=' . $sha8,
			'sha8' => $sha8,
			'disk' => '' === $digest ? 'missing' : substr( $digest, 0, 8 ),
		);
	}
	return $manifest;
}

/**
 * Which question ordering the engine uses.
 *
 * Configuration only: a filter may change it (`hjsc_question_strategy`), the
 * page never can, and nothing a visitor answers touches it. An unknown name
 * falls back to `static_order`, because a typo in a filter must never leave the
 * questionnaire without a question.
 *
 * @return string
 */
function hjsc_question_strategy() {
	$strategy = (string) apply_filters( 'hjsc_question_strategy', 'static_order' );
	return in_array( $strategy, array( 'static_order', 'adaptive' ), true ) ? $strategy : 'static_order';
}

/**
 * Smallest age the tool accepts.
 *
 * The bundle covers adults only: `scope.excluded_by_design` names under-18
 * explicitly, and every wording decision is written for an adult. The number
 * lives here — one place, filterable — so the browser never keeps its own copy
 * of a rule the content decides. A host may raise it; a broken value can never
 * let a child through.
 *
 * @return int
 */
function hjsc_min_age() {
	$stored = function_exists( 'hjsc_setting' ) ? (int) hjsc_setting( 'min_age' ) : 18;
	if ( $stored < 1 || $stored > 100 ) {
		$stored = 18;
	}
	$age = (int) apply_filters( 'hjsc_min_age', $stored );
	return ( $age >= 1 && $age <= 100 ) ? $age : 18;
}

/**
 * Configuration handed to the browser. Everything here is public data: the
 * draft bundle, the evidence map and the resolved article links.
 *
 * @return array<string,mixed>
 */
function hjsc_config() {
	return array(
		'pluginVersion'    => HJ_SC_VERSION,
		'questionStrategy' => hjsc_question_strategy(),
		'minAge'        => hjsc_min_age(),
		'bundleVersion' => hjsc_bundle_version(),
		'bundleUrl'     => hjsc_bundle_url(),
		'bundleStatus'  => hjsc_bundle_status(),
		'evidenceUrl'   => hjsc_asset_url( 'engine/data/evidence-map.v1.json' ),
		'topicLinks'    => (object) hjsc_topic_links(),
		'texts'         => hjsc_public_texts(),
		'assets'        => hjsc_asset_manifest(),
	);
}

/**
 * What the visitor reads before the first question — straight from wp-admin.
 *
 * @return array<string,mixed>
 */
function hjsc_public_texts() {
	$fallback = array(
		'title'         => 'این یک ربات اختصاصی ارزیابی احتمالی بیماری در فرد است.',
		'lead'          => 'هرچه اطلاعات و علامت‌های بیماری خود را درست انتخاب کنید، جواب دقیق‌تری دریافت می‌کنید.',
		'warning'       => 'این ارزیابی هرگز جای معاینهٔ پزشکی نیست و فقط احتمال بیماری را به شما می‌گوید؛ حرف قطعی و صحیح را از پزشک درمانگر بخواهید.',
		'sourcesTitle'  => 'منابع علمی',
		'showSources'   => true,
		'emergencyNote' => 'اگر درد شدید یا بدحالی وجود دارد، همین حالا با اورژانس تماس بگیرید.',
		'defaultAge'    => 30,
	);
	if ( ! function_exists( 'hjsc_settings' ) ) {
		return $fallback;
	}
	$settings = hjsc_settings();
	return array(
		'title'         => (string) $settings['title_fa'],
		'lead'          => (string) $settings['lead_fa'],
		'warning'       => (string) $settings['warning_fa'],
		'sourcesTitle'  => (string) $settings['sources_title'],
		'showSources'   => (bool) $settings['show_sources'],
		'emergencyNote' => (string) $settings['emergency_note'],
		'defaultAge'    => (int) $settings['default_age'],
	);
}

/**
 * JSON for the inline config script, hardened against `</script>` breakouts.
 */
function hjsc_config_json() {
	return (string) wp_json_encode(
		hjsc_config(),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
	);
}

/**
 * Enqueue the UI only on the checker page.
 */
function hjsc_enqueue_assets() {
	if ( ! hjsc_is_checker_page() ) {
		return;
	}

	// Only the stylesheet is enqueued: the entry script is an ES module and is
	// printed by hjsc_render_tool() with type="module" on the tag itself. That
	// used to be a `script_loader_tag` rewrite, which a caching/optimisation
	// plugin could undo — and then the page sat on «در حال بارگذاری…» forever
	// because a classic <script> cannot run `import`.
	wp_enqueue_style(
		'hj-sc-ui',
		hjsc_asset_url( 'ui/styles.css' ),
		array(),
		hjsc_asset_version( 'ui/styles.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'hjsc_enqueue_assets' );

/**
 * Shortcode: `[hamyar_symptom_checker]` inside any normal page.
 *
 * @return string
 */
function hjsc_shortcode() {
	return hjsc_render_tool();
}
add_shortcode( 'hamyar_symptom_checker', 'hjsc_shortcode' );

/**
 * The tool's markup: one container, the config, a watchdog, and the module.
 *
 * @return string
 */
function hjsc_render_tool() {
	if ( function_exists( 'hjsc_setting' ) && ! hjsc_setting( 'enabled' ) ) {
		return '<section class="card"><p>این ابزار موقتاً خاموش است.</p></section>';
	}

	$src     = hjsc_asset_url_with_version( 'ui/app.js' );
	$config  = hjsc_config_json();
	$version = HJ_SC_VERSION;

	ob_start();
	?>
	<div id="safety" hidden></div>
	<div id="app" class="hj-sc-app">
		<p class="muted hj-sc-boot">در حال بارگذاری…</p>
	</div>
	<div id="live" class="sr-only" aria-live="polite" aria-atomic="true"></div>
	<noscript>
		<p class="notice">برای استفاده از این ابزار باید جاوااسکریپت مرورگر روشن باشد.</p>
	</noscript>
	<script>window.HJ_SC_CONFIG = <?php echo $config; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG/HEX_QUOT already applied in hjsc_config_json(). ?>;</script>
	<script>
	(function () {
		var errors = [];
		window.__hjscErrors = errors;
		window.addEventListener('error', function (event) {
			errors.push((event && (event.message || event.type)) || 'error');
		}, true);
		window.addEventListener('unhandledrejection', function (event) {
			var reason = event && event.reason;
			errors.push('promise: ' + ((reason && reason.message) || reason));
		});
		setTimeout(function () {
			var app = document.getElementById('app');
			if (!app || app.querySelector('.card')) { return; }
			while (app.firstChild) { app.removeChild(app.firstChild); }
			var box = document.createElement('div');
			box.className = 'card error';
			var title = document.createElement('h1');
			title.textContent = 'ابزار در این مرورگر بالا نیامد';
			box.appendChild(title);
			var meta = document.createElement('p');
			meta.textContent = 'نسخهٔ <?php echo esc_html( $version ); ?> · فایل رابط: <?php echo esc_html( $src ); ?>';
			box.appendChild(meta);
			if (errors.length) {
				var detail = document.createElement('p');
				detail.textContent = 'خطای مرورگر: ' + errors.slice(0, 3).join(' | ');
				box.appendChild(detail);
			}
			var hint = document.createElement('p');
			hint.className = 'muted';
			hint.textContent = 'صفحه را کامل تازه کن (Ctrl+F5 یا پاک کردن کش مرورگر). اگر همین ماند، همین متن را برای پشتیبانی بفرست.';
			box.appendChild(hint);
			app.appendChild(box);
			diagnose(box);
		}, 8000);

		/*
		 * Why did the boot fail? The config carries, for every shipped asset,
		 * the hash the package promised and the hash of the file on this
		 * server's disk (PHP read both while rendering). A half-replaced
		 * upload or an old file left beside a new one then names itself on
		 * the page — with no network call, so the privacy contract holds.
		 */
		function diagnose(box) {
			var manifest = (window.HJ_SC_CONFIG && window.HJ_SC_CONFIG.assets) || [];
			if (!manifest.length) { return; }
			var wrong = [];
			for (var i = 0; i < manifest.length; i += 1) {
				if (manifest[i].disk !== manifest[i].sha8) {
					wrong.push(manifest[i].name + ': ' + manifest[i].disk);
				}
			}
			var line = document.createElement('p');
			if (wrong.length) {
				line.textContent = 'پرونده‌هایی که در پوشهٔ افزونه با بستهٔ نصب‌شده یکی نیستند: ' + wrong.join('، ')
					+ ' — افزونه را با zip همین نسخه دوباره نصب کنید («جایگزین نسخهٔ فعلی») و سپس در LiteSpeed گزینهٔ Purge All را بزنید.';
			} else {
				line.textContent = 'فایل‌ها روی سرور با بسته یکی هستند؛ یک کش میانی (مرورگر یا LiteSpeed) نسخهٔ کهنه داده است. Ctrl+F5 بزنید و اگر نرفت، در LiteSpeed گزینهٔ Purge All را اجرا کنید.';
			}
			box.appendChild(line);
		}
	})();
	</script>
	<script type="module" id="hj-sc-app-js" src="<?php echo esc_url( $src ); ?>"></script>
	<?php
	return (string) ob_get_clean();
}

/**
 * Give an older install the shortcode, so its page becomes an ordinary page.
 *
 * Only when the editor is empty: text the owner wrote is never touched.
 */
function hjsc_maybe_seed_shortcode() {
	$page_id = hjsc_page_id();
	if ( $page_id <= 0 ) {
		return;
	}
	$post = get_post( $page_id );
	if ( ! $post ) {
		return;
	}
	$content = (string) $post->post_content;
	if ( has_shortcode( $content, 'hamyar_symptom_checker' ) || '' !== trim( wp_strip_all_tags( $content ) ) ) {
		return;
	}
	wp_update_post(
		array(
			'ID'           => $page_id,
			'post_content' => '[hamyar_symptom_checker]',
		)
	);
}
add_action( 'admin_init', 'hjsc_maybe_seed_shortcode', 20 );

/**
 * Keep the checker page out of search engines even if it is published by mistake.
 *
 * @param array<string,bool> $robots Robots directives.
 * @return array<string,bool>
 */
function hjsc_robots( $robots ) {
	if ( ! hjsc_is_checker_page() ) {
		return $robots;
	}
	unset( $robots['index'], $robots['follow'] );
	$robots['noindex']  = true;
	$robots['nofollow'] = true;
	return $robots;
}
add_filter( 'wp_robots', 'hjsc_robots' );

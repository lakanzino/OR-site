<?php
/**
 * Analytics & search-console connections for Hamyar Jarahi (child 0.7.0).
 *
 * Gives the site owner one dashboard page («آمار و اتصال‌ها») where the
 * Google Analytics 4 measurement ID, Microsoft Clarity project ID and the
 * Google Search Console / Bing Webmaster verification tokens are pasted
 * once. The theme then prints the verification meta tags and loads the
 * official GA4/Clarity tags on the front end — no code editing, no plugin.
 *
 * The dashboard tab shows the real connection status, honest local site
 * numbers (published content, sitemap) and one-click deep links into the
 * official GA4 / Search Console / Clarity / Bing reports. It never invents
 * analytics numbers: live third-party metrics stay in their own dashboards
 * (pulling them in would need Google/Microsoft OAuth, e.g. Site Kit).
 *
 * @package Lax_Theme_Child
 * @since   0.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default connection settings.
 *
 * @return array
 */
function hj_an_defaults() {
	return array(
		'ga4_id'           => '',
		'ga4_property_id'  => '',
		'clarity_id'       => '',
		'gsc_verification' => '',
		'bing_verification' => '',
		'gsc_property'     => '',
		'exclude_admins'   => 1,
	);
}

/**
 * Current settings (stored option merged over defaults).
 *
 * @return array
 */
function hj_an_settings() {
	static $settings = null;
	if ( null === $settings ) {
		$stored    = get_option( 'hj_analytics_settings', array() );
		$settings  = array_merge( hj_an_defaults(), is_array( $stored ) ? $stored : array() );
	}
	return $settings;
}

/**
 * Accept either a bare token/ID or a whole pasted meta tag and return the
 * token only. Makes the fields forgiving for non-technical editors.
 *
 * @param string $raw Raw field value.
 * @return string
 */
function hj_an_extract_token( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '/content\s*=\s*"([^"]+)"/i', $raw, $m ) || preg_match( "/content\\s*=\\s*'([^']+)'/i", $raw, $m ) ) {
		$raw = $m[1];
	}
	return sanitize_text_field( $raw );
}

/**
 * Settings sanitize callback: validate every code, drop anything malformed
 * and tell the editor exactly which field was rejected.
 *
 * @param mixed $input Submitted values.
 * @return array
 */
function hj_an_sanitize_settings( $input ) {
	$out   = hj_an_defaults();
	$input = is_array( $input ) ? $input : array();
	$error = static function ( $code, $message ) {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( 'hj_analytics_settings', $code, $message, 'error' );
		}
	};

	$ga4 = strtoupper( hj_an_extract_token( $input['ga4_id'] ?? '' ) );
	if ( '' !== $ga4 ) {
		if ( preg_match( '/^G-[A-Z0-9]{4,}$/', $ga4 ) ) {
			$out['ga4_id'] = $ga4;
		} else {
			$error( 'hj_an_ga4', 'شناسه GA4 معتبر نیست؛ باید شبیه G-XXXXXXXXXX باشد. این فیلد خالی ذخیره شد.' );
		}
	}

	$prop = preg_replace( '/\D+/', '', (string) ( $input['ga4_property_id'] ?? '' ) );
	if ( '' !== $prop ) {
		$out['ga4_property_id'] = $prop;
	}

	$clarity = hj_an_extract_token( $input['clarity_id'] ?? '' );
	if ( '' !== $clarity ) {
		if ( preg_match( '/^[A-Za-z0-9_-]{4,32}$/', $clarity ) ) {
			$out['clarity_id'] = $clarity;
		} else {
			$error( 'hj_an_clarity', 'شناسه پروژه Microsoft Clarity معتبر نیست. این فیلد خالی ذخیره شد.' );
		}
	}

	foreach ( array( 'gsc_verification', 'bing_verification' ) as $key ) {
		$token = hj_an_extract_token( $input[ $key ] ?? '' );
		if ( '' !== $token ) {
			if ( preg_match( '/^[A-Za-z0-9_\-.]{4,160}$/', $token ) ) {
				$out[ $key ] = $token;
			} else {
				$error( 'hj_an_' . $key, 'کد تأیید واردشده معتبر به نظر نمی‌رسد. این فیلد خالی ذخیره شد.' );
			}
		}
	}

	$out['gsc_property']   = sanitize_text_field( (string) ( $input['gsc_property'] ?? '' ) );
	$out['exclude_admins'] = ! empty( $input['exclude_admins'] ) ? 1 : 0;

	return $out;
}

/**
 * Register the settings group.
 */
function hj_an_register_settings() {
	register_setting(
		'hj_analytics',
		'hj_analytics_settings',
		array(
			'sanitize_callback' => 'hj_an_sanitize_settings',
			'default'           => hj_an_defaults(),
		)
	);
}
add_action( 'admin_init', 'hj_an_register_settings' );

/* -------------------------------------------------------------------------
 * Front-end output
 * ---------------------------------------------------------------------- */

/**
 * A dedicated analytics plugin (Site Kit, MonsterInsights, ExactMetrics)
 * already prints the GA tag — never load it twice.
 *
 * @return bool
 */
function hj_an_ga_plugin_active() {
	return defined( 'GOOGLESITEKIT_VERSION' )
		|| defined( 'MONSTERINSIGHTS_VERSION' )
		|| defined( 'EXACTMETRICS_VERSION' )
		|| class_exists( 'MonsterInsights' );
}

/**
 * Whether tracking tags may run for the current request.
 *
 * @param array $settings Settings array.
 * @return bool
 */
function hj_an_should_track( $settings ) {
	if ( is_admin() ) {
		return false;
	}
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}
	if ( ! empty( $settings['exclude_admins'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return false;
	}
	return (bool) apply_filters( 'hj_an_should_track', true );
}

/**
 * Search-engine ownership verification meta tags (Google + Bing).
 * Printed for every visitor so verification also works while logged in.
 */
function hj_an_verification_metas() {
	if ( ! apply_filters( 'hj_an_verification_enabled', true ) ) {
		return;
	}
	$s = hj_an_settings();
	if ( '' !== $s['gsc_verification'] ) {
		printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $s['gsc_verification'] ) );
	}
	if ( '' !== $s['bing_verification'] ) {
		printf( '<meta name="msvalidate.01" content="%s">' . "\n", esc_attr( $s['bing_verification'] ) );
	}
}
add_action( 'wp_head', 'hj_an_verification_metas', 2 );

/**
 * Official GA4 gtag.js loader + config.
 */
function hj_an_enqueue_ga4() {
	$s = hj_an_settings();
	if ( '' === $s['ga4_id'] || hj_an_ga_plugin_active() || ! hj_an_should_track( $s ) ) {
		return;
	}
	wp_enqueue_script(
		'hj-gtag',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $s['ga4_id'] ),
		array(),
		null,
		array(
			'in_footer' => false,
			'strategy'  => 'async',
		)
	);
	wp_add_inline_script(
		'hj-gtag',
		'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $s['ga4_id'] ) . ',{"anonymize_ip":true});',
		'after'
	);
}
add_action( 'wp_enqueue_scripts', 'hj_an_enqueue_ga4', 5 );

/**
 * Official Microsoft Clarity tag.
 */
function hj_an_clarity() {
	$s = hj_an_settings();
	if ( '' === $s['clarity_id'] || ! hj_an_should_track( $s ) ) {
		return;
	}
	printf(
		'<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",%s);</script>' . "\n",
		wp_json_encode( $s['clarity_id'] )
	);
}
add_action( 'wp_head', 'hj_an_clarity', 30 );

/* -------------------------------------------------------------------------
 * Deep links into the official dashboards
 * ---------------------------------------------------------------------- */

/**
 * Resolve the Search Console `resource_id` for this site.
 *
 * @param array $settings Settings array.
 * @return string e.g. "sc-domain:hamyarjarahi.ir" or a URL-prefix.
 */
function hj_an_gsc_property_param( $settings ) {
	$p = trim( (string) ( $settings['gsc_property'] ?? '' ) );
	if ( '' !== $p ) {
		if ( 0 === strpos( $p, 'sc-domain:' ) ) {
			return $p;
		}
		if ( preg_match( '#^https?://#i', $p ) ) {
			return trailingslashit( $p );
		}
		return 'sc-domain:' . preg_replace( '#^www\.#i', '', trim( $p, '/' ) );
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return 'sc-domain:' . preg_replace( '#^www\.#i', '', $host );
}

/**
 * All outbound dashboard links in one place (also used by tests).
 *
 * @param array $settings Settings array.
 * @return array<string,string>
 */
function hj_an_links( $settings ) {
	$resource = rawurlencode( hj_an_gsc_property_param( $settings ) );
	$ga       = 'https://analytics.google.com/analytics/web/';
	if ( '' !== ( $settings['ga4_property_id'] ?? '' ) ) {
		$ga = 'https://analytics.google.com/analytics/web/#/p' . $settings['ga4_property_id'] . '/reports/intelligenthome';
	}
	$clarity = 'https://clarity.microsoft.com/';
	if ( '' !== ( $settings['clarity_id'] ?? '' ) ) {
		$clarity = 'https://clarity.microsoft.com/projects/view/' . rawurlencode( $settings['clarity_id'] ) . '/dashboard';
	}
	return array(
		'ga4'            => $ga,
		'clarity'        => $clarity,
		'gsc_performance' => 'https://search.google.com/search-console/performance/search-analytics?resource_id=' . $resource,
		'gsc_index'      => 'https://search.google.com/search-console/index?resource_id=' . $resource,
		'gsc_sitemaps'   => 'https://search.google.com/search-console/sitemaps?resource_id=' . $resource,
		'gsc_welcome'    => 'https://search.google.com/search-console/welcome',
		'bing'           => 'https://www.bing.com/webmasters/home?siteUrl=' . rawurlencode( home_url( '/' ) ),
		'sitemap'        => home_url( '/wp-sitemap.xml' ),
		'robots'         => home_url( '/robots.txt' ),
		'pagespeed'      => 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( home_url( '/' ) ),
		'rich_results'   => 'https://search.google.com/test/rich-results?url=' . rawurlencode( home_url( '/' ) ),
	);
}

/* -------------------------------------------------------------------------
 * Admin page: menu, assets, rendering
 * ---------------------------------------------------------------------- */

/**
 * Register the «آمار و اتصال‌ها» page right under the GitHub updater.
 */
function hj_an_admin_menu() {
	add_menu_page(
		'آمار و اتصال‌های همیار جراحی',
		'آمار و اتصال‌ها',
		'manage_options',
		'hj-analytics',
		'hj_an_render_page',
		'dashicons-chart-area',
		59
	);
}
add_action( 'admin_menu', 'hj_an_admin_menu' );

/**
 * Small navy admin styling, only on our page.
 *
 * @param string $hook Current admin page hook.
 */
function hj_an_admin_assets( $hook ) {
	if ( 'toplevel_page_hj-analytics' !== $hook ) {
		return;
	}
	wp_register_style( 'hj-an-admin', false, array(), LAX_THEME_CHILD_VERSION );
	wp_enqueue_style( 'hj-an-admin' );
	wp_add_inline_style(
		'hj-an-admin',
		'.hj-an-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:16px 0}'
		. '.hj-an-card{background:#fff;border:1px solid #d7e0ec;border-top:4px solid #06265c;border-radius:10px;padding:16px}'
		. '.hj-an-card h2{margin:0 0 8px;font-size:15px;color:#06265c}'
		. '.hj-an-chip{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:#eef4fa;color:#06265c;border:1px solid #b9c9d9}'
		. '.hj-an-chip.on{background:#e6f4ea;border-color:#7fb98d;color:#14532d}'
		. '.hj-an-code{direction:ltr;text-align:left;font-family:Consolas,monospace}'
		. '.hj-an-actions{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}'
		. '.hj-an-counts{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}'
		. '.hj-an-count{background:#f4f8fc;border:1px solid #d7e0ec;border-radius:8px;padding:6px 12px}'
		. '.hj-an-muted{color:#5b6b7f}'
	);
}
add_action( 'admin_enqueue_scripts', 'hj_an_admin_assets' );

/**
 * Published-content counts per public post type (real local numbers).
 *
 * @return array<string,int> Post type label => published count.
 */
function hj_an_content_counts() {
	$counts = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
		if ( 'attachment' === $type->name ) {
			continue;
		}
		$c = wp_count_posts( $type->name );
		$counts[ $type->labels->name ] = isset( $c->publish ) ? (int) $c->publish : 0;
	}
	return $counts;
}

/**
 * One connection card on the dashboard tab.
 *
 * @param string $title   Service name.
 * @param bool   $on      Whether a code is stored.
 * @param string $detail  Small detail line (the stored ID, or empty).
 * @param string $url     Official dashboard URL.
 * @param string $hint    One-line help text.
 */
function hj_an_card( $title, $on, $detail, $url, $hint ) {
	$settings_url = admin_url( 'admin.php?page=hj-analytics&tab=settings' );
	echo '<div class="hj-an-card">';
	echo '<h2>' . esc_html( $title ) . '</h2>';
	echo '<span class="hj-an-chip' . ( $on ? ' on' : '' ) . '">' . esc_html( $on ? 'متصل است' : 'کد وارد نشده' ) . '</span>';
	if ( '' !== $detail ) {
		echo '<p class="hj-an-code">' . esc_html( $detail ) . '</p>';
	}
	echo '<p class="hj-an-muted">' . esc_html( $hint ) . '</p>';
	echo '<div class="hj-an-actions">';
	echo '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">مشاهده داشبورد رسمی</a>';
	echo '<a class="button" href="' . esc_url( $settings_url ) . '">' . esc_html( $on ? 'ویرایش کد' : 'وارد کردن کد' ) . '</a>';
	echo '</div></div>';
}

/**
 * Dashboard tab: status cards, sitemap, honest local numbers, deep links.
 *
 * @param array $s Settings.
 */
function hj_an_render_dashboard( $s ) {
	$links = hj_an_links( $s );

	if ( ! get_option( 'blog_public' ) ) {
		echo '<div class="notice notice-error"><p><strong>هشدار:</strong> گزینه‌ی «ممانعت از موتورهای جستجو» فعال است؛ تا وقتی در تنظیمات ← خواندن خاموش نشود، سرچ‌کنسول و نقشه‌ی سایت عملاً بی‌اثرند.</p></div>';
	}
	if ( hj_an_ga_plugin_active() ) {
		echo '<div class="notice notice-info"><p>یک افزونه‌ی آنالیتیکس (Site Kit یا مشابه آن) فعال است و تگ GA را خودش بارگذاری می‌کند؛ قالب برای جلوگیری از شمارش دوباره، تگ GA4 خود را چاپ نمی‌کند.</p></div>';
	}

	echo '<div class="hj-an-grid">';
	hj_an_card(
		'گوگل آنالیتیکس (GA4)',
		'' !== $s['ga4_id'],
		$s['ga4_id'],
		$links['ga4'],
		'بازدید، منابع ورودی و رفتار خوانندگان. گزارش زنده در داشبورد رسمی گوگل دیده می‌شود.'
	);
	hj_an_card(
		'مایکروسافت کلریتی (Clarity)',
		'' !== $s['clarity_id'],
		$s['clarity_id'],
		$links['clarity'],
		'نقشه‌ی حرارتی و ضبط جلسه‌ها؛ برای دیدن اینکه بیمار در صفحه کجا گیر می‌کند.'
	);
	hj_an_card(
		'گوگل سرچ‌کنسول',
		'' !== $s['gsc_verification'],
		'' !== $s['gsc_verification'] ? 'meta verification' : '',
		$links['gsc_performance'],
		'جستجوها، نمایش و کلیک در گوگل و وضعیت ایندکس صفحات.'
	);
	hj_an_card(
		'Bing Webmaster',
		'' !== $s['bing_verification'],
		'' !== $s['bing_verification'] ? 'msvalidate.01' : '',
		$links['bing'],
		'دیده‌شدن در بینگ و چت‌های مایکروسافت؛ تأیید مالکیت با متا تگ.'
	);
	echo '</div>';

	echo '<div class="hj-an-grid">';
	echo '<div class="hj-an-card"><h2>نقشه‌ی سایت (خودکار)</h2>';
	echo '<p>نقشه‌ی سایت توسط هسته‌ی وردپرس به‌صورت خودکار و لحظه‌ای ساخته می‌شود و با انتشار هر محتوا خودش تازه است؛ در <span class="hj-an-code">robots.txt</span> هم معرفی شده است.</p>';
	echo '<div class="hj-an-actions">';
	echo '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $links['sitemap'] ) . '">مشاهده wp-sitemap.xml</a>';
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $links['robots'] ) . '">robots.txt</a>';
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $links['gsc_sitemaps'] ) . '">ثبت/بررسی در سرچ‌کنسول</a>';
	echo '</div>';
	echo '<p class="hj-an-muted">اگر هنوز ثبت نشده، در سرچ‌کنسول از بخش Sitemaps نشانی <span class="hj-an-code">wp-sitemap.xml</span> را یک بار ثبت کنید.</p>';
	echo '</div>';

	echo '<div class="hj-an-card"><h2>محتوای منتشرشده همین حالا</h2><div class="hj-an-counts">';
	foreach ( hj_an_content_counts() as $label => $n ) {
		echo '<span class="hj-an-count">' . esc_html( $label ) . ': <strong>' . esc_html( number_format_i18n( $n ) ) . '</strong></span>';
	}
	echo '</div>';
	$last = get_lastpostmodified( 'gmt' );
	if ( $last ) {
		echo '<p class="hj-an-muted">آخرین به‌روزرسانی محتوا: ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $last ) ) ) . '</p>';
	}
	echo '<div class="hj-an-actions">';
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $links['gsc_index'] ) . '">وضعیت ایندکس در گوگل</a>';
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $links['pagespeed'] ) . '">تست سرعت PageSpeed</a>';
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $links['rich_results'] ) . '">تست ریچ‌ریزالت</a>';
	echo '</div></div>';
	echo '</div>';

	echo '<div class="notice notice-info inline"><p><strong>درباره‌ی عددهای زنده داخل پیشخوان:</strong> نمایش نمودارهای GA4 و سرچ‌کنسول داخل خود وردپرس نیازمند اتصال OAuth گوگل است (همان کاری که افزونه‌ی رسمی Site Kit می‌کند). این داشبورد عمداً هیچ عدد ساختگی نشان نمی‌دهد: وضعیت اتصال و اعداد واقعی همین سایت اینجاست و گزارش‌های کامل با یک کلیک در داشبورد رسمی هر سرویس باز می‌شود. اگر بعداً Site Kit نصب شود، قالب به‌صورت خودکار از چاپ دوباره‌ی تگ GA دست می‌کشد.</p></div>';
}

/**
 * Settings tab: the code fields + short how-to for each service.
 *
 * @param array $s Settings.
 */
function hj_an_render_settings( $s ) {
	echo '<form method="post" action="options.php">';
	settings_fields( 'hj_analytics' );
	echo '<table class="form-table" role="presentation">';

	$field = static function ( $key, $label, $value, $help, $placeholder ) {
		echo '<tr><th scope="row"><label for="hj-an-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input id="hj-an-' . esc_attr( $key ) . '" class="regular-text hj-an-code" type="text" dir="ltr" name="hj_analytics_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
		echo '<p class="description">' . esc_html( $help ) . '</p>';
		echo '</td></tr>';
	};

	$field( 'ga4_id', 'شناسه GA4 گوگل (Measurement ID)', $s['ga4_id'], 'در گوگل آنالیتیکس: Admin ← Data streams ← جریان وب سایت ← Measurement ID؛ چیزی شبیه G-XXXXXXXXXX.', 'G-XXXXXXXXXX' );
	$field( 'ga4_property_id', 'شناسه عددی پراپرتی GA4 (اختیاری)', $s['ga4_property_id'], 'فقط برای اینکه دکمه‌ی داشبورد مستقیم به همان پراپرتی برود: Admin ← Property settings ← Property ID (فقط عدد).', '123456789' );
	$field( 'clarity_id', 'شناسه پروژه Microsoft Clarity', $s['clarity_id'], 'در clarity.microsoft.com پروژه بسازید؛ شناسه در Settings پروژه یا داخل کد نصب آن است.', 'abcd1234ef' );
	$field( 'gsc_verification', 'کد تأیید گوگل سرچ‌کنسول', $s['gsc_verification'], 'در سرچ‌کنسول پراپرتی از نوع URL prefix بسازید و روش HTML tag را انتخاب کنید؛ فقط مقدار content یا کل تگ را همین‌جا بچسبانید.', 'google-site-verification token' );
	$field( 'bing_verification', 'کد تأیید Bing Webmaster', $s['bing_verification'], 'در Bing Webmaster روش Meta tag را انتخاب کنید؛ مقدار content تگ msvalidate.01 یا کل تگ را بچسبانید.', 'msvalidate.01 token' );
	$field( 'gsc_property', 'پراپرتی سرچ‌کنسول برای لینک داشبورد (اختیاری)', $s['gsc_property'], 'مثال: sc-domain:hamyarjarahi.ir یا نشانی کامل پراپرتی URL-prefix. خالی بماند، از دامنه‌ی سایت ساخته می‌شود.', 'sc-domain:hamyarjarahi.ir' );

	echo '<tr><th scope="row">ردیابی مدیران</th><td>';
	echo '<label><input type="checkbox" name="hj_analytics_settings[exclude_admins]" value="1"' . checked( 1, (int) $s['exclude_admins'], false ) . '> بازدید مدیرِ واردشده شمرده نشود (پیشنهاد می‌شود)</label>';
	echo '<p class="description">تگ‌های تأیید مالکیت در هر حال چاپ می‌شوند تا تأیید سرچ‌کنسول/بینگ خراب نشود.</p>';
	echo '</td></tr></table>';
	submit_button( 'ذخیره اتصال‌ها' );
	echo '</form>';

	echo '<div class="hj-an-card" style="max-width:860px"><h2>قدم‌های اتصال، خلاصه</h2><ol>';
	echo '<li><strong>سرچ‌کنسول:</strong> پراپرتی بسازید ← کد تأیید را بالا ذخیره کنید ← در سرچ‌کنسول Verify بزنید ← از تب داشبورد، نقشه‌ی سایت را ثبت کنید.</li>';
	echo '<li><strong>GA4:</strong> پراپرتی و جریان وب بسازید ← Measurement ID را بالا ذخیره کنید ← چند دقیقه بعد در گزارش Realtime آنالیتیکس بازدید خودتان را (در پنجره‌ی ناشناس) می‌بینید.</li>';
	echo '<li><strong>Clarity:</strong> پروژه بسازید ← شناسه را بالا ذخیره کنید ← داشبورد کلریتی ظرف چند ساعت اول داده می‌گیرد.</li>';
	echo '<li><strong>حریم خصوصی:</strong> بعد از فعال‌کردن هر تحلیل‌گر، بخش «تحلیل بازدید» برگه‌ی حریم خصوصی را با همان سرویس به‌روز کنید.</li>';
	echo '</ol></div>';
}

/**
 * Page router (tabs).
 */
function hj_an_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	$s   = hj_an_settings();
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
	if ( ! in_array( $tab, array( 'dashboard', 'settings' ), true ) ) {
		$tab = 'dashboard';
	}
	$base = admin_url( 'admin.php?page=hj-analytics' );

	echo '<div class="wrap"><h1>آمار و اتصال‌های همیار جراحی</h1>';
	echo '<nav class="nav-tab-wrapper">';
	echo '<a class="nav-tab' . ( 'dashboard' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base ) . '">داشبورد</a>';
	echo '<a class="nav-tab' . ( 'settings' === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&tab=settings' ) . '">اتصال‌ها و کدها</a>';
	echo '</nav>';

	if ( 'settings' === $tab ) {
		hj_an_render_settings( $s );
	} else {
		hj_an_render_dashboard( $s );
	}
	echo '</div>';
}

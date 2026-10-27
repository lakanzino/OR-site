<?php
/**
 * Indexation health screen (child theme 0.14.2).
 *
 * One read-only admin page that answers the question "why is Google not
 * indexing this site?" without guessing: blog_public switch, permalinks,
 * SEO plugin takeover, core sitemap reachability, virtual robots.txt,
 * Googlebot-UA self-fetch comparison, explicit noindex meta counts,
 * deployment freshness and the homepage surgery-term linking bug.
 *
 * Every check runs live on page load against THIS site; nothing is written.
 *
 * @package Lax_Theme_Child
 * @since   0.14.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register «سلامت ایندکس» under the «آمار و اتصال‌ها» menu.
 *
 * @return void
 */
function hj_shc_admin_menu() {
	add_submenu_page(
		'hj-analytics',
		'سلامت ایندکس گوگل — همیار جراحی',
		'سلامت ایندکس',
		'manage_options',
		'hj-seo-health',
		'hj_shc_render_page'
	);
}
add_action( 'admin_menu', 'hj_shc_admin_menu', 20 );

/**
 * Small navy styling, only on this screen.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function hj_shc_admin_assets( $hook ) {
	// Submenu hook suffix = parent slug + '_page_' + page slug.
	if ( 'hj-analytics_page_hj-seo-health' !== $hook ) {
		return;
	}
	wp_register_style( 'hj-shc-admin', false, array(), LAX_THEME_CHILD_VERSION );
	wp_enqueue_style( 'hj-shc-admin' );
	wp_add_inline_style(
		'hj-shc-admin',
		'.hj-shc-table{margin:16px 0;background:#fff;border:1px solid #d7e0ec;border-radius:10px;border-collapse:collapse;width:100%;max-width:1100px}'
		. '.hj-shc-table th,.hj-shc-table td{padding:10px 12px;border-bottom:1px solid #e6ecf4;text-align:right;vertical-align:top;font-size:13px}'
		. '.hj-shc-table th{background:#f4f7fb;font-weight:700}'
		. '.hj-shc-badge{display:inline-block;min-width:52px;text-align:center;padding:2px 8px;border-radius:999px;color:#fff;font-weight:700;font-size:12px}'
		. '.hj-shc-ok{background:#15803d}.hj-shc-warn{background:#b45309}.hj-shc-bad{background:#b91c1c}'
		. '.hj-shc-detail{color:#334155}.hj-shc-detail code{background:#f1f5f9;padding:1px 5px;border-radius:4px}'
		. '.hj-shc-banner{padding:12px 16px;border-radius:10px;margin:16px 0;max-width:1100px;font-weight:600}'
		. '.hj-shc-banner-ok{background:#dcfce7;color:#14532d;border:1px solid #86efac}'
		. '.hj-shc-banner-warn{background:#fef3c7;color:#78350f;border:1px solid #fcd34d}'
		. '.hj-shc-banner-bad{background:#fee2e2;color:#7f1d1d;border:1px solid #fca5a5}'
		. '.hj-shc-muted{color:#64748b;font-size:12px;margin:8px 0}'
	);
}
add_action( 'admin_enqueue_scripts', 'hj_shc_admin_assets' );

/**
 * Fetch one of the site's own URLs with a fixed timeout.
 *
 * @param string $path       Path relative to home URL.
 * @param string $user_agent Optional User-Agent header.
 * @return array{ok:bool,error?:string,code?:int,content_type?:string,x_robots?:string,body?:string,url:string}
 */
function hj_shc_self_get( $path, $user_agent = '' ) {
	$url  = home_url( $path );
	$args = array(
		'timeout'     => 8,
		'redirection' => 3,
	);
	if ( '' !== $user_agent ) {
		$args['user-agent'] = $user_agent;
	}

	$response = wp_remote_get( $url, $args );
	if ( is_wp_error( $response ) ) {
		return array(
			'ok'    => false,
			'error' => $response->get_error_message(),
			'url'   => $url,
		);
	}

	$body = wp_remote_retrieve_body( $response );
	return array(
		'ok'           => true,
		'code'         => (int) wp_remote_retrieve_response_code( $response ),
		'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
		'x_robots'     => (string) wp_remote_retrieve_header( $response, 'x-robots-tag' ),
		'body'         => is_string( $body ) ? $body : '',
		'url'          => $url,
	);
}

/**
 * Extract a meta robots directive from an HTML body.
 *
 * @param string $body Raw HTML.
 * @return string Directive text, or '' when no robots meta exists.
 */
function hj_shc_extract_meta_robots( $body ) {
	if ( '' === (string) $body ) {
		return '';
	}
	if ( preg_match( '/<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m )
		|| preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']robots["\']/i', $body, $m ) ) {
		return trim( $m[1] );
	}
	return '';
}

/**
 * Build one result row.
 *
 * @param string $status ok|warn|bad.
 * @param string $label  Row title.
 * @param string $detail Raw HTML detail (escaped by caller).
 * @return array{status:string,label:string,detail:string}
 */
function hj_shc_row( $status, $label, $detail ) {
	return array(
		'status' => $status,
		'label'  => $label,
		'detail' => $detail,
	);
}

/**
 * Run all checks and return the ordered row list.
 *
 * @return array<int,array{status:string,label:string,detail:string}>
 */
function hj_shc_collect_checks() {
	$rows       = array();
	$reading    = admin_url( 'options-reading.php' );
	$permalinks = admin_url( 'options-permalink.php' );
	$gsc        = 'https://search.google.com/search-console';

	// 1) blog_public — the only WordPress switch that can noindex the whole site.
	if ( (bool) get_option( 'blog_public' ) ) {
		$rows[] = hj_shc_row(
			'ok',
			'موتورهای جستجو مجازند',
			'گزینه‌ی «ممانعت از موتورهای جستجو» در تنظیمات ← خواندن خاموش است؛ وردپرس متای noindex سراسری نمی‌فرستد.'
		);
	} else {
		$rows[] = hj_shc_row(
			'bad',
			'ممانعت از موتورهای جستجو فعال است',
			'تا وقتی این گزینه در <a href="' . esc_url( $reading ) . '">تنظیمات ← خواندن</a> خاموش نشود، همه‌ی صفحه‌ها (از جمله مقاله‌ها) <code>noindex</code> می‌گیرند و robots.txt هم <code>Disallow: /</code> می‌شود. این محتمل‌ترین علت ایندکس‌نشدن کامل است.'
		);
	}

	// 2) Permalink structure.
	if ( '' !== (string) get_option( 'permalink_structure' ) ) {
		$rows[] = hj_shc_row( 'ok', 'پیوندهای یکتا', 'ساختار پیوند خوانا فعال است و نقشه‌ی سایت قابل ساخت است.' );
	} else {
		$rows[] = hj_shc_row(
			'bad',
			'پیوندهای یکتا روی حالت ساده است',
			'از <a href="' . esc_url( $permalinks ) . '">تنظیمات ← پیوندهای یکتا</a> گزینه‌ی «نام نوشته» را انتخاب کنید؛ بدون آن نشانی مقاله‌ها و سایت‌مپ برای گوگل ضعیف است.'
		);
	}

	// 3) Dedicated SEO plugin takeover.
	$plugin_seo = function_exists( 'hj_seo_plugin_active' ) && hj_seo_plugin_active();
	if ( $plugin_seo ) {
		$rows[] = hj_shc_row(
			'warn',
			'یک افزونه‌ی سئو کنترل ربات‌ها را در دست گرفته',
			'قالب در حضور افزونه‌ی سئو کاملاً سکوت می‌کند؛ متای robots، canonical و سایت‌مپ را تنظیمات همان افزونه تعیین می‌کند. داخل افزونه بررسی کنید که انواع محتوای <code>surgery</code>، <code>patient_guide</code> و <code>or_environment</code> روی «ایندکس» باشند و noindex سراسری/پیش‌فرض فعال نباشد.'
		);
	} else {
		$rows[] = hj_shc_row(
			'ok',
			'افزونه‌ی سئوی جداگانه فعال نیست',
			'متای robots و canonical را خود قالب (<code>inc/seo-basics.php</code>) تولید می‌کند؛ مقاله‌های تکی هرگز noindex نمی‌شوند مگر با متای صریح <code>_hj_noindex</code>.'
		);
	}

	// 4) Core sitemap enablement + live fetch.
	$sitemaps_on = ! function_exists( 'wp_sitemaps_is_enabled' ) || wp_sitemaps_is_enabled();
	$sitemap     = hj_shc_self_get( '/wp-sitemap.xml' );
	if ( ! $sitemaps_on ) {
		$rows[] = hj_shc_row(
			'bad',
			'سایت‌مپ هسته‌ی وردپرس غیرفعال است',
			'کسی فیلتر <code>wp_sitemaps_enabled</code> را روی false گذاشته (افزونه یا قطعه کد). بدون سایت‌مپ، کشف مقاله‌ها فقط به لینک داخلی وابسته می‌شود.'
		);
	} elseif ( ! $sitemap['ok'] ) {
		$rows[] = hj_shc_row(
			'bad',
			'سایت‌مپ قابل باز کردن نیست',
			'درخواست <code>' . esc_html( $sitemap['url'] ) . '</code> با خطا مواجه شد: <code>' . esc_html( $sitemap['error'] ) . '</code>. اگر هاست/افزونه‌ی امنیتی این نشانی را می‌بندد، سرچ‌کنسول هم نمی‌تواند آن را بخواند.'
		);
	} else {
		$sub_count = substr_count( $sitemap['body'], '<sitemap>' );
		$is_xml    = ( 200 === $sitemap['code'] ) && ( false !== strpos( $sitemap['content_type'], 'xml' ) );
		$detail    = 'HTTP ' . $sitemap['code'] . ' · نوع محتوا: <code>' . esc_html( $sitemap['content_type'] ) . '</code> · ' . $sub_count . ' سایت‌مپ فرعی. ';
		$detail   .= '<a href="' . esc_url( $sitemap['url'] ) . '" target="_blank" rel="noopener">باز کردن مستقیم</a>';
		$rows[]    = hj_shc_row( $is_xml ? 'ok' : 'warn', 'سایت‌مپ هسته (<code>wp-sitemap.xml</code>)', $detail );
	}

	// 5) Virtual robots.txt.
	$robots = hj_shc_self_get( '/robots.txt' );
	if ( ! $robots['ok'] ) {
		$rows[] = hj_shc_row(
			'warn',
			'robots.txt قابل خواندن نشد',
			'خطای درخواست: <code>' . esc_html( $robots['error'] ) . '</code>. وجود یک robots.txt فیزیکی روی هاست را هم بررسی کنید.'
		);
	} else {
		$blocks_all  = (bool) preg_match( '/disallow:\s*\/\s*$/im', $robots['body'] );
		$has_sitemap = (bool) preg_match( '/sitemap:/im', $robots['body'] );
		if ( $blocks_all ) {
			$rows[] = hj_shc_row(
				'bad',
				'robots.txt کل سایت را بسته است',
				'عبارت <code>Disallow: /</code> در پاسخ دیده شد؛ گوگل‌بات طبق robots.txt اجازه‌ی خزش هیچ صفحه‌ای را ندارد. علت معمول: فعال بودن «ممانعت از موتورهای جستجو» یا یک robots.txt فیزیکی قدیمی روی هاست.'
			);
		} elseif ( $has_sitemap ) {
			$rows[] = hj_shc_row( 'ok', 'robots.txt', 'خط Sitemap موجود است و مسیری مسدود نشده. <a href="' . esc_url( $robots['url'] ) . '" target="_blank" rel="noopener">مشاهده</a>' );
		} else {
			$rows[] = hj_shc_row(
				'warn',
				'robots.txt خط Sitemap ندارد',
				'نشانی سایت‌مپ در robots.txt اعلام نشده؛ ثبت دستی آن در سرچ‌کنسول این نقص را جبران می‌کند. <a href="' . esc_url( $robots['url'] ) . '" target="_blank" rel="noopener">مشاهده</a>'
			);
		}
	}

	// 6) Googlebot-UA self-fetch vs ordinary UA, homepage.
	$bot_ua   = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
	$normal   = hj_shc_self_get( '/' );
	$bot_home = hj_shc_self_get( '/', $bot_ua );
	if ( ! $bot_home['ok'] ) {
		$rows[] = hj_shc_row(
			'bad',
			'تست گوگل‌بات روی صفحه‌ی اصلی',
			'درخواست با User-Agent گوگل‌بات شکست خورد: <code>' . esc_html( $bot_home['error'] ) . '</code>. اگر این تست از مرورگر شما سالم است، یعنی فایروال هاست/افزونه‌ی امنیتی بازدیدکننده‌ی ناشناس را می‌بندد و گوگل‌بات هم همان رفتار را می‌بیند.'
		);
	} else {
		$bot_meta   = hj_shc_extract_meta_robots( $bot_home['body'] );
		$norm_meta  = $normal['ok'] ? hj_shc_extract_meta_robots( $normal['body'] ) : '';
		$bot_noidx  = ( '' !== $bot_meta && false !== stripos( $bot_meta, 'noindex' ) ) || ( '' !== $bot_home['x_robots'] && false !== stripos( $bot_home['x_robots'], 'noindex' ) );
		$mismatch   = $normal['ok'] && ( $normal['code'] !== $bot_home['code'] || ( $norm_meta !== $bot_meta ) );
		$detail     = 'HTTP ' . $bot_home['code'] . ' · meta robots: <code>' . ( $bot_meta ? esc_html( $bot_meta ) : 'ندارد (یعنی index)' ) . '</code>';
		if ( '' !== $bot_home['x_robots'] ) {
			$detail .= ' · X-Robots-Tag: <code>' . esc_html( $bot_home['x_robots'] ) . '</code>';
		}
		if ( $bot_noidx ) {
			$rows[] = hj_shc_row( 'bad', 'تست گوگل‌بات — صفحه‌ی اصلی', $detail . '؛ یعنی گوگل‌بات عملاً مجاز به ایندکس نیست.' );
		} elseif ( $mismatch ) {
			$rows[] = hj_shc_row( 'warn', 'تست گوگل‌بات — صفحه‌ی اصلی', $detail . ' · پاسخ گوگل‌بات با بازدید عادی فرق دارد (کد/متا)؛ ممکن است کش یا فایروال رفتار متفاوت بدهد.' );
		} else {
			$rows[] = hj_shc_row( 'ok', 'تست گوگل‌بات — صفحه‌ی اصلی', $detail . ' · تفاوتی با بازدید عادی ندارد.' );
		}
	}

	// 7) Same test on one newest article across the three content CPTs.
	$sample = get_posts(
		array(
			'post_type'           => array( 'patient_guide', 'surgery', 'or_environment' ),
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	if ( empty( $sample ) ) {
		$rows[] = hj_shc_row( 'warn', 'تست گوگل‌بات — مقاله', 'هیچ مقاله‌ی منتشرشده‌ای برای آزمایش پیدا نشد.' );
	} else {
		$permalink = get_permalink( $sample[0]->ID );
		$path      = wp_parse_url( (string) $permalink, PHP_URL_PATH );
		$bot_post  = hj_shc_self_get( (string) $path, $bot_ua );
		if ( ! $bot_post['ok'] ) {
			$rows[] = hj_shc_row( 'bad', 'تست گوگل‌بات — تازه‌ترین مقاله', 'خطا: <code>' . esc_html( $bot_post['error'] ) . '</code> · ' . esc_html( $sample[0]->post_title ) );
		} else {
			$meta     = hj_shc_extract_meta_robots( $bot_post['body'] );
			$noidx    = ( '' !== $meta && false !== stripos( $meta, 'noindex' ) ) || ( '' !== $bot_post['x_robots'] && false !== stripos( $bot_post['x_robots'], 'noindex' ) );
			$canon    = (bool) preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $bot_post['body'] );
			$detail   = 'HTTP ' . $bot_post['code'] . ' · meta robots: <code>' . ( $meta ? esc_html( $meta ) : 'ندارد (یعنی index)' ) . '</code> · canonical: ' . ( $canon ? 'دارد' : 'ندارد' );
			$detail  .= ' · <a href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener">' . esc_html( $sample[0]->post_title ) . '</a>';
			$rows[]    = hj_shc_row( $noidx ? 'bad' : 'ok', 'تست گوگل‌بات — تازه‌ترین مقاله', $detail );
		}
	}

	// 8) Explicit _hj_noindex meta counts.
	$noindexed = get_posts(
		array(
			'post_type'      => array( 'post', 'page', 'patient_guide', 'surgery', 'or_environment', 'student_content' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_hj_noindex',
			'meta_value'     => '1',
		)
	);
	$count_no  = is_array( $noindexed ) ? count( $noindexed ) : 0;
	if ( 0 === $count_no ) {
		$rows[] = hj_shc_row( 'ok', 'متای صریح noindex', 'هیچ مقاله/برگه‌ای متای <code>_hj_noindex=1</code> ندارد؛ ایمپورتر چیزی را پنهان نکرده است.' );
	} else {
		$rows[] = hj_shc_row(
			'warn',
			'متای صریح noindex',
			$count_no . ' نوشته متای <code>_hj_noindex=1</code> دارد و از سایت‌مپ حذف می‌شود. شناسه‌ها: <code>' . esc_html( implode( '، ', array_map( 'intval', $noindexed ) ) ) . '</code>'
		);
	}

	// 9) Deployment freshness: 0.13.0+ markers.
	$markers = array(
		'یکدست‌سازی تاریخ شمسی'  => 'hj_dates_normalize_content',
		'سیاست noindex آرشیوها' => 'hj_index_robots',
		'ترمیم شماره‌گذاری واژه‌نامه' => 'hj_glossary_number_repair',
	);
	$missing = array();
	foreach ( $markers as $name => $function ) {
		if ( ! function_exists( $function ) ) {
			$missing[] = $name;
		}
	}
	if ( empty( $missing ) ) {
		$rows[] = hj_shc_row( 'ok', 'نسخه‌ی قالب مستقرشده', 'نسخه‌ی <code>' . esc_html( LAX_THEME_CHILD_VERSION ) . '</code> با همه‌ی اصلاحات 0.13 به بعد (تاریخ، سیاست ایندکس، واژه‌نامه) فعال است.' );
	} else {
		$rows[] = hj_shc_row(
			'warn',
			'نسخه‌ی قالب مستقرشده قدیمی است',
			'نسخه‌ی فعلی <code>' . esc_html( LAX_THEME_CHILD_VERSION ) . '</code> است اما این قابلیت‌ها وجود ندارند: ' . esc_html( implode( '، ', $missing ) ) . '. یعنی بسته‌ی تازه‌ی قالب هنوز از صفحه‌ی «بروزرسانی گیت‌هاب» روی سایت اعمال نشده؛ تاریخ‌های ناهمگون و باگ «1.» واژه‌نامه هم تا آن زمان باقی می‌مانند.'
		);
	}

	// 10) Homepage surgery-term linking (the empty specialty block bug).
	$raw_terms    = get_terms(
		array(
			'taxonomy'   => 'surgery_specialty',
			'parent'     => 0,
			'hide_empty' => true,
			'pad_counts' => false,
		)
	);
	$padded_terms = get_terms(
		array(
			'taxonomy'   => 'surgery_specialty',
			'parent'     => 0,
			'hide_empty' => true,
			'pad_counts' => true,
		)
	);
	$raw_count    = is_array( $raw_terms ) ? count( $raw_terms ) : 0;
	$padded_count = is_array( $padded_terms ) ? count( $padded_terms ) : 0;
	$surgery_live = (int) wp_count_posts( 'surgery' )->publish;
	if ( $surgery_live > 0 && 0 === $raw_count && $padded_count > 0 ) {
		$rows[] = hj_shc_row(
			'warn',
			'لینک‌دهی بلوک جراحی‌های صفحه‌ی اصلی',
			$surgery_live . ' مقاله‌ی جراحی منتشر شده، اما تخصص‌های والد مستقیماً مقاله ندارند (مقاله‌ها روی تخصص‌های فرزند نشسته‌اند). نسخه‌های قدیمی‌تر از 0.14.2 در این حالت بلوک ثابت «به‌تدریج کامل می‌شوند» را نشان می‌دادند و هیچ لینک داخلی به جراحی‌ها نمی‌دادند؛ در 0.14.2 با <code>pad_counts</code> و فهرست جایگزین رفع شده است.'
		);
	} elseif ( $surgery_live > 0 ) {
		$rows[] = hj_shc_row( 'ok', 'لینک‌دهی بلوک جراحی‌های صفحه‌ی اصلی', $padded_count . ' تخصص والد با محتوای منتشرشده قابل لینک‌دهی‌اند.' );
	} else {
		$rows[] = hj_shc_row( 'warn', 'لینک‌دهی بلوک جراحی‌های صفحه‌ی اصلی', 'هنوز مقاله‌ای از نوع surgery منتشر نشده است.' );
	}

	// 11) Published content totals per indexable CPT.
	$types   = array( 'patient_guide', 'surgery', 'or_environment' );
	$counts  = array();
	foreach ( $types as $type ) {
		$object   = get_post_type_object( $type );
		$counts[] = ( $object ? esc_html( $object->labels->name ) : $type ) . ': ' . (int) wp_count_posts( $type )->publish;
	}
	$rows[] = hj_shc_row( 'ok', 'محتوای منتشرشده', implode( ' · ', $counts ) . ' — این مقاله‌ها باید در <code>wp-sitemap-posts-{type}-1.xml</code> دیده شوند.' );

	// 12) Search Console shortcut.
	$gsc_property = function_exists( 'hj_an_settings' ) ? (string) hj_an_settings()['gsc_property'] : '';
	$gsc_links    = ' <a href="' . esc_url( $gsc ) . '" target="_blank" rel="noopener">باز کردن سرچ‌کنسول</a>';
	if ( '' !== $gsc_property ) {
		$gsc_links .= ' · <a href="' . esc_url( 'https://search.google.com/search-console/sitemaps?resource_id=' . rawurlencode( $gsc_property ) ) . '" target="_blank" rel="noopener">گزارش سایت‌مپ‌ها</a>';
	}
	$rows[] = hj_shc_row(
		'warn',
		'اقدام دستی در سرچ‌کنسول',
		'سایت‌مپ <code>' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</code> را ثبت کنید و برای ۱۰ مقاله ابزار URL Inspection ← Request Indexing را بزنید.' . $gsc_links
	);

	return $rows;
}

/**
 * Render the admin screen.
 *
 * @return void
 */
function hj_shc_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$rows       = hj_shc_collect_checks();
	$bad        = 0;
	$warn       = 0;
	foreach ( $rows as $row ) {
		if ( 'bad' === $row['status'] ) {
			++$bad;
		} elseif ( 'warn' === $row['status'] ) {
			++$warn;
		}
	}

	$labels = array(
		'ok'   => array( 'سالم', 'hj-shc-ok' ),
		'warn' => array( 'هشدار', 'hj-shc-warn' ),
		'bad'  => array( 'بحرانی', 'hj-shc-bad' ),
	);

	echo '<div class="wrap">';
	echo '<h1>سلامت ایندکس گوگل</h1>';
	echo '<p class="hj-shc-muted">همه‌ی آزمون‌ها همین لحظه روی همین سایت اجرا می‌شوند و هیچ چیزی نوشته یا تغییر داده نمی‌شود. برای تازه‌سازی کافی است صفحه را دوباره بارگذاری کنید.</p>';

	if ( $bad > 0 ) {
		echo '<div class="hj-shc-banner hj-shc-banner-bad">' . esc_html( $bad . ' مورد بحرانی پیدا شد؛ تا رفع آن‌ها انتظار ایندکس‌شدن واقع‌بینانه نیست. موارد بحرانی را به‌ترتیب جدول رفع کنید.' ) . '</div>';
	} elseif ( $warn > 0 ) {
		echo '<div class="hj-shc-banner hj-shc-banner-warn">' . esc_html( 'مورد بحرانی‌ای که جلوی خزش/ایندکس را بگیرد وجود ندارد، اما ' . $warn . ' هشدار هست. اگر گوگل هنوز چیزی ایندکس نکرده، علت معمولاً تازه‌بودن دامنه و ثبت‌نشدن سایت‌مپ در سرچ‌کنسول است، نه کد قالب.' ) . '</div>';
	} else {
		echo '<div class="hj-shc-banner hj-shc-banner-ok">همه‌ی آزمون‌های فنی سالم‌اند؛ ایندکس‌نشدن باقی‌مانده مربوط به زمان خزش گوگل یا سیگنال‌های بیرونی (لینک ورودی، قدمت دامنه) است.</div>';
	}

	echo '<table class="hj-shc-table"><thead><tr><th style="width:90px">وضعیت</th><th style="width:240px">آزمون</th><th>جزئیات و اقدام</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$badge = $labels[ $row['status'] ];
		echo '<tr>';
		echo '<td><span class="hj-shc-badge ' . esc_attr( $badge[1] ) . '">' . esc_html( $badge[0] ) . '</span></td>';
		echo '<td><strong>' . esc_html( wp_strip_all_tags( $row['label'] ) ) . '</strong></td>';
		echo '<td class="hj-shc-detail">' . wp_kses_post( $row['detail'] ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	echo '<p class="hj-shc-muted">یادآوری: سیاست noindex قالب (<code>inc/seo-index-policy.php</code>) فقط آرشیوها و واژه‌نامه‌های کم‌محتوا را پنهان می‌کند و هرگز به مقاله‌های تکی دست نمی‌زند؛ با رسیدن هر بخش به حد نصاب سه مقاله، خودکار به ایندکس برمی‌گردد.</p>';
	echo '</div>';
}

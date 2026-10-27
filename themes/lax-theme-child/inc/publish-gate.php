<?php
/**
 * Pre-publish gate for medical articles (child 0.16.0).
 *
 * The same rules as `tools/content-lint.py`, but applied inside WordPress so
 * an article cannot go live from the editor while a critical requirement is
 * missing. It replaces the need for a third-party checklist plugin and keeps
 * the rules in one place (no duplicate settings screen).
 *
 * Behaviour:
 *  - New publish of `surgery` / `patient_guide` is demoted to draft when a
 *    critical check fails, with a readable notice for the editor.
 *  - Editing an already published article is never demoted; the box only
 *    shows the checklist so live pages cannot disappear by accident.
 *  - A site administrator can force a publish with the override checkbox.
 *
 * No medical judgement happens here: the gate counts approved sources, links,
 * the warning block, the featured image and forbidden numeric patterns.
 *
 * Since 0.17.0 the source check is tiered: three distinct domains from
 * `data/source-whitelist.json` and at least one from tier 1 (see
 * inc/source-whitelist.php). A single publisher repeating its own pages no
 * longer satisfies the gate.
 *
 * @package Lax_Theme_Child
 * @since   0.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hj_source_summary' ) ) {
	require_once __DIR__ . '/source-whitelist.php';
}

/**
 * Post types covered by the gate.
 *
 * @return array<int,string>
 */
function hj_gate_post_types(): array {
	return apply_filters( 'hj_gate_post_types', array( 'surgery', 'patient_guide' ) );
}

/**
 * Count Persian/Latin words in HTML.
 *
 * @param string $html Content.
 * @return int
 */
function hj_gate_word_count( string $html ): int {
	$text = wp_strip_all_tags( strip_shortcodes( $html ) );
	$n    = preg_match_all( '/[\p{L}\p{N}]+/u', $text, $unused );
	return (int) $n;
}

/**
 * Run every gate check.
 *
 * @param string $content   Post content (raw HTML).
 * @param int    $post_id   Post ID; 0 when the post is not saved yet.
 * @param string $post_type Post type.
 * @return array{items:array<int,array<string,string>>,fails:int,warns:int}
 */
function hj_gate_checks( string $content, int $post_id, string $post_type ): array {
	$items = array();

	$add = static function ( string $key, string $label, string $status, string $hint ) use ( &$items ) {
		$items[] = array(
			'key'    => $key,
			'label'  => $label,
			'status' => $status, // ok | warn | fail.
			'hint'   => $hint,
		);
	};

	// 1. Length floor.
	$min_words = (int) apply_filters( 'hj_gate_min_words', 900 );
	$words     = hj_gate_word_count( $content );
	$add(
		'words',
		'حجم متن',
		$words >= $min_words ? 'ok' : 'fail',
		sprintf( '%d کلمه (حداقل %d)', $words, $min_words )
	);

	// 2. Approved medical sources (tiered whitelist, 0.17.0).
	$sources = hj_source_summary( $content );
	if ( ! $sources['loaded'] ) {
		$add(
			'sources',
			'منابع علمی معتبر',
			'warn',
			'فهرست دامنه‌های معتبر (data/source-whitelist.json) خوانده نشد؛ شمارش منابع انجام نشد.'
		);
	} else {
		$source_hint = sprintf( 'منابع معتبر: %1$d از %2$d دامنه', $sources['approved_count'], $sources['min'] );
		if ( $sources['approved'] ) {
			$source_hint .= ' — ' . implode( '، ', $sources['approved'] );
		}
		if ( $sources['approved_count'] >= $sources['min'] && $sources['tier1_count'] < 1 ) {
			$source_hint .= ' · دست‌کم یک منبع ردهٔ یک (WHO/NIH/CDC/NICE/Cochrane/بیمارستان معتبر) لازم است';
		} elseif ( $sources['approved_count'] < $sources['min'] ) {
			$source_hint .= ' · دامنه‌های دیگری از ردهٔ یک یا انجمن‌های تخصصی افزوده شود';
		}
		$add(
			'sources',
			'منابع علمی معتبر (۳ دامنه)',
			$sources['ok'] ? 'ok' : 'fail',
			$source_hint
		);
	}

	// 3. Internal links (keep the cluster connected).
	$internal = preg_match_all( '/href=["\']\/[^"\']*["\']/i', $content, $m2 );
	$add(
		'internal',
		'لینک داخلی',
		(int) $internal >= 2 ? 'ok' : 'warn',
		sprintf( '%d لینک داخلی (پیشنهاد: حداقل ۲)', (int) $internal )
	);

	// 4. Warning / emergency block.
	$has_warning = (bool) preg_match( '/<h[23]\b[^>]*>[^<]*(?:تماس|اورژانس|هشدار)[^<]*<\/h[23]>/iu', $content )
		&& ( false !== strpos( $content, '۱۱۵' ) || false !== strpos( $content, '115' ) || false !== strpos( $content, 'اورژانس' ) );
	$add(
		'warning',
		'بخش علائم هشدار و تماس با اورژانس',
		$has_warning ? 'ok' : 'fail',
		$has_warning ? 'بخش هشدار با ارجاع به ۱۱۵ یافت شد.' : 'یک H2/H3 با «چه زمانی تماس بگیرید» و اشاره به ۱۱۵ لازم است.'
	);

	// 5. FAQ.
	$faq_meta = $post_id ? get_post_meta( $post_id, '_hj_faq', true ) : array();
	$faq      = is_array( $faq_meta ) ? count( $faq_meta ) : 0;
	$faq_body = (bool) preg_match( '/<h[23]\b[^>]*>[^<]*(?:پرسش|سؤال|سوال)[^<]*<\/h[23]>/iu', $content );
	$add(
		'faq',
		'پرسش‌های متداول',
		( $faq >= 3 || $faq_body ) ? 'ok' : 'warn',
		$faq >= 3 ? sprintf( '%d پرسش در متا', $faq ) : ( $faq_body ? 'بخش پرسش در متن هست (اسکیما ندارد)' : 'حداقل ۳ پرسش متداول لازم است' )
	);

	// 6. Featured image + alt.
	$thumb_id = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
	$alt      = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
	$add(
		'image',
		'تصویر شاخص و متن جانشین',
		( $thumb_id && mb_strlen( trim( $alt ) ) >= 20 ) ? 'ok' : 'fail',
		$thumb_id ? ( '' === trim( $alt ) ? 'تصویر شاخص alt ندارد' : 'تصویر شاخص و alt ثبت شده است' ) : 'تصویر شاخص تنظیم نشده است'
	);

	// 7. Forbidden numeric patterns (dose, volume, success rate).
	$forbidden = array(
		'/[\d۰-۹]+\s*(?:میلی‌?گرم|میلیگرم|mg)\b/iu'          => 'دوز بر حسب میلی‌گرم',
		'/[\d۰-۹]+\s*(?:سی‌سی|سیسی|cc|ml|میلی‌لیتر)\b/iu'      => 'حجم بر حسب میلی‌لیتر',
		'/(?:دوز|مقدار مصرف)\s*[:：]?\s*[\d۰-۹]/iu'           => 'مقدار مصرف عددی',
		'/[\d۰-۹]+\s*درصد\s*(?:موفقیت|عوارض|احتمال|ریسک)/iu'  => 'درصد موفقیت یا عوارض',
	);
	$hits = array();
	foreach ( $forbidden as $pattern => $label ) {
		if ( preg_match( $pattern, $content ) ) {
			$hits[] = $label;
		}
	}
	$add(
		'numbers',
		'عدد ممنوع (دوز/درصد)',
		$hits ? 'fail' : 'ok',
		$hits ? 'حذف شود: ' . implode( '، ', $hits ) : 'عدد دارویی یا آماری یافت نشد'
	);

	// 8. After-surgery articles should carry a timeline table.
	$stage_slugs = array();
	if ( $post_id ) {
		$terms = get_the_terms( $post_id, 'surgery_stage' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$stage_slugs = wp_list_pluck( $terms, 'slug' );
		}
	}
	if ( 'surgery' === $post_type && in_array( 'after-surgery', $stage_slugs, true ) ) {
		$has_table = (bool) preg_match( '/<table\b/i', $content );
		$add(
			'table',
			'جدول دورهٔ بهبود',
			$has_table ? 'ok' : 'warn',
			$has_table ? 'جدول زمانی/مقایسه در متن هست' : 'برای مقالهٔ «بعد از عمل» یک جدول زمانی یا مقایسه پیشنهاد می‌شود'
		);
	}

	// 9. Taxonomy.
	$tax_map = array(
		'surgery'       => 'surgery_specialty',
		'patient_guide' => 'guide_stage',
	);
	$taxonomy = isset( $tax_map[ $post_type ] ) ? $tax_map[ $post_type ] : '';
	$has_term = false;
	if ( $post_id && $taxonomy ) {
		$t = get_the_terms( $post_id, $taxonomy );
		$has_term = ( $t && ! is_wp_error( $t ) );
	}
	$add(
		'taxonomy',
		'دسته‌بندی محتوا',
		$has_term ? 'ok' : 'warn',
		$has_term ? 'دسته ثبت شده است' : 'یک دسته از تاکسونومی مقاله انتخاب شود'
	);

	$fails = 0;
	$warns = 0;
	foreach ( $items as $item ) {
		if ( 'fail' === $item['status'] ) {
			++$fails;
		} elseif ( 'warn' === $item['status'] ) {
			++$warns;
		}
	}

	return array(
		'items' => $items,
		'fails' => $fails,
		'warns' => $warns,
	);
}

/**
 * Enforce the gate on a new publish.
 *
 * @param array $data    Slashed post data.
 * @param array $postarr Original post array.
 * @return array
 */
function hj_gate_enforce_on_publish( $data, $postarr ) {
	if ( empty( $data['post_status'] ) || 'publish' !== $data['post_status'] ) {
		return $data;
	}
	if ( empty( $data['post_type'] ) || ! in_array( $data['post_type'], hj_gate_post_types(), true ) ) {
		return $data;
	}

	$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
		return $data; // Never demote a live article while editing it.
	}

	$override = false;
	if ( ! empty( $_POST['hj_gate_override'] ) && isset( $_POST['hj_gate_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_gate_nonce'] ) ), 'hj_gate_save' )
		&& current_user_can( 'manage_options' ) ) {
		$override = true;
	}

	if ( $override ) {
		return $data;
	}

	$result = hj_gate_checks( (string) $data['post_content'], $post_id, (string) $data['post_type'] );
	if ( $result['fails'] < 1 ) {
		return $data;
	}

	$data['post_status'] = 'draft';

	$labels = array();
	foreach ( $result['items'] as $item ) {
		if ( 'fail' === $item['status'] ) {
			$labels[] = $item['label'];
		}
	}
	set_transient(
		'hj_gate_blocked_' . get_current_user_id(),
		array(
			'title'  => isset( $data['post_title'] ) ? (string) $data['post_title'] : '',
			'labels' => $labels,
		),
		120
	);

	return $data;
}
add_filter( 'wp_insert_post_data', 'hj_gate_enforce_on_publish', 20, 2 );

/**
 * Explain a blocked publish to the editor.
 *
 * @return void
 */
function hj_gate_blocked_notice(): void {
	$key    = 'hj_gate_blocked_' . get_current_user_id();
	$notice = get_transient( $key );
	if ( ! is_array( $notice ) ) {
		return;
	}
	delete_transient( $key );
	?>
	<div class="notice notice-warning is-dismissible">
		<p><strong>انتشار انجام نشد و مقاله پیش‌نویس ماند.</strong>
			<?php echo esc_html( ' «' . $notice['title'] . '» ' ); ?>
			این موارد لازم است: <?php echo esc_html( implode( '، ', (array) $notice['labels'] ) ); ?>.
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'hj_gate_blocked_notice' );

/**
 * Register the checklist box.
 *
 * @return void
 */
function hj_gate_add_meta_box(): void {
	foreach ( hj_gate_post_types() as $type ) {
		add_meta_box(
			'hj-publish-gate',
			'دروازهٔ انتشار (قالب ۰٫۱۷)',
			'hj_gate_render_meta_box',
			$type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'hj_gate_add_meta_box' );

/**
 * Render the checklist.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function hj_gate_render_meta_box( $post ): void {
	$result = hj_gate_checks( (string) $post->post_content, (int) $post->ID, (string) $post->post_type );

	wp_nonce_field( 'hj_gate_save', 'hj_gate_nonce' );

	$icons = array(
		'ok'   => '✔',
		'warn' => '!',
		'fail' => '✖',
	);
	?>
	<ul class="hj-gate-list">
		<?php foreach ( $result['items'] as $item ) : ?>
			<li class="hj-gate-list__item hj-gate-list__item--<?php echo esc_attr( $item['status'] ); ?>">
				<span class="hj-gate-list__mark" aria-hidden="true"><?php echo esc_html( $icons[ $item['status'] ] ); ?></span>
				<span class="hj-gate-list__label"><?php echo esc_html( $item['label'] ); ?></span>
				<span class="hj-gate-list__hint"><?php echo esc_html( $item['hint'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="description">
		<?php
		printf(
			/* translators: 1: failing count, 2: warning count. */
			esc_html__( 'خطای بازدارنده: %1$d · هشدار: %2$d', 'lax-theme-child' ),
			(int) $result['fails'],
			(int) $result['warns']
		);
		?>
	</p>
	<?php if ( current_user_can( 'manage_options' ) && $result['fails'] > 0 ) : ?>
		<p>
			<label>
				<input type="checkbox" name="hj_gate_override" value="1">
				<?php esc_html_e( 'انتشار با وجود خطا (فقط مدیر سایت)', 'lax-theme-child' ); ?>
			</label>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Minimal styles for the checklist box (no extra admin stylesheet).
 *
 * @return void
 */
function hj_gate_admin_styles(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}
	echo '<style>
		.hj-gate-list{margin:0 0 6px;padding:0;list-style:none}
		.hj-gate-list__item{display:grid;grid-template-columns:18px 1fr;gap:2px 6px;padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:12px;line-height:1.6}
		.hj-gate-list__item:last-child{border-bottom:0}
		.hj-gate-list__mark{font-weight:700;text-align:center}
		.hj-gate-list__label{font-weight:600;color:#1d2327}
		.hj-gate-list__hint{grid-column:2;color:#646970}
		.hj-gate-list__item--ok .hj-gate-list__mark{color:#007017}
		.hj-gate-list__item--warn .hj-gate-list__mark{color:#996800}
		.hj-gate-list__item--fail .hj-gate-list__mark{color:#b32d2e}
	</style>';
}
add_action( 'admin_print_styles', 'hj_gate_admin_styles' );

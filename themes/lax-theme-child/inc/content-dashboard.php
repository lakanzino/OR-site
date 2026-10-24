<?php
/**
 * Editorial Roadmap Goals & Publishing Progress Dashboard (Admin Page + WP Dashboard Widget).
 *
 * Displays all 184 base (210 full) target articles across 10 color-coded sections,
 * live published/draft percentages, milestone progress badges, live SEO/thumbnail
 * health indicators on existing articles, instant filtering, and 1-click draft/import actions.
 *
 * @package Lax_Theme_Child
 * @since   0.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register:
 *   1) Top-level admin menu «همیار جراحی» (position 3) with Dashboard + Importer Hub
 *   2) Shortcut under WordPress «پیشخوان» (index.php) -> «داشبورد مقالات هدف»
 *
 * @return void
 */
function hj_dashboard_admin_menu(): void {
	$cap = defined( 'HJ_IMPORTER_CAP' ) ? HJ_IMPORTER_CAP : 'manage_options';

	add_menu_page(
		'داشبورد اهداف و پیشرفت محتوا — همیار جراحی',
		'همیار جراحی',
		$cap,
		'hj-content-dashboard',
		'hj_dashboard_render_page',
		'dashicons-chart-pie',
		3
	);

	add_submenu_page(
		'hj-content-dashboard',
		'داشبورد اهداف و پیشرفت مقالات',
		'📊 اهداف و درصد پیشرفت',
		$cap,
		'hj-content-dashboard',
		'hj_dashboard_render_page'
	);

	if ( function_exists( 'hj_importer_render_page' ) ) {
		add_submenu_page(
			'hj-content-dashboard',
			'مرکز هوشمند ایمپورت مقالات (ZIP)',
			'📦 مرکز ایمپورت (ZIP)',
			$cap,
			'hj-importer-hub',
			'hj_importer_render_page'
		);
	}

	// Also place directly inside WordPress «پیشخوان» (Dashboard -> مقالات هدف و پیشرفت).
	add_dashboard_page(
		'داشبورد مقالات هدف و درصد پیشرفت',
		'🎯 مقالات هدف و پیشرفت',
		$cap,
		'hj-roadmap-overview',
		'hj_dashboard_render_page'
	);
}
add_action( 'admin_menu', 'hj_dashboard_admin_menu', 9 );

/**
 * Register summary progress widget right on the main WordPress Dashboard (/wp-admin/index.php).
 *
 * @return void
 */
function hj_dashboard_register_widget(): void {
	$cap = defined( 'HJ_IMPORTER_CAP' ) ? HJ_IMPORTER_CAP : 'manage_options';
	if ( ! current_user_can( $cap ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'hj_roadmap_progress_widget',
		'🎯 همیار جراحی — درصد پیشرفت انتشار مقالات هدف',
		'hj_dashboard_render_widget'
	);
}
add_action( 'wp_dashboard_setup', 'hj_dashboard_register_widget' );

/**
 * Render the compact color-coded progress widget on /wp-admin/index.php.
 *
 * @return void
 */
function hj_dashboard_render_widget(): void {
	if ( ! function_exists( 'hj_roadmap_compute_progress' ) ) {
		echo '<p>ماژول نقشه راه بارگذاری نشده است.</p>';
		return;
	}
	$report     = hj_roadmap_compute_progress( false );
	$tot        = $report['totals'];
	$badge      = $tot['badge'];
	$dash_url   = add_query_arg( array( 'page' => 'hj-content-dashboard' ), admin_url( 'admin.php' ) );
	$import_url = add_query_arg( array( 'page' => 'hj-importer-hub' ), admin_url( 'admin.php' ) );
	?>
	<div style="direction:rtl;font-family:Tahoma,-apple-system,sans-serif">
		<div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border:1px solid #e2e8f0;border-right:4px solid #06265C;border-radius:8px;padding:10px 12px;margin-bottom:12px">
			<div>
				<div style="font-size:12px;color:#475569">پیشرفت کل نقشه‌ی راه پایه (<?php echo esc_html( number_format_i18n( $tot['total'] ) ); ?> مقاله)</div>
				<div style="font-size:20px;font-weight:800;color:#06265C;margin-top:2px">
					<?php echo esc_html( number_format_i18n( $tot['percent_publish'], 1 ) ); ?>٪
					<span style="font-size:12px;font-weight:600;color:#334155">
						(<?php echo esc_html( number_format_i18n( $tot['published'] ) ); ?> منتشر · <?php echo esc_html( number_format_i18n( $tot['draft'] ) ); ?> پیش‌نویس · <?php echo esc_html( number_format_i18n( $tot['remaining'] ) ); ?> باقی‌مانده)
					</span>
				</div>
			</div>
			<span style="background:<?php echo esc_attr( $badge['bg'] ); ?>;color:<?php echo esc_attr( $badge['color'] ); ?>;padding:4px 9px;border-radius:999px;font-size:11.5px;font-weight:700">
				<?php echo esc_html( $badge['symbol'] . ' ' . $badge['label'] ); ?>
			</span>
		</div>

		<div style="display:grid;gap:8px;margin-bottom:14px">
			<?php foreach ( $report['sections'] as $sec ) : ?>
				<?php $sb = $sec['badge']; ?>
				<div style="border:1px solid #eef2f7;border-right:4px solid <?php echo esc_attr( $sec['color'] ); ?>;border-radius:6px;padding:7px 10px;background:#fff">
					<div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:5px">
						<strong style="color:#0f172a"><?php echo esc_html( $sec['icon'] . ' ' . $sec['short_title'] ); ?></strong>
						<span style="display:inline-flex;align-items:center;gap:6px">
							<span style="font-size:11px;padding:1px 7px;border-radius:999px;background:<?php echo esc_attr( $sb['bg'] ); ?>;color:<?php echo esc_attr( $sb['color'] ); ?>">
								<?php echo esc_html( $sb['symbol'] . ' ' . $sb['label'] ); ?>
							</span>
							<strong style="color:<?php echo esc_attr( $sec['color'] ); ?>">
								<?php echo esc_html( number_format_i18n( $sec['percent'], 1 ) ); ?>٪
							</strong>
							<small style="color:#64748b">(<?php echo esc_html( number_format_i18n( $sec['published'] ) . '/' . number_format_i18n( $sec['total'] ) ); ?>)</small>
						</span>
					</div>
					<div style="height:6px;background:#f1f5f9;border-radius:999px;overflow:hidden;display:flex">
						<span style="width:<?php echo esc_attr( (string) $sec['percent'] ); ?>%;background:<?php echo esc_attr( $sec['color'] ); ?>"></span>
						<?php if ( $sec['draft_percent'] > 0 ) : ?>
							<span style="width:<?php echo esc_attr( (string) $sec['draft_percent'] ); ?>%;background:#f59e0b;opacity:.65"></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div style="display:flex;gap:8px;flex-wrap:wrap">
			<a class="button button-primary" href="<?php echo esc_url( $dash_url ); ?>">📊 مشاهده‌ی لیست کامل مقالات هدف</a>
			<a class="button" href="<?php echo esc_url( $import_url ); ?>">📦 ایمپورت بسته‌ی ZIP</a>
		</div>
	</div>
	<?php
}

/**
 * 1-Click handler to create an initial skeleton Draft post for any target article in the roadmap.
 *
 * @return void
 */
function hj_dashboard_create_target_draft(): void {
	$cap = defined( 'HJ_IMPORTER_CAP' ) ? HJ_IMPORTER_CAP : 'manage_options';
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ) );
	}
	check_admin_referer( 'hj_create_target_draft' );

	$roadmap_id = isset( $_POST['roadmap_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['roadmap_id'] ) ) : '';
	$target     = null;
	foreach ( hj_roadmap_items() as $item ) {
		if ( $item['id'] === $roadmap_id ) {
			$target = $item;
			break;
		}
	}
	if ( ! $target ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'hj-content-dashboard', 'err' => 'not_found' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// Check if already exists.
	$existing = get_posts(
		array(
			'name'           => $target['slug'],
			'post_type'      => $target['post_type'],
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 1,
		)
	);
	if ( ! empty( $existing[0] ) ) {
		wp_safe_redirect( (string) get_edit_post_link( $existing[0]->ID, 'raw' ) );
		exit;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => $target['post_type'],
			'post_name'    => $target['slug'],
			'post_title'   => $target['title'],
			'post_status'  => 'draft',
			'post_content' => '',
		),
		true
	);
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'hj-content-dashboard', 'err' => 'insert_failed' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	update_post_meta( (int) $post_id, '_hj_roadmap_id', $target['id'] );

	// Assign primary taxonomy + optional extra taxonomy (e.g. surgery_specialty + surgery_stage).
	$tax_assignments = array();
	if ( ! empty( $target['taxonomy'] ) && ! empty( $target['term'] ) ) {
		$tax_assignments[] = array(
			'taxonomy' => $target['taxonomy'],
			'term'     => $target['term'],
		);
	}
	if ( ! empty( $target['extra_tax']['taxonomy'] ) && ! empty( $target['extra_tax']['term'] ) ) {
		$tax_assignments[] = array(
			'taxonomy' => $target['extra_tax']['taxonomy'],
			'term'     => $target['extra_tax']['term'],
		);
	}
	foreach ( $tax_assignments as $ta ) {
		if ( taxonomy_exists( $ta['taxonomy'] ) ) {
			$term = term_exists( $ta['term'], $ta['taxonomy'] );
			if ( ! $term ) {
				$term = wp_insert_term( $ta['term'], $ta['taxonomy'], array( 'slug' => $ta['term'] ) );
			}
			if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
				wp_set_object_terms( (int) $post_id, array( (int) $term['term_id'] ), $ta['taxonomy'], false );
			}
		}
	}

	wp_safe_redirect( (string) get_edit_post_link( (int) $post_id, 'raw' ) );
	exit;
}
add_action( 'admin_post_hj_create_target_draft', 'hj_dashboard_create_target_draft' );

/**
 * Render the full Editorial Goals & Publishing Progress Dashboard page.
 *
 * @return void
 */
function hj_dashboard_render_page(): void {
	$cap = defined( 'HJ_IMPORTER_CAP' ) ? HJ_IMPORTER_CAP : 'manage_options';
	if ( ! current_user_can( $cap ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$include_optional = ! empty( $_GET['full'] ) && '1' === (string) $_GET['full'];
	$report           = hj_roadmap_compute_progress( $include_optional );
	$tot              = $report['totals'];
	$overall_badge    = $tot['badge'];
	$gl               = $report['glossary'];
	$gl_badge         = $gl['badge'];

	$base_mode_url = add_query_arg( array( 'page' => 'hj-content-dashboard' ), admin_url( 'admin.php' ) );
	$full_mode_url = add_query_arg( array( 'page' => 'hj-content-dashboard', 'full' => '1' ), admin_url( 'admin.php' ) );
	$hub_imp_url   = add_query_arg( array( 'page' => 'hj-importer-hub' ), admin_url( 'admin.php' ) );
	$gl_imp_url    = function_exists( 'hj_glossary_admin_url' ) ? hj_glossary_admin_url( 'import' ) : ( function_exists( 'hj_importer_admin_url' ) ? hj_importer_admin_url( 'glossary' ) : $hub_imp_url );
	$post_action   = admin_url( 'admin-post.php' );

	// Donut SVG calculations (r = 52 -> circumference = 326.73).
	$circ          = 326.73;
	$draft_pct_tot = $tot['total'] > 0 ? round( ( $tot['draft'] / $tot['total'] ) * 100, 1 ) : 0.0;
	$pub_arc       = round( ( $tot['percent_publish'] / 100 ) * $circ, 2 );
	$drf_arc       = round( ( $draft_pct_tot / 100 ) * $circ, 2 );
	$drf_offset    = -1 * $pub_arc;
	?>
	<style>
		.hj-rd-wrap { max-width: 1280px; direction: rtl; font-family: Tahoma, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #0f172a; }
		.hj-rd-topbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:14px; background:#fff; border:1px solid #dcdcde; border-right:6px solid #06265C; border-radius:12px; padding:18px 22px; margin:16px 0; box-shadow:0 2px 8px rgba(6,38,92,.06); }
		.hj-rd-topbar h1 { margin:0 0 6px; font-size:21px; color:#06265C; padding:0; }
		.hj-rd-topbar p { margin:0; font-size:13px; color:#475569; }
		.hj-rd-mode-switch { display:inline-flex; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:3px; gap:4px; }
		.hj-rd-mode-btn { padding:6px 12px; border-radius:6px; text-decoration:none; font-size:12.5px; font-weight:700; color:#334155; }
		.hj-rd-mode-btn.is-active { background:#06265C; color:#fff; }

		.hj-rd-hero { display:grid; grid-template-columns: 290px 1fr; gap:18px; margin-bottom:20px; }
		@media (max-width: 960px) { .hj-rd-hero { grid-template-columns: 1fr; } }
		.hj-rd-donut-card { background:linear-gradient(145deg, #06265C 0%, #0b3b8c 100%); color:#fff; border-radius:14px; padding:20px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; box-shadow:0 6px 18px rgba(6,38,92,.18); }
		.hj-rd-donut-badge { display:inline-block; margin-top:10px; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(255,255,255,.16); color:#fff; border:1px solid rgba(255,255,255,.28); }
		.hj-rd-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(195px, 1fr)); gap:14px; }
		.hj-rd-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px 18px; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 6px rgba(15,23,42,.04); }
		.hj-rd-kpi__lbl { font-size:12.5px; color:#64748b; font-weight:600; }
		.hj-rd-kpi__val { font-size:26px; font-weight:800; margin:8px 0 4px; color:#0f172a; }
		.hj-rd-kpi__sub { font-size:12px; color:#475569; }

		.hj-rd-sec-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:16px; margin-bottom:24px; }
		.hj-rd-sec-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 8px rgba(15,23,42,.05); transition:transform .15s ease; }
		.hj-rd-sec-card:hover { transform:translateY(-2px); }
		.hj-rd-sec-card__head { padding:14px 16px 10px; display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
		.hj-rd-sec-card__title { margin:0; font-size:14px; font-weight:800; color:#0f172a; line-height:1.45; }
		.hj-rd-sec-card__desc { margin:4px 0 0; font-size:11.5px; color:#64748b; }
		.hj-rd-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
		.hj-rd-sec-card__body { padding:6px 16px 14px; }
		.hj-rd-sec-stats { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:7px; }
		.hj-rd-sec-pct { font-size:22px; font-weight:800; }
		.hj-rd-sec-counts { font-size:12px; color:#475569; }
		.hj-rd-bar { height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; display:flex; }
		.hj-rd-bar__pub { height:100%; transition:width .3s ease; }
		.hj-rd-bar__drf { height:100%; background:repeating-linear-gradient(45deg,#f59e0b,#f59e0b 5px,#fbbf24 5px,#fbbf24 10px); }
		.hj-rd-sec-card__foot { padding:10px 16px; background:#f8fafc; border-top:1px solid #eef2f7; display:flex; justify-content:space-between; align-items:center; font-size:12px; }
		.hj-rd-sec-card__foot a { text-decoration:none; font-weight:600; }

		.hj-rd-legend { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:18px; display:flex; flex-wrap:wrap; align-items:center; gap:12px; font-size:12px; }
		.hj-rd-toolbar { background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:14px 16px; margin-bottom:18px; display:flex; flex-wrap:wrap; align-items:center; gap:10px; position:sticky; top:36px; z-index:20; box-shadow:0 3px 10px rgba(15,23,42,.06); }
		.hj-rd-search { min-width:250px; flex:1; padding:6px 12px; border:1px solid #94a3b8; border-radius:7px; font-size:13px; }
		.hj-rd-filter-btn { padding:6px 12px; border-radius:7px; border:1px solid #cbd5e1; background:#f8fafc; cursor:pointer; font-size:12px; font-weight:600; color:#334155; }
		.hj-rd-filter-btn.is-active { background:#06265C; border-color:#06265C; color:#fff; }

		.hj-rd-acc { background:#fff; border:1px solid #cbd5e1; border-radius:12px; margin-bottom:16px; overflow:hidden; }
		.hj-rd-acc summary { list-style:none; cursor:pointer; padding:14px 18px; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; font-weight:700; }
		.hj-rd-acc summary::-webkit-details-marker { display:none; }
		.hj-rd-table { width:100%; border-collapse:collapse; font-size:12.5px; }
		.hj-rd-table th { background:#f8fafc; color:#334155; text-align:right; padding:10px 12px; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; }
		.hj-rd-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
		.hj-rd-table tr.is-publish { background:#f0fdf438; }
		.hj-rd-table tr.is-draft { background:#fffbeb55; }
		.hj-rd-code { display:inline-block; padding:2px 7px; border-radius:5px; font-family:monospace; font-size:11.5px; font-weight:700; background:#eef2f7; color:#1e293b; }
		.hj-rd-p1 { display:inline-block; background:#dbeafe; color:#1e40af; padding:1px 6px; border-radius:4px; font-size:10.5px; font-weight:700; margin-right:4px; }
		.hj-rd-opt { display:inline-block; background:#f3e8ff; color:#6b21a8; padding:1px 6px; border-radius:4px; font-size:10.5px; font-weight:700; margin-right:4px; }
		.hj-rd-st-pub { display:inline-flex; align-items:center; gap:4px; background:#dcfce7; color:#166534; padding:3px 9px; border-radius:999px; font-weight:700; font-size:11.5px; }
		.hj-rd-st-drf { display:inline-flex; align-items:center; gap:4px; background:#fef3c7; color:#92400e; padding:3px 9px; border-radius:999px; font-weight:700; font-size:11.5px; }
		.hj-rd-st-mis { display:inline-flex; align-items:center; gap:4px; background:#f1f5f9; color:#64748b; padding:3px 9px; border-radius:999px; font-weight:600; font-size:11.5px; }
		.hj-rd-health-warn { display:inline-block; font-size:11px; color:#b45309; background:#fef3c7; padding:1px 6px; border-radius:4px; margin-top:3px; }
		.hj-rd-health-ok { display:inline-block; font-size:11px; color:#15803d; margin-top:3px; }
	</style>

	<div class="wrap hj-rd-wrap">
		<div class="hj-rd-topbar">
			<div>
				<h1>🎯 داشبورد جامع مقالات هدف و درصد پیشرفت انتشار — همیار جراحی</h1>
				<p>پایش زنده‌ی همه‌ی مقالات هدف در ۱۰ بخش رنگی سایت، درصد پیشرفت واقعی، نشان‌های مرحله‌ای و دسترسی سریع به ساخت پیش‌نویس و ایمپورت ZIP.</p>
			</div>
			<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
				<div class="hj-rd-mode-switch">
					<a class="hj-rd-mode-btn <?php echo ! $include_optional ? 'is-active' : ''; ?>" href="<?php echo esc_url( $base_mode_url ); ?>">
						نقشه راه پایه (۱۸۴ مقاله)
					</a>
					<a class="hj-rd-mode-btn <?php echo $include_optional ? 'is-active' : ''; ?>" href="<?php echo esc_url( $full_mode_url ); ?>">
						نقشه راه کامل با اختیاری‌ها (۲۱۰ مقاله)
					</a>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( $hub_imp_url ); ?>">📦 مرکز ایمپورت مقالات (ZIP)</a>
			</div>
		</div>

		<?php // Hero Overall Progress Ring + KPI Cards. ?>
		<div class="hj-rd-hero">
			<div class="hj-rd-donut-card">
				<svg width="136" height="136" viewBox="0 0 136 136" aria-label="درصد کل پیشرفت">
					<circle cx="68" cy="68" r="52" fill="none" stroke="rgba(255,255,255,0.16)" stroke-width="14"></circle>
					<?php if ( $drf_arc > 0 ) : ?>
						<circle cx="68" cy="68" r="52" fill="none" stroke="#fbbf24" stroke-width="14"
							stroke-dasharray="<?php echo esc_attr( $drf_arc . ' ' . $circ ); ?>"
							stroke-dashoffset="<?php echo esc_attr( (string) $drf_offset ); ?>"
							transform="rotate(-90 68 68)"></circle>
					<?php endif; ?>
					<circle cx="68" cy="68" r="52" fill="none" stroke="#4ade80" stroke-width="14" stroke-linecap="round"
						stroke-dasharray="<?php echo esc_attr( $pub_arc . ' ' . $circ ); ?>"
						transform="rotate(-90 68 68)"></circle>
					<text x="68" y="64" text-anchor="middle" fill="#ffffff" font-size="22" font-weight="800">
						<?php echo esc_html( number_format_i18n( $tot['percent_publish'], 1 ) ); ?>٪
					</text>
					<text x="68" y="84" text-anchor="middle" fill="#cbd5e1" font-size="11">منتشرشده کل</text>
				</svg>
				<div style="font-size:13.5px;font-weight:700;margin-top:6px">
					<?php echo esc_html( number_format_i18n( $tot['published'] ) ); ?> از <?php echo esc_html( number_format_i18n( $tot['total'] ) ); ?> مقاله‌ی هدف
				</div>
				<span class="hj-rd-donut-badge">
					<?php echo esc_html( $overall_badge['symbol'] . ' نشان کل پروژه: ' . $overall_badge['label'] ); ?>
				</span>
			</div>

			<div class="hj-rd-kpi-grid">
				<div class="hj-rd-kpi" style="border-top:4px solid #16a34a">
					<span class="hj-rd-kpi__lbl">🟢 مقالات منتشرشده در سایت</span>
					<div class="hj-rd-kpi__val" style="color:#15803d"><?php echo esc_html( number_format_i18n( $tot['published'] ) ); ?></div>
					<span class="hj-rd-kpi__sub"><?php echo esc_html( number_format_i18n( $tot['percent_publish'], 1 ) ); ?>٪ از کل فهرست هدف</span>
				</div>

				<div class="hj-rd-kpi" style="border-top:4px solid #d97706">
					<span class="hj-rd-kpi__lbl">📝 آماده در پیش‌نویس (Draft)</span>
					<div class="hj-rd-kpi__val" style="color:#b45309"><?php echo esc_html( number_format_i18n( $tot['draft'] ) ); ?></div>
					<span class="hj-rd-kpi__sub">
						مجموع پوشش (منتشر + پیش‌نویس): <strong><?php echo esc_html( number_format_i18n( $tot['percent_written'], 1 ) ); ?>٪</strong>
					</span>
				</div>

				<div class="hj-rd-kpi" style="border-top:4px solid #64748b">
					<span class="hj-rd-kpi__lbl">⚪ مقالات باقی‌مانده</span>
					<div class="hj-rd-kpi__val"><?php echo esc_html( number_format_i18n( $tot['remaining'] ) ); ?></div>
					<span class="hj-rd-kpi__sub">در انتظار نگارش / ایمپورت ZIP</span>
				</div>

				<div class="hj-rd-kpi" style="border-top:4px solid #0284c7">
					<span class="hj-rd-kpi__lbl">⭐ پیشرفت ستون‌ها و اولویت فاز ۱</span>
					<div class="hj-rd-kpi__val" style="color:#0369a1"><?php echo esc_html( number_format_i18n( $tot['phase1_percent'], 1 ) ); ?>٪</div>
					<span class="hj-rd-kpi__sub">
						<?php echo esc_html( number_format_i18n( $tot['phase1_publish'] ) ); ?> از <?php echo esc_html( number_format_i18n( $tot['phase1_total'] ) ); ?> مقاله‌ی هسته اولیه
					</span>
				</div>

				<div class="hj-rd-kpi" style="border-top:4px solid #059669">
					<span class="hj-rd-kpi__lbl">📖 بانک واژه‌نامه و هایلایت سبز</span>
					<div class="hj-rd-kpi__val" style="color:#047857"><?php echo esc_html( number_format_i18n( $gl['published'] ) ); ?></div>
					<span class="hj-rd-kpi__sub">
						واژه‌ی فعال (هدف: +<?php echo esc_html( number_format_i18n( $gl['target'] ) ); ?> واژه) · <a href="<?php echo esc_url( $gl_imp_url ); ?>" style="font-weight:700;color:#059669;">ورود انبوه اکسل/CSV</a>
					</span>
				</div>
			</div>
		</div>

		<?php // Milestone Badges Legend. ?>
		<div class="hj-rd-legend">
			<strong>راهنمای نشان‌های پیشرفت هر بخش:</strong>
			<span>🏆 <strong>تکمیل ۱۰۰٪</strong> (انتشار کامل تمام مقالات بخش)</span>
			<span>🚀 <strong>پیشتاز</strong> (۷۵٪ تا ۹۹٪)</span>
			<span>⚡ <strong>نیمه‌راه</strong> (۴۰٪ تا ۷۴٪)</span>
			<span>🌱 <strong>در جریان</strong> (۱٪ تا ۳۹٪)</span>
			<span>📝 <strong>در پیش‌نویس</strong> (۰٪ منتشر ولی دارای پیش‌نویس)</span>
			<span>⏳ <strong>در انتظار شروع</strong> (۰٪)</span>
		</div>

		<?php // 10 Distinct Color-Coded Section Cards. ?>
		<div class="hj-rd-sec-grid">
			<?php foreach ( $report['sections'] as $sec_key => $sec ) : ?>
				<?php
				$sb          = $sec['badge'];
				$sec_imp_url = function_exists( 'hj_importer_admin_url' ) ? hj_importer_admin_url( $sec['post_type'] ) : $hub_imp_url;
				?>
				<div class="hj-rd-sec-card" style="border-top:5px solid <?php echo esc_attr( $sec['color'] ); ?>">
					<div>
						<div class="hj-rd-sec-card__head" style="background:<?php echo esc_attr( $sec['bg'] ); ?>">
							<div>
								<h3 class="hj-rd-sec-card__title" style="color:<?php echo esc_attr( $sec['color'] ); ?>">
									<?php echo esc_html( $sec['icon'] . ' ' . $sec['short_title'] ); ?>
								</h3>
								<p class="hj-rd-sec-card__desc"><?php echo esc_html( $sec['description'] ); ?></p>
							</div>
							<span class="hj-rd-badge" style="background:<?php echo esc_attr( $sb['bg'] ); ?>;color:<?php echo esc_attr( $sb['color'] ); ?>">
								<?php echo esc_html( $sb['symbol'] . ' ' . $sb['label'] ); ?>
							</span>
						</div>

						<div class="hj-rd-sec-card__body">
							<div class="hj-rd-sec-stats">
								<span class="hj-rd-sec-pct" style="color:<?php echo esc_attr( $sec['color'] ); ?>">
									<?php echo esc_html( number_format_i18n( $sec['percent'], 1 ) ); ?>٪
								</span>
								<span class="hj-rd-sec-counts">
									<strong><?php echo esc_html( number_format_i18n( $sec['published'] ) ); ?></strong> از <strong><?php echo esc_html( number_format_i18n( $sec['total'] ) ); ?></strong> منتشر
									<?php if ( $sec['draft'] > 0 ) : ?>
										· <span style="color:#b45309"><?php echo esc_html( number_format_i18n( $sec['draft'] ) ); ?> پیش‌نویس</span>
									<?php endif; ?>
									· <?php echo esc_html( number_format_i18n( $sec['remaining'] ) ); ?> مانده
								</span>
							</div>
							<div class="hj-rd-bar" title="منتشرشده: <?php echo esc_attr( (string) $sec['percent'] ); ?>% | پیش‌نویس: <?php echo esc_attr( (string) $sec['draft_percent'] ); ?>%">
								<span class="hj-rd-bar__pub" style="width:<?php echo esc_attr( (string) $sec['percent'] ); ?>%;background:<?php echo esc_attr( $sec['color'] ); ?>"></span>
								<?php if ( $sec['draft_percent'] > 0 ) : ?>
									<span class="hj-rd-bar__drf" style="width:<?php echo esc_attr( (string) $sec['draft_percent'] ); ?>%"></span>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<div class="hj-rd-sec-card__foot">
						<a href="#hj-sec-<?php echo esc_attr( $sec_key ); ?>" style="color:<?php echo esc_attr( $sec['color'] ); ?>" onclick="var d=document.getElementById('hj-sec-<?php echo esc_attr( $sec_key ); ?>');if(d){d.open=true;}">
							📋 مشاهده لیست (<?php echo esc_html( number_format_i18n( $sec['total'] ) ); ?> مقاله) ↓
						</a>
						<a href="<?php echo esc_url( $sec_imp_url ); ?>" style="color:#334155">📤 ایمپورت ZIP</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php // Sticky Filter & Instant Search Bar. ?>
		<div class="hj-rd-toolbar">
			<input type="search" id="hj-rd-search-input" class="hj-rd-search" placeholder="🔎 جستجوی سریع در عنوان فارسی، نامک انگلیسی یا شماره ردیف (مثلاً آپاندکتومی، medications، 23)...">
			<button type="button" class="hj-rd-filter-btn is-active" data-filter="all">همه (<?php echo esc_html( number_format_i18n( $tot['total'] ) ); ?>)</button>
			<button type="button" class="hj-rd-filter-btn" data-filter="publish">🟢 منتشرشده (<?php echo esc_html( number_format_i18n( $tot['published'] ) ); ?>)</button>
			<button type="button" class="hj-rd-filter-btn" data-filter="draft">📝 پیش‌نویس (<?php echo esc_html( number_format_i18n( $tot['draft'] ) ); ?>)</button>
			<button type="button" class="hj-rd-filter-btn" data-filter="todo">⚪ باقی‌مانده (<?php echo esc_html( number_format_i18n( $tot['remaining'] ) ); ?>)</button>
			<button type="button" class="hj-rd-filter-btn" data-filter="phase1">⭐ فقط ستون‌ها و فاز ۱ (<?php echo esc_html( number_format_i18n( $tot['phase1_total'] ) ); ?>)</button>
			<button type="button" class="button button-small" id="hj-rd-expand-all">باز کردن همه بخش‌ها</button>
			<button type="button" class="button button-small" id="hj-rd-collapse-all">بستن همه بخش‌ها</button>
		</div>

		<?php // Complete Target Article Lists per Color-Coded Section. ?>
		<?php foreach ( $report['sections'] as $sec_key => $sec ) : ?>
			<?php
			$sb          = $sec['badge'];
			$sec_imp_url = function_exists( 'hj_importer_admin_url' ) ? hj_importer_admin_url( $sec['post_type'] ) : $hub_imp_url;
			$open_by_def = ( $sec['published'] > 0 || $sec['draft'] > 0 || 'pillars' === $sec_key || 'guide_before' === $sec_key );
			?>
			<details id="hj-sec-<?php echo esc_attr( $sec_key ); ?>" class="hj-rd-acc" style="border-right:6px solid <?php echo esc_attr( $sec['color'] ); ?>" <?php echo $open_by_def ? 'open' : ''; ?>>
				<summary style="background:<?php echo esc_attr( $sec['bg'] ); ?>">
					<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
						<span style="font-size:16px;color:<?php echo esc_attr( $sec['color'] ); ?>">
							<?php echo esc_html( $sec['icon'] . ' ' . $sec['title'] ); ?>
						</span>
						<span class="hj-rd-badge" style="background:<?php echo esc_attr( $sb['bg'] ); ?>;color:<?php echo esc_attr( $sb['color'] ); ?>">
							<?php echo esc_html( $sb['symbol'] . ' ' . $sb['label'] ); ?>
						</span>
					</div>
					<div style="display:flex;align-items:center;gap:12px;font-size:12.5px">
						<span style="color:<?php echo esc_attr( $sec['color'] ); ?>;font-size:15px;font-weight:800">
							<?php echo esc_html( number_format_i18n( $sec['percent'], 1 ) ); ?>٪
						</span>
						<span>
							🟢 <?php echo esc_html( number_format_i18n( $sec['published'] ) ); ?> منتشر ·
							📝 <?php echo esc_html( number_format_i18n( $sec['draft'] ) ); ?> پیش‌نویس ·
							⚪ <?php echo esc_html( number_format_i18n( $sec['remaining'] ) ); ?> مانده
							(از <?php echo esc_html( number_format_i18n( $sec['total'] ) ); ?>)
						</span>
					</div>
				</summary>

				<table class="hj-rd-table">
					<thead>
						<tr>
							<th style="width:92px">ردیف</th>
							<th>عنوان مقاله‌ی هدف</th>
							<th style="width:235px">نامک (Slug) و دسته</th>
							<th style="width:205px">وضعیت و سلامت سئو/تصویر</th>
							<th style="width:230px">عملیات سریع</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sec['items'] as $row ) : ?>
							<?php
							$st_code      = $row['status']; // publish | draft | todo.
							$p_info       = $row['post'];
							$row_class    = 'is-' . $st_code;
							$is_p1        = ( (int) $row['phase'] <= 1 );
							$search_blob  = strtolower( $row['num'] . ' ' . $row['slug'] . ' ' . $row['title'] . ( $p_info ? ' ' . $p_info['slug'] . ' ' . $p_info['title'] : '' ) );
							$health_warns = array();
							if ( is_array( $p_info ) ) {
								if ( empty( $p_info['has_image'] ) ) {
									$health_warns[] = 'بدون تصویر شاخص';
								} elseif ( empty( $p_info['has_alt'] ) ) {
									$health_warns[] = 'بدون alt تصویر';
								}
								if ( empty( $p_info['has_meta'] ) ) {
									$health_warns[] = 'بدون متای سئو';
								}
								if ( empty( $p_info['has_faq'] ) ) {
									$health_warns[] = 'بدون FAQ';
								}
							}
							?>
							<tr class="hj-rd-row <?php echo esc_attr( $row_class ); ?>"
								data-state="<?php echo esc_attr( $st_code ); ?>"
								data-phase1="<?php echo $is_p1 ? '1' : '0'; ?>"
								data-search="<?php echo esc_attr( $search_blob ); ?>">
								<td>
									<span class="hj-rd-code">#<?php echo esc_html( $row['num'] ); ?></span>
									<?php if ( 0 === (int) $row['phase'] ) : ?>
										<span class="hj-rd-p1">ستون مادر</span>
									<?php elseif ( 1 === (int) $row['phase'] ) : ?>
										<span class="hj-rd-p1">فاز ۱</span>
									<?php endif; ?>
									<?php if ( ! empty( $row['optional'] ) ) : ?>
										<span class="hj-rd-opt">اختیاری</span>
									<?php endif; ?>
								</td>
								<td>
									<strong style="font-size:13px;color:#0f172a"><?php echo esc_html( $row['title'] ); ?></strong>
									<?php if ( is_array( $p_info ) && $p_info['title'] !== $row['title'] ) : ?>
										<div style="font-size:11.5px;color:#64748b;margin-top:2px">عنوان فعلی در سایت: «<?php echo esc_html( $p_info['title'] ); ?>»</div>
									<?php endif; ?>
								</td>
								<td>
									<code style="font-size:11px;display:inline-block;direction:ltr"><?php echo esc_html( is_array( $p_info ) ? $p_info['slug'] : $row['slug'] ); ?></code>
									<div style="font-size:11px;color:#64748b;margin-top:3px">
										<?php
										$tx_parts = array( $row['taxonomy'] . ':' . $row['term'] );
										if ( ! empty( $row['extra_tax']['taxonomy'] ) && ! empty( $row['extra_tax']['term'] ) ) {
											$tx_parts[] = $row['extra_tax']['taxonomy'] . ':' . $row['extra_tax']['term'];
										}
										echo esc_html( implode( ' | ', $tx_parts ) );
										?>
									</div>
								</td>
								<td>
									<?php if ( 'publish' === $st_code ) : ?>
										<span class="hj-rd-st-pub">🟢 منتشرشده</span>
										<?php if ( ! empty( $p_info['updated_at'] ) ) : ?>
											<div style="font-size:11px;color:#64748b;margin-top:2px">به‌روزرسانی: <?php echo esc_html( $p_info['updated_at'] ); ?></div>
										<?php endif; ?>
									<?php elseif ( 'draft' === $st_code ) : ?>
										<span class="hj-rd-st-drf">📝 پیش‌نویس آماده</span>
									<?php else : ?>
										<span class="hj-rd-st-mis">⚪ منتشرنشده</span>
									<?php endif; ?>

									<?php if ( is_array( $p_info ) ) : ?>
										<?php if ( ! empty( $health_warns ) ) : ?>
											<div><span class="hj-rd-health-warn">⚠️ <?php echo esc_html( implode( ' · ', $health_warns ) ); ?></span></div>
										<?php else : ?>
											<div><span class="hj-rd-health-ok">✓ تصویر، alt، متا و FAQ کامل</span></div>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( is_array( $p_info ) ) : ?>
										<div style="display:flex;gap:6px;flex-wrap:wrap">
											<?php if ( ! empty( $p_info['edit_link'] ) ) : ?>
												<a class="button button-small button-primary" href="<?php echo esc_url( $p_info['edit_link'] ); ?>">✏️ ویرایش</a>
											<?php endif; ?>
											<?php if ( ! empty( $p_info['permalink'] ) ) : ?>
												<a class="button button-small" href="<?php echo esc_url( $p_info['permalink'] ); ?>" target="_blank" rel="noopener">👁️ مشاهده</a>
											<?php endif; ?>
											<a class="button button-small" href="<?php echo esc_url( $sec_imp_url ); ?>" title="آپدیت از طریق بسته‌ی ZIP">🔄 ZIP</a>
										</div>
									<?php else : ?>
										<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
											<form method="post" action="<?php echo esc_url( $post_action ); ?>" style="margin:0">
												<input type="hidden" name="action" value="hj_create_target_draft">
												<input type="hidden" name="roadmap_id" value="<?php echo esc_attr( $row['id'] ); ?>">
												<?php wp_nonce_field( 'hj_create_target_draft' ); ?>
												<button type="submit" class="button button-small" title="ساخت خودکار پیش‌نویس با نامک و دسته‌بندی استاندارد">➕ ساخت پیش‌نویس</button>
											</form>
											<a class="button button-small button-primary" href="<?php echo esc_url( $sec_imp_url ); ?>">📤 ایمپورت ZIP</a>
										</div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endforeach; ?>

		<?php if ( ! empty( $report['unmapped_posts'] ) ) : ?>
			<div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:16px 20px;margin-top:20px">
				<h3 style="margin-top:0;color:#06265C">📌 نوشته‌های اضافی در سایت (خارج از نامک‌های پیش‌فرض فهرست بالا: <?php echo esc_html( number_format_i18n( count( $report['unmapped_posts'] ) ) ); ?> مورد)</h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>عنوان نوشته</th>
							<th>نامک</th>
							<th>بخش</th>
							<th>وضعیت</th>
							<th>لینک‌ها</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $report['unmapped_posts'] as $up ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $up['title'] ); ?></strong></td>
								<td><code><?php echo esc_html( $up['slug'] ); ?></code></td>
								<td><?php echo esc_html( $up['post_type'] ); ?></td>
								<td><?php echo 'publish' === $up['status'] ? '🟢 منتشرشده' : '📝 پیش‌نویس'; ?></td>
								<td>
									<a href="<?php echo esc_url( $up['edit_link'] ); ?>">ویرایش</a> |
									<a href="<?php echo esc_url( $up['permalink'] ); ?>" target="_blank" rel="noopener">مشاهده</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<script>
	(function() {
		var searchInput = document.getElementById('hj-rd-search-input');
		var filterBtns  = document.querySelectorAll('.hj-rd-filter-btn');
		var rows        = document.querySelectorAll('.hj-rd-row');
		var accordions  = document.querySelectorAll('.hj-rd-acc');
		var activeState = 'all';

		function applyFilter() {
			var q = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
			rows.forEach(function(row) {
				var st     = row.getAttribute('data-state');
				var p1     = row.getAttribute('data-phase1');
				var blob   = row.getAttribute('data-search') || '';
				var matchState = true;
				if (activeState === 'publish') matchState = (st === 'publish');
				else if (activeState === 'draft') matchState = (st === 'draft');
				else if (activeState === 'todo') matchState = (st === 'todo');
				else if (activeState === 'phase1') matchState = (p1 === '1');
				var matchQuery = (!q || blob.indexOf(q) !== -1);
				row.style.display = (matchState && matchQuery) ? '' : 'none';
			});
			if (q || activeState !== 'all') {
				accordions.forEach(function(acc) {
					var visible = acc.querySelectorAll('.hj-rd-row:not([style*="display: none"])').length;
					acc.style.display = visible > 0 ? '' : 'none';
					if (visible > 0) acc.open = true;
				});
			} else {
				accordions.forEach(function(acc) { acc.style.display = ''; });
			}
		}

		if (searchInput) {
			searchInput.addEventListener('input', applyFilter);
		}
		filterBtns.forEach(function(btn) {
			btn.addEventListener('click', function() {
				filterBtns.forEach(function(b) { b.classList.remove('is-active'); });
				btn.classList.add('is-active');
				activeState = btn.getAttribute('data-filter') || 'all';
				applyFilter();
			});
		});

		var expBtn = document.getElementById('hj-rd-expand-all');
		var colBtn = document.getElementById('hj-rd-collapse-all');
		if (expBtn) expBtn.addEventListener('click', function() { accordions.forEach(function(a) { a.open = true; }); });
		if (colBtn) colBtn.addEventListener('click', function() { accordions.forEach(function(a) { a.open = false; }); });
	})();
	</script>
	<?php
}

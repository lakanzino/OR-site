<?php
/**
 * Admin surface: dashboard, per-post panel, draft-fix and JSON export.
 *
 * Every write path here is deliberate and narrow:
 *  - the draft-fix creates a *new draft* and never touches the published post,
 *  - the review flag stores who looked and when,
 *  - the redirect map is an explicit, validated list of paths.
 *
 * @package Hamyar_Sentinel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI + actions.
 */
final class HJS_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var HJS_Admin|null
	 */
	private static $instance = null;

	/**
	 * Instance accessor.
	 *
	 * @return HJS_Admin
	 */
	public static function instance(): HJS_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'admin_post_hjs_draft_fix', array( $this, 'handle_draft_fix' ) );
		add_action( 'admin_post_hjs_mark_reviewed', array( $this, 'handle_mark_reviewed' ) );
		add_action( 'admin_post_hjs_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_hjs_save_redirects', array( $this, 'handle_save_redirects' ) );
	}

	/**
	 * Tools → «سنتینل».
	 *
	 * @return void
	 */
	public function menu(): void {
		add_management_page(
			'سنتینل — سلامت محتوا',
			'سنتینل (سلامت محتوا)',
			HJS_CAP,
			'hjs-dashboard',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Side panel inside the article editor.
	 *
	 * @return void
	 */
	public function metabox(): void {
		foreach ( hjs_post_types() as $type ) {
			add_meta_box(
				'hjs_panel',
				'🩺 سنتینل — وضعیت سلامت این مقاله',
				array( $this, 'render_panel' ),
				$type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Shared action link with nonce.
	 *
	 * @param string $action  admin-post action.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private function action_url( string $action, int $post_id ): string {
		$url = add_query_arg(
			array(
				'action'  => $action,
				'post_id' => $post_id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, $action . '_' . $post_id );
	}

	/**
	 * Render the editor panel.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_panel( $post ): void {
		$audit = HJS_Audit::instance()->audit( (int) $post->ID );
		$score = (int) $audit['score'];
		$tone  = $score >= 85 ? '#16a34a' : ( $score >= 60 ? '#d97706' : '#dc2626' );

		echo '<div style="direction:rtl;text-align:right;font-size:12px">';
		printf(
			'<p style="margin:0 0 8px"><strong style="color:%1$s;font-size:15px">%2$s٪</strong> <span style="color:#475569">انطباق با چک‌لیست</span></p>',
			esc_attr( $tone ),
			esc_html( number_format_i18n( $score ) )
		);

		echo '<ul style="margin:0 0 10px;padding-inline-start:16px">';
		foreach ( $audit['issues'] as $issue ) {
			$icon = 'ok' === $issue['status'] ? '✅' : ( 'warn' === $issue['status'] ? '🟡' : '🔴' );
			printf(
				'<li style="margin-bottom:5px">%1$s %2$s</li>',
				esc_html( $icon ),
				esc_html( $issue['label'] )
			);
			if ( ! empty( $issue['hint'] ) && 'ok' !== $issue['status'] ) {
				printf( '<div style="color:#475569;font-size:11px">%s</div>', esc_html( $issue['hint'] ) );
			}
		}
		echo '</ul>';

		$fixable = false;
		foreach ( $audit['issues'] as $issue ) {
			if ( ! empty( $issue['fixable'] ) && 'ok' !== $issue['status'] ) {
				$fixable = true;
				break;
			}
		}

		if ( $fixable ) {
			printf(
				'<a class="button button-primary" style="width:100%%;text-align:center" href="%s">ساخت پیش‌نویس اصلاح (بازبینی انسانی)</a>',
				esc_url( $this->action_url( 'hjs_draft_fix', (int) $post->ID ) )
			);
			echo '<p style="color:#475569;font-size:11px;margin:6px 0 0">این دکمه نوشتهٔ منتشرشده را تغییر نمی‌دهد؛ یک پیش‌نویس جدا می‌سازد تا خودتان تأیید کنید.</p>';
		}

		$reviewed = (int) get_post_meta( (int) $post->ID, '_hjs_reviewed', true );
		echo '<hr style="margin:10px 0">';
		if ( $reviewed ) {
			$user = (int) get_post_meta( (int) $post->ID, '_hjs_reviewed_by', true );
			printf(
				'<p style="margin:0 0 8px;color:#16a34a">آخرین بازبینی انسانی: %1$s (%2$s)</p>',
				esc_html( wp_date( 'Y/m/d', $reviewed ) ),
				esc_html( $user ? get_the_author_meta( 'display_name', $user ) : '—' )
			);
		} else {
			echo '<p style="margin:0 0 8px;color:#475569">هنوز بازبینی انسانی ثبت نشده است.</p>';
		}
		printf(
			'<a class="button" style="width:100%%;text-align:center" href="%s">ثبت بازبینی انسانی</a>',
			esc_url( $this->action_url( 'hjs_mark_reviewed', (int) $post->ID ) )
		);
		echo '</div>';
	}

	/**
	 * Handle: create a draft copy with the proposed block appended.
	 *
	 * @return void
	 */
	public function handle_draft_fix(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		check_admin_referer( 'hjs_draft_fix_' . $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}

		$source = get_post( $post_id );
		if ( ! $source ) {
			wp_die( 'نوشته پیدا نشد.' );
		}

		$boilerplate = "\n\n<!-- hjs:proposed-danger-box | پیش‌نویس خودکار سنتینل — پیش از انتشار بازبینی و ویرایش شود -->\n"
			. "<h2>علائم خطر فوری</h2>\n"
			. "<p>⚠️ اگر بعد از عمل تب بالا، تنگی نفس، درد شدید و فزاینده، خون‌ریزی غیرعادی، ترشح چرکی از محل برش یا افت هوشیاری دارید، معطل نکنید: با مرکز جراحی یا تیم درمان خود تماس بگیرید و در وضعیت اورژانسی با <strong>۱۱۵</strong> تماس بگیرید.</p>";

		$draft_id = wp_insert_post(
			array(
				'post_type'    => $source->post_type,
				'post_status'  => 'draft',
				'post_title'   => 'پیش‌نویس اصلاح — ' . $source->post_title,
				'post_content' => $source->post_content . $boilerplate,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $draft_id ) ) {
			wp_die( esc_html( $draft_id->get_error_message() ) );
		}

		update_post_meta( $draft_id, '_hjs_fix_for', $post_id );
		wp_safe_redirect( get_edit_post_link( $draft_id, 'url' ) );
		exit;
	}

	/**
	 * Handle: mark a human review.
	 *
	 * @return void
	 */
	public function handle_mark_reviewed(): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		check_admin_referer( 'hjs_mark_reviewed_' . $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}

		update_post_meta( $post_id, '_hjs_reviewed', time() );
		update_post_meta( $post_id, '_hjs_reviewed_by', get_current_user_id() );

		wp_safe_redirect( get_edit_post_link( $post_id, 'url' ) );
		exit;
	}

	/**
	 * Handle: download the audit as JSON for the webmaster agent.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( HJS_CAP ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}
		check_admin_referer( 'hjs_export' );

		$rows = $this->collect();
		$data = array(
			'generated_at' => gmdate( 'c' ),
			'site'         => home_url( '/' ),
			'plugin'       => HJS_VERSION,
			'summary'      => $this->summarise( $rows ),
			'posts'        => $rows,
			'redirects'    => HJS_Recovery::instance()->map(),
			'misses'       => HJS_Recovery::instance()->misses(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="hamyar-sentinel-audit.json"' );
		echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle: save the 404 map from the dashboard textarea.
	 *
	 * @return void
	 */
	public function handle_save_redirects(): void {
		if ( ! current_user_can( HJS_CAP ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}
		check_admin_referer( 'hjs_save_redirects' );

		$raw = isset( $_POST['hjs_redirects'] ) ? (string) wp_unslash( $_POST['hjs_redirects'] ) : '';
		HJS_Recovery::instance()->save_map_from_lines( $raw );

		wp_safe_redirect( admin_url( 'tools.php?page=hjs-dashboard&saved=1' ) );
		exit;
	}

	/**
	 * Audit every article once.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function collect(): array {
		$ids = get_posts(
			array(
				'post_type'      => hjs_post_types(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$rows = array();
		foreach ( $ids as $id ) {
			$audit  = HJS_Audit::instance()->audit( (int) $id );
			$rows[] = array(
				'id'         => (int) $id,
				'title'      => get_the_title( $id ),
				'status'     => get_post_status( $id ),
				'url'        => get_permalink( $id ),
				'score'      => (int) $audit['score'],
				'issues'     => array_values(
					array_map(
						static function ( $issue ) {
							return array(
								'key'    => $issue['key'],
								'status' => $issue['status'],
								'label'  => $issue['label'],
							);
						},
						$audit['issues']
					)
				),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['score'] <=> $b['score'];
			}
		);

		return $rows;
	}

	/**
	 * Aggregate numbers for the header cards.
	 *
	 * @param array<int,array<string,mixed>> $rows Audit rows.
	 * @return array<string,int>
	 */
	private function summarise( array $rows ): array {
		$perfect = 0;
		$weak    = 0;
		$sum     = 0;

		foreach ( $rows as $row ) {
			$sum += (int) $row['score'];
			if ( (int) $row['score'] >= 100 ) {
				$perfect++;
			}
			if ( (int) $row['score'] < 60 ) {
				$weak++;
			}
		}

		return array(
			'total'   => count( $rows ),
			'perfect' => $perfect,
			'weak'    => $weak,
			'average' => $rows ? (int) round( $sum / count( $rows ) ) : 0,
		);
	}

	/**
	 * Dashboard screen.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( HJS_CAP ) ) {
			wp_die( 'دسترسی مجاز نیست.' );
		}

		$rows  = $this->collect();
		$stats = $this->summarise( $rows );
		$map   = HJS_Recovery::instance()->map();
		$miss  = HJS_Recovery::instance()->misses();
		?>
		<div class="wrap" style="direction:rtl">
			<h1>سنتینل — سلامت محتوای همیارجراحی</h1>
			<p style="max-width:70ch;color:#475569">
				این صفحه فقط می‌خواند و گزارش می‌دهد. هیچ مقاله‌ای را خودکار منتشر یا بازنویسی نمی‌کند؛ اصلاح‌ها به‌شکل پیش‌نویس ساخته می‌شوند.
			</p>

			<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">
				<?php
				$cards = array(
					array( 'کل مقاله‌ها', $stats['total'], '#0f172a' ),
					array( 'میانگین انطباق', $stats['average'] . '٪', '#0e7490' ),
					array( 'کامل (۱۰۰٪)', $stats['perfect'], '#16a34a' ),
					array( 'زیر ۶۰٪', $stats['weak'], '#dc2626' ),
				);
				foreach ( $cards as $card ) :
					?>
					<div style="flex:1 1 160px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px">
						<div style="font-size:12px;color:#475569"><?php echo esc_html( $card[0] ); ?></div>
						<div style="font-size:22px;font-weight:800;color:<?php echo esc_attr( $card[2] ); ?>"><?php echo esc_html( (string) $card[1] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hjs_export' ), 'hjs_export' ) ); ?>">دریافت گزارش JSON</a>
			</p>

			<table class="widefat striped" style="margin-top:12px">
				<thead>
					<tr>
						<th style="width:70px">انطباق</th>
						<th>مقاله</th>
						<th style="width:110px">وضعیت</th>
						<th>موارد باز</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php $open = array_filter( $row['issues'], static function ( $i ) { return 'ok' !== $i['status']; } ); ?>
						<tr>
							<td><strong><?php echo esc_html( number_format_i18n( (int) $row['score'] ) ); ?>٪</strong></td>
							<td><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['id'] ) ); ?>"><?php echo esc_html( (string) $row['title'] ); ?></a></td>
							<td><?php echo esc_html( 'publish' === $row['status'] ? 'منتشرشده' : 'پیش‌نویس' ); ?></td>
							<td>
								<?php if ( ! $open ) : ?>
									<span style="color:#16a34a">✅ چیزی باز نیست</span>
								<?php else : ?>
									<?php foreach ( $open as $issue ) : ?>
										<div style="font-size:12px;color:<?php echo esc_attr( 'fail' === $issue['status'] ? '#dc2626' : '#b45309' ); ?>">
											<?php echo esc_html( ( 'fail' === $issue['status'] ? '🔴 ' : '🟡 ' ) . $issue['label'] ); ?>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px">نقشهٔ هدایت ۴۰۴</h2>
			<p style="max-width:70ch;color:#475569">
				هیچ ریدایرکت عمومی روی همهٔ ۴۰۴ها وجود ندارد؛ فقط مسیرهای همین جدول ۳۰۱ می‌شوند. هر خط به شکل <code>/old-path/ =&gt; /new-path/</code>.
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hjs_save_redirects">
				<?php wp_nonce_field( 'hjs_save_redirects' ); ?>
				<textarea name="hjs_redirects" rows="6" class="large-text code" spellcheck="false" style="direction:ltr"><?php
					$lines = array();
					foreach ( $map as $from => $to ) {
						$lines[] = $from . ' => ' . $to;
					}
					echo esc_textarea( implode( "\n", $lines ) );
				?></textarea>
				<p><button type="submit" class="button">ذخیرهٔ نقشه</button></p>
			</form>

			<h2 style="margin-top:24px">آخرین ۴۰۴های ثبت‌شده</h2>
			<?php if ( ! $miss ) : ?>
				<p style="color:#475569">چیزی ثبت نشده است.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>مسیر</th><th style="width:90px">تعداد</th><th style="width:160px">آخرین بار</th></tr></thead>
					<tbody>
						<?php foreach ( $miss as $path => $info ) : ?>
							<tr>
								<td><code><?php echo esc_html( (string) $path ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( (int) $info['count'] ) ); ?></td>
								<td><?php echo esc_html( wp_date( 'Y/m/d H:i', (int) $info['last'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

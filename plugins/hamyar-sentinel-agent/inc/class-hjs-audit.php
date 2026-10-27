<?php
/**
 * Audit engine: one question per check, one honest answer each.
 *
 * The engine never edits anything — it only reads. Everything it reports is
 * something an editor can see, verify and fix, which is what keeps the badge
 * trustworthy (ADR-027).
 *
 * @package Hamyar_Sentinel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only health checks for one article.
 */
final class HJS_Audit {

	/**
	 * Singleton instance.
	 *
	 * @var HJS_Audit|null
	 */
	private static $instance = null;

	/**
	 * Per-request cache: post ID => audit result.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $cache = array();

	/**
	 * Instance accessor.
	 *
	 * @return HJS_Audit
	 */
	public static function instance(): HJS_Audit {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Run every check for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{score:int,issues:array<int,array<string,mixed>>,checked_at:int}
	 */
	public function audit( int $post_id ): array {
		if ( isset( $this->cache[ $post_id ] ) ) {
			return $this->cache[ $post_id ];
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'score'      => 0,
				'issues'     => array(),
				'checked_at' => time(),
			);
		}

		$content = (string) $post->post_content;
		$issues  = array(
			$this->check_warning_box( $post_id, $content ),
			$this->check_sources( $content ),
			$this->check_faq( $post_id, $content ),
			$this->check_quick_facts( $post_id ),
			$this->check_structure( $content ),
			$this->check_freshness( $post_id ),
		);

		$total = count( $issues );
		$ok    = 0;
		foreach ( $issues as $issue ) {
			if ( 'ok' === $issue['status'] ) {
				$ok++;
			}
		}

		$result = array(
			'score'      => $total ? (int) round( $ok / $total * 100 ) : 0,
			'issues'     => $issues,
			'checked_at' => time(),
		);

		$this->cache[ $post_id ] = $result;
		return $result;
	}

	/**
	 * Only failures, for compact lists.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function failures( int $post_id ): array {
		$audit = $this->audit( $post_id );
		return array_values(
			array_filter(
				$audit['issues'],
				static function ( $issue ) {
					return 'ok' !== $issue['status'];
				}
			)
		);
	}

	/**
	 * Does the article carry a red-flag box (shortcode, rendered class or the
	 * ⚠️ paragraph every current package uses)?
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Raw content.
	 * @return array<string,mixed>
	 */
	private function check_warning_box( int $post_id, string $content ): array {
		$has = (bool) preg_match( '/hj-box--danger|med-box-danger|\[hjbox[^\]]*danger|⚠️/u', $content );

		return array(
			'key'      => 'warning_box',
			'status'   => $has ? 'ok' : 'fail',
			'label'    => $has ? 'باکس علائم هشدار موجود است' : 'باکس قرمز علائم هشدار نیست',
			'hint'     => $has ? '' : 'یک پاراگراف با نشان ⚠️ و ارجاع به ۱۱۵ یا مرکز جراحی اضافه کنید.',
			'fixable'  => ! $has,
			'fix_kind' => 'draft',
			'post_id'  => $post_id,
		);
	}

	/**
	 * Count approved source domains. Delegates to the theme when it is active
	 * so the dashboard and the publish gate can never report different numbers.
	 *
	 * @param string $content Raw content.
	 * @return array<string,mixed>
	 */
	private function check_sources( string $content ): array {
		if ( function_exists( 'hj_source_summary' ) ) {
			$summary = hj_source_summary( $content );
			if ( ! empty( $summary['loaded'] ) ) {
				return array(
					'key'    => 'sources',
					'status' => ! empty( $summary['ok'] ) ? 'ok' : 'fail',
					'label'  => sprintf(
						'منابع معتبر: %1$d از %2$d دامنه (ردهٔ ۱: %3$d)',
						(int) $summary['approved_count'],
						(int) $summary['min'],
						(int) $summary['tier1_count']
					),
					'hint'   => ! empty( $summary['ok'] ) ? '' : 'دست‌کم سه دامنهٔ فهرست سفید و یک منبع ردهٔ ۱ لازم است.',
					'fixable' => false,
				);
			}
		}

		return array(
			'key'     => 'sources',
			'status'  => 'warn',
			'label'   => 'شمارش منابع انجام نشد (قالب فعال نیست یا فهرست سفید خوانده نشد)',
			'hint'    => 'افزونه و قالب همیارجراحی باید فعال باشند تا شمارش دامنه‌ها معتبر باشد.',
			'fixable' => false,
		);
	}

	/**
	 * Visible FAQ section or editor rows.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Raw content.
	 * @return array<string,mixed>
	 */
	private function check_faq( int $post_id, string $content ): array {
		$has = false;
		if ( function_exists( 'hj_faq_content_has_section' ) ) {
			$has = hj_faq_content_has_section( $content );
		} else {
			$has = (bool) preg_match( '/<h[23]\b[^>]*>[^<]*(?:پرسش|سؤال|سوال)[^<]*<\/h[23]>/iu', $content );
		}

		if ( ! $has && function_exists( 'hj_faq_entry_rows' ) ) {
			$has = (bool) hj_faq_entry_rows( $post_id );
		}

		return array(
			'key'     => 'faq',
			'status'  => $has ? 'ok' : 'warn',
			'label'   => $has ? 'بخش پرسش‌های متداول موجود است' : 'بخش پرسش‌های متداول پیدا نشد',
			'hint'    => $has ? '' : 'سه تا پنج پرسش کوتاه در بخش «پرسش‌های متداول» یا در باکس ویرایشگر اضافه کنید.',
			'fixable' => false,
		);
	}

	/**
	 * Quick-facts rows (the box is optional but the best-performing element
	 * on a surgery page, so a missing one is a warning, never a failure).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private function check_quick_facts( int $post_id ): array {
		$count = 0;
		if ( function_exists( 'hj_quick_facts_rows' ) ) {
			$count = count( hj_quick_facts_rows( $post_id ) );
		} else {
			$meta  = get_post_meta( $post_id, '_hj_quick_facts', true );
			$count = is_array( $meta ) ? count( $meta ) : 0;
		}

		$ok = $count >= 2;

		return array(
			'key'     => 'quick_facts',
			'status'  => $ok ? 'ok' : 'warn',
			'label'   => $ok
				? sprintf( 'خلاصهٔ بالینی: %d ردیف تأییدشده', $count )
				: 'خلاصهٔ بالینی (مدت بستری، دوش، بازگشت به کار) پر نشده',
			'hint'    => $ok ? '' : 'در باکس «خلاصهٔ بالینی» دست‌کم دو ردیف با عدد تأییدشده بنویسید.',
			'fixable' => false,
		);
	}

	/**
	 * Section count — six H2s is the editorial template floor.
	 *
	 * @param string $content Raw content.
	 * @return array<string,mixed>
	 */
	private function check_structure( string $content ): array {
		$h2 = preg_match_all( '/<h2\b/i', $content );
		$h1 = preg_match_all( '/<h1\b/i', $content );
		$ok = $h2 >= 6 && 0 === $h1;

		return array(
			'key'     => 'structure',
			'status'  => $ok ? 'ok' : 'warn',
			'label'   => $ok
				? sprintf( 'ساختار: %d بخش H2 بدون H1', $h2 )
				: sprintf( 'ساختار: %1$d بخش H2 (کف ۶) و %2$d تگ H1 (باید صفر باشد)', $h2, $h1 ),
			'hint'    => $ok ? '' : 'سرفصل‌ها را به قالب چهار فازی مقاله نزدیک کنید؛ H1 از عنوان نوشته می‌آید.',
			'fixable' => false,
		);
	}

	/**
	 * Stale content — a warning only, because only a human can decide
	 * whether a page is still clinically accurate.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private function check_freshness( int $post_id ): array {
		$modified = (int) get_post_modified_time( 'U', true, $post_id );
		$days     = $modified ? (int) floor( ( time() - $modified ) / DAY_IN_SECONDS ) : 9999;
		$limit    = (int) apply_filters( 'hjs_freshness_days', 400 );
		$ok       = $days <= $limit;

		return array(
			'key'    => 'freshness',
			'status' => $ok ? 'ok' : 'warn',
			'label'  => $ok
				? sprintf( 'آخرین ویرایش %d روز پیش', $days )
				: sprintf( 'آخرین ویرایش %d روز پیش (بیش از %d روز)', $days, $limit ),
			'hint'   => $ok ? '' : 'منابع و اعداد را دوباره کنترل و تاریخ بازبینی را به‌روز کنید.',
			'fixable' => false,
		);
	}
}

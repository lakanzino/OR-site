<?php
/**
 * Patient-need sections for article templates (child 0.15.0).
 *
 * Adds four patient-facing blocks that were designed from real search demand
 * (Ninisite crowd-search analysis, docs/content/CROWD-SEARCH-NINISITE.fa.md):
 *
 *   1. Scope note      — "what this page is / is not", printed under the title.
 *   2. Ask-before list — printable questions for the doctor visit.
 *   3. Plain words     — folk term → simple explanation (per article).
 *   4. Visible FAQ     — renders the imported `_hj_faq` when the body has no
 *                        FAQ section, so schema markup always has visible text.
 *
 * Editorial rules respected here: nothing is diagnosed, no dose, no test
 * interpretation. These blocks only organise questions and vocabulary that
 * the patient takes to their own clinician.
 *
 * @package Lax_Theme_Child
 * @since   0.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types that receive the patient-need sections. */
function orsite_pn_post_types(): array {
	return apply_filters( 'orsite_pn_post_types', array( 'surgery', 'patient_guide' ) );
}

/**
 * Whether the current post type gets patient-need sections.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function orsite_pn_applies( int $post_id ): bool {
	return in_array( (string) get_post_type( $post_id ), orsite_pn_post_types(), true );
}

/**
 * Normalise a multi-line / array meta value into a clean list of strings.
 *
 * @param mixed $raw Raw meta value.
 * @return array<int,string>
 */
function orsite_pn_lines( $raw ): array {
	$lines = array();

	if ( is_array( $raw ) ) {
		foreach ( $raw as $item ) {
			if ( is_array( $item ) ) {
				continue;
			}
			foreach ( preg_split( '/\r\n|\r|\n/', (string) $item ) as $part ) {
				$lines[] = $part;
			}
		}
	} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
	}

	$clean = array();
	foreach ( $lines as $line ) {
		$line = trim( wp_strip_all_tags( (string) $line ) );
		if ( '' !== $line ) {
			$clean[] = $line;
		}
	}

	return array_values( array_unique( $clean ) );
}

/**
 * Questions the patient should ask before surgery.
 *
 * Stored in `_hj_ask_doctor`. When an article has no list yet, a default set
 * of purely procedural questions is used (no diagnosis, no numbers, no dose).
 *
 * @param int $post_id Post ID.
 * @return array<int,string>
 */
function orsite_pn_ask_doctor( int $post_id ): array {
	$items = orsite_pn_lines( get_post_meta( $post_id, '_hj_ask_doctor', true ) );
	if ( $items ) {
		return array_slice( $items, 0, 10 );
	}

	/**
	 * Default procedural questions. Filterable per site; the editorial team
	 * can replace them per article through the article editor box.
	 */
	return apply_filters(
		'orsite_pn_default_questions',
		array(
			'این عمل دقیقاً چه کاری انجام می‌دهد و چرا برای من پیشنهاد شده است؟',
			'اگر عمل را انجام ندهم، چه گزینه‌های دیگری دارم؟',
			'طبق نظر شما، روند بهبود و بازگشت به کار من معمولاً چگونه است؟',
			'چه داروهایی را قبل از عمل باید به شما و تیم بیهوشی بگویم؟',
			'چه نشانه‌هایی بعد از عمل باید فوراً به شما یا اورژانس گزارش شود؟',
			'ویزیت پیگیری چه زمانی است و در آن ویزیت چه چیزی بررسی می‌شود؟',
		),
		$post_id
	);
}

/**
 * Plain-language term pairs for the article.
 *
 * Stored in `_hj_terms` as lines of `term | explanation`. Returns pairs.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{term:string,plain:string}>
 */
function orsite_pn_terms( int $post_id ): array {
	$pairs = array();

	foreach ( orsite_pn_lines( get_post_meta( $post_id, '_hj_terms', true ) ) as $line ) {
		$parts = preg_split( '/\s*(?:\||–|—|=)\s*/u', $line, 2 );
		if ( ! is_array( $parts ) || 2 !== count( $parts ) ) {
			continue;
		}
		$term  = trim( (string) $parts[0] );
		$plain = trim( (string) $parts[1] );
		if ( '' === $term || '' === $plain ) {
			continue;
		}
		$pairs[] = array(
			'term'  => $term,
			'plain' => $plain,
		);
	}

	return array_slice( $pairs, 0, 10 );
}

/**
 * Scope note text for the current article: states the boundary of the page.
 *
 * @param int $post_id Post ID.
 * @return array{title:string,body:string}
 */
function orsite_pn_scope_note( int $post_id ): array {
	$stage = 'overview';
	$terms = get_the_terms( $post_id, 'surgery_stage' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$slugs = wp_list_pluck( $terms, 'slug' );
		if ( in_array( 'after-surgery', $slugs, true ) ) {
			$stage = 'after';
		} elseif ( in_array( 'before-surgery', $slugs, true ) ) {
			$stage = 'before';
		}
	}

	$not = 'این صفحه فرایند، آمادگی و مراقبت را توضیح می‌دهد؛ تشخیص نمی‌دهد، آزمایش و تصویربرداری را تفسیر نمی‌کند و دارو تجویز نمی‌کند.';

	$by_stage = array(
		'overview' => 'هدف این صفحه این است که بدانید در این عمل چه اتفاقی می‌افتد، چه کسانی در آن نقش دارند و برای تصمیم‌گیری چه سؤال‌هایی را باید از پزشک خود بپرسید.',
		'before'   => 'هدف این صفحه این است که بدانید چه آمادگی‌های عمومی لازم است و چه چیزی را باید به تیم درمان اطلاع دهید. ساعت ناشتایی، داروها و آزمایش‌های اختصاصی را تیم درمان شما تعیین می‌کند.',
		'after'    => 'هدف این صفحه این است که بدانید کدام نشانه‌ها در دوره‌ی بهبود رایج‌اند و کدام‌ها را باید گزارش کنید. تصمیم درباره‌ی شدت درد، زخم یا ادامه‌ی درمان با پزشک شماست.',
	);

	$body = isset( $by_stage[ $stage ] ) ? $by_stage[ $stage ] : $by_stage['overview'];

	return array(
		'title' => 'این صفحه چه چیزی هست و چه چیزی نیست',
		'body'  => $body . ' ' . $not,
	);
}

/**
 * Render the scope note box.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function orsite_pn_render_scope_note( int $post_id ): void {
	if ( ! orsite_pn_applies( $post_id ) ) {
		return;
	}

	$note = orsite_pn_scope_note( $post_id );
	?>
	<div class="orsite-scope" role="note">
		<p class="orsite-scope__title"><?php echo esc_html( $note['title'] ); ?></p>
		<p class="orsite-scope__body"><?php echo esc_html( $note['body'] ); ?></p>
	</div>
	<?php
}

/**
 * Render the printable "ask before surgery" checklist.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function orsite_pn_render_ask_doctor( int $post_id ): void {
	if ( ! orsite_pn_applies( $post_id ) ) {
		return;
	}

	$items = orsite_pn_ask_doctor( $post_id );
	if ( ! $items ) {
		return;
	}
	?>
	<section class="orsite-ask" id="ask-doctor" aria-labelledby="ask-doctor-title">
		<h2 class="orsite-ask__title" id="ask-doctor-title">پیش از عمل، این سؤال‌ها را از پزشک خود بپرسید</h2>
		<p class="orsite-ask__hint">فهرست را همراه خود ببرید یا چاپ کنید. نوشتن پاسخ‌ها در جلسه‌ی مشاوره، معمولاً از پرسیدن دوباره در روز عمل جلوگیری می‌کند.</p>
		<ul class="orsite-ask__list">
			<?php foreach ( $items as $item ) : ?>
				<li><?php echo esc_html( $item ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="orsite-ask__note">پاسخ‌ها به شرایط هر بیمار بستگی دارد؛ ملاک، نظر پزشک شما و پروتکل مرکز درمانی است.</p>
	</section>
	<?php
}

/**
 * Render the plain-words glossary box.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function orsite_pn_render_terms( int $post_id ): void {
	if ( ! orsite_pn_applies( $post_id ) ) {
		return;
	}

	$pairs = orsite_pn_terms( $post_id );
	if ( ! $pairs ) {
		return;
	}
	?>
	<section class="orsite-terms" id="plain-words" aria-labelledby="plain-words-title">
		<h2 class="orsite-terms__title" id="plain-words-title">واژه‌های این صفحه، به زبان ساده</h2>
		<dl class="orsite-terms__list">
			<?php foreach ( $pairs as $pair ) : ?>
				<div class="orsite-terms__row">
					<dt><?php echo esc_html( $pair['term'] ); ?></dt>
					<dd><?php echo esc_html( $pair['plain'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</section>
	<?php
}

/**
 * Whether the rendered body already contains a visible FAQ / question section.
 *
 * @param string $content Rendered content HTML.
 * @return bool
 */
function orsite_pn_content_has_faq( string $content ): bool {
	if ( '' === trim( $content ) ) {
		return false;
	}

	$pattern = '/<h[23]\b[^>]*>[^<]*(?:پرسش|سؤال|سوال)[^<]*<\/h[23]>/iu';
	return (bool) preg_match( $pattern, $content );
}

/**
 * Render the FAQ list from imported meta when the body has no FAQ section.
 *
 * The same `_hj_faq` rows feed the FAQPage JSON-LD, so schema and visible
 * text stay identical instead of schema describing invisible content.
 *
 * @param int    $post_id Post ID.
 * @param string $content Rendered content HTML (duplicate guard).
 * @return void
 */
function orsite_pn_render_faq( int $post_id, string $content ): void {
	if ( ! orsite_pn_applies( $post_id ) || orsite_pn_content_has_faq( $content ) ) {
		return;
	}

	// One resolver for both editor sources (ACF repeater, then `_hj_faq`),
	// shared with the FAQPage JSON-LD so schema and page never disagree.
	$rows = function_exists( 'hj_faq_entry_rows' )
		? hj_faq_entry_rows( $post_id )
		: array();

	if ( ! $rows ) {
		return;
	}

	if ( function_exists( 'hj_faq_render_section' ) ) {
		echo hj_faq_render_section( 'پرسش‌های متداول', $rows, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside the renderer.
		return;
	}
	?>
	<section class="orsite-faq" id="faq" aria-labelledby="faq-title">
		<h2 class="orsite-faq__title" id="faq-title">پرسش‌های متداول</h2>
		<div class="hj-faq-list">
			<?php foreach ( $rows as $row ) : ?>
				<details>
					<summary><?php echo esc_html( $row['q'] ); ?></summary>
					<div class="orsite-faq__answer"><?php echo wp_kses_post( $row['a'] ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/* -------------------------------------------------------------------------
 * Editor box: the two lists are editable per article without re-import.
 * ---------------------------------------------------------------------- */

/**
 * Register the meta box on supported post types.
 *
 * @return void
 */
function orsite_pn_add_meta_box(): void {
	foreach ( orsite_pn_post_types() as $type ) {
		add_meta_box(
			'orsite-patient-needs',
			'بخش‌های نیاز بیمار (قالب ۰٫۱۵)',
			'orsite_pn_render_meta_box',
			$type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'orsite_pn_add_meta_box' );

/**
 * Meta box fields.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function orsite_pn_render_meta_box( $post ): void {
	$ask   = orsite_pn_lines( get_post_meta( $post->ID, '_hj_ask_doctor', true ) );
	$terms = orsite_pn_lines( get_post_meta( $post->ID, '_hj_terms', true ) );

	wp_nonce_field( 'orsite_pn_save', 'orsite_pn_nonce' );
	?>
	<p><strong>سؤال‌هایی که بیمار قبل از عمل بپرسد</strong> — هر سؤال در یک خط. اگر خالی بماند، فهرست عمومی پیش‌فرض نمایش داده می‌شود.</p>
	<textarea name="orsite_pn_ask" rows="6" class="large-text code" placeholder="این عمل دقیقاً چه کاری انجام می‌دهد؟"><?php echo esc_textarea( implode( "\n", $ask ) ); ?></textarea>

	<p style="margin-top:1rem"><strong>واژه‌های این صفحه به زبان ساده</strong> — هر خط به شکل <code>اصطلاح | توضیح ساده</code>. خالی بماند، بخش نمایش داده نمی‌شود.</p>
	<textarea name="orsite_pn_terms" rows="6" class="large-text code" placeholder="هموروئید | رگ‌های متورم ناحیه‌ی مقعد"><?php echo esc_textarea( implode( "\n", $terms ) ); ?></textarea>

	<p class="description">این دو فهرست فقط پرسش و واژه‌اند؛ پاسخ درمانی، دوز دارو یا تفسیر آزمایش در آن‌ها نوشته نمی‌شود.</p>
	<?php
}

/**
 * Save the meta box values.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function orsite_pn_save_meta_box( int $post_id ): void {
	if ( ! isset( $_POST['orsite_pn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['orsite_pn_nonce'] ) ), 'orsite_pn_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'orsite_pn_ask'   => '_hj_ask_doctor',
		'orsite_pn_terms' => '_hj_terms',
	);

	foreach ( $fields as $field => $meta_key ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			continue;
		}
		$raw   = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
		$lines = orsite_pn_lines( $raw );
		if ( $lines ) {
			update_post_meta( $post_id, $meta_key, $lines );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}
}
add_action( 'save_post', 'orsite_pn_save_meta_box' );

/**
 * Insert the patient-need blocks before the closing medical-disclaimer section.
 *
 * The canonical disclaimer is always the last section of an article, so the
 * supplementary blocks are placed immediately before it instead of after it.
 *
 * @param string $content  Rendered article HTML.
 * @param string $sections Rendered blocks HTML.
 * @return string
 */
function orsite_pn_inject_sections( string $content, string $sections ): string {
	if ( '' === trim( $sections ) ) {
		return $content;
	}

	if ( preg_match_all( '/<h2\b[^>]*>[^<]*سلب[^<]*<\/h2>/iu', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		$last  = end( $matches[0] );
		$start = (int) $last[1];
		if ( $start > 0 ) {
			return substr( $content, 0, $start ) . $sections . "\n" . substr( $content, $start );
		}
	}

	return $content . "\n" . $sections;
}

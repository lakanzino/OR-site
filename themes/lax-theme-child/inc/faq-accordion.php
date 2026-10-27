<?php
/**
 * FAQ accordion — native, editable and schema-consistent (child 0.19.0).
 *
 * The request was an ACF repeater plus an accordion at the end of every
 * surgery article, with the same questions in FAQPage schema. Three facts in
 * this codebase shaped the implementation:
 *
 *  1. The site does not use ACF and does not run on `post`; articles live in
 *     the `surgery` / `patient_guide` CPTs. So ACF is supported when present,
 *     but a native editor exists for the day it is not — the feature cannot
 *     depend on a plugin that may never be installed.
 *  2. Every article already carries a "پرسش‌های متداول" H2 with
 *     `<p><strong>question</strong><br>answer</p>` rows, and the FAQPage
 *     JSON-LD was read from a separate meta field. Visible text and schema
 *     could drift apart. One resolver (`hj_faq_visible_rows()`) now feeds
 *     both the accordion and the JSON-LD.
 *  3. Appending to `the_content` at the default priority puts the FAQ *after*
 *     the medical disclaimer, which ADR-012 forbids. The section is injected
 *     by `orsite_pn_inject_sections()` before that heading instead, and the
 *     theme never renders two FAQ blocks on one page.
 *
 * Rendering is `<details>/<summary>`: no JavaScript, no layout shift. The
 * `name` attribute makes the browser open one answer at a time where it is
 * supported; older browsers simply allow several to stay open.
 *
 * @package Lax_Theme_Child
 * @since   0.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that get the FAQ fields and box.
 *
 * @return array<int,string>
 */
function hj_faq_post_types(): array {
	return array_values( array_filter( (array) apply_filters( 'hj_faq_post_types', array( 'surgery', 'patient_guide' ) ) ) );
}

/**
 * Persian digits for the count label.
 *
 * @param int $number Count.
 * @return string
 */
function hj_faq_num( int $number ): string {
	if ( function_exists( 'hj_refs_num' ) ) {
		return hj_refs_num( $number );
	}
	return str_replace(
		array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
		array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		(string) max( 0, $number )
	);
}

/**
 * Normalise a mixed row set to `q` / `a` pairs.
 *
 * Accepts the ACF shape (`faq_question` / `faq_answer`) and the meta shape
 * (`q` / `a`) so callers never branch on the source.
 *
 * @param mixed $rows Rows from ACF or meta.
 * @return array<int,array{q:string,a:string}>
 */
function hj_faq_normalize( $rows ): array {
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$q = isset( $row['faq_question'] ) ? $row['faq_question'] : ( isset( $row['q'] ) ? $row['q'] : '' );
		$a = isset( $row['faq_answer'] ) ? $row['faq_answer'] : ( isset( $row['a'] ) ? $row['a'] : '' );
		if ( is_array( $q ) ) {
			$q = reset( $q );
		}
		if ( is_array( $a ) ) {
			$a = reset( $a );
		}
		$q = trim( wp_strip_all_tags( (string) $q ) );
		$a = trim( (string) $a );
		if ( '' === $q || '' === $a ) {
			continue;
		}
		$out[] = array(
			'q' => $q,
			'a' => $a,
		);
	}

	return $out;
}

/**
 * Rows stored by the ACF repeater `surgery_faqs`, when ACF is active.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{q:string,a:string}>
 */
function hj_faq_entry_acf_rows( int $post_id ): array {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$rows = get_field( 'surgery_faqs', $post_id );
	return hj_faq_normalize( $rows );
}

/**
 * Rows stored in the theme's own meta field `_hj_faq`.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{q:string,a:string}>
 */
function hj_faq_entry_meta_rows( int $post_id ): array {
	return hj_faq_normalize( get_post_meta( $post_id, '_hj_faq', true ) );
}

/**
 * Editor-entered rows: ACF first, then the native meta field.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{q:string,a:string}>
 */
function hj_faq_entry_rows( int $post_id ): array {
	$rows = hj_faq_entry_acf_rows( $post_id );
	if ( ! $rows ) {
		$rows = hj_faq_entry_meta_rows( $post_id );
	}

	/**
	 * Filter the editor-entered FAQ rows.
	 *
	 * @param array $rows    Normalised rows.
	 * @param int   $post_id Post ID.
	 */
	return hj_faq_normalize( apply_filters( 'hj_faq_entry_rows', $rows, $post_id ) );
}

/**
 * Locate the question heading inside a piece of HTML.
 *
 * A real FAQ ("پرسش‌های متداول") wins over a checklist heading such as
 * «سؤال‌هایی که خوب است قبل از عمل بپرسید» — preparation articles carry both,
 * and only the first is a FAQ.
 *
 * @param string $html Raw HTML.
 * @return array<int,mixed> preg_match_all-style match with offsets, or empty.
 */
function hj_faq_heading_match( string $html ): array {
	$patterns = array(
		'/<h([23])\b[^>]*>[^<]*متداول[^<]*<\/h\1>/iu',
		'/<h([23])\b[^>]*>[^<]*(?:پرسش|سؤال|سوال)[^<]*<\/h\1>/iu',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return $m;
		}
	}

	return array();
}

/**
 * Read the FAQ section that is written inside the article body.
 *
 * Handles the shape used by every package:
 * `<p><strong>question?</strong><br>answer</p>` under a question heading.
 * Returns an empty array when the shape is anything else, so a hand-written
 * section is never mangled.
 *
 * @param string $html Raw post content.
 * @return array<int,array{q:string,a:string}>
 */
function hj_faq_content_rows( string $html ): array {
	if ( '' === trim( $html ) ) {
		return array();
	}

	$m = hj_faq_heading_match( $html );
	if ( ! $m ) {
		return array();
	}

	$start = (int) $m[0][1] + strlen( $m[0][0] );
	$rest  = substr( $html, $start );
	$stop  = preg_match( '/<h2\b/iu', $rest, $mm, PREG_OFFSET_CAPTURE ) ? (int) $mm[0][1] : strlen( $rest );
	$block = substr( $rest, 0, $stop );

	if ( ! preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $block, $ps ) ) {
		return array();
	}

	$rows = array();
	foreach ( $ps[1] as $paragraph ) {
		if ( ! preg_match( '/^\s*<strong\b[^>]*>(.*?)<\/strong>\s*(?:<br\s*\/?>)?\s*(.*)$/is', $paragraph, $pm ) ) {
			return array();
		}
		$q = trim( wp_strip_all_tags( $pm[1] ) );
		$a = trim( $pm[2] );
		if ( '' === $q || '' === $a ) {
			return array();
		}
		$rows[] = array(
			'q' => $q,
			'a' => $a,
		);
	}

	return $rows;
}

/**
 * Whether the raw body already has a question section.
 *
 * @param string $html Raw post content.
 * @return bool
 */
function hj_faq_content_has_section( string $html ): bool {
	return (bool) preg_match( '/<h[23]\b[^>]*>[^<]*(?:پرسش|سؤال|سوال)[^<]*<\/h[23]>/iu', $html );
}

/**
 * The rows a visitor can actually see, which is what the schema must describe.
 *
 * A body section wins over editor rows (it is the text on the page). If the
 * body has a section that cannot be parsed, the result is empty on purpose:
 * describing hidden meta rows would put answers in the schema that nobody
 * can read.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{q:string,a:string}>
 */
function hj_faq_visible_rows( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}

	$html = (string) $post->post_content;
	if ( hj_faq_content_has_section( $html ) ) {
		return hj_faq_content_rows( $html );
	}

	return hj_faq_entry_rows( $post_id );
}

/**
 * Render one answer, keeping inline formatting and line breaks.
 *
 * @param string $answer Raw answer.
 * @return string
 */
function hj_faq_answer_html( string $answer ): string {
	$html = wp_kses_post( $answer );
	if ( false === strpos( $html, '<' ) ) {
		$html = nl2br( $html );
	}
	return $html;
}

/**
 * Build the accordion section.
 *
 * @param string $title    Section title.
 * @param array  $rows     Normalised rows.
 * @param int    $post_id  Post ID (used for the exclusive-open group name).
 * @param string $heading  Heading level: 'h2' or 'h3'.
 * @return string
 */
function hj_faq_render_section( string $title, array $rows, int $post_id = 0, string $heading = 'h2' ): string {
	if ( ! $rows ) {
		return '';
	}

	$level = in_array( $heading, array( 'h2', 'h3' ), true ) ? $heading : 'h2';
	$group = 'hj-faq-' . ( $post_id ? $post_id : 'x' );

	$out  = '<section class="orsite-faq" id="faq" aria-labelledby="faq-title">';
	$out .= '<' . $level . ' class="orsite-faq__title" id="faq-title">' . esc_html( $title );
	$out .= '<span class="orsite-faq__count">' . esc_html( hj_faq_num( count( $rows ) ) . ' پرسش' ) . '</span>';
	$out .= '</' . $level . '>';
	$out .= '<div class="hj-faq-list">';

	foreach ( $rows as $row ) {
		$out .= '<details class="orsite-faq__item" name="' . esc_attr( $group ) . '">';
		$out .= '<summary class="orsite-faq__q">' . esc_html( $row['q'] ) . '</summary>';
		$out .= '<div class="orsite-faq__answer">' . hj_faq_answer_html( $row['a'] ) . '</div>';
		$out .= '</details>';
	}

	$out .= '</div></section>';

	return $out;
}

/**
 * Turn the body's plain question section into the accordion.
 *
 * Idempotent and conservative: a body that already carries the accordion, or
 * a section whose shape is not understood, is returned unchanged.
 *
 * @param string $html Rendered content.
 * @return string
 */
function hj_faq_upgrade_content( string $html ): string {
	if ( '' === trim( $html ) || false !== strpos( $html, 'hj-faq-list' ) ) {
		return $html;
	}

	$m = hj_faq_heading_match( $html );
	if ( ! $m ) {
		return $html;
	}

	$rows = hj_faq_content_rows( $html );
	if ( ! $rows ) {
		return $html;
	}

	$heading_html = (string) $m[0][0];
	$start        = (int) $m[0][1];
	$after        = $start + strlen( $heading_html );
	$rest         = substr( $html, $after );
	$stop         = preg_match( '/<h2\b/iu', $rest, $mm, PREG_OFFSET_CAPTURE ) ? (int) $mm[0][1] : strlen( $rest );

	$title = trim( wp_strip_all_tags( $heading_html ) );
	$title = '' !== $title ? $title : 'پرسش‌های متداول';

	$section = hj_faq_render_section( $title, $rows, (int) get_the_ID() );

	return substr( $html, 0, $start ) . $section . "\n" . substr( $rest, $stop );
}
add_filter( 'the_content', 'hj_faq_upgrade_content', 7 );

/* -------------------------------------------------------------------------
 * ACF repeater (optional) — registered only when ACF is active.
 * ---------------------------------------------------------------------- */

/**
 * Register the field group `surgery_faqs` in code.
 *
 * Doing it in code rather than by hand means the field names, the row shape
 * and the post types can never drift from what the theme reads.
 *
 * @return void
 */
function hj_faq_register_acf_field_group(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	if ( ! apply_filters( 'hj_faq_register_acf_group', true ) ) {
		return;
	}

	$location = array();
	foreach ( hj_faq_post_types() as $type ) {
		$location[] = array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $type,
			),
		);
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_hj_surgery_faqs',
			'title'    => 'پرسش و پاسخ‌های متداول جراحی (FAQ)',
			'fields'   => array(
				array(
					'key'          => 'field_hj_surgery_faqs',
					'label'        => 'پرسش و پاسخ‌های متداول',
					'name'         => 'surgery_faqs',
					'type'         => 'repeater',
					'instructions' => 'سه تا شش پرسش کوتاه بیمار. پاسخ‌ها فقط راهنمای عمومی‌اند: بدون دوز، درصد و توصیهٔ درمانی.',
					'layout'       => 'row',
					'button_label' => 'افزودن پرسش جدید',
					'min'          => 3,
					'max'          => 8,
					'sub_fields'   => array(
						array(
							'key'      => 'field_hj_faq_question',
							'label'    => 'پرسش بیمار',
							'name'     => 'faq_question',
							'type'     => 'text',
							'required' => 1,
						),
						array(
							'key'       => 'field_hj_faq_answer',
							'label'     => 'پاسخ راهنما',
							'name'      => 'faq_answer',
							'type'      => 'textarea',
							'rows'      => 4,
							'required'  => 1,
							'new_lines' => 'br',
						),
					),
				),
			),
			'location' => $location,
			'position' => 'normal',
			'style'    => 'default',
			'active'   => true,
		)
	);
}
add_action( 'acf/init', 'hj_faq_register_acf_field_group' );

/* -------------------------------------------------------------------------
 * Native editor (no ACF needed) — repeatable q/a rows on the same meta field
 * the importer writes, so both paths stay compatible.
 * ---------------------------------------------------------------------- */

/**
 * Register the FAQ meta box when ACF is not providing one.
 *
 * @return void
 */
function hj_faq_add_meta_box(): void {
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		return; // ACF owns the editor; two editable copies would confuse.
	}

	foreach ( hj_faq_post_types() as $type ) {
		add_meta_box(
			'hj-faq-editor',
			'پرسش‌های متداول (FAQ) — قالب ۰٫۱۹',
			'hj_faq_render_meta_box',
			$type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'hj_faq_add_meta_box' );

/**
 * Render the repeatable rows.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function hj_faq_render_meta_box( $post ): void {
	$rows = hj_faq_entry_meta_rows( (int) $post->ID );
	wp_nonce_field( 'hj_faq_save', 'hj_faq_nonce' );
	?>
	<p class="description">هر ردیف یک پرسش و یک پاسخ. اگر مقاله خودش بخش «پرسش‌های متداول» داشته باشد، همان نمایش داده می‌شود و این ردیف‌ها استفاده نمی‌شوند. همین پرسش‌ها در اسکیمای FAQPage هم می‌آیند.</p>
	<div id="hj-faq-rows">
		<?php foreach ( $rows as $row ) : ?>
			<div class="hj-faq-row">
				<p><label><strong>پرسش</strong><br />
					<input type="text" class="widefat" name="hj_faq_q[]" value="<?php echo esc_attr( $row['q'] ); ?>" /></label></p>
				<p><label><strong>پاسخ</strong><br />
					<textarea class="widefat" rows="3" name="hj_faq_a[]"><?php echo esc_textarea( $row['a'] ); ?></textarea></label></p>
				<p><button type="button" class="button hj-faq-remove">حذف این ردیف</button></p>
				<hr />
			</div>
		<?php endforeach; ?>
	</div>
	<p><button type="button" class="button button-secondary" id="hj-faq-add">افزودن پرسش جدید</button></p>
	<script>
	( function () {
		var wrap = document.getElementById( 'hj-faq-rows' );
		var add  = document.getElementById( 'hj-faq-add' );
		if ( ! wrap || ! add ) { return; }
		add.addEventListener( 'click', function () {
			var row = document.createElement( 'div' );
			row.className = 'hj-faq-row';
			row.innerHTML = '<p><label><strong>پرسش</strong><br /><input type="text" class="widefat" name="hj_faq_q[]" value="" /></label></p>'
				+ '<p><label><strong>پاسخ</strong><br /><textarea class="widefat" rows="3" name="hj_faq_a[]"></textarea></label></p>'
				+ '<p><button type="button" class="button hj-faq-remove">حذف این ردیف</button></p><hr />';
			wrap.appendChild( row );
		} );
		wrap.addEventListener( 'click', function ( event ) {
			if ( event.target && event.target.classList.contains( 'hj-faq-remove' ) ) {
				event.target.closest( '.hj-faq-row' ).remove();
			}
		} );
	}() );
	</script>
	<?php
}

/**
 * Save the repeatable rows into `_hj_faq`.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function hj_faq_save_meta_box( $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['hj_faq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_faq_nonce'] ) ), 'hj_faq_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! in_array( get_post_type( $post_id ), hj_faq_post_types(), true ) ) {
		return;
	}

	$questions = isset( $_POST['hj_faq_q'] ) ? (array) wp_unslash( $_POST['hj_faq_q'] ) : array();
	$answers   = isset( $_POST['hj_faq_a'] ) ? (array) wp_unslash( $_POST['hj_faq_a'] ) : array();

	$rows = array();
	foreach ( $questions as $i => $question ) {
		$q = sanitize_text_field( (string) $question );
		$a = isset( $answers[ $i ] ) ? wp_kses_post( (string) $answers[ $i ] ) : '';
		if ( '' === $q || '' === trim( wp_strip_all_tags( $a ) ) ) {
			continue;
		}
		$rows[] = array(
			'q' => $q,
			'a' => $a,
		);
	}

	if ( $rows ) {
		update_post_meta( $post_id, '_hj_faq', $rows );
	} else {
		delete_post_meta( $post_id, '_hj_faq' );
	}
}
add_action( 'save_post', 'hj_faq_save_meta_box' );

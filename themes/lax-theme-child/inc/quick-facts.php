<?php
/**
 * «خلاصهٔ بالینی» بالای مقاله (child 0.20.0).
 *
 * A patient who lands on a surgical article has three questions before
 * reading anything: how long am I in hospital, when can I shower, when can
 * I go back to work. The template answers them in one glance — but only
 * with facts the editors have actually verified.
 *
 * Nothing is derived, guessed or generated here: the box renders only from
 * the `_hj_quick_facts` post meta (label + value rows written by a human in
 * the editor meta box). No meta, no box. That keeps the template honest and
 * keeps ADR-019 (no invented clinical detail) intact.
 *
 * @package Lax_Theme_Child
 * @since   0.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How many rows the editor may fill. */
const HJ_QUICK_FACTS_MAX = 6;

/**
 * Post types that get the box.
 *
 * @return array<int,string>
 */
function hj_quick_facts_post_types(): array {
	return (array) apply_filters( 'hj_quick_facts_post_types', array( 'surgery' ) );
}

/**
 * The small icon set, inline so the box costs zero requests.
 *
 * @return array<string,string>
 */
function hj_quick_facts_icons(): array {
	$common = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';

	return array(
		'clock'   => '<svg ' . $common . '><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
		'droplet' => '<svg ' . $common . '><path d="M12 3s6 6.2 6 10a6 6 0 0 1-12 0c0-3.8 6-10 6-10z"/></svg>',
		'brief'   => '<svg ' . $common . '><rect x="3" y="7.5" width="18" height="12" rx="2.5"/><path d="M9 7.5V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1.5"/></svg>',
		'bed'     => '<svg ' . $common . '><path d="M3 18v-7h13a4 4 0 0 1 4 4v3"/><path d="M3 14h17"/><circle cx="7.5" cy="9.5" r="2"/></svg>',
		'shield'  => '<svg ' . $common . '><path d="M12 3l7 3v6c0 4.2-3 7.4-7 9-4-1.6-7-4.8-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>',
		'walk'    => '<svg ' . $common . '><circle cx="13" cy="4.5" r="1.8"/><path d="M11 21l2-5-2-3 1-4 3 2 2 1"/><path d="M8 21l2-4-1-4"/></svg>',
	);
}

/**
 * Icon order: the first four rows get a sensible, non-decorative icon each.
 *
 * @param int $index Row index.
 * @return string
 */
function hj_quick_facts_icon( int $index ): string {
	$order = array( 'bed', 'droplet', 'brief', 'clock', 'walk', 'shield' );
	$icons = hj_quick_facts_icons();
	$key   = $order[ $index % count( $order ) ] ?? 'shield';

	return $icons[ $key ] ?? '';
}

/**
 * Read and normalise the rows for one post.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{label:string,value:string}>
 */
function hj_quick_facts_rows( int $post_id ): array {
	if ( ! in_array( get_post_type( $post_id ), hj_quick_facts_post_types(), true ) ) {
		return array();
	}

	$raw = get_post_meta( $post_id, '_hj_quick_facts', true );
	$raw = is_array( $raw ) ? $raw : array();
	$out = array();

	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';
		$value = isset( $row['value'] ) ? trim( wp_strip_all_tags( (string) $row['value'] ) ) : '';
		if ( '' === $label || '' === $value ) {
			continue;
		}
		$out[] = array(
			'label' => $label,
			'value' => $value,
		);
		if ( count( $out ) >= HJ_QUICK_FACTS_MAX ) {
			break;
		}
	}

	return (array) apply_filters( 'hj_quick_facts_rows', $out, $post_id );
}

/**
 * Print the quick-facts box. Nothing is printed when fewer than two verified
 * rows exist — a one-line box would look like decoration, not information.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function hj_quick_facts_render( int $post_id ): void {
	$rows = hj_quick_facts_rows( $post_id );
	if ( count( $rows ) < 2 ) {
		return;
	}
	?>
	<section class="hj-quickfacts" aria-labelledby="hj-quickfacts-title">
		<h2 class="hj-quickfacts__title" id="hj-quickfacts-title">خلاصهٔ بالینی در یک نگاه</h2>
		<ul class="hj-quickfacts__grid">
			<?php foreach ( $rows as $index => $row ) : ?>
				<li class="hj-quickfacts__item">
					<span class="hj-quickfacts__icon"><?php echo hj_quick_facts_icon( (int) $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG. ?></span>
					<span class="hj-quickfacts__text">
						<span class="hj-quickfacts__label"><?php echo esc_html( $row['label'] ); ?></span>
						<span class="hj-quickfacts__value"><?php echo esc_html( $row['value'] ); ?></span>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="hj-quickfacts__note">این عددها میانگین تجربهٔ بیماران است، نه تعهد برای یک مورد مشخص؛ برنامهٔ نهایی را تیم درمان شما می‌چیند.</p>
	</section>
	<?php
}

/* -------------------------------------------------------------------------
 * Editor meta box — the only way data enters the box.
 * ---------------------------------------------------------------------- */

/**
 * Register the meta box on the article types that use it.
 *
 * @return void
 */
function hj_quick_facts_add_meta_box(): void {
	foreach ( hj_quick_facts_post_types() as $type ) {
		add_meta_box(
			'hj_quick_facts',
			'خلاصهٔ بالینی (باکس بالای مقاله)',
			'hj_quick_facts_render_meta_box',
			$type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'hj_quick_facts_add_meta_box' );

/**
 * Render the meta box: six fixed rows, no JavaScript, no repeater plugin.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function hj_quick_facts_render_meta_box( $post ): void {
	$rows = hj_quick_facts_rows( (int) $post->ID );
	wp_nonce_field( 'hj_quick_facts_save', 'hj_quick_facts_nonce' );
	?>
	<div style="direction:rtl;text-align:right">
		<p style="margin:0 0 8px;font-size:12px;color:#475569">
			فقط عددهای تأییدشده را بنویسید؛ ردیف خالی نمایش داده نمی‌شود. کمتر از دو ردیف پر، باکس را حذف می‌کند.
		</p>
		<?php for ( $i = 0; $i < HJ_QUICK_FACTS_MAX; $i++ ) : ?>
			<?php
			$label = isset( $rows[ $i ]['label'] ) ? $rows[ $i ]['label'] : '';
			$value = isset( $rows[ $i ]['value'] ) ? $rows[ $i ]['value'] : '';
			?>
			<p style="margin:0 0 6px">
				<label style="display:block;font-size:11px;color:#475569" for="hj_qf_label_<?php echo esc_attr( (string) $i ); ?>">برچسب <?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></label>
				<input type="text" class="widefat" id="hj_qf_label_<?php echo esc_attr( (string) $i ); ?>" name="hj_qf_label[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo esc_attr( $label ); ?>" placeholder="مثلاً مدت بستری">
				<input type="text" class="widefat" style="margin-top:3px" name="hj_qf_value[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="مثلاً ۱ روز">
			</p>
		<?php endfor; ?>
	</div>
	<?php
}

/**
 * Save the rows.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function hj_quick_facts_save( int $post_id ): void {
	if ( ! isset( $_POST['hj_quick_facts_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_quick_facts_nonce'] ) ), 'hj_quick_facts_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$labels = isset( $_POST['hj_qf_label'] ) ? (array) wp_unslash( $_POST['hj_qf_label'] ) : array();
	$values = isset( $_POST['hj_qf_value'] ) ? (array) wp_unslash( $_POST['hj_qf_value'] ) : array();
	$rows   = array();

	for ( $i = 0; $i < HJ_QUICK_FACTS_MAX; $i++ ) {
		$label = isset( $labels[ $i ] ) ? sanitize_text_field( (string) $labels[ $i ] ) : '';
		$value = isset( $values[ $i ] ) ? sanitize_text_field( (string) $values[ $i ] ) : '';
		if ( '' === trim( $label ) || '' === trim( $value ) ) {
			continue;
		}
		$rows[] = array(
			'label' => $label,
			'value' => $value,
		);
	}

	if ( $rows ) {
		update_post_meta( $post_id, '_hj_quick_facts', $rows );
	} else {
		delete_post_meta( $post_id, '_hj_quick_facts' );
	}
}
add_action( 'save_post', 'hj_quick_facts_save' );

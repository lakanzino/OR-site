<?php
/**
 * Medical callout boxes for article bodies (child 0.16.0).
 *
 * One shortcode, four tones, one place for the markup:
 *
 *   [hjbox type="danger"  title="علائم خطر"]…[/hjbox]
 *   [hjbox type="warning" title="مراقبت"]…[/hjbox]
 *   [hjbox type="info"    title="نکته"]…[/hjbox]
 *   [hjbox type="success" title="طبیعی است"]…[/hjbox]
 *
 * Why a shortcode instead of custom blocks: article bodies are plain HTML
 * written in `content.html` and imported by the theme, so a shortcode works
 * in both the editor and the import pipeline. The four tones map to the
 * existing boxed navy design; nothing else is configurable, which keeps the
 * article pages consistent.
 *
 * Editorial limits are enforced by the gate and the style guide, not here:
 * a danger box states what to report and to whom, never a numeric threshold.
 *
 * @package Lax_Theme_Child
 * @since   0.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed tones and their accessible role.
 *
 * @return array<string,string>
 */
function hj_box_types(): array {
	return array(
		'danger'  => 'alert',
		'warning' => 'note',
		'info'    => 'note',
		'success' => 'note',
	);
}

/**
 * Render `[hjbox]`.
 *
 * @param array       $atts    Shortcode attributes.
 * @param string|null $content Inner content.
 * @return string
 */
function hj_medical_box_shortcode( $atts, $content = null ): string {
	$args = shortcode_atts(
		array(
			'type'  => 'info',
			'title' => '',
		),
		(array) $atts,
		'hjbox'
	);

	$types = hj_box_types();
	$type  = isset( $types[ $args['type'] ] ) ? (string) $args['type'] : 'info';
	$role  = $types[ $type ];
	$title = trim( (string) $args['title'] );

	$body = do_shortcode( (string) $content );
	$body = wp_kses_post( $body );
	if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
		return '';
	}

	$out  = '<aside class="hj-box hj-box--' . esc_attr( $type ) . '" role="' . esc_attr( $role ) . '">';
	if ( '' !== $title ) {
		$out .= '<p class="hj-box__title">' . esc_html( $title ) . '</p>';
	}
	$out .= '<div class="hj-box__body">' . $body . '</div>';
	$out .= '</aside>';

	return $out;
}
add_shortcode( 'hjbox', 'hj_medical_box_shortcode' );

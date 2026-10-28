<?php
/**
 * Presentation-only integration for the Soft UI layer.
 *
 * @package Lax_Theme_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Use the child copy of the font in the editor and on the front end.
 * The parent's preload would otherwise download a second copy of the same face.
 *
 * @return void
 */
function hj_soft_ui_setup() {
	remove_action( 'wp_head', 'lax_theme_preload_font', 1 );
	add_editor_style( array( 'style.css', 'assets/css/soft-ui-editor.css' ) );
}
add_action( 'after_setup_theme', 'hj_soft_ui_setup', 20 );

/**
 * Preload only the regular local face used for body text.
 *
 * @return void
 */
function hj_soft_ui_preload_font() {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_stylesheet_directory_uri() . '/fonts/Vazirmatn-Regular.woff2' )
	);
}
add_action( 'wp_head', 'hj_soft_ui_preload_font', 1 );

/**
 * Let editors apply the three alert appearances to an existing Group block.
 * Registering styles does not alter any post or insert medical copy.
 *
 * @return void
 */
function hj_soft_ui_register_group_styles() {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	$styles = array(
		'hj-alert-success' => __( 'نکته (سبز)', 'lax-theme-child' ),
		'hj-alert-warning' => __( 'هشدار (زرد)', 'lax-theme-child' ),
		'hj-alert-error'   => __( 'مراجعه به پزشک (قرمز)', 'lax-theme-child' ),
	);

	foreach ( $styles as $name => $label ) {
		register_block_style(
			'core/group',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}
}
add_action( 'init', 'hj_soft_ui_register_group_styles' );

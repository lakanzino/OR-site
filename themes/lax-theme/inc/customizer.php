<?php
/**
 * Lax Theme Customizer options.
 *
 * @package Lax_Theme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lax_theme_customize_register' ) ) {
	/**
	 * Add postMessage support for site title and description.
	 */
	function lax_theme_customize_register( $wp_customize ) {
		$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
		$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
		$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

		if ( isset( $wp_customize->selective_refresh ) ) {
			$wp_customize->selective_refresh->add_partial(
				'blogname',
				array(
					'selector'        => '.site-title a',
					'render_callback' => 'lax_theme_customize_partial_blogname',
				)
			);
			$wp_customize->selective_refresh->add_partial(
				'blogdescription',
				array(
					'selector'        => '.site-description',
					'render_callback' => 'lax_theme_customize_partial_blogdescription',
				)
			);
		}

		// Add section for footer text.
		$wp_customize->add_section(
			'lax_theme_options',
			array(
				'title'    => esc_html__( 'Theme Options', 'lax-theme' ),
				'priority' => 30,
			)
		);

		// Footer copyright text.
		$wp_customize->add_setting(
			'lax_theme_footer_text',
			array(
				'default'           => esc_html__( 'All rights reserved.', 'lax-theme' ),
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'lax_theme_footer_text',
			array(
				'label'   => esc_html__( 'Footer Text', 'lax-theme' ),
				'section' => 'lax_theme_options',
				'type'    => 'text',
			)
		);
	}
}
add_action( 'customize_register', 'lax_theme_customize_register' );

if ( ! function_exists( 'lax_theme_customize_partial_blogname' ) ) {
	function lax_theme_customize_partial_blogname() {
		bloginfo( 'name' );
	}
}

if ( ! function_exists( 'lax_theme_customize_partial_blogdescription' ) ) {
	function lax_theme_customize_partial_blogdescription() {
		bloginfo( 'description' );
	}
}
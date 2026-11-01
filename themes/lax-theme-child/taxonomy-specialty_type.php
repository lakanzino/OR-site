<?php
/**
 * Specialty-type archive (`/specialty-type/{slug}/`). Section 8 — same
 * listing UI as the doctor archive; falls back to the parent theme when the
 * plugin is off.
 *
 * @package Lax_Theme_Child
 */

if ( ! function_exists( 'hj_doctor_archive_html' ) ) {
	require get_template_directory() . '/archive.php';
	return;
}

echo hj_doctor_archive_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the plugin renderer.

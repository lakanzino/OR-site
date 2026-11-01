<?php
/**
 * Doctor archive (post type `doctor`) and its filtered variants.
 *
 * Section 8: delegates to the hamyar-doctors plugin renderer. When the
 * plugin is inactive (or the file is missing) the parent theme's archive
 * template takes over, so renaming this file is an instant rollback.
 *
 * @package Lax_Theme_Child
 */

if ( ! function_exists( 'hj_doctor_archive_html' ) ) {
	require get_template_directory() . '/archive.php';
	return;
}

get_header();
?>
<div class="hj-ar-wrap">
<?php echo hj_doctor_archive_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the plugin renderer. ?>
</div>
<?php
get_footer();

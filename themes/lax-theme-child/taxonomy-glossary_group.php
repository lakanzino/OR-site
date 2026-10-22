<?php
/**
 * Glossary Topic / Category Archive (`/glossary-group/{slug}/`).
 *
 * @package Lax_Theme_Child
 * @since   0.12.0
 */

get_header();
?>

<div class="container orsite-archive-wrap">
	<main id="primary" class="orsite-archive">
		<?php get_template_part( 'template-parts/glossary', 'directory' ); ?>
	</main>
</div>

<?php
get_footer();

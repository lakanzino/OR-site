<?php
/**
 * Single Glossary Term View (`/glossary/{slug}/`).
 *
 * Supports both terms stored in `hj_glossary_terms` (routed via
 * `hj_glossary_resolve_single_term_route()`) and classic `glossary` CPT posts.
 *
 * @package Lax_Theme_Child
 * @since   0.12.0
 */

get_header();

$hj_term = $GLOBALS['hj_single_term_data'] ?? null;
if ( ! $hj_term && have_posts() ) {
	the_post();
	$t_groups = get_the_terms( get_the_ID(), 'glossary_group' );
	$g_first  = ( is_array( $t_groups ) && ! empty( $t_groups ) ) ? $t_groups[0] : null;
	$g_link   = $g_first ? get_term_link( $g_first, 'glossary_group' ) : '';
	$hj_term  = array(
		'term'       => get_the_title(),
		'en_term'    => '',
		'aliases'    => '',
		'definition' => wp_strip_all_tags( get_the_excerpt() ? get_the_excerpt() : get_the_content() ),
		'group_id'   => $g_first ? (int) $g_first->term_id : 0,
		'group_name' => $g_first ? $g_first->name : '',
		'group_url'  => ( $g_first && ! is_wp_error( $g_link ) ) ? (string) $g_link : '',
	);
}

$hj_hub_url = function_exists( 'hj_archive_url' ) ? hj_archive_url( 'glossary' ) : home_url( '/glossary/' );
?>

<div class="container orsite-article-wrap" style="padding-top:1.75rem;padding-bottom:3rem;">
	<main id="primary">
		<?php if ( $hj_term ) : ?>
			<nav class="orsite-breadcrumb" aria-label="مسیر صفحه" style="margin-bottom:1rem;">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( $hj_hub_url ); ?>">واژه‌نامه</a>
				<?php if ( ! empty( $hj_term['group_name'] ) && ! empty( $hj_term['group_url'] ) ) : ?>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( $hj_term['group_url'] ); ?>"><?php echo esc_html( $hj_term['group_name'] ); ?></a>
				<?php endif; ?>
			</nav>

			<article class="hj-glossary-hub__hero" style="border-top-color:#059669;">
				<div class="hj-glossary-hub__top">
					<div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:10px;">
						<h1 class="hj-glossary-hub__title"><?php echo esc_html( $hj_term['term'] ); ?></h1>
						<?php if ( ! empty( $hj_term['en_term'] ) ) : ?>
							<span class="hj-glossary-item__en" style="font-size:0.95rem;"><?php echo esc_html( $hj_term['en_term'] ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $hj_term['group_name'] ) && ! empty( $hj_term['group_url'] ) ) : ?>
						<a class="hj-glossary-hub__count-pill" style="text-decoration:none;" href="<?php echo esc_url( $hj_term['group_url'] ); ?>">
							<?php echo esc_html( $hj_term['group_name'] ); ?>
						</a>
					<?php endif; ?>
				</div>

				<p style="font-size:1.05rem;line-height:1.9;color:#1e293b;margin:0.75rem 0 1.25rem;">
					<?php echo esc_html( $hj_term['definition'] ); ?>
				</p>

				<?php if ( ! empty( $hj_term['aliases'] ) ) : ?>
					<p style="font-size:0.85rem;color:#64748b;margin:0 0 1rem;">
						<strong>نام‌ها و املاهای هم‌ارز:</strong> <?php echo esc_html( $hj_term['aliases'] ); ?>
					</p>
				<?php endif; ?>

				<div style="padding-top:0.9rem;border-top:1px solid #e2e8f0;display:flex;flex-wrap:wrap;gap:10px;">
					<a class="hj-glossary-page-btn is-active" href="<?php echo esc_url( $hj_hub_url ); ?>">← بازگشت به همه‌ی واژگان</a>
					<?php if ( ! empty( $hj_term['group_name'] ) && ! empty( $hj_term['group_url'] ) ) : ?>
						<a class="hj-glossary-page-btn" href="<?php echo esc_url( $hj_term['group_url'] ); ?>">واژگان بخش «<?php echo esc_html( $hj_term['group_name'] ); ?>»</a>
					<?php endif; ?>
				</div>
			</article>
		<?php endif; ?>
	</main>
</div>

<?php
get_footer();

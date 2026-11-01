<?php
/**
 * Specialty archive (child 0.32.0): all doctors of one specialty — the target
 * of article cross-links («مقالات ارتوپدی → بخش تمام ارتوپدها»).
 *
 * @package Lax_Theme_Child
 */

defined( 'ABSPATH' ) || exit;
get_header();

$hj_term = get_queried_object();
?>
<style>
.hjd-page{--ink:#0f172a;--line:#e2e8f0;--muted:#64748b;--navy:#0a2f6b;color:var(--ink);line-height:1.9}
.hjd-page h1{font-size:1.6rem;color:var(--navy);margin:0 0 .5rem}
.hjd-page .hjd-intro{color:var(--muted);font-size:.92rem;margin:0 0 1rem}
</style>
<div class="container orsite-article-wrap hjd-page" style="padding-top:1.75rem;padding-bottom:3rem"><main id="primary">
<nav class="orsite-breadcrumb" aria-label="مسیر صفحه">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a> /
	<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'doctor' ) ); ?>">پزشکان</a> /
	<span><?php echo esc_html( $hj_term instanceof WP_Term ? $hj_term->name : 'رشته' ); ?></span>
</nav>
<h1><?php echo esc_html( $hj_term instanceof WP_Term ? $hj_term->name : 'پزشکان' ); ?></h1>
<p class="hjd-intro">همهٔ پزشکان و جراحان این رشته، با میانگین ستاره‌های آرای کاربران.</p>
<?php
$hj_list_doctors = get_posts(
	array(
		'post_type'   => 'doctor',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'specialty',
				'field'    => 'term_id',
				'terms'    => $hj_term instanceof WP_Term ? (int) $hj_term->term_id : 0,
			),
		),
	)
);
get_template_part( 'template-parts/doctor-list' );
?>
</main></div><?php get_footer();

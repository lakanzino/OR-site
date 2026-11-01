<?php
/**
 * Doctor directory listing (child 0.32.0): all specialists grouped by
 * specialty, each as a compact technology-style card linking to the profile.
 *
 * @package Lax_Theme_Child
 */

defined( 'ABSPATH' ) || exit;
get_header();

$hj_terms = get_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => false ) );
$hj_terms = is_array( $hj_terms ) ? $hj_terms : array();
?>
<style>
.hjd-page{--ink:#0f172a;--line:#e2e8f0;--muted:#64748b;--navy:#0a2f6b;color:var(--ink);line-height:1.9}
.hjd-page h1{font-size:1.6rem;color:var(--navy);margin:0 0 .5rem}
.hjd-page h2{font-size:1.2rem;color:var(--navy);margin:1.6rem 0 .7rem;padding-bottom:.35rem;border-bottom:2px solid var(--line)}
.hjd-page .hjd-intro{color:var(--muted);font-size:.92rem;margin:0 0 1rem}
</style>
<div class="container orsite-article-wrap hjd-page" style="padding-top:1.75rem;padding-bottom:3rem"><main id="primary">
<h1>پزشکان و جراحان</h1>
<p class="hjd-intro">پروفایل هر پزشک با مدرک، کد نظام پزشکی، محل طبابت و میانگین ستاره‌های آرای کاربران نمایش داده می‌شود.</p>

<?php
$hj_listed = array();
foreach ( $hj_terms as $hj_term ) :
	$hj_docs = get_posts(
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
					'terms'    => (int) $hj_term->term_id,
				),
			),
		)
	);
	if ( ! $hj_docs ) {
		continue;
	}
	$hj_listed = array_merge( $hj_listed, wp_list_pluck( $hj_docs, 'ID' ) );
	?>
	<h2><?php echo esc_html( $hj_term->name ); ?></h2>
	<?php
	$hj_list_doctors = $hj_docs;
	get_template_part( 'template-parts/doctor-list' );
endforeach;

$hj_unterm = get_posts(
	array(
		'post_type'   => 'doctor',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
		'post__not_in' => $hj_listed, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_post__not_in
	)
);
if ( $hj_unterm ) :
	?>
	<h2>سایر پزشکان</h2>
	<?php
	$hj_list_doctors = $hj_unterm;
	get_template_part( 'template-parts/doctor-list' );
endif;
?>

<aside class="hjd-cta" aria-label="ثبت پروفایل پزشک">
	<strong>پزشک یا جراح هستید؟</strong>
	<p>پروفایل خود را رایگان یا با تعرفهٔ درج بسازید، اطلاعاتتان را خودتان ویرایش کنید و ویجت ستاره‌هایتان را در سایت خودتان بگذارید.</p>
	<a class="hjd-cta-btn" href="<?php echo esc_url( function_exists( 'hj_doctor_dashboard_url' ) ? hj_doctor_dashboard_url() : home_url( '/my-profile/' ) ); ?>">ثبت / ویرایش پروفایل پزشک</a>
</aside>
</main></div><?php get_footer();

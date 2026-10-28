<?php
/**
 * Individual disease: navy profile layout built from the 15-field disease profile.
 * Empty profile fields are hidden. Records without a profile show the editor content.
 * Styles are scoped to .hj-disease-navy so the rest of the site is unaffected.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<style>
.hj-disease-navy{--navy:#0a2f6b;--navy-deep:#041a3f;--navy-soft:#e8eef8;--ok:#16a34a;--ok-soft:#f0fdf4;--warn:#d97706;--warn-soft:#fffbeb;--danger:#dc2626;--danger-soft:#fef2f2;--ink:#0f172a;--line:#e2e8f0;color:var(--ink);line-height:1.9}
.hj-disease-navy .hj-dn-hero{background:var(--navy);color:#fff;border-radius:14px;padding:1.6rem 1.5rem;margin-bottom:1.25rem}
.hj-disease-navy .hj-dn-hero h1{color:#fff;margin:0 0 .35rem;font-size:1.9rem;line-height:1.5}
.hj-disease-navy .hj-dn-en{direction:ltr;text-align:left;opacity:.9;margin:0}
.hj-disease-navy .hj-dn-meta{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.8rem}
.hj-disease-navy .hj-dn-chip{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:.2rem .75rem;font-size:.9rem}
.hj-disease-navy .hj-dn-box{border-radius:12px;padding:1rem 1.2rem;margin:0 0 1rem;border:1px solid var(--line);background:#fff}
.hj-disease-navy .hj-dn-box h2{color:var(--navy-deep);font-size:1.2rem;margin:0 0 .5rem;padding-bottom:.35rem;border-bottom:2px solid var(--navy-soft)}
.hj-disease-navy .hj-dn-box p{margin:.25rem 0}
.hj-disease-navy .hj-dn-box ul{margin:.25rem 0 0;padding-right:1.25rem;list-style:disc}
.hj-disease-navy .hj-dn-warn-yellow{background:var(--warn-soft);border-color:var(--warn)}
.hj-disease-navy .hj-dn-warn-yellow h2{border-bottom-color:#fde68a}
.hj-disease-navy .hj-dn-warn-red{background:var(--danger-soft);border-color:var(--danger)}
.hj-disease-navy .hj-dn-warn-red h2{color:var(--danger);border-bottom-color:#fecaca}
.hj-disease-navy .hj-dn-ok{background:var(--ok-soft);border-color:var(--ok)}
.hj-disease-navy .hj-dn-ok h2{border-bottom-color:#bbf7d0}
.hj-disease-navy .hj-dn-pending{color:#475569;font-style:italic}
</style>
<div class="container orsite-article-wrap" style="padding-top:1.75rem;padding-bottom:3rem"><main id="primary">
<?php while ( have_posts() ) : the_post();
	$hj_p = hj_disease_profile_for( get_the_ID() );
	$hj_has_profile = false;
	foreach ( $hj_p as $hj_k => $hj_v ) { if ( in_array( $hj_k, array( 'nameFa', 'nameEn' ), true ) ) { continue; } if ( '' !== trim( (string) $hj_v ) ) { $hj_has_profile = true; break; } }
	$hj_title = ! empty( $hj_p['nameFa'] ) ? $hj_p['nameFa'] : get_the_title();
	$hj_en = get_post_meta( get_the_ID(), '_hj_disease_en', true );
	$hj_list = function ( $key ) use ( $hj_p ) {
		$lines = hj_disease_profile_lines( $hj_p[ $key ] ?? '' );
		if ( ! $lines ) { return ''; }
		$out = '<ul>';
		foreach ( $lines as $line ) { $out .= '<li>' . esc_html( $line ) . '</li>'; }
		return $out . '</ul>';
	};
	$hj_text = function ( $key ) use ( $hj_p ) {
		return ! empty( $hj_p[ $key ] ) ? '<p>' . nl2br( esc_html( trim( $hj_p[ $key ] ) ) ) . '</p>' : '';
	};
	$hj_section = function ( $class, $title, $body ) {
		return '' === $body ? '' : '<section class="hj-dn-box ' . $class . '"><h2>' . esc_html( $title ) . '</h2>' . $body . '</section>';
	};
?>
<nav class="orsite-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a> / <a href="<?php echo esc_url( get_post_type_archive_link( 'disease' ) ); ?>">دانشنامه بیماری‌ها</a></nav>
<article <?php post_class( 'hj-disease-entry hj-disease-navy' ); ?>>
	<header class="hj-dn-hero">
		<h1><?php echo esc_html( $hj_title ); ?></h1>
		<?php if ( $hj_en || ! empty( $hj_p['nameEn'] ) ) : ?><p class="hj-dn-en" dir="ltr"><?php echo esc_html( $hj_en ? $hj_en : $hj_p['nameEn'] ); ?></p><?php endif; ?>
		<div class="hj-dn-meta">
			<?php if ( ! empty( $hj_p['icd10'] ) ) : ?><span class="hj-dn-chip">ICD-10: <?php echo esc_html( $hj_p['icd10'] ); ?></span><?php endif; ?>
			<?php if ( ! empty( $hj_p['specialty'] ) ) : ?><span class="hj-dn-chip"><?php echo esc_html( $hj_p['specialty'] ); ?></span><?php endif; ?>
		</div>
	</header>

	<section class="hj-dn-box hj-dn-warn-yellow" role="note"><h2>هشدار</h2><p>این مطلب آموزشی است و جایگزین تشخیص یا درمان پزشکی نیست. برای بررسی علائم به پزشک مراجعه کنید.</p></section>

<?php if ( $hj_has_profile ) :
	echo $hj_section( '', 'معرفی کوتاه', $hj_text( 'intro' ) );
	echo $hj_section( '', 'نام‌های دیگر', $hj_text( 'otherNames' ) );
	echo $hj_section( '', 'علت بیماری', $hj_text( 'cause' ) );
	echo $hj_section( '', 'عوامل خطر', $hj_list( 'riskFactors' ) );
	echo $hj_section( '', 'علائم', $hj_list( 'symptoms' ) );
	echo $hj_section( '', 'روش‌های تشخیص', $hj_list( 'diagnosis' ) );
	echo $hj_section( '', 'درمان‌های رایج', $hj_list( 'treatment' ) );
	echo $hj_section( '', 'پیشگیری', $hj_list( 'prevention' ) );
	echo $hj_section( 'hj-dn-warn-yellow', 'عوارض', $hj_list( 'complications' ) );
	echo $hj_section( 'hj-dn-warn-red', 'چه زمانی به پزشک مراجعه کنیم؟', $hj_list( 'whenToSeeDoctor' ) );
	echo $hj_section( 'hj-dn-ok', 'منابع پزشکی معتبر', $hj_list( 'sources' ) );
	?>
<?php else : ?>
	<section class="hj-dn-box"><p class="hj-dn-pending">اطلاعات این بیماری در حال تکمیل و بازبینی پزشکی است.</p></section>
	<div class="entry-content"><?php the_content(); ?></div>
<?php endif; ?>
</article>
<?php endwhile; ?></main></div><?php get_footer();

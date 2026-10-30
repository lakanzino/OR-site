<?php
/**
 * Symptom checker test page (noindex). Loaded by inc/symptom-checker-test.php.
 *
 * @package Lax_Theme_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$hj_sc_base      = get_stylesheet_directory_uri() . '/data/symptom-checker';
$hj_sc_emergency = hj_sc_emergency_number();
$hj_sc_img       = get_stylesheet_directory_uri() . '/assets/img/symptom-checker';
$hj_sc_stats     = hj_sc_stats();
?>
<main id="primary" class="hj-sc-test">
	<section class="hj-sc-hero">
		<div class="hj-sc-hero__text">
			<p class="hj-sc-badge">فضای تست · نسخهٔ آزمایشی</p>
			<h1>ابزار تشخیص علائم</h1>
			<p class="hj-sc-lead">با چند پرسش ساده، علائمتان را با بیماری‌های این مجموعه مقایسه می‌کنیم. این ابزار تشخیص نمی‌دهد.</p>
			<ul class="hj-sc-stats" aria-label="آمار داده‌ها">
				<li><strong><?php echo esc_html( $hj_sc_stats['diseases'] ); ?></strong><span>بیماری</span></li>
				<li><strong><?php echo esc_html( $hj_sc_stats['rankable'] ); ?></strong><span>قابل مقایسه</span></li>
				<li><strong><?php echo esc_html( $hj_sc_stats['tags'] ); ?></strong><span>علامت</span></li>
				<li><strong><?php echo esc_html( $hj_sc_stats['redflags'] ); ?></strong><span>قاعدهٔ ایمنی</span></li>
			</ul>
		</div>
		<figure class="hj-sc-figure">
			<img src="<?php echo esc_url( $hj_sc_img . '/hero-cyber.jpg' ); ?>" alt="" width="1200" height="675" loading="eager" decoding="async">
		</figure>
	</section>

	<div class="hj-sc-warning" role="note">
		<strong>هشدار مهم:</strong> اگر علائم شدید یا ناگهانی است، همین حالا با اورژانس <?php echo esc_html( $hj_sc_emergency ); ?> تماس بگیرید یا به نزدیک‌ترین اورژانس بروید. این ابزار جای مراجعه‌ی فوری را نمی‌گیرد. داده‌ها هنوز بازبینی پزشکی نشده‌اند.
	</div>

	<section id="hj-sc-app" class="hj-sc-app" data-base="<?php echo esc_attr( $hj_sc_base ); ?>" data-emergency="<?php echo esc_attr( $hj_sc_emergency ); ?>" aria-live="polite">
		<noscript>برای استفاده از این ابزار، جاوااسکریپت باید فعال باشد.</noscript>
	</section>

	<section class="hj-sc-about" aria-labelledby="hj-sc-about-title">
		<h2 id="hj-sc-about-title">این ابزار چه می‌کند؟</h2>
		<ol class="hj-sc-steps">
			<li><strong>سن و جنسیت</strong> فهرست بیماری‌های مرتبط را محدود می‌کنند.</li>
			<li><strong>پرسش‌های ایمنی</strong> اول پرسیده می‌شوند. اگر یکی از آن‌ها بله باشد، ابزار رتبه‌بندی نمی‌کند و شما را به اورژانس می‌فرستد.</li>
			<li><strong>پرسش‌های علامت</strong> بر اساس ناحیهٔ بدن و پاسخ‌های قبلی انتخاب می‌شوند.</li>
			<li><strong>نتیجه</strong> سه بیماری نزدیک را با سطح همخوانی نسبی و علائم همراه نشان می‌دهد. درصد احتمال نیست.</li>
		</ol>
		<p class="hj-sc-muted">منابع داده: NHS، Mayo Clinic، NIDDK، MedlinePlus، WHO، CDC، و انجمن‌های تخصصی مانند AUA، ACG، ADA، AAD و AAO-HNS. فهرست کامل منابع هر بیماری در کارت همان بیماری آمده است.</p>
		<figure class="hj-sc-figure hj-sc-figure--wide">
			<img src="<?php echo esc_url( $hj_sc_img . '/decision-tree.jpg' ); ?>" alt="" width="1200" height="675" loading="lazy" decoding="async">
		</figure>
	</section>
</main>
<?php
get_footer();

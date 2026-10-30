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
$hj_sc_base = get_stylesheet_directory_uri() . '/data/symptom-checker';
?>
<main id="primary" class="hj-sc-test">
	<section class="hj-sc-hero">
		<p class="hj-sc-badge">فضای تست · نسخهٔ آزمایشی</p>
		<h1>چک‌کنندهٔ علائم</h1>
		<p class="hj-sc-lead">با چند پرسش ساده، علائمتان را با بیماری‌های این مجموعه مقایسه می‌کنیم. این ابزار تشخیص نمی‌دهد.</p>
		<div class="hj-sc-warning" role="note">
			<strong>هشدار مهم:</strong> اگر علائم شدید یا ناگهانی است، همین حالا با اورژانس ۱۱۵ تماس بگیرید یا به نزدیک‌ترین اورژانس بروید. این ابزار جای مراجعه‌ی فوری را نمی‌گیرد.
		</div>
	</section>
	<section id="hj-sc-app" class="hj-sc-app" data-base="<?php echo esc_attr( $hj_sc_base ); ?>" aria-live="polite">
		<noscript>برای استفاده از این ابزار، جاوااسکریپت باید فعال باشد.</noscript>
	</section>
</main>
<?php
get_footer();

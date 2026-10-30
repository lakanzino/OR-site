<?php
/**
 * Symptom Checker Test Page (Child Theme)
 * Version 0.30.0
 */
get_header();
?>

<div class="container hj-home-wrap">
	<main id="primary" class="hj-home">
		<section class="hj-home-hero" aria-labelledby="hj-symptom-title">
			<h1 id="hj-symptom-title" class="hj-home-hero__title">راهنمای اولیه علائم | همیار جراحی</h1>
			<p class="muted">چند پرسش کوتاه برای پیدا کردن منابع آموزشی معتبر. این ابزار تشخیص پزشکی نیست.</p>
			
			<!-- ابزار تشخیص علائم -->
			<div id="symptom-checker-shell" class="shell">
				<header>
					<div class="brand">✳ همیار جراحی <span> / راهنمای اولیه علائم</span></div>
					<h1>علائم خود را بهتر بشناسید</h1>
					<p>چند پرسش کوتاه برای پیدا کردن منابع آموزشی معتبر</p>
					<div class="progress" aria-hidden="true"><div id="progress-bar"></div></div>
					<div id="step-number"></div>
				</header>
				<main>
					<div class="notice"><strong>این ابزار تشخیص پزشکی نیست.</strong> در صورت علائم شدید یا نگران‌کننده، منتظر نتیجه نمانید و با اورژانس محلی تماس بگیرید؛ در ایران ۱۱۵.</div>
					<nav class="app-nav" aria-label="بخش‌های برنامه">
						<button type="button" data-view="checker" aria-pressed="true">راهنمای علائم</button>
						<button type="button" data-view="encyclopedia" aria-pressed="false">دانشنامه بیماری‌ها</button>
					</nav>
					<div id="content" aria-live="polite">
						<p>در حال بارگذاری داده‌ها…</p>
					</div>
				</main>
				<footer>نمونه آموزشی · اطلاعات شما به سرور ارسال یا ذخیره نمی‌شود</footer>
			</div>
		</section>
	</main>
</div>

<!-- بارگذاری اسکریپت‌ها و داده‌ها -->
<script>
	// مسیرهای فایل‌ها در قالب فرزند
	window.hamyarDbUrl = '<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/data/symptom-checker/medical_db.json';
</script>
<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/css/style.css">
<script src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/js/engine.js" defer></script>
<script src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/js/ui.js" defer></script>

<?php
get_footer();
?>

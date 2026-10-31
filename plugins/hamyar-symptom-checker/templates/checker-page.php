<?php
/**
 * Standalone template for the checker page. Deliberately not a shortcode and
 * not an iframe: the page markup lives here, the behaviour lives in
 * assets/ui/app.js and the decisions live in the engine.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'hj-sc-page' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}
?>
<a class="skip-link" href="#app">پرش به محتوای اصلی</a>

<header class="site-header">
	<div class="wrap">
		<p class="brand">همیار جراحی — دستیار نشانه‌ها (پیش‌نمایش داخلی)</p>
	</div>
</header>

<main class="wrap">
	<div id="safety" hidden></div>
	<div id="app">
		<p class="muted">در حال بارگذاری…</p>
	</div>
	<div id="live" class="sr-only" aria-live="polite" aria-atomic="true"></div>
	<noscript>
		<p class="notice">برای استفاده از این ابزار باید جاوااسکریپت مرورگر روشن باشد.</p>
	</noscript>
</main>

<footer class="site-footer wrap">
	<p>این ابزار تشخیص پزشکی نیست و جای معاینه، آزمایش یا مشاورهٔ پزشک را نمی‌گیرد.</p>
	<p>در درد شدید یا بدحالی، همین حالا کمک پزشکی بگیر.</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>

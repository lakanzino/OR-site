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
		<p class="brand">همیار جراحی</p>
	</div>
</header>

<main class="wrap">
	<?php echo hjsc_render_tool(); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_* inside the renderer. ?>
</main>

<?php wp_footer(); ?>
</body>
</html>

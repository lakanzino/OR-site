<?php
/**
 * Child theme footer (0.9.0): professional navy footer with the custom
 * logo, two compact organized link columns and a slim legal bar.
 *
 * The retired editorial-policy page is intentionally never linked.
 *
 * @package Lax_Theme_Child
 * @since   0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hj_footer_sections = array(
	array(
		'url'   => hj_archive_url( 'patient_guide' ),
		'label' => 'راهنمای بیمار',
	),
	array(
		'url'   => hj_archive_url( 'or_environment' ),
		'label' => 'اتاق عمل، تیم و تجهیزات',
	),
	array(
		'url'   => hj_archive_url( 'surgery' ),
		'label' => 'جراحی‌ها',
	),
	array(
		'url'   => hj_archive_url( 'glossary' ),
		'label' => 'واژه‌نامه',
	),
);

$hj_footer_pages = array(
	array(
		'url'   => hj_page_url( 'about' ),
		'label' => 'درباره ما',
	),
	array(
		'url'   => hj_page_url( 'contact' ),
		'label' => 'تماس با ما',
	),
	array(
		'url'   => hj_page_url( 'faq' ),
		'label' => 'پرسش‌های متداول',
	),
	array(
		'url'   => hj_page_url( 'privacy' ),
		'label' => 'حریم خصوصی',
	),
	array(
		'url'   => hj_page_url( 'terms' ),
		'label' => 'شرایط استفاده',
	),
);
?>

<footer id="colophon" class="site-footer hj-footer">
	<div class="container">

		<div class="hj-footer__top">

			<div class="hj-footer__brand">
				<?php if ( has_custom_logo() ) : ?>
					<?php
					// WP core already wraps the logo in a home link; add the
					// footer modifier class for the white-chip styling.
					echo str_replace( 'class="custom-logo"', 'class="custom-logo hj-footer__logo-img"', get_custom_logo() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP core markup.
					?>
				<?php else : ?>
					<a class="hj-footer__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
				<p class="hj-footer__tagline">مرجع آموزشی بیمارمحور برای شناخت اتاق عمل، تیم درمان و مسیر جراحی؛ از تصمیم تا بازگشت به زندگی.</p>
				<a class="hj-footer__email" href="mailto:mail@hamyarjarahi.ir">mail@hamyarjarahi.ir</a>
			</div>

			<nav class="hj-footer__col" aria-label="بخش‌های مجله">
				<h3 class="hj-footer__heading">بخش‌های مجله</h3>
				<ul>
					<?php foreach ( $hj_footer_sections as $hj_footer_item ) : ?>
						<li><a href="<?php echo esc_url( $hj_footer_item['url'] ); ?>"><?php echo esc_html( $hj_footer_item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<nav class="hj-footer__col" aria-label="دسترسی سریع">
				<h3 class="hj-footer__heading">دسترسی سریع</h3>
				<ul>
					<?php foreach ( $hj_footer_pages as $hj_footer_item ) : ?>
						<li><a href="<?php echo esc_url( $hj_footer_item['url'] ); ?>"><?php echo esc_html( $hj_footer_item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

		</div>

		<aside class="hj-emergency" aria-label="شماره‌های اورژانسی">
			<p><strong>وضعیت اورژانسی؟</strong> این مجله آموزش عمومی است و جایگزین پزشک نیست. در علائم خطر مانند تنگی نفس، درد قفسهٔ سینه، خون‌ریزی شدید یا افت هوشیاری، منتظر خواندن مقاله نمانید.</p>
			<ul class="hj-emergency__numbers">
				<li><a href="tel:115">اورژانس ۱۱۵</a></li>
			</ul>
		</aside>

		<div class="hj-footer__bottom">
			<p class="hj-footer__copyright">
				&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				— همه‌ی حقوق محفوظ است.
			</p>
			<p class="hj-footer__legal">محتوای همیار جراحی آموزش عمومی برای بیمار و همراه است و رابطهٔ پزشک و بیمار ایجاد نمی‌کند؛ تشخیص، نسخه و تصمیم درمانی فقط با تیم درمانی است که پروندهٔ شما را دیده است. برای دیدن شرایط کامل، <a href="<?php echo esc_url( hj_page_url( 'terms' ) ); ?>">شرایط استفاده</a> و <a href="<?php echo esc_url( hj_page_url( 'privacy' ) ); ?>">حریم خصوصی</a> را بخوانید.</p>
		</div>

	</div>
</footer><!-- #colophon -->

<?php wp_footer(); ?>

</body>
</html>

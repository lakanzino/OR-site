<?php
/**
 * Single doctor profile (child override): «technology» profile widget,
 * star-only rating via the native engine (inc/doctor-ratings.php — zero
 * plugins), and embed/edit tools.
 *
 * Rules from the owner's brief (docs/doctor-ratings/brief.fa.md, §1) and the
 * 2026-11-01 profile decisions (docs/doctor-ratings/profile-rollout.fa.md,
 * docs/doctor-ratings/profile-widgets.fa.md):
 * - stars only: the review form hides every text field (title, content,
 *   name, email); the public review list is never rendered;
 * - the average appears only once the doctor has at least 5 votes (the
 *   widget enforces the same threshold);
 * - profile fields (degree, medical-council code, location, short bio) come
 *   from post meta and render as the full technology-style card; the classic
 *   light header stays as a fallback if inc/doctor-profile.php is missing;
 * - no legal text on this page — the «قوانین» link lives in the footer menu;
 * - the doctor (author) and the site admin see an edit link + ready-made
 *   advertising embed codes (strip/full widgets) for other websites;
 * - if inc/doctor-ratings.php is missing, the rating block disappears
 *   entirely (function_exists guard — nothing raw can print).
 *
 * Page styles are scoped to .hj-doctor-page; widget styles live in
 * assets/css/doctor-widgets.css (enqueued by inc/doctor-profile.php).
 *
 * @package Lax_Theme_Child
 */

defined( 'ABSPATH' ) || exit;
get_header();

$hj_has_widgets = function_exists( 'hj_doctor_widget' );
?>
<style>
.hj-doctor-page{--ink:#0f172a;--line:#e2e8f0;--muted:#64748b;--navy:#0a2f6b;color:var(--ink);line-height:1.9}
.hj-doctor-page .hj-dp-box{border:1px solid var(--line);border-radius:12px;background:#fff;padding:1rem 1.2rem;margin:0 0 1rem}
.hj-doctor-page .hj-dp-head{display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start}
.hj-doctor-page .hj-dp-photo{width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid var(--line)}
.hj-doctor-page h1{font-size:1.6rem;margin:0 0 .35rem;color:var(--navy)}
.hj-doctor-page .hj-dp-muted{color:var(--muted);font-size:.9rem;margin:.5rem 0 0}
.hj-doctor-page .hj-dp-stars{max-width:560px}
.hj-doctor-page .hj-dp-note{font-size:.85rem;color:var(--muted)}
.hj-doctor-page .hjw{margin:0 0 1rem}
.hj-doctor-page .hj-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.hj-doctor-page .hj-dp-tools label{display:block;font-weight:700;font-size:.85rem;margin:.6rem 0 .2rem}
.hj-doctor-page .hj-dp-tools .hj-embed-row{display:flex;gap:.5rem;align-items:flex-start}
.hj-doctor-page .hj-dp-tools textarea{flex:1;min-height:56px;font-family:ui-monospace,Consolas,monospace;font-size:.75rem;direction:ltr;text-align:left;color:#334155;background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:.5rem}
.hj-doctor-page .hj-dp-tools .hj-btn{border:0;border-radius:8px;background:#334155;color:#fff;font:inherit;font-size:.8rem;font-weight:700;padding:.35rem .9rem;cursor:pointer}
.hj-doctor-page .hj-dp-tools .hj-btn.primary{background:var(--navy);padding:.5rem 1.2rem}
@media (max-width:480px){.hj-doctor-page .hj-dp-photo{width:72px;height:72px}}
</style>
<div class="container orsite-article-wrap hj-doctor-page" style="padding-top:1.75rem;padding-bottom:3rem"><main id="primary">
<?php while ( have_posts() ) : the_post(); ?>
<nav class="orsite-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a><?php $hj_archive = get_post_type_archive_link( 'doctor' ); if ( $hj_archive ) : ?> / <a href="<?php echo esc_url( $hj_archive ) ); ?>">پزشکان</a><?php endif; ?></nav>
<article <?php post_class( 'hj-doctor-entry' ); ?>>
	<h1 class="hj-sr-only"><?php the_title(); ?> — پروفایل پزشک</h1>

	<?php if ( $hj_has_widgets ) : ?>
		<?php echo hj_doctor_widget( (int) get_the_ID(), 'full' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped inside the builder. ?>
		<p class="hj-dp-note">امتیازها نظر شخصی کاربران‌اند و توصیهٔ پزشکی نیستند؛ این صفحه جایگزین مشورت پزشکی نیست.</p>
	<?php else : ?>
		<header class="hj-dp-box hj-dp-head">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'medium', array( 'class' => 'hj-dp-photo' ) ); ?>
			<?php endif; ?>
			<div>
				<h1><?php the_title(); ?></h1>
				<p class="hj-dp-note">امتیازها نظر شخصی کاربران‌اند و توصیهٔ پزشکی نیستند؛ این صفحه جایگزین مشورت پزشکی نیست.</p>
			</div>
		</header>
	<?php endif; ?>

	<?php if ( '' !== trim( (string) get_the_content() ) ) : ?>
		<div class="hj-dp-box entry-content">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<?php if ( function_exists( 'hj_doctor_vote_form' ) ) : ?>
		<section class="hj-dp-box hj-dp-stars" aria-label="امتیازدهی به پزشک">
			<?php echo hj_doctor_vote_messages(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<?php echo hj_doctor_summary_html( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<?php echo hj_doctor_vote_form( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
		</section>
	<?php endif; ?>

	<?php
	// Edit + advertising embed tools: only for the site admin or the doctor.
	if ( $hj_has_widgets && function_exists( 'hj_doctor_user_can_edit' ) && hj_doctor_user_can_edit( (int) get_the_ID() ) ) :
		?>
		<section class="hj-dp-box hj-dp-tools" aria-label="ابزارهای پروفایل">
			<strong>ابزارهای پروفایل (فقط شما و مدیر سایت این بخش را می‌بینید)</strong>
			<p>
				<a class="hj-btn primary" href="<?php echo esc_url( function_exists( 'hj_doctor_dashboard_url' ) ? add_query_arg( 'doctor', (int) get_the_ID(), hj_doctor_dashboard_url() ) : admin_url( 'post.php?post=' . (int) get_the_ID() . '&action=edit' ) ); ?>">ویرایش پروفایل</a>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a class="hj-btn" href="<?php echo esc_url( (string) get_edit_post_link() ); ?>">ویرایش در پیشخوان</a>
				<?php endif; ?>
			</p>
			<label>کد تبلیغاتی ویجت نواری (برای سایت‌های دیگر)</label>
			<div class="hj-embed-row">
				<textarea readonly onclick="this.select()"><?php echo esc_textarea( hj_doctor_embed_code( (int) get_the_ID(), 'strip' ) ); ?></textarea>
				<button type="button" class="hj-btn hj-copy">کپی</button>
			</div>
			<label>کد تبلیغاتی ویجت بزرگ</label>
			<div class="hj-embed-row">
				<textarea readonly onclick="this.select()"><?php echo esc_textarea( hj_doctor_embed_code( (int) get_the_ID(), 'full' ) ); ?></textarea>
				<button type="button" class="hj-btn hj-copy">کپی</button>
			</div>
			<script>
			document.querySelectorAll('.hj-doctor-page .hj-copy').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var ta = btn.parentElement.querySelector('textarea');
					ta.select();
					if (navigator.clipboard) { navigator.clipboard.writeText(ta.value); }
					try { document.execCommand('copy'); } catch (e) {}
					btn.textContent = 'کپی شد ✓';
					setTimeout(function () { btn.textContent = 'کپی'; }, 1600);
				});
			});
			</script>
		</section>
	<?php endif; ?>
</article>
<?php endwhile; ?></main></div><?php get_footer();

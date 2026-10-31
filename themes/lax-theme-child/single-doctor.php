<?php
/**
 * Single doctor profile (child override): star-only rating via Site Reviews.
 *
 * Rules from the owner's brief (docs/doctor-ratings/brief.fa.md, §1):
 * - stars only: the review form hides every text field (title, content,
 *   name, email); the public review list is never rendered;
 * - the average (summary) appears only once the doctor has at least 5 votes;
 * - no legal text on this page — the «قوانین» link lives in the footer menu;
 * - if Site Reviews is not active, the rating block disappears entirely
 *   (an unregistered shortcode must never print itself raw).
 *
 * Styles are scoped to .hj-doctor-page so the rest of the site is unaffected.
 *
 * @package Lax_Theme_Child
 */

defined( 'ABSPATH' ) || exit;
get_header();

$hj_doctor_min_votes = 5;
?>
<style>
.hj-doctor-page{--ink:#0f172a;--line:#e2e8f0;--muted:#64748b;--navy:#0a2f6b;color:var(--ink);line-height:1.9}
.hj-doctor-page .hj-dp-box{border:1px solid var(--line);border-radius:12px;background:#fff;padding:1rem 1.2rem;margin:0 0 1rem}
.hj-doctor-page .hj-dp-head{display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start}
.hj-doctor-page .hj-dp-photo{width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid var(--line)}
.hj-doctor-page h1{font-size:1.6rem;margin:0 0 .35rem;color:var(--navy)}
.hj-doctor-page .hj-dp-muted{color:var(--muted);font-size:.9rem;margin:.5rem 0 0}
.hj-doctor-page .hj-dp-stars .glrs-summary,.hj-doctor-page .glrs-form{max-width:520px}
.hj-doctor-page .hj-dp-note{font-size:.85rem;color:var(--muted)}
@media (max-width:480px){.hj-doctor-page .hj-dp-photo{width:72px;height:72px}}
</style>
<div class="container orsite-article-wrap hj-doctor-page" style="padding-top:1.75rem;padding-bottom:3rem"><main id="primary">
<?php while ( have_posts() ) : the_post(); ?>
<nav class="orsite-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a><?php $hj_archive = get_post_type_archive_link( 'doctor' ); if ( $hj_archive ) : ?> / <a href="<?php echo esc_url( $hj_archive ); ?>">پزشکان</a><?php endif; ?></nav>
<article <?php post_class( 'hj-doctor-entry' ); ?>>
	<header class="hj-dp-box hj-dp-head">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium', array( 'class' => 'hj-dp-photo' ) ); ?>
		<?php endif; ?>
		<div>
			<h1><?php the_title(); ?></h1>
			<p class="hj-dp-note">امتیازها نظر شخصی کاربران‌اند و توصیهٔ پزشکی نیستند؛ این صفحه جایگزین مشورت پزشکی نیست.</p>
		</div>
	</header>

	<div class="hj-dp-box entry-content">
		<?php the_content(); ?>
	</div>

	<?php if ( shortcode_exists( 'site_reviews_form' ) ) : ?>
		<section class="hj-dp-box hj-dp-stars" aria-label="امتیازدهی به پزشک">
			<?php
			$hj_rating = function_exists( 'glsr_get_rating' ) ? glsr_get_rating( get_the_ID() ) : null;
			$hj_votes  = is_object( $hj_rating ) && isset( $hj_rating->count ) ? (int) $hj_rating->count : 0;
			if ( $hj_votes >= $hj_doctor_min_votes ) :
				echo do_shortcode( '[site_reviews_summary assigned_posts="' . (int) get_the_ID() . '"]' );
			else :
				?>
				<p class="hj-dp-muted">میانگین امتیاز پس از رسیدن به حداقل <?php echo (int) $hj_doctor_min_votes; ?> رأی نمایش داده می‌شود.</p>
			<?php endif; ?>
			<?php echo do_shortcode( '[site_reviews_form assigned_posts="' . (int) get_the_ID() . '" hide="title,content,name,email"]' ); ?>
		</section>
	<?php endif; ?>
</article>
<?php endwhile; ?></main></div><?php get_footer();

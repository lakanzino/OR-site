<?php
/** Individual disease: content is editable in the WordPress editor. */
get_header();
?>
<div class="container orsite-article-wrap" style="padding-top:1.75rem;padding-bottom:3rem"><main id="primary">
<?php while ( have_posts() ) : the_post(); ?>
<nav class="orsite-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a> / <a href="<?php echo esc_url( get_post_type_archive_link( 'disease' ) ); ?>">دانشنامه بیماری‌ها</a></nav>
<article <?php post_class( 'hj-disease-entry' ); ?>><h1><?php the_title(); ?></h1><div class="hj-disease-notice">این مطلب آموزشی است و جایگزین تشخیص یا درمان پزشکی نیست. برای بررسی علائم به پزشک مراجعه کنید.</div><div class="entry-content"><?php the_content(); ?></div></article>
<?php endwhile; ?></main></div><?php get_footer();

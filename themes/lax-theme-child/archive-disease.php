<?php
/** Disease directory archive. Only published entries are displayed publicly. */
get_header();
$base = get_post_type_archive_link( 'disease' );
$groups = get_terms( array( 'taxonomy' => 'disease_group', 'hide_empty' => true ) );
$search = isset( $_GET['disease_search'] ) ? sanitize_text_field( wp_unslash( $_GET['disease_search'] ) ) : '';
$selected = isset( $_GET['disease_group'] ) ? sanitize_title( wp_unslash( $_GET['disease_group'] ) ) : '';
?>
<div class="container orsite-archive-wrap"><main id="primary" class="orsite-archive">
<header class="orsite-archive__header"><h1 class="orsite-archive__title">دانشنامه بیماری‌ها</h1><p>آشنایی با بیماری‌ها، علائم، علت، تاریخچه و درمان‌های رایج با منابع پزشکی. این مطالب جایگزین مراجعه به پزشک نیستند.</p></header>
<form method="get" action="<?php echo esc_url( $base ); ?>" class="hj-disease-search"><label for="disease-search">جست‌وجوی نام بیماری</label><input id="disease-search" type="search" name="disease_search" value="<?php echo esc_attr( $search ); ?>" placeholder="نام فارسی یا انگلیسی بیماری"><button type="submit">جست‌وجو</button></form>
<?php if ( ! is_wp_error( $groups ) && $groups ) : ?><nav class="hj-disease-groups" aria-label="دسته‌های بیماری"><a href="<?php echo esc_url( $base ); ?>">همه</a><?php foreach ( $groups as $group ) : ?><a href="<?php echo esc_url( add_query_arg( 'disease_group', $group->slug, $base ) ); ?>" <?php if ( $selected === $group->slug ) { echo 'aria-current="page"'; } ?>><?php echo esc_html( $group->name ); ?></a><?php endforeach; ?></nav><?php endif; ?>
<?php if ( have_posts() ) : ?><div class="hj-disease-grid"><?php while ( have_posts() ) : the_post(); ?><article class="hj-disease-card"><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php $english = get_post_meta( get_the_ID(), '_hj_disease_en', true ); if ( $english ) : ?><p dir="ltr"><?php echo esc_html( $english ); ?></p><?php endif; ?><a href="<?php the_permalink(); ?>">مشاهده معرفی بیماری ←</a></article><?php endwhile; ?></div><?php the_posts_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) ); ?><?php else : ?><div class="orsite-empty"><p>هنوز بیماری منتشرشده‌ای در این بخش وجود ندارد یا نتیجه‌ای پیدا نشد.</p></div><?php endif; ?>
</main></div><?php get_footer();

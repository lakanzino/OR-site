<?php
/** Public doctor profile. All descriptive content remains editable in wp-admin. */
get_header();
?>
<main id="primary" class="container orsite-article-wrap hj-public-doctor" tabindex="-1">
<?php while ( have_posts() ) : the_post();
	$id = get_the_ID();
	$get = function ( $key ) use ( $id ) {
		$value = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( $key, $id ) : get_post_meta( $id, $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	};
	?>
	<article <?php post_class( 'orsite-article' ); ?>>
		<header class="orsite-title-box hj-doctor-heading">
			<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium', array( 'class' => 'hj-doctor-photo' ) ); } ?>
			<div><?php the_title( '<h1 class="orsite-article__title">', '</h1>' ); ?>
			<?php if ( $get( 'name_en' ) ) : ?><p dir="ltr"><?php echo esc_html( $get( 'name_en' ) ); ?></p><?php endif; ?>
			<?php if ( $get( 'specialty_en' ) ) : ?><p dir="ltr"><?php echo esc_html( $get( 'specialty_en' ) ); ?></p><?php endif; ?>
			<?php if ( $get( 'specialty_fa' ) ) : ?><p><?php echo esc_html( $get( 'specialty_fa' ) ); ?></p><?php endif; ?>
			<?php if ( $get( 'experience_years' ) ) : ?><p>سابقه کاری: <?php echo esc_html( $get( 'experience_years' ) ); ?> سال</p><?php endif; ?>
			<?php if ( $get( 'medical_license' ) ) : ?><p>شماره نظام پزشکی: <?php echo esc_html( $get( 'medical_license' ) ); ?></p><?php endif; ?></div>
		</header>
		<?php foreach ( array( 'bio_fa' => 'درباره پزشک', 'bio_en' => 'About', 'education' => 'تحصیلات و دوره‌ها', 'surgeries_performed' => 'جراحی‌های انجام‌شده' ) as $key => $label ) :
			$value = $get( $key ); if ( '' === $value ) { continue; } ?>
			<section class="hj-doctor-section"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( $value ) ); ?></p></section>
		<?php endforeach; ?>
		<?php if ( $get( 'phone_clinic' ) || $get( 'address_clinic' ) || $get( 'address_hospital' ) || $get( 'map_link' ) ) : ?>
		<section class="hj-doctor-section"><h2>اطلاعات تماس و نشانی</h2>
			<?php if ( $get( 'phone_clinic' ) ) : ?><p>تلفن مطب: <span dir="ltr"><?php echo esc_html( $get( 'phone_clinic' ) ); ?></span></p><?php endif; ?>
			<?php if ( $get( 'address_clinic' ) ) : ?><p>مطب: <?php echo esc_html( $get( 'address_clinic' ) ); ?></p><?php endif; ?>
			<?php if ( $get( 'address_hospital' ) ) : ?><p>بیمارستان: <?php echo esc_html( $get( 'address_hospital' ) ); ?></p><?php endif; ?>
			<?php if ( $get( 'map_link' ) ) : ?><p><a rel="noopener noreferrer" href="<?php echo esc_url( $get( 'map_link' ), array( 'https' ) ); ?>">مشاهده نقشه</a></p><?php endif; ?>
		</section><?php endif; ?>
		<?php
		$hours = function_exists( 'hj_doctors_field' ) ? hj_doctors_field( 'working_hours', $id ) : get_post_meta( $id, 'working_hours', true );
		$days = apply_filters( 'hj_dash_days', array( 'saturday' => 'شنبه', 'sunday' => 'یکشنبه', 'monday' => 'دوشنبه', 'tuesday' => 'سه‌شنبه', 'wednesday' => 'چهارشنبه', 'thursday' => 'پنجشنبه', 'friday' => 'جمعه' ) );
		if ( is_array( $hours ) && $hours ) : ?>
		<section class="hj-doctor-section"><h2>ساعات کاری</h2><ul>
		<?php foreach ( $hours as $row ) :
			if ( ! is_array( $row ) || empty( $row['day_of_week'] ) || ! isset( $days[ $row['day_of_week'] ] ) ) { continue; }
			$time = ! empty( $row['is_closed'] ) ? 'تعطیل' : substr( (string) ( $row['start_time'] ?? '' ), 0, 5 ) . ' تا ' . substr( (string) ( $row['end_time'] ?? '' ), 0, 5 ); ?>
			<li><?php echo esc_html( $days[ $row['day_of_week'] ] . ': ' . $time ); ?></li>
		<?php endforeach; ?></ul></section><?php endif; ?>
		<div class="entry-content hj-doctor-section"><?php the_content(); ?></div>
		<?php if ( current_user_can( 'edit_post', $id ) ) : ?><p><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">ویرایش این پروفایل</a></p><?php endif; ?>
	</article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>

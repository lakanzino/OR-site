<?php
/**
 * Doctor cards grid (partial, child 0.32.0). Expects $hj_list_doctors (WP_Post[]).
 *
 * @package Lax_Theme_Child
 */

defined( 'ABSPATH' ) || exit;
$hj_list = isset( $hj_list_doctors ) && is_array( $hj_list_doctors ) ? $hj_list_doctors : array();
if ( ! $hj_list ) {
	echo '<p class="hjd-intro">هنوز پزشکی در این بخش ثبت نشده است.</p>';
	return;
}
$hj_keys = function_exists( 'hj_doctor_meta_keys' ) ? hj_doctor_meta_keys() : array();
?>
<div class="hjd-grid">
<?php foreach ( $hj_list as $hj_doc ) :
	$hj_id    = (int) $hj_doc->ID;
	$hj_terms = get_the_terms( $hj_id, 'specialty' );
	$hj_term  = ( is_array( $hj_terms ) && $hj_terms ) ? $hj_terms[0] : null;
	$hj_code  = $hj_keys ? (string) get_post_meta( $hj_id, $hj_keys['code'], true ) : '';
	$hj_loc   = $hj_keys ? (string) get_post_meta( $hj_id, $hj_keys['location'], true ) : '';
	$hj_r     = function_exists( 'hj_doctor_rating' ) ? hj_doctor_rating( $hj_id ) : array( 'count' => 0, 'average' => 0.0 );
	?>
	<a class="hjd-card<?php echo $hj_term ? ' sp-' . esc_attr( sanitize_html_class( $hj_term->slug ) ) : ''; ?>" href="<?php echo esc_url( (string) get_permalink( $hj_id ) ); ?>">
		<span class="hjd-name"><?php echo esc_html( $hj_doc->post_title ); ?></span>
		<?php if ( $hj_term ) : ?><span class="hjw-chip"><?php echo esc_html( $hj_term->name ); ?></span><?php endif; ?>
		<span class="hjd-stars">
			<?php
			if ( $hj_r['count'] >= 5 && function_exists( 'hj_doctor_stars_html' ) ) {
				echo hj_doctor_stars_html( (float) $hj_r['average'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				echo '<span class="hjd-count">' . esc_html( number_format_i18n( $hj_r['average'], 1 ) ) . ' از ' . esc_html( number_format_i18n( $hj_r['count'] ) ) . ' رأی</span>';
			} else {
				echo '<span class="hjd-count">امتیاز پس از ۵ رأی</span>';
			}
			?>
		</span>
		<?php if ( $hj_code ) : ?><span class="hjd-code">نظام پزشکی <?php echo esc_html( $hj_code ); ?></span><?php endif; ?>
		<?php if ( $hj_loc ) : ?><span class="hjd-loc"><?php echo esc_html( $hj_loc ); ?></span><?php endif; ?>
	</a>
<?php endforeach; ?>
</div>

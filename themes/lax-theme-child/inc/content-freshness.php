<?php
/**
 * Content freshness watch (child 0.16.0).
 *
 * Medical guidance ages. This file keeps a daily cron that lists published
 * `surgery` / `patient_guide` articles that have not been touched for a while
 * (270 days by default) and shows them to the editorial team in the admin.
 *
 * It never edits an article by itself and never claims a medical review:
 * the only action an editor can take is "I read it and it is still correct",
 * which bumps the post modification date — the same honest date the article
 * page and the JSON-LD already show.
 *
 * @package Lax_Theme_Child
 * @since   0.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cron hook name. */
const HJ_FRESHNESS_HOOK = 'hj_freshness_run';

/**
 * Number of days after which an article is considered due for a re-read.
 *
 * @return int
 */
function hj_freshness_days(): int {
	return (int) apply_filters( 'hj_freshness_days', 270 );
}

/**
 * Schedule the daily check once.
 *
 * @return void
 */
function hj_freshness_schedule(): void {
	if ( ! wp_next_scheduled( HJ_FRESHNESS_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', HJ_FRESHNESS_HOOK );
	}
}
add_action( 'init', 'hj_freshness_schedule' );

/**
 * Collect articles whose last change is older than the threshold.
 *
 * @return array<int,array{id:int,title:string,date:string}>
 */
function hj_freshness_collect(): array {
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( hj_freshness_days() * DAY_IN_SECONDS ) );
	$types  = function_exists( 'hj_gate_post_types' ) ? hj_gate_post_types() : array( 'surgery', 'patient_guide' );

	$posts = get_posts(
		array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $cutoff,
				),
			),
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);

	$out = array();
	foreach ( $posts as $id ) {
		$out[] = array(
			'id'    => (int) $id,
			'title' => get_the_title( $id ),
			'date'  => (string) get_post_modified_time( 'Y-m-d', false, $id ),
		);
	}

	return $out;
}

/**
 * Cron callback: refresh the stored list.
 *
 * @return void
 */
function hj_freshness_run(): void {
	update_option( 'hj_stale_posts', hj_freshness_collect(), false );
}
add_action( HJ_FRESHNESS_HOOK, 'hj_freshness_run' );

/**
 * Stored list, refreshed on demand when empty.
 *
 * @return array<int,array{id:int,title:string,date:string}>
 */
function hj_freshness_stale(): array {
	$stored = get_option( 'hj_stale_posts', null );
	if ( ! is_array( $stored ) ) {
		$stored = hj_freshness_collect();
		update_option( 'hj_stale_posts', $stored, false );
	}
	return $stored;
}

/**
 * Notice listing the articles that are due for a re-read.
 *
 * @return void
 */
function hj_freshness_notice(): void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	if ( get_user_meta( get_current_user_id(), 'hj_freshness_dismissed', true ) ) {
		return;
	}

	$stale = hj_freshness_stale();
	if ( ! $stale ) {
		return;
	}

	$shown = array_slice( $stale, 0, 6 );
	?>
	<div class="notice notice-info">
		<p><strong>بازبینی محتوا:</strong>
			<?php
			printf(
				/* translators: %d: number of stale articles. */
				esc_html__( '%d مقاله بیش از ۹ ماه تغییر نکرده است. یک بار بخوانید و اگر درست است، «ثبت بازبینی» را بزنید.', 'lax-theme-child' ),
				count( $stale )
			);
			?>
		</p>
		<ul style="margin:0 0 8px 0;list-style:disc;padding-inline-start:20px">
			<?php foreach ( $shown as $item ) : ?>
				<li>
					<a href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
					<span style="color:#646970">
						<?php echo esc_html( ' — آخرین تغییر: ' . $item['date'] ); ?>
					</span>
					<a class="button button-small" style="margin-inline-start:8px"
						href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hj_mark_reviewed&post=' . $item['id'] ), 'hj_mark_reviewed_' . $item['id'] ) ); ?>">
						<?php esc_html_e( 'ثبت بازبینی', 'lax-theme-child' ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hj_dismiss_freshness' ), 'hj_dismiss_freshness' ) ); ?>">
				<?php esc_html_e( 'این فهرست را تا ۳۰ روز نشان نده', 'lax-theme-child' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'hj_freshness_notice' );

/**
 * Record that an editor re-read the article.
 *
 * @return void
 */
function hj_freshness_mark_reviewed(): void {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id || ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'hj_mark_reviewed_' . $post_id )
		|| ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'درخواست معتبر نیست.', 'lax-theme-child' ), 403 );
	}

	// A no-op update bumps post_modified: the honest "last read" date.
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => get_post_status( $post_id ),
		)
	);
	delete_option( 'hj_stale_posts' );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_hj_mark_reviewed', 'hj_freshness_mark_reviewed' );

/**
 * Hide the freshness list for this user for 30 days.
 *
 * @return void
 */
function hj_freshness_dismiss(): void {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'hj_dismiss_freshness' ) ) {
		wp_die( esc_html__( 'درخواست معتبر نیست.', 'lax-theme-child' ), 403 );
	}

	$until = time() + ( 30 * DAY_IN_SECONDS );
	update_user_meta( get_current_user_id(), 'hj_freshness_dismissed', $until );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_hj_dismiss_freshness', 'hj_freshness_dismiss' );

/**
 * Re-arm the notice for everyone at the start of a new cycle.
 *
 * @return void
 */
function hj_freshness_rearm(): void {
	$until = (int) get_user_meta( get_current_user_id(), 'hj_freshness_dismissed', true );
	if ( $until && $until < time() ) {
		delete_user_meta( get_current_user_id(), 'hj_freshness_dismissed' );
	}
}
add_action( 'admin_init', 'hj_freshness_rearm' );

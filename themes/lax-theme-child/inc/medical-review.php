<?php
/**
 * Medical reviewer: who checked the article, and when.
 *
 * Health content that tells a patient what to do with a medicine or an
 * anaesthetic needs a named human behind it. This file stores that name,
 * shows it under the article, and feeds the `reviewedBy` / `lastReviewed`
 * fields of the JSON-LD that `inc/seo-basics.php` already prints.
 *
 * Design rule: the theme never invents a reviewer. If no name is stored, the
 * article says so plainly instead of implying a review that did not happen.
 *
 * @package Lax_Theme_Child
 * @since   0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys used for the reviewer record.
 */
const HJ_REVIEW_META_NAME = '_hj_medical_reviewer';
const HJ_REVIEW_META_JOB  = '_hj_medical_reviewer_job';
const HJ_REVIEW_META_DATE = '_hj_reviewed_at';

/**
 * Post types whose articles can require a medical reviewer.
 *
 * @return string[]
 */
function hj_review_post_types(): array {
	return (array) apply_filters( 'hj_review_post_types', array( 'patient_guide', 'surgery', 'or_environment' ) );
}

/**
 * Words that make an article medical advice rather than general information.
 *
 * @return string[]
 */
function hj_review_ymyl_keywords(): array {
	return (array) apply_filters(
		'hj_review_ymyl_keywords',
		array(
			'دارو',
			'بیهوشی',
			'بی‌حسی',
			'بی حسی',
			'ناشتا',
			'دوز',
			'تداخل',
			'آرام‌بخش',
			'آرام بخش',
			'مخدر',
			'مسکن',
			'آنتی‌بیوتیک',
			'آنتی بیوتیک',
			'تزریق',
			'عوارض',
			'واکنش',
			'آلرژی',
			'حساسیت',
			'خون‌ریزی',
			'خونریزی',
			'عفونت',
			'بی‌هوش',
			'بی هوش',
		)
	);
}

/**
 * Stored reviewer record.
 *
 * @param int $post_id Post ID.
 * @return array{name:string,job:string,date:string,date_fa:string}|null
 */
function hj_review_get( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return null;
	}

	$name = trim( (string) get_post_meta( $post_id, HJ_REVIEW_META_NAME, true ) );
	if ( '' === $name || false !== strpos( $name, 'در انتظار' ) ) {
		return null;
	}

	$job  = trim( (string) get_post_meta( $post_id, HJ_REVIEW_META_JOB, true ) );
	$date = trim( (string) get_post_meta( $post_id, HJ_REVIEW_META_DATE, true ) );

	$date_fa = '';
	if ( '' !== $date ) {
		$timestamp = strtotime( $date );
		if ( $timestamp ) {
			$date_fa = function_exists( 'hj_format_jalali_timestamp' )
				? hj_format_jalali_timestamp( $timestamp, 'long' )
				: $date;
		}
	}

	return array(
		'name'    => $name,
		'job'     => $job,
		'date'    => $date,
		'date_fa' => $date_fa,
	);
}

/**
 * Does this article give medical advice that needs a specialist reviewer?
 *
 * An explicit `_hj_ymyl` meta value always wins, so an editor can mark an
 * article either way without touching code.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function hj_review_is_ymyl( $post_id ) {
	$post_id = (int) $post_id;

	$explicit = get_post_meta( $post_id, '_hj_ymyl', true );
	if ( '1' === (string) $explicit ) {
		return true;
	}
	if ( '0' === (string) $explicit ) {
		return false;
	}

	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, hj_review_post_types(), true ) ) {
		return false;
	}

	$haystack = get_the_title( $post ) . ' ' . (string) get_post_meta( $post_id, '_hj_focus_keyword', true );
	$haystack .= ' ' . (string) $post->post_excerpt;

	foreach ( hj_review_ymyl_keywords() as $keyword ) {
		if ( '' === $keyword ) {
			continue;
		}
		$found = function_exists( 'mb_strpos' )
			? mb_strpos( $haystack, $keyword )
			: strpos( $haystack, $keyword );
		if ( false !== $found ) {
			return true;
		}
	}

	return false;
}

/**
 * The reviewer line shown under the title.
 *
 * @param int $post_id Post ID.
 * @return string Safe HTML, empty when nothing truthful can be said.
 */
function hj_review_byline( $post_id ) {
	$post_id = (int) $post_id;
	$review  = hj_review_get( $post_id );

	if ( $review ) {
		$parts = array( esc_html( $review['name'] ) );
		if ( '' !== $review['job'] ) {
			$parts[] = esc_html( $review['job'] );
		}
		$label = '<strong>' . esc_html__( 'بازبینی پزشکی:', 'lax-theme-child' ) . '</strong> ' . implode( ' — ', $parts );
		if ( '' !== $review['date_fa'] ) {
			$label .= ' <span class="hj-review__date">' . esc_html( $review['date_fa'] ) . '</span>';
		}
		return '<p class="hj-review hj-review--done">' . $label . '</p>';
	}

	if ( hj_review_is_ymyl( $post_id ) ) {
		return '<p class="hj-review hj-review--pending">'
			. esc_html__( 'این مقاله بازبینی پزشکی تخصصی نشده است؛ پیش از هر تصمیم درمانی با پزشک خود مشورت کنید.', 'lax-theme-child' )
			. '</p>';
	}

	return '';
}

/**
 * Print the byline inside an article header.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function hj_review_print_byline( $post_id ) {
	$html = hj_review_byline( $post_id );
	if ( '' !== $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	}
}

/**
 * Count how many YMYL articles still lack a reviewer.
 *
 * @return int
 */
function hj_review_pending_count(): int {
	$posts = get_posts(
		array(
			'post_type'      => hj_review_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'no_found_rows'  => true,
		)
	);

	$pending = 0;
	foreach ( $posts as $post ) {
		if ( hj_review_is_ymyl( $post->ID ) && ! hj_review_get( $post->ID ) ) {
			++$pending;
		}
	}

	return $pending;
}

/**
 * Register the reviewer screen.
 *
 * @return void
 */
function hj_review_admin_menu() {
	add_management_page(
		__( 'بازبین پزشکی', 'lax-theme-child' ),
		__( 'بازبین پزشکی', 'lax-theme-child' ),
		'manage_options',
		'hj-medical-review',
		'hj_review_admin_page'
	);
}
add_action( 'admin_menu', 'hj_review_admin_menu' );

/**
 * Handle the two bulk actions.
 *
 * @return string Notice message, empty when nothing happened.
 */
function hj_review_handle_post() {
	if ( ! isset( $_POST['hj_review_action'] ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$nonce = isset( $_POST['hj_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_review_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'hj_review_bulk' ) ) {
		return __( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.', 'lax-theme-child' );
	}

	$action = sanitize_key( (string) $_POST['hj_review_action'] );
	$ids    = isset( $_POST['hj_review_ids'] ) ? array_map( 'intval', (array) $_POST['hj_review_ids'] ) : array();
	$ids    = array_values( array_filter( $ids ) );

	if ( empty( $ids ) ) {
		return __( 'هیچ مقاله‌ای انتخاب نشده بود.', 'lax-theme-child' );
	}

	if ( 'remove' === $action ) {
		foreach ( $ids as $id ) {
			delete_post_meta( $id, HJ_REVIEW_META_NAME );
			delete_post_meta( $id, HJ_REVIEW_META_JOB );
			delete_post_meta( $id, HJ_REVIEW_META_DATE );
		}
		return sprintf(
			/* translators: %d: number of articles */
			__( 'بازبینی از %d مقاله برداشته شد.', 'lax-theme-child' ),
			count( $ids )
		);
	}

	// Stamping a reviewer is a factual claim, so it needs an explicit tick.
	if ( empty( $_POST['hj_review_confirm'] ) ) {
		return __( 'برای ثبت، باید تأیید کنید که بازبین این مقاله‌ها را واقعاً خوانده است.', 'lax-theme-child' );
	}

	$name = isset( $_POST['hj_review_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_review_name'] ) ) : '';
	$job  = isset( $_POST['hj_review_job'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_review_job'] ) ) : '';
	$date = isset( $_POST['hj_review_date'] ) ? sanitize_text_field( wp_unslash( $_POST['hj_review_date'] ) ) : '';

	if ( '' === $name ) {
		return __( 'نام بازبین لازم است.', 'lax-theme-child' );
	}

	$date = hj_review_normalize_date( $date );

	foreach ( $ids as $id ) {
		update_post_meta( $id, HJ_REVIEW_META_NAME, $name );
		update_post_meta( $id, HJ_REVIEW_META_JOB, $job );
		update_post_meta( $id, HJ_REVIEW_META_DATE, $date );
	}

	update_option( 'hj_review_last_name', $name, false );
	update_option( 'hj_review_last_job', $job, false );

	return sprintf(
		/* translators: 1: number of articles, 2: reviewer name */
		__( 'بازبینی %2$s برای %1$d مقاله ثبت شد.', 'lax-theme-child' ),
		count( $ids ),
		$name
	);
}

/**
 * Jalali → Gregorian, the exact inverse of hj_gregorian_to_jalali().
 *
 * @param int $jy Jalali year.
 * @param int $jm Jalali month 1..12.
 * @param int $jd Jalali day 1..31.
 * @return array{0:int,1:int,2:int} [ $gy, $gm, $gd ]
 */
function hj_review_jalali_to_gregorian( $jy, $jm, $jd ) {
	$jy  += 1595;
	$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd
		+ ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );

	$gy    = 400 * intdiv( $days, 146097 );
	$days %= 146097;

	if ( $days > 36524 ) {
		--$days;
		$gy   += 100 * intdiv( $days, 36524 );
		$days %= 36524;
		if ( $days >= 365 ) {
			++$days;
		}
	}

	$gy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;

	if ( $days > 365 ) {
		$gy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}

	$gd    = $days + 1;
	$leap  = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
	$month = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

	$gm = 0;
	while ( $gm < 13 && $gd > $month[ $gm ] ) {
		$gd -= $month[ $gm ];
		++$gm;
	}

	return array( (int) $gy, (int) $gm, (int) $gd );
}

/**
 * Accept a Jalali or Gregorian date and store it as Y-m-d.
 *
 * @param string $value Raw input.
 * @return string
 */
function hj_review_normalize_date( $value ) {
	$value = trim( function_exists( 'hj_en_digits' ) ? hj_en_digits( (string) $value ) : (string) $value );

	if ( '' === $value ) {
		return (string) current_time( 'Y-m-d' );
	}

	if ( preg_match( '/^(1[34]\d{2})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $value, $m ) ) {
		list( $gy, $gm, $gd ) = hj_review_jalali_to_gregorian( (int) $m[1], (int) $m[2], (int) $m[3] );
		return sprintf( '%04d-%02d-%02d', $gy, $gm, $gd );
	}

	if ( preg_match( '/^(20\d{2})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $value, $m ) ) {
		return sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
	}

	return (string) current_time( 'Y-m-d' );
}

/**
 * Render the reviewer screen.
 *
 * @return void
 */
function hj_review_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = hj_review_handle_post();
	$filter = isset( $_GET['hj_review_filter'] ) ? sanitize_key( wp_unslash( $_GET['hj_review_filter'] ) ) : 'pending';
	$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$per    = 40;

	$all = get_posts(
		array(
			'post_type'      => hj_review_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 400,
			'no_found_rows'  => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$ymyl   = array();
	$others = array();
	foreach ( $all as $post ) {
		if ( hj_review_is_ymyl( $post->ID ) ) {
			$ymyl[] = $post;
		} else {
			$others[] = $post;
		}
	}

	$pending = array();
	foreach ( $ymyl as $hj_review_post ) {
		if ( null === hj_review_get( $hj_review_post->ID ) ) {
			$pending[] = $hj_review_post;
		}
	}

	if ( 'pending' === $filter ) {
		$list = $pending;
	} elseif ( 'ymyl' === $filter ) {
		$list = $ymyl;
	} elseif ( 'others' === $filter ) {
		$list = $others;
	} else {
		$list = array_merge( $ymyl, $others );
	}

	$total  = count( $list );
	$pages  = max( 1, (int) ceil( $total / $per ) );
	$paged  = min( $paged, $pages );
	$slice  = array_slice( $list, ( $paged - 1 ) * $per, $per );
	$saved  = (string) get_option( 'hj_review_last_name', '' );
	$savedj = (string) get_option( 'hj_review_last_job', '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'بازبین پزشکی', 'lax-theme-child' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<p style="max-width:52em;line-height:2">
			<?php esc_html_e( 'مقاله‌هایی که درباره‌ی دارو، بیهوشی، ناشتایی یا عوارض حرف می‌زنند باید نام یک پزشک واقعی زیرشان باشد. این صفحه همان نام را ثبت می‌کند و در همان لحظه زیر مقاله و در داده‌ی ساختاریافته‌ی گوگل می‌نشیند. اگر نامی ثبت نشود، مقاله صادقانه می‌گوید بازبینی نشده — هیچ اسمی از خودش نمی‌سازد.', 'lax-theme-child' ); ?>
		</p>

		<p style="max-width:52em">
			<strong><?php echo esc_html( sprintf( /* translators: 1: pending, 2: ymyl */ __( '%1$d مقاله از %2$d مقاله‌ی حساس، بازبین ندارد.', 'lax-theme-child' ), count( $pending ), count( $ymyl ) ) ); ?></strong>
		</p>

		<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px;max-width:52em;margin:18px 0">
			<?php wp_nonce_field( 'hj_review_bulk', 'hj_review_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hj_review_name"><?php esc_html_e( 'نام بازبین', 'lax-theme-child' ); ?></label></th>
					<td><input type="text" id="hj_review_name" name="hj_review_name" class="regular-text" value="<?php echo esc_attr( $saved ); ?>" placeholder="<?php esc_attr_e( 'دکتر نام و نام خانوادگی', 'lax-theme-child' ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="hj_review_job"><?php esc_html_e( 'تخصص', 'lax-theme-child' ); ?></label></th>
					<td><input type="text" id="hj_review_job" name="hj_review_job" class="regular-text" value="<?php echo esc_attr( $savedj ); ?>" placeholder="<?php esc_attr_e( 'متخصص بیهوشی و مراقبت‌های ویژه', 'lax-theme-child' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="hj_review_date"><?php esc_html_e( 'تاریخ بازبینی', 'lax-theme-child' ); ?></label></th>
					<td>
						<input type="text" id="hj_review_date" name="hj_review_date" class="regular-text" value="<?php echo esc_attr( function_exists( 'hj_format_jalali_timestamp' ) ? hj_format_jalali_timestamp( (int) current_time( 'timestamp' ), 'numeric' ) : current_time( 'Y-m-d' ) ); ?>">
						<p class="description"><?php esc_html_e( 'شمسی یا میلادی، هر دو پذیرفته می‌شود. شمسی ذخیره و نمایش داده می‌شود.', 'lax-theme-child' ); ?></p>
					</td>
				</tr>
			</table>

			<p>
				<label>
					<input type="checkbox" name="hj_review_confirm" value="1">
					<?php esc_html_e( 'تأیید می‌کنم که این بازبین مقاله‌های انتخاب‌شده را واقعاً خوانده و تأیید کرده است.', 'lax-theme-child' ); ?>
				</label>
			</p>

			<ul class="subsubsub" style="margin:10px 0 0">
				<?php
				$tabs = array(
					'pending' => sprintf( /* translators: %d: count */ __( 'بدون بازبین (%d)', 'lax-theme-child' ), count( $pending ) ),
					'ymyl'    => sprintf( /* translators: %d: count */ __( 'حساس (%d)', 'lax-theme-child' ), count( $ymyl ) ),
					'all'     => sprintf( /* translators: %d: count */ __( 'همه (%d)', 'lax-theme-child' ), count( $ymyl ) + count( $others ) ),
					'others'  => sprintf( /* translators: %d: count */ __( 'عمومی (%d)', 'lax-theme-child' ), count( $others ) ),
				);
				$i = 0;
				foreach ( $tabs as $key => $label ) :
					++$i;
					?>
					<li>
						<a class="<?php echo $filter === $key ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'hj-medical-review', 'hj_review_filter' => $key ), admin_url( 'tools.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php echo ( $i < count( $tabs ) ) ? ' |' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<table class="widefat striped" style="margin-top:10px">
				<thead>
					<tr>
						<td style="width:2em"><input type="checkbox" onclick="var c=this.checked;document.querySelectorAll('.hj-review-cb').forEach(function(b){b.checked=c;})"></td>
						<th><?php esc_html_e( 'مقاله', 'lax-theme-child' ); ?></th>
						<th style="width:8em"><?php esc_html_e( 'بخش', 'lax-theme-child' ); ?></th>
						<th style="width:10em"><?php esc_html_e( 'بازبین فعلی', 'lax-theme-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $slice ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'چیزی در این فهرست نیست.', 'lax-theme-child' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $slice as $post ) : ?>
						<?php
						$record   = hj_review_get( $post->ID );
						$type_obj = get_post_type_object( $post->post_type );
						$is_ymyl  = hj_review_is_ymyl( $post->ID );
						?>
						<tr>
							<td><input type="checkbox" class="hj-review-cb" name="hj_review_ids[]" value="<?php echo (int) $post->ID; ?>"></td>
							<td>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong></a>
								<?php if ( $is_ymyl ) : ?>
									<span style="color:#b32d2e;font-size:11px">● <?php esc_html_e( 'حساس', 'lax-theme-child' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $type_obj ? $type_obj->labels->singular_name : $post->post_type ); ?></td>
							<td>
								<?php if ( $record ) : ?>
									<?php echo esc_html( $record['name'] ); ?>
									<?php if ( '' !== $record['date_fa'] ) : ?>
										<br><small><?php echo esc_html( $record['date_fa'] ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span style="color:#b32d2e">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<p style="margin-top:14px">
				<button type="submit" name="hj_review_action" value="apply" class="button button-primary"><?php esc_html_e( 'ثبت بازبینی برای انتخاب‌شده‌ها', 'lax-theme-child' ); ?></button>
				<button type="submit" name="hj_review_action" value="remove" class="button" onclick="return confirm('<?php echo esc_js( __( 'بازبینی از مقاله‌های انتخاب‌شده برداشته شود؟', 'lax-theme-child' ) ); ?>')"><?php esc_html_e( 'حذف بازبینی', 'lax-theme-child' ); ?></button>
			</p>
		</form>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '‹',
							'next_text' => '›',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'قاعده‌ای که این صفحه دنبال می‌کند', 'lax-theme-child' ); ?></h2>
		<ul style="max-width:52em;line-height:2;list-style:disc;padding-inline-start:22px">
			<li><?php esc_html_e( 'تجربه‌ی تکنسین اتاق عمل برای «اتاق عمل چه شکلی است» کافی است و بازبین لازم ندارد.', 'lax-theme-child' ); ?></li>
			<li><?php esc_html_e( 'برای دارو، بیهوشی، ناشتایی و عوارض، نام پزشک الزامی است.', 'lax-theme-child' ); ?></li>
			<li><?php esc_html_e( 'تا وقتی نامی ثبت نشده، مقاله خودش می‌گوید بازبینی نشده است. این جمله را با زور پاک نکنید؛ با ثبت بازبین واقعی پاک می‌شود.', 'lax-theme-child' ); ?></li>
		</ul>
	</div>
	<?php
}

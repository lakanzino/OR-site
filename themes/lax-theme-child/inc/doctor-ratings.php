<?php
/**
 * Native doctor star-rating engine — zero plugins (owner decision 2026-11-01:
 * «اگر بشود افزونه‌ها را حذف کرد و همه اطلاعات در قالب نگهداری شود، بهتر است»).
 *
 * Replaces the Site Reviews plugin entirely:
 *  - votes live in a dedicated table `{prefix}hj_doctor_votes`;
 *  - stars only (no text, no name, no email), guests may vote;
 *  - every public vote is stored `pending` — manual approval by the admin
 *    (معیارهای رد/حذف محتوامحور، runbook §5);
 *  - one vote per doctor per user/IP (IP stored as a salted SHA-256 hash,
 *    raw IPs are never kept);
 *  - the average shows only from 5 approved votes (same threshold everywhere:
 *    page, full widget, strip widget);
 *  - bot guards without external services: honeypot field + minimum form age
 *    + per-IP daily cap;
 *  - admin page (پیشخوان → همیار جراحی → آرای پزشکان): approve / archive with
 *    reason / restore / delete, edit each vote's stars and date, add votes,
 *    import CSV, and generate the initial seed votes per
 *    docs/doctor-ratings/seed-votes.fa.md (random 22–61 votes, 80% five
 *    stars, the rest random two/three stars, dates spread over ~180 days);
 *  - archiving is reversible and keeps the reason (بایگانی به‌جای حذف خاموش).
 *
 * @package Lax_Theme_Child
 * @since   0.27.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'HJ_DOCTOR_VOTES_DB_VERSION' ) ) {
	define( 'HJ_DOCTOR_VOTES_DB_VERSION', '1.0' );
}
if ( ! defined( 'HJ_DOCTOR_MIN_VOTES' ) ) {
	define( 'HJ_DOCTOR_MIN_VOTES', 5 );
}
if ( ! defined( 'HJ_DOCTOR_DAILY_IP_CAP' ) ) {
	define( 'HJ_DOCTOR_DAILY_IP_CAP', 20 );
}
if ( ! defined( 'HJ_DOCTOR_MIN_FORM_SECONDS' ) ) {
	define( 'HJ_DOCTOR_MIN_FORM_SECONDS', 3 );
}

/* -------------------------------------------------------------------------
 * 1. Table
 * ---------------------------------------------------------------------- */

/**
 * Votes table name.
 *
 * @return string
 */
function hj_doctor_votes_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'hj_doctor_votes';
}

/**
 * Create/upgrade the votes table (dbDelta, gated by a version option).
 *
 * @return void
 */
function hj_doctor_votes_maybe_install(): void {
	if ( get_option( 'hj_doctor_votes_db_version' ) === HJ_DOCTOR_VOTES_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = hj_doctor_votes_table();
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		post_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		rating TINYINT(1) UNSIGNED NOT NULL,
		ip_hash CHAR(64) NOT NULL DEFAULT '',
		status VARCHAR(12) NOT NULL DEFAULT 'pending',
		source VARCHAR(10) NOT NULL DEFAULT 'public',
		reason TEXT NULL,
		voted_at DATETIME NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY post_status (post_id,status),
		KEY ip_hash (ip_hash),
		KEY voted_at (voted_at)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'hj_doctor_votes_db_version', HJ_DOCTOR_VOTES_DB_VERSION );
}
add_action( 'init', 'hj_doctor_votes_maybe_install' );

/* -------------------------------------------------------------------------
 * 2. Read helpers
 * ---------------------------------------------------------------------- */

/**
 * Salted hash of the voter IP (privacy: raw IPs are never stored).
 *
 * @return string
 */
function hj_doctor_ip_hash(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '';
}

/**
 * Approved-vote summary for one doctor.
 *
 * @param int $post_id Doctor post ID.
 * @return array{count:int,average:float}
 */
function hj_doctor_rating( int $post_id ): array {
	global $wpdb;
	$table = hj_doctor_votes_table();
	$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT COUNT(*) AS c, AVG(rating) AS a FROM {$table} WHERE post_id = %d AND status = 'approved'", $post_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
	$count = $row ? (int) $row->c : 0;
	return array(
		'count'   => $count,
		'average' => $count ? round( (float) $row->a, 2 ) : 0.0,
	);
}

/**
 * Has this visitor already voted for this doctor (pending or approved)?
 *
 * @param int $post_id Doctor post ID.
 * @return bool
 */
function hj_doctor_has_voted( int $post_id ): bool {
	if ( isset( $_COOKIE[ 'hj_voted_' . $post_id ] ) ) {
		return true;
	}
	global $wpdb;
	$table   = hj_doctor_votes_table();
	$user_id = get_current_user_id();
	$ip      = hj_doctor_ip_hash();
	$row     = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE post_id = %d AND status IN ('pending','approved') AND ( ( user_id > 0 AND user_id = %d ) OR ( ip_hash <> '' AND ip_hash = %s ) ) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$post_id,
			$user_id,
			$ip
		)
	);
	return (bool) $row;
}

/**
 * HTML star glyphs for an average (used by the on-page summary and widgets).
 *
 * @param float $average 0–5.
 * @return string
 */
function hj_doctor_stars_html( float $average ): string {
	$pct = max( 0, min( 100, (int) round( $average / 5 * 100 ) ) );
	return '<span class="hjw-stars" role="img" aria-label="میانگین ' . esc_attr( number_format_i18n( $average, 1 ) ) . '">'
		. '<span class="hjw-stars-bg" aria-hidden="true">★★★★★</span>'
		. '<span class="hjw-stars-fill" aria-hidden="true" style="width:' . (int) $pct . '%">★★★★★</span>'
		. '</span>';
}

/**
 * On-page summary block (average + count, only from HJ_DOCTOR_MIN_VOTES).
 *
 * @param int $post_id Doctor post ID.
 * @return string
 */
function hj_doctor_summary_html( int $post_id ): string {
	$r = hj_doctor_rating( $post_id );
	if ( $r['count'] < HJ_DOCTOR_MIN_VOTES ) {
		return '<p class="hj-dp-muted">میانگین امتیاز پس از رسیدن به حداقل ' . esc_html( number_format_i18n( HJ_DOCTOR_MIN_VOTES ) ) . ' رأی نمایش داده می‌شود.</p>';
	}
	return '<div class="hj-rate-summary">' . hj_doctor_stars_html( $r['average'] )
		. '<span class="hj-rate-summary-text">میانگین ' . esc_html( number_format_i18n( $r['average'], 1 ) )
		. ' از ' . esc_html( number_format_i18n( $r['count'] ) ) . ' رأی</span></div>';
}

/* -------------------------------------------------------------------------
 * 3. Public vote form (stars only) + submission handler
 * ---------------------------------------------------------------------- */

/**
 * Render the star-only vote form (guests allowed; lands in the approval queue).
 *
 * @param int $post_id Doctor post ID.
 * @return string
 */
function hj_doctor_vote_form( int $post_id ): string {
	if ( hj_doctor_has_voted( $post_id ) ) {
		return '<p class="hj-dp-muted">شما قبلاً به این پزشک رأی داده‌اید — یک رأی برای هر کاربر/IP. از اعتماد شما سپاسگزاریم.</p>';
	}
	$html  = '<form class="hj-rate" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$html .= '<input type="hidden" name="action" value="hj_doctor_vote">';
	$html .= '<input type="hidden" name="post_id" value="' . (int) $post_id . '">';
	$html .= '<input type="hidden" name="hj_form_ts" value="' . (int) time() . '">';
	// Honeypot: invisible to humans, tasty for bots. Never mention it publicly.
	$html .= '<span class="hj-hp" aria-hidden="true"><label for="hj-website-' . (int) $post_id . '">وب‌سایت</label>'
		. '<input type="text" id="hj-website-' . (int) $post_id . '" name="hj_website" value="" tabindex="-1" autocomplete="off"></span>';
	$html .= '<fieldset class="hj-rate-stars"><legend>به این پزشک چند ستاره می‌دهید؟</legend><div class="hj-rate-row">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$html .= '<input type="radio" id="hj-star-' . (int) $post_id . '-' . $i . '" name="rating" value="' . $i . '" required>'
			. '<label for="hj-star-' . (int) $post_id . '-' . $i . '" title="' . $i . ' از ۵">★</label>';
	}
	$html .= '</div></fieldset>';
	$html .= '<button type="submit" class="hj-rate-submit">ثبت رأی</button>';
	$html .= '<p class="hj-rate-note">رأی شما پس از بازبینی منتشر می‌شود؛ امتیازها نظر شخصی کاربران‌اند.</p>';
	$html .= '<script>document.querySelectorAll(".hj-rate").forEach(function(f){var row=f.querySelector(".hj-rate-row");'
		. 'function paint(){var v=f.querySelector("input[name=rating]:checked");'
		. 'row.querySelectorAll("label").forEach(function(l){l.classList.toggle("on",v&&+l.previousElementSibling.value<=+v.value);});}'
		. 'f.querySelectorAll("input[name=rating]").forEach(function(r){r.addEventListener("change",paint);});paint();});</script>';
	$html .= '</form>';
	return $html;
}

/**
 * Status message after the vote redirect.
 *
 * @return string
 */
function hj_doctor_vote_messages(): string {
	if ( ! isset( $_GET['vote'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '';
	}
	$code = sanitize_key( wp_unslash( $_GET['vote'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$msgs = array(
		'ok'  => 'رأی شما ثبت شد و پس از بازبینی مدیر در میانگین اعمال می‌شود. سپاس!',
		'dup' => 'شما قبلاً به این پزشک رأی داده‌اید — یک رأی برای هر کاربر/IP.',
		'cap' => 'سقف رأی‌های امروز این دستگاه پر شده است؛ فردا دوباره تلاش کنید.',
		'err' => 'رأی ثبت نشد؛ لطفاً دوباره تلاش کنید.',
	);
	if ( ! isset( $msgs[ $code ] ) ) {
		return '';
	}
	return '<p class="hj-vote-msg' . ( 'ok' === $code ? '' : ' err' ) . '">' . esc_html( $msgs[ $code ] ) . '</p>';
}

/**
 * Handle a public vote (logged-in or guest).
 *
 * @return void
 */
function hj_doctor_handle_vote(): void {
	$back = wp_get_referer();
	if ( ! $back ) {
		$back = home_url( '/' );
	}
	$fail = function ( string $code ) use ( $back ): void {
		wp_safe_redirect( add_query_arg( 'vote', $code, remove_query_arg( 'vote', $back ) ) );
		exit;
	};

	// Honeypot: pretend success, store nothing.
	if ( ! empty( $_POST['hj_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'vote', 'ok', remove_query_arg( 'vote', $back ) ) );
		exit;
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
	$ts      = isset( $_POST['hj_form_ts'] ) ? absint( $_POST['hj_form_ts'] ) : 0;
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post || 'doctor' !== $post->post_type || 'publish' !== $post->post_status ) {
		$fail( 'err' );
	}
	if ( $rating < 1 || $rating > 5 ) {
		$fail( 'err' );
	}
	// Time gate: faster than a human can pick stars, or replayed a day later.
	$age = time() - $ts;
	if ( $age < HJ_DOCTOR_MIN_FORM_SECONDS || $age > DAY_IN_SECONDS ) {
		$fail( 'err' );
	}
	if ( hj_doctor_has_voted( $post_id ) ) {
		$fail( 'dup' );
	}

	// Per-IP daily cap across the whole site.
	global $wpdb;
	$table = hj_doctor_votes_table();
	$ip    = hj_doctor_ip_hash();
	if ( $ip ) {
		$today = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at >= %s", $ip, gmdate( 'Y-m-d 00:00:00' ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( $today >= HJ_DOCTOR_DAILY_IP_CAP ) {
			$fail( 'cap' );
		}
	}

	$now = current_time( 'mysql', true );
	$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'post_id'    => $post_id,
			'user_id'    => get_current_user_id(),
			'rating'     => $rating,
			'ip_hash'    => $ip,
			'status'     => 'pending',
			'source'     => 'public',
			'voted_at'   => $now,
			'created_at' => $now,
		),
		array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( ! $ok ) {
		$fail( 'err' );
	}

	// Remember on this device (UX only — the real dedupe is user/IP above).
	if ( ! headers_sent() ) {
		setcookie( 'hj_voted_' . $post_id, '1', time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}
	wp_safe_redirect( add_query_arg( 'vote', 'ok', remove_query_arg( 'vote', (string) get_permalink( $post_id ) ) ) );
	exit;
}
add_action( 'admin_post_hj_doctor_vote', 'hj_doctor_handle_vote' );
add_action( 'admin_post_nopriv_hj_doctor_vote', 'hj_doctor_handle_vote' );

/* -------------------------------------------------------------------------
 * 4. Admin: menu, row operations, bulk tools (add / import / seed)
 * ---------------------------------------------------------------------- */

/**
 * Register the votes admin page (under «همیار جراحی» when present).
 *
 * @return void
 */
function hj_doctor_votes_admin_menu(): void {
	$title = 'آرای پزشکان';
	$cap   = 'manage_options';
	$slug  = 'hj-doctor-votes';
	if ( isset( $GLOBALS['admin_page_hooks']['hj-content-dashboard'] ) ) {
		add_submenu_page( 'hj-content-dashboard', 'مدیریت آرای پزشکان — همیار جراحی', $title, $cap, $slug, 'hj_doctor_votes_render_page' );
	} else {
		add_menu_page( 'مدیریت آرای پزشکان — همیار جراحی', $title, $cap, $slug, 'hj_doctor_votes_render_page', 'dashicons-star-filled', 4 );
	}
}
add_action( 'admin_menu', 'hj_doctor_votes_admin_menu', 99 );

/**
 * Admin page URL.
 *
 * @return string
 */
function hj_doctor_votes_page_url(): string {
	return admin_url( 'admin.php?page=hj-doctor-votes' );
}

/**
 * Shared guard for admin mutations.
 *
 * @param string $nonce_action Nonce action name.
 * @return void
 */
function hj_doctor_votes_admin_guard( string $nonce_action ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازهٔ دسترسی ندارید.' );
	}
	check_admin_referer( $nonce_action );
}

/**
 * Row operations from the votes list: edit stars/date/note, approve, archive
 * with reason, restore, or hard delete. One hidden form per row.
 *
 * @return void
 */
function hj_doctor_votes_row_action(): void {
	hj_doctor_votes_admin_guard( 'hj_votes_row' );
	global $wpdb;
	$table = hj_doctor_votes_table();
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$back  = wp_get_referer() ? wp_get_referer() : hj_doctor_votes_page_url();
	if ( ! $id ) {
		wp_safe_redirect( $back );
		exit;
	}

	if ( isset( $_POST['del'] ) ) {
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	} elseif ( isset( $_POST['approve'] ) || isset( $_POST['archive'] ) || isset( $_POST['restore'] ) ) {
		$status = isset( $_POST['archive'] ) ? 'archived' : 'approved';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$wpdb->update( $table, array( 'status' => $status, 'reason' => $reason ?: null ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	} else {
		$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$date   = isset( $_POST['voted_at'] ) ? strtotime( sanitize_text_field( wp_unslash( $_POST['voted_at'] ) ) ) : false;
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$data   = array();
		$fmt    = array();
		if ( $rating >= 1 && $rating <= 5 ) {
			$data['rating'] = $rating;
			$fmt[]          = '%d';
		}
		if ( $date ) {
			$data['voted_at'] = gmdate( 'Y-m-d H:i:s', $date );
			$fmt[]            = '%s';
		}
		if ( '' !== $reason ) {
			$data['reason'] = $reason;
			$fmt[]          = '%s';
		}
		if ( $data ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), $fmt, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	wp_safe_redirect( add_query_arg( 'hj-notice', rawurlencode( 'row-' . $id ), $back ) );
	exit;
}
add_action( 'admin_post_hj_votes_row', 'hj_doctor_votes_row_action' );

/**
 * Bulk tools: add one vote, import CSV, generate initial seed votes.
 *
 * @return void
 */
function hj_doctor_votes_tools_action(): void {
	$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
	if ( ! in_array( $tool, array( 'add', 'import', 'seed' ), true ) ) {
		return;
	}
	hj_doctor_votes_admin_guard( 'hj_votes_' . $tool );

	global $wpdb;
	$table  = hj_doctor_votes_table();
	$back   = wp_get_referer() ? wp_get_referer() : hj_doctor_votes_page_url();
	$now    = current_time( 'mysql', true );
	$notice = 'bad';

	if ( 'add' === $tool ) {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$status  = ( isset( $_POST['status'] ) && 'pending' === $_POST['status'] ) ? 'pending' : 'approved';
		$raw     = isset( $_POST['voted_at'] ) ? sanitize_text_field( wp_unslash( $_POST['voted_at'] ) ) : '';
		$date    = '' !== $raw ? strtotime( $raw ) : false;
		if ( $post_id && 'doctor' === get_post_type( $post_id ) && $rating >= 1 && $rating <= 5 ) {
			$ok = $wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'post_id'    => $post_id,
				'rating'     => $rating,
				'status'     => $status,
				'source'     => 'admin',
				'voted_at'   => $date ? gmdate( 'Y-m-d H:i:s', $date ) : $now,
				'created_at' => $now,
			), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
			$notice = $ok ? 'added' : 'bad';
		}
	} elseif ( 'import' === $tool ) {
		// CSV rows: post_id,rating,date,status (optional header; the same
		// layout tools/seed-votes/generate_csv.py produces).
		$csv   = isset( $_POST['csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csv'] ) ) : '';
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) $csv ) ) );
		$added = 0;
		foreach ( $lines as $line ) {
			$cols = str_getcsv( $line );
			if ( count( $cols ) < 2 || 'post_id' === strtolower( trim( (string) $cols[0] ) ) ) {
				continue;
			}
			$post_id = absint( $cols[0] );
			$rating  = absint( $cols[1] );
			$raw     = isset( $cols[2] ) ? trim( (string) $cols[2] ) : '';
			$date    = '' !== $raw ? strtotime( $raw ) : false;
			$st      = isset( $cols[3] ) ? trim( (string) $cols[3] ) : '';
			$status  = in_array( $st, array( 'pending', 'approved', 'archived' ), true ) ? $st : 'approved';
			if ( $post_id && 'doctor' === get_post_type( $post_id ) && $rating >= 1 && $rating <= 5 ) {
				$ok = $wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					'post_id'    => $post_id,
					'rating'     => $rating,
					'status'     => $status,
					'source'     => 'import',
					'voted_at'   => $date ? gmdate( 'Y-m-d H:i:s', $date ) : $now,
					'created_at' => $now,
				), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
				$added += $ok ? 1 : 0;
			}
		}
		$notice = 'imported-' . $added;
	} else { // seed — per docs/doctor-ratings/seed-votes.fa.md.
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'one';
		$min   = isset( $_POST['min'] ) ? max( 1, absint( $_POST['min'] ) ) : 22;
		$max   = isset( $_POST['max'] ) ? max( $min, absint( $_POST['max'] ) ) : 61;
		$pct5  = isset( $_POST['pct5'] ) ? min( 100, max( 0, absint( $_POST['pct5'] ) ) ) : 80;
		$days  = isset( $_POST['days'] ) ? max( 1, absint( $_POST['days'] ) ) : 180;
		if ( 'one' === $scope ) {
			$ids = array( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );
		} else {
			$all = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
			$ids = array();
			foreach ( (array) $all as $candidate ) {
				$candidate = absint( $candidate );
				$has       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $candidate ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $candidate && 0 === $has ) {
					$ids[] = $candidate;
				}
			}
		}
		$total = 0;
		foreach ( $ids as $id ) {
			if ( ! $id || 'doctor' !== get_post_type( $id ) ) {
				continue;
			}
			$n    = wp_rand( $min, $max );
			$five = (int) round( $n * $pct5 / 100 );
			for ( $i = 0; $i < $n; $i++ ) {
				$rating = $i < $five ? 5 : ( wp_rand( 0, 1 ) ? 3 : 2 );
				$when   = time() - wp_rand( 0, $days * DAY_IN_SECONDS );
				$ok     = $wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					'post_id'    => $id,
					'rating'     => $rating,
					'status'     => 'approved',
					'source'     => 'seed',
					'voted_at'   => gmdate( 'Y-m-d H:i:s', $when ),
					'created_at' => $now,
				), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
				$total += $ok ? 1 : 0;
			}
		}
		$notice = 'seeded-' . $total;
	}

	wp_safe_redirect( add_query_arg( array( 'tab' => $tool, 'hj-notice' => rawurlencode( $notice ) ), $back ) );
	exit;
}
add_action( 'admin_post_hj_votes_tools', 'hj_doctor_votes_tools_action' );

/**
 * Render the admin page (list + tools).
 *
 * @return void
 */
function hj_doctor_votes_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازهٔ دسترسی ندارید.' );
	}
	global $wpdb;
	$table    = hj_doctor_votes_table();
	$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'votes'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$f_status = isset( $_GET['f_status'] ) ? sanitize_key( wp_unslash( $_GET['f_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$f_post   = isset( $_GET['f_post'] ) ? absint( $_GET['f_post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per      = 50;
	$doctors  = get_posts( array( 'post_type' => 'doctor', 'post_status' => array( 'publish', 'pending', 'draft' ), 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC' ) );
	$notice   = isset( $_GET['hj-notice'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['hj-notice'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tabs     = array( 'votes' => 'فهرست و رسیدگی', 'add' => 'افزودن رأی', 'import' => 'ورود CSV', 'seed' => 'آرای اولیه (رندوم)' );
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'votes';
	}
	?>
	<div class="wrap" dir="rtl">
		<h1>آرای پزشکان — همیار جراحی</h1>
		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p>انجام شد: <?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'hj-doctor-votes', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php if ( 'votes' === $tab ) :
			$where  = array( '1=1' );
			$params = array();
			if ( in_array( $f_status, array( 'pending', 'approved', 'archived' ), true ) ) {
				$where[]  = 'status = %s';
				$params[] = $f_status;
			}
			if ( $f_post ) {
				$where[]  = 'post_id = %d';
				$params[] = $f_post;
			}
			$where_sql = implode( ' AND ', $where );
			$offset    = ( $paged - 1 ) * $per;
			if ( $params ) {
				$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per, $offset ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			} else {
				$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$counts = $wpdb->get_row( "SELECT SUM(status='pending') AS p, SUM(status='approved') AS a, SUM(status='archived') AS r, COUNT(*) AS t FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows   = is_array( $rows ) ? $rows : array();
			?>
			<form id="hj-filter-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-top:14px">
				<input type="hidden" name="page" value="hj-doctor-votes">
				<input type="hidden" name="tab" value="votes">
				وضعیت
				<select name="f_status">
					<option value="">همه</option>
					<?php foreach ( array( 'pending' => 'در انتظار', 'approved' => 'تأییدشده', 'archived' => 'بایگانی' ) as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f_status, $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				پزشک
				<select name="f_post">
					<option value="0">همه</option>
					<?php foreach ( $doctors as $d ) : ?>
						<option value="<?php echo (int) $d->ID; ?>" <?php selected( $f_post, (int) $d->ID ); ?>><?php echo esc_html( $d->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button">فیلتر</button>
				<span style="margin-inline-start:12px">
					در انتظار: <strong><?php echo (int) ( $counts->p ?? 0 ); ?></strong> ·
					تأییدشده: <strong><?php echo (int) ( $counts->a ?? 0 ); ?></strong> ·
					بایگانی: <strong><?php echo (int) ( $counts->r ?? 0 ); ?></strong> ·
					کل: <strong><?php echo (int) ( $counts->t ?? 0 ); ?></strong>
				</span>
			</form>

			<table class="widefat striped" style="max-width:1100px;margin-top:10px">
				<thead><tr><th>#</th><th>پزشک</th><th>ستاره</th><th>تاریخ رأی</th><th>منبع</th><th>وضعیت</th><th>دلیل/یادداشت</th><th>عملیات</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : $rid = (int) $row->id; ?>
					<tr>
						<td><?php echo (int) $row->id; ?></td>
						<td><a href="<?php echo esc_url( (string) get_permalink( (int) $row->post_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( (int) $row->post_id ) ?: '(#' . (int) $row->post_id . ')' ); ?></a></td>
						<td><?php echo (int) $row->rating; ?> ★</td>
						<td dir="ltr"><code><?php echo esc_html( $row->voted_at ); ?></code></td>
						<td><?php echo esc_html( $row->source ); ?></td>
						<td><?php echo esc_html( $row->status ); ?></td>
						<td><?php echo esc_html( (string) $row->reason ); ?></td>
						<td style="white-space:nowrap">
							<button class="button button-small" form="hj-row-<?php echo $rid; ?>" name="save" value="1">ذخیرهٔ ویرایش</button>
							<?php if ( 'approved' !== $row->status ) : ?>
								<button class="button button-small button-primary" form="hj-row-<?php echo $rid; ?>" name="approve" value="1">تأیید</button>
							<?php endif; ?>
							<?php if ( 'archived' !== $row->status ) : ?>
								<button class="button button-small" form="hj-row-<?php echo $rid; ?>" name="archive" value="1">بایگانی</button>
							<?php else : ?>
								<button class="button button-small" form="hj-row-<?php echo $rid; ?>" name="restore" value="1">بازگردانی</button>
							<?php endif; ?>
							<button class="button button-small" style="color:#b32d2e" form="hj-row-<?php echo $rid; ?>" name="del" value="1" onclick="return confirm('حذف دائمی این رأی؟ مسیر مستند، بایگانی با دلیل است.');">حذف</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$pages = (int) ceil( $total / $per );
			if ( $pages > 1 ) :
				echo '<p>';
				for ( $i = 1; $i <= $pages; $i++ ) {
					printf(
						'<a class="button button-small" style="margin:2px" href="%s">%d</a> ',
						esc_url( add_query_arg( array( 'page' => 'hj-doctor-votes', 'tab' => 'votes', 'f_status' => $f_status, 'f_post' => $f_post, 'paged' => $i ), admin_url( 'admin.php' ) ) ),
						(int) $i
					);
				}
				echo '</p>';
			endif;

			// One hidden form per row (nested forms are invalid HTML); the
			// visible buttons above submit these via the `form` attribute.
			foreach ( $rows as $row ) :
				$rid = (int) $row->id;
				?>
				<form id="hj-row-<?php echo $rid; ?>" class="hj-row-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hj_votes_row">
					<input type="hidden" name="id" value="<?php echo $rid; ?>">
					<?php wp_nonce_field( 'hj_votes_row' ); ?>
					<label>ستاره
						<select name="rating">
							<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
								<option value="<?php echo $i; ?>" <?php selected( (int) $row->rating, $i ); ?>><?php echo $i; ?> ★</option>
							<?php endfor; ?>
						</select>
					</label>
					<label>تاریخ <input type="text" name="voted_at" value="<?php echo esc_attr( $row->voted_at ); ?>" size="18" dir="ltr"></label>
					<label>دلیل/یادداشت <input type="text" name="reason" value="<?php echo esc_attr( (string) $row->reason ); ?>" size="24" placeholder="دلیل بایگانی…"></label>
				</form>
			<?php endforeach; ?>

		<?php elseif ( 'add' === $tab ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
				<input type="hidden" name="action" value="hj_votes_tools">
				<input type="hidden" name="tool" value="add">
				<?php wp_nonce_field( 'hj_votes_add' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th>پزشک</th><td><select name="post_id"><?php foreach ( $doctors as $d ) : ?><option value="<?php echo (int) $d->ID; ?>"><?php echo esc_html( $d->post_title ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th>ستاره</th><td><select name="rating"><?php for ( $i = 5; $i >= 1; $i-- ) : ?><option value="<?php echo $i; ?>"><?php echo $i; ?> ★</option><?php endfor; ?></select></td></tr>
					<tr><th>تاریخ رأی</th><td><input type="text" name="voted_at" dir="ltr" placeholder="YYYY-MM-DD HH:MM:SS (خالی = اکنون)"></td></tr>
					<tr><th>وضعیت</th><td><select name="status"><option value="approved">تأییدشده</option><option value="pending">در انتظار</option></select></td></tr>
				</tbody></table>
				<p><button class="button button-primary">افزودن رأی</button></p>
			</form>

		<?php elseif ( 'import' === $tab ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
				<input type="hidden" name="action" value="hj_votes_tools">
				<input type="hidden" name="tool" value="import">
				<?php wp_nonce_field( 'hj_votes_import' ); ?>
				<p>هر خط: <code dir="ltr">post_id,rating,date,status</code> — همان خروجی ابزار <code dir="ltr">tools/seed-votes/generate_csv.py</code>. ردیف سرستون نادیده گرفته می‌شود؛ وضعیت پیش‌فرض «تأییدشده» و منبع <code>import</code>.</p>
				<p><textarea name="csv" rows="12" style="width:100%;max-width:700px;font-family:monospace" dir="ltr" placeholder="101,5,2026-08-14 09:30:00,approved"></textarea></p>
				<p><button class="button button-primary">ورود رأی‌ها</button></p>
			</form>

		<?php else : // seed. ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
				<input type="hidden" name="action" value="hj_votes_tools">
				<input type="hidden" name="tool" value="seed">
				<?php wp_nonce_field( 'hj_votes_seed' ); ?>
				<p>مطابق سند <strong>آرای اولیه</strong>: تعداد رندوم بین حداقل و حداکثر؛ سهم پنج‌ستاره (پیش‌فرض ۸۰٪) و بقیه رندوم بین ۲ و ۳ ستاره؛ تاریخ‌ها پراکنده در بازهٔ روزهای گذشته. رأی‌ها «تأییدشده» و با منبع <code>seed</code> ثبت می‌شوند — در دفترچهٔ داخلی سردبیر هم یادداشت کنید.</p>
				<table class="form-table" role="presentation"><tbody>
					<tr><th>دامنه</th><td>
						<label><input type="radio" name="scope" value="one" checked> یک پزشک:
						<select name="post_id"><?php foreach ( $doctors as $d ) : ?><option value="<?php echo (int) $d->ID; ?>"><?php echo esc_html( $d->post_title ); ?></option><?php endforeach; ?></select></label><br>
						<label><input type="radio" name="scope" value="missing"> همهٔ پزشکان منتشرشدهٔ <strong>بدون رأی</strong></label>
					</td></tr>
					<tr><th>حداقل تعداد</th><td><input type="number" name="min" value="22" min="1" max="200" class="small-text"></td></tr>
					<tr><th>حداکثر تعداد</th><td><input type="number" name="max" value="61" min="1" max="500" class="small-text"></td></tr>
					<tr><th>سهم پنج‌ستاره (٪)</th><td><input type="number" name="pct5" value="80" min="0" max="100" class="small-text"></td></tr>
					<tr><th>پراکندگی تاریخ (روز)</th><td><input type="number" name="days" value="180" min="1" class="small-text"></td></tr>
				</tbody></table>
				<p><button class="button button-primary" onclick="return confirm('آرای اولیه ساخته و مستقیماً تأییدشده ثبت می‌شوند. ادامه؟');">ساخت آرای اولیه</button></p>
			</form>
		<?php endif; ?>
	</div>
	<style>
		.hj-row-form { display: none; }
	</style>
	<?php
}

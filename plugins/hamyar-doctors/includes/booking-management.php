<?php
/** Private booking lists and status management. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', function () {
	if ( hj_booking_enabled() && ! get_role( 'hj_booking_secretary' ) ) { add_role( 'hj_booking_secretary', 'منشی نوبت‌دهی', array( 'read' => true, 'hj_manage_appointments' => true ) ); }
}, 10 );
function hj_booking_management_labels() {
	return array( 'pending' => 'در انتظار تأیید', 'confirmed' => 'تأیید شده', 'completed' => 'انجام شده', 'no_show' => 'عدم حضور', 'cancelled' => 'لغو شده' );
}
function hj_booking_management_secretary_doctors() {
	global $wpdb;
	if ( ! hj_booking_ready() || ! is_user_logged_in() || ! current_user_can( 'hj_manage_appointments' ) ) { return array(); }
	$table = hj_booking_table( 'secretaries' );
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT doctor_id FROM `$table` WHERE user_id=%d ORDER BY doctor_id", get_current_user_id() ) );
	return $wpdb->last_error ? array() : array_map( 'intval', (array) $ids );
}
function hj_booking_manage_assignment( $doctor, $user_id, $add ) {
	if ( ! current_user_can( 'manage_options' ) || get_post_type( $doctor ) !== 'doctor' || ! get_userdata( $user_id ) || ( $add && ! user_can( $user_id, 'hj_manage_appointments' ) ) ) { return array( 'status' => 'forbidden' ); }
	return hj_booking_transaction( $doctor, 'assignment', function ( $db ) use ( $doctor, $user_id, $add ) {
		$table = hj_booking_table( 'secretaries' );
		if ( $add ) { hj_booking_query( $db, $db->prepare( "INSERT INTO `$table` (user_id,doctor_id) VALUES (%d,%d) ON DUPLICATE KEY UPDATE user_id=user_id", $user_id, $doctor ) ); }
		else { hj_booking_query( $db, $db->prepare( "DELETE FROM `$table` WHERE user_id=%d AND doctor_id=%d", $user_id, $doctor ) ); }
		return array( 'status' => 'updated' );
	} );
}
/** Shared list query: apply authorization *before* querying private details. */
function hj_booking_management_list( $scope, $doctor = 0, $filter = 'all', $page = 1, $admin_filters = array() ) {
	global $wpdb;
	if ( ! hj_booking_ready() || ! is_user_logged_in() || ! current_user_can( 'read' ) ) { return false; }
	if ( $scope === 'doctor' && ! hj_booking_can_manage( $doctor ) ) { return false; }
	if ( $scope === 'admin' && ! current_user_can( 'manage_options' ) ) { return false; }
	if ( ! in_array( $scope, array( 'doctor', 'patient', 'admin' ), true ) ) { return false; }
	$a = hj_booking_table( 'appointments' ); $d = hj_booking_table( 'details' );
	$where = array( '1=1' ); $params = array();
	if ( $scope === 'doctor' ) { $where[] = 'a.doctor_id=%d'; $params[] = $doctor; }
	if ( $scope === 'patient' ) { $where[] = 'a.patient_id=%d'; $params[] = get_current_user_id(); }
	if ( $scope === 'admin' && ! empty( $admin_filters['doctor'] ) ) { $where[] = 'a.doctor_id=%d'; $params[] = $admin_filters['doctor']; }
	if ( $scope === 'admin' && ! empty( $admin_filters['status'] ) && array_key_exists( $admin_filters['status'], hj_booking_management_labels() ) ) { $where[] = 'a.status=%s'; $params[] = $admin_filters['status']; }
	$date = $scope === 'admin' ? ( $admin_filters['date'] ?? '' ) : '';
	if ( $filter === 'today' ) { $date = wp_date( 'Y-m-d', time(), wp_timezone() ); }
	if ( $date !== '' ) {
		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) { return false; }
		$where[] = 'a.starts_at >= %s AND a.starts_at < %s';
		$params[] = $day->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$params[] = $day->modify( '+1 day' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	} elseif ( $filter === 'future' ) { $where[] = 'a.starts_at >= %s'; $params[] = gmdate( 'Y-m-d H:i:s' ); }
	$page = max( 1, min( 10000, (int) $page ) ); $params[] = 21; $params[] = ( $page - 1 ) * 20;
	// Patient queries do not join the contact table or users.
	$columns = $scope === 'patient' ? 'a.id,a.doctor_id,a.starts_at,a.status' : 'a.id,a.doctor_id,a.starts_at,a.status,u.display_name,d.phone,d.national_code';
	$joins = $scope === 'patient' ? '' : " LEFT JOIN `$d` d ON d.appointment_id=a.id LEFT JOIN `{$wpdb->users}` u ON u.ID=a.patient_id";
	// The patient query is owner-scoped and selects only the tracking code,
	// never the contact or patient detail fields. Staff are authorized above.
	if ( hj_booking_capacity_v2_ready() ) {
		$extra = hj_booking_patient_details_v2_table();
		if ( ! $extra ) { return false; }
		$columns .= ',x.tracking_code';
		if ( $scope !== 'patient' ) { $columns .= ',x.first_name,x.last_name,x.service,x.note'; }
		$joins .= " LEFT JOIN `$extra` x ON x.appointment_id=a.id";
	}
	$sql = "SELECT $columns FROM `$a` a $joins WHERE " . implode( ' AND ', $where ) . ( $filter === 'future' ? ' ORDER BY a.starts_at ASC,a.id ASC' : ' ORDER BY a.starts_at DESC,a.id DESC' ) . ' LIMIT %d OFFSET %d';
	if ( $scope === 'doctor' ) {
		$actor = get_current_user_id();
		$result = hj_booking_transaction( $doctor, 'list', function ( $db ) use ( $doctor, $actor, $sql, $params ) {
			if ( ! current_user_can( 'manage_options' ) && ! ( current_user_can( HJ_Doctors_Roles::CAP_EDIT_OWN ) && hj_doctors_user_owns_profile( $actor, $doctor ) ) ) {
				$assignments = hj_booking_table( 'secretaries' );
				$mapping = hj_booking_row( $db, $db->prepare( "SELECT user_id FROM `$assignments` WHERE user_id=%d AND doctor_id=%d FOR UPDATE", $actor, $doctor ) );
				if ( ! current_user_can( 'hj_manage_appointments' ) || ! $mapping ) { return array( 'status' => 'forbidden' ); }
			}
			return array( 'status' => 'ok', 'rows' => hj_booking_rows( $db, $db->prepare( $sql, $params ) ) );
		} );
		return $result['status'] === 'ok' ? $result['rows'] : false;
	}
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	return $wpdb->last_error || ! is_array( $rows ) ? false : $rows;
}
function hj_booking_management_int( $name, $default = 0 ) {
	$v = isset( $_GET[$name] ) && is_string( $_GET[$name] ) ? wp_unslash( $_GET[$name] ) : '';
	return hj_booking_ui_int( $v ) ?: $default;
}
function hj_booking_management_filter() {
	$v = isset( $_GET['hj_b_filter'] ) && is_string( $_GET['hj_b_filter'] ) ? sanitize_key( wp_unslash( $_GET['hj_b_filter'] ) ) : 'all';
	return in_array( $v, array( 'all', 'today', 'future' ), true ) ? $v : 'all';
}
function hj_booking_management_url( $args ) {
	$base = get_permalink( get_queried_object_id() );
	return add_query_arg( $args, $base ?: home_url( '/' ) ) . '#hj-bookings';
}
function hj_booking_management_status_form( $row, $scope ) {
	$status = $row['status'];
	if ( ! in_array( $status, array( 'pending', 'confirmed' ), true ) ) { return ''; }
	$out = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="hj-bookings__action">';
	$out .= '<input type="hidden" name="action" value="hj_booking_status"><input type="hidden" name="doctor_id" value="' . esc_attr( $row['doctor_id'] ) . '"><input type="hidden" name="booking_id" value="' . esc_attr( $row['id'] ) . '"><input type="hidden" name="scope" value="' . esc_attr( $scope === 'patient' ? 'patient' : 'manage' ) . '">' . wp_nonce_field( 'hj_booking_status_' . $row['id'], '_wpnonce', true, false );
	if ( $scope === 'patient' ) { $out .= '<input type="hidden" name="status" value="cancelled"><button type="submit">لغو نوبت</button>'; }
	else {
		$out .= '<label>تغییر وضعیت <select name="status">';
		foreach ( array( 'confirmed', 'completed', 'no_show', 'cancelled' ) as $value ) { $out .= '<option value="' . esc_attr( $value ) . '">' . esc_html( hj_booking_management_labels()[$value] ) . '</option>'; }
		$out .= '</select></label> <button type="submit">ثبت وضعیت</button>';
	}
	return $out . '</form>';
}
function hj_booking_management_render( $scope, $doctor = 0, $admin_filters = array() ) {
	$filter = $scope === 'admin' ? 'all' : hj_booking_management_filter();
	$page = hj_booking_management_int( 'hj_b_page', 1 );
	$rows = hj_booking_management_list( $scope, $doctor, $filter, $page, $admin_filters );
	if ( $rows === false ) { return '<p>فهرست در دسترس نیست.</p>'; }
	wp_enqueue_style( 'hj-bookings', HJ_DOCTORS_URL . 'assets/css/booking-management.css', array(), HJ_DOCTORS_VERSION );
	$out = '<section class="hj-bookings" dir="rtl"><h2>' . ( $scope === 'patient' ? 'نوبت‌های من' : 'لیست نوبت‌ها' ) . '</h2>';
	if ( $scope === 'doctor' ) { $out .= '<h3>' . esc_html( get_the_title( $doctor ) ) . '</h3>'; }
	if ( $scope !== 'admin' ) {
		$out .= '<nav aria-label="فیلتر نوبت‌ها">';
		foreach ( array( 'today' => 'امروز', 'future' => 'آینده', 'all' => 'همه' ) as $key => $label ) { $out .= '<a href="' . esc_url( hj_booking_management_url( array( 'hj_b_filter' => $key, 'hj_b_page' => 1 ) ) ) . '"' . ( $filter === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a> '; }
		$out .= '</nav>';
	}
	if ( ! $rows ) { $out .= '<p>نوبتی برای نمایش وجود ندارد.</p>'; }
	$labels = hj_booking_management_labels();
	foreach ( array_slice( $rows, 0, 20 ) as $row ) {
		$timestamp = strtotime( $row['starts_at'] . ' UTC' );
		$extra_view = $scope !== 'patient' && hj_booking_capacity_v2_ready();
		$has_patient = $extra_view && is_string( $row['first_name'] ?? null ) && $row['first_name'] !== '' && is_string( $row['last_name'] ?? null ) && $row['last_name'] !== '';
		$patient_name = $has_patient ? $row['first_name'] . ' ' . $row['last_name'] : 'نام بیمار در نوبت قدیمی ثبت نشده';
		$heading = $scope === 'patient' || $scope === 'admin' ? get_the_title( (int) $row['doctor_id'] ) : ( $extra_view ? $patient_name : ( $row['display_name'] ?: 'نام حساب در دسترس نیست' ) );
		$out .= '<article class="hj-bookings__item"><p><strong>' . esc_html( $heading ) . '</strong></p>';
		if ( $scope === 'admin' && $extra_view ) { $out .= '<p>نام بیمار: ' . esc_html( $patient_name ) . '</p>'; }
		if ( $scope === 'admin' || ( $scope === 'doctor' && $extra_view ) ) { $out .= '<p>' . ( $extra_view ? 'نام حساب ثبت‌کننده: ' : 'نام حساب بیمار: ' ) . esc_html( $row['display_name'] ?: 'در دسترس نیست' ) . '</p>'; }
		if ( $scope !== 'patient' ) {
			$out .= '<p>موبایل: <span dir="ltr">' . esc_html( $row['phone'] ?: '—' ) . '</span> · کد ملی: <span dir="ltr">' . esc_html( $row['national_code'] ?: '—' ) . '</span></p>';
			if ( $has_patient ) {
				$services = array( 'consultation'=>'مشاوره', 'visit'=>'ویزیت', 'surgery'=>'جراحی' );
				$out .= '<p>نوع خدمت: ' . esc_html( $services[$row['service']] ?? 'نامعتبر' ) . '</p>';
				if ( is_string( $row['note'] ) && $row['note'] !== '' ) { $out .= '<p class="hj-bookings__note">توضیح: ' . esc_html( $row['note'] ) . '</p>'; }
			}
		}
		$out .= '<p>زمان: ' . esc_html( $timestamp !== false ? wp_date( 'Y/m/d H:i', $timestamp, wp_timezone() ) : 'نامعتبر' ) . ' · وضعیت: ' . esc_html( $labels[$row['status']] ?? 'نامعتبر' ) . '</p>';
		if ( hj_booking_capacity_v2_ready() && is_string( $row['tracking_code'] ?? null ) && preg_match( '/^[A-F0-9]{32}\z/D', $row['tracking_code'] ) ) { $out .= '<p>کد پیگیری: <span dir="ltr">' . esc_html( $row['tracking_code'] ) . '</span></p>'; }
		$out .= hj_booking_management_status_form( $row, $scope ) . '</article>';
	}
	if ( $scope !== 'admin' ) {
		$out .= '<nav aria-label="صفحه‌های نوبت">';
		if ( $page > 1 ) { $out .= '<a href="' . esc_url( hj_booking_management_url( array( 'hj_b_filter' => $filter, 'hj_b_page' => $page - 1 ) ) ) . '">قبلی</a> '; }
		if ( count( $rows ) > 20 ) { $out .= '<a href="' . esc_url( hj_booking_management_url( array( 'hj_b_filter' => $filter, 'hj_b_page' => $page + 1 ) ) ) . '">بعدی</a>'; }
		$out .= '</nav>';
	} else {
		$args = array_merge( array( 'post_type' => 'doctor', 'page' => 'hj-bookings', 'doctor_id' => $admin_filters['doctor'] ?? '', 'booking_status' => $admin_filters['status'] ?? '', 'booking_date' => $admin_filters['date'] ?? '' ) );
		$out .= '<nav>';
		if ( $page > 1 ) { $out .= '<a href="' . esc_url( add_query_arg( array_merge( $args, array( 'hj_b_page' => $page - 1 ) ), admin_url( 'edit.php' ) ) ) . '">قبلی</a> '; }
		if ( count( $rows ) > 20 ) { $out .= '<a href="' . esc_url( add_query_arg( array_merge( $args, array( 'hj_b_page' => $page + 1 ) ), admin_url( 'edit.php' ) ) ) . '">بعدی</a>'; }
		$out .= '</nav>';
	}
	return $out . '</section>';
}
function hj_booking_secretary_dashboard() {
	if ( ! hj_booking_ready() || ! current_user_can( 'hj_manage_appointments' ) ) { return ''; }
	$doctors = hj_booking_management_secretary_doctors();
	$out = '<div id="hj-bookings" dir="rtl">';
	if ( ! $doctors ) { return $out . '<p>هنوز جراحی برای این منشی اختصاص داده نشده است.</p></div>'; }
	foreach ( $doctors as $doctor ) { $out .= hj_booking_management_render( 'doctor', $doctor ); }
	return $out . '</div>';
}
add_action( 'hj_doctor_dashboard_appointments', function ( $doctor ) {
	if ( ! hj_booking_ready() ) { return; }
	$out = hj_booking_can_manage( (int) $doctor ) ? hj_booking_management_render( 'doctor', (int) $doctor ) : '';
	foreach ( hj_booking_management_secretary_doctors() as $assigned ) {
		if ( $assigned !== (int) $doctor ) { $out .= hj_booking_management_render( 'doctor', $assigned ); }
	}
	if ( $out !== '' ) { echo '<div id="hj-bookings">' . $out . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} );
add_shortcode( 'hj_patient_bookings', function () {
	if ( ! hj_booking_ready() ) { return ''; }
	if ( ! is_user_logged_in() ) { return '<p>برای دیدن نوبت‌ها وارد حساب کاربری شوید.</p>'; }
	return '<div id="hj-bookings">' . hj_booking_management_render( 'patient' ) . '</div>';
} );
add_action( 'template_redirect', function () {
	$post = get_queried_object();
	if ( hj_booking_enabled() && is_singular() && $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'hj_patient_bookings' ) || has_shortcode( $post->post_content, 'hj_doctors_dashboard' ) ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); } nocache_headers();
	}
}, 0 );
function hj_booking_management_post_value( $name ) { return isset( $_POST[$name] ) && is_string( $_POST[$name] ) ? trim( wp_unslash( $_POST[$name] ) ) : ''; }
function hj_booking_management_finish( $result ) {
	$status = $result['status'] ?? 'error';
	if ( $status === 'updated' ) { wp_safe_redirect( wp_get_referer() ?: hj_member_account_url() ); exit; }
	$messages = array( 'forbidden' => array( 'دسترسی به این نوبت مجاز نیست.', 403 ), 'invalid' => array( 'درخواست نامعتبر است.', 400 ), 'invalid_transition' => array( 'این وضعیت قابل تغییر نیست.', 409 ), 'too_early' => array( 'ثبت انجام‌شده یا عدم حضور پیش از زمان نوبت مجاز نیست.', 409 ), 'unknown' => array( 'نتیجه مشخص نیست؛ صفحه را بازخوانی و وضعیت را بررسی کنید.', 503 ) );
	$msg = $messages[$status] ?? array( 'عملیات انجام نشد؛ دوباره تلاش کنید.', 503 );
	wp_die( esc_html( $msg[0] ), '', array( 'response' => $msg[1] ) );
}
add_action( 'admin_post_hj_booking_status', function () {
	if ( ! hj_booking_ready() || ! is_user_logged_in() || ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	$id = hj_booking_ui_int( hj_booking_management_post_value( 'booking_id' ) ); $doctor = hj_booking_ui_int( hj_booking_management_post_value( 'doctor_id' ) );
	if ( ! $id || ! $doctor ) { wp_die( 'درخواست نامعتبر', '', array( 'response' => 400 ) ); }
	check_admin_referer( 'hj_booking_status_' . $id );
	$scope = hj_booking_management_post_value( 'scope' ); $status = sanitize_key( hj_booking_management_post_value( 'status' ) );
	if ( $scope === 'patient' && $status === 'cancelled' ) { $result = hj_booking_patient_cancel( $doctor, $id ); }
	elseif ( $scope === 'manage' ) { $result = hj_booking_change_status( $doctor, $id, $status ); }
	else { $result = array( 'status' => 'forbidden' ); }
	hj_booking_management_finish( $result );
} );
add_action( 'admin_post_hj_booking_assignment', function () {
	if ( ! hj_booking_ready() || ! current_user_can( 'manage_options' ) || ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'hj_booking_assignment' );
	$doctor = hj_booking_ui_int( hj_booking_management_post_value( 'doctor_id' ) ); $user = hj_booking_ui_int( hj_booking_management_post_value( 'user_id' ) ); $mode = hj_booking_management_post_value( 'mode' );
	if ( ! $doctor || ! $user || ! in_array( $mode, array( 'add', 'remove' ), true ) ) { wp_die( 'درخواست نامعتبر', '', array( 'response' => 400 ) ); }
	hj_booking_management_finish( hj_booking_manage_assignment( $doctor, $user, $mode === 'add' ) );
} );
add_action( 'admin_menu', function () {
	if ( ! hj_booking_ready() ) { return; }
	add_submenu_page( 'edit.php?post_type=doctor', 'نوبت‌ها', 'نوبت‌ها', 'manage_options', 'hj-bookings', 'hj_booking_admin_page' );
} );
add_action( 'admin_enqueue_scripts', function () {
	$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( hj_booking_ready() && current_user_can( 'manage_options' ) && $page === 'hj-bookings' ) { wp_enqueue_style( 'hj-bookings', HJ_DOCTORS_URL . 'assets/css/booking-management.css', array(), HJ_DOCTORS_VERSION ); }
} );
function hj_booking_admin_page() {
	if ( ! hj_booking_ready() || ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز', '', array( 'response' => 403 ) ); }
	$doctor = hj_booking_management_int( 'doctor_id' );
	$status = isset( $_GET['booking_status'] ) && is_string( $_GET['booking_status'] ) ? sanitize_key( wp_unslash( $_GET['booking_status'] ) ) : '';
	$date = isset( $_GET['booking_date'] ) && is_string( $_GET['booking_date'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_date'] ) ) : '';
	$doctors = get_posts( array( 'post_type' => 'doctor', 'post_status' => array( 'publish', 'private', 'draft', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<div class="wrap" dir="rtl"><h1>مدیریت نوبت‌ها</h1><form method="get" action="' . esc_url( admin_url( 'edit.php' ) ) . '"><input type="hidden" name="post_type" value="doctor"><input type="hidden" name="page" value="hj-bookings"><label>جراح <select name="doctor_id"><option value="">همه</option>';
	foreach ( $doctors as $doc ) { echo '<option value="' . esc_attr( $doc->ID ) . '" ' . selected( $doctor, $doc->ID, false ) . '>' . esc_html( get_the_title( $doc ) ) . '</option>'; }
	echo '</select></label> <label>وضعیت <select name="booking_status"><option value="">همه</option>';
	foreach ( hj_booking_management_labels() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
	echo '</select></label> <label>تاریخ <input type="date" name="booking_date" value="' . esc_attr( $date ) . '"></label> <button type="submit">فیلتر</button></form>';
	echo hj_booking_management_render( 'admin', 0, array( 'doctor' => $doctor, 'status' => $status, 'date' => $date ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<section class="hj-bookings" dir="rtl"><h2>اتصال منشی به جراح</h2><p>ابتدا نقش «منشی نوبت‌دهی» را در مدیریت کاربران به حساب بدهید؛ سپس شناسهٔ کاربر و جراح را وارد کنید.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="hj_booking_assignment">' . wp_nonce_field( 'hj_booking_assignment', '_wpnonce', true, false ) . '<label>شناسهٔ کاربر <input name="user_id" type="number" min="1" required></label> <label>جراح <select name="doctor_id" required>';
	foreach ( $doctors as $doc ) { echo '<option value="' . esc_attr( $doc->ID ) . '">' . esc_html( get_the_title( $doc ) ) . '</option>'; }
	echo '</select></label> <select name="mode"><option value="add">اتصال</option><option value="remove">قطع اتصال</option></select> <button type="submit">ثبت</button></form></section></div>';
}

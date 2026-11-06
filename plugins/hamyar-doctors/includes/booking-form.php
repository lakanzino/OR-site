<?php
/** Patient booking UI and public AJAX; opt-in with the booking backend. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hj_booking_ui_value( $name ) {
	return isset( $_POST[$name] ) && is_string( $_POST[$name] ) ? trim( wp_unslash( $_POST[$name] ) ) : '';
}
function hj_booking_ui_int( $value ) {
	return is_string( $value ) && preg_match( '/^[1-9][0-9]{0,17}$/D', $value ) && (string) (int) $value === $value ? (int) $value : 0;
}
function hj_booking_ui_uuid( $value ) {
	return is_string( $value ) && preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
}
function hj_booking_ui_error( $code, $message, $http = 400 ) {
	wp_send_json_error( array( 'code' => $code, 'message' => $message ), $http );
}
function hj_booking_ui_guard( $action, $limit = 120 ) {
	if ( ! hj_booking_ready() ) { hj_booking_ui_error( 'unavailable', 'نوبت‌دهی در حال حاضر در دسترس نیست.', 503 ); }
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ! check_ajax_referer( 'hj_booking_ui', 'nonce', false ) ) { hj_booking_ui_error( 'forbidden', 'درخواست معتبر نیست؛ صفحه را بازخوانی کنید.', 403 ); }
	if ( ! hj_sec_rate_limit( $action, $limit, MINUTE_IN_SECONDS ) ) { hj_booking_ui_error( 'rate_limit', 'درخواست‌های زیادی ارسال شده؛ کمی بعد تلاش کنید.', 429 ); }
}
function hj_booking_intent_cookie( $value, $expires ) {
	setcookie( 'hj_booking_intent', $value, array( 'expires' => $expires, 'path' => '/', 'domain' => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
}
function hj_booking_intent_read() {
	$raw = isset( $_COOKIE['hj_booking_intent'] ) && is_string( $_COOKIE['hj_booking_intent'] ) ? wp_unslash( $_COOKIE['hj_booking_intent'] ) : '';
	$parts = explode( '.', $raw );
	if ( count( $parts ) !== 2 || ! preg_match( '/^[A-Za-z0-9_-]+$/D', $parts[0] ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { return false; }
	$json = base64_decode( strtr( $parts[0], '-_', '+/' ), true );
	$data = is_string( $json ) ? json_decode( $json, true ) : false;
	if ( ! is_array( $data ) || ! isset( $data['doctor'], $data['page'], $data['start'], $data['revision'], $data['key'], $data['expires'] ) || ! is_int( $data['doctor'] ) || ! is_int( $data['page'] ) || ! is_int( $data['revision'] ) || ! is_int( $data['expires'] ) || $data['doctor'] < 1 || $data['page'] < 1 || $data['revision'] < 1 || $data['expires'] < time() || $data['expires'] > time() + 900 || ! is_string( $data['start'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $data['start'] ) || ! hj_booking_ui_uuid( $data['key'] ) ) { return false; }
	return $data;
}
function hj_booking_intent_page_valid( $page, $doctor ) {
	$post = get_post( $page );
	return $post && $post->post_status === 'publish' && ( ( $post->post_type === 'doctor' && (int) $post->ID === $doctor ) || ( $post->post_type === 'page' && has_shortcode( $post->post_content, 'hj_booking_form' ) ) );
}
/** No user-supplied redirect URL is ever accepted. */
function hj_booking_intent_destination() {
	$intent = hj_booking_intent_read();
	if ( ! $intent || ! hj_booking_intent_page_valid( $intent['page'], $intent['doctor'] ) || get_post_type( $intent['doctor'] ) !== 'doctor' || get_post_status( $intent['doctor'] ) !== 'publish' ) { return false; }
	$url = get_permalink( $intent['page'] );
	return $url ? $url . '#hj-booking' : false;
}
function hj_booking_ui_result_error( $status ) {
	$messages = array(
		'revision_conflict' => array( 'ساعات کاری تغییر کرده، لطفاً دوباره انتخاب کنید.', 409 ),
		'invalid_slot' => array( 'این زمان رزرو شد؛ لطفاً زمان دیگری انتخاب کنید.', 409 ),
		'pending_limit' => array( 'شما سه نوبت در انتظار دارید؛ ابتدا وضعیت آن‌ها را پیگیری کنید.', 429 ),
		'idempotency_conflict' => array( 'اطلاعات درخواست قبلی تغییر کرده است؛ برای پیگیری از همان اطلاعات استفاده کنید.', 409 ),
		'forbidden' => array( 'دسترسی به این درخواست مجاز نیست.', 403 ),
		'legacy_schedule' => array( 'برای این برنامه تقویم ظرفیت هنوز آماده نیست؛ از انتخاب تاریخ استفاده کنید.', 409 ),
		'invalid' => array( 'اطلاعات درخواست معتبر نیست.', 400 ),
		'busy' => array( 'سامانه مشغول است؛ با همین درخواست دوباره تلاش کنید.', 503 ),
		'unknown' => array( 'نتیجه مشخص نیست؛ با همین اطلاعات دوباره تلاش کنید.', 503 ),
	);
	$entry = $messages[$status] ?? array( 'نوبت‌دهی موقتاً در دسترس نیست؛ دوباره تلاش کنید.', 503 );
	hj_booking_ui_error( $status, $entry[0], $entry[1] );
}
function hj_booking_ui_slots() {
	hj_booking_ui_guard( 'booking_slots' );
	$doctor = hj_booking_ui_int( hj_booking_ui_value( 'doctor_id' ) );
	$result = hj_booking_public_slots( $doctor, hj_booking_ui_value( 'date' ) );
	if ( $result['status'] !== 'ok' ) { hj_booking_ui_result_error( $result['status'] ); }
	$slots = array();
	foreach ( $result['slots'] as $start ) {
		$ts = strtotime( $start . ' UTC' );
		if ( $ts !== false ) { $slots[] = array( 'start' => $start, 'label' => wp_date( 'H:i', $ts, wp_timezone() ) ); }
	}
	wp_send_json_success( array( 'revision' => $result['revision'], 'slots' => $slots ) );
}
/** CI-only experimental calendar; it returns aggregate availability, never patient data. */
function hj_booking_ui_calendar() {
	if ( ! hj_booking_capacity_v2_ready() ) { hj_booking_ui_error( 'unavailable', 'تقویم در دسترس نیست.', 503 ); }
	hj_booking_ui_guard( 'booking_calendar', 60 );
	$doctor = hj_booking_ui_int( hj_booking_ui_value( 'doctor_id' ) );
	$result = hj_booking_capacity_calendar( $doctor );
	if ( $result['status'] !== 'ok' ) { hj_booking_ui_result_error( $result['status'] ); }
	nocache_headers();
	wp_send_json_success( array( 'revision'=>$result['revision'], 'days'=>$result['days'] ) );
}
function hj_booking_ui_intent() {
	hj_booking_ui_guard( 'booking_intent', 30 );
	$doctor = hj_booking_ui_int( hj_booking_ui_value( 'doctor_id' ) ); $page = hj_booking_ui_int( hj_booking_ui_value( 'page_id' ) );
	$revision = hj_booking_ui_int( hj_booking_ui_value( 'revision' ) ); $start = hj_booking_ui_value( 'slot_start' ); $key = hj_booking_ui_value( 'idempotency_key' );
	$instant = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $start, new DateTimeZone( 'UTC' ) );
	if ( ! $doctor || ! $page || ! $revision || ! hj_booking_ui_uuid( $key ) || ! $instant || $instant->format( 'Y-m-d H:i:s' ) !== $start || ! hj_booking_intent_page_valid( $page, $doctor ) ) { hj_booking_ui_error( 'invalid', 'انتخاب نوبت معتبر نیست.' ); }
	$slots = hj_booking_public_slots( $doctor, $instant->setTimezone( wp_timezone() )->format( 'Y-m-d' ) );
	if ( $slots['status'] !== 'ok' ) { hj_booking_ui_result_error( $slots['status'] ); }
	if ( $slots['revision'] !== $revision ) { hj_booking_ui_result_error( 'revision_conflict' ); }
	if ( ! in_array( $start, $slots['slots'], true ) ) { hj_booking_ui_result_error( 'invalid_slot' ); }
	$data = array( 'doctor' => $doctor, 'page' => $page, 'start' => $start, 'revision' => $revision, 'key' => $key, 'expires' => time() + 900 );
	$encoded = rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
	hj_booking_intent_cookie( $encoded . '.' . hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) ), $data['expires'] );
	$destination = get_permalink( $page ) . '#hj-booking';
	$url = function_exists( 'hj_member_google_is_ready' ) && hj_member_google_is_ready() ? add_query_arg( 'hj_booking_return', '1', hj_member_google_start_url() ) : wp_login_url( $destination );
	wp_send_json_success( array( 'login_url' => $url ) );
}
function hj_booking_ui_create() {
	hj_booking_ui_guard( 'booking_create', 30 );
	if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) { hj_booking_ui_error( 'login_required', 'برای ثبت نوبت وارد حساب کاربری شوید.', 403 ); }
	$doctor = hj_booking_ui_int( hj_booking_ui_value( 'doctor_id' ) ); $revision = hj_booking_ui_int( hj_booking_ui_value( 'revision' ) );
	$start = hj_booking_ui_value( 'slot_start' ); $key = hj_booking_ui_value( 'idempotency_key' );
	$phone = hj_to_en_digits( hj_booking_ui_value( 'phone' ) ); $national = hj_to_en_digits( hj_booking_ui_value( 'national_code' ) );
	if ( ! preg_match( '/^09[0-9]{9}$/D', $phone ) ) { hj_booking_ui_error( 'invalid_phone', 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ آغاز شود.' ); }
	if ( hj_booking_national_code( $national ) === false ) { hj_booking_ui_error( 'invalid_national', 'کد ملی ۱۰ رقمی معتبر وارد کنید.' ); }
	if ( ! $doctor || ! $revision || ! hj_booking_ui_uuid( $key ) ) { hj_booking_ui_error( 'invalid', 'انتخاب نوبت معتبر نیست.' ); }
	$result = hj_booking_create( $doctor, $start, str_replace( '-', '', $key ), $phone, $national, $revision );
	if ( ! in_array( $result['status'], array( 'created', 'replayed' ), true ) ) { hj_booking_ui_result_error( $result['status'] ); }
	hj_booking_intent_cookie( '', time() - 3600 );
	wp_send_json_success( array( 'status' => $result['status'], 'id' => $result['id'], 'message' => 'درخواست نوبت شما ثبت شد و در انتظار تأیید است.' ) );
}
if ( hj_booking_capacity_v2_ready() ) {
	add_action( 'wp_ajax_hj_booking_calendar', 'hj_booking_ui_calendar' );
	add_action( 'wp_ajax_nopriv_hj_booking_calendar', 'hj_booking_ui_calendar' );
}
add_action( 'wp_ajax_hj_booking_slots', 'hj_booking_ui_slots' );
add_action( 'wp_ajax_nopriv_hj_booking_slots', 'hj_booking_ui_slots' );
add_action( 'wp_ajax_hj_booking_intent', 'hj_booking_ui_intent' );
add_action( 'wp_ajax_nopriv_hj_booking_intent', 'hj_booking_ui_intent' );
add_action( 'wp_ajax_hj_booking_create', 'hj_booking_ui_create' );
add_action( 'wp_ajax_nopriv_hj_booking_create', 'hj_booking_ui_create' );

add_action( 'template_redirect', function () {
	$post = get_queried_object();
	if ( hj_booking_enabled() && is_singular() && $post instanceof WP_Post && ( $post->post_type === 'doctor' || has_shortcode( $post->post_content, 'hj_booking_form' ) ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
	}
}, 0 );
function hj_booking_form_shortcode( $attributes = array() ) {
	if ( ! hj_booking_ready() ) { return ''; }
	$attrs = shortcode_atts( array( 'doctor_id' => '' ), $attributes, 'hj_booking_form' );
	$page = get_queried_object_id();
	$doctor = hj_booking_ui_int( (string) $attrs['doctor_id'] );
	if ( ! $doctor && is_singular( 'doctor' ) ) { $doctor = $page; }
	$intent = is_user_logged_in() ? hj_booking_intent_read() : false;
	if ( $intent && (int) $page !== $intent['page'] ) { $intent = false; }
	if ( $intent && $doctor && $doctor !== $intent['doctor'] ) { $intent = false; }
	if ( $intent ) { $intent['local_date'] = ( new DateTimeImmutable( $intent['start'], new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' ); }
	$doctors = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	wp_enqueue_style( 'hj-booking-form', HJ_DOCTORS_URL . 'assets/css/booking-form.css', array(), HJ_DOCTORS_VERSION );
	wp_enqueue_script( 'hj-booking-form', HJ_DOCTORS_URL . 'assets/js/booking-form.js', array(), HJ_DOCTORS_VERSION, true );
	$today = new DateTimeImmutable( 'now', wp_timezone() );
	$capacity_calendar = hj_booking_capacity_v2_ready();
	ob_start(); ?>
	<section id="hj-booking" class="hj-booking" dir="rtl" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'hj_booking_ui' ) ); ?>" data-page="<?php echo esc_attr( $page ); ?>" data-fixed="<?php echo $doctor ? '1' : '0'; ?>" data-logged="<?php echo is_user_logged_in() ? '1' : '0'; ?>" data-intent="<?php echo esc_attr( $intent ? wp_json_encode( $intent ) : '' ); ?>">
		<h2>درخواست نوبت</h2><p>پس از ثبت، درخواست شما در انتظار تأیید مطب خواهد بود.</p>
		<form class="hj-booking__form" novalidate>
			<label>جراح <select name="doctor_id" required <?php disabled( (bool) $doctor ); ?>><option value="">انتخاب جراح</option><?php foreach ( $doctors as $doc ) : ?><option value="<?php echo esc_attr( $doc->ID ); ?>" <?php selected( $doctor ?: ( $intent['doctor'] ?? 0 ), $doc->ID ); ?>><?php echo esc_html( get_the_title( $doc ) ); ?></option><?php endforeach; ?></select></label>
			<?php if ( $capacity_calendar ) : ?>
			<div class="hj-booking__calendar" aria-label="تقویم نوبت‌های ۱۴ روز آینده">
				<p>۱۴ روز آینده: <span class="hj-booking__legend-blue">آبی: بسته، تکمیل یا گذشته</span>؛ <span class="hj-booking__legend-yellow">زرد: ۱ تا ۳ جای خالی</span>؛ <span class="hj-booking__legend-green">سبز: بیش از ۳ جای خالی</span>.</p>
				<p>تعدادها لحظه‌ای هستند و زمان ثبت دوباره بررسی می‌شوند.</p>
				<div class="hj-booking__calendar-days" aria-label="انتخاب روز"></div>
				<p class="hj-booking__calendar-status" role="status" aria-live="polite">ابتدا جراح را انتخاب کنید.</p>
				<button type="button" class="hj-booking__calendar-retry">به‌روزرسانی روزها</button>
			</div>
			<?php endif; ?>
			<label class="hj-booking__date">تاریخ <input name="date" type="date" required min="<?php echo esc_attr( $today->format( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( $today->modify( $capacity_calendar ? '+13 days' : '+30 days' )->format( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $today->format( 'Y-m-d' ) ); ?>"></label>
			<button type="button" class="hj-booking__load">نمایش زمان‌های خالی</button><div class="hj-booking__slots" aria-label="زمان‌های خالی"></div>
			<?php if ( is_user_logged_in() ) : ?><label>شمارهٔ موبایل <input name="phone" inputmode="numeric" autocomplete="tel" required></label><label>کد ملی <input name="national_code" inputmode="numeric" autocomplete="off" required></label><button type="submit">ثبت درخواست نوبت</button><?php else : ?><button type="submit">ورود و ادامهٔ رزرو</button><?php endif; ?>
			<p class="hj-booking__status" role="status" aria-live="polite"></p>
		</form>
	</section>
	<?php return ob_get_clean();
}
add_shortcode( 'hj_booking_form', 'hj_booking_form_shortcode' );

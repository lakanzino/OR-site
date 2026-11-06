<?php
/** Experimental weekly office settings; unavailable outside disposable v2 CI. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hj_capacity_days() {
	return array( 'saturday'=>'شنبه', 'sunday'=>'یکشنبه', 'monday'=>'دوشنبه', 'tuesday'=>'سه‌شنبه', 'wednesday'=>'چهارشنبه', 'thursday'=>'پنجشنبه', 'friday'=>'جمعه' );
}
/** Read one consistent snapshot with a single SELECT; never creates rows on GET. */
function hj_capacity_editor_state( $doctor ) {
	global $wpdb;
	if ( ! hj_booking_ready() || ! hj_booking_capacity_v2_ready() ) { return array( 'status'=>'unavailable' ); }
	if ( ! hj_booking_can_configure( $doctor ) ) { return array( 'status'=>'forbidden' ); }
	$d = hj_booking_table( 'doctors' ); $s = hj_booking_table( 'schedules' );
	$previous = $wpdb->suppress_errors( true );
	try {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT d.doctor_id AS configured,d.enabled,d.duration_minutes,d.buffer_minutes,s.doctor_id AS scheduled,s.revision,s.rows_json,s.rows_hash FROM (SELECT %d AS doctor_id) x LEFT JOIN `$d` d ON d.doctor_id=x.doctor_id LEFT JOIN `$s` s ON s.doctor_id=x.doctor_id", $doctor ), ARRAY_A );
		if ( $wpdb->last_error || ! $row || ( $row['scheduled'] !== null && $row['configured'] === null ) ) { return array( 'status'=>'invalid_schedule' ); }
		$duration = $row['configured'] === null ? 30 : (int)$row['duration_minutes'];
		$buffer = $row['configured'] === null ? 0 : (int)$row['buffer_minutes'];
		if ( $duration < 1 || $duration > 240 || $buffer < 0 || $buffer > 120 || ( $row['configured'] !== null && ! in_array( (int)$row['enabled'], array(0,1), true ) ) ) { return array( 'status'=>'invalid_schedule' ); }
		$enabled = $row['configured'] !== null && (bool)(int)$row['enabled'];
		$expected = array( 'duration'=>$row['configured'] === null ? 30 : $duration, 'buffer'=>$row['configured'] === null ? 5 : $buffer, 'enabled'=>$enabled );
		if ( $row['scheduled'] === null ) { return array( 'status'=>'ok', 'revision'=>0, 'enabled'=>$enabled, 'rows'=>array(), 'duration'=>$duration, 'buffer'=>$buffer, 'expected_config'=>$expected, 'missing'=>true ); }
		$snapshot = hj_booking_decode_snapshot( $row );
		if ( $snapshot === false ) { return array( 'status'=>'invalid_schedule' ); }
		$rows = array();
		foreach ( $snapshot['rows'] as $entry ) {
			if ( $snapshot['format'] === 1 ) { $entry += array( 'duration_minutes'=>$duration, 'buffer_minutes'=>$buffer, 'capacity'=>1 ); }
			$rows[$entry['day_of_week']] = $entry;
		}
		return array( 'status'=>'ok', 'revision'=>$snapshot['revision'], 'enabled'=>$enabled, 'rows'=>$rows, 'duration'=>$snapshot['format'] === 1 ? $duration : 30, 'buffer'=>$snapshot['format'] === 1 ? $buffer : 0, 'expected_config'=>$expected, 'missing'=>false );
	} catch ( Throwable $e ) { return array( 'status'=>'unavailable' ); }
	finally { $wpdb->suppress_errors( $previous ); }
}
function hj_capacity_page_valid( $page ) {
	$post = get_post( $page );
	return $post && $post->post_type === 'page' && $post->post_status === 'publish' && has_shortcode( $post->post_content, 'hj_capacity_settings' );
}
add_action( 'template_redirect', function () {
	$post = get_queried_object();
	if ( hj_booking_capacity_v2_ready() && $post instanceof WP_Post && $post->post_type === 'page' && has_shortcode( $post->post_content, 'hj_capacity_settings' ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
	}
}, 0 );
function hj_capacity_settings_shortcode( $attributes = array() ) {
	if ( ! hj_booking_ready() || ! hj_booking_capacity_v2_ready() || ! is_user_logged_in() ) { return ''; }
	$attrs = shortcode_atts( array( 'doctor_id'=>'' ), $attributes, 'hj_capacity_settings' );
	$doctor = $attrs['doctor_id'] !== '' ? absint( $attrs['doctor_id'] ) : absint( get_user_meta( get_current_user_id(), 'hj_doctor_post_id', true ) );
	$page = get_queried_object_id();
	if ( ! $doctor || ! hj_booking_can_configure( $doctor ) || ! hj_capacity_page_valid( $page ) ) { return ''; }
	$state = hj_capacity_editor_state( $doctor );
	if ( $state['status'] !== 'ok' ) { return '<section class="hj-capacity" dir="rtl"><p>برنامه در دسترس نیست؛ برای جلوگیری از بازنویسی، با مدیر سایت تماس بگیرید.</p></section>'; }
	wp_enqueue_style( 'hj-capacity-settings', HJ_DOCTORS_URL . 'assets/css/booking-capacity-settings.css', array(), HJ_DOCTORS_VERSION );
	$messages = array(
		'saved'=>'برنامه ذخیره شد.',
		'conflict'=>'برنامه در صفحهٔ دیگری تغییر کرده است؛ اطلاعات جدید را بررسی و دوباره وارد کنید.',
		'booked'=>'تا زمان وجود نوبت آینده یا در حال انجام، تغییر برنامه مجاز نیست؛ نوبت‌ها حذف نمی‌شوند.',
		'invalid'=>'اطلاعات برنامه معتبر نیست. لطفاً زمان‌ها و مقدارها را بررسی کنید.',
		'unknown'=>'نتیجهٔ ذخیره مشخص نیست؛ قبل از تلاش دوباره، برنامهٔ نمایش‌داده‌شده را بررسی کنید.',
		'error'=>'ذخیره انجام نشد یا وضعیت برنامه قابل بررسی نیست. لطفاً بعداً دوباره تلاش کنید.',
	);
	$status = isset( $_GET['hj_cap_status'] ) && is_string( $_GET['hj_cap_status'] ) ? sanitize_key( wp_unslash( $_GET['hj_cap_status'] ) ) : '';
	ob_start(); ?>
	<section class="hj-capacity" id="hj-capacity-settings" dir="rtl">
		<h2>برنامهٔ نوبت‌دهی مطب</h2>
		<p>برای هر روز ساعت، طول بازه و تعداد افراد را تعیین کنید. تغییر برنامه در صورت وجود نوبت آینده یا در حال انجام موقتاً متوقف می‌شود.</p>
		<?php if ( isset( $messages[$status] ) ) : ?><p class="hj-capacity__message" role="status"><?php echo esc_html( $messages[$status] ); ?></p><?php endif; ?>
		<?php if ( $state['missing'] ) : ?><p>هنوز برنامه‌ای ثبت نشده است؛ ساعات نمایشی قبلی خودکار وارد نمی‌شوند.</p><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hj_capacity_week_save">
			<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor ); ?>">
			<input type="hidden" name="page_id" value="<?php echo esc_attr( $page ); ?>">
			<input type="hidden" name="revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<input type="hidden" name="original_duration" value="<?php echo esc_attr( $state['expected_config']['duration'] ); ?>">
			<input type="hidden" name="original_buffer" value="<?php echo esc_attr( $state['expected_config']['buffer'] ); ?>">
			<input type="hidden" name="original_enabled" value="<?php echo $state['expected_config']['enabled'] ? '1' : '0'; ?>">
			<?php wp_nonce_field( 'hj_capacity_week_' . $doctor, 'hj_capacity_nonce' ); ?>
			<label class="hj-capacity__switch">وضعیت نوبت‌دهی <select name="enabled" required><option value="0" <?php selected( $state['enabled'], false ); ?>>غیرفعال</option><option value="1" <?php selected( $state['enabled'], true ); ?>>فعال</option></select></label>
			<div class="hj-capacity__days">
			<?php foreach ( hj_capacity_days() as $key => $label ) :
				$day = $state['rows'][$key] ?? array( 'is_closed'=>1, 'start_time'=>'', 'end_time'=>'', 'duration_minutes'=>$state['duration'], 'buffer_minutes'=>$state['buffer'], 'capacity'=>1 ); ?>
				<fieldset class="hj-capacity__day"><legend><?php echo esc_html( $label ); ?></legend>
					<label>وضعیت <select name="week[<?php echo esc_attr( $key ); ?>][closed]"><option value="1" <?php selected( (int)$day['is_closed'], 1 ); ?>>تعطیل</option><option value="0" <?php selected( (int)$day['is_closed'], 0 ); ?>>فعال</option></select></label>
					<label>از <input type="time" step="1" name="week[<?php echo esc_attr( $key ); ?>][start]" value="<?php echo esc_attr( $day['start_time'] ); ?>"></label>
					<label>تا <input type="time" step="1" name="week[<?php echo esc_attr( $key ); ?>][end]" value="<?php echo esc_attr( $day['end_time'] ); ?>"></label>
					<label>طول بازه (دقیقه) <input type="number" min="1" max="240" inputmode="numeric" name="week[<?php echo esc_attr( $key ); ?>][duration]" value="<?php echo esc_attr( $day['duration_minutes'] ); ?>" required></label>
					<label>ظرفیت هر بازه <input type="number" min="1" max="10" inputmode="numeric" name="week[<?php echo esc_attr( $key ); ?>][capacity]" value="<?php echo esc_attr( $day['capacity'] ); ?>" required></label>
					<label>فاصلهٔ اضافی (دقیقه) <input type="number" min="0" max="120" inputmode="numeric" name="week[<?php echo esc_attr( $key ); ?>][buffer]" value="<?php echo esc_attr( $day['buffer_minutes'] ); ?>" required></label>
				</fieldset>
			<?php endforeach; ?>
			</div><button type="submit">ذخیرهٔ برنامه</button>
		</form>
	</section>
	<?php return ob_get_clean();
}
add_shortcode( 'hj_capacity_settings', 'hj_capacity_settings_shortcode' );
function hj_capacity_int( $value, $min, $max ) {
	if ( ! is_string( $value ) ) { return false; }
	$value = hj_to_en_digits( trim( $value ) );
	if ( ! preg_match( '/^(0|[1-9][0-9]{0,2})$/D', $value ) ) { return false; }
	$n = (int)$value;
	return $n >= $min && $n <= $max ? $n : false;
}
function hj_capacity_week_save() {
	if ( ! hj_booking_ready() || ! hj_booking_capacity_v2_ready() ) { wp_die( 'نوبت‌دهی در دسترس نیست.', '', array( 'response'=>503 ) ); }
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ! is_user_logged_in() ) { wp_die( 'درخواست معتبر نیست.', '', array( 'response'=>403 ) ); }
	$raw_doctor = isset( $_POST['doctor_id'] ) && is_string( $_POST['doctor_id'] ) ? wp_unslash( $_POST['doctor_id'] ) : '';
	$raw_page = isset( $_POST['page_id'] ) && is_string( $_POST['page_id'] ) ? wp_unslash( $_POST['page_id'] ) : '';
	$doctor = hj_booking_ui_int( $raw_doctor ); $page = hj_booking_ui_int( $raw_page );
	$nonce = isset( $_POST['hj_capacity_nonce'] ) && is_string( $_POST['hj_capacity_nonce'] ) ? wp_unslash( $_POST['hj_capacity_nonce'] ) : '';
	if ( ! $doctor || ! $page || ! hj_capacity_page_valid( $page ) || ! wp_verify_nonce( $nonce, 'hj_capacity_week_' . $doctor ) || ! hj_booking_can_configure( $doctor ) ) { wp_die( 'دسترسی مجاز نیست.', '', array( 'response'=>403 ) ); }
	if ( ! hj_sec_rate_limit( 'capacity_week_save', 60, HOUR_IN_SECONDS ) ) { wp_die( 'درخواست‌های زیادی ارسال شده است؛ بعداً تلاش کنید.', '', array( 'response'=>429 ) ); }
	$raw_revision = isset( $_POST['revision'] ) && is_string( $_POST['revision'] ) ? wp_unslash( $_POST['revision'] ) : '';
	$enabled = isset( $_POST['enabled'] ) && is_string( $_POST['enabled'] ) ? wp_unslash( $_POST['enabled'] ) : '';
	$original_enabled = isset( $_POST['original_enabled'] ) && is_string( $_POST['original_enabled'] ) ? wp_unslash( $_POST['original_enabled'] ) : '';
	$original_duration = hj_capacity_int( isset( $_POST['original_duration'] ) && is_string( $_POST['original_duration'] ) ? wp_unslash( $_POST['original_duration'] ) : '', 1, 240 );
	$original_buffer = hj_capacity_int( isset( $_POST['original_buffer'] ) && is_string( $_POST['original_buffer'] ) ? wp_unslash( $_POST['original_buffer'] ) : '', 0, 120 );
	$week = isset( $_POST['week'] ) && is_array( $_POST['week'] ) ? wp_unslash( $_POST['week'] ) : array();
	$valid_revision = preg_match( '/^(0|[1-9][0-9]*)$/D', $raw_revision ) && strlen( $raw_revision ) <= strlen( (string)PHP_INT_MAX ) && (string)(int)$raw_revision === $raw_revision;
	$valid = $valid_revision && in_array( $enabled, array('0','1'), true ) && in_array( $original_enabled, array('0','1'), true ) && $original_duration !== false && $original_buffer !== false && count( $week ) === 7; $rows = array();
	foreach ( hj_capacity_days() as $key=>$label ) {
		$day = $week[$key] ?? null;
		if ( ! is_array( $day ) || count( $day ) !== 6 || ! isset( $day['closed'], $day['start'], $day['end'], $day['duration'], $day['buffer'], $day['capacity'] ) || ! is_string( $day['closed'] ) || ! in_array( $day['closed'], array('0','1'), true ) || ! is_string( $day['start'] ) || ! is_string( $day['end'] ) ) { $valid = false; break; }
		$duration = hj_capacity_int( $day['duration'], 1, 240 ); $buffer = hj_capacity_int( $day['buffer'], 0, 120 ); $capacity = hj_capacity_int( $day['capacity'], 1, 10 );
		if ( $duration === false || $buffer === false || $capacity === false ) { $valid = false; break; }
		$rows[] = array( 'day_of_week'=>$key, 'is_closed'=>(int)$day['closed'], 'start_time'=>trim( $day['start'] ), 'end_time'=>trim( $day['end'] ), 'duration_minutes'=>$duration, 'buffer_minutes'=>$buffer, 'capacity'=>$capacity );
	}
	$expected_config = array( 'duration'=>$original_duration, 'buffer'=>$original_buffer, 'enabled'=>$original_enabled === '1' );
	$result = $valid ? hj_booking_save_capacity_week( $doctor, $rows, (int)$raw_revision, $enabled === '1', $expected_config ) : array( 'status'=>'invalid' );
	$codes = array( 'saved'=>'saved', 'revision_conflict'=>'conflict', 'has_future_bookings'=>'booked', 'invalid'=>'invalid', 'unknown'=>'unknown' );
	$status = $codes[$result['status'] ?? ''] ?? 'error';
	$url = add_query_arg( 'hj_cap_status', $status, get_permalink( $page ) ) . '#hj-capacity-settings';
	wp_safe_redirect( $url, 303 ); exit;
}
add_action( 'admin_post_hj_capacity_week_save', 'hj_capacity_week_save' );

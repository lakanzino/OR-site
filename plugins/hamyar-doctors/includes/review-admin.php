<?php
/** Doctor review queue and editor fields, independent of ACF. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hj_review_fields() {
	return array(
		'name_fa' => 'نام فارسی', 'name_en' => 'نام انگلیسی',
		'specialty_fa' => 'عنوان تخصص', 'specialty_en' => 'عنوان تخصص انگلیسی',
		'bio_fa' => 'معرفی فارسی', 'bio_en' => 'معرفی انگلیسی',
		'medical_license' => 'شماره نظام پزشکی', 'experience_years' => 'سابقه (سال)',
		'education' => 'تحصیلات و دوره‌ها', 'surgeries_performed' => 'جراحی‌های انجام‌شده',
		'phone_clinic' => 'تلفن مطب', 'phone_mobile' => 'موبایل',
		'address_clinic' => 'نشانی مطب', 'address_hospital' => 'نشانی بیمارستان', 'map_link' => 'لینک نقشه',
	);
}
function hj_review_pending_count() {
	$counts = wp_count_posts( 'doctor' );
	return isset( $counts->pending ) ? (int) $counts->pending : 0;
}
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'hj_approve_profiles' ) ) { return; }
	$count = hj_review_pending_count();
	if ( ! $count ) { return; }
	global $menu;
	foreach ( $menu as &$item ) {
		if ( isset( $item[2] ) && $item[2] === 'edit.php?post_type=doctor' ) {
			$item[0] .= ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . (int) $count . '</span></span>';
		}
	}
} , 999 );
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'hj_approve_profiles' ) || ! ( $count = hj_review_pending_count() ) ) { return; }
	echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( '%s پروفایل پزشک در انتظار بررسی است.', number_format_i18n( $count ) ) ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_status=pending&post_type=doctor' ) ) . '">بررسی پروفایل‌ها</a></p></div>';
} );
add_action( 'wp_dashboard_setup', function () {
	if ( current_user_can( 'hj_approve_profiles' ) ) { wp_add_dashboard_widget( 'hj_doctors_review', 'درخواست‌های پزشکان', function () {
		$count = hj_review_pending_count();
		echo '<p>' . esc_html( sprintf( '%s پروفایل در انتظار بررسی است.', number_format_i18n( $count ) ) ) . '</p><p><a href="' . esc_url( admin_url( 'edit.php?post_status=pending&post_type=doctor' ) ) . '">مشاهده درخواست‌ها</a></p>';
	} ); }
} );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'doctor' !== $post->post_type || $new === $old ) { return; }
	if ( 'pending' === $new ) { update_post_meta( $post->ID, 'hj_profile_status', 'pending' ); }
	elseif ( 'publish' === $new ) { update_post_meta( $post->ID, 'hj_profile_status', 'published' ); }
	elseif ( 'draft' === $new && 'rejected' !== get_post_meta( $post->ID, 'hj_profile_status', true ) ) { update_post_meta( $post->ID, 'hj_profile_status', 'draft' ); }
	if ( 'pending' === $new ) {
		$to = get_option( 'admin_email' );
		if ( is_email( $to ) ) {
			$sent = wp_mail( $to, 'پروفایل پزشک در انتظار بررسی', "یک پروفایل پزشک برای بررسی ارسال شده است.\n\n" . get_edit_post_link( $post->ID, 'raw' ) );
			if ( ! $sent ) { hj_doctors_log( 'Review notification email could not be sent; check WordPress mail settings.' ); }
		}
	}
}, 10, 3 );

// ACF provides its own editor when active; otherwise provide equivalent native fields.
add_action( 'add_meta_boxes_doctor', function () {
	if ( ! function_exists( 'acf_get_field_group' ) || ! acf_get_field_group( 'group_hj_doctor_profile' ) ) {
		add_meta_box( 'hj_doctor_fields', 'اطلاعات پروفایل پزشک', 'hj_review_render_fields', 'doctor', 'normal', 'high' );
	}
} );
function hj_review_render_fields( $post ) {
	wp_nonce_field( 'hj_review_fields_' . $post->ID, 'hj_review_nonce' );
	echo '<p>اطلاعات ثبت‌شده پزشک را ویرایش کنید. شهر و تخصص، تصویر شاخص و متن صفحه با ابزارهای همین ویرایشگر قابل تغییر هستند.</p>';
	foreach ( hj_review_fields() as $key => $label ) {
		$value = get_post_meta( $post->ID, $key, true );
		if ( ! is_scalar( $value ) ) { $value = ''; }
		echo '<p><label for="hj-field-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
		if ( in_array( $key, array( 'bio_fa', 'bio_en', 'education', 'surgeries_performed' ), true ) ) {
			echo '<textarea class="widefat" rows="4" id="hj-field-' . esc_attr( $key ) . '" name="hj_review[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input class="widefat" id="hj-field-' . esc_attr( $key ) . '" name="hj_review[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
		}
		echo '</p>';
	}
	$days = apply_filters( 'hj_dash_days', array( 'saturday' => 'شنبه', 'sunday' => 'یکشنبه', 'monday' => 'دوشنبه', 'tuesday' => 'سه‌شنبه', 'wednesday' => 'چهارشنبه', 'thursday' => 'پنجشنبه', 'friday' => 'جمعه' ) );
	$rows = get_post_meta( $post->ID, 'working_hours', true ); $indexed = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) { if ( is_array( $row ) && ! empty( $row['day_of_week'] ) ) { $indexed[$row['day_of_week']] = $row; } }
	echo '<h3>ساعات کاری</h3><input type="hidden" name="hj_hours_present" value="1">';
	foreach ( (array) $days as $key => $label ) {
		$row = isset( $indexed[$key] ) ? $indexed[$key] : array();
		echo '<p><strong>' . esc_html( $label ) . '</strong> <label><input type="checkbox" name="hj_hours[' . esc_attr( $key ) . '][closed]" value="1" ' . checked( ! empty( $row['is_closed'] ), true, false ) . '> تعطیل</label> <label>از <input type="time" name="hj_hours[' . esc_attr( $key ) . '][start]" value="' . esc_attr( substr( $row['start_time'] ?? '', 0, 5 ) ) . '"></label> <label>تا <input type="time" name="hj_hours[' . esc_attr( $key ) . '][end]" value="' . esc_attr( substr( $row['end_time'] ?? '', 0, 5 ) ) . '"></label></p>';
	}
}
add_action( 'save_post_doctor', function ( $id ) {
	if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id ) || ! isset( $_POST['hj_review_nonce'], $_POST['hj_review'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hj_review_nonce'] ) ), 'hj_review_fields_' . $id ) || ! is_array( $_POST['hj_review'] ) ) { return; }
	$input = wp_unslash( $_POST['hj_review'] );
	foreach ( hj_review_fields() as $key => $label ) {
		if ( ! isset( $input[$key] ) || ! is_string( $input[$key] ) ) { continue; }
		$value = in_array( $key, array( 'bio_fa', 'bio_en', 'education', 'surgeries_performed' ), true ) ? sanitize_textarea_field( $input[$key] ) : sanitize_text_field( $input[$key] );
		if ( 'map_link' === $key ) { $value = esc_url_raw( $value, array( 'https' ) ); }
		update_post_meta( $id, $key, $value );
	}
	if ( isset( $_POST['hj_hours_present'] ) ) {
		$days = apply_filters( 'hj_dash_days', array( 'saturday' => 'شنبه', 'sunday' => 'یکشنبه', 'monday' => 'دوشنبه', 'tuesday' => 'سه‌شنبه', 'wednesday' => 'چهارشنبه', 'thursday' => 'پنجشنبه', 'friday' => 'جمعه' ) );
		$hours = isset( $_POST['hj_hours'] ) && is_array( $_POST['hj_hours'] ) ? wp_unslash( $_POST['hj_hours'] ) : array();
		$rows = array(); $valid = true;
		foreach ( (array) $days as $day => $label ) {
			$r = isset( $hours[$day] ) && is_array( $hours[$day] ) ? $hours[$day] : array();
			$start = isset( $r['start'] ) && is_string( $r['start'] ) ? $r['start'] : '';
			$end = isset( $r['end'] ) && is_string( $r['end'] ) ? $r['end'] : '';
			$closed = ! empty( $r['closed'] );
			if ( $closed ) { $rows[] = array( 'day_of_week' => $day, 'is_closed' => 1, 'start_time' => '', 'end_time' => '' ); continue; }
			if ( '' === $start && '' === $end ) { continue; }
			if ( ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end ) || $start >= $end ) { $valid = false; break; }
			$rows[] = array( 'day_of_week' => $day, 'is_closed' => 0, 'start_time' => $start . ':00', 'end_time' => $end . ':00' );
		}
		if ( $valid ) { update_post_meta( $id, 'working_hours', $rows ); }
	}
}, 10, 1 );

add_action( 'add_meta_boxes_doctor', function () {
	if ( current_user_can( 'hj_approve_profiles' ) ) { add_meta_box( 'hj_review_documents', 'مدارک ثبت‌شده (خصوصی)', 'hj_review_documents', 'doctor', 'side' ); }
} );
function hj_review_documents( $post ) {
	$docs = get_post_meta( $post->ID, 'hj_private_docs', true );
	if ( ! is_array( $docs ) || ! $docs ) { echo '<p>مدرکی ثبت نشده است.</p>'; return; }
	echo '<ul>';
	foreach ( $docs as $doc ) {
		if ( empty( $doc['id'] ) ) { continue; }
		$id = sanitize_key( $doc['id'] );
		$url = add_query_arg( array( 'action' => 'hj_doc_download', 'pid' => $post->ID, 'doc' => $id, '_wpnonce' => wp_create_nonce( 'hj_doc_dl_' . $post->ID . '_' . $id ) ), admin_url( 'admin-ajax.php' ) );
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $doc['name'] ?? 'مدرک' ) . '</a></li>';
	}
	echo '</ul>';
}
// Keep the dashboard photo in sync when an editor changes the featured image.
function hj_review_sync_photo( $meta_id, $post_id, $key, $value = null ) {
	if ( '_thumbnail_id' !== $key || 'doctor' !== get_post_type( $post_id ) ) { return; }
	$thumbnail = (int) get_post_thumbnail_id( $post_id );
	if ( $thumbnail ) { update_post_meta( $post_id, 'profile_photo', $thumbnail ); }
	else { delete_post_meta( $post_id, 'profile_photo' ); }
}
add_action( 'added_post_meta', 'hj_review_sync_photo', 10, 4 );
add_action( 'updated_post_meta', 'hj_review_sync_photo', 10, 4 );
add_action( 'deleted_post_meta', 'hj_review_sync_photo', 10, 4 );

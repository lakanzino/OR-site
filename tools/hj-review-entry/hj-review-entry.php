<?php
/**
 * Plugin Name: HJ Review Entry
 * Description: Simple administrator entry of a review for an existing surgeon.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Hamyar Jarahi
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'HJ_Review_Entry' ) ) {
final class HJ_Review_Entry {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_hj_review_entry', array( __CLASS__, 'handle' ) );
	}
	public static function menu() {
		$hook = add_management_page( 'ثبت نظر جراح', 'ثبت نظر جراح', 'manage_options', 'hj-review-entry', array( __CLASS__, 'page' ) );
		if ( $hook ) { add_action( 'load-' . $hook, 'nocache_headers' ); }
	}
	private static function error( $message ) { return new WP_Error( 'hj_review_entry', $message ); }
	private static function ident( $name ) { return is_string( $name ) && preg_match( '/\A[A-Za-z0-9_]+\z/', $name ); }
	private static function pick( $columns, $candidates ) { foreach ( $candidates as $name ) { if ( isset( $columns[ $name ] ) ) { return $name; } } return ''; }
	private static function valid_doctor( $id ) { return $id > 0 && get_post_type( $id ) === 'doctor' && get_post_status( $id ) === 'publish'; }
	private static function integer_type( $type ) { return (bool) preg_match( '/\A(?:tinyint|smallint|mediumint|int|integer|bigint)(?:\(\d+\))?(?: unsigned)?\z/i', trim( $type ) ); }
	private static function text_limit( $type ) {
		$type = strtolower( trim( $type ) );
		if ( preg_match( '/\A(?:varchar|char)\((\d+)\)\z/', $type, $m ) ) { return (int) $m[1]; }
		if ( $type === 'tinytext' ) { return 255; }
		if ( in_array( $type, array( 'text', 'mediumtext', 'longtext' ), true ) ) { return 65535; }
		return 0;
	}
	/** Fail closed unless the vote bridge and public profile use the same table and columns. */
	private static function inspect() {
		global $wpdb;
		if ( ! is_callable( array( 'HJ_Doctors_Rating_Bridge', 'schema' ) ) ) { return self::error( 'افزونه همیار پزشک‌ها یا جدول رأی در دسترس نیست.' ); }
		$bridge = HJ_Doctors_Rating_Bridge::schema(); $table = $wpdb->prefix . 'hj_doctor_votes';
		if ( ! self::ident( $table ) || ! is_array( $bridge ) || empty( $bridge['ok'] ) || ( $bridge['table'] ?? '' ) !== $table || ! is_array( $bridge['map'] ?? null ) ) { return self::error( 'جدول رأی با پروفایل سازگار نیست.' ); }
		$columns_raw = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		if ( $wpdb->last_error !== '' || ! is_array( $columns_raw ) || ! $columns_raw ) { return self::error( 'ساختار جدول رأی قابل بررسی نیست.' ); }
		$columns = array();
		foreach ( $columns_raw as $col ) {
			$name = $col['Field'] ?? null;
			if ( ! self::ident( $name ) || ! isset( $col['Type'], $col['Null'], $col['Extra'] ) ) { return self::error( 'ساختار جدول پشتیبانی نمی‌شود.' ); }
			$columns[ $name ] = $col;
		}
		$doctor = self::pick( $columns, array( 'doctor_id', 'post_id', 'doctor' ) );
		$rating = self::pick( $columns, array( 'rating', 'stars', 'score', 'vote', 'value' ) );
		$comment = self::pick( $columns, array( 'comment', 'review', 'content', 'body', 'text' ) );
		$status = self::pick( $columns, array( 'status', 'is_approved', 'approved', 'state' ) );
		$created = self::pick( $columns, array( 'created_at', 'voted_at', 'date_created', 'created', 'vote_date', 'time' ) );
		if ( ! $doctor || ! $rating || ! $comment || ! $status || ! $created || ( $bridge['map']['doctor_id'] ?? '' ) !== $doctor || ( $bridge['map']['rating'] ?? '' ) !== $rating || ( $bridge['map']['status'] ?? '' ) !== $status || ! self::integer_type( $columns[ $doctor ]['Type'] ) || ! self::integer_type( $columns[ $rating ]['Type'] ) || ! self::text_limit( $columns[ $comment ]['Type'] ) ) { return self::error( 'ستون نظر، امتیاز، وضعیت یا تاریخ سازگار پیدا نشد.' ); }
		$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( $wpdb->last_error !== '' || strtoupper( (string) $engine ) !== 'INNODB' ) { return self::error( 'جدول رأی InnoDB نیست یا قابل بررسی نیست.' ); }
		return compact( 'table', 'columns', 'bridge', 'doctor', 'rating', 'comment', 'status', 'created' );
	}
	private static function date_value( $type, $date ) {
		$type = strtolower( trim( $type ) );
		if ( $type === 'date' ) { return $date; }
		if ( preg_match( '/\A(?:datetime|timestamp)(?:\(\d+\))?\z/', $type ) ) { return $date . ' 12:00:00'; }
		if ( self::integer_type( $type ) ) { return strtotime( $date . ' 12:00:00 UTC' ); }
		return null;
	}
	private static function approved_value( $type ) {
		$type = strtolower( trim( $type ) );
		if ( self::integer_type( $type ) || in_array( $type, array( 'bit', 'bit(1)', 'bool', 'boolean' ), true ) ) { return 1; }
		if ( self::text_limit( $type ) >= 8 ) { return 'approved'; }
		if ( preg_match( '/\Aenum\((.*)\)\z/', $type, $m ) && preg_match( '/(?:\A|,)\s*\x27approved\x27\s*(?:,|\z)/', $m[1] ) ) { return 'approved'; }
		return null;
	}
	public static function insert( $doctor, $name, $text, $rating, $date ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! is_int( $doctor ) || ! self::valid_doctor( $doctor ) ) { return self::error( 'دسترسی یا جراح انتخاب‌شده معتبر نیست.' ); }
		if ( ! is_string( $name ) || ! is_string( $text ) || ! is_int( $rating ) || $rating < 1 || $rating > 5 || ! is_string( $date ) || ! preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $date ) ) { return self::error( 'اطلاعات واردشده معتبر نیست.' ); }
		$name = trim( sanitize_text_field( $name ) ); $text = trim( sanitize_textarea_field( $text ) );
		if ( $name === '' || $text === '' || strlen( $name ) > 300 || strlen( $text ) > 4000 ) { return self::error( 'نام و متن لازم است و نباید بیش از حد طولانی باشد.' ); }
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $date || $date < '1970-01-01' || $date > '2100-12-31' ) { return self::error( 'تاریخ معتبر وارد کنید.' ); }
		$old = $wpdb->suppress_errors( true );
		try {
			$s = self::inspect(); if ( is_wp_error( $s ) ) { return $s; }
			// The existing reader has a fixed author label; name is shown in the comment itself.
			$body = $name . ' — ' . $text;
			if ( strlen( $body ) > self::text_limit( $s['columns'][ $s['comment'] ]['Type'] ) ) { return self::error( 'نام و متن برای ستون نظر طولانی است.' ); }
			$status = self::approved_value( $s['columns'][ $s['status'] ]['Type'] );
			if ( $status === null ) { return self::error( 'وضعیت تأییدشده در جدول پشتیبانی نمی‌شود.' ); }
			$values = array( $s['doctor'] => $doctor, $s['rating'] => $rating, $s['comment'] => $body, $s['status'] => $status );
			foreach ( array_unique( array( $s['created'], 'created_at', 'voted_at' ) ) as $column ) {
				if ( ! isset( $s['columns'][ $column ] ) || isset( $values[ $column ] ) || stripos( $s['columns'][ $column ]['Extra'], 'generated' ) !== false ) { continue; }
				$value = self::date_value( $s['columns'][ $column ]['Type'], $date );
				if ( $value === null ) { return self::error( 'نوع ستون تاریخ پشتیبانی نمی‌شود.' ); }
				$values[ $column ] = $value;
			}
			foreach ( $s['columns'] as $column => $details ) {
				if ( array_key_exists( $column, $values ) || strtoupper( $details['Null'] ) === 'YES' || $details['Default'] !== null || stripos( $details['Extra'], 'auto_increment' ) !== false || stripos( $details['Extra'], 'generated' ) !== false ) { continue; }
				$type = strtolower( trim( $details['Type'] ) );
				if ( $column === ( $s['bridge']['map']['user_id'] ?? '' ) && self::integer_type( $type ) ) { $values[ $column ] = 0; continue; }
				// Some schemas require an IP-hash field. Use a random placeholder, never an IP or its hash.
				if ( $column === ( $s['bridge']['map']['ip_hash'] ?? '' ) && preg_match( '/\A(?:char|varchar)\((\d+)\)\z/', $type, $m ) && (int) $m[1] >= 32 ) { $values[ $column ] = substr( hash( 'sha256', wp_generate_uuid4() . wp_generate_password( 24, true, true ) ), 0, min( 64, (int) $m[1] ) ); continue; }
				if ( in_array( $column, array( 'source', 'reason' ), true ) && self::text_limit( $type ) ) { $values[ $column ] = ''; continue; }
				return self::error( 'ستون اجباری پشتیبانی‌نشده وجود دارد؛ هیچ نظری ثبت نشد.' );
			}
			$result = $wpdb->insert( $s['table'], $values );
			if ( $result !== 1 ) { return self::error( 'ثبت نظر ناموفق بود؛ ساختار یا محدودیت جدول را بررسی کنید.' ); }
			return true;
		} catch ( Throwable $e ) { return self::error( 'خطای ثبت نظر؛ پیش از تکرار، وجود آن را بررسی کنید.' ); }
		finally { $wpdb->suppress_errors( $old ); }
	}
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'روش نامعتبر.', '', array( 'response' => 405 ) ); }
		check_admin_referer( 'hj_review_entry' );
		$doctor_raw = isset( $_POST['doctor'] ) && is_string( $_POST['doctor'] ) ? wp_unslash( $_POST['doctor'] ) : '';
		$rating_raw = isset( $_POST['rating'] ) && is_string( $_POST['rating'] ) ? wp_unslash( $_POST['rating'] ) : '';
		$doctor = preg_match( '/\A[1-9][0-9]{0,18}\z/', $doctor_raw ) ? (int) $doctor_raw : 0;
		$rating = preg_match( '/\A[1-5]\z/', $rating_raw ) ? (int) $rating_raw : 0;
		$name = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
		$text = isset( $_POST['comment'] ) && is_string( $_POST['comment'] ) ? wp_unslash( $_POST['comment'] ) : '';
		$date = isset( $_POST['date'] ) && is_string( $_POST['date'] ) ? wp_unslash( $_POST['date'] ) : '';
		$result = self::insert( $doctor, $name, $text, $rating, $date );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 'ثبت نظر', array( 'response' => 400, 'back_link' => true ) ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'hj-review-entry', 'saved' => 1 ), admin_url( 'tools.php' ) ) ); exit;
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی غیرمجاز.', '', array( 'response' => 403 ) ); }
		$doctors = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?><div class="wrap"><h1>ثبت نظر جراح</h1>
		<?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) : ?><div class="notice notice-success"><p>نظر ثبت شد.</p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="hj_review_entry"><?php wp_nonce_field( 'hj_review_entry' ); ?>
		<p><label for="hj-re-doctor">جراح</label><br><select id="hj-re-doctor" name="doctor" required><option value="">انتخاب کنید</option><?php foreach ( $doctors as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->ID ); ?>"><?php echo esc_html( get_the_title( $doctor ) ); ?></option><?php endforeach; ?></select></p>
		<p><label for="hj-re-name">نام</label><br><input id="hj-re-name" type="text" name="name" class="regular-text" maxlength="100" required></p>
		<p><label for="hj-re-comment">متن نظر</label><br><textarea id="hj-re-comment" name="comment" class="large-text" rows="5" required></textarea></p>
		<p><label for="hj-re-rating">امتیاز</label><br><select id="hj-re-rating" name="rating" required><option value="">انتخاب کنید</option><?php for ( $i = 1; $i <= 5; $i++ ) : ?><option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option><?php endfor; ?></select></p>
		<p><label for="hj-re-date">تاریخ</label><br><input id="hj-re-date" type="date" name="date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" min="1970-01-01" max="2100-12-31" required></p>
		<?php submit_button( 'ثبت رأی' ); ?></form></div><?php
	}
}
HJ_Review_Entry::init();
}

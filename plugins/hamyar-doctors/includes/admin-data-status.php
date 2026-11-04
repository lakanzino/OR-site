<?php
/** Admin-only, non-mutating summary of doctor data. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', 'hj_doctors_data_status_menu' );
function hj_doctors_data_status_menu() {
	$hook = add_submenu_page(
		'edit.php?post_type=doctor',
		'وضعیت داده‌های پزشکان',
		'وضعیت داده‌ها',
		'manage_options',
		'hj-doctors-data-status',
		'hj_doctors_data_status_page'
	);
	if ( $hook ) { add_action( 'load-' . $hook, 'nocache_headers' ); }
}

/** Summarize the rating bridge's detected mapping without reading vote rows. */
function hj_doctors_vote_schema_summary( $schema ) {
	$summary = array( 'table' => 'نامعلوم یا شناسایی نشد', 'id' => 'نامعلوم', 'doctor_id' => 'نامعلوم', 'rating' => 'نامعلوم', 'status' => 'نامعلوم' );
	if ( ! is_array( $schema ) || empty( $schema['table'] ) || ! is_string( $schema['table'] ) ) { return $summary; }
	$summary['table'] = 'توسط پل رأی شناسایی شد';
	if ( ! isset( $schema['map'], $schema['cols'] ) || ! is_array( $schema['map'] ) || ! is_array( $schema['cols'] ) ) { return $summary; }
	foreach ( array( 'id', 'doctor_id', 'rating', 'status' ) as $key ) {
		if ( ! array_key_exists( $key, $schema['map'] ) || ! is_string( $schema['map'][ $key ] ) ) { continue; }
		$column = $schema['map'][ $key ];
		if ( '' === $column ) { $summary[ $key ] = 'شناسایی نشد'; }
		elseif ( in_array( $column, $schema['cols'], true ) ) { $summary[ $key ] = 'شناسایی شد'; }
	}
	return $summary;
}

/** Render a small read-only summary, separately from the seed/repair tools. */
function hj_doctors_data_status_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی غیرمجاز.', 'hamyar-doctors' ), '', array( 'response' => 403 ) );
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
	if ( 'GET' !== $method ) {
		wp_die( esc_html__( 'این گزارش فقط با درخواست مشاهده قابل دسترسی است.', 'hamyar-doctors' ), '', array( 'response' => 405 ) );
	}
	nocache_headers();
	global $wpdb;
	$doctor_type = HJ_DOCTORS_CPT;
	$statuses = array( 'publish' => 'منتشرشده', 'pending' => 'در انتظار بررسی', 'draft' => 'پیش‌نویس', 'private' => 'خصوصی', 'trash' => 'زباله‌دان' );
	// Fixed status set, no user-provided SQL. Unknown statuses are not included.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_status, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending','draft','private','trash') GROUP BY post_status",
		$doctor_type
	), ARRAY_A );
	$counts = array_fill_keys( array_keys( $statuses ), 0 );
	$counts_ok = is_array( $rows ) && '' === $wpdb->last_error;
	if ( $counts_ok ) {
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row['post_status'] ] ) ) { $counts[ $row['post_status'] ] = (int) $row['total']; }
		}
	}
	$marked = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND pm.meta_key = %s",
		$doctor_type,
		'_hj_demo_doctor'
	) );
	$marked_ok = null !== $marked && '' === $wpdb->last_error;
	$missing = new stdClass();
	$options = array(
		'hj_iran_locations_seeded_v1' => 'نشان اجرای داده‌های مرجع',
		'hj_iran_locations_repaired_v2' => 'نشان تعمیر داده‌های مرجع',
		'hj_demo_doctors_seeded_v1' => 'نشان داده‌های نمونه',
	);
	$hooks = array(
		'hj_iran_locations_auto_seed' => 'سید خودکار داده‌های مرجع',
		'hj_iran_locations_auto_repair' => 'تعمیر خودکار داده‌های مرجع',
		'hj_demo_doctors_auto_seed' => 'سید خودکار پزشکان نمونه',
	);
	$taxonomies = array( 'province' => 'استان', 'city' => 'شهر', 'specialty_type' => 'گروه تخصص', 'specialty' => 'تخصص' );
	$stored_version = get_option( HJ_DOCTORS_VERSION_OPTION, $missing );
	$vote_schema = null;
	if ( is_callable( array( 'HJ_Doctors_Rating_Bridge', 'schema' ) ) ) {
		try { $vote_schema = HJ_Doctors_Rating_Bridge::schema(); }
		catch ( Throwable $error ) { $vote_schema = null; }
	}
	$vote_summary = hj_doctors_vote_schema_summary( $vote_schema );
	$vote_labels = array( 'table' => 'جدول رأی', 'id' => 'ستون شناسه ردیف', 'doctor_id' => 'ستون شناسه پزشک', 'rating' => 'ستون امتیاز', 'status' => 'ستون وضعیت' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'وضعیت داده‌های پزشکان', 'hamyar-doctors' ); ?></h1>
		<p><?php echo esc_html__( 'این گزارش فقط اطلاعات کلی را نمایش می‌دهد و دکمه‌ای برای ایجاد، تغییر یا حذف داده ندارد. دیگر بخش‌های وردپرس ممکن است در همین درخواست عملیات خود را انجام دهند.', 'hamyar-doctors' ); ?></p>
		<h2><?php echo esc_html__( 'نسخه و نشان‌های موجود', 'hamyar-doctors' ); ?></h2>
		<table class="widefat striped"><tbody>
		<tr><th scope="row"><?php echo esc_html__( 'نسخه افزونه', 'hamyar-doctors' ); ?></th><td><?php echo esc_html( HJ_DOCTORS_VERSION ); ?></td></tr>
		<tr><th scope="row"><?php echo esc_html__( 'نسخه ثبت‌شده در پایگاه داده', 'hamyar-doctors' ); ?></th><td><?php echo esc_html( is_scalar( $stored_version ) ? (string) $stored_version : 'ناموجود یا نامعتبر' ); ?></td></tr>
		<?php foreach ( $options as $name => $label ) : ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( get_option( $name, $missing ) === $missing ? 'ثبت نشده' : 'ثبت شده' ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<h2><?php echo esc_html__( 'اتصال قلاب‌های خودکار به admin_init در این درخواست', 'hamyar-doctors' ); ?></h2>
		<table class="widefat striped"><tbody>
		<?php foreach ( $hooks as $callback => $label ) : ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( false === has_action( 'admin_init', $callback ) ? 'در این قلاب متصل نیست' : 'در این قلاب متصل است' ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p><?php echo esc_html__( 'این بررسی، فراخوانی مستقیم یا اتصال‌های دیگر را رد نمی‌کند.', 'hamyar-doctors' ); ?></p>
		<h2><?php echo esc_html__( 'تعداد پروفایل‌ها', 'hamyar-doctors' ); ?></h2>
		<table class="widefat striped"><tbody>
		<?php foreach ( $statuses as $status => $label ) : ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $counts_ok ? (string) $counts[ $status ] : 'نامعلوم' ); ?></td></tr>
		<?php endforeach; ?>
		<tr><th scope="row"><?php echo esc_html__( 'دارای نشانگر نمونه، در همه وضعیت‌ها', 'hamyar-doctors' ); ?></th><td><?php echo esc_html( $marked_ok ? (string) (int) $marked : 'نامعلوم' ); ?></td></tr>
		</tbody></table>
		<p><?php echo esc_html__( 'نشانگر نمونه به‌تنهایی ماهیت یا مالکیت داده را ثابت نمی‌کند؛ نبود آن نیز به معنی واقعی‌بودن قطعی نیست. وضعیت‌های دیگر در ردیف‌های بالا شمارش نشده‌اند.', 'hamyar-doctors' ); ?></p>
		<h2><?php echo esc_html__( 'تعداد کل عنوان‌های مکانی و تخصصی', 'hamyar-doctors' ); ?></h2>
		<table class="widefat striped"><tbody>
		<?php foreach ( $taxonomies as $taxonomy => $label ) : ?>
			<?php $total = taxonomy_exists( $taxonomy ) ? wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : null; ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( null !== $total && ! is_wp_error( $total ) && is_numeric( $total ) ? (string) (int) $total : 'نامعلوم' ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p><?php echo esc_html__( 'این اعداد تعداد عنوان‌ها هستند، نه تعداد پزشکان و نه نشان‌دهندهٔ صلاحیت جراحی.', 'hamyar-doctors' ); ?></p>
		<h2><?php echo esc_html__( 'ساختار رأی از دید پل فعلی', 'hamyar-doctors' ); ?></h2>
		<table class="widefat striped"><tbody>
		<?php foreach ( $vote_labels as $key => $label ) : ?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $vote_summary[ $key ] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p><?php echo esc_html__( 'این گزارش فقط تشخیص پل فعلی را نشان می‌دهد؛ «نامعلوم یا شناسایی نشد» می‌تواند ناشی از نبود جدول یا خطای بررسی باشد. شناخته‌شدن ستون شناسه، یکتا بودن آن را ثابت نمی‌کند. هیچ رأی یا اطلاعات رأی‌دهنده خوانده یا تغییر داده نمی‌شود؛ این صفحه ابزار مدیریت رأی نیست.', 'hamyar-doctors' ); ?></p>
	</div>
	<?php
}

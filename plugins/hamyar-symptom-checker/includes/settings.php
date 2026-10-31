<?php
/**
 * The owner's controls.
 *
 * Everything a visitor reads before the first question — the title, the one
 * sentence under it, the warning in the yellow box, whether the source list is
 * shown, the age default and the emergency number — is an option here, saved
 * with the Settings API and read back by the front end. Nothing about a visitor
 * is stored: these are site settings, not answers.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HJ_SC_SETTINGS_OPTION', 'hj_sc_display_settings' );

/**
 * The texts the owner may change, with the wording the plugin ships.
 *
 * @return array<string,string|int|bool>
 */
function hjsc_settings_defaults() {
	return array(
		'enabled'         => true,
		'title_fa'        => 'این یک ربات اختصاصی ارزیابی احتمالی بیماری در فرد است.',
		'lead_fa'         => 'هرچه اطلاعات و علامت‌های بیماری خود را درست انتخاب کنید، جواب دقیق‌تری دریافت می‌کنید.',
		'warning_fa'      => 'این ارزیابی هرگز جای معاینهٔ پزشکی نیست و فقط احتمال بیماری را به شما می‌گوید؛ حرف قطعی و صحیح را از پزشک درمانگر بخواهید.',
		'show_sources'    => true,
		'sources_title'   => 'منابع علمی',
		'min_age'         => 18,
		'default_age'     => 30,
		'emergency_note'  => 'اگر درد شدید یا بدحالی وجود دارد، همین حالا با اورژانس تماس بگیرید.',
	);
}

/**
 * Stored settings merged over the defaults.
 *
 * A missing key is the default, never an empty string: an option half-saved by
 * an interrupted request must not leave the page blank.
 *
 * @return array<string,string|int|bool>
 */
function hjsc_settings() {
	$stored = get_option( HJ_SC_SETTINGS_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	$merged = array_merge( hjsc_settings_defaults(), $stored );

	$merged['enabled']      = (bool) $merged['enabled'];
	$merged['show_sources'] = (bool) $merged['show_sources'];
	$merged['min_age']      = hjsc_clamp_age( $merged['min_age'], 1, 100, 18 );
	$merged['default_age']  = hjsc_clamp_age( $merged['default_age'], $merged['min_age'], 100, 30 );

	foreach ( array( 'title_fa', 'lead_fa', 'warning_fa', 'sources_title', 'emergency_note' ) as $key ) {
		$value                = is_scalar( $merged[ $key ] ) ? (string) $merged[ $key ] : '';
		$merged[ $key ]       = '' === trim( $value ) ? hjsc_settings_defaults()[ $key ] : $value;
	}

	return $merged;
}

/**
 * One setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function hjsc_setting( $key ) {
	$settings = hjsc_settings();
	return $settings[ $key ] ?? null;
}

/**
 * Integer inside a range, or the fallback.
 *
 * @param mixed $value    Raw value.
 * @param int   $min      Lower bound.
 * @param int   $max      Upper bound.
 * @param int   $fallback Value used when the input is not a number.
 * @return int
 */
function hjsc_clamp_age( $value, $min, $max, $fallback ) {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}
	$number = (int) round( (float) $value );
	return max( $min, min( $max, $number ) );
}

/**
 * Sanitise everything the form sends before it is stored.
 *
 * @param mixed $input Raw option value.
 * @return array<string,string|int|bool>
 */
function hjsc_sanitize_settings( $input ) {
	$defaults = hjsc_settings_defaults();
	$input    = is_array( $input ) ? $input : array();
	$clean    = array();

	$clean['enabled']      = ! empty( $input['enabled'] );
	$clean['show_sources'] = ! empty( $input['show_sources'] );

	foreach ( array( 'title_fa', 'lead_fa', 'warning_fa', 'sources_title', 'emergency_note' ) as $key ) {
		$value          = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
		$value          = trim( wp_kses_post( $value ) );
		$clean[ $key ]  = '' === trim( wp_strip_all_tags( $value ) ) ? $defaults[ $key ] : $value;
	}

	$clean['min_age']     = hjsc_clamp_age( $input['min_age'] ?? $defaults['min_age'], 1, 100, $defaults['min_age'] );
	$clean['default_age'] = hjsc_clamp_age( $input['default_age'] ?? $defaults['default_age'], $clean['min_age'], 100, $defaults['default_age'] );

	return $clean;
}

/**
 * Register the option, its section and its fields.
 */
function hjsc_register_settings() {
	register_setting(
		'hjsc_settings',
		HJ_SC_SETTINGS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'hjsc_sanitize_settings',
			'default'           => hjsc_settings_defaults(),
		)
	);

	add_settings_section(
		'hjsc_section_texts',
		'متن‌های صفحهٔ اول',
		'hjsc_section_texts_intro',
		'hjsc_settings'
	);

	$text_fields = array(
		'title_fa'      => array( 'عنوان صفحه', 'input' ),
		'lead_fa'       => array( 'جملهٔ زیر عنوان', 'input' ),
		'warning_fa'    => array( 'متن کادر زرد', 'textarea' ),
		'sources_title' => array( 'عنوان فهرست منابع', 'input' ),
	);
	foreach ( $text_fields as $key => $meta ) {
		add_settings_field(
			'hjsc_' . $key,
			$meta[0],
			'hjsc_field_text',
			'hjsc_settings',
			'hjsc_section_texts',
			array(
				'key'  => $key,
				'type' => $meta[1],
			)
		);
	}

	add_settings_field(
		'hjsc_show_sources',
		'فهرست منابع',
		'hjsc_field_checkbox',
		'hjsc_settings',
		'hjsc_section_texts',
		array(
			'key'   => 'show_sources',
			'label' => 'فهرست منابع علمی روی صفحهٔ اول دیده شود',
		)
	);

	add_settings_section(
		'hjsc_section_behaviour',
		'سن و ایمنی',
		'hjsc_section_behaviour_intro',
		'hjsc_settings'
	);

	foreach ( array(
		'min_age'        => 'کمترین سن مجاز',
		'default_age'    => 'سنی که کادر با آن باز می‌شود',
		'emergency_note' => 'جملهٔ هشدار فوری',
	) as $key => $label ) {
		add_settings_field(
			'hjsc_' . $key,
			$label,
			'hjsc_field_text',
			'hjsc_settings',
			'hjsc_section_behaviour',
			array(
				'key'  => $key,
				'type' => in_array( $key, array( 'min_age', 'default_age' ), true ) ? 'number' : 'input',
			)
		);
	}

	add_settings_field(
		'hjsc_enabled',
		'وضعیت ابزار',
		'hjsc_field_checkbox',
		'hjsc_settings',
		'hjsc_section_behaviour',
		array(
			'key'   => 'enabled',
			'label' => 'ابزار روشن باشد (اگر خاموش شود، صفحه پیام کوتاه نشان می‌دهد)',
		)
	);
}
add_action( 'admin_init', 'hjsc_register_settings' );

/** Section intro: where the text lands. */
function hjsc_section_texts_intro() {
	echo '<p>این متن‌ها همان چیزی است که بازدیدکننده پیش از هر پرسشی می‌بیند.</p>';
}

/** Section intro: age and safety. */
function hjsc_section_behaviour_intro() {
	echo '<p>سن فقط برای «این ابزار برای شما ساخته شده یا نه» استفاده می‌شود و هیچ‌جا ذخیره نمی‌شود.</p>';
}

/**
 * One text/number field.
 *
 * @param array{key:string,type:string} $args Field arguments.
 */
function hjsc_field_text( $args ) {
	$key   = (string) ( $args['key'] ?? '' );
	$type  = (string) ( $args['type'] ?? 'input' );
	$value = hjsc_setting( $key );
	$name  = HJ_SC_SETTINGS_OPTION . '[' . $key . ']';
	$id    = 'hjsc-field-' . $key;

	if ( 'textarea' === $type ) {
		printf(
			'<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_textarea( (string) $value )
		);
		return;
	}

	printf(
		'<input id="%1$s" name="%2$s" type="%3$s" value="%4$s" class="regular-text" %5$s>',
		esc_attr( $id ),
		esc_attr( $name ),
		'number' === $type ? 'number' : 'text',
		esc_attr( (string) $value ),
		'number' === $type ? 'min="1" max="100" step="1"' : ''
	);
}

/**
 * One checkbox.
 *
 * @param array{key:string,label:string} $args Field arguments.
 */
function hjsc_field_checkbox( $args ) {
	$key   = (string) ( $args['key'] ?? '' );
	$label = (string) ( $args['label'] ?? $key );
	$name  = HJ_SC_SETTINGS_OPTION . '[' . $key . ']';
	printf(
		'<label for="hjsc-field-%1$s"><input id="hjsc-field-%1$s" name="%2$s" type="checkbox" value="1" %3$s> %4$s</label>',
		esc_attr( $key ),
		esc_attr( $name ),
		checked( (bool) hjsc_setting( $key ), true, false ),
		esc_html( $label )
	);
}

/**
 * The settings screen: the form, then a read-only health box in plain words.
 */
function hjsc_settings_render() {
	if ( ! current_user_can( hjsc_admin_capability() ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	?>
	<div class="wrap">
		<h1>تنظیمات نمایش «دستیار نشانه‌ها»</h1>
		<p>هر تغییری اینجا بدهید، همان لحظه روی صفحه اعمال می‌شود؛ نیازی به نسخهٔ تازهٔ افزونه نیست.</p>
		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<?php
			settings_fields( 'hjsc_settings' );
			do_settings_sections( 'hjsc_settings' );
			submit_button( 'ذخیرهٔ تنظیمات' );
			?>
		</form>
		<?php hjsc_settings_health_box(); ?>
	</div>
	<?php
}

/**
 * A short, honest health summary: which page is live, which bundle is served.
 */
function hjsc_settings_health_box() {
	$page_id  = function_exists( 'hjsc_page_id' ) ? hjsc_page_id() : 0;
	$status   = $page_id > 0 ? get_post_status( $page_id ) : '';
	$edit     = $page_id > 0 ? get_edit_post_link( $page_id, '' ) : '';
	$view     = $page_id > 0 ? get_permalink( $page_id ) : '';
	$bundle   = function_exists( 'hjsc_bundle_status' ) ? hjsc_bundle_status() : array();
	?>
	<hr>
	<h2>وضعیت فعلی</h2>
	<table class="widefat striped" style="max-width:760px">
		<tbody>
		<tr>
			<th>نسخهٔ افزونه</th>
			<td><?php echo esc_html( HJ_SC_VERSION ); ?></td>
		</tr>
		<tr>
			<th>برگهٔ ابزار</th>
			<td>
				<?php if ( $page_id > 0 ) : ?>
					<?php echo esc_html( 'شناسه ' . $page_id . ' — ' . ( 'publish' === $status ? 'منتشرشده' : $status ) ); ?>
					<?php if ( $edit ) : ?> · <a href="<?php echo esc_url( $edit ); ?>">ویرایش برگه</a><?php endif; ?>
					<?php if ( $view ) : ?> · <a href="<?php echo esc_url( $view ); ?>">دیدن صفحه</a><?php endif; ?>
				<?php else : ?>
					برگه‌ای ثبت نشده است؛ افزونه را یک بار غیرفعال و فعال کنید.
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th>بستهٔ دانش</th>
			<td>
				<?php
				if ( is_array( $bundle ) && ! empty( $bundle['source'] ) ) {
					echo esc_html(
						'منبع: ' . (string) $bundle['source']
						. ' · نسخه: ' . (string) ( $bundle['version'] ?? '—' )
						. ' · اثر انگشت: ' . (string) ( $bundle['hash'] ?? '—' )
					);
				} else {
					echo 'نامشخص';
				}
				?>
			</td>
		</tr>
		<tr>
			<th>آدرس فایل رابط</th>
			<td><code dir="ltr"><?php echo esc_html( function_exists( 'hjsc_asset_url' ) ? hjsc_asset_url( 'ui/app.js' ) : '' ); ?></code></td>
		</tr>
		</tbody>
	</table>
	<p class="description">اگر صفحه بالا نیامد، همین آدرس را در مرورگر باز کنید: اگر ۴۰۴ داد، فایل‌های افزونه کامل بارگذاری نشده‌اند.</p>
	<?php
}

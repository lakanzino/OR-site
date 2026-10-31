<?php
/**
 * Plugin Name: همیار جراحی — دستیار نشانه‌ها (پیش‌نمایش)
 * Description: برگهٔ مستقل و غیرعمومی برای اجرای «دستیار نشانه‌ها» روی موتور مستقل پروژه. بستهٔ دانش و موتور از داخل همین افزونه بارگذاری می‌شوند و پاسخ‌های کاربر هیچ‌گاه به سرور فرستاده یا ذخیره نمی‌شوند.
 * Version: 0.1.1
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: همیار جراحی
 * License: GPL-2.0-or-later
 * Text Domain: hamyar-symptom-checker
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HJ_SC_VERSION', '0.1.1' );
define( 'HJ_SC_FILE', __FILE__ );
define( 'HJ_SC_DIR', plugin_dir_path( __FILE__ ) );
define( 'HJ_SC_URL', plugin_dir_url( __FILE__ ) );
define( 'HJ_SC_PAGE_OPTION', 'hj_symptom_checker_page_id' );
define( 'HJ_SC_PAGE_SLUG', 'symptom-checker' );

require_once HJ_SC_DIR . 'includes/mapping.php';
require_once HJ_SC_DIR . 'includes/page.php';

register_activation_hook( __FILE__, 'hj_sc_activate' );

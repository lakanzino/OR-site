<?php
/**
 * Plugin Name:       Hamyar Jarahi — Sentinel (خودپایشگر سلامت محتوا)
 * Plugin URI:        https://github.com/lakanzino/OR-site
 * Description:       ممیزی خودکار استانداردهای YMYL روی مقاله‌های جراحی: باکس هشدار، منابع رده‌بندی‌شده، بخش پرسش‌ها، خلاصهٔ بالینی و تازگی محتوا. هیچ محتوایی را خودکار منتشر نمی‌کند؛ اصلاح‌ها به‌شکل «پیش‌نویس» ساخته می‌شوند و بازیابی ۴۰۴ فقط با نقشهٔ تأییدشده انجام می‌شود.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            OR Site
 * License:           GPL-2.0-or-later
 * Text Domain:       hamyar-sentinel
 *
 * @package Hamyar_Sentinel
 *
 * سه محدودیت آگاهانه (ADR-027):
 *  1. Sentinel هیچ‌گاه به نوشتهٔ منتشرشده نمی‌نویسد. «اصلاح» یعنی ساخت یک
 *     پیش‌نویس جدا با برچسب تأیید انسانی.
 *  2. تشخیص پزشک بازبین حذف شده است (ADR-020)؛ به‌جایش «تازگی و کامل‌بودن»
 *     سنجیده می‌شود.
 *  3. ریدایرکت ۴۰۴ عمومی وجود ندارد؛ فقط مسیرهایی که در نقشهٔ تأییدشده هست.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HJS_VERSION', '1.0.0' );
define( 'HJS_DIR', __DIR__ );
define( 'HJS_CAP', 'edit_others_posts' );
define( 'HJS_REDIRECT_OPTION', 'hjs_redirect_map' );
define( 'HJS_MISS_OPTION', 'hjs_404_log' );

require_once HJS_DIR . '/inc/class-hjs-audit.php';
require_once HJS_DIR . '/inc/class-hjs-admin.php';
require_once HJS_DIR . '/inc/class-hjs-recovery.php';

/**
 * Boot the three engines.
 *
 * @return void
 */
function hjs_boot(): void {
	HJS_Audit::instance();
	HJS_Admin::instance();
	HJS_Recovery::instance();
}
add_action( 'plugins_loaded', 'hjs_boot' );

/**
 * Post types the plugin audits. Filterable so a new section can opt in.
 *
 * @return array<int,string>
 */
function hjs_post_types(): array {
	return (array) apply_filters( 'hjs_post_types', array( 'surgery', 'patient_guide' ) );
}

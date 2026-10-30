<?php
/**
 * Plugin Name: همیار جراحی — بررسی همگام‌سازی پروفایل
 * Description: نمایش نسخه و اثرانگشت فایل همگام‌سازی، بدون تغییر فایل یا محتوای سایت.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: hj-profile-sync-inspector
 */
defined( 'ABSPATH' ) || exit;

/** Read-only: no PHP source, profiles, answers or database data leave the server. */
function hjsi_inspect_file( $path ) {
    $result = array( 'status' => 'unreadable', 'sha256' => '', 'old_count' => 0, 'fixed_count' => 0 );
    if ( ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > 1048576 ) { return $result; }
    $source = file_get_contents( $path );
    if ( ! is_string( $source ) ) { return $result; }
    $old = "update_post_meta( \$post_id, '_hj_disease_profile', wp_json_encode( \$profile, JSON_UNESCAPED_UNICODE ) );";
    $fixed = "update_post_meta( \$post_id, '_hj_disease_profile', wp_slash( wp_json_encode( \$profile, JSON_UNESCAPED_UNICODE ) ) );";
    $result['sha256'] = hash( 'sha256', $source );
    $result['old_count'] = substr_count( $source, $old );
    $result['fixed_count'] = substr_count( $source, $fixed );
    if ( 'e2f47715281ceaabaf49bf9da079de3dba446038126df9d8ffcba1b73f3bccf6' === $result['sha256'] && 1 === $result['old_count'] && 0 === $result['fixed_count'] ) {
        $result['status'] = 'matches_original_tag';
    } elseif ( 1 === $result['old_count'] && 0 === $result['fixed_count'] ) {
        $result['status'] = 'original_line_custom_file';
    } elseif ( 0 === $result['old_count'] && 1 === $result['fixed_count'] ) {
        $result['status'] = 'fixed_line_found';
    } else {
        $result['status'] = 'unrecognized_or_ambiguous';
    }
    return $result;
}

add_action( 'admin_menu', function () {
    add_management_page( 'بررسی همگام‌سازی پروفایل', 'بررسی همگام‌سازی پروفایل', 'manage_options', 'hjsi-profile-sync', 'hjsi_page' );
} );

function hjsi_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
    $theme = wp_get_theme();
    $report = hjsi_inspect_file( trailingslashit( get_stylesheet_directory() ) . 'inc/disease-directory.php' );
    $labels = array(
        'matches_original_tag' => 'فایل دقیقاً با فایل تگ اصلی ۰٫۳۰٫۰ منطبق است و خط قدیمی پیدا شد.',
        'original_line_custom_file' => 'خط قدیمی پیدا شد، اما فایل دقیقاً با تگ مرجع یکسان نیست؛ به بررسی بیشتر نیاز دارد.',
        'fixed_line_found' => 'یک خط اصلاح‌شده یافت شد. این نتیجه به‌تنهایی سلامت کل همگام‌سازی را تضمین نمی‌کند.',
        'unrecognized_or_ambiguous' => 'الگو نامشخص یا چندگانه است؛ تغییری نباید خودکار انجام شود.',
        'unreadable' => 'فایل پیدا نشد، قابل خواندن نبود یا بیش از حد مجاز بود.',
    );
    echo '<div class="wrap" dir="rtl"><h1>بررسی همگام‌سازی پروفایل</h1>';
    echo '<p>این صفحه فقط فایل قالب فعال را می‌خواند؛ هیچ تغییری در فایل‌ها، مقاله‌ها یا پروفایل‌ها انجام نمی‌دهد و اطلاعات به سرویس دیگری ارسال نمی‌کند.</p>';
    echo '<table class="widefat striped"><tbody>';
    foreach ( array( 'نام قالب فعال' => $theme->get( 'Name' ), 'نسخهٔ قالب' => $theme->get( 'Version' ), 'وضعیت' => $labels[ $report['status'] ], 'شناسهٔ وضعیت' => $report['status'], 'تعداد خط قدیمی' => $report['old_count'], 'تعداد خط اصلاح‌شده' => $report['fixed_count'], 'SHA-256 فایل' => $report['sha256'] ?: 'ناموجود' ) as $label => $value ) {
        echo '<tr><th>' . esc_html( $label ) . '</th><td><code dir="auto">' . esc_html( (string) $value ) . '</code></td></tr>';
    }
    echo '</tbody></table><p>برای ادامه، تصویر همین صفحه را ارسال کنید. این افزونه اصلاح، انتشار یا تعویض قالب انجام نمی‌دهد.</p></div>';
}

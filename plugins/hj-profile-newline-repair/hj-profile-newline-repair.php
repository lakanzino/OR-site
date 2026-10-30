<?php
/**
 * Plugin Name: همیار جراحی — اصلاح امن خط‌های پروفایل بیماری
 * Description: پیش‌نمایش و اصلاح دستی تنها پروفایل‌های بیماری که دقیقاً با الگوی قابل اثبات تبدیل خط جدید به n همسان هستند.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: hj-profile-newline-repair
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/repair-core.php';

add_action( 'admin_menu', function () {
    add_management_page( 'اصلاح امن خط‌های پروفایل', 'اصلاح خط‌های پروفایل', 'manage_options', 'hjnr-repair', 'hjnr_page' );
} );

function hjnr_posts() {
    return get_posts( array( 'post_type' => 'disease', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
}

function hjnr_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
    echo '<div class="wrap" dir="rtl"><h1>اصلاح امن خط‌های پروفایل بیماری</h1>';
    echo '<p>این ابزار هیچ حرف n را به‌صورت سراسری حذف نمی‌کند. فقط پروفایل‌های منتشرشدهٔ بیماری که تمام فیلدهایشان دقیقاً با مرجع بسته و الگوی مشخص همخوان باشند قابل اصلاح‌اند. نوشته‌ها، لینک‌ها، قالب و انواع دیگر محتوا تغییر نمی‌کنند.</p>';
    echo '<p><strong>پیش از اصلاح:</strong> پشتیبان پایگاه داده و فایل‌ها بگیرید و بازگردانی را بیازمایید. بسته فقط داده‌های مرجع شناخته‌شده را پوشش می‌دهد؛ تعداد آماده، تعداد همهٔ مشکل‌های سایت نیست.</p>';
    if ( isset( $_GET['hjnr_done'] ) ) {
        $done = absint( $_GET['hjnr_done'] ); $skipped = isset( $_GET['hjnr_skipped'] ) ? absint( $_GET['hjnr_skipped'] ) : 0;
        echo '<div class="notice notice-info"><p>انجام‌شده: ' . esc_html( $done ) . '؛ انجام‌نشده یا نیازمند بررسی: ' . esc_html( $skipped ) . '.</p></div>';
    }
    if ( get_option( HJNR_LOCK ) ) { echo '<div class="notice notice-error"><p>عملیات دیگری در حال اجراست یا قفل عملیات قبلی باقی مانده است. پیش از ادامه، وضعیت را توسط مدیر فنی بررسی کنید.</p></div>'; }
    try { hjnr_reference(); $ids = hjnr_posts(); } catch ( Throwable $e ) { echo '<div class="notice notice-error"><p>فایل مرجع قابل استفاده نیست؛ هیچ تغییری مجاز نیست.</p></div></div>'; return; }
    $ready = array(); $backups = array();
    foreach ( $ids as $id ) {
        $item = hjnr_candidate( $id );
        if ( $item ) { $ready[ $id ] = $item; }
        if ( metadata_exists( 'post', $id, HJNR_BACKUP_META ) ) { $backups[] = $id; }
    }
    echo '<h2>پیش‌نمایش بدون تغییر</h2><p>بیماری‌های منتشرشده: ' . esc_html( count( $ids ) ) . '؛ قابل اصلاح با تطبیق کامل: ' . esc_html( count( $ready ) ) . '؛ دارای نسخهٔ پشتیبان این افزونه: ' . esc_html( count( $backups ) ) . '.</p>';
    if ( $ready ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'hjnr_apply' );
        echo '<input type="hidden" name="action" value="hjnr_apply"><table class="widefat striped"><thead><tr><th>بیماری</th><th>فیلدها</th><th>نمونه قبل و بعد</th></tr></thead><tbody>';
        foreach ( $ready as $post_id => $candidate ) {
            $before = json_decode( $candidate['before'], true ); $after = json_decode( $candidate['after'], true );
            $field = $candidate['fields'][0];
            $hash = hash( 'sha256', $candidate['before'] . "\0" . $candidate['after'] );
            echo '<tr><td>' . esc_html( get_the_title( $post_id ) ) . ' <code>' . esc_html( $candidate['id'] ) . '</code><input type="hidden" name="hjnr_items[' . esc_attr( $post_id ) . ']" value="' . esc_attr( $hash ) . '"></td>';
            echo '<td><code>' . esc_html( implode( ', ', $candidate['fields'] ) ) . '</code></td><td><details><summary>نمایش تغییر نمونه</summary><p>قبل:</p><pre style="white-space:pre-wrap;direction:auto">' . esc_html( $before[ $field ] ) . '</pre><p>بعد:</p><pre style="white-space:pre-wrap;direction:auto">' . esc_html( $after[ $field ] ) . '</pre></details></td></tr>';
        }
        echo '</tbody></table><p><label><input type="checkbox" name="hjnr_confirm" value="1" required> پشتیبان دارم و پیش‌نمایش را بررسی کردم.</label></p>';
        submit_button( 'اصلاح موارد کاملاً منطبق', 'primary', 'submit', false ); echo '</form>';
    } else { echo '<p>در حال حاضر مورد واجد شرایط یافت نشد.</p>'; }
    if ( $backups ) {
        echo '<h2>بازگردانی اختیاری</h2><p>فقط اگر پروفایل پس از اصلاح تغییر نکرده باشد، مقدار قبلی همان افزونه بازگردانده می‌شود. نسخهٔ پشتیبان برای ثبت باقی می‌ماند. بازگرداندن ممکن است خرابی متن را دوباره نمایش دهد.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'hjnr_restore' );
        echo '<input type="hidden" name="action" value="hjnr_restore"><select name="post_id" required><option value="">انتخاب بیماری</option>';
        foreach ( $backups as $post_id ) { echo '<option value="' . esc_attr( $post_id ) . '">' . esc_html( get_the_title( $post_id ) ) . ' (#' . esc_html( $post_id ) . ')</option>'; }
        echo '</select> '; submit_button( 'بازگردانی این بیماری', 'secondary', 'submit', false ); echo '</form>';
    }
    echo '</div>';
}

function hjnr_run_operation( $action ) {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'دسترسی ندارید.' ); }
    check_admin_referer( 'hjnr_' . $action );
    if ( 'apply' === $action && ( ! isset( $_POST['hjnr_confirm'] ) || '1' !== $_POST['hjnr_confirm'] ) ) { wp_die( 'تأیید پشتیبان لازم است.' ); }
    $token = wp_generate_uuid4();
    if ( ! add_option( HJNR_LOCK, $token, '', false ) ) { wp_die( 'عملیات دیگری در حال اجراست؛ وضعیت قفل باید بررسی شود.' ); }
    $done = 0; $skipped = 0;
    try {
        if ( 'apply' === $action ) {
            $items = isset( $_POST['hjnr_items'] ) && is_array( $_POST['hjnr_items'] ) ? wp_unslash( $_POST['hjnr_items'] ) : array();
            if ( count( $items ) > 500 ) { throw new RuntimeException( 'too_many_items' ); }
            foreach ( $items as $id => $hash ) {
                $id = absint( $id );
                $candidate = $id ? hjnr_candidate( $id ) : null;
                if ( ! $candidate || ! is_string( $hash ) || ! hash_equals( hash( 'sha256', $candidate['before'] . "\0" . $candidate['after'] ), $hash ) ) { $skipped++; continue; }
                if ( 'repaired' === hjnr_apply( $id, $hash ) ) { $done++; } else { $skipped++; }
            }
        } else {
            $id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
            if ( $id && 'restored' === hjnr_restore( $id ) ) { $done++; } else { $skipped++; }
        }
    } catch ( Throwable $e ) { $skipped++; }
    finally { if ( get_option( HJNR_LOCK ) === $token ) { delete_option( HJNR_LOCK ); } }
    wp_safe_redirect( add_query_arg( array( 'page' => 'hjnr-repair', 'hjnr_done' => $done, 'hjnr_skipped' => $skipped ), admin_url( 'tools.php' ) ) ); exit;
}
add_action( 'admin_post_hjnr_apply', function () { hjnr_run_operation( 'apply' ); } );
add_action( 'admin_post_hjnr_restore', function () { hjnr_run_operation( 'restore' ); } );

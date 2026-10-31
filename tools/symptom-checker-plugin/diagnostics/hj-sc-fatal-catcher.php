<?php
/**
 * Plugin Name: همیار جراحی — ثبت خطای مرگبار (ابزار موقت)
 * Description: متن خطای مرگبار PHP را در فایل <code>wp-content/hj-sc-fatal.log</code> می‌نویسد تا علت «مشکل جدی» هنگام فعال‌سازی معلوم شود. اگر آن را به‌صورت افزونه نصب کردید، <strong>اول خودش را فعال کنید</strong>؛ افزونهٔ غیرفعال در آن درخواست بارگذاری نمی‌شود و چیزی ثبت نمی‌کند.
 * Version: 1.0.0
 * Author: همیار جراحی
 *
 * این فایل یک mu-plugin است: فعال‌سازی لازم ندارد، فقط در پوشهٔ
 * wp-content/mu-plugins/ قرار می‌گیرد و بلافاصله کار می‌کند.
 * هیچ داده‌ای از بازدیدکننده جمع نمی‌کند؛ فقط خطای PHP را در فایل لاگ می‌نویسد.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hj_sc_fatal_catcher_write' ) ) {
	/**
	 * Write one fatal error block to wp-content/hj-sc-fatal.log.
	 *
	 * @param array $error Result of error_get_last().
	 */
	function hj_sc_fatal_catcher_write( $error ) {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__ );
		$log_file    = $content_dir . '/hj-sc-fatal.log';

		// Keep the file small: a single session is enough to find the cause.
		if ( file_exists( $log_file ) && filesize( $log_file ) > 262144 ) {
			@unlink( $log_file );
		}

		$active = array();
		if ( function_exists( 'get_option' ) && did_action( 'plugins_loaded' ) ) {
			$plugin_list = get_option( 'active_plugins' );
			if ( is_array( $plugin_list ) ) {
				$active = $plugin_list;
			}
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '(cli)';

		$lines   = array();
		$lines[] = sprintf( '[%s] FATAL type=%d', gmdate( 'Y-m-d H:i:s' ), (int) $error['type'] );
		$lines[] = 'message: ' . str_replace( "\n", ' ', (string) $error['message'] );
		$lines[] = 'file: ' . (string) $error['file'];
		$lines[] = 'line: ' . (int) $error['line'];
		$lines[] = 'request: ' . $uri;
		$lines[] = 'php: ' . PHP_VERSION . ' | wp: ' . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '?' )
			. ' | checker: ' . ( defined( 'HJ_SC_VERSION' ) ? HJ_SC_VERSION : '(not loaded)' );
		$lines[] = 'active: ' . ( $active ? implode( ', ', $active ) : '(unknown)' );
		$lines[] = '---';

		@file_put_contents( $log_file, implode( "\n", $lines ) . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * Shutdown hook: the fatal error is only visible at the very end.
	 */
	function hj_sc_fatal_catcher() {
		$error = error_get_last();
		if ( ! is_array( $error ) ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
		if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
			return;
		}

		hj_sc_fatal_catcher_write( $error );
	}

	register_shutdown_function( 'hj_sc_fatal_catcher' );
}

<?php
/**
 * بخش ۴ (فایل ۲): primitiveهای امنیتی که بقیهٔ بخش‌ها صدا می‌زنند.
 *
 * کلاس HJ_Doctors_Roles (فایل roles.php) برای سقف حجم و rate limit
 * بارگذاری نقش پزشک به همین دو تابع تکیه می‌کند. هر دو مستقل، نگهبان‌دار
 * و بی‌صدا هستند: هیچ‌چیز به debug.log نوشته نمی‌شود.
 *
 * @package HamyarDoctors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hj_sec_max_upload_bytes' ) ) {
	/**
	 * سقف حجم بارگذاری برای نقش پزشک (بایت؛ پیش‌فرض ۲ مگابایت).
	 *
	 * @return int
	 */
	function hj_sec_max_upload_bytes() {
		$max = (int) apply_filters( 'hj_sec_max_upload_bytes', 2 * 1024 * 1024 );

		return $max > 0 ? $max : 2 * 1024 * 1024;
	}
}

if ( ! function_exists( 'hj_sec_rate_limit' ) ) {
	/**
	 * rate limit ساده با transient به ازای کاربر واردشده یا IP مهمان.
	 *
	 * شمارش با هر صدا زدن زیاد می‌شود و پس از پایان بازه خودکار صفر می‌شود
	 * (انقضای transient). هیچ جدولی ساخته نمی‌شود و چیزی لاگ نمی‌شود.
	 *
	 * @param string       $action نام عمل مانند 'doctor_upload'.
	 * @param int          $limit  حداکثر تعداد مجاز در بازه.
	 * @param int          $window بازه به ثانیه مانند HOUR_IN_SECONDS.
	 * @param bool|string  $scope  true = کلید IP حتی برای کاربر لاگین‌شده
	 *                             (ثبت‌نام)؛ رشتهٔ غیرخالی = کلید سفارشی
	 *                             (مثلاً شناسهٔ کاربر از سمت داشبورد)؛
	 *                             false = کاربر لاگین وگرنه IP.
	 * @return bool true یعنی هنوز جا هست؛ false یعنی سقف رد شده.
	 */
	function hj_sec_rate_limit( $action, $limit, $window, $scope = false ) {
		$limit  = max( 1, (int) $limit );
		$window = max( 1, (int) $window );

		$user_id = (int) get_current_user_id();
		if ( is_string( $scope ) && '' !== $scope ) {
			$who = 'k' . md5( $scope );
		} elseif ( true === $scope || ! $user_id ) {
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$who = 'ip' . md5( $ip );
		} else {
			$who = 'u' . $user_id;
		}

		$key  = 'hj_sec_rl_' . sanitize_key( (string) $action ) . '_' . $who;
		$hits = (int) get_transient( $key );

		if ( $hits >= $limit ) {
			return false;
		}

		set_transient( $key, $hits + 1, $window );

		return true;
	}
}

if ( ! function_exists( 'hj_sec_clean' ) ) {
	/**
	 * پاک‌سازی ورودی متنی بر اساس نوع فیلد.
	 *
	 * @param string $raw  مقدار خام.
	 * @param string $type یکی از text/textarea/url.
	 * @return string
	 */
	function hj_sec_clean( $raw, $type = 'text' ) {
		$raw = (string) $raw;
		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $raw );
		}
		if ( 'url' === $type ) {
			return esc_url_raw( $raw );
		}
		if ( 'numeric' === $type ) {
			return preg_replace( '/[^0-9]/', '', hj_to_en_digits( $raw ) );
		}
		return sanitize_text_field( $raw );
	}
}

if ( ! function_exists( 'hj_sec_private_dir' ) ) {
	/**
	 * پوشهٔ خصوصی مدارک: uploads/hj-private با .htaccess مسدود.
	 *
	 * روی Nginx قانون deny باید جدا گذاشته شود (یادداشت بخش ۵/۶).
	 *
	 * @return string مسیر مطلق یا رشتهٔ خالی اگر ساختن ممکن نشد.
	 */
	function hj_sec_private_dir() {
		$up = wp_get_upload_dir();
		if ( empty( $up['basedir'] ) ) {
			return '';
		}
		$dir = apply_filters( 'hj_sec_private_dir', untrailingslashit( $up['basedir'] ) . '/hj-private' );
		$dir = (string) $dir;

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\nOptions -Indexes\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return $dir;
	}
}

if ( ! function_exists( 'hj_sec_new_document_dir' ) ) {
	/**
	 * Explicit, pre-existing storage for NEW documents. The deployment operator
	 * must independently verify server aliases, CDN and backup access. Legacy
	 * documents continue to use hj_sec_private_dir(), not this directory.
	 *
	 * @param bool $for_upload Whether to require the operator's upload opt-in.
	 * @return string Canonical path or empty string (fail closed).
	 */
	function hj_sec_new_document_dir( $for_upload = true ) {
		if ( ! defined( 'HJ_PRIVATE_DOCUMENT_DIR' ) || ! is_string( HJ_PRIVATE_DOCUMENT_DIR ) || HJ_PRIVATE_DOCUMENT_DIR === '' || is_link( HJ_PRIVATE_DOCUMENT_DIR ) ) {
			return '';
		}
		if ( $for_upload && ( ! defined( 'HJ_PRIVATE_DOCUMENT_STORAGE_VERIFIED' ) || HJ_PRIVATE_DOCUMENT_STORAGE_VERIFIED !== true ) ) {
			return '';
		}
		$base = realpath( HJ_PRIVATE_DOCUMENT_DIR );
		$docroot = isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
		$wp_root = realpath( ABSPATH );
		$content = realpath( WP_CONTENT_DIR );
		$uploads = wp_get_upload_dir();
		$upload_root = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( ! $base || ! is_dir( $base ) || ! is_writable( $base ) || ! $docroot || ! $wp_root || ! $content || ! $upload_root ) {
			return '';
		}
		$base = rtrim( wp_normalize_path( $base ), '/' );
		foreach ( array( $docroot, $wp_root, $content, $upload_root ) as $root ) {
			$root = rtrim( wp_normalize_path( $root ), '/' );
			if ( $root === '' || $base === $root || strpos( $base, $root . '/' ) === 0 || strpos( $root, $base . '/' ) === 0 ) {
				return '';
			}
		}
		return $base;
	}
}

if ( ! function_exists( 'hj_sec_validate_upload' ) ) {
	/**
	 * اعتبارسنجی فایل بارگذاری‌شده پیش از ذخیره.
	 *
	 * @param array  $file آیتم $_FILES.
	 * @param string $kind یکی از photo/document.
	 * @return true|false|WP_Error
	 */
	function hj_sec_validate_upload( $file, $kind = 'photo' ) {
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'hj_bad_file', 'فایل نامعتبر است.' );
		}

		$max  = ( 'photo' === $kind ) ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > $max ) {
			return new WP_Error( 'hj_bad_size', 'حجم فایل مجاز نیست.' );
		}

		$allowed = ( 'photo' === $kind )
			? array( 'image/jpeg' => 'jpg', 'image/png' => 'png' )
			: array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf' );

		$ext = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'pdf' ), true ) ) {
			return new WP_Error( 'hj_bad_ext', 'نوع فایل مجاز نیست.' );
		}

		if ( class_exists( 'finfo' ) ) {
			$fi   = new finfo( FILEINFO_MIME_TYPE );
			$mime = (string) $fi->file( $file['tmp_name'] );
			$ok   = false;
			foreach ( $allowed as $m => $e ) {
				if ( $mime === $m && ( $ext === $e || ( 'jpeg' === $ext && 'jpg' === $e ) ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return new WP_Error( 'hj_bad_mime', 'محتوای فایل با نوع اعلام‌شده نمی‌خواند.' );
			}
		}

		return true;
	}
}

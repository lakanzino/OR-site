<?php
/**
 * Phase 5-b: edit the content layer from wp-admin.
 *
 * What an administrator can do here, and nothing more:
 *
 * - see which bundle the page is serving (uploads or the copy inside the zip),
 *   its version, its hash and how many conditions/questions it has;
 * - activate one of the bundles that already sit in `uploads/hj-sc/`, or return
 *   to automatic ("newest file wins");
 * - upload a bundle produced by `author.py publish-bundle`, after a shape check;
 * - edit the **text layer** — question texts, help lines, option labels,
 *   condition names, safety and coverage messages, disclaimers — and save it as
 *   a *new* file with a bumped version. The previous file is never touched.
 *
 * What it deliberately does not do:
 *
 * - it does not add, delete or reorder questions, options, evidence links or
 *   rules: ids, `evidence`, `when`, `profile` rows and `source_refs` are copied
 *   byte-for-byte, so a text edit can never change routing, ranking or safety;
 * - it does not validate clinical content (the CLI and the engine do that) and
 *   it does not invent sources: a new version is stamped
 *   `review_status: pending_team_review`;
 * - it stores no answer, sends nothing anywhere, and never writes outside
 *   `uploads/hj-sc/`.
 *
 * @package HamyarSymptomChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Slug of the admin screen. */
const HJ_SC_ADMIN_SLUG = 'hj-sc-content';

/** Nonce actions. */
const HJ_SC_NONCE_SAVE   = 'hjsc_save_text';
const HJ_SC_NONCE_UPLOAD = 'hjsc_upload_bundle';
const HJ_SC_NONCE_ACTIVE = 'hjsc_activate_bundle';

/**
 * Capability required to open the screen. Filterable so a site can hand
 * content editing to an editor role without giving it `manage_options`.
 *
 * @return string
 */
function hjsc_admin_capability() {
	return (string) apply_filters( 'hjsc_admin_capability', 'manage_options' );
}

/**
 * Is the current request allowed to use this screen?
 *
 * @return bool
 */
function hjsc_admin_user_can() {
	if ( ! function_exists( 'current_user_can' ) ) {
		return false;
	}
	return current_user_can( hjsc_admin_capability() );
}

/**
 * Admin URL of this screen, with query arguments already encoded.
 *
 * Built by hand instead of `add_query_arg()` so the same code runs in the PHP
 * test harness, and so no argument is ever echoed without `rawurlencode()`.
 *
 * @param array<string,string> $args Query arguments.
 * @return string
 */
function hjsc_admin_url( $args = array() ) {
	$base = function_exists( 'admin_url' ) ? admin_url( 'admin.php' ) : 'admin.php';
	$url  = $base . '?page=' . rawurlencode( HJ_SC_ADMIN_SLUG );
	foreach ( $args as $key => $value ) {
		$url .= '&' . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
	return $url;
}

/**
 * Register the screen. A top-level menu (not a submenu of Settings) because the
 * owner opens it often and it must be findable without instructions.
 */
function hjsc_admin_menu() {
	add_menu_page(
		'دستیار نشانه‌ها — محتوا',
		'دستیار نشانه‌ها',
		hjsc_admin_capability(),
		HJ_SC_ADMIN_SLUG,
		'hjsc_admin_render',
		'dashicons-search',
		58
	);

	// 0.1.14: the display settings live next to the content screen, because the
	// owner opens both from the same menu.
	add_submenu_page(
		HJ_SC_ADMIN_SLUG,
		'تنظیمات نمایش',
		'تنظیمات نمایش',
		hjsc_admin_capability(),
		HJ_SC_SETTINGS_SLUG,
		'hjsc_settings_render'
	);
}
add_action( 'admin_menu', 'hjsc_admin_menu' );

/**
 * Fixed notices. Codes are mapped to text here, so a query argument can never
 * inject a message.
 *
 * @param string $code Notice code.
 * @return array{0:string,1:string} Type and message.
 */
function hjsc_admin_notice_text( $code ) {
	$notices = array(
		'saved'          => array( 'success', 'متن ذخیره شد و نسخهٔ تازه فعال شد؛ فایل قبلی دست‌نخورده در پوشه مانده است.' ),
		'unchanged'      => array( 'info', 'تغییری پیدا نشد؛ فایلی ساخته نشد.' ),
		'activated'      => array( 'success', 'بستهٔ انتخاب‌شده فعال شد.' ),
		'auto'           => array( 'success', 'حالت خودکار برگشت: تازه‌ترین بستهٔ معتبر در پوشه سرو می‌شود.' ),
		'uploaded'       => array( 'success', 'فایل بارگذاری و فعال شد.' ),
		'bad_nonce'      => array( 'error', 'درخواست منقضی شده بود؛ دوباره تلاش کنید.' ),
		'bad_file'       => array( 'error', 'فایل قابل استفاده نیست: JSON سالم با schema 2.0.0 و حداقل یک پرسش لازم است.' ),
		'bad_upload'     => array( 'error', 'بارگذاری فایل انجام نشد. فقط فایل .json مجاز است.' ),
		'bad_bundle'     => array( 'error', 'این فایل خوانا نیست یا نامش با قرارداد پوشه نمی‌خواند.' ),
		'bad_version'    => array( 'error', 'نسخهٔ بسته قابل افزایش نیست؛ فایل را با author.py دوباره بسازید.' ),
		'write_failed'   => array( 'error', 'نوشتن فایل در پوشهٔ بارگذاری‌ها ممکن نشد.' ),
	);
	if ( ! isset( $notices[ $code ] ) ) {
		return array( '', '' );
	}
	return $notices[ $code ];
}

/**
 * Every readable bundle in the uploads folder, newest first.
 *
 * @return array<int,array<string,mixed>>
 */
function hjsc_admin_bundles() {
	$dir = hjsc_upload_dir();
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return array();
	}
	$paths = glob( $dir . 'knowledge-bundle.*.json' );
	if ( ! is_array( $paths ) ) {
		return array();
	}
	$found = array();
	foreach ( $paths as $path ) {
		if ( ! preg_match( hjsc_bundle_filename_regex(), basename( $path ) ) ) {
			continue;
		}
		$info = hjsc_bundle_file_info( $path );
		if ( null === $info ) {
			continue;
		}
		$info['bytes'] = (int) @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file is reported by the empty size, not a warning.
		$info['mtime'] = (int) @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- same.
		$found[]       = $info;
	}
	usort(
		$found,
		static function ( $left, $right ) {
			$compare = version_compare( $right['bundle_version'], $left['bundle_version'] );
			return 0 !== $compare ? $compare : strcmp( $right['sha8'], $left['sha8'] );
		}
	);
	return $found;
}

/**
 * What the front end is serving right now, plus the raw option.
 *
 * @return array<string,mixed>
 */
function hjsc_admin_state() {
	$source  = hjsc_bundle_source();
	$bundle  = hjsc_bundle();
	$option  = (string) get_option( HJ_SC_ACTIVE_BUNDLE_OPTION, '' );
	$counts  = array(
		'conditions' => isset( $bundle['conditions'] ) && is_array( $bundle['conditions'] ) ? count( $bundle['conditions'] ) : 0,
		'questions'  => isset( $bundle['questions'] ) && is_array( $bundle['questions'] ) ? count( $bundle['questions'] ) : 0,
		'symptoms'   => isset( $bundle['symptoms'] ) && is_array( $bundle['symptoms'] ) ? count( $bundle['symptoms'] ) : 0,
	);
	$options = 0;
	if ( isset( $bundle['questions'] ) && is_array( $bundle['questions'] ) ) {
		foreach ( $bundle['questions'] as $question ) {
			if ( isset( $question['options'] ) && is_array( $question['options'] ) ) {
				$options += count( $question['options'] );
			}
		}
	}
	$counts['options'] = $options;

	return array(
		'source'  => $source,
		'option'  => $option,
		'autodetect' => (bool) apply_filters( 'hjsc_bundle_autodetect', true ),
		'counts'  => $counts,
		'dir'     => hjsc_upload_dir(),
	);
}

/**
 * Next minor-of-patch version for an edited copy: `0.2.0-draft` → `0.2.1-draft`.
 *
 * Only the numeric part moves; a suffix such as `-draft` is preserved so a
 * dashboard edit can never look like a reviewed release.
 *
 * @param string $version Current bundle version.
 * @return string Empty string when the version cannot be parsed.
 */
function hjsc_admin_bump_version( $version ) {
	$version = trim( (string) $version );
	if ( '' === $version || ! preg_match( '/^(\d+)\.(\d+)\.(\d+)(-[A-Za-z0-9._-]+)?$/', $version, $matches ) ) {
		return '';
	}
	$suffix = $matches[4] ?? '';
	$patch  = (int) $matches[3] + 1;
	return $matches[1] . '.' . $matches[2] . '.' . $patch . $suffix;
}

/**
 * Text fields a dashboard edit is allowed to touch, collected from a bundle.
 *
 * Keyed by a flat path (`questions.<id>.text_fa`) for the form, and by the
 * value the bundle holds today, so the save step can tell a real edit from a
 * re-submitted form.
 *
 * @param array<string,mixed> $bundle Bundle.
 * @return array<int,array{path:string,kind:string,label:string,group:string,value:string,multiline:bool}>
 */
function hjsc_admin_text_fields( $bundle ) {
	$fields = array();

	if ( isset( $bundle['disclaimers'] ) && is_array( $bundle['disclaimers'] ) ) {
		$labels = array(
			'before_start_fa' => 'متن پیش از شروع',
			'during_fa'       => 'متن هنگام پرسش',
			'result_fa'       => 'متن زیر نتیجه',
		);
		foreach ( $labels as $key => $label ) {
			if ( isset( $bundle['disclaimers'][ $key ] ) && is_string( $bundle['disclaimers'][ $key ] ) ) {
				$fields[] = array(
					'path'      => 'disclaimers.' . $key,
					'kind'      => 'disclaimers',
					'label'     => $label,
					'group'     => 'سلب مسئولیت‌ها',
					'value'     => $bundle['disclaimers'][ $key ],
					'multiline' => true,
				);
			}
		}
	}

	if ( isset( $bundle['conditions'] ) && is_array( $bundle['conditions'] ) ) {
		foreach ( array_values( $bundle['conditions'] ) as $index => $condition ) {
			if ( empty( $condition['id'] ) || ! isset( $condition['name_fa'] ) ) {
				continue;
			}
			$fields[] = array(
				'path'      => 'conditions.' . $index . '.name_fa',
				'kind'      => 'conditions',
				'label'     => 'نام بیماری («' . (string) $condition['id'] . '»)',
				'group'     => 'نام بیماری‌ها',
				'value'     => (string) $condition['name_fa'],
				'multiline' => false,
			);
		}
	}

	if ( isset( $bundle['questions'] ) && is_array( $bundle['questions'] ) ) {
		foreach ( array_values( $bundle['questions'] ) as $q_index => $question ) {
			if ( empty( $question['id'] ) ) {
				continue;
			}
			$group = 'پرسش‌ها';
			if ( isset( $question['text_fa'] ) && is_string( $question['text_fa'] ) ) {
				$fields[] = array(
					'path'      => 'questions.' . $q_index . '.text_fa',
					'kind'      => 'questions',
					'label'     => 'متن پرسش',
					'group'     => $group,
					'value'     => $question['text_fa'],
					'multiline' => false,
				);
			}
			if ( isset( $question['why_fa'] ) && is_string( $question['why_fa'] ) ) {
				$fields[] = array(
					'path'      => 'questions.' . $q_index . '.why_fa',
					'kind'      => 'why',
					'label'     => 'توضیح «چرا می‌پرسیم»',
					'group'     => $group,
					'value'     => $question['why_fa'],
					'multiline' => true,
				);
			}
			if ( isset( $question['options'] ) && is_array( $question['options'] ) ) {
				foreach ( array_values( $question['options'] ) as $o_index => $option ) {
					if ( empty( $option['id'] ) || ! isset( $option['label_fa'] ) ) {
						continue;
					}
					$fields[] = array(
						'path'      => 'questions.' . $q_index . '.options.' . $o_index . '.label_fa',
						'kind'      => 'options',
						'label'     => 'برچسب گزینه («' . (string) $option['id'] . '»)',
						'group'     => $group,
						'value'     => (string) $option['label_fa'],
						'multiline' => false,
					);
				}
			}
		}
	}

	foreach ( array( 'safety_rules' => 'پیام‌های ایمنی', 'coverage_rules' => 'پیام‌های پوشش' ) as $kind => $group ) {
		if ( ! isset( $bundle[ $kind ] ) || ! is_array( $bundle[ $kind ] ) ) {
			continue;
		}
		foreach ( array_values( $bundle[ $kind ] ) as $r_index => $rule ) {
			if ( empty( $rule['id'] ) || ! isset( $rule['message_fa'] ) ) {
				continue;
			}
			$fields[] = array(
				'path'      => $kind . '.' . $r_index . '.message_fa',
				'kind'      => $kind,
				'label'     => 'پیام («' . (string) $rule['id'] . '»)',
				'group'     => $group,
				'value'     => (string) $rule['message_fa'],
				'multiline' => true,
			);
		}
	}

	return $fields;
}

/**
 * Apply submitted text to a bundle — text only.
 *
 * The submitted value is matched back to the field list produced from the
 * *current* bundle, so a request can only ever replace the value of a field the
 * screen itself rendered. Ids, evidence, `when`, profile rows, `source_refs`
 * and every other key are copied unchanged.
 *
 * @param array<string,mixed>        $bundle Bundle to copy.
 * @param array<string,mixed>        $input  Raw `hjsc_text` request value.
 * @return array{bundle:array<string,mixed>,changes:array<int,string>}
 */
function hjsc_admin_apply_text( $bundle, $input ) {
	$changes = array();
	if ( ! is_array( $input ) ) {
		return array( 'bundle' => $bundle, 'changes' => $changes );
	}

	foreach ( hjsc_admin_text_fields( $bundle ) as $field ) {
		$path   = explode( '.', $field['path'] );
		$posted = $input;
		foreach ( $path as $step ) {
			if ( ! is_array( $posted ) || ! array_key_exists( $step, $posted ) ) {
				continue 2;
			}
			$posted = $posted[ $step ];
		}
		if ( ! is_string( $posted ) ) {
			continue;
		}
		$raw   = function_exists( 'wp_unslash' ) ? wp_unslash( $posted ) : $posted;
		$clean = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $raw ) : trim( $raw );
		$clean = trim( (string) $clean );
		if ( '' === $clean || $clean === $field['value'] ) {
			continue;
		}
		$changes[] = $field['group'] . ' ← ' . $field['path'];
		hjsc_admin_set_path( $bundle, $path, $clean );
	}

	return array( 'bundle' => $bundle, 'changes' => $changes );
}

/**
 * Write a string at a `.`-separated path. Caller has already proved the path
 * exists in the bundle.
 *
 * @param array<string,mixed> $bundle Bundle (by reference).
 * @param string[]            $path   Path steps.
 * @param string              $value  New value.
 */
function hjsc_admin_set_path( &$bundle, $path, $value ) {
	$reference = &$bundle;
	foreach ( $path as $step ) {
		if ( ! is_array( $reference ) || ! array_key_exists( $step, $reference ) ) {
			return;
		}
		$reference = &$reference[ $step ];
	}
	$reference = $value;
}

/**
 * Stamp the edited copy: new version, a change note, and "needs review".
 *
 * @param array<string,mixed> $bundle  Edited bundle.
 * @param array<int,string>   $changes What was edited.
 * @param string              $version New version.
 * @param string              $date    Y-m-d.
 * @return array<string,mixed>
 */
function hjsc_admin_stamp( $bundle, $changes, $version, $date ) {
	$bundle['bundle_version'] = $version;

	$notes = isset( $bundle['change_notes'] ) && is_array( $bundle['change_notes'] ) ? $bundle['change_notes'] : array();
	$entry = array(
		'version'       => $version,
		'date'          => $date,
		'changes_fa'    => array_values(
			array_merge(
				array( 'ویرایش متن از پیشخوان وردپرس (فقط متن؛ شناسه‌ها و قواعد دست‌نخورده).' ),
				array_map(
					static function ( $line ) {
						return 'تغییر: ' . $line;
					},
					$changes
				)
			)
		),
		'review_status' => 'pending_team_review',
	);
	$notes[] = $entry;
	$bundle['change_notes'] = $notes;

	return $bundle;
}

/**
 * Serialise a bundle the same way the CLI does, so a dashboard edit produces a
 * file that `author.py publish-bundle --check` and the engine both accept.
 *
 * @param array<string,mixed> $bundle Bundle.
 * @return string JSON bytes with a trailing newline.
 */
function hjsc_admin_encode( $bundle ) {
	$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
	if ( defined( 'JSON_PRESERVE_ZERO_FRACTION' ) ) {
		$flags |= JSON_PRESERVE_ZERO_FRACTION;
	}
	$json = wp_json_encode( $bundle, $flags );
	return is_string( $json ) ? $json . "\n" : '';
}

/**
 * Save a bundle as a new file in the uploads folder and activate it.
 *
 * The name follows the same contract as `author.py publish-bundle`:
 * `knowledge-bundle.<bundle_version>.<sha8>.json`. An identical bundle maps to
 * an identical name, so the call is idempotent; a different bundle can never
 * overwrite this one.
 *
 * @param array<string,mixed> $bundle Bundle to write.
 * @return array{ok:bool,error:string,name:string,sha8:string,path:string,existed:bool}
 */
function hjsc_admin_write_bundle( $bundle ) {
	$failure = array( 'ok' => false, 'error' => 'write_failed', 'name' => '', 'sha8' => '', 'path' => '', 'existed' => false );

	if ( ! hjsc_bundle_is_readable( $bundle ) ) {
		$failure['error'] = 'bad_bundle';
		return $failure;
	}
	$version = isset( $bundle['bundle_version'] ) ? (string) $bundle['bundle_version'] : '';
	if ( '' === $version || ! preg_match( '/^[0-9A-Za-z._-]+$/', $version ) ) {
		$failure['error'] = 'bad_version';
		return $failure;
	}

	$json = hjsc_admin_encode( $bundle );
	if ( '' === $json || ! is_array( json_decode( $json, true ) ) ) {
		$failure['error'] = 'write_failed';
		return $failure;
	}

	$sha8 = substr( hash( 'sha256', $json ), 0, 8 );
	$name = 'knowledge-bundle.' . $version . '.' . $sha8 . '.json';
	if ( ! preg_match( hjsc_bundle_filename_regex(), $name ) ) {
		$failure['error'] = 'bad_bundle';
		return $failure;
	}

	$dir = hjsc_upload_dir();
	if ( '' === $dir ) {
		return $failure;
	}
	if ( ! is_dir( $dir ) && function_exists( 'wp_mkdir_p' ) && ! wp_mkdir_p( $dir ) ) {
		return $failure;
	}

	$path    = $dir . $name;
	$existed = is_readable( $path );
	if ( ! $existed ) {
		$written = file_put_contents( $path, $json, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local upload file, path built and validated above.
		if ( false === $written ) {
			return $failure;
		}
	}
	if ( ! hjsc_bundle_is_readable( hjsc_read_json_file( $path ) ) ) {
		return $failure;
	}

	update_option( HJ_SC_ACTIVE_BUNDLE_OPTION, $name );

	return array(
		'ok'      => true,
		'error'   => '',
		'name'    => $name,
		'sha8'    => $sha8,
		'path'    => $path,
		'existed' => $existed,
	);
}

/**
 * Activate a file that is already in the uploads folder, or return to automatic.
 *
 * @param string $name File name, or `auto`.
 * @return string Notice code.
 */
function hjsc_admin_activate( $name ) {
	if ( 'auto' === $name ) {
		delete_option( HJ_SC_ACTIVE_BUNDLE_OPTION );
		return 'auto';
	}
	$name = basename( (string) $name );
	if ( ! preg_match( hjsc_bundle_filename_regex(), $name ) ) {
		return 'bad_bundle';
	}
	$dir = hjsc_upload_dir();
	if ( '' === $dir || null === hjsc_bundle_file_info( $dir . $name ) ) {
		return 'bad_bundle';
	}
	update_option( HJ_SC_ACTIVE_BUNDLE_OPTION, $name );
	return 'activated';
}

/**
 * Handle a JSON upload: verify shape, then store it under the contract name.
 *
 * `wp_handle_upload()` only receives the file after the request already passed
 * the capability check and the nonce check below; the temporary file is moved
 * (not linked) and a file that does not parse never reaches the uploads folder.
 *
 * @param array<string,mixed> $files Usually `$_FILES`.
 * @return string Notice code.
 */
function hjsc_admin_handle_upload( $files ) {
	if ( empty( $files['hjsc_bundle_file']['tmp_name'] ) || ! is_readable( $files['hjsc_bundle_file']['tmp_name'] ) ) {
		return 'bad_upload';
	}
	$name = isset( $files['hjsc_bundle_file']['name'] ) ? (string) $files['hjsc_bundle_file']['name'] : '';
	if ( '.json' !== strtolower( substr( $name, -5 ) ) ) {
		return 'bad_upload';
	}
	// A web request may only hand us a file PHP itself received; otherwise a
	// crafted POST could point `tmp_name` at any readable path on the server.
	// The PHP test harness has no request method and writes its own fixture.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && function_exists( 'is_uploaded_file' ) && ! is_uploaded_file( $files['hjsc_bundle_file']['tmp_name'] ) ) {
		return 'bad_upload';
	}
	$bundle = hjsc_read_json_file( $files['hjsc_bundle_file']['tmp_name'] );
	if ( ! hjsc_bundle_is_readable( $bundle ) ) {
		return 'bad_file';
	}
	$result = hjsc_admin_write_bundle( $bundle );
	if ( ! $result['ok'] ) {
		return $result['error'];
	}
	return 'uploaded';
}

/**
 * Run the action the form asked for, if any, after capability + nonce checks.
 *
 * @return string Notice code.
 */
function hjsc_admin_handle_request() {
	$action = isset( $_POST['hjsc_action'] ) ? sanitize_key( (string) $_POST['hjsc_action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below per action.
	if ( '' === $action ) {
		return '';
	}
	if ( ! hjsc_admin_user_can() ) {
		return 'bad_nonce';
	}

	if ( 'save_text' === $action ) {
		if ( ! check_admin_referer( HJ_SC_NONCE_SAVE ) ) {
			return 'bad_nonce';
		}
		// Edit the file the form rendered, never "whatever is served": an
		// administrator may be editing yesterday's content on purpose.
		$target = isset( $_POST['hjsc_file'] ) ? sanitize_file_name( (string) $_POST['hjsc_file'] ) : '';
		$bundle = array();
		if ( '' !== $target && preg_match( hjsc_bundle_filename_regex(), $target ) ) {
			$dir    = hjsc_upload_dir();
			$bundle = '' !== $dir ? hjsc_read_json_file( $dir . $target ) : null;
		} else {
			$bundle = hjsc_bundle();
		}
		if ( ! is_array( $bundle ) || empty( $bundle['questions'] ) ) {
			return 'bad_file';
		}
		$edited = hjsc_admin_apply_text(
			$bundle,
			isset( $_POST['hjsc_text'] ) ? wp_unslash( $_POST['hjsc_text'] ) : array() // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above, each value sanitised in apply_text.
		);
		if ( empty( $edited['changes'] ) ) {
			return 'unchanged';
		}
		$version = hjsc_admin_bump_version( isset( $bundle['bundle_version'] ) ? (string) $bundle['bundle_version'] : '' );
		if ( '' === $version ) {
			return 'bad_version';
		}
		$stamped = hjsc_admin_stamp( $edited['bundle'], $edited['changes'], $version, gmdate( 'Y-m-d' ) );
		$result  = hjsc_admin_write_bundle( $stamped );
		return $result['ok'] ? 'saved' : $result['error'];
	}

	if ( 'activate' === $action ) {
		if ( ! check_admin_referer( HJ_SC_NONCE_ACTIVE ) ) {
			return 'bad_nonce';
		}
		$target = isset( $_POST['hjsc_target'] ) ? sanitize_file_name( (string) $_POST['hjsc_target'] ) : '';
		return hjsc_admin_activate( $target );
	}

	if ( 'upload' === $action ) {
		if ( ! check_admin_referer( HJ_SC_NONCE_UPLOAD ) ) {
			return 'bad_nonce';
		}
		return hjsc_admin_handle_upload( $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above, file read as JSON and shape-checked.
	}

	return '';
}

/* -------------------------------------------------------------------------
 * Rendering
 * ---------------------------------------------------------------------- */

/**
 * One table row: version, hash, size, source, actions.
 *
 * @param array<string,mixed> $info   Bundle info.
 * @param bool                $active Whether this file is the active one.
 * @param bool                $is_served Whether this file is what the page uses.
 */
function hjsc_admin_bundle_row( $info, $active, $is_served ) {
	$badge = $is_served ? ' — <strong>در حال سرو</strong>' : '';
	echo '<tr><td><code>' . esc_html( $info['name'] ) . '</code>' . $badge . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $badge is a fixed string.
	echo '<td>' . esc_html( $info['bundle_version'] ) . '</td>';
	echo '<td><code>' . esc_html( $info['sha8'] ) . '</code></td>';
	echo '<td>' . esc_html( number_format_i18n( (int) $info['bytes'] ) ) . ' B</td>';
	echo '<td>';
	if ( ! $active && ! $is_served ) {
		echo '<form method="post" style="margin:0">';
		wp_nonce_field( HJ_SC_NONCE_ACTIVE );
		echo '<input type="hidden" name="hjsc_action" value="activate" />';
		echo '<input type="hidden" name="hjsc_target" value="' . esc_attr( $info['name'] ) . '" />';
		echo '<button type="submit" class="button">فعال کن</button>';
		echo '</form>';
	} elseif ( $active ) {
		echo '<span class="description">انتخاب‌شده</span>';
	}
	echo '</td><td><a class="button" href="' . esc_url( hjsc_admin_url( array( 'action' => 'edit', 'file' => $info['name'] ) ) ) . '">ویرایش متن</a></td></tr>';
}

/**
 * The screen.
 */
function hjsc_admin_render() {
	if ( ! hjsc_admin_user_can() ) {
		wp_die( esc_html( 'دسترسی به این صفحه مجاز نیست.' ), '', array( 'response' => 403 ) );
	}

	$notice = '';
	if ( ! empty( $_POST['hjsc_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the handler checks the nonce per action.
		$notice = hjsc_admin_handle_request();
	} elseif ( isset( $_GET['hjsc_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- fixed codes only, see hjsc_admin_notice_text().
		$notice = sanitize_key( (string) $_GET['hjsc_notice'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$state = hjsc_admin_state();
	$action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.

	echo '<div class="wrap">';
	echo '<h1>دستیار نشانه‌ها — محتوا</h1>';

	if ( '' !== $notice ) {
		list( $type, $message ) = hjsc_admin_notice_text( $notice );
		if ( '' !== $message ) {
			$class = 'notice-' . ( 'error' === $type ? 'error' : ( 'success' === $type ? 'success' : 'info' ) );
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	// What the visitor gets right now.
	echo '<h2>وضعیت فعلی</h2><table class="widefat striped" style="max-width:820px"><tbody>';
	echo '<tr><th style="width:200px">منبع</th><td>' . esc_html( 'upload' === $state['source']['source'] ? 'فایل بارگذاری‌شده (uploads/hj-sc)' : 'نسخهٔ داخل بستهٔ افزونه' ) . '</td></tr>';
	echo '<tr><th>فایل</th><td><code>' . esc_html( (string) $state['source']['name'] ) . '</code></td></tr>';
	echo '<tr><th>نسخه</th><td>' . esc_html( (string) $state['source']['bundle_version'] ) . '</td></tr>';
	echo '<tr><th>schema</th><td>' . esc_html( (string) $state['source']['schema_version'] ) . '</td></tr>';
	echo '<tr><th>هش (۸ رقم)</th><td><code>' . esc_html( (string) $state['source']['sha8'] ) . '</code></td></tr>';
	echo '<tr><th>محتوا</th><td>' . esc_html( sprintf( '%d بیماری · %d پرسش · %d نشانه · %d گزینه', $state['counts']['conditions'], $state['counts']['questions'], $state['counts']['symptoms'], $state['counts']['options'] ) ) . '</td></tr>';
	echo '<tr><th>پوشه</th><td><code>' . esc_html( (string) $state['dir'] ) . '</code></td></tr>';
	echo '</tbody></table>';

	if ( ! $state['autodetect'] ) {
		echo '<p class="description">تشخیص خودکار با فیلتر <code>hjsc_bundle_autodetect</code> خاموش شده است؛ فقط انتخاب دستی (یا نسخهٔ داخل زیپ) سرو می‌شود.</p>';
	}
	if ( '' !== $state['option'] ) {
		echo '<p>فایل قفل‌شده: <code>' . esc_html( $state['option'] ) . '</code> · ';
		echo '<form method="post" style="display:inline;margin:0">';
		wp_nonce_field( HJ_SC_NONCE_ACTIVE );
		echo '<input type="hidden" name="hjsc_action" value="activate" />';
		echo '<input type="hidden" name="hjsc_target" value="auto" />';
		echo '<button type="submit" class="button-link">بازگشت به خودکار</button></form></p>';
	}

	// Editor tab.
	if ( 'edit' === $action ) {
		$file  = isset( $_GET['file'] ) ? sanitize_file_name( (string) $_GET['file'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- used only to pick a readable file from the folder.
		$dir   = $state['dir'];
		$path  = '' !== $file && '' !== $dir ? $dir . basename( $file ) : '';
		$bundle = '' !== $path && preg_match( hjsc_bundle_filename_regex(), basename( $path ) ) ? hjsc_read_json_file( $path ) : null;
		if ( ! is_array( $bundle ) || empty( $bundle['questions'] ) ) {
			echo '<div class="notice notice-error"><p>فایل انتخاب‌شده خوانا نیست.</p></div>';
			echo '<p><a href="' . esc_url( hjsc_admin_url() ) . '">بازگشت</a></p></div>';
			return;
		}

		echo '<h2>ویرایش متن — <code>' . esc_html( basename( $file ) ) . '</code></h2>';
		echo '<p>فقط متن‌ها قابل ویرایش‌اند. شناسه‌ها، شاهدها، قواعد ایمنی و ترتیب پرسش‌ها دست‌نخورده می‌مانند؛ با ذخیره، نسخهٔ تازه‌ای با شمارهٔ بعدی ساخته و فعال می‌شود و این فایل حذف نمی‌شود.</p>';
		echo '<form method="post">';
		wp_nonce_field( HJ_SC_NONCE_SAVE );
		echo '<input type="hidden" name="hjsc_action" value="save_text" />';
		echo '<input type="hidden" name="hjsc_file" value="' . esc_attr( basename( $file ) ) . '" />';

		$group = '';
		foreach ( hjsc_admin_text_fields( $bundle ) as $field ) {
			if ( $field['group'] !== $group ) {
				if ( '' !== $group ) {
					echo '</fieldset>';
				}
				$group = $field['group'];
				echo '<fieldset style="margin:18px 0;padding:12px;border:1px solid #dcdcde;background:#fff"><legend><strong>' . esc_html( $group ) . '</strong></legend>';
			}
			$name = 'hjsc_text[' . str_replace( '.', '][', $field['path'] ) . ']';
			echo '<p style="margin:10px 0"><label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $field['label'] ) . '</label>';
			if ( $field['multiline'] ) {
				echo '<textarea name="' . esc_attr( $name ) . '" rows="3" class="large-text" dir="rtl">' . esc_textarea( $field['value'] ) . '</textarea>';
			} else {
				echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $field['value'] ) . '" class="large-text" dir="rtl" />';
			}
			echo '<span class="description"><code>' . esc_html( $field['path'] ) . '</code></span></p>';
		}
		if ( '' !== $group ) {
			echo '</fieldset>';
		}
		submit_button( 'ذخیره به‌عنوان نسخهٔ تازه' );
		echo '</form>';
		echo '<p><a href="' . esc_url( hjsc_admin_url() ) . '">بازگشت به فهرست</a></p></div>';
		return;
	}

	// Overview: the files that exist.
	$bundles = hjsc_admin_bundles();
	echo '<h2>فایل‌های موجود</h2>';
	if ( empty( $bundles ) ) {
		echo '<p>هنوز فایلی در <code>uploads/hj-sc/</code> نیست؛ صفحه از نسخهٔ داخل افزونه استفاده می‌کند.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>فایل</th><th>نسخه</th><th>هش</th><th>اندازه</th><th>فعال‌سازی</th><th>ویرایش</th></tr></thead><tbody>';
		foreach ( $bundles as $info ) {
			$active   = $state['option'] === $info['name'];
			$is_served = $state['source']['source'] === 'upload' && $state['source']['name'] === $info['name'];
			hjsc_admin_bundle_row( $info, $active, $is_served );
		}
		echo '</tbody></table>';
	}

	echo '<h2>بارگذاری بستهٔ تازه</h2>';
	echo '<p>فایل ساخته‌شده با <code>author.py publish-bundle</code> را اینجا بارگذاری کنید. نام فایل باید با قرارداد <code>knowledge-bundle.&lt;version&gt;.&lt;sha8&gt;.json</code> بخواند؛ شکل بسته پیش از ذخیره سنجیده می‌شود.</p>';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( HJ_SC_NONCE_UPLOAD );
	echo '<input type="hidden" name="hjsc_action" value="upload" />';
	echo '<input type="file" name="hjsc_bundle_file" accept=".json,application/json" required /> ';
	submit_button( 'بارگذاری و فعال‌سازی', 'primary', 'submit', false );
	echo '</form>';

	echo '<h2>چه چیزی این صفحه نمی‌کند</h2><ul style="list-style:disc;padding-inline-start:20px">';
	echo '<li>پرسش، گزینه، بیماری یا قاعدهٔ تازه نمی‌سازد و هیچ‌چیز را حذف نمی‌کند (برای آن، داده را با <code>author.py</code> بسازید و همین‌جا بارگذاری کنید).</li>';
	echo '<li>هیچ پاسخی از کاربر را ذخیره یا ارسال نمی‌کند؛ این صفحه فقط فایل محتوا را مدیریت می‌کند.</li>';
	echo '<li>محتوای بالینی را داوری نمی‌کند؛ نسخهٔ ساخته‌شده با <code>pending_team_review</code> مهر می‌خورد.</li>';
	echo '</ul></div>';
}

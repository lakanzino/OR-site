<?php
/** Disposable loopback-only HTTP bridge. Never install, deploy or expose. */
if (PHP_SAPI!=='cli-server' || getenv('GITHUB_ACTIONS')!=='true' || getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!=='1' || ($_SERVER['REMOTE_ADDR'] ?? '')!=='127.0.0.1' || !in_array($_SERVER['HTTP_HOST'] ?? '',array('example.invalid:8995'),true)) { http_response_code(403); exit; }
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (!in_array($path,array('/review','/review.js','/test/history','/test/compare','/test/status','/test/current'),true)) { return false; }
require '/wordpress/wp-load.php';
if (DB_NAME!=='hj_fixture_db' || DB_HOST!=='127.0.0.1:3306' || $GLOBALS['wpdb']->prefix!=='hjt_' || WP_HOME!=='http://example.invalid') { http_response_code(403); exit; }
require_once getenv('GITHUB_WORKSPACE').'/plugins/hamyar-doctors/hamyar-doctors.php'; hj_doctors(); do_action('init');
global $wpdb; $table=$wpdb->prefix.'ci_schedule_intents';
require __DIR__.'/schedule-intent-functions.php';
header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff');
if ($path==='/review.js') { if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; } header('Content-Type: application/javascript; charset=utf-8'); readfile(__DIR__.'/schedule-review-prototype.js'); exit; }
$rawDoctor=$path==='/review' ? ($_GET['doctor'] ?? '') : ($_SERVER['HTTP_X_FIXTURE_DOCTOR'] ?? '');
$doctor=intent_history_number($rawDoctor);
if ($doctor===false || !hj_booking_can_configure($doctor)) { http_response_code(403); exit; }
if ($path==='/review') {
 if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
 $nonce=wp_create_nonce('ci-review-'.$doctor);
 $html=file_get_contents(__DIR__.'/schedule-review-prototype.html');
 $meta='<meta name="fixture-auth" content="'.esc_attr($nonce).'" data-doctor="'.esc_attr((string)$doctor).'">';
 header('Content-Type: text/html; charset=utf-8'); echo str_replace('</head>',$meta.'</head>',$html); exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
if (!wp_verify_nonce($_SERVER['HTTP_X_FIXTURE_NONCE'] ?? '', 'ci-review-'.$doctor)) { http_response_code(403); exit; }
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')!==0 || (int)($_SERVER['CONTENT_LENGTH'] ?? 0)>4096) { http_response_code(400); exit; }
$raw=file_get_contents('php://input',false,null,0,4097);
$input=json_decode($raw,true); if (strlen($raw)>4096 || !is_array($input) || json_last_error()!==JSON_ERROR_NONE) { http_response_code(400); exit; }
try {
 if ($path==='/test/history') { $result=ci_intent_history($doctor); }
 elseif ($path==='/test/compare') { $result=ci_intent_compare($doctor,$input['key'] ?? null,$input['rows'] ?? null); }
 elseif ($path==='/test/status') { $result=ci_intent_find($doctor,$input['key'] ?? null); }
 else { $snapshot=intent_snapshot($doctor); $result=hj_booking_legacy_snapshot_rows($snapshot)===false ? array('status'=>'unknown') : array('revision'=>(int)$snapshot['revision']); }
 header('Content-Type: application/json; charset=utf-8'); echo wp_json_encode($result); exit;
} catch (Throwable $e) { http_response_code(503); header('Content-Type: application/json'); echo '{"status":"unknown"}'; exit; }

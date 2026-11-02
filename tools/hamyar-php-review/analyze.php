<?php
/**
 * hamyar-php-review — بازبینی ایستا به سبک WPCS روی توکنایزر واقعی PHP.
 *
 * چرا: PHPCS رسمی به افزونه‌های simplexml/mbstring/dom نیاز دارد و در محیط
 * بدون PHP نصب‌شدنی نیست. این اسکریپت با token_get_all(..., TOKEN_PARSE) کار
 * می‌کند، یعنی نحو را واقعاً پارس می‌کند و قواعد پرکاربرد WPCS را بررسی می‌کند.
 *
 * اجرا:
 *   php tools/hamyar-php-review/analyze.php plugins/hamyar-doctors
 *
 * خروجی: کد خروج ۱ اگر خطای ERROR وجود داشته باشد، وگرنه ۰.
 * قواعد: escaping، sanitization، CSRF، prefixing، i18n، SQL، مسیر ثابت، @ و …
 * هر یافته با // phpcs:ignore در همان خط نادیده گرفته می‌شود (مثل PHPCS).
 */
$root = '/plugin';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) { if (substr($f->getFilename(), -4) === '.php') { $files[] = $f->getPathname(); } }
sort($files);

$findings = [];
$add = function ($sev, $file, $line, $rule, $msg) use (&$findings) {
    // inline suppression، مطابق رفتار PHPCS
    if ($line > 0 && isset($GLOBALS['CUR_LINES'][$line - 1]) && strpos($GLOBALS['CUR_LINES'][$line - 1], 'phpcs:ignore') !== false) { return; }
    $findings[] = compact('sev','file','line','rule','msg');
};

$declared = []; $calls = [];

$ESCAPERS = '/^(esc_html|esc_attr|esc_url|esc_js|esc_textarea|esc_xml|wp_kses|wp_kses_post|wp_kses_data|absint|intval|floatval|number_format|number_format_i18n|wp_json_encode|checked|selected|disabled|wp_nonce_field|wp_nonce_url|date_i18n|size_format|tag_escape|sanitize_)/';
$SANITIZERS = '/^(sanitize_|absint|intval|floatval|esc_url_raw|wp_unslash|wp_verify_nonce|check_admin_referer|check_ajax_referer|wp_kses|current_user_can)/';

foreach ($files as $path) {
    $src = file_get_contents($path);
    $rel = ltrim(str_replace($root, '', $path), '/');
    $GLOBALS['CUR_LINES'] = explode("\n", $src);
    $tokens = token_get_all($src);

    // flatten: drop whitespace/comments, keep line numbers
    $flat = []; $lastLine = 1;
    foreach ($tokens as $t) {
        if (is_array($t)) {
            $id = $t[0]; $txt = $t[1]; $lastLine = $t[2];
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                // خط توکن بعدی = شروع توکن + تعداد خطوط جدید درون آن
                $lastLine = $t[2] + substr_count($t[1], "\n");
                continue;
            }
        } else { $id = null; $txt = $t; }
        $flat[] = ['id' => $id, 'txt' => $txt, 'ln' => $lastLine, 'raw' => is_array($t)];
    }
    $n = count($flat);

    // ---- statement segmentation ----
    $stmts = []; $cur = []; $paren = 0; $bracket = 0;
    foreach ($flat as $idx => $tk) {
        $cur[] = $idx;
        $t = $tk['txt'];
        if ($t === '(') $paren++;
        elseif ($t === ')') $paren--;
        elseif ($t === '[') $bracket++;
        elseif ($t === ']') $bracket--;
        elseif (($t === ';' && $paren <= 0 && $bracket <= 0) || $t === '{' || $t === '}') {
            if ($cur) { $stmts[] = $cur; }
            $cur = [];
            if ($t === '{' || $t === '}') { /* keep block boundaries clean */ }
        }
    }
    if ($cur) { $stmts[] = $cur; }

    $hasEscAlias = (bool) preg_match('/\$e\s*=\s*[\'"]esc_html[\'"]/', $src);
    $checkStmt = function ($idxList) use ($flat, $rel, $add, $ESCAPERS, $SANITIZERS, $hasEscAlias) {
        if (!$idxList) return;
        $text = ''; $names = []; $vars = []; $line = $flat[$idxList[0]]['ln'];
        $firstId = $flat[$idxList[0]]['id'];
        foreach ($idxList as $i) {
            $tk = $flat[$i];
            $text .= $tk['txt'] . ' ';
            if ($tk['id'] === T_STRING || $tk['id'] === T_STRING_CAST) { $names[] = strtolower($tk['txt']); }
            if ($tk['id'] === T_VARIABLE) { $vars[] = $tk['txt']; }
        }
        $escaped = false; $sanitized = false;
        foreach ($names as $nm) {
            if (preg_match($ESCAPERS, $nm)) { $escaped = true; }
            if (preg_match($SANITIZERS, $nm)) { $sanitized = true; }
        }
        if (strpos($text, '(int)') !== false || strpos($text, '(float)') !== false) { $escaped = true; $sanitized = true; }
        // نام مستعار رایج escape در این پروژه: $e = 'esc_html'
        if ($hasEscAlias && preg_match('/\$e\s*\(/', $text)) { $escaped = true; }
        // ویجت‌ها عمداً before_widget/after_widget را خام چاپ می‌کنند (قرارداد هسته).
        if (preg_match('/\$args\s*\[\s*[\'"]after_widget|\$args\s*\[\s*[\'"]before_widget/', $text)) { $escaped = true; }

        // R3: echo/print variable without escaping
        if (($firstId === T_ECHO || $firstId === T_PRINT) && $vars && !$escaped) {
            $add('WARN', $rel, $line, 'escaping', 'echo/print of variable without escaping: ' . substr(preg_replace('/\s+/', ' ', $text), 0, 120));
        }

        // R2: superglobal input without sanitizer
        $inputVars = array_filter($vars, function ($v) {
            return preg_match('/^\$_(GET|POST|REQUEST|COOKIE|FILES)$/', $v);
        });
        // بررسی صرفِ وجود کلید (isset/empty) یا تبدیل به ۰/۱، افشای داده نیست.
        $presenceOnly = (bool) preg_match('/(isset|empty)\s*\(\s*\$_/', $text);
        if (preg_match('/\$_\s*\[[^\]]*\]\s*=(?!=)/', $text)) { $presenceOnly = false; }
        // نوشتن در $_COOKIE (مثل setcookie) خواندن ورودی نیست.
        if (preg_match('/\$_COOKIE\s*\[[^\]]*\]\s*=(?!=)/', $text)) { $presenceOnly = true; }
        if ($inputVars && !$sanitized && !$presenceOnly) {
            $add('WARN', $rel, $line, 'input', 'request data without sanitizer/cast: ' . substr(preg_replace('/\s+/', ' ', $text), 0, 120));
        }
        // $_SERVER only flagged when used beyond REQUEST_METHOD
        if (preg_match('/\$_SERVER\s*\[\s*[\'"](?!REQUEST_METHOD)[^\'"]+/', $text) && !$sanitized) {
            $add('NOTICE', $rel, $line, 'input', '$_SERVER read without sanitizer');
        }

        // R6: $wpdb without prepare
        if (preg_match('/\$wpdb\s*->\s*(query|get_results|get_var|get_row|get_col|get_col_info)/', $text)
            && !preg_match('/prepare|SHOW\s+(TABLES|COLUMNS)/i', $text)
            && strpos($text, 'phpcs:ignore') === false) {
            $add('NOTICE', $rel, $line, 'sql', 'direct $wpdb call without prepare on same statement');
        }
    };

    foreach ($stmts as $s) { $checkStmt($s); }

    // ---- token-level rules ----
    $classDepth = -1; $braceDepth = 0;
    for ($i = 0; $i < $n; $i++) {
        $tk = $flat[$i]; $id = $tk['id']; $txt = $tk['txt']; $ln = $tk['ln'];

        if ($txt === '{') { $braceDepth++; }
        elseif ($txt === '}') { $braceDepth--; if ($classDepth > $braceDepth) { $classDepth = -1; } }
        if ($id === T_CLASS || $id === T_TRAIT || $id === T_INTERFACE) { $classDepth = $braceDepth + 1; }

        if ($id === T_EVAL) { $add('ERROR', $rel, $ln, 'dangerous', 'eval()'); }
        if ($txt === '@') { $add('WARN', $rel, $ln, 'suppression', '"@" error suppression'); }
        if ($id === T_STRING && in_array(strtolower($txt), ['extract','create_function','mysql_query','each','get_currentuserinfo'], true)) {
            if (($flat[$i+1]['txt'] ?? '') === '(') { $add('ERROR', $rel, $ln, 'dangerous', $txt . '()'); }
        }

        if ($id === T_FUNCTION) {
            $j = $i + 1;
            $name = '';
            if (isset($flat[$j]) && $flat[$j]['id'] === T_STRING) { $name = $flat[$j]['txt']; }
            $isGlobal = ($classDepth < 0 || $braceDepth < $classDepth);
            if ($name !== '' && $isGlobal) {
                $declared[$name][] = $rel;
                if (!preg_match('/^(hj_|wn_|orsite_)/', $name)) {
                    $add('WARN', $rel, $ln, 'naming', 'global function without project prefix: ' . $name);
                }
            }
        }

        // R4 i18n domain
        if ($id === T_STRING && in_array($txt, ['__','_e','esc_html__','esc_html_e','esc_attr__','esc_attr_e','_x','_n','_nx','esc_js__'], true)
            && ($flat[$i+1]['txt'] ?? '') === '(') {
            $depth = 0; $buf = '';
            for ($k = $i + 1; $k < $n; $k++) {
                $tt = $flat[$k]['txt'];
                if ($tt === '(') { $depth++; if ($depth === 1) continue; }
                if ($tt === ')') { $depth--; if ($depth === 0) break; }
                $buf .= $tt . ' ';
            }
            if (strpos($buf, 'hamyar-doctors') === false) {
                $add('WARN', $rel, $ln, 'i18n', $txt . '() without "hamyar-doctors" text domain');
            }
        }

        // R8 hardcoded path inside a string literal
        if ($id === T_CONSTANT_ENCAPSED_STRING && strpos($txt, 'wp-content/plugins') !== false) {
            $add('WARN', $rel, $ln, 'paths', 'hardcoded wp-content/plugins path in string');
        }

        // R7 error_log
        if ($id === T_STRING && $txt === 'error_log' && ($flat[$i+1]['txt'] ?? '') === '(') {
            $add('NOTICE', $rel, $ln, 'logging', 'direct error_log() call');
        }

        // hj_* calls (code only, comments already excluded)
        if ($id === T_STRING && preg_match('/^hj_[a-z0-9_]+$/', $txt) && ($flat[$i+1]['txt'] ?? '') === '(') {
            $calls[$txt][] = $rel . ':' . $ln;
        }
    }

    // R9 CSRF: فقط فایل‌هایی که واقعاً وضعیت را تغییر می‌دهند اهمیت دارند.
    // خواندن GET برای فیلتر آرشیو، ورودی OAuth یا ویجت عمومیِ فقط‌خواندنی
    // نیازی به nonce ندارد و نباید هشدار بدهد.
    $writesState = (bool) preg_match(
        '/update_option\s*\(|delete_option\s*\(|wp_insert_post\s*\(|wp_update_post\s*\(|wp_delete_post\s*\(|update_post_meta\s*\(|delete_post_meta\s*\(|wp_set_object_terms\s*\(|\$wpdb\s*->\s*(insert|update|delete|query)\s*\(/',
        $src
    );
    if ($writesState
        && preg_match('/\$_(POST|GET|REQUEST)\b/', $src)
        && !preg_match('/wp_verify_nonce|check_admin_referer|check_ajax_referer|wp_nonce_field/', $src)) {
        $add('ERROR', $rel, 0, 'csrf', 'state-changing file reads request data without any nonce verification');
    }
}

$undefined = [];
foreach ($calls as $fn => $sites) { if (!isset($declared[$fn])) { $undefined[$fn] = array_slice(array_unique($sites), 0, 3); } }

$bysev = ['ERROR' => 0, 'WARN' => 0, 'NOTICE' => 0];
foreach ($findings as $f) { $bysev[$f['sev']]++; }

echo "=== FINDINGS ===\n";
if (!$findings) echo "none\n";
foreach ($findings as $f) printf("%-6s %s:%d  [%s] %s\n", $f['sev'], $f['file'], $f['line'], $f['rule'], substr($f['msg'], 0, 150));
echo "\n=== UNDEFINED hj_* FUNCTIONS ===\n";
if (!$undefined) echo "none\n";
foreach ($undefined as $fn => $sites) echo $fn . '  <- ' . implode(', ', $sites) . "\n";
echo "\n=== SUMMARY ===\n";
echo "files=" . count($files) . " errors={$bysev['ERROR']} warnings={$bysev['WARN']} notices={$bysev['NOTICE']}\n";

exit($bysev['ERROR'] > 0 ? 1 : 0);

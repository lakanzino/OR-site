/**
 * End-to-end check of the installable plugin inside a real WordPress.
 *
 *   node tools/symptom-checker-plugin/check-wordpress.mjs
 *   node tools/symptom-checker-plugin/check-wordpress.mjs --keep   # keep the temp site
 *
 * What it does (all local, nothing touches the live site):
 *   1. builds releases/hamyar-symptom-checker-<version>.zip from the repository sources;
 *   2. downloads WordPress 6.8.3 from GitHub (codeload) and verifies its SHA-256;
 *   3. installs the WordPress Playground CLI + jsdom in a temporary directory;
 *   4. boots the site twice: once with the page still a draft (must be 404 for a
 *      visitor) and once published, so the page itself can be rendered;
 *   5. asserts the page markup, the enqueued assets, the `noindex` policy, the
 *      article mapping (published only) and that other pages stay untouched;
 *   6. drives the packaged UI in jsdom against the live WordPress URLs.
 *
 * Requirements: node >= 20, npm, python3, and network access to npm and
 * codeload.github.com. This is a disposable local site; it is not the live one.
 */
import { spawn, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, mkdirSync, readFileSync, writeFileSync, rmSync, readdirSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../..');
// HJ_WP_VERSION lets the same harness run against another WordPress release, so
// a host that runs a newer core can be reproduced before shipping a fix.
const WP_VERSION = process.env.HJ_WP_VERSION || '6.8.3';
const WP_SHA256 = {
  '6.8.3': '96ddc66e09427764dc7671361ef27542dcf642d820d4280307d32913208dca3f',
};
const EXPECTED_HASH = WP_SHA256[WP_VERSION] || null;
const PLAYGROUND_VERSION = '3.1.61';
// HJ_PHP_VERSION reproduces the PHP build of the hosting account (the live site
// runs PHP 8.4); without it Playground uses its own default build.
const PHP_VERSION = process.env.HJ_PHP_VERSION || null;
const PORT = Number(process.env.HJ_WP_PORT ?? 9411);
const BASE = `http://127.0.0.1:${PORT}`;
const keep = process.argv.includes('--keep');

const work = mkdtempSync(join(tmpdir(), 'hj-sc-wp-'));
let server = null;
const results = [];

/** `3.0.0-draft` → `3.0.1-draft`; the dashboard never overwrites a version. */
function bumpDraftVersion(version) {
  const match = String(version ?? '').match(/^(\d+)\.(\d+)\.(\d+)(-.+)?$/);
  if (!match) throw new Error(`unexpected bundle version: ${version}`);
  return `${match[1]}.${match[2]}.${Number(match[3]) + 1}${match[4] ?? ''}`;
}

/** The one knowledge bundle the built package ships (its name carries no version). */
function packagedBundlePath() {
  const dir = join(work, 'plugins/hamyar-symptom-checker/assets/data');
  const file = readdirSync(dir).find((name) => /^knowledge-bundle\..*\.json$/.test(name));
  if (!file) throw new Error('the package ships no knowledge bundle');
  return join(dir, file);
}


// A second UI run (phase 5-a) reports the same assertions under another banner,
// so the log never shows two identical lines with different meanings.
let checkPrefix = '';

function check(text, ok, detail = '') {
  const label = checkPrefix ? `${checkPrefix}${text}` : text;
  results.push({ label, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`);
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, { encoding: 'utf8', ...options });
  if (result.status !== 0 && !options.allowFailure) {
    throw new Error(`${command} ${args.join(' ')} failed: ${result.stderr?.slice(0, 800) ?? ''}`);
  }
  return result;
}

async function waitForServer(timeoutMs = 180000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    try {
      const response = await fetch(`${BASE}/wp-json/`, { redirect: 'manual' });
      if (response.status < 500) return true;
    } catch {
      /* not listening yet */
    }
    await new Promise((done) => setTimeout(done, 500));
  }
  return false;
}

async function startSite(blueprint) {
  if (server) {
    server.kill('SIGTERM');
    await new Promise((done) => server.once('exit', done));
    server = null;
    await new Promise((done) => setTimeout(done, 500));
  }
  rmSync(reportPath(), { force: true });
  const cli = join(work, 'node_modules/@wp-playground/cli/wp-playground.js');
  server = spawn(
    process.execPath,
    [
      cli,
      'server',
      '--mount-before-install', `${join(work, 'wordpress/WordPress-' + WP_VERSION)}:/wordpress`,
      '--mount', `${join(work, 'plugins/hamyar-symptom-checker')}:/wordpress/wp-content/plugins/hamyar-symptom-checker`,
      '--mount', `${join(work, 'mu')}:/wordpress/wp-content/mu-plugins`,
      '--wordpress-install-mode', 'install-from-existing-files',
      '--blueprint', blueprint,
      '--port', String(PORT),
      '--verbosity', 'quiet',
      ...(PHP_VERSION ? ['--php', PHP_VERSION] : []),
    ],
    { stdio: ['ignore', 'pipe', 'pipe'] },
  );
  server.stdout.on('data', (data) => process.env.HJ_VERBOSE && process.stdout.write(`[wp] ${data}`));
  server.stderr.on('data', (data) => process.env.HJ_VERBOSE && process.stderr.write(`[wp] ${data}`));

  const ready = await waitForServer();
  if (!ready) throw new Error('WordPress Playground did not become ready');
  return server;
}

async function text(path) {
  const response = await fetch(BASE + path);
  return { status: response.status, body: await response.text() };
}

async function statusOf(path) {
  const response = await fetch(BASE + path, { redirect: 'follow' });
  return response.status;
}

function writeBlueprint(name, publishPage, extraPhp = '') {
  const php = `<?php
require_once '/wordpress/wp-load.php';
if ( ! post_type_exists( 'disease' ) ) {
    register_post_type( 'disease', array( 'label' => 'Diseases', 'public' => true, 'has_archive' => true, 'rewrite' => array( 'slug' => 'disease' ), 'supports' => array( 'title', 'editor' ) ) );
}
$existing = get_page_by_path( 'appendicitis', OBJECT, 'disease' );
$published = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'disease', 'post_status' => 'publish', 'post_title' => 'آپاندیسیت', 'post_name' => 'appendicitis', 'meta_input' => array( '_hj_disease_seed_id' => 'appendicitis' ) ), true );
$draft = get_page_by_path( 'kidney-stones', OBJECT, 'disease' );
$draft_id = $draft ? $draft->ID : wp_insert_post( array( 'post_type' => 'disease', 'post_status' => 'draft', 'post_title' => 'سنگ کلیه', 'post_name' => 'kidney-stones' ), true );
// The page is created on the first admin request after activation, so this
// script fires that hook exactly like wp-admin would.
do_action( 'admin_init' );
$page = (int) get_option( 'hj_symptom_checker_page_id' );
if ( ${publishPage ? 'true' : 'false'} ) {
    wp_update_post( array( 'ID' => $page, 'post_status' => 'publish' ) );
}
// Phase 0 moved the homepage button from the retired theme page to the plugin
// page. Run the theme's exact lookup so the decision is proved, not assumed.
$hj_lookup = get_posts( array(
    'post_type' => 'page',
    'post_status' => 'publish',
    'name' => 'symptom-checker',
    'numberposts' => 1,
    'no_found_rows' => true,
) );
flush_rewrite_rules( false );
file_put_contents( '/wordpress/hj-check.json', wp_json_encode( array(
    'wp' => get_bloginfo( 'version' ),
    'php' => PHP_VERSION,
    'page_id' => $page,
    'page_status' => get_post_status( $page ),
    'permalink' => get_permalink( $page ),
    'published' => get_permalink( $published ),
    'draft_status' => get_post_status( $draft_id ),
    'links' => hjsc_topic_links(),
    'bundle' => hjsc_bundle_version(),
    'pending' => (bool) get_option( 'hj_sc_activation_pending' ),
    'activation_error' => get_option( 'hj_sc_last_error', null ),
    // The live site's child theme declares these names; the plugin must not.
    'theme_collision' => function_exists( 'hj_sc_enqueue_assets' ),
    'home_button' => array(
        'found' => $hj_lookup ? (int) $hj_lookup[0]->ID : 0,
        'url' => $hj_lookup ? get_permalink( $hj_lookup[0] ) : '',
    ),
), JSON_UNESCAPED_UNICODE ) );
${extraPhp}
`;
  const blueprint = {
    steps: [
      { step: 'activatePlugin', pluginPath: 'hamyar-symptom-checker/hamyar-symptom-checker.php' },
      { step: 'runPHP', code: php },
    ],
  };
  const path = join(work, name);
  writeFileSync(path, JSON.stringify(blueprint, null, 2));
  return path;
}

function reportPath() {
  return join(work, `wordpress/WordPress-${WP_VERSION}/hj-check.json`);
}

function report() {
  const path = reportPath();
  return existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : null;
}

/** The blueprint finishes after the server is ready; wait for its report. */
async function waitForReport(timeoutMs = 120000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    const data = report();
    if (data) return data;
    await new Promise((done) => setTimeout(done, 500));
  }
  return null;
}

/**
 * Phase 5-a end to end: a bundle dropped into `uploads/hj-sc/` is served, is
 * reachable, carries its hash, and disappears again when the file is removed.
 */
async function bundlePublishChecks() {
  const published = join(work, 'publish');
  const built = spawnSync(
    'python3',
    [join(REPO, 'tools/symptom-checker-author/author.py'), 'publish-bundle', '--out', published],
    { encoding: 'utf8' },
  );
  if (built.status !== 0) {
    check('the authoring CLI publishes a bundle', false, (built.stderr || built.stdout || '').slice(0, 300));
    return;
  }
  const file = readdirSync(published).find((name) => name.startsWith('knowledge-bundle.') && name.endsWith('.json'));
  check('the authoring CLI publishes a bundle', Boolean(file), file ?? 'no file');
  if (!file) return;
  check('the published name carries version and hash', /^knowledge-bundle\.[0-9A-Za-z._-]+\.[0-9a-f]{8}\.json$/.test(file), file);

  const uploads = join(work, `wordpress/WordPress-${WP_VERSION}/wp-content/uploads/hj-sc`);
  mkdirSync(uploads, { recursive: true });
  const target = join(uploads, file);
  writeFileSync(target, readFileSync(join(published, file)));

  await startSite(writeBlueprint('published-with-bundle.json', true));

  const page = await text('/symptom-checker/');
  const configMatch = page.body.match(/window\.HJ_SC_CONFIG = (\{.*?\});/s);
  const config = configMatch ? JSON.parse(configMatch[1]) : null;
  check(
    'an uploaded bundle becomes the active one',
    config?.bundleStatus?.source === 'upload' && String(config?.bundleUrl ?? '').includes(`/uploads/hj-sc/${file}`),
    `${config?.bundleStatus?.source ?? 'none'} ${config?.bundleUrl ?? ''}`,
  );
  check('the uploaded URL carries the hash', String(config?.bundleUrl ?? '').includes(`ver=${file.match(/\.([0-9a-f]{8})\.json$/)?.[1]}`), config?.bundleUrl ?? '');
  const bundleResponse = await fetch(config?.bundleUrl ?? `${BASE}/missing.json`);
  const bundleText = await bundleResponse.text();
  let bundleJson = null;
  try {
    bundleJson = JSON.parse(bundleText);
  } catch {
    bundleJson = null;
  }
  check('the uploaded bundle is served as JSON', bundleResponse.status === 200 && Boolean(bundleJson), `${bundleResponse.status}`);
  check('the served bundle is the schema the engine reads', bundleJson?.schema_version === '2.0.0', bundleJson?.schema_version ?? 'none');
  check('the served bundle has questions', Array.isArray(bundleJson?.questions) && bundleJson.questions.length > 0, String(bundleJson?.questions?.length ?? 0));
  if (config) await uiCheck(config, { tag: 'uploaded-bundle', prefix: 'uploaded bundle: ' });

  rmSync(target, { force: true });
  await startSite(writeBlueprint('published-without-bundle.json', true));
  const fallbackPage = await text('/symptom-checker/');
  const fallbackMatch = fallbackPage.body.match(/window\.HJ_SC_CONFIG = (\{.*?\});/s);
  const fallback = fallbackMatch ? JSON.parse(fallbackMatch[1]) : null;
  check(
    'removing the uploaded file falls back to the packaged bundle',
    fallback?.bundleStatus?.source === 'package' &&
      String(fallback?.bundleUrl ?? '').includes('/plugins/hamyar-symptom-checker/assets/'),
    `${fallback?.bundleStatus?.source ?? 'none'} ${fallback?.bundleUrl ?? ''}`,
  );
  check('the page still renders after the fallback', fallbackPage.status === 200 && fallbackPage.body.includes('id="app"'));
}

async function uiCheck(config, options = {}) {
  // Node caches ES modules by URL, so a second run needs its own URL or the
  // entry script is never executed again.
  const cacheBuster = options.tag ? `?run=${options.tag}` : '';
  checkPrefix = options.prefix ?? '';
  let JSDOM;
  try {
    ({ JSDOM } = await import(pathToFileURL(join(work, 'node_modules/jsdom/lib/api.js')).href));
  } catch {
    ({ JSDOM } = await import('jsdom'));
  }

  const page = await text('/symptom-checker/');
  const dom = new JSDOM(page.body.replace(/<script[^>]*type="module"[^>]*><\/script>/g, ''), {
    url: `${BASE}/symptom-checker/`,
    runScripts: 'outside-only',
    pretendToBeVisual: true,
  });
  const { window } = dom;
  window.HJ_SC_CONFIG = config;
  const realFetch = globalThis.fetch;
  const requests = [];
  window.fetch = async (url, options = {}) => {
    requests.push({
      url: String(url),
      method: (options.method ?? 'GET').toUpperCase(),
      body: options.body ?? null,
    });
    return realFetch(new URL(url, `${BASE}/symptom-checker/`), options);
  };
  globalThis.window = window;
  globalThis.document = window.document;
  globalThis.fetch = window.fetch;
  globalThis.location = window.location;

  await import(pathToFileURL(join(work, 'plugins/hamyar-symptom-checker/assets/ui/app.js')).href + cacheBuster);
  await new Promise((done) => setTimeout(done, 600));

  const doc = window.document;
  // 0.1.14: the page is an ordinary page, so the theme's own header/footer is
  // expected — what must not come back is *our* old chrome: the preview label
  // in the header and the plugin's second disclaimer in the footer.
  check(
    'the plugin adds no preview label and no second disclaimer to the page',
    !page.body.includes('(پیش‌نمایش داخلی)')
      && !page.body.includes('این ابزار تشخیص پزشکی نیست و جای معاینه، آزمایش یا مشاورهٔ پزشک را نمی‌گیرد'),
  );
  check(
    'the entry script is served as a module, with a cache-busting version',
    /<script type="module" src="[^"]*ui\/app\.js\?ver=[^"]+"><\/script>/.test(page.body),
    (page.body.match(/<script[^>]*ui\/app\.js[^>]*>/) ?? ['missing'])[0],
  );
  check(
    'a watchdog is printed so a boot failure is written on the page, not swallowed',
    page.body.includes('ابزار در این مرورگر بالا نیامد'),
  );
  check('packaged UI boots inside WordPress', Boolean(doc.querySelector('#app button')));
  const start = [...doc.querySelectorAll('#app button')].find((button) => button.textContent.includes('شروع'));
  check('the start button is rendered', Boolean(start));
  if (!start) {
    checkPrefix = '';
    return;
  }

  start.click();
  await new Promise((done) => setTimeout(done, 150));
  check('the first question renders from the packaged bundle', Boolean(doc.getElementById('question-title')));

  // Phase 6: the packaged page opens with the sex gate and the body map, not
  // with the abdomen. Walk the gates exactly like a visitor would.
  const pickGate = async (optionId) => {
    const card = [...doc.querySelectorAll('#app .gate-option')].find((button) => button.dataset.optionId === optionId);
    if (!card) throw new Error(`gate card missing inside WordPress: ${optionId}`);
    card.click();
    doc.getElementById('submit-answer').click();
    await new Promise((done) => setTimeout(done, 60));
  };
  // Only the bundled 3.x content opens with the body. The upload scenario serves
  // the phase-1 authoring bundle (abdomen only), so the gates are expected to be
  // absent there — and present in the packaged one.
  const hasGates = Boolean(doc.querySelector('#app .gate-cards[data-question-id="q_sex"]'));
  const packaged = (config.bundleStatus?.source ?? 'package') === 'package';
  check(
    packaged ? 'the packaged tool starts with the sex gate' : 'an uploaded older bundle skips the body gates',
    hasGates === packaged,
    `${hasGates ? 'gates' : 'no gates'} · source ${config.bundleStatus?.source ?? 'package'}`,
  );
  if (hasGates) {
    await pickGate('male');
    check(
      'the body map renders inside WordPress, with its five zones',
      doc.querySelectorAll('#app .zone-shape').length === 5,
      `${doc.querySelectorAll('#app .zone-shape').length} zones`,
    );
    await pickGate('torso');
    check('the chosen zone offers its parts', doc.querySelectorAll('#app .gate-option').length === 3);
    await pickGate('abdomen');
  }

  const answers = {
    q_pain_present: 'yes',
    q_pain_location: 'right_lower',
    q_pain_migrated_rlq: 'yes',
    q_pain_course: 'worse',
    q_pain_severity: 8,
    q_pain_worse_movement: 'yes',
    q_flank_radiation: 'yes',
    q_fever_chills: 'yes',
    q_unbearable_pain: 'yes',
    q_pregnancy_possible: 'no',
  };
  let guard = 0;
  while (guard++ < 40 && doc.getElementById('question-title')) {
    const name = [...doc.querySelectorAll('#app input')].map((input) => input.name).find(Boolean);
    const questionId = name.replace(/^q-/, '');
    const value = answers[questionId] ?? 'unknown';
    const number = doc.querySelector(`#app input[type="number"][name="${name}"]`);
    const option = (id) => doc.querySelector(`#app input[name="${name}"][value="${id}"]`) ?? doc.querySelector(`#app input[name="${name}"][value="unknown"]`);
    if (number) {
      if (value === 'unknown') doc.querySelector('#app input[type="checkbox"][id$="-unknown"]').click();
      else {
        number.value = String(value);
        number.dispatchEvent(new window.Event('input', { bubbles: true }));
      }
    } else if (Array.isArray(value)) value.forEach((id) => option(id)?.click());
    else option(value)?.click();
    doc.getElementById('submit-answer').click();
    await new Promise((done) => setTimeout(done, 40));
  }

  const cards = [...doc.querySelectorAll('#app article.card')];
  check('the engine renders result cards inside WordPress', cards.length > 0, cards.map((card) => card.querySelector('h2')?.textContent).join(' | '));
  const safety = doc.getElementById('safety');
  check('safety banners appear in WordPress', safety.hidden === false && Boolean(safety.querySelector('.safety')));
  const link = doc.querySelector('#app article.card a[href]');
  check('the mapped article link is rendered', Boolean(link) && link.getAttribute('href') === config.topicLinks.app, link?.getAttribute('href') ?? 'no link');
  const withoutLink = [...doc.querySelectorAll('#app article.card')].filter((card) => !card.querySelector('a[href]'));
  check('unmapped topics stay link-free', withoutLink.every((card) => card.textContent.includes('هیچ لینکی نمایش داده نمی‌شود')));
  check('the UI wrote nothing to localStorage', window.localStorage.length === 0);
  check('the UI wrote nothing to sessionStorage', window.sessionStorage.length === 0);
  check('the UI set no cookie', window.document.cookie === '');
  check('the packaged UI only asked this WordPress for its two data files',
    requests.length >= 2 &&
      requests.every((item) => item.method === 'GET' && item.body === null) &&
      requests.every((item) => new URL(item.url, `${BASE}/symptom-checker/`).origin === new URL(BASE).origin),
    requests.map((item) => `${item.method} ${item.url}`).join(' ; '),
  );
  // WordPress prints namespace URIs (`rel="https://api.w.org/"`) that are not
  // requests. Only real resource loaders matter: script, stylesheet, image, font.
  const loaders = [...page.body.matchAll(/<(script|link|img|iframe|source)\b[^>]*?(?:src|href)\s*=\s*["'](https?:\/\/[^"']+)/gi)];
  const externalLoaders = loaders.filter((match) => {
    try {
      return new URL(match[2]).host !== new URL(BASE).host;
    } catch {
      return true;
    }
  });
  check('the page loads no resource from another origin',
    externalLoaders.length === 0,
    `${loaders.length} loaders: ` + externalLoaders.map((match) => match[2]).join(' ; '),
  );
  // Only the plugin's own stylesheet is ours to answer for; the theme may load
  // whatever it ships with.
  const pluginCss = readFileSync(join(work, 'plugins/hamyar-symptom-checker/assets/ui/styles.css'), 'utf8');
  check(
    'the plugin loads no font or image from another origin',
    !/@import\s+(?:url\()?['"]?https?:/i.test(pluginCss),
  );
  // A theme legitimately prints a search form; the tool itself must not post.
  check(
    'the tool itself never posts anything',
    !/<form[^>]*>[\s\S]*?id="hj-age"[\s\S]*?<\/form>/i.test(page.body)
      && !/<form[^>]+action="[^"]*symptom/i.test(page.body)
      && !/name="hj[-_]?answers?"/i.test(page.body),
  );
  checkPrefix = '';
}

/**
 * Phase 5-b end to end: the owner edits text in wp-admin, and the site serves
 * the new version — with the old file untouched and no code change.
 */
async function adminChecks() {
  const php = `
// The blueprint runs in a front-end bootstrap, so wp-admin's own helpers are
// not loaded. Load them the way an admin request would.
require_once ABSPATH . 'wp-admin/includes/admin.php';
$admin_error = null;
try {
wp_set_current_user( 1 );
$admin_can = hjsc_admin_user_can();
do_action( 'admin_menu' );
$menu_hit = array();
foreach ( (array) $GLOBALS['menu'] as $item ) {
    if ( isset( $item[2] ) && HJ_SC_ADMIN_SLUG === $item[2] ) {
        $menu_hit = $item;
    }
}
$_GET = array( 'page' => HJ_SC_ADMIN_SLUG );
ob_start();
hjsc_admin_render();
$overview = ob_get_clean();
$package_before = hash_file( 'sha256', hjsc_package_bundle_path() );
$base = hjsc_bundle();
$base_conditions = isset( $base['conditions'] ) ? count( $base['conditions'] ) : 0;
$_POST = array(
    'hjsc_action' => 'save_text',
    '_wpnonce'    => wp_create_nonce( HJ_SC_NONCE_SAVE ),
    'hjsc_text'   => array( 'conditions' => array( 0 => array( 'name_fa' => 'ویرایش آزمایشی از پیشخوان' ) ) ),
);
// WordPress reads $_REQUEST, which PHP fills before any code runs; a blueprint
// assigns $_POST afterwards, so mirror it the way a real POST would arrive.
$_REQUEST = $_POST;
$save = hjsc_admin_handle_request();
$_POST = array();
$_REQUEST = array();
$option = (string) get_option( HJ_SC_ACTIVE_BUNDLE_OPTION, '' );
$path = hjsc_upload_dir() . $option;
$saved = ( '' !== $option && is_readable( $path ) ) ? hjsc_read_json_file( $path ) : array();
$package_after = hash_file( 'sha256', hjsc_package_bundle_path() );
$subscriber = wp_insert_user( array( 'user_login' => 'hjsc_sub_' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => 'sub' . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$subscriber_can = null;
if ( ! is_wp_error( $subscriber ) ) {
    wp_set_current_user( $subscriber );
    $subscriber_can = hjsc_admin_user_can();
    wp_set_current_user( 1 );
}
// 0.1.14 — the page is a normal page (shortcode in the editor), and the owner
// has a settings screen of his own.
$page_object  = get_post( hjsc_page_id() );
$page_content = $page_object ? (string) $page_object->post_content : '';
$settings_menu = false;
foreach ( (array) ( $GLOBALS['submenu'][ HJ_SC_ADMIN_SLUG ] ?? array() ) as $sub ) {
    if ( isset( $sub[2] ) && 'hj-sc-settings' === $sub[2] ) {
        $settings_menu = true;
    }
}
do_action( 'admin_init' );
ob_start();
hjsc_settings_render();
$settings_html = ob_get_clean();
$clean = hjsc_sanitize_settings( array(
    'title_fa'      => 'عنوانی که از پیشخوان نوشتم',
    'lead_fa'       => 'توضیحی که از پیشخوان نوشتم',
    'warning_fa'    => 'هشداری که از پیشخوان نوشتم',
    'sources_title' => 'منابع',
    'min_age'       => '18',
    'default_age'   => '33',
    'show_sources'  => '1',
    'enabled'       => '1',
    'emergency_note' => 'زنگ بزن',
) );
update_option( HJ_SC_SETTINGS_OPTION, $clean );
$config_texts = hjsc_config()['texts'];
} catch ( Throwable $e ) {
    $admin_error = get_class( $e ) . ': ' . $e->getMessage();
}
file_put_contents( '/wordpress/hj-admin.json', wp_json_encode( array(
    'error'            => $admin_error,
    'admin_can'        => $admin_can,
    'menu_slug'        => isset( $menu_hit[2] ) ? $menu_hit[2] : '',
    'menu_capability'  => isset( $menu_hit[1] ) ? $menu_hit[1] : '',
    'overview_bytes'   => strlen( $overview ),
    'overview_nonce'   => false !== strpos( $overview, 'name="_wpnonce"' ),
    'overview_upload'  => false !== strpos( $overview, 'type="file"' ),
    'save_result'      => $save,
    'option'           => $option,
    'file_exists'      => ( '' !== $option && is_readable( $path ) ),
    'saved_version'    => isset( $saved['bundle_version'] ) ? $saved['bundle_version'] : '',
    'saved_condition'  => isset( $saved['conditions'][0]['name_fa'] ) ? $saved['conditions'][0]['name_fa'] : '',
    'page_content'     => $page_content ?? '',
    'settings_menu'    => (bool) ( $settings_menu ?? false ),
    'settings_bytes'   => strlen( $settings_html ?? '' ),
    'settings_field'   => false !== strpos( $settings_html ?? '', 'name="' . HJ_SC_SETTINGS_OPTION . '[title_fa]"' ),
    'config_title'     => $config_texts['title'] ?? '',
    'config_default_age' => $config_texts['defaultAge'] ?? 0,
    'saved_count'      => isset( $saved['conditions'] ) ? count( $saved['conditions'] ) : 0,
    'base_count'       => $base_conditions,
    'package_unchanged' => $package_before === $package_after,
    'subscriber_can'   => $subscriber_can,
    'uploads_dir'      => hjsc_upload_dir(),
), JSON_UNESCAPED_UNICODE ) );
`;
  await startSite(writeBlueprint('admin.json', true, php));
  const adminPath = join(work, `wordpress/WordPress-${WP_VERSION}/hj-admin.json`);
  const started = Date.now();
  while (Date.now() - started < 120000 && !existsSync(adminPath)) {
    await new Promise((done) => setTimeout(done, 500));
  }
  const data = existsSync(adminPath) ? JSON.parse(readFileSync(adminPath, 'utf8')) : null;
  check('the admin blueprint finished without a fatal', Boolean(data) && data.error === null, String(data?.error ?? 'no report'));
  check('the admin screen is registered for the owner', Boolean(data) && data.menu_slug === 'hj-sc-content', data?.menu_slug ?? 'no report');
  check('the admin screen requires manage_options by default', data?.menu_capability === 'manage_options', data?.menu_capability ?? '');
  check('the owner can open the content screen', data?.admin_can === true);
  check('a subscriber cannot open the content screen', data?.subscriber_can === false, String(data?.subscriber_can));
  check('the overview renders', (data?.overview_bytes ?? 0) > 800, `${data?.overview_bytes ?? 0} bytes`);
  check('the overview carries a nonce', data?.overview_nonce === true);
  check('the overview offers a bundle upload', data?.overview_upload === true);
  check(
    'the page the owner edits is a normal page, with the shortcode in the editor',
    String(data?.page_content ?? '').includes('[hamyar_symptom_checker]'),
    String(data?.page_content ?? '').slice(0, 40),
  );
  check('the settings screen sits under the checker menu', data?.settings_menu === true);
  check(
    'the settings screen renders its fields',
    (data?.settings_bytes ?? 0) > 800 && data?.settings_field === true,
    `${data?.settings_bytes ?? 0} bytes`,
  );
  check('the saved title reaches the page config', data?.config_title === 'عنوانی که از پیشخوان نوشتم', data?.config_title ?? '');
  check('the saved opening age reaches the page config', Number(data?.config_default_age) === 33, String(data?.config_default_age ?? ''));
  check('saving a text edit succeeds', data?.save_result === 'saved', data?.save_result ?? 'no result');
  // "Bumped" means the next patch of whatever bundle is active — not a frozen
  // string. Hard-coding 0.2.1 silently pinned this scenario to phase-1 content.
  const packagedBundle = JSON.parse(readFileSync(packagedBundlePath(), 'utf8'));
  const expectedVersion = bumpDraftVersion(packagedBundle.bundle_version);
  check(
    'the saved file follows the upload contract',
    new RegExp(`^knowledge-bundle\\.${expectedVersion.replace(/\./g, '\\.')}\\.[0-9a-f]{8}\\.json$`).test(String(data?.option ?? '')),
    `${data?.option ?? ''} (expected ${expectedVersion})`,
  );
  check('the edited file exists in uploads/hj-sc', data?.file_exists === true);
  check('the new version is bumped, not overwritten', data?.saved_version === expectedVersion, `${data?.saved_version ?? ''} (expected ${expectedVersion})`);
  check('the edited text is in the new bundle', data?.saved_condition === 'ویرایش آزمایشی از پیشخوان', data?.saved_condition ?? '');
  check('the edit changed no condition count', data?.saved_count === data?.base_count, `${data?.saved_count} vs ${data?.base_count}`);
  check('the bundle inside the plugin is untouched', data?.package_unchanged === true);

  // What a visitor now receives.
  const page = await text('/symptom-checker/');
  const match = page.body.match(/window\.HJ_SC_CONFIG = (\{.*?\});/s);
  const config = match ? JSON.parse(match[1]) : null;
  check(
    'the page serves the title the owner saved in settings',
    String(page.body).includes('عنوانی که از پیشخوان نوشتم'),
    'config title',
  );
  check(
    'the checker now serves the dashboard-edited bundle',
    config?.bundleStatus?.source === 'upload' && String(config?.bundleUrl ?? '').includes(String(data?.option ?? 'x')),
    `${config?.bundleStatus?.source ?? 'none'} ${config?.bundleUrl ?? ''}`,
  );
  check('the cache buster is the hash of the new file', String(config?.bundleUrl ?? '').includes(`ver=${String(data?.option ?? '').match(/\.([0-9a-f]{8})\.json$/)?.[1]}`), config?.bundleUrl ?? '');
  check('the page still renders the checker', page.status === 200 && page.body.includes('id="app"'));
  check('the edited version reaches the page config', config?.bundleStatus?.version === expectedVersion, `${config?.bundleStatus?.version ?? ''} (expected ${expectedVersion})`);
  const served = await fetch(config?.bundleUrl ?? `${BASE}/missing.json`);
  const servedJson = served.status === 200 ? await served.json() : null;
  check('the served bundle carries the edited condition', servedJson?.conditions?.[0]?.name_fa === 'ویرایش آزمایشی از پیشخوان', servedJson?.conditions?.[0]?.name_fa ?? '');
  check('the served bundle keeps its questions', Array.isArray(servedJson?.questions) && servedJson.questions.length > 0, String(servedJson?.questions?.length ?? 0));

  // The admin screen itself is not public.
  const direct = await fetch(`${BASE}/wp-admin/admin.php?page=hj-sc-content`, { redirect: 'manual' });
  check('wp-admin is not reachable for a visitor', [302, 301, 403].includes(direct.status), String(direct.status));
}

let exitCode = 0;
try {
  console.log(`work dir: ${work}`);

  // 1. package
  const build = run('python3', [join(HERE, 'build_package.py'), '--out', join(work, 'pkg')]);
  const zipName = readdirSync(join(work, 'pkg')).find((name) => name.endsWith('.zip'));
  if (!zipName) throw new Error('no package built');
  console.log(build.stdout.trim());

  // 2. WordPress core
  const zipPath = join(work, 'wordpress.zip');
  // curl is used on purpose: it honours the proxy settings of the sandbox,
  // while Node's fetch does not.
  run('curl', ['--fail', '--location', '--silent', '--show-error', '--max-time', '300',
    '-o', zipPath, `https://codeload.github.com/WordPress/WordPress/zip/refs/tags/${WP_VERSION}`]);
  const digest = createHash('sha256').update(readFileSync(zipPath)).digest('hex');
  if (EXPECTED_HASH && digest !== EXPECTED_HASH) {
    throw new Error(`WordPress zip hash mismatch: ${digest}`);
  }
  if (!EXPECTED_HASH) console.log(`no pinned hash for ${WP_VERSION}; using digest ${digest}`);
  mkdirSync(join(work, 'wordpress'), { recursive: true });
  run('python3', ['-c', 'import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])', zipPath, join(work, 'wordpress')]);
  mkdirSync(join(work, 'plugins'), { recursive: true });
  run('python3', ['-c', 'import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])', join(work, 'pkg', zipName), join(work, 'plugins')]);
  console.log(`WordPress ${WP_VERSION} extracted, plugin ${zipName} unpacked`);

  // 3. tooling
  mkdirSync(join(work, 'mu'), { recursive: true });
  // The child theme 0.30.1 loads inc/symptom-checker-test.php, which declares
  // hj_sc_enqueue_assets() and friends. On 2026-10-31 that name collided with
  // the plugin and every activation died with "Cannot redeclare function".
  // Loading the same names from an mu-plugin reproduces the theme at boot.
  writeFileSync(
    join(work, 'mu/hj-fixture-theme-hj-sc-names.php'),
    `<?php
// Test fixture: the hj_sc_* functions the shipped child theme declares.
function hj_sc_enqueue_assets() {}
function hj_sc_is_test_request() { return false; }
function hj_sc_template_redirect() {}
function hj_sc_noindex_meta() {}
`,
  );
  writeFileSync(
    join(work, 'mu/hj-fixture-disease-cpt.php'),
    `<?php
// Test fixture: stands in for the child theme that registers the disease post type.
add_action( 'init', function () {
    register_post_type( 'disease', array( 'label' => 'Diseases', 'public' => true, 'has_archive' => true, 'rewrite' => array( 'slug' => 'disease' ), 'supports' => array( 'title', 'editor' ) ) );
} );
`,
  );
  writeFileSync(join(work, 'package.json'), JSON.stringify({ name: 'hj-sc-wp-check', private: true }));
  console.log('installing WordPress Playground CLI and jsdom (temp only)…');
  run('npm', ['install', '--no-audit', '--no-fund', '--silent', `@wp-playground/cli@${PLAYGROUND_VERSION}`, 'jsdom'], { cwd: work });

  // 4. draft phase: the page must not be reachable for a visitor
  await startSite(writeBlueprint('draft.json', false));
  let info = await waitForReport();
  check('WordPress booted with the plugin active', Boolean(info) && info.wp === WP_VERSION, info ? `WP ${info.wp} / PHP ${info.php}` : 'no report');
  if (PHP_VERSION) {
    // Playground ships a fixed set of PHP builds (8.5.10 today); say out loud
    // which one actually ran so a claim about the host's PHP stays honest.
    console.log(`requested PHP ${PHP_VERSION}, running PHP ${info?.php ?? '?'}`);
  }
  check('activation created the page as a draft', info?.page_status === 'draft', info?.page_status ?? '');
  check(
    'the first admin request finished the setup and cleared the pending note',
    info?.pending === false,
    `pending=${info?.pending}`,
  );
  check(
    'the theme fixture declares the hj_sc_* names that broke the live site',
    info?.theme_collision === true,
    `theme_collision=${info?.theme_collision}`,
  );
  check(
    'no activation error was recorded',
    info?.activation_error === null,
    JSON.stringify(info?.activation_error ?? null).slice(0, 200),
  );
  check('the draft page is not reachable for a visitor', (await statusOf(`/?page_id=${info?.page_id ?? 0}`)) === 404);
  check(
    'the homepage button stays hidden while the checker page is a draft',
    info?.home_button?.found === 0,
    `found=${info?.home_button?.found}`,
  );
  check('the bundle version reaches PHP', info?.bundle === '3.0.0-draft', info?.bundle ?? '');

  // 5. published phase: render the page and inspect what WordPress serves
  await startSite(writeBlueprint('published.json', true));
  info = await waitForReport();
  check('the page can be published by the owner', info?.page_status === 'publish', info?.permalink ?? '');
  check(
    'the homepage button points at the published checker page',
    info?.home_button?.found === info?.page_id && info?.home_button?.url === info?.permalink,
    `${info?.home_button?.url ?? 'none'} vs ${info?.permalink ?? 'none'}`,
  );
  const page = await text('/symptom-checker/');
  check('the checker page returns 200', page.status === 200);
  check('the tool markup is on the page', ['id="app"', 'id="safety"', 'id="live"'].every((needle) => page.body.includes(needle)));
  check('the page is noindex, nofollow', page.body.includes('noindex') && page.body.includes('nofollow'));
  const scriptTags = [...page.body.matchAll(/<script\b[^>]*>/gi)].map((match) => match[0]);
  // Newer cores print their own module scripts (speculation rules, block
  // assets), so the check looks at *our* tag instead of counting module tags.
  const ourScriptTag = scriptTags.find((tag) => tag.includes('id="hj-sc-app-js"'))
    ?? scriptTags.find((tag) => tag.includes('assets/ui/app.js'))
    ?? '';
  check(
    'the entry script is a module',
    /\btype="module"/.test(ourScriptTag),
    ourScriptTag || scriptTags.join(' | '),
  );
  check('the page is neither a shortcode nor an iframe', !page.body.includes('<iframe') && !page.body.includes('shortcode'));

  const configMatch = page.body.match(/window\.HJ_SC_CONFIG = (\{.*?\});/s);
  const config = configMatch ? JSON.parse(configMatch[1]) : null;
  check(
    'the inline config is printed before the module',
    Boolean(configMatch) && ourScriptTag !== ''
      && page.body.indexOf('window.HJ_SC_CONFIG') < page.body.indexOf(ourScriptTag),
  );
  check('the config points inside the plugin', Boolean(config?.bundleUrl?.includes('/wp-content/plugins/hamyar-symptom-checker/assets/')));
  check('the draft bundle version is exposed', config?.bundleVersion === '3.0.0-draft', config?.bundleVersion ?? '');
  check('the config states the question strategy', config?.questionStrategy === 'static_order', config?.questionStrategy ?? 'missing');
  check('the config states the age gate', config?.minAge === 18, String(config?.minAge ?? 'missing'));
  check('the published article is mapped', Boolean(config?.topicLinks?.app?.endsWith('/disease/appendicitis/')), JSON.stringify(config?.topicLinks ?? {}));
  check('the draft article is not mapped', !('kidney_stone' in (config?.topicLinks ?? {})));

  const assets = [
    config?.bundleUrl,
    config?.evidenceUrl,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/app.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/view-model.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/question-help.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/styles.css`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/anatomy-hero.jpg`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/engine.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/adapter.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/load-bundle.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/strategy.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/profile-match.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/evidence.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/validate-bundle.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/errors.js`,
  ].filter(Boolean);
  const assetStatuses = await Promise.all(assets.map(async (url) => (await fetch(url)).status));
  check('every asset of the package is served', assetStatuses.every((status) => status === 200), `${assets.length} files`);

  const home = await text('/');
  check('other pages do not load the checker assets', !home.body.includes('hamyar-symptom-checker/assets') && !home.body.includes('HJ_SC_CONFIG'));
  check('other pages still render normally', home.status === 200 && home.body.includes('</html>'));
  check('a draft article stays private', (await statusOf('/disease/kidney-stones/')) === 404);
  check('the mapped article is public', (await statusOf('/disease/appendicitis/')) === 200);

  // 6. the packaged UI against the live URLs
  if (config) await uiCheck(config);

  // 7. phase 5-a: publish content without publishing the plugin
  await bundlePublishChecks();

  // 8. phase 5-b: edit content in wp-admin, then check what a visitor gets
  await adminChecks();
} catch (error) {
  console.error(`\nERROR ${error?.message ?? error}`);
  exitCode = 1;
} finally {
  if (server) {
    server.kill('SIGTERM');
    await new Promise((done) => setTimeout(done, 800));
  }
  if (!keep) rmSync(work, { recursive: true, force: true });
  else console.log(`kept: ${work}`);
}

const failed = results.filter((item) => !item.ok);
const summary = `${results.length - failed.length}/${results.length} WordPress checks passed`;
console.log(`\n${summary}`);
if (failed.length) {
  for (const item of failed) console.log(`FAILED: ${item.label}${item.detail ? ` — ${item.detail}` : ''}`);
  exitCode = 1;
}
process.exit(exitCode);

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
const PORT = Number(process.env.HJ_WP_PORT ?? 9411);
const BASE = `http://127.0.0.1:${PORT}`;
const keep = process.argv.includes('--keep');

const work = mkdtempSync(join(tmpdir(), 'hj-sc-wp-'));
let server = null;
const results = [];

function check(label, ok, detail = '') {
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

function writeBlueprint(name, publishPage) {
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
flush_rewrite_rules( false );
file_put_contents( '/wordpress/hj-check.json', wp_json_encode( array(
    'wp' => get_bloginfo( 'version' ),
    'php' => PHP_VERSION,
    'page_id' => $page,
    'page_status' => get_post_status( $page ),
    'permalink' => get_permalink( $page ),
    'published' => get_permalink( $published ),
    'draft_status' => get_post_status( $draft_id ),
    'links' => hj_sc_topic_links(),
    'bundle' => hj_sc_bundle_version(),
    'pending' => (bool) get_option( 'hj_sc_activation_pending' ),
    'activation_error' => get_option( 'hj_sc_last_error', null ),
), JSON_UNESCAPED_UNICODE ) );
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

async function uiCheck(config) {
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

  await import(pathToFileURL(join(work, 'plugins/hamyar-symptom-checker/assets/ui/app.js')).href);
  await new Promise((done) => setTimeout(done, 600));

  const doc = window.document;
  check('packaged UI boots inside WordPress', Boolean(doc.querySelector('#app button')));
  const start = [...doc.querySelectorAll('#app button')].find((button) => button.textContent.includes('شروع'));
  check('the start button is rendered', Boolean(start));
  if (!start) return;

  start.click();
  await new Promise((done) => setTimeout(done, 150));
  check('the first question renders from the packaged bundle', Boolean(doc.getElementById('question-title')));

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
  check(
    'the packaged UI only asked this WordPress for its two data files',
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
  check(
    'the page loads no resource from another origin',
    externalLoaders.length === 0,
    `${loaders.length} loaders: ` + externalLoaders.map((match) => match[2]).join(' ; '),
  );
  check('the page has no inline font import', !/@import\s+(?:url\()?['"]?https?:/i.test(page.body));
  check('the checker page has no posting form', !/<form[^>]+action=/i.test(page.body));
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
  check('activation created the page as a draft', info?.page_status === 'draft', info?.page_status ?? '');
  check(
    'the first admin request finished the setup and cleared the pending note',
    info?.pending === false,
    `pending=${info?.pending}`,
  );
  check(
    'no activation error was recorded',
    info?.activation_error === null,
    JSON.stringify(info?.activation_error ?? null).slice(0, 200),
  );
  check('the draft page is not reachable for a visitor', (await statusOf(`/?page_id=${info?.page_id ?? 0}`)) === 404);
  check('the bundle version reaches PHP', info?.bundle === '0.2.0-draft', info?.bundle ?? '');

  // 5. published phase: render the page and inspect what WordPress serves
  await startSite(writeBlueprint('published.json', true));
  info = await waitForReport();
  check('the page can be published by the owner', info?.page_status === 'publish', info?.permalink ?? '');
  const page = await text('/symptom-checker/');
  check('the checker page returns 200', page.status === 200);
  check('the plugin template is used', ['id="app"', 'id="safety"', 'id="live"'].every((needle) => page.body.includes(needle)));
  check('the page is noindex, nofollow', page.body.includes('noindex') && page.body.includes('nofollow'));
  const scriptTags = [...page.body.matchAll(/<script\b[^>]*>/gi)].map((match) => match[0]);
  // Newer cores print their own module scripts (speculation rules, block
  // assets), so the check looks at *our* tag instead of counting module tags.
  const ourScriptTag = scriptTags.find((tag) => tag.includes('id="hj-sc-app-js"')) ?? '';
  check(
    'the entry script is a module',
    /\btype="module"/.test(ourScriptTag),
    ourScriptTag || scriptTags.join(' | '),
  );
  check('the page is neither a shortcode nor an iframe', !page.body.includes('<iframe') && !page.body.includes('shortcode'));

  const configMatch = page.body.match(/window\.HJ_SC_CONFIG = (\{.*?\});/s);
  const config = configMatch ? JSON.parse(configMatch[1]) : null;
  check('the inline config is printed before the module', Boolean(configMatch) && page.body.indexOf('window.HJ_SC_CONFIG') < page.body.indexOf('<script type="module"'));
  check('the config points inside the plugin', Boolean(config?.bundleUrl?.includes('/wp-content/plugins/hamyar-symptom-checker/assets/')));
  check('the draft bundle version is exposed', config?.bundleVersion === '0.2.0-draft', config?.bundleVersion ?? '');
  check('the published article is mapped', Boolean(config?.topicLinks?.app?.endsWith('/disease/appendicitis/')), JSON.stringify(config?.topicLinks ?? {}));
  check('the draft article is not mapped', !('kidney_stone' in (config?.topicLinks ?? {})));

  const assets = [
    config?.bundleUrl,
    config?.evidenceUrl,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/app.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/view-model.js`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/ui/styles.css`,
    `${BASE}/wp-content/plugins/hamyar-symptom-checker/assets/engine/src/engine.js`,
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

/**
 * Run the plugin's PHP checks in a real PHP interpreter (WebAssembly build).
 *
 *   npm install --no-save @php-wasm/node
 *   node tools/symptom-checker-plugin/php-checks.mjs
 *
 * Steps: build the installable package, unpack it, then in PHP 8.3
 *   - parse every PHP file with token_get_all(..., TOKEN_PARSE),
 *   - run the activation / mapping / config / enqueue / robots / template /
 *     guard scenarios against WordPress stubs.
 *
 * This proves the plugin's own logic, not how WordPress behaves. Either way the
 * package is the thing under test, so a drift between sources and release shows
 * up here too.
 */
import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readFileSync } from 'node:fs';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../..');
const PHP_VERSION = process.env.HJ_PHP_VERSION ?? '8.3';

let PHP;
let loadNodeRuntime;
let createNodeFsMountHandler;
try {
  ({ PHP } = await import('@php-wasm/universal'));
  ({ loadNodeRuntime, createNodeFsMountHandler } = await import('@php-wasm/node'));
} catch {
  console.log('SKIP  php-checks needs @php-wasm: run `npm install --no-save @php-wasm/node @php-wasm/universal`');
  process.exit(0);
}

const work = mkdtempSync(join(tmpdir(), 'hj-sc-php-'));
const cleanup = () => rmSync(work, { recursive: true, force: true });
process.on('exit', cleanup);

// 1. Build and unpack the real package so the checks run on shipped files.
const build = spawnSync('python3', [join(HERE, 'build_package.py'), '--out', work], { encoding: 'utf8' });
if (build.status !== 0) {
  console.error(build.stdout, build.stderr);
  process.exit(build.status ?? 1);
}
const zipName = readdirSync(work).find((name) => name.endsWith('.zip'));
if (!zipName) {
  console.error('no package was built');
  process.exit(1);
}
const unzip = spawnSync('python3', ['-c', `import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])`, join(work, zipName), work], { encoding: 'utf8' });
if (unzip.status !== 0) {
  console.error(unzip.stderr);
  process.exit(unzip.status ?? 1);
}
const pluginDir = `/work/hamyar-symptom-checker`;

// 2. Boot PHP and mount the package + the test harness.
const runtimeId = await loadNodeRuntime(PHP_VERSION, { emscriptenOptions: { processId: 1 } });
const php = new PHP(runtimeId);
await php.mount('/work', createNodeFsMountHandler(work));
await php.mount('/php', createNodeFsMountHandler(HERE));

const version = await php.run({ code: '<?php echo PHP_VERSION;' });
console.log(`PHP ${version.text.trim()} — package ${zipName}`);

const scenarios = [
  'syntax',
  'guards',
  'activation',
  'mapping',
  'mapping-nodisease',
  'slugs',
  'slugs-filter',
  'config',
  'escaping',
  'enqueue',
  'robots',
  'template',
];

const requested = process.argv.slice(2);
const selected = requested.length ? requested : scenarios;
let failed = 0;
let checks = 0;

for (const scenario of selected) {
  const code = `<?php $argv = array('test-plugin.php', '${pluginDir}', '${scenario}'); $argc = 3; require '/php/php/test-plugin.php';`;
  let result;
  try {
    result = await php.run({ code });
  } catch (error) {
    // A non-zero PHP exit code rejects the promise; the output is still usable.
    result = error?.response;
    if (!result) {
      console.log(`FAIL  ${scenario} crashed: ${error?.message ?? error}`);
      failed += 1;
      continue;
    }
  }
  const text = (result.text ?? '').trimEnd();
  if (result.errors) {
    console.log(text);
    console.error(String(result.errors));
  } else {
    console.log(text);
  }
  const summary = text.split('\n').pop() ?? '';
  const match = summary.match(/(\d+)\/(\d+) checks passed/);
  if (match) {
    checks += Number(match[2]);
    if (Number(match[1]) !== Number(match[2])) failed += 1;
  } else {
    failed += 1;
  }
  if (result.exitCode && result.exitCode !== 0) failed += Number(match ? 0 : 1);
}

console.log(`\n${selected.length - failed}/${selected.length} PHP scenarios clean, ${checks} checks`);
process.exit(failed ? 1 : 0);

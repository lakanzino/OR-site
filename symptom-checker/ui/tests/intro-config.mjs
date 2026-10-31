/**
 * What wp-admin can change, and what the page must do with it.
 *
 * The owner asked for the first page's three sentences, the source list and the
 * opening age to be his to edit. This runs the real `app.js` in jsdom with a
 * `HJ_SC_CONFIG` shaped exactly like the one the plugin prints, and checks that
 * the page shows *his* words — not the ones shipped in the file.
 *
 * It also checks the two failure modes that are worse than a wrong word: an
 * empty setting must fall back to the shipped wording (never a blank title),
 * and the tool must still open on a usable age.
 */

import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../../..');
const UI = resolve(REPO, 'symptom-checker/ui');

let JSDOM;
let VirtualConsole;
try {
  ({ JSDOM, VirtualConsole } = await import('jsdom'));
} catch {
  console.log('SKIP  intro-config needs jsdom: run `npm install --no-save jsdom` first');
  process.exit(0);
}

const wait = (ms) => new Promise((done) => setTimeout(done, ms));
const checks = [];
const errors = [];

function check(label, ok, detail = '') {
  checks.push({ label, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`);
}

/**
 * Boot the real page with a given config.
 *
 * @param {object} texts The `config.texts` the plugin would print.
 * @param {string} label A scenario name, for the log.
 */
async function boot(texts, label) {
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', (error) => errors.push(`${label}: ${error.message}`));
  virtualConsole.on('error', (...args) => errors.push(`${label}: ${args.join(' ')}`));

  const dom = new JSDOM(readFileSync(resolve(UI, 'index.html'), 'utf8'), {
    url: 'http://demo.local/symptom-checker/ui/index.html',
    runScripts: 'outside-only',
    pretendToBeVisual: true,
    virtualConsole,
  });
  const { window } = dom;

  window.HJ_SC_CONFIG = {
    pluginVersion: '0.1.14',
    minAge: 18,
    bundleUrl: 'https://example.test/assets/data/knowledge-bundle.v3.json',
    evidenceUrl: 'https://example.test/assets/engine/data/evidence-map.v1.json',
    topicLinks: {},
    texts,
  };

  window.fetch = async (url) => {
    const path = new URL(url, window.location.href).pathname;
    const map = {
      '/assets/data/knowledge-bundle.v3.json': resolve(REPO, 'docs/symptom-checker/phase-6/knowledge-bundle.v3.json'),
      '/assets/engine/data/evidence-map.v1.json': resolve(REPO, 'symptom-checker/engine/data/evidence-map.v1.json'),
    };
    const file = map[path];
    if (!file) {
      return { ok: false, status: 404, async json() { throw new Error('404'); }, async text() { return ''; } };
    }
    const body = readFileSync(file, 'utf8');
    return { ok: true, status: 200, async json() { return JSON.parse(body); }, async text() { return body; } };
  };

  globalThis.window = window;
  globalThis.document = window.document;
  globalThis.fetch = window.fetch;
  globalThis.location = window.location;

  await import(`${pathToFileURL(resolve(UI, 'app.js')).href}?case=${encodeURIComponent(label)}`);
  await wait(300);
  return window;
}

const text = (node) => (node?.textContent ?? '').replace(/\s+/g, ' ').trim();

// --- 1. the owner's words win ---------------------------------------------
{
  const window = await boot({
    title: 'عنوانی که خودم نوشتم',
    lead: 'جمله‌ای که خودم نوشتم',
    warning: 'هشداری که خودم نوشتم',
    sourcesTitle: 'مراجع من',
    showSources: true,
    defaultAge: 42,
  }, 'mine');
  const doc = window.document;

  check('the title is the one saved in wp-admin', text(doc.querySelector('#app .workspace__title')) === 'عنوانی که خودم نوشتم', text(doc.querySelector('#app .workspace__title')));
  check('the sentence under it is the saved one', text(doc.querySelector('#app .workspace__lead')) === 'جمله‌ای که خودم نوشتم');
  check('the yellow box carries the saved warning', text(doc.querySelector('#app .warn-box')) === 'هشداری که خودم نوشتم');
  check('the source list takes its heading from the settings', text(doc.querySelector('#app .sources__title')) === 'مراجع من');
  check('the age field opens on the saved age', doc.querySelector('#app #hj-age')?.value === '۴۲', doc.querySelector('#app #hj-age')?.value);
}

// --- 2. no source list when the owner turns it off ------------------------
{
  const window = await boot({
    title: 'عنوان',
    lead: 'توضیح',
    warning: 'هشدار',
    showSources: false,
    defaultAge: 30,
  }, 'no-sources');
  const doc = window.document;

  check('turning the list off removes it entirely', !doc.querySelector('#app .sources'));
  check('the rest of the page still renders', Boolean(doc.querySelector('#app .workspace__title') && doc.querySelector('#app #hj-age')));
}

// --- 3. an empty setting can never blank the page -------------------------
{
  const window = await boot({
    title: '   ',
    lead: '',
    warning: '',
    showSources: true,
    defaultAge: 30,
  }, 'empty');
  const doc = window.document;

  check(
    'an empty title falls back to the shipped sentence',
    text(doc.querySelector('#app .workspace__title')).includes('ربات اختصاصی ارزیابی احتمالی بیماری'),
    text(doc.querySelector('#app .workspace__title')),
  );
  check('an empty warning falls back too', text(doc.querySelector('#app .warn-box')).includes('جای معاینهٔ پزشکی'));
}

// --- 4. a nonsense age falls back to a usable one -------------------------
{
  const window = await boot({
    title: 'عنوان',
    lead: 'توضیح',
    warning: 'هشدار',
    showSources: false,
    defaultAge: 'abc',
  }, 'bad-age');
  const doc = window.document;
  check('a broken default age still opens on a usable number', doc.querySelector('#app #hj-age')?.value === '۳۰', doc.querySelector('#app #hj-age')?.value);
}

console.log('');
if (errors.length) {
  for (const error of errors) console.log(`ERROR ${error}`);
}
const failed = checks.filter((item) => !item.ok).length + (errors.length ? 1 : 0);
console.log(`${checks.length - checks.filter((item) => !item.ok).length}/${checks.length} checks passed`);
process.exit(failed ? 1 : 0);

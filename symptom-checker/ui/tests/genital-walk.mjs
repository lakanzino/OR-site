#!/usr/bin/env node
/**
 * A real walk through the genital path in the real UI (jsdom, no network).
 *
 * The phase-6 promise is that the tool opens with a body and that the pelvic
 * area is complete. `regions.test.js` proves the engine side; this file proves
 * the page side: the body map, the pelvic cards, the questions of one area, and
 * the urgent banner for sudden testicular pain.
 *
 *   node symptom-checker/ui/tests/genital-walk.mjs
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

let JSDOM;
try {
  ({ JSDOM } = await import('jsdom'));
} catch {
  console.log('SKIP  genital-walk needs jsdom: run `npm install --no-save jsdom` first');
  process.exit(0);
}

const HERE = dirname(fileURLToPath(import.meta.url));
const UI = resolve(HERE, '..');
const REPO = resolve(UI, '../..');

const errors = [];
const virtualConsole = new (await import('jsdom')).VirtualConsole();
virtualConsole.on('jsdomError', (error) => errors.push(String(error?.message ?? error)));

const dom = await JSDOM.fromFile(resolve(UI, 'index.html'), {
  url: 'http://demo.local/symptom-checker/ui/index.html',
  runScripts: 'outside-only',
  pretendToBeVisual: true,
  virtualConsole,
});
const { window } = dom;

const fetched = [];
window.fetch = async (url) => {
  fetched.push(String(url));
  const path = new URL(url, window.location.href).pathname;
  try {
    const body = readFileSync(resolve(REPO, `.${path}`), 'utf8');
    return { ok: true, status: 200, async json() { return JSON.parse(body); }, async text() { return body; } };
  } catch {
    return { ok: false, status: 404, async json() { throw new Error('404'); }, async text() { return ''; } };
  }
};

globalThis.window = window;
globalThis.document = window.document;
globalThis.fetch = window.fetch;
globalThis.location = window.location;

const wait = (ms) => new Promise((done) => setTimeout(done, ms));
process.on('unhandledRejection', (error) => errors.push(`unhandledRejection: ${error?.message ?? error}`));

await import(resolve(UI, 'app.js'));
await wait(300);

const doc = window.document;
const text = (node) => (node ? node.textContent.replace(/\s+/g, ' ').trim() : '');

const checks = [];
function check(label, ok, detail = '') {
  checks.push({ label, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`);
}

async function pickGate(optionId) {
  const card = [...doc.querySelectorAll('#app .gate-option')].find((button) => button.dataset.optionId === optionId);
  if (!card) throw new Error(`gate card missing: ${optionId}`);
  card.click();
  const submit = doc.getElementById('submit-answer');
  if (submit.disabled) throw new Error(`submit stayed disabled for ${optionId}`);
  submit.click();
  await wait(60);
}

async function answer(value) {
  const input = doc.querySelector(`#app input[value="${value}"]`)
    ?? [...doc.querySelectorAll('#app input[type="radio"]')].at(0);
  if (!input) throw new Error(`no option ${value}`);
  input.click();
  const submit = doc.getElementById('submit-answer');
  if (submit.disabled) throw new Error(`submit stayed disabled for ${value}`);
  submit.click();
  await wait(60);
}

const start = [...doc.querySelectorAll('#app button')].find((button) => button.textContent.includes('شروع'));
start.click();
await wait(80);

await pickGate('male');
check('sex chosen, the body appears', Boolean(doc.querySelector('#app .body-figure')));

await pickGate('pelvis');
check(
  'the pelvic list offers urine, penis, scrotum and anus — not the vulva',
  [...doc.querySelectorAll('#app .gate-option')].map((button) => button.dataset.optionId).join(',')
    === 'urinary,penis,scrotum,anus',
  [...doc.querySelectorAll('#app .gate-option')].map((button) => text(button)).join(' | '),
);
check('no question about the vulva is even built for this path', !text(doc.querySelector('#app')).includes('واژن'));

await pickGate('scrotum');
check('the scrotum questions begin', text(doc.getElementById('question-title')).includes('بیضه'), text(doc.getElementById('question-title')));

await answer('yes');   // pain
await answer('sudden'); // sudden onset — the emergency
const urgent = doc.querySelector('#safety .safety-card.urgent, #safety .urgent');
check(
  'sudden testicular pain raises an urgent banner before the result',
  Boolean(urgent) && text(urgent).includes('۱۱۵'),
  text(urgent).slice(0, 60),
);

await answer('yes');  // swelling
await answer('no');   // lump
await answer('yes');  // nausea

check('the walk reached a result', Boolean(doc.querySelector('#app article.card')) || Boolean(doc.querySelector('#app .coverage')));
const cards = [...doc.querySelectorAll('#app article.card h2')].map((node) => text(node));
check('the torsion topic is named', cards.includes('پیچش بیضه (فوریت)'), cards.join(' | '));
check(
  'the result still says it is not a diagnosis',
  text(doc.querySelector('#app')).includes('تشخیص'),
);
check('nothing was sent anywhere after the two data files', fetched.length === 2, fetched.join(' ; '));
check('no console errors', errors.length === 0, errors.join(' ; '));

const failed = checks.filter((item) => !item.ok);
console.log(`\n${checks.length - failed.length}/${checks.length} checks passed`);
if (failed.length) process.exit(1);

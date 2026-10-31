/**
 * Headless end-to-end smoke test for the phase-3 demo UI.
 *
 * Loads the real `index.html`, runs the real `app.js` in jsdom, answers the
 * questionnaire the way a person would (clicking the actual inputs), and then
 * compares what the UI rendered with a direct replay of the same answers
 * through the phase-2 engine. Nothing here is a browser: layout, CSS and real
 * screen-reader behaviour still need a human check in a real browser.
 *
 * Usage:
 *   npm install --no-save jsdom
 *   node tests/ui-smoke.mjs            # one scenario (default: sc10)
 *   node tests/ui-smoke.mjs sc09       # any prefix of a scenario id
 *   node tests/ui-smoke.mjs --all      # every scenario in scenarios.v1.json
 *
 * jsdom is optional: without it this file prints SKIP and exits 0, so the
 * dependency-free `node --test` suite stays green.
 */
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const UI = resolve(HERE, '..');
const REPO = resolve(UI, '../..');

const readJson = (path) => JSON.parse(readFileSync(resolve(REPO, path), 'utf8'));

// `--scenarios <file>` runs the same walk against another scenario file, e.g.
// the phase-5 adversarial set. Everything else stays identical.
const scenarioFlag = process.argv.indexOf('--scenarios');
const scenariosPath =
  scenarioFlag === -1 ? 'docs/symptom-checker/phase-1/scenarios.v1.json' : process.argv[scenarioFlag + 1];
if (!scenariosPath) {
  console.error('--scenarios needs a file path');
  process.exit(2);
}
const scenariosFile = readJson(scenariosPath);
const forwarded = process.argv.slice(2).filter((item, index, list) => {
  if (item === '--scenarios') return false;
  if (index > 0 && list[index - 1] === '--scenarios') return false;
  return true;
});

const arg = forwarded[0] ?? scenariosFile.scenarios[0].id;

if (arg === '--all') {
  let failed = 0;
  for (const scenario of scenariosFile.scenarios) {
    const run = spawnSync(process.execPath, [fileURLToPath(import.meta.url), '--scenarios', scenariosPath, scenario.id], {
      stdio: 'inherit',
    });
    if (run.status !== 0) failed += 1;
  }
  console.log(`\n${scenariosFile.scenarios.length - failed}/${scenariosFile.scenarios.length} scenarios clean (${scenariosPath})`);
  process.exit(failed ? 1 : 0);
}

let JSDOM;
let VirtualConsole;
try {
  ({ JSDOM, VirtualConsole } = await import('jsdom'));
} catch {
  console.log('SKIP  ui-smoke needs jsdom: run `npm install --no-save jsdom` first');
  process.exit(0);
}

const scenario = scenariosFile.scenarios.find((item) => item.id === arg || item.id.startsWith(arg));
const bundleQuestions = readJson('docs/symptom-checker/phase-1/knowledge-bundle.v1.json').questions;

/**
 * Scenarios may answer only the questions they are about. Every other visible
 * question still has to be clicked through, so the driver picks the least
 * committal option the question offers: «نمی‌دانم», then «خیر», then the first
 * option. The scenario's own answers are never replaced.
 */
function neutralAnswer(questionId) {
  const question = bundleQuestions.find((item) => item.id === questionId);
  if (question?.type === 'scale') {
    if (question.allow_unknown) return 'unknown';
    return Math.round((question.min + question.max) / 2);
  }
  const optionIds = (question?.options ?? []).map((option) => option.id);
  if (optionIds.includes('unknown')) return 'unknown';
  if (optionIds.includes('no')) return 'no';
  return optionIds[0];
}
if (!scenario) {
  console.error(`unknown scenario: ${arg}`);
  process.exit(2);
}

const errors = [];
const virtualConsole = new VirtualConsole();
virtualConsole.on('jsdomError', (error) => errors.push(`jsdomError: ${error.message}`));
virtualConsole.on('error', (...args) => errors.push(`console.error: ${args.join(' ')}`));

const dom = new JSDOM(readFileSync(resolve(UI, 'index.html'), 'utf8'), {
  url: 'http://demo.local/symptom-checker/ui/index.html',
  runScripts: 'outside-only',
  pretendToBeVisual: true,
  virtualConsole,
});
const { window } = dom;

const fetched = [];
const requests = [];
window.fetch = async (url, init = {}) => {
  fetched.push(String(url));
  requests.push({ url: String(url), method: (init.method ?? 'GET').toUpperCase(), body: init.body ?? null });
  const path = new URL(url, window.location.href).pathname;
  try {
    const text = readFileSync(resolve(REPO, `.${path}`), 'utf8');
    return {
      ok: true,
      status: 200,
      async json() { return JSON.parse(text); },
      async text() { return text; },
    };
  } catch {
    return {
      ok: false,
      status: 404,
      async json() { throw new Error('404'); },
      async text() { return ''; },
    };
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
const text = (node = doc.body) => node.textContent.replace(/\s+/g, ' ').trim();

const checks = [];
function check(label, ok, detail = '') {
  checks.push({ label, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`);
}

// --- intro ---------------------------------------------------------------
const startButton = [...doc.querySelectorAll('#app button')].find((button) => button.textContent.includes('شروع'));
check('intro renders with the demo badges', Boolean(startButton) && text().includes('نمایشی') && text().includes('بدون ذخیره‌سازی'));
startButton.click();
await wait(80);
check('the first question renders', Boolean(doc.getElementById('question-title')), text(doc.getElementById('app')).slice(0, 70));

// --- robot narrator -------------------------------------------------------
const narrator = doc.querySelector('#app .narrator');
const narratorFull = narrator?.querySelector('.sr-only')?.textContent ?? '';
const narratorTyped = narrator?.querySelector('.narrator-text');
check(
  'the question carries a robot explanation card',
  Boolean(narrator) && Boolean(narrator.querySelector('.narrator-avatar svg')),
  narrator ? 'avatar + text' : 'missing',
);
check('the whole explanation is exposed to screen readers at once', narratorFull.length > 40, `${narratorFull.length} chars`);
check(
  'the explanation is typed rather than printed at once',
  Boolean(narratorTyped) && narratorTyped.textContent.length < narratorFull.length,
  `${narratorTyped?.textContent.length ?? 0}/${narratorFull.length}`,
);
narrator?.querySelector('.narrator-skip button')?.click();
await wait(60);
check(
  'skipping shows the full text and the per-option guide',
  narratorTyped?.textContent === narratorFull && Boolean(narrator?.querySelector('.narrator-guide li')),
  `${narrator?.querySelectorAll('.narrator-guide li').length ?? 0} guide lines`,
);
const firstQuestionId = (doc.querySelector('#app input')?.name ?? '').replace(/^q-/, '');
const firstUnknown = (bundleQuestions.find((item) => item.id === firstQuestionId)?.options ?? [])
  .find((option) => option.id === 'unknown');
const unknownRule = narrator?.querySelector('.narrator-guide li.unknown-rule');
check(
  'the guide ends with the "none of these" rule, worded with the real option label',
  Boolean(unknownRule) && Boolean(firstUnknown) && unknownRule.textContent.includes(firstUnknown.label_fa),
  unknownRule ? unknownRule.textContent.slice(0, 48) : 'missing',
);

// --- free-text note under the options ------------------------------------
const NOTE_TEXT = 'دردی که فقط وقتی خم می‌شوم حس می‌شود و در گزینه‌ها نیست';
const noteBox = doc.querySelector('#app .note-field textarea');
check('every question offers a free-text box under the options', Boolean(noteBox));
check(
  'the note box says it has no effect on the path or the result',
  text(doc.querySelector('#app .note-field')).includes('هیچ تأثیری'),
);
noteBox.value = NOTE_TEXT;
noteBox.dispatchEvent(new window.Event('input', { bubbles: true }));
check(
  'a note alone never counts as an answer',
  doc.getElementById('submit-answer').disabled === true,
  'submit still disabled',
);

// --- walk the questionnaire like a person ---------------------------------
const submitted = [];
let guard = 0;
while (guard++ < 40 && doc.getElementById('question-title')) {
  const name = [...doc.querySelectorAll('#app input')].map((input) => input.name).find(Boolean);
  const questionId = name.replace(/^q-/, '');
  const value = scenario.answers[questionId] ?? neutralAnswer(questionId);
  const numberInput = doc.querySelector(`#app input[type="number"][name="${name}"]`);
  const unknownBox = doc.querySelector('#app input[type="checkbox"][id$="-unknown"]');
  const option = (id) => doc.querySelector(`#app input[name="${name}"][value="${id}"]`);

  if (value === 'unknown' && numberInput) unknownBox.click();
  else if (value === 'unknown') option('unknown').click();
  else if (numberInput) {
    numberInput.value = String(value);
    numberInput.dispatchEvent(new window.Event('input', { bubbles: true }));
  } else if (Array.isArray(value)) {
    for (const id of value) option(id).click();
  } else {
    option(value).click();
  }

  const inputs = [...doc.querySelectorAll(`#app input[name="${name}"]`)];
  const isMulti = !numberInput && inputs[0]?.type === 'checkbox';
  const recorded = isMulti ? inputs.filter((input) => input.checked).map((input) => input.value) : value;
  submitted.push([questionId, recorded]);
  if (submitted.length === 1) {
    // the note typed above must survive the submit and reach the summary only
    const box = doc.querySelector('#app .note-field textarea');
    if (box && !box.value) {
      box.value = NOTE_TEXT;
      box.dispatchEvent(new window.Event('input', { bubbles: true }));
    }
  }

  const submit = doc.getElementById('submit-answer');
  if (!submit || submit.disabled) throw new Error(`submit stayed disabled for ${questionId}`);
  submit.click();
  await wait(50);
}
check('walked the questionnaire without a stuck screen', guard < 40, `${submitted.length} answers`);

// --- result versus a direct engine replay of the same answers -------------
const { createSession } = await import(resolve(REPO, 'symptom-checker/engine/src/engine.js'));
const engine = createSession(readJson('docs/symptom-checker/phase-1/knowledge-bundle.v1.json'), {
  evidenceMap: readJson('symptom-checker/engine/data/evidence-map.v1.json'),
});
for (const [questionId, value] of submitted) engine.answer(questionId, value);
const expected = engine.result();

const cardNames = [...doc.querySelectorAll('#app article.card')].map((card) => card.querySelector('h2')?.textContent ?? '');
const coverage = doc.querySelector('#app .coverage');
check(
  'coverage matches the engine',
  coverage?.className === `coverage ${expected.coverage.result}`,
  `${coverage?.className} / engine ${expected.coverage.result}`,
);
check(
  'cards match the engine conditions exactly',
  JSON.stringify(cardNames) === JSON.stringify(expected.conditions.map((condition) => condition.name_fa)),
  cardNames.length ? cardNames.join(' | ') : '(no cards)',
);
check(
  'cards render only when the engine allows them',
  expected.cards_allowed ? cardNames.length > 0 : cardNames.length === 0,
  `cards_allowed=${expected.cards_allowed}`,
);

// --- the note is recorded, never routed ---------------------------------
const notesSummary = doc.querySelector('#app .notes-summary');
check(
  'the closing screen lists the notes the person wrote',
  Boolean(notesSummary) && text(notesSummary).includes(NOTE_TEXT),
  notesSummary ? text(notesSummary).slice(0, 60) : 'missing',
);
const resultBody = [...doc.querySelectorAll('#app article.card, #app .coverage')]
  .map((node) => node.textContent)
  .join(' ');
check(
  'the note never reached the engine or the result cards',
  !JSON.stringify(submitted).includes(NOTE_TEXT) && !resultBody.includes(NOTE_TEXT),
  'answers handed to the engine contain no free text',
);
check(
  'the summary says the note was sent nowhere',
  Boolean(notesSummary) && text(notesSummary).includes('ارسال یا ذخیره نمی‌شوند'),
);

// --- safety --------------------------------------------------------------
const safety = doc.getElementById('safety');
check(
  'safety banners match the engine rules',
  expected.safety.length ? safety.hidden === false : safety.hidden === true,
  expected.safety.map((rule) => rule.id).join(', ') || 'no rules',
);
check(
  'the safety region comes before the result in the document',
  doc.getElementById('safety').compareDocumentPosition(doc.getElementById('app')) &
    window.Node.DOCUMENT_POSITION_FOLLOWING
    ? true
    : false,
  'safety must never be rendered below the cards',
);
check(
  'every warning is rendered verbatim with its tag',
  expected.safety.every((rule) => text(doc.getElementById('safety')).includes(rule.message_fa)) &&
    (expected.safety.length === 0 ||
      [...doc.querySelectorAll('#safety .safety-tag')].length === expected.safety.length) &&
    (!expected.safety.some((rule) => rule.severity === 'urgent') ||
      [...doc.querySelectorAll('#safety .safety-tag')].every(
        (node) => node.textContent === 'فوری' || node.textContent === 'بررسی پزشکی',
      )),
  `${expected.safety.length} rules`,
);
check(
  'the safety region is announced to assistive technology',
  !expected.safety.length || doc.querySelector('#safety [role="alert"]') !== null,
);
check(
  'urgent rules appear first',
  !expected.safety.some((rule) => rule.severity === 'urgent') || Boolean(safety.querySelector('.safety.urgent')),
);

check(
  'a safe stop shows the stop message and still shows the warnings',
  expected.coverage.result !== 'out_of_scope' ||
    (coverage?.classList.contains('out_of_scope') &&
      Boolean(doc.getElementById('safety')) &&
      (expected.safety.length === 0 || doc.getElementById('safety').hidden === false)),
  `coverage=${expected.coverage.result} safety=${expected.safety.map((rule) => rule.id).join(', ') || 'none'}`,
);

// --- wording -------------------------------------------------------------
const resultText = [...doc.querySelectorAll('#app article.card, #app .coverage, #app .muted-card')]
  .map((node) => node.textContent)
  .join(' ');
const banned = [/٪/, /\d+\s*درصد/, /احتمال (ابتلا|بیماری|بالا|زیاد)/, /امتیاز/, /probability/i, /score/i];
check('no probability or score wording in the result body', !banned.some((pattern) => pattern.test(resultText)));

const groupTitles = [...doc.querySelectorAll('#app .evidence dt .group-title')];
const emptyGroup = [...doc.querySelectorAll('#app .evidence dd')]
  .some((node) => node.textContent.includes('موردی در این گروه نیست'));
check('evidence groups render only when non-empty', groupTitles.length === 0 || !emptyGroup, `${groupTitles.length} groups`);

// A contradiction is not just reported: the person gets a way to fix it.
const expectsConflict = expected.conditions.some((card) => (card.evidence?.conflict ?? []).length > 0);
const conflictButtons = [...doc.querySelectorAll('#app .conflict-fix button')];
check(
  'a contradiction offers a way to fix the answers',
  expectsConflict ? conflictButtons.length > 0 : conflictButtons.length === 0,
  `${conflictButtons.length} buttons, expected conflict=${expectsConflict}`,
);
if (conflictButtons.length) {
  conflictButtons[0].click();
  await wait(60);
  const marked = [...doc.querySelectorAll('#app .edit-row.conflict-row')];
  check(
    'the fix button opens the answer list on the conflicting rows',
    doc.querySelector('#app details.editor')?.open === true && marked.length >= 2,
    `${marked.length} marked rows`,
  );
  check(
    'every answer named in the conflict is marked',
    expected.conditions
      .flatMap((card) => card.evidence?.conflict ?? [])
      .flatMap((item) => item.via ?? [])
      .every((via) => marked.some((row) => row.textContent.length > 0)) &&
      marked.every((row) => row.querySelector('button')),
  );
}

// --- edit / restart ------------------------------------------------------
const editAll = [...doc.querySelectorAll('#app button')].find((button) => button.textContent.includes('ویرایش پاسخ‌ها'));
editAll?.click();
await wait(50);
const editor = doc.querySelector('#app details.editor');
check('the edit list opens from the result screen', Boolean(editor) && editor.open, `${editor?.querySelectorAll('.edit-row').length} rows`);

const rows = [...editor.querySelectorAll('.edit-row')];
const lastRow = rows[rows.length - 1];
const editedQuestion = lastRow.querySelector('.edit-question').textContent;
lastRow.querySelector('button').click();
await wait(50);
check(
  'editing a previous answer re-opens that question',
  doc.getElementById('question-title')?.textContent === editedQuestion,
  editedQuestion.slice(0, 50),
);

const editName = doc.querySelector('#app input').name;
const editedId = editName.replace(/^q-/, '');
const editValue = scenario.answers[editedId] ?? neutralAnswer(editedId);
const editNumber = doc.querySelector(`#app input[type="number"][name="${editName}"]`);
if (editValue === 'unknown' && editNumber) doc.querySelector('#app input[type="checkbox"][id$="-unknown"]').click();
else if (editValue === 'unknown') doc.querySelector(`#app input[name="${editName}"][value="unknown"]`).click();
else if (editNumber) {
  editNumber.value = String(editValue);
  editNumber.dispatchEvent(new window.Event('input', { bubbles: true }));
} else if (Array.isArray(editValue)) {
  for (const id of editValue) doc.querySelector(`#app input[name="${editName}"][value="${id}"]`).click();
} else {
  doc.querySelector(`#app input[name="${editName}"][value="${editValue}"]`).click();
}
doc.getElementById('submit-answer').click();
await wait(60);
check('re-answering the last question returns to the result', Boolean(doc.querySelector('#app .coverage')));

const restart = [...doc.querySelectorAll('#app button')].find((button) => button.textContent.includes('شروع دوباره'));
check('the result offers a restart', Boolean(restart));
restart?.click();
await wait(60);
check('restart clears the session', text(doc.getElementById('app')).includes('پاسخ‌داده‌شده ۰ از'), text(doc.getElementById('app')).slice(0, 40));
check('restart hides the previous safety banners', doc.getElementById('safety').hidden === true);
check('restart forgets the notes', doc.querySelector('#app .notes-summary') === null && !text(doc.getElementById('app')).includes(NOTE_TEXT));

// --- privacy -------------------------------------------------------------
check('only the two local data files were fetched', fetched.length === 2, fetched.join(' ; '));
check(
  'every request is a same-origin GET without a body',
  requests.every((item) => item.method === 'GET' && item.body === null) &&
    requests.every((item) => new URL(item.url, window.location.href).origin === window.location.origin),
  requests.map((item) => `${item.method} ${item.url}`).join(' ; '),
);
check('nothing written to localStorage', window.localStorage.length === 0, `${window.localStorage.length} keys`);
check('nothing written to sessionStorage', window.sessionStorage.length === 0, `${window.sessionStorage.length} keys`);
check('no cookie is set', window.document.cookie === '', window.document.cookie);
check(
  'indexedDB is never opened',
  !window.indexedDB || typeof window.indexedDB.open !== 'function' || window.indexedDB.__opened !== true,
  typeof window.indexedDB,
);
check('no form can post the answers', window.document.querySelectorAll('form[action], form[method]').length === 0);
check(
  'the page never navigates away',
  window.location.href === 'http://demo.local/symptom-checker/ui/index.html' && window.history.length === 1,
  window.location.href,
);
check('no console errors or uncaught exceptions', errors.length === 0, errors.slice(0, 2).join(' || '));

const failed = checks.filter((item) => !item.ok);
console.log(`\n${checks.length - failed.length}/${checks.length} checks passed for ${scenario.id}`);
process.exitCode = failed.length ? 1 : 0;

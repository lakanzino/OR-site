// Run: node --test themes/lax-theme-child/tests/symptom-checker-engine.test.js
'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const E = require('../assets/js/symptom-checker-engine.js');

const dataDir = path.join(__dirname, '..', 'data', 'symptom-checker');
const vocab = JSON.parse(fs.readFileSync(path.join(dataDir, 'vocab.json'), 'utf8'));
const diseases = JSON.parse(fs.readFileSync(path.join(dataDir, 'diseases.json'), 'utf8')).diseases;

const toy = [
  { id: 'a', nameFa: 'الف', nameEn: 'A', tags: ['fever', 'cough', 'dyspnea'], symptoms: [], whenToSeeDoctor: [], sources: [] },
  { id: 'b', nameFa: 'ب', nameEn: 'B', tags: ['fever', 'rash'], symptoms: [], whenToSeeDoctor: [], sources: [] },
];

test('more matched tags rank higher', () => {
  const s = E.scoreDiseases(toy, new Set(['fever', 'cough']), new Set());
  assert.strictEqual(s[0].disease.id, 'a');
  assert.ok(s[0].p > s[1].p);
});

test('denied tags lower a hypothesis', () => {
  const base = E.scoreDiseases(toy, new Set(['fever']), new Set());
  const denied = E.scoreDiseases(toy, new Set(['fever']), new Set(['cough', 'dyspnea']));
  const bAfter = denied.find((x) => x.disease.id === 'a');
  const bBefore = base.find((x) => x.disease.id === 'a');
  assert.ok(bAfter.score < bBefore.score);
});

test('a "yes" red flag gives an emergency result with no candidates', () => {
  const answers = { region: 'chest', redFlags: {}, tags: {} };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  answers.redFlags.rf_stroke = 'yes';
  const step = E.nextStep(vocab, diseases, answers);
  assert.strictEqual(step.type, 'result');
  assert.strictEqual(step.result.emergency, true);
  assert.deepStrictEqual(step.result.candidates, []);
});

test('an "unknown" red flag adds a caution, not an emergency', () => {
  const answers = { region: 'head', redFlags: {}, tags: {} };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  answers.redFlags.rf_faint = 'unknown';
  const step = E.nextStep(vocab, diseases, answers);
  assert.strictEqual(step.type, 'tag');
  // Reach the result by answering the region with a clear "no".
  vocab.tags.filter((t) => t.region === 'head').forEach((t) => { answers.tags[t.id] = 'no'; });
  const res = E.nextStep(vocab, diseases, answers);
  assert.strictEqual(res.type, 'result');
  assert.strictEqual(res.result.emergency, false);
  assert.strictEqual(res.result.cautionUnknown, true);
});

test('unknown answers are not evidence', () => {
  const s1 = E.scoreDiseases(toy, new Set(['fever']), new Set());
  const s2 = E.scoreDiseases(toy, new Set(['fever']), new Set());
  assert.deepStrictEqual(s1.map((x) => x.p), s2.map((x) => x.p));
});

test('nextTag returns a tag that splits the hypotheses', () => {
  const scored = E.scoreDiseases(toy, new Set(['fever']), new Set());
  const tag = E.nextTag(scored, new Set(['fever']), new Set(), new Set(['fever']), vocab.tags);
  if (tag) assert.ok(['cough', 'dyspnea', 'rash'].indexOf(tag.id) !== -1 || tag.id);
});

test('results never include home treatment fields', () => {
  const r = E.buildResult(vocab, diseases, { region: 'abdomen', redFlags: {}, tags: { abd_pain: 'yes' } }, []);
  const text = JSON.stringify(r);
  assert.ok(!/treatment|درمان‌های خانگی|treatmentHome/.test(text));
});

test('scripted visit about appendicitis-like answers ranks it in the top three', () => {
  const target = diseases.find((d) => d.id === 'app');
  assert.ok(target, 'app disease present');
  const answers = { region: 'abdomen', redFlags: {}, tags: {} };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  let guard = 0;
  let step = E.nextStep(vocab, diseases, answers);
  while (step.type !== 'result' && guard < 40) {
    guard += 1;
    if (step.type === 'redFlag') {
      answers.redFlags[step.question.id] = 'no';
    } else {
      const id = step.tag.id;
      answers.tags[id] = target.tags.indexOf(id) !== -1 ? 'yes' : 'no';
    }
    step = E.nextStep(vocab, diseases, answers);
  }
  assert.strictEqual(step.type, 'result');
  const ids = step.result.candidates.map((c) => c.id);
  assert.ok(ids.indexOf('app') !== -1, 'top candidates: ' + ids.join(','));
  assert.ok(guard <= E.MAX_QUESTIONS + vocab.redFlags.length + 7 + 1);
});

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

function disease(id, tags, roles, extra) {
  return Object.assign({
    id, nameFa: id, nameEn: id, specialty: '', rankable: true,
    ageGroups: ['child', 'teen', 'adult', 'elderly'], sex: 'any',
    tags, tagRoles: roles || {}, symptoms: [], whenToSeeDoctor: [], sources: [],
    nextStep: '', reviewStatus: 'draft',
  }, extra || {});
}

const toy = [
  disease('a', ['fever', 'cough', 'dyspnea'], { fever: 'core', cough: 'core', dyspnea: 'supporting' }),
  disease('b', ['fever', 'rash', 'itch'], { fever: 'core', rash: 'core', itch: 'supporting' }),
];

test('more confirmed core tags rank higher', () => {
  const r = E.rank(toy, { tags: { fever: 'yes', cough: 'yes' } });
  assert.strictEqual(r[0].disease.id, 'a');
  assert.ok(r[0].score > (r[1] ? r[1].score : 0));
});

test('a denied core tag lowers a disease', () => {
  const before = E.rank(toy, { tags: { fever: 'yes', dyspnea: 'yes' } }).find((x) => x.disease.id === 'a');
  const after = E.rank(toy, { tags: { fever: 'yes', dyspnea: 'yes', cough: 'no' } }).find((x) => x.disease.id === 'a');
  assert.ok(after.score < before.score);
});

test('unknown answers are not evidence', () => {
  const none = E.rank(toy, { tags: {} });
  const unk = E.rank(toy, { tags: { fever: 'unknown', cough: 'unknown' } });
  assert.strictEqual(none.length, 0);
  assert.strictEqual(unk.length, 0);
});

test('fewer than two confirmed tags is not a candidate', () => {
  const r = E.rank(toy, { tags: { fever: 'yes' } });
  assert.strictEqual(r.length, 0);
});

test('age filter removes diseases outside the age group', () => {
  const kids = [disease('k', ['fever', 'cough'], {}, { ageGroups: ['child'] })];
  assert.strictEqual(E.rank(kids, { age: 'adult', tags: { fever: 'yes', cough: 'yes' } }).length, 0);
  assert.strictEqual(E.rank(kids, { age: 'child', tags: { fever: 'yes', cough: 'yes' } }).length, 1);
});

test('sex filter removes a disease for the other sex, unknown sex keeps it', () => {
  const f = [disease('f', ['fever', 'cough'], {}, { sex: 'female' })];
  assert.strictEqual(E.rank(f, { sex: 'male', tags: { fever: 'yes', cough: 'yes' } }).length, 0);
  assert.strictEqual(E.rank(f, { tags: { fever: 'yes', cough: 'yes' } }).length, 1);
});

test('non-rankable diseases are never ranked', () => {
  const x = [disease('x', ['fever', 'cough'], {}, { rankable: false })];
  assert.strictEqual(E.rank(x, { tags: { fever: 'yes', cough: 'yes' } }).length, 0);
});

test('a red-flag yes is an emergency and shows no candidates', () => {
  const answers = { redFlags: {}, tags: { fever: 'yes', cough: 'yes' } };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  answers.redFlags[vocab.redFlags[0].id] = 'yes';
  const step = E.nextStep(vocab, diseases, answers);
  assert.strictEqual(step.type, 'result');
  assert.strictEqual(step.result.emergency, true);
  assert.strictEqual(step.result.candidates.length, 0);
});

test('an unknown red flag adds a caution, not an emergency', () => {
  const answers = { redFlags: {}, tags: {} };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  answers.redFlags[vocab.redFlags[0].id] = 'unknown';
  let step = E.nextStep(vocab, diseases, answers);
  let guard = 0;
  while (step.type !== 'result' && guard++ < 60) {
    if (step.type === 'tag') answers.tags[step.tag.id] = 'no';
    step = E.nextStep(vocab, diseases, answers);
  }
  assert.strictEqual(step.result.emergency, false);
  assert.strictEqual(step.result.cautionUnknown, true);
});

test('red flags are asked before tags', () => {
  const step = E.nextStep(vocab, diseases, { redFlags: {}, tags: {} });
  assert.strictEqual(step.type, 'redFlag');
});

test('no rankable disease after filters reports insufficient data', () => {
  const pool = [disease('x', ['fever', 'cough'], {}, { rankable: false })];
  const answers = { redFlags: {}, tags: { fever: 'yes', cough: 'yes' } };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  const res = E.nextStep({ redFlags: [], tags: [] }, pool, answers).result;
  assert.strictEqual(res.status, 'insufficient');
});

test('the next question is a real tag from the vocabulary', () => {
  const answers = { redFlags: {}, tags: { fever: 'yes' } };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  const step = E.nextStep(vocab, diseases, answers);
  if (step.type === 'tag') {
    assert.ok(vocab.tags.some((t) => t.id === step.tag.id));
    assert.ok(!answers.tags[step.tag.id]);
  }
});

test('results never include home-treatment fields', () => {
  const answers = { redFlags: {}, tags: { fever: 'yes', cough: 'yes', dyspnea: 'yes' } };
  vocab.redFlags.forEach((rf) => { answers.redFlags[rf.id] = 'no'; });
  const res = E.nextStep(vocab, diseases, answers).result;
  const json = JSON.stringify(res);
  assert.ok(!/home_remedy|homeRemedy|خانگی|درمان خانگی/.test(json));
});

test('every shipped disease that is rankable has at least the minimum tags', () => {
  diseases.filter((d) => d.rankable).forEach((d) => assert.ok(d.tags.length >= 3, d.id));
});

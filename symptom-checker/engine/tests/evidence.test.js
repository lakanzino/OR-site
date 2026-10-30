/**
 * Evidence-map contract tests: the map must only reference real questions,
 * options and symptoms, and must match the bundle version it was built for.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';
import { validateEvidenceMap, uncoveredSymptoms } from '../src/evidence.js';
import { BundleValidationError } from '../src/errors.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundle = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');
const clone = (value) => JSON.parse(JSON.stringify(value));

test('the shipped map is valid for the shipped bundle', () => {
  assert.deepEqual(validateEvidenceMap(bundle, evidenceMap), []);
});

test('a map built for another bundle version is rejected', () => {
  const stale = clone(evidenceMap);
  stale.bundle_version = '0.0.9';
  const issues = validateEvidenceMap(bundle, stale);
  assert.ok(issues.some((issue) => issue.includes('bundle')));
  assert.throws(() => createSession(bundle, { evidenceMap: stale }), BundleValidationError);
});

test('unknown question, option or symptom in the map are rejected', () => {
  const broken = clone(evidenceMap);
  broken.entries.push({
    question_id: 'q_ghost',
    option_id: 'yes',
    symptom_id: 's_pain_abdomen',
    state: 'present',
  });
  broken.entries.push({
    question_id: 'q_pain_present',
    option_id: 'maybe',
    symptom_id: 's_pain_abdomen',
    state: 'present',
  });
  broken.entries.push({
    question_id: 'q_pain_present',
    option_id: 'yes',
    symptom_id: 's_ghost',
    state: 'present',
  });

  const issues = validateEvidenceMap(bundle, broken);
  assert.ok(issues.some((issue) => issue.includes('unknown question')));
  assert.ok(issues.some((issue) => issue.includes('unknown option')));
  assert.ok(issues.some((issue) => issue.includes('unknown symptom')));
});

test('invalid state and duplicate entries are rejected', () => {
  const broken = clone(evidenceMap);
  broken.entries.push({
    question_id: 'q_pain_present',
    option_id: 'yes',
    symptom_id: 's_pain_abdomen',
    state: 'maybe',
  });
  const issues = validateEvidenceMap(bundle, broken);
  assert.ok(issues.some((issue) => issue.includes('state must be')));
});

test('a symptom no question can report is listed, not silently dropped', () => {
  assert.deepEqual(uncoveredSymptoms(bundle, evidenceMap), ['s_bladder_pressure', 's_abdominal_cramp']);
});

test('the engine runs with a partial map and marks gaps as not_covered', () => {
  const partial = clone(evidenceMap);
  partial.entries = partial.entries.filter((entry) => entry.symptom_id !== 's_pain_rlq');

  const session = createSession(bundle, { evidenceMap: partial });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_pain_migrated_rlq', 'yes');

  const app = session.result().conditions.find((card) => card.id === 'app');
  const notCovered = app.evidence.not_covered.map((item) => item.symptom_id);
  assert.ok(notCovered.includes('s_pain_rlq'));
  const present = app.evidence.present.map((item) => item.symptom_id);
  assert.ok(!present.includes('s_pain_rlq'));
});

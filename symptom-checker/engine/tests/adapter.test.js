/**
 * Tests for the schema-2.0.0 adapter.
 *
 * Phase 1 of the migration adds a second input shape (disease × symptom matrix)
 * without touching behaviour. These tests pin the mapping rules and prove the
 * embedded evidence is actually read — not silently ignored in favour of the old
 * external map.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';
import {
  bundleToEngineShape,
  engineSymptomId,
  evidenceMapFromBundle,
  isV2Bundle,
} from '../src/adapter.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundleV1 = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const bundleV2 = read('docs/symptom-checker/phase-1/knowledge-bundle.v2.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');
const scenarios = read('docs/symptom-checker/phase-1/scenarios.v1.json').scenarios;

const clone = (value) => JSON.parse(JSON.stringify(value));

/** Answer a scenario in ask_order, exactly like the engine's own test. */
function play(session, scenario, bundle) {
  const order = new Map(bundle.questions.map((question) => [question.id, question.ask_order]));
  for (const [questionId, value] of Object.entries(scenario.answers).sort(
    (a, b) => order.get(a[0]) - order.get(b[0]),
  )) {
    session.answer(questionId, value);
  }
  return session;
}

test('a 2.x bundle is recognised and a 1.x bundle is not', () => {
  assert.equal(isV2Bundle(bundleV2), true);
  assert.equal(isV2Bundle(bundleV1), false);
  assert.equal(isV2Bundle(null), false);
  assert.equal(isV2Bundle({}), false);
});

test('the v2 bundle keeps the legacy symptom ids for the engine', () => {
  const engine = bundleToEngineShape(bundleV2);
  const ids = engine.symptoms.map((symptom) => symptom.id);
  assert.equal(ids.length, bundleV1.symptoms.length);
  assert.deepEqual(
    ids,
    bundleV1.symptoms.map((symptom) => symptom.id),
    'v2 symptoms must map back to the v1 ids, in order',
  );
  assert.equal(engineSymptomId({ id: 'abd.new_one' }), 'abd.new_one');
});

test('options carry no evidence once adapted — the map does', () => {
  const engine = bundleToEngineShape(bundleV2);
  for (const question of engine.questions) {
    for (const option of question.options ?? []) {
      assert.equal('evidence' in option, false, `${question.id}.${option.id}`);
    }
  }
});

test('the rebuilt evidence map equals evidence-map.v1.json', () => {
  const rebuilt = evidenceMapFromBundle(bundleV2);
  assert.equal(rebuilt.entries.length, evidenceMap.entries.length);
  assert.equal(rebuilt.bundle_id, evidenceMap.bundle_id);
  assert.equal(rebuilt.bundle_version, evidenceMap.bundle_version);

  const key = (entry) => `${entry.question_id}|${entry.option_id}|${entry.symptom_id}|${entry.state}`;
  assert.deepEqual(
    rebuilt.entries.map(key).sort(),
    evidenceMap.entries.map(key).sort(),
    'the same answer→symptom statements, whichever shape they are stored in',
  );
});

test('per symptom, the reporting order is preserved', () => {
  const group = (entries) => {
    const out = new Map();
    for (const entry of entries) {
      if (!out.has(entry.symptom_id)) out.set(entry.symptom_id, []);
      out.get(entry.symptom_id).push(`${entry.question_id}.${entry.option_id}:${entry.state}`);
    }
    return out;
  };
  const rebuilt = group(evidenceMapFromBundle(bundleV2).entries);
  const original = group(evidenceMap.entries);
  for (const symptomId of original.keys()) {
    assert.deepEqual(rebuilt.get(symptomId), original.get(symptomId), symptomId);
  }
});

test('every published scenario gives the same result from v2 as from v1', () => {
  for (const scenario of scenarios) {
    const fromV1 = play(createSession(bundleV1, { evidenceMap }), scenario, bundleV1).result();
    const fromV2 = play(createSession(bundleV2), scenario, bundleV2).result();
    assert.deepEqual(fromV2, fromV1, scenario.id);
  }
});

test('the session reports the schema the caller passed in', () => {
  assert.equal(createSession(bundleV2).schemaVersion, '2.0.0');
  assert.equal(createSession(bundleV1, { evidenceMap }).schemaVersion, '1.0.0');
});

test('an explicit evidence map still wins over the embedded one', () => {
  const empty = { ...evidenceMap, entries: [] };
  const session = createSession(bundleV2, { evidenceMap: empty });
  assert.equal(session.hasEvidenceMap, true);
  const scenario = scenarios[0];
  play(session, scenario, bundleV2);
  const result = session.result();
  for (const condition of result.conditions) {
    for (const row of condition.evidence.present) {
      assert.equal(row.symptom_id.startsWith('s_'), true);
    }
  }
});

test('editing an option evidence changes the derived evidence', () => {
  const edited = clone(bundleV2);
  const question = edited.questions.find((item) => item.id === 'q_associated_symptoms');
  const fever = question.options.find((option) => option.id === 'fever');
  fever.evidence[0].state = 'absent';

  const scenario = scenarios.find((item) => item.id === 'sc05_pyelonephritis_pattern');
  const before = play(createSession(bundleV2), scenario, bundleV2).result();
  const after = play(createSession(edited), scenario, edited).result();

  const presentBefore = before.conditions.flatMap((card) => card.evidence.present.map((row) => row.symptom_id));
  const presentAfter = after.conditions.flatMap((card) => card.evidence.present.map((row) => row.symptom_id));
  assert.ok(presentBefore.includes('s_fever'));
  assert.ok(!presentAfter.includes('s_fever'), 'the embedded evidence must be the one that is read');
});

test('level is carried but not consumed yet', () => {
  // Phase 1 is a format change only: changing a profile level must not alter a
  // single result, or the equivalence claim would be false.
  const edited = clone(bundleV2);
  for (const condition of edited.conditions) {
    for (const row of condition.profile) row.level = 'not_expected';
  }
  const scenario = scenarios[0];
  const before = play(createSession(bundleV2), scenario, bundleV2).result();
  const after = play(createSession(edited), scenario, edited).result();
  assert.deepEqual(after, before);
});

test('an unknown schema version is still refused, naming the version', () => {
  const future = { ...clone(bundleV2), schema_version: '3.0.0' };
  assert.throws(() => createSession(future), /3\.0\.0/);
});

test('a v2 bundle without overrides is refused by the runtime validator', () => {
  const broken = clone(bundleV2);
  delete broken.overrides;
  let error = null;
  try {
    createSession(broken);
  } catch (caught) {
    error = caught;
  }
  assert.ok(error, 'a bundle that lost its overrides must not open a session');
  assert.equal(error.code, 'BUNDLE_INVALID');
  assert.ok(
    error.issues.some((issue) => /paths|override|no path can open/i.test(issue)),
    `expected a missing-paths issue, got: ${JSON.stringify(error.issues)}`,
  );
});

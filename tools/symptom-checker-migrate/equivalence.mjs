#!/usr/bin/env node
/**
 * Phase-1 gate: a schema-2.0.0 bundle must reproduce the frozen v1 output
 * byte for byte.
 *
 * The engine now reads v2 through `symptom-checker/engine/src/adapter.js`. This
 * script takes the recorded answers of the golden snapshots — 25 published
 * scenarios and 5000 seeded random paths — plays them through the v2 bundle and
 * compares every `result()` with what `tests/golden/v1/` froze in phase 0.
 *
 * It also checks the one thing a result comparison cannot see: that the evidence
 * rebuilt from the option-level `evidence[]` is the same evidence, in the same
 * order per symptom, as `evidence-map.v1.json`.
 *
 *   node tools/symptom-checker-migrate/equivalence.mjs
 *   node tools/symptom-checker-migrate/equivalence.mjs --quick   # 100 random paths
 *   node tools/symptom-checker-migrate/equivalence.mjs --json    # machine-readable
 *
 * Exit code 1 on any difference: the runbook's rule is stop and report, never
 * edit the golden expectations.
 */
import { readFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gunzipSync } from 'node:zlib';

import { createSession } from '../../symptom-checker/engine/src/engine.js';
import { evidenceMapFromBundle } from '../../symptom-checker/engine/src/adapter.js';
import { applyAnswers, canonical, diffPath, mulberry32, randomPath, valueAt } from '../symptom-checker-golden/replay.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const GOLDEN = resolve(ROOT, 'tests/golden/v1');

const V1_PATH = 'docs/symptom-checker/phase-1/knowledge-bundle.v1.json';
const V2_PATH = 'docs/symptom-checker/phase-1/knowledge-bundle.v2.json';
const MAP_PATH = 'symptom-checker/engine/data/evidence-map.v1.json';

const readJson = (relative) => JSON.parse(readFileSync(resolve(ROOT, relative), 'utf8'));
const argv = process.argv.slice(2);
const quick = argv.includes('--quick');
const asJson = argv.includes('--json');
// `--random` grows the gate beyond the frozen 5000: fresh seeded paths are
// played on v1 and on v2 at the same time and the two results are compared.
const differential = argv.includes('--random');
const valueOf = (flag, fallback) => {
  const index = argv.indexOf(flag);
  return index === -1 ? fallback : Number(argv[index + 1]);
};
const randomCount = valueOf('--count', quick ? 100 : 1000);
const randomSeed = valueOf('--seed', 1);
const failures = [];

function fail(label, where, expected, actual) {
  failures.push({
    label,
    where,
    expected: expected === undefined ? 'undefined' : JSON.stringify(expected).slice(0, 240),
    actual: actual === undefined ? 'undefined' : JSON.stringify(actual).slice(0, 240),
  });
}

function compare(label, expected, actual) {
  if (canonical(expected) === canonical(actual)) return;
  const where = diffPath(expected, actual) ?? '(unknown)';
  fail(label, where, valueAt(expected, where), valueAt(actual, where));
}

// --- 1. evidence parity -----------------------------------------------------
const map = readJson(MAP_PATH);
const bundleV2 = readJson(V2_PATH);
const rebuilt = evidenceMapFromBundle(bundleV2);

const group = (entries) => {
  const bySymptom = new Map();
  for (const entry of entries) {
    const key = entry.symptom_id;
    if (!bySymptom.has(key)) bySymptom.set(key, []);
    bySymptom.get(key).push(`${entry.question_id}.${entry.option_id}:${entry.state}`);
  }
  return bySymptom;
};

const original = group(map.entries ?? []);
const regenerated = group(rebuilt.entries ?? []);
for (const symptomId of new Set([...original.keys(), ...regenerated.keys()])) {
  compare(`evidence ${symptomId}`, original.get(symptomId) ?? [], regenerated.get(symptomId) ?? []);
}
if (map.entries.length !== rebuilt.entries.length) {
  fail('evidence count', 'entries', map.entries.length, rebuilt.entries.length);
}

// --- 2. the 25 published scenarios against the frozen snapshots -------------
const bundleV1 = readJson(V1_PATH);
const orderOf = new Map(bundleV1.questions.map((question) => [question.id, question.ask_order]));
const scenarioDir = resolve(GOLDEN, 'scenarios');
const manifestPath = resolve(GOLDEN, 'manifest.json');
if (!existsSync(manifestPath)) {
  console.error('no golden manifest: run tools/symptom-checker-golden/golden.mjs record first');
  process.exit(1);
}
const manifest = readJson('tests/golden/v1/manifest.json');
const scenarioIds = Object.keys(manifest.scenarios.files);

for (const id of scenarioIds) {
  const stored = readJson(`tests/golden/v1/scenarios/${id}.json`);
  const ordered = Object.fromEntries(
    Object.entries(stored.answers).sort((a, b) => orderOf.get(a[0]) - orderOf.get(b[0])),
  );
  const session = createSession(bundleV2);
  applyAnswers(session, ordered);
  compare(`scenario ${id} result`, stored.result, session.result());
  compare(`scenario ${id} answers`, stored.answers, session.answers());
}

// --- 3. the seeded random paths against the frozen snapshots ----------------
const lines = gunzipSync(readFileSync(resolve(GOLDEN, manifest.random.file)))
  .toString('utf8')
  .split('\n')
  .filter(Boolean);
const limit = quick ? Math.min(100, lines.length) : lines.length;
for (let index = 0; index < limit; index += 1) {
  const stored = JSON.parse(lines[index]);
  const session = createSession(bundleV2);
  try {
    applyAnswers(session, stored.answers);
  } catch (error) {
    fail(`random path ${index}`, 'replay', 'answers accepted', String(error?.message ?? error));
    continue;
  }
  compare(`random path ${index}`, stored.result, session.result());
}

// --- 4. optional differential run on fresh random paths ---------------------
if (differential) {
  const mapV1 = readJson(MAP_PATH);
  const randomV1 = mulberry32(randomSeed);
  const randomV2 = mulberry32(randomSeed);
  for (let index = 0; index < randomCount; index += 1) {
    const fromV1 = randomPath(bundleV1, mapV1, randomV1);
    const fromV2 = randomPath(bundleV2, null, randomV2);
    compare(`random seed ${randomSeed} path ${index} result`, fromV1.result, fromV2.result);
    compare(`random seed ${randomSeed} path ${index} answers`, fromV1.answers, fromV2.answers);
  }
}

// --- report -----------------------------------------------------------------
const summary = {
  scenarios: scenarioIds.length,
  random_paths: limit,
  differential: differential ? { count: randomCount, seed: randomSeed } : null,
  evidence_entries: rebuilt.entries.length,
  failures,
};

if (asJson) {
  console.log(JSON.stringify(summary, null, 2));
} else {
  for (const item of failures.slice(0, 12)) {
    console.error(`  ${item.label} at ${item.where}`);
    console.error(`    golden v1: ${item.expected}`);
    console.error(`    v2 bundle: ${item.actual}`);
  }
  if (failures.length > 12) console.error(`  … and ${failures.length - 12} more`);
  console.log(
    failures.length
      ? `MISMATCH — ${failures.length} difference(s) between the v2 bundle and tests/golden/v1`
      : `OK schema 2.0.0 reproduces tests/golden/v1 exactly: ${scenarioIds.length} scenarios + ${limit} random paths, ${rebuilt.entries.length} evidence rows` +
        (differential ? ` — plus ${randomCount} fresh paths (seed ${randomSeed}) identical on v1 and v2` : ''),
  );
}

process.exitCode = failures.length ? 1 : 0;

#!/usr/bin/env node
/**
 * Golden snapshots for the symptom-checker engine (phase 0 of the migration).
 *
 * The migration to the v2 matrix must not change a single answer the engine
 * gives. The way to prove that is to freeze today's output first and compare
 * against it after every step:
 *
 *   tests/golden/v1/scenarios/<id>.json   full `result()` for the 25 published
 *                                         scenarios (12 phase-1 + 13 adversarial)
 *   tests/golden/v1/random/paths-*.jsonl.gz
 *                                         5000 seeded random answer paths, one
 *                                         JSON line per path: `{i, answers, result}`
 *   tests/golden/v1/manifest.json         versions, seed, counts and the sha256
 *                                         of every frozen file plus the bundle
 *                                         and the evidence map
 *
 * The recorder and the verifier share the same code path, so "record" and
 * "verify" can never disagree about how a scenario is answered.
 *
 *   node tools/symptom-checker-golden/golden.mjs record
 *   node tools/symptom-checker-golden/golden.mjs verify
 *   node tools/symptom-checker-golden/golden.mjs verify --quick       # 100 paths
 *   node tools/symptom-checker-golden/golden.mjs dump random:412
 *   node tools/symptom-checker-golden/golden.mjs dump sc01
 *
 * Nothing here is a clinical test: it only proves that the engine still returns
 * the same object it returned before a refactor.
 */
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync, gunzipSync } from 'node:zlib';

import {
  canonical,
  describeDifference,
  diffPath,
  mulberry32,
  randomPath,
  runScenario,
  valueAt,
} from './replay.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const GOLDEN = resolve(ROOT, 'tests/golden/v1');

const BUNDLE_PATH = 'docs/symptom-checker/phase-1/knowledge-bundle.v1.json';
const MAP_PATH = 'symptom-checker/engine/data/evidence-map.v1.json';
const SCENARIO_FILES = [
  'docs/symptom-checker/phase-1/scenarios.v1.json',
  'docs/symptom-checker/phase-5/scenarios-adversarial.v1.json',
];

const DEFAULT_PATHS = 5000;
const DEFAULT_SEED = 5000;

const readJson = (relative) => JSON.parse(readFileSync(resolve(ROOT, relative), 'utf8'));
const sha256 = (text) => createHash('sha256').update(text, 'utf8').digest('hex');

// --- record ----------------------------------------------------------------
function scenarioSnapshots(bundle, evidenceMap) {
  const snapshots = [];
  for (const file of SCENARIO_FILES) {
    const data = readJson(file);
    for (const scenario of data.scenarios) {
      const { answers, result } = runScenario(bundle, evidenceMap, scenario);
      snapshots.push({ id: scenario.id, source: file, title_fa: scenario.title_fa, answers, result });
    }
  }
  return snapshots;
}

function randomSnapshots(bundle, evidenceMap, count, seed) {
  const random = mulberry32(seed);
  const paths = [];
  for (let index = 0; index < count; index += 1) {
    const { answers, result } = randomPath(bundle, evidenceMap, random);
    paths.push({ i: index, answers, result });
  }
  return paths;
}

function record(argv) {
  const count = Number(optionValue(argv, '--paths') ?? DEFAULT_PATHS);
  const seed = Number(optionValue(argv, '--seed') ?? DEFAULT_SEED);
  const bundle = readJson(BUNDLE_PATH);
  const evidenceMap = readJson(MAP_PATH);

  rmSync(GOLDEN, { recursive: true, force: true });
  mkdirSync(resolve(GOLDEN, 'scenarios'), { recursive: true });
  mkdirSync(resolve(GOLDEN, 'random'), { recursive: true });

  const scenarios = scenarioSnapshots(bundle, evidenceMap);
  const scenarioHashes = {};
  for (const snapshot of scenarios) {
    const file = `scenarios/${snapshot.id}.json`;
    const body = `${JSON.stringify(snapshot, null, 2)}\n`;
    writeFileSync(resolve(GOLDEN, file), body);
    scenarioHashes[snapshot.id] = sha256(canonical(snapshot));
  }

  const paths = randomSnapshots(bundle, evidenceMap, count, seed);
  const jsonl = `${paths.map((path) => JSON.stringify(path)).join('\n')}\n`;
  const packed = gzipSync(Buffer.from(jsonl, 'utf8'), { level: 9 });
  const randomFile = `random/paths-${count}-seed-${seed}.jsonl.gz`;
  writeFileSync(resolve(GOLDEN, randomFile), packed);

  const manifest = {
    golden_version: '1.0.0',
    created_from: {
      bundle_id: bundle.bundle_id,
      bundle_version: bundle.bundle_version,
      schema_version: bundle.schema_version,
      evidence_map_version: evidenceMap.bundle_version,
    },
    sources: {
      [BUNDLE_PATH]: sha256(readFileSync(resolve(ROOT, BUNDLE_PATH), 'utf8')),
      [MAP_PATH]: sha256(readFileSync(resolve(ROOT, MAP_PATH), 'utf8')),
    },
    scenarios: { count: scenarios.length, files: scenarioHashes },
    random: {
      count,
      seed,
      file: randomFile,
      jsonl_sha256: sha256(jsonl),
      result_hashes: paths.map((path) => sha256(canonical(path.result)).slice(0, 16)),
    },
  };
  writeFileSync(resolve(GOLDEN, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);

  console.log(`recorded ${scenarios.length} scenarios and ${count} random paths`);
  console.log(`  ${randomFile}: ${packed.length} bytes (jsonl ${jsonl.length})`);
  console.log(`  jsonl sha256: ${manifest.random.jsonl_sha256}`);
  console.log(`  bundle: ${bundle.bundle_id} ${bundle.bundle_version} · schema ${bundle.schema_version}`);
}

// --- verify ---
function verify(argv) {
  const quick = argv.includes('--quick');
  if (!existsSync(resolve(GOLDEN, 'manifest.json'))) {
    console.error('no golden manifest: run `record` first');
    return 1;
  }
  const manifest = readJson('tests/golden/v1/manifest.json');
  const bundle = readJson(BUNDLE_PATH);
  const evidenceMap = readJson(MAP_PATH);
  const failures = [];

  for (const [file, expectedHash] of Object.entries(manifest.sources)) {
    const actual = sha256(readFileSync(resolve(ROOT, file), 'utf8'));
    if (actual !== expectedHash) failures.push({ label: `source ${file}`, where: 'sha256', expected: expectedHash, actual });
  }

  for (const [id, expectedHash] of Object.entries(manifest.scenarios.files)) {
    const stored = readJson(`tests/golden/v1/scenarios/${id}.json`);
    const fresh = runScenario(bundle, evidenceMap, stored);
    const snapshot = { ...stored, answers: fresh.answers, result: fresh.result };
    if (sha256(canonical(snapshot)) !== expectedHash) {
      failures.push(describeDifference(`scenario ${id}`, { ...stored }, snapshot));
    }
  }

  const count = quick ? Math.min(100, manifest.random.count) : manifest.random.count;
  const packed = readFileSync(resolve(GOLDEN, manifest.random.file));
  const lines = gunzipSync(packed).toString('utf8').split('\n').filter(Boolean);
  if (lines.length !== manifest.random.count) {
    failures.push({ label: 'random file', where: 'line count', expected: String(manifest.random.count), actual: String(lines.length) });
  }
  // Regenerate from the same seed: the PRNG must produce the same paths in the
  // same order, otherwise the comparison is meaningless.
  const random = mulberry32(manifest.random.seed);
  for (let index = 0; index < count; index += 1) {
    const fresh = randomPath(bundle, evidenceMap, random);
    const stored = JSON.parse(lines[index]);
    if (canonical(fresh.result) !== canonical(stored.result)) {
      failures.push(describeDifference(`random path ${index}`, stored.result, fresh.result));
    }
  }

  if (failures.length) {
    console.error(`MISMATCH — ${failures.length} difference(s) against tests/golden/v1`);
    for (const item of failures.slice(0, 10)) {
      console.error(`  ${item.label} at ${item.where}`);
      console.error(`    expected: ${item.expected}`);
      console.error(`    actual:   ${item.actual}`);
    }
    if (failures.length > 10) console.error(`  … and ${failures.length - 10} more`);
    return 1;
  }

  console.log(
    `OK ${Object.keys(manifest.scenarios.files).length} scenarios + ${count} random paths ` +
      `(seed ${manifest.random.seed}) match tests/golden/v1`,
  );
  return 0;
}

// --- dump ------------------------------------------------------------------
function dump(target) {
  const bundle = readJson(BUNDLE_PATH);
  const evidenceMap = readJson(MAP_PATH);
  if (target.startsWith('random:')) {
    const index = Number(target.split(':')[1]);
    const manifest = readJson('tests/golden/v1/manifest.json');
    const lines = gunzipSync(readFileSync(resolve(GOLDEN, manifest.random.file))).toString('utf8').split('\n').filter(Boolean);
    const stored = JSON.parse(lines[index]);
    const random = mulberry32(manifest.random.seed);
    let fresh = null;
    for (let step = 0; step <= index; step += 1) fresh = randomPath(bundle, evidenceMap, random);
    console.log(JSON.stringify({ stored: stored.result, fresh: fresh.result }, null, 2));
    return canonical(fresh.result) === canonical(stored.result) ? 0 : 1;
  }
  const stored = readJson(`tests/golden/v1/scenarios/${target}.json`);
  const fresh = runScenario(bundle, evidenceMap, stored);
  console.log(JSON.stringify({ stored: stored.result, fresh: fresh.result }, null, 2));
  return canonical(fresh.result) === canonical(stored.result) ? 0 : 1;
}

function optionValue(argv, flag) {
  const index = argv.indexOf(flag);
  return index === -1 ? undefined : argv[index + 1];
}

const [command, argument] = process.argv.slice(2);
let code = 1;
if (command === 'record') {
  record(process.argv.slice(2));
  code = 0;
} else if (command === 'verify') {
  code = verify(process.argv.slice(2));
} else if (command === 'dump' && argument) {
  code = dump(argument);
} else {
  console.error('usage: golden.mjs record|verify|dump <scenario-id|random:N> [--paths N] [--seed N] [--quick]');
}
process.exitCode = code;

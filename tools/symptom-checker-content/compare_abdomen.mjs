#!/usr/bin/env node
/**
 * آیا محتوای شکم/پهلو در بستهٔ ۳ همان جواب بستهٔ ۱ را می‌دهد؟
 *
 * بستهٔ ۳ پرسش‌های شکم را از فاز ۱ برداشته و فقط یک دروازهٔ ناحیه جلوی آن‌ها
 * گذاشته است. این ابزار همان ۲۵ سناریوی منتشرشده و ۱۳ سناریوی دشوار را یک‌بار
 * روی بستهٔ قدیم و یک‌بار روی بستهٔ جدید (با پاسخ دادن به سه پرسش دروازه) اجرا
 * می‌کند و موضوع‌ها، هشدارها و وضعیت پوشش را مقایسه می‌کند.
 *
 * چیزی شبیه «آزمون بالینی» نیست: فقط ثابت می‌کند جابه‌جا کردن پرسش‌ها جواب را
 * عوض نکرده است.
 *
 *   node tools/symptom-checker-content/compare_abdomen.mjs
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../../symptom-checker/engine/src/engine.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');

const OLD_BUNDLE = 'docs/symptom-checker/phase-1/knowledge-bundle.v1.json';
const OLD_MAP = 'symptom-checker/engine/data/evidence-map.v1.json';
const NEW_BUNDLE = 'docs/symptom-checker/phase-6/knowledge-bundle.v3.json';
const SCENARIOS = [
  'docs/symptom-checker/phase-1/scenarios.v1.json',
  'docs/symptom-checker/phase-5/scenarios-adversarial.v1.json',
];

const readJson = (relative) => JSON.parse(readFileSync(resolve(ROOT, relative), 'utf8'));
const ids = (result) => result.conditions.map((card) => card.id).sort().join(',');
const safety = (result) => result.safety.map((rule) => rule.id).sort().join(',');

/** Same walk as the golden recorder: answers in the bundle's ask order. */
function playInto(session, order, answers) {
  const entries = Object.entries(answers).sort((a, b) => order.get(a[0]) - order.get(b[0]));
  for (const [questionId, value] of entries) {
    if (!session.canAnswer(questionId)) {
      throw new Error(`answer for a question that is not visible: ${questionId}`);
    }
    session.answer(questionId, value);
  }
  return session;
}

const oldBundle = readJson(OLD_BUNDLE);
const oldMap = readJson(OLD_MAP);
const newBundle = readJson(NEW_BUNDLE);
const oldOrder = new Map((oldBundle.questions ?? []).map((question) => [question.id, question.ask_order]));

const scenarios = SCENARIOS.flatMap((path) => readJson(path).scenarios ?? []);
const problems = [];
let compared = 0;

for (const scenario of scenarios) {
  const before = playInto(
    createSession(oldBundle, { evidenceMap: oldMap }),
    oldOrder,
    scenario.answers,
  ).result();

  const after = createSession(newBundle);
  after.answer('q_sex', 'female');
  after.answer('q_region', 'torso');
  after.answer('q_area_torso', 'abdomen');
  playInto(after, oldOrder, scenario.answers);
  const now = after.result();

  compared += 1;
  if (ids(before) !== ids(now)) {
    problems.push(`${scenario.id}: conditions ${ids(before)} → ${ids(now)}`);
  }
  if (safety(before) !== safety(now)) {
    problems.push(`${scenario.id}: safety ${safety(before)} → ${safety(now)}`);
  }
  if (before.coverage.result !== now.coverage.result) {
    problems.push(`${scenario.id}: coverage ${before.coverage.result} → ${now.coverage.result}`);
  }
}

if (problems.length) {
  console.log('FAIL');
  for (const problem of problems) console.log(' -', problem);
  process.exit(1);
}

console.log(`OK ${compared} scenarios: the abdomen path gives the same topics, warnings and coverage as phase 1`);

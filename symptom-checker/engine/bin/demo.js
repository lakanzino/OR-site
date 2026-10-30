#!/usr/bin/env node
/**
 * Small CLI to run the phase-1 scenarios through the phase-2 engine and print
 * what a UI would be allowed to render.
 *
 *   node bin/demo.js              # list scenarios
 *   node bin/demo.js sc01_classic_appendicitis_pattern
 *   node bin/demo.js sc05 --json  # raw result object
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundle = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const scenarios = read('docs/symptom-checker/phase-1/scenarios.v1.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');

const args = process.argv.slice(2);
const json = args.includes('--json');
const requested = args.find((arg) => !arg.startsWith('--'));

if (!requested) {
  console.log('سناریوهای موجود:\n');
  for (const scenario of scenarios.scenarios) {
    console.log(`  ${scenario.id.padEnd(40)} ${scenario.title_fa}`);
  }
  console.log('\nمثال: node bin/demo.js sc01 --json');
  process.exit(0);
}

const scenario = scenarios.scenarios.find(
  (item) => item.id === requested || item.id.startsWith(requested),
);
if (!scenario) {
  console.error(`سناریو پیدا نشد: ${requested}`);
  process.exit(1);
}

const session = createSession(bundle, { evidenceMap });
const order = new Map(bundle.questions.map((question) => [question.id, question.ask_order]));
for (const [questionId, value] of Object.entries(scenario.answers).sort(
  (a, b) => order.get(a[0]) - order.get(b[0]),
)) {
  session.answer(questionId, value);
}

const result = session.result();
if (json) {
  console.log(JSON.stringify(result, null, 2));
  process.exit(0);
}

console.log(`\nسناریو: ${scenario.title_fa} (${scenario.id})`);
console.log(`وضعیت: ${result.state} | پوشش: ${result.coverage.result}`);
console.log(`پیام پوشش: ${result.coverage.message_fa}`);
console.log(`اجازهٔ نمایش کارت: ${result.cards_allowed ? 'بله' : 'خیر'}`);

if (result.safety.length) {
  console.log('\n— هشدارهای ایمنی (مستقل از کارت‌ها) —');
  for (const rule of result.safety) {
    console.log(`  [${rule.severity}] ${rule.id}: ${rule.message_fa}`);
  }
}

if (result.conditions.length) {
  console.log('\n— موضوع‌های مرتبط (بدون رتبه‌بندی و بدون درصد) —');
  for (const card of result.conditions) {
    console.log(`\n  • ${card.name_fa}  (${card.id})`);
    for (const reason of card.reasons) {
      console.log(`      دلیل: ${reason.note_fa}`);
      console.log(`      مسیر: ${reason.path_id} | منابع: ${reason.source_refs.join(', ')}`);
    }
    const groups = card.evidence ?? {};
    const summary = Object.entries(groups)
      .filter(([, items]) => items.length)
      .map(([status, items]) => `${status}=${items.length}`)
      .join(' ');
    console.log(`      شواهد: ${summary}`);
  }
} else {
  console.log('\n— هیچ کارت موضوعی نمایش داده نمی‌شود —');
}

console.log('\n' + (result.disclaimers.result_fa ?? '') + '\n');

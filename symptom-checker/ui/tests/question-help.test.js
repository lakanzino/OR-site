/**
 * Coverage tests for the Persian narrator copy.
 *
 * Every question and every option of the shipped bundle must have a line in
 * `question-help.js`; otherwise the robot card would fall back to a generic
 * sentence and the person loses the "which option is mine" guidance. Adding a
 * question or an option to the bundle therefore fails this test until the copy
 * is written — that is the point.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { QUESTION_HELP } from '../question-help.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../../..');
const bundle = JSON.parse(
  readFileSync(resolve(REPO, 'docs/symptom-checker/phase-1/knowledge-bundle.v1.json'), 'utf8'),
);

test('every question has an intro', () => {
  for (const question of bundle.questions) {
    const help = QUESTION_HELP[question.id];
    assert.ok(help, `missing help for ${question.id}`);
    assert.equal(typeof help.intro, 'string', `${question.id}: intro must be a string`);
    assert.ok(
      help.intro.trim().length >= 40,
      `${question.id}: intro is too short to explain anything`,
    );
  }
});

test('every option of every question has its own line', () => {
  for (const question of bundle.questions) {
    const lines = QUESTION_HELP[question.id]?.options ?? {};
    for (const option of question.options ?? []) {
      const line = lines[option.id];
      assert.equal(typeof line, 'string', `${question.id}.${option.id}: no help line`);
      assert.ok(line.trim().length >= 6, `${question.id}.${option.id}: help line is too short`);
    }
  }
});

test('the help has no line for an option that does not exist', () => {
  for (const [questionId, help] of Object.entries(QUESTION_HELP)) {
    const question = bundle.questions.find((item) => item.id === questionId);
    assert.ok(question, `help for unknown question ${questionId}`);
    const optionIds = new Set((question.options ?? []).map((option) => option.id));
    for (const optionId of Object.keys(help.options ?? {})) {
      assert.ok(optionIds.has(optionId), `${questionId}.${optionId} is not in the bundle`);
    }
  }
});

test('the "none of these" rule points at a real unknown option', () => {
  // Questions without an unknown option must word it with their own last resort.
  for (const question of bundle.questions) {
    const unknown = (question.options ?? []).find((option) => option.id === 'unknown');
    if (!unknown) continue;
    const line = QUESTION_HELP[question.id].options.unknown;
    assert.ok(
      line.includes(unknown.label_fa),
      `${question.id}: the unknown line must use the real label "${unknown.label_fa}"`,
    );
  }
});

test('the copy never promises a probability, a score or a diagnosis', () => {
  // «احتمال» alone is allowed (it can describe a possible pregnancy); a claim
  // about the chance of a disease is not.
  const banned = [/درصد/, /امتیاز/, /احتمال (ابتلا|بیماری|بالا|زیاد)/, /تشخیص قطعی/, /قطعاً/];
  const all = Object.entries(QUESTION_HELP)
    .flatMap(([id, help]) => [id, help.intro, ...Object.values(help.options ?? {})])
    .join(' ');
  for (const pattern of banned) {
    assert.ok(!pattern.test(all), `the narrator copy must not use ${pattern}`);
  }
});

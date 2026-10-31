/**
 * Phase-5 adversarial regression: the edge cases that matter for safety and
 * privacy, replayed through the real engine.
 *
 * The expectations come from `docs/symptom-checker/phase-5/scenarios-adversarial.v1.json`
 * and are cross-checked by the Python authoring gate (an independent
 * implementation of the same rules), so this file guards the engine against
 * drifting away from the published scenarios.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundle = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');
const adversarial = read('docs/symptom-checker/phase-5/scenarios-adversarial.v1.json');

const newSession = () => createSession(bundle, { evidenceMap });

/** Answer in ask_order so that visibility rules are satisfied. */
function runScenario(scenario) {
  const session = newSession();
  const order = new Map(bundle.questions.map((question) => [question.id, question.ask_order]));
  const entries = Object.entries(scenario.answers).sort((a, b) => order.get(a[0]) - order.get(b[0]));
  for (const [questionId, value] of entries) session.answer(questionId, value);
  return session;
}

for (const scenario of adversarial.scenarios) {
  test(`adversarial ${scenario.id}: ${scenario.title_fa}`, () => {
    const session = runScenario(scenario);
    const expected = scenario.expected;
    const result = session.result();

    const branches = session.branches().map((branch) => branch.id).sort();
    assert.deepEqual(branches, [...expected.branches_open].sort(), 'open branches');
    for (const forbidden of expected.branches_must_not_open) {
      assert.ok(!branches.includes(forbidden), `forbidden branch opened: ${forbidden}`);
    }

    const safety = session.safety().map((rule) => rule.id).sort();
    assert.deepEqual(safety, [...expected.safety_flags].sort(), 'safety flags');
    assert.equal(new Set(safety).size, safety.length, 'safety rules must not repeat');

    assert.equal(session.coverage().result, expected.coverage, 'coverage');
    assert.equal(result.cards_allowed, expected.result_cards_allowed, 'cards allowed');

    if (!expected.result_cards_allowed) {
      assert.deepEqual(result.conditions, [], 'no cards may be rendered');
    }

    const conflicts = result.conditions.flatMap((card) => card.evidence.conflict.map((item) => item.symptom_id)).sort();
    assert.deepEqual(conflicts, [...(expected.conflicts ?? [])].sort(), 'conflict evidence');
  });
}

test('an urgent warning never depends on cards or on coverage', () => {
  const session = runScenario(adversarial.scenarios.find((item) => item.id === 'sc13_severe_pain_without_any_branch'));
  const result = session.result();
  assert.equal(session.coverage().result, 'insufficient');
  assert.equal(result.cards_allowed, false);
  assert.deepEqual(result.conditions, []);
  assert.ok(
    session.safety().some((rule) => rule.id === 'sf_severe_pain' && rule.severity === 'urgent'),
    'the urgent rule must still be reported without any card',
  );
});

test('out_of_scope stops the questionnaire but keeps every warning', () => {
  const session = runScenario(adversarial.scenarios.find((item) => item.id === 'sc17_pregnancy_with_severe_worsening_pain'));
  assert.equal(session.state, 'stopped');
  assert.equal(session.nextQuestion(), null);
  assert.equal(session.coverage().result, 'out_of_scope');
  const ids = session.safety().map((rule) => rule.id);
  for (const id of ['sf_severe_pain', 'sf_pain_worsening', 'sf_pregnancy_out_of_scope']) {
    assert.ok(ids.includes(id), `missing warning ${id}`);
  }
  assert.equal(session.result().cards_allowed, false);
});

test('answering after a safe stop is refused with a typed error', () => {
  const session = runScenario(adversarial.scenarios.find((item) => item.id === 'sc17_pregnancy_with_severe_worsening_pain'));
  assert.throws(
    () => session.answer('q_urinary_symptoms', ['none']),
    (error) => error.code === 'SESSION_STOPPED' || error.code === 'QUESTION_NOT_VISIBLE',
  );
});

test('undo leaves the stopped state and allows new answers', () => {
  const session = runScenario(adversarial.scenarios.find((item) => item.id === 'sc17_pregnancy_with_severe_worsening_pain'));
  assert.equal(session.state, 'stopped');
  assert.ok(session.canUndo());
  session.undo();
  assert.equal(session.state, 'asking');
  assert.equal(session.coverage().result, 'insufficient');
  assert.equal(session.result().cards_allowed, false);
  assert.ok(session.nextQuestion(), 'a question is available again');
});

test('answers to questions that are no longer shown stop counting', () => {
  const session = newSession();
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_pain_migrated_rlq', 'yes');
  assert.ok(session.branches().some((branch) => branch.id === 'app'), 'the branch is open first');

  // The person corrects the first answer: without pain, the location answers are hidden.
  session.answer('q_pain_present', 'no');
  const branches = session.branches().map((branch) => branch.id);
  assert.ok(!branches.includes('app'), 'hidden answers must not keep the branch open');
  assert.equal(session.coverage().result, 'insufficient');
});

test('invalid input is rejected with a stable error code', () => {
  const cases = [
    { answers: [['q_pain_present', 'maybe']], code: 'ANSWER_INVALID' },
    { answers: [['q_pain_present', 'yes'], ['q_pain_severity', 0]], code: 'ANSWER_INVALID' },
    { answers: [['q_pain_present', 'yes'], ['q_pain_severity', 11]], code: 'ANSWER_INVALID' },
    { answers: [['q_pain_present', 'yes'], ['q_pain_severity', 3.5]], code: 'ANSWER_INVALID' },
    { answers: [['q_pain_present', 'yes'], ['q_associated_symptoms', []]], code: 'ANSWER_INVALID' },
    { answers: [['q_pain_present', 'yes'], ['q_associated_symptoms', ['none', 'nausea']]], code: 'ANSWER_INVALID' },
    { answers: [['q_pain_present', 'yes'], ['q_associated_symptoms', ['fever', 'fever']]], code: 'ANSWER_INVALID' },
    { answers: [['q_unknown_question', 'yes']], code: 'QUESTION_NOT_FOUND' },
  ];

  for (const { answers, code } of cases) {
    const session = newSession();
    let lastError = null;
    for (const [questionId, value] of answers) {
      try {
        session.answer(questionId, value);
      } catch (error) {
        lastError = error;
        break;
      }
    }
    assert.ok(lastError, `expected an error for ${JSON.stringify(answers)}`);
    assert.equal(lastError.code, code, `wrong code for ${JSON.stringify(answers)}: ${lastError.code}`);
  }
});

test('a hostile answer cannot change the shape of the result', () => {
  const session = newSession();
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  const result = session.result();
  const serialized = JSON.stringify(result);
  for (const forbidden of ['percentage', 'probability', 'score', 'rank', 'weight']) {
    assert.ok(!serialized.includes(`"${forbidden}"`), `result exposes ${forbidden}`);
  }
  // Free-text never reaches the result: answers are option ids only.
  assert.ok(!serialized.includes('<script'), 'no raw markup in the result');
});

test('the same answers always produce the same result', () => {
  for (const scenario of adversarial.scenarios) {
    const first = runScenario(scenario).result();
    const second = runScenario(scenario).result();
    assert.deepEqual(first, second, `non-deterministic result for ${scenario.id}`);
  }
});

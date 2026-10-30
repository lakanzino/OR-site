/**
 * Integration test: the published phase-1 scenarios must produce exactly the
 * expected branches, safety flags and coverage when run through the real engine.
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
const scenarios = read('docs/symptom-checker/phase-1/scenarios.v1.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');

/** Answer in ask_order so visibility rules are satisfied. */
function runScenario(scenario) {
  const session = createSession(bundle, { evidenceMap });
  const order = new Map(bundle.questions.map((question) => [question.id, question.ask_order]));
  const entries = Object.entries(scenario.answers).sort((a, b) => order.get(a[0]) - order.get(b[0]));

  for (const [questionId, value] of entries) {
    session.answer(questionId, value);
  }
  return session;
}

for (const scenario of scenarios.scenarios) {
  test(`scenario ${scenario.id}: ${scenario.title_fa}`, () => {
    const session = runScenario(scenario);
    const expected = scenario.expected;
    const result = session.result();

    const branches = session
      .branches()
      .map((branch) => branch.id)
      .sort();
    assert.deepEqual(branches, [...expected.branches_open].sort(), 'open branches');

    for (const forbidden of expected.branches_must_not_open) {
      assert.ok(!branches.includes(forbidden), `forbidden branch opened: ${forbidden}`);
    }

    const safety = session
      .safety()
      .map((rule) => rule.id)
      .sort();
    assert.deepEqual(safety, [...expected.safety_flags].sort(), 'safety flags');

    assert.equal(session.coverage().result, expected.coverage, 'coverage');
    assert.equal(result.cards_allowed, expected.result_cards_allowed, 'cards allowed');

    if (!expected.result_cards_allowed) {
      assert.deepEqual(result.conditions, [], 'no cards may be rendered');
    }
  });
}

test('every scenario is answered without an error and the engine stops cleanly', () => {
  for (const scenario of scenarios.scenarios) {
    const session = runScenario(scenario);
    // Either the questionnaire is exhausted or a safe stop was reached.
    const next = session.nextQuestion();
    if (session.state === 'stopped') {
      assert.equal(next, null, `${scenario.id}: stopped session must not ask more`);
    } else {
      assert.ok(next === null || typeof next.id === 'string');
    }
  }
});

test('result never exposes probability, score or ranking fields', () => {
  const session = runScenario(scenarios.scenarios[0]);
  const result = session.result();
  const forbidden = ['probability', 'score', 'rank', 'confidence', 'likelihood', 'percent'];

  const walk = (node, path = 'result') => {
    if (Array.isArray(node)) {
      node.forEach((item, index) => walk(item, `${path}[${index}]`));
      return;
    }
    if (node && typeof node === 'object') {
      for (const [key, value] of Object.entries(node)) {
        assert.ok(
          !forbidden.includes(key.toLowerCase()),
          `forbidden field "${key}" at ${path}`,
        );
        walk(value, `${path}.${key}`);
      }
    }
  };

  walk(result);
});

test('cards keep their documented reasons and sources', () => {
  const session = runScenario(scenarios.scenarios[0]);
  const [card] = session.result().conditions;
  assert.equal(card.id, 'app');
  assert.ok(card.reasons.length >= 1);
  for (const reason of card.reasons) {
    assert.ok(reason.path_id);
    assert.ok(reason.note_fa);
    assert.ok(reason.source_refs.length >= 1);
  }
  assert.equal(card.link_status, 'needs_live_verification');
});

test('safety warnings are returned even when no card is shown', () => {
  const scenario = scenarios.scenarios.find((item) => item.id === 'sc09_pregnancy_possible');
  const session = runScenario(scenario);
  const result = session.result();
  assert.equal(result.conditions.length, 0);
  assert.ok(result.safety.some((rule) => rule.id === 'sf_pregnancy_out_of_scope'));
});

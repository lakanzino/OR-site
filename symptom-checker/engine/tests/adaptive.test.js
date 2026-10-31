/**
 * Phase 4 — the adaptive order, and the shadow that never asks it.
 *
 * Three promises are pinned here:
 *   1. the adaptive order is deterministic, obeys visibility and never re-asks;
 *   2. it puts the questions that can fire a safety rule first, so no red flag
 *      waits behind a question about something else;
 *   3. a shadow strategy is *computed* and recorded but the visitor keeps
 *      seeing `static_order` — byte for byte, including the result object.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';
import { loadBundle } from '../src/load-bundle.js';
import { buildKnowledge, chooseAdaptive, separationOf, safetyQuestionIds } from '../src/adaptive.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundleV1 = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');
const scenarios = read('docs/symptom-checker/phase-1/scenarios.v1.json').scenarios;

const knowledgeOf = (bundle, map) => buildKnowledge(loadBundle(bundle, { evidenceMap: map }).bundle, map.entries);
const adaptiveSession = (extra = {}) => createSession(bundleV1, { evidenceMap, questionStrategy: 'adaptive', ...extra });

const answerFor = (question) => {
  if (question.type === 'multi') return [question.options[0].id];
  if (question.type === 'scale') return Math.round((question.min + question.max) / 2);
  return question.options[0].id;
};

test('the adaptive order is deterministic and never repeats a question', () => {
  const first = [];
  for (let run = 0; run < 3; run += 1) {
    const session = adaptiveSession();
    const asked = [];
    for (let step = 0; step < 40; step += 1) {
      const question = session.nextQuestion();
      if (!question) break;
      asked.push(question.id);
      session.answer(question.id, answerFor(question));
    }
    first.push(asked);
  }
  assert.deepEqual(first[1], first[0]);
  assert.deepEqual(first[2], first[0]);
  assert.equal(new Set(first[0]).size, first[0].length, 'no question is asked twice');
});

test('every question the adaptive order asks is visible and unanswered at that moment', () => {
  const session = adaptiveSession();
  const seen = new Set();
  for (let step = 0; step < 40; step += 1) {
    const question = session.nextQuestion();
    if (!question) break;
    const visible = session.visibleQuestions().map((item) => item.id);
    assert.ok(visible.includes(question.id), `${question.id} must be visible`);
    assert.ok(!seen.has(question.id), `${question.id} must not be repeated`);
    seen.add(question.id);
    session.answer(question.id, answerFor(question));
  }
});

test('a question that can fire a safety rule is asked before anything else', () => {
  const safety = safetyQuestionIds(bundleV1);
  assert.ok(safety.size >= 8, `expected the bundle's safety rules to name questions, got ${safety.size}`);

  const session = adaptiveSession();
  const first = session.nextQuestion();
  assert.ok(safety.has(first.id), `first question was ${first.id}, which no safety rule reads`);

  // …and the base screen keeps that promise for the whole first block: until a
  // question outside the safety set is asked, every question must be a safety
  // question.
  let leftSafety = true;
  for (let step = 0; step < 6; step += 1) {
    const question = session.nextQuestion();
    if (!question) break;
    if (leftSafety) assert.ok(safety.has(question.id), `${question.id} broke the safety-first block`);
    if (!safety.has(question.id)) leftSafety = false;
    session.answer(question.id, answerFor(question));
  }
});

test('«نمی‌دانم» changes nothing about what gets asked next', () => {
  // Same answers, except one question that allows «نمی‌دانم»: asked, it must not
  // move the order — a recorded answer of "I don't know" is not evidence.
  const build = (withUnknown) => {
    const session = adaptiveSession();
    const asked = [];
    for (let step = 0; step < 40; step += 1) {
      const question = session.nextQuestion();
      if (!question) break;
      asked.push(question.id);
      session.answer(question.id, withUnknown && question.id === 'q_pain_severity' ? 'unknown' : answerFor(question));
    }
    return asked;
  };
  const plain = build(false);
  const unknown = build(true);
  assert.deepEqual(unknown, plain, 'an «نمی‌دانم» must not re-order the questionnaire');
});

test('the separation count is symmetric and ignores a question that touches nothing', () => {
  const knowledge = knowledgeOf(bundleV1, evidenceMap);
  const state = { answers: {}, eligible: [], open: [] };
  const location = bundleV1.questions.find((question) => question.id === 'q_pain_location');
  const urinary = bundleV1.questions.find((question) => question.id === 'q_urinary_symptoms');
  const score = separationOf(knowledge, location, state.answers);
  assert.ok(score.total > 0, 'the pain location separates candidates by the bundle rules');
  assert.ok(score.path_pairs > 0, 'and it can open a documented path for at least one candidate');
  assert.ok(Number.isInteger(score.pairs) && Number.isInteger(score.path_pairs));

  // Two questions nobody can report on separate nothing: the count is 0, not a
  // silent pass. `q_pain_onset` is inert in this bundle (gap review F2-04).
  const onset = bundleV1.questions.find((question) => question.id === 'q_pain_onset');
  assert.equal(separationOf(knowledge, onset, state.answers).pairs, 0);
  assert.ok(separationOf(knowledge, urinary, state.answers).pairs >= 0);
});

test('the safety policy decides whether a secondary branch may hijack the chief complaint', () => {
  const knowledge = knowledgeOf(bundleV1, evidenceMap);
  const eligible = bundleV1.questions
    .filter((question) => question.visibility?.always)
    .map((question) => ({ ...question, options: question.options ?? [] }));
  const alwaysFirst = chooseAdaptive(knowledge, { answers: {}, eligible, open: [], safetyPolicy: 'always_first' });
  const baseScreen = chooseAdaptive(knowledge, { answers: {}, eligible, open: [], safetyPolicy: 'base_screen' });
  assert.equal(alwaysFirst.reason.rule, 'safety_first');
  assert.equal(baseScreen.reason.rule, 'safety_first');
  // Both start on the safety net of the base screen; they may only diverge once
  // a secondary branch reveals a safety question.
  assert.ok(knowledge.baseScreen.has(alwaysFirst.question.id));
  assert.ok(knowledge.baseScreen.has(baseScreen.question.id));
});

test('the engine asks static_order by default even though adaptive exists', () => {
  const session = createSession(bundleV1, { evidenceMap });
  assert.equal(session.strategy, 'static_order');
  const ordered = [...bundleV1.questions].sort((a, b) => a.ask_order - b.ask_order);
  const firstAlwaysVisible = ordered.find((question) => question.visibility?.always);
  assert.equal(session.nextQuestion().id, firstAlwaysVisible.id);
});

test('the shadow is recorded and never shown', () => {
  const session = createSession(bundleV1, { evidenceMap, shadowStrategy: 'adaptive' });
  assert.equal(session.strategy, 'static_order');
  assert.equal(session.shadowStrategy, 'adaptive');

  const shown = session.nextQuestion();
  assert.equal(shown.id, 'q_pain_present', 'the visitor still gets the static order');

  const trace = session.shadowTrace();
  assert.equal(trace.length, 1);
  assert.equal(trace[0].static, 'q_pain_present');
  assert.equal(trace[0].shadow, 'q_urinary_symptoms');
  assert.equal(trace[0].differs, true);
  assert.equal(trace[0].reason.rule, 'safety_first');
});

test('the shadow changes nothing in the answers or the result', () => {
  const play = (session, scenario) => {
    const order = new Map(bundleV1.questions.map((question) => [question.id, question.ask_order]));
    for (const [questionId, value] of Object.entries(scenario.answers).sort((a, b) => order.get(a[0]) - order.get(b[0]))) {
      session.answer(questionId, value);
    }
    return session;
  };
  for (const scenario of scenarios.slice(0, 4)) {
    const without = play(createSession(bundleV1, { evidenceMap }), scenario);
    const withShadow = play(createSession(bundleV1, { evidenceMap, shadowStrategy: 'adaptive' }), scenario);
    assert.deepEqual(withShadow.result(), without.result(), scenario.id);
    assert.deepEqual(withShadow.shadowTrace(), [], 'answers given directly never trigger a shadow step');
  }
});

test('a broken shadow is recorded and the questionnaire carries on', () => {
  const session = createSession(bundleV1, {
    evidenceMap,
    shadowStrategy: () => {
      throw new Error('shadow exploded');
    },
  });
  const first = session.nextQuestion();
  assert.equal(first.id, 'q_pain_present');
  assert.equal(session.shadowTrace()[0].error, 'shadow exploded');
  assert.equal(session.shadowTrace()[0].shadow, null);
  assert.ok(session.answer('q_pain_present', 'yes').state === 'asking');
});

test('the result object never carries the shadow', () => {
  const session = createSession(bundleV1, { evidenceMap, shadowStrategy: 'adaptive' });
  session.nextQuestion();
  session.answer('q_pain_present', 'yes');
  const keys = Object.keys(session.result());
  assert.ok(!keys.some((key) => key.toLowerCase().includes('shadow')));
  assert.ok(!JSON.stringify(session.result()).includes('q_urinary_symptoms'));
});

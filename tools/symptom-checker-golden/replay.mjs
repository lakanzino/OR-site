/**
 * Shared replay logic for the golden snapshots and the migration's equivalence
 * gate.
 *
 * Both tools must answer a scenario in exactly the same way as
 * `symptom-checker/engine/tests/scenarios.test.js` (answers in `ask_order`,
 * nothing filled in), and the random walk must use the same seeded generator,
 * otherwise "the same output" would mean nothing. Keeping one copy here is the
 * only way to guarantee that.
 */

import { createSession } from '../../symptom-checker/engine/src/engine.js';

/** Stable JSON: object keys sorted, so a stored snapshot and a fresh run compare. */
export function canonical(value) {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value && typeof value === 'object') {
    const keys = Object.keys(value).sort();
    return `{${keys.map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
  }
  return JSON.stringify(value);
}

/** mulberry32: small, fast, and identical on every platform and node version. */
export function mulberry32(seed) {
  let state = seed >>> 0;
  return function next() {
    state = (state + 0x6d2b79f5) >>> 0;
    let t = Math.imul(state ^ (state >>> 15), 1 | state);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/**
 * Replicates `symptom-checker/engine/tests/scenarios.test.js`: answers are given
 * in ask_order and nothing else is filled in.
 */
export function runScenario(bundle, evidenceMap, scenario) {
  const session = createSession(bundle, evidenceMap ? { evidenceMap } : undefined);
  const order = new Map((bundle.questions ?? []).map((question) => [question.id, question.ask_order]));
  const entries = Object.entries(scenario.answers).sort((a, b) => order.get(a[0]) - order.get(b[0]));
  for (const [questionId, value] of entries) {
    if (!session.canAnswer(questionId)) {
      throw new Error(`${scenario.id}: answer for a question that is not visible: ${questionId}`);
    }
    session.answer(questionId, value);
  }
  return { answers: session.answers(), result: session.result() };
}

/** Apply a recorded answer list (in recorded order) to an existing session. */
export function applyAnswers(session, answers) {
  for (const [questionId, value] of Object.entries(answers)) {
    session.answer(questionId, value);
  }
  return session;
}

export function randomAnswer(question, random) {
  const pick = (list) => list[Math.floor(random() * list.length)];

  if (question.type === 'scale') {
    if (question.allow_unknown && random() < 0.1) return 'unknown';
    return question.min + Math.floor(random() * (question.max - question.min + 1));
  }

  if (question.type === 'multi') {
    const exclusive = question.options.filter((option) => option.exclusive);
    if (exclusive.length && random() < 0.3) return [pick(exclusive).id];
    const pool = question.options.filter((option) => !option.exclusive);
    const wanted = 1 + Math.floor(random() * Math.min(pool.length, 3));
    const chosen = new Set();
    while (chosen.size < wanted) chosen.add(pick(pool).id);
    // Keep the bundle's option order, exactly like the engine does.
    return question.options.map((option) => option.id).filter((id) => chosen.has(id));
  }

  return pick(question.options).id;
}

export function randomPath(bundle, evidenceMap, random) {
  const session = createSession(bundle, evidenceMap ? { evidenceMap } : undefined);
  for (;;) {
    const question = session.nextQuestion();
    if (!question) break;
    session.answer(question.id, randomAnswer(question, random));
  }
  return { answers: session.answers(), result: session.result() };
}

/** First differing path between two values, e.g. `.result.conditions.0.name_fa`. */
export function diffPath(expected, actual, trail = '') {
  if (canonical(expected) === canonical(actual)) return null;
  if (expected && actual && typeof expected === 'object' && typeof actual === 'object') {
    const keys = new Set([...Object.keys(expected), ...Object.keys(actual)]);
    for (const key of keys) {
      const found = diffPath(expected[key], actual[key], `${trail}.${key}`);
      if (found) return found;
    }
  }
  return trail || '(root)';
}

export function valueAt(value, trail) {
  if (trail === '(root)' || !trail) return value;
  return trail
    .split('.')
    .slice(1)
    .reduce((node, key) => (node === undefined || node === null ? undefined : node[key]), value);
}

export function describeDifference(label, expected, actual) {
  const where = diffPath(expected, actual) ?? '(unknown)';
  const show = (value) => {
    const found = valueAt(value, where);
    return found === undefined ? 'undefined' : JSON.stringify(found).slice(0, 300);
  };
  return { label, where, expected: show(expected), actual: show(actual) };
}

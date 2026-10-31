/**
 * Phase 2: the engine becomes configurable without changing what it does.
 *
 * Three promises are pinned here:
 *   1. `loadBundle()` turns both bundle shapes into one model, and refuses
 *      anything else;
 *   2. the question order is injectable, `static_order` is the default, and
 *      `adaptive` is a real strategy that only a configuration may switch to;
 *   3. `profile_match` appears only when asked for, never carries a number, and
 *      «نمی‌دانم» never removes a card.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';
import { loadBundle } from '../src/load-bundle.js';
import { BUILT_IN_STRATEGIES, STATIC_ORDER, ADAPTIVE, resolveStrategy } from '../src/strategy.js';
import { PROFILE_MATCH_LEVELS } from '../src/profile-match.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundleV1 = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const bundleV2 = read('docs/symptom-checker/phase-1/knowledge-bundle.v2.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');
const scenarios = read('docs/symptom-checker/phase-1/scenarios.v1.json').scenarios;
const clone = (value) => JSON.parse(JSON.stringify(value));

function play(session, scenario, bundle) {
  const order = new Map(bundle.questions.map((question) => [question.id, question.ask_order]));
  for (const [questionId, value] of Object.entries(scenario.answers).sort(
    (a, b) => order.get(a[0]) - order.get(b[0]),
  )) {
    session.answer(questionId, value);
  }
  return session;
}

// --- loadBundle -------------------------------------------------------------

test('loadBundle normalises both shapes into one model', () => {
  const one = loadBundle(bundleV1, { evidenceMap });
  const two = loadBundle(bundleV2);

  assert.equal(one.format, '1.0.0');
  assert.equal(two.format, '2.0.0');
  assert.equal(two.schemaVersion, '2.0.0');
  assert.equal(one.bundle.schema_version, two.bundle.schema_version, 'both become the internal shape');
  assert.equal(one.bundleId, two.bundleId);
  assert.equal(one.bundleVersion, two.bundleVersion);
  assert.equal(one.evidenceMap.entries.length, evidenceMap.entries.length);
  assert.equal(two.evidenceMap.entries.length, evidenceMap.entries.length);
});

test('loadBundle refuses a non-bundle and names what it got', () => {
  for (const bad of [null, undefined, 'a string', 42, []]) {
    assert.throws(() => loadBundle(bad), (error) => {
      assert.equal(error.code, 'BUNDLE_FORMAT_INVALID');
      return true;
    });
  }
});

test('loadBundle refuses an unknown schema but names the version', () => {
  assert.throws(() => loadBundle({ ...clone(bundleV1), schema_version: '9.9.9' }), /9\.9\.9/);
});

test('loadBundle reports the bundle validation issues it found', () => {
  const broken = clone(bundleV1);
  delete broken.paths;
  assert.throws(() => loadBundle(broken, { evidenceMap }), (error) => {
    assert.equal(error.code, 'BUNDLE_INVALID');
    assert.ok(Array.isArray(error.issues) && error.issues.length > 0);
    return true;
  });
});

// --- strategies -------------------------------------------------------------

test('static_order is the default and is what the engine always did', () => {
  const scenario = scenarios[0];
  const plain = play(createSession(bundleV1, { evidenceMap }), scenario, bundleV1);
  const explicit = play(
    createSession(bundleV1, { evidenceMap, questionStrategy: STATIC_ORDER }),
    scenario,
    bundleV1,
  );
  assert.equal(plain.strategy, STATIC_ORDER);
  assert.equal(explicit.strategy, STATIC_ORDER);
  assert.deepEqual(explicit.result(), plain.result());
});

test('both built-in strategies exist', () => {
  assert.deepEqual([...BUILT_IN_STRATEGIES], [STATIC_ORDER, ADAPTIVE]);
  assert.equal(resolveStrategy(ADAPTIVE).name, ADAPTIVE);
});

test('adaptive is a working strategy, and no visitor is switched to it', () => {
  // Phase 4 filled the adaptive order in. What must stay pinned is the other
  // half of the promise: the engine's default is still `static_order`, and a
  // session that was configured with `adaptive` derives exactly the same result
  // from the same answers — order changes what is asked when, never what it
  // means.
  assert.equal(createSession(bundleV1, { evidenceMap }).strategy, STATIC_ORDER);

  for (const scenario of scenarios.slice(0, 6)) {
    const fromStatic = play(createSession(bundleV1, { evidenceMap }), scenario, bundleV1);
    const fromAdaptive = play(
      createSession(bundleV1, { evidenceMap, questionStrategy: ADAPTIVE }),
      scenario,
      bundleV1,
    );
    assert.equal(fromAdaptive.strategy, ADAPTIVE);
    assert.deepEqual(fromAdaptive.answers(), fromStatic.answers(), scenario.id);
    assert.deepEqual(fromAdaptive.result(), fromStatic.result(), scenario.id);
  }

  // The two orders really are different — otherwise the test above would prove
  // nothing. The static order starts with the pain question, the adaptive one
  // with the safety net.
  const staticFirst = createSession(bundleV1, { evidenceMap }).nextQuestion().id;
  const adaptiveFirst = createSession(bundleV1, { evidenceMap, questionStrategy: ADAPTIVE }).nextQuestion().id;
  assert.notEqual(adaptiveFirst, staticFirst);
});

test('the question order comes from the strategy, not from the engine', () => {
  // A deliberately different order: newest ask_order first. Legal because the
  // bundle states its visibility rules, and this strategy still respects them.
  const reversed = ({ eligible }) => eligible[eligible.length - 1] ?? null;
  const session = createSession(bundleV1, { evidenceMap, selectNextQuestion: reversed });
  assert.equal(session.strategy, 'custom');

  const first = session.nextQuestion();
  const order = [...bundleV1.questions].sort((a, b) => b.ask_order - a.ask_order);
  assert.equal(first.id, order.find((question) => question.visibility?.always).id);
});

test('an unknown strategy name is refused with a typed error', () => {
  assert.throws(() => createSession(bundleV1, { evidenceMap, questionStrategy: 'by_vibes' }), (error) => {
    assert.equal(error.code, 'QUESTION_STRATEGY_INVALID');
    assert.match(error.message, /by_vibes/);
    return true;
  });
});

test('a strategy cannot ask a question the bundle hides or one already answered', () => {
  // `q_keep_fluids` is only visible after diarrhea or vomiting is reported.
  const scenario = scenarios.find((item) => item.id === 'sc09_pregnancy_possible');
  const hidden = createSession(bundleV1, { evidenceMap, selectNextQuestion: () => 'q_keep_fluids' });
  assert.throws(() => hidden.nextQuestion(), (error) => {
    assert.equal(error.code, 'QUESTION_STRATEGY_INVALID');
    assert.match(error.message, /not askable/);
    return true;
  });

  const repeat = createSession(bundleV1, { evidenceMap, selectNextQuestion: ({ asked }) => asked[0] ?? null });
  repeat.answer('q_pain_present', 'yes');
  assert.throws(() => repeat.nextQuestion(), (error) => error.code === 'QUESTION_STRATEGY_INVALID');

  assert.equal(typeof scenario?.id, 'string', 'the pregnancy scenario still exists');
});

test('a strategy that returns null ends the questionnaire without an error', () => {
  const session = createSession(bundleV1, { evidenceMap, selectNextQuestion: () => null });
  assert.equal(session.nextQuestion(), null);
  assert.equal(session.result().state, 'asking');
});

test('the strategy receives copies, so it cannot mutate the session', () => {
  const vandal = ({ answers, eligible }) => {
    answers.q_pain_present = 'yes';
    eligible[0].text_fa = 'changed';
    return eligible[0];
  };
  const session = createSession(bundleV1, { evidenceMap, selectNextQuestion: vandal });
  session.nextQuestion();
  assert.deepEqual(session.answers(), {});
  assert.notEqual(session.nextQuestion().text_fa, 'changed');
});

// --- profile_match ----------------------------------------------------------

test('profile_match is absent unless the host asks for it', () => {
  const scenario = scenarios[0];
  const result = play(createSession(bundleV1, { evidenceMap }), scenario, bundleV1).result();
  assert.equal('profile_match' in result, false, 'phase 2 must not change the result shape by default');
});

test('profile_match is a word per condition, never a number', () => {
  const scenario = scenarios[0];
  const session = play(
    createSession(bundleV1, { evidenceMap, profileMatch: true }),
    scenario,
    bundleV1,
  );
  const result = session.result();
  assert.ok(result.profile_match, 'the field is present when enabled');
  for (const [conditionId, level] of Object.entries(result.profile_match)) {
    assert.ok(PROFILE_MATCH_LEVELS.includes(level), `${conditionId}: ${level}`);
    assert.equal(typeof level, 'string');
  }
  assert.ok(Object.keys(result.profile_match).length > 0);
});

test('profile_match covers exactly the conditions that have cards', () => {
  const scenario = scenarios[0];
  const result = play(
    createSession(bundleV1, { evidenceMap, profileMatch: true }),
    scenario,
    bundleV1,
  ).result();
  assert.deepEqual(
    Object.keys(result.profile_match).sort(),
    result.conditions.map((card) => card.id).sort(),
  );
});

test('a stronger answer set cannot lower the group of the same condition', () => {
  const strong = scenarios.find((item) => item.id === 'sc01_classic_appendicitis_pattern');
  const weaker = clone(strong);
  weaker.answers.q_pain_migrated_rlq = 'unknown';
  weaker.answers.q_associated_symptoms = ['none'];
  weaker.answers.q_pain_worse_movement = 'unknown';

  const levelOf = (scenario) => {
    const result = play(createSession(bundleV1, { evidenceMap, profileMatch: true }), scenario, bundleV1).result();
    return result.profile_match?.app ?? null;
  };
  const rank = { weak: 0, partial: 1, strong: 2 };
  assert.ok(levelOf(weaker) !== null && levelOf(strong) !== null);
  assert.ok(rank[levelOf(strong)] >= rank[levelOf(weaker)]);
});

test('no cards means no profile_match to read', () => {
  const scenario = scenarios.find((item) => item.id === 'sc08_unknown_everything');
  const result = play(
    createSession(bundleV1, { evidenceMap, profileMatch: true }),
    scenario,
    bundleV1,
  ).result();
  assert.equal(result.cards_allowed, false);
  assert.deepEqual(result.profile_match, {});
});

// --- safety ----------------------------------------------------------------

test('«نمی‌دانم» never removes a card that is already open', () => {
  for (const strategyName of BUILT_IN_STRATEGIES) {
    const session = createSession(bundleV1, { evidenceMap, questionStrategy: strategyName });
    session.answer('q_pain_present', 'yes');
    session.answer('q_pain_location', 'right_lower');
    const before = session.result();
    assert.ok(before.conditions.some((card) => card.id === 'app'), `${strategyName}: card open`);

    const next = session.nextQuestion();
    assert.ok(next, `${strategyName}: there is a next question`);
    if (next.type === 'scale') {
      session.answer(next.id, 'unknown');
    } else if (next.type === 'multi') {
      session.answer(next.id, ['unknown']);
    } else {
      session.answer(next.id, 'unknown');
    }

    const after = session.result();
    assert.ok(
      after.conditions.some((card) => card.id === 'app'),
      `${strategyName}: an unknown answer must not close the appendicitis card`,
    );
  }
});

test('with the flag on, «نمی‌دانم» still never adds a card', () => {
  const session = createSession(bundleV1, { evidenceMap, profileMatch: true });
  session.answer('q_pain_present', 'unknown');
  const result = session.result();
  assert.deepEqual(result.conditions, []);
  assert.deepEqual(result.profile_match, {});
});

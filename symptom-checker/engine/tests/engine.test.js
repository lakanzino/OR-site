/**
 * Unit tests for the phase-2 engine: validation, answer semantics, visibility,
 * editing, undo and the safe stop.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createSession } from '../src/engine.js';
import {
  AnswerValidationError,
  BundleValidationError,
  BundleVersionError,
  QuestionNotVisibleError,
  SessionStoppedError,
  UnknownQuestionError,
} from '../src/errors.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

const bundle = read('docs/symptom-checker/phase-1/knowledge-bundle.v1.json');
const evidenceMap = read('symptom-checker/engine/data/evidence-map.v1.json');

const clone = (value) => JSON.parse(JSON.stringify(value));

test('a valid bundle with a matching evidence map opens a session', () => {
  const session = createSession(bundle, { evidenceMap });
  assert.equal(session.bundleId, bundle.bundle_id);
  assert.equal(session.state, 'asking');
  assert.equal(session.nextQuestion().id, 'q_pain_present');
});

test('unsupported schema version is rejected before any question', () => {
  const broken = clone(bundle);
  broken.schema_version = '9.9.9';
  assert.throws(() => createSession(broken), BundleVersionError);
});

test('structurally broken bundle is rejected with a list of issues', () => {
  const broken = clone(bundle);
  broken.paths[0].source_refs = [];
  assert.throws(
    () => createSession(broken),
    (error) => error instanceof BundleValidationError && error.issues.length > 0,
  );
});

test('unknown question id is rejected', () => {
  const session = createSession(bundle, { evidenceMap });
  assert.throws(() => session.answer('q_does_not_exist', 'yes'), UnknownQuestionError);
});

test('invalid option and invalid multi answers are rejected', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  assert.throws(() => session.answer('q_pain_location', 'left_elbow'), AnswerValidationError);
  assert.throws(
    () => session.answer('q_urinary_symptoms', []),
    AnswerValidationError,
  );
  assert.throws(
    () => session.answer('q_urinary_symptoms', ['dysuria', 'dysuria']),
    AnswerValidationError,
  );
  assert.throws(
    () => session.answer('q_urinary_symptoms', ['none', 'dysuria']),
    AnswerValidationError,
  );
});

test('scale answers are bounded and must be integers', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  assert.throws(() => session.answer('q_pain_severity', 11), AnswerValidationError);
  assert.throws(() => session.answer('q_pain_severity', 3.5), AnswerValidationError);
  session.answer('q_pain_severity', 4);
  assert.equal(session.answers().q_pain_severity, 4);
});

test('a hidden question cannot be answered', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'no');
  assert.throws(() => session.answer('q_pain_severity', 5), QuestionNotVisibleError);
});

test('questions are asked in order and hidden ones are skipped', () => {
  const session = createSession(bundle, { evidenceMap });
  assert.equal(session.nextQuestion().id, 'q_pain_present');
  session.answer('q_pain_present', 'no');
  const visibleIds = session.visibleQuestions().map((question) => question.id);
  assert.ok(!visibleIds.includes('q_pain_location'));
  assert.ok(visibleIds.includes('q_urinary_symptoms'));
  assert.equal(session.nextQuestion().id, 'q_urinary_symptoms');
});

test('unknown never opens a branch that needs a positive answer', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'unknown');
  session.answer('q_pain_migrated_rlq', 'unknown');
  session.answer('q_urinary_symptoms', ['unknown']);
  assert.deepEqual(session.branches(), []);
  assert.equal(session.coverage().result, 'insufficient');
});

test('safety rules fire immediately, before the questionnaire ends', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_pain_migrated_rlq', 'yes');
  session.answer('q_pain_onset', 'today');
  session.answer('q_pain_course', 'stable');
  session.answer('q_pain_severity', 6);
  session.answer('q_pain_worse_movement', 'yes');
  session.answer('q_associated_symptoms', ['nausea']);
  session.answer('q_urinary_symptoms', ['none']);
  session.answer('q_flank_radiation', 'no');
  session.answer('q_fever_chills', 'no');
  session.answer('q_diarrhea_vomiting', ['none']);
  assert.ok(session.safety().some((rule) => rule.id === 'sf_severe_pain') === false);
  session.answer('q_unbearable_pain', 'yes');
  assert.ok(session.safety().some((rule) => rule.id === 'sf_severe_pain'));
});

test('changing a parent answer invalidates answers that are no longer visible', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_pain_migrated_rlq', 'yes');
  assert.deepEqual(session.branches().map((branch) => branch.id), ['app']);

  session.answer('q_pain_present', 'no');
  const answers = session.answers();
  assert.equal(answers.q_pain_location, undefined, 'hidden answer must leave the active set');
  assert.equal(answers.q_pain_migrated_rlq, undefined);
  assert.equal(session.inactiveAnswers().q_pain_location, 'right_lower');
  assert.deepEqual(session.branches(), []);
});

test('undo restores the previous answers', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  assert.equal(session.canUndo(), true);

  session.answer('q_pain_location', 'flank_or_back');
  session.undo();
  assert.equal(session.answers().q_pain_location, 'right_lower');
  assert.equal(session.answers().q_pain_present, 'yes');
});

test('out_of_scope stops the session, blocks new questions and can be undone', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_pain_migrated_rlq', 'yes');
  session.answer('q_urinary_symptoms', ['none']);
  session.answer('q_diarrhea_vomiting', ['none']);
  session.answer('q_pregnancy_possible', 'yes');

  assert.equal(session.state, 'stopped');
  assert.equal(session.nextQuestion(), null);
  assert.equal(session.result().cards_allowed, false);
  assert.throws(() => session.answer('q_pain_course', 'worse'), SessionStoppedError);

  // The person can still correct the answer that caused the stop.
  session.answer('q_pregnancy_possible', 'no');
  assert.equal(session.state, 'asking');
  assert.ok(session.nextQuestion() !== null);
});

test('evidence separates present, unknown, absent and not covered', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_pain_migrated_rlq', 'yes');
  session.answer('q_urinary_symptoms', ['dysuria']);
  session.answer('q_fever_chills', 'unknown');

  const result = session.result();
  assert.equal(result.coverage.result, 'sufficient');
  const app = result.conditions.find((card) => card.id === 'app');
  const present = app.evidence.present.map((item) => item.symptom_id);
  const unknown = app.evidence.unknown.map((item) => item.symptom_id);
  const absent = app.evidence.absent.map((item) => item.symptom_id);

  assert.ok(present.includes('s_pain_rlq'));
  assert.ok(present.includes('s_pain_migrates_rlq'));
  assert.ok(unknown.includes('s_fever'));
  assert.ok(absent.includes('s_nausea') === false, 'never answered ⇒ not absent');

  const uti = result.conditions.find((card) => card.id === 'urinary_tract');
  const notCovered = uti.evidence.not_covered.map((item) => item.symptom_id);
  assert.ok(notCovered.includes('s_bladder_pressure'));
});

test('contradictory evidence is reported as a conflict, not silently merged', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  session.answer('q_associated_symptoms', ['fever']);
  session.answer('q_fever_chills', 'no');

  const app = session.result().conditions.find((card) => card.id === 'app');
  const conflict = app.evidence.conflict.map((item) => item.symptom_id);
  assert.ok(conflict.includes('s_fever'));
});

test('result carries disclaimers and a bundle version for the UI', () => {
  const session = createSession(bundle, { evidenceMap });
  session.answer('q_pain_present', 'yes');
  session.answer('q_pain_location', 'right_lower');
  const result = session.result();
  assert.equal(result.bundle_version, bundle.bundle_version);
  assert.ok(result.disclaimers.before_start_fa.length > 10);
  assert.ok(result.disclaimers.result_fa.length > 10);
});

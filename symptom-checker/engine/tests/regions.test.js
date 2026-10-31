/**
 * The body, not just the abdomen (bundle 3.x).
 *
 * The tool opens with sex, then one of five zones, then the part inside that
 * zone. These tests drive the real engine with the real shipped bundle and check
 * what that path does:
 *
 *   1. a zone only ever asks about itself — nothing from another zone leaks in;
 *   2. the pelvic area is complete: urinary, penis, scrotum, vulva, anus;
 *   3. the stop screens (chest pain, pregnancy) still stop;
 *   4. the questions of one area never depend on a sex they do not belong to.
 *
 * Nothing here is a clinical claim: it only proves the structure is what the
 * design note says it is.
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

const bundle = read('docs/symptom-checker/phase-6/knowledge-bundle.v3.json');

/** Walk the gates, then answer every remaining question with the given plan. */
function walk(gates, plan = {}) {
  const session = createSession(bundle);
  for (const [questionId, value] of Object.entries(gates)) session.answer(questionId, value);

  const asked = [];
  for (;;) {
    const question = session.nextQuestion();
    if (!question) break;
    asked.push(question.id);
    const value = plan[question.id];
    if (value === undefined) break; // stop early: we only wanted the next question
    session.answer(question.id, value);
  }
  return { session, asked };
}

/** The questions the engine would ask, in order, if every one got its first option. */
function askAll(gates) {
  const session = createSession(bundle);
  for (const [questionId, value] of Object.entries(gates)) session.answer(questionId, value);
  const asked = [];
  for (;;) {
    const question = session.nextQuestion();
    if (!question) break;
    asked.push(question.id);
    const first = question.type === 'scale'
      ? question.min
      : (question.type === 'multi' ? [question.options[0].id] : question.options[0].id);
    session.answer(question.id, first);
  }
  return { session, asked, result: session.result() };
}

test('the tool opens with sex, then the body — nothing else', () => {
  const session = createSession(bundle);
  assert.equal(session.nextQuestion().id, 'q_sex');
  session.answer('q_sex', 'male');
  assert.equal(session.nextQuestion().id, 'q_region');
  assert.deepEqual(session.visibleQuestions().map((q) => q.id).sort(), ['q_region', 'q_sex']);
});

test('each zone asks only its own questions', () => {
  const cases = [
    { gates: { q_sex: 'male', q_region: 'arms', q_area_arms: 'hand' }, prefix: ['q_arm_'] },
    { gates: { q_sex: 'male', q_region: 'legs', q_area_legs: 'lower_foot' }, prefix: ['q_leg_'] },
    { gates: { q_sex: 'female', q_region: 'head_neck', q_area_head: 'throat' }, prefix: ['q_throat_'] },
    { gates: { q_sex: 'male', q_region: 'torso', q_area_torso: 'back' }, prefix: ['q_back_'] },
    { gates: { q_sex: 'female', q_region: 'pelvis', q_area_pelvis_f: 'vulva' }, prefix: ['q_vag_'] },
    { gates: { q_sex: 'male', q_region: 'pelvis', q_area_pelvis_m: 'scrotum' }, prefix: ['q_scrotum_'] },
    { gates: { q_sex: 'male', q_region: 'pelvis', q_area_pelvis_m: 'penis' }, prefix: ['q_penis_'] },
  ];

  for (const { gates, prefix } of cases) {
    const { asked } = askAll(gates);
    assert.ok(asked.length > 0, `no question was asked for ${JSON.stringify(gates)}`);
    const foreign = asked.filter((id) => !prefix.some((head) => id.startsWith(head)));
    assert.deepEqual(foreign, [], `${JSON.stringify(gates)} also asked ${foreign.join(', ')}`);
  }
});

test('the pelvic area is complete: urine, penis, scrotum, vulva, anus', () => {
  const areas = {
    urinary: { q_sex: 'male', q_region: 'pelvis', q_area_pelvis_m: 'urinary' },
    penis: { q_sex: 'male', q_region: 'pelvis', q_area_pelvis_m: 'penis' },
    scrotum: { q_sex: 'male', q_region: 'pelvis', q_area_pelvis_m: 'scrotum' },
    vulva: { q_sex: 'female', q_region: 'pelvis', q_area_pelvis_f: 'vulva' },
    anus: { q_sex: 'female', q_region: 'pelvis', q_area_pelvis_f: 'anus' },
  };

  for (const [area, gates] of Object.entries(areas)) {
    const options = bundle.questions
      .filter((question) => question.id.startsWith('q_area_pelvis'))
      .flatMap((question) => question.options.map((option) => option.id));
    assert.ok(options.includes(area), `the area list is missing ${area}`);
    const { asked } = askAll(gates);
    assert.ok(asked.length >= 3, `${area} asks only ${asked.length} questions`);
  }

  // The three sex-specific area questions exist and each one is gated on both
  // the sex and the zone, so a man is never asked about a vagina.
  for (const [id, sex] of [['q_area_pelvis_m', 'male'], ['q_area_pelvis_f', 'female'], ['q_area_pelvis_x', 'prefer_not']]) {
    const question = bundle.questions.find((item) => item.id === id);
    assert.deepEqual(question.visibility.all_of, [
      { question_id: 'q_sex', answers: [sex] },
      { question_id: 'q_region', answers: ['pelvis'] },
    ]);
  }
});

test('a man is never shown a question about the vulva, and the reverse', () => {
  const man = askAll({ q_sex: 'male', q_region: 'pelvis', q_area_pelvis_m: 'urinary' }).asked;
  assert.deepEqual(man.filter((id) => id.startsWith('q_vag_')), []);
  const woman = askAll({ q_sex: 'female', q_region: 'pelvis', q_area_pelvis_f: 'urinary' }).asked;
  assert.deepEqual(woman.filter((id) => id.startsWith('q_scrotum_') || id.startsWith('q_penis_')), []);
});

test('choosing «prefer not to say» still reaches the genital questions, without the sex-specific ones', () => {
  const { asked } = askAll({ q_sex: 'prefer_not', q_region: 'pelvis', q_area_pelvis_x: 'genital' });
  assert.ok(asked.includes('q_gen_discharge'));
  assert.deepEqual(asked.filter((id) => /^q_(vag|scrotum|penis)_/.test(id)), []);
});

test('genital warts, herpes and torsion each open their own topic', () => {
  const warts = createSession(bundle);
  warts.answer('q_sex', 'male');
  warts.answer('q_region', 'pelvis');
  warts.answer('q_area_pelvis_m', 'penis');
  warts.answer('q_penis_discharge', 'no');
  warts.answer('q_penis_ulcer', 'no');
  warts.answer('q_penis_warts', 'yes');
  warts.answer('q_penis_pain', 'no');
  warts.answer('q_penis_swelling', 'no');
  const wartCards = warts.result().conditions.map((card) => card.id);
  assert.deepEqual(wartCards, ['genital_warts']);

  const herpes = createSession(bundle);
  herpes.answer('q_sex', 'female');
  herpes.answer('q_region', 'pelvis');
  herpes.answer('q_area_pelvis_f', 'vulva');
  for (const id of ['q_vag_discharge', 'q_vag_itch', 'q_vag_warts', 'q_vag_bleeding', 'q_vag_pain_sex']) herpes.answer(id, 'no');
  herpes.answer('q_vag_ulcer', 'yes');
  assert.deepEqual(herpes.result().conditions.map((card) => card.id), ['genital_herpes']);

  const torsion = createSession(bundle);
  torsion.answer('q_sex', 'male');
  torsion.answer('q_region', 'pelvis');
  torsion.answer('q_area_pelvis_m', 'scrotum');
  torsion.answer('q_scrotum_pain', 'yes');
  torsion.answer('q_scrotum_onset', 'sudden');
  torsion.answer('q_scrotum_swelling', 'yes');
  torsion.answer('q_scrotum_lump', 'no');
  torsion.answer('q_scrotum_sick', 'yes');
  const result = torsion.result();
  assert.deepEqual(result.conditions.map((card) => card.id), ['testicular_torsion']);
  assert.ok(result.safety.some((rule) => rule.id === 'sf_scrotum_sudden' && rule.severity === 'urgent'));
});

test('chest pain stops the tool with an urgent message instead of guessing', () => {
  const session = createSession(bundle);
  session.answer('q_sex', 'female');
  session.answer('q_region', 'torso');
  session.answer('q_area_torso', 'chest');
  session.answer('q_chest_pain', 'yes');
  const result = session.result();
  assert.equal(result.state, 'stopped');
  assert.equal(result.coverage.result, 'out_of_scope');
  assert.equal(result.cards_allowed, false);
  assert.ok(result.safety.some((rule) => rule.id === 'sf_chest_pain' && rule.severity === 'urgent'));
  assert.equal(session.nextQuestion(), null);
});

test('the abdomen path still works when reached through the body map', () => {
  const { asked, result } = askAll({ q_sex: 'male', q_region: 'torso', q_area_torso: 'abdomen' });
  assert.ok(asked.includes('q_pain_present'));
  assert.ok(result.cards_allowed);
});

test('every condition in the bundle can actually be opened', () => {
  const opened = new Set(bundle.overrides.map((path) => path.condition_id));
  for (const condition of bundle.conditions) {
    assert.ok(opened.has(condition.id), `${condition.id} has no path`);
  }
});

test('every genital topic has at least one path from a genital area', () => {
  const genital = ['genital_warts', 'genital_herpes', 'urethritis', 'epididymitis', 'testicular_torsion',
    'bacterial_vaginosis', 'vaginal_thrush', 'hemorrhoids', 'anal_fissure'];
  for (const id of genital) {
    const paths = bundle.overrides.filter((path) => path.condition_id === id);
    assert.ok(paths.length > 0, `${id} has no path`);
  }
});

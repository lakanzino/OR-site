/**
 * Safety-path matrix: every red-flag rule must fire from the answers that
 * describe it, and must stay silent when the answer does not.
 *
 * This is the executable form of the phase-5 requirement «قواعد هشدار همیشه
 * فعال شوند و هرگز زیر کارت‌ها پنهان نشوند». It is deliberately not a clinical
 * claim: each rule is checked against its own data, not against a diagnosis.
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

const session = () => createSession(bundle, { evidenceMap });
const ids = (rules) => rules.map((rule) => rule.id);

/** Minimal answers that describe the situation a rule exists for. */
const CASES = [
  {
    rule: 'sf_severe_pain',
    severity: 'urgent',
    answers: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_unbearable_pain: 'yes' },
    silent: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_unbearable_pain: 'unknown' },
  },
  {
    rule: 'sf_pain_worsening',
    severity: 'prompt',
    answers: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_pain_course: 'worse' },
    silent: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_pain_course: 'stable' },
  },
  {
    rule: 'sf_fever_flank',
    severity: 'urgent',
    answers: { q_pain_present: 'yes', q_pain_location: 'flank_or_back', q_fever_chills: 'yes' },
    // Fever with pain somewhere else is not this rule.
    silent: { q_pain_present: 'yes', q_pain_location: 'right_lower', q_fever_chills: 'yes' },
  },
  {
    rule: 'sf_cannot_keep_fluids',
    severity: 'prompt',
    answers: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_diarrhea_vomiting: ['vomiting'], q_keep_fluids: 'no' },
    silent: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_diarrhea_vomiting: ['vomiting'], q_keep_fluids: 'yes' },
  },
  {
    rule: 'sf_dehydration',
    severity: 'prompt',
    answers: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_diarrhea_vomiting: ['diarrhea'], q_dehydration_signs: ['thirst'] },
    silent: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_diarrhea_vomiting: ['diarrhea'], q_dehydration_signs: ['none'] },
  },
  {
    rule: 'sf_blood_in_stool',
    severity: 'prompt',
    answers: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_diarrhea_vomiting: ['diarrhea'], q_blood_in_stool: 'yes' },
    silent: { q_pain_present: 'yes', q_pain_location: 'other_abdomen', q_diarrhea_vomiting: ['diarrhea'], q_blood_in_stool: 'no' },
  },
  {
    rule: 'sf_pregnancy_out_of_scope',
    severity: 'urgent',
    answers: { q_pain_present: 'yes', q_pregnancy_possible: 'yes' },
    silent: { q_pain_present: 'yes', q_pregnancy_possible: 'no' },
  },
];

function play(answers) {
  const run = session();
  for (const [questionId, value] of Object.entries(answers)) run.answer(questionId, value);
  return run;
}

for (const item of CASES) {
  test(`${item.rule}: fires on its own answers and keeps its severity`, () => {
    const run = play(item.answers);
    const rule = run.safety().find((entry) => entry.id === item.rule);
    assert.ok(rule, `${item.rule} did not fire`);
    assert.equal(rule.severity, item.severity);
    assert.ok(rule.message_fa.trim().length > 10, 'a warning without text is not a warning');
    assert.ok(rule.source_refs.length > 0, 'a warning without a source is not reviewable');
    assert.equal(rule.review_status, 'pending_team_review', 'no rule may claim clinical review');
  });

  test(`${item.rule}: stays silent for the neighbouring answer`, () => {
    const run = play(item.silent);
    assert.ok(!ids(run.safety()).includes(item.rule), `${item.rule} fired when it should not`);
  });
}

test('every safety rule in the bundle is reachable from the matrix', () => {
  const covered = new Set(CASES.map((item) => item.rule));
  const declared = bundle.safety_rules.map((rule) => rule.id);
  assert.deepEqual([...declared].sort(), [...covered].sort(), 'a safety rule exists that no test can trigger');
});

test('two independent warnings can be active at once', () => {
  const run = play({
    q_pain_present: 'yes',
    q_pain_location: 'flank_or_back',
    q_unbearable_pain: 'yes',
    q_fever_chills: 'yes',
  });
  const active = ids(run.safety());
  assert.ok(active.includes('sf_severe_pain'));
  assert.ok(active.includes('sf_fever_flank'));
});

test('warnings survive a safe stop and are not replaced by the stop message', () => {
  const run = play({
    q_pain_present: 'yes',
    q_pain_location: 'flank_or_back',
    q_unbearable_pain: 'yes',
    q_fever_chills: 'yes',
    q_pregnancy_possible: 'yes',
  });
  assert.equal(run.state, 'stopped');
  const result = run.result();
  assert.equal(result.cards_allowed, false);
  assert.deepEqual(ids(result.safety).sort(), ['sf_fever_flank', 'sf_pregnancy_out_of_scope', 'sf_severe_pain']);
  assert.ok(result.safety[0].severity === 'urgent', 'urgent warnings must be listed first');
});

test('urgent warnings come before prompt warnings in the result', () => {
  const run = play({
    q_pain_present: 'yes',
    q_pain_location: 'other_abdomen',
    q_pain_course: 'worse',
    q_unbearable_pain: 'yes',
  });
  const severities = run.result().safety.map((rule) => rule.severity);
  assert.deepEqual(severities, ['urgent', 'prompt']);
});

test('dehydration path: fluids, signs and stool blood are reported together', () => {
  const run = play({
    q_pain_present: 'yes',
    q_pain_location: 'other_abdomen',
    q_pain_onset: 'two_three_days',
    q_pain_course: 'stable',
    q_associated_symptoms: ['diarrhea'],
    q_urinary_symptoms: ['none'],
    q_fever_chills: 'no',
    q_diarrhea_vomiting: ['diarrhea', 'vomiting'],
    q_keep_fluids: 'no',
    q_dehydration_signs: ['dry_mouth', 'little_urine'],
    q_blood_in_stool: 'yes',
    q_pregnancy_possible: 'no',
  });
  const active = ids(run.safety()).sort();
  assert.deepEqual(active, ['sf_blood_in_stool', 'sf_cannot_keep_fluids', 'sf_dehydration']);
  const result = run.result();
  assert.ok(result.cards_allowed);
  assert.ok(result.conditions.some((card) => card.id === 'gastroenteritis'));
});

test('pregnancy answers that stop the flow never produce a card', () => {
  for (const value of ['yes', 'unsure', 'prefer_not']) {
    const run = play({ q_pain_present: 'yes', q_pain_location: 'right_lower', q_pregnancy_possible: value });
    assert.equal(run.state, 'stopped', value);
    assert.equal(run.coverage().result, 'out_of_scope', value);
    assert.equal(run.result().cards_allowed, false, value);
    assert.deepEqual(run.result().conditions, [], value);
  }
});

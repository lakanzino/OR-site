/**
 * The age dial's arithmetic — no DOM, no browser.
 *
 * The gate is the first thing a visitor touches, so it must be boringly
 * predictable: the same drag must always land on the same age, a broken host
 * config must never let a child through, and the number that is shown must be
 * the number that was stored.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
  AGE_DEFAULT,
  AGE_MAX,
  AGE_MIN,
  MIN_AGE_FALLBACK,
  ageAfterDrag,
  ageIsSupported,
  clampAge,
  describeAge,
  formatAge,
  minAgeFrom,
  turnForAge,
} from '../age-gate.js';

test('any input becomes an age inside the dial', () => {
  assert.equal(clampAge(30), 30);
  assert.equal(clampAge('42'), 42);
  assert.equal(clampAge(-5), AGE_MIN);
  assert.equal(clampAge(999), AGE_MAX);
  assert.equal(clampAge(29.6), 30);
  assert.equal(clampAge('abc'), AGE_DEFAULT);
  assert.equal(clampAge(undefined), AGE_DEFAULT);
  assert.equal(clampAge(NaN), AGE_DEFAULT);
});

test('the gear turns with the age and stays put when the age does', () => {
  assert.equal(turnForAge(AGE_MIN), 0);
  assert.ok(turnForAge(30) > turnForAge(20));
  assert.equal(turnForAge(200), turnForAge(AGE_MAX));
  assert.equal(turnForAge('bad'), turnForAge(AGE_DEFAULT));
});

test('a drag is reversible and lands on whole years', () => {
  const start = 220;
  const up = ageAfterDrag(20, start, start + 12);
  assert.equal(up, 21);
  assert.equal(ageAfterDrag(21, start + 12, start), 20, 'dragging back returns the same age');
  assert.equal(ageAfterDrag(20, start, start + 6), 21, 'half a year rounds to the nearer year');
  assert.equal(ageAfterDrag(20, start, start - 12), 19);
  assert.equal(ageAfterDrag(AGE_MIN, start, start - 600), AGE_MIN, 'the range holds at the bottom');
  assert.equal(ageAfterDrag(AGE_MAX, start, start + 600), AGE_MAX, 'the range holds at the top');
});

test('a broken host config cannot lower the adult gate', () => {
  assert.equal(minAgeFrom({ minAge: 18 }), 18);
  assert.equal(minAgeFrom({ minAge: 21 }), 21);
  assert.equal(minAgeFrom({}), MIN_AGE_FALLBACK);
  assert.equal(minAgeFrom(null), MIN_AGE_FALLBACK);
  assert.equal(minAgeFrom({ minAge: 'teen' }), MIN_AGE_FALLBACK);
  assert.equal(minAgeFrom({ minAge: 0 }), MIN_AGE_FALLBACK);
  assert.equal(minAgeFrom({ minAge: -3 }), MIN_AGE_FALLBACK);
  assert.equal(minAgeFrom({ minAge: 3.5 }), MIN_AGE_FALLBACK);
  assert.equal(minAgeFrom({ minAge: 500 }), MIN_AGE_FALLBACK);

  for (const bad of [{ minAge: 0 }, { minAge: -1 }, { minAge: 'x' }, {}]) {
    assert.equal(ageIsSupported(12, minAgeFrom(bad)), false, 'a child never passes a broken config');
    assert.equal(ageIsSupported(18, minAgeFrom(bad)), true);
  }
});

test('«supported» means adult, and only adult', () => {
  assert.equal(ageIsSupported(17, 18), false);
  assert.equal(ageIsSupported(18, 18), true);
  assert.equal(ageIsSupported(19, 18), true);
  assert.equal(ageIsSupported(20, 21), false);
  assert.equal(ageIsSupported(ageAfterDrag(17, 0, 12), 18), true, '17 + one drag step is 18');
});

test('the number is shown in Persian digits and read out with its unit', () => {
  assert.equal(formatAge(30), new Intl.NumberFormat('fa-IR', { useGrouping: false }).format(30));
  assert.equal(formatAge(30), '۳۰');
  assert.equal(formatAge(999), '۱۰۰');
  assert.equal(describeAge(41), '۴۱ سال');
  assert.match(describeAge(7), /سال$/);
});

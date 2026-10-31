/**
 * The age field's arithmetic — no DOM, no browser.
 *
 * The gate is the first thing a visitor touches, so it must be boringly
 * predictable: the same typed digits must always mean the same age, a broken
 * host config must never let a child through, and the number that is shown must
 * be the number that was stored.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
  AGE_DEFAULT,
  AGE_MAX,
  AGE_MIN,
  MIN_AGE_FALLBACK,
  ageFromText,
  ageIsSupported,
  bumpAge,
  clampAge,
  describeAge,
  formatAge,
  minAgeFrom,
} from '../age-gate.js';

test('any input becomes an age inside the field', () => {
  assert.equal(clampAge(30), 30);
  assert.equal(clampAge('42'), 42);
  assert.equal(clampAge(-5), AGE_MIN);
  assert.equal(clampAge(999), AGE_MAX);
  assert.equal(clampAge(29.6), 30);
  assert.equal(clampAge('abc'), AGE_DEFAULT);
  assert.equal(clampAge(undefined), AGE_DEFAULT);
  assert.equal(clampAge(NaN), AGE_DEFAULT);
});

test('typed Persian, Arabic or Latin digits all mean the same year', () => {
  assert.equal(ageFromText('۳۰'), 30);
  assert.equal(ageFromText('30'), 30);
  assert.equal(ageFromText('٣٠'), 30);
  assert.equal(ageFromText(' ۲۵ '), 25);
  assert.equal(ageFromText('٤٥'), 45);
  assert.equal(ageFromText('سن ۴۲'), 42, 'letters around the digits are ignored');
});

test('a field with no digit in it is empty, not silently thirty', () => {
  assert.equal(ageFromText(''), null);
  assert.equal(ageFromText('   '), null);
  assert.equal(ageFromText('سال'), null);
  assert.equal(ageFromText(null), null);
  assert.equal(ageFromText(undefined), null);
});

test('the field cannot leave its travel, whatever is typed', () => {
  assert.equal(ageFromText('-5'), 5, 'a minus sign is not a digit, so it is dropped');
  assert.equal(ageFromText('0'), 1, 'zero is clamped to the first year the tool knows');
  assert.equal(ageFromText('999'), 100);
  assert.equal(ageFromText('۲۹۹'), 100);
});

test('one press of ± moves exactly one year and stays in range', () => {
  assert.equal(bumpAge(30, 1), 31);
  assert.equal(bumpAge(30, -1), 29);
  assert.equal(bumpAge(30, 5), 35);
  assert.equal(bumpAge(1, -1), 1, 'the bottom holds');
  assert.equal(bumpAge(100, 1), 100, 'the top holds');
  assert.equal(bumpAge(30, 'x'), 30);
  assert.equal(bumpAge('31', 1), 32);
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
  assert.equal(ageIsSupported(bumpAge(17, 1), 18), true, '17 + one press is 18');
});

test('the number is shown in Persian digits and read out with its unit', () => {
  assert.equal(formatAge(30), new Intl.NumberFormat('fa-IR', { useGrouping: false }).format(30));
  assert.equal(formatAge(30), '۳۰');
  assert.equal(formatAge(999), '۱۰۰');
  assert.equal(describeAge(41), '۴۱ سال');
  assert.match(describeAge(7), /سال$/);
});

/**
 * Age gate — pure helpers for the first field of the checker.
 *
 * The age is asked before anything else because two decisions depend on it and
 * neither may wait: the bundle covers adults only (`scope.excluded_by_design`
 * names under-18 explicitly), and every later wording decision is written for
 * an adult. The number never leaves the page and never reaches the engine in
 * this phase: it only decides «continue» or «this tool is not built for you».
 *
 * It is a number field, not something to be dragged: a dial that turned with
 * the pointer moved by a random number of years on a phone (the angle jumped
 * whenever the finger crossed the centre), and a random age meant a random
 * answer to «is this tool for you». Typed digits always mean what they say.
 */

export const AGE_MIN = 1;
export const AGE_MAX = 100;
export const AGE_DEFAULT = 30;

/** Used when the host does not say otherwise. Mirrors the bundle's adult scope. */
export const MIN_AGE_FALLBACK = 18;

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

const clampInt = (value, min, max) => Math.min(max, Math.max(min, Math.round(Number(value))));

/** Any input → an integer age inside the field's travel. */
export function clampAge(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return AGE_DEFAULT;
  return clampInt(number, AGE_MIN, AGE_MAX);
}

/**
 * The digits of a typed age, or null when there is no digit at all.
 *
 * Persian (۳۰), Arabic-Indic (٣٠) and Latin (30) digits all mean the same
 * number, and every other character is dropped — a phone keyboard in Persian
 * still produces «۳۰», and nobody should have to think about which one is right.
 */
export function ageFromText(text) {
  const digits = String(text ?? '')
    .replace(/[۰-۹]/g, (digit) => String(PERSIAN_DIGITS.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String(ARABIC_DIGITS.indexOf(digit)))
    .replace(/[^\d]/g, '');
  if (!digits) return null;
  return clampAge(digits);
}

/** One press of ± or one arrow key. Always inside the field's travel. */
export function bumpAge(age, delta) {
  const change = Number(delta);
  return clampAge(clampAge(age) + (Number.isFinite(change) ? change : 0));
}

/** The smallest age the host supports; a broken config can never allow zero. */
export function minAgeFrom(config) {
  const raw = Number(config?.minAge);
  if (!Number.isInteger(raw) || raw < AGE_MIN || raw > AGE_MAX) return MIN_AGE_FALLBACK;
  return raw;
}

/** True when the age is inside what the bundle covers. */
export function ageIsSupported(age, minimum = MIN_AGE_FALLBACK) {
  return clampAge(age) >= clampAge(minimum);
}

/** Persian digits, because every other number on the page is Persian. */
export function formatAge(age) {
  const value = clampAge(age);
  try {
    return new Intl.NumberFormat('fa-IR', { useGrouping: false }).format(value);
  } catch {
    return String(value);
  }
}

/** The text a screen reader hears instead of a bare number. */
export function describeAge(age) {
  return `${formatAge(age)} سال`;
}

/**
 * Age gate — pure helpers for the dial that opens the checker.
 *
 * The age is asked before anything else because two decisions depend on it and
 * neither may wait: the bundle covers adults only (`scope.excluded_by_design`
 * names under-18 explicitly), and every later wording decision is written for
 * an adult. The number never leaves the page and never reaches the engine in
 * this phase: it only decides «continue» or «this tool is not built for you».
 *
 * Keeping the arithmetic here (and not in the DOM code) is what makes the dial
 * testable without a browser: same value in, same angle out, every time.
 */

export const AGE_MIN = 1;
export const AGE_MAX = 100;
export const AGE_DEFAULT = 30;

/** Used when the host does not say otherwise. Mirrors the bundle's adult scope. */
export const MIN_AGE_FALLBACK = 18;

/** Degrees of dial rotation per year of age, when the visitor drags it. */
export const DEGREES_PER_YEAR = 12;

const clampInt = (value, min, max) => Math.min(max, Math.max(min, Math.round(Number(value))));

/** Any input → an integer age inside the dial's travel. */
export function clampAge(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return AGE_DEFAULT;
  return clampInt(number, AGE_MIN, AGE_MAX);
}

/** The smallest age the host supports; a broken config can never allow zero. */
export function minAgeFrom(config) {
  const raw = Number(config?.minAge);
  if (!Number.isInteger(raw) || raw < AGE_MIN || raw > AGE_MAX) return MIN_AGE_FALLBACK;
  return raw;
}

/** How far the gear has turned for this age, in degrees. */
export function turnForAge(age) {
  return (clampAge(age) - AGE_MIN) * DEGREES_PER_YEAR;
}

/**
 * Age after a drag from `startAngle` to `currentAngle`.
 *
 * The caller feeds raw pointer angles (CSS degrees, clockwise from 12 o'clock);
 * a full turn is twelve years on this dial, which is slow enough to hit a single
 * year with a thumb and fast enough to cross the range.
 */
export function ageAfterDrag(startAge, startAngle, currentAngle) {
  const swept = Number(currentAngle) - Number(startAngle);
  if (!Number.isFinite(swept)) return clampAge(startAge);
  return clampAge(Number(startAge) + swept / DEGREES_PER_YEAR);
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

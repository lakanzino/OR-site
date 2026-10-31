/**
 * `profile_match`: how much of a condition's *typical* profile the person has
 * actually reported, expressed as one of three words — never as a number.
 *
 * This is a display grouping for phase 6, not a probability and not a ranking:
 *
 *   strong   most of the typical rows were reported
 *   partial  some of them were
 *   weak     few or none were
 *
 * The boundaries are internal constants; they never leave the engine and the UI
 * only ever renders the word. The field is opt-in (`profileMatch: true`) so that
 * phase 2 changes nothing until the interface is allowed to show it.
 *
 * Only `level: typical` rows count. `possible` rows are not the core picture,
 * `rare` is not evidence of anything, and `not_expected` can never count towards
 * a match. Rows the questionnaire cannot ask about are simply absent from the
 * denominator's *numerator*, which keeps the grouping honest instead of
 * pretending an unasked symptom was denied.
 */

export const PROFILE_MATCH_STRONG = 'strong';
export const PROFILE_MATCH_PARTIAL = 'partial';
export const PROFILE_MATCH_WEAK = 'weak';
export const PROFILE_MATCH_LEVELS = [PROFILE_MATCH_STRONG, PROFILE_MATCH_PARTIAL, PROFILE_MATCH_WEAK];

// Share of the condition's typical rows that must be reported for each group.
const STRONG_FROM = 0.7;
const PARTIAL_FROM = 0.4;

/** @returns {'strong'|'partial'|'weak'|null} null when the condition has no typical rows. */
export function profileMatchLevel(condition, groups) {
  // A v1 bundle has no level at all: every link there was written as a typical
  // picture of the disease, so an absent level counts as `typical`.
  const typical = (condition?.symptom_links ?? []).filter((link) => (link.level ?? 'typical') === 'typical');
  if (typical.length === 0) return null;

  const reported = new Set((groups?.present ?? []).map((row) => row.symptom_id));
  const matched = typical.filter((link) => reported.has(link.symptom_id)).length;
  const share = matched / typical.length;

  if (share >= STRONG_FROM) return PROFILE_MATCH_STRONG;
  if (share >= PARTIAL_FROM) return PROFILE_MATCH_PARTIAL;
  return PROFILE_MATCH_WEAK;
}

/**
 * One entry per condition that has a card; conditions without cards are absent
 * rather than reported as `weak` (that would read as a judgement about an
 * infection nobody asked about).
 */
export function profileMatchFor(conditionIds, conditions, evidence) {
  const match = {};
  for (const conditionId of conditionIds) {
    const level = profileMatchLevel(conditions.get(conditionId), evidence?.[conditionId]);
    if (level) match[conditionId] = level;
  }
  return match;
}

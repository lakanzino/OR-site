/**
 * Adapter: read schema 2.0.0 (disease × symptom matrix) with the v1 engine.
 *
 * The migration is incremental (owner's rule): the v1 shape stays until full
 * equivalence is proven, and the engine reads both forms. This module is the
 * only place that knows the difference, so `engine.js` keeps deriving from the
 * same shape it always did.
 *
 * Mapping rules, all of them data-driven:
 *   symptom id      `abd.pain_rlq` → the id in `legacy_id`, else unchanged
 *   option evidence `evidence[]` on each option → the evidence map the engine
 *                   already validates and consumes
 *   condition       `profile[]` → `symptom_links[]` (order preserved)
 *   `overrides`     → `paths` (the rules a matrix cannot express)
 *
 * `level` (typical|possible|rare|not_expected) is carried by the bundle but not
 * consumed yet: phases 2–4 give it meaning (`profile_match`, adaptive order).
 * Until then every row behaves exactly like the v1 link it came from.
 */

export const V2_SCHEMA_PREFIX = '2.';
export const ENGINE_SCHEMA_VERSION = '1.0.0';

export const isV2Bundle = (bundle) =>
  typeof bundle?.schema_version === 'string' && bundle.schema_version.startsWith(V2_SCHEMA_PREFIX);

/** The id the engine uses for a v2 symptom: its v1 id when it has one. */
export const engineSymptomId = (symptom) => symptom?.legacy_id ?? symptom?.id;

function symptomIdMap(bundle) {
  const map = new Map();
  for (const symptom of bundle.symptoms ?? []) map.set(symptom.id, engineSymptomId(symptom));
  return map;
}

const translateSymptomId = (map, id) => map.get(id) ?? id;

/** v2 bundle → the shape `engine.js` validates and derives from. */
export function bundleToEngineShape(bundle) {
  const ids = symptomIdMap(bundle);
  const questions = (bundle.questions ?? []).map((question) => {
    const copy = { ...question };
    if (Array.isArray(question.options)) {
      copy.options = question.options.map(({ evidence, ...option }) => ({ ...option }));
    }
    return copy;
  });

  return {
    schema_version: ENGINE_SCHEMA_VERSION,
    bundle_id: bundle.bundle_id,
    bundle_version: bundle.bundle_version,
    status: bundle.status,
    locale: bundle.locale,
    created: bundle.created,
    scope: bundle.scope,
    answer_states: bundle.answer_states,
    sources: bundle.sources,
    symptoms: (bundle.symptoms ?? []).map((symptom) => ({
      id: engineSymptomId(symptom),
      label_fa: symptom.label_fa,
      synonyms: symptom.synonyms ?? [],
      category: symptom.category,
    })),
    questions,
    conditions: (bundle.conditions ?? []).map((condition) => ({
      id: condition.id,
      name_fa: condition.name_fa,
      article_slug: condition.article_slug ?? null,
      profile_ref: condition.profile_ref ?? null,
      link_status: condition.link_status ?? null,
      symptom_links: (Array.isArray(condition.profile) ? condition.profile : (condition.symptom_links ?? [])).map(
        (row) => ({
          symptom_id: translateSymptomId(ids, row.symptom_id),
          profile_line: row.profile_line,
          source_refs: row.source_refs ?? [],
        }),
      ),
      red_flag_refs: condition.red_flag_refs ?? [],
      review_status: condition.review_status ?? 'pending_team_review',
    })),
    paths: bundle.overrides ?? [],
    safety_rules: bundle.safety_rules ?? [],
    coverage_rules: bundle.coverage_rules ?? [],
    disclaimers: bundle.disclaimers ?? {},
    change_notes: bundle.change_notes ?? [],
  };
}

/**
 * Rebuild the evidence map from the option-level evidence.
 *
 * Order matters: `buildEvidence` lists the answers that reported a symptom in
 * map order, so questions are walked in `ask_order` and options in bundle order.
 * That reproduces the ordering of `evidence-map.v1.json` exactly (proven by the
 * equivalence gate), which is what keeps `result()` byte-identical.
 */
export function evidenceMapFromBundle(bundle) {
  const ids = symptomIdMap(bundle);
  const questions = [...(bundle.questions ?? [])].sort((a, b) => a.ask_order - b.ask_order);
  const entries = [];
  for (const question of questions) {
    for (const option of question.options ?? []) {
      for (const item of option.evidence ?? []) {
        entries.push({
          question_id: question.id,
          option_id: option.id,
          symptom_id: translateSymptomId(ids, item.symptom_id),
          state: item.state,
        });
      }
    }
  }
  return {
    map_version: '1.0.0',
    bundle_id: bundle.bundle_id,
    bundle_version: bundle.bundle_version,
    description:
      'ساخته‌شده از evidence هر گزینه در بستهٔ v2؛ همان نگاشت بیرونی v1، بدون تغییر محتوا.',
    entries,
  };
}

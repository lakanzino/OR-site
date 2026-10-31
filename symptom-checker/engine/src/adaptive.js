/**
 * Adaptive question selection (phase 4) — computed in the shadow only.
 *
 * The rule from the runbook is narrow on purpose:
 *
 *   1. candidates are the conditions of the bundle — an answer never removes a
 *      candidate, it only makes it look less like the person;
 *   2. the next question is the one that separates the remaining candidates
 *      most, counted over candidate *pairs* with a uniform prior — no
 *      probability, no percentage, no score ever leaves this module;
 *   3. the safety net every visitor sees first (the questions the bundle marks
 *      `visibility.always`, plus the scope gate) is asked before anything chosen
 *      for separation; a safety question that a *secondary* branch reveals is
 *      preferred on a tie and never skipped, but it may not push the chief
 *      complaint aside (measured in phase 4: a side symptom otherwise hijacks
 *      the questionnaire — see `docs/symptom-checker/phase-4-adaptive/`);
 *   4. «نمی‌دانم» changes nothing, because it maps to no evidence at all;
 *   5. ties break on `ask_order`, then on the question id, so the sequence is
 *      reproducible — no `Math.random()` anywhere in this file.
 *
 * What this module does *not* do: it does not decide what the visitor sees. The
 * engine still asks `static_order` unless a host explicitly configures
 * otherwise, and phase 4 ships the adaptive order as a shadow trace only.
 */

export const SAFETY_ALWAYS_FIRST = 'always_first';
export const SAFETY_BASE_SCREEN = 'base_screen';

export const SEPARATION = 'separation';
export const SAFETY_FIRST = 'safety_first';
export const ORDER_FALLBACK = 'order_fallback';

const asArray = (value) => (Array.isArray(value) ? value : []);
const LEVELS = ['typical', 'possible', 'rare', 'not_expected'];

/** Every `{question_id, answers}` leaf of a clause tree, at any depth. */
function leavesOf(clauses) {
  const leaves = [];
  const walk = (clause) => {
    if (!clause || typeof clause !== 'object') return;
    if (typeof clause.question_id === 'string') {
      leaves.push({ question_id: clause.question_id, answers: asArray(clause.answers) });
    }
    for (const sub of asArray(clause.any_of)) walk(sub);
  };
  asArray(clauses).forEach(walk);
  return leaves;
}

/** Question ids that can fire a safety rule or the scope gate, in bundle order. */
export function safetyQuestionIds(bundle) {
  const ids = new Set();

  const collect = (clause) => {
    if (!clause || typeof clause !== 'object') return;
    if (typeof clause.question_id === 'string') ids.add(clause.question_id);
    for (const sub of asArray(clause.any_of)) collect(sub);
  };

  for (const rule of asArray(bundle?.safety_rules)) asArray(rule.when).forEach(collect);
  for (const rule of asArray(bundle?.coverage_rules)) {
    // Only the rules that stop the session: a coverage rule that merely says
    // "not enough information yet" has no safety deadline of its own.
    if (rule.result === 'out_of_scope') asArray(rule.when).forEach(collect);
  }
  return ids;
}

/**
 * Everything the selector reads, built once per session. Read-only by contract:
 * `strategyState()` hands it to strategies without a copy, and the engine never
 * mutates it.
 */
export function buildKnowledge(bundle, evidenceEntries = []) {
  const conditions = asArray(bundle?.conditions).map((condition) => ({
    id: condition.id,
    rows: asArray(condition.symptom_links).map((link) => ({
      symptom_id: link.symptom_id,
      level: LEVELS.includes(link.level) ? link.level : 'typical',
    })),
  }));

  // `question_id|option_id` → the symptoms that option reports, with state.
  const evidenceByOption = new Map();
  for (const entry of asArray(evidenceEntries)) {
    const key = `${entry.question_id}|${entry.option_id}`;
    if (!evidenceByOption.has(key)) evidenceByOption.set(key, []);
    evidenceByOption.get(key).push({ symptom_id: entry.symptom_id, state: entry.state });
  }

  const evidenceBySymptom = new Map();
  for (const entry of asArray(evidenceEntries)) {
    if (!evidenceBySymptom.has(entry.symptom_id)) evidenceBySymptom.set(entry.symptom_id, []);
    evidenceBySymptom.get(entry.symptom_id).push(entry);
  }

  const askOrder = new Map(asArray(bundle?.questions).map((question) => [question.id, question.ask_order]));
  // The questions every visitor sees first, before any answer narrows anything.
  const baseScreen = new Set(
    asArray(bundle?.questions).filter((question) => question?.visibility?.always).map((question) => question.id),
  );

  // Documented paths: the rules that actually open a card. A question that can
  // satisfy one of them separates two candidates *by the bundle's own logic*,
  // which a profile comparison alone cannot see.
  const paths = asArray(bundle?.paths).map((path) => ({
    id: path.id,
    condition_id: path.condition_id,
    leaves: leavesOf(path.when),
  }));

  return Object.freeze({
    conditions,
    evidenceByOption,
    evidenceBySymptom,
    askOrder,
    baseScreen,
    paths,
    safetyQuestions: safetyQuestionIds(bundle),
  });
}

const matches = (raw, optionId) => {
  if (raw === undefined) return false;
  return Array.isArray(raw) ? raw.includes(optionId) : raw === optionId;
};

/** What the person has said about each symptom so far: reported / denied. */
function currentSets(knowledge, answers) {
  const present = new Set();
  const absent = new Set();
  for (const [symptomId, entries] of knowledge.evidenceBySymptom) {
    for (const entry of entries) {
      if (!matches(answers[entry.question_id], entry.option_id)) continue;
      (entry.state === 'present' ? present : absent).add(symptomId);
    }
  }
  return { present, absent };
}

/**
 * The picture of one condition as five counts. Never shown, never compared with
 * a threshold — only subtracted from another condition's counts so that two
 * candidates can be told apart.
 */
function effect(condition, sets, added) {
  const present = added ? new Set([...sets.present, ...added.present]) : sets.present;
  const absent = added ? new Set([...sets.absent, ...added.absent]) : sets.absent;
  const counts = { typical_present: 0, typical_absent: 0, possible_present: 0, not_expected_present: 0, not_expected_absent: 0 };
  for (const row of condition.rows) {
    const has = present.has(row.symptom_id);
    const denied = absent.has(row.symptom_id);
    if (!has && !denied) continue;
    if (row.level === 'typical') has ? (counts.typical_present += 1) : (counts.typical_absent += 1);
    else if (row.level === 'possible') has ? (counts.possible_present += 1) : null;
    else if (row.level === 'not_expected') {
      if (has) counts.not_expected_present += 1;
      else counts.not_expected_absent += 1;
    }
  }
  return counts;
}

const componentKeys = ['typical_present', 'typical_absent', 'possible_present', 'not_expected_present', 'not_expected_absent'];

/** Difference of two effect counts, as a stable string. */
function delta(a, b) {
  return componentKeys.map((key) => a[key] - b[key]).join(',');
}

function optionEffect(knowledge, questionId, optionId) {
  const entries = knowledge.evidenceByOption.get(`${questionId}|${optionId}`) ?? [];
  const present = entries.filter((entry) => entry.state === 'present').map((entry) => entry.symptom_id);
  const absent = entries.filter((entry) => entry.state === 'absent').map((entry) => entry.symptom_id);
  return { present, absent };
}

/**
 * How many candidate pairs this question can tell apart.
 *
 * A pair is separable when two different answers to the question change their
 * *relative* picture. With a uniform prior that pair count is the whole
 * criterion; nothing is weighted and nothing is normalised.
 */
export function separationOf(knowledge, question, answers, openConditions = []) {
  const sets = currentSets(knowledge, answers);
  const options = asArray(question.options).map((option) => optionEffect(knowledge, question.id, option.id));
  // «نمی‌دانم» is always a possible answer and it changes nothing, so it is the
  // neutral element of the same comparison.
  const effects = [...options, { present: [], absent: [] }];

  const base = new Map(knowledge.conditions.map((condition) => [condition.id, effect(condition, sets, null)]));
  let pairs = 0;
  let touched = 0;

  for (let i = 0; i < knowledge.conditions.length; i += 1) {
    for (let j = i + 1; j < knowledge.conditions.length; j += 1) {
      const a = knowledge.conditions[i];
      const b = knowledge.conditions[j];
      const signatures = new Set();
      for (const add of effects) {
        const ea = effect(a, sets, add);
        const eb = effect(b, sets, add);
        signatures.add(delta(ea, eb));
      }
      // Two signatures are already the "no change" one plus one answer that
      // moves them apart; more than that means several answers separate them.
      if (signatures.size > 1) pairs += 1;
    }
  }

  for (const condition of knowledge.conditions) {
    const before = base.get(condition.id);
    for (const add of effects) {
      const after = effect(condition, sets, add);
      if (delta(after, before) !== '0,0,0,0,0') {
        touched += 1;
        break;
      }
    }
  }

  // Path separation: the options that could satisfy a documented path of a
  // candidate that has no card yet. Two candidates are separated when those
  // option sets differ — the bundle says one of them can be opened by this
  // question and the other cannot.
  const open = new Set(openConditions);
  const pathOptions = (conditionId) => {
    if (open.has(conditionId)) return '';
    const ids = new Set();
    for (const path of knowledge.paths) {
      if (path.condition_id !== conditionId) continue;
      for (const leaf of path.leaves) {
        if (leaf.question_id !== question.id) continue;
        for (const answer of leaf.answers) ids.add(answer);
      }
    }
    return [...ids].sort().join('|');
  };

  let pathPairs = 0;
  for (let i = 0; i < knowledge.conditions.length; i += 1) {
    for (let j = i + 1; j < knowledge.conditions.length; j += 1) {
      const a = knowledge.conditions[i].id;
      const b = knowledge.conditions[j].id;
      if (open.has(a) && open.has(b)) continue;
      if (pathOptions(a) !== pathOptions(b)) pathPairs += 1;
    }
  }

  return { pairs, path_pairs: pathPairs, total: pairs + pathPairs, touched };
}

const orderOf = (knowledge, question) => knowledge.askOrder.get(question.id) ?? 0;

/**
 * The phase-4 decision, as a pure function of the visible state.
 *
 * @returns {{question: object|null, reason: {rule: string, pairs?: number, touched?: number}}}
 */
export function chooseAdaptive(
  knowledge,
  { answers, eligible, open = [], safetyPolicy = SAFETY_BASE_SCREEN },
) {
  const candidates = asArray(eligible);
  if (candidates.length === 0) return { question: null, reason: { rule: ORDER_FALLBACK } };

  const safetyAll = candidates.filter((question) => knowledge.safetyQuestions.has(question.id));
  const safety =
    safetyPolicy === SAFETY_BASE_SCREEN
      ? safetyAll.filter(
          // Only the safety net of the base screen keeps absolute priority; a
          // safety question that a *secondary* branch revealed waits its turn,
          // otherwise a side symptom hijacks the chief complaint.
          (question) => knowledge.baseScreen.has(question.id),
        )
      : safetyAll;
  safety.sort((a, b) => orderOf(knowledge, a) - orderOf(knowledge, b) || a.id.localeCompare(b.id));

  if (safety.length > 0) {
    return { question: safety[0], reason: { rule: SAFETY_FIRST, pending_safety: safety.length } };
  }

  const safetyRank = (question) => (question && knowledge.safetyQuestions.has(question.id) ? 1 : 0);

  let best = null;
  let bestScore = null;
  for (const question of candidates) {
    const score = separationOf(knowledge, question, answers, open);
    const better =
      best === null ||
      score.total > bestScore.total ||
      (score.total === bestScore.total && safetyRank(question) > safetyRank(best)) ||
      (score.total === bestScore.total &&
        safetyRank(question) === safetyRank(best) &&
        score.touched > bestScore.touched) ||
      (score.total === bestScore.total &&
        score.touched === bestScore.touched &&
        orderOf(knowledge, question) < orderOf(knowledge, best)) ||
      (score.total === bestScore.total &&
        score.touched === bestScore.touched &&
        orderOf(knowledge, question) === orderOf(knowledge, best) &&
        question.id.localeCompare(best.id) < 0);
    if (better) {
      best = question;
      bestScore = score;
    }
  }

  if (bestScore.total === 0) {
    return { question: best, reason: { rule: ORDER_FALLBACK, pairs: 0, touched: bestScore.touched } };
  }
  return {
    question: best,
    reason: { rule: SEPARATION, pairs: bestScore.pairs, path_pairs: bestScore.path_pairs, touched: bestScore.touched },
  };
}

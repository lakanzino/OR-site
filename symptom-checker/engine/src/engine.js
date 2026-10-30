/**
 * Symptom-checker engine — phase 2.
 *
 * Pure JavaScript, no WordPress, no network, no DOM. The engine only consumes a
 * validated knowledge bundle (plus an optional evidence map) and a sequence of
 * user answers. It never scores, never ranks and never computes a probability;
 * it opens documented paths, raises documented safety rules, and refuses to show
 * cards when the evidence is not sufficient.
 *
 * Contract (see docs/symptom-checker/phase-2-report.fa.md):
 *   - unanswered questions are "not asked", never "no";
 *   - "unknown" satisfies nothing that requires a positive answer;
 *   - branches are parallel: opening one never closes another;
 *   - safety rules are independent of the result cards;
 *   - out_of_scope stops the questioning and suppresses every card.
 */

import {
  AnswerValidationError,
  BundleValidationError,
  BundleVersionError,
  QuestionNotVisibleError,
  SessionStoppedError,
  UnknownQuestionError,
} from './errors.js';
import { SUPPORTED_SCHEMA_VERSION, collectBundleIssues } from './validate-bundle.js';
import { buildEvidence, validateEvidenceMap } from './evidence.js';

const asArray = (value) => (Array.isArray(value) ? value : []);
const clone = (value) => JSON.parse(JSON.stringify(value));

function questionIsVisible(question, answers) {
  const visibility = question.visibility ?? {};
  if (visibility.always) return true;
  return asArray(visibility.any_of).some((rule) => {
    const given = answers[rule.question_id];
    if (given === undefined) return false;
    const list = Array.isArray(given) ? given : [given];
    return asArray(rule.answers).some((answer) => list.includes(answer));
  });
}

function clauseMatches(clause, answers, branchCount) {
  if ('branches_open_min' in clause) return branchCount >= Number(clause.branches_open_min);
  if ('branches_open_max' in clause) return branchCount <= Number(clause.branches_open_max);

  if (Array.isArray(clause.any_of)) {
    return clause.any_of.some((sub) => clauseMatches(sub, answers, branchCount));
  }

  const given = answers[clause.question_id];
  if (given === undefined) return false;
  const list = Array.isArray(given) ? given : [given];
  return asArray(clause.answers).some((answer) => list.includes(answer));
}

const clausesMatch = (clauses, answers, branchCount) =>
  asArray(clauses).every((clause) => clauseMatches(clause, answers, branchCount));

function validateAnswer(question, value) {
  const type = question.type;
  const options = asArray(question.options);
  const optionIds = new Set(options.map((option) => option.id));

  if (type === 'scale') {
    if (value === 'unknown') {
      if (!question.allow_unknown) {
        throw new AnswerValidationError(question.id, 'unknown is not allowed');
      }
      return 'unknown';
    }
    if (!Number.isInteger(value)) {
      throw new AnswerValidationError(question.id, 'scale answers must be integers');
    }
    if (value < question.min || value > question.max) {
      throw new AnswerValidationError(
        question.id,
        `out of range ${question.min}..${question.max}`,
        { value },
      );
    }
    return value;
  }

  if (type === 'multi') {
    if (!Array.isArray(value) || value.length === 0) {
      throw new AnswerValidationError(question.id, 'multi answers need a non-empty array');
    }
    const unique = new Set(value);
    if (unique.size !== value.length) {
      throw new AnswerValidationError(question.id, 'duplicate options in answer');
    }
    for (const item of unique) {
      if (!optionIds.has(item)) {
        throw new AnswerValidationError(question.id, `unknown option "${item}"`);
      }
    }
    const exclusive = options.filter((option) => option.exclusive).map((option) => option.id);
    if (value.length > 1 && exclusive.some((id) => unique.has(id))) {
      throw new AnswerValidationError(
        question.id,
        'an exclusive option cannot be combined with other options',
      );
    }
    // Keep the bundle's option order so results are deterministic.
    return options.map((option) => option.id).filter((id) => unique.has(id));
  }

  if (typeof value !== 'string' || !optionIds.has(value)) {
    throw new AnswerValidationError(question.id, `unknown option "${String(value)}"`);
  }
  return value;
}

export function createSession(bundle, { evidenceMap = null } = {}) {
  if (bundle?.schema_version !== SUPPORTED_SCHEMA_VERSION) {
    throw new BundleVersionError(bundle?.schema_version, SUPPORTED_SCHEMA_VERSION);
  }
  const issues = collectBundleIssues(bundle);
  if (issues.length) throw new BundleValidationError(issues);

  if (evidenceMap) {
    const mapIssues = validateEvidenceMap(bundle, evidenceMap);
    if (mapIssues.length) throw new BundleValidationError(mapIssues);
  }

  const questions = asArray(bundle.questions).slice().sort((a, b) => a.ask_order - b.ask_order);
  const questionById = new Map(questions.map((question) => [question.id, question]));
  const conditions = new Map(asArray(bundle.conditions).map((item) => [item.id, item]));

  let answers = {};
  let inactive = {};
  let history = [];

  // --- derivation -------------------------------------------------------
  function matchedPaths(activeAnswers) {
    const map = new Map();
    for (const path of asArray(bundle.paths)) {
      if (!clausesMatch(path.when, activeAnswers, 0)) continue;
      if (!map.has(path.condition_id)) map.set(path.condition_id, []);
      map.get(path.condition_id).push({
        path_id: path.id,
        note_fa: path.note_fa,
        source_refs: clone(asArray(path.source_refs)),
      });
    }
    return map;
  }

  function matchedSafety(activeAnswers, branchCount) {
    return asArray(bundle.safety_rules)
      .filter((rule) => clausesMatch(rule.when, activeAnswers, branchCount))
      .map((rule) => ({
        id: rule.id,
        severity: rule.severity,
        message_fa: rule.message_fa,
        source_refs: clone(asArray(rule.source_refs)),
        review_status: rule.review_status ?? 'pending_team_review',
      }));
  }

  function resolveCoverage(activeAnswers, branchCount) {
    for (const rule of asArray(bundle.coverage_rules)) {
      if (clausesMatch(rule.when, activeAnswers, branchCount)) {
        return { result: rule.result, rule_id: rule.id, message_fa: rule.message_fa };
      }
    }
    return {
      result: 'insufficient',
      rule_id: null,
      message_fa: 'اطلاعات کافی برای نمایش موضوعی وجود ندارد.',
    };
  }

  /**
   * Answers to questions that are no longer visible must not influence anything.
   * They are kept in `inactive` (for debugging and for restoring on undo) but the
   * derivation only ever reads `answers`.
   */
  function pruneInvisible() {
    for (let pass = 0; pass < questions.length; pass += 1) {
      let changed = false;
      for (const question of questions) {
        if (answers[question.id] === undefined) continue;
        if (!questionIsVisible(question, answers)) {
          inactive[question.id] = answers[question.id];
          delete answers[question.id];
          changed = true;
        }
      }
      if (!changed) break;
    }
  }

  function derive() {
    pruneInvisible();
    const paths = matchedPaths(answers);
    const branchCount = paths.size;
    const safety = matchedSafety(answers, branchCount);
    const coverage = resolveCoverage(answers, branchCount);
    const stopped = coverage.result === 'out_of_scope';
    return { paths, branchCount, safety, coverage, stopped };
  }

  function pushHistory() {
    history.push({ answers: clone(answers), inactive: clone(inactive) });
    if (history.length > 50) history.shift();
  }

  // --- public API -------------------------------------------------------
  const session = {
    bundleId: bundle.bundle_id,
    bundleVersion: bundle.bundle_version,
    schemaVersion: bundle.schema_version,
    hasEvidenceMap: Boolean(evidenceMap),

    get state() {
      return derive().stopped ? 'stopped' : 'asking';
    },

    answers() {
      return clone(answers);
    },

    inactiveAnswers() {
      return clone(inactive);
    },

    question(id) {
      const question = questionById.get(id);
      if (!question) throw new UnknownQuestionError(id);
      return clone(question);
    },

    visibleQuestions() {
      return questions
        .filter((question) => questionIsVisible(question, answers))
        .map((question) => clone(question));
    },

    /** Next unanswered, visible question in ask_order; null when finished or stopped. */
    nextQuestion() {
      const derived = derive();
      if (derived.stopped) return null;
      const question = questions.find(
        (item) => questionIsVisible(item, answers) && answers[item.id] === undefined,
      );
      return question ? clone(question) : null;
    },

    canAnswer(id) {
      const question = questionById.get(id);
      if (!question) return false;
      return questionIsVisible(question, answers);
    },

    answer(id, value) {
      const question = questionById.get(id);
      if (!question) throw new UnknownQuestionError(id);

      const derived = derive();
      const alreadyAnswered = answers[id] !== undefined;
      if (derived.stopped && !alreadyAnswered) {
        throw new SessionStoppedError(derived.coverage.rule_id ?? 'out_of_scope');
      }
      if (!questionIsVisible(question, answers)) {
        throw new QuestionNotVisibleError(id);
      }

      const normalised = validateAnswer(question, value);
      pushHistory();
      answers[id] = normalised;
      delete inactive[id];
      return session.result();
    },

    clearAnswer(id) {
      if (answers[id] === undefined) return session.result();
      pushHistory();
      delete answers[id];
      delete inactive[id];
      return session.result();
    },

    canUndo() {
      return history.length > 0;
    },

    undo() {
      const previous = history.pop();
      if (!previous) return false;
      answers = previous.answers;
      inactive = previous.inactive;
      return true;
    },

    /** Open branches with their documented reasons (order = bundle order). */
    branches() {
      const derived = derive();
      return [...derived.paths.entries()].map(([conditionId, reasons]) => ({
        id: conditionId,
        name_fa: conditions.get(conditionId)?.name_fa ?? conditionId,
        article_slug: conditions.get(conditionId)?.article_slug ?? null,
        link_status: conditions.get(conditionId)?.link_status ?? 'unmapped',
        reasons,
      }));
    },

    safety() {
      return derive().safety;
    },

    coverage() {
      return derive().coverage;
    },

    evidence() {
      const built = buildEvidence(bundle, evidenceMap, answers);
      return Object.fromEntries([...built.entries()].map(([id, groups]) => [id, clone(groups)]));
    },

    /**
     * The only shape a UI may render. Cards exist only when coverage is
     * sufficient; safety warnings are always returned, even when no card is shown.
     */
    result() {
      const derived = derive();
      const cardsAllowed = derived.coverage.result === 'sufficient';
      const evidence = cardsAllowed ? session.evidence() : {};

      return {
        state: derived.stopped ? 'stopped' : 'asking',
        coverage: derived.coverage,
        cards_allowed: cardsAllowed,
        conditions: cardsAllowed
          ? [...derived.paths.keys()].map((conditionId) => ({
              id: conditionId,
              name_fa: conditions.get(conditionId)?.name_fa ?? conditionId,
              article_slug: conditions.get(conditionId)?.article_slug ?? null,
              link_status: conditions.get(conditionId)?.link_status ?? 'unmapped',
              reasons: derived.paths.get(conditionId),
              evidence: evidence[conditionId] ?? null,
            }))
          : [],
        safety: derived.safety,
        answered: Object.keys(answers).length,
        remaining: cardsAllowed
          ? questions.filter(
              (item) => questionIsVisible(item, answers) && answers[item.id] === undefined,
            ).length
          : 0,
        disclaimers: clone(bundle.disclaimers ?? {}),
        bundle_version: bundle.bundle_version,
      };
    },

    snapshot() {
      const derived = derive();
      return {
        bundle_id: bundle.bundle_id,
        bundle_version: bundle.bundle_version,
        answers: clone(answers),
        inactive: clone(inactive),
        coverage: derived.coverage,
        cards_allowed: derived.coverage.result === 'sufficient',
        branches: [...derived.paths.keys()],
        safety: derived.safety.map((rule) => rule.id),
        can_undo: history.length > 0,
      };
    },
  };

  return session;
}

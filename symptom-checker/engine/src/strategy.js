/**
 * Question-selection strategies.
 *
 * Phase 2 of the migration makes the order injectable without changing it: the
 * default (`static_order`) is exactly what the engine always did — first visible,
 * unanswered question in `ask_order`. Phase 4 fills in `adaptive`; until then the
 * skeleton walks the same order, so a session created with it behaves the same.
 *
 * A strategy is a pure function of the visible state:
 *
 *   (state) => question | questionId | null
 *
 * with `state` holding a read-only copy of the answers, the derived state, the
 * questions that may be asked right now (in `ask_order`) and `nextInOrder()`.
 * The engine re-checks whatever comes back: a strategy can re-order questions,
 * never invent one, reveal a hidden one or re-ask an answered one.
 *
 * The strategy is chosen by configuration only (`questionStrategy` in the
 * `createSession` options, or the WordPress filter that feeds it) — never by the
 * page, and never by an answer.
 */

import { chooseAdaptive } from './adaptive.js';
import { QuestionStrategyError } from './errors.js';

export const STATIC_ORDER = 'static_order';
export const ADAPTIVE = 'adaptive';
export const CUSTOM = 'custom';

/**
 * The order the engine has always used: `ask_order`, first match wins.
 * Everything else in phase 2 must not move a single question, so this is the
 * default and the reference implementation.
 */
const staticOrder = (state) => state.nextInOrder();

/**
 * Phase 4: the adaptive order. It is a real strategy now, but nothing switches
 * to it by itself — the page still configures `static_order`, and the shadow
 * trace (see `engine.js`) records what this would have asked *without* asking
 * it. The switch criterion lives in `docs/symptom-checker/phase-4-adaptive/`.
 */
const adaptive = (state) => chooseAdaptive(state.knowledge, state).question;

export const BUILT_IN_STRATEGIES = [STATIC_ORDER, ADAPTIVE];

const registry = new Map([
  [STATIC_ORDER, staticOrder],
  [ADAPTIVE, adaptive],
]);

/** Register or replace a strategy. Used by tests and by later phases. */
export function registerStrategy(name, select) {
  if (typeof name !== 'string' || !name) {
    throw new QuestionStrategyError(String(name), 'strategy name must be a non-empty string');
  }
  if (typeof select !== 'function') {
    throw new QuestionStrategyError(name, 'strategy must be a function');
  }
  registry.set(name, select);
}

/** @returns {{name: string, select: Function}} */
export function resolveStrategy(questionStrategy) {
  if (typeof questionStrategy === 'function') {
    return { name: CUSTOM, select: questionStrategy };
  }
  if (questionStrategy === undefined || questionStrategy === null) {
    return { name: STATIC_ORDER, select: registry.get(STATIC_ORDER) };
  }
  const select = registry.get(questionStrategy);
  if (!select) {
    throw new QuestionStrategyError(
      String(questionStrategy),
      `unknown strategy; known: ${[...registry.keys()].join(', ')}`,
    );
  }
  return { name: questionStrategy, select };
}

/** The state a strategy receives. Copies only — a strategy cannot mutate a session. */
export function strategyState({ answers, derived, eligible, clone, knowledge = null }) {
  return {
    answers: clone(answers),
    derived: clone({
      stopped: derived.stopped,
      branchCount: derived.branchCount,
      coverage: derived.coverage,
      safety: derived.safety,
      // Conditions that already have a card: their documented paths are spent,
      // so the adaptive order stops counting them as separations to win.
      open: [...derived.paths.keys()],
    }),
    eligible: eligible.map((question) => clone(question)),
    // Read-only model of the bundle, for strategies that need to compare
    // candidates. Never copied: a strategy may read it, never write it.
    knowledge,
    asked: Object.keys(answers),
    nextInOrder: () => (eligible[0] ? clone(eligible[0]) : null),
  };
}

/**
 * Typed errors for the symptom-checker engine.
 *
 * Every failure carries a stable `code` so the (future) UI can show a safe stop
 * message instead of guessing, and so tests can assert on the reason.
 */

export class SymptomEngineError extends Error {
  constructor(code, message, details = {}) {
    super(message);
    this.name = 'SymptomEngineError';
    this.code = code;
    this.details = details;
  }
}

/** Bundle is structurally broken — the engine must not run on it. */
export class BundleValidationError extends SymptomEngineError {
  constructor(issues) {
    super('BUNDLE_INVALID', 'The knowledge bundle is invalid or incomplete.', { issues });
    this.name = 'BundleValidationError';
    this.issues = issues;
  }
}

/** Bundle schema version is not supported by this engine build. */
export class BundleVersionError extends SymptomEngineError {
  constructor(found, supported) {
    super('BUNDLE_VERSION_UNSUPPORTED', `Unsupported bundle schema version: ${found}`, {
      found,
      supported,
    });
    this.name = 'BundleVersionError';
  }
}

/** Caller sent an answer that does not match the question definition. */
export class AnswerValidationError extends SymptomEngineError {
  constructor(questionId, reason, details = {}) {
    super('ANSWER_INVALID', `Invalid answer for ${questionId}: ${reason}`, {
      questionId,
      reason,
      ...details,
    });
    this.name = 'AnswerValidationError';
  }
}

/** Question id does not exist in the bundle. */
export class UnknownQuestionError extends SymptomEngineError {
  constructor(questionId) {
    super('QUESTION_NOT_FOUND', `Unknown question: ${questionId}`, { questionId });
    this.name = 'UnknownQuestionError';
  }
}

/** Question exists but its visibility rule is not satisfied. */
export class QuestionNotVisibleError extends SymptomEngineError {
  constructor(questionId) {
    super('QUESTION_NOT_VISIBLE', `Question is not visible with the current answers: ${questionId}`, {
      questionId,
    });
    this.name = 'QuestionNotVisibleError';
  }
}

/** The session reached a safe stop (outside coverage) and no more input is expected. */
export class SessionStoppedError extends SymptomEngineError {
  constructor(reason) {
    super('SESSION_STOPPED', `Session is stopped: ${reason}`, { reason });
    this.name = 'SessionStoppedError';
  }
}

/**
 * The question-selection strategy asked for does not exist, or returned
 * something the engine must never ask (unknown id, hidden question, a question
 * that was already answered). A strategy is a hook, not a way around the
 * bundle's own rules.
 */
export class QuestionStrategyError extends SymptomEngineError {
  constructor(strategy, reason, details = {}) {
    super('QUESTION_STRATEGY_INVALID', `Invalid question strategy (${strategy}): ${reason}`, {
      strategy,
      reason,
      ...details,
    });
    this.name = 'QuestionStrategyError';
  }
}

/** The loader was handed something that is not a bundle object at all. */
export class BundleFormatError extends SymptomEngineError {
  constructor(received) {
    super('BUNDLE_FORMAT_INVALID', `A knowledge bundle object was expected, got ${received}`, {
      received,
    });
    this.name = 'BundleFormatError';
  }
}

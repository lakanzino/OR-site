/**
 * Pure view helpers for the phase-3 demo UI.
 *
 * No DOM here: everything is a plain function over the engine result, so it can
 * be unit-tested with `node --test` and so the demo cannot quietly invent a
 * probability, a score or a ranking.
 */

export const EVIDENCE_GROUPS = [
  { key: 'present', label: 'گزارش‌شده', hint: 'نشانه‌ای که شما گفتید' },
  { key: 'conflict', label: 'پاسخ متناقض', hint: 'دو پاسخ شما با هم نمی‌خواند' },
  { key: 'absent', label: 'گزارش‌نشده', hint: 'شما گفتید این نشانه را ندارید' },
  { key: 'unknown', label: 'نامعلوم', hint: 'شما گفتید مطمئن نیستید' },
  { key: 'not_reported', label: 'انتخاب‌نشده', hint: 'به این مورد پاسخ داده‌اید، ولی این نشانه را انتخاب نکرده‌اید' },
  { key: 'not_asked', label: 'پرسیده‌نشده', hint: 'این پرسش برای شما نمایش داده نشد' },
  { key: 'not_covered', label: 'خارج از پرسش‌نامه', hint: 'این نشانه در این نسخه پرسیده نمی‌شود؛ یعنی «ندارد» نیست' },
];

const SEVERITY_ORDER = { urgent: 0, prompt: 1, info: 2 };
const DIGITS = '۰۱۲۳۴۵۶۷۸۹';

/** Persian digits for anything the person reads. */
export function faDigits(value) {
  return String(value).replace(/[0-9]/g, (digit) => DIGITS[Number(digit)]);
}

export function evidenceGroups(evidence) {
  if (!evidence) return [];
  return EVIDENCE_GROUPS.map((group) => ({
    ...group,
    items: Array.isArray(evidence[group.key]) ? evidence[group.key] : [],
  }));
}

export function sortSafety(rules = []) {
  return [...rules].sort(
    (a, b) => (SEVERITY_ORDER[a.severity] ?? 9) - (SEVERITY_ORDER[b.severity] ?? 9),
  );
}

/**
 * Only absolute http(s) links survive. Everything else (javascript:, data:,
 * relative, empty) is dropped so a bad mapping can never inject a link.
 */
export function safeHttpUrl(value) {
  if (typeof value !== 'string') return '';
  const trimmed = value.trim();
  return /^https?:\/\/[^\s]+$/i.test(trimmed) ? trimmed : '';
}

/**
 * @param {object} result engine `session.result()`
 * @param {{topicLinks?: Record<string, string>}} [options] condition id → published article URL
 * @returns {{showCards: boolean, coverage: object, cards: Array, safety: Array, emptyMessage: string, disclaimer: string}}
 */
export function buildResultView(result, options = {}) {
  const topicLinks = options.topicLinks ?? {};
  const showCards = Boolean(result?.cards_allowed) && Array.isArray(result?.conditions) && result.conditions.length > 0;

  return {
    showCards,
    coverage: result?.coverage ?? { result: 'insufficient', message_fa: '' },
    safety: sortSafety(result?.safety ?? []),
    cards: showCards
      ? result.conditions.map((card) => ({
          id: card.id,
          name_fa: card.name_fa,
          article_slug: card.article_slug,
          link_status: card.link_status,
          link: safeHttpUrl(topicLinks[card.id]),
          reasons: card.reasons ?? [],
          groups: evidenceGroups(card.evidence).filter((group) => group.items.length > 0),
        }))
      : [],
    emptyMessage: showCards ? '' : (result?.coverage?.message_fa ?? ''),
    disclaimer: result?.disclaimers?.result_fa ?? '',
  };
}

export function buildProgress(answered = 0, remaining = 0) {
  const total = Math.max(0, answered + remaining);
  const percent = total === 0 ? 0 : Math.round((answered / total) * 100);
  return {
    answered,
    remaining,
    total,
    percent: Math.min(100, Math.max(0, percent)),
    label: total === 0 ? 'پرسشی برای نمایش نیست' : `پاسخ‌داده‌شده ${faDigits(answered)} از ${faDigits(total)}`,
  };
}

/** Human label for one answered question, used in the "edit answers" list. */
export function describeAnswer(question, value) {
  if (value === undefined || value === null) return 'بدون پاسخ';
  if (!question) return String(value);

  if (question.type === 'scale') {
    return value === 'unknown'
      ? 'نمی‌دانم'
      : `شدت ${faDigits(value)} از ${faDigits(question.min === 1 ? 10 : question.max)}`;
  }

  const options = Array.isArray(question.options) ? question.options : [];
  const labelFor = (id) => options.find((option) => option.id === id)?.label_fa ?? id;

  if (Array.isArray(value)) {
    if (value.length === 0) return 'بدون پاسخ';
    return value.map(labelFor).join('، ');
  }
  return labelFor(value);
}

/** True when any urgent safety rule is active (used for the sticky banner). */
export function hasUrgent(safety = []) {
  return safety.some((rule) => rule.severity === 'urgent');
}

/**
 * Evidence mapping: which symptom of a condition was reported, reported absent,
 * unknown, never asked, or is not covered by the questionnaire at all.
 *
 * This layer never scores, ranks or computes a probability. It only answers
 * "what did the person actually say" — the separation the roadmap requires
 * between «مشترک با پاسخ شما»، «گزارش نکرده‌اید» و «نامعلوم / پرسیده‌نشده».
 */

const asArray = (value) => (Array.isArray(value) ? value : []);

export const STATUSES = [
  'present',
  'absent',
  'unknown',
  'not_reported',
  'not_asked',
  'conflict',
  'not_covered',
];

export function validateEvidenceMap(bundle, map) {
  const issues = [];
  if (!map || typeof map !== 'object') return ['evidence map is not an object'];

  if (map.bundle_id !== bundle.bundle_id) {
    issues.push('evidence map bundle_id does not match the knowledge bundle');
  }
  if (map.bundle_version !== bundle.bundle_version) {
    issues.push(
      `evidence map was built for bundle ${map.bundle_version}, bundle is ${bundle.bundle_version}`,
    );
  }

  const questions = new Map(asArray(bundle.questions).map((question) => [question.id, question]));
  const symptoms = new Set(asArray(bundle.symptoms).map((symptom) => symptom.id));
  const seen = new Set();

  for (const entry of asArray(map.entries)) {
    const owner = `evidence entry ${entry.question_id ?? '?'}->${entry.symptom_id ?? '?'}`;

    const question = questions.get(entry.question_id);
    if (!question) {
      issues.push(`${owner}: unknown question`);
      continue;
    }
    const optionIds = new Set(asArray(question.options).map((option) => option.id));
    if (!optionIds.has(entry.option_id)) {
      issues.push(`${owner}: unknown option ${entry.question_id}.${entry.option_id}`);
    }
    if (!symptoms.has(entry.symptom_id)) {
      issues.push(`${owner}: unknown symptom`);
    }
    if (entry.state !== 'present' && entry.state !== 'absent') {
      issues.push(`${owner}: state must be "present" or "absent"`);
    }
    const key = `${entry.question_id}|${entry.option_id}|${entry.symptom_id}|${entry.state}`;
    if (seen.has(key)) issues.push(`${owner}: duplicate entry`);
    seen.add(key);
  }

  return issues;
}

/**
 * Symptoms that no question can report on. They are not an error: the result
 * must show them as `not_covered` instead of pretending the person denied them.
 */
export function uncoveredSymptoms(bundle, map) {
  const used = new Set(asArray(map?.entries).map((entry) => entry.symptom_id));
  const uncovered = new Set();
  for (const condition of asArray(bundle.conditions)) {
    for (const link of asArray(condition.symptom_links)) {
      if (!used.has(link.symptom_id)) uncovered.add(link.symptom_id);
    }
  }
  return [...uncovered];
}

function matchesOption(entry, answers) {
  const raw = answers[entry.question_id];
  if (raw === undefined) return false;
  if (Array.isArray(raw)) return raw.includes(entry.option_id);
  return raw === entry.option_id;
}

function isUnknownAnswer(answers, questionId) {
  const raw = answers[questionId];
  if (raw === undefined) return false;
  if (Array.isArray(raw)) return raw.length === 1 && raw[0] === 'unknown';
  return raw === 'unknown';
}

/**
 * @param {object} bundle Knowledge bundle.
 * @param {object} map Evidence map (may be null — then every symptom is not_covered).
 * @param {object} answers Answers keyed by question id.
 * @returns {Map<string, Record<string, Array<{symptom_id: string, label_fa: string, via: Array}>>>}
 */
export function buildEvidence(bundle, map, answers) {
  const entries = asArray(map?.entries);
  const bySymptom = new Map();
  for (const entry of entries) {
    if (!bySymptom.has(entry.symptom_id)) bySymptom.set(entry.symptom_id, []);
    bySymptom.get(entry.symptom_id).push(entry);
  }

  const symptomLabels = new Map(asArray(bundle.symptoms).map((symptom) => [symptom.id, symptom.label_fa]));
  const result = new Map();

  for (const condition of asArray(bundle.conditions)) {
    const groups = Object.fromEntries(STATUSES.map((status) => [status, []]));

    for (const link of asArray(condition.symptom_links)) {
      const symptomId = link.symptom_id;
      const label = symptomLabels.get(symptomId) ?? symptomId;
      const related = bySymptom.get(symptomId) ?? [];

      if (related.length === 0) {
        groups.not_covered.push({ symptom_id: symptomId, label_fa: label, via: [] });
        continue;
      }

      const present = related.filter((entry) => entry.state === 'present' && matchesOption(entry, answers));
      const absent = related.filter((entry) => entry.state === 'absent' && matchesOption(entry, answers));
      const unknown = related.filter((entry) => isUnknownAnswer(answers, entry.question_id));
      const answered = related.filter((entry) => answers[entry.question_id] !== undefined);

      const via = (list) =>
        list.map((entry) => ({ question_id: entry.question_id, option_id: entry.option_id }));

      let status;
      if (present.length && absent.length) status = 'conflict';
      else if (present.length) status = 'present';
      else if (absent.length) status = 'absent';
      else if (unknown.length) status = 'unknown';
      else if (answered.length) status = 'not_reported';
      else status = 'not_asked';

      groups[status].push({
        symptom_id: symptomId,
        label_fa: label,
        via: status === 'present' ? via(present) : status === 'absent' ? via(absent) : via(unknown),
      });
    }

    result.set(condition.id, groups);
  }

  return result;
}

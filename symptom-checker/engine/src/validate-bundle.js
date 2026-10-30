/**
 * Structural validation of the knowledge bundle, independent of WordPress.
 *
 * The Python validator (`tools/symptom-checker-audit/validate_phase1.py`) is the
 * authoring gate; this module is the *runtime* gate: a malformed or incomplete
 * bundle must stop the tool safely instead of producing a made-up result.
 */

export const SUPPORTED_SCHEMA_VERSION = '1.0.0';
const BOOLEAN3 = ['yes', 'no', 'unknown'];

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const asArray = (value) => (Array.isArray(value) ? value : []);

function duplicates(ids) {
  const seen = new Set();
  const dupes = new Set();
  for (const id of ids) {
    if (seen.has(id)) dupes.add(id);
    seen.add(id);
  }
  return [...dupes];
}

function checkRefs(issues, owner, refs, known, label) {
  if (!Array.isArray(refs) || refs.length === 0) {
    issues.push(`${owner}: missing ${label}`);
    return;
  }
  for (const ref of refs) {
    if (!known.has(ref)) issues.push(`${owner}: unknown ${label} "${ref}"`);
  }
}

function checkClause(issues, owner, clause, questions) {
  if (!isObject(clause)) {
    issues.push(`${owner}: clause is not an object`);
    return;
  }
  if (Array.isArray(clause.any_of)) {
    if (clause.any_of.length === 0) issues.push(`${owner}: empty any_of`);
    clause.any_of.forEach((sub) => checkClause(issues, owner, sub, questions));
    return;
  }
  if ('branches_open_min' in clause || 'branches_open_max' in clause) return;

  const question = questions.get(clause.question_id);
  if (!question) {
    issues.push(`${owner}: unknown question "${clause.question_id}"`);
    return;
  }
  const answers = asArray(clause.answers);
  if (answers.length === 0) issues.push(`${owner}: empty answer list for ${clause.question_id}`);
  const optionIds = new Set(asArray(question.options).map((option) => option.id));
  if (optionIds.size > 0) {
    for (const answer of answers) {
      if (!optionIds.has(answer)) {
        issues.push(`${owner}: unknown option ${clause.question_id}.${answer}`);
      }
    }
  }
}

export function collectBundleIssues(bundle) {
  const issues = [];

  if (!isObject(bundle)) {
    return ['bundle is not an object'];
  }

  for (const key of [
    'schema_version',
    'bundle_id',
    'bundle_version',
    'status',
    'answer_states',
    'sources',
    'symptoms',
    'questions',
    'conditions',
    'paths',
    'safety_rules',
    'coverage_rules',
  ]) {
    if (!(key in bundle)) issues.push(`missing top-level key: ${key}`);
  }

  if (bundle.schema_version !== SUPPORTED_SCHEMA_VERSION) {
    issues.push(
      `schema_version must be ${SUPPORTED_SCHEMA_VERSION}, got ${String(bundle.schema_version)}`,
    );
  }

  const states = new Set(asArray(bundle.answer_states));
  for (const state of ['yes', 'no', 'unknown', 'not_asked', 'not_in_profile']) {
    if (!states.has(state)) issues.push(`answer_states is missing "${state}"`);
  }

  const sources = new Map(asArray(bundle.sources).map((item) => [item.id, item]));
  const symptoms = new Map(asArray(bundle.symptoms).map((item) => [item.id, item]));
  const questions = new Map(asArray(bundle.questions).map((item) => [item.id, item]));
  const conditions = new Map(asArray(bundle.conditions).map((item) => [item.id, item]));

  for (const [label, items] of [
    ['source', asArray(bundle.sources)],
    ['symptom', asArray(bundle.symptoms)],
    ['question', asArray(bundle.questions)],
    ['condition', asArray(bundle.conditions)],
    ['path', asArray(bundle.paths)],
    ['safety_rule', asArray(bundle.safety_rules)],
    ['coverage_rule', asArray(bundle.coverage_rules)],
  ]) {
    for (const dupe of duplicates(items.map((item) => item?.id))) {
      issues.push(`duplicate ${label} id: ${dupe}`);
    }
  }

  const askOrder = new Map();
  for (const question of questions.values()) {
    const owner = `question ${question.id}`;

    if (!question.text_fa) issues.push(`${owner}: missing text_fa`);
    if (!question.why_fa) issues.push(`${owner}: missing why_fa`);
    checkRefs(issues, owner, question.source_refs, new Set(sources.keys()), 'source');

    const order = question.ask_order;
    if (!Number.isInteger(order) || order < 1) {
      issues.push(`${owner}: invalid ask_order`);
    } else if (askOrder.has(order)) {
      issues.push(`${owner}: ask_order ${order} already used by ${askOrder.get(order)}`);
    } else {
      askOrder.set(order, question.id);
    }

    const visibility = question.visibility ?? {};
    if (!visibility.always && !Array.isArray(visibility.any_of)) {
      issues.push(`${owner}: missing visibility rule`);
    }
    for (const rule of asArray(visibility.any_of)) {
      const dependency = questions.get(rule.question_id);
      if (!dependency) {
        issues.push(`${owner}: visibility references unknown question ${rule.question_id}`);
        continue;
      }
      if (dependency.id === question.id) issues.push(`${owner}: visibility references itself`);
      if (dependency.ask_order >= order) {
        issues.push(`${owner}: dependency ${dependency.id} is asked later than the dependent question`);
      }
      const optionIds = new Set(asArray(dependency.options).map((option) => option.id));
      for (const answer of asArray(rule.answers)) {
        if (optionIds.size && !optionIds.has(answer)) {
          issues.push(`${owner}: visibility uses unknown option ${dependency.id}.${answer}`);
        }
      }
    }

    const type = question.type;
    const options = asArray(question.options);
    const optionIds = options.map((option) => option.id);

    if (new Set(optionIds).size !== optionIds.length) {
      issues.push(`${owner}: duplicate option ids`);
    }

    if (type === 'boolean3') {
      const set = [...optionIds].sort().join(',');
      if (set !== [...BOOLEAN3].sort().join(',')) {
        issues.push(`${owner}: boolean3 needs exactly yes/no/unknown options`);
      }
    } else if (type === 'single' || type === 'multi') {
      if (options.length < 2) issues.push(`${owner}: needs at least two options`);
    } else if (type === 'scale') {
      if (!Number.isInteger(question.min) || !Number.isInteger(question.max)) {
        issues.push(`${owner}: scale needs integer min/max`);
      } else if (question.min >= question.max) {
        issues.push(`${owner}: scale range is invalid`);
      }
    } else {
      issues.push(`${owner}: unknown type "${String(type)}"`);
    }

    if (type === 'multi') {
      for (const option of options) {
        if ((option.id === 'none' || option.id === 'unknown') && option.exclusive !== true) {
          issues.push(`${owner}: "${option.id}" must be exclusive in a multi question`);
        }
      }
    }
  }

  for (const condition of conditions.values()) {
    const owner = `condition ${condition.id}`;
    const links = asArray(condition.symptom_links);
    if (links.length < 3) issues.push(`${owner}: fewer than 3 symptom links`);
    for (const link of links) {
      if (!symptoms.has(link.symptom_id)) {
        issues.push(`${owner}: unknown symptom "${link.symptom_id}"`);
      }
      checkRefs(issues, owner, link.source_refs, new Set(sources.keys()), 'source');
    }
    for (const ref of asArray(condition.red_flag_refs)) {
      if (!asArray(bundle.safety_rules).some((rule) => rule.id === ref)) {
        issues.push(`${owner}: unknown safety rule "${ref}"`);
      }
    }
    if (typeof condition.profile_ref !== 'string' || !condition.profile_ref.includes('#')) {
      issues.push(`${owner}: profile_ref must point to a profile id`);
    }
    if (!['needs_live_verification', 'verified_published', 'unmapped'].includes(condition.link_status)) {
      issues.push(`${owner}: invalid link_status`);
    }
  }

  for (const path of asArray(bundle.paths)) {
    const owner = `path ${path.id}`;
    if (!conditions.has(path.condition_id)) {
      issues.push(`${owner}: unknown condition "${path.condition_id}"`);
    }
    checkRefs(issues, owner, path.source_refs, new Set(sources.keys()), 'source');
    for (const clause of asArray(path.when)) checkClause(issues, owner, clause, questions);
  }

  for (const rule of asArray(bundle.safety_rules)) {
    const owner = `safety rule ${rule.id}`;
    if (!['urgent', 'prompt', 'info'].includes(rule.severity)) {
      issues.push(`${owner}: invalid severity`);
    }
    if (!rule.message_fa) issues.push(`${owner}: missing message_fa`);
    checkRefs(issues, owner, rule.source_refs, new Set(sources.keys()), 'source');
    for (const clause of asArray(rule.when)) checkClause(issues, owner, clause, questions);
  }

  for (const rule of asArray(bundle.coverage_rules)) {
    const owner = `coverage rule ${rule.id}`;
    if (!['sufficient', 'insufficient', 'out_of_scope'].includes(rule.result)) {
      issues.push(`${owner}: invalid result`);
    }
    checkRefs(issues, owner, rule.source_refs, new Set(sources.keys()), 'source');
    for (const clause of asArray(rule.when)) checkClause(issues, owner, clause, questions);
  }

  for (const id of conditions.keys()) {
    if (!asArray(bundle.paths).some((path) => path.condition_id === id)) {
      issues.push(`condition ${id}: no path can open it`);
    }
  }

  return issues;
}

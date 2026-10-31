/**
 * Phase-4 shadow study: how would an adaptive order behave, question by
 * question, compared with the order the tool ships today?
 *
 * Nothing here runs in the browser or in the plugin. The tool drives the same
 * engine the page uses (`createSession`), with the same knowledge bundle, and
 * compares two sessions on identical personas:
 *
 *   static     `questionStrategy: 'static_order'` — what the visitor sees today
 *   adaptive   `questionStrategy: 'adaptive'`     — the phase-4 candidate
 *
 * The personas are synthetic, not patients: a `typical` persona reports the
 * rows the bundle marks as the typical picture, and a `random` persona reports
 * a seeded subset of them. The answerer only ever echoes the persona back; it
 * never invents a symptom and never claims a diagnosis. Numbers printed here are
 * counts of questions — never probabilities, never accuracy.
 *
 *   node tools/symptom-checker-adaptive/simulate.mjs
 *   node tools/symptom-checker-adaptive/simulate.mjs --json out.json
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { chooseAdaptive } from '../../symptom-checker/engine/src/adaptive.js';
import { createSession } from '../../symptom-checker/engine/src/engine.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const read = (path) => JSON.parse(readFileSync(resolve(ROOT, path), 'utf8'));

/** The bundle that ships in the plugin, and the newest CLI-built bundle. */
export const BUNDLES = [
  { name: 'shipped-v1', path: 'docs/symptom-checker/phase-1/knowledge-bundle.v1.json', externalMap: 'symptom-checker/engine/data/evidence-map.v1.json' },
  { name: 'cholecystitis-v2', path: 'docs/symptom-checker/phase-3/built/knowledge-bundle.v2.cholecystitis-demo.json', externalMap: null },
];

/**
 * Questions the persona model cannot express. The scope gate has no evidence
 * mapping on purpose (it is not a symptom), and the two inert questions map to
 * nothing either. Keeping them in one visible table is the honest alternative to
 * teaching the answerer to guess.
 */
export const UNMAPPED_ANSWERS = {
  q_pregnancy_possible: 'no',
};

/**
 * The entry question asks about «شکم، پهلو یا کمر» in one sentence, but its
 * evidence only maps «بله» to abdominal pain. A persona whose pain is described
 * as flank pain must therefore answer it here, or the whole questionnaire would
 * be skipped for a person the tool's own text invites in. This is a modelling
 * assumption of the study and it is reported as a data finding — the bundle's
 * evidence for `q_pain_present` is left alone.
 */
// Two id styles are in use: `s_pain_*` (v1) and `abd.pain_*` (v2).
const PAIN_SYMPTOM = /^(?:s_(?:pain|flank_pain)|abd\.(?:pain|flank_pain))/;

/** Deterministic PRNG (mulberry32) — the study must be reproducible. */
export function rng(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const asArray = (value) => (Array.isArray(value) ? value : []);

/** condition → rows, in bundle order, with the level the bundle wrote. */
export function conditionProfiles(bundle) {
  return asArray(bundle.conditions).map((condition) => ({
    id: condition.id,
    name_fa: condition.name_fa,
    rows: asArray(condition.profile ?? condition.symptom_links).map((row) => ({
      symptom_id: row.symptom_id,
      level: row.level ?? 'typical',
    })),
  }));
}

/** The typical picture: what the bundle says this condition usually looks like. */
export function typicalPersona(profile) {
  const present = new Set(profile.rows.filter((row) => row.level === 'typical').map((row) => row.symptom_id));
  const absent = new Set(profile.rows.filter((row) => row.level === 'not_expected').map((row) => row.symptom_id));
  return { label: `typical:${profile.id}`, target: profile.id, present, absent };
}

/** Same condition, but a seeded share of the typical rows is missing. */
export function randomPersona(profile, random) {
  const keep = 0.5 + random() * 0.5;
  const present = new Set();
  for (const row of profile.rows) {
    if (row.level !== 'typical') continue;
    if (random() < keep) present.add(row.symptom_id);
  }
  const absent = new Set(profile.rows.filter((row) => row.level === 'not_expected').map((row) => row.symptom_id));
  return { label: `random:${profile.id}:${Math.round(keep * 100)}`, target: profile.id, present, absent };
}

/** evidence of one option, straight from the bundle (v2) or the external map. */
function optionEvidence(bundle, map, questionId, optionId) {
  if (map) return map.entries.filter((entry) => entry.question_id === questionId && entry.option_id === optionId);
  const question = bundle.questions.find((item) => item.id === questionId);
  const option = asArray(question?.options).find((item) => item.id === optionId);
  return asArray(option?.evidence);
}

/**
 * The persona's answer to one question: the options whose evidence is entirely
 * true for this persona, preferring the option that says the most.
 */
export function answerFor(bundle, map, question, persona) {
  if (question.id in UNMAPPED_ANSWERS) return UNMAPPED_ANSWERS[question.id];
  if (question.id === 'q_pain_present') {
    return [...persona.present].some((symptomId) => PAIN_SYMPTOM.test(symptomId)) ? 'yes' : 'no';
  }

  if (question.type === 'scale') return question.allow_unknown ? 'unknown' : question.min;

  const claims = asArray(question.options).map((option) => {
    const evidence = optionEvidence(bundle, map, question.id, option.id);
    const trueStatements = evidence.filter((entry) =>
      entry.state === 'present' ? persona.present.has(entry.symptom_id) : persona.absent.has(entry.symptom_id),
    ).length;
    const falseStatements = evidence.filter((entry) =>
      entry.state === 'present' ? persona.absent.has(entry.symptom_id) : persona.present.has(entry.symptom_id),
    ).length;
    return { id: option.id, exclusive: Boolean(option.exclusive), total: evidence.length, trueStatements, falseStatements };
  });

  const consistent = claims.filter((claim) => claim.falseStatements === 0 && claim.total > 0);
  const best = consistent
    .slice()
    .sort((a, b) => b.trueStatements - a.trueStatements || a.id.localeCompare(b.id))[0];

  if (question.type === 'multi') {
    if (consistent.length > 0) {
      const chosen = consistent.filter((claim) => claim.trueStatements > 0);
      return chosen.length ? chosen.map((claim) => claim.id) : [best.id];
    }
    return question.allow_unknown ? ['unknown'] : [asArray(question.options)[0].id];
  }

  if (best) return best.id;
  if (question.allow_unknown) return 'unknown';

  // Nothing in the persona matches any option: take the option that denies the
  // most, because a persona that reports none of those symptoms did deny them.
  const denial = claims
    .filter((claim) => claim.trueStatements > 0)
    .sort((a, b) => b.trueStatements - a.trueStatements || a.id.localeCompare(b.id))[0];
  if (denial) return denial.id;

  const none = asArray(question.options).find((option) => /^(no|none|nothing)$/i.test(option.id));
  if (none) return none.id;
  return asArray(question.options)[0].id;
}

/**
 * Answers that describe the situation one safety rule exists for, from the rule
 * itself — no hand-written expectation to drift away from the data.
 */
export function ruleAnswers(rule) {
  const answers = {};
  const walk = (clause) => {
    if (!clause || typeof clause !== 'object') return;
    if (typeof clause.question_id === 'string' && !(clause.question_id in answers)) {
      answers[clause.question_id] = asArray(clause.answers)[0];
    }
    for (const sub of asArray(clause.any_of)) walk(sub);
  };
  asArray(rule.when).forEach(walk);
  return answers;
}

/**
 * Per safety rule: does the rule still fire when the *adaptive* order decides
 * what is asked when? The runbook asks for this matrix explicitly — an order
 * that is quieter about danger is a regression, whatever it does to counts.
 */
export function safetyMatrix(bundle, map) {
  const rows = [];
  for (const rule of asArray(bundle.safety_rules)) {
    const prescribed = ruleAnswers(rule);
    // Every rule in this bundle lives behind the pain gate, so the persona is a
    // person with abdominal pain whose other answers come from the rule itself.
    const persona = {
      label: `rule:${rule.id}`,
      target: null,
      present: new Set(['s_pain_abdomen', 'abd.pain_abdomen']),
      absent: new Set(),
      prescribed,
    };
    const run = (strategy) => {
      const session = createSession(bundle, { evidenceMap: map, questionStrategy: strategy });
      for (let step = 0; step < 40; step += 1) {
        const question = session.nextQuestion();
        if (!question) break;
        const raw = question.id in prescribed ? prescribed[question.id] : answerFor(bundle, map, question, persona);
        // A rule clause names one option; a `multi` question needs a list.
        const answer = Array.isArray(raw) || question.type !== 'multi' ? raw : [raw];
        try {
          session.answer(question.id, answer);
        } catch {
          break;
        }
      }
      return session.result().safety.map((item) => item.id);
    };
    rows.push({ rule: rule.id, severity: rule.severity, static: run('static_order').includes(rule.id), adaptive: run('adaptive').includes(rule.id) });
  }
  return rows;
}

/** One session, answered by the persona, until the questionnaire ends. */
export function runSession(bundle, map, persona, strategy, { maxQuestions = 40, safetyPolicy = null } = {}) {
  // `adaptive` can be run with either safety policy; `static_order` ignores it.
  const options =
    strategy === 'adaptive' && safetyPolicy
      ? { evidenceMap: map, selectNextQuestion: (state) => chooseAdaptive(state.knowledge, { ...state, safetyPolicy }).question }
      : { evidenceMap: map, questionStrategy: strategy };
  const session = createSession(bundle, options);
  const asked = [];
  let toCard = null;
  let disabledBy = null;

  for (let step = 0; step < maxQuestions; step += 1) {
    const question = session.nextQuestion();
    if (!question) break;
    const answer = answerFor(bundle, map, question, persona);
    let result;
    try {
      result = session.answer(question.id, answer);
    } catch (error) {
      return { asked, toCard, cards: [], safety: [], error: `${question.id}: ${error.message}` };
    }
    asked.push(question.id);
    if (result.state === 'stopped') {
      disabledBy = result.coverage.rule_id;
      break;
    }
    if (toCard === null && result.cards_allowed && result.conditions.some((card) => card.id === persona.target)) {
      toCard = asked.length;
    }
  }

  const result = session.result();
  return {
    asked,
    toCard,
    cards: result.conditions.map((card) => card.id),
    safety: result.safety.map((rule) => rule.id),
    stoppedBy: disabledBy,
  };
}

export function study({ quick = false } = {}) {
  const out = { bundles: [], generated: new Date().toISOString().slice(0, 10) };

  for (const descriptor of BUNDLES) {
    const bundle = read(descriptor.path);
    const map = descriptor.externalMap ? read(descriptor.externalMap) : null;
    const profiles = conditionProfiles(bundle);

    const personas = [];
    for (const profile of profiles) personas.push(typicalPersona(profile));
    const random = rng(20261031);
    for (let index = 0; index < (quick ? 10 : 200); index += 1) {
      personas.push(randomPersona(profiles[index % profiles.length], random));
    }

    const perCondition = [];
    for (const profile of profiles) {
      const persona = typicalPersona(profile);
      const base = runSession(bundle, map, persona, 'static_order');
      const literal = runSession(bundle, map, persona, 'adaptive', { safetyPolicy: 'always_first' });
      const baseScreen = runSession(bundle, map, persona, 'adaptive');
      const adaptive = literal;
      perCondition.push({
        condition: profile.id,
        name_fa: profile.name_fa,
        static_to_card: base.toCard,
        literal_to_card: literal.toCard,
        base_screen_to_card: baseScreen.toCard,
        adaptive_to_card: adaptive.toCard,
        static_questions: base.asked.length,
        literal_questions: literal.asked.length,
        base_screen_questions: baseScreen.asked.length,
        static_safety: base.safety,
        literal_safety: literal.safety,
        base_screen_safety: baseScreen.safety,
        cards_static: base.cards,
        cards_literal: literal.cards,
        cards_base_screen: baseScreen.cards,
        literal_first_questions: literal.asked.slice(0, 6),
        base_screen_first_questions: baseScreen.asked.slice(0, 6),
      });
    }

    let staticTotal = 0;
    let adaptiveTotal = 0;
    let baseScreenTotal = 0;
    let staticMisses = 0;
    let adaptiveMisses = 0;
    let baseScreenMisses = 0;
    let safetyLost = 0;
    let baseScreenSafetyLost = 0;
    for (const persona of personas) {
      const base = runSession(bundle, map, persona, 'static_order');
      const literal = runSession(bundle, map, persona, 'adaptive', { safetyPolicy: 'always_first' });
      const baseScreen = runSession(bundle, map, persona, 'adaptive');
      staticTotal += base.asked.length;
      adaptiveTotal += literal.asked.length;
      baseScreenTotal += baseScreen.asked.length;
      if (!base.cards.includes(persona.target)) staticMisses += 1;
      if (!literal.cards.includes(persona.target)) adaptiveMisses += 1;
      if (!baseScreen.cards.includes(persona.target)) baseScreenMisses += 1;
      // A rule the static order fired for this persona must also fire in the
      // adaptive order: the shadow must not be quieter about danger.
      for (const rule of base.safety) {
        if (!literal.safety.includes(rule)) safetyLost += 1;
        if (!baseScreen.safety.includes(rule)) baseScreenSafetyLost += 1;
      }
    }

    out.bundles.push({
      name: descriptor.name,
      safety_matrix: safetyMatrix(bundle, map),
      conditions: profiles.length,
      questions: bundle.questions.length,
      personas: personas.length,
      perCondition,
      totals: {
        mean_questions_static: Number((staticTotal / personas.length).toFixed(2)),
        mean_questions_literal: Number((adaptiveTotal / personas.length).toFixed(2)),
        mean_questions_base_screen: Number((baseScreenTotal / personas.length).toFixed(2)),
        static_to_card_max: Math.max(...perCondition.map((row) => row.static_to_card ?? 99)),
        literal_to_card_max: Math.max(...perCondition.map((row) => row.literal_to_card ?? 99)),
        base_screen_to_card_max: Math.max(...perCondition.map((row) => row.base_screen_to_card ?? 99)),
        static_misses: staticMisses,
        literal_misses: adaptiveMisses,
        base_screen_misses: baseScreenMisses,
        safety_rules_lost: safetyLost,
        base_screen_safety_rules_lost: baseScreenSafetyLost,
      },
    });
  }

  return out;
}

const isMain = process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url));
if (isMain) {
  const quick = process.argv.includes('--quick');
  const check = process.argv.includes('--check');
  const result = study({ quick });
  const jsonFlag = process.argv.indexOf('--json');
  if (jsonFlag !== -1 && process.argv[jsonFlag + 1]) {
    writeFileSync(resolve(process.cwd(), process.argv[jsonFlag + 1]), `${JSON.stringify(result, null, 2)}\n`, 'utf8');
  }
  for (const bundle of result.bundles) {
    console.log(`\n=== ${bundle.name} (${bundle.conditions} conditions, ${bundle.questions} questions, ${bundle.personas} personas)`);
    console.log('condition           static  literal  candidate   q static/literal/candidate   safety static/literal/candidate');
    for (const row of bundle.perCondition) {
      console.log(
        `${row.condition.padEnd(18)} ${String(row.static_to_card).padStart(6)}  ${String(row.literal_to_card).padStart(7)}  ${String(row.base_screen_to_card).padStart(9)}   ${row.static_questions}/${row.literal_questions}/${row.base_screen_questions}   ${row.static_safety.length}/${row.literal_safety.length}/${row.base_screen_safety.length}`,
      );
    }
    console.log('safety matrix:', bundle.safety_matrix.map((row) => `${row.rule}=${row.static ? 'y' : 'n'}/${row.adaptive ? 'y' : 'n'}`).join('  '));
    console.log('totals:', bundle.totals);
  }

  if (check) {
    // The shadow may be imperfect — that is what a study is for — but it may
    // never be quieter about danger, and the baseline it is compared with must
    // itself reach every typical card.
    const failures = [];
    for (const bundle of result.bundles) {
      if (bundle.totals.safety_rules_lost !== 0) failures.push(`${bundle.name}: adaptive lost ${bundle.totals.safety_rules_lost} safety rule(s)`);
      if (bundle.totals.base_screen_safety_rules_lost !== 0) failures.push(`${bundle.name}: base-screen policy lost ${bundle.totals.base_screen_safety_rules_lost} safety rule(s)`);
      for (const row of bundle.safety_matrix) {
        if (!row.static) failures.push(`${bundle.name}/${row.rule}: the rule never fires even in static_order — the fixture is wrong`);
        if (!row.adaptive) failures.push(`${bundle.name}/${row.rule}: the adaptive order loses this safety rule`);
      }
      for (const row of bundle.perCondition) {
        if (row.static_to_card === null) failures.push(`${bundle.name}/${row.condition}: the shipped order never shows its own typical card`);
        if (row.base_screen_to_card === null) failures.push(`${bundle.name}/${row.condition}: the candidate policy loses the typical card`);
        if (!row.cards_static.includes(row.condition)) failures.push(`${bundle.name}/${row.condition}: typical persona has no card in static_order`);
      }
    }
    if (failures.length > 0) {
      console.error('\nFAIL the shadow study broke a promise:');
      for (const failure of failures) console.error(' -', failure);
      process.exit(1);
    }
    console.log('\nPASS no safety rule is lost, and every typical persona keeps its card in both orders');
  }
}

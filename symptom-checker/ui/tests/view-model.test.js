/**
 * View-model tests: the demo may only render what the engine allows, and it must
 * never invent a probability, a score or a ranking.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

import {
  EVIDENCE_GROUPS,
  buildProgress,
  buildResultView,
  describeAnswer,
  evidenceGroups,
  hasUrgent,
  sortSafety,
} from '../view-model.js';

const baseResult = (overrides = {}) => ({
  state: 'asking',
  coverage: { result: 'sufficient', rule_id: 'cv_sufficient', message_fa: 'موضوع‌های مرتبط…' },
  cards_allowed: true,
  conditions: [
    {
      id: 'app',
      name_fa: 'آپاندیسیت',
      article_slug: 'appendicitis',
      link_status: 'needs_live_verification',
      reasons: [{ path_id: 'p_app_rlq', note_fa: 'درد پایین راست مسیر آپاندیسیت را باز می‌کند.', source_refs: ['src_nhs_app'] }],
      evidence: {
        present: [{ symptom_id: 's_pain_rlq', label_fa: 'درد پایین سمت راست شکم', via: [] }],
        absent: [{ symptom_id: 's_nausea', label_fa: 'تهوع', via: [] }],
        unknown: [],
        not_reported: [],
        not_asked: [{ symptom_id: 's_bloating', label_fa: 'نفخ شکم', via: [] }],
        conflict: [],
        not_covered: [{ symptom_id: 's_abdominal_cramp', label_fa: 'درد یا گرفتگی شکم', via: [] }],
      },
    },
  ],
  safety: [
    { id: 'sf_pain_worsening', severity: 'prompt', message_fa: 'درد بدتر می‌شود.' },
    { id: 'sf_severe_pain', severity: 'urgent', message_fa: 'درد شدید.' },
  ],
  disclaimers: { result_fa: 'این‌ها تشخیص نیستند.' },
  ...overrides,
});

test('cards are rendered when the engine allows them', () => {
  const view = buildResultView(baseResult());
  assert.equal(view.showCards, true);
  assert.equal(view.cards.length, 1);
  assert.equal(view.cards[0].name_fa, 'آپاندیسیت');
});

test('cards are suppressed when coverage is not sufficient', () => {
  const view = buildResultView(
    baseResult({
      cards_allowed: false,
      conditions: [],
      coverage: { result: 'out_of_scope', message_fa: 'خارج از پوشش.' },
    }),
  );
  assert.equal(view.showCards, false);
  assert.deepEqual(view.cards, []);
  assert.equal(view.emptyMessage, 'خارج از پوشش.');
});

test('cards are suppressed when the engine returns an empty card list', () => {
  const view = buildResultView(baseResult({ conditions: [] }));
  assert.equal(view.showCards, false);
});

test('evidence groups keep the documented order and hide empty groups', () => {
  const view = buildResultView(baseResult());
  const keys = view.cards[0].groups.map((group) => group.key);
  assert.deepEqual(keys, ['present', 'absent', 'not_asked', 'not_covered']);
  assert.ok(!keys.includes('unknown'));
});

test('evidenceGroups returns all seven documented groups', () => {
  const groups = evidenceGroups({
    present: [], absent: [], unknown: [], not_reported: [], not_asked: [], conflict: [], not_covered: [],
  });
  assert.equal(groups.length, 7);
  assert.deepEqual(groups.map((group) => group.key), EVIDENCE_GROUPS.map((group) => group.key));
  for (const group of groups) {
    assert.ok(group.label.length > 0);
    assert.ok(group.hint.length > 0);
  }
});

test('evidenceGroups tolerates missing evidence', () => {
  assert.deepEqual(evidenceGroups(null), []);
});

test('safety rules are sorted urgent first', () => {
  const sorted = sortSafety(baseResult().safety);
  assert.deepEqual(sorted.map((rule) => rule.id), ['sf_severe_pain', 'sf_pain_worsening']);
  assert.equal(hasUrgent(sorted), true);
  assert.equal(hasUrgent([{ severity: 'prompt' }]), false);
});

test('progress is clamped and labelled', () => {
  assert.deepEqual(buildProgress(0, 0), {
    answered: 0,
    remaining: 0,
    total: 0,
    percent: 0,
    label: 'پرسشی برای نمایش نیست',
  });
  assert.equal(buildProgress(3, 1).percent, 75);
  assert.equal(buildProgress(10, 0).percent, 100);
  assert.equal(buildProgress(1, 0).label, 'پاسخ‌داده‌شده ۱ از ۱');
});

test('answers are described with the questionnaire labels', () => {
  const multi = {
    type: 'multi',
    options: [
      { id: 'nausea', label_fa: 'تهوع' },
      { id: 'vomiting', label_fa: 'استفراغ' },
      { id: 'none', label_fa: 'هیچ‌کدام' },
    ],
  };
  assert.equal(describeAnswer(multi, ['nausea', 'vomiting']), 'تهوع، استفراغ');
  assert.equal(describeAnswer(multi, ['none']), 'هیچ‌کدام');
  assert.equal(describeAnswer(multi, []), 'بدون پاسخ');

  const single = { type: 'single', options: [{ id: 'yes', label_fa: 'بله' }, { id: 'unknown', label_fa: 'نمی‌دانم' }] };
  assert.equal(describeAnswer(single, 'unknown'), 'نمی‌دانم');

  const scale = { type: 'scale', min: 1, max: 10 };
  assert.equal(describeAnswer(scale, 7), 'شدت ۷ از ۱۰');
  assert.equal(describeAnswer(scale, 'unknown'), 'نمی‌دانم');
});

test('result view never contains probability, percentage or score wording', () => {
  const view = buildResultView(baseResult());
  const text = JSON.stringify(view);
  for (const forbidden of ['درصد', '٪', 'احتمال', 'امتیاز', 'probability', 'score']) {
    assert.ok(!text.includes(forbidden), `forbidden wording: ${forbidden}`);
  }
});

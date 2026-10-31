/**
 * Phase-3 demo UI controller.
 *
 * Loads the phase-1 bundle and the evidence map, runs the phase-2 engine and
 * renders a demo that is explicitly labelled as non-published. Answers stay in
 * this page's memory only: no storage, no cookies, no network calls after load.
 */

import { createSession } from '../engine/src/engine.js';
import {
  buildProgress,
  buildResultView,
  describeAnswer,
  hasUrgent,
} from './view-model.js';

/**
 * The dev demo runs from the repository with no config. Inside WordPress the
 * plugin injects `window.HJ_SC_CONFIG` with absolute asset URLs and the
 * resolved article links; nothing else about this file changes there.
 */
const config = (typeof window !== 'undefined' && window.HJ_SC_CONFIG && typeof window.HJ_SC_CONFIG === 'object')
  ? window.HJ_SC_CONFIG
  : {};

const BUNDLE_URL = config.bundleUrl || '../../docs/symptom-checker/phase-1/knowledge-bundle.v1.json';
const EVIDENCE_URL = config.evidenceUrl || '../engine/data/evidence-map.v1.json';
const TOPIC_LINKS = (config.topicLinks && typeof config.topicLinks === 'object') ? config.topicLinks : {};

const state = {
  bundle: null,
  evidenceMap: null,
  session: null,
  screen: 'loading',
  current: null,
  error: null,
  draft: null,
  showEditor: false,
  // Questions whose answers contradict each other in the current result.
  conflictQuestions: new Set(),
};

const app = document.getElementById('app');
const safetyRegion = document.getElementById('safety');
const liveRegion = document.getElementById('live');

// --- tiny DOM helpers (textContent only, never innerHTML with data) --------
function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (value === undefined || value === null || value === false) continue;
    if (key === 'class') node.className = value;
    else if (key === 'text') node.textContent = value;
    else if (key.startsWith('on') && typeof value === 'function') {
      node.addEventListener(key.slice(2).toLowerCase(), value);
    } else if (value === true) node.setAttribute(key, '');
    else node.setAttribute(key, String(value));
  }
  for (const child of [].concat(children)) {
    if (child === null || child === undefined || child === false) continue;
    node.append(child.nodeType ? child : document.createTextNode(String(child)));
  }
  return node;
}

const clear = (node) => {
  while (node.firstChild) node.removeChild(node.firstChild);
};

function announce(message) {
  liveRegion.textContent = '';
  window.setTimeout(() => {
    liveRegion.textContent = message;
  }, 50);
}

// --- data ----------------------------------------------------------------
async function loadData() {
  const [bundleResponse, evidenceResponse] = await Promise.all([
    fetch(BUNDLE_URL, { cache: 'no-store' }),
    fetch(EVIDENCE_URL, { cache: 'no-store' }),
  ]);
  if (!bundleResponse.ok) throw new Error(`بارگذاری بستهٔ دانش ناموفق بود (${bundleResponse.status})`);
  if (!evidenceResponse.ok) throw new Error(`بارگذاری نگاشت شواهد ناموفق بود (${evidenceResponse.status})`);

  state.bundle = await bundleResponse.json();
  state.evidenceMap = await evidenceResponse.json();
}

function startSession() {
  state.session = createSession(state.bundle, { evidenceMap: state.evidenceMap });
  state.error = null;
  state.showEditor = false;
  state.conflictQuestions = new Set();
  state.screen = 'question';
  state.draft = null;
  renderSafetyBanners(safetyRegion);
  goToNextQuestion();
  render();
}

function goToNextQuestion() {
  const next = state.session.nextQuestion();
  if (!next) {
    state.screen = 'result';
    state.current = null;
    return;
  }
  state.screen = 'question';
  state.current = next;
  state.draft = null;
}

// --- rendering -----------------------------------------------------------
function render() {
  clear(app);
  if (state.screen === 'loading') return renderLoading();
  if (state.screen === 'error') return renderError();
  if (state.screen === 'intro') return renderIntro();
  if (state.screen === 'question') return renderQuestion();
  if (state.screen === 'result') return renderResult();
  return undefined;
}

function renderLoading() {
  app.append(el('section', { class: 'card' }, [
    el('h1', { text: 'در حال بارگذاری…' }),
    el('p', { class: 'muted', text: 'بستهٔ دانش و موتور در همین صفحه بارگذاری می‌شوند.' }),
  ]));
}

function renderError() {
  app.append(el('section', { class: 'card error' }, [
    el('h1', { text: 'ابزار متوقف شد' }),
    el('p', { text: state.error }),
    el('p', { class: 'muted', text: 'این توقف امن است: هیچ نتیجه‌ای از دادهٔ معیوب ساخته نمی‌شود.' }),
    el('button', { class: 'primary', text: 'تلاش دوباره', onClick: boot }),
  ]));
}

function renderIntro() {
  const disclaimers = state.bundle.disclaimers ?? {};
  app.append(el('section', { class: 'card' }, [
    el('h1', { text: 'همیار نشانه‌ها — نمونهٔ نمایشی' }),
    el('p', { class: 'badge-line' }, [
      el('span', { class: 'badge warn', text: 'نمایشی' }),
      el('span', { class: 'badge', text: 'دادهٔ پیش‌نویس، منتشر نشده' }),
      el('span', { class: 'badge', text: 'بدون ذخیره‌سازی' }),
    ]),
    el('p', { class: 'lead', text: disclaimers.before_start_fa ?? '' }),
    el('ul', { class: 'facts' }, [
      el('li', { text: 'پاسخ‌ها فقط در حافظهٔ همین صفحه می‌مانند و با بستن صفحه پاک می‌شوند.' }),
      el('li', { text: 'خروجی، «موضوع‌های مرتبط» است؛ نه تشخیص، نه احتمال و نه درصد.' }),
      el('li', { text: 'اگر درد شدید یا بدحالی داری، منتظر پایان پرسش‌ها نمان و کمک پزشکی بگیر.' }),
    ]),
    el('button', { class: 'primary', text: 'شروع', onClick: startSession }),
  ]));
}

function renderSafetyBanners(container) {
  if (!state.session) return;
  const rules = state.session.safety();
  clear(container);
  if (!rules.length) {
    container.hidden = true;
    return;
  }
  container.hidden = false;
  container.append(
    el('div', { class: hasUrgent(rules) ? 'safety urgent' : 'safety', role: 'alert' }, [
      el('h2', { text: 'توجه' }),
      ...rules.map((rule) =>
        el('p', { class: `safety-${rule.severity}` }, [
          el('span', { class: 'safety-tag', text: rule.severity === 'urgent' ? 'فوری' : 'بررسی پزشکی' }),
          el('span', { text: rule.message_fa }),
        ]),
      ),
      el('p', { class: 'muted small', text: 'این هشدارها مستقل از نتیجهٔ موضوع‌ها نمایش داده می‌شوند.' }),
    ]),
  );
}

function fieldName(question) {
  return question.id;
}

function renderScaleInput(question) {
  const input = el('input', {
    type: 'number',
    id: `${fieldName(question)}-number`,
    name: fieldName(question),
    min: question.min,
    max: question.max,
    step: 1,
    inputmode: 'numeric',
    'aria-describedby': `${fieldName(question)}-hint`,
  });
  input.addEventListener('input', () => {
    state.draft = { kind: 'scale', value: input.value === '' ? null : Number(input.value) };
    updateSubmitState();
  });

  const unknownId = `${fieldName(question)}-unknown`;
  const unknown = el('input', { type: 'checkbox', id: unknownId });
  unknown.addEventListener('change', () => {
    input.disabled = unknown.checked;
    state.draft = unknown.checked ? { kind: 'scale', value: 'unknown' } : { kind: 'scale', value: null };
    updateSubmitState();
  });

  return el('div', { class: 'scale' }, [
    el('label', { for: input.id, text: 'شدت را از ۱ تا ۱۰ انتخاب کن' }),
    input,
    el('p', { id: `${fieldName(question)}-hint`, class: 'muted small', text: 'این عدد فقط برای توصیف است و به‌تنهایی معنی تشخیصی ندارد.' }),
    el('div', { class: 'checkline' }, [unknown, el('label', { for: unknownId, text: 'نمی‌دانم' })]),
  ]);
}

function renderOptions(question) {
  const container = el('div', { class: 'options' });

  if (question.type === 'scale') {
    container.append(renderScaleInput(question));
    return container;
  }

  const multi = question.type === 'multi';
  const inputs = [];

  for (const option of question.options) {
    const id = `${fieldName(question)}-${option.id}`;
    const input = el('input', {
      type: multi ? 'checkbox' : 'radio',
      name: fieldName(question),
      id,
      value: option.id,
    });
    inputs.push({ input, option });

    input.addEventListener('change', () => {
      if (multi) {
        const exclusiveIds = question.options.filter((item) => item.exclusive).map((item) => item.id);
        if (option.exclusive && input.checked) {
          for (const item of inputs) if (item.option.id !== option.id) item.input.checked = false;
        } else if (input.checked) {
          for (const item of inputs) {
            if (exclusiveIds.includes(item.option.id)) item.input.checked = false;
          }
        }
        const values = inputs.filter((item) => item.input.checked).map((item) => item.option.id);
        state.draft = { kind: 'multi', value: values };
      } else {
        state.draft = { kind: 'single', value: option.id };
      }
      updateSubmitState();
    });

    container.append(
      el('div', { class: 'option' }, [
        input,
        el('label', { for: id, text: option.label_fa }),
      ]),
    );
  }

  return container;
}

function updateSubmitState() {
  const button = document.getElementById('submit-answer');
  if (!button) return;
  const draft = state.draft;
  const ready =
    draft && ((draft.kind === 'multi' && draft.value.length > 0) || (draft.kind !== 'multi' && draft.value !== null));
  button.disabled = !ready;
}

function submitAnswer() {
  const question = state.current;
  const draft = state.draft;
  if (!question || !draft) return;

  const value = draft.kind === 'multi' ? draft.value : draft.value;
  try {
    state.session.answer(question.id, value);
  } catch (error) {
    state.error = error?.message ?? String(error);
    state.screen = 'error';
    render();
    return;
  }

  renderSafetyBanners(safetyRegion);
  goToNextQuestion();
  render();
  announce(state.screen === 'result' ? 'پرسش‌ها تمام شد. نتیجه نمایش داده شد.' : 'پاسخ ثبت شد.');
}

function renderEditor() {
  const answers = state.session.answers();
  const entries = Object.entries(answers);
  if (!entries.length) return null;

  const list = entries.map(([questionId, value]) => {
    const question = state.session.question(questionId);
    const conflicting = state.conflictQuestions.has(questionId);
    return el('div', { class: conflicting ? 'edit-row conflict-row' : 'edit-row' }, [
      el('div', {}, [
        el('div', { class: 'edit-question', text: question.text_fa }),
        el('div', { class: 'muted small', text: describeAnswer(question, value) }),
        conflicting
          ? el('div', { class: 'conflict-note', text: 'این پاسخ با پاسخ دیگری در تناقض است؛ یکی را اصلاح کنید.' })
          : null,
      ]),
      el('button', {
        class: 'ghost small',
        text: 'ویرایش',
        onClick: () => {
          state.session.clearAnswer(questionId);
          state.screen = 'question';
          state.current = question;
          state.draft = null;
          state.showEditor = false;
          renderSafetyBanners(safetyRegion);
          render();
        },
      }),
    ]);
  });

  return el('details', {
    class: 'editor',
    open: state.showEditor,
    onToggle: (event) => {
      state.showEditor = event.target.open;
    },
  }, [
    el('summary', { text: 'ویرایش پاسخ‌های قبلی' }),
    el('div', { class: 'edit-list' }, list),
  ]);
}

function renderQuestion() {
  const question = state.current;
  const progress = buildProgress(
    Object.keys(state.session.answers()).length,
    state.session.visibleQuestions().filter((item) => state.session.answers()[item.id] === undefined).length,
  );

  pushProgress(progress.percent);

  app.append(
    el('section', { class: 'card' }, [
      el('div', { class: 'progress' }, [
        el('div', { class: 'bar' }, [el('div', { class: 'fill', style: `width:${progress.percent}%` })]),
        el('span', { class: 'muted small', text: progress.label }),
      ]),
      el('h1', { class: 'question', tabindex: '-1', id: 'question-title', text: question.text_fa }),
      el('p', { class: 'muted small', text: question.why_fa }),
      el('form', {
        onSubmit: (event) => {
          event.preventDefault();
          submitAnswer();
        },
      }, [
        el('fieldset', {}, [
          el('legend', { class: 'sr-only', text: question.text_fa }),
          renderOptions(question),
        ]),
        el('div', { class: 'actions' }, [
          el('button', { id: 'submit-answer', class: 'primary', type: 'submit', text: 'ادامه', disabled: true }),
          el('button', {
            class: 'ghost',
            type: 'button',
            text: 'بازگشت',
            disabled: !state.session.canUndo(),
            onClick: () => {
              state.session.undo();
              state.showEditor = false;
              renderSafetyBanners(safetyRegion);
              goToNextQuestion();
              render();
            },
          }),
        ]),
      ]),
      renderEditor(),
    ]),
  );

  document.getElementById('question-title')?.focus?.();
}

function pushProgress(percent) {
  const bar = document.querySelector('.progress .fill');
  if (bar) bar.style.width = `${percent}%`;
}

function renderCard(card) {
  return el('article', { class: 'card result-card' }, [
    el('h2', { text: card.name_fa }),
    el('p', { class: 'muted small', text: 'چرا این موضوع مطرح شد:' }),
    el('ul', { class: 'reasons' }, card.reasons.map((reason) => el('li', {
      text: reason.note_fa,
    }))),
    el('p', { class: 'muted small', text: 'نشانه‌های شناسنامه و آنچه شما گفته‌اید:' }),
    el('dl', { class: 'evidence' }, card.groups.flatMap((group) => [
      el('dt', {}, [
        el('span', { class: 'group-title', text: group.label }),
        el('span', { class: 'muted small', text: ` — ${group.hint}` }),
      ]),
      el('dd', {}, group.items.length
        ? [
          el('ul', { class: 'chips' }, group.items.map((item) => el('li', { text: item.label_fa }))),
          group.key === 'conflict'
            ? el('div', { class: 'conflict-fix' }, [
              el('button', {
                class: 'ghost small',
                type: 'button',
                text: 'این دو پاسخ را اصلاح کنم',
                onClick: () => openConflictEditor(),
              }),
            ])
            : null,
        ]
        : el('span', { class: 'muted small', text: 'موردی در این گروه نیست' })),
    ])),
    card.link
      ? el('p', { class: 'topic-link' }, [
        el('a', { href: card.link, rel: 'noopener', text: 'مطالعهٔ مقالهٔ مرتبط در دانشنامه' }),
      ])
      : el('p', { class: 'muted small', text: 'پیوند مقالهٔ این موضوع تأیید نشده است؛ تا تأیید، هیچ لینکی نمایش داده نمی‌شود.' }),
  ]);
}

/** Question ids behind every contradictory pair in the current result. */
function collectConflictQuestions(cards) {
  const ids = new Set();
  for (const card of cards) {
    for (const group of card.groups) {
      if (group.key !== 'conflict') continue;
      for (const item of group.items) {
        for (const via of item.via ?? []) ids.add(via.question_id);
      }
    }
  }
  return ids;
}

/**
 * The conflict group explains what disagrees; this turns the explanation into an
 * action: open the answer list, highlight those rows and put the cursor there.
 */
function openConflictEditor() {
  state.showEditor = true;
  render();
  const row = document.querySelector('#app .edit-row.conflict-row');
  row?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
  row?.querySelector('button')?.focus?.();
  announce('پاسخ‌هایی که با هم نمی‌خوانند مشخص شدند؛ یکی را اصلاح کنید.');
}

function renderResult() {
  const result = state.session.result();
  const view = buildResultView(result, { topicLinks: TOPIC_LINKS });
  state.conflictQuestions = collectConflictQuestions(view.cards);
  pushProgress(100);

  const heading = view.showCards ? 'موضوع‌های مرتبط با پاسخ‌های شما' : 'نتیجه‌ای برای نمایش نیست';

  app.append(
    el('section', { class: 'card' }, [
      el('h1', { text: heading }),
      el('p', { class: `coverage ${view.coverage.result}`, text: view.coverage.message_fa }),
      el('p', { class: 'muted small', text: view.disclaimer }),
      el('div', { class: 'actions' }, [
        el('button', { class: 'primary', text: 'شروع دوباره', onClick: startSession }),
        el('button', {
          class: 'ghost',
          text: 'ویرایش پاسخ‌ها',
          onClick: () => {
            state.showEditor = true;
            render();
          },
        }),
      ]),
      renderEditor(),
    ]),
  );

  for (const card of view.cards) app.append(renderCard(card));

  if (!view.showCards && view.emptyMessage) {
    app.append(el('section', { class: 'card muted-card' }, [
      el('p', { text: 'این نتیجه به معنی سالم بودن نیست. اگر نگرانی داری با پزشک مشورت کن.' }),
    ]));
  }
}

// --- boot ----------------------------------------------------------------
async function boot() {
  state.screen = 'loading';
  render();
  try {
    await loadData();
    state.screen = 'intro';
  } catch (error) {
    state.error = error?.message ?? String(error);
    state.screen = 'error';
  }
  render();
}

boot();

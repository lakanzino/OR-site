/**
 * Phase-3 demo UI controller.
 *
 * Loads the phase-1 bundle and the evidence map, runs the phase-2 engine and
 * renders a demo that is explicitly labelled as non-published. Answers stay in
 * this page's memory only: no storage, no cookies, no network calls after load.
 */

import { createSession } from '../engine/src/engine.js';
import {
  AGE_DEFAULT,
  AGE_MAX,
  AGE_MIN,
  ageAfterDrag,
  ageIsSupported,
  clampAge,
  describeAge,
  formatAge,
  minAgeFrom,
  turnForAge,
} from './age-gate.js';
import { FALLBACK_HELP, QUESTION_HELP } from './question-help.js';
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

// Configuration, never a user choice: the host may ask for another ordering.
// An unknown value falls back to the order the tool has always used.
const QUESTION_STRATEGY = ['static_order', 'adaptive'].includes(config.questionStrategy)
  ? config.questionStrategy
  : 'static_order';

const BUNDLE_URL = config.bundleUrl || '../../docs/symptom-checker/phase-6/knowledge-bundle.v3.json';
const EVIDENCE_URL = config.evidenceUrl || '../engine/data/evidence-map.v1.json';
const TOPIC_LINKS = (config.topicLinks && typeof config.topicLinks === 'object') ? config.topicLinks : {};

const state = {
  // The age gate opens the tool. Memory only: it never reaches the engine and
  // never leaves the page.
  age: AGE_DEFAULT,
  minAge: minAgeFrom(config),
  bundle: null,
  evidenceMap: null,
  session: null,
  screen: 'loading',
  current: null,
  error: null,
  draft: null,
  showEditor: false,
  // Free-text notes written under the options. Memory only: they are never
  // passed to the engine and never leave this page.
  notes: {},
  noteDraft: '',
  // Active typing timer for the robot explanation, cleared on every render.
  typing: null,
  narratorStart: null,
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

// --- robot narrator -------------------------------------------------------
const SVG_NS = 'http://www.w3.org/2000/svg';

function prefersReducedMotion() {
  return typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function stopTyping() {
  if (!state.typing) return;
  window.clearInterval(state.typing.timer);
  state.typing = null;
}

/**
 * Types `text` into `target` over roughly `durationMs`, then calls `onDone`.
 * The animation is decoration only: `renderNarrator` also writes the whole
 * sentence once into an `.sr-only` paragraph, so a screen reader reads it
 * normally instead of character by character.
 */
function typeText(target, text, { durationMs = 2500, onDone } = {}) {
  stopTyping();
  const caret = el('span', { class: 'caret', 'aria-hidden': 'true' });
  target.textContent = '';
  target.append(caret);

  const finish = () => {
    stopTyping();
    target.textContent = text;
    onDone?.();
  };

  if (!text || prefersReducedMotion()) {
    finish();
    return;
  }

  // 14–38 ms per character keeps the whole sentence inside ~2.5 seconds.
  const step = Math.min(38, Math.max(14, Math.round(durationMs / Math.max(text.length, 1))));
  let index = 0;
  const timer = window.setInterval(() => {
    index += 1;
    target.textContent = text.slice(0, index);
    target.append(caret);
    if (index >= text.length) finish();
  }, step);
  state.typing = { timer, finish };
}

function skipTyping() {
  state.typing?.finish();
}

/** Inline SVG robot head — static markup, no data injected. */
function robotAvatar() {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('viewBox', '0 0 48 48');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const add = (tag, attrs) => {
    const node = document.createElementNS(SVG_NS, tag);
    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value);
    svg.append(node);
  };
  add('rect', { x: '9', y: '15', width: '30', height: '23', rx: '8', fill: '#ffffff', stroke: '#041d47', 'stroke-width': '2.4' });
  add('rect', { x: '6', y: '21', width: '4', height: '8', rx: '2', fill: '#041d47' });
  add('rect', { x: '38', y: '21', width: '4', height: '8', rx: '2', fill: '#041d47' });
  add('path', { d: 'M24 9v6', stroke: '#041d47', 'stroke-width': '2.4', 'stroke-linecap': 'round' });
  add('circle', { cx: '24', cy: '7', r: '2.8', fill: '#ff6a00' });
  add('circle', { cx: '18.5', cy: '24', r: '3.1', fill: '#0b47a1' });
  add('circle', { cx: '29.5', cy: '24', r: '3.1', fill: '#0b47a1' });
  add('rect', { x: '18', y: '31', width: '12', height: '3.4', rx: '1.7', fill: '#e85d00' });
  return svg;
}

function helpFor(question) {
  return QUESTION_HELP[question.id] ?? FALLBACK_HELP;
}

/** The "if it feels like this → pick that" list, built only from real options. */
function narratorGuide(question, help) {
  const options = question.options ?? [];
  const items = [];

  for (const option of options) {
    if (option.id === 'unknown') continue;
    const line = help.options?.[option.id];
    if (line) items.push(el('li', { text: line }));
  }
  if (!items.length) return null;

  const unknownOption = options.find((option) => option.id === 'unknown');
  if (unknownOption) {
    items.unshift(el('li', { text: 'اگر یکی از این‌ها با حالت امروزت می‌خواند، همان را انتخاب کن:' }));
    // The unknown line is styled as a rule, not as one more choice. The
    // fallback uses the real label, so the text always matches the option.
    items.push(el('li', {
      class: 'unknown-rule',
      text: help.options?.unknown
        ?? `اگر هیچ‌کدام نمی‌خواند یا شک داری، «${unknownOption.label_fa}» را انتخاب کن.`,
    }));
  }

  return el('ul', { class: 'narrator-guide' }, items);
}

function renderNarrator(question) {
  const help = helpFor(question);
  const text = help.intro;
  const guide = narratorGuide(question, help);

  const textNode = el('p', { class: 'narrator-text', 'aria-hidden': 'true' });
  const skipHolder = el('div', { class: 'narrator-skip' });
  const guideHolder = el('div');

  const node = el('div', { class: 'narrator' }, [
    el('div', { class: 'narrator-avatar' }, [robotAvatar()]),
    el('div', { class: 'narrator-body' }, [
      el('p', { class: 'narrator-title', text: 'راهنمای انتخاب گزینه' }),
      el('p', { class: 'sr-only', text }),
      textNode,
      guideHolder,
      skipHolder,
    ]),
  ]);

  state.narratorStart = () => {
    if (!guide) return;
    const skipButton = el('button', {
      type: 'button',
      text: 'نمایش کامل متن',
      onClick: () => skipTyping(),
    });
    skipHolder.append(skipButton);
    typeText(textNode, text, {
      durationMs: 2500,
      onDone: () => {
        skipHolder.textContent = '';
        guideHolder.append(guide);
      },
    });
  };

  return node;
}

// --- free-text notes ------------------------------------------------------
function syncNoteField(isUnknown) {
  const field = document.querySelector('.note-field');
  if (!field) return;
  field.classList.toggle('is-unknown', Boolean(isUnknown));
}

function renderNoteField(question) {
  const id = `note-${question.id}`;
  const textarea = el('textarea', {
    id,
    maxlength: 500,
    rows: 3,
    placeholder: 'مثلاً: دردی که فقط وقتی خم می‌شوم حس می‌شود و در گزینه‌ها نیست.',
  });
  textarea.value = state.noteDraft ?? '';
  textarea.addEventListener('input', () => {
    state.noteDraft = textarea.value;
  });

  return el('div', { class: 'note-field' }, [
    el('label', { for: id, text: 'چیزی هست که در گزینه‌ها نیست؟' }),
    textarea,
    el('p', {
      class: 'note-hint',
      text: 'این یادداشت هیچ تأثیری روی پرسش‌های بعدی، مسیر ابزار و نتیجه ندارد. جایی ارسال نمی‌شود و فقط تا پایان همین صفحه می‌ماند.',
    }),
  ]);
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
  // A 2.x bundle carries its evidence inside each option; handing it the old
  // external map would be a second, older truth.
  const externalMap = String(state.bundle?.schema_version ?? '').startsWith('2.') ? null : state.evidenceMap;
  state.session = createSession(state.bundle, {
    evidenceMap: externalMap,
    questionStrategy: QUESTION_STRATEGY,
  });
  state.error = null;
  state.showEditor = false;
  state.conflictQuestions = new Set();
  state.notes = {};
  state.noteDraft = '';
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
  stopTyping();
  state.narratorStart = null;
  clear(app);
  if (state.screen === 'loading') return renderLoading();
  if (state.screen === 'error') return renderError();
  if (state.screen === 'intro') return renderIntro();
  if (state.screen === 'underage') return renderUnderage();
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

  // The turning hologram stands above the card, on its own, so it can never sit
  // on top of the title (it did, on a phone, until 0.1.11). Decoration only.
  app.append(el('figure', { class: 'hero-stage', 'aria-hidden': 'true' }, [
    el('div', { class: 'hero-figure' }),
  ]));

  app.append(el('section', { class: 'card intro-card' }, [
    el('h1', { text: 'همیار نشانه‌ها — نمونهٔ نمایشی' }),
    el('p', { class: 'badge-line' }, [
      el('span', { class: 'badge warn', text: 'نمایشی' }),
      el('span', { class: 'badge', text: 'دادهٔ پیش‌نویس، منتشر نشده' }),
      el('span', { class: 'badge', text: 'بدون ذخیره‌سازی' }),
    ]),
    el('p', {
      class: 'notice-card',
      role: 'note',
      text: 'این برگه پیش‌نمایش است و دادهٔ آن پیش‌نویس و منتشرنشده؛ پاسخ‌ها ذخیره یا ارسال نمی‌شوند.',
    }),
    el('p', { class: 'lead', text: disclaimers.before_start_fa ?? '' }),
    el('ul', { class: 'facts' }, [
      el('li', { text: 'پاسخ‌ها فقط در حافظهٔ همین صفحه می‌مانند و با بستن صفحه پاک می‌شوند.' }),
      el('li', { text: 'خروجی، «موضوع‌های مرتبط» است؛ نه تشخیص، نه احتمال و نه درصد.' }),
      el('li', { text: 'اگر درد شدید یا بدحالی داری، منتظر پایان پرسش‌ها نمان و کمک پزشکی بگیر.' }),
    ]),
    renderAgeGate(),
    el('button', {
      class: 'primary start-button',
      text: 'شروع پرسش‌ها',
      onClick: () => {
        // The bundle covers adults only. This is the one place the age decides
        // anything, and it decides between «continue» and «not built for you».
        if (!ageIsSupported(state.age, state.minAge)) {
          state.screen = 'underage';
          render();
          return;
        }
        startSession();
      },
    }),
  ]));
}

/**
 * The hand-turned dial that sets the age.
 *
 * A gear, not a number field: the visitor drags it, scrolls it, taps ± or uses
 * the arrow keys, and the readout moves with it. `role="slider"` is what makes
 * it work for a screen reader and a keyboard; the drag is the extra, not the
 * only way in.
 */
function renderAgeGate() {
  const dial = el('div', {
    class: 'age-dial',
    role: 'slider',
    tabindex: '0',
    'aria-label': 'سن',
    'aria-valuemin': String(AGE_MIN),
    'aria-valuemax': String(AGE_MAX),
    'aria-valuenow': String(state.age),
    'aria-valuetext': describeAge(state.age),
  }, [
    el('span', { class: 'age-dial__gear', 'aria-hidden': 'true' }),
    el('span', { class: 'age-dial__readout' }, [
      el('span', { class: 'age-dial__value', text: formatAge(state.age) }),
      el('span', { class: 'age-dial__unit', text: 'سال' }),
    ]),
  ]);

  const valueNode = dial.querySelector('.age-dial__value');

  const paint = (next) => {
    state.age = clampAge(next);
    valueNode.textContent = formatAge(state.age);
    dial.setAttribute('aria-valuenow', String(state.age));
    dial.setAttribute('aria-valuetext', describeAge(state.age));
    dial.style.setProperty('--age-turn', `${turnForAge(state.age)}deg`);
  };

  const pointerAngle = (event) => {
    const box = dial.getBoundingClientRect();
    const dx = event.clientX - (box.left + box.width / 2);
    const dy = event.clientY - (box.top + box.height / 2);
    // 0° at the top, clockwise — the way the gear is drawn.
    return (Math.atan2(dx, -dy) * 180) / Math.PI;
  };

  let drag = null;
  dial.addEventListener('pointerdown', (event) => {
    if (event.button !== undefined && event.button !== 0) return;
    drag = { angle: pointerAngle(event), age: state.age };
    if (typeof dial.setPointerCapture === 'function') {
      try {
        dial.setPointerCapture(event.pointerId);
      } catch {
        // A pointer that vanished mid-press is not worth failing over.
      }
    }
    event.preventDefault();
  });
  dial.addEventListener('pointermove', (event) => {
    if (!drag) return;
    paint(ageAfterDrag(drag.age, drag.angle, pointerAngle(event)));
    event.preventDefault();
  });
  const endDrag = () => {
    drag = null;
  };
  dial.addEventListener('pointerup', endDrag);
  dial.addEventListener('pointercancel', endDrag);
  dial.addEventListener('pointerleave', (event) => {
    if (event.buttons === 0) endDrag();
  });

  dial.addEventListener('wheel', (event) => {
    paint(state.age + (event.deltaY < 0 ? 1 : -1));
    event.preventDefault();
  }, { passive: false });

  dial.addEventListener('keydown', (event) => {
    const step = { ArrowUp: 1, ArrowRight: 1, PageUp: 5, ArrowDown: -1, ArrowLeft: -1, PageDown: -5 }[event.key];
    if (step) {
      paint(state.age + step);
      event.preventDefault();
      return;
    }
    if (event.key === 'Home') {
      paint(AGE_MIN);
      event.preventDefault();
    } else if (event.key === 'End') {
      paint(AGE_MAX);
      event.preventDefault();
    }
  });

  const stepButton = (delta, label, text) => el('button', {
    type: 'button',
    class: 'age-step',
    text,
    'aria-label': label,
    onClick: () => paint(state.age + delta),
  });

  paint(state.age);

  return el('div', { class: 'age-gate' }, [
    el('h2', { class: 'age-gate__title', text: 'اول سنت را تنظیم کن' }),
    el('p', {
      class: 'age-gate__lead',
      text: 'چرخ‌دنده را با انگشت بچرخان یا از دکمه‌ها استفاده کن؛ عدد بالای آن تغییر می‌کند.',
    }),
    el('div', { class: 'age-gate__body' }, [
      dial,
      el('div', { class: 'age-gate__side' }, [
        stepButton(1, 'یک سال بیشتر', '+'),
        stepButton(-1, 'یک سال کمتر', '−'),
      ]),
    ]),
    el('p', {
      class: 'age-gate__note',
      text: 'سن فقط در حافظهٔ همین صفحه می‌ماند؛ نه ذخیره می‌شود و نه ارسال.',
    }),
  ]);
}

function renderUnderage() {
  const scope = state.bundle?.scope ?? {};
  const exclusion = Array.isArray(scope.excluded_by_design)
    ? scope.excluded_by_design.find((line) => typeof line === 'string' && /۱۸|کودک|نوجوان/.test(line))
    : null;

  app.append(el('section', { class: 'card stop-card' }, [
    el('h1', { text: 'این ابزار برای بزرگسالان است' }),
    el('p', { class: 'lead', text: `سن واردشده ${formatAge(state.age)} سال است. ${exclusion ?? 'این بسته کودکان و نوجوانان زیر ۱۸ سال را پوشش نمی‌دهد.'}` }),
    el('p', { text: 'الگوی نشانه‌ها در کودک و نوجوان می‌تواند متفاوت باشد؛ لطفاً با پزشک یا مرکز درمانی تماس بگیر.' }),
    el('p', { class: 'muted', text: 'اگر درد شدید یا بدحالی وجود دارد، همین حالا کمک پزشکی بگیر.' }),
    el('button', { class: 'ghost', text: 'تغییر سن', onClick: () => { state.screen = 'intro'; render(); } }),
  ]));
}


// --- gates: sex, body map, area ------------------------------------------
//
// The first three screens of the tool are questions like any other — the
// engine validates them and they gate what is asked next — but they are drawn
// as cards and as a body, not as a list of radio buttons. Nothing here scores
// or routes anything: the answer is stored by the engine, and the bundle
// decides which questions become visible.

const ZONES = {
  head_neck: { color: '#6d4aff' },
  torso: { color: '#0a74d6' },
  pelvis: { color: '#d81b60' },
  arms: { color: '#d27d00' },
  legs: { color: '#0f7a63' },
};

const ZONE_OF_AREA = {
  head: 'head_neck', throat: 'head_neck',
  chest: 'torso', abdomen: 'torso', back: 'torso',
  urinary: 'pelvis', penis: 'pelvis', scrotum: 'pelvis', vulva: 'pelvis', genital: 'pelvis', anus: 'pelvis',
  arm: 'arms', hand: 'arms',
  thigh_knee: 'legs', lower_foot: 'legs',
};

const isGateQuestion = (question) => question.id === 'q_sex'
  || question.id === 'q_region'
  || question.id.startsWith('q_area_');

function svgEl(tag, attrs) {
  const node = document.createElementNS(SVG_NS, tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (value !== undefined && value !== null) node.setAttribute(key, String(value));
  }
  return node;
}

/**
 * One selectable card. `role="radio"` inside a `role="radiogroup"` is what makes
 * the picture and the list behave like the radio buttons they are, for a screen
 * reader and for the keyboard alike.
 */
function gateCard(option, { color, title } = {}) {
  const button = el('button', {
    type: 'button',
    class: 'gate-option',
    role: 'radio',
    'aria-checked': 'false',
    style: color ? `--zone:${color}` : undefined,
  }, [
    el('span', { class: 'gate-title', text: title ?? option.label_fa }),
  ]);
  button.dataset.optionId = option.id;
  return button;
}

function gateGroup(question, build, onSelect) {
  const group = el('div', {
    class: 'gate-cards',
    role: 'radiogroup',
    'aria-labelledby': 'question-title',
  });
  group.dataset.questionId = question.id;
  const cards = new Map();
  const labels = new Map(question.options.map((option) => [option.id, option.label_fa]));

  const select = (id) => {
    state.draft = { kind: 'single', value: id };
    for (const [optionId, button] of cards) {
      const on = optionId === id;
      button.setAttribute('aria-checked', on ? 'true' : 'false');
      button.classList.toggle('selected', on);
    }
    updateSubmitState();
    announce(`${labels.get(id) ?? id} انتخاب شد`);
    onSelect?.(id);
  };

  for (const option of question.options) {
    const { button, extra } = build(option);
    button.addEventListener('click', () => select(option.id));
    cards.set(option.id, button);
    group.append(extra ?? button);
  }

  // Arrow keys move between the cards, like a native radio group.
  group.addEventListener('keydown', (event) => {
    const keys = ['ArrowDown', 'ArrowRight', 'ArrowLeft', 'ArrowUp'];
    if (!keys.includes(event.key)) return;
    const ids = question.options.map((option) => option.id);
    const current = state.draft?.value;
    const index = Math.max(0, ids.indexOf(current));
    const step = (event.key === 'ArrowDown' || event.key === 'ArrowRight') ? 1 : -1;
    const nextId = ids[(index + step + ids.length) % ids.length];
    event.preventDefault();
    select(nextId);
    cards.get(nextId)?.focus?.();
  });

  return group;
}

function renderSexGate(question) {
  return gateGroup(question, (option) => {
    const color = option.id === 'female' ? '#a5246b' : (option.id === 'male' ? '#1c5fae' : '#607089');
    const button = gateCard(option, { color });
    const hint = option.id === 'prefer_not'
      ? 'پرسش‌های عمومی همین ناحیه پرسیده می‌شود'
      : 'پرسش‌های همین بدن نمایش داده می‌شود';
    button.append(el('span', { class: 'gate-hint', text: hint }));
    return { button };
  });
}

/** The picture: five zones, one colour each. Decoration for the eye, buttons for the hand. */
function bodyFigure(question, onPick) {
  const svg = svgEl('svg', {
    viewBox: '0 0 200 420',
    class: 'body-figure',
    'aria-hidden': 'true',
    focusable: 'false',
  });

  const shapes = {
    legs: [
      svgEl('rect', { x: 62, y: 252, width: 34, height: 148, rx: 10 }),
      svgEl('rect', { x: 104, y: 252, width: 34, height: 148, rx: 10 }),
    ],
    arms: [
      svgEl('path', { d: 'M64 88 L42 246 L60 250 L78 100 Z' }),
      svgEl('path', { d: 'M136 88 L158 246 L140 250 L122 100 Z' }),
    ],
    torso: [svgEl('path', { d: 'M68 84 H132 L142 206 H58 Z' })],
    pelvis: [svgEl('rect', { x: 56, y: 206, width: 88, height: 48, rx: 12 })],
    head_neck: [
      svgEl('circle', { cx: 100, cy: 42, r: 27 }),
      svgEl('rect', { x: 88, y: 66, width: 24, height: 18, rx: 6 }),
    ],
  };

  const groups = new Map();
  for (const zoneId of ['legs', 'arms', 'torso', 'pelvis', 'head_neck']) {
    const group = svgEl('g', { class: 'zone-shape', 'data-zone': zoneId, style: `--zone:${ZONES[zoneId].color}` });
    for (const shape of shapes[zoneId]) group.append(shape);
    group.addEventListener('click', () => onPick(zoneId));
    svg.append(group);
    groups.set(zoneId, group);
  }
  return { svg, groups };
}

function renderRegionGate(question) {
  let groups = new Map();
  const highlight = (picked) => {
    for (const [zoneId, node] of groups) {
      node.classList.toggle('selected', zoneId === picked);
      node.classList.toggle('dimmed', Boolean(picked) && zoneId !== picked);
    }
  };

  // Clicking a shape on the picture is the same gesture as clicking its card:
  // the card owns the answer, the picture follows it.
  const figure = bodyFigure(question, (zoneId) => {
    document.querySelector(`#app .gate-option[data-zone-id="${zoneId}"]`)?.click?.();
  });
  groups = figure.groups;
  highlight(undefined);

  // Clicking a shape and clicking a card do the same thing, and the picture
  // follows the choice. No observer: the selection callback is enough.
  const group = gateGroup(question, (option) => {
    const zone = ZONES[option.id]?.color;
    const button = gateCard(option, { color: zone });
    button.dataset.zoneId = option.id;
    button.prepend(el('span', { class: 'zone-dot', style: `--zone:${zone}` }));
    return { button };
  }, highlight);

  return el('div', { class: 'body-map' }, [
    figure.svg,
    el('div', { class: 'zone-list' }, [
      el('p', { class: 'muted small body-hint', text: 'روی بخش بدن بزن یا از این فهرست انتخاب کن. رنگ‌ها فقط برای جدا کردن بخش‌ها هستند.' }),
      group,
    ]),
  ]);
}

function renderAreaGate(question) {
  return gateGroup(question, (option) => {
    const zone = ZONE_OF_AREA[option.id];
    const button = gateCard(option, { color: ZONES[zone]?.color });
    button.prepend(el('span', { class: 'zone-dot', style: `--zone:${ZONES[zone]?.color ?? '#607089'}` }));
    return { button };
  });
}

function renderGateScreen(question) {
  if (question.id === 'q_sex') return renderSexGate(question);
  if (question.id === 'q_region') return renderRegionGate(question);
  return renderAreaGate(question);
}

/** «۳۲ سال · مرد · لگن و اندام تناسلی» — the path so far, in words. */
function renderCrumbs() {
  if (!state.session) return null;
  const answers = state.session.answers();
  const parts = [`${formatAge(state.age)} سال`];
  const gateIds = ['q_sex', 'q_region', 'q_area_head', 'q_area_torso', 'q_area_pelvis_m',
    'q_area_pelvis_f', 'q_area_pelvis_x', 'q_area_arms', 'q_area_legs'];
  for (const id of gateIds) {
    const value = answers[id];
    if (value === undefined) continue;
    const label = state.session.question(id).options?.find((option) => option.id === value)?.label_fa;
    if (label) parts.push(label);
  }
  if (parts.length === 1) return null;
  return el('div', { class: 'crumbs' }, parts.map((part) => el('span', { class: 'crumb', text: part })));
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
    syncNoteField(unknown.checked);
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
        syncNoteField(values.includes('unknown'));
      } else {
        state.draft = { kind: 'single', value: option.id };
        syncNoteField(option.id === 'unknown');
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

  // The free-text note is kept beside the answer for the closing summary only.
  // It is never passed to session.answer(): the engine sees the option alone.
  const note = (state.noteDraft ?? '').trim();
  if (note) state.notes[question.id] = note;
  else delete state.notes[question.id];
  state.noteDraft = '';

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
          state.noteDraft = state.notes[questionId] ?? '';
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
      renderCrumbs(),
      el('h1', { class: 'question', tabindex: '-1', id: 'question-title', text: question.text_fa }),
      el('p', { class: 'muted small', text: question.why_fa }),
      isGateQuestion(question) ? null : renderNarrator(question),
      el('form', {
        onSubmit: (event) => {
          event.preventDefault();
          submitAnswer();
        },
      }, [
        el('fieldset', {}, [
          el('legend', { class: 'sr-only', text: question.text_fa }),
          isGateQuestion(question) ? renderGateScreen(question) : renderOptions(question),
        ]),
        isGateQuestion(question) ? null : renderNoteField(question),
        el('div', { class: 'actions' }, [
          el('button', { id: 'submit-answer', class: 'primary', type: 'submit', text: 'ادامه', disabled: true }),
          el('button', {
            class: 'ghost',
            type: 'button',
            text: 'بازگشت',
            disabled: !state.session.canUndo(),
            onClick: () => {
              if (state.current) delete state.notes[state.current.id];
              state.noteDraft = '';
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
  state.narratorStart?.();
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

function renderNotesSummary() {
  const entries = Object.entries(state.notes);
  if (!entries.length) return null;

  return el('section', { class: 'card notes-summary' }, [
    el('h2', { text: 'یادداشت‌های شما' }),
    el('p', {
      class: 'small',
      text: 'این‌ها همان چیزهایی است که در گزینه‌ها نبود. نه پرسشی را تغییر داده‌اند و نه در نتیجه اثری داشته‌اند.',
    }),
    el('ul', {}, entries.map(([questionId, note]) => {
      const question = state.session.question(questionId);
      return el('li', {}, [
        el('strong', { text: `${question?.text_fa ?? questionId}: ` }),
        el('span', { text: note }),
      ]);
    })),
    el('p', {
      class: 'small muted',
      text: 'در این نسخه یادداشت‌ها هیچ‌جا ارسال یا ذخیره نمی‌شوند و با بستن صفحه پاک می‌شوند.',
    }),
  ]);
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

  const notesSummary = renderNotesSummary();
  if (notesSummary) app.append(notesSummary);

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

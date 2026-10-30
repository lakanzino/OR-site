/* Symptom checker UI (test space). Renders with textContent only. */
(function () {
  'use strict';
  var root = document.getElementById('hj-sc-app');
  if (!root || !window.SymptomEngine) return;

  var AGE_LABELS = { child: 'کودک', teen: 'نوجوان', adult: 'بزرگسال', elderly: 'سالمند' };
  var emergencyNumber = root.getAttribute('data-emergency') || '۱۱۵';

  var state = { age: null, sex: null, region: null, redFlags: {}, tags: {} };
  var vocab = null;
  var diseases = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = String(text);
    return n;
  }

  function button(label, cls, onClick) {
    var b = el('button', 'hj-sc-btn ' + (cls || ''), label);
    b.type = 'button';
    b.addEventListener('click', onClick);
    return b;
  }

  function answerRow(onAnswer) {
    var row = el('div', 'hj-sc-row');
    row.appendChild(button('بله', 'is-yes', function () { onAnswer('yes'); }));
    row.appendChild(button('خیر', 'is-no', function () { onAnswer('no'); }));
    row.appendChild(button('نمی‌دانم', 'is-unknown', function () { onAnswer('unknown'); }));
    return row;
  }

  function askedCount() {
    return Object.keys(state.tags).length;
  }

  function render() {
    root.textContent = '';
    if (!state.age) return renderAge();
    if (!state.region) return renderRegion();
    var step = window.SymptomEngine.nextStep(vocab, diseases, state);
    if (step.type === 'redFlag') return renderRedFlag(step.question);
    if (step.type === 'tag') return renderTag(step.tag);
    return renderResult(step.result);
  }

  function renderAge() {
    root.appendChild(el('p', 'hj-sc-step', 'مرحله ۱ از ۳ · سن'));
    root.appendChild(el('h2', 'hj-sc-question', 'سن شما چند سال است؟'));
    var grid = el('div', 'hj-sc-grid');
    vocab.ageGroups.forEach(function (a) {
      grid.appendChild(button(AGE_LABELS[a] || a, '', function () {
        state.age = a;
        renderSex();
      }));
    });
    root.appendChild(grid);
  }

  function renderSex() {
    root.textContent = '';
    root.appendChild(el('p', 'hj-sc-step', 'مرحله ۱ از ۳ · جنسیت'));
    root.appendChild(el('h2', 'hj-sc-question', 'جنسیت‌تان چیست؟ (برای فیلتر بیماری‌های مرتبط)'));
    var grid = el('div', 'hj-sc-grid');
    grid.appendChild(button('زن', '', function () { state.sex = 'female'; render(); }));
    grid.appendChild(button('مرد', '', function () { state.sex = 'male'; render(); }));
    grid.appendChild(button('ترجیح می‌دهم نگویم', 'is-ghost', function () { state.sex = null; render(); }));
    root.appendChild(grid);
  }

  function renderRegion() {
    root.appendChild(el('p', 'hj-sc-step', 'مرحله ۲ از ۳ · ناحیهٔ اصلی'));
    root.appendChild(el('h2', 'hj-sc-question', 'مشکل اصلی‌تان کجاست؟'));
    var grid = el('div', 'hj-sc-grid');
    vocab.regions.forEach(function (r) {
      grid.appendChild(button(r.name, '', function () {
        state.region = r.id;
        render();
      }));
    });
    root.appendChild(grid);
  }

  function renderRedFlag(rf) {
    root.appendChild(el('p', 'hj-sc-step', 'ایمنی · پرسش فوری'));
    root.appendChild(el('h2', 'hj-sc-question', rf.question));
    root.appendChild(answerRow(function (a) {
      state.redFlags[rf.id] = a;
      render();
    }));
  }

  function renderTag(tag) {
    root.appendChild(el('p', 'hj-sc-step', 'مرحله ۳ از ۳ · پرسش ' + (askedCount() + 1) + ' · ' + tag.label));
    root.appendChild(el('h2', 'hj-sc-question', tag.question));
    root.appendChild(answerRow(function (a) {
      state.tags[tag.id] = a;
      render();
    }));
  }

  function renderEmergency() {
    var alert = el('div', 'hj-sc-alert is-emergency');
    alert.appendChild(el('strong', '', 'بر اساس پاسخ‌های شما، نیاز به ارزیابی فوری پزشکی است.'));
    alert.appendChild(el('p', '', 'همین حالا با اورژانس ' + emergencyNumber + ' تماس بگیرید یا به نزدیک‌ترین اورژانس بروید. این ابزار را برای تأخیر در مراجعه استفاده نکنید.'));
    root.appendChild(alert);
    root.appendChild(el('p', 'hj-sc-disclaimer', 'این ابزار تشخیص پزشکی نمی‌دهد و جایگزین مراجعه‌ی فوری نیست.'));
    root.appendChild(button('شروع دوباره', 'is-ghost', restart));
  }

  function renderCandidate(c, i) {
    var card = el('article', 'hj-sc-card');
    var head = el('div', 'hj-sc-card__head');
    head.appendChild(el('span', 'hj-sc-rank', '#' + (i + 1)));
    head.appendChild(el('h3', '', c.nameFa + ' (' + c.nameEn + ')'));
    head.appendChild(el('span', 'hj-sc-prob', c.matchLabelFa));
    card.appendChild(head);
    if (c.reviewStatus !== 'published') {
      card.appendChild(el('p', 'hj-sc-draft', 'پیش‌نویس: هنوز بازبینی پزشکی نشده است.'));
    }
    if (c.matched.length) {
      card.appendChild(el('p', 'hj-sc-muted', 'با پاسخ‌های شما همخوان: ' + c.matched.join('، ')));
    }
    if (c.missingCore.length) {
      card.appendChild(el('p', 'hj-sc-muted', 'برای بررسی بیشتر، این موارد هنوز پرسیده نشده: ' + c.missingCore.join('، ')));
    }
    if (c.symptoms.length) {
      card.appendChild(el('h4', '', 'علائم رایج'));
      var ul = el('ul', 'hj-sc-list');
      c.symptoms.forEach(function (s) { ul.appendChild(el('li', '', s)); });
      card.appendChild(ul);
    }
    if (c.whenToSeeDoctor.length) {
      card.appendChild(el('h4', '', 'چه زمانی فوراً مراجعه کنید'));
      var ul2 = el('ul', 'hj-sc-list is-warn');
      c.whenToSeeDoctor.forEach(function (s) { ul2.appendChild(el('li', '', s)); });
      card.appendChild(ul2);
    }
    if (c.specialty) {
      card.appendChild(el('p', 'hj-sc-muted', 'تخصص پیشنهادی: ' + c.specialty));
    }
    if (c.sources.length) {
      card.appendChild(el('p', 'hj-sc-muted', 'منابع: ' + c.sources.map(function (s) { return s.publisher; }).join('، ')));
    }
    return card;
  }

  function renderResult(result) {
    root.appendChild(el('p', 'hj-sc-step', 'نتیجهٔ آزمایشی'));
    if (result.emergency) return renderEmergency();

    if (result.cautionUnknown) {
      root.appendChild(el('div', 'hj-sc-alert is-caution',
        'برخی پرسش‌های ایمنی را مطمئن نبودید. اگر علائمتان شدید است یا بدتر می‌شود، همین حالا با تیم درمان یا اورژانس ' + emergencyNumber + ' تماس بگیرید.'));
    }

    if (result.status === 'insufficient') {
      root.appendChild(el('p', 'hj-sc-muted', 'اطلاعات این مجموعه برای مقایسه‌ی علائم شما کافی نیست. با پزشک مشورت کنید.'));
    } else if (!result.candidates.length) {
      root.appendChild(el('p', 'hj-sc-muted', 'با پاسخ‌های داده‌شده، فرضیهٔ قابل‌اتکایی پیدا نشد. با پزشک یا متخصص مشورت کنید.'));
    } else if (!result.confident) {
      root.appendChild(el('p', 'hj-sc-muted', 'اطمینان این نتیجه پایین است. برای بررسی دقیق با پزشک مشورت کنید.'));
    }

    result.candidates.forEach(function (c, i) {
      root.appendChild(renderCandidate(c, i));
    });

    root.appendChild(el('p', 'hj-sc-disclaimer', 'این ابزار آزمایشی و آموزشی است. تشخیص پزشکی نمی‌دهد، جایگزین ویزیت پزشک نیست و تصمیم درمانی را نباید بر اساس آن گرفت.'));
    root.appendChild(button('شروع دوباره', 'is-ghost', restart));
  }

  function restart() {
    state = { age: null, sex: null, region: null, redFlags: {}, tags: {} };
    render();
  }

  function load() {
    var base = root.getAttribute('data-base');
    Promise.all([
      fetch(base + '/vocab.json').then(function (r) { return r.json(); }),
      fetch(base + '/diseases.json').then(function (r) { return r.json(); }),
    ]).then(function (parts) {
      vocab = parts[0];
      diseases = parts[1].diseases;
      render();
    }).catch(function () {
      root.textContent = 'بارگذاری داده‌ها انجام نشد. لطفاً صفحه را دوباره باز کنید.';
    });
  }

  load();
})();

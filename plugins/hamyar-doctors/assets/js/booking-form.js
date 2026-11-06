/* Patient booking: server is authoritative for slots, revisions and validation. */
(() => {
  'use strict';
  const digits = value => value.replace(/[۰-۹٠-٩]/g, c => String(c.charCodeAt(0) - (c >= '۰' && c <= '۹' ? 1776 : 1632)));
  const validNational = value => {
    if (!/^\d{10}$/.test(value) || /^(\d)\1{9}$/.test(value)) return false;
    let sum = 0; for (let i = 0; i < 9; i++) sum += Number(value[i]) * (10 - i);
    const r = sum % 11; return Number(value[9]) === (r < 2 ? r : 11 - r);
  };
  const uuid = () => {
    const a = new Uint8Array(16); crypto.getRandomValues(a); a[6] = (a[6] & 15) | 64; a[8] = (a[8] & 63) | 128;
    const h = Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;
  };
  for (const root of document.querySelectorAll('.hj-booking')) {
    const storageKey = 'hj_booking_pending_v1:' + root.dataset.page;
    const form = root.querySelector('.hj-booking__form'), doctor = form.elements.doctor_id, date = form.elements.date;
    const list = root.querySelector('.hj-booking__slots'), status = root.querySelector('.hj-booking__status'), load = root.querySelector('.hj-booking__load'), submit = form.querySelector('[type=submit]');
    const calendar = root.querySelector('.hj-booking__calendar');
    const calendarDays = calendar && calendar.querySelector('.hj-booking__calendar-days');
    const calendarStatus = calendar && calendar.querySelector('.hj-booking__calendar-status');
    const calendarRetry = calendar && calendar.querySelector('.hj-booking__calendar-retry');
    const dateLabel = calendar && root.querySelector('.hj-booking__date');
    const review = root.querySelector('.hj-booking__review');
    const reviewSummary = review && review.querySelector('.hj-booking__review-summary');
    let reviewConfirmed = false, reviewSignature = null;
    function hideReview() { if (review) review.hidden = true; reviewConfirmed = false; reviewSignature = null; }
    const say = message => { status.textContent = message; };
    let selection = null, pending = null, intent = null, busy = false, calendarLoading = false, calendarSequence = 0;
    try { intent = JSON.parse(root.dataset.intent || 'null'); } catch (_) { /* ignore malformed local input */ }
    try { pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (_) { /* storage is unavailable */ }
    if (!pending || !/^\d+$/.test(String(pending.doctor)) || !/^\d{4}-\d{2}-\d{2}$/.test(pending.date || '') || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(pending.start || '') || !Number.isSafeInteger(pending.revision) || !/^[a-f0-9-]{36}$/.test(pending.key || '')) pending = null;
    async function post(action, values) {
      const body = new URLSearchParams({action, nonce: root.dataset.nonce, ...values});
      let response, json;
      try {
        response = await fetch(root.dataset.ajax, {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body});
        json = await response.json();
      } catch (_) { const error = new Error('نتیجه مشخص نیست؛ با همین اطلاعات دوباره تلاش کنید.'); error.code = 'unknown'; throw error; }
      if (!response.ok || !json.success) { const error = new Error(json.data?.message || 'خطا در ارتباط با سامانه.'); error.code = json.data?.code || 'unknown'; throw error; }
      return json.data;
    }
    function calendarControls() {
      if (!calendar) return;
      calendarRetry.disabled = busy || !!pending || calendarLoading;
      for (const button of calendarDays.querySelectorAll('button')) button.disabled = busy || !!pending || calendarLoading || button.dataset.selectable !== '1';
    }
    const setBusy = state => { busy = state; load.disabled = state || !!pending; submit.disabled = state; calendarControls(); };
    function lockPending() { doctor.disabled = !!pending || root.dataset.fixed === '1'; date.disabled = !!pending; load.disabled = !!pending; for (const button of list.querySelectorAll('button')) button.disabled = !!pending; if (review) submit.textContent = pending ? 'تکرار درخواست قبلی' : 'مرور درخواست نوبت'; calendarControls(); }
    async function loadCalendar() {
      if (!calendar || pending) return;
      const seq = ++calendarSequence, requestedDoctor = doctor.value;
      calendarDays.replaceChildren(); dateLabel.hidden = false; load.hidden = false;
      if (!requestedDoctor) { calendarLoading = false; calendarStatus.textContent = 'ابتدا جراح را انتخاب کنید.'; calendarControls(); return; }
      calendarLoading = true; calendarControls(); calendarStatus.textContent = 'در حال دریافت روزها…';
      try {
        const result = await post('hj_booking_calendar', {doctor_id:requestedDoctor});
        if (seq !== calendarSequence || doctor.value !== requestedDoctor) return;
        const states = {closed:'تعطیل',full:'تکمیل',past:'گذشته',unavailable:'غیرقابل انتخاب',limited:'جای خالی',available:'جای خالی'};
        if (!Array.isArray(result.days) || result.days.length !== 14 || result.days.some(d => !/^\d{4}-\d{2}-\d{2}$/.test(d.date) || !Object.prototype.hasOwnProperty.call(states, d.state) || !['blue','yellow','green'].includes(d.color) || !Number.isSafeInteger(d.remaining) || d.remaining < 0)) throw new Error('اطلاعات تقویم معتبر نیست؛ دوباره تلاش کنید.');
        let formatter;
        try { formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {weekday:'short',day:'numeric',month:'long',timeZone:'UTC'}); if (formatter.resolvedOptions().calendar !== 'persian') formatter = null; } catch (_) { formatter = null; }
        for (const day of result.days) {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'hj-booking__day hj-booking__day--' + day.color;
          const label = formatter ? formatter.format(new Date(day.date + 'T12:00:00Z')) : day.date;
          const available = day.state === 'available' || day.state === 'limited';
          button.textContent = label + ' — ' + (available ? day.remaining.toLocaleString('fa-IR') + ' جای خالی' : states[day.state]);
          button.dataset.selectable = available ? '1' : '0'; button.disabled = !available; button.setAttribute('aria-pressed','false');
          button.addEventListener('click', () => {
            if (busy || pending || calendarLoading || !available) return;
            for (const other of calendarDays.querySelectorAll('button')) other.setAttribute('aria-pressed','false');
            button.setAttribute('aria-pressed','true');
            date.value = day.date; date.dispatchEvent(new Event('change', {bubbles:true})); loadSlots(null);
          });
          calendarDays.append(button);
        }
        dateLabel.hidden = true; load.hidden = true; calendarStatus.textContent = 'روز موردنظر را انتخاب کنید.';
      } catch (e) {
        if (seq === calendarSequence && doctor.value === requestedDoctor) { calendarStatus.textContent = e.message || 'تقویم در دسترس نیست؛ دوباره تلاش کنید.'; }
      } finally {
        if (seq === calendarSequence) { calendarLoading = false; calendarControls(); }
      }
    }
    function clearPending() { pending = null; try { sessionStorage.removeItem(storageKey); } catch (_) {} lockPending(); }
    function selectedMessage() { say(selection ? `زمان انتخاب‌شده: ${selection.label || selection.start}` : 'لطفاً یک زمان خالی انتخاب کنید.'); }
    async function loadSlots(restoring) {
      if (busy || pending) return;
      hideReview();
      if (!doctor.value || !date.value) { say('جراح و تاریخ را انتخاب کنید.'); return; }
      if (calendar && !restoring && (date.value < date.min || date.value > date.max)) { say('تاریخی از ۱۴ روز آینده انتخاب کنید.'); return; }
      const requestedDoctor = doctor.value, requestedDate = date.value;
      selection = null; list.replaceChildren(); setBusy(true); say('در حال دریافت زمان‌های خالی…');
      try {
        const result = await post('hj_booking_slots', {doctor_id:requestedDoctor, date:requestedDate});
        if (doctor.value !== requestedDoctor || date.value !== requestedDate) { say('انتخاب تغییر کرد؛ زمان‌ها را دوباره دریافت کنید.'); return; }
        for (const slot of result.slots) {
          const button = document.createElement('button'); button.type = 'button'; button.textContent = slot.label; button.className = 'hj-booking__slot';
          button.addEventListener('click', () => { if (busy || pending) return; for (const b of list.querySelectorAll('button')) b.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-pressed', 'true'); selection = {doctor:requestedDoctor, date:requestedDate, start:slot.start, revision:result.revision, key:uuid(), label:slot.label}; selectedMessage(); });
          button.setAttribute('aria-pressed', 'false'); list.append(button);
          if (restoring && String(restoring.doctor) === requestedDoctor && restoring.start === slot.start && restoring.revision === result.revision) {
            button.setAttribute('aria-pressed', 'true'); selection = {doctor:requestedDoctor, date:requestedDate, start:slot.start, revision:result.revision, key:restoring.key, label:slot.label};
          }
        }
        if (selection) selectedMessage();
        else if (restoring) say('زمان یا ساعات قبلی تغییر کرده است؛ لطفاً دوباره انتخاب کنید.');
        else say(result.slots.length ? 'یکی از زمان‌های خالی را انتخاب کنید.' : 'برای این تاریخ زمان خالی وجود ندارد.');
      } catch (e) { say(e.message || 'خطا در دریافت زمان‌ها.'); }
      finally { setBusy(false); }
    }
    doctor.addEventListener('change', () => { hideReview(); selection = null; list.replaceChildren(); say('برای جراح انتخاب‌شده زمان‌ها را دریافت کنید.'); loadCalendar(); });
    date.addEventListener('change', () => { hideReview(); selection = null; list.replaceChildren(); say('برای تاریخ انتخاب‌شده زمان‌ها را دریافت کنید.'); });
    load.addEventListener('click', () => loadSlots(null));
    if (calendar) calendarRetry.addEventListener('click', () => loadCalendar());
    if (review) {
      review.querySelector('.hj-booking__edit').addEventListener('click', hideReview);
      review.querySelector('.hj-booking__confirm').addEventListener('click', () => { if (!busy && !pending) { reviewConfirmed = true; form.requestSubmit(); } });
    }
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (busy) return;
      if (!selection) { say('لطفاً ابتدا یک زمان خالی انتخاب کنید.'); return; }
      const data = {doctor_id:String(selection.doctor), slot_start:selection.start, revision:String(selection.revision), idempotency_key:selection.key};
      if (root.dataset.logged === '1') {
        const phone = digits(form.elements.phone.value.trim()), national = digits(form.elements.national_code.value.trim());
        if (!/^09\d{9}$/.test(phone)) { say('شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ آغاز شود.'); return; }
        if ((!review || national !== '') && !validNational(national)) { say('کد ملی ۱۰ رقمی معتبر وارد کنید.'); return; }
        data.phone = phone; data.national_code = national;
        if (review) {
          const first = form.elements.first_name.value.trim(), last = form.elements.last_name.value.trim();
          const service = form.elements.service.value, note = form.elements.note.value.trim();
          if (!first || Array.from(first).length > 80 || !last || Array.from(last).length > 80 || !['consultation','visit','surgery'].includes(service) || Array.from(note).length > 500) { say('نام و نام خانوادگی بیمار، نوع خدمت و توضیح را بررسی کنید.'); return; }
          Object.assign(data, {first_name:first, last_name:last, service, note});
          const signature = JSON.stringify(data);
          if (!pending && (!reviewConfirmed || reviewSignature !== signature)) {
            let formatted = selection.date;
            try { const f = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {weekday:'long',day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}); if (f.resolvedOptions().calendar === 'persian') formatted = f.format(new Date(selection.date + 'T12:00:00Z')); } catch (_) {}
            const services = {consultation:'مشاوره',visit:'ویزیت',surgery:'جراحی'};
            reviewSummary.textContent = `بیمار: ${first} ${last} | جراح: ${doctor.selectedOptions[0]?.textContent || ''} | ${formatted} | ساعت ${selection.label || selection.start} | خدمت: ${services[service]}`;
            reviewSignature = signature; reviewConfirmed = false; review.hidden = false; say('اطلاعات را مرور و ثبت را تأیید کنید.'); return;
          }
          hideReview();
        }
        if (!pending) {
          try { sessionStorage.setItem(storageKey, JSON.stringify({doctor:selection.doctor, date:selection.date, start:selection.start, revision:selection.revision, key:selection.key})); pending = selection; lockPending(); }
          catch (_) { say('برای پیگیری ایمن درخواست، ذخیره‌سازی این تب باید فعال باشد.'); return; }
        }
      } else { data.page_id = root.dataset.page; }
      let refreshCalendar = false;
      setBusy(true); say('در حال ارسال…');
      try {
        const result = await post(root.dataset.logged === '1' ? 'hj_booking_create' : 'hj_booking_intent', data);
        if (root.dataset.logged === '1') { clearPending(); selection = null; list.replaceChildren(); form.elements.phone.value = ''; form.elements.national_code.value = ''; if (review) { form.elements.first_name.value = ''; form.elements.last_name.value = ''; form.elements.service.value = ''; form.elements.note.value = ''; } say(result.message); refreshCalendar = true; }
        else { window.location.assign(result.login_url); }
      } catch (e) {
        // Only these server outcomes are checked after idempotent replay; other
        // errors could precede reconciliation of an already committed request.
        if (['revision_conflict', 'invalid_slot', 'pending_limit'].includes(e.code)) {
          clearPending(); if (e.code === 'revision_conflict' || e.code === 'invalid_slot') { selection = null; list.replaceChildren(); refreshCalendar = true; }
        }
        say(e.message || 'نتیجه مشخص نیست؛ با همین اطلاعات دوباره تلاش کنید.');
      } finally { setBusy(false); if (refreshCalendar) loadCalendar(); }
    });
    if (pending && root.dataset.logged === '1') {
      doctor.value = String(pending.doctor); date.value = pending.date; selection = pending; lockPending();
      say(review ? 'نتیجهٔ درخواست قبلی ممکن است نامعلوم باشد. همهٔ اطلاعات قبلی بیمار را دقیقاً دوباره وارد کنید و ثبت را بزنید.' : 'نتیجهٔ درخواست قبلی ممکن است نامعلوم باشد. همان شماره و کد ملی قبلی را وارد کنید و دوباره ثبت را بزنید.');
    } else if (intent && root.dataset.logged === '1') {
      doctor.value = String(intent.doctor); date.value = intent.local_date; loadSlots(intent);
    }
    if (calendar && !pending) loadCalendar();
  }
})();

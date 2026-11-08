/* A first click asks for a visual review; the second submits the same email. */
(() => {
  'use strict';
  document.querySelectorAll('.hj-magazine-section form[data-hj-home-newsletter="1"]').forEach(form => {
    const output = form.querySelector('.hj-newsletter-status');
    const button = form.querySelector('button[type="submit"]');
    const email = form.querySelector('input[name="email"]');
    const reviewed = form.querySelector('input[name="hj_reviewed_email"]');
    if (!output || !button || !email || !reviewed || !window.fetch || !window.FormData) return;
    let target;
    try { target = new URL(form.getAttribute('action') || window.location.href, window.location.href); } catch (_) { return; }
    if (target.origin !== window.location.origin) return;
    email.addEventListener('input', () => { reviewed.value = ''; output.textContent = ''; output.dataset.state = ''; });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (button.disabled || !form.reportValidity()) return;
      if (reviewed.value !== email.value.trim()) {
        reviewed.value = email.value.trim();
        output.dataset.state = 'review';
        output.textContent = 'یه بار جیمیلت رو چک کن، ببین درسته؟ اگه درسته یه بار دیگه دکمه اشتراک رو بزن.';
        return;
      }
      button.disabled = true;
      output.dataset.state = 'working';
      output.textContent = 'در حال ثبت اشتراک...';
      try {
        const response = await fetch(target.href, {method:'POST', body:new FormData(form), credentials:'same-origin', cache:'no-store', headers:{'X-HJ-Newsletter-UI':'1'}});
        const data = await response.json();
        const allowed = ['confirmed', 'busy', 'invalid', 'rate', 'mail_failed', 'error'];
        if (!data || !allowed.includes(data.status) || typeof data.message !== 'string') throw new Error('Unexpected response');
        output.dataset.state = data.status;
        output.textContent = data.message;
      } catch (_) {
        output.dataset.state = 'error';
        output.textContent = 'نتیجه مشخص نیست؛ لطفاً بعداً دوباره تلاش کنید.';
      } finally { button.disabled = false; }
    });
  });
})();

/* Progressive enhancement: the server redirects back when JavaScript is unavailable. */
(() => {
  'use strict';
  document.querySelectorAll('.hj-magazine-section form[data-hj-home-newsletter="1"]').forEach(form => {
    const output = form.querySelector('.hj-newsletter-status');
    const button = form.querySelector('button[type="submit"]');
    if (!output || !button || !window.fetch || !window.FormData) return;
    let target;
    try { target = new URL(form.action, window.location.href); } catch (_) { return; }
    if (target.origin !== window.location.origin) return;
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (button.disabled || !form.reportValidity()) return;
      button.disabled = true;
      output.textContent = 'در حال ثبت درخواست...';
      try {
        const response = await fetch(target.href, {method:'POST', body:new FormData(form), credentials:'same-origin', cache:'no-store', headers:{'X-HJ-Newsletter-UI':'1'}});
        const data = await response.json();
        const allowed = ['pending', 'check', 'busy', 'invalid', 'rate', 'mail_failed', 'error'];
        if (!data || !allowed.includes(data.status) || typeof data.message !== 'string') throw new Error('Unexpected response');
        output.textContent = data.message;
      } catch (_) {
        output.textContent = 'نتیجه مشخص نیست؛ لطفاً ایمیل خود را بررسی کنید یا بعداً تلاش کنید.';
      } finally { button.disabled = false; }
    });
  });
})();

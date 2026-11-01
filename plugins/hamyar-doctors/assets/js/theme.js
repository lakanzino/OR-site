/* Hamyar Doctors — theme.js (Section 13)

   Replaces the browser's validation bubble with an inline message under the field.

   The form is still blocked natively while invalid. No alert/confirm/prompt. */

(function () {

  'use strict';



  var d = document;

  var de = d.documentElement;

  de.classList.add('hj-js');



  var MSG = {

    valueMissing: 'این فیلد را تکمیل کنید.',

    typeMismatch: 'مقدار واردشده معتبر نیست.',

    patternMismatch: 'قالب مقدار درست نیست.',

    tooShort: 'متن کوتاه‌تر از حد مجاز است.',

    tooLong: 'متن بلندتر از حد مجاز است.',

    rangeUnderflow: 'عدد کمتر از حد مجاز است.',

    rangeOverflow: 'عدد بیشتر از حد مجاز است.',

    stepMismatch: 'عدد واردشده مجاز نیست.',

    badInput: 'مقدار واردشده معتبر نیست.'

  };

  var ORDER = ['valueMissing', 'typeMismatch', 'patternMismatch', 'tooShort', 'tooLong',

               'rangeUnderflow', 'rangeOverflow', 'stepMismatch', 'badInput'];



  var uid = 0;

  var lock = false;



  function inScope() {

    return !!(d.body && d.body.classList.contains('hj-scope'));

  }



  function textFor(el) {

    var v = el.validity;

    if (v.customError && el.validationMessage) { return el.validationMessage; }

    for (var i = 0; i < ORDER.length; i++) {

      var k = ORDER[i];

      if (v[k]) {

        if (k === 'valueMissing') {

          var t = (el.type || '').toLowerCase();

          if (t === 'checkbox' || t === 'radio') { return 'این گزینه باید انتخاب شود.'; }

          if (t === 'file') { return 'فایل را انتخاب کنید.'; }

          if (el.tagName === 'SELECT') { return 'یک گزینه را انتخاب کنید.'; }

        }

        if (k === 'typeMismatch') {

          if (el.type === 'email') { return 'نشانی ایمیل معتبر نیست.'; }

          if (el.type === 'url') { return 'نشانی اینترنتی معتبر نیست.'; }

        }

        return MSG[k];

      }

    }

    return MSG.badInput;

  }



  function ensureId(el) {

    if (!el.id) { el.id = 'hj-f-' + (++uid); }

    return el.id;

  }



  function show(el) {

    var id = ensureId(el);

    var bid = id + '-hjerr';

    var box = d.getElementById(bid);

    if (!box) {

      box = d.createElement('span');

      box.id = bid;

      box.className = 'hj-field-error';

      box.setAttribute('aria-live', 'polite');

      if (el.insertAdjacentElement) { el.insertAdjacentElement('afterend', box); }

      else if (el.parentNode) { el.parentNode.insertBefore(box, el.nextSibling); }



      var prev = el.getAttribute('aria-describedby');

      el.setAttribute('data-hj-desc', prev || '');

      el.setAttribute('aria-describedby', prev ? prev + ' ' + bid : bid);

    }

    box.textContent = textFor(el);

    box.hidden = false;

    el.setAttribute('aria-invalid', 'true');

  }



  function clear(el) {

    var bid = (el.id || '') + '-hjerr';

    var box = el.id ? d.getElementById(bid) : null;

    if (box && box.parentNode) { box.parentNode.removeChild(box); }

    el.removeAttribute('aria-invalid');

    if (el.hasAttribute('data-hj-desc')) {

      var prev = el.getAttribute('data-hj-desc');

      if (prev) { el.setAttribute('aria-describedby', prev); }

      else { el.removeAttribute('aria-describedby'); }

      el.removeAttribute('data-hj-desc');

    }

  }



  // "invalid" does not bubble, so listen in the capture phase.

  d.addEventListener('invalid', function (e) {

    var el = e.target;

    if (!inScope() || !el || !el.validity) { return; }

    e.preventDefault(); // suppress the native bubble; the form is still blocked

    show(el);

    if (!lock) { // the first invalid field gets the focus

      lock = true;

      try { el.focus(); } catch (x) { /* ignore */ }

      setTimeout(function () { lock = false; }, 0);

    }

  }, true);



  function onFix(e) {

    var el = e.target;

    if (!el || !el.validity || !el.hasAttribute || !el.hasAttribute('aria-invalid')) { return; }

    if (el.validity.valid) { clear(el); } else { show(el); }

  }

  d.addEventListener('input', onFix, true);

  d.addEventListener('change', onFix, true);

})();

/* Hamyar Doctors — copy-embed.js (Section 12)

   Builds the selected embed code (type + layout) and copies it. No alerts. */

(function () {

  'use strict';



  function ready(fn) {

    if (document.readyState === 'loading') {

      document.addEventListener('DOMContentLoaded', fn);

    } else {

      fn();

    }

  }



  function init(box) {

    if (box.getAttribute('data-hj-ready')) { return; }



    var codes;

    try { codes = JSON.parse(box.getAttribute('data-hj-codes') || '{}'); } catch (e) { return; }



    var ta = box.querySelector('[data-hj-code]');

    var btn = box.querySelector('[data-hj-copy]');

    var sel = box.querySelector('[data-hj-layout]');

    var tabs = box.querySelectorAll('[data-hj-type]');

    var msg = box.querySelector('.hj-eb-msg');

    if (!ta || !btn) { return; }



    box.setAttribute('data-hj-ready', '1');

    box.classList.remove('hj-eb--nojs');



    var okText = box.getAttribute('data-hj-msg-ok') || 'OK';

    var failText = box.getAttribute('data-hj-msg-fail') || '';

    var label = box.getAttribute('data-hj-label') || btn.textContent;

    var type = 'iframe';

    var timer = null;

    var i;



    function layout() {

      var v = sel ? sel.value : 'auto';

      return (v === 'v' || v === 'h') ? v : 'auto';

    }



    function render() {

      var k = type + '_' + layout();

      if (typeof codes[k] === 'string') { ta.value = codes[k]; }

      for (i = 0; i < tabs.length; i++) {

        var on = tabs[i].getAttribute('data-hj-type') === type;

        tabs[i].classList.toggle('is-active', on);

        tabs[i].setAttribute('aria-pressed', on ? 'true' : 'false');

      }

      reset();

    }



    function reset() {

      clearTimeout(timer);

      box.classList.remove('is-copied', 'is-failed');

      btn.textContent = label;

      if (msg) { msg.textContent = ''; }

    }



    function legacyCopy() {

      var ok = false;

      try {

        ta.focus();

        ta.select();

        try { ta.setSelectionRange(0, ta.value.length); } catch (e1) { /* ignore */ }

        ok = document.execCommand('copy');

      } catch (e2) { ok = false; }

      return ok;

    }



    function done(ok) {

      box.classList.toggle('is-copied', !!ok);

      box.classList.toggle('is-failed', !ok);

      if (ok) { btn.textContent = okText; }

      if (msg) { msg.textContent = ok ? okText : failText; }

      clearTimeout(timer);

      timer = setTimeout(reset, 2400);

    }



    for (i = 0; i < tabs.length; i++) {

      tabs[i].addEventListener('click', function () {

        type = this.getAttribute('data-hj-type') === 'script' ? 'script' : 'iframe';

        render();

      });

    }

    if (sel) { sel.addEventListener('change', render); }



    ta.addEventListener('focus', function () { ta.select(); });



    btn.addEventListener('click', function () {

      var text = ta.value;

      if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {

        navigator.clipboard.writeText(text).then(

          function () { done(true); },

          function () { done(legacyCopy()); }

        );

      } else {

        done(legacyCopy());

      }

    });



    render();

  }



  ready(function () {

    var boxes = document.querySelectorAll('.hj-eb[data-hj-codes]');

    for (var n = 0; n < boxes.length; n++) { init(boxes[n]); }

  });

})();

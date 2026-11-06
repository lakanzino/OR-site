// Execute the real dashboard script in a tiny DOM harness; not a browser E2E.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../plugins/hamyar-doctors/assets/js/dashboard.js'), 'utf8');
let submit;
let nextResponse = { success: true, data: { schedule_revision: 5 } };
const revision = { value: '4' };
const error = { textContent: '' };
const root = {
  querySelector(sel) { return sel === '[data-err="form"]' ? error : null; },
  querySelectorAll() { return []; },
  addEventListener() {},
};
const form = {
  addEventListener(type, fn) { if (type === 'submit') submit = fn; },
  querySelector(sel) { return sel === 'input[name="hj_schedule_revision"]' ? revision : null; },
};
let sent;
class FakeFormData {
  constructor(source) { this.data = source ? { hj_schedule_revision: revision.value } : {}; }
  append(k, v) { this.data[k] = v; }
}
const context = {
  document: { getElementById(id) { return id === 'hj-dash' ? root : id === 'hj-dash-form' ? form : null; } },
  window: { HJ_DASH: { nonce: 'fixture-nonce', ajax: '/wp-admin/admin-ajax.php', i18n: { fail: 'failed' } }, location: { reload() {} } },
  FormData: FakeFormData,
  fetch(url, options) { sent = options.body.data; return Promise.resolve({ json: () => Promise.resolve(nextResponse) }); },
  Promise, Number, String, Array, Object, Event: class {},
};
vm.runInNewContext(script, context, { filename: 'dashboard.js' });
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
  assert.equal(typeof submit, 'function');
  submit({ preventDefault() {} }); await flush();
  assert.equal(sent.hj_schedule_revision, '4');
  assert.equal(revision.value, '5');
  nextResponse = { success: false, message: 'برنامه تغییر کرده است؛ صفحه را بازخوانی کنید.' };
  submit({ preventDefault() {} }); await flush();
  assert.equal(sent.hj_schedule_revision, '5');
  assert.equal(revision.value, '5');
  assert.match(error.textContent, /بازخوانی/);
  nextResponse = { success: true, data: { schedule_revision: '8' } };
  submit({ preventDefault() {} }); await flush();
  assert.equal(revision.value, '5', 'malformed response must not change revision');
  console.log('PASS dashboard.js sends revision, updates it after success and preserves it on conflict');
})().catch(e => { console.error(e); process.exitCode = 1; });

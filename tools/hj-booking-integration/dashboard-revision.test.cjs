// Execute the real dashboard script with deterministic network/timer mocks, not browser E2E.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../plugins/hamyar-doctors/assets/js/dashboard.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness(hasRevision=true) {
  let submit, timer, resolveFetch, rejectFetch, calls = 0;
  const revision = { value: '4' }, button = {disabled:false}, error = {textContent:''};
  const root = {querySelector(sel) { return sel === '[data-err="form"]' ? error : null; },querySelectorAll() { return []; },addEventListener() {}};
  const form = {addEventListener(type, fn) { if (type === 'submit') submit = fn; },querySelector(sel) { if (sel === 'input[name="hj_schedule_revision"]') return hasRevision ? revision : null; if (sel === '[data-hj="save"]') return button; return null; }};
  let sent;
  class FakeFormData { constructor() { this.data = hasRevision ? {hj_schedule_revision: revision.value} : {}; } append(k,v) { this.data[k]=v; } }
  const context = {
    document: {getElementById(id) { return id === 'hj-dash' ? root : id === 'hj-dash-form' ? form : null; }},
    window: {HJ_DASH: {nonce:'fixture',ajax:'/ajax',i18n:{fail:'failed'}},location:{reload(){}},setTimeout(fn) { timer=fn; return 1; },clearTimeout() {timer=null;}},
    FormData:FakeFormData, fetch(url,options) { calls++; sent=options.body.data; return new Promise((resolve,reject)=>{resolveFetch=resolve;rejectFetch=reject;}); },
    Promise,Number,String,Array,Object,Event:class {},
  };
  vm.runInNewContext(script,context,{filename:'dashboard.js'});
  return {button,error,revision,submit(){submit({preventDefault(){}});},sent(){return sent;},calls(){return calls;},fireTimer(){assert.ok(timer); const fn=timer;timer=null;fn();},reply(status,body){resolveFetch({status,json:()=>Promise.resolve(body)});},invalidJson(status){resolveFetch({status,json:()=>Promise.reject(new Error('truncated'))});},reject(){rejectFetch(new Error('network'));}};
}
(async () => {
  let h=harness(); h.submit(); h.submit();
  assert.equal(h.calls(),1,'double submit is suppressed'); assert.equal(h.button.disabled,true); assert.equal(h.sent().hj_schedule_revision,'4');
  h.reply(422,{success:false,message:'invalid',errors:{name_fa:'invalid'}}); await flush();
  assert.equal(h.button.disabled,false); assert.equal(h.error.textContent,'invalid'); assert.equal(h.revision.value,'4');
  h.submit(); assert.equal(h.calls(),2); h.reply(200,{success:true,data:{schedule_revision:5}}); await flush();
  assert.equal(h.revision.value,'5'); assert.equal(h.button.disabled,false); assert.equal(h.error.textContent,'');
  for (const scenario of ['network','truncated','503','409','bad-success','bad-revision','timeout']) {
    h=harness(); h.submit();
    if (scenario==='network') h.reject();
    if (scenario==='truncated') h.invalidJson(200);
    if (scenario==='503') h.reply(503,{success:false,message:'unknown'});
    if (scenario==='409') h.reply(409,{success:false,message:'conflict'});
    if (scenario==='bad-success') h.reply(200,{success:true});
    if (scenario==='bad-revision') h.reply(200,{success:true,data:{schedule_revision:'5'}});
    if (scenario==='timeout') {h.fireTimer(); h.reply(200,{success:true,data:{schedule_revision:5}});}
    await flush(); assert.equal(h.button.disabled,true,scenario); assert.equal(h.revision.value,'4',scenario);
    assert.match(h.error.textContent,/نتیجهٔ ذخیره مشخص نیست/,scenario); h.submit(); assert.equal(h.calls(),1,scenario+' blocks retry');
  }
  h=harness(false); h.submit(); h.reply(200,{success:true,data:{}}); await flush(); h.submit();
  assert.equal(h.calls(),2,'non-v1 handler keeps its existing behavior');
  console.log('PASS actual dashboard JS: double-submit, definite validation, unknown outcomes, timeout/late response and flag-off behavior');
})().catch(e=>{console.error(e);process.exitCode=1;});

// Execute real UI script with a small DOM/fetch harness; not browser E2E.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('plugins/hamyar-doctors/assets/js/booking-form.js', 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
const flush = async () => { for (let i=0;i<5;i++) await tick(); };
function environment(intent, firstNetworkFails = false) {
  const callbacks = {};
  function element(value='') { return {value, disabled:false, textContent:'', children:[], attrs:{},
    addEventListener(type, cb) { this[type] = cb; }, setAttribute(k,v) {this.attrs[k]=v;},
    append(el) {this.children.push(el);}, replaceChildren() {this.children=[];}, querySelectorAll() {return this.children;} }; }
  const doctor = element(), date = element(), phone = element('۰۹۱۲۳۴۵۶۷۸۹'), national = element('۱۲۳۴۵۶۷۸۹۱');
  const list = element(), status = element(), load = element(), submit = element();
  const form = {elements:{doctor_id:doctor,date,phone,national_code:national}, addEventListener(type, cb) {callbacks[type]=cb;}, querySelector() {return submit;}};
  const root = {dataset:{page:'12',ajax:'/wp-admin/admin-ajax.php',nonce:'abc',logged:'1',fixed:'0',intent:JSON.stringify(intent)},
    querySelector(sel) {return ({'.hj-booking__form':form,'.hj-booking__slots':list,'.hj-booking__status':status,'.hj-booking__load':load})[sel];}};
  const stored = new Map(), requests = [];
  const start = intent.start, key = intent.key;
  const ctx = {document:{querySelectorAll:()=>[root],createElement:()=>element()}, URLSearchParams,
    sessionStorage:{getItem:k=>stored.get(k) ?? null,setItem:(k,v)=>stored.set(k,v),removeItem:k=>stored.delete(k)},
    crypto:{getRandomValues:a=>{a.fill(1);return a;}}, Uint8Array, window:{location:{assign(){}}},
    fetch: async (url, opts) => {
      const data = Object.fromEntries(opts.body); requests.push(data);
      if (data.action === 'hj_booking_slots') return {ok:true,json:async()=>({success:true,data:{revision:7,slots:[{start,label:'09:00'}]}})};
      if (firstNetworkFails) { firstNetworkFails = false; throw new Error('simulated lost response'); }
      return {ok:true,json:async()=>({success:true,data:{status:'replayed',id:8,message:'درخواست ثبت شد'}})};
    }};
  vm.runInNewContext(script, ctx);
  return {callbacks,doctor,date,phone,national,list,status,submit,stored,requests,key};
}
(async () => {
  const intent={doctor:42,start:'2026-12-02 05:30:00',revision:7,key:'01234567-89ab-4cde-8f01-23456789abcd',local_date:'2026-12-02'};
  const e=environment(intent,true); await flush();
  assert.equal(e.requests[0].action,'hj_booking_slots'); assert.equal(e.doctor.value,'42'); assert.equal(e.date.value,'2026-12-02');
  assert.equal(e.list.children[0].attrs['aria-pressed'],'true');
  e.callbacks.submit({preventDefault(){}}); await flush();
  assert.match(e.status.textContent,/نتیجه مشخص نیست/); assert.equal(e.stored.size,1);
  const saved=JSON.stringify([...e.stored.values()]); assert(!saved.includes('09123456789') && !saved.includes('1234567891'));
  const first=e.requests[1]; assert.equal(first.phone,'09123456789'); assert.equal(first.national_code,'1234567891'); assert.equal(first.idempotency_key,intent.key); assert.equal(e.doctor.disabled,true); assert.equal(e.list.children[0].disabled,true);
  e.callbacks.submit({preventDefault(){}}); await flush();
  assert.equal(e.requests[2].idempotency_key,first.idempotency_key); assert.equal(e.stored.size,0); assert.match(e.status.textContent,/ثبت شد/);
  console.log('PASS: real UI revalidates restored intent, normalizes digits and retries the same UUID after lost response without storing private data');
})().catch(e=>{console.error(e); process.exit(1);});

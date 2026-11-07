/* CI-only: authenticated Chromium -> real WordPress dashboard AJAX -> MariaDB. */
'use strict';
const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
const crypto=require('node:crypto');
const {chromium}=require('playwright-core');
const base='http://example.invalid:8997';
const doctor=String(process.env.HJ_BROWSER_DOCTOR);
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--no-proxy-server','--host-resolver-rules=MAP example.invalid 127.0.0.1']});
 const context=await browser.newContext({viewport:{width:375,height:850}}); const page=await context.newPage();
 try {
  await context.addCookies([{name:'wordpress_test_cookie',value:'WP Cookie check',url:base}]);
  await page.goto(base+'/wp-login.php');
  await page.evaluate(async ()=>{const f=new URLSearchParams({log:'synthetic_doctor_b',pwd:'disposable-test-password','wp-submit':'Log In',testcookie:'1'}); await fetch('/wp-login.php',{method:'POST',body:f,redirect:'manual'});});
  assert((await context.cookies()).some(c=>c.name.startsWith('wordpress_logged_in_')),'real WP login');
  const fixture=await page.evaluate(async ()=>{
   async function call(action) {const r=await fetch('/wp-admin/admin-ajax.php',{method:'POST',body:new URLSearchParams({action})});if(!r.ok)throw Error('fixture HTTP '+r.status);return (await r.json()).data;}
   return {nonce:(await call('hj_ci_dashboard_nonce')).nonce,html:(await call('hj_ci_render_dashboard')).html};
  });
  await page.evaluate(({html,nonce})=>{document.body.innerHTML=html;window.HJ_DASH={nonce,ajax:'/wp-admin/admin-ajax.php',i18n:{fail:'failed'}};},fixture);
  await page.addScriptTag({path:path.join(process.env.GITHUB_WORKSPACE,'plugins/hamyar-doctors/assets/js/dashboard.js')});
  const state=()=>page.evaluate(async doctor=>{const r=await fetch('/wp-admin/admin-ajax.php',{method:'POST',body:new URLSearchParams({action:'hj_ci_schedule_state',pid:doctor})});if(!r.ok)throw Error('state '+r.status);return (await r.json()).data;},doctor);
  const before=await state(); const revision=Number(before.snapshot.revision);
  assert.equal(await page.locator('[name="pid"]').inputValue(),doctor);
  assert.equal(await page.locator('[name="hj_schedule_revision"]').inputValue(),String(revision));
  await page.locator('[name="name_fa"]').fill('Synthetic Browser Save');
  await page.locator('[name="hours[wednesday][start]"]').fill('09:15');
  await page.locator('[name="hours[wednesday][end]"]').fill('12:15');
  await page.locator('[data-hj="save"]').click();
  await page.waitForFunction(rev=>document.querySelector('[name="hj_schedule_revision"]').value===String(rev+1),revision);
  const after=await state(); const rows=JSON.parse(after.snapshot.rows_json);
  assert.equal(Number(after.snapshot.revision),revision+1); assert.equal(after.name,'Synthetic Browser Save');
  assert.equal(rows[0].start_time,'09:15:00'); assert.equal(rows[0].end_time,'12:15:00');
  assert.equal(after.display[0].start_time,'09:15:00');
  assert.equal(await page.locator('[data-hj="save"]').isDisabled(),false);
  console.log('PASS real authenticated Chromium dashboard save, revision, MariaDB snapshot and display mirror');
  // Deterministically hold the real writer under its doctor mutex while the user edits.
  const token=crypto.randomBytes(16).toString('hex');
  const prefix=path.join(process.env.RUNNER_TEMP,'hj-wpdb-test-save-hold-'+token);
  const ready=prefix+'-ready', release=prefix+'-released';
  try {
   await page.locator('[name="name_fa"]').fill('Synthetic Pending Initial');
   await page.locator('[name="hours[wednesday][start]"]').fill('10:00');
   await page.evaluate(token=>{
    const input=document.createElement('input');input.type='hidden';input.name='hj_ci_hold_lock';input.value=token;document.querySelector('#hj-dash-form').append(input);
    const real=window.fetch.bind(window); window.__saveCalls=0;
    window.fetch=(url,opts)=>{if(opts && opts.body instanceof FormData && opts.body.get('action')==='hj_dash_save') window.__saveCalls++;return real(url,opts);};
   },token);
   await page.locator('[data-hj="save"]').click();
   const until=Date.now()+15000;
   while(!fs.existsSync(ready) && Date.now()<until) await new Promise(r=>setTimeout(r,25));
   assert(fs.existsSync(ready),'actual schedule writer reached mutex barrier');
   assert.equal(await page.locator('[data-hj="save"]').isDisabled(),true);
   await page.locator('[name="name_fa"]').fill('Synthetic Pending Edited');
   await page.locator('[name="hours[wednesday][start]"]').fill('10:30');
   await page.evaluate(()=>document.querySelector('#hj-dash-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));
   assert.equal(await page.evaluate(()=>window.__saveCalls),1,'parallel submission blocked');
   fs.writeFileSync(release,'go');
   await page.waitForFunction(rev=>document.querySelector('[name="hj_schedule_revision"]').value===String(rev+1),revision+1);
   await page.waitForFunction(()=>document.querySelector('[data-err="form"]').textContent.includes('بعد از ارسال'));
   const first=await state();
   assert.equal(first.name,'Synthetic Pending Initial'); assert.equal(JSON.parse(first.snapshot.rows_json)[0].start_time,'10:00:00');
   assert.equal(await page.locator('[name="name_fa"]').inputValue(),'Synthetic Pending Edited');
   assert.equal(await page.locator('[name="hours[wednesday][start]"]').inputValue(),'10:30');
   assert.equal(await page.locator('[data-hj="save"]').isDisabled(),false);
   await page.locator('[name="hj_ci_hold_lock"]').evaluate(el=>el.remove());
   await page.locator('[data-hj="save"]').click();
   await page.waitForFunction(rev=>document.querySelector('[name="hj_schedule_revision"]').value===String(rev+1),revision+2);
   const second=await state();
   assert.equal(second.name,'Synthetic Pending Edited'); assert.equal(JSON.parse(second.snapshot.rows_json)[0].start_time,'10:30:00');
   assert.equal(Number(second.snapshot.revision),revision+3); assert.equal(await page.evaluate(()=>window.__saveCalls),2);
   console.log('PASS real browser in-flight edits, mutex barrier, original save and explicit subsequent save');
  } finally {
   fs.writeFileSync(release,'go');
   for(const f of [ready,release]) try {fs.unlinkSync(f);} catch(e) {if(e.code!=='ENOENT')throw e;}
  }

  // Drop the response only after the real WordPress handler has returned it.
  await page.locator('[name="name_fa"]').fill('Synthetic Committed Response Lost');
  await page.locator('[name="hours[wednesday][start]"]').fill('11:00');
  await page.evaluate(()=>{
   const real=window.fetch.bind(window);window.__drop=true;window.__droppedStatus=null;
   window.fetch=async (url,opts)=>{
    const isSave=opts && opts.body instanceof FormData && opts.body.get('action')==='hj_dash_save';
    const response=await real(url,opts);
    if(isSave && window.__drop) {window.__drop=false;window.__droppedStatus=response.status;await response.text();throw new Error('synthetic response loss after handler completed');}
    return response;
   };
  });
  await page.locator('[data-hj="save"]').click();
  await page.waitForFunction(()=>document.querySelector('[data-err="form"]').textContent.includes('نتیجهٔ ذخیره مشخص نیست'));
  assert.equal(await page.evaluate(()=>window.__droppedStatus),200,'real endpoint completed before response was discarded');
  assert.equal(await page.locator('[data-hj="save"]').isDisabled(),true);
  assert.equal(await page.locator('[name="hj_schedule_revision"]').inputValue(),String(revision+3));
  const committed=await state();
  assert.equal(Number(committed.snapshot.revision),revision+4); assert.equal(committed.name,'Synthetic Committed Response Lost');
  assert.equal(JSON.parse(committed.snapshot.rows_json)[0].start_time,'11:00:00');
  await page.evaluate(()=>document.querySelector('#hj-dash-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));
  assert.equal(await page.evaluate(()=>window.__saveCalls),3,'blocked form does not send another request');
  assert.equal(Number((await state()).snapshot.revision),revision+4,'no second commit from same page');
  // New page context within the same logged-in browser deliberately shows that the in-memory guard is not durable.
  const reopened=await context.newPage();
  try {
   const nav=await reopened.goto(base+'/wp-admin/admin-ajax.php?action=hj_ci_render_dashboard'); assert.equal(nav.status(),200);
   const fresh=await reopened.evaluate(async ()=>{
    async function call(action){const r=await fetch('/wp-admin/admin-ajax.php',{method:'POST',body:new URLSearchParams({action})});if(!r.ok)throw Error('fixture '+r.status);return (await r.json()).data;}
    return {nonce:(await call('hj_ci_dashboard_nonce')).nonce,html:(await call('hj_ci_render_dashboard')).html};
   });
   await reopened.evaluate(({html,nonce})=>{document.body.innerHTML=html;window.HJ_DASH={nonce,ajax:'/wp-admin/admin-ajax.php',i18n:{fail:'failed'}};},fresh);
   await reopened.addScriptTag({path:path.join(process.env.GITHUB_WORKSPACE,'plugins/hamyar-doctors/assets/js/dashboard.js')});
   assert.equal(await reopened.locator('[name="hj_schedule_revision"]').inputValue(),String(revision+4));
   assert.equal(await reopened.locator('[name="name_fa"]').inputValue(),'Synthetic Committed Response Lost');
   assert.equal(await reopened.locator('[data-hj="save"]').isDisabled(),false,'fresh page cannot retain in-memory unknown lock');
  } finally {await reopened.close();}
  console.log('PASS real committed save with synthetic dropped response, blocked retry and nonpersistent page lock');

 } finally {await context.close();await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

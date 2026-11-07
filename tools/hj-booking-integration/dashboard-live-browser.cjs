/* CI-only: authenticated Chromium -> real WordPress dashboard AJAX -> MariaDB. */
'use strict';
const assert=require('node:assert/strict');
const path=require('node:path');
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
 } finally {await context.close();await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

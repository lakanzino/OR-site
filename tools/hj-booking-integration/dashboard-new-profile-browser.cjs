/* CI-only: first-time doctor dashboard with real WordPress/MariaDB and test barrier. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs'); const path=require('node:path'); const crypto=require('node:crypto');
const {chromium}=require('playwright-core'); const base='http://example.invalid:8997';
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--no-proxy-server','--host-resolver-rules=MAP example.invalid 127.0.0.1']});
 const context=await browser.newContext({viewport:{width:375,height:850}}); const page=await context.newPage();
 const token=crypto.randomBytes(16).toString('hex'); const prefix=path.join(process.env.RUNNER_TEMP,'hj-wpdb-test-save-hold-'+token);
 const ready=prefix+'-ready', release=prefix+'-released';
 try {
  await context.addCookies([{name:'wordpress_test_cookie',value:'WP Cookie check',url:base}]);
  await page.goto(base+'/wp-login.php');
  await page.evaluate(async ()=>{const f=new URLSearchParams({log:'synthetic_new_profile',pwd:'disposable-test-password','wp-submit':'Log In',testcookie:'1'});await fetch('/wp-login.php',{method:'POST',body:f,redirect:'manual'});});
  assert((await context.cookies()).some(c=>c.name.startsWith('wordpress_logged_in_')),'real new doctor login');
  async function call(action,extra={}) {return page.evaluate(async ({action,extra})=>{const r=await fetch('/wp-admin/admin-ajax.php',{method:'POST',body:new URLSearchParams({action,...extra})});if(!r.ok)throw Error('fixture '+r.status);return (await r.json()).data;},{action,extra});}
  const fixture={nonce:(await call('hj_ci_dashboard_nonce')).nonce,html:(await call('hj_ci_render_dashboard')).html};
  assert.deepEqual((await call('hj_ci_own_profiles')).ids,[]);
  await page.evaluate(({html,nonce})=>{document.body.innerHTML=html;window.HJ_DASH={nonce,ajax:'/wp-admin/admin-ajax.php',i18n:{fail:'failed'}};},fixture);
  await page.addScriptTag({path:path.join(process.env.GITHUB_WORKSPACE,'plugins/hamyar-doctors/assets/js/dashboard.js')});
  assert.equal(await page.locator('[name="pid"]').inputValue(),'0'); assert.equal(await page.locator('[name="hj_schedule_revision"]').inputValue(),'0');
  await page.locator('[name="name_fa"]').fill('Synthetic New Original');
  await page.locator('[name="hours[wednesday][start]"]').fill('09:00'); await page.locator('[name="hours[wednesday][end]"]').fill('12:00');
  await page.evaluate(token=>{const el=document.createElement('input');el.type='hidden';el.name='hj_ci_hold_lock';el.value=token;document.querySelector('#hj-dash-form').append(el);},token);
  await page.locator('[data-hj="save"]').click();
  const until=Date.now()+15000;while(!fs.existsSync(ready) && Date.now()<until)await new Promise(r=>setTimeout(r,25));
  assert(fs.existsSync(ready),'first profile save reached real schedule mutex');
  await page.locator('[name="name_fa"]').fill('Synthetic New Edited');
  await page.locator('[name="hours[wednesday][start]"]').fill('10:00');
  fs.writeFileSync(release,'go');
  await page.waitForFunction(()=>document.querySelector('[data-err="form"]').textContent.includes('بعد از ارسال'));
  assert.equal(await page.locator('[name="name_fa"]').inputValue(),'Synthetic New Edited');
  assert.equal(await page.locator('[name="hours[wednesday][start]"]').inputValue(),'10:00');
  assert.equal(await page.locator('[data-hj="save"]').isDisabled(),true); assert.equal(await page.locator('[name="pid"]').inputValue(),'0');
  const own=await call('hj_ci_own_profiles'); assert.equal(own.ids.length,1); assert.equal(own.linked,own.ids[0]);
  const state=await call('hj_ci_schedule_state',{pid:String(own.linked)});
  assert.equal(state.name,'Synthetic New Original'); assert.equal(Number(state.snapshot.revision),1);
  assert.equal(JSON.parse(state.snapshot.rows_json)[0].start_time,'09:00:00');
  console.log('PASS actual first profile creation with in-flight edits, retained DOM and original snapshot');
 } finally {
  fs.writeFileSync(release,'go');for(const f of [ready,release])try{fs.unlinkSync(f);}catch(e){if(e.code!=='ENOENT')throw e;}
  await context.close();await browser.close();
 }
})().catch(e=>{console.error(e);process.exitCode=1;});

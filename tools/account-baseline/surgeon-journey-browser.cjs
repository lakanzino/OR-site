// CI only: synthetic doctor, WP and DB; no OAuth or live data.
if (process.env.GITHUB_ACTIONS !== 'true' || process.env.HJ_DISPOSABLE_MARIADB_FIXTURE !== '1') throw Error('CI fixture only');
const {chromium} = require('playwright-core');
const assert = require('node:assert/strict');
const base='http://example.invalid';
(async () => {
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage','--no-proxy-server','--host-resolver-rules=MAP example.invalid 127.0.0.1']});
 try {
  const page=await browser.newPage({viewport:{width:375,height:900}});
  async function login(name,pass) {
   await page.goto(base+'/wp-login.php',{waitUntil:'domcontentloaded'});
   await page.locator('#user_login').fill(name); await page.locator('#user_pass').fill(pass);
   await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
  }
  await login('fixture_admin','disposable-password-please-ignore');
  let info=await page.evaluate(async ()=>(await fetch('/wp-admin/admin-post.php?action=hj_fixture_surgeon_journey')).json());
  assert.equal(info.pid,0); assert.ok(info.city && info.province && info.other);
  await page.context().clearCookies();
  await login('journey_surgeon','fixture-journey-only');
  await page.goto(base+'/?pagename=my-profile',{waitUntil:'networkidle'});
  assert.equal(await page.locator('#hj-dash-form').count(),1,'surgeon dashboard');
  assert.equal(await page.locator('input[name=pid]').inputValue(),'0','no profile yet');
  const save=page.locator('[data-hj=save]');
  await page.locator('input[name=name_fa]').fill('جراح نمونه مسیر');
  let done=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('hj_dash_save'));
  await save.click(); let result=await (await done).json();
  assert.equal(result.success,true,JSON.stringify(result)); assert.equal(result.data.created,true,'first save creates draft');
  await page.waitForFunction(()=>Number(document.querySelector('input[name=pid]')?.value)>0,null,{timeout:15000});
  let pid=Number(await page.locator('input[name=pid]').inputValue());
  await page.waitForFunction(()=>!!window.HJ_DASH?.nonce);
  async function ajax(action, target, nonce=true) {return page.evaluate(async ({action,target,nonce})=>{
   const body=new FormData();body.set('action',action);body.set('pid',target);body.set('_hjn',nonce?window.HJ_DASH.nonce:'wrong');
   const r=await fetch('/wp-admin/admin-ajax.php',{method:'POST',body});return {code:r.status,data:await r.json()};
  },{action,target:String(target),nonce});}
  let res=await ajax('hj_dash_submit',pid); assert.equal(res.code,422,JSON.stringify(res)); assert.match(res.data.message,/تکمیل کنید/,'incomplete review rejected');
  res=await ajax('hj_dash_submit',pid,false); assert.equal(res.code,403,'nonce required');
  res=await ajax('hj_dash_submit',info.other); assert.equal(res.code,403,'cannot submit another profile');
  await page.locator('input[name=specialty_fa]').fill('جراحی آزمایشی');
  await page.locator('input[name=phone_clinic]').fill('02112345678');
  await page.locator('input[name=address_clinic]').fill('نشانی ساختگی');
  await page.locator('#hj-dash-province').selectOption(String(info.province));
  await page.locator('#hj-dash-city').selectOption(String(info.city));
  done=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('hj_dash_save'));
  await save.click(); result=await (await done).json(); assert.equal(result.success,true,JSON.stringify(result));
  await page.reload({waitUntil:'networkidle'});
  assert.equal(await page.locator('input[name=name_fa]').inputValue(),'جراح نمونه مسیر');
  assert.equal(await page.locator('input[name=address_clinic]').inputValue(),'نشانی ساختگی');
  assert.equal(await page.locator('#hj-dash-city').inputValue(),String(info.city));
  assert.equal(await page.locator('[data-hj=status-chip]').getAttribute('data-status'),'draft');
  assert.equal(await page.locator('[data-hj=submit-review]').isEnabled(),true,'complete fixture can submit');
  const guest=await browser.newContext();
  try {const g=await guest.newPage(); const r=await g.goto(base+'/?post_type=doctor&p='+pid); assert.notEqual(r.status(),200,'draft not publicly available');} finally {await guest.close();}
  done=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('hj_dash_submit'));
  await page.locator('[data-hj=submit-review]').click(); result=await (await done).json();
  assert.equal(result.success,true,JSON.stringify(result)); assert.equal(result.data.status,'pending');
  await page.waitForFunction(()=>document.querySelector('[data-hj=status-chip]')?.dataset.status==='pending');
  // Use a fresh context so the synthetic admin login cannot interfere with the surgeon session.
  const admin=await browser.newPage();
  try {
   await admin.goto(base+'/wp-login.php',{waitUntil:'domcontentloaded'});
   await admin.locator('#user_login').fill('fixture_admin');await admin.locator('#user_pass').fill('disposable-password-please-ignore');
   await Promise.all([admin.waitForNavigation({waitUntil:'domcontentloaded'}),admin.locator('#wp-submit').click()]);
   info=await admin.evaluate(async ()=>(await fetch('/wp-admin/admin-post.php?action=hj_fixture_surgeon_journey')).json());
   assert.equal(info.pid,pid); assert.equal(info.status,'pending'); assert.equal(info.profile_status,'pending');
   await admin.goto(base+'/wp-admin/edit.php?post_type=doctor',{waitUntil:'domcontentloaded'});
   const approve=admin.locator(`#post-${pid} a`).filter({hasText:'تأیید و انتشار'});
   assert.equal(await approve.count(),1,'admin approve action present');
   const url=await approve.getAttribute('href'); await admin.goto(url,{waitUntil:'domcontentloaded'});
   info=await admin.evaluate(async ()=>(await fetch('/wp-admin/admin-post.php?action=hj_fixture_surgeon_journey')).json());
   assert.equal(info.status,'publish'); assert.equal(info.profile_status,'published');
  } finally {await admin.context().close();}
  const publicContext=await browser.newContext();
  try {const p=await publicContext.newPage();const response=await p.goto(base+'/?post_type=doctor&p='+pid);assert.equal(response.status(),200,'published profile visible');} finally {await publicContext.close();}
  console.log('PASS synthetic save, ownership, nonce, review, admin publication; booking disabled; no OAuth');
 } finally {await browser.close();}
})().catch(err=>{console.error(err);process.exit(1)});

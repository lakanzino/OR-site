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
  // First save reloads the page immediately; inspect the resulting persisted ID instead of a response body lost to navigation.
  await save.click();
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
  res=await ajax('hj_dash_save',info.other); assert.equal(res.code,403,'cannot save another profile');

  // One synthetic document only. The builtin PHP web server ignores .htaccess;
  // direct access here demonstrates a deployment-dependent risk, not the live server's configuration.
  const uploaded=await page.evaluate(async pid=>{
   const form=new FormData();form.set('action','hj_dash_doc');form.set('pid',String(pid));form.set('_hjn',window.HJ_DASH.nonce);
   form.set('doc',new File(['%PDF-1.4\n% synthetic CI fixture only\n%%EOF\n'],'fixture.pdf',{type:'application/pdf'}));
   const r=await fetch('/wp-admin/admin-ajax.php',{method:'POST',body:form});return {status:r.status,body:await r.json()};
  },pid);
  assert.equal(uploaded.status,200,JSON.stringify(uploaded));assert.equal(uploaded.body.success,true);
  const link=await page.evaluate(html=>{const el=document.createElement('div');el.innerHTML=html;return el.querySelector('a').href;},uploaded.body.data.docs_html);
  let docDownload=await page.evaluate(async link=>(await fetch(link)).status,link);assert.equal(docDownload,200,'owner may download synthetic document');
  const docsAdmin=await browser.newPage();
  try {
   await docsAdmin.goto(base+'/wp-login.php',{waitUntil:'domcontentloaded'});
   await docsAdmin.locator('#user_login').fill('fixture_admin');await docsAdmin.locator('#user_pass').fill('disposable-password-please-ignore');
   await Promise.all([docsAdmin.waitForNavigation({waitUntil:'domcontentloaded'}),docsAdmin.locator('#wp-submit').click()]);
   let state=await docsAdmin.evaluate(async ()=>(await fetch('/wp-admin/admin-post.php?action=hj_fixture_surgeon_journey')).json());
   assert.equal(state.docs.length,1); const doc=state.docs[0];
   const stranger=await browser.newPage();
   try {
    await stranger.goto(base+'/wp-login.php',{waitUntil:'domcontentloaded'});
    await stranger.locator('#user_login').fill('responsive_surgeon');await stranger.locator('#user_pass').fill('disposable-password-please-ignore');
    await Promise.all([stranger.waitForNavigation({waitUntil:'domcontentloaded'}),stranger.locator('#wp-submit').click()]);
    const nonce=await stranger.evaluate(async ({pid,id})=>{const f=new FormData();f.set('action','hj_fixture_doc_nonce');f.set('pid',pid);f.set('doc',id);return (await (await fetch('/wp-admin/admin-ajax.php',{method:'POST',body:f})).json()).data.nonce;},{pid:String(pid),id:doc.id});
    const url=new URL(link);url.searchParams.set('_wpnonce',nonce);
    assert.equal(await stranger.evaluate(async url=>(await fetch(url)).status,url.toString()),403,'valid nonce alone cannot authorize another surgeon');
   } finally {await stranger.context().close();}
   const guestDoc=await browser.newContext();
   try {
    const g=await guestDoc.newPage();await g.goto(base+'/',{waitUntil:'domcontentloaded'});
    assert.notEqual(await g.evaluate(async url=>(await fetch(url)).status,link),200,'guest cannot use authenticated handler');
    const raw=await g.evaluate(async file=>{const r=await fetch('/wp-content/uploads/hj-private/'+file);return {status:r.status,text:await r.text()};},doc.file);
    assert.equal(raw.status,200,'PHP built-in server exposes synthetic file because it ignores .htaccess');
    assert.match(raw.text,/synthetic CI fixture only/);
   } finally {await guestDoc.close();}
  } finally {await docsAdmin.context().close();}
  await page.locator('input[name=specialty_fa]').fill('جراحی آزمایشی');
  await page.locator('input[name=phone_clinic]').fill('02112345678');
  await page.locator('input[name=address_clinic]').fill('نشانی ساختگی');
  await page.locator('#hj-dash-province').selectOption(String(info.province));
  await page.locator('#hj-dash-city').selectOption(String(info.city));
  let done=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('hj_dash_save'));
  await save.click(); let result=await (await done).json(); assert.equal(result.success,true,JSON.stringify(result));
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
  const pendingGuest=await browser.newContext();
  try {const g=await pendingGuest.newPage(); const r=await g.goto(base+'/?post_type=doctor&p='+pid); assert.notEqual(r.status(),200,'pending profile not publicly available');} finally {await pendingGuest.close();}
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
  await page.reload({waitUntil:'networkidle'});
  assert.equal(await page.locator('[data-hj=status-chip]').getAttribute('data-status'),'published');
  await page.locator('input[name=address_clinic]').fill('نشانی ساختگی ویرایش شده');
  done=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('hj_dash_save'));
  await save.click(); result=await (await done).json();
  assert.equal(result.success,true,JSON.stringify(result)); assert.equal(result.data.status,'published','baseline default keeps edits published');
  const publicContext=await browser.newContext();
  try {const p=await publicContext.newPage();const response=await p.goto(base+'/?post_type=doctor&p='+pid);assert.equal(response.status(),200,'published profile visible');} finally {await publicContext.close();}
  console.log('PASS synthetic save, ownership, nonce, review, admin publication and published edit behavior; booking disabled; no OAuth');
 } finally {await browser.close();}
})().catch(err=>{console.error(err);process.exit(1)});

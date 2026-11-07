/* Real WP cookies/nonces, PHP fixture and MariaDB through disposable loopback HTTP, never product. */
'use strict';
const assert=require('node:assert/strict'); const {chromium}=require('playwright-core');
const base='http://example.invalid:8995', doctor=process.env.HJ_REVIEW_DOCTOR;
const a='0'.repeat(30)+'65', b='0'.repeat(30)+'66', ownerKey='0'.repeat(30)+'67';
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--no-proxy-server','--host-resolver-rules=MAP example.invalid 127.0.0.1']});
 async function session(name) {
  const context=await browser.newContext({viewport:{width:375,height:850}}); const page=await context.newPage();
  if(name) {
   await context.addCookies([{name:'wordpress_test_cookie',value:'WP Cookie check',url:base}]);
   await page.goto(base+'/wp-login.php');
   await page.evaluate(async name=>{const form=new URLSearchParams({log:name,pwd:'disposable-test-password','wp-submit':'Log In',testcookie:'1'}); await fetch('/wp-login.php',{method:'POST',body:form,redirect:'manual'});},name);
   assert((await context.cookies()).some(c=>c.name.startsWith('wordpress_logged_in_')),'real WP login: '+name);
  }
  return {context,page};
 }
 async function post(page,route,nonce,doc,body={}) {
  return page.evaluate(async ({route,nonce,doc,body})=>{const r=await fetch(route,{method:'POST',headers:{'Content-Type':'application/json','X-Fixture-Nonce':nonce,'X-Fixture-Doctor':doc},body:JSON.stringify(body)});return {code:r.status,cache:r.headers.get('cache-control'),body:await r.text()};},{route,nonce,doc,body});
 }
 const sessions=[];
 try {
  const admin=await session('fixture_admin'); sessions.push(admin); let page=admin.page;
  let response=await page.goto(base+'/review?doctor='+doctor); assert.equal(response.status(),200); assert.match(response.headers()['cache-control'],/no-store/);
  await page.waitForFunction(()=>!document.querySelector('#fields').disabled);
  const nonce=await page.locator('meta[name=fixture-auth]').getAttribute('content');
  assert.equal(await page.locator('#candidate option').count(),3,'two admin rows'); assert.equal(await page.locator('#candidate').inputValue(),'');
  for(const index of ['0','1']) { await page.locator('#candidate').selectOption(index); await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('هویت درخواست را قطعی ثابت نمی‌کند')); assert.match(await page.locator('#message').textContent(),/لغوشده/); }
  await page.locator('#start').fill('14:00'); await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('همخوان نیست'));
  assert.equal(await page.evaluate(()=>localStorage.length+sessionStorage.length),0); assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth),false);
  const row={day_of_week:'monday',is_closed:0,start_time:'15:00',end_time:'20:00'};
  for(const key of [a,b]) {let r=await post(page,'/test/compare',nonce,doctor,{key,rows:[row]}); assert.equal(r.code,200); assert.equal(JSON.parse(r.body).status,'matched'); assert.match(r.cache,/no-store/);}
  assert.equal(JSON.parse((await post(page,'/test/compare',nonce,doctor,{key:ownerKey,rows:[row]})).body).status,'unknown');
  assert.equal((await post(page,'/test/history','bad',doctor)).code,403,'bad nonce');
  assert.equal((await post(page,'/test/history',nonce,'99999999')).code,403,'wrong doctor');
  assert.equal(await page.evaluate(async ({doctor,nonce})=>(await fetch('/test/history',{headers:{'X-Fixture-Doctor':doctor,'X-Fixture-Nonce':nonce}})).status,{doctor,nonce}),405);
  const own=await session('synthetic_doctor_a'); sessions.push(own); response=await own.page.goto(base+'/review?doctor='+doctor); assert.equal(response.status(),200); await own.page.waitForFunction(()=>!document.querySelector('#fields').disabled);
  const ownNonce=await own.page.locator('meta[name=fixture-auth]').getAttribute('content'); assert.equal(await own.page.locator('#candidate option').count(),2,'owner sees own record only');
  assert.equal((await post(own.page,'/test/history',nonce,doctor)).code,403,'nonce bound to session');
  assert.equal(JSON.parse((await post(own.page,'/test/status',ownNonce,doctor,{key:a})).body).status,'unknown','owner cannot inspect admin record');
  const patient=await session('synthetic_race_0'); sessions.push(patient); assert.equal((await patient.page.goto(base+'/review?doctor='+doctor)).status(),403);
  const guest=await session(); sessions.push(guest); assert.equal((await guest.page.goto(base+'/review?doctor='+doctor)).status(),403);
  console.log('PASS: real WordPress authenticated loopback HTTP and Chromium: actor-scoped review, matching ambiguity, nonce and permissions');
 } finally { for(const s of sessions) await s.context.close(); await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});

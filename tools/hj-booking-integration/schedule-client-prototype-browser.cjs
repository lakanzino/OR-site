/* Real Chromium + local synthetic transport, not WordPress/OAuth/E2E. */
'use strict';
const http = require('node:http'), fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const {chromium} = require('playwright-core');
const dir=__dirname, html=fs.readFileSync(path.join(dir,'schedule-client-prototype.html'),'utf8'), js=fs.readFileSync(path.join(dir,'schedule-client-prototype.js'),'utf8');
let revision=3, calls=0, queryMode='not_found', receipt=null, savedStart='09:00';
function json(res,data) {res.writeHead(200,{'Content-Type':'application/json','Cache-Control':'no-store'});res.end(JSON.stringify(data));}
const server=http.createServer((req,res)=>{
  if (req.method==='GET' && req.url==='/') {res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'});res.end(html.replace('<body>','<body data-revision="'+revision+'" data-generation="2">').replace('value="09:00"','value="'+savedStart+'"'));return;}
  if (req.method==='GET' && req.url==='/client.js') {res.writeHead(200,{'Content-Type':'application/javascript'});res.end(js);return;}
  if (req.method!=='POST' || !['/test/save','/test/status','/test/current'].includes(req.url)) {res.writeHead(404);res.end();return;}
  let body=''; req.on('data',chunk=>{body+=chunk; if(body.length>4096) req.destroy();});
  req.on('end',()=>{
    let input; try {input=JSON.parse(body);} catch (_) {res.writeHead(400);res.end();return;}
    if(req.url==='/test/current') {json(res,{revision});return;}
    if(req.url==='/test/status') {json(res,queryMode==='completed' && receipt && input.key===receipt.key ? {status:'completed',revision:receipt.revision} : {status:queryMode});return;}
    calls++;
    if (!receipt) {if (!input.payload || !input.payload.rows || !/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(input.payload.rows[0].start)) {res.writeHead(400);res.end();return;} savedStart=input.payload.rows[0].start;revision++;receipt={key:input.key,revision};res.writeHead(200,{'Content-Type':'application/json'});res.write('{');setTimeout(()=>res.destroy(),30);return;}
    if (input.key===receipt.key) {json(res,{status:'replayed',revision:receipt.revision});return;}
    json(res,{status:'unknown'});
  });
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox']});
  try {
    const page=await browser.newPage({viewport:{width:375,height:850}});
    await page.goto('http://127.0.0.1:'+server.address().port+'/');
    await page.locator('#start').fill('09:15'); await page.locator('#save').click();
    await page.waitForFunction(()=>window.fixtureState()==='uncertain');
    assert.equal(calls,1); const key=await page.evaluate(()=>sessionStorage.getItem('hj-ci-schedule-pending-key'));
    assert.match(key,/^[a-f0-9]{32}$/); assert(!key.includes('09:15')); assert.equal(await page.locator('#save').isDisabled(),true);
    await page.reload(); await page.waitForFunction(()=>window.fixtureState()==='uncertain');
    assert.equal(await page.locator('#retry').isDisabled(),true,'reload must not resend without in-memory payload'); assert.equal(calls,1);
    await page.locator('#check').click(); await page.waitForFunction(()=>window.fixtureState()==='uncertain');
    assert.equal(await page.evaluate(()=>sessionStorage.getItem('hj-ci-schedule-pending-key')),key,'not-found is not proof of failure');
    queryMode='completed'; await page.locator('#check').click(); await page.waitForFunction(()=>window.fixtureState()==='resolved');
    assert.equal(await page.evaluate(()=>sessionStorage.getItem('hj-ci-schedule-pending-key')),null); assert.equal(calls,1);
    assert.match(await page.locator('#status').textContent(),/بازخوانی/); assert.equal(await page.locator('#save').isDisabled(),true,'resolved view must not submit old form');
    await page.reload(); await page.waitForFunction(()=>window.fixtureState()==='idle'); assert.equal(await page.locator('body').getAttribute('data-revision'),'4'); assert.equal(await page.locator('#start').inputValue(),'09:15');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth),false);
    await page.close();
    const restricted=await browser.newContext({viewport:{width:375,height:850}});
    await restricted.addInitScript(() => Object.defineProperty(window,'sessionStorage',{get(){throw new Error('storage blocked');}}));
    const blockedPage=await restricted.newPage(); await blockedPage.goto('http://127.0.0.1:'+server.address().port+'/');
    await blockedPage.waitForFunction(()=>window.fixtureState()==='storage_error');
    assert.equal(await blockedPage.locator('#save').isDisabled(),true); assert.equal(calls,1); await restricted.close();
    console.log('PASS: synthetic mobile browser handles lost response, reload, ambiguous status, restricted storage and historical receipt without storing payload');
  } finally {await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});

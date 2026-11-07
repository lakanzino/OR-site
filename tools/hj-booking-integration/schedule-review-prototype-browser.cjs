/* Real Chromium, synthetic local server only: NOT WordPress or an authenticated API. */
'use strict';
const http=require('node:http'), fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
const {chromium}=require('playwright-core');
const html=fs.readFileSync(path.join(__dirname,'schedule-review-prototype.html'),'utf8'), js=fs.readFileSync(path.join(__dirname,'schedule-review-prototype.js'),'utf8');
const keyA='a'.repeat(32), keyB='b'.repeat(32);
let historyMode='ok', compareMode='normal', currentMode='ok', delay=0, calls=[];
function json(res,value) { res.writeHead(200,{'Content-Type':'application/json','Cache-Control':'no-store'}); res.end(JSON.stringify(value)); }
const server=http.createServer((req,res)=>{
  if(req.method==='GET' && req.url==='/') {res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'});res.end(html);return;}
  if(req.method==='GET' && req.url==='/review.js') {res.writeHead(200,{'Content-Type':'application/javascript'});res.end(js);return;}
  if(req.method!=='POST' || !['/test/history','/test/compare','/test/status','/test/current'].includes(req.url)) {res.writeHead(404);res.end();return;}
  let body=''; req.on('data',chunk=>{body+=chunk;if(body.length>4096)req.destroy();}); req.on('end',()=>{
    let input;try{input=JSON.parse(body);}catch(_){res.writeHead(400);res.end();return;} calls.push(req.url);
    if(req.url==='/test/history') {json(res,{status:'ok',items:[{key:keyA,status:'cancelled',created_utc:historyMode==='bad' ? '<img src=x onerror=alert(1)>' : '2026-11-07 12:00:00'},{key:keyB,status:'cancelled',created_utc:'2026-11-07 12:00:00'}],next:null});return;}
    if(req.url==='/test/compare') {
      const row=Array.isArray(input.rows) && input.rows[0];
      const result=compareMode==='unknown' ? {status:'unknown'} : {status:(input.key===keyA || input.key===keyB) && row && row.start_time==='15:00' && row.end_time==='20:00' ? 'matched' : 'different'};
      setTimeout(()=>json(res,result),delay);return;
    }
    if(req.url==='/test/status') {json(res,{status:(input.key===keyA || input.key===keyB) ? 'cancelled' : 'unknown'});return;}
    if(currentMode==='error') {res.writeHead(503);res.end();return;} json(res,{revision:6});
  });
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox']});
  try {
    const page=await browser.newPage({viewport:{width:375,height:850}}), url='http://127.0.0.1:'+server.address().port+'/';
    await page.goto(url); await page.waitForFunction(()=>!document.querySelector('#fields').disabled);
    assert.equal(await page.locator('#candidate').inputValue(),'','no item is preselected');
    await page.locator('#compare').click(); assert.match(await page.locator('#message').textContent(),/ابتدا/); assert.equal(calls.filter(x=>x==='/test/compare').length,0);
    await page.locator('#candidate').selectOption('0'); await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('هویت درخواست را قطعی ثابت نمی‌کند'));
    assert.match(await page.locator('#message').textContent(),/لغوشده/); assert.equal(calls.filter(x=>x==='/test/compare').length,1);
    assert.equal(await page.evaluate(()=>sessionStorage.length+localStorage.length),0); assert.equal(await page.evaluate(k=>document.documentElement.textContent.includes(k),keyA),false);
    await page.locator('#candidate').selectOption('1'); await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('ممکن است چند ردیف'));
    assert.equal(calls.filter(x=>x==='/test/compare').length,2,'identical rows can both match; user explicitly chooses each');
    await page.locator('#start').fill('14:00'); await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('همخوان نیست'));
    compareMode='unknown'; await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('ناموفق یا نامعلوم'));
    compareMode='normal'; currentMode='error'; await page.locator('#start').fill('15:00'); await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('ناموفق یا نامعلوم'));
    currentMode='ok'; delay=200; const before=calls.filter(x=>x==='/test/compare').length;
    await page.locator('#compare').click(); await page.waitForFunction(()=>document.querySelector('#fields').disabled);
    await page.evaluate(()=>{document.querySelector('#review').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));const el=document.querySelector('#start');el.value='14:00';el.dispatchEvent(new Event('input',{bubbles:true}));});
    await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('نتیجه کنار گذاشته شد'));
    assert.equal(calls.filter(x=>x==='/test/compare').length,before+1,'busy guard prevents a duplicate request');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth),false);
    assert(calls.every(x=>['/test/history','/test/compare','/test/status','/test/current'].includes(x)),'no save, cancel, or acknowledge endpoint');
    historyMode='bad'; await page.reload(); await page.waitForFunction(()=>document.querySelector('#message').textContent.includes('فهرست در دسترس نیست'));
    assert.equal(await page.locator('#compare').isDisabled(),true); assert.equal(await page.locator('img').count(),0,'untrusted history is never rendered as HTML');
    await page.close(); console.log('PASS: synthetic mobile review requires explicit selection, rejects stale results and performs no mutation or browser storage');
  } finally {await browser.close(); await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});

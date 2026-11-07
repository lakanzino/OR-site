/* Real WordPress-rendered form + dashboard script, isolated Chromium with mocked fetch. */
'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright-core');
(async () => {
  const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox']});
  const html=fs.readFileSync(process.env.HJ_SECONDS_HTML,'utf8');
  async function setup() {
    const page=await browser.newPage({viewport:{width:375,height:900}});
    await page.setContent(html);
    await page.evaluate(() => {
      window.HJ_DASH={nonce:'fixture',ajax:'/not-used',i18n:{fail:'failed'}};
      window.__count=0; window.__timer=null; window.__resolve=null; window.__reject=null;
      const nativeSet=window.setTimeout.bind(window),nativeClear=window.clearTimeout.bind(window);
      window.setTimeout=(fn,ms)=>ms===30000?(window.__timer=fn,99999):nativeSet(fn,ms);
      window.clearTimeout=id=>id===99999?(window.__timer=null):nativeClear(id);
      window.fetch=(url,options)=>{window.__count++; window.__form=Object.fromEntries(options.body); return new Promise((resolve,reject)=>{window.__resolve=resolve;window.__reject=reject;});};
      window.__send=()=>document.getElementById('hj-dash-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
      window.__reply=(status,body)=>window.__resolve({status,json:()=>Promise.resolve(body)});
      window.__badJson=()=>window.__resolve({status:200,json:()=>Promise.reject(new Error('truncated'))});
    });
    await page.addScriptTag({path:path.join(process.env.GITHUB_WORKSPACE,'plugins/hamyar-doctors/assets/js/dashboard.js')});
    return page;
  }
  try {
    let page=await setup();
    await page.evaluate(()=>{window.__send();window.__send();});
    assert.equal(await page.evaluate(()=>window.__count),1);
    assert.equal(await page.locator('[data-hj="save"]').isDisabled(),true);
    assert.equal(await page.evaluate(()=>window.__form['hours[wednesday][start]']),'09:00:30');
    assert.equal(await page.evaluate(()=>window.__form['hours[friday][present]']),'1');
    await page.evaluate(()=>window.__reply(422,{success:false,message:'نامعتبر',errors:{name_fa:'نامعتبر'}}));
    await page.waitForFunction(()=>!document.querySelector('[data-hj="save"]').disabled);
    assert.equal(await page.locator('[data-err="form"]').textContent(),'نامعتبر');
    const rev=await page.locator('[name="hj_schedule_revision"]').inputValue();
    await page.evaluate(()=>window.__send());
    await page.evaluate(value=>window.__reply(200,{success:true,data:{schedule_revision:Number(value)+1}}),rev);
    await page.waitForFunction(value=>document.querySelector('[name="hj_schedule_revision"]').value===String(Number(value)+1),rev);
    assert.equal(await page.locator('[data-hj="save"]').isDisabled(),false);
    await page.close();
    for (const scenario of ['network','503','409','bad-json','bad-revision','timeout']) {
      page=await setup(); await page.evaluate(()=>window.__send());
      await page.evaluate(kind=>{
        if(kind==='network') window.__reject(new Error('offline'));
        if(kind==='503'||kind==='409') window.__reply(Number(kind),{success:false,message:'problem'});
        if(kind==='bad-json') window.__badJson();
        if(kind==='bad-revision') window.__reply(200,{success:true,data:{schedule_revision:'wrong'}});
        if(kind==='timeout') {window.__timer(); window.__reply(200,{success:true,data:{schedule_revision:Number(window.__form.hj_schedule_revision)+1}});}
      },scenario);
      await page.waitForFunction(()=>document.querySelector('[data-err="form"]').textContent.includes('نتیجهٔ ذخیره مشخص نیست'));
      assert.equal(await page.locator('[data-hj="save"]').isDisabled(),true,scenario);
      await page.evaluate(()=>window.__send()); assert.equal(await page.evaluate(()=>window.__count),1,scenario);
      await page.close();
    }
    console.log('PASS Chromium real dashboard form: pending, validation, unknown results and late response');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});

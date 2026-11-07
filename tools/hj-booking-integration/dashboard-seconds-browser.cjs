/* Actual WP-rendered form and dashboard JS in isolated Chromium; HTTP tested separately. */
'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
const {chromium} = require('playwright-core');
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport:{width:375,height:900}});
    await page.setContent(fs.readFileSync(process.env.HJ_SECONDS_HTML,'utf8'));
    await page.evaluate(() => { window.HJ_DASH={nonce:'synthetic',ajax:'/not-used',i18n:{}}; });
    await page.addScriptTag({path:path.join(process.env.GITHUB_WORKSPACE,'plugins/hamyar-doctors/assets/js/dashboard.js')});
    const values = await page.evaluate(() => {
      const form=document.querySelector('#hj-dash-form');
      const start=form.elements.namedItem('hours[wednesday][start]');
      const end=form.elements.namedItem('hours[wednesday][end]');
      const snapshot=() => Object.fromEntries([...new FormData(form)].filter(([key]) => key.startsWith('hours[')));
      const data=new FormData(form);
      const result={start:data.get(start.name),end:data.get(end.name),precision:data.get('hj_schedule_precision'),valid:start.validity.valid && end.validity.valid,stepStart:start.step,stepEnd:end.step,hours:snapshot()};
      const checkbox=form.elements.namedItem('hours[wednesday][closed]');
      checkbox.checked=true; checkbox.dispatchEvent(new Event('change',{bubbles:true}));
      result.disabled=start.disabled && end.disabled; result.closed_hours=snapshot();
      return result;
    });
    assert.equal(values.valid,true); assert.equal(values.stepStart,'1'); assert.equal(values.stepEnd,'1');
    for (const day of ['saturday','sunday','monday','tuesday','wednesday','thursday','friday']) assert.equal(values.hours[`hours[${day}][present]`],'1');
    assert.equal(values.hours['hours[thursday][start]'],''); assert.equal(values.hours['hours[thursday][end]'],'');
    assert.equal(values.disabled,true); assert.equal(values.closed_hours['hours[wednesday][present]'],'1'); assert.equal(values.closed_hours['hours[wednesday][closed]'],'1');
    assert.equal('hours[wednesday][start]' in values.closed_hours,false); assert.equal('hours[wednesday][end]' in values.closed_hours,false);
    delete values.valid; delete values.stepStart; delete values.stepEnd; delete values.disabled;
    console.log(JSON.stringify(values));
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});

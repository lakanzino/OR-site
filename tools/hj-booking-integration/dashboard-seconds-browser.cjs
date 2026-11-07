/* Actual WP-rendered form, isolated Chromium. HTTP submission is tested separately. */
'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require('playwright-core');
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport:{width:375,height:900}});
    await page.setContent(fs.readFileSync(process.env.HJ_SECONDS_HTML,'utf8'));
    const values = await page.evaluate(() => {
      const form=document.querySelector('#hj-dash-form');
      const start=form.elements.namedItem('hours[wednesday][start]');
      const end=form.elements.namedItem('hours[wednesday][end]');
      const data=new FormData(form);
      return {start:data.get(start.name),end:data.get(end.name),precision:data.get('hj_schedule_precision'),valid:start.validity.valid && end.validity.valid,stepStart:start.step,stepEnd:end.step};
    });
    assert.equal(values.valid,true); assert.equal(values.stepStart,'1'); assert.equal(values.stepEnd,'1');
    console.log(JSON.stringify({start:values.start,end:values.end,precision:values.precision}));
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});

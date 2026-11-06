/** Real isolated Chromium UI with mocked transport; HTTP endpoint is separately exercised against WP. */
const {chromium} = require('playwright-core');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const fixture = JSON.parse(fs.readFileSync(process.env.HJ_PATIENT_CALENDAR,'utf8'));
const dir = path.join(process.env.GITHUB_WORKSPACE,'plugins/hamyar-doctors/assets');
const css = fs.readFileSync(path.join(dir,'css/booking-form.css'),'utf8');
const script = fs.readFileSync(path.join(dir,'js/booking-form.js'),'utf8');
(async()=>{
 const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox']});
 try {
  for (const width of [375,1024]) {
   const page=await browser.newPage({viewport:{width,height:900}}); let createRequests=[]; let failed=false;
   await page.route('**/*',async route=>{
    const req=route.request();
    if(req.url().endsWith('/calendar-fixture')) return route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>'+fixture.patient+'</body></html>'});
    if(req.url().includes('admin-ajax.php')) {
     const data=new URLSearchParams(req.postData() || ''); const action=data.get('action');
     if(action==='hj_booking_calendar') {
      if(data.get('doctor_id')===fixture.legacy) return route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({success:false,data:{code:'legacy_schedule',message:'تقویم ظرفیت آماده نیست؛ از انتخاب تاریخ استفاده کنید.'}})});
      return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:true,data:fixture.calendar})});
     }
     if(action==='hj_booking_slots') return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:true,data:{revision:fixture.calendar.revision,slots:[{start:'2030-01-01 05:30:00',label:'09:00'}]}})});
     if(action==='hj_booking_create') {
      createRequests.push(Object.fromEntries(data));
      if(!failed) { failed=true; return route.abort('failed'); }
      return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:true,data:{status:'replayed',id:8,message:'درخواست ثبت شد'}})});
     }
    }
    return route.abort();
   });
   await page.goto('http://example.invalid/calendar-fixture'); await page.addStyleTag({content:css}); await page.addScriptTag({content:script});
   const doc=page.locator('select[name=doctor_id]'); await doc.selectOption(fixture.doctor);
   const days=page.locator('.hj-booking__calendar-days button'); await days.first().waitFor();
   assert.equal(await days.count(),14); assert.equal(await page.locator('.hj-booking__date').isHidden(),true);
   assert.equal(await days.nth(2).isEnabled(),true); assert.equal(await days.nth(1).isDisabled(),true);
   assert.match(await days.nth(2).innerText(),/جای خالی/); assert.match(await days.nth(2).innerText(),/[۰-۹]/);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth),false,'calendar must fit viewport');
   assert.equal(await page.locator('.hj-booking').evaluate(el=>getComputedStyle(el).direction),'rtl');
   await days.nth(2).click(); assert.equal(await days.nth(2).getAttribute('aria-pressed'),'true');
   const slot=page.locator('.hj-booking__slot'); await slot.waitFor(); await slot.click();
   await page.locator('input[name=first_name]').fill('نام مستقل'); await page.locator('input[name=last_name]').fill('بیمار آزمایشی');
   await page.locator('input[name=phone]').fill('۰۹۱۲۳۴۵۶۷۸۹'); await page.locator('select[name=service]').selectOption('visit');
   await page.locator('textarea[name=note]').fill('یادداشت آزمایشی');
   await page.locator('.hj-booking__form [type=submit]').click(); assert.equal(createRequests.length,0,'review must precede submission');
   assert.match(await page.locator('.hj-booking__review-summary').innerText(),/نام مستقل/); await page.locator('.hj-booking__edit').click();
   assert.equal(await page.locator('.hj-booking__review').isHidden(),true,'cancel returns to editable form');
   await page.locator('.hj-booking__form [type=submit]').click(); await page.locator('.hj-booking__confirm').click(); await page.getByText('نتیجه مشخص نیست', {exact:false}).waitFor();
   assert.equal(await doc.isDisabled(),true); assert.equal(await days.nth(2).isDisabled(),true);
   const saved=await page.evaluate(()=>JSON.stringify(sessionStorage)); assert(!saved.includes('09123456789') && !saved.includes('1234567891') && !saved.includes('نام مستقل') && !saved.includes('یادداشت آزمایشی'));
   await page.locator('.hj-booking__form [type=submit]').click(); await page.getByText('درخواست ثبت شد',{exact:false}).waitFor();
   assert.equal(createRequests.length,2); assert.equal(createRequests[0].idempotency_key,createRequests[1].idempotency_key); assert.equal(createRequests[0].national_code,''); assert.equal(createRequests[0].first_name,'نام مستقل');
   await page.waitForFunction(()=>!document.querySelector('.hj-booking__calendar-retry').disabled);
   await doc.selectOption(fixture.legacy); await page.getByText('تقویم ظرفیت آماده نیست', {exact:false}).waitFor();
   assert.equal(await page.locator('.hj-booking__date').isVisible(),true,'legacy schedule keeps manual date fallback');
   assert.equal(await days.count(),0);
   await page.close(); console.log('PASS: experimental patient calendar, accessible states and retry at '+width+'px');
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

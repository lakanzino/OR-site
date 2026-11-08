// Real WP rendering with a disposable fixture; no Google or live data involved.
if (process.env.GITHUB_ACTIONS !== 'true' || process.env.HJ_DISPOSABLE_MARIADB_FIXTURE !== '1') throw Error('CI fixture only');
const {chromium} = require('playwright-core');
const assert = require('node:assert/strict');
const base = 'http://example.invalid';
(async () => {
 const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage','--no-proxy-server','--host-resolver-rules=MAP example.invalid 127.0.0.1']});
 try {
  const page = await browser.newPage({viewport:{width:375,height:850}, isMobile:true, hasTouch:true});
  await page.goto(base + '/wp-login.php', {waitUntil:'domcontentloaded'});
  await page.locator('#user_login').fill('responsive_surgeon');
  await page.locator('#user_pass').fill('disposable-password-please-ignore');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
  for (const width of [280,320,360,375,393,414,768,1024]) {
   await page.setViewportSize({width,height:850});
   const response = await page.goto(base + '/?pagename=my-profile', {waitUntil:'networkidle'});
   assert.equal(response.status(),200); assert.equal(await page.locator('#hj-dash-form').count(), 1, `dashboard form missing at ${page.url()}: ${(await page.locator('body').innerText()).slice(0,500)}`);
   await page.evaluate(() => document.fonts.ready);
   assert.ok(await page.locator('.hj-dash__chip input').count() >= 18, 'fixture has multiple specialty choices');
   const oversizedChips = await page.locator('.hj-dash__chip input').evaluateAll(els => els.filter(e => {const r=e.getBoundingClientRect(), p=e.parentElement.getBoundingClientRect();return r.width > p.width + 1 || r.left < p.left - 1 || r.right > p.right + 1;}).slice(0,5).map(e => ({input:e.getBoundingClientRect().toJSON(), label:e.parentElement.getBoundingClientRect().toJSON()})));
   assert.equal(oversizedChips.length, 0, `${width} invisible checkbox overflows its label: ${JSON.stringify(oversizedChips)}`);
   const fields = ['.hj-dash__head','.hj-dash__photo','.hj-dash__grid','.hj-dash__form','.hj-dash__side','.hj-dash__card', '.hj-dash__field input:not([type=hidden])','.hj-dash__field select','.hj-dash__field textarea','.hj-dash__hrow input[type=time]','.hj-dash__chip span','#hj-bookings','.hj-bookings__item'];
   const report = await page.evaluate((selectors) => {
    const vw=document.documentElement.clientWidth;
    const rects=[];
    for (const selector of selectors) for (const el of document.querySelectorAll(selector)) {
      if (!el.getClientRects().length || getComputedStyle(el).visibility === 'hidden') continue;
      const r=el.getBoundingClientRect(); if (r.left < -1 || r.right > vw+1) rects.push({selector,tag:el.tagName,left:r.left,right:r.right,width:r.width,text:el.textContent.trim().slice(0,35)});
    }
    return {vw, scroll:document.documentElement.scrollWidth, body:document.body.scrollWidth, rects:rects.slice(0,12)};
   },fields);
   assert.ok(report.scroll<=width+1 && report.body<=width+1 && !report.rects.length, `${width} overflow: ${JSON.stringify(report)}`);
   if(width<=640) {
    const columns = await page.locator('.hj-dash__grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns);
    assert.equal(columns.split(' ').length,1, `${width} dashboard must be one column: ${columns}`);
   }
   await page.locator('input[name="name_fa"]').fill('نمونه تست');
   assert.equal(await page.locator('input[name="name_fa"]').inputValue(),'نمونه تست');
   assert.ok(await page.locator('.hj-dash__btn[data-hj=save]').isVisible());
   const time = page.locator('.hj-dash__hrow input[type=time]').first();
   await time.scrollIntoViewIfNeeded(); await time.focus();
   assert.ok(await time.isVisible());
   const bounds = await time.boundingBox(); assert.ok(bounds.x >= -1 && bounds.x+bounds.width<=width+1,`${width} time input fits`);
   console.log(`PASS dashboard mobile fit ${width}px`);
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});

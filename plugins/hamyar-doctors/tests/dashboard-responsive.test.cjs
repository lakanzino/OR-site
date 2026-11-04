// Run only against a disposable WordPress instance with a test doctor.
// WP_TEST_URL, WP_TEST_USER and WP_TEST_PASS must be supplied; playwright-core
// and CHROMIUM_PATH are supplied by the test environment.
const { chromium } = require('playwright-core');
const assert = require('node:assert/strict');
(async () => {
  const { WP_TEST_URL: url, WP_TEST_USER: user, WP_TEST_PASS: password } = process.env;
  if (!url || !user || !password) throw new Error('Disposable WordPress test URL and test credentials are required.');
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH, args: ['--no-sandbox', '--disable-dev-shm-usage'], headless: true });
  const page = await browser.newPage();
  await page.goto(url + '/wp-login.php');
  await page.locator('#user_login').fill(user); await page.locator('#user_pass').fill(password); await page.locator('#wp-submit').click();
  await page.goto(url + '/my-profile/'); await page.locator('#hj-dash-form').waitFor();
  for (const width of [280, 320, 360, 375, 414, 540, 640, 768, 900, 1024, 1280, 1440, 1920]) {
    await page.setViewportSize({ width, height: 850 });
    const view = await page.evaluate(() => {
      const box = e => { const r = e.getBoundingClientRect(); return { left:r.left, right:r.right, top:r.top, bottom:r.bottom, width:r.width }; };
      const dash = document.querySelector('.hj-dash'), grid = dash.querySelector('.hj-dash__grid'), pair = dash.querySelector('.hj-dash__pair');
      const row = dash.querySelector('.hj-dash__hrow'), start = row.querySelector('[aria-label="شروع"]'), end = row.querySelector('[aria-label="پایان"]');
      const targets = [...dash.querySelectorAll('input:not([type=hidden]):not([type=file]), textarea, select, button:not([hidden])')].filter(e => e.getClientRects().length);
      return { scroll:document.documentElement.scrollWidth, dash:box(dash), form:box(dash.querySelector('.hj-dash__form')), side:box(dash.querySelector('.hj-dash__side')), pairColumns:getComputedStyle(pair).gridTemplateColumns.split(' ').length, gridColumns:getComputedStyle(grid).gridTemplateColumns.split(' ').length, row:box(row), start:box(start), end:box(end), fieldOverflow:targets.filter(e => e.scrollWidth > e.clientWidth + 1 && !['checkbox','radio'].includes(e.type)).map(e=>e.outerHTML.slice(0,100)), targets:targets.map(box) };
    });
    assert(view.scroll <= width + 1, `${width}: document overflow ${view.scroll}`);
    assert.equal(view.fieldOverflow.length,0,`${width}: field overflow ${JSON.stringify(view.fieldOverflow)}`);
    for (const r of view.targets) assert(r.left >= -1 && r.right <= width + 1, `${width}: control off-screen ${JSON.stringify(r)}`);
    assert(view.start.width >= 120 && view.end.width >= 120, `${width}: time controls are too narrow`);
    assert(view.start.left >= view.row.left && view.start.right <= view.row.right && view.end.left >= view.row.left && view.end.right <= view.row.right, `${width}: times outside day row`);
    if (view.dash.width <= 540) { assert.equal(view.pairColumns,1,`${width}: fields should stack`); assert(view.end.top >= view.start.bottom,`${width}: time inputs should stack`); }
    if (view.dash.width <= 820) { assert.equal(view.gridColumns,1,`${width}: sidebar must stack`); assert(view.side.top >= view.form.bottom,`${width}: sidebar overlaps form`); }
    if (width >= 1280) assert(Math.abs((view.dash.left + view.dash.right)/2-width/2)<4, `${width}: dashboard not centered`);
    console.log('PASS viewport', width);
  }
  await page.setViewportSize({width:375,height:850});
  const name = page.locator('#hj-dash-name_fa'); await name.fill('پزشک آزمایشی');
  assert.equal(await page.locator('[data-pvout="name_fa"]').innerText(),'پزشک آزمایشی');
  const row=page.locator('.hj-dash__hrow').first(), toggle=row.locator('[data-hj="closed"]');
  await toggle.check(); assert(await row.locator('input[type=time]').first().isDisabled());
  await toggle.uncheck(); assert(await row.locator('input[type=time]').first().isEnabled());
  const responsePromise = page.waitForResponse(r=>r.url().includes('admin-ajax.php') && r.request().postData()?.includes('hj_dash_save'));
  await page.locator('[data-hj="save"]').click(); const response = await responsePromise;
  assert.equal(response.status(),200,`Save HTTP ${response.status()}`);
  await page.waitForFunction(() => Number(document.querySelector('#hj-dash-form input[name=pid]')?.value) > 0);
  await page.reload(); await page.locator('#hj-dash-form').waitFor();
  assert.equal(await page.locator('#hj-dash-name_fa').inputValue(),'پزشک آزمایشی');
  console.log('PASS live preview, working-hours toggle, and AJAX save on disposable site');
  await browser.close();
})().catch(error=>{ console.error(error); process.exit(1); });

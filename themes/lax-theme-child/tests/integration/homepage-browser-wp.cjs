// Real WordPress + MariaDB, synthetic data and CI only.
if (process.env.GITHUB_ACTIONS !== 'true' || process.env.HJ_DISPOSABLE_MARIADB_FIXTURE !== '1') throw Error('CI fixture only');
const {chromium} = require('playwright-core');
const assert = require('node:assert/strict');
const base = 'http://example.invalid';
(async () => {
 const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage','--no-proxy-server','--host-resolver-rules=MAP example.invalid 127.0.0.1']});
 try {
  const page = await browser.newPage({viewport:{width:375,height:850}});
  let response = await page.goto(base + '/', {waitUntil:'domcontentloaded'});
  assert.equal(response.status(),200); assert.match(await page.locator('.hj-landing-hero h1').innerText(), /آگاهی بیشتر/);
  // Before any admin action, version 0.33.25 must display the three known
  // surgeon labels but must not touch the stored hero or patient grid.
  assert.match(await page.locator('.hj-landing-note').first().innerText(), /جراح هستید؟/);
  assert.equal(await page.getByText('جراحان و متخصصان', {exact:true}).count(), 1);
  const dashboardLink = page.getByRole('link', {name:'ورود به پنل جراح'});
  assert.equal(await dashboardLink.count(), 1);
  assert.equal(new URL(await dashboardLink.getAttribute('href')).pathname, '/my-profile/');
  assert.equal(await page.getByText('ویژه دانشجویان اتاق عمل', {exact:true}).count(), 0);
  assert.equal(await page.getByText('اتاق عمل، تیم و تجهیزات', {exact:true}).count(), 0);
  const patientGrid = page.locator('.hj-landing-grid').first();
  for (const [text,path] of [['راهنمای پیش از عمل', '/guide-stage/before/'], ['راهنمای حین عمل', '/guide-stage/during/'], ['راهنمای پس از عمل', '/guide-stage/after/']]) {
    const link = patientGrid.getByRole('link', {name:new RegExp(text)});
    assert.equal(await link.count(), 1, text);
    assert.equal(new URL(await link.getAttribute('href')).pathname, path);
  }
  const patientHtmlBefore = await patientGrid.innerHTML();
  const position = await page.evaluate(() => ({content:document.querySelector('.entry-content')?.getBoundingClientRect().bottom, login:document.querySelector('.hj-member-home-entry')?.getBoundingClientRect().top}));
  assert.ok(position.login > position.content, 'automatic member login is below content');
  await page.goto(base + '/wp-login.php', {waitUntil:'domcontentloaded'});
  await page.locator('#user_login').fill('fixture_admin'); await page.locator('#user_pass').fill('disposable-password-please-ignore');
  await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
  await page.goto(base + '/wp-admin/themes.php?page=hj-orbit-home', {waitUntil:'domcontentloaded'});
  assert.equal(await page.locator('input[name="action"][value="hj_orbit_apply"]').count(),1, 'admin apply form');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded', timeout:30000}), page.locator('form input[type="submit"]').click()]);
  if (!page.url().includes('/wp-admin/post.php?')) {
   const body = (await page.locator('body').innerText()).slice(0,1000);
   await page.goto(base + '/wp-admin/admin-post.php?action=hj_fixture_revision_debug');
   throw new Error('apply failed: ' + body + ' diagnostic=' + (await page.locator('body').innerText()).slice(0,1600));
  }
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0, null, {timeout:45000});
  const editor = await page.evaluate(() => {
   const walk = blocks => blocks.flatMap(b => [b, ...walk(b.innerBlocks || [])]);
   const blocks = walk(window.wp.data.select('core/block-editor').getBlocks());
   return blocks.filter(b => ['hj-hero-title', 'hj-landing-lead'].some(c => (b.attributes?.className || '').split(/\s+/).includes(c))).map(b => ({name:b.name, valid:b.isValid, className:b.attributes.className}));
  });
  assert.equal(editor.length,2, `editable hero blocks found: ${JSON.stringify(editor)}`);
  assert.ok(editor.every(block => block.valid === true), `edited blocks valid in Gutenberg: ${JSON.stringify(editor)}`);
  await page.goto(base + '/wp-admin/admin-post.php?action=hj_fixture_revision_debug');
  const revisionReport = JSON.parse(await page.locator('body').innerText());
  assert.equal(revisionReport.expected_found, true, 'updated content retains other blocks byte-for-byte and has a pre-change revision');
  for (const width of [280,320,360,375,414,768,980,1280,1440]) {
   await page.setViewportSize({width,height:900});
   response=await page.goto(base + '/', {waitUntil:'domcontentloaded'}); assert.equal(response.status(),200);
   await page.waitForSelector('.hj-hero-slogan'); await page.waitForSelector('.hj-surgeon-card');
   assert.equal(await page.locator('.hj-landing-grid').first().innerHTML(), patientHtmlBefore, `${width} patient grid preserved through manager action`);
   assert.match(await page.locator('.hj-hero-slogan').innerText(), /آرامش در مسیر جراحی با همیار\s*جراحی/);
   await page.evaluate(async () => {await document.fonts.ready;});
   const m=await page.evaluate(() => {
    const rect=e=>e.getBoundingClientRect();
    const hero=rect(document.querySelector('.hj-landing-hero')), copy=rect(document.querySelector('.hj-landing-hero-copy'));
    const title=document.querySelector('.hj-hero-title'), h=rect(title), t=rect(title.querySelector('.hj-hero-slogan'));
    const cards=[...document.querySelectorAll('.hj-surgeon-card')].map(el=>{const r=rect(el);return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,width:r.width,link:!!el.querySelector('a[href]')}});
    return {scroll:document.documentElement.scrollWidth,hero:hero.width,copy:copy.width,titleleft:h.left,titleright:h.right,textleft:t.left,textright:t.right,nowrap:getComputedStyle(title).whiteSpace,cards,viewport:document.querySelector('meta[name="viewport"]')?.content};
   });
   assert.match(m.viewport||'',/width=device-width/);
   assert.ok(m.scroll<=width+1, `${width} page overflow ${JSON.stringify(m)}`);
   assert.ok(Math.abs(m.hero-m.copy)<3, `${width} hero ${JSON.stringify(m)}`);
   assert.equal(m.nowrap,'nowrap'); assert.ok(m.textleft>=m.titleleft-1 && m.textright<=m.titleright+1, `${width} slogan clipped ${JSON.stringify(m)}`);
   assert.ok(m.cards.length>=3, `${width} cards present ${JSON.stringify(m)}`);
   for(let i=0;i<m.cards.length;i++){let c=m.cards[i];assert.ok(c.link && c.left>=-1 && c.right<=width+1, `${width} card bounds`); if(i) assert.ok(c.top>=m.cards[i-1].bottom,`${width} stacked cards`);}
   console.log(`PASS real WP at ${width}px`);
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});

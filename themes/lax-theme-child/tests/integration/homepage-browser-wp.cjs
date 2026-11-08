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
  assert.equal(await page.getByRole('link', {name:'ثبت‌نام جراح با گوگل'}).count(), 0, 'extra surgeon button hidden only on homepage');
  const patientEntry = page.locator('.hj-member-home-entry');
  assert.match(await patientEntry.innerText(), /برای ثبت نوبت در سامانه/);
  assert.equal(new URL(await patientEntry.locator('.hj-member-google').getAttribute('href')).searchParams.get('hj_member_google'), '1');
  assert.equal(await patientEntry.locator('.hj-member-google__icon svg path').count(), 4, 'multicolour Google icon');
  assert.equal(await page.getByText('ویژه دانشجویان اتاق عمل', {exact:true}).count(), 0);
  assert.equal(await page.getByText('اتاق عمل، تیم و تجهیزات', {exact:true}).count(), 0);
  const patientGrid = page.locator('.hj-landing-grid').first();
  for (const [text,path] of [['راهنمای پیش از عمل', '/guide-stage/before/'], ['راهنمای حین عمل', '/guide-stage/during/'], ['راهنمای پس از عمل', '/guide-stage/after/']]) {
    const link = patientGrid.getByRole('link', {name:new RegExp(text)});
    assert.equal(await link.count(), 1, text);
    assert.equal(new URL(await link.getAttribute('href')).pathname, path);
  }
  const patientHtmlBefore = await patientGrid.innerHTML();
  const newsletter = page.locator('.hj-magazine-section');
  assert.equal(await newsletter.count(), 1, 'newsletter fixture present');
  assert.match(await newsletter.locator('h2').innerText(), /اینجا جیمیلت رو وارد کن/);
  assert.match(await newsletter.locator('h2').innerText(), /هر وقت هم نخواستی/);
  assert.equal(await newsletter.locator('.hj-magazine-cancel').innerText(), 'لغو');
  assert.equal(await newsletter.locator('a').count(), 0, 'extra links removed only in newsletter');
  assert.equal(await newsletter.locator('input[name=email][required]').count(), 1);
  assert.equal(await newsletter.locator('input[name=consent][required]').count(), 1);
  assert.match(await newsletter.innerText(), /لینک تأیید به ایمیل شما ارسال می‌شود/);
  await page.goto(base + '/?pagename=fixture-other-newsletter', {waitUntil:'domcontentloaded'});
  assert.match(await page.locator('.hj-magazine-section h2').innerText(), /اشتراک رایگان مجله اینترنتی/);
  assert.ok(await page.locator('.hj-magazine-section a').count() > 0, 'non-home newsletter left unchanged');
  await page.goto(base + '/', {waitUntil:'domcontentloaded'});

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
   const mag = await page.evaluate(() => {
     const r = e => { const b=e.getBoundingClientRect(); return {left:b.left,right:b.right,top:b.top,bottom:b.bottom,width:b.width}; };
     const section=document.querySelector('.hj-magazine-section'), above=[...document.querySelectorAll('.hj-landing-grid')].at(-1);
     const email=section.querySelector('input[name=email]'), button=section.querySelector('.hj-magazine-row button'), consent=section.querySelector('input[name=consent]'), label=section.querySelector('.hj-magazine-consent');
     const cancel=section.querySelector('.hj-magazine-cancel');
     return {section:r(section),above:r(above),email:r(email),button:r(button),consent:r(consent),label:r(label),color:getComputedStyle(cancel).color,weight:getComputedStyle(cancel).fontWeight};
   });
   assert.ok(mag.section.top - mag.above.bottom >= 39, `${width} newsletter spacing ${JSON.stringify(mag)}`);
   for (const [name,r] of Object.entries({section:mag.section,email:mag.email,button:mag.button,consent:mag.consent})) assert.ok(r.left>=-1 && r.right<=width+1 && r.width>0, `${width} ${name} bounds ${JSON.stringify(mag)}`);
   assert.ok(mag.consent.left >= mag.label.left-1 && mag.consent.right <= mag.label.right+1, `${width} checkbox inside label`);
   assert.equal(mag.color,'rgb(181, 31, 50)'); assert.ok(Number(mag.weight)>=700, 'cancel bold red');
   if (width<=600) assert.ok(mag.button.top >= mag.email.bottom, `${width} fields stack`);

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
  // Test actual pointer hit-testing on the stretched patient-row links in a
  // disposable browser; destination pages are intercepted, not live content.
  await page.route('**/guide-stage/**', route => route.fulfill({status:200, contentType:'text/html', body:'Synthetic destination'}));
  for (const width of [375, 1280]) {
   await page.setViewportSize({width, height:900});
   for (const [i, path] of ['/guide-stage/before/', '/guide-stage/during/', '/guide-stage/after/'].entries()) {
    await page.goto(base + '/', {waitUntil:'domcontentloaded'});
    const row = page.locator('.hj-landing-grid').first().locator('.hj-landing-card').nth(i);
    await row.scrollIntoViewIfNeeded();
    const box = await row.boundingBox(); assert.ok(box, `${width} row ${i} visible`);
    const target = await page.evaluate(({x,y}) => document.elementFromPoint(x,y)?.closest('a')?.getAttribute('href'), {x:box.x+box.width/2,y:box.y+box.height/2});
    assert.equal(new URL(target || '/', base).pathname, path, `${width} row ${i} hit target`);
    await Promise.all([page.waitForURL(url => url.pathname === path), page.mouse.click(box.x+box.width/2, box.y+box.height/2)]);
    assert.equal(await page.locator('body').innerText(), 'Synthetic destination');
   }
   console.log(`PASS synthetic patient row clicks at ${width}px`);
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});

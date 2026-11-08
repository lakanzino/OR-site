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
  const logoStyle = await page.locator('.site-header .custom-logo-link').evaluate(el => { const s = getComputedStyle(el); return {bg:s.backgroundColor, padding:s.padding, shadow:s.boxShadow}; });
  assert.equal(logoStyle.bg, 'rgba(0, 0, 0, 0)'); assert.equal(logoStyle.padding, '0px'); assert.equal(logoStyle.shadow, 'none');
  const newsletter = page.locator('.hj-magazine-section');
  assert.equal(await newsletter.count(), 1, 'newsletter fixture present');
  assert.match(await newsletter.locator('h2').innerText(), /اینجا جیمیلت رو وارد کن/);
  assert.match(await newsletter.locator('h2').innerText(), /هر وقت هم نخواستی/);
  assert.equal(await newsletter.locator('.hj-magazine-cancel').innerText(), 'لغو');
  assert.equal(await newsletter.locator('a').count(), 0, 'extra links removed only in newsletter');
  assert.doesNotMatch(await newsletter.innerText(), /حریم خصوصی/, 'no orphaned link label');
  assert.equal(await newsletter.locator('input[name=email][required]').count(), 1);
  assert.equal(await newsletter.locator('input[name=consent][type=checkbox]').count(), 0);
  assert.equal(await newsletter.locator('input[name=consent][type=hidden][value="1"]').count(), 1);
  assert.doesNotMatch(await newsletter.innerText(), /لینک تأیید به ایمیل شما ارسال می‌شود/);
  await page.goto(base + '/?pagename=fixture-other-newsletter', {waitUntil:'domcontentloaded'});
  assert.match(await page.locator('.hj-magazine-section h2').innerText(), /اشتراک رایگان مجله اینترنتی/);
  assert.ok(await page.locator('.hj-magazine-section a').count() > 0, 'non-home newsletter left unchanged');
  assert.equal(await page.locator('.hj-magazine-section input[name=consent][type=checkbox][required]').count(), 1, 'other page form unchanged');
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
     const email=section.querySelector('input[name=email]'), button=section.querySelector('.hj-magazine-row button');
     const cancel=section.querySelector('.hj-magazine-cancel');
     return {section:r(section),above:r(above),email:r(email),button:r(button),color:getComputedStyle(cancel).color,weight:getComputedStyle(cancel).fontWeight};
   });
   assert.ok(mag.section.top - mag.above.bottom >= 39, `${width} newsletter spacing ${JSON.stringify(mag)}`);
   for (const [name,r] of Object.entries({section:mag.section,email:mag.email,button:mag.button})) assert.ok(r.left>=-1 && r.right<=width+1 && r.width>0, `${width} ${name} bounds ${JSON.stringify(mag)}`);
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
   const logo = await page.locator('.site-header .custom-logo-link').evaluate(el => { const s=getComputedStyle(el); return {background:s.backgroundColor, padding:s.padding, border:s.borderWidth, shadow:s.boxShadow}; });
   assert.deepEqual(logo, {background:'rgba(0, 0, 0, 0)',padding:'0px',border:'0px',shadow:'none'}, `${width} transparent header logo`);
   console.log(`PASS real WP at ${width}px`);
  }
  // On mobile, measure real browser line boxes: an orphan line is folded back
  // when this is possible without reducing text below the readability floor.
  await page.setViewportSize({width:375,height:900});
  await page.evaluate(() => {
    const root = document.querySelector('main.hj-static-home');
    const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d'); ctx.font = '20px Arial';
    const t = 'alpha beta gamma delta epsilon';
    const p = document.createElement('p'); p.id = 'fixture-wrap-short'; p.textContent = t;
    p.style.cssText = `font:20px/1.5 Arial;direction:ltr;width:${ctx.measureText(t).width * .94}px;max-width:none;`;
    root.append(p);
    const manual = document.createElement('p'); manual.id = 'fixture-wrap-manual'; manual.innerHTML = 'one two<br>three four';
    manual.style.cssText = 'font:20px/1.5 Arial;direction:ltr;width:240px'; root.append(manual);
    const long = document.createElement('p'); long.id = 'fixture-wrap-impossible'; long.textContent = 'superlongword superlongword superlongword';
    long.style.cssText = 'font:20px/1.5 Arial;direction:ltr;width:30px;overflow-wrap:anywhere'; root.append(long);
  });
  await page.waitForFunction(() => document.querySelector('#fixture-wrap-short')?.dataset.hjMobileWrap === 'adjusted', null, {timeout:10000});
  const wrap = await page.locator('#fixture-wrap-short').evaluate(el => ({size:parseFloat(getComputedStyle(el).fontSize), lines:[...(() => { const r=document.createRange();r.selectNodeContents(el);return r.getClientRects(); })()].filter(r=>r.width>0).map(r=>Math.round(r.top))}));
  assert.ok(wrap.size >= 16.4 && wrap.size < 20, 'limited, readable reduction');
  assert.equal(new Set(wrap.lines).size, 1, 'short final line returns to the first');
  assert.equal(await page.locator('#fixture-wrap-manual').getAttribute('data-hj-mobile-wrap'), null, 'manual break left intact');
  assert.equal(await page.locator('#fixture-wrap-impossible').getAttribute('data-hj-mobile-wrap'), 'unresolved', 'impossible width does not shrink indefinitely');
  await page.setViewportSize({width:1024,height:900});
  await page.waitForFunction(() => !document.querySelector('#fixture-wrap-short')?.hasAttribute('data-hj-mobile-wrap'), null, {timeout:10000});
  assert.equal(await page.locator('#fixture-wrap-short').evaluate(el => parseFloat(getComputedStyle(el).fontSize)), 20, 'desktop font restored');
  await page.evaluate(() => ['fixture-wrap-short','fixture-wrap-manual','fixture-wrap-impossible'].forEach(id => document.getElementById(id).remove()));
  // The mail transport is stubbed, no actual message leaves the fixture.
  await page.setViewportSize({width:375,height:900});
  await page.goto(base + '/', {waitUntil:'domcontentloaded'});
  const form = page.locator('.hj-magazine-section form.hj-magazine-form');
  const email = form.locator('input[name=email]'), status = form.locator('.hj-newsletter-status');
  async function waitStatus(text) {
   try { await page.waitForFunction(t => document.querySelector('.hj-newsletter-status')?.textContent.includes(t), text, {timeout:15000}); }
   catch (err) { throw new Error('Expected status ' + text + ' but saw: ' + await status.innerText() + ' url=' + page.url()); }
  }
  await email.fill('fixture@example.invalid');
  const before = page.url();
  await form.locator('button[type=submit]').click();
  await waitStatus('یه بار جیمیلت رو چک کن');
  let state = await page.evaluate(async () => (await fetch('/wp-admin/admin-post.php?action=hj_fixture_newsletter_state')).json());
  assert.equal(state.status, 'missing', 'first click only reviews, does not subscribe');
  await form.locator('button[type=submit]').click();
  await waitStatus('شما مشترک همیار جراحی شدین');
  assert.equal(page.url(), before, 'no navigation or white page');
  assert.equal(await status.getAttribute('data-state'), 'confirmed');
  state = await page.evaluate(async () => (await fetch('/wp-admin/admin-post.php?action=hj_fixture_newsletter_state')).json());
  assert.equal(state.status, 'publish', 'subscription active immediately');
  const mail = await page.evaluate(async () => (await fetch('/wp-admin/admin-post.php?action=hj_fixture_confirm_link')).json());
  assert.match(mail.message, /شما مشترک همیار جراحی شدین/);
  assert.match(mail.message, /Fixture article/);
  assert.doesNotMatch(mail.message, /hj_magazine_confirm|تأیید اشتراک/);
  const cancel = mail.message.match(/href="([^"]*action=hj_magazine_cancel[^"]*)"/);
  assert.ok(cancel, 'welcome mail has a cancellation link');
  const cancelUrl = await page.evaluate(href => { const e = document.createElement('textarea'); e.innerHTML = href; return e.value; }, cancel[1]);
  await email.fill('mail-failure@example.invalid'); await form.locator('button[type=submit]').click(); await waitStatus('یه بار جیمیلت رو چک کن');
  await form.locator('button[type=submit]').click(); await waitStatus('ارسال ایمیل خوش‌آمد ممکن نشد');
  assert.doesNotMatch(await status.innerText(), /مشترک.*شدین/, 'mail failure is not success');
  // Changing email after review requires another review.
  await email.fill('changed@example.invalid'); await form.locator('button[type=submit]').click(); await waitStatus('یه بار جیمیلت رو چک کن');
  await email.fill('approved@example.invalid'); await form.locator('button[type=submit]').click(); await waitStatus('یه بار جیمیلت رو چک کن');
  await form.locator('button[type=submit]').click(); await waitStatus('شما مشترک همیار جراحی شدین');
  // Existing published subscriptions do not have their old cancel token rotated.
  await email.fill('fixture@example.invalid'); await form.locator('button[type=submit]').click(); await form.locator('button[type=submit]').click(); await waitStatus('شما مشترک همیار جراحی شدین');
  await page.goto(cancelUrl, {waitUntil:'domcontentloaded'});
  await page.locator('form button[type=submit]').click();
  state = await page.evaluate(async () => (await fetch('/wp-admin/admin-post.php?action=hj_fixture_newsletter_state')).json());
  assert.equal(state.status, 'missing', 'original unsubscribe link remains usable');

  const noJS = await browser.newContext({javaScriptEnabled:false, viewport:{width:375,height:900}});
  try {
   const p = await noJS.newPage(); await p.goto(base + '/', {waitUntil:'domcontentloaded'});
   const f=p.locator('.hj-magazine-section form.hj-magazine-form');
   await f.locator('input[name=email]').fill('no-js@example.invalid');
   await Promise.all([p.waitForResponse(r => r.request().method() === 'POST'), f.locator('button[type=submit]').click()]);
   await f.locator('.hj-newsletter-status').waitFor();
   assert.match(await f.locator('.hj-newsletter-status').innerText(), /یه بار جیمیلت رو چک کن/);
   await Promise.all([p.waitForURL(url => url.searchParams.get('hj_newsletter_result') === 'confirmed'), f.locator('button[type=submit]').click()]);
   assert.match(await f.locator('.hj-newsletter-status').innerText(), /شما مشترک همیار جراحی شدین/);
   assert.ok(!p.url().includes('no-js%40'), 'email omitted from result URL');
  } finally { await noJS.close(); }
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

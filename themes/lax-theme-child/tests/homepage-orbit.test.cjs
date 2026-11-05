// Synthetic layout only. Requires playwright-core and CHROMIUM_PATH.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '../../..');
const child = path.join(root, 'themes/lax-theme-child');
const files = ['themes/lax-theme/assets/css/main.css', 'themes/lax-theme-child/style.css', 'themes/lax-theme-child/assets/css/child.css', 'themes/lax-theme-child/assets/css/soft-ui.css', 'themes/lax-theme-child/assets/css/static-home.css', 'themes/lax-theme-child/assets/css/responsive.css', 'plugins/hamyar-doctors/assets/css/site-responsive.css', 'themes/lax-theme-child/assets/css/hj-emerald-glass.css', 'themes/lax-theme-child/assets/css/hj-emerald-glass-compat.css', 'themes/lax-theme-child/assets/css/home-rows.css', 'themes/lax-theme-child/assets/css/homepage-draft.css', 'themes/lax-theme-child/assets/css/homepage-orbit.css'];
const css = files.map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');
const script = fs.readFileSync(path.join(child, 'assets/js/homepage-orbit.js'), 'utf8');
const card = `<article class="hj-surgeon-card"><div class="hj-surgeon-card__avatar"><span class="hj-surgeon-card__placeholder">✚</span></div><div class="hj-surgeon-card__details"><h3>دکتر جراح نمونه با نام بلند</h3><p class="hj-surgeon-card__specialty">جراح قلب و عروق</p><p class="hj-surgeon-card__education">مدرک تخصصی جراحی</p><p class="hj-surgeon-card__license">شماره نظام پزشکی: <bdi>۱۲۳۴۵۶</bdi></p><p class="hj-surgeon-card__rating"><span>★</span> ۴٫۵ از ۵</p><a href="/doctor/test/">مشاهده پروفایل ←</a></div></article>`;
const starter = fs.readFileSync(path.join(child, 'homepage/starter.html'), 'utf8').replace(/{{[A-Z_]+}}/g, '/target/').replace('[hj_home_surgeons]', `<div class="hj-home-surgeons">${card.repeat(3)}</div>`);
const font = fs.readFileSync(path.join(child, 'fonts/Vazirmatn-Bold.woff2')).toString('base64');
(async () => {
 const browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH, args:['--no-sandbox','--disable-dev-shm-usage'], headless:true});
 try {
  const page = await browser.newPage();
  for (const wrapped of [false,true]) for (const width of [280,320,360,375,414,768,980,1280,1440]) {
   await page.setViewportSize({width,height:950});
   await page.setContent(`<html dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><style>@font-face{font-family:Vazirmatn;src:url(data:font/woff2;base64,${font});font-weight:700 900}body .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:650px;margin-left:auto!important;margin-right:auto!important}.is-layout-flow > * + *{margin-block-start:1.5rem}</style></head><body><main class="hj-static-home"><div class="entry-content">${starter}</div></main></body></html>`);
   await page.evaluate(wrap => {document.querySelector('.hj-landing').classList.add('is-layout-constrained'); if(wrap) document.querySelectorAll('.hj-landing.wp-block-group,.hj-landing .wp-block-group').forEach(el=>{const inner=document.createElement('div');inner.className='wp-block-group__inner-container is-layout-flow';while(el.firstChild)inner.appendChild(el.firstChild);el.appendChild(inner);});}, wrapped);
   await page.addScriptTag({content:script});
   await page.evaluate(() => document.fonts.ready);
   await page.waitForTimeout(100);
   const m = await page.evaluate(() => {
    const hero=document.querySelector('.hj-landing-hero'), copy=document.querySelector('.hj-landing-hero-copy'), title=document.querySelector('.hj-hero-title'), text=title.querySelector('.hj-hero-slogan');
    const r=x=>x.getBoundingClientRect(); const h=r(title), t=r(text), g=r(hero), c=r(copy);
    const cards=[...document.querySelectorAll('.hj-surgeon-card')].map(x=>{let q=r(x);return {top:q.top,bottom:q.bottom,width:q.width,left:q.left,right:q.right,avatar:r(x.querySelector('.hj-surgeon-card__avatar')).width,link:!!x.querySelector('a[href]')}});
    const buttons=r(document.querySelector('.hj-landing-hero-copy .hj-landing-actions'));
    return {scroll:document.documentElement.scrollWidth, hero:g.width,copy:c.width, title:h.width, text:t.width, textleft:t.left,textright:t.right,titleleft:h.left,titleright:h.right,nowrap:getComputedStyle(title).whiteSpace,font:parseFloat(getComputedStyle(title).fontSize),cards,buttonsleft:buttons.left,buttonswidth:buttons.width,copyleft:c.left};
   });
   assert.ok(m.scroll<=width+1, `${wrapped}/${width}: page overflow: ${JSON.stringify(m)}`);
   assert.ok(Math.abs(m.hero-m.copy)<3, `${wrapped}/${width}: hero copy not full width: ${JSON.stringify(m)}`);
   assert.equal(m.nowrap,'nowrap');
   assert.ok(m.textleft >= m.titleleft-1 && m.textright<=m.titleright+1, `${wrapped}/${width}: slogan clipping: ${JSON.stringify(m)}`);
   assert.ok(m.font>=11, `${wrapped}/${width}: slogan too small: ${m.font}`);
   assert.equal(m.cards.length,3);
   for (let i=0;i<3;i++) {const x=m.cards[i];assert.ok(x.left>=-1 && x.right<=width+1 && x.width>width*0.5 && x.link);if(i) assert.ok(x.top>=m.cards[i-1].bottom, `${wrapped}/${width}: cards not stacked`);}
   if (process.env.HJ_VISUAL_DIR && wrapped && (width === 375 || width === 1280)) await page.screenshot({path: path.join(process.env.HJ_VISUAL_DIR, `homepage-${width}.png`), fullPage: true});
   console.log(`PASS ${wrapped?'classic-wrapper':'bare'} ${width}, slogan ${m.font}px`);
  }
 } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1)});

/** Homepage layout regression with both classic WordPress wrappers and bare blocks.
 * Install playwright-core and set CHROMIUM_PATH to Chromium, then run:
 * CHROMIUM_PATH=/path/to/chromium node themes/lax-theme-child/tests/homepage-responsive.test.cjs
 * Optionally set HJ_WORDPRESS_URL to a local WordPress homepage using this
 * theme and the starter page; that mode checks actual WordPress-rendered HTML.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');
const child = path.resolve(__dirname, '..');
const themes = path.resolve(child, '..');
const css = ['lax-theme/assets/css/main.css', 'lax-theme-child/style.css', 'lax-theme-child/assets/css/child.css', 'lax-theme-child/assets/css/soft-ui.css', 'lax-theme-child/assets/css/static-home.css']
  .map(p => fs.readFileSync(path.join(themes, p), 'utf8')).join('\n');
const starter = fs.readFileSync(path.join(child, 'homepage/starter.html'), 'utf8')
  .replace(/{{SYMPTOM_BUTTON}}/g, '').replace(/{{[A-Z_]+}}/g, '#');
const image = 'data:image/png;base64,' + fs.readFileSync(path.join(child, 'assets/images/home-medical-tech.png')).toString('base64');
const wpCss = `:root{--wp--style--global--content-size:650px}
body .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:var(--wp--style--global--content-size);margin-left:auto!important;margin-right:auto!important}
.is-layout-flow > * + *{margin-block-start:1.5rem}`;

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'], headless: true });
  try {
    const page = await browser.newPage();
    const modes = process.env.HJ_WORDPRESS_URL ? ['wordpress'] : ['bare', 'classic-wrappers'];
    for (const mode of modes) {
      for (const width of [320, 375, 640, 768, 900, 980, 1024, 1280, 1440, 1920]) {
        await page.setViewportSize({ width, height: 900 });
        if (mode === 'wordpress') {
          const response = await page.goto(process.env.HJ_WORDPRESS_URL, { waitUntil: 'load', timeout: 60000 });
          assert.equal(response.status(), 200, 'WordPress homepage response');
        } else {
          await page.setContent(`<html dir="rtl"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><style>${wpCss}</style></head><body><main class="hj-static-home"><div class="entry-content">${starter}</div></main></body></html>`);
          await page.evaluate((wrapped) => {
            document.querySelector('.hj-landing').classList.add('is-layout-constrained');
            if (wrapped) document.querySelectorAll('.hj-landing.wp-block-group, .hj-landing .wp-block-group').forEach(group => {
              const inner = document.createElement('div'); inner.className = 'wp-block-group__inner-container is-layout-flow';
              while (group.firstChild) inner.appendChild(group.firstChild);
              group.appendChild(inner);
            });
          }, mode === 'classic-wrappers');
        }
        await page.evaluate(async imageData => {
          const img = document.querySelector('.hj-landing-image img'); img.src = imageData;
          await img.decode();
          document.querySelector('.hj-landing-card h3').textContent = 'آگاهی'.repeat(20);
        }, image);
        const actual = await page.evaluate(() => {
          const landing = document.querySelector('.hj-landing');
          const grid = document.querySelector('.hj-landing-grid');
          const hero = document.querySelector('.hj-landing-hero');
          const art = document.querySelector('.hj-landing-hero-art');
          const img = art.querySelector('img');
          const cards = Array.from(grid.querySelectorAll('.hj-landing-card'));
          const rect = el => el.getBoundingClientRect();
          return {
            viewport: document.querySelector('meta[name="viewport"]')?.content,
            documentWidth: document.documentElement.scrollWidth,
            landingWidth: rect(landing).width, gridWidth: rect(grid).width,
            heroWidth: rect(hero).width, artWidth: rect(art).width, imageWidth: rect(img).width,
            columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
            heroColumns: getComputedStyle(hero).gridTemplateColumns.split(' ').length,
            cards: cards.map(card => ({ top: Math.round(rect(card).top), left: rect(card).left, width: rect(card).width, bottom: rect(card).bottom })),
            overflow: Array.from(landing.querySelectorAll('*')).some(el => rect(el).left < -1 || rect(el).right > innerWidth + 1),
          };
        });
        assert.match(actual.viewport || '', /width=device-width/, `${mode} ${width}: viewport`);
        assert.ok(actual.documentWidth <= width + 1 && !actual.overflow, `${mode} ${width}: horizontal overflow`);
        assert.ok(Math.abs(actual.gridWidth - actual.landingWidth) < 2, `${mode} ${width}: grid width`);
        assert.ok(Math.abs(actual.heroWidth - actual.landingWidth) < 2, `${mode} ${width}: hero width`);
        assert.equal(actual.columns, width <= 768 ? 1 : width <= 1050 ? 2 : 3, `${mode} ${width}: grid columns`);
        assert.equal(actual.heroColumns, width <= 900 ? 1 : 2, `${mode} ${width}: hero columns`);
        if (width <= 768) {
          assert.ok(Math.abs(actual.cards[0].width - actual.gridWidth) < 2, `${mode} ${width}: mobile card must fill row`);
          assert.ok(actual.cards[1].top > actual.cards[0].bottom, `${mode} ${width}: mobile cards must have a gap`);
          assert.ok(Math.abs(actual.artWidth - actual.heroWidth) < 3 && Math.abs(actual.imageWidth - actual.artWidth) < 2, `${mode} ${width}: mobile image must fill hero`);
        } else {
          assert.equal(actual.cards[0].top, actual.cards[1].top, `${mode} ${width}: cards must share a row`);
          assert.ok(Math.abs(actual.cards[0].left - actual.cards[1].left) > 2, `${mode} ${width}: cards must be in different columns`);
        }
        if (mode === 'wordpress') {
          const showcase = await page.evaluate(() => {
            const grid = document.querySelector('.hj-home-surgeons');
            if (!grid) return null;
            const cards = [...grid.querySelectorAll('.hj-surgeon-card')].map(el => {
              const r = el.getBoundingClientRect(); return {top: Math.round(r.top), width: r.width};
            });
            return {columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length, width: grid.getBoundingClientRect().width, cards};
          });
          if (showcase && showcase.cards.length >= 2) {
            assert.equal(showcase.columns, width <= 768 ? 1 : width <= 1050 ? 2 : 3, `${mode} ${width}: showcase columns`);
            if (width <= 768) assert.ok(Math.abs(showcase.cards[0].width - showcase.width) < 2 && showcase.cards[1].top > showcase.cards[0].top, `${mode} ${width}: full-width showcase cards`);
            else assert.equal(showcase.cards[0].top, showcase.cards[1].top, `${mode} ${width}: showcase row`);
          }
        }
        console.log(`PASS ${mode} ${width}px: widths, image, card placement and overflow`);
      }
    }
  } finally { await browser.close(); }
})().catch(err => { console.error(err); process.exitCode = 1; });

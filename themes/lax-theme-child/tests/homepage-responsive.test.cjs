/** Browser layout regression for the editable homepage.
 * Install playwright-core separately; set CHROMIUM_PATH to an existing Chromium.
 * Run: CHROMIUM_PATH=/path/to/chromium node themes/lax-theme-child/tests/homepage-responsive.test.cjs
 * The WordPress layout CSS below models the constrained/flow rules WordPress
 * adds at render time. This fixture does not replace a live WordPress test.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');

const child = path.resolve(__dirname, '..');
const themes = path.resolve(child, '..');
const files = [
  path.join(themes, 'lax-theme/assets/css/main.css'),
  path.join(child, 'style.css'),
  path.join(child, 'assets/css/child.css'),
  path.join(child, 'assets/css/soft-ui.css'),
  path.join(child, 'assets/css/static-home.css'),
];
const css = files.map(file => fs.readFileSync(file, 'utf8')).join('\n');
const starter = fs.readFileSync(path.join(child, 'homepage/starter.html'), 'utf8')
  .replace(/{{SYMPTOM_BUTTON}}/g, '')
  .replace(/{{[A-Z_]+}}/g, '#');
const wpCss = `:root{--wp--style--global--content-size:650px}
body .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:var(--wp--style--global--content-size);margin-left:auto!important;margin-right:auto!important}
.is-layout-flow > * + *{margin-block-start:1.5rem}`;

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    headless: true,
  });
  try {
    const page = await browser.newPage();
    for (const width of [320, 375, 640, 768, 900, 1024, 1280, 1440, 1920]) {
      await page.setViewportSize({ width, height: 900 });
      await page.setContent(`<html dir="rtl"><head><style>${css}</style><style>${wpCss}</style></head><body><main class="hj-static-home"><div class="entry-content">${starter}</div></main></body></html>`);
      await page.evaluate(() => {
        document.querySelector('.hj-landing').classList.add('is-layout-constrained');
        document.querySelectorAll('.hj-landing .wp-block-group').forEach(el => el.classList.add('is-layout-flow'));
        // An editor may add a long, unbroken title; it must not widen a column.
        document.querySelector('.hj-landing-card h3').textContent = 'آگاهی'.repeat(20);
      });
      const actual = await page.evaluate(() => {
        const landing = document.querySelector('.hj-landing');
        const grid = document.querySelector('.hj-landing-grid');
        const hero = document.querySelector('.hj-landing-hero');
        const cards = Array.from(grid.children);
        const bounds = el => el.getBoundingClientRect();
        return {
          documentWidth: document.documentElement.scrollWidth,
          landingWidth: bounds(landing).width,
          gridWidth: bounds(grid).width,
          heroWidth: bounds(hero).width,
          columns: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
          heroColumns: getComputedStyle(hero).gridTemplateColumns.split(' ').length,
          tops: cards.map(card => Math.round(bounds(card).top)),
          overflow: Array.from(landing.querySelectorAll('*')).some(el => bounds(el).left < -1 || bounds(el).right > innerWidth + 1),
        };
      });
      assert.ok(actual.documentWidth <= width + 1 && !actual.overflow, `${width}: horizontal overflow`);
      assert.ok(Math.abs(actual.gridWidth - actual.landingWidth) < 2, `${width}: grid constrained to reading width`);
      assert.ok(Math.abs(actual.heroWidth - actual.landingWidth) < 2, `${width}: hero constrained to reading width`);
      assert.equal(actual.columns, width <= 640 ? 1 : width <= 1050 ? 2 : 3, `${width}: grid columns`);
      assert.equal(actual.heroColumns, width <= 900 ? 1 : 2, `${width}: hero columns`);
      if (width > 640) assert.equal(actual.tops[0], actual.tops[1], `${width}: cards do not align`);
      console.log(`PASS ${width}px: section width, columns, alignment and overflow`);
    }
  } finally {
    await browser.close();
  }
})().catch(err => { console.error(err); process.exitCode = 1; });

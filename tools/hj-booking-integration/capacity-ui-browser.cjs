/** Real headless browser against the actual rendered form + isolated plugin CSS.
 * This is not a test of the active theme, full WordPress navigation, or OAuth.
 */
const fs = require('fs');
const { chromium } = require('playwright-core');
const path = require('path');
const assert = require('assert');
const html = fs.readFileSync(process.env.HJ_CAPACITY_HTML, 'utf8');
const css = fs.readFileSync(path.join(process.env.GITHUB_WORKSPACE, 'plugins/hamyar-doctors/assets/css/booking-capacity-settings.css'), 'utf8');
(async () => {
  const browser = await chromium.launch({ executablePath:process.env.CHROMIUM_PATH, args:['--no-sandbox'] });
  try {
    for (const width of [375, 1024]) {
      const page = await browser.newPage({ viewport:{width,height:900} });
      await page.setContent('<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>'+html+'</body></html>');
      if (process.env.HJ_DASHBOARD_TEST === '1') await page.addStyleTag({content:fs.readFileSync(path.join(process.env.GITHUB_WORKSPACE, 'plugins/hamyar-doctors/assets/css/dashboard.css'), 'utf8')});
      await page.addStyleTag({content:css});
      if (process.env.HJ_DASHBOARD_TEST === '1') {
        assert.equal(await page.locator('#hj-dash-form').count(), 1);
        assert.equal(await page.locator('#hj-capacity-settings form').count(), 2);
        assert.equal(await page.locator('#hj-dash-form .hj-capacity').count(), 0, 'capacity forms must not be nested in profile form');
        assert.equal(await page.locator('#hj-dash-form input[name^="hours["]').count(), 0);
      }
      const result = await page.evaluate(() => {
        const section = document.querySelector('.hj-capacity');
        const cards = Array.from(document.querySelectorAll('.hj-capacity__day'));
        const inputs = Array.from(section.querySelectorAll('input, select, button')).filter(el => el.type !== 'hidden');
        return {overflow:document.documentElement.scrollWidth > document.documentElement.clientWidth, direction:getComputedStyle(section).direction, cards:cards.map(el => {const r=el.getBoundingClientRect(); return {left:Math.round(r.left),width:Math.round(r.width)};}), small:inputs.some(el => el.getBoundingClientRect().height < 40)};
      });
      assert.equal(result.overflow,false,'viewport '+width+' must not overflow horizontally');
      assert.equal(result.direction,'rtl'); assert.equal(result.cards.length,7); assert.equal(result.small,false,'controls have touch-friendly height');
      if (width===375) assert.equal(new Set(result.cards.map(c=>c.left)).size,1,'mobile day cards must stack');
      else assert(new Set(result.cards.map(c=>c.left)).size>1,'desktop uses multiple columns');
      await page.close();
      console.log('PASS: isolated rendered office form at '+width+'px in headless Chrome');
    }
  } finally { await browser.close(); }
})().catch(err=>{console.error(err);process.exitCode=1;});

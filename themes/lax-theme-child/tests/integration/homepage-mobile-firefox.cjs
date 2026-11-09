// Synthetic homepage only: verify the screenshot paragraphs in Firefox.
if (process.env.GITHUB_ACTIONS !== 'true' || process.env.HJ_DISPOSABLE_MARIADB_FIXTURE !== '1') throw Error('CI fixture only');
const {firefox} = require('playwright');
const assert = require('node:assert/strict');
(async () => {
 const browser = await firefox.launch({headless:true});
 try {
  const page = await browser.newPage({viewport:{width:375,height:900}});
  for (const width of [320,360,375,393,414]) {
   await page.setViewportSize({width,height:900});
   assert.equal((await page.goto('http://example.invalid/', {waitUntil:'load'})).status(),200);
   await page.evaluate(() => document.fonts.ready);
   await page.waitForFunction(() => {
    const targets = [document.querySelector('.hj-landing-disclaimer'), ...document.querySelectorAll('.hj-feature p:last-child'), ...document.querySelectorAll('.hj-surgeon-copy p:not(.hj-landing-eyebrow)')];
    return targets.length >= 4 && targets.every(el => el && el.hasAttribute('data-hj-mobile-wrap'));
   }, null, {timeout:15000});
   const lines = await page.evaluate(() => {
    const targets = [document.querySelector('.hj-landing-disclaimer'), ...document.querySelectorAll('.hj-feature p:last-child'), ...document.querySelectorAll('.hj-surgeon-copy p:not(.hj-landing-eyebrow)'), ...document.querySelectorAll('.hj-magazine-form > p:not(.hj-newsletter-status)')];
    return targets.map(el => {
     const node=el.firstChild, text=node.textContent, groups=[];
     for (const match of text.matchAll(/\S+/gu)) {
      const range=document.createRange(); range.setStart(node,match.index); range.setEnd(node,match.index+match[0].length);
      const r=Array.from(range.getClientRects()).find(rect=>rect.width>0); if(!r) continue;
      let group=groups.find(g=>Math.abs(g.y-r.top)<4); if(!group){group={y:r.top,words:[]};groups.push(group)} group.words.push(match[0]);
     }
     return {text:el.textContent, state:el.dataset.hjMobileWrap, counts:groups.sort((a,b)=>a.y-b.y).map(g=>g.words)};
    });
   });
   for(const entry of lines) if(entry.counts.length > 1) assert.ok(entry.counts.every(c=>c.length>=3), `${width} orphan line ${JSON.stringify(entry)}`);
   assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth+1), `${width} overflow`);
   console.log(`PASS Firefox ${width}px Persian screenshot paragraphs`);
  }
 } finally { await browser.close(); }
})().catch(err => { console.error(err); process.exit(1); });

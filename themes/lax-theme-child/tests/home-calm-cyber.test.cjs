// Synthetic HTML; does not assert live WordPress, authentication, or ACF.
// NODE_PATH=/path/to/node_modules CHROMIUM_PATH=/path/to/chromium node ...
const assert=require('node:assert/strict'), fs=require('node:fs'), path=require('node:path');
const {chromium}=require('playwright-core');
const root=path.resolve(__dirname,'../../..'), child=path.join(root,'themes/lax-theme-child');
const files=['themes/lax-theme/assets/css/main.css','themes/lax-theme-child/style.css','themes/lax-theme-child/assets/css/child.css','themes/lax-theme-child/assets/css/soft-ui.css','themes/lax-theme-child/assets/css/static-home.css','themes/lax-theme-child/assets/css/responsive.css','plugins/hamyar-doctors/assets/css/site-responsive.css','themes/lax-theme-child/assets/css/hj-emerald-glass.css','themes/lax-theme-child/assets/css/hj-emerald-glass-compat.css','themes/lax-theme-child/assets/css/footer-black.css','themes/lax-theme-child/assets/css/home-rows.css','themes/lax-theme-child/assets/css/header-black.css','themes/lax-theme-child/assets/css/homepage-orbit.css'];
const css=files.map(f=>fs.readFileSync(path.join(root,f),'utf8')).join('\n');
const newCss=fs.readFileSync(path.join(child,'assets/css/home-calm-cyber.css'),'utf8');
const font=fs.readFileSync(path.join(child,'fonts/Vazirmatn-Regular.woff2')).toString('base64');
const card='<article class="hj-surgeon-card"><div class="hj-surgeon-card__avatar"><span class="hj-surgeon-card__placeholder">✚</span></div><div class="hj-surgeon-card__details"><h3>دکتر جراح نمونه</h3><p>جراح عمومی</p><a href="#profile">مشاهده پروفایل</a></div></article>';
const image='data:image/png;base64,'+fs.readFileSync(path.join(child,'assets/images/home-medical-tech.png')).toString('base64');
const starter=fs.readFileSync(path.join(child,'homepage/starter.html'),'utf8').replace(/{{[A-Z_]+}}/g,'#').replace('[hj_home_surgeons]',`<div class="hj-home-surgeons">${card.repeat(2)}</div>`).replace('src="#"',`src="${image}"`).replace('[hj_home_google]','<a class="hj-google-btn" href="#google">ثبت‌نام جراح با گوگل</a>');
const script=fs.readFileSync(path.join(child,'assets/js/homepage-orbit.js'),'utf8');
const template=(extra)=>`<!doctype html><html lang="fa" dir="rtl"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}\n${extra}\n@font-face{font-family:Vazirmatn;src:url(data:font/woff2;base64,${font})}body .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:650px;margin-left:auto!important;margin-right:auto!important}.is-layout-flow>*+*{margin-block-start:1.5rem}</style></head><body><header class="site-header"><div class="container site-header__inner"><div class="site-branding"><a class="brand" href="#"><span class="brand__text"><span class="site-title">همیار جراحی</span></span></a></div><nav class="hj-desktop-nav"><ul><li><a href="#">خانه</a></li><li><a href="#">راهنمای بیمار</a></li><li><a href="#">جراحان</a></li></ul></nav></div></header><main class="hj-static-home"><div class="entry-content">${starter}</div></main><footer class="site-footer hj-footer"><div class="container"><p>همیار جراحی — طرح آزمایشی</p></div></footer></body></html>`;
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox','--disable-dev-shm-usage'],headless:true});try{const page=await browser.newPage();
for(const wrapped of [false,true])for(const width of [320,375,768,1280]){
 await page.setViewportSize({width,height:900});
 async function render(extra){await page.setContent(template(extra));await page.evaluate(wrap=>{document.querySelector('.hj-landing').classList.add('is-layout-constrained');if(wrap)document.querySelectorAll('.hj-landing.wp-block-group,.hj-landing .wp-block-group').forEach(el=>{const inner=document.createElement('div');inner.className='wp-block-group__inner-container is-layout-flow';while(el.firstChild)inner.appendChild(el.firstChild);el.appendChild(inner);});},wrapped);await page.addScriptTag({content:script});await page.evaluate(()=>document.fonts.ready);}
 const read=()=>page.evaluate(()=>{const style=s=>{const e=document.querySelector(s),c=getComputedStyle(e),r=e.getBoundingClientRect();return {bg:c.backgroundImage,color:c.color,border:c.borderColor,w:r.width,h:r.height,top:r.top,left:r.left,right:r.right};};return {scroll:document.documentElement.scrollWidth,patient:style('.hj-landing-grid .hj-landing-card'),google:style('.hj-google-btn'),surgeon:style('.hj-landing-surgeon'),feature:style('.hj-feature'),featureText:style('.hj-feature h3'),hero:style('.hj-landing-hero'),cta:style('.hj-landing-hero .hj-btn-primary a')};});
 await render('');const old=await read(); await render(newCss);const now=await read();
 assert.ok(now.scroll<=width+1,`${wrapped}/${width} overflow ${now.scroll}`);
 assert.deepEqual(now.patient,old.patient,`${wrapped}/${width} patient journey altered`);
 assert.equal(now.google.bg,old.google.bg,`${wrapped}/${width} Google background altered`);
 assert.equal(now.google.color,old.google.color,`${wrapped}/${width} Google colour altered`);
 assert.notEqual(now.hero.bg,old.hero.bg);assert.notEqual(now.surgeon.bg,old.surgeon.bg);
 assert.equal(now.featureText.color,'rgb(243, 250, 248)');assert.ok(now.cta.h>=48);
 if(width<=768) assert.ok(now.surgeon.left>=-1&&now.surgeon.right<=width+1);
 if(process.env.HJ_VISUAL_DIR && wrapped && (width===375||width===1280))await page.screenshot({path:path.join(process.env.HJ_VISUAL_DIR,`calm-cyber-${width}.png`),fullPage:true});
 console.log(`PASS ${wrapped?'wrapped':'bare'} ${width}px, patient and Google unchanged`);
}
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});

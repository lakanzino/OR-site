'use strict';
let db, engine;
let currentView = 'checker', directoryCategory = '', directorySearch = '';
const state = {step: 1, region: '', painLevel: 0, selectedSymptoms: [], search: ''};
const content = document.getElementById('content');
const escapeText = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function chips(items, selected) {
  return `<div class="chips">${items.map(value => `<button type="button" class="chip" data-value="${escapeText(value)}" aria-pressed="${selected.includes(value)}">${escapeText(value)}</button>`).join('')}</div>`;
}
function warning() {
  return `<div id="early-warning" class="alert" role="alert" ${engine.isUrgentCase(state) ? '' : 'hidden'}>علائم انتخاب‌شده یا درد بسیار شدید ممکن است اورژانسی باشد. منتظر پایان فرم نمانید؛ با اورژانس محلی (در ایران ۱۱۵) تماس بگیرید یا فوراً به اورژانس مراجعه کنید.</div>`;
}
function render() {
  if (currentView === 'encyclopedia') return renderDirectory();
  document.getElementById('step-number').textContent = state.step <= 3 ? `مرحله ${state.step} از ۳` : 'نتیجه و منابع';
  document.getElementById('progress-bar').style.width = `${Math.min(state.step,3)*100/3}%`;
  if (state.step === 4) return renderFinalResult();
  let title, desc, body;
  if (state.step === 1) {
    title='علائم شما در کدام ناحیه است؟'; desc='ابتدا ناحیه اصلی را انتخاب کنید؛ در مرحله بعد می‌توانید علائم دیگر را هم اضافه کنید.';
    body=`<div class="region-grid">${db.regions.map(r=>`<button type="button" class="region-card" data-region="${escapeText(r.id)}" aria-pressed="${state.region===r.id}"><strong>${escapeText(r.name)}</strong><span>${escapeText(r.description)}</span><b aria-hidden="true">←</b></button>`).join('')}</div>`;
  } else if (state.step === 2) {
    const region=db.regions.find(r=>r.id===state.region);
    title=`علائم ${escapeText(region.name)}`; desc='همه مواردی را که دارید انتخاب کنید. از بخش علائم دیگر هم می‌توانید موردی اضافه کنید.';
    const priority=db.symptomCategories[state.region];
    const others=[...new Set(Object.values(db.symptomCategories).flat())].filter(s=>!priority.includes(s));
    body=`<div class="section-heading">علائم این ناحیه <span>${state.selectedSymptoms.length} انتخاب</span></div>${chips(priority,state.selectedSymptoms)}<details class="other-symptoms" ${state.search?'open':''}><summary>افزودن علائم از ناحیه‌های دیگر</summary><label class="search-label" for="symptom-search">جست‌وجوی علائم دیگر</label><input class="search" type="search" id="symptom-search" placeholder="مثلاً تب یا سوزش ادرار" autocomplete="off"><div id="other-list">${chips(others,state.selectedSymptoms)}</div><p id="search-empty" class="muted" hidden>علامتی پیدا نشد.</p></details>`;
  } else {
    title='شدت درد چقدر است؟'; desc='اگر درد ندارید، صفر را انتخاب کنید. شدت درد به‌تنهایی معیار تشخیص نیست.';
    body=`<div class="pain-card"><label for="pain">شدت درد <strong id="pain-value">${state.painLevel}</strong> از ۱۰</label><input type="range" id="pain" min="0" max="10" value="${state.painLevel}"><div class="range-labels"><span>بدون درد</span><span>شدیدترین درد</span></div></div>`;
  }
  content.innerHTML=`<h2>${title}</h2><p class="muted">${desc}</p>${warning()}${body}<div class="actions">${state.step>1?'<button class="secondary" id="back">بازگشت</button>':''}<button class="primary" id="next" ${(state.step===1&&!state.region)?'disabled':''}>${state.step===3?'مشاهده نتیجه':'ادامه'}</button></div>`;
  content.querySelectorAll('[data-region]').forEach(button=>button.addEventListener('click',()=>{if(state.region!==button.dataset.region){state.region=button.dataset.region;state.selectedSymptoms=[];}state.step=2;render();}));
  content.querySelectorAll('.chip').forEach(button=>button.addEventListener('click',()=>{const v=button.dataset.value;state.selectedSymptoms=state.selectedSymptoms.includes(v)?state.selectedSymptoms.filter(s=>s!==v):[...state.selectedSymptoms,v];const details=content.querySelector('details');const wasOpen=details&&details.open;render();if(wasOpen)content.querySelector('details').open=true;const again=[...content.querySelectorAll('.chip')].find(el=>el.dataset.value===v);if(again)again.focus();}));
  const search=document.getElementById('symptom-search'); if(search){search.value=state.search;const filter=()=>{state.search=search.value;let visible=0;document.querySelectorAll('#other-list .chip').forEach(button=>{button.hidden=!button.textContent.includes(search.value.trim());if(!button.hidden)visible++;});document.getElementById('search-empty').hidden=visible>0;};search.addEventListener('input',filter);filter();}
  const pain=document.getElementById('pain'); if(pain)pain.addEventListener('input',()=>{state.painLevel=Number(pain.value);document.getElementById('pain-value').textContent=pain.value;document.getElementById('early-warning').hidden=!engine.isUrgentCase(state);});
  document.getElementById('next').onclick=()=>{state.step++;state.search='';render();};
  const back=document.getElementById('back');if(back)back.onclick=()=>{state.step--;state.search='';render();};
}
function diseaseArticleLink(d) {
  if (typeof window.hamyarDiseaseLinks === 'undefined') return db.encyclopedia.some(e=>e.diseaseId===d.id) ? `<button class="entry-link" data-entry="${escapeText(d.id)}">مشاهده معرفی بیماری</button>` : '';
  const value = window.hamyarDiseaseLinks[d.id];
  try { const url = new URL(value, location.href); if (value && url.origin === location.origin && ['http:', 'https:'].includes(url.protocol)) return `<a class="entry-link" href="${escapeText(url.href)}">مطالعه مقاله بیماری ←</a>`; } catch (_) {}
  return '<span class="muted">مقاله این بیماری هنوز منتشر نشده است.</span>';
}
function renderFinalResult() {
  const result=engine.getFinalResult(state);
  const cards=result.resources.map(d=>`<article class="result"><div class="result-label">منبع آموزشی مرتبط</div><h3>${escapeText(d.name)}</h3>${diseaseArticleLink(d)}<p>علائم مشترک: ${d.matchedSymptoms.map(escapeText).join('، ')}</p>${d.sources.filter(s=>{try{return new URL(s.url).protocol==='https:'}catch{return false}}).map(s=>`<a href="${escapeText(s.url)}" target="_blank" rel="noopener noreferrer">📖 ${escapeText(s.text)} ↗</a>`).join('<br>')}</article>`).join('');
  content.innerHTML=`<h2>موضوعات احتمالی مرتبط با علائم شما</h2>${warning()}<p>این موارد با تطبیق علائم انتخاب شده‌اند؛ تشخیص قطعی یا احتمال عددی نیستند و ممکن است علت‌های دیگر وجود داشته باشند. برای بررسی به پزشک مراجعه کنید. نبود هشدار به معنی بی‌خطر بودن نیست.</p>${cards||'<p class="result">برای علائم انتخاب‌شده منبع مرتبطی پیدا نشد؛ این نتیجه وضعیت شما را رد نمی‌کند.</p>'}<div class="actions"><button class="secondary" id="back">بازگشت</button><button class="primary" id="restart">شروع دوباره</button></div>`;
  content.querySelectorAll('[data-entry]').forEach(button=>button.onclick=()=>showEntry(button.dataset.entry));
  document.getElementById('back').onclick=()=>{state.step=3;render();};
  document.getElementById('restart').onclick=()=>{Object.assign(state,{step:1,region:'',painLevel:0,selectedSymptoms:[],search:''});render();};
}
function switchView(view) {
  currentView=view;
  document.querySelectorAll('[data-view]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.view===view)));
  if(view==='encyclopedia') renderDirectory(); else render();
}
function renderDirectory() {
  document.getElementById('step-number').textContent='دانشنامه بیماری‌ها';
  document.getElementById('progress-bar').style.width='100%';
  const categories=[...new Set(db.encyclopedia.map(e=>e.category))];
  content.innerHTML=`<h2>دانشنامه بیماری‌ها</h2><p class="muted">${db.encyclopedia.length} مدخل اولیه · نام فارسی و انگلیسی، علائم، علت، درمان و تاریخچه با منابع. محتوا برای استفاده آموزشی است.</p><label class="search-label" for="directory-search">جست‌وجو به فارسی یا انگلیسی</label><input class="search" type="search" id="directory-search" placeholder="نام بیماری را بنویسید"><div class="category-list"><button class="category-chip" data-category="" aria-pressed="${!directoryCategory}">همه</button>${categories.map(c=>`<button class="category-chip" data-category="${escapeText(c)}" aria-pressed="${directoryCategory===c}">${escapeText(c)}</button>`).join('')}</div><div id="entries"></div>`;
  const input=document.getElementById('directory-search'); input.value=directorySearch;
  const list=()=>{const query=directorySearch.trim().toLocaleLowerCase();const entries=db.encyclopedia.filter(e=>(!directoryCategory||e.category===directoryCategory)&&(`${e.nameFa} ${e.nameEn}`.toLocaleLowerCase().includes(query)));document.getElementById('entries').innerHTML=entries.length?entries.map(e=>`<button class="directory-card" data-entry="${escapeText(e.diseaseId)}"><span>${escapeText(e.category)}</span><strong>${escapeText(e.nameFa)}</strong><small dir="ltr">${escapeText(e.nameEn)}</small><b aria-hidden="true">←</b></button>`).join(''):'<p class="muted">موردی پیدا نشد.</p>';document.querySelectorAll('[data-entry]').forEach(b=>b.onclick=()=>showEntry(b.dataset.entry));};
  input.addEventListener('input',()=>{directorySearch=input.value;list();});
  content.querySelectorAll('[data-category]').forEach(button=>button.onclick=()=>{directoryCategory=button.dataset.category;renderDirectory();});list();
}
function showEntry(id) {
  const e=db.encyclopedia.find(entry=>entry.diseaseId===id); if(!e)return;
  currentView='encyclopedia';document.querySelectorAll('[data-view]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.view==='encyclopedia')));
  document.getElementById('step-number').textContent='دانشنامه بیماری‌ها';document.getElementById('progress-bar').style.width='100%';
  const links=sources=>sources.map(s=>{try{if(new URL(s.url).protocol!=='https:')return '';}catch{return '';}return `<a href="${escapeText(s.url)}" target="_blank" rel="noopener noreferrer">${escapeText(s.text)} ↗</a>`;}).join('');
  content.innerHTML=`<button class="entry-link" id="directory-back">← بازگشت به دانشنامه</button><article class="ency-entry"><div class="result-label">${escapeText(e.category)}</div><h2>${escapeText(e.nameFa)}</h2><p class="english-name" dir="ltr">${escapeText(e.nameEn)}</p><p class="draft-note">${escapeText(e.reviewStatus)} · بررسی منابع: ${escapeText(e.sourceCheckedAt)}</p><section><h3>علت بیماری</h3><p>${escapeText(e.cause)}</p></section><section><h3>تاریخچه مختصر</h3><p>${escapeText(e.history)}</p><div class="entry-sources">${links(e.historySources)}</div></section><section><h3>علائم اصلی</h3><p>${escapeText(e.mainSymptoms)}</p></section><section><h3>درمان‌های رایج</h3><p>${escapeText(e.commonTreatment)}</p></section><section><h3>منابع پزشکی</h3><div class="entry-sources">${links(e.clinicalSources)}</div></section><p class="muted">این اطلاعات توصیه درمانی شخصی نیست؛ برای تشخیص و انتخاب درمان به پزشک مراجعه کنید.</p></article>`;
  document.getElementById('directory-back').onclick=renderDirectory;
}
document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',()=>{if(db)switchView(button.dataset.view);}));
fetch(window.hamyarDbUrl || 'data/medical_db.json').then(response=>{if(!response.ok)throw new Error('HTTP '+response.status);return response.json()}).then(data=>{db=data;engine=new DecisionEngine(db);render();}).catch(()=>{content.innerHTML='<div class="alert">بارگذاری داده‌ها انجام نشد. اتصال و وجود فایل داده را بررسی کنید؛ نسخه مستقل باید از طریق سرور محلی اجرا شود.</div>';});

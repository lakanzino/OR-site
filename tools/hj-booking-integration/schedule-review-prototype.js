/* Isolated read-only demo. No WordPress integration or persistent storage. */
(() => {
  'use strict';
  const fields=document.querySelector('#fields'), form=document.querySelector('#review'), candidate=document.querySelector('#candidate');
  const start=document.querySelector('#start'), end=document.querySelector('#end'), message=document.querySelector('#message');
  const names={prepared:'در انتظار',completed:'ثبت‌شده',acknowledged:'بازبینی‌شده',cancelled:'لغوشده'};
  const keyPattern=/^[a-f0-9]{32}$/, datePattern=/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/;
  let items=[], busy=false, generation=0;
  const say=text=>{message.textContent=text;};
  const post=async(url,body)=>{
    const controller=new AbortController(); const timer=setTimeout(()=>controller.abort(),8000);
    try {
      const headers={'Content-Type':'application/json'};
      const auth=document.querySelector('meta[name=fixture-auth]');
      if (auth) { headers['X-Fixture-Nonce']=auth.content; headers['X-Fixture-Doctor']=auth.dataset.doctor; }
      const response=await fetch(url,{method:'POST',cache:'no-store',headers,body:JSON.stringify(body),signal:controller.signal});
      if (!response.ok) { const error=new Error('transport'); error.httpStatus=response.status; throw error; }
      return await response.json();
    } finally { clearTimeout(timer); }
  };
  async function load() {
    try {
      const data=await post('/test/history',{});
      if (!data || data.status!=='ok' || !Array.isArray(data.items) || data.items.length<1 || data.items.length>3) throw new Error('invalid history');
      const valid=data.items.every(item=>item && typeof item.key==='string' && keyPattern.test(item.key) && Object.hasOwn(names,item.status) && typeof item.created_utc==='string' && datePattern.test(item.created_utc));
      if (!valid || new Set(data.items.map(item=>item.key)).size!==data.items.length) throw new Error('invalid history');
      items=data.items.map(item=>({key:item.key,status:item.status,created:item.created_utc}));
      items.forEach((item,index)=>{
        const option=document.createElement('option'); option.value=String(index); option.textContent='سابقه '+String(index+1)+' — '+item.created+' UTC — '+names[item.status]; candidate.append(option);
      });
      fields.disabled=false; say('یک ردیف انتخاب کنید و اطلاعات را دوباره وارد کنید.');
    } catch (_) { fields.disabled=true; say('فهرست در دسترس نیست؛ نتیجه همچنان نامعلوم است.'); }
  }
  form.addEventListener('input',()=>{generation++; if (!busy) say('انتخاب یا اطلاعات تغییر کرد؛ دوباره بررسی کنید.');});
  form.addEventListener('change',()=>{generation++; if (!busy) say('انتخاب یا اطلاعات تغییر کرد؛ دوباره بررسی کنید.');});
  form.addEventListener('submit',async event=>{
    event.preventDefault(); if (busy || fields.disabled) return;
    const index=candidate.value;
    if (!/^(0|[1-9][0-9]*)$/.test(index) || !items[Number(index)]) { say('ابتدا یک ردیف انتخاب کنید.'); return; }
    const timePattern=/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/;
    if (!timePattern.test(start.value) || !timePattern.test(end.value) || start.value>=end.value) { say('ساعت معتبر وارد کنید.'); return; }
    const selected=items[Number(index)], startValue=start.value, endValue=end.value, at=generation;
    const unchanged=()=>generation===at && candidate.value===index && start.value===startValue && end.value===endValue;
    busy=true; fields.disabled=true; say('در حال بررسی…');
    try {
      const comparison=await post('/test/compare',{key:selected.key,rows:[{day_of_week:'monday',is_closed:0,start_time:startValue,end_time:endValue}]});
      if (!unchanged()) { say('اطلاعات تغییر کرد؛ نتیجه کنار گذاشته شد.'); return; }
      if (!comparison || typeof comparison.status!=='string') throw new Error('unknown comparison');
      if (comparison.status==='different') { say('اطلاعات همخوان نیست؛ نتیجهٔ درخواست اصلی همچنان نامعلوم است.'); return; }
      if (comparison.status!=='matched') throw new Error('unknown comparison');
      const status=await post('/test/status',{key:selected.key});
      const current=await post('/test/current',{});
      if (!unchanged()) { say('اطلاعات تغییر کرد؛ نتیجه کنار گذاشته شد.'); return; }
      if (!status || !Object.hasOwn(names,status.status) || !current || !Number.isSafeInteger(current.revision) || current.revision<1) throw new Error('unknown status');
      say('اطلاعات با ردیف انتخاب‌شده همخوان است، اما هویت درخواست را قطعی ثابت نمی‌کند؛ ممکن است چند ردیف همخوان باشند. آخرین وضعیت خوانده‌شدهٔ ردیف: '+names[status.status]+'؛ بازبینی برنامهٔ جاری لازم است.');
    } catch (error) { say(error.httpStatus===429 ? 'تعداد بررسی‌ها موقتاً محدود شده است؛ بعداً دوباره تلاش کنید. نتیجه همچنان نامعلوم است.' : 'بررسی ناموفق یا نامعلوم است؛ نتیجه قطعی نیست.'); }
    finally { busy=false; fields.disabled=false; }
  });
  load();
})();

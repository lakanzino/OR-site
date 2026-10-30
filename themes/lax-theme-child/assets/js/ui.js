/**
 * رابط کاربری ابزار تشخیص علائم (نسخه ۰.۳۰.۰)
 * ارتباط مستقیم با medical_db.json (۱۲۰ بیماری)
 * سوالات اختصاصی، اقدامات متفاوت (۱۱۵، مراجعه، مشاوره)
 */

// داده‌های ابزار (تولید شده از build-encyclopedia-bridge.py)
let symptomData = null;
let currentRegion = null;
let currentSymptoms = [];
let currentDisease = null;

// دسته‌بندی‌های ناحیه
const REGIONS = {
    head: { id: "head", name: "سر و صورت", description: "سردرد، سرگیجه، علائم صورت" },
    chest: { id: "chest", name: "جلوی سینه و تنفس", description: "درد سینه، تنگی نفس، عرق سرد" },
    abdomen: { id: "abdomen", name: "شکم و لگن", description: "درد شکم، تهوع، تغییرات گوارشی" },
    back: { id: "back", name: "کمر پایین و پهلو", description: "گرفتگی کمر، درد پهلو و ناحیه کلیه" },
    urinary: { id: "urinary", name: "ادرار و کلیه", description: "سوزش ادرار، تکرر یا خون در ادرار" },
    skin: { id: "skin", name: "پوست و جوش", description: "جوش، خارش، تغییرات پوست" },
    general: { id: "general", name: "علائم عمومی", description: "تب، خستگی، لرز یا سایر موارد" }
};

// دسته‌بندی‌های علائم
const SYMPTOM_CATEGORIES = {
    head: ["سردرد", "درد شدید ناگهانی", "سرگیجه", "تهوع", "اختلال تکلم", "ضعف یک‌طرفه", "حساسیت به نور", "گرفتگی بینی", "سفتی گردن", "تب", "استفراغ", "گیجی", "تاری دید"],
    chest: ["درد قفسه سینه", "عرق سرد", "درد دست یا فک", "تنگی نفس", "تهوع", "تپش قلب", "درد با حرکت", "تب", "سرفه", "سرفه خونی", "درد و تورم یک پا", "استفراغ"],
    abdomen: ["درد شکم", "درد شدید ناگهانی", "سفتی شکم", "تب", "تهوع", "استفراغ", "اسهال", "سوزش ادرار", "سوزش سر دل", "بی‌اشتهایی", "تکرر ادرار", "خونریزی واژینال", "درد نوک شانه", "سرگیجه", "غش"],
    back: ["درد کمر", "گرفتگی عضلات کمر", "درد پهلو یا ناحیه کلیه", "درد با حرکت", "سوزش ادرار", "تکرر ادرار", "خون در ادرار", "تب", "لرز", "تهوع", "استفراغ", "بی‌اختیاری جدید ادرار یا مدفوع", "بی‌حسی ناحیه نشیمنگاه"],
    urinary: ["سوزش ادرار", "تکرر ادرار", "خون در ادرار", "درد پهلو یا ناحیه کلیه", "درد شکم", "تب", "لرز", "تهوع", "استفراغ"],
    skin: ["جوش پوست", "پوست چرب", "برجستگی دردناک پوست", "خارش پوست", "تاول پوستی", "درد یا سوزش پوست", "تب"],
    general: ["خستگی", "تب", "سرفه", "تنگی نفس", "تپش قلب", "تشنگی زیاد", "تکرر ادرار", "ضعف یک‌طرفه", "اختلال تکلم", "گیجی", "لرز", "سرفه خونی", "غش", "تاری دید", "کاهش وزن بی‌دلیل", "احساس سرما", "یبوست", "خشکی پوست", "افزایش وزن", "درد و تورم یک پا"]
};

// قوانین اورژانسی
const URGENT_RULES = [
    { id: "stroke", any: ["ضعف یک‌طرفه", "اختلال تکلم"] },
    { id: "breath", any: ["تنگی نفس"] },
    { id: "chest", region: "chest", any: ["درد قفسه سینه", "عرق سرد", "درد دست یا فک"] },
    { id: "sudden", any: ["درد شدید ناگهانی"] },
    { id: "rigid", region: "abdomen", any: ["سفتی شکم"] },
    { id: "altered", any: ["گیجی", "غش", "سرفه خونی"] },
    { id: "meningitis", all: ["سفتی گردن", "تب"] },
    { id: "pregnancy_warning", all: ["خونریزی واژینال", "درد شکم"] },
    { id: "chest_pain_any_region", any: ["درد قفسه سینه"] },
    { id: "cauda_equina", any: ["بی‌اختیاری جدید ادرار یا مدفوع", "بی‌حسی ناحیه نشیمنگاه"] }
];

// نقشه‌های اقدام
const ACTION_MAP = {
    urgent: "ارتباط فوری با اورژانس (۱۱۵)؛ مراجعه فوری به مرکز اورژانسی.",
    referral: "مراجعه به پزشک یا متخصص؛ بررسی بالینی در اسرع وقت.",
    consult: "مشاوره پزشکی یا پیگیری؛ مشاهده علائم و مراجعه در صورت تشدید."
};

// تابع بارگذاری داده‌ها
async function loadData() {
    try {
        const response = await fetch('/data/symptom-checker/medical_db.json');
        symptomData = await response.json();
        console.log("داده‌های دانشنامه (۱۲۰ بیماری) بارگذاری شدند.");
        renderRegions();
    } catch (error) {
        console.error("خطا در بارگذاری داده‌ها:", error);
        document.getElementById('status').innerText = "خطا در بارگذاری داده‌ها. لطفاً صفحه را بازخوانی کنید.";
    }
}

// نمایش ناحیه‌ها
function renderRegions() {
    const container = document.getElementById('region-container');
    container.innerHTML = '';
    
    for (const [key, region] of Object.entries(REGIONS)) {
        const btn = document.createElement('button');
        btn.className = 'region-btn';
        btn.innerText = region.name;
        btn.onclick = () => selectRegion(key);
        container.appendChild(btn);
    }
}

// انتخاب ناحیه
function selectRegion(regionId) {
    currentRegion = regionId;
    currentSymptoms = [];
    currentDisease = null;
    
    // نمایش علائم ناحیه
    const symptomsContainer = document.getElementById('symptoms-container');
    symptomsContainer.innerHTML = '';
    
    const categorySymptoms = SYMPTOM_CATEGORIES[regionId] || [];
    categorySymptoms.forEach(symptom => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = symptom;
        checkbox.id = `symptom-${symptom}`;
        
        const label = document.createElement('label');
        label.htmlFor = `symptom-${symptom}`;
        label.innerText = symptom;
        
        const div = document.createElement('div');
        div.className = 'symptom-item';
        div.appendChild(checkbox);
        div.appendChild(label);
        
        symptomsContainer.appendChild(div);
    });
    
    document.getElementById('status').innerText = `ناحیه انتخاب شده: ${REGIONS[regionId].name}`;
}

// بررسی علائم و تشخیص
function checkSymptoms() {
    const checkboxes = document.querySelectorAll('#symptoms-container input:checked');
    currentSymptoms = Array.from(checkboxes).map(cb => cb.value);
    
    if (currentSymptoms.length === 0) {
        document.getElementById('status').innerText = "لطفاً حداقل یک علامت را انتخاب کنید.";
        return;
    }
    
    // پیدا کردن بیماری‌های مطابقت‌کننده
    const matchingDiseases = symptomData.diseases.filter(d => 
        d.region === currentRegion && 
        d.symptoms.some(s => currentSymptoms.includes(s))
    );
    
    // پیدا کردن اقدام هوشمند (بر اساس قوانین اورژانسی)
    let actionLevel = "consult";
    let actionText = ACTION_MAP.consult;
    
    // بررسی قوانین اورژانسی
    for (const rule of URGENT_RULES) {
        const matchesAny = rule.any && rule.any.some(s => currentSymptoms.includes(s));
        const matchesAll = rule.all && rule.all.every(s => currentSymptoms.includes(s));
        const matchesRegion = !rule.region || rule.region === currentRegion;
        
        if ((matchesAny || matchesAll) && matchesRegion) {
            actionLevel = "urgent";
            actionText = ACTION_MAP.urgent;
            break;
        }
    }
    
    // اگر اورژانسی نبود، بررسی مراجعه (متوسط)
    if (actionLevel === "consult") {
        if (currentSymptoms.length >= 3 || matchingDiseases.length > 0) {
            actionLevel = "referral";
            actionText = ACTION_MAP.referral;
        }
    }
    
    // نمایش نتیجه
    const resultContainer = document.getElementById('result-container');
    resultContainer.innerHTML = '';
    
    if (matchingDiseases.length === 0) {
        resultContainer.innerHTML = `<div class="result-item">هیچ بیماری مطابقت‌کننده در دانشنامه یافت نشد. لطفاً علائم را بررسی کنید.</div>`;
        document.getElementById('status').innerText = "هیچ نتیجه‌ای یافت نشد.";
        return;
    }
    
    // نمایش بیماری‌های مطابقت‌کننده
    matchingDiseases.slice(0, 5).forEach(disease => {
        const diseaseData = symptomData.encyclopedia.find(e => e.diseaseId === disease.id);
        if (!diseaseData) return;
        
        const div = document.createElement('div');
        div.className = 'result-item';
        
        // سوالات اختصاصی
        const questionsHtml = diseaseData.specificQuestions.map(q => `<li>${q}</li>`).join('');
        
        // اقدامات متفاوت
        const actionHtml = `<div class="action-box">${actionText}</div>`;
        
        // اطلاعات کامل (علت، درمان، تاریخچه، دسته‌بندی)
        const infoHtml = `
            <div class="info-box">
                <strong>علت:</strong> ${diseaseData.cause}<br>
                <strong>درمان:</strong> ${diseaseData.commonTreatment}<br>
                <strong>تاریخچه:</strong> ${diseaseData.history}<br>
                <strong>دسته‌بندی:</strong> ${diseaseData.category}
            </div>
        `;
        
        div.innerHTML = `
            <h3>${diseaseData.nameFa} (${diseaseData.nameEn})</h3>
            <p><strong>سوالات اختصاصی:</strong></p>
            <ul>${questionsHtml}</ul>
            ${actionHtml}
            ${infoHtml}
        `;
        
        resultContainer.appendChild(div);
    });
    
    document.getElementById('status').innerText = `تعداد بیماری‌های مطابقت‌کننده: ${matchingDiseases.length} | اقدام: ${actionLevel}`;
}

// بارگذاری اولیه
window.onload = loadData;

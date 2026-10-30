/**
 * موتور تشخیص علائم (نسخه ۰.۳۰.۰)
 * پردازش داده‌های medical_db.json (۱۲۰ بیماری)
 * پشتیبانی از سوالات اختصاصی و اقدامات متفاوت
 */

// تابع پردازش داده‌ها
function processSymptoms(symptoms, region, data) {
    // فیلتر بیماری‌ها بر اساس ناحیه و علائم
    const matchingDiseases = data.diseases.filter(d => 
        d.region === region && 
        d.symptoms.some(s => symptoms.includes(s))
    );
    
    // پیدا کردن اقدام هوشمند
    let actionLevel = "consult";
    let actionText = data.actionMap.consult;
    
    // بررسی قوانین اورژانسی
    for (const rule of data.urgentRules) {
        const matchesAny = rule.any && rule.any.some(s => symptoms.includes(s));
        const matchesAll = rule.all && rule.all.every(s => symptoms.includes(s));
        const matchesRegion = !rule.region || rule.region === region;
        
        if ((matchesAny || matchesAll) && matchesRegion) {
            actionLevel = "urgent";
            actionText = data.actionMap.urgent;
            break;
        }
    }
    
    // اگر اورژانسی نبود، بررسی مراجعه
    if (actionLevel === "consult") {
        if (symptoms.length >= 3 || matchingDiseases.length > 0) {
            actionLevel = "referral";
            actionText = data.actionMap.referral;
        }
    }
    
    return {
        matchingDiseases,
        actionLevel,
        actionText,
        region,
        symptoms
    };
}

// تابع نمایش نتیجه
function displayResult(result, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    if (result.matchingDiseases.length === 0) {
        container.innerHTML = `<div class="result-item">هیچ بیماری مطابقت‌کننده در دانشنامه یافت نشد.</div>`;
        return;
    }
    
    // نمایش بیماری‌های مطابقت‌کننده
    result.matchingDiseases.slice(0, 5).forEach(disease => {
        const diseaseData = result.data.encyclopedia.find(e => e.diseaseId === disease.id);
        if (!diseaseData) return;
        
        const div = document.createElement('div');
        div.className = 'result-item';
        
        // سوالات اختصاصی
        const questionsHtml = diseaseData.specificQuestions.map(q => `<li>${q}</li>`).join('');
        
        // اقدامات متفاوت
        const actionHtml = `<div class="action-box">${result.actionText}</div>`;
        
        // اطلاعات کامل
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
        
        container.appendChild(div);
    });
}

// تابع اصلی (برای استفاده در قالب)
function runSymptomChecker(symptoms, region, dataPath = '/data/symptom-checker/medical_db.json') {
    // بارگذاری داده‌ها
    fetch(dataPath)
        .then(response => response.json())
        .then(data => {
            const result = processSymptoms(symptoms, region, data);
            result.data = data; // برای نمایش اطلاعات کامل
            displayResult(result, 'result-container');
        })
        .catch(error => {
            console.error("خطا در پردازش:", error);
            document.getElementById('result-container').innerHTML = `<div class="result-item">خطا در پردازش داده‌ها.</div>`;
        });
}

// تابع برای تست (در صفحه تست)
function testEngine() {
    // نمونه تست: آپاندیسیت (درد شکم، تهوع، تب)
    const testSymptoms = ["درد شکم", "تهوع", "تب"];
    const testRegion = "abdomen";
    
    fetch('/data/symptom-checker/medical_db.json')
        .then(response => response.json())
        .then(data => {
            const result = processSymptoms(testSymptoms, testRegion, data);
            result.data = data;
            displayResult(result, 'test-result');
            console.log("تست موتور موفقیت‌آمیز بود.");
        })
        .catch(error => {
            console.error("خطا در تست:", error);
        });
}

// فراخوانی تست در صفحه تست
if (window.location.pathname.includes('symptom-checker-test.php')) {
    testEngine();
}

#!/usr/bin/env python3
"""
ابزار «ساخت‌کننده دانشنامه»
تبدیل ۱۲۰ بیماری از disease-profiles.json به فرمت medical_db.json
با سوالات اختصاصی، اقدامات متفاوت (۱۱۵، مراجعه، مشاوره) و دسته‌بندی‌ها.
"""

import json
import os
from datetime import datetime

# مسیرهای فایل‌ها
INPUT_PATH = "/home/user/OR-site/themes/lax-theme-child/data/disease-profiles.json"
OUTPUT_PATH = "/home/user/OR-site/themes/lax-theme-child/data/symptom-checker/medical_db.json"

# دسته‌بندی‌های ناحیه (مناطق اصلی ابزار)
REGIONS = {
    "head": {"id": "head", "name": "سر و صورت", "description": "سردرد، سرگیجه، علائم صورت"},
    "chest": {"id": "chest", "name": "جلوی سینه و تنفس", "description": "درد سینه، تنگی نفس، عرق سرد"},
    "abdomen": {"id": "abdomen", "name": "شکم و لگن", "description": "درد شکم، تهوع، تغییرات گوارشی"},
    "back": {"id": "back", "name": "کمر پایین و پهلو", "description": "گرفتگی کمر، درد پهلو و ناحیه کلیه"},
    "urinary": {"id": "urinary", "name": "ادرار و کلیه", "description": "سوزش ادرار، تکرر یا خون در ادرار"},
    "skin": {"id": "skin", "name": "پوست و جوش", "description": "جوش، خارش، تغییرات پوست"},
    "general": {"id": "general", "name": "علائم عمومی", "description": "تب، خستگی، لرز یا سایر موارد"}
}

# دسته‌بندی‌های علائم (برای هر ناحیه)
SYMPTOM_CATEGORIES = {
    "head": ["سردرد", "درد شدید ناگهانی", "سرگیجه", "تهوع", "اختلال تکلم", "ضعف یک‌طرفه", "حساسیت به نور", "گرفتگی بینی", "سفتی گردن", "تب", "استفراغ", "گیجی", "تاری دید"],
    "chest": ["درد قفسه سینه", "عرق سرد", "درد دست یا فک", "تنگی نفس", "تهوع", "تپش قلب", "درد با حرکت", "تب", "سرفه", "سرفه خونی", "درد و تورم یک پا", "استفراغ"],
    "abdomen": ["درد شکم", "درد شدید ناگهانی", "سفتی شکم", "تب", "تهوع", "استفراغ", "اسهال", "سوزش ادرار", "سوزش سر دل", "بی‌اشتهایی", "تکرر ادرار", "خونریزی واژینال", "درد نوک شانه", "سرگیجه", "غش"],
    "back": ["درد کمر", "گرفتگی عضلات کمر", "درد پهلو یا ناحیه کلیه", "درد با حرکت", "سوزش ادرار", "تکرر ادرار", "خون در ادرار", "تب", "لرز", "تهوع", "استفراغ", "بی‌اختیاری جدید ادرار یا مدفوع", "بی‌حسی ناحیه نشیمنگاه"],
    "urinary": ["سوزش ادرار", "تکرر ادرار", "خون در ادرار", "درد پهلو یا ناحیه کلیه", "درد شکم", "تب", "لرز", "تهوع", "استفراغ"],
    "skin": ["جوش پوست", "پوست چرب", "برجستگی دردناک پوست", "خارش پوست", "تاول پوستی", "درد یا سوزش پوست", "تب"],
    "general": ["خستگی", "تب", "سرفه", "تنگی نفس", "تپش قلب", "تشنگی زیاد", "تکرر ادرار", "ضعف یک‌طرفه", "اختلال تکلم", "گیجی", "لرز", "سرفه خونی", "غش", "تاری دید", "کاهش وزن بی‌دلیل", "احساس سرما", "یبوست", "خشکی پوست", "افزایش وزن", "درد و تورم یک پا"]
}

# قوانین اورژانسی (برای هشدار هوشمند)
URGENT_RULES = [
    {"id": "stroke", "any": ["ضعف یک‌طرفه", "اختلال تکلم"]},
    {"id": "breath", "any": ["تنگی نفس"]},
    {"id": "chest", "region": "chest", "any": ["درد قفسه سینه", "عرق سرد", "درد دست یا فک"]},
    {"id": "sudden", "any": ["درد شدید ناگهانی"]},
    {"id": "rigid", "region": "abdomen", "any": ["سفتی شکم"]},
    {"id": "altered", "any": ["گیجی", "غش", "سرفه خونی"]},
    {"id": "meningitis", "all": ["سفتی گردن", "تب"]},
    {"id": "pregnancy_warning", "all": ["خونریزی واژینال", "درد شکم"]},
    {"id": "chest_pain_any_region", "any": ["درد قفسه سینه"]},
    {"id": "cauda_equina", "any": ["بی‌اختیاری جدید ادرار یا مدفوع", "بی‌حسی ناحیه نشیمنگاه"]}
]

# نقشه‌های اقدام بر اساس شدت (۱۱۵، مراجعه، مشاوره)
ACTION_MAP = {
    "urgent": "ارتباط فوری با اورژانس (۱۱۵)؛ مراجعه فوری به مرکز اورژانسی.",
    "referral": "مراجعه به پزشک یا متخصص؛ بررسی بالینی در اسرع وقت.",
    "consult": "مشاوره پزشکی یا پیگیری؛ مشاهده علائم و مراجعه در صورت تشدید."
}

def get_region_for_disease(disease_profile):
    """تعیین ناحیه بر اساس علائم یا تخصص"""
    category = disease_profile.get("category", "")
    specialty = disease_profile.get("profile", {}).get("specialty", "")
    symptoms_text = disease_profile.get("profile", {}).get("symptoms", "")
    
    # منطق ساده برای تعیین ناحیه
    if "سر" in specialty or "مغز" in specialty or "اعصاب" in specialty or "گوش" in specialty or "چشم" in specialty:
        return "head"
    if "قلب" in specialty or "عروق" in specialty or "ریه" in specialty or "تنفس" in specialty:
        return "chest"
    if "گوارش" in specialty or "کبد" in specialty or "جراحی عمومی" in specialty:
        return "abdomen"
    if "کلیه" in specialty or "ادرار" in specialty or "نفرولوژی" in specialty or "اورولوژی" in specialty:
        return "urinary"
    if "پوست" in specialty or "درماتولوژی" in specialty:
        return "skin"
    if "غدد" in specialty or "متابولیسم" in specialty or "خون" in specialty or "هماتولوژی" in specialty:
        return "general"
    if "کمر" in specialty or "اروپدی" in specialty:
        return "back"
    
    # بررسی علائم
    symptoms = symptoms_text.lower()
    if any(s in symptoms for s in ["سردرد", "سرگیجه", "چشم", "گوش", "تکلم", "ضعف یک‌طرفه", "تاری دید", "سفتی گردن", "گرفتگی بینی"]):
        return "head"
    if any(s in symptoms for s in ["سینه", "تنگی نفس", "عرق", "قلب", "تپش", "سرفه", "ریه", "تنفسی"]):
        return "chest"
    if any(s in symptoms for s in ["شکم", "گوارش", "کبد", "صفرا", "پروتئین", "روده", "گاسترو", "پروستات", "رحم", "واژن", "قاعدگی"]):
        return "abdomen"
    if any(s in symptoms for s in ["کمر", "کلیه", "ادرار", "مثانه", "پروستات", "پروانه", "لگن", "ناحیه کلیه"]):
        return "urinary"
    if any(s in symptoms for s in ["پوست", "جوش", "خارش", "تاول", "زخم", "مو", "ناخن", "لکه"]):
        return "skin"
    
    return "general"

def get_symptoms_list(disease_profile):
    """استخراج علائم اختصاصی از متن"""
    profile = disease_profile.get("profile", {})
    symptoms_text = profile.get("symptoms", "")
    
    # استخراج علائم از لیست‌های علامت (با استفاده از کلمات کلیدی)
    extracted = []
    for cat_symptoms in SYMPTOM_CATEGORIES.values():
        for sym in cat_symptoms:
            if sym in symptoms_text:
                extracted.append(sym)
    
    # حذف تکرار
    return list(set(extracted))

def get_action_level(disease_profile):
    """تعیین اقدام بر اساس شدت (بر اساس whenToSeeDoctor و علائم هشدار)"""
    profile = disease_profile.get("profile", {})
    when_to_see = profile.get("whenToSeeDoctor", "").lower()
    symptoms = profile.get("symptoms", "").lower()
    
    # اورژانس (۱۱۵)
    if any(word in when_to_see or word in symptoms for word in ["اورژانس", "فوری", "سریع", "مرگ", "سکته", "نارسایی", "خونریزی", "سفتی", "تنگی نفس شدید", "ضعف شدید", "تشنج", "افسردگی شدید", "خودکشی", "تب بالا", "لرز", "تاریکی", "سفتی گردن", "سرفه خونی", "خونریزی واژینال", "درد شدید ناگهانی", "سفتی شکم", "گیجی", "غش", "بی‌اختیاری", "بی‌حسی", "درد قفسه سینه", "عرق سرد", "درد دست", "فک", "تپش", "سرفه خونی", "درد و تورم", "تب", "لرز", "تهوع", "استفراغ", "اسهال", "سوزش", "تکرر", "خون", "درد کمر", "گرفتگی", "بی‌اختیاری", "بی‌حسی", "جوش", "پوست", "تب", "خستگی", "تنگی", "تپش", "تشنگی", "تکرر", "ضعف", "اختلال", "گیجی", "لرز", "سرفه", "غش", "تاری", "کاهش", "احساس", "یبوست", "خشکی", "افزایش", "درد", "تورم"]):
        return "urgent"
    
    # مراجعه (متوسط)
    if any(word in when_to_see or word in symptoms for word in ["مراجعه", "پزشک", "متخصص", "درمان", "آزمایش", "سونوگرافی", "سی‌تی‌اسکن", "ام‌آرآی", "بیوپسی", "جراحی", "آنتی‌بیوتیک", "دارو", "مسکن", "درد", "تب", "خستگی", "سرفه", "تنگی", "تپش", "تشنگی", "تکرر", "ضعف", "اختلال", "گیجی", "لرز", "سرفه", "غش", "تاری", "کاهش", "احساس", "یبوست", "خشکی", "افزایش", "درد", "تورم"]):
        return "referral"
    
    # مشاوره (خفیف)
    return "consult"

def get_specific_questions(disease_profile):
    """ساخت سوالات اختصاصی برای هر بیماری"""
    profile = disease_profile.get("profile", {})
    name = profile.get("nameFa", "")
    symptoms = profile.get("symptoms", "")
    
    # سوالات بر اساس علائم کلیدی
    questions = []
    if "درد" in symptoms:
        questions.append(f"آیا درد در {name} دارید؟")
    if "تب" in symptoms:
        questions.append(f"آیا تب یا لرز در {name} مشاهده می‌شود؟")
    if "سرفه" in symptoms:
        questions.append(f"آیا سرفه یا تنگی نفس در {name} وجود دارد؟")
    if "تهوع" in symptoms or "استفراغ" in symptoms:
        questions.append(f"آیا تهوع یا استفراغ در {name} رخ می‌دهد؟")
    if "خستگی" in symptoms:
        questions.append(f"آیا خستگی یا ضعف در {name} وجود دارد؟")
    if "تاری دید" in symptoms or "سرگیجه" in symptoms:
        questions.append(f"آیا سرگیجه یا تاری دید در {name} دیده می‌شود؟")
    if "سوزش" in symptoms:
        questions.append(f"آیا سوزش یا درد در {name} وجود دارد؟")
    if "خون" in symptoms:
        questions.append(f"آیا خونریزی یا تغییر رنگ در {name} دیده می‌شود؟")
    
    # اگر سوالی ساخته نشد، سوال کلی
    if not questions:
        questions = [f"علائم اصلی {name} چیست؟ (درد، تب، سرفه، تهوع، خستگی، تاری دید، سوزش، خونریزی)"]
    
    return questions[:3]  # حداکثر ۳ سوال

def build_encyclopedia_bridge():
    print(f"در حال خواندن منبع: {INPUT_PATH}")
    
    with open(INPUT_PATH, 'r', encoding='utf-8') as f:
        profiles = json.load(f)
    
    print(f"تعداد بیماری‌های خوانده شده: {len(profiles)}")
    
    # ساخت بخش‌های اصلی
    regions_list = list(REGIONS.values())
    symptom_categories = SYMPTOM_CATEGORIES
    urgent_rules = URGENT_RULES
    
    diseases = []
    encyclopedia = []
    
    for profile_data in profiles:
        disease_id = profile_data.get("diseaseId", "")
        profile = profile_data.get("profile", {})
        category = profile_data.get("category", "دانشنامه بیماری‌ها")
        name_fa = profile.get("nameFa", "")
        name_en = profile.get("nameEn", "")
        cause = profile.get("cause", "")
        history = profile.get("history", "")
        main_symptoms = profile.get("symptoms", "")
        common_treatment = profile.get("treatment", "")
        review_status = profile.get("profileStatus", "پیش‌نویس")
        source_checked_at = datetime.now().strftime("%Y-%m-%d")
        
        # منابع بالینی و تاریخچه
        clinical_sources = []
        history_sources = []
        for src in profile.get("sources", []):
            # ساده‌سازی منابع (متن و آدرس)
            text = src.split("•")[0].strip() if "•" in src else src
            url = ""
            if "https://" in src:
                url = src.split("https://")[-1].split()[0]
                url = "https://" + url
            clinical_sources.append({"text": text, "url": url})
            history_sources.append({"text": text, "url": url})
        
        # تعیین ناحیه
        region_id = get_region_for_disease(profile_data)
        
        # علائم اختصاصی
        symptoms_list = get_symptoms_list(profile_data)
        
        # سوالات اختصاصی
        questions = get_specific_questions(profile_data)
        
        # اقدام هوشمند
        action_level = get_action_level(profile_data)
        action_text = ACTION_MAP[action_level]
        
        # اضافه به لیست بیماری‌ها (برای ابزار تشخیص)
        diseases.append({
            "id": disease_id,
            "name": name_fa,
            "region": region_id,
            "symptoms": symptoms_list,
            "sources": clinical_sources[:2]  # حداکثر ۲ منبع برای نمایش سریع
        })
        
        # اضافه به دانشنامه (برای نمایش کامل)
        encyclopedia.append({
            "diseaseId": disease_id,
            "category": category,
            "nameFa": name_fa,
            "nameEn": name_en,
            "cause": cause,
            "history": history,
            "mainSymptoms": main_symptoms,
            "commonTreatment": common_treatment,
            "clinicalSources": clinical_sources,
            "historySources": history_sources,
            "reviewStatus": review_status,
            "sourceCheckedAt": source_checked_at,
            "specificQuestions": questions,
            "actionLevel": action_level,
            "actionText": action_text
        })
    
    # ساخت خروجی نهایی
    output_data = {
        "version": "0.30.0-encyclopedia",
        "lastUpdated": datetime.now().strftime("%Y-%m-%d"),
        "notice": "محتوای دانشنامه بیماری‌ها؛ ۱۲۰ بیماری کامل؛ سوالات اختصاصی و اقدامات متفاوت (۱۱۵، مراجعه، مشاوره)؛ پشتیبانی از به‌روزرسانی هفتگی.",
        "sources": {
            "icd11": "https://icd.who.int/",
            "gbd": "https://ghdx.healthdata.org/gbd-results-tool",
            "nhs": "https://www.nhs.uk/conditions/",
            "medline": "https://medlineplus.gov/",
            "mayo": "https://www.mayoclinic.org/",
            "aha": "https://www.ahajournals.org/journal/circ"
        },
        "symptomCategories": symptom_categories,
        "urgentRules": urgent_rules,
        "diseases": diseases,
        "regions": regions_list,
        "encyclopedia": encyclopedia
    }
    
    # ذخیره خروجی
    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
    with open(OUTPUT_PATH, 'w', encoding='utf-8') as f:
        json.dump(output_data, f, ensure_ascii=False, indent=2)
    
    print(f"خروجی تولید شد: {OUTPUT_PATH}")
    print(f"تعداد بیماری‌های پردازش‌شده: {len(diseases)}")
    print(f"تعداد مدخل‌های دانشنامه: {len(encyclopedia)}")

if __name__ == "__main__":
    build_encyclopedia_bridge()

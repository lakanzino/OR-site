#!/usr/bin/env python3
"""سازندهٔ بستهٔ دانش «کل بدن» (نسخهٔ ۳٫۰٫۰-پیش‌نویس).

بسته به‌جای یک خوشه، از ناحیهٔ بدن شروع می‌کند: جنسیت → ناحیه (پنج بخش) →
زیرناحیه → پرسش‌های همان ناحیه. محتوای شکم/پهلو از بستهٔ فاز ۱ گرفته می‌شود
(بی‌تغییر در متن، فقط با دروازهٔ ناحیه) و بقیه در همین فایل نوشته می‌شود.

قواعد:
- هر پرسش منبع دارد؛ هر ردیف شناسنامه هم.
- «ندارد» و «نمی‌دانم» هرگز به «هست» تبدیل نمی‌شوند؛ موتور همان‌طور که هست.
- سن و جنسیت فقط انتخاب می‌کنند «چه پرسیده شود»؛ در مسیر و نتیجه اثری ندارند.
- خروجی با اعتبارسنج موتور (`collectBundleIssues`) و اسکیمای مخزن سنجیده می‌شود.

اجرا:
    python3 tools/symptom-checker-content/build_body_bundle.py           # ساخت
    python3 tools/symptom-checker-content/build_body_bundle.py --check   # فقط سنجش
"""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "docs" / "symptom-checker" / "phase-1" / "knowledge-bundle.v2.json"
OUT = ROOT / "docs" / "symptom-checker" / "phase-6" / "knowledge-bundle.v3.json"
SCHEMA = ROOT / "docs" / "symptom-checker" / "schema" / "knowledge-bundle.v2.schema.json"

ACCESSED = "2026-10-31"

# ---------------------------------------------------------------------------
# منابع
# ---------------------------------------------------------------------------

NEW_SOURCES = [
    ("src_repo_body_map", "internal", "یادداشت طراحی نقشهٔ بدن و ناحیه‌ها در مخزن",
     "https://github.com/lakanzino/OR-site/blob/main/docs/symptom-checker/phase-6/body-map.fa.md"),
    ("src_nhs_genital_warts", "clinical", "NHS — زگیل تناسلی (نشانه‌ها و زمان مراجعه)",
     "https://www.nhs.uk/conditions/genital-warts/"),
    ("src_nhsinform_herpes", "clinical", "NHS Inform — تبخال تناسلی",
     "https://www.nhsinform.scot/illnesses-and-conditions/sexual-and-reproductive/genital-herpes/"),
    ("src_nhs_epididymitis", "clinical", "NHS — اپیدیدیمیت (درد و تورم بیضه)",
     "https://www.nhs.uk/conditions/epididymitis/"),
    ("src_cuh_torsion", "clinical", "NHS CUH — پیچش بیضه (نشانه‌های فوریت)",
     "https://www.cuh.nhs.uk/patient-information/testicular-torsion-information-for-teenagers/"),
    ("src_nhs_ngu", "clinical", "NHS — اورتریت غیرگونوکوکی (ترشح و سوزش ادرار)",
     "https://www.nhs.uk/conditions/non-gonococcal-urethritis/symptoms/"),
    ("src_nhsinform_chlamydia", "clinical", "NHS Inform — کلامیدیا",
     "https://www.nhsinform.scot/illnesses-and-conditions/sexual-and-reproductive/chlamydia/"),
    ("src_nhsinform_bv", "clinical", "NHS Inform — واژینوز باکتریایی (ترشح و بو)",
     "https://www.nhsinform.scot/illnesses-and-conditions/sexual-and-reproductive/bacterial-vaginosis/"),
    ("src_nhs_thrush", "clinical", "NHS — کاندیدا/برفک در زنان و مردان",
     "https://www.nhs.uk/conditions/thrush-in-men-and-women/"),
    ("src_guys_piles", "clinical", "Guy's and St Thomas' NHS — هموروئید (بواسیر)",
     "https://www.guysandstthomas.nhs.uk/health-information/piles-haemorrhoids"),
    ("src_guys_fissure", "clinical", "Guy's and St Thomas' NHS — شقاق مقعد",
     "https://www.guysandstthomas.nhs.uk/health-information/anal-fissure-treatment"),
    ("src_nhs_tension_headache", "clinical", "NHS — سردرد تنشی",
     "https://www.nhs.uk/conditions/tension-headaches/"),
    ("src_nhs_migraine", "clinical", "NHS — میگرن",
     "https://www.nhs.uk/conditions/migraine/"),
    ("src_nhsinform_tonsillitis", "clinical", "NHS Inform — التهاب لوزه",
     "https://www.nhsinform.scot/illnesses-and-conditions/ears-nose-and-throat/tonsillitis/"),
    ("src_nhs_heart_attack", "clinical", "NHS — حملهٔ قلبی (درد قفسهٔ سینه و ۹۹۹)",
     "https://www.nhs.uk/conditions/heart-attack/"),
    ("src_nhs_bronchitis", "clinical", "NHS — برونشیت حاد",
     "https://www.nhs.uk/conditions/bronchitis/"),
    ("src_nhs_common_cold", "clinical", "NHS — سرماخوردگی",
     "https://www.nhs.uk/conditions/common-cold/"),
    ("src_nhs_low_back_pain", "clinical", "NHS — کمردرد (نشانه‌های هشدار و مراجعه)",
     "https://www.dynamichealth.nhs.uk/help-and-advice/lower-back-pain/"),
    ("src_cleveland_cauda_equina", "clinical", "Cleveland Clinic — سندرم دم اسب (فوریت)",
     "https://my.clevelandclinic.org/health/diseases/22132-cauda-equina-syndrome"),
    ("src_nhs_sprains", "clinical", "NHS — پیچ‌خوردگی و کشیدگی",
     "https://www.nhs.uk/conditions/sprains-and-strains/"),
    ("src_uclh_dvt", "clinical", "UCLH NHS — ترومبوز ورید عمقی (DVT)",
     "https://www.uclh.nhs.uk/patients-and-visitors/patient-information-pages/deep-vein-thrombosis"),
    ("src_chelwest_cellulitis", "clinical", "Chelsea and Westminster NHS — عفونت پوست (سلولیت)",
     "https://www.chelwest.nhs.uk/your-visit/patient-leaflets/medicine-services/skin-infection-cellulitis"),
]

# ---------------------------------------------------------------------------
# نشانه‌ها: (id، برچسب، خوشه، مترادف‌ها)
# ---------------------------------------------------------------------------

NEW_SYMPTOMS = [
    ("hed.headache", "سردرد", "hed", ["سر درد", "درد سر"]),
    ("hed.one_side", "سردرد یک‌طرفه", "hed", []),
    ("hed.both_sides", "سردرد دوطرفه", "hed", []),
    ("hed.throbbing", "درد ضربان‌دار", "hed", []),
    ("hed.pressing", "احساس فشار یا گرفتگی دور سر", "hed", []),
    ("hed.nausea", "تهوع همراه سردرد", "hed", []),
    ("hed.light_sensitivity", "حساسیت به نور", "hed", []),
    ("hed.sound_sensitivity", "حساسیت به صدا", "hed", []),
    ("hed.sudden_worst", "سردرد ناگهانی و شدیدترین سردرد عمر", "hed", []),
    ("hed.neck_stiff_fever", "تب همراه سفت‌شدن گردن", "hed", []),
    ("hed.neurologic", "نشانهٔ عصبی همراه سردرد (ضعف، تکلم، بینایی، گیجی)", "hed", []),
    ("hed.activity_worse", "بدترشدن با فعالیت روزمره", "hed", []),
    ("thr.sore", "گلودرد", "thr", ["درد گلو"]),
    ("thr.odynophagia", "درد هنگام بلع", "thr", []),
    ("thr.fever", "تب", "thr", ["تب‌کردن"]),
    ("thr.cough", "سرفه همراه گلودرد", "thr", []),
    ("thr.white_spots", "لکهٔ سفید یا چرک روی لوزه", "thr", []),
    ("thr.swallow_difficulty", "سختی بلع، تنفس یا بازکردن دهان", "thr", []),
    ("thr.long_duration", "گلودرد بیش از یک هفته", "thr", []),
    ("chs.pain", "درد یا فشار قفسهٔ سینه", "chs", ["درد سینه"]),
    ("chs.breathless", "تنگی نفس", "chs", ["نفس‌تنگی"]),
    ("chs.cough", "سرفه", "chs", []),
    ("chs.sputum", "خلط", "chs", ["بلغم"]),
    ("chs.fever", "تب همراه نشانه‌های سینه", "chs", []),
    ("chs.runny_nose", "آبریزش بینی یا عطسه", "chs", []),
    ("bck.pain", "کمردرد", "bck", ["درد کمر", "کمر درد"]),
    ("bck.leg_radiation", "انتشار درد کمر به پا", "bck", ["درد سیاتیکی"]),
    ("bck.bladder_bowel", "اختلال تازهٔ ادرار یا مدفوع همراه کمردرد", "bck", []),
    ("bck.saddle_numb", "بی‌حسی ناحیهٔ زین (بین پاها و اطراف مقعد)", "bck", []),
    ("bck.fever", "تب همراه کمردرد", "bck", []),
    ("gen.discharge", "ترشح از نوک آلت", "gen", ["ترشح تناسلی"]),
    ("gen.ulcer", "زخم یا تاول تناسلی", "gen", []),
    ("gen.warts", "زگیل یا تودهٔ پوستی تناسلی", "gen", ["زگیل تناسلی"]),
    ("gen.swelling", "تورم آلت تناسلی", "gen", []),
    ("gen.pain", "درد ناحیهٔ تناسلی", "gen", []),
    ("gen.testis_pain", "درد بیضه", "gen", ["درد بیضه‌ها"]),
    ("gen.testis_sudden", "شروع ناگهانی درد بیضه", "gen", []),
    ("gen.scrotal_swelling", "تورم کیسهٔ بیضه", "gen", []),
    ("gen.testis_lump", "توده در بیضه", "gen", []),
    ("gen.sick", "تهوع یا استفراغ همراه درد بیضه", "gen", []),
    ("gen.vag_discharge", "ترشح واژن", "gen", []),
    ("gen.odor", "بوی بد ترشح", "gen", []),
    ("gen.itch", "خارش ناحیهٔ تناسلی", "gen", []),
    ("gen.vag_warts", "زگیل یا تودهٔ پوستی ناحیهٔ فرج", "gen", []),
    ("gen.bleeding_between", "خونریزی بین دوره‌ها یا بعد از نزدیکی", "gen", []),
    ("gen.dyspareunia", "درد هنگام نزدیکی", "gen", []),
    ("ans.pain", "درد مقعد", "ans", ["درد نشیمن‌گاه"]),
    ("ans.bleeding", "خونریزی از مقعد", "ans", ["خون در مدفوع"]),
    ("ans.lump", "توده یا برجستگی نزدیک مقعد", "ans", []),
    ("ans.pain_stool", "درد هنگام دفع", "ans", []),
    ("ans.discharge", "ترشح یا خارش مقعد", "ans", []),
    ("lmb.pain", "درد عضو", "lmb", []),
    ("lmb.injury", "ضربه یا آسیب به عضو", "lmb", []),
    ("lmb.swelling", "تورم عضو", "lmb", []),
    ("lmb.warm_red", "گرمی و قرمزی پوست عضو", "lmb", []),
    ("lmb.deformity", "تغییر شکل یا زاویهٔ غیرعادی عضو", "lmb", []),
    ("lmb.numb_cold", "بی‌حسی یا سردی عضو", "lmb", []),
    ("lmb.one_side_swelling", "تورم یک‌طرفهٔ پا", "lmb", []),
]

CLUSTERS = [
    ("hed", "سر و سردرد"),
    ("thr", "گلو و گردن"),
    ("chs", "سینه و تنفس"),
    ("bck", "پشت و کمر"),
    ("gen", "اندام تناسلی"),
    ("ans", "مقعد و راست‌روده"),
    ("lmb", "دست‌ها و پاها"),
]


def evidence(pairs):
    """[(symptom, state)] → شکل evidence در بسته."""
    return [{"symptom_id": symptom, "state": state} for symptom, state in pairs]


def opt(option_id, label, pairs=()):
    return {"id": option_id, "label_fa": label, "evidence": evidence(pairs)}


def yes_no_unknown(symptom, yes_label="بله", no_label="نه", unknown_label="مطمئن نیستم"):
    return [
        opt("yes", yes_label, [(symptom, "present")]),
        opt("no", no_label, [(symptom, "absent")]),
        opt("unknown", unknown_label),
    ]


def question(qid, order, text, why, qtype, options, visibility, sources):
    payload = {
        "id": qid,
        "text_fa": text,
        "why_fa": why,
        "type": qtype,
        "options": options,
        "visibility": visibility,
        "ask_order": order,
        "source_refs": list(sources),
        "review_status": "pending_team_review",
    }
    return payload


def always():
    return {"always": True}


def any_of(*rules):
    return {"any_of": [{"question_id": q, "answers": list(a)} for q, a in rules]}


def all_of(*rules):
    return {"all_of": [{"question_id": q, "answers": list(a)} for q, a in rules]}


# ---------------------------------------------------------------------------
# پرسش‌ها
# ---------------------------------------------------------------------------

def gate_questions():
    return [
        question(
            "q_sex", 10,
            "برای شروع، جنسیت زیستی‌ات را انتخاب کن",
            "این انتخاب فقط تعیین می‌کند کدام پرسش‌های ناحیهٔ تناسلی نمایش داده شوند؛ در مسیر، شاهد یا نتیجه اثری ندارد و ذخیره نمی‌شود.",
            "single",
            [
                opt("female", "زن"),
                opt("male", "مرد"),
                opt("prefer_not", "ترجیح می‌دهم نگویم"),
            ],
            always(),
            ["src_repo_body_map", "src_nhs_ngu"],
        ),
        question(
            "q_region", 20,
            "کدام بخش بدن بیشتر اذیتت می‌کند؟",
            "از ناحیهٔ بزرگ شروع می‌کنیم تا فقط پرسش‌های همان ناحیه پرسیده شود؛ اگر چند ناحیه درد دارد، مهم‌ترین را انتخاب کن.",
            "single",
            [
                opt("head_neck", "سر، صورت و گردن"),
                opt("torso", "سینه، شکم و پشت"),
                opt("pelvis", "لگن و اندام تناسلی"),
                opt("arms", "دست‌ها و بازوها"),
                opt("legs", "پاها و مچ پا"),
            ],
            always(),
            ["src_repo_body_map"],
        ),
        question(
            "q_area_head", 30,
            "دقیق‌تر کجای سر و گردن؟",
            "زیرناحیه، پرسش‌های بعدی را به همان قسمت محدود می‌کند.",
            "single",
            [
                opt("head", "سر و سردرد"),
                opt("throat", "گلو و گردن"),
            ],
            any_of(("q_region", ["head_neck"])),
            ["src_repo_body_map", "src_nhs_tension_headache"],
        ),
        question(
            "q_area_torso", 31,
            "دقیق‌تر کجای تنه؟",
            "سینه، شکم و پشت سه مسیر جدا دارند؛ انتخاب تو مسیر را مشخص می‌کند.",
            "single",
            [
                opt("chest", "سینه و قفسهٔ سینه"),
                opt("abdomen", "شکم و پهلو"),
                opt("back", "پشت و کمر"),
            ],
            any_of(("q_region", ["torso"])),
            ["src_repo_body_map", "src_nhs_low_back_pain"],
        ),
        question(
            "q_area_pelvis_m", 32,
            "دقیق‌تر کجای لگن؟",
            "این فهرست بر اساس اندام‌های بدن مردانه است؛ هر گزینه مسیر خودش را دارد.",
            "single",
            [
                opt("urinary", "ادرار و مثانه"),
                opt("penis", "آلت تناسلی"),
                opt("scrotum", "بیضه‌ها و کیسهٔ بیضه"),
                opt("anus", "مقعد"),
            ],
            all_of(("q_sex", ["male"]), ("q_region", ["pelvis"])),
            ["src_repo_body_map", "src_nhs_epididymitis"],
        ),
        question(
            "q_area_pelvis_f", 33,
            "دقیق‌تر کجای لگن؟",
            "این فهرست بر اساس اندام‌های بدن زنانه است؛ هر گزینه مسیر خودش را دارد.",
            "single",
            [
                opt("urinary", "ادرار و مثانه"),
                opt("vulva", "فرج و واژن"),
                opt("anus", "مقعد"),
            ],
            all_of(("q_sex", ["female"]), ("q_region", ["pelvis"])),
            ["src_repo_body_map", "src_nhsinform_bv"],
        ),
        question(
            "q_area_pelvis_x", 34,
            "دقیق‌تر کدام قسمت لگن؟",
            "اگر نمی‌خواهی جنسیت را بگویی، همین گزینه‌های عمومی هم مسیر را باز می‌کنند.",
            "single",
            [
                opt("urinary", "ادرار و مثانه"),
                opt("genital", "ناحیهٔ تناسلی (بدون جزئیات)"),
                opt("anus", "مقعد"),
            ],
            all_of(("q_sex", ["prefer_not"]), ("q_region", ["pelvis"])),
            ["src_repo_body_map", "src_nhs_genital_warts"],
        ),
        question(
            "q_area_arms", 35,
            "دقیق‌تر کجای دست؟",
            "دست و بازو مسیرهای متفاوتی دارند؛ انتخاب تو پرسش‌ها را کوتاه می‌کند.",
            "single",
            [
                opt("arm", "بازو و آرنج"),
                opt("hand", "ساعد، مچ و دست"),
            ],
            any_of(("q_region", ["arms"])),
            ["src_repo_body_map", "src_nhs_sprains"],
        ),
        question(
            "q_area_legs", 36,
            "دقیق‌تر کجای پا؟",
            "ران و زانو با ساق و مچ پا مسیر جدا دارند.",
            "single",
            [
                opt("thigh_knee", "ران و زانو"),
                opt("lower_foot", "ساق، مچ و پا"),
            ],
            any_of(("q_region", ["legs"])),
            ["src_repo_body_map", "src_uclh_dvt"],
        ),
    ]


def head_questions():
    area = ("q_area_head", ["head"])
    return [
        question("q_headache_present", 40, "سردرد داری؟",
                 "بدون سردرد، پرسش‌های بعدی این مسیر نمایش داده نمی‌شوند.",
                 "boolean3", yes_no_unknown("hed.headache"), all_of(area), ["src_nhs_tension_headache"]),
        question("q_headache_side", 41, "درد بیشتر کجای سر است؟",
                 "یک‌طرفه‌بودن به سمت الگوی میگرن اشاره می‌کند؛ دوطرفه به سردرد تنشی.",
                 "single",
                 [opt("one_side", "یک طرف سر", [("hed.one_side", "present")]),
                  opt("both_sides", "دو طرف سر", [("hed.both_sides", "present")]),
                  opt("whole", "کل سر، مثل کلاه", [("hed.both_sides", "present")]),
                  opt("unknown", "نمی‌دانم")],
                 all_of(area, ("q_headache_present", ["yes", "unknown"])),
                 ["src_nhs_migraine", "src_nhs_tension_headache"]),
        question("q_headache_quality", 42, "درد چه شکلی است؟",
                 "ضربان‌دار بودن به میگرن و فشار/گرفتگی به سردرد تنشی نزدیک‌تر است.",
                 "single",
                 [opt("throbbing", "ضربان‌دار", [("hed.throbbing", "present")]),
                  opt("pressing", "فشار یا گرفتگی", [("hed.pressing", "present")]),
                  opt("other", "شکل دیگری دارد")],
                 all_of(area, ("q_headache_present", ["yes", "unknown"])),
                 ["src_nhs_migraine", "src_nhs_tension_headache"]),
        question("q_headache_associated", 43, "این‌ها هم همراه سردرد هست؟",
                 "تهوع و حساسیت به نور/صدا الگوی میگرن را تقویت می‌کند؛ انتخاب چندگانه است.",
                 "multi",
                 [opt("nausea", "تهوع", [("hed.nausea", "present")]),
                  opt("light", "حساسیت به نور", [("hed.light_sensitivity", "present")]),
                  opt("sound", "حساسیت به صدا", [("hed.sound_sensitivity", "present")]),
                  opt("worse_activity", "با فعالیت بدتر می‌شود", [("hed.activity_worse", "present")]),
                  {"id": "none", "label_fa": "هیچ‌کدام", "exclusive": True, "evidence": []},
                  {"id": "unknown", "label_fa": "نمی‌دانم", "exclusive": True, "evidence": []}],
                 all_of(area, ("q_headache_present", ["yes", "unknown"])),
                 ["src_nhs_migraine"]),
        question("q_headache_sudden", 44, "سردرد ناگهانی شروع شد و شدیدترین سردرد عمرت بود؟",
                 "این الگو نیاز به ارزیابی فوری دارد و پیش از هر نتیجه‌ای هشدار می‌گیرد.",
                 "boolean3", yes_no_unknown("hed.sudden_worst"),
                 all_of(area, ("q_headache_present", ["yes", "unknown"])),
                 ["src_nhs_migraine"]),
        question("q_headache_neck_fever", 45, "همراه سردرد، تب و سفتی گردن داری؟",
                 "تب با گردن سفت یکی از نشانه‌های هشدار مننژیت است.",
                 "boolean3", yes_no_unknown("hed.neck_stiff_fever"),
                 all_of(area, ("q_headache_present", ["yes", "unknown"])),
                 ["src_nhs_migraine"]),
        question("q_headache_neuro", 46, "کدام‌یک همراه سردرد پیش آمده؟",
                 "ضعف، مشکل تکلم یا بینایی و گیجی نشانه‌های سکته‌اند و فوریت دارند.",
                 "multi",
                 [opt("weakness", "ضعف یا بی‌حسی یک طرف بدن", [("hed.neurologic", "present")]),
                  opt("speech", "مشکل در حرف زدن یا فهمیدن", [("hed.neurologic", "present")]),
                  opt("vision", "تاری یا از دست رفتن بینایی", [("hed.neurologic", "present")]),
                  opt("confusion", "گیجی یا خواب‌آلودگی غیرعادی", [("hed.neurologic", "present")]),
                  {"id": "none", "label_fa": "هیچ‌کدام", "exclusive": True, "evidence": []},
                  {"id": "unknown", "label_fa": "نمی‌دانم", "exclusive": True, "evidence": []}],
                 all_of(area, ("q_headache_present", ["yes", "unknown"])),
                 ["src_nhs_migraine"]),
        # --- گلو
        question("q_throat_pain", 47, "گلویت درد می‌کند؟",
                 "شروع مسیر گلو از وجود گلودرد است.",
                 "boolean3", yes_no_unknown("thr.sore"), all_of(("q_area_head", ["throat"])),
                 ["src_nhsinform_tonsillitis"]),
        question("q_throat_fever", 48, "تب داری؟",
                 "تب همراه گلودرد و لکهٔ چرکی احتمال التهاب لوزه را مطرح می‌کند.",
                 "boolean3", yes_no_unknown("thr.fever"),
                 all_of(("q_area_head", ["throat"]), ("q_throat_pain", ["yes", "unknown"])),
                 ["src_nhsinform_tonsillitis"]),
        question("q_throat_swallow", 49, "بلع یا نفس کشیدن سخت شده، یا دهانت را به‌سختی باز می‌کنی؟",
                 "این نشانه‌ها فوریت دارند و پیش از ادامه هشدار می‌گیرند.",
                 "boolean3", yes_no_unknown("thr.swallow_difficulty"),
                 all_of(("q_area_head", ["throat"]), ("q_throat_pain", ["yes", "unknown"])),
                 ["src_nhsinform_tonsillitis"]),
        question("q_throat_cough", 50, "سرفه هم داری؟",
                 "سرفه بیشتر به گلودرد ویروسی نزدیک است تا التهاب لوزه.",
                 "boolean3", yes_no_unknown("thr.cough"),
                 all_of(("q_area_head", ["throat"]), ("q_throat_pain", ["yes", "unknown"])),
                 ["src_nhs_common_cold", "src_nhsinform_tonsillitis"]),
        question("q_throat_spots", 51, "لکهٔ سفید یا چرک روی لوزه‌ها دیده‌ای؟",
                 "لکهٔ چرکی روی لوزه در التهاب لوزه شایع است و مسیر آن را باز می‌کند.",
                 "boolean3", yes_no_unknown("thr.white_spots"),
                 all_of(("q_area_head", ["throat"]), ("q_throat_pain", ["yes", "unknown"])),
                 ["src_nhsinform_tonsillitis"]),
        question("q_throat_days", 52, "چند وقت است گلویت درد می‌کند؟",
                 "گلودرد بیش از یک هفته بدون بهبود، دلیل مراجعه است.",
                 "single",
                 [opt("few", "کمتر از ۴ روز"),
                  opt("week", "۴ تا ۷ روز"),
                  opt("more", "بیش از یک هفته", [("thr.long_duration", "present")])],
                 all_of(("q_area_head", ["throat"]), ("q_throat_pain", ["yes", "unknown"])),
                 ["src_nhsinform_tonsillitis"]),
    ]


def torso_questions():
    chest_area = ("q_area_torso", ["chest"])
    back_area = ("q_area_torso", ["back"])
    return [
        question("q_chest_pain", 60, "همین حالا درد یا فشار در قفسهٔ سینه داری؟",
                 "درد قفسهٔ سینه در این ابزار بررسی نمی‌شود و پاسخ «بله» مسیر را به توصیهٔ فوری می‌برد.",
                 "boolean3", yes_no_unknown("chs.pain"),
                 all_of(chest_area), ["src_nhs_heart_attack"]),
        question("q_breathless", 61, "نفس کشیدن برایت سخت شده؟",
                 "تنگی نفس شدید یکی از نشانه‌های فوریت است.",
                 "boolean3", yes_no_unknown("chs.breathless"),
                 all_of(chest_area, ("q_chest_pain", ["no"])), ["src_nhs_heart_attack"]),
        question("q_chest_cough", 62, "سرفه داری؟",
                 "سرفه شایع‌ترین نشانهٔ برونشیت و سرماخوردگی است.",
                 "boolean3", yes_no_unknown("chs.cough"),
                 all_of(chest_area, ("q_chest_pain", ["no"])), ["src_nhs_bronchitis"]),
        question("q_cough_days", 63, "چند وقت است سرفه می‌کنی؟",
                 "سرفهٔ بیش از سه هفته بدون بهبود دلیل مراجعه است.",
                 "single",
                 [opt("under1", "کمتر از یک هفته"),
                  opt("one_three", "یک تا سه هفته"),
                  opt("over3", "بیش از سه هفته")],
                 all_of(chest_area, ("q_chest_cough", ["yes"])), ["src_nhs_bronchitis"]),
        question("q_chest_sputum", 64, "با سرفه خلط بیرون می‌آید؟",
                 "خلط در برونشیت شایع است و در سرماخوردگی هم دیده می‌شود.",
                 "boolean3", yes_no_unknown("chs.sputum"),
                 all_of(chest_area, ("q_chest_cough", ["yes"])), ["src_nhs_bronchitis"]),
        question("q_chest_fever", 65, "تب داری؟",
                 "تب همراه سرفه به عفونت تنفسی اشاره می‌کند.",
                 "boolean3", yes_no_unknown("chs.fever"),
                 all_of(chest_area, ("q_chest_pain", ["no"])), ["src_nhs_bronchitis"]),
        question("q_chest_cold", 66, "آبریزش بینی یا عطسه هم داری؟",
                 "آبریزش بینی و عطسه بیشتر به سرماخوردگی نزدیک است.",
                 "boolean3", yes_no_unknown("chs.runny_nose"),
                 all_of(chest_area, ("q_chest_pain", ["no"])), ["src_nhs_common_cold"]),
        # --- پشت
        question("q_back_pain", 70, "کمرت درد می‌کند؟",
                 "شروع مسیر کمر از وجود درد است.",
                 "boolean3", yes_no_unknown("bck.pain"), all_of(back_area), ["src_nhs_low_back_pain"]),
        question("q_back_leg", 71, "درد کمر به پایت هم می‌زند؟",
                 "انتشار درد به پا الگوی سیاتیک را مطرح می‌کند.",
                 "boolean3", yes_no_unknown("bck.leg_radiation"),
                 all_of(back_area, ("q_back_pain", ["yes", "unknown"])), ["src_nhs_low_back_pain"]),
        question("q_back_flags", 72, "کدام‌یک همراه کمردرد پیش آمده؟",
                 "اختلال ادرار یا مدفوع و بی‌حسی ناحیهٔ زین از فوریت‌های جراحی هستند.",
                 "multi",
                 [opt("bladder", "تغییر تازه در دفع ادرار (نگه‌داشتن یا بی‌اختیاری)", [("bck.bladder_bowel", "present")]),
                  opt("bowel", "تغییر تازه در کنترل مدفوع", [("bck.bladder_bowel", "present")]),
                  opt("saddle", "بی‌حسی ناحیهٔ زین (بین پاها و اطراف مقعد)", [("bck.saddle_numb", "present")]),
                  opt("fever", "تب یا لرز", [("bck.fever", "present")]),
                  {"id": "none", "label_fa": "هیچ‌کدام", "exclusive": True, "evidence": []},
                  {"id": "unknown", "label_fa": "نمی‌دانم", "exclusive": True, "evidence": []}],
                 all_of(back_area, ("q_back_pain", ["yes", "unknown"])),
                 ["src_cleveland_cauda_equina", "src_nhs_low_back_pain"]),
    ]


def pelvis_questions():
    anywhere_urinary = any_of(
        ("q_area_pelvis_m", ["urinary"]),
        ("q_area_pelvis_f", ["urinary"]),
        ("q_area_pelvis_x", ["urinary"]),
    )
    anus_anyone = any_of(
        ("q_area_pelvis_m", ["anus"]),
        ("q_area_pelvis_f", ["anus"]),
        ("q_area_pelvis_x", ["anus"]),
    )
    return [
        question("q_urin_pain", 100, "هنگام ادرار کردن سوزش یا درد داری؟",
                 "سوزش ادرار شایع‌ترین نشانهٔ عفونت ادراری است.",
                 "boolean3", yes_no_unknown("abd.dysuria"), anywhere_urinary, ["src_nhs_uti"]),
        question("q_urin_freq", 101, "بیشتر از معمول به دستشویی می‌روی؟",
                 "تکرر ادرار در عفونت ادراری شایع است.",
                 "boolean3", yes_no_unknown("abd.frequency"), anywhere_urinary, ["src_nhs_uti"]),
        question("q_urin_blood", 102, "خون در ادرار دیده‌ای؟",
                 "خون در ادرار دلیل بررسی پزشکی است و مسیر سنگ و عفونت را باز می‌کند.",
                 "boolean3", yes_no_unknown("abd.hematuria"), anywhere_urinary, ["src_nhs_uti", "src_niddk_stone"]),
        question("q_urin_fever", 103, "تب یا لرز داری؟",
                 "تب همراه نشانه‌های ادراری می‌تواند به عفونت کلیه اشاره کند.",
                 "boolean3", yes_no_unknown("abd.fever"), anywhere_urinary, ["src_nhs_kidney_infection"]),
        question("q_urin_flank", 104, "درد پهلو یا کمر داری؟",
                 "درد پهلو همراه تب، الگوی عفونت کلیه است.",
                 "boolean3", yes_no_unknown("abd.flank_pain"), anywhere_urinary,
                 ["src_nhs_kidney_infection", "src_niddk_stone"]),
        # --- آلت (مردانه)
        question("q_penis_discharge", 110, "ترشح از نوک آلت دیدی؟",
                 "ترشح، نشانهٔ اصلی اورتریت است.",
                 "boolean3", yes_no_unknown("gen.discharge"),
                 all_of(("q_area_pelvis_m", ["penis"])), ["src_nhs_ngu", "src_nhsinform_chlamydia"]),
        question("q_penis_ulcer", 111, "زخم یا تاول روی آلت دیدی؟",
                 "زخم یا تاول تناسلی مسیر تبخال تناسلی را باز می‌کند.",
                 "boolean3", yes_no_unknown("gen.ulcer"),
                 all_of(("q_area_pelvis_m", ["penis"])), ["src_nhsinform_herpes"]),
        question("q_penis_warts", 112, "توده یا زگیل کوچک روی آلت حس کرده‌ای؟",
                 "زگیل تناسلی معمولاً بی‌درد است و تشخیصش با معاینه است.",
                 "boolean3", yes_no_unknown("gen.warts"),
                 all_of(("q_area_pelvis_m", ["penis"])), ["src_nhs_genital_warts"]),
        question("q_penis_pain", 113, "هنگام ادرار سوزش داری؟",
                 "سوزش ادرار همراه ترشح، الگوی اورتریت است.",
                 "boolean3", yes_no_unknown("abd.dysuria"),
                 all_of(("q_area_pelvis_m", ["penis"])), ["src_nhs_ngu"]),
        question("q_penis_swelling", 114, "تورم یا قرمزی آلت داری؟",
                 "تورم و قرمزی می‌تواند نشانهٔ عفونت یا التهاب پوستی باشد.",
                 "boolean3", yes_no_unknown("gen.swelling"),
                 all_of(("q_area_pelvis_m", ["penis"])), ["src_nhs_thrush"]),
        # --- بیضه (مردانه)
        question("q_scrotum_pain", 120, "بیضه یا کیسهٔ بیضه درد می‌کند؟",
                 "درد بیضه مسیر بیضه را باز می‌کند؛ شروع ناگهانی فوریت است.",
                 "boolean3", yes_no_unknown("gen.testis_pain"),
                 all_of(("q_area_pelvis_m", ["scrotum"])), ["src_nhs_epididymitis", "src_cuh_torsion"]),
        question("q_scrotum_onset", 121, "درد چطور شروع شد؟",
                 "شروع ناگهانی درد شدید، الگوی پیچش بیضه است و فوریت دارد.",
                 "single",
                 [opt("sudden", "ناگهانی و شدید", [("gen.testis_sudden", "present")]),
                  opt("gradual", "تدریجی، طی چند ساعت یا چند روز")],
                 all_of(("q_area_pelvis_m", ["scrotum"]), ("q_scrotum_pain", ["yes", "unknown"])),
                 ["src_cuh_torsion", "src_nhs_epididymitis"]),
        question("q_scrotum_swelling", 122, "کیسهٔ بیضه‌ات ورم کرده یا قرمز شده؟",
                 "تورم و قرمزی همراه درد به عفونت اپیدیدیم یا پیچش اشاره می‌کند.",
                 "boolean3", yes_no_unknown("gen.scrotal_swelling"),
                 all_of(("q_area_pelvis_m", ["scrotum"]), ("q_scrotum_pain", ["yes", "unknown"])),
                 ["src_nhs_epididymitis"]),
        question("q_scrotum_lump", 123, "توده یا برجستگی در بیضه حس می‌کنی؟",
                 "هر تودهٔ بیضه باید توسط پزشک بررسی شود.",
                 "boolean3", yes_no_unknown("gen.testis_lump"),
                 all_of(("q_area_pelvis_m", ["scrotum"])), ["src_nhs_epididymitis"]),
        question("q_scrotum_sick", 124, "تهوع یا استفراغ هم داری؟",
                 "تهوع همراه درد شدید بیضه در پیچش شایع است.",
                 "boolean3", yes_no_unknown("gen.sick"),
                 all_of(("q_area_pelvis_m", ["scrotum"]), ("q_scrotum_pain", ["yes", "unknown"])),
                 ["src_cuh_torsion"]),
        # --- فرج و واژن (زنانه)
        question("q_vag_discharge", 130, "ترشح واژن داری که برایت تازه یا متفاوت است؟",
                 "تغییر ترشح، شایع‌ترین نشانهٔ واژینوز و برفک است.",
                 "boolean3", yes_no_unknown("gen.vag_discharge"),
                 all_of(("q_area_pelvis_f", ["vulva"])), ["src_nhsinform_bv", "src_nhs_thrush"]),
        question("q_vag_odor", 131, "ترشح بوی بد یا ماهی‌مانند دارد؟",
                 "بوی ماهی‌مانند به واژینوز باکتریایی نزدیک‌تر است.",
                 "boolean3", yes_no_unknown("gen.odor"),
                 all_of(("q_area_pelvis_f", ["vulva"]), ("q_vag_discharge", ["yes", "unknown"])),
                 ["src_nhsinform_bv"]),
        question("q_vag_itch", 132, "خارش یا سوزش در ناحیهٔ فرج داری؟",
                 "خارش شدید بیشتر به برفک نزدیک است.",
                 "boolean3", yes_no_unknown("gen.itch"),
                 all_of(("q_area_pelvis_f", ["vulva"])), ["src_nhs_thrush"]),
        question("q_vag_warts", 133, "زگیل یا تودهٔ پوستی در این ناحیه دیدی؟",
                 "زگیل تناسلی معمولاً بی‌درد است و با معاینه تشخیص داده می‌شود.",
                 "boolean3", yes_no_unknown("gen.vag_warts"),
                 all_of(("q_area_pelvis_f", ["vulva"])), ["src_nhs_genital_warts"]),
        question("q_vag_ulcer", 134, "زخم یا تاول در این ناحیه دیدی؟",
                 "زخم یا تاول مسیر تبخال تناسلی را باز می‌کند.",
                 "boolean3", yes_no_unknown("gen.ulcer"),
                 all_of(("q_area_pelvis_f", ["vulva"])), ["src_nhsinform_herpes"]),
        question("q_vag_bleeding", 135, "بین دوره‌ها یا بعد از نزدیکی خون‌ریزی داری؟",
                 "خون‌ریزی غیرعادی دلیل بررسی پزشکی است.",
                 "boolean3", yes_no_unknown("gen.bleeding_between"),
                 all_of(("q_area_pelvis_f", ["vulva"])), ["src_nhsinform_chlamydia"]),
        question("q_vag_pain_sex", 136, "نزدیکی برایت دردناک شده؟",
                 "درد هنگام نزدیکی در عفونت‌های تناسلی شایع است.",
                 "boolean3", yes_no_unknown("gen.dyspareunia"),
                 all_of(("q_area_pelvis_f", ["vulva"])), ["src_nhs_thrush", "src_nhsinform_chlamydia"]),
        # --- مقعد
        question("q_anus_pain", 140, "مقعدت درد می‌کند؟",
                 "درد مقعد مسیر شقاق و هموروئید را باز می‌کند.",
                 "boolean3", yes_no_unknown("ans.pain"), anus_anyone, ["src_guys_fissure"]),
        question("q_anus_bleeding", 141, "خون روشن از مقعد یا روی دستمال دیده‌ای؟",
                 "خونریزی مقعدی دلایل خوش‌خیم و جدی دارد و همیشه بررسی می‌شود.",
                 "boolean3", yes_no_unknown("ans.bleeding"), anus_anyone, ["src_guys_piles"]),
        question("q_anus_lump", 142, "توده یا برجستگی نزدیک مقعد حس می‌کنی؟",
                 "تودهٔ نزدیک مقعد بیشتر هموروئید است، ولی معاینه لازم دارد.",
                 "boolean3", yes_no_unknown("ans.lump"), anus_anyone, ["src_guys_piles"]),
        question("q_anus_stool", 143, "هنگام دفع درد تیز داری؟",
                 "درد تیز هنگام دفع، نشانهٔ اصلی شقاق مقعد است.",
                 "boolean3", yes_no_unknown("ans.pain_stool"), anus_anyone, ["src_guys_fissure"]),
        question("q_anus_discharge", 144, "ترشح، خارش یا احساس دفع ناقص داری؟",
                 "ترشح و خارش در هموروئید دیده می‌شود.",
                 "boolean3", yes_no_unknown("ans.discharge"), anus_anyone, ["src_guys_piles"]),
        # --- تناسلی عمومی (وقتی جنسیت گفته نمی‌شود)
        question("q_gen_discharge", 150, "ترشح از ناحیهٔ تناسلی داری؟",
                 "ترشح، شایع‌ترین نشانهٔ عفونت‌های تناسلی است.",
                 "boolean3", yes_no_unknown("gen.discharge"),
                 all_of(("q_area_pelvis_x", ["genital"])), ["src_nhs_ngu"]),
        question("q_gen_ulcer", 151, "زخم یا تاول در ناحیهٔ تناسلی داری؟",
                 "زخم یا تاول مسیر تبخال تناسلی را باز می‌کند.",
                 "boolean3", yes_no_unknown("gen.ulcer"),
                 all_of(("q_area_pelvis_x", ["genital"])), ["src_nhsinform_herpes"]),
        question("q_gen_warts", 152, "زگیل یا تودهٔ پوستی در این ناحیه دیدی؟",
                 "زگیل تناسلی با معاینه تشخیص داده می‌شود و درمان دارد.",
                 "boolean3", yes_no_unknown("gen.warts"),
                 all_of(("q_area_pelvis_x", ["genital"])), ["src_nhs_genital_warts"]),
        question("q_gen_pain", 153, "درد یا سوزش در ناحیهٔ تناسلی داری؟",
                 "درد و سوزش بدون جزئیات، مسیر عمومی عفونت را باز می‌کند.",
                 "boolean3", yes_no_unknown("gen.pain"),
                 all_of(("q_area_pelvis_x", ["genital"])), ["src_nhs_ngu"]),
    ]


def limb_questions():
    arms = any_of(("q_area_arms", ["arm"]), ("q_area_arms", ["hand"]))
    legs = any_of(("q_area_legs", ["thigh_knee"]), ("q_area_legs", ["lower_foot"]))
    return [
        question("q_arm_injury", 160, "ضربه یا آسیبی به دست خورده؟",
                 "آسیب، شایع‌ترین علت درد و تورم دست است.",
                 "boolean3", yes_no_unknown("lmb.injury"), arms, ["src_nhs_sprains"]),
        question("q_arm_swelling", 161, "دستت ورم کرده؟",
                 "تورم همراه آسیب یا عفونت دیده می‌شود.",
                 "boolean3", yes_no_unknown("lmb.swelling"), arms, ["src_nhs_sprains", "src_chelwest_cellulitis"]),
        question("q_arm_deformity", 162, "شکل دست عوض شده یا زاویهٔ غیرعادی دارد؟",
                 "تغییر شکل یعنی احتمال شکستگی یا دررفتگی و نیاز به ارزیابی فوری.",
                 "boolean3", yes_no_unknown("lmb.deformity"), arms, ["src_nhs_sprains"]),
        question("q_arm_numb", 163, "دستت بی‌حس، سرد یا رنگ‌پریده شده؟",
                 "بی‌حسی و سردی یعنی اختلال گردش خون و فوریت دارد.",
                 "boolean3", yes_no_unknown("lmb.numb_cold"), arms, ["src_nhs_sprains"]),
        question("q_arm_warm", 164, "پوست دست گرم، قرمز یا دردناک شده؟",
                 "گرمی و قرمزی رو به گسترش می‌تواند عفونت پوست باشد.",
                 "boolean3", yes_no_unknown("lmb.warm_red"), arms, ["src_chelwest_cellulitis"]),
        question("q_leg_injury", 170, "ضربه یا آسیبی به پا خورده؟",
                 "آسیب، شایع‌ترین علت درد و تورم پاست.",
                 "boolean3", yes_no_unknown("lmb.injury"), legs, ["src_nhs_sprains"]),
        question("q_leg_swelling", 171, "پایت ورم کرده؟",
                 "تورم پا می‌تواند از آسیب یا لخته باشد.",
                 "boolean3", yes_no_unknown("lmb.swelling"), legs, ["src_nhs_sprains", "src_uclh_dvt"]),
        question("q_leg_one_side", 172, "تورم فقط در یک پاست و همراه درد یا گرمی است؟",
                 "تورم یک‌طرفهٔ پا با درد و گرمی، الگوی لختهٔ وریدی است و بررسی فوری می‌خواهد.",
                 "boolean3", yes_no_unknown("lmb.one_side_swelling"), legs, ["src_uclh_dvt"]),
        question("q_leg_deformity", 173, "شکل پا عوض شده یا زاویهٔ غیرعادی دارد؟",
                 "تغییر شکل یعنی احتمال شکستگی یا دررفتگی و نیاز به ارزیابی فوری.",
                 "boolean3", yes_no_unknown("lmb.deformity"), legs, ["src_nhs_sprains"]),
        question("q_leg_numb", 174, "پایت بی‌حس، سرد یا رنگ‌پریده شده؟",
                 "بی‌حسی و سردی یعنی اختلال گردش خون و فوریت دارد.",
                 "boolean3", yes_no_unknown("lmb.numb_cold"), legs, ["src_nhs_sprains"]),
        question("q_leg_warm", 175, "پوست پا گرم، قرمز یا دردناک شده؟",
                 "گرمی و قرمزی رو به گسترش می‌تواند عفونت پوست باشد.",
                 "boolean3", yes_no_unknown("lmb.warm_red"), legs, ["src_chelwest_cellulitis"]),
    ]


# ---------------------------------------------------------------------------
# شناسنامه‌ها (موضوع‌ها)
# ---------------------------------------------------------------------------

def row(symptom, level, line, *sources):
    return {"symptom_id": symptom, "level": level, "profile_line": line, "source_refs": list(sources)}


def condition(cid, name, rows, red_flags=(), article=None, link_status="unmapped"):
    return {
        "id": cid,
        "name_fa": name,
        "article_slug": article,
        "profile_ref": f"docs/symptom-checker/phase-6/knowledge-bundle.v3.json#{cid}",
        "link_status": link_status,
        "profile": rows,
        "red_flag_refs": list(red_flags),
        "review_status": "pending_team_review",
    }


def new_conditions():
    return [
        condition("headache_tension", "سردرد تنشی (فشار عصبی)", [
            row("hed.both_sides", "typical", "درد در دو طرف سر، صورت یا گردن", "src_nhs_tension_headache"),
            row("hed.pressing", "typical", "احساس فشار یا گرفتگی دور سر، مثل نواری که سفت شده", "src_nhs_tension_headache"),
            row("hed.throbbing", "possible", "گاهی ضربان‌دار هم می‌شود", "src_nhs_tension_headache"),
            row("hed.nausea", "rare", "تهوع معمولاً دیده نمی‌شود", "src_nhs_tension_headache"),
            row("hed.light_sensitivity", "rare", "حساسیت شدید به نور معمول نیست", "src_nhs_tension_headache"),
            row("hed.activity_worse", "possible", "معمولاً می‌توانی کارهای روزمره را ادامه بدهی", "src_nhs_tension_headache"),
        ], red_flags=["sf_headache_sudden", "sf_headache_meningitis", "sf_headache_neuro"]),
        condition("migraine", "میگرن", [
            row("hed.one_side", "typical", "درد اغلب یک طرف سر است", "src_nhs_migraine"),
            row("hed.throbbing", "typical", "درد ضربان‌دار", "src_nhs_migraine"),
            row("hed.nausea", "typical", "تهوع یا استفراغ همراه حمله", "src_nhs_migraine"),
            row("hed.light_sensitivity", "typical", "حساسیت شدید به نور", "src_nhs_migraine"),
            row("hed.sound_sensitivity", "typical", "حساسیت شدید به صدا", "src_nhs_migraine"),
            row("hed.activity_worse", "typical", "با حرکت و فعالیت بدتر می‌شود", "src_nhs_migraine"),
            row("hed.both_sides", "possible", "گاهی درد دو طرفه است", "src_nhs_migraine"),
        ], red_flags=["sf_headache_sudden", "sf_headache_neuro"]),
        condition("pharyngitis", "گلودرد ویروسی (فارنژیت)", [
            row("thr.sore", "typical", "گلودرد، اغلب همراه سرفه", "src_nhs_common_cold"),
            row("thr.odynophagia", "typical", "درد هنگام بلع", "src_nhs_common_cold"),
            row("thr.cough", "typical", "سرفه و آبریزش بینی", "src_nhs_common_cold"),
            row("thr.fever", "possible", "تب خفیف ممکن است", "src_nhs_common_cold"),
            row("thr.white_spots", "not_expected", "لکهٔ چرکی روی لوزه معمول نیست", "src_nhsinform_tonsillitis"),
        ], red_flags=["sf_throat_airway"]),
        condition("tonsillitis", "التهاب لوزه", [
            row("thr.sore", "typical", "گلودرد شدید", "src_nhsinform_tonsillitis"),
            row("thr.odynophagia", "typical", "درد شدید هنگام بلع", "src_nhsinform_tonsillitis"),
            row("thr.fever", "typical", "تب بالای ۳۸ درجه", "src_nhsinform_tonsillitis"),
            row("thr.white_spots", "typical", "لکه‌های سفید یا چرکی روی لوزه", "src_nhsinform_tonsillitis"),
            row("thr.cough", "not_expected", "سرفه در التهاب لوزه معمول نیست", "src_nhsinform_tonsillitis"),
            row("thr.long_duration", "possible", "اگر بیش از ۴ روز طول بکشد، بررسی لازم است", "src_nhsinform_tonsillitis"),
        ], red_flags=["sf_throat_airway"]),
        condition("acute_bronchitis", "برونشیت حاد", [
            row("chs.cough", "typical", "سرفهٔ اصلی‌ترین نشانه است و ممکن است هفته‌ها بماند", "src_nhs_bronchitis"),
            row("chs.sputum", "typical", "خلط سفید، زرد یا سبز", "src_nhs_bronchitis"),
            row("chs.breathless", "possible", "تنگی نفس یا خس‌خس سینه", "src_nhs_bronchitis"),
            row("chs.fever", "possible", "تب خفیف", "src_nhs_bronchitis"),
            row("chs.runny_nose", "possible", "آبریزش بینی و گلودرد", "src_nhs_bronchitis"),
        ], red_flags=["sf_chest_pain", "sf_breathless"]),
        condition("common_cold", "سرماخوردگی", [
            row("chs.runny_nose", "typical", "آبریزش بینی و عطسه", "src_nhs_common_cold"),
            row("thr.sore", "typical", "گلودرد خفیف", "src_nhs_common_cold"),
            row("chs.cough", "typical", "سرفهٔ خفیف", "src_nhs_common_cold"),
            row("chs.fever", "rare", "تب بالا معمول نیست", "src_nhs_common_cold"),
            row("chs.sputum", "possible", "خلط روشن", "src_nhs_common_cold"),
        ], red_flags=["sf_breathless"]),
        condition("mechanical_back_pain", "کمردرد مکانیکی (بدون نشانهٔ هشدار)", [
            row("bck.pain", "typical", "درد کمر که با حرکت و وضعیت بدن تغییر می‌کند", "src_nhs_low_back_pain"),
            row("bck.leg_radiation", "possible", "انتشار درد به باسن یا پا", "src_nhs_low_back_pain"),
            row("bck.saddle_numb", "not_expected", "بی‌حسی ناحیهٔ زین نشانهٔ هشدار است", "src_cleveland_cauda_equina"),
            row("bck.bladder_bowel", "not_expected", "تغییر کنترل ادرار یا مدفوع نشانهٔ هشدار است", "src_cleveland_cauda_equina"),
            row("bck.fever", "not_expected", "تب همراه کمردرد نیاز به بررسی دارد", "src_nhs_low_back_pain"),
        ], red_flags=["sf_back_emergency", "sf_back_fever"]),
        condition("urethritis", "اورتریت (التهاب مجرای ادرار)", [
            row("gen.discharge", "typical", "ترشح سفید، زرد یا شفاف از نوک آلت", "src_nhs_ngu"),
            row("abd.dysuria", "typical", "سوزش یا درد هنگام ادرار", "src_nhs_ngu"),
            row("gen.itch", "possible", "خارش یا تحریک نوک آلت", "src_nhs_ngu"),
            row("gen.testis_pain", "possible", "درد بیضه در عفونت‌های درمان‌نشده", "src_nhsinform_chlamydia"),
            row("gen.pain", "possible", "درد یا ناراحتی ناحیهٔ تناسلی", "src_nhs_ngu"),
        ]),
        condition("genital_warts", "زگیل تناسلی", [
            row("gen.warts", "typical", "توده‌های کوچک و زبر، تک یا گروهی، روی آلت", "src_nhs_genital_warts"),
            row("gen.vag_warts", "typical", "توده‌های کوچک اطراف واژن یا مقعد", "src_nhs_genital_warts"),
            row("ans.lump", "possible", "برجستگی اطراف مقعد", "src_nhs_genital_warts"),
            row("gen.itch", "possible", "گاهی خارش یا خون‌ریزی خفیف", "src_nhs_genital_warts"),
            row("gen.pain", "possible", "گاهی درد یا ناراحتی هنگام نزدیکی", "src_nhs_genital_warts"),
        ]),
        condition("genital_herpes", "تبخال تناسلی", [
            row("gen.ulcer", "typical", "تاول‌های دردناک که می‌ترکند و زخم می‌شوند", "src_nhsinform_herpes"),
            row("gen.itch", "typical", "سوزش یا خارش پیش از ظاهر شدن تاول", "src_nhsinform_herpes"),
            row("abd.dysuria", "possible", "درد هنگام ادرار از تماس ادرار با زخم", "src_nhsinform_herpes"),
            row("gen.discharge", "possible", "ترشح همراه عفونت اول", "src_nhsinform_herpes"),
            row("gen.vag_discharge", "possible", "ترشح واژن در عفونت اول", "src_nhsinform_herpes"),
        ]),
        condition("epididymitis", "اپیدیدیمیت (عفونت لولهٔ پشت بیضه)", [
            row("gen.testis_pain", "typical", "درد یک یا هر دو بیضه، تدریجی یا ناگهانی", "src_nhs_epididymitis"),
            row("gen.scrotal_swelling", "typical", "کیسهٔ بیضه گرم، حساس و ورم‌کرده", "src_nhs_epididymitis"),
            row("abd.dysuria", "possible", "سختی یا سوزش ادرار", "src_nhs_epididymitis"),
            row("gen.discharge", "possible", "ترشح سفید، زرد یا سبز از نوک آلت", "src_nhs_epididymitis"),
            row("gen.testis_lump", "possible", "احساس توده یا آب‌آوردگی دور بیضه", "src_nhs_epididymitis"),
        ], red_flags=["sf_scrotum_sudden", "sf_scrotum_lump"]),
        condition("testicular_torsion", "پیچش بیضه (فوریت)", [
            row("gen.testis_sudden", "typical", "شروع ناگهانی درد شدید یک بیضه", "src_cuh_torsion"),
            row("gen.testis_pain", "typical", "درد شدید و مداوم بیضه", "src_cuh_torsion"),
            row("gen.scrotal_swelling", "typical", "تورم و قرمزی یک طرف کیسهٔ بیضه", "src_cuh_torsion"),
            row("gen.sick", "typical", "تهوع یا استفراغ همراه درد", "src_cuh_torsion"),
            row("gen.testis_lump", "not_expected", "تودهٔ بی‌درد معمولاً علت دیگری دارد", "src_nhs_epididymitis"),
        ], red_flags=["sf_scrotum_sudden"]),
        condition("bacterial_vaginosis", "واژینوز باکتریایی", [
            row("gen.vag_discharge", "typical", "ترشح نازک سفید-خاکستری و آبکی", "src_nhsinform_bv"),
            row("gen.odor", "typical", "بوی ماهی‌مانند، بیشتر بعد از رابطه", "src_nhsinform_bv"),
            row("gen.itch", "not_expected", "خارش و سوزش معمولاً دیده نمی‌شود", "src_nhsinform_bv"),
            row("gen.dyspareunia", "possible", "ناراحتی خفیف هنگام نزدیکی", "src_nhsinform_bv"),
        ]),
        condition("vaginal_thrush", "برفک واژن (کاندیدا)", [
            row("gen.vag_discharge", "typical", "ترشح سفید و غلیظ، شبیه پنیر، بی‌بو", "src_nhs_thrush"),
            row("gen.itch", "typical", "خارش و سوزش ناحیهٔ فرج و واژن", "src_nhs_thrush"),
            row("gen.dyspareunia", "typical", "سوزش هنگام نزدیکی یا ادرار", "src_nhs_thrush"),
            row("gen.odor", "possible", "معمولاً بی‌بوست، ولی بوی خفیف ممکن است", "src_nhs_thrush"),
        ]),
        condition("hemorrhoids", "هموروئید (بواسیر)", [
            row("ans.bleeding", "typical", "خون روشن، جدا از مدفوع یا روی دستمال", "src_guys_piles"),
            row("ans.lump", "typical", "توده یا برجستگی اطراف مقعد", "src_guys_piles"),
            row("ans.pain_stool", "typical", "درد یا سوزش هنگام دفع و زور زدن", "src_guys_piles"),
            row("ans.discharge", "possible", "خارش یا ترشح مخاطی", "src_guys_piles"),
            row("ans.pain", "possible", "درد مقعد، بیشتر اگر لخته شده باشد", "src_guys_piles"),
        ], red_flags=["sf_anus_bleeding"]),
        condition("anal_fissure", "شقاق مقعد", [
            row("ans.pain_stool", "typical", "درد تیز و برنده هنگام دفع، که ساعت‌ها می‌ماند", "src_guys_fissure"),
            row("ans.bleeding", "typical", "خون روشن و کم روی دستمال", "src_guys_fissure"),
            row("ans.pain", "typical", "درد یا سوزش مقعد بعد از دفع", "src_guys_fissure"),
        ], red_flags=["sf_anus_bleeding"]),
        condition("sprain_strain", "پیچ‌خوردگی یا کشیدگی", [
            row("lmb.injury", "typical", "بعد از ضربه، پیچ‌خوردگی یا حرکت ناگهانی", "src_nhs_sprains"),
            row("lmb.pain", "typical", "درد در محل آسیب", "src_nhs_sprains"),
            row("lmb.swelling", "typical", "تورم و کبودی", "src_nhs_sprains"),
            row("lmb.warm_red", "rare", "گرمی و قرمزی شدید نشانهٔ عفونت است، نه کشیدگی", "src_chelwest_cellulitis"),
        ], red_flags=["sf_limb_deformity", "sf_limb_circulation"]),
        condition("cellulitis", "عفونت پوست (سلولیت)", [
            row("lmb.warm_red", "typical", "پوست ناگهان قرمز، گرم، دردناک و متورم می‌شود", "src_chelwest_cellulitis"),
            row("lmb.swelling", "typical", "تورم و حساسیت به لمس", "src_chelwest_cellulitis"),
            row("lmb.pain", "typical", "درد که با حرکت بدتر می‌شود", "src_chelwest_cellulitis"),
            row("lmb.injury", "possible", "زخم یا خراش کوچک روی پوست", "src_chelwest_cellulitis"),
        ]),
        condition("deep_vein_thrombosis", "لختهٔ ورید عمقی (DVT)", [
            row("lmb.one_side_swelling", "typical", "تورم یک پا، معمولاً ساق یا ران", "src_uclh_dvt"),
            row("lmb.pain", "typical", "درد ضربان‌دار یا گرفتگی در همان پا", "src_uclh_dvt"),
            row("lmb.warm_red", "typical", "گرمی و قرمزی پوست اطراف محل درد", "src_uclh_dvt"),
            row("lmb.injury", "not_expected", "بدون آسیب یا ضربه رخ می‌دهد", "src_uclh_dvt"),
        ], red_flags=["sf_leg_dvt"]),
    ]


# ---------------------------------------------------------------------------
# مسیرها (paths) — کلید باز شدن هر موضوع
# ---------------------------------------------------------------------------

def path(pid, cid, when, note, *sources):
    return {
        "id": pid,
        "condition_id": cid,
        "when": [{"question_id": q, "answers": list(a)} for q, a in when],
        "note_fa": note,
        "source_refs": list(sources),
    }


def new_paths():
    return [
        path("p_tension_headache", "headache_tension",
             [("q_area_head", ["head"]), ("q_headache_present", ["yes", "unknown"]),
              ("q_headache_side", ["both_sides", "whole"])],
             "سردرد دوطرفه با احساس فشار، مسیر سردرد تنشی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_tension_headache"),
        path("p_migraine", "migraine",
             [("q_area_head", ["head"]), ("q_headache_present", ["yes", "unknown"]),
              ("q_headache_side", ["one_side"])],
             "سردرد یک‌طرفه مسیر میگرن را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_migraine"),
        path("p_pharyngitis", "pharyngitis",
             [("q_area_head", ["throat"]), ("q_throat_cough", ["yes"])],
             "گلودرد همراه سرفه بیشتر الگوی ویروسی دارد؛ تشخیص نیست.",
             "src_nhs_common_cold"),
        path("p_tonsillitis", "tonsillitis",
             [("q_area_head", ["throat"]), ("q_throat_spots", ["yes"])],
             "لکهٔ چرکی روی لوزه مسیر التهاب لوزه را باز می‌کند؛ تشخیص نیست.",
             "src_nhsinform_tonsillitis"),
        path("p_bronchitis", "acute_bronchitis",
             [("q_area_torso", ["chest"]), ("q_chest_cough", ["yes"]),
              ("q_cough_days", ["one_three", "over3"])],
             "سرفهٔ بیش از یک هفته مسیر برونشیت حاد را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_bronchitis"),
        path("p_common_cold", "common_cold",
             [("q_area_torso", ["chest"]), ("q_chest_cold", ["yes"])],
             "آبریزش بینی و عطسه مسیر سرماخوردگی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_common_cold"),
        path("p_mechanical_back", "mechanical_back_pain",
             [("q_area_torso", ["back"]), ("q_back_pain", ["yes", "unknown"])],
             "کمردرد بدون نشانهٔ هشدار، مسیر کمردرد مکانیکی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_low_back_pain"),
        path("p_urethritis", "urethritis",
             [("q_penis_discharge", ["yes"])],
             "ترشح از نوک آلت مسیر اورتریت را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_ngu"),
        path("p_urethritis_general", "urethritis",
             [("q_gen_discharge", ["yes"])],
             "ترشح تناسلی بدون جزئیات هم همین مسیر را باز می‌کند.",
             "src_nhs_ngu"),
        path("p_warts_penis", "genital_warts",
             [("q_penis_warts", ["yes"])],
             "توده یا زگیل روی آلت مسیر زگیل تناسلی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_genital_warts"),
        path("p_warts_vulva", "genital_warts",
             [("q_vag_warts", ["yes"])],
             "توده یا زگیل ناحیهٔ فرج مسیر زگیل تناسلی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_genital_warts"),
        path("p_warts_general", "genital_warts",
             [("q_gen_warts", ["yes"])],
             "تودهٔ پوستی تناسلی مسیر زگیل تناسلی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_genital_warts"),
        path("p_herpes_penis", "genital_herpes",
             [("q_penis_ulcer", ["yes"])],
             "زخم یا تاول روی آلت مسیر تبخال تناسلی را باز می‌کند؛ تشخیص نیست.",
             "src_nhsinform_herpes"),
        path("p_herpes_vulva", "genital_herpes",
             [("q_vag_ulcer", ["yes"])],
             "زخم یا تاول ناحیهٔ فرج مسیر تبخال تناسلی را باز می‌کند؛ تشخیص نیست.",
             "src_nhsinform_herpes"),
        path("p_herpes_general", "genital_herpes",
             [("q_gen_ulcer", ["yes"])],
             "زخم تناسلی مسیر تبخال تناسلی را باز می‌کند؛ تشخیص نیست.",
             "src_nhsinform_herpes"),
        path("p_epididymitis", "epididymitis",
             [("q_area_pelvis_m", ["scrotum"]), ("q_scrotum_pain", ["yes", "unknown"]),
              ("q_scrotum_onset", ["gradual"]), ("q_scrotum_swelling", ["yes"])],
             "درد تدریجی همراه تورم کیسهٔ بیضه، مسیر اپیدیدیمیت را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_epididymitis"),
        path("p_torsion", "testicular_torsion",
             [("q_area_pelvis_m", ["scrotum"]), ("q_scrotum_pain", ["yes"]),
              ("q_scrotum_onset", ["sudden"])],
             "درد ناگهانی و شدید بیضه، مسیر پیچش را باز می‌کند و هم‌زمان هشدار فوری می‌دهد.",
             "src_cuh_torsion"),
        path("p_bv", "bacterial_vaginosis",
             [("q_vag_discharge", ["yes", "unknown"]), ("q_vag_odor", ["yes"])],
             "ترشح همراه بوی ماهی‌مانند، مسیر واژینوز باکتریایی را باز می‌کند؛ تشخیص نیست.",
             "src_nhsinform_bv"),
        path("p_thrush", "vaginal_thrush",
             [("q_vag_discharge", ["yes", "unknown"]), ("q_vag_itch", ["yes"])],
             "ترشح همراه خارش، مسیر برفک را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_thrush"),
        path("p_piles", "hemorrhoids",
             [("q_anus_lump", ["yes"])],
             "تودهٔ نزدیک مقعد مسیر هموروئید را باز می‌کند؛ تشخیص نیست.",
             "src_guys_piles"),
        path("p_fissure", "anal_fissure",
             [("q_anus_stool", ["yes"])],
             "درد تیز هنگام دفع مسیر شقاق را باز می‌کند؛ تشخیص نیست.",
             "src_guys_fissure"),
        path("p_sprain_arm", "sprain_strain",
             [("q_area_arms", ["arm", "hand"]), ("q_arm_injury", ["yes"])],
             "آسیب دست، مسیر پیچ‌خوردگی/کشیدگی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_sprains"),
        path("p_sprain_leg", "sprain_strain",
             [("q_area_legs", ["thigh_knee", "lower_foot"]), ("q_leg_injury", ["yes"])],
             "آسیب پا، مسیر پیچ‌خوردگی/کشیدگی را باز می‌کند؛ تشخیص نیست.",
             "src_nhs_sprains"),
        path("p_cellulitis_arm", "cellulitis",
             [("q_arm_warm", ["yes"])],
             "گرمی و قرمزی پوست دست مسیر عفونت پوست را باز می‌کند؛ تشخیص نیست.",
             "src_chelwest_cellulitis"),
        path("p_cellulitis_leg", "cellulitis",
             [("q_leg_warm", ["yes"])],
             "گرمی و قرمزی پوست پا مسیر عفونت پوست را باز می‌کند؛ تشخیص نیست.",
             "src_chelwest_cellulitis"),
        path("p_dvt", "deep_vein_thrombosis",
             [("q_leg_one_side", ["yes"])],
             "تورم یک‌طرفهٔ پا مسیر لختهٔ وریدی را باز می‌کند و هشدار مراجعه می‌دهد.",
             "src_uclh_dvt"),
    ]


# ---------------------------------------------------------------------------
# ایمنی و پوشش
# ---------------------------------------------------------------------------

def safety(sid, when, severity, message, *sources):
    return {
        "id": sid,
        "when": [clause_or_tuple(item) for item in when],
        "severity": severity,
        "message_fa": message,
        "source_refs": list(sources),
        "review_status": "pending_team_review",
    }


def new_safety():
    return [
        safety("sf_chest_pain",
               [("q_chest_pain", ["yes"])], "urgent",
               "درد یا فشار قفسهٔ سینه نیاز به ارزیابی فوری دارد؛ اگر همین حالا ادامه دارد، با ۱۱۵ تماس بگیر و منتظر پایان این پرسش‌نامه نمان.",
               "src_nhs_heart_attack"),
        safety("sf_breathless",
               [("q_breathless", ["yes"])], "urgent",
               "تنگی نفس شدید یا ناگهانی نیاز به ارزیابی فوری دارد؛ با ۱۱۵ تماس بگیر.",
               "src_nhs_heart_attack"),
        safety("sf_headache_sudden",
               [("q_headache_sudden", ["yes"])], "urgent",
               "سردرد ناگهانی و شدیدترین سردرد عمر، باید فوری بررسی شود؛ با ۱۱۵ تماس بگیر یا به اورژانس برو.",
               "src_nhs_migraine"),
        safety("sf_headache_meningitis",
               [("q_headache_neck_fever", ["yes"])], "urgent",
               "تب همراه سردرد و سفتی گردن یکی از نشانه‌های مننژیت است و ارزیابی فوری لازم دارد.",
               "src_nhs_migraine"),
        safety("sf_headache_neuro",
               [("q_headache_neuro", ["weakness", "speech", "vision", "confusion"])], "urgent",
               "ضعف یک‌طرفه، مشکل تکلم یا بینایی و گیجی همراه سردرد نشانه‌های سکته‌اند؛ همین حالا با ۱۱۵ تماس بگیر.",
               "src_nhs_migraine"),
        safety("sf_throat_airway",
               [("q_throat_swallow", ["yes"])], "urgent",
               "سختی بلع یا نفس کشیدن، یا ناتوانی در باز کردن دهان، نیاز به ارزیابی فوری دارد.",
               "src_nhsinform_tonsillitis"),
        safety("sf_scrotum_sudden",
               [("q_scrotum_onset", ["sudden"])], "urgent",
               "درد ناگهانی و شدید بیضه می‌تواند پیچش بیضه باشد؛ همین حالا به اورژانس برو یا با ۱۱۵ تماس بگیر.",
               "src_cuh_torsion"),
        safety("sf_scrotum_lump",
               [("q_scrotum_lump", ["yes"])], "prompt",
               "هر توده یا برجستگی در بیضه باید توسط پزشک بررسی شود؛ این بررسی را به تأخیر نینداز.",
               "src_nhs_epididymitis"),
        safety("sf_back_emergency",
               [("q_back_flags", ["bladder", "bowel", "saddle"])], "urgent",
               "تغییر تازه در کنترل ادرار یا مدفوع، یا بی‌حسی ناحیهٔ زین همراه کمردرد، فوریت جراحی است؛ همین حالا به اورژانس برو.",
               "src_cleveland_cauda_equina"),
        safety("sf_back_fever",
               [("q_back_flags", ["fever"])], "prompt",
               "تب یا لرز همراه کمردرد نیاز به بررسی همان روز دارد.",
               "src_nhs_low_back_pain"),
        safety("sf_leg_dvt",
               [("q_leg_one_side", ["yes"])], "prompt",
               "تورم یک‌طرفهٔ پا با درد و گرمی باید همان روز بررسی شود؛ اگر تنگی نفس یا درد سینه اضافه شد، فوری با ۱۱۵ تماس بگیر.",
               "src_uclh_dvt"),
        safety("sf_limb_deformity",
               [{"any_of": [{"question_id": "q_arm_deformity", "answers": ["yes"]},
                            {"question_id": "q_leg_deformity", "answers": ["yes"]}]}], "urgent",
               "تغییر شکل یا زاویهٔ غیرعادی عضو بعد از آسیب، نیاز به ارزیابی فوری دارد.",
               "src_nhs_sprains"),
        safety("sf_limb_circulation",
               [{"any_of": [{"question_id": "q_arm_numb", "answers": ["yes"]},
                            {"question_id": "q_leg_numb", "answers": ["yes"]}]}], "urgent",
               "بی‌حسی، سردی یا رنگ‌پریدگی عضو یعنی اختلال گردش خون؛ فوری ارزیابی شود.",
               "src_nhs_sprains"),
        safety("sf_anus_bleeding",
               [("q_anus_bleeding", ["yes"])], "prompt",
               "خونریزی مقعدی همیشه باید توسط پزشک بررسی شود؛ بیشتر علت‌ها خوش‌خیم‌اند، ولی معاینه لازم است.",
               "src_guys_piles"),
    ]


def coverage(cid, when, result, message, *sources):
    return {
        "id": cid,
        "when": [clause_or_tuple(item) for item in when],
        "result": result,
        "message_fa": message,
        "source_refs": list(sources),
    }


def new_coverage():
    return [
        coverage("cv_out_of_scope_chest_pain",
                 [("q_chest_pain", ["yes"])], "out_of_scope",
                 "درد یا فشار قفسهٔ سینه در این ابزار بررسی نمی‌شود. اگر همین حالا ادامه دارد، یا با تنگی نفس، تعریق، تهوع یا انتشار به دست و فک همراه است، با ۱۱۵ تماس بگیر.",
                 "src_nhs_heart_attack"),
        coverage("cv_out_of_scope_pregnancy",
                 [("q_pregnancy_possible", ["yes", "unsure", "prefer_not"])], "out_of_scope",
                 "این پرسش‌نامه برای احتمال بارداری طراحی نشده است؛ نتیجه‌ای دربارهٔ بیماری‌ها نمایش داده نمی‌شود. برای درد شکم در بارداری با پزشک یا ماما تماس بگیر.",
                 "src_nhs_app"),
        coverage("cv_sufficient",
                 [{"branches_open_min": 1}], "sufficient",
                 "موضوع‌های مرتبط با پاسخ‌های شما در این ناحیه نمایش داده می‌شوند؛ این‌ها تشخیص نیستند.",
                 "src_nhs_uti"),
        coverage("cv_complete_no_match",
                 [{"branches_open_max": 0}, {"unanswered_max": 0}], "insufficient",
                 "همهٔ پرسش‌های این ناحیه را پاسخ داده‌اید و پاسخ‌ها با موضوع‌های این نسخه هم‌خوان نبود. این به معنی سالم بودن نیست؛ اگر نشانه ادامه دارد یا بدتر می‌شود، با پزشک مشورت کن.",
                 "src_nhs_uti"),
        coverage("cv_no_branch",
                 [{"branches_open_max": 0}], "insufficient",
                 "با پاسخ‌های فعلی، موضوعی از این ناحیه قابل بررسی نیست. این به معنی سالم بودن نیست.",
                 "src_nhs_uti"),
    ]


# ---------------------------------------------------------------------------
# محتوای شکم/پهلو از بستهٔ فاز ۱ (بی‌تغییر در متن، با دروازهٔ ناحیه)
# ---------------------------------------------------------------------------

ABDOMEN_FIRST_ORDER = 80


def clause_or_tuple(item):
    """یک بند را از شکل (سؤال، پاسخ‌ها) یا دیکشنری آماده بساز."""
    if isinstance(item, dict):
        return item
    qid, answers = item
    return {"question_id": qid, "answers": list(answers)}


def with_area(area_question, area_value, visibility):
    """(ناحیه) AND (قاعدهٔ قبلی) — قاعدهٔ قبلی ممکن است چند شاخه داشته باشد."""
    gates = [{"question_id": area_question, "answers": [area_value]}]
    if visibility.get("always"):
        return {"all_of": gates}
    previous = [clause_or_tuple(item) for item in visibility.get("any_of", [])]
    if not previous:
        return {"all_of": gates}
    if len(previous) == 1:
        return {"all_of": gates + previous}
    return {"all_of": gates + [{"any_of": previous}]}


def abdomen_questions(base):
    ordered = sorted(base["questions"], key=lambda item: item["ask_order"])
    converted = []
    for index, question in enumerate(ordered):
        copy = json.loads(json.dumps(question, ensure_ascii=False))
        copy["ask_order"] = ABDOMEN_FIRST_ORDER + index
        copy["visibility"] = with_area("q_area_torso", "abdomen", question.get("visibility", {}))
        if copy["id"] == "q_pregnancy_possible":
            # A man is not asked about pregnancy: the question now depends on the
            # sex gate as well as on the area, so the same answer can never mean
            # two different things.
            copy["visibility"] = {
                "all_of": [
                    {"question_id": "q_area_torso", "answers": ["abdomen"]},
                    {"any_of": [
                        {"question_id": "q_sex", "answers": ["female"]},
                        {"question_id": "q_sex", "answers": ["prefer_not"]},
                    ]},
                ]
            }
        converted.append(copy)
    return converted


def abdomen_conditions(base):
    converted = []
    for condition in base["conditions"]:
        copy = json.loads(json.dumps(condition, ensure_ascii=False))
        converted.append(copy)
    return converted


def abdomen_paths(base):
    converted = []
    for override in base["overrides"]:
        copy = json.loads(json.dumps(override, ensure_ascii=False))
        copy["when"] = [{"question_id": "q_area_torso", "answers": ["abdomen"]}] + copy["when"]
        converted.append(copy)
    return converted


# ---------------------------------------------------------------------------
# مونتاژ
# ---------------------------------------------------------------------------

def dedupe(items, label):
    seen = set()
    for item in items:
        if item["id"] in seen:
            raise SystemExit(f"duplicate {label} id: {item['id']}")
        seen.add(item["id"])
    return items


def build():
    base = json.loads(BASE.read_text(encoding="utf-8"))

    sources = dedupe(
        [dict(item) for item in base["sources"]]
        + [
            {"id": sid, "kind": kind, "title_fa": title, "url": url, "accessed": ACCESSED,
             "publisher": "NHS" if "nhs" in url or "nhsinform" in url else ("internal" if kind == "internal" else "clinical")}
            for sid, kind, title, url in NEW_SOURCES
        ],
        "source",
    )

    symptoms = dedupe(
        [dict(item) for item in base["symptoms"]]
        + [
            {"id": sid, "cluster": cluster, "label_fa": label, "synonyms": synonyms, "category": None}
            for sid, label, cluster, synonyms in NEW_SYMPTOMS
        ],
        "symptom",
    )

    clusters = dedupe(
        [dict(item) for item in base["symptom_clusters"]]
        + [{"id": cid, "label_fa": label} for cid, label in CLUSTERS],
        "cluster",
    )

    questions = dedupe(
        gate_questions() + head_questions() + torso_questions() + pelvis_questions()
        + limb_questions() + abdomen_questions(base),
        "question",
    )

    conditions = dedupe(new_conditions() + abdomen_conditions(base), "condition")
    paths = dedupe(new_paths() + abdomen_paths(base), "path")

    # قاعده‌های ایمنی و پوشش: بندهای آماده هم پذیرفته می‌شوند.
    def normalise(rules):
        result = []
        for rule in rules:
            copy = json.loads(json.dumps(rule, ensure_ascii=False))
            copy["when"] = [clause_or_tuple(item) for item in copy["when"]]
            result.append(copy)
        return result

    safety_rules = dedupe(normalise(new_safety()) + normalise(base["safety_rules"]), "safety rule")
    coverage_rules = normalise(new_coverage())

    return {
        "schema_version": "2.0.0",
        "bundle_id": "symptom-checker.body.v3",
        "bundle_version": "3.0.0-draft",
        "status": "draft",
        "locale": "fa-IR",
        "created": "2026-10-31",
        "migrated_from": {
            "bundle_id": base["bundle_id"],
            "bundle_version": base["bundle_version"],
            "note_fa": "خوشهٔ شکم/پهلو از فاز ۱ گرفته شده و مسیر آن با دروازهٔ ناحیه حفظ شده است.",
        },
        "migration_notes_fa": [
            "پرسش‌های شکم/پهلو همان متن و همان شاهد فاز ۱ را دارند؛ فقط ترتیب (برای جا دادن ناحیه‌ها) و دروازهٔ ناحیه عوض شده است.",
            "شرط نمایش پرسش حالا می‌تواند «همهٔ این‌ها» (all_of) باشد: ناحیه باید انتخاب شده باشد و قاعدهٔ قبلی هم برقرار باشد.",
        ],
        "scope": {
            "cluster": "کل بدن — پنج ناحیه، در نسخهٔ نمایشی",
            "first_path": "ناحیه‌ای که بازدیدکننده انتخاب می‌کند",
            "conditions_in_scope": [item["id"] for item in conditions],
            "excluded_by_design": [
                "کودکان زیر ۱۸ سال به‌عنوان پوشش فعال (می‌تواند الگوی نشانه متفاوت داشته باشد)",
                "بارداری و احتمال بارداری (خارج از پوشش این بسته؛ ارجاع به ارزیابی فوری)",
                "وضعیت پس از جراحی",
                "تشخیص و رد حملهٔ قلبی (درد قفسهٔ سینه فقط توصیهٔ فوری می‌دهد)",
                "ارزیابی فوریت‌های واقعی؛ این ابزار جای اورژانس نیست",
            ],
            "not_a_diagnosis": "این بسته موضوع‌های مرتبط را بر پایهٔ قواعد مستند پیشنهاد می‌کند؛ تشخیص، معاینه و آزمایش نیست و ادعای دقت بالینی ندارد.",
        },
        "answer_states": base["answer_states"],
        "symptom_clusters": clusters,
        "sources": sources,
        "symptoms": symptoms,
        "questions": questions,
        "conditions": conditions,
        "overrides": paths,
        "safety_rules": safety_rules,
        "coverage_rules": coverage_rules,
        "disclaimers": {
            "before_start_fa": "این ابزار تشخیص پزشکی نیست. اول سن، بعد جنسیت و ناحیهٔ بدن را می‌پرسد و بعد چند پرسش کوتاه، تا موضوع‌های مرتبط با نشانه‌هایت را نشان دهد. اگر درد شدید یا بدحالی داری، همین حالا کمک پزشکی بگیر.",
            "during_fa": "پاسخ‌ها فقط در همین صفحه و در حافظهٔ مرورگر می‌مانند و به سرور فرستاده نمی‌شوند؛ سن و جنسیت هم ذخیره نمی‌شوند.",
            "result_fa": "این‌ها موضوع‌هایی برای بررسی هستند، نه تشخیص. پزشک بر اساس معاینه تصمیم می‌گیرد چه آزمایش یا تصویربرداری لازم است.",
        },
        "change_notes": [
            {
                "version": "3.0.0-draft",
                "date": "2026-10-31",
                "changes_fa": [
                    "ابزار از ناحیهٔ بدن شروع می‌شود: جنسیت، پنج ناحیه، زیرناحیه، بعد پرسش‌ها.",
                    "ناحیهٔ لگن و اندام تناسلی کامل شد: ادرار، آلت، بیضه، فرج و واژن، مقعد (۹ موضوع).",
                    "سر و گردن، سینه و پشت، دست‌ها و پاها هر کدام مسیر و موضوع‌های مستند خودشان را گرفتند.",
                    "۲۲ قاعدهٔ ایمنی، از جمله پیچش بیضه، سندرم دم اسب، سردرد ناگهانی و لختهٔ پا.",
                ],
            }
        ],
    }


def check_bundle(bundle):
    """سنجش ساختاری + اسکیمای مخزن (اگر jsonschema نصب باشد)."""
    errors = []
    ids = {}

    for key in ("sources", "symptoms", "questions", "conditions", "overrides", "safety_rules", "coverage_rules"):
        seen = set()
        for item in bundle[key]:
            if item["id"] in seen:
                errors.append(f"{key}: duplicate id {item['id']}")
            seen.add(item["id"])
        ids[key] = seen

    question_ids = ids["questions"]
    orders = {}
    for question in bundle["questions"]:
        order = question["ask_order"]
        if order in orders:
            errors.append(f"ask_order {order} used twice: {orders[order]} and {question['id']}")
        orders[order] = question["id"]
        for ref in question["source_refs"]:
            if ref not in ids["sources"]:
                errors.append(f"{question['id']}: unknown source {ref}")
        visibility = question.get("visibility", {})
        if not (visibility.get("always") or visibility.get("any_of") or visibility.get("all_of")):
            errors.append(f"{question['id']}: no visibility")

    for item in bundle["conditions"]:
        if len(item["profile"]) < 3:
            errors.append(f"{item['id']}: fewer than 3 profile rows")
        for row_ in item["profile"]:
            if row_["symptom_id"] not in ids["symptoms"]:
                errors.append(f"{item['id']}: unknown symptom {row_['symptom_id']}")
            for ref in row_["source_refs"]:
                if ref not in ids["sources"]:
                    errors.append(f"{item['id']}: unknown source {ref}")

    def leaves(clause):
        if isinstance(clause, dict) and isinstance(clause.get("any_of"), list):
            for sub in clause["any_of"]:
                yield from leaves(sub)
            return
        if isinstance(clause, dict) and isinstance(clause.get("all_of"), list):
            for sub in clause["all_of"]:
                yield from leaves(sub)
            return
        yield clause

    for path_ in bundle["overrides"]:
        if path_["condition_id"] not in ids["conditions"]:
            errors.append(f"{path_['id']}: unknown condition {path_['condition_id']}")
        for clause in path_["when"]:
            for leaf in leaves(clause):
                if leaf.get("question_id") not in question_ids:
                    errors.append(f"{path_['id']}: unknown question {leaf.get('question_id')}")

    opened = {path_["condition_id"] for path_ in bundle["overrides"]}
    for condition in bundle["conditions"]:
        if condition["id"] not in opened:
            errors.append(f"{condition['id']}: no path opens it")

    try:
        import jsonschema  # type: ignore

        schema = json.loads(SCHEMA.read_text(encoding="utf-8"))
        jsonschema.validate(bundle, schema)
    except ImportError:
        pass
    except Exception as exc:  # noqa: BLE001 - the schema error is the message
        errors.append(f"schema: {exc}")

    return errors


def main():
    parser = argparse.ArgumentParser(description="ساخت بستهٔ دانش کل بدن (نسخهٔ ۳)")
    parser.add_argument("--check", action="store_true", help="فقط بررسی کن که فایل با محتوا هم‌خوان است")
    args = parser.parse_args()

    bundle = build()
    errors = check_bundle(bundle)
    if errors:
        print("INVALID")
        for error in errors[:40]:
            print(" -", error)
        return 1

    payload = json.dumps(bundle, ensure_ascii=False, indent=2) + "\n"
    if args.check:
        current = OUT.read_text(encoding="utf-8") if OUT.exists() else ""
        if current != payload:
            print(f"OUT OF DATE: {OUT.relative_to(ROOT)}")
            return 1
        print(f"OK {OUT.relative_to(ROOT)} is up to date")
        return 0

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(payload, encoding="utf-8")
    digest = hashlib.sha256(payload.encode("utf-8")).hexdigest()
    print(f"built {OUT.relative_to(ROOT)}")
    print(f"  questions {len(bundle['questions'])} · conditions {len(bundle['conditions'])} · "
          f"paths {len(bundle['overrides'])} · safety {len(bundle['safety_rules'])} · sources {len(bundle['sources'])}")
    print(f"  sha256 {digest}")
    return 0


if __name__ == "__main__":
    sys.exit(main())

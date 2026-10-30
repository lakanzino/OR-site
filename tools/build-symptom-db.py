#!/usr/bin/env python3
"""Build the symptom-checker data for the child theme (test space).

Input : themes/lax-theme-child/data/disease-seed.json (120 ids)
        themes/lax-theme-child/data/disease-profiles.json (profile text per id)
Output: themes/lax-theme-child/data/symptom-checker/diseases.json
        themes/lax-theme-child/data/symptom-checker/vocab.json

Symptom bullets are free Persian text. Each bullet is mapped to controlled
tags by keyword. The engine reasons only over these tags.
"""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "themes/lax-theme-child/data"
OUT = DATA / "symptom-checker"

# tag -> (Persian label, yes/no question, region group, keywords)
TAGS = {
    "fever": ("تب", "آیا تب دارید یا گرمای بدن را حس کرده‌اید؟", "general", ["تب"]),
    "fatigue": ("خستگی", "آیا خستگی یا بی‌حالی غیرعادی دارید؟", "general", ["خستگی", "بی‌حالی"]),
    "chills": ("لرز", "آیا لرز یا تکان‌های ناگهانی دارید؟", "general", ["لرز"]),
    "weight_loss": ("کاهش وزن", "آیا بدون دلیل وزنتان کم شده است؟", "general", ["کاهش وزن"]),
    "thirst": ("تشنگی زیاد", "آیا تشنگی زیاد دارید؟", "general", ["تشنگی"]),
    "sweating": ("تعریق یا عرق سرد", "آیا عرق سرد یا تعریق غیرعادی دارید؟", "general", ["عرق", "تعریق"]),
    "headache": ("سردرد", "آیا سردرد دارید؟", "head", ["سردرد"]),
    "dizziness": ("سرگیجه", "آیا سرگیجه دارید؟", "head", ["سرگیجه"]),
    "vision": ("تاری دید", "آیا دیدتان تار شده یا درد چشم دارید؟", "head", ["تاری دید", "چشم"]),
    "ear_nose": ("گوش یا بینی", "آیا درد گوش، گرفتگی یا آبریزش بینی دارید؟", "head", ["گوش", "بینی"]),
    "sore_throat": ("گلودرد", "آیا گلودرد دارید؟", "head", ["گلو"]),
    "neck_stiff": ("سفتی گردن", "آیا گردنتان سفت شده و خم کردنش سخت است؟", "head", ["سفتی گردن"]),
    "cough": ("سرفه", "آیا سرفه دارید؟", "chest", ["سرفه"]),
    "chest_pain": ("درد قفسه سینه", "آیا درد یا فشار در قفسه سینه دارید؟", "chest", ["قفسه سینه", "قفسه‌ی سینه", "درد سینه"]),
    "dyspnea": ("تنگی نفس", "آیا نفستان تنگ شده است؟", "chest", ["تنگی نفس"]),
    "palpitation": ("تپش قلب", "آیا تپش قلب دارید؟", "chest", ["تپش"]),
    "arm_jaw_pain": ("درد بازو یا فک", "آیا درد به بازو، فک یا پشت منتشر می‌شود؟", "chest", ["بازو", "فک"]),
    "abd_pain": ("درد شکم", "آیا درد شکم دارید؟", "abdomen", ["درد شکم", "شکم"]),
    "right_lower": ("درد پایین سمت راست شکم", "آیا درد بیشتر در پایین سمت راست شکم است؟", "abdomen", ["پایین سمت راست", "راست پایین"]),
    "nausea": ("تهوع", "آیا تهوع دارید؟", "abdomen", ["تهوع"]),
    "vomiting": ("استفراغ", "آیا استفراغ کرده‌اید؟", "abdomen", ["استفراغ"]),
    "diarrhea": ("اسهال", "آیا اسهال دارید؟", "abdomen", ["اسهال"]),
    "constipation": ("یبوست", "آیا یبوست دارید؟", "abdomen", ["یبوست"]),
    "heartburn": ("سوزش سر دل", "آیا سوزش سر دل دارید؟", "abdomen", ["سوزش سر دل", "سر دل"]),
    "appetite": ("بی‌اشتهایی", "آیا اشتهایتان کم شده است؟", "abdomen", ["بی‌اشتهایی"]),
    "jaundice": ("زردی پوست یا چشم", "آیا زردی پوست یا سفیدی چشم دیده‌اید؟", "abdomen", ["زردی"]),
    "back_pain": ("درد کمر", "آیا درد کمر دارید؟", "back", ["درد کمر", "کمر"]),
    "flank_pain": ("درد پهلو یا کلیه", "آیا درد پهلو یا ناحیهٔ کلیه دارید؟", "back", ["پهلو", "کلیه"]),
    "joint_pain": ("درد مفاصل", "آیا درد مفاصل یا تورم دارید؟", "back", ["مفصل"]),
    "body_ache": ("بدن‌درد", "آیا بدن‌درد یا درد عضلات دارید؟", "back", ["بدن‌درد", "بدن درد", "عضلات"]),
    "burning_urine": ("سوزش هنگام ادرار", "آیا هنگام ادرار سوزش دارید؟", "urinary", ["سوزش ادرار"]),
    "frequent_urine": ("تکرر ادرار", "آیا بیشتر از معمول ادرار می‌کنید؟", "urinary", ["تکرر"]),
    "blood_urine": ("خون در ادرار", "آیا خون در ادرار دیده‌اید؟", "urinary", ["خون در ادرار"]),
    "vaginal_bleed": ("خونریزی واژینال", "آیا خونریزی غیرعادی واژینال دارید؟", "urinary", ["خونریزی واژینال"]),
    "rash": ("جوش، خارش یا راش پوستی", "آیا جوش، خارش یا تغییر رنگ پوست دارید؟", "skin", ["خارش", "جوش", "راش", "پوست"]),
}

# Red-flag questions. Any "yes" routes to the emergency result.
RED_FLAGS = [
    {"id": "rf_chest", "question": "آیا درد قفسه سینه همراه با تعریق سرد یا تنگی نفس دارید؟", "tags_any": ["chest_pain"], "tags_all": []},
    {"id": "rf_thunder", "question": "آیا ناگهان دچار سردرد بسیار شدید شده‌اید، بدترین سردرد عمرتان؟", "tags_any": ["headache"], "tags_all": []},
    {"id": "rf_stroke", "question": "آیا ضعف ناگهانی یک طرف بدن، افتادگی صورت یا اختلال تکلم دارید؟", "tags_any": [], "tags_all": []},
    {"id": "rf_breath", "question": "آیا تنگی نفس شدید دارید و نمی‌توانید جمله‌ای کامل بگویید؟", "tags_any": ["dyspnea"], "tags_all": []},
    {"id": "rf_faint", "question": "آیا غش کرده‌اید یا گیجی ناگهانی دارید؟", "tags_any": [], "tags_all": []},
    {"id": "rf_hemoptysis", "question": "آیا خون سرفه یا خون استفراغ کرده‌اید؟", "tags_any": [], "tags_all": []},
    {"id": "rf_rigid", "question": "آیا شکمتان سفت شده و لمس آن بسیار دردناک است؟", "tags_any": ["abd_pain"], "tags_all": []},
    {"id": "rf_pregnancy", "question": "آیا خونریزی واژینال همراه با درد شکم دارید؟", "tags_any": [], "tags_all": ["vaginal_bleed", "abd_pain"]},
    {"id": "rf_meningitis", "question": "آیا سفتی گردن همراه با تب دارید؟", "tags_any": [], "tags_all": ["neck_stiff", "fever"]},
    {"id": "rf_cauda", "question": "آیا بی‌اختیاری تازه ادرار یا مدفوع، یا بی‌حسی ناحیهٔ نشیمنگاه دارید؟", "tags_any": [], "tags_all": []},
]

REGIONS = [
    {"id": "head", "name": "سر و صورت"},
    {"id": "chest", "name": "قفسه سینه و تنفس"},
    {"id": "abdomen", "name": "شکم و لگن"},
    {"id": "back", "name": "کمر و پهلو"},
    {"id": "urinary", "name": "ادرار و کلیه"},
    {"id": "skin", "name": "پوست"},
    {"id": "general", "name": "علائم عمومی"},
]


def tags_for(text: str) -> list:
    return [tag for tag, (_, _, _, kws) in TAGS.items() if any(k in text for k in kws)]


def _bullets(text: str) -> list:
    return [b.strip().lstrip("•").strip() for b in (text or "").split("\n") if b.strip().lstrip("•").strip()]


def main() -> None:
    seed = json.loads((DATA / "disease-seed.json").read_text(encoding="utf-8"))
    profiles = {p["diseaseId"]: p for p in json.loads((DATA / "disease-profiles.json").read_text(encoding="utf-8"))}
    diseases = []
    for s in seed:
        p = profiles.get(s["diseaseId"])
        if not p:
            continue
        prof = p["profile"]
        symptoms = _bullets(prof.get("symptoms", ""))
        tag_set = set()
        for b in symptoms:
            tag_set.update(tags_for(b))
        # Source names only: the profiles carry no verified URLs, so none are invented.
        sources = _bullets(prof.get("sources", "")) if isinstance(prof.get("sources"), str) else []
        diseases.append({
            "id": s["diseaseId"],
            "nameFa": prof["nameFa"],
            "nameEn": prof["nameEn"],
            "specialty": prof.get("specialty", ""),
            "symptoms": symptoms,
            "tags": sorted(tag_set),
            "whenToSeeDoctor": _bullets(prof.get("whenToSeeDoctor", "")),
            "sources": sources,
            "reviewStatus": "unreviewed",
        })
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "diseases.json").write_text(json.dumps({"version": 1, "count": len(diseases), "diseases": diseases}, ensure_ascii=False, indent=1), encoding="utf-8")
    vocab = {
        "version": 1,
        "regions": REGIONS,
        "tags": [{"id": t, "label": v[0], "question": v[1], "region": v[2]} for t, v in TAGS.items()],
        "redFlags": RED_FLAGS,
    }
    (OUT / "vocab.json").write_text(json.dumps(vocab, ensure_ascii=False, indent=1), encoding="utf-8")
    empty = sum(1 for d in diseases if not d["tags"])
    print(f"diseases={len(diseases)} empty_tag_sets={empty}")


if __name__ == "__main__":
    main()

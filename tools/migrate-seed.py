#!/usr/bin/env python3
"""One-time migration: generated checker data -> hand-editable src/ tree.

Reads  data/symptom-checker/diseases.json, vocab.json   (the 0.29.x build output)
Writes data/symptom-checker/src/tags.json
       data/symptom-checker/src/red-flags.json
       data/symptom-checker/src/diseases/<id>.json       (one file per disease)
       reports/needs-review.md

Every migrated value that is a default (not taken from the old data) is listed
in the report. All profiles are written with review.status = "draft".
Re-running overwrites src/; only run it again if you deliberately want to reset
hand edits.
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
DATA = ROOT / "themes" / "lax-theme-child" / "data" / "symptom-checker"
SRC = DATA / "src"

# Short Persian definitions (added by hand; the old vocab had labels only).
DEFINITIONS = {
    "fever": "افزایش دمای بدن (معمولاً بالای ۳۸ درجه) یا احساس گرما و لرز.",
    "fatigue": "احساس خستگی یا بی‌حالی که با استراحت بهتر نمی‌شود.",
    "chills": "لرزهای ناگهانی که معمولاً همراه تب است.",
    "weight_loss": "کاهش وزن بدون تغییر عمدی در رژیم یا فعالیت.",
    "thirst": "تشنگی بیش از حد معمول.",
    "sweating": "تعریق زیاد یا عرق سرد، بدون علت واضح.",
    "headache": "درد یا فشار در ناحیهٔ سر.",
    "dizziness": "احساس گیجی، چرخش یا سبکی سر.",
    "vision": "تغییر دید: تاری، دوبینی یا درد چشم.",
    "ear_nose": "درد گوش، گرفتگی یا آبریزش بینی.",
    "sore_throat": "درد یا سوزش گلو، به‌ویژه هنگام بلع.",
    "neck_stiff": "سفتی گردن و دشواری خم کردن آن.",
    "cough": "سرفه، با یا بدون خلط.",
    "chest_pain": "درد، فشار یا سنگینی در قفسهٔ سینه.",
    "dyspnea": "احساس تنگی یا کمبود نفس.",
    "palpitation": "احساس تپش، ضربان نامنظم یا تند قلب.",
    "arm_jaw_pain": "درد منتشرشده به بازو، فک یا پشت.",
    "abd_pain": "درد در هر ناحیهٔ شکم.",
    "right_lower": "درد بیشتر در پایین سمت راست شکم.",
    "nausea": "حالت تهوع یا احساس نیاز به استفراغ.",
    "vomiting": "استفراغ.",
    "diarrhea": "اجابت مزاج مکرر با مدفوع آبکی.",
    "constipation": "یبوست یا اجابت مزاج دشوار و کم‌دفعات.",
    "heartburn": "سوزش در ناحیهٔ پشت جناغ یا معده.",
    "appetite": "کاهش اشتها یا میل به غذا.",
    "jaundice": "زردی پوست یا سفیدی چشم.",
    "back_pain": "درد در ناحیهٔ کمر.",
    "flank_pain": "درد در پهلو یا ناحیهٔ کلیه.",
    "joint_pain": "درد یا تورم مفاصل.",
    "body_ache": "درد عمومی عضلات یا بدن.",
    "burning_urine": "سوزش یا درد هنگام ادرار.",
    "frequent_urine": "ادرار بیشتر از حد معمول، با یا بدون احساس فوریت.",
    "blood_urine": "دیده شدن خون در ادرار.",
    "vaginal_bleed": "خونریزی غیرعادی از واژن.",
    "rash": "جوش، خارش یا تغییر رنگ پوست.",
}

# Rough background frequency in the attending population (approximation).
COMMON_BACKGROUND = {"fever", "fatigue", "headache", "cough", "nausea", "dizziness",
                     "chills", "abd_pain", "body_ache", "sore_throat", "vomiting",
                     "diarrhea", "constipation", "back_pain", "joint_pain", "rash"}
RARE_BACKGROUND = {"jaundice", "blood_urine", "vaginal_bleed", "arm_jaw_pain",
                   "neck_stiff", "right_lower", "flank_pain", "weight_loss"}

# Disease-level urgency suggestions (draft; for physician review).
URGENT = {"mi", "stroke", "pulmonary_embolism", "research_021", "research_041"}
SOON = {"app", "kidney_infection", "pneumonia", "pancreatitis", "research_042"}

ALL_AGES = ["child", "teen", "adult", "elderly"]


def main():
    diseases = json.loads((DATA / "diseases.json").read_text(encoding="utf-8"))["diseases"]
    vocab = json.loads((DATA / "vocab.json").read_text(encoding="utf-8"))
    region_of = {t["id"]: t["region"] for t in vocab["tags"]}
    questions = {t["id"]: t["question"] for t in vocab["tags"]}
    labels = {t["id"]: t["label"] for t in vocab["tags"]}

    if SRC.exists():
        raise SystemExit("src/ already exists; delete it deliberately to re-run")
    (SRC / "diseases").mkdir(parents=True)

    tags = []
    for t in vocab["tags"]:
        tags.append({
            "id": t["id"],
            "label_fa": labels[t["id"]],
            "definition_fa": DEFINITIONS[t["id"]],
            "question_fa": questions[t["id"]],
            "category": t["region"],
            "background": "common" if t["id"] in COMMON_BACKGROUND
            else ("rare" if t["id"] in RARE_BACKGROUND else "usual"),
            "synonyms_fa": [],
        })
    (SRC / "tags.json").write_text(json.dumps({"tags": tags}, ensure_ascii=False, indent=2) + "\n",
                                   encoding="utf-8")

    rules = []
    for r in vocab["redFlags"]:
        rules.append({
            "id": r["id"],
            "label_fa": r["question"],
            "question_fa": r["question"],
            "message_fa": "این علائم می‌تواند اورژانسی باشد. فوراً با {{EMERGENCY}} تماس بگیرید یا به نزدیک‌ترین اورژانس بروید.",
            "sources": [],
            "review": {"status": "draft", "reviewer": "", "date": ""},
        })
    (SRC / "red-flags.json").write_text(json.dumps({"rules": rules}, ensure_ascii=False, indent=2) + "\n",
                                        encoding="utf-8")

    needs = []
    for d in diseases:
        did = d["id"]
        n_tags = len(d["tags"])
        urgency = "urgent" if did in URGENT else ("soon" if did in SOON else "routine")
        profile_tags = []
        for i, tid in enumerate(d["tags"]):
            profile_tags.append({"tag": tid, "freq": "common",
                                 "role": "core" if i < 2 else "supporting"})
        first = d["whenToSeeDoctor"][0] if d["whenToSeeDoctor"] else ""
        record = {
            "id": did,
            "name_fa": d["nameFa"],
            "name_en": d["nameEn"],
            "category": d["specialty"].split(" — ")[0],
            "prevalence": "common",
            "urgency": urgency,
            "age_groups": ALL_AGES,
            "sex": "any",
            "tags": profile_tags,
            "symptoms_fa": d["symptoms"],
            "when_to_see_doctor_fa": d["whenToSeeDoctor"],
            "summary_fa": f"{d['nameFa']} (تخصص: {d['specialty'].split(' — ')[-1]}). علائم و موارد مراجعه فوری در ادامه آمده است.",
            "next_step_fa": "اگر علائم ادامه یافت یا هر یک از موارد «مراجعهٔ فوری» را داشتید، به پزشک مراجعه کنید.",
            "sources": [{"publisher": s, "title": "", "url": "", "accessed": ""} for s in d["sources"]],
            "review": {"status": "draft", "reviewer": "", "date": ""},
        }
        (SRC / "diseases" / f"{did}.json").write_text(
            json.dumps(record, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        gaps = []
        if n_tags < 4:
            gaps.append(f"{n_tags} برچسب (حداقل ۴)")
        if not d["sources"]:
            gaps.append("منبع ندارد")
        gaps.append("منبع بدون URL")
        needs.append((did, d["nameFa"], n_tags, "; ".join(gaps), urgency))

    report = ["# نیازمند بازبینی (migrate-seed)", "",
              "همهٔ پروفایل‌ها `draft` هستند. مقادیر پیش‌فرض: freq=common، دو برچسب اول core، "
              "prevalence=common، sex=any، age=همه، urgency فقط از فهرست پیشنهادی.", "",
              "| id | نام | برچسب | مشکل‌ها | urgency پیشنهادی |", "|---|---|---|---|---|"]
    for row in needs:
        report.append(f"| {row[0]} | {row[1]} | {row[2]} | {row[3]} | {row[4]} |")
    out = ROOT / "reports" / "needs-review.md"
    out.parent.mkdir(exist_ok=True)
    out.write_text("\n".join(report) + "\n", encoding="utf-8")
    print(f"migrated {len(diseases)} diseases, {len(tags)} tags, {len(rules)} red-flag rules")


if __name__ == "__main__":
    main()

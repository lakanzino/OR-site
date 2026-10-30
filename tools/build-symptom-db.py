#!/usr/bin/env python3
"""Build the symptom-checker runtime data from the reviewed source files.

Input  (source of truth, edit these):
  themes/lax-theme-child/data/symptom-checker/src/diseases/<id>.json
  themes/lax-theme-child/data/symptom-checker/src/tags.json
  themes/lax-theme-child/data/symptom-checker/src/red-flags.json

Output (generated, do not edit by hand):
  themes/lax-theme-child/data/symptom-checker/diseases.json
  themes/lax-theme-child/data/symptom-checker/vocab.json

Usage:
  python3 tools/build-symptom-db.py                 # include draft profiles
  python3 tools/build-symptom-db.py --published-only # only review.status == "published"

Drafts are included by default so the test space can be exercised before
physician review. Each disease carries reviewStatus, and the UI must show it.
A disease with fewer than MIN_TAGS tags is marked rankable=false: the engine
will not rank it and will say the data is insufficient instead of guessing.
"""
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "themes/lax-theme-child/data/symptom-checker/src"
OUT = ROOT / "themes/lax-theme-child/data/symptom-checker"

VERSION = 5
MIN_TAGS = 3  # below this a disease cannot be ranked fairly
CORE, SUPPORTING = "core", "supporting"

# Tag category -> question-flow region (order matters for the UI).
REGIONS = [
    {"id": "head", "name": "سر و صورت"},
    {"id": "chest", "name": "قفسه سینه و تنفس"},
    {"id": "abdomen", "name": "شکم و لگن"},
    {"id": "back", "name": "کمر و پهلو"},
    {"id": "urinary", "name": "ادرار و کلیه"},
    {"id": "skin", "name": "پوست"},
    {"id": "general", "name": "علائم عمومی"},
]
REGION_IDS = {r["id"] for r in REGIONS}
AGE_GROUPS = ["child", "teen", "adult", "elderly"]
SEXES = ["any", "female", "male"]


def load(path):
    with open(path, encoding="utf8") as f:
        return json.load(f)


def write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", encoding="utf8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        f.write("\n")


def build(published_only=False):
    tag_doc = load(SRC / "tags.json")
    rf_doc = load(SRC / "red-flags.json")
    tags = tag_doc["tags"]
    tag_ids = {t["id"] for t in tags}

    vocab_tags = []
    for t in tags:
        if t["category"] not in REGION_IDS:
            raise SystemExit(f"tag {t['id']}: unknown category {t['category']}")
        vocab_tags.append({
            "id": t["id"],
            "label": t["label_fa"],
            "question": t["question_fa"],
            "definition": t["definition_fa"],
            "region": t["category"],
            "background": t["background"],
            "synonyms": t.get("synonyms_fa", []),
        })

    red_flags = []
    for r in rf_doc["rules"]:
        when = r["when"]
        used = list(when.get("all", [])) + list(when.get("any", []))
        for tid in used:
            if tid not in tag_ids:
                raise SystemExit(f"red flag {r['id']}: unknown tag {tid}")
        red_flags.append({
            "id": r["id"],
            "label": r["label_fa"],
            "question": r["question_fa"],
            "when": {"all": when.get("all", []), "any": when.get("any", [])},
            "message": r["message_fa"],
            "sources": r.get("sources", []),
            "reviewStatus": r["review"]["status"],
        })

    diseases = []
    skipped = []
    for path in sorted((SRC / "diseases").glob("*.json")):
        d = load(path)
        status = d["review"]["status"]
        if published_only and status != "published":
            skipped.append(d["id"])
            continue
        seen = set()
        tag_list, roles, freqs = [], {}, {}
        for t in d["tags"]:
            if t["tag"] not in tag_ids:
                raise SystemExit(f"{d['id']}: unknown tag {t['tag']}")
            if t["tag"] in seen:
                raise SystemExit(f"{d['id']}: duplicate tag {t['tag']}")
            seen.add(t["tag"])
            tag_list.append(t["tag"])
            roles[t["tag"]] = t["role"]
            freqs[t["tag"]] = t["freq"]
        for a in d["age_groups"]:
            if a not in AGE_GROUPS:
                raise SystemExit(f"{d['id']}: unknown age group {a}")
        if d["sex"] not in SEXES:
            raise SystemExit(f"{d['id']}: unknown sex {d['sex']}")
        diseases.append({
            "id": d["id"],
            "nameFa": d["name_fa"],
            "nameEn": d["name_en"],
            "specialty": d["category"],
            "prevalence": d["prevalence"],
            "urgency": d["urgency"],
            "ageGroups": d["age_groups"],
            "sex": d["sex"],
            "tags": tag_list,
            "tagRoles": roles,
            "tagFreq": freqs,
            "rankable": len(tag_list) >= MIN_TAGS and d.get("checker_rankable", True),
            "checkerNote": d.get("checker_note", ""),
            "symptoms": d["symptoms_fa"],
            "whenToSeeDoctor": d["when_to_see_doctor_fa"],
            "summary": d["summary_fa"],
            "nextStep": d["next_step_fa"],
            "sources": [
                {"publisher": s.get("publisher", ""), "url": s.get("url", ""),
                 "verified": bool(s.get("verified", False))}
                for s in d["sources"]
            ],
            "reviewStatus": status,
        })

    if skipped:
        print(f"skipped (not published): {len(skipped)}")

    ids = [d["id"] for d in diseases]
    if len(ids) != len(set(ids)):
        raise SystemExit("duplicate disease ids")

    vocab = {
        "version": VERSION,
        "regions": REGIONS,
        "ageGroups": AGE_GROUPS,
        "sexes": SEXES,
        "tags": vocab_tags,
        "redFlags": red_flags,
    }
    out = {
        "version": VERSION,
        "count": len(diseases),
        "rankableCount": sum(1 for d in diseases if d["rankable"]),
        "diseases": diseases,
    }
    write(OUT / "diseases.json", out)
    write(OUT / "vocab.json", vocab)
    print(f"diseases={len(diseases)} rankable={out['rankableCount']} "
          f"tags={len(vocab_tags)} redFlags={len(red_flags)}")


if __name__ == "__main__":
    build(published_only="--published-only" in sys.argv[1:])

#!/usr/bin/env python3
"""Validate the generated symptom-checker data (diseases.json + vocab.json).

Usage:
  python3 tools/validate-symptom-db.py            # errors fail, warnings report
  python3 tools/validate-symptom-db.py --strict   # warnings also fail

Schema matches tools/build-symptom-db.py output. Run it after every
build and before every release that touches the checker data.
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
DATA = ROOT / "themes" / "lax-theme-child" / "data" / "symptom-checker"

REQUIRED = ["id", "nameFa", "nameEn", "specialty", "symptoms", "tags",
            "whenToSeeDoctor", "sources", "reviewStatus"]
ID_RE = re.compile(r"^[a-z0-9_]+$")
ALLOWED_REVIEW = {"unreviewed", "reviewed"}
# Home-remedy wording must never reach the checker output (product rule).
FORBIDDEN = ["دمنوش", "روغن‌مالی", "روغن مالی", "عنبرنسارا", "عرق نعناع",
             "زنجبیل", "سرکه سیب", "حجامت", "گیاهی", "طب سنتی"]


def load(name):
    with open(DATA / name, encoding="utf-8") as fh:
        return json.load(fh)


def main(argv):
    strict = "--strict" in argv
    errors, warnings = [], []
    diseases = load("diseases.json")["diseases"]
    vocab = load("vocab.json")
    tag_ids = {t["id"] for t in vocab["tags"]}
    rf_ids = set()

    for rf in vocab["redFlags"]:
        rf_ids.add(rf["id"])
        for tag in rf.get("tags_any", []) + rf.get("tags_all", []):
            if tag not in tag_ids:
                errors.append(f"red flag {rf['id']} uses unknown tag '{tag}'")

    seen = set()
    for d in diseases:
        did = d.get("id", "<no id>")
        for field in REQUIRED:
            if field not in d:
                errors.append(f"[{did}] missing field '{field}'")
        if did in seen:
            errors.append(f"[{did}] duplicate id")
        seen.add(did)
        if not ID_RE.match(did):
            errors.append(f"[{did}] id must match ^[a-z0-9_]+$")
        if not d.get("symptoms"):
            errors.append(f"[{did}] no symptoms")
        if not d.get("whenToSeeDoctor"):
            errors.append(f"[{did}] no 'when to see a doctor' items")
        if not d.get("sources"):
            errors.append(f"[{did}] no sources")
        if d.get("reviewStatus") not in ALLOWED_REVIEW:
            errors.append(f"[{did}] reviewStatus must be one of {sorted(ALLOWED_REVIEW)}")
        tags = d.get("tags", [])
        for tag in tags:
            if tag not in tag_ids:
                errors.append(f"[{did}] unknown tag '{tag}'")
        if len(tags) < 2:
            warnings.append(f"[{did}] only {len(tags)} tag(s); matching will be weak")
        blob = json.dumps(d, ensure_ascii=False)
        for word in FORBIDDEN:
            if word in blob:
                errors.append(f"[{did}] forbidden home-remedy wording: '{word}'")

    print(f"diseases: {len(diseases)}  tags: {len(tag_ids)}  red flags: {len(rf_ids)}")
    for e in errors:
        print("ERROR  " + e)
    for w in warnings:
        print("WARN   " + w)
    failed = bool(errors) or (strict and bool(warnings))
    print(f"errors={len(errors)} warnings={len(warnings)} -> {'FAIL' if failed else 'OK'}")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

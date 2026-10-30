#!/usr/bin/env python3
"""Validate the hand-edited checker source tree: data/symptom-checker/src/.

  python3 tools/validate-symptom-db.py [--strict] [--check-urls]

Draft profiles (review.status = "draft") report content gaps as WARN, so
they never block publishing. Structural errors always fail. --strict makes
all warnings fail. Reviewed profiles must pass every content rule.

Emergency number: no ASCII or Persian-digit "115" may appear in any text
field. The number belongs in the theme settings ({{EMERGENCY}}).
"""
import argparse
import glob
import json
import os
import pathlib
import re
import sys
import urllib.request
from urllib.parse import urlparse

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT / "themes" / "lax-theme-child" / "data" / "symptom-checker" / "src"

MIN_TAGS, MIN_CORE, MAX_TAGS = 4, 2, 25
FREQ = {"always", "very_common", "common", "occasional", "rare"}
ROLES = {"core", "supporting", "red_flag"}
PREV = {"common", "uncommon", "rare"}
URG = {"routine", "soon", "urgent", "emergency"}
BG = {"common", "usual", "rare"}
AGES = {"child", "teen", "adult", "elderly"}
SEX = {"any", "female", "male"}
REGIONS = {"head", "chest", "abdomen", "back", "urinary", "skin", "general"}
REQUIRED = ["id", "name_fa", "name_en", "category", "prevalence", "urgency", "age_groups",
            "sex", "tags", "symptoms_fa", "when_to_see_doctor_fa", "summary_fa", "next_step_fa",
            "sources", "review"]
ALLOWED_DOMAINS = {"nhs.uk", "heart.org", "stroke.org", "aafp.org", "cdc.gov", "who.int",
                   "nih.gov", "nlm.nih.gov", "medlineplus.gov", "mayoclinic.org", "nice.org.uk",
                   "acog.org", "diabetes.org", "thoracic.org", "cochrane.org"}
ID_RE = re.compile(r"^[a-z0-9_]+$")
PERSIAN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
BANNED = [r"درمان خانگی", r"دمنوش", r"گیاه(?:ان)? دارویی", r"داروی گیاهی", r"طب سنتی",
          r"عرقیات", r"عسل", r"سرکه", r"آب نمک", r"غرغره", r"(?<!\w)بخور(?!\w)", r"کمپرس",
          r"ماساژ", r"خودتان را درمان", r"home remed", r"herbal",
          r"\d+\s*(?:میلی ?گرم|mg|قرص)"]
BANNED_RE = [re.compile(p) for p in BANNED]
EMERGENCY_NUM = re.compile(r"(?<!\d)115(?!\d)")

errors, warns = [], []


def add(ctx, msg, soft):
    (warns if soft else errors).append(f"{ctx}: {msg}")


def norm(s):
    return s.replace("\u200c", " ").replace("\u200f", "").replace("ي", "ی").replace("ك", "ک").lower()


def strings(obj, skip=("sources", "name_en", "id")):
    if isinstance(obj, str):
        yield obj
    elif isinstance(obj, dict):
        for k, v in obj.items():
            if k not in skip:
                yield from strings(v, skip)
    elif isinstance(obj, list):
        for v in obj:
            yield from strings(v, skip)


def check_text(ctx, obj):
    for s in strings(obj):
        n = norm(s).translate(PERSIAN_DIGITS)
        for rx in BANNED_RE:
            if rx.search(n):
                add(ctx, f"عبارت ممنوع (درمان خانگی/دوز): «{s[:50]}»", False)
        if EMERGENCY_NUM.search(n):
            add(ctx, "شمارهٔ اورژانس ثابت در متن؛ از {{EMERGENCY}} استفاده کن", False)


def check_sources(ctx, sources, soft, check_urls):
    if not isinstance(sources, list) or not sources:
        return add(ctx, "حداقل یک منبع لازم است", soft)
    for i, s in enumerate(sources):
        if not s.get("publisher"):
            add(ctx, f"sources[{i}].publisher خالی است", soft)
        url = s.get("url", "")
        if not url:
            add(ctx, f"sources[{i}].url خالی است (بدون URL قابل بازبینی نیست)", soft)
            continue
        u = urlparse(url)
        if u.scheme != "https" or not u.netloc:
            add(ctx, f"sources[{i}].url باید https باشد", soft)
            continue
        host = u.netloc.lower().removeprefix("www.")
        if not any(host == d or host.endswith("." + d) for d in ALLOWED_DOMAINS):
            add(ctx, f"دامنهٔ منبع در فهرست مجاز نیست: {host}", soft)
        if check_urls:
            try:
                req = urllib.request.Request(url, method="HEAD", headers={"User-Agent": "Mozilla/5.0"})
                urllib.request.urlopen(req, timeout=10)
            except Exception as exc:  # network errors are reported, not fatal
                add(ctx, f"URL پاسخ نداد ({exc})", True)


def load(path):
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def run(strict=False, check_urls=False):
    errors.clear()
    warns.clear()
    tags = {}
    for t in load(SRC / "tags.json")["tags"]:
        tid = t.get("id", "")
        c = f"tag:{tid}"
        if not ID_RE.match(tid):
            add(c, "id نامعتبر", False)
        if tid in tags:
            add(c, "id تکراری", False)
        if len(t.get("definition_fa", "")) < 15:
            add(c, "تعریف کوتاه یا خالی", False)
        if not t.get("label_fa"):
            add(c, "label_fa خالی", False)
        if not t.get("question_fa"):
            add(c, "question_fa خالی", False)
        if t.get("background") not in BG:
            add(c, "background نامعتبر", False)
        if t.get("category") not in REGIONS:
            add(c, "category ناشناخته", False)
        tags[tid] = t
    labels = [norm(t["label_fa"]) for t in tags.values() if t.get("label_fa")]
    for dup in {x for x in labels if labels.count(x) > 1}:
        add("tags", f"label تکراری: {dup}", False)

    used = set()
    ids = set()
    disease_paths = sorted(glob.glob(str(SRC / "diseases" / "*.json")))
    for p in disease_paths:
        d = load(p)
        did = d.get("id", "?")
        c = f"disease:{did}"
        status = d.get("review", {}).get("status", "draft")
        soft = status == "draft" and not strict
        if os.path.basename(p) != f"{did}.json":
            add(c, "نام فایل با id یکی نیست", False)
        if not ID_RE.match(did):
            add(c, "id نامعتبر (فقط حروف کوچک، عدد و _)", False)
        if did in ids:
            add(c, "id تکراری", False)
        ids.add(did)
        for k in REQUIRED:
            if k not in d or d[k] in ("", [], None):
                add(c, f"فیلد خالی یا ناموجود: {k}", soft)
        if d.get("prevalence") not in PREV:
            add(c, "prevalence نامعتبر", soft)
        if d.get("urgency") not in URG:
            add(c, "urgency نامعتبر", soft)
        if any(a not in AGES for a in d.get("age_groups", [])):
            add(c, "age_groups نامعتبر", False)
        if d.get("sex") not in SEX:
            add(c, "sex نامعتبر", False)
        tl = d.get("tags", [])
        if len(tl) < MIN_TAGS:
            add(c, f"حداقل {MIN_TAGS} برچسب (فعلی: {len(tl)})", soft)
        if len(tl) > MAX_TAGS:
            add(c, f"بیش از {MAX_TAGS} برچسب", False)
        if sum(1 for t in tl if t.get("role") == "core") < MIN_CORE:
            add(c, f"حداقل {MIN_CORE} برچسب core (فعلی: {sum(1 for t in tl if t.get('role') == 'core')})", soft)
        seen = set()
        for t in tl:
            tid = t.get("tag")
            if tid not in tags:
                add(c, f"برچسب ناشناخته: {tid}", False)
            if tid in seen:
                add(c, f"برچسب تکراری: {tid}", False)
            seen.add(tid)
            used.add(tid)
            if t.get("freq") not in FREQ:
                add(c, f"freq نامعتبر برای {tid}", False)
            if t.get("role") not in ROLES:
                add(c, f"role نامعتبر برای {tid}", False)
        if d.get("urgency") in ("urgent", "emergency") and not any(
                t.get("role") == "red_flag" for t in tl):
            add(c, "بیماری فوری باید حداقل یک برچسب red_flag داشته باشد", soft)
        check_sources(c, d.get("sources"), soft, check_urls)
        check_text(c, d)
        review = d.get("review", {})
        if status == "reviewed" and not (review.get("reviewer") and review.get("date")):
            add(c, "reviewed نیازمند reviewer و date است", False)

    rules = load(SRC / "red-flags.json")["rules"]
    for r in rules:
        c = f"redflag:{r.get('id')}"
        if not r.get("label_fa"):
            add(c, "label_fa خالی", False)
        if "{{EMERGENCY}}" not in r.get("message_fa", ""):
            add(c, "message_fa باید {{EMERGENCY}} داشته باشد", False)
        when = r.get("when", {})
        if not (when.get("all") or when.get("any")):
            add(c, "when خالی است", False)
        for grp in ("all", "any"):
            for tid in when.get(grp, []):
                if tid not in tags:
                    add(c, f"برچسب ناشناخته: {tid}", False)
                used.add(tid)
        check_sources(c, r.get("sources", []), True, check_urls)
        check_text(c, r)

    unused = sorted(set(tags) - used)
    for tid in unused:
        warns.append(f"tag:{tid}: در هیچ بیماری یا قاعدهٔ خطر استفاده نشده")

    for w in warns:
        print("WARN ", w)
    for e in errors:
        print("ERROR", e)
    print(f"\n{len(ids)} بیماری، {len(tags)} برچسب، {len(rules)} قاعدهٔ خطر فوری | "
          f"{len(errors)} خطا، {len(warns)} هشدار")
    failed = bool(errors) or (strict and bool(warns))
    return (not failed), errors, warns


def main(argv):
    ap = argparse.ArgumentParser()
    ap.add_argument("--strict", action="store_true")
    ap.add_argument("--check-urls", action="store_true")
    a = ap.parse_args(argv)
    ok, _, _ = run(strict=a.strict, check_urls=a.check_urls)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

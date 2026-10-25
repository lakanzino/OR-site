#!/usr/bin/env python3
"""Content gate: SEO floor + AI-writing tells + YMYL safety for article packages.

Usage:
    python3 tools/content-lint.py content/drafts/*/        # check packages
    python3 tools/content-lint.py --live                    # only the live allowlist

Exit code 1 if any article has an error. Warnings do not fail the build.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

# --- URLs that exist on the live site or inside the current batch -------------
LIVE = {
    "/patient-guide/glossary-surgical-terms/", "/patient-guide/what-is-operating-room/",
    "/patient-guide/surgical-care-team/", "/patient-guide/patient-journey/",
    "/patient-guide/operating-room-moment-by-moment/", "/patient-guide/general-anesthesia/",
    "/patient-guide/preoperative-fasting/", "/patient-guide/pacu-recovery/",
    "/patient-guide/fear-of-anesthesia-awareness/",
    "/patient-guide/informed-consent-anesthesia-consult/",
    "/patient-guide/general-vs-spinal-anesthesia/", "/patient-guide/preoperative-tests/",
    "/patient-guide/what-not-to-eat-before-surgery/", "/patient-guide/post-surgery-pain/",
    "/patient-guide/suture-removal/", "/patient-guide/surgical-site-infection/",
    "/patient-guide/post-surgery-nutrition/", "/patient-guide/early-walking-after-surgery/",
    "/patient-guide/companion-role-admission-day/", "/patient-guide/medications-before-surgery/",
    "/patient-guide/mental-preparation-before-surgery/", "/patient-guide/anesthesia-consultation/",
    "/patient-guide/anesthesia-side-effects/", "/patient-guide/warning-signs-after-surgery/",
    "/or-environment/surgical-team-roles/",
    "/surgery/appendectomy-preparation/", "/surgery/appendectomy-recovery/",
    "/surgery/cesarean-overview/", "/surgery/cesarean-preparation/",
    "/surgery/cesarean-recovery/", "/surgery/hernia-overview/",
}
BATCH = {
    "/surgery/appendectomy-overview/", "/surgery/hernia-preparation/", "/surgery/hernia-recovery/",
    "/surgery/laparoscopy-overview/", "/surgery/laparoscopy-preparation/", "/surgery/laparoscopy-recovery/",
    "/patient-guide/children-and-anesthesia/", "/patient-guide/elderly-and-surgery/",
    "/patient-guide/emergency-vs-elective-surgery/", "/patient-guide/outpatient-vs-inpatient-surgery/",
    "/patient-guide/intubation-explained/", "/patient-guide/anesthesia-and-memory/",
    "/patient-guide/home-wound-care/", "/patient-guide/dvt-pulmonary-embolism-prevention/",
    "/patient-guide/discharge-checklist/",
    "/surgery/cataract-overview/", "/surgery/cataract-preparation/",
    "/surgery/cataract-recovery/", "/surgery/cholecystectomy-overview/",
    "/patient-guide/general-preparation-before-surgery/", "/patient-guide/questions-before-surgery/",
    "/patient-guide/medical-history-disclosure/", "/patient-guide/smoking-and-surgery/",
    "/patient-guide/diabetes-and-surgery/", "/patient-guide/obesity-and-anesthesia/",
    "/surgery/cholecystectomy-preparation/", "/surgery/cholecystectomy-recovery/",
    "/surgery/tonsillectomy-overview/", "/surgery/tonsillectomy-preparation/",
    "/surgery/tonsillectomy-recovery/",
}
ALLOWED = LIVE | BATCH

# --- phrases that make Persian copy read like machine output ------------------
AI_TELLS = [
    "در دنیای امروز", "در دنیای پیچیده امروز", "شایان ذکر است", "لازم به ذکر است",
    "قابل توجه است که", "ناگفته نماند", "بدیهی است که", "در واقع", "به عبارت دیگر",
    "از سوی دیگر", "در نهایت باید گفت", "به طور کلی می‌توان گفت", "لازم است بدانید",
    "بسیار مهم است که", "نکته حائز اهمیت", "همه چیز درباره", "راهنمای جامع و کامل",
    "همانطور که می‌دانید", "همان‌طور که می‌دانید", "در این مقاله سعی کرده‌ایم",
    "امیدواریم این مقاله", "با ما همراه باشید", "شگفت‌زده خواهید شد", "رازهایی که",
    "کلید موفقیت", "سفری هیجان‌انگیز",
]
# --- YMYL guard rails: numbers we never publish -----------------------------
FORBIDDEN = [
    (r"\d+\s*(?:میلی‌گرم|میلی گرم|mg\b)", "دوز دارو"),
    (r"\d+\s*(?:ساعت|ساعت‌)\s*(?:ناشتا|قبل از عمل|پیش از عمل)", "ساعت ناشتایی"),
    (r"(?:ناشتا|ناشتایی)[^.]{0,25}\d+\s*ساعت", "ساعت ناشتایی"),
    (r"\d+\s*(?:درصد|٪)", "درصد آماری"),
    (r"(?:امکان‌پذیر|موفقیت)[^.]{0,15}\d+%", "درصد موفقیت"),
]
BRANDS = ["استامینوفن", "ایبوپروفن", "دیکلوفناک", "سلکوکسیب", "ترامادول", "مورفین", "پتیدین",
          "متوکلوپرامید", "اندانسترون", "سفازولین", "سفتریاکسون", "هپارین", "انوکساپارین",
          "وارفارین", "آسپرین", "پروپوفول", "سوکسینیل‌کولین", "آتراکوریوم", "میدازولام",
          "فنتانیل", "رمی‌فنتانیل", "کتفولاک", "اپی‌دورال", "بوپیواکائین", "لیدوکائین"]
# ordinary Persian prepositions / adverbs: repeating these is not a tell
START_WORDS = {
    "در", "با", "برای", "به", "از", "که", "این", "آن", "تا", "بر", "اگر", "وقتی",
    "هنگام", "روز", "شب", "پس", "قبل", "بعد", "هم", "نه", "بله", "او", "شما", "ما",
    "معمولاً", "اغلب", "بیشتر", "کمتر", "بعضی", "هر", "چه", "چرا", "کدام", "چگونه",
    "آیا", "بسیاری", "چند", "تنها", "حتی", "شاید", "خود", "یکی", "دو", "سه", "اما",
}

# --- mandatory blocks -------------------------------------------------------
REQUIRED = [
    ("سلب مسئولیت پزشکی", r"سلب\s*مسئولیت"),
    ("شماره اورژانس ۱۱۵", r"۱۱۵"),
    ("منابع", r"##?\s*|منابع و چارچوب مرجع|<h2>منابع"),
    ("پرسش‌های متداول", r"پرسش‌های متداول"),
]


def words(html: str) -> int:
    text = re.sub(r"<[^>]+>", " ", html)
    text = re.sub(r"&[a-zA-Z]+;", " ", text)
    return len([w for w in re.split(r"\s+", text) if w.strip()])


def lint(pkg: Path) -> tuple[list[str], list[str]]:
    errs: list[str] = []
    warns: list[str] = []
    man_path, html_path = pkg / "article.json", pkg / "content.html"
    if not man_path.exists() or not html_path.exists():
        return [f"missing article.json or content.html"], warns

    man = json.loads(man_path.read_text(encoding="utf-8"))
    html = html_path.read_text(encoding="utf-8")
    title = man.get("title", "")
    meta = man.get("seo", {}).get("meta_description") or man.get("excerpt", "")
    seo = man.get("seo", {})
    focus = seo.get("focus_keyword", "")
    body = words(html)

    # ---- SEO floor
    if not 20 <= len(title) <= 65:
        errs.append(f"title length {len(title)} (want 20-65)")
    if not 120 <= len(meta) <= 165:
        errs.append(f"meta length {len(meta)} (want 120-165)")
    if body < 1000:
        errs.append(f"body {body} words (want >= 1000)")
    if body > 1600:
        warns.append(f"body {body} words (pillar range? consider trimming)")

    h1 = len(re.findall(r"<h1\b", html, re.I))
    if h1:
        errs.append("content.html must not contain H1 (title comes from the manifest)")
    h2 = len(re.findall(r"<h2\b", html, re.I))
    if h2 < 6:
        errs.append(f"{h2} H2 sections (want >= 6)")

    links = re.findall(r'href="([^"]+)"', html)
    internal = [l for l in links if l.startswith("/")]
    external = [l for l in links if l.startswith("http")]
    if not 3 <= len(internal) <= 7:
        errs.append(f"{len(internal)} internal links (want 3-7)")
    dead = [l for l in internal if l not in ALLOWED]
    if dead:
        errs.append("link target not live and not in this batch: " + ", ".join(sorted(set(dead))))
    if len(external) < 2:
        errs.append(f"{len(external)} source links (want >= 2)")

    faq = man.get("faq") or []
    if len(faq) < 3:
        errs.append(f"{len(faq)} FAQ entries (want >= 3)")
    for item in faq:
        if not item.get("q") or not item.get("a"):
            errs.append("FAQ entry with empty q or a")

    if not focus:
        errs.append("seo.focus_keyword is empty")
    elif focus not in title and focus not in meta:
        warns.append(f"focus keyword «{focus}» missing from title and meta")
    if not seo.get("search_intent"):
        errs.append("seo.search_intent is empty")
    if len(seo.get("keywords") or []) < 3:
        errs.append("seo.keywords needs at least 3 entries")

    alt = (man.get("featured_image") or {}).get("alt", "")
    if len(alt) < 40:
        errs.append(f"image alt too short ({len(alt)} chars, want >= 40)")
    if alt.strip() == title.strip():
        errs.append("image alt is a copy of the title")
    img = pkg / "featured.webp"
    if not img.exists():
        errs.append("featured.webp missing")
    else:
        kb = img.stat().st_size // 1024
        if kb > 180:
            errs.append(f"featured image {kb} KB (max 180)")

    # ---- AI tells
    for phrase in AI_TELLS:
        if phrase in html:
            errs.append(f"AI tell: «{phrase}»")
    # sources are allowed to use dashes as citation separators
    prose = re.split(r"<h2>\s*منابع", html)[0]
    dashes = prose.count("—")
    if dashes > 6:
        warns.append(f"{dashes} em-dashes in prose (reads machine-made; keep <= 6)")
    # repeated discourse connectives, not ordinary Persian prepositions
    starts = [m for m in re.findall(r"<p>([^\s<،]{2,14})", html)
              if m not in START_WORDS]
    if starts:
        top = max(set(starts), key=starts.count)
        if starts.count(top) > 3:
            warns.append(f"{starts.count(top)} paragraphs open with «{top}»")

    # ---- YMYL
    for pattern, label in FORBIDDEN:
        for m in re.finditer(pattern, html):
            errs.append(f"forbidden number ({label}): «{m.group(0)}»")
    for brand in BRANDS:
        if brand in html:
            errs.append(f"brand name in patient copy: «{brand}»")
    for label, pattern in REQUIRED:
        if not re.search(pattern, html):
            errs.append(f"missing required block: {label}")

    # ---- package sanity
    if man.get("post_status") != "draft":
        warns.append(f"post_status is «{man.get('post_status')}» (gate expects draft)")
    if man.get("word_count") and abs(int(man["word_count"]) - body) > 15:
        warns.append(f"manifest word_count {man['word_count']} vs actual {body}")
    return errs, warns


def main() -> int:
    targets = [Path(p) for p in sys.argv[1:]] or sorted(Path("content/drafts").iterdir())
    failed = 0
    for pkg in targets:
        if not pkg.is_dir():
            continue
        errs, warns = lint(pkg)
        status = "FAIL" if errs else ("warn" if warns else "ok  ")
        print(f"[{status}] {pkg.name}")
        for e in errs:
            print(f"        ERR  {e}")
        for w in warns:
            print(f"        WARN {w}")
        failed += 1 if errs else 0
    print(f"\n{len(targets)} packages · {failed} failing")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())

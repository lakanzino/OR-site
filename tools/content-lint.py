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
    "/or-environment/surgical-team-roles/", "/patient-guide/anesthesia-and-memory/",
    "/patient-guide/anesthesia-consultation/", "/patient-guide/anesthesia-side-effects/",
    "/patient-guide/children-and-anesthesia/", "/patient-guide/companion-role-admission-day/",
    "/patient-guide/diabetes-and-surgery/", "/patient-guide/discharge-checklist/",
    "/patient-guide/dvt-pulmonary-embolism-prevention/", "/patient-guide/early-walking-after-surgery/",
    "/patient-guide/elderly-and-surgery/", "/patient-guide/emergency-vs-elective-surgery/",
    "/patient-guide/fear-of-anesthesia-awareness/", "/patient-guide/general-anesthesia/",
    "/patient-guide/general-preparation-before-surgery/", "/patient-guide/general-vs-spinal-anesthesia/",
    "/patient-guide/glossary-surgical-terms/", "/patient-guide/home-wound-care/",
    "/patient-guide/informed-consent-anesthesia-consult/", "/patient-guide/intubation-explained/",
    "/patient-guide/medical-history-disclosure/", "/patient-guide/medications-before-surgery/",
    "/patient-guide/mental-preparation-before-surgery/", "/patient-guide/obesity-and-anesthesia/",
    "/patient-guide/operating-room-moment-by-moment/", "/patient-guide/outpatient-vs-inpatient-surgery/",
    "/patient-guide/pacu-recovery/", "/patient-guide/patient-journey/",
    "/patient-guide/post-surgery-nutrition/", "/patient-guide/post-surgery-pain/",
    "/patient-guide/preoperative-fasting/", "/patient-guide/preoperative-tests/",
    "/patient-guide/questions-before-surgery/", "/patient-guide/smoking-and-surgery/",
    "/patient-guide/surgical-care-team/", "/patient-guide/surgical-site-infection/",
    "/patient-guide/suture-removal/", "/patient-guide/warning-signs-after-surgery/",
    "/patient-guide/what-is-operating-room/", "/patient-guide/what-not-to-eat-before-surgery/",
    "/surgery/appendectomy-overview/", "/surgery/appendectomy-preparation/",
    "/surgery/appendectomy-recovery/", "/surgery/cataract-overview/",
    "/surgery/cataract-preparation/", "/surgery/cataract-recovery/",
    "/surgery/cesarean-overview/", "/surgery/cesarean-preparation/",
    "/surgery/cesarean-recovery/", "/surgery/cholecystectomy-overview/",
    "/surgery/cholecystectomy-preparation/", "/surgery/cholecystectomy-recovery/",
    "/surgery/circumcision-overview/", "/surgery/circumcision-preparation/",
    "/surgery/circumcision-recovery/", "/surgery/hemorrhoid-overview/",
    "/surgery/hemorrhoid-preparation/", "/surgery/hemorrhoid-recovery/",
    "/surgery/hernia-overview/", "/surgery/hernia-preparation/",
    "/surgery/hernia-recovery/", "/surgery/kidney-stone-overview/",
    "/surgery/kidney-stone-preparation/", "/surgery/kidney-stone-recovery/",
    "/surgery/laparoscopy-overview/", "/surgery/laparoscopy-preparation/",
    "/surgery/laparoscopy-recovery/", "/surgery/thyroidectomy-overview/",
    "/surgery/thyroidectomy-preparation/", "/surgery/thyroidectomy-recovery/",
    "/surgery/tonsillectomy-overview/", "/surgery/tonsillectomy-preparation/",
    "/surgery/tonsillectomy-recovery/",
}
# All 44 article packages were live as of 2026-10-25 (64 + batch-04's 9 = 73); BATCH kept as an empty set for future batches.
BATCH = set()
ALLOWED = LIVE | BATCH

# --- phrases that make Persian copy read like machine output ------------------
AI_TELLS = [
    "در دنیای امروز", "در دنیای پیچیده امروز", "شایان ذکر است", "لازم به ذکر است",
    "قابل توجه است که", "ناگفته نماند", "بدیهی است که", "در واقع", "به عبارت دیگر",
    "از سوی دیگر", "در نهایت باید گفت", "به طور کلی می‌توان گفت", "لازم است بدانید",
    "بسیار مهم است که", "نکته حائز اهمیت", "همه چیز درباره", "راهنمای جامع و کامل",
    "همانطور که می‌دانید", "همان‌طور که می‌دانید", "در این مقاله سعی کرده‌ایم",
    "امیدواریم این مقاله", "با ما همراه باشید", "شگفت‌زده خواهید شد", "رازهایی که",
    "کلید موفقیت", "سفری هیجان‌انگیز", "این منابع چارچوب آموزشی", "دسترسی به آن‌ها در",
    "بررسی شده است", "در این بخش خواهیم", "به شما کمک می‌کند تا", "لازم به تأکید است",
]

# Boilerplate that is legally required and therefore exempt from the
# duplicate-sentence check below (it is meant to read the same everywhere).
ALLOWED_BOILERPLATE = (
    "سلب مسئولیت پزشکی",
    "این مقاله صرفاً جنبه‌ی آموزش و اطلاع‌رسانی دارد",
    "دستور پزشک و پروتکل مرکز درمانی شما بر هر متن عمومی مقدم است",
)

# A sentence that repeats across many packages reads machine-made and turns
# the whole batch into near-duplicate content. This is the check that would
# have caught the "دسترسی … بررسی شده است" line that shipped in every article.
DUP_MIN_PACKAGES = 6
DUP_MIN_CHARS = 45
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

    # ---- inline infographics (T-32): alt, shipped file and Persian caption
    figure_blocks = re.findall(r"<figure\b.*?</figure>", html, re.S | re.I)
    inline_imgs = re.findall(r"<img\b[^>]*>", html, re.I)
    for tag in inline_imgs:
        alt_m = re.search(r"""alt\s*=\s*['"]([^'"]*)['"]""", tag, re.I)
        if not alt_m or not alt_m.group(1).strip():
            errs.append("inline <img> without non-empty alt text")
        src_m = re.search(r"""src\s*=\s*['"]([^'"]+)['"]""", tag, re.I)
        if src_m:
            src = src_m.group(1)
            if re.match(r"^(https?:|data:|//)", src, re.I):
                warns.append(f"inline img uses non-local src (portability risk): {src}")
            else:
                rel = re.sub(r"^(?:\./)+", "", src).split("?")[0].split("#")[0].lstrip("/")
                if not (pkg / rel).is_file():
                    errs.append(f"inline img file missing in package: {src}")
    for fig in figure_blocks:
        cap = re.search(r"<figcaption\b[^>]*>(.*?)</figcaption>", fig, re.S | re.I)
        if not cap or not cap.group(1).strip():
            errs.append("<figure> without non-empty <figcaption>")
        elif not re.search(r"[\u0600-\u06FF]", cap.group(1)):  # Arabic block (covers Persian letters)
            errs.append("figure <figcaption> must contain Persian text")
    if inline_imgs and len(inline_imgs) != len(figure_blocks):
        errs.append(
            f"{len(inline_imgs)} inline <img> but {len(figure_blocks)} <figure> "
            "blocks (each infographic must be wrapped in a figure)"
        )

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


def sentences_of(pkg: Path) -> set[str]:
    """Normalised sentences of one package, for boilerplate detection."""
    f = pkg / "content.html"
    if not f.exists():
        return set()
    txt = re.sub(r"<[^>]+>", " ", f.read_text(encoding="utf-8"))
    txt = re.sub(r"\s+", " ", txt)
    out = set()
    for sent in re.split(r"(?<=[.!؟])\s+", txt):
        sent = sent.strip()
        if len(sent) >= DUP_MIN_CHARS and not any(a in sent for a in ALLOWED_BOILERPLATE):
            out.add(sent)
    return out


def duplicated_sentences(targets: list[Path]) -> dict[Path, list[str]]:
    """Map each package to the sentences it shares with too many others."""
    per_pkg = {p: sentences_of(p) for p in targets if p.is_dir()}
    counts: dict[str, int] = {}
    for sents in per_pkg.values():
        for sent in sents:
            counts[sent] = counts.get(sent, 0) + 1
    shared = {s for s, n in counts.items() if n >= DUP_MIN_PACKAGES}
    return {p: sorted(sents & shared) for p, sents in per_pkg.items() if sents & shared}


def main() -> int:
    targets = [Path(p) for p in sys.argv[1:]] or sorted(Path("content/drafts").iterdir())
    dupes = duplicated_sentences(targets)
    failed = 0
    for pkg in targets:
        if not pkg.is_dir():
            continue
        errs, warns = lint(pkg)
        for sent in dupes.get(pkg, []):
            errs.append(f"boilerplate shared with {DUP_MIN_PACKAGES}+ packages: «{sent[:70]}…»")
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

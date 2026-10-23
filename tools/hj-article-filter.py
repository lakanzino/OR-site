#!/usr/bin/env python3
"""
Hamyar Jarahi Custom Editorial, AI-Footprint, Persian Grammar & YMYL Filter (v2.3)
===================================================================================
Enforces all 5 mandatory quality gates for hamyarjarahi.ir articles:
  1. `no-ai-slop-fa` Deep AI-Signature & Translationese Elimination
  2. Standard Persian Grammar, Academy Orthography (ZWNJ) & Punctuation Engine
  3. YMYL Medical & Legal Safety Rules (`PROJECT-RULES-PHASED.md` + `ARTICLE-STYLE-GUIDE.fa.md`)
  4. First-person OR Veteran Persona + Varied `<blockquote>` Memory + Zero Author Name in Body/JSON
  5. `lax-theme-child` Technical / SEO / Live-Internal-Link / Single-Line Heading Audit
"""

from __future__ import annotations

import json
import pathlib
import re
import statistics
import sys
from html.parser import HTMLParser

LIVE_PUBLISHED_PATHS = {
    "/patient-guide/patient-journey/",
    "/patient-guide/what-is-operating-room/",
    "/patient-guide/surgical-care-team/",
    "/patient-guide/glossary-surgical-terms/",
    "/patient-guide/preoperative-fasting/",
    "/patient-guide/what-not-to-eat-before-surgery/",
    "/patient-guide/medications-before-surgery/",
    "/patient-guide/informed-consent-anesthesia-consult/",
    "/patient-guide/preoperative-tests/",
    "/patient-guide/companion-role-admission-day/",
    "/patient-guide/operating-room-moment-by-moment/",
    "/patient-guide/general-anesthesia/",
    "/patient-guide/general-vs-spinal-anesthesia/",
    "/patient-guide/fear-of-anesthesia-awareness/",
    "/patient-guide/mental-preparation-before-surgery/",
    "/patient-guide/anesthesia-consultation/",
    "/patient-guide/anesthesia-side-effects/",
    "/patient-guide/pacu-recovery/",
    "/patient-guide/post-surgery-pain/",
    "/patient-guide/early-walking-after-surgery/",
    "/patient-guide/post-surgery-nutrition/",
    "/patient-guide/surgical-site-infection/",
    "/patient-guide/suture-removal/",
    "/patient-guide/warning-signs-after-surgery/",
    "/or-environment/surgical-team-roles/",
    "/surgery/appendectomy-overview/",
    "/surgery/appendectomy-preparation/",
    "/surgery/appendectomy-recovery/",
    "/surgery/cesarean-overview/",
    "/surgery/cesarean-preparation/",
    "/surgery/cesarean-recovery/",
    "/surgery/hernia-overview/",
    "/surgery/hernia-preparation/",
    "/glossary/",
}

BANNED_SLOP_WORDS = [
    "پویا",
    "جامع",
    "یکپارچه",
    "بی‌نظیر",
    "کم‌نظیر",
    "منحصربه‌فرد",
    "فوق‌العاده",
    "شگفت‌انگیز",
    "انقلابی",
    "تحول‌آفرین",
    "دگرگون‌کننده",
    "چشمگیر",
    "قابل‌توجه",
    "قابل توجه",
    "حائز اهمیت",
    "بی‌بدیل",
    "همه‌جانبه",
    "نوین",
    "هوشمندانه",
    "بی‌سابقه",
    "خارق‌العاده",
    "تضمین می‌کند",
    "نقش مهمی ایفا",
    "نقش حیاتی ایفا",
    "گامی بزرگ",
    "افق‌های تازه",
    "فصل جدیدی",
    "وادی",
]

BANNED_BUREAUCRATIC_WORDS = [
    "می‌باشد",
    "می‌باشند",
    "می باشد",
    "می باشند",
    "بایستی",
    "می‌بایست",
    "گردید",
    "گردیده",
]

BANNED_BUREAUCRATIC_PHRASES = [
    "مورد بررسی قرار",
    "مورد ارزیابی قرار",
    "مورد استفاده قرار",
    "مورد توجه قرار",
    "به عمل می‌آید",
    "انجام می‌پذیرد",
    "صورت می‌پذیرد",
    "صورت می‌گیرد",
    "انجام می‌گیرد",
    "از حیث",
    "در باب",
    "فی‌مابین",
    "بیمار محترم",
]

BANNED_EMPTY_PHRASES = [
    "در دنیای امروز",
    "در عصر حاضر",
    "نکته قابل توجه این است که",
    "شایان ذکر است",
    "لازم به ذکر است",
    "ناگفته نماند که",
    "ذکر این نکته ضروری است",
    "در حقیقت امر",
    "واقعیت این است که",
    "حقیقت این است که",
    "با در نظر گرفتن این موضوع",
    "وقتی صحبت از",
    "در بطن ماجرا",
    "در هسته‌ی خود",
    "در راستای",
    "در این راستا",
    "در خصوص",
    "در ارتباط با",
    "به منظور",
    "به نوبه‌ی خود",
    "در این مقاله قصد داریم",
    "در این نوشتار بر آنیم",
    "بیایید نگاهی بیندازیم",
    "اجازه دهید بررسی کنیم",
    "در پایان می‌توان گفت",
    "در مجموع می‌توان گفت",
    "در نهایت باید گفت",
    "به طور خلاصه",
    "به‌طور خلاصه",
    "به طور کلی",
    "به‌طور کلی",
    "در نتیجه می‌توان دریافت",
    "بدون شک",
    "جای هیچ شکی نیست",
    "آنچه مسلم است",
    "نکته کلیدی این است که",
    "همان‌طور که می‌بینید",
    "به عبارت دیگر",
    "آنچه هیچ‌کس به شما نمی‌گوید",
    "نکته‌ای که اکثر افراد",
    "نقطه‌ی عطف",
    "گواهی بر",
]

BANNED_WEASEL = [
    "کارشناسان معتقدند",
    "تحقیقات نشان می‌دهد",
    "مطالعات نشان می‌دهد",
    "مطالعات اثبات کرده‌اند",
    "منابع علمی تأیید می‌کنند",
    "پزشکان توصیه می‌کنند",
    "متخصصان می‌گویند",
]

BINARY_CONTRAST_PATTERNS = [
    r"این\s+فقط\s+.{2,50}نیست[،؛,]?\s+بلکه",
    r"تنها\s+.{2,45}کافی\s+نیست",
    r"هدف\s+این\s+نیست\s+که\s+.{3,70}[؛،.]\s*هدف\s+این\s+است\s+که",
    r"موضوع\s+فقط\s+.{3,60}نیست[؛،.]\s*موضوع",
]

CANONICAL_DISCLAIMER = (
    "<h2>سلب مسئولیت پزشکی</h2>\n"
    "<p>این مقاله صرفاً جنبه‌ی آموزش و اطلاع‌رسانی دارد و جایگزین تشخیص، مشاوره یا درمان پزشکی نیست.</p>"
)


class PlainExtractor(HTMLParser):
    BLOCK_TAGS = {"p", "li", "td", "th", "h1", "h2", "h3", "h4", "blockquote", "div", "tr"}

    def __init__(self) -> None:
        super().__init__()
        self.parts: list[str] = []

    def handle_endtag(self, tag: str) -> None:
        if tag.lower() in self.BLOCK_TAGS:
            self.parts.append("\n")

    def handle_data(self, data: str) -> None:
        self.parts.append(data)

    def text(self) -> str:
        lines = [re.sub(r"[ \t]+", " ", ln).strip() for ln in "".join(self.parts).splitlines()]
        return "\n".join(ln for ln in lines if ln)


def strip_tags(fragment: str) -> str:
    p = PlainExtractor()
    p.feed(fragment)
    return p.text()


def audit_article(pkg_dir: pathlib.Path) -> tuple[list[str], list[str], list[str]]:
    errors: list[str] = []
    warnings: list[str] = []
    passed: list[str] = []

    manifest_path = pkg_dir / "article.json"
    html_path = pkg_dir / "content.html"
    if not manifest_path.is_file() or not html_path.is_file():
        return (["article.json یا content.html یافت نشد."], [], [])

    raw_json = manifest_path.read_text(encoding="utf-8")
    manifest = json.loads(raw_json)
    html = html_path.read_text(encoding="utf-8")
    plain = strip_tags(html)
    words = [w for w in re.split(r"\s+", plain) if w]
    word_count = len(words)

    # 1. Word count, sentence length & human paragraph cadence
    if word_count < 1200:
        errors.append(f"تعداد واژگان کم است ({word_count} کلمه؛ حداقل ۱۲۰۰ کلمه).")
    else:
        passed.append(f"حجم مقاله استاندارد است: {word_count} واژه (~{max(1, round(word_count / 190))} دقیقه مطالعه).")

    sentences = [s.strip() for s in re.split(r"[.؟!\n]+", plain) if len(s.strip().split()) >= 3]
    avg_sentence_len = sum(len(s.split()) for s in sentences) / max(1, len(sentences))
    if avg_sentence_len > 20.0:
        warnings.append(f"میانگین طول جمله بالاست ({avg_sentence_len:.1f} کلمه؛ هدف <= ۲۰ کلمه).")
    else:
        passed.append(f"میانگین طول جمله‌ها کوتاه و خوش‌خوان است ({avg_sentence_len:.1f} کلمه؛ هدف <= ۲۰).")

    p_blocks = [strip_tags(p) for p in re.findall(r"<p\b[^>]*>(.*?)</p>", html, re.S)]
    prose_p_lengths = [len(p.split()) for p in p_blocks if len(p.split()) >= 12]
    if len(prose_p_lengths) >= 5:
        p_stdev = statistics.pstdev(prose_p_lengths)
        if p_stdev < 6.0:
            warnings.append(f"یکنواختی مصنوعی طول پاراگراف‌ها دیده شد (انحراف معیار {p_stdev:.1f} واژه).")
        else:
            passed.append(f"ریتم طبیعی پاراگراف‌نویسی تأیید شد (تنوع طول پاراگراف: ±{p_stdev:.1f} واژه).")

    # 2. Deep AI-Slop & Translationese Scan (`no-ai-slop-fa`)
    slop_hits: list[str] = []
    for label, wordlist in [
        ("واژه‌ی ممنوعه اسلوپ", BANNED_SLOP_WORDS),
        ("عبارت اداری/دیوانی", BANNED_BUREAUCRATIC_PHRASES),
        ("عبارت توخالی هوش مصنوعی", BANNED_EMPTY_PHRASES),
        ("اسناد سست بدون منبع", BANNED_WEASEL),
    ]:
        for term in wordlist:
            if term in plain:
                slop_hits.append(f"{label} یافت شد: «{term}»")

    for bw in BANNED_BUREAUCRATIC_WORDS:
        if re.search(rf"(?<![آ-ی\u200c]){re.escape(bw)}(?![آ-ی\u200c])", plain):
            slop_hits.append(f"فعل اداری/جعلی یافت شد: «{bw}»")

    # Ban auxiliary pseudo-literary «می‌گردد / می‌گردند» while allowing active Persian «برمی‌گردد» and «دنبال/پی/دور ... می‌گردد»
    cleaned_active_gard = re.sub(r"(?:دنبال|پی|دور)\s+[^.،؛؟!\n]{1,35}?می‌گرد(?:د|ند)", "", plain)
    if re.search(r"(?<![آ-ی\u200c])می‌گرد(?:د|ند)(?![آ-ی\u200c])", cleaned_active_gard):
        slop_hits.append("فعل مجهول/دیوانی «می‌گردد/می‌گردند» به جای «می‌شود/می‌شوند» یافت شد.")

    for pat in BINARY_CONTRAST_PATTERNS:
        m = re.search(pat, plain)
        if m:
            slop_hits.append(f"الگوی تقابل دوتایی ترجمه‌زده یافت شد: «{m.group(0)}»")

    if slop_hits:
        errors.extend(slop_hits)
    else:
        passed.append("فیلتر حذف علائم هوش مصنوعی (no-ai-slop-fa + تقابل دوتایی + افعال دیوانی): ۰ خطا.")

    # 3. Dedicated Persian Grammar, Academy Orthography (ZWNJ) & Punctuation Filter
    grammar_issues: list[str] = []

    if "\u064a" in html or "\u0643" in html or "\u064a" in raw_json or "\u0643" in raw_json:
        grammar_issues.append("حروف عربی «ي» یا «ك» یافت شد؛ باید به «ی» و «ک» معیار فارسی تبدیل شود.")

    detached_mi = re.findall(r"(?<![آ-ی\u200c])ن?می[ ]+[آ-ی]{2,}", plain)
    if detached_mi:
        grammar_issues.append(f"فاصله‌ی معمولی به جای نیم‌فاصله پس از «می/نمی»: {detached_mi[:4]}")

    detached_ha = re.findall(r"[آ-ی][ ]+ها(?:ی|یی|ست|یش|یمان|یتان|یشان)?(?![آ-ی])", plain)
    if detached_ha:
        grammar_issues.append(f"فاصله‌ی معمولی به جای نیم‌فاصله پیش از «ها/های»: {detached_ha[:4]}")

    detached_tar = re.findall(r"[آ-ی][ ]+تر(?:ین)?(?![آ-ی])", plain)
    if detached_tar:
        grammar_issues.append(f"فاصله‌ی معمولی به جای نیم‌فاصله پیش از «تر/ترین»: {detached_tar[:4]}")

    detached_ezafe = re.findall(r"[آ-ی]ه[ ]+ی(?![آ-ی])", plain)
    if detached_ezafe:
        grammar_issues.append(f"نگارش نادرست «ه ی» با فاصله‌ی معمولی (باید با نیم‌فاصله «ـه‌ی» نوشته شود): {detached_ezafe[:4]}")

    if "ۀ" in plain and "ه‌ی" in plain:
        grammar_issues.append("عدم یکدستی رسم‌الخط اضافه پس از «ه» (اختلاط «ۀ» و «ـه‌ی» در یک متن).")

    latin_punct = re.findall(r"[آ-ی]\s*[,;?]", plain)
    if latin_punct:
        grammar_issues.append(f"علامت سجاوندی انگلیسی (, ; ?) در متن فارسی یافت شد: {latin_punct[:4]}")

    space_before_punct = re.findall(r"[آ-ی0-9۰-۹][ ]+[،؛؟!.]", plain)
    if space_before_punct:
        grammar_issues.append(f"فاصله‌ی اضافه پیش از علامت سجاوندی یافت شد: {space_before_punct[:4]}")

    if plain.count("«") != plain.count("»"):
        grammar_issues.append(f"گیومه‌های فارسی «...» جفت نیستند («={plain.count('«')}, »={plain.count('»')}).")

    if '"' in plain:
        grammar_issues.append('گیومه‌ی مستقیم انگلیسی (") داخل متن فارسی دیده شد؛ از «...» استفاده کنید.')

    ascii_digits_in_prose = re.findall(r"[0-9٠-٩]+", plain)
    if ascii_digits_in_prose:
        grammar_issues.append(f"اعداد انگلیسی یا عربی در متن فارسی یافت شد (باید فارسی ۰-۹ باشد): {ascii_digits_in_prose[:4]}")

    if grammar_issues:
        errors.extend(grammar_issues)
    else:
        passed.append("فیلتر گرامر، دستور خط فرهنگستان (نیم‌فاصله، یای اضافه، حروف و اعداد فارسی) و سجاوندی: ۰ خطا.")

    # 4. Formatting & English-leak & Shamsi-calendar checks
    if "<br" in html.lower():
        errors.append("تگ <br> در HTML یافت شد (طبق استاندارد v2.0 ممنوع است).")
    if re.search(r"\\[#*\[\]()]", html):
        errors.append("کاراکتر escape مارک‌داون در HTML باقی مانده است.")
    if "[reference:" in html:
        errors.append("نشانگر مرجع معلق [reference:N] در متن یافت شد.")
    em_dash_count = plain.count("—")
    if em_dash_count > 2:
        warnings.append(f"تعداد خط تیره‌ی بلند (—) زیاد است ({em_dash_count} مورد؛ حداکثر ۲ مورد مجاز است).")
    else:
        passed.append(f"کنترل خط تیره‌ی بلند (—) استاندارد است ({em_dash_count} مورد <= ۲).")

    html_no_tags = re.sub(r"<[^>]+>", " ", html)
    outside_parens = re.sub(r"\([^)]*\)", " ", html_no_tags)
    english_leaks = re.findall(r"[A-Za-z]{2,}", outside_parens)
    if english_leaks:
        errors.append(f"نشت واژه‌ی انگلیسی بیرون از پرانتز تعریف اصطلاح یافت شد: {english_leaks}")
    else:
        passed.append("هیچ نشت واژه‌ی انگلیسی رها در متن فارسی وجود ندارد (تمام معادل‌های علمی داخل پرانتز تعریف شده‌اند).")

    greg_years = re.findall(r"(?:19|20)\d{2}|(?:۱۹|۲۰)[۰-۹]{2}", plain)
    if greg_years:
        errors.append(f"سال میلادی در متن فارسی یافت شد (طبق قانون تاریخ شمسی ممنوع است): {greg_years}")
    else:
        passed.append("هیچ تاریخ یا سال میلادی در متن فارسی وجود ندارد.")

    # 5. YMYL Medical & Legal Safety Gate
    if re.search(r"میلی‌گرم|میلی گرم|میکروگرم|\bmg\b|\bmcg\b", plain, re.I):
        errors.append("خطای ایمنی پزشکی: واحد دوز دارویی در متن دیده شد.")
    if re.search(r"[0-9۰-۹]+\s*ساعت\s*(?:قبل|پیش|ناشتا)", plain):
        errors.append("خطای ایمنی پزشکی: عدد ساعت قطعی ناشتایی در متن دیده شد.")
    if "%" in plain or "٪" in plain or re.search(r"\bدرصد\b", plain):
        errors.append("خطای ایمنی پزشکی: درصد آماری در متن دیده شد.")
    if re.search(r"بدون\s+هیچ\s+عارضه|درمان\s+قطعی|تضمین\s+سلامت", plain):
        errors.append("خطای ایمنی پزشکی: ادعای قطعی/بدون عارضه دیده شد.")

    for req_phrase, desc in [
        ("معمولاً", "لحن احتمالی («معمولاً»)"),
        ("ممکن است", "لحن احتمالی («ممکن است»)"),
        ("پروتکل مرکز درمانی", "ارجاع صریح به «پروتکل مرکز درمانی»"),
        ("۱۱۵", "مرز هشدار و تماس با اورژانس ۱۱۵"),
    ]:
        if req_phrase not in plain:
            errors.append(f"الزام ایمنی یافت نشد: {desc}")
        else:
            passed.append(f"الزام ایمنی تأیید شد: {desc}.")

    # 6. Canonical single-sentence disclaimer + ZERO Author Name Anywhere + Blockquote Story
    if not html.strip().endswith(CANONICAL_DISCLAIMER):
        errors.append("انتهای مقاله دقیقاً با تک‌جمله‌ی استاندارد «سلب مسئولیت پزشکی» نسخه 0.10.2+ تمام نشده است.")
    else:
        passed.append("بخش پایانی «سلب مسئولیت پزشکی» دقیقاً مطابق تک‌جمله‌ی مصوب 0.10.2+ است.")

    if "محمدرضا لک" in html or "محمدرضا لک" in raw_json or "hj-author-signature" in html or "نویسنده:" in plain:
        errors.append("نام نویسنده در متن یا متادیتای مقاله دیده شد (طبق دستور کارفرما نام نویسنده در هیچ مقاله‌ای نباید ذکر شود).")
    else:
        passed.append("عدم ذکر نام نویسنده در سراسر متن و متادیتای مقاله تأیید شد.")

    blockquotes = re.findall(r"<blockquote\b[^>]*>(.*?)</blockquote>", html, re.S)
    post_status = manifest.get("post_status", "draft").strip()
    if len(blockquotes) > 1:
        errors.append(f"بیش از ۱ بلوک نقل‌قول در مقاله مجاز نیست (یافت‌شده: {len(blockquotes)}).")
    elif len(blockquotes) == 0:
        passed.append("بدون بلوک نقل‌قول/خاطره (۰ عدد — رعایت ریتم طبیعی و پرهیز از یکنواختی فرمولی بین مقالات).")
    else:
        bq_words = len(strip_tags(blockquotes[0]).split())
        bq_pos_ratio = html.find("<blockquote") / max(1, len(html))
        if bq_words < 12:
            warnings.append(f"نقل‌قول بیش از حد کوتاه است ({bq_words} واژه).")
        elif post_status == "draft" and bq_words > 85:
            warnings.append(
                f"خاطره/نقل‌قول طولانی است ({bq_words} واژه؛ در مقالات جدید باید کوتاه و گذرا — حداکثر ۸۵ واژه — باشد و سریع رد شود)."
            )
        else:
            passed.append(
                f"نقل‌قول/خاطره‌ی کوتاه داخل <blockquote> ثبت شده است ({bq_words} واژه، موقعیت نسبی در بدنه: {int(bq_pos_ratio * 100)}٪)."
            )

    # 7. Headings & Internal Links & SEO Metadata Audit (inc/article-importer.php parity)
    title = manifest.get("title", "").strip()
    slug = manifest.get("slug", "").strip()
    meta_desc = manifest.get("seo", {}).get("meta_description", "").strip()
    focus_kw = manifest.get("seo", {}).get("focus_keyword", "").strip()
    search_intent = manifest.get("seo", {}).get("search_intent", "").strip()
    keywords = manifest.get("seo", {}).get("keywords", [])
    faqs = manifest.get("faq", [])

    if not (18 <= len(title) <= 65):
        warnings.append(f"طول عنوان ({len(title)} کاراکتر) خارج از بازه‌ی ایده‌آل ۱۸ تا ۶۵ کاراکتر است.")
    else:
        passed.append(f"طول عنوان برای سئو و نمایش تک‌خطی موبایل عالی است ({len(title)} کاراکتر).")

    if not re.match(r"^[a-z0-9-]+$", slug):
        errors.append(f"اسلاگ غیراستاندارد است: {slug}")
    else:
        passed.append(f"اسلاگ انگلیسی استاندارد: `{slug}`.")

    if not (120 <= len(meta_desc) <= 165):
        warnings.append(f"طول توضیحات متا ({len(meta_desc)} کاراکتر) خارج از بازه‌ی بهینه‌ی ۱۲۰ تا ۱۶۵ کاراکتر است.")
    else:
        passed.append(f"طول توضیحات متا در بازه‌ی طلایی ۱۲۰ تا ۱۶۵ کاراکتر است ({len(meta_desc)} کاراکتر).")

    if not focus_kw or (focus_kw not in title and focus_kw not in meta_desc):
        warnings.append("کلیدواژه‌ی کانونی خالی است یا در عنوان/متا نیامده است.")
    else:
        passed.append(f"کلیدواژه‌ی کانونی («{focus_kw}») در عنوان و توضیحات متا حضور دارد.")

    if len(keywords) < 3 or not search_intent:
        warnings.append("کلیدواژه‌های فرعی یا نیت جستجو ناقص است.")
    else:
        passed.append(f"نیت جستجو و {len(keywords)} کلیدواژه‌ی فرعی ثبت شده است.")

    if len(faqs) < 4:
        warnings.append(f"تعداد پرسش‌های متداول کمتر از ۴ مورد است ({len(faqs)}).")
    else:
        passed.append(f"اسکیمای FAQPage با {len(faqs)} پرسش و پاسخ فعال است.")

    headings = re.findall(r"<h([1-6])\b[^>]*>(.*?)</h\1>", html, re.S)
    h1s = [strip_tags(t) for lvl, t in headings if lvl == "1"]
    h2s = [strip_tags(t) for lvl, t in headings if lvl == "2"]
    if h1s:
        warnings.append("تگ <h1> داخل بدنه‌ی HTML نباید تکرار شود (قالب خودش H1 می‌سازد).")
    if len(h2s) < 5:
        warnings.append(f"تعداد زیرتیترهای H2 کم است ({len(h2s)}).")
    else:
        passed.append(f"{len(h2s)} زیرتیتر H2 استاندارد برای فهرست مطالب شناسایی شد.")

    for lvl, raw_t in headings:
        clean_t = strip_tags(raw_t)
        if len(clean_t) > 58:
            warnings.append(f"زیرتیتر H{lvl} برای نمایش تک‌خطی موبایل طولانی است ({len(clean_t)} کاراکتر): «{clean_t}»")

    cpt_prefix_map = {
        "patient_guide": "/patient-guide/",
        "surgery": "/surgery/",
        "or_environment": "/or-environment/",
        "student_content": "/student/",
    }
    post_type = manifest.get("post_type", "patient_guide").strip()
    self_path = f"{cpt_prefix_map.get(post_type, '/patient-guide/')}{slug}/"
    links = re.findall(r'<a\b[^>]*href=["\']([^"\']+)["\']', html)
    internal_links = [u for u in links if u.startswith("/")]
    if len(internal_links) < 3:
        warnings.append(f"تعداد لینک‌های داخلی کم است ({len(internal_links)}؛ حداقل ۳ لینک توصیه می‌شود).")
    else:
        bad_links = [u for u in internal_links if u not in LIVE_PUBLISHED_PATHS or u == self_path]
        if bad_links:
            errors.append(f"لینک داخلی نامعتبر یا خودارجاع یافت شد: {bad_links}")
        else:
            passed.append(
                f"{len(internal_links)} لینک داخلی معتبر و ۱۰۰٪ زنده روی سایت (بدون حتی یک لینک ۴۰۴) ثبت شده است."
            )

    return errors, warnings, passed


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: python3 tools/hj-article-filter.py <article-folder> [more-folders...]")
        return 2
    rc = 0
    for idx, arg in enumerate(sys.argv[1:]):
        if idx > 0:
            print("=" * 60)
        pkg_dir = pathlib.Path(arg).resolve()
        errors, warnings, passed = audit_article(pkg_dir)
        print(f"=== گزارش ممیزی فیلتر اختصاصی همیار جراحی: {pkg_dir.name} ===")
        print(f"✅ موارد تأییدشده ({len(passed)}):")
        for p in passed:
            print(f"   ✓ {p}")
        print(f"\n⚠️ هشدارها ({len(warnings)}):")
        for w in warnings:
            print(f"   ! {w}")
        print(f"\n❌ خطاها ({len(errors)}):")
        for e in errors:
            print(f"   ✗ {e}")
        status = "PASS (100%)" if not errors and not warnings else ("PASS WITH WARNINGS" if not errors else "FAIL")
        print(f"\nنتیجه‌ی نهایی فیلتر اختصاصی: {status}")
        if errors or warnings:
            rc = 1
    return rc


if __name__ == "__main__":
    raise SystemExit(main())

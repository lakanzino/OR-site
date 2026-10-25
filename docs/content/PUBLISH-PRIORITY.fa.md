# اولویت انتشار مقالات — نقشه‌ی ده مقاله‌ی بعدی

- **تاریخ تدوین:** ۲۰۲۶-۱۰-۲۴ (۲ آبان ۱۴۰۵)
- **آخرین بازبینی:** همان روز — نقشه‌ی سایت دوباره خوانده شد و اعداد بخش ۱ تأیید شد (هنوز ۲۸ مقاله؛ هیچ‌کدام از ده بستهٔ تازه روی سایت نیست).
- **مبنای اولویت:** `themes/lax-theme-child/inc/content-roadmap-data.php` (فاز هر مقاله) + `docs/content/CONTENT-MAP.md` (اول مسیر بیمار، بعد جراحی‌های خاص)
- **مبنای وضعیت موجود:** نقشه‌ی سایت زنده (`wp-sitemap-posts-*.xml`) که در همین تاریخ بررسی شد — نه تخمین و نه سند قدیمی.

---

## ۱. وضعیت واقعی سایت (بررسی‌شده از نقشه‌ی سایت)

| CPT | تعداد زنده | نامک‌ها |
|---|---:|---|
| `patient_guide` | ۲۳ | `glossary-surgical-terms`، `what-is-operating-room`، `surgical-care-team`، `patient-journey`، `operating-room-moment-by-moment`، `general-anesthesia`، `preoperative-fasting`، `pacu-recovery`، `fear-of-anesthesia-awareness`، `informed-consent-anesthesia-consult`، `general-vs-spinal-anesthesia`، `preoperative-tests`، `what-not-to-eat-before-surgery`، `post-surgery-pain`، `suture-removal`، `surgical-site-infection`، `post-surgery-nutrition`، `early-walking-after-surgery`، `companion-role-admission-day`، `medications-before-surgery`، `mental-preparation-before-surgery`، `anesthesia-side-effects`، `warning-signs-after-surgery` |
| `or_environment` | ۱ | `surgical-team-roles` |
| `surgery` | ۴ | `appendectomy-preparation`، `appendectomy-recovery`، `cesarean-overview`، `cesarean-preparation` |
| `student_content` | ۰ | — |
| `glossary` | ۰ | CPT بدون ردیف |

**جمع: ۲۸ مقاله‌ی زنده از ۱۸۴ مقاله‌ی هدف پایه → ۱۵۶ مقاله باقی مانده (۱۵٪ پیشرفت).**

> ⚠️ **تصحیح اسناد:** `NEXT-ARTICLES-PLAN.md` می‌گوید «۵ مقاله زنده» و `content/live-mirror/governing/00-مانيفست-آینه.md` می‌گوید «۱۵ مقاله منتشرشده». هیچ‌کدام امروز درست نیست؛ عدد درست **۲۸** است. مانیفست آینه هم فقط ۵ مقاله را در خود دارد، در حالی که ۲۳ مقاله‌ی `patient_guide` زنده است. پیشنهاد: پس از این دسته، آینه‌ی متنی ۱۰ مقاله‌ی تازه و ۵ مقاله‌ی زنده‌ی بدون آینه تکمیل شود.

### شکاف‌های فاز ۱ (فقط ۶ مقاله)

| دسته | باقی‌مانده‌ی فاز ۱ |
|---|---|
| قبل از عمل | `anesthesia-consultation` **(هم‌پوشان — پایین را ببینید)** |
| جراحی‌ها، گروه ۱ | `appendectomy-overview`، `cesarean-recovery`، `hernia-overview`، `hernia-preparation`، `hernia-recovery` |

یعنی فاز ۱ عملاً تمام شده است و از این به بعد ترتیب، فاز ۲ و تکمیل خوشه‌های نیمه‌کاره است.

---

## ۲. قاعده‌ی رتبه‌بندی (چرا این ترتیب و نه ترتیب دیگر)

1. **فاز نقشه‌راه** — عدد کوچک‌تر = اولویت بیشتر (۱ تا ۶). این عدد را خود پروژه تعیین کرده است.
2. **تکمیل خوشه‌ی باز** — مقاله‌ای که خوشه‌اش نیمه‌کاره روی سایت است، از مقاله‌ای که خوشه‌ی تازه باز می‌کند جلو می‌زند؛ چون لینک داخلی و اعتبار موضوعی را همان لحظه پس می‌دهد.
3. **ستون مادر قبل از برگ** — در خوشه‌ی جراحی، اول مقاله‌ی «چیست و چه مراحلی دارد؟» (هاب)، بعد «قبل از عمل»، بعد «بعد از عمل».
4. **ترتیب بخش‌های نقشه‌راه** — قبل از عمل → حین عمل → بعد از عمل → گروه‌های ویژه → محیط اتاق عمل → جراحی‌ها → دانشجویان.
5. **بدون هم‌پوشانی** — مقاله‌ای که نیت جستجویش با یک مقاله‌ی زنده یکی است، حتی اگر در نقشه‌راه فاز ۱ باشد، وارد صف نمی‌شود.

### یک هشدار هم‌پوشانی

`anesthesia-consultation` («متخصص بیهوشی دقیقاً چه چیزهایی را بررسی می‌کند؟») با مقاله‌ی زنده‌ی `informed-consent-anesthesia-consult` هم‌تاکسونومی و تقریباً هم‌نیت است. اگر هر دو منتشر شوند، روی هم اثر منفی می‌گذارند. پیشنهاد: این ردیف از نقشه‌راه حذف یا به «بخش پرسش‌های متداول» همان مقاله‌ی زنده تبدیل شود. تا تصمیم شما، از صف خارج می‌ماند و جایش را مقاله‌ی دیگری می‌گیرد.

---

## ۳. ده مقاله‌ی اول (این دسته نوشته شد)

| # | عنوان | نامک | CPT | بخش / تاکسونومی | فاز | نیت جستجو | ~کلمه |
|---:|---|---|---|---|---:|---|---:|
| ۱ | آپاندکتومی (عمل آپاندیس) چیست و چه مراحلی دارد؟ | `appendectomy-overview` | `surgery` | `surgery_stage` → `surgery-overview` · `surgery_specialty` → `appendectomy` | ۱ | «عمل آپاندیس چگونه است» | ۱۳۰۰ |
| ۲ | بعد از سزارین چه مراقبت‌هایی لازم است؟ | `cesarean-recovery` | `surgery` | `after-surgery` · `cesarean` | ۱ | «مراقبت بعد از سزارین» | ۱۳۰۰ |
| ۳ | جراحی فتق (هرنی) چیست و چه مراحلی دارد؟ | `hernia-overview` | `surgery` | `surgery-overview` · `hernia-repair` | ۱ | «عمل فتق چیست» | ۱۳۰۰ |
| ۴ | قبل از جراحی فتق چه آمادگی‌هایی لازم است؟ | `hernia-preparation` | `surgery` | `before-surgery` · `hernia-repair` | ۱ | «آمادگی قبل از عمل فتق» | ۱۳۰۰ |
| ۵ | بعد از جراحی فتق چه مراقبت‌هایی لازم است؟ | `hernia-recovery` | `surgery` | `after-surgery` · `hernia-repair` | ۱ | «مراقبت بعد از عمل فتق» | ۱۳۰۰ |
| ۶ | اینتوباسیون (لوله‌گذاری تنفسی) چیست و چرا انجام می‌شود؟ | `intubation-explained` | `patient_guide` | `guide_stage` → `anesthesia-types` | ۲ | «لوله تنفسی در بیهوشی» | ۱۳۰۰ |
| ۷ | آیا بیهوشی روی حافظه و تمرکز اثر می‌گذارد؟ | `anesthesia-and-memory` | `patient_guide` | `guide_stage` → `anesthesia-types` | ۲ | «فراموشی بعد از بیهوشی» | ۱۳۰۰ |
| ۸ | مراقبت از زخم و پانسمان جراحی در خانه | `home-wound-care` | `patient_guide` | `guide_stage` → `infection-prevention` | ۲ | «مراقبت از زخم بعد از عمل» | ۱۳۰۰ |
| ۹ | ترومبوز ورید عمقی و آمبولی ریه بعد از جراحی | `dvt-pulmonary-embolism-prevention` | `patient_guide` | `guide_stage` → `warning-signs` | ۲ | «لخته خون بعد از عمل» | ۱۳۰۰ |
| ۱۰ | چک‌لیست ترخیص: هنگام خروج از بیمارستان چه سؤال‌هایی بپرسیم؟ | `discharge-checklist` | `patient_guide` | `guide_stage` → `discharge` | ۲ | «ترخیص از بیمارستان» | ۱۳۰۰ |

**چرا ۱ و ۲ اول آمدند؟** `appendectomy` دو مقاله از سه مقاله‌اش زنده است و `cesarean` هم دو تا؛ ولی هر دو هاب («چیست و چه مراحلی دارد؟» / «بعد از سزارین چه مراقبتی؟») را کم دارند. بدون هاب، آن چهار مقاله‌ی زنده به ستون مادر وصل نیستند. این دو مقاله، خوشه را کامل می‌کنند.

**چرا ۳ تا ۵؟** `hernia-repair` تنها خوشه‌ی کامل‌نشده‌ی فاز ۱ است. فتق اینگوینال یکی از پرجستجوترین عمل‌های جراحی عمومی است و سه‌گانه‌اش (هاب + قبل + بعد) با هم یک خوشه‌ی کامل می‌سازد.

**چرا ۶ تا ۱۰؟** فاز ۲ به ترتیب بخش‌های نقشه‌راه: دو مقاله‌ی مانده‌ی بیهوشی، سپس سه مقاله‌ی «بعد از عمل» که به خوشه‌ی قوی و زنده‌ی بعد از عمل (درد، بخیه، عفونت، تغذیه، راه رفتن، علائم خطر) وصل می‌شوند.

---

## ۴. پانزده مقاله‌ی بعد از این دسته

| # | نامک | فاز | چرا بعدی است |
|---:|---|---:|---|
| ۱۱ | `children-and-anesthesia` | ۲ | گروه‌های ویژه؛ والدین بیشترین جستجو را دارند |
| ۱۲ | `elderly-and-surgery` | ۲ | گروه‌های ویژه |
| ۱۳ | `emergency-vs-elective-surgery` | ۲ | تصمیم مسیر؛ به هاب مسیر بیمار وصل می‌شود |
| ۱۴ | `outpatient-vs-inpatient-surgery` | ۲ | تصمیم مسیر |
| ۱۵ | `laparoscopy-overview` | ۲ | خوشه‌ی لاپاروسکوپی، هاب |
| ۱۶ | `laparoscopy-preparation` | ۲ | تکمیل همان خوشه |
| ۱۷ | `laparoscopy-recovery` | ۲ | تکمیل همان خوشه |
| ۱۸ | `cholecystectomy-overview` | ۲ | خوشه‌ی کیسه صفرا |
| ۱۹ | `cholecystectomy-preparation` | ۲ | — |
| ۲۰ | `cholecystectomy-recovery` | ۲ | — |
| ۲۱ | `cataract-overview` | ۲ | خوشه‌ی آب مروارید |
| ۲۲ | `cataract-preparation` | ۲ | — |
| ۲۳ | `cataract-recovery` | ۲ | — |
| ۲۴ | `thyroidectomy-overview` | ۲ | خوشه‌ی تیروئید |
| ۲۵ | `thyroidectomy-preparation` | ۲ | — |

بعد از فاز ۲، فاز ۳ (بیماری‌های زمینه‌ای: `smoking-and-surgery`، `diabetes-and-surgery`، `obesity-and-anesthesia`، `blood-donation-reserve-before-surgery`) و بعد ۲۱ مقاله‌ی محیط اتاق عمل می‌آید.

---

## ۵. بسته‌های این دسته

هر مقاله یک بسته‌ی سه‌فایلی است در `content/drafts/<slug>/` و یک ZIP آماده‌ی ایمپورت در `releases/article-packages/article-package-<slug>.zip`:

```
article.json      عنوان، نامک، تاکسونومی، متای سئو، تاریخ‌ها، alt تصویر، پرسش‌های متداول
content.html      متن کامل، بدون H1 (عنوان از manifest می‌آید)
featured.webp     ۱۲۰۰×۶۷۵، WebP، زیر ۱۸۰ کیلوبایت
```

- **وضعیت همه‌ی بسته‌ها `draft` است** — طبق دروازه‌ی انتشار در `NEXT-ARTICLES-PLAN.md`، انتشار بعد از بازبینی پزشکی و تأیید صریح شما انجام می‌شود.
- هر مقاله ۳ تا ۶ لینک داخلی دارد. همه‌ی مقصدها یا **همین حالا زنده‌اند** یا **یکی از ده مقاله‌ی همین دسته**؛ هیچ مقصد ناموجودی در متن نیست. لینک‌های درون‌دسته‌ای فقط وقتی کار می‌کنند که ده مقاله با هم منتشر شوند.
- هر مقاله بخش «چه زمانی باید فوراً تماس بگیرید؟» با ۱۱۵، بخش منابع نام‌دار، و جمله‌ی استاندارد سلب مسئولیت دارد.

## ۶. وضعیت تحویل و پیگیری (این جدول را بعد از هر ایمپورت به‌روز کنید)

| # | نامک | بسته | ایمپورت | بازبینی پزشکی | انتشار |
|---:|---|---|---|---|---|
| ۱ | `appendectomy-overview` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۲ | `cesarean-recovery` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۳ | `hernia-overview` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۴ | `hernia-preparation` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۵ | `hernia-recovery` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۶ | `intubation-explained` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۷ | `anesthesia-and-memory` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۸ | `home-wound-care` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۹ | `dvt-pulmonary-embolism-prevention` | ✅ `draft` | ⬜ | ⬜ | ⬜ |
| ۱۰ | `discharge-checklist` | ✅ `draft` | ⬜ | ⬜ | ⬜ |

پس از انتشار هر مقاله: نشانی آن به فهرست لینک داخلی مجاز اضافه می‌شود تا دستهٔ بعدی بتواند به آن لینک بدهد. این فهرست در بخش ۲ همین سند نگه‌داری می‌شود.

## ۷. گام‌های پس از ایمپورت (برای هر ده مقاله)

1. بازبینی پزشکی متن و افزودن نام بازبین در فیلد مربوطه.
2. ایمپورت با «ایمپورتر مقاله» در پیشخوان (وضعیت: پیش‌نویس).
3. تغییر وضعیت به «منتشرشده» و LiteSpeed → **Purge All**.
4. به‌روزرسانی مانیفست آینه + کپی سه فایل در `content/live-mirror/articles/<slug>/`.
5. ثبت نشانی‌های تازه در فهرست لینک داخلی همان مانیفست تا دسته‌ی بعدی بتواند به آن‌ها لینک بدهد.
6. علامت زدن ردیف همان مقاله در جدول بخش ۶ و به‌روزرسانی عدد پیشرفت در بخش ۱ (۲۸ → ۲۹ و …).

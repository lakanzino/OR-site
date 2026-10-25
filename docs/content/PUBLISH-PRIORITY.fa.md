# اولویت انتشار مقالات — نقشه‌ی ده مقاله‌ی بعدی

- **تاریخ تدوین:** ۲۰۲۶-۱۰-۲۴ (۲ آبان ۱۴۰۵)
- **آخرین بازبینی:** ۲۰۲۶-۱۰-۲۵ — نقشه‌ی سایت با پارامتر ضدکش دوباره خوانده شد: **۳۱ مقاله**. سه مقاله تازه روی سایت رفت: `anesthesia-consultation`، `cesarean-recovery` و `hernia-overview`.
- **هشدار روش‌شناسی:** نقشه‌ی سایت وردپرس از کش CDN سرو می‌شود؛ اولین خواندن بدون `?v=` عدد قدیمی ۲۸ را برگرداند. برای شمارش همیشه نسخه‌ی کش‌نشده خوانده شود (`?v=<timestamp>`).
- **مبنای اولویت:** `themes/lax-theme-child/inc/content-roadmap-data.php` (فاز هر مقاله) + `docs/content/CONTENT-MAP.md` (اول مسیر بیمار، بعد جراحی‌های خاص)
- **مبنای وضعیت موجود:** نقشه‌ی سایت زنده (`wp-sitemap-posts-*.xml`) که در همین تاریخ بررسی شد — نه تخمین و نه سند قدیمی.

---

## ۱. وضعیت واقعی سایت (بررسی‌شده از نقشه‌ی سایتِ کش‌نشده)

| CPT | تعداد زنده | نامک‌ها |
|---|---:|---|
| `patient_guide` | ۲۴ | `glossary-surgical-terms`، `what-is-operating-room`، `surgical-care-team`، `patient-journey`، `operating-room-moment-by-moment`، `general-anesthesia`، `preoperative-fasting`، `pacu-recovery`، `fear-of-anesthesia-awareness`، `informed-consent-anesthesia-consult`، `general-vs-spinal-anesthesia`، `preoperative-tests`، `what-not-to-eat-before-surgery`، `post-surgery-pain`، `suture-removal`، `surgical-site-infection`، `post-surgery-nutrition`، `early-walking-after-surgery`، `companion-role-admission-day`، `medications-before-surgery`، `mental-preparation-before-surgery`، `anesthesia-consultation`، `anesthesia-side-effects`، `warning-signs-after-surgery` |
| `or_environment` | ۱ | `surgical-team-roles` |
| `surgery` | ۶ | `appendectomy-preparation`، `appendectomy-recovery`، `cesarean-overview`، `cesarean-preparation`، `cesarean-recovery`، `hernia-overview` |
| `student_content` | ۰ | — |
| `glossary` | ۰ | CPT بدون ردیف |

**جمع: ۳۱ مقاله‌ی زنده از ۱۸۴ مقاله‌ی هدف پایه → ۱۵۳ مقاله باقی مانده (۱۶٫۸٪ پیشرفت).**

> ⚠️ **تصحیح اسناد:** `NEXT-ARTICLES-PLAN.md` می‌گوید «۵ مقاله زنده» و مانیفست آینه می‌گفت «۱۵ مقاله منتشرشده». هیچ‌کدام امروز درست نیست؛ عدد درست **۳۱** است. مانیفست آینه هم فقط ۵ مقاله را در خود دارد، در حالی که ۲۴ مقاله‌ی `patient_guide` زنده است. پیشنهاد: آینه‌ی متنی ۲۶ مقاله‌ی زنده‌ی بدون آینه تکمیل شود (`content/live-mirror/README.md`).

### شکاف‌های فاز ۱ (فقط ۳ مقاله)

| دسته | باقی‌مانده‌ی فاز ۱ |
|---|---|
| قبل از عمل | — (منتشر شد) |
| جراحی‌ها، گروه ۱ | `appendectomy-overview`، `hernia-preparation`، `hernia-recovery` — **سه بسته‌ی آماده دارند و منتظر انتشارند** |

یعنی فاز ۱ با انتشار همان سه بسته‌ی آماده تمام می‌شود؛ بعد از آن ترتیب، فاز ۲ و تکمیل خوشه‌های نیمه‌کاره است.

---

## ۲. قاعده‌ی رتبه‌بندی (چرا این ترتیب و نه ترتیب دیگر)

1. **فاز نقشه‌راه** — عدد کوچک‌تر = اولویت بیشتر (۱ تا ۶). این عدد را خود پروژه تعیین کرده است.
2. **تکمیل خوشه‌ی باز** — مقاله‌ای که خوشه‌اش نیمه‌کاره روی سایت است، از مقاله‌ای که خوشه‌ی تازه باز می‌کند جلو می‌زند؛ چون لینک داخلی و اعتبار موضوعی را همان لحظه پس می‌دهد.
3. **ستون مادر قبل از برگ** — در خوشه‌ی جراحی، اول مقاله‌ی «چیست و چه مراحلی دارد؟» (هاب)، بعد «قبل از عمل»، بعد «بعد از عمل».
4. **ترتیب بخش‌های نقشه‌راه** — قبل از عمل → حین عمل → بعد از عمل → گروه‌های ویژه → محیط اتاق عمل → جراحی‌ها → دانشجویان.
5. **بدون هم‌پوشانی** — مقاله‌ای که نیت جستجویش با یک مقاله‌ی زنده یکی است، حتی اگر در نقشه‌راه فاز ۱ باشد، وارد صف نمی‌شود.

### یک هشدار هم‌پوشانی

`anesthesia-consultation` («مشاوره‌ی بیهوشی پیش از عمل: چه مواردی بررسی می‌شود؟») با مقاله‌ی زنده‌ی `informed-consent-anesthesia-consult` هم‌تاکسونومی و تقریباً هم‌نیت بود و من از صف خارجش کرده بودم. **این مقاله در ۱۴۰۵/۰۸/۰۳ روی سایت منتشر شد** (نه از بسته‌های من). تصمیم با شماست: یا عنوان/محتوا نسبت به مقاله‌ی رضایت آگاهانه متمایزتر شود، یا یکی از دو صفحه با ۳۰۱ به دیگری هدایت شود. تا آن زمان، جدول اولویت هیچ ردیف تازه‌ای در این نیت جستجو نمی‌سازد.

---

## ۳. ده مقاله‌ی اول (این دسته نوشته شد)

| # | عنوان | نامک | CPT | بخش / تاکسونومی | فاز | نیت جستجو | ~کلمه |
|---:|---|---|---|---|---:|---|---:|
| ۱ | آپاندکتومی (عمل آپاندیس) چیست و چه مراحلی دارد؟ | `appendectomy-overview` | `surgery` | `surgery_stage` → `surgery-overview` · `surgery_specialty` → `appendectomy` | ۱ | «عمل آپاندیس چگونه است» | ۱۳۰۰ |
| ۲ | بعد از سزارین چه مراقبت‌هایی لازم است؟ | `cesarean-recovery` | `surgery` | `after-surgery` · `cesarean` | ۱ | «مراقبت بعد از سزارین» | ۱۳۰۰ · ✅ منتشرشده |
| ۳ | جراحی فتق (هرنی) چیست و چه مراحلی دارد؟ | `hernia-overview` | `surgery` | `surgery-overview` · `hernia-repair` | ۱ | «عمل فتق چیست» | ۱۳۰۰ · ✅ منتشرشده |
| ۴ | قبل از جراحی فتق چه آمادگی‌هایی لازم است؟ | `hernia-preparation` | `surgery` | `before-surgery` · `hernia-repair` | ۱ | «آمادگی قبل از عمل فتق» | ۱۳۰۰ |
| ۵ | بعد از جراحی فتق چه مراقبت‌هایی لازم است؟ | `hernia-recovery` | `surgery` | `after-surgery` · `hernia-repair` | ۱ | «مراقبت بعد از عمل فتق» | ۱۳۰۰ |
| ۶ | اینتوباسیون (لوله‌گذاری تنفسی) چیست و چرا انجام می‌شود؟ | `intubation-explained` | `patient_guide` | `guide_stage` → `anesthesia-types` | ۲ | «لوله تنفسی در بیهوشی» | ۱۳۰۰ |
| ۷ | آیا بیهوشی روی حافظه و تمرکز اثر می‌گذارد؟ | `anesthesia-and-memory` | `patient_guide` | `guide_stage` → `anesthesia-types` | ۲ | «فراموشی بعد از بیهوشی» | ۱۳۰۰ |
| ۸ | مراقبت از زخم و پانسمان جراحی در خانه | `home-wound-care` | `patient_guide` | `guide_stage` → `infection-prevention` | ۲ | «مراقبت از زخم بعد از عمل» | ۱۳۰۰ |
| ۹ | ترومبوز ورید عمقی و آمبولی ریه بعد از جراحی | `dvt-pulmonary-embolism-prevention` | `patient_guide` | `guide_stage` → `warning-signs` | ۲ | «لخته خون بعد از عمل» | ۱۳۰۰ |
| ۱۰ | چک‌لیست ترخیص: هنگام خروج از بیمارستان چه سؤال‌هایی بپرسیم؟ | `discharge-checklist` | `patient_guide` | `guide_stage` → `discharge` | ۲ | «ترخیص از بیمارستان» | ۱۳۰۰ |

**چرا ۱ و ۲ اول آمدند؟** `appendectomy` دو مقاله از سه مقاله‌اش زنده بود و `cesarean` هم دو تا؛ ولی هر دو هاب («چیست و چه مراحلی دارد؟» / «بعد از سزارین چه مراقبتی؟») را کم داشتند. بدون هاب، آن چهار مقاله‌ی زنده به ستون مادر وصل نبودند. **هاب سزارین منتشر شد؛ هاب آپاندکتومی هنوز نه.**

**چرا ۳ تا ۵؟** `hernia-repair` تنها خوشه‌ی کامل‌نشده‌ی فاز ۱ است. فتق اینگوینال یکی از پرجستجوترین عمل‌های جراحی عمومی است. **هاب فتق منتشر شد؛ «قبل» و «بعد» هنوز در انتظارند و همین دو، فاز ۱ را می‌بندند.**

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
| ۲ | `cesarean-recovery` | ✅ `draft` | ✅ | ✅ | ✅ منتشرشده (۱۴۰۵/۰۸/۰۳) |
| ۳ | `hernia-overview` | ✅ `draft` | ✅ | ✅ | ✅ منتشرشده (۱۴۰۵/۰۸/۰۳) |
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
6. علامت زدن ردیف همان مقاله در جدول بخش ۶ و به‌روزرسانی عدد پیشرفت در بخش ۱ (۳۱ → ۳۲ و …).

---

## ۸. دستهٔ سوم — ۴۴ بستهٔ ۱۴۰۵/۰۸/۰۳ (همه آماده)

- **تصویر (سیاست تازه):** هیچ نوشته‌ی فارسی روی تصویر نیست؛ تصاویر روشن‌تر، ساده‌تر و کلوزآپ‌تر شدند و تنها نشان لاتین «HanyarJarahi» با کد قالب `0.12.6` روی آن‌ها می‌نشیند. پنج بسته‌ای که پیش‌تر تصویر تولیدی داشتند (`anesthesia-and-memory`، `intubation-explained`، `home-wound-care`، `dvt-pulmonary-embolism-prevention`، `discharge-checklist`) تصویر خودِ شما را گرفتند. نقشه‌ی کامل در `docs/content/IMAGE-INVENTORY.fa.md`.
- **بسته‌ی تصویر:** `releases/featured-images-clean.zip` شامل ۸۴ تصویر بدون متن برای جایگزینی تصویر مقاله‌های زنده (`T-15`).
- **گیت:** هر ۴۴ بسته از `tools/content-lint.py` عبور کرد و ZIP شد؛ هیچ بسته‌ای بدون تصویر نماند.
- **سئوی دسته:** پرسش‌های متداول صفحه با اسکیمای `article.json` یکی شده است، همه‌ی لینک‌های بیرونی منابع لینک واقعی دارند، و لینک داخلی به صفحه‌ای که قرار است ۳۰۱ شود زده نشده است.

| # | نامک | CPT | وضعیت |
|---:|---|---|---|
| ۱ | `appendectomy-overview` | `surgery` | ✅ آماده |
| ۲ | `hernia-preparation` | `surgery` | ✅ آماده |
| ۳ | `hernia-recovery` | `surgery` | ✅ آماده |
| ۴ | `laparoscopy-overview` | `surgery` | ✅ آماده |
| ۵ | `laparoscopy-preparation` | `surgery` | ✅ آماده |
| ۶ | `laparoscopy-recovery` | `surgery` | ✅ آماده |
| ۷ | `children-and-anesthesia` | `patient_guide` | ✅ آماده |
| ۸ | `elderly-and-surgery` | `patient_guide` | ✅ آماده |
| ۹ | `emergency-vs-elective-surgery` | `patient_guide` | ✅ آماده |
| ۱۰ | `outpatient-vs-inpatient-surgery` | `patient_guide` | ✅ آماده |
| ۱۱ | `intubation-explained` | `patient_guide` | ✅ آماده (بازبینی) |
| ۱۲ | `anesthesia-and-memory` | `patient_guide` | ✅ آماده (بازبینی) |
| ۱۳ | `home-wound-care` | `patient_guide` | ✅ آماده (بازبینی) |
| ۱۴ | `dvt-pulmonary-embolism-prevention` | `patient_guide` | ✅ آماده (بازبینی) |
| ۱۵ | `discharge-checklist` | `patient_guide` | ✅ آماده (بازبینی) |
| ۱۶ | `cesarean-recovery` | `surgery` | ✅ بازبینی محتوای زنده |
| ۱۷ | `hernia-overview` | `surgery` | ✅ بازبینی محتوای زنده |
| ۱۸ | `cataract-overview` | `surgery` | ✅ آماده |
| ۱۹ | `cataract-preparation` | `surgery` | ✅ آماده |
| ۲۰ | `cataract-recovery` | `surgery` | ✅ آماده |
| ۲۱ | `general-preparation-before-surgery` | `patient_guide` | ✅ آماده |
| ۲۲ | `questions-before-surgery` | `patient_guide` | ✅ آماده |
| ۲۳ | `medical-history-disclosure` | `patient_guide` | ✅ آماده |
| ۲۴ | `smoking-and-surgery` | `patient_guide` | ✅ آماده |
| ۲۵ | `diabetes-and-surgery` | `patient_guide` | ✅ آماده |
| ۲۶ | `obesity-and-anesthesia` | `patient_guide` | ✅ آماده |
| ۲۷ | `cholecystectomy-overview` | `surgery` | ✅ آماده |
| ۲۸ | `cholecystectomy-preparation` | `surgery` | ✅ آماده |
| ۲۹ | `cholecystectomy-recovery` | `surgery` | ✅ آماده |
| ۳۰ | `tonsillectomy-overview` | `surgery` | ✅ آماده |
| ۳۱ | `tonsillectomy-preparation` | `surgery` | ✅ آماده |
| ۳۲ | `tonsillectomy-recovery` | `surgery` | ✅ آماده |
| ۳۳ | `thyroidectomy-overview` | `surgery` | ✅ آماده |
| ۳۴ | `thyroidectomy-preparation` | `surgery` | ✅ آماده |
| ۳۵ | `thyroidectomy-recovery` | `surgery` | ✅ آماده |
| ۳۶ | `kidney-stone-overview` | `surgery` | ✅ آماده |
| ۳۷ | `kidney-stone-preparation` | `surgery` | ✅ آماده |
| ۳۸ | `kidney-stone-recovery` | `surgery` | ✅ آماده |
| ۳۹ | `circumcision-overview` | `surgery` | ✅ آماده |
| ۴۰ | `circumcision-preparation` | `surgery` | ✅ آماده |
| ۴۱ | `circumcision-recovery` | `surgery` | ✅ آماده |
| ۴۲ | `hemorrhoid-overview` | `surgery` | ✅ آماده |
| ۴۳ | `hemorrhoid-preparation` | `surgery` | ✅ آماده |
| ۴۴ | `hemorrhoid-recovery` | `surgery` | ✅ آماده |

**عددهای پس از ایمپورت:** زنده ۳۱ → ۷۳ (۳۹٫۷٪ از ۱۸۴). این به‌روزرسانی در `T-10` ثبت شده است.

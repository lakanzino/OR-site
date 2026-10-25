# بک‌لاگ محتوا — کارهای باز، بلاکرها و قاعده‌های ثابت

- **تاریخ تدوین:** ۲۰۲۶-۱۰-۲۵ (۳ آبان ۱۴۰۵)
- **جایگاه این سند:** تنها مرجع کارهای باز محتوا. هر چیزی که «باید بعداً انجام شود» اینجا نوشته می‌شود، نه در گفتگو. وقتی کاری بسته شد، ردیفش با ✅ می‌ماند تا سابقه از دست نرود.
- **مالک:** کارفرما (تصمیم پزشکی و انتشار) + ایجنت (متن، تصویر، بسته‌بندی، اسناد).

---

## ۱. گیت اجباری پیش از هر انتشار

هیچ بسته‌ای بدون عبور از این گیت ZIP نمی‌شود:

```
python3 tools/content-lint.py content/drafts/<slug>/
```

گیت چه چیزی را چک می‌کند:

| گروه | قاعده |
|---|---|
| سئو | عنوان ۲۰ تا ۶۵ نویسه، متای توضیح ۱۲۰ تا ۱۶۵ نویسه، متن ۱۰۰۰ تا ۱۶۰۰ کلمه، حداقل ۶ تیتر H2، بدون H1 داخل متن |
| لینک | ۳ تا ۷ لینک داخلی که همه‌شان زنده یا در همین دسته باشند، حداقل ۲ منبع بیرونی با لینک واقعی |
| ساختار | حداقل ۳ پرسش متداول، `focus_keyword` و `search_intent` پر، حداقل ۳ کلیدواژه، متن alt تصویر بالای ۴۰ نویسه و متفاوت از عنوان |
| علائم هوش مصنوعی | فهرست عبارت‌های ماشینی ممنوع؛ سقف خط تیره‌ی بلند در متن اصلی؛ تکرار بیش از حد شروع یکسان پاراگراف‌ها |
| ایمنی پزشکی | ممنوعیت دوز دارو، ساعت ناشتایی عددی، درصد آماری، نام تجاری دارو؛ الزام بخش «۱۱۵»، منابع و سلب مسئولیت |
| یکپارچگی | متن پرسش‌های متداولِ صفحه باید مو‌به‌مو با آرایه‌ی `faq` در `article.json` یکی باشد (شرط اسکیمای FAQ گوگل) |

**قاعده‌ی دائمی:** اگر متای `article.json` عوض شد، بخش پرسش‌های متداول `content.html` از همان مانیفست بازنویسی می‌شود؛ دستی ویرایش نکنید.

---

## ۲. کارهای باز

| شناسه | کار | نوع | وضعیت | معیار پذیرش |
|---|---|---|---|---|
| `T-01` | تصویر شاخص برای `cholecystectomy-overview` | بلاکر انتشار | ✅ بسته شد — با پرامپت اتاق عمل خالی (بدون دست جراح و ابزار در پیش‌زمینه) تولید و نشان‌گذاری شد | فایل `1200×675` با نشان فارسی، گیت سبز و ZIP ساخته شد |
| `T-02` | ریدایرکت ۳۰۱ از `anesthesia-consultation` به `informed-consent-anesthesia-consult` | بلاکر سئو | 🟡 باز — تصمیم گرفته شده، کد نوشته نشده | `inc/redirects.php` روی `template_redirect` با `wp_safe_redirect(301)`، تحمل اسلش انتهایی و کوئری؛ نسخه‌ی فرزند `0.12.4`؛ هدف نقشه‌راه ۱۸۴ → ۱۸۳ و زنده ۳۱ → ۳۰ |
| `T-03` | برگه‌ی «عمل سرپایی و بستری» در `guide_stage` | بهبود ساختار | 🟡 باز — مقاله فعلاً روی والد `special-groups` نشسته | افزودن `outpatient-inpatient` به `orsite_content_map()` و بامپ `ORSITE_STRUCTURE_VERSION` به `1.0.1` (بذر اصطلاحات idempotent است و چیزی را پاک نمی‌کند)؛ سپس تغییر `taxonomy` مقاله |
| `T-17` | جمله‌ی قالبی «دسترسی به آن‌ها در … بررسی شده است» در ۴۴ مقاله | کیفیت/سئو | ✅ حذف شد و گیت تازه اضافه شد | گیت `content-lint.py` حالا هر جمله‌ای را که در ۶ بسته یا بیشتر تکرار شود «boilerplate» می‌شناسد و رد می‌کند (به‌جز سلب مسئولیت و جمله‌ی تقدم دستور پزشک) |
| `T-18` | افزونهٔ «اتصال تصاویر رسانه به مقاله‌ها» | بلاکر انتشار | ✅ ساخته شد — `releases/orsite-media-matcher.zip` | نصب افزونه، اجرای «پیش‌نمایش» و بعد «اجرا کن»؛ برای دو تصویر جاافتاده دکمهٔ «از مخزن بگیر» همان صفحه را کامل می‌کند |
| `T-19` | تصویر شاخص همهٔ مقاله‌های زنده | بلاکر انتشار | ✅ **بسته شد** — ۲۰۲۶-۱۰-۲۵، تأییدشده با REST API سایت: **۶۴ از ۶۴** مقاله تصویر شاخص دارند | — |
| `T-15` | جایگزینی تصویر شاخص مقاله‌های زنده با نسخه‌ی بدون متن فارسی | بلاکر انتشار | ✅ **بسته شد** — تصاویر نصب و وصل شدند؛ سایت ۶۴ از ۶۴ | بسته‌ی `releases/featured-images-clean.zip` (۸۴ فایل) آپلود شود؛ برای هر مقاله تصویر شاخص جدید انتخاب شود، سپس `alt` با صحنه‌ی تازه یکی شود |
| `T-16` | واترمارک «HamyarJarahi» روی همه‌ی تصاویر | بهبود برند | ✅ کد نوشته شد — در `0.12.7` | نصب فرزند `0.12.7`؛ پس از نصب، نشان روی همه‌ی تصاویر شاخص (زنده و آینده) خودکار ظاهر می‌شود و هیچ فایل تصویری تغییر نمی‌کند |
| `T-14` | فعال‌سازی «تشخیص خودکار بخش» در ایمپورتر | بلاکر ایمپورت | ✅ کد نوشته شد — در `0.12.4`؛ تا نصب نشدنش، در فرم، گزینه‌ی «تشخیص خودکار از article.json» را دستی انتخاب کنید | نصب `0.12.4` و ایمپورت یک بسته‌ی آزمایشی از منوی اشتباه، سپس بررسی اینکه در CPT درست نشسته باشد |
| `T-04` | اجرای اجباری گیت در ساخت ZIP | فرایند | 🟡 باز | اسکریپت ساخت بسته پیش از zip، `tools/content-lint.py` را صدا بزند و روی خروجی غیرصفر متوقف شود |
| `T-05` | تکمیل آینه‌ی متنی مقالات زنده‌ی بدون آینه | مستندسازی | 🟡 باز — شمار زنده ۲۰۲۶-۱۰-۲۵ به **۷۳** رسید (تأیید کارفرما) و آینه فقط ۵ مقاله دارد؛ شکاف فعلی **۶۸** | `content/live-mirror/` برای همه‌ی ۷۳ مقاله‌ی زنده فایل داشته باشد |
| `T-06` | تصمیم درباره‌ی دو تصویر ذخیره (`1000104459`، `1000104428`) | محتوا | 🟡 باز | هر دو در مقاله‌ی آینده‌ی مرتبط مصرف شوند یا برای همیشه کنار گذاشته شوند |
| `T-07` | مقاله‌ی `blood-donation-reserve-before-surgery` | محتوا | ⛔ باز — تصویر رد شد | مقاله با تصویر تازه (تولیدی) ساخته شود، نه با فایل ردشده |
| `T-08` | صف فاز بعدی | محتوا | 🟡 باز | دسته‌ی چهارم با هشت خوشه بسته شد. صف بعدی: `septoplasty` (خوشه‌ی سه‌تایی)، سپس خوشه‌های g2 و g3؛ پیشنهاد جانبی: `glossary` و `student_content` که هنوز صفر مقاله دارد |
| `T-09` | فیلتر تحریریه‌ی بسته‌ها | دروازه‌ی انتشار | 🟡 باز | کنترل منابع معتبر، لحن غیرقطعی، ساختار و لینک‌ها برای هر بسته پیش از `publish`؛ مسئولیت هرگونه بازبینی تخصصی بیرونی با صاحب سایت است و در سیستم ثبت نمی‌شود |
| `T-10` | به‌روزرسانی عددهای زنده | مستندسازی | ✅ **بسته شد** — عددها با بازبینی زندهٔ ۲۰۲۶-۱۰-۲۵ در `PUBLISH-PRIORITY`، `CONTENT-MAP`، چنج‌لاگ و README اصلاح شدند | — |
| `T-20` | تصویر شاخص اختصاصی برای `glossary-surgical-terms` | بلاکر کیفیت | ✅ **فایل ساخته شد** — `content/images/featured/glossary-surgical-terms.webp` (۲۰ کیلوبایت، ۱۲۰۰×۶۷۵). نصب روی سایت با افزونهٔ ۱٫۲٫۰ | اجرای دکمهٔ «از مخزن بگیر» در افزونهٔ ۱٫۲٫۰؛ تصویر شاخص واژه‌نامه خودکار عوض می‌شود |
| `T-21` | بازگرداندن ۱۲ تصویر زنده به مخزن | ریسک پشتیبان | 🟡 باز — تصویرشان فقط روی سایت است | دانلود نسخهٔ `-featured` از سایت، پردازش با قاعدهٔ تصویر، ذخیره با نامک دقیق در `content/images/featured/`، بازسازی بستهٔ تصاویر |
| `T-22` | انتشار ۹ بستهٔ آماده | بلاکر رشد | ✅ **بسته شد — ۲۰۲۶-۱۰-۲۵**؛ کارفرما ایمپورت را تأیید کرد: شمار زنده ۶۴ → **۷۳** (۳۴٫۸٪ → ۳۹٫۷٪ هدف ۱۸۴). بازبینی نهایی، بستهٔ `BATCH-04-PUBLISH-9.zip` و راهنمای ترتیب ایمپورت در `docs/reports/publish-batch-04-2026-10-25.fa.md` | — |
| `T-23` | نوشتن ۲۰ مقالهٔ خوشهٔ «اتاق عمل، تیم و تجهیزات» | محتوا | 🟡 باز — ۱ از ۲۰ آماده شد: `why-operating-room-is-sterile` (۲۰۲۶-۱۰-۲۵، گیت سبز، منابع WHO/CDC/PMC). **تصویر بقیه از قبل آماده است** | `surgical-light-and-table`، `sponge-and-instrument-count`، `surgeon-and-assistant-role`، `scrub-technician-role`، `scrub-gown-sterile-field-patient`، `circulating-*`، `recovery-and-ward-nurse-role`، `recovery-to-ward-and-discharge`، `patient-site-identification`، `surgical-safety-checklist-patient`، `surgical-suction-device`، `surgical-instrument-set-intro`، `tourniquet-pads-body-attachments`، `vital-signs-monitor-explained`، `monitoring-under-anesthesia-and-family`، `electrosurgery-cautery-explained`، `why-operating-room-is-cold`، `why-operating-room-is-sterile` ✅ بستهٔ پیش‌نویس آماده، `general-post-op-care`، `exercise-activity-after-surgery` |
| `T-24` | پاک‌سازی ۲۴ فایل تصویر تکراری | بهداشت رسانه | 🟡 باز — اختیاری، چیزی را خراب نمی‌کند | ۲۱ نام دو یا سه نسخه دارند؛ جدیدترین بماند، بقیه حذف. قاعده در گزارش بازبینی زنده آمده |
| `T-26` | کامل‌کردن تعریف ۱۰۰ اصطلاح پرجستجوی واژه‌نامه | سئو | 🟡 باز — زیرساخت آماده است | هر اصطلاح با تعریف ≥ ۲۰۰ نویسه خودکار وارد ایندکس می‌شود؛ بقیه `noindex` می‌مانند |
| `T-27` | رساندن `or_environment` از ۱ به ۳ مقاله | سئو | 🟡 باز — ۲۰ تصویر آماده منتظرند | با مقالهٔ سوم، آرشیو خودکار از noindex خارج می‌شود |
| `T-28` | تاریخ‌های دستی جامانده در محتوای برگه‌ها | کیفیت | 🟡 باز — خروجی خودکار پاک شد | خروجی از ۰٫۱۳٫۰ یکدست شمسی است؛ اصلاح متن اصلی اختیاری |
| `T-29` | اجرای بستهٔ شفافیت روی «سرزمین آریان» | پروژهٔ دیگر | 🟡 باز — نیاز به دسترسی | `docs/reports/ARYAN-TRANSPARENCY-KIT.md` آماده است؛ مخزن یا دسترسی آریان لازم است |
| `T-30` | بهینه‌سازی سئوی هر ۳۳ مقالهٔ جراحی (۱۱ خوشه) طبق SOP هشت‌بندی ۲۰۲۶-۱۰-۲۵ | سئو/محتوا | 🟡 باز — هشت خوشه از یازده خوشه کامل و گیت‌سبز است: آپاندیس، فتق، سزارین، آب‌مروارید، کوله‌سیستکتومی، سنگ کلیه، لاپاروسکوپی و تانسیلکتومی (۲۴ بسته) با SOP هشت‌بندی، FAQ شش‌تایی H3، چک‌لیست/جدول تغذیه، شانزده تصویر آموزشی تمام‌انگلیسی با کپشن فارسی و بیست‌وچهار تصویر شاخص تازه (مجموع ۳۹ فایل تصویر منبع، شامل سبک جدید تامبنیل انگلیسی برای overview)؛ دو اسلاگ فقط‌زندهٔ سزارین و دو اسلاگ آپاندیس از WXR بازسازی شدند. جزئیات پیوست‌های ۳ تا ۹ سند برنامه. ایمپورت figureها منوط به T-32. ترتیب قطعی سه خوشهٔ باقی‌مانده: thyroidectomy، circumcision، hemorrhoid (۹ بسته) | هر ۳۳ مقاله (۲۹ بسته + ۴ اسلاگ فقط‌زنده که از WXR بازسازی می‌شوند) با الگوی تیتر، پاسخ Featured Snippet، چک‌لیست، جدول تغذیه، کادر Red Flags و FAQ پنج‌تا هشت‌تایی H3 بازنویسی و گیت‌سبز شوند؛ ترتیب قدیمی‌ترین اول با موج آپاندیس؛ sterile تا پایان کنار می‌ماند و یکجا تحویل می‌شود |
| `T-31` | افزودن اسکیمای HowTo به ایمپورتر/قالب | توسعه | 🟡 باز — بند ۴ SOP به آن وابسته است | فیلد `how_to` در مانیفست + ذخیره در متای پست + خروجی JSON-LD در `article-importer.php` با الگوی `hj_importer_faq_schema` + راستی‌آزمایی در گیت و بامپ نسخهٔ فرزند؛ بدون نصب روی سایت زنده بدون تأیید کارفرما |
| `T-32` | پشتیبانی ایمپورتر از تصاویر درون‌متنی پوشهٔ `assets/` بسته | توسعه | 🟡 باز — سیاست تصویری SOP (بند ۴-الف) به آن وابسته است | اسکن `content.html` برای `<img>` با مسیر محلی بسته، آپلود هر فایل `assets/*.webp` در کتابخانهٔ رسانه، بازنویسی src به URL رسانه، حفظ `<figure>`/`<figcaption>` پس از `wp_kses_post`؛ افزودن قاعدهٔ گیت برای وجود alt و کپشن فارسی هر figure؛ بامپ نسخهٔ فرزند؛ بدون نصب روی سایت زنده بدون تأیید کارفرما |
| `T-13` | تکمیل خوشهٔ آپاندیس: `appendectomy-preparation` و `appendectomy-recovery` | محتوا | 🟡 باز | دو مقاله با همان فرمول سه‌گانه، سپس خوشهٔ آپاندیس کامل می‌شود |
| `T-11` | خارج کردن `imagetool (25).zip` از مخزن | پاک‌سازی | ✅ بسته شد در همین دسته | فایل از ایندکس git حذف و در `.gitignore` ماند |
| `T-12` | یکسان‌سازی پرسش‌های متداول صفحه با `article.json` | کیفیت | ✅ بسته شد برای ۲۱ بسته | بازنویسی خودکار از مانیفست + قاعده‌ی دائمی در بخش ۱ |

---

## ۳. وضعیت این دسته

| گروه | تعداد | وضعیت |
|---|---:|---|
| بسته‌ی تازه‌ی آماده‌ی ایمپورت | ۴۲ | ✅ گیت سبز، ZIP ساخته شد |
| بازبینی محتوای دو مقاله‌ی زنده | ۲ | ✅ پرسش‌های متداول یکسان شد؛ ZIP تازه جای نسخه‌ی قبلی را گرفت |
| متن کامل، تصویر در انتظار | ۰ | ✅ `T-01` بسته شد |

عددهای واقعی: زنده ۳۱ → **۷۳** پس از ایمپورت این دسته (۳۹٫۷٪ از ۱۸۴).

## ۴. چهل‌ودو بسته‌ی تازه‌ی آماده‌ی ایمپورت

| # | نامک | CPT | تاکسونومی | کلمه |
|---:|---|---|---|---:|
| 1 | `appendectomy-overview` | `surgery` | `surgery-overview` · `appendectomy` | 1٫455 |
| 2 | `hernia-preparation` | `surgery` | `before-surgery` · `hernia-repair` | 1٫120 |
| 3 | `hernia-recovery` | `surgery` | `after-surgery` · `hernia-repair` | 1٫199 |
| 4 | `laparoscopy-overview` | `surgery` | `surgery-overview` · `general-laparoscopy` | 1٫035 |
| 5 | `laparoscopy-preparation` | `surgery` | `before-surgery` · `general-laparoscopy` | 1٫090 |
| 6 | `laparoscopy-recovery` | `surgery` | `after-surgery` · `general-laparoscopy` | 1٫049 |
| 7 | `children-and-anesthesia` | `patient_guide` | `children` | 1٫158 |
| 8 | `elderly-and-surgery` | `patient_guide` | `elderly` | 1٫084 |
| 9 | `emergency-vs-elective-surgery` | `patient_guide` | `emergency-elective` | 1٫069 |
| 10 | `outpatient-vs-inpatient-surgery` | `patient_guide` | `special-groups` (تا `T-03`) | 1٫027 |
| 11 | `intubation-explained` | `patient_guide` | `anesthesia-types` | 1٫112 |
| 12 | `anesthesia-and-memory` | `patient_guide` | `anesthesia-types` | 1٫110 |
| 13 | `home-wound-care` | `patient_guide` | `infection-prevention` | 1٫177 |
| 14 | `dvt-pulmonary-embolism-prevention` | `patient_guide` | `warning-signs` | 1٫141 |
| 15 | `discharge-checklist` | `patient_guide` | `discharge` | 1٫161 |
| 16 | `cataract-overview` | `surgery` | `surgery-overview` · `cataract` | 1٫217 |
| 17 | `cataract-preparation` | `surgery` | `before-surgery` · `cataract` | 1٫168 |
| 18 | `cataract-recovery` | `surgery` | `after-surgery` · `cataract` | 1٫084 |
| 19 | `general-preparation-before-surgery` | `patient_guide` | `general-preparation` | 1٫128 |
| 20 | `questions-before-surgery` | `patient_guide` | `questions-to-ask` | 1٫002 |
| 21 | `medical-history-disclosure` | `patient_guide` | `questions-to-ask` | 1٫126 |
| 22 | `smoking-and-surgery` | `patient_guide` | `general-preparation` | 1٫055 |
| 23 | `diabetes-and-surgery` | `patient_guide` | `general-preparation` | 1٫091 |
| 24 | `obesity-and-anesthesia` | `patient_guide` | `general-preparation` | 1٫002 |
| 25 | `cholecystectomy-overview` | `surgery` | `surgery-overview` · `cholecystectomy` | 1٫182 |
| 26 | `cholecystectomy-preparation` | `surgery` | `before-surgery` · `cholecystectomy` | 1٫133 |
| 27 | `cholecystectomy-recovery` | `surgery` | `after-surgery` · `cholecystectomy` | 1٫128 |
| 28 | `tonsillectomy-overview` | `surgery` | `surgery-overview` · `tonsillectomy` | 1٫063 |
| 29 | `tonsillectomy-preparation` | `surgery` | `before-surgery` · `tonsillectomy` | 1٫002 |
| 30 | `tonsillectomy-recovery` | `surgery` | `after-surgery` · `tonsillectomy` | 1٫017 |
| 31 | `thyroidectomy-overview` | `surgery` | `surgery-overview` · `thyroidectomy` | 1٫079 |
| 32 | `thyroidectomy-preparation` | `surgery` | `before-surgery` · `thyroidectomy` | 1٫088 |
| 33 | `thyroidectomy-recovery` | `surgery` | `after-surgery` · `thyroidectomy` | 1٫187 |
| 34 | `kidney-stone-overview` | `surgery` | `surgery-overview` · `kidney-stone` | 1٫195 |
| 35 | `kidney-stone-preparation` | `surgery` | `before-surgery` · `kidney-stone` | 1٫047 |
| 36 | `kidney-stone-recovery` | `surgery` | `after-surgery` · `kidney-stone` | 1٫004 |
| 37 | `circumcision-overview` | `surgery` | `surgery-overview` · `circumcision` | 1٫058 |
| 38 | `circumcision-preparation` | `surgery` | `before-surgery` · `circumcision` | 1٫009 |
| 39 | `circumcision-recovery` | `surgery` | `after-surgery` · `circumcision` | 1٫070 |
| 40 | `hemorrhoid-overview` | `surgery` | `surgery-overview` · `hemorrhoid-anal` | 1٫085 |
| 41 | `hemorrhoid-preparation` | `surgery` | `before-surgery` · `hemorrhoid-anal` | 1٫051 |
| 42 | `hemorrhoid-recovery` | `surgery` | `after-surgery` · `hemorrhoid-anal` | 1٫019 |

دو بسته‌ی دیگرِ این دسته، `cesarean-recovery` و `hernia-overview`، از قبل روی سایت زنده‌اند و فقط محتوایشان بازبینی شد؛ ZIP آن‌ها برای جایگزینی نسخه‌ی قبلی ساخته شده است.

هشت خوشه‌ی کامل: لاپاروسکوپی، کیسه صفرا، لوزه، تیروئید، سنگ کلیه، سزارین، ختنه و هموروئید. خوشه‌ی آپاندیس با `T-13` بررسی شد و «قبل» و «بعد» آن از قبل زنده‌اند؛ پس کامل است. خوشه‌ی فتق مقاله‌ی «قبل» و «بعد» دارد و نسخه‌ی زنده‌اش بازبینی شد.

## ۵. قاعده‌های ثابتی که در این دسته تثبیت شدند

1. متن فارسی پس از نوشتن با یک اسکن نویسه‌های CJK/کره‌ای/ژاپنی بازبینی می‌شود؛ نشت نویسه‌ی غیرفارسی یک خطای واقعی است.
2. خط تیره‌ی بلند در متن اصلی حداکثر ۶ بار؛ در فهرست منابع آزاد است.
3. پرسش‌های متداول صفحه و مانیفست همیشه یکی هستند.
4. لینک داخلی به صفحه‌ای که قرار است ۳۰۱ شود زده نمی‌شود.
5. هر تصویر تازه پیش از استفاده در بسته، از نظر چهره‌ی قابل شناسایی، خون، **نبود هر متن فارسی** و ابعاد بازبینی می‌شود.
6. **هیچ جمله‌ی قالبی تکراری مجاز نیست.** گیت، جمله‌های تکرارشده در ۶ بسته یا بیشتر را رد می‌کند؛ فقط سلب مسئولیت و جمله‌ی «دستور پزشک … مقدم است» استثنا هستند.
7. **سیاست تصویر از ۱۴۰۵/۰۸/۰۳ عوض شد:** هیچ نوشته‌ی فارسی روی تصویر نمی‌نشیند؛ تنها نشان لاتین «HamyarJarahi» با کد قالب (`0.12.6`) و به‌صورت لایه‌ی CSS روی تصویر می‌آید. تصویر باید روشن‌تر، ساده‌تر و کلوزآپ‌تر باشد.

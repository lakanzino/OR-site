# فهرست و قاعده‌ی تصاویر شاخص (منبع رسمی)

- **تاریخ تدوین:** ۲۰۲۶-۱۰-۲۵ (۳ آبان ۱۴۰۵) — بازنویسی‌شده پس از تغییر سیاست تصویر
- **جایگاه این سند:** مرجع واحد تصویر شاخص. جایگزین کامل `IMAGE-MAPPING-REPORT.md` در شاخه‌ی `images` است که **منسوخ** شده: فهرست «مقالات بدون تصویر» آن غلط بود و شماره‌هایش با هم نمی‌خواند. به آن سند استناد نکنید.
- **وضعیت:** ۸۶ تصویر شاخص آماده در `content/images/featured/`، همه دقیقاً `1200×675` و WebP، **هیچ‌کدام متن فارسی روی تصویر ندارند**.

---

## ۱. قاعده‌ی تصویر (ثابت — نسخه‌ی ۲)

1. **مقاله‌ای که تصویر شما دارد، همان تصویر را می‌گیرد.** هیچ تصویر تولیدی جای عکس شما را نمی‌گیرد.
2. **تصویر تولیدی فقط برای مقاله‌ی بدون تصویر** ساخته می‌شود.
3. **تصویر نامناسب استفاده نمی‌شود**، حتی اگر تنها گزینه باشد. مقاله بدون تصویر می‌ماند و در بک‌لاگ ثبت می‌شود.
4. **هیچ نوشته‌ی فارسی روی تصویر نمی‌نشیند.** نشان فارسی قدیمی (مستطیل سرمه‌ای با دو خط متن) از همه‌ی فایل‌ها حذف شد و دیگر ساخته نمی‌شود.
5. **واترمارک لاتین با کد اعمال می‌شود، نه با ویرایش فایل.** قالب فرزند از نسخه‌ی `0.12.6` هر تصویر شاخص را با فیلتر `post_thumbnail_html` می‌پیچد و نشان «HamyarJarahi» را روی آن می‌گذارد: بدون زمینه، ریز، با فاصله‌ی حروف، سمت چپ و پایین‌تر از نیمه‌ی پایین کادر. حرف `H` سبز روشن `#22c55e`، بقیه‌ی حروف سرمه‌ای `#002d5f`. چون این لایه CSS است، روی تصاویر فعلی و هر تصویر آینده‌ی سایت خودکار می‌نشیند و در فایل ذخیره نمی‌شود.
6. **فرمول تصویر:** عکس روشن و واقع‌گرا، کلوزآپ‌تر از قبل، پس‌زمینه‌ی سفید با لهجه‌ی سرمه‌ای یا آبی ملایم، نور روز نرم، عمق میدان کم، بی‌چهره، بدون خون، بدون متن و لوگو.
7. **کادر و پردازش:** اگر نشان فارسی روی فایل قدیمی باشد، نوار بالای تصویر بریده و کادر روی `16:9` دوباره بسته می‌شود (`tools/clean-image.py`)، سپس روشنایی `+۵٪`، کنتراست `+۵٪`، شارپنس `+۱۵٪` و زوم ملایم `۱٫۰۶`. خروجی `1200×675`، WebP کیفیت ۸۲، زیر ۱۸۰ کیلوبایت، نام فایل = نامک دقیق انگلیسی.
8. **متن alt** توصیف صحنه‌ی واقعی است، نه تکرار عنوان و نه متن روی تصویر.

---

## ۲. فهرست کامل (۸۶ فایل)

| نامک مقاله/برگه | شناسه‌ی فایل اصلی | منبع | حجم WebP |
|---|---|---|---:|
| `anesthesia-and-memory` | `1000104309` | عکس کارفرما | 43 KB |
| `anesthesia-consultation` | `1000104428` | عکس کارفرما | 65 KB |
| `anesthesia-machine-explained` | `1000104468` | عکس کارفرما | 66 KB |
| `anesthesiologist-and-technician-role` | `1000104454` | عکس کارفرما | 40 KB |
| `appendectomy-overview` | — | تولیدی | 46 KB |
| `cast-splint-bandage-drain-guide` | `1000104470` | عکس کارفرما | 51 KB |
| `cataract-overview` | — | تولیدی | 28 KB |
| `cataract-preparation` | — | تولیدی | 21 KB |
| `cataract-recovery` | — | تولیدی | 71 KB |
| `children-and-anesthesia` | — | تولیدی | 58 KB |
| `cholecystectomy-overview` | — | تولیدی | 64 KB |
| `cholecystectomy-preparation` | — | تولیدی | 34 KB |
| `cholecystectomy-recovery` | — | تولیدی | 60 KB |
| `circulating-technician-role` | `1000104456` | عکس کارفرما | 39 KB |
| `circumcision-overview` | — | تولیدی | 29 KB |
| `circumcision-preparation` | — | تولیدی | 41 KB |
| `circumcision-recovery` | — | تولیدی | 38 KB |
| `companion-role-admission-day` | `1000104435` | عکس کارفرما | 39 KB |
| `cssd-and-support-staff-role` | `1000104458` | عکس کارفرما | 83 KB |
| `diabetes-and-surgery` | `1000104418` | عکس کارفرما | 34 KB |
| `discharge-checklist` | `1000104446` | عکس کارفرما | 65 KB |
| `dvt-pulmonary-embolism-prevention` | `1000104443` | عکس کارفرما | 55 KB |
| `early-walking-after-surgery` | `1000104437` | عکس کارفرما | 48 KB |
| `elderly-and-surgery` | — | تولیدی | 74 KB |
| `electrosurgery-cautery-explained` | `1000104462` | عکس کارفرما | 25 KB |
| `emergency-vs-elective-surgery` | — | تولیدی | 40 KB |
| `exercise-activity-after-surgery` | `1000104450` | عکس کارفرما | 45 KB |
| `general-post-op-care` | `1000104448` | عکس کارفرما | 50 KB |
| `general-preparation-before-surgery` | `1000104319` | عکس کارفرما | 63 KB |
| `hemorrhoid-overview` | — | تولیدی | 29 KB |
| `hemorrhoid-preparation` | — | تولیدی | 36 KB |
| `hemorrhoid-recovery` | — | تولیدی | 31 KB |
| `hernia-preparation` | — | تولیدی | 36 KB |
| `hernia-recovery` | — | تولیدی | 44 KB |
| `home-wound-care` | `1000104442` | عکس کارفرما | 40 KB |
| `informed-consent-anesthesia-consult` | `1000104433` | عکس کارفرما | 35 KB |
| `intubation-explained` | `1000104307` | عکس کارفرما | 55 KB |
| `kidney-stone-overview` | — | تولیدی | 32 KB |
| `kidney-stone-preparation` | — | تولیدی | 23 KB |
| `kidney-stone-recovery` | — | تولیدی | 54 KB |
| `laparoscopy-overview` | — | تولیدی | 65 KB |
| `laparoscopy-preparation` | — | تولیدی | 40 KB |
| `laparoscopy-recovery` | — | تولیدی | 52 KB |
| `medical-history-disclosure` | `1000104415` | عکس کارفرما | 50 KB |
| `medications-before-surgery` | `1000104432` | عکس کارفرما | 41 KB |
| `mental-preparation-before-surgery` | `1000104427` | عکس کارفرما | 33 KB |
| `monitoring-under-anesthesia-and-family` | `1000104317` | عکس کارفرما | 36 KB |
| `obesity-and-anesthesia` | `1000104426` | عکس کارفرما | 50 KB |
| `operating-room-moment-by-moment` | `1000104313` | عکس کارفرما | 34 KB |
| `outpatient-vs-inpatient-surgery` | — | تولیدی | 75 KB |
| `patient-site-identification` | `1000104316` | عکس کارفرما | 35 KB |
| `post-surgery-nutrition` | `1000104384` | عکس کارفرما | 54 KB |
| `post-surgery-pain` | `1000104436` | عکس کارفرما | 41 KB |
| `preoperative-fasting` | `1000104430` | عکس کارفرما | 28 KB |
| `preoperative-tests` | `1000104434` | عکس کارفرما | 39 KB |
| `questions-before-surgery` | `1000104414` | عکس کارفرما | 36 KB |
| `recovery-and-ward-nurse-role` | `1000104457` | عکس کارفرما | 32 KB |
| `recovery-to-ward-and-discharge` | `1000104447` | عکس کارفرما | 58 KB |
| `return-to-work-after-surgery` | `1000104449` | عکس کارفرما | 37 KB |
| `scrub-gown-sterile-field-patient` | `1000104314` | عکس کارفرما | 54 KB |
| `scrub-technician-role` | `1000104455` | عکس کارفرما | 74 KB |
| `smoking-and-surgery` | `1000104417` | عکس کارفرما | 52 KB |
| `sponge-and-instrument-count` | `1000104464` | عکس کارفرما | 73 KB |
| `surgeon-and-assistant-role` | `1000104453` | عکس کارفرما | 54 KB |
| `surgical-instrument-set-intro` | `1000104463` | عکس کارفرما | 105 KB |
| `surgical-light-and-table` | `1000104466` | عکس کارفرما | 49 KB |
| `surgical-safety-checklist-patient` | `1000104315` | عکس کارفرما | 50 KB |
| `surgical-site-infection` | `1000104439` | عکس کارفرما | 40 KB |
| `surgical-suction-device` | `1000104461` | عکس کارفرما | 66 KB |
| `surgical-team-roles` | `1000104452` | عکس کارفرما | 121 KB |
| `suture-removal` | `1000104440` | عکس کارفرما | 48 KB |
| `thyroidectomy-overview` | — | تولیدی | 30 KB |
| `thyroidectomy-preparation` | — | تولیدی | 33 KB |
| `thyroidectomy-recovery` | — | تولیدی | 58 KB |
| `tonsillectomy-overview` | — | تولیدی | 34 KB |
| `tonsillectomy-preparation` | — | تولیدی | 40 KB |
| `tonsillectomy-recovery` | — | تولیدی | 32 KB |
| `tourniquet-pads-body-attachments` | `1000104465` | عکس کارفرما | 34 KB |
| `vital-signs-monitor-explained` | `1000104467` | عکس کارفرما | 47 KB |
| `warning-signs-after-surgery` | `1000104441` | عکس کارفرما | 32 KB |
| `what-is-operating-room` | `1000104420` | عکس کارفرما | 68 KB |
| `what-not-to-eat-before-surgery` | `1000104431` | عکس کارفرما | 61 KB |
| `why-operating-room-is-cold` | `1000104312` | عکس کارفرما | 51 KB |
| `why-operating-room-is-sterile` | `1000104311` | عکس کارفرما | 101 KB |
| `cesarean-recovery` | — | تولیدی (بدون متن) | 53 KB |
| `hernia-overview` | — | تولیدی (بدون متن) | 51 KB |

«—» در ستون شناسه یعنی تصویر تولیدی است و فایل اصلی ندارد. همه‌ی فایل‌ها از نسخه‌ی بدون متن فارسی بازسازی شده‌اند.

## ۳. تصاویر ردشده (استفاده نمی‌شوند)

| شناسه | موضوع روی تصویر | دلیل رد |
|---|---|---|
| `1000104236` | تفاوت بیهوشی عمومی و بی‌حسی نخاعی | کلاژ دو صحنه‌ی چسبیده؛ حس یک عکس واحد ندارد |
| `1000104416` | ذخیره‌ی خون قبل از عمل | کیسه‌های خون باز؛ برای صفحه‌ی بیمار آزارنده |

مقاله‌ی `blood-donation-reserve-before-surgery` به همین دلیل **بدون تصویر** ماند و در بک‌لاگ ثبت شد؛ با تصویر تازه ساخته می‌شود، نه با همین فایل.

---

## ۴. تصاویر تکراری و ذخیره

| شناسه | وضعیت | یادداشت |
|---|---|---|
| `1000104438` | کنار گذاشته | تقریباً یکسان با `1000104384`؛ نسخه‌ی `1000104384` برای `post-surgery-nutrition` انتخاب شد |
| `1000104469` | کنار گذاشته | تقریباً یکسان با `1000104443`؛ نسخه‌ی `1000104443` برای `dvt-pulmonary-embolism-prevention` ماند |
| `1000104459` | ذخیره | عنوان «فضاهای اتاق عمل و مناطق استریل» با `1000104460` هم‌پوشانی دارد؛ برای مقاله‌ی آینده‌ی همین موضوع نگه داشته می‌شود |
| `1000104428` | ذخیره | تصویر «مشاوره بیهوشی پیش از عمل»؛ پس از ۳۰۱ شدن `anesthesia-consultation` آزاد می‌شود و برای مقاله‌ی بعدی همین خوشه به کار می‌آید |

---

## ۵. تصاویر در انتظار تولید

هیچ. `cholecystectomy-overview` با پرامپت اتاق عمل خالی (بدون دست جراح و ابزار در پیش‌زمینه) تولید شد و `T-01` بسته شد.

## ۶. تصاویر تولیدی (۳۱ فایل)

تصاویر تولیدی با فرمول بند ۱ ساخته شده‌اند و **متن فارسی روی آن‌ها نیست**؛ فهرست کامل با حجم در بخش ۲ آمده است («—» در ستون شناسه). دسته‌بندی:

| دسته | تعداد | نامک‌ها |
|---|---:|---|
| هشت خوشهٔ کامل جراحی | ۲۴ | `cataract-*`، `cholecystectomy-*`، `circumcision-*`، `hemorrhoid-*`، `kidney-stone-*`، `laparoscopy-*`، `thyroidectomy-*`، `tonsillectomy-*` (سه‌تایی) |
| دو خوشهٔ ناتمام | ۳ | `appendectomy-overview`، `hernia-preparation`، `hernia-recovery` |
| راهنماهای عمومی | ۴ | `children-and-anesthesia`، `elderly-and-surgery`، `emergency-vs-elective-surgery`، `outpatient-vs-inpatient-surgery` |

> شش مقالهٔ `general-preparation-before-surgery`، `questions-before-surgery`، `medical-history-disclosure`، `smoking-and-surgery`، `diabetes-and-surgery` و `obesity-and-anesthesia` تصویر خودِ کارفرما گرفتند؛ نسخهٔ تولیدی قدیمی‌شان کنار گذاشته شد.

**جمع تصاویر شاخص آماده: ۸۴ فایل** (۵۳ تصویر خودِ کارفرما + ۳۱ تصویر تولیدی).

---

## ۷. تحویل تصاویر به سایت

| بسته | محتوا | استفاده |
|---|---|---|
| `releases/featured-images-clean.zip` | همه‌ی ۸۶ تصویر شاخص بدون متن فارسی، با نام فایل = نامک | جایگزینی تصویر مقاله‌های زنده‌ای که تصویرشان نشان فارسی داشت |
| `releases/article-packages/*.zip` | هر بسته شامل `featured.webp` تازه | ایمپورت دوباره‌ی بسته، تصویر شاخص را به‌روز می‌کند |
| `releases/lax-theme-child.zip` (نسخه‌ی `0.12.6`) | کد واترمارک خودکار | نصب یک‌باره؛ از آن پس نشان «HamyarJarahi» روی همه‌ی تصاویر شاخص می‌نشیند |

> واترمارک در فایل تصویر نیست؛ لایه‌ی CSS روی تصویر است. پس اگر روزی رنگ یا جای آن عوض شد، فقط یک استایل تغییر می‌کند و لازم نیست ۸۴ فایل دوباره ساخته شوند.

---

## ۹. پوشش زندهٔ سایت (بازبینی ۲۰۲۶-۱۰-۲۵ از REST API)

| شاخص | عدد |
|---|---:|
| مقالهٔ منتشرشده | ۶۴ |
| از آن‌ها با تصویر شاخص | **۶۴** |
| تصویر با نام دقیقِ نامک مقاله | ۶۳ |
| استثنا | `glossary-surgical-terms` → فایل عمومی `featured.webp` (`T-20`) |
| فایل تصویری در رسانهٔ سایت | ۱۱۵ |
| نام یکتا | ۹۱ |
| تصویر بدون مقاله | ۲۸ → ۲۰ مقالهٔ نانوشته + ۶ بستهٔ منتشرنشده + لوگو + `featured.webp` |
| آپلود تکراری | ۲۱ نام، ۲۴ فایل اضافی (`T-24`) |
| تصویر زنده که در مخزن نیست | ۱۲ (`T-21`) |

**نتیجه:** قاعدهٔ «هر مقاله تصویر خودش را دارد» روی سایت اجرا شد. ۸۶ فایل `content/images/featured/` برای ۶۴ مقالهٔ زنده کافی است به‌جز واژه‌نامه، و ۲۰ فایل باقی‌مانده تصویر مقالاتی است که هنوز نوشته نشده‌اند.

جزئیات کامل: `docs/reports/LIVE-SITE-AUDIT-2026-10-25.md`

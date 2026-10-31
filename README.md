# or-site — سایت آموزشی اتاق عمل

قالب‌های وردپرس و اسناد پروژه‌ی **«همیار جراحی»** — سایت آموزشی اتاق عمل و راهنمای بیمار.

🌐 دامنه‌ی رسمی: **[hamyarjarahi.ir](https://hamyarjarahi.ir)** — نام سایت: **همیار جراحی**

| جزء | نسخه | مسیر |
|---|---|---|
| قالب والد `lax-theme` | 1.0.1 | [`themes/lax-theme/`](themes/lax-theme/) |
| قالب فرزند `lax-theme-child` (سربرگ شاخهٔ اصلی) | 0.26.6 | [`themes/lax-theme-child/`](themes/lax-theme-child/) |
| افزونهٔ `orsite-media-matcher` | 1.2.0 | [`plugins/orsite-media-matcher/`](plugins/orsite-media-matcher/) |
| افزونهٔ `hamyar-symptom-guide` | 0.5.0 | [`plugins/hamyar-symptom-guide/`](plugins/hamyar-symptom-guide/) |
| ساختار محتوا (`ORSITE_STRUCTURE_VERSION`) | 1.0.0 | `themes/lax-theme-child/inc/content-types.php` |

این شماره‌ها از فایل‌های شاخهٔ اصلی خوانده شده‌اند و نشان‌دهندهٔ نسخهٔ نصب‌شده روی سایت نیستند. آخرین ریلیز فرزند در GitHub تگ [`child-v0.30.1`](https://github.com/lakanzino/OR-site/releases/tag/child-v0.30.1) است که روی یک کامیت مخصوص انتشار نشسته و با سورس شاخهٔ اصلی یکسان نیست؛ آرشیو و مانیفست در همان تگ قرار دارند. **آخرین نصب تأییدشده: ۰٫۳۰٫۱ روی سایت زنده (۳۱ اکتبر ۲۰۲۶)** — شاهد: صفحهٔ «بررسی همگام‌سازی پروفایل» با نتیجهٔ `fixed_line_found` و هش `8e5e662f…b59881` ([گزارش](docs/reports/child-v0301-install-confirmed-2026-10-31.fa.md)). این شاهد فقط همان فایل و نسخه را می‌پوشاند، نه سلامت کل سایت. اختلاف نسخه‌ها و ایرادهای ابزار علائم در [گزارش بررسی ۰٫۳۰٫۰](docs/reports/symptom-checker-version-audit-2026-10-30.fa.md) ثبت شده‌اند.

## وضعیت زندهٔ سایت (بررسی‌شده از REST API — ۲۰۲۶-۱۰-۲۵)

| شاخص | عدد |
|---|---:|
| مقالهٔ منتشرشده | **۶۴** (۲۴ جراحی + ۳۹ راهنمای بیمار + ۱ اتاق عمل) |
| تصویر شاخص دارد | **۶۴ / ۶۴** (واژه‌نامه از ۱٫۲٫۰ تصویر اختصاصی دارد) |
| بستهٔ آمادهٔ منتشرنشده | ۹ |
| پیشرفت از ۱۸۴ مقالهٔ هدف | ۳۴٫۸٪ |

گزارش کامل: [`docs/reports/LIVE-SITE-AUDIT-2026-10-25.md`](docs/reports/LIVE-SITE-AUDIT-2026-10-25.md)

## افزونهٔ راهنمای علائم

افزونهٔ وردپرس [`plugins/hamyar-symptom-guide/`](plugins/hamyar-symptom-guide/) برگه‌ای مستقل و پیش‌نویس بدون شورت‌کد برای بررسی و انتشار می‌سازد. راهنمای نصب و محدودیت‌ها در README افزونه است.

**نسخهٔ ۰.۱.۲ افزونه — نصب مطمئن:** پس از آنکه فعال‌سازی ۰.۱.۱ روی سایت با پیام «مشکل جدی» شکست خورد، فعال‌سازی بی‌خطر شد (ساخت برگه در پیشخوان بعدی)، فایل‌ها با نگهبان بارگذاری می‌شوند و هر خطا در پیشخوان با فایل/خط/نسخه‌ها گزارش می‌شود. بسته: [`releases/hamyar-symptom-checker-0.1.2.zip`](releases/hamyar-symptom-checker-0.1.2.zip). [راهنمای نصب و عیب‌یابی](docs/symptom-checker/phase-5/release-0.1.2.fa.md)

**نسخهٔ ۰.۱.۳ افزونه — تداخل نام تابع با پوسته برطرف شد:** علت شکست فعال‌سازی روی سایت زنده پیدا شد: تابع `hj_sc_enqueue_assets()` هم در پوستهٔ ۰.۳۰.۱ (`inc/symptom-checker-test.php`) و هم در افزونه تعریف شده بود و PHP خطای «تعریف دوباره» می‌داد. همهٔ توابع افزونه به پیشوند `hjsc_` رفتند و دروازهٔ تداخل نام اضافه شد. بسته: [`releases/hamyar-symptom-checker-0.1.3.zip`](releases/hamyar-symptom-checker-0.1.3.zip) (SHA-256 `44fb67d2…4c28f`). آزمون: ۳۷/۳۷ در وردپرس ۶.۸.۳ و ۷.۱.۴ با mu-plugin هم‌نام پوسته. [راهنما](docs/symptom-checker/phase-5/release-0.1.3.fa.md)

**نسخهٔ ۰.۱.۱ افزونه — چهار شکاف اصلاح شد:** متن پایان پرسش‌نامه، دکمهٔ اصلاح تناقض، گزینه و قاعدهٔ فوری «ناتوانی در دفع ادرار»، و نمایش پرسش‌های کم‌آبی با تب/استفراغ. بسته: [`releases/hamyar-symptom-checker-0.1.1.zip`](releases/hamyar-symptom-checker-0.1.1.zip) (دادهٔ `0.2.0-draft`). ۸۱ آزمون موتور، ۲۵ سناریو، ۳۴/۳۴ وردپرس محلی، ۱۲/۱۲ + ۱۳/۱۳ سناریوی رابط. [راهنمای نصب و بازبینی](docs/symptom-checker/phase-5/release-0.1.1.fa.md)

**کش‌زدایی از گراف ماژول + تشخیص فایل کهنه (۰.۱.۱۵):** خرابی زندهٔ «does not provide an export named 'ageFromText'» ریشه‌اش ایمپورتهای داخلی بدون `?ver=` بود: کش مرورگر/LiteSpeed می‌توانست `app.js` تازه را با `age-gate.js` کهنه بالا بیاورد. حالا بسته‌ساز هر ایمپورت داخلی را با هش محتوای خودش نشانی می‌کند (`?ver=<sha8>`)، نگهبانِ صفحه در شکست بوت نام فایل ناسازگار را می‌نویسد (بدون هیچ درخواست شبکهٔ تازه، تا قرارداد حریم خصوصی دست‌نخورده بماند)، و برگهٔ میراثی با عنوان «…(پیش‌نمایش داخلی)» یک بار در پیشخوان به «ارزیابی احتمالی بیماری» تغییر نام می‌یابد. بسته: [`releases/hamyar-symptom-checker-0.1.15.zip`](releases/hamyar-symptom-checker-0.1.15.zip) — ۲۷ فایل، ۱۴۵٬۳۴۸ بایت، sha256 `cc46c17d…e32e8a`. [نصب](docs/symptom-checker/phase-8/release-0.1.15.fa.md)

**برگهٔ معمولی + تنظیمات در دسترس (۰.۱.۱۴):** برگه حالا یک برگهٔ عادی با شورت‌کد `[hamyar_symptom_checker]` است (در ویرایشگر دیده می‌شود و متن خودتان را می‌توانید بالایش بنویسید)، و منوی «دستیار نشانه‌ها ← تنظیمات نمایش» عنوان، جملهٔ دوم، متن کادر زرد، نمایش فهرست منابع، کمترین سن، سن شروع و روشن/خاموش بودن ابزار را در اختیار شما می‌گذارد. علت صفحهٔ آبی خالی هم پیدا و بسته شد: تگ `type="module"` حالا مستقیم چاپ می‌شود، به‌همراه نگهبانی که خطا را روی صفحه می‌نویسد. بسته: [`releases/hamyar-symptom-checker-0.1.14.zip`](releases/hamyar-symptom-checker-0.1.14.zip) — ۲۷ فایل، sha256 `dfcf6730…b7b1`. [گزارش](docs/symptom-checker/phase-8/report.fa.md) · [نصب](docs/symptom-checker/phase-8/release-0.1.14.fa.md)

**سنِ عددی و صفحهٔ اولِ تمیز (۰.۱.۱۳):** چرخ‌دندهٔ سن (که با هر حرکت، سن را تصادفی می‌پراند) حذف شد؛ کادر عددی با − و + و ↑/↓. صفحهٔ اول فقط سه چیز دارد: یک جمله که این ربات چیست، یک جمله که دقت جواب به دقت پاسخ‌ها بسته است، و سن. ۲۶ منبع واقعی (NHS، NIDDK/NIH، Cleveland Clinic، …) با پیوند مستقیم، و در آخر صفحه یک کادر زرد: «این ارزیابی هرگز جای معاینهٔ پزشکی نیست…». بسته: [`releases/hamyar-symptom-checker-0.1.13.zip`](releases/hamyar-symptom-checker-0.1.13.zip) — ۲۶ فایل، sha256 `4d95fbeb…95f8`. [گزارش](docs/symptom-checker/phase-7/report.fa.md) · [نصب](docs/symptom-checker/phase-7/release-0.1.13.fa.md)

**ناحیه‌محور شدن ابزار (۰.۱.۱۲):** ابزار حالا از کلی به جزئی می‌رود — سن، جنسیت، **نقشهٔ بدن با پنج ناحیهٔ رنگی**، زیرناحیه، بعد پرسش‌ها. بخش تناسلی کامل است (ادرار، آلت، بیضه، فرج و واژن، مقعد + مسیر عمومی). بستهٔ تازه: ۹۱ پرسش، ۲۴ موضوع، ۲۲ قاعدهٔ ایمنی، ۳۲ منبع. بسته: [`releases/hamyar-symptom-checker-0.1.12.zip`](releases/hamyar-symptom-checker-0.1.12.zip) — ۲۶ فایل، sha256 `22b62678…9798`. [گزارش](docs/symptom-checker/phase-6/report.fa.md) · [نقشهٔ بدن](docs/symptom-checker/phase-6/body-map.fa.md) · [نصب](docs/symptom-checker/phase-6/release-0.1.12.fa.md)

**اصلاح موبایل و دروازهٔ سن (۰.۱.۱۱):** نوار بالای صفحه با `.site-header` پوسته تصادم داشت و متن سفیدش روی زمینهٔ سفید گم می‌شد؛ با `body.hj-sc-page` قفل شد. یادداشت «پیش‌نمایش» به داخل کارت رفت، مجسمهٔ چرخان بالای کارت نشست، و ابزار با یک چرخ‌دندهٔ دستی برای تعیین سن شروع می‌شود (زیر ۱۸ سال متوقف می‌شود؛ سن ذخیره یا ارسال نمی‌شود). بسته: [`releases/hamyar-symptom-checker-0.1.11.zip`](releases/hamyar-symptom-checker-0.1.11.zip) — ۲۶ فایل، sha256 `15180ef5…abdb82`. [گزارش](docs/symptom-checker/phase-5/mobile-and-age-gate.fa.md) · [راهنمای نصب](docs/symptom-checker/phase-5/release-0.1.11.fa.md)

**پرسش تطبیقی (فاز ۴ رانبوک) — فقط در سایه:** ترتیب تطبیقی در `symptom-checker/engine/src/adaptive.js` ساخته شد؛ موتور با `shadowStrategy` آن را در هر گام حساب و در حافظه ثبت می‌کند (`session.shadowTrace()`) ولی صفحه همچنان `static_order` را می‌پرسد. شبیه‌ساز: `node tools/symptom-checker-adaptive/simulate.mjs --check` (۲ بسته، ۴۱۱ شخصیت، ۸/۸ قاعدهٔ ایمنی). **سوئیچ انجام نشد:** معیارها برآورده‌اند ولی برای آپاندیسیت و سنگ کلیه کارت دیرتر می‌آید و ترتیب تعداد پرسش را کم نمی‌کند. بسته: [`releases/hamyar-symptom-checker-0.1.10.zip`](releases/hamyar-symptom-checker-0.1.10.zip) — ۲۵ فایل، sha256 `f670d9b4…15655a`. [گزارش](docs/symptom-checker/phase-4-adaptive/shadow-study.fa.md) · [راهنمای نصب](docs/symptom-checker/phase-5/release-0.1.10.fa.md)

**فاز ۵ (آزمون ایمنی و محرمانگی) انجام شد — بازبینی انسانی باز است:** ۱۰ سناریوی حاشیه‌ای، ماتریس ۷ قاعدهٔ ایمنی، بازرس محرمانگی ایستا و دو آزمون محرمانگی زمان‌اجرا (رابط و وردپرس محلی) اضافه شد؛ ۷۶ آزمون موتور، ۶۴ آزمون دروازهٔ تألیف و ۳۴/۳۴ بررسی وردپرس. اجرا: `cd symptom-checker/engine && node --test`، `python3 tools/symptom-checker-audit/privacy_scan.py --zip releases/hamyar-symptom-checker-0.1.0.zip` و `node symptom-checker/ui/tests/ui-smoke.mjs --scenarios docs/symptom-checker/phase-5/scenarios-adversarial.v1.json --all`. چک‌لیست بازبینی انسانی: [`docs/symptom-checker/phase-5/ui-review-checklist.fa.md`](docs/symptom-checker/phase-5/ui-review-checklist.fa.md). [گزارش فاز ۵](docs/symptom-checker/phase-5-report.fa.md)

**فاز ۴ (افزونهٔ وردپرس) کامل شد:** افزونهٔ مستقل [`plugins/hamyar-symptom-checker/`](plugins/hamyar-symptom-checker/) یک برگهٔ **پیش‌نویس** با قالب اختصاصی می‌سازد، دارایی‌ها را فقط در همان برگه بار می‌گذارد، `noindex` می‌کند و فقط برای موضوع‌هایی لینک مقاله می‌سازد که یک نوشتهٔ منتشرشدهٔ متناظر داشته باشند. بستهٔ نصبی: [`releases/hamyar-symptom-checker-0.1.0.zip`](releases/hamyar-symptom-checker-0.1.0.zip). آزمون: `python3 tools/symptom-checker-plugin/build_package.py --check`، `python3 -m unittest discover -s tools/symptom-checker-plugin -p 'test_*.py'`، `node tools/symptom-checker-plugin/php-checks.mjs` و `node tools/symptom-checker-plugin/check-wordpress.mjs` (WordPress 6.8.3 محلی). [گزارش فاز ۴](docs/symptom-checker/phase-4-report.fa.md)

**فاز ۳ (رابط نمایشی) کامل شد:** نمونهٔ داخلی [`symptom-checker/ui/`](symptom-checker/ui/) همان موتور فاز ۲ را آفلاین در مرورگر اجرا می‌کند: کارت موضوع، هشدار فوری، «اطلاعات کافی نیست»، تفکیک هفت‌گروهی شواهد، ویرایش پاسخ و شروع دوباره؛ برچسب صریح «نمایشی / دادهٔ پیش‌نویس / بدون ذخیره‌سازی» و بدون اتصال به سایت. اجرا: `cd symptom-checker/ui && npm run serve` و باز کردن `http://localhost:8080/symptom-checker/ui/`؛ آزمون: `node --test` و `node tests/ui-smoke.mjs --all` (۱۰ آزمون واحد و ۲۲۸ بررسی سرتاسری). [گزارش فاز ۳](docs/symptom-checker/phase-3-report.fa.md)

**فاز ۲ (موتور) کامل شد:** موتور مستقل و بدون وردپرس در [`symptom-checker/engine/`](symptom-checker/engine/) با ۳۸ آزمون Node — شامل اجرای همان ۱۲ سناریوی فاز ۱ — و تفکیک شواهد هفت‌گانه. اجرا: `cd symptom-checker/engine && node --test` و `node bin/demo.js sc05`. [گزارش فاز ۲](docs/symptom-checker/phase-2-report.fa.md)

**فاز ۱ (داده و سؤال) شروع شد:** بستهٔ دانش پیش‌نویس خوشهٔ درد شکم/پهلو با قرارداد schema، ۱۷ سؤال مشترک، ۵ موضوع، لایهٔ ایمنی مستقل و ۱۲ سناریوی آزمون در [`docs/symptom-checker/phase-1/`](docs/symptom-checker/phase-1/) است؛ اعتبارسنج و ۲۱ آزمون خودکار: `python3 tools/symptom-checker-audit/validate_phase1.py` و `python3 tools/symptom-checker-audit/test_phase1.py`. هیچ انتشار یا صفحهٔ عمومی از این بسته ساخته نشده و تأیید محتوایی تیم باز است. [گزارش فاز ۱](docs/symptom-checker/phase-1-report.fa.md)

## نمونهٔ راهنمای علائم

نمونهٔ مستقل آموزشی در [`hamyarjarahi-frontend/`](hamyarjarahi-frontend/) قرار دارد. این ابزار تشخیصی یا اعتبارسنجی‌شده نیست. راه‌اندازی، منابع و محدودیت‌ها در [`docs/resources.md`](hamyarjarahi-frontend/docs/resources.md) آن آمده است.

## ساختار مخزن

- `themes/lax-theme/` — قالب والد (ارائه‌ی عمومی، راست‌چین، فونت وزیرمتن).
- `themes/lax-theme-child/` — قالب فرزند اختصاصی پروژه: ساختار محتوا، قالب مقاله با فهرست مطالب، طراحی راست‌به‌چپ Soft UI، فونت محلی و سایزهای تصویر بدون برش.
- `docs/` — قوانین فازبندی پروژه، تصمیم‌ها (ADR)، چنج‌لاگ کامل، رودمپ محتوا و گزارش گام‌ها.
  - `docs/content/PUBLISH-PRIORITY.fa.md` — **مرجع اولویت انتشار**: وضعیت واقعی سایت (بررسی‌شده از نقشه‌ی سایت)، ده مقاله‌ی جاری و پانزده مقاله‌ی بعدی.
- `content/drafts/<slug>/` — بسته‌های سه‌فایلی پیش‌نویس مقالات (`article.json` + `content.html` + `featured.webp`)؛ وضعیت همه `draft` تا عبور از فیلتر تحریریه و تأیید کارفرما. سازوکار «بازبین پزشکی» از نسخهٔ 0.14.0 قالب به‌طور کامل حذف شده است.
- `releases/article-packages/` — همان بسته‌ها به شکل ZIP آماده‌ی ایمپورت در پیشخوان.
- `plugins/orsite-media-matcher/` — افزونهٔ مستقل: تصویرهای رسانه را با نام فایل به مقاله‌ی هم‌نام وصل می‌کند و تصویر شاخص می‌گذارد؛ با پیش‌نمایش، گزارش و تکمیل خودکار از مخزن.
- `content/live-mirror/` — آینه‌ی متنی محتوای منتشرشده (اکنون فقط ۵ مقاله‌ی اول؛ شکاف آینه در `content/live-mirror/README.md` ثبت شده است).
- `design/` — پیش‌نمایش HTML طراحی جاری.
- `data/` — موجودی دسته‌بندی‌شده‌ی فایل‌های قالب والد.


## به‌روزرسانی از پیشخوان (قابلیت داخلی قالب)

قالب از نسخهٔ 0.5.0 صفحهٔ **«بروزرسانی گیت‌هاب»** را در پیشخوان دارد: ذخیرهٔ توکن، اتصال به همین
مخزن، مقایسهٔ نسخهٔ نصب‌شده با آخرین ریلیز، پیش‌نمایش فایل‌های تغییرکرده و به‌روزرسانی فایلی
(فقط فایل‌های متفاوت جایگزین می‌شوند).

از نسخهٔ 0.12.2 نسخهٔ تازه در سه جای پیشخوان دیده می‌شود: **ویجت «بروزرسانی قالب از گیت‌هاب»**
در صفحهٔ اول پیشخوان، **نشان در نوار مدیریت**، و **فهرست بومی «پیشخوان → بروزرسانی‌ها» / «نمایش → پوسته‌ها»**.
بررسی خودکار هر ۳ ساعت انجام می‌شود و کامیت تازهٔ شاخهٔ `main` هم (حتی بدون تگ ریلیز) به‌عنوان
بروزرسانی نمایش داده می‌شود.

نسخهٔ ریلیز از میان **همهٔ** انتشارها با `version_compare` انتخاب می‌شود، نه اولین آیتم فهرست API
(فهرست `/releases` گیت‌هاب در این مخزن از تازه به قدیم مرتب نیست). اگر اتصال برقرار نشود، صفحه
«قطع — دادهٔ ذخیره‌شده» را با خطای واقعی نشان می‌دهد و دکمهٔ **«تست کامل اتصال»** وضعیت API،
`releases.atom`، `raw.githubusercontent.com`، `codeload` و اعتبار توکن را می‌سنجد.

**قرارداد ریلیز:** تگ `child-vX.Y.Z` و سه فایل `lax-theme-child.zip`، `lax-theme.zip` و `orsite-release.json` در مسیر [`releases/`](releases/) همان تگ. به‌روزرسان در صورت وجود از فایل‌های پیوست Release استفاده می‌کند و در نبود آن‌ها نسخه‌های داخل مخزن را از مسیرهای جایگزین دریافت می‌کند. ریلیزهای 0.22.0، 0.22.1 و 0.22.2 فایل‌ها را داخل تگ دارند، نه به‌صورت پیوست Assets؛ ZIP خودکار «Source code» بستهٔ نصب قالب نیست.

## کنترل نسخه و بسته‌ها

برای بررسی همخوانی نسخهٔ قالب‌ها، افزونه‌های موجود، مانیفست، ZIPها و تاریخچهٔ چنج‌لاگ، از ریشهٔ مخزن اجرا کنید:

```sh
python3 themes/lax-theme-child/tests/test_release_versions.py
```

اصلاحات مستندات بعد از انتشار 0.22.0 در سورس ثبت شدند؛ بسته و تگ قدیمی ثابت مانده‌اند و بستهٔ 0.22.1 متن به‌روز را دربر دارد.

## نصب

1. قالب والد `lax-theme` را نصب کنید (فعال نکنید).
2. قالب فرزند `lax-theme-child` را نصب و فعال کنید.
3. پیوندهای یکتا را روی «نام نوشته» (`/%postname%/`) بگذارید و یک بار ذخیره کنید.
4. یک بار وارد پیشخوان شوید تا اصطلاحات ساختار محتوا ساخته شوند.

جزئیات نسخه‌ها و تغییرات: [`docs/PROJECT-CHANGELOG.md`](docs/PROJECT-CHANGELOG.md) — تصمیم‌های معماری: [`docs/DECISIONS.md`](docs/DECISIONS.md) — اولویت و صف انتشار: [`docs/content/PUBLISH-PRIORITY.fa.md`](docs/content/PUBLISH-PRIORITY.fa.md) — برنامه‌ی محتوا و لینک‌دهی داخلی: [`docs/CONTENT-MAP.md`](docs/CONTENT-MAP.md)

> متن‌های پزشکی را تیم محتوا می‌نویسد. بازبینی پزشکی در این سیستم انجام و الزامی نمی‌شود. بسته‌های ZIP آمادهٔ نصب در مسیر `releases/` هر تگ نگه‌داری می‌شوند و ممکن است به ریلیز نیز پیوست شده باشند.

# پروندهٔ افزونهٔ «دستیار نشانه‌ها» — همیار جراحی

این سند، فهرست و راهنمای پروندهٔ افزونه است: همان ساختاری که در Google Drive هم ساخته شده (پوشهٔ «افزونهٔ دستیار نشانه‌ها — همیار جراحی»). هر بخش، سند اصلی خودش را در همین مخزن دارد؛ اینجا فقط نقشه است تا هیچ‌چیز گم نشود.

نسخهٔ جاری: **۰.۱.۱۴** · تاریخ: ۳۱ اکتبر ۲۰۲۶

---

## ۱. خود افزونه (نصب، نسخه‌ها، بازگشت)

| نسخه | فایل | اندازه | وضعیت |
| --- | --- | --- | --- |
| ۰.۱.۰ | `releases/hamyar-symptom-checker-0.1.0.zip` | — | نسخهٔ بازگشتی پایه؛ هرگز حذف نمی‌شود |
| ۰.۱.۳ | `releases/hamyar-symptom-checker-0.1.3.zip` | — | آخرین نسخه‌ای که روی سایت زنده نصب شده |
| ۰.۱.۴ – ۰.۱.۷ | `releases/hamyar-symptom-checker-0.1.7.zip` | ۲۲ فایل، ۸۸٬۴۱۸ بایت | در `main`، روی سایت زنده تأیید نشده |
| ۰.۱.۸ | `releases/hamyar-symptom-checker-0.1.8.zip` | ۲۳ فایل، ۹۲٬۱۳۱ بایت<br>sha256 `df5e798ed214b9cb1075096340bc4e1afec1a61ee35975b217c43e34f8e18bc4` | انتشار محتوا بدون ریلیز (فاز ۵-الف)؛ نصب‌نشده |
| ۰.۱.۹ | `releases/hamyar-symptom-checker-0.1.9.zip` | ۲۴ فایل (۱ فایل تازه: `includes/admin.php`) | ویرایش محتوا در پیشخوان (فاز ۵-ب) |
| ۰.۱.۱۰ | `releases/hamyar-symptom-checker-0.1.10.zip` | ۲۵ فایل (۱ فایل تازه: `assets/engine/src/adaptive.js`)<br>sha256 `f670d9b4a92552a833bc54d75da6b555e6f3e98b4bec14762824cbece015655a` | ترتیب تطبیقی در حالت سایه (فاز ۴)؛ رفتار بازدیدکننده بی‌تغییر |
| ۰.۱.۱۱ | `releases/hamyar-symptom-checker-0.1.11.zip` | ۲۶ فایل (۱ فایل تازه: `assets/ui/age-gate.js`)<br>sha256 `15180ef5ed746424d9a5dca07b36d61864ca89575587674db6028c9f79abdb82` | اصلاح موبایل + دروازهٔ سن |
| ۰.۱.۱۲ | `releases/hamyar-symptom-checker-0.1.12.zip` | ۲۶ فایل (بستهٔ دانش جایگزین شد: `assets/data/knowledge-bundle.v3.json`)<br>sha256 `22b626787317874e3c6ebd6cbb6cbff0d0728353f689874482f7e5365adb9798` | شروع از ناحیهٔ بدن + بخش تناسلی کامل |
| ۰.۱.۱۳ | `releases/hamyar-symptom-checker-0.1.13.zip` | ۲۶ فایل<br>sha256 `4d95fbeb…95f8` | سنِ عددی، صفحهٔ اولِ تمیز، منابع با لینک |
| ۰.۱.۱۴ | `releases/hamyar-symptom-checker-0.1.14.zip` | ۲۷ فایل، ۱۴۳٬۶۲۹ بایت<br>sha256 `dfcf6730…b7b1` | برگهٔ معمولی، تنظیمات در wp-admin، رفع صفحهٔ خالی |
| **۰.۱.۱۵** | `releases/hamyar-symptom-checker-0.1.15.zip` | ۲۷ فایل، ۱۴۵٬۳۴۸ بایت<br>sha256 `cc46c17d1fa9be4e97e0163e34b72f3ba60c9c90d9dd8d28689b09efdea32e8a` | **تازه** — نشانی محتوایی برای هر ایمپورت داخلی (`?ver=<sha8>`)، نگهبانِ تشخیص فایل کهنه بدون درخواست شبکه، تغییر نام برگهٔ میراثی از برچسب «پیش‌نمایش داخلی» |

- راهنمای نصب و بازگشت ۰.۱.۱۵: [`docs/symptom-checker/phase-8/release-0.1.15.fa.md`](phase-8/release-0.1.15.fa.md) (۰.۱.۱۴: [`release-0.1.14.fa.md`](phase-8/release-0.1.14.fa.md) · ۰.۱.۱۳: [`release-0.1.13.fa.md`](phase-7/release-0.1.13.fa.md) · ۰.۱.۱۲: [`release-0.1.12.fa.md`](phase-6/release-0.1.12.fa.md))
- فاز ۸ — برگهٔ معمولی و تنظیمات: [`phase-8/report.fa.md`](phase-8/report.fa.md)
- فاز ۷ — صفحهٔ اول و سن: [`phase-7/report.fa.md`](phase-7/report.fa.md)
- فاز ۶ — ناحیه‌محور: [`phase-6/report.fa.md`](phase-6/report.fa.md) · [`phase-6/body-map.fa.md`](phase-6/body-map.fa.md)
- اصلاح موبایل و دروازهٔ سن: [`docs/symptom-checker/phase-5/mobile-and-age-gate.fa.md`](phase-5/mobile-and-age-gate.fa.md) (۰.۱.۹: [`release-0.1.9.fa.md`](phase-5/release-0.1.9.fa.md))
- مطالعهٔ فاز ۴ (سایه، اعداد، تصمیم سوئیچ‌نکردن): [`docs/symptom-checker/phase-4-adaptive/shadow-study.fa.md`](phase-4-adaptive/shadow-study.fa.md)
- **صفحهٔ محتوا در پیشخوان:** پیشخوان ← «دستیار نشانه‌ها» (`admin.php?page=hj-sc-content`) — وضعیت، فعال‌سازی/بازگشت، بارگذاری و ویرایش متن. [گزارش فاز ۵-ب](phase-5/admin-content-editor.fa.md)
- پیش از هر نصب: پشتیبان کامل (فایل‌ها + دیتابیس). هر ریلیز تصمیم خودش را دارد.
- نصب: پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه ← انتخاب زیپ ← نصب ← فعال‌سازی. **هیچ فایلی در `wp-config.php` لازم نیست.**
- بازگشت: نصب زیپ نسخهٔ قبلی روی همان افزونه (وردپرس جایگزین می‌کند).

## ۲. اطلاعات (ریلیزها و وضعیت)

- **تغییرات هر نسخه:** [`docs/PROJECT-CHANGELOG.md`](../PROJECT-CHANGELOG.md) (ورودی‌های «ابزار علائم»).
- **تصمیم‌ها و دلیل‌ها:** [`docs/symptom-checker/decision-log.fa.md`](decision-log.fa.md).
- **وضعیت فازها و کارهای باز:** [`docs/symptom-checker/runbook-v2.fa.md`](runbook-v2.fa.md).
- **فاز ۵-الف (انتشار محتوا بدون ریلیز):** [`docs/symptom-checker/phase-5/publish-without-release.fa.md`](phase-5/publish-without-release.fa.md).
- **فاز ۵-ب (ویرایش محتوا در پیشخوان):** [`docs/symptom-checker/phase-5/admin-content-editor.fa.md`](phase-5/admin-content-editor.fa.md).
- **README فنی افزونه** (قلاب‌ها، فیلترها، فایل‌ها، رفتار): [`plugins/hamyar-symptom-checker/README.md`](../../plugins/hamyar-symptom-checker/README.md).

## ۳. روند ساخت

- **گزارش توسعهٔ ۰.۱.۰:** [`docs/lax-robot/lax-robot-0.1.0/development-log.fa.md`](../lax-robot/lax-robot-0.1.0/development-log.fa.md) — فاز ۰ تا ۳: بازرسی سایت، مهاجرت داده، موتور، رابط، افزونه، ابزار نویسندگی.
- **تغییرات ۰.۲.۰ سند (فاز ۵-الف + کار کیفیت):** [`docs/lax-robot/lax-robot-0.2.0/changes.fa.md`](../lax-robot/lax-robot-0.2.0/changes.fa.md).
- **ابزارهای ساخت:**
  - `tools/symptom-checker-plugin/build_package.py` — ساخت زیپ از منبع (`--check` می‌سنجد زیپ با منبع یکی است).
  - `tools/symptom-checker-plugin/php-checks.mjs` — اجرای PHP واقعی (WASM) و ۲۴ سناریو / ۱۳۷ بررسی.
  - `tools/symptom-checker-plugin/check-wordpress.mjs` — ۶۳ بررسی در WordPress Playground (۶.۸.۳ و ۷.۱.۴).
  - `tools/symptom-checker-author/author.py` — اعتبارسنجی، ساخت بسته، `publish-bundle`.
  - `.github/workflows/` — همان دروازه‌ها در CI روی هر PR.

## ۴. معماری

- **سند کامل معماری ۰.۱.۰:** [`docs/lax-robot/lax-robot-0.1.0/architecture.fa.md`](../lax-robot/lax-robot-0.1.0/architecture.fa.md) — مرزهای موتور/بسته/رابط/افزونه، مدل داده، مسیر یک پاسخ از کلیک تا کارت نتیجه، و قاعده‌های ثابت.
- **ظرفیت واقعی و سقف رشد:** [`docs/lax-robot/lax-robot-0.2.0/capacity.fa.md`](../lax-robot/lax-robot-0.2.0/capacity.fa.md).
- خلاصهٔ معماری در یک نفس:
  1. **بستهٔ دانش** (`knowledge-bundle.*.json`) تنها منبع محتواست: نشانه‌ها، پرسش‌ها، گزینه‌ها، بیماری‌ها، قواعد.
  2. **موتور** فقط داده را می‌خواند و «تصویر شواهد» را می‌سازد؛ هیچ متن بالینی در کد نیست.
  3. **رابط** همان موتور را در مرورگر اجرا می‌کند؛ هیچ پاسخی ذخیره یا ارسال نمی‌شود.
  4. **افزونه** فقط دو فایل JSON را در همان دامنه سرو می‌کند: بستهٔ دانش و نقشهٔ مقاله‌ها. از ۰.۱.۸ بسته می‌تواند از `uploads/hj-sc/` بیاید، بی‌نیاز از ریلیز تازه.
  5. **ابزار نویسندگی** محتوا را می‌سنجد و بسته را می‌سازد؛ تست طلایی و هم‌ارزی نگهبان رفتار است.

## ۵. پژوهش ساخت

- **پژوهش کامل (با منابع):** [`docs/lax-robot/lax-robot-0.2.0/research.fa.md`](../lax-robot/lax-robot-0.2.0/research.fa.md) — دقت واقعی ابزارهای هم‌خانواده، الگوی خطا (بیش‌ارجاعی)، چارچوب «نباید از دست برود»، حاکمیت دانش و نسخه‌بندی، تحلیل ایستای ناسازگاری/ناقص‌بودن، و محدودهٔ مقرراتی.
- **فهرست نسخه‌های سند:** [`docs/lax-robot/README.md`](../lax-robot/README.md).
- **سه جملهٔ تصمیم‌ساز پژوهش:**
  1. دقت تشخیصی ابزارهای تجاری پایین است (تشخیص اول ۳۴٪، سه‌گزینهٔ اول ۵۱٪) و خطای اصلی‌شان **بیش‌ارجاعی** است؛ پس ما عدد و درصد نشان نمی‌دهیم و «احتمال» را با کلمه می‌گوییم.
  2. چارچوب ایمنی تشخیصی (نباید از دست برود / پرچم قرمز / دام) حکم می‌کند پرسش‌های ایمنی **قبل از هر رتبه‌بندی** پرسیده شوند — همان کاری که موتور از فاز ۲ می‌کند.
  3. خروجی «فهرست بیماری + فوریت» در اتحادیهٔ اروپا ابزار پزشکی است (MDR Rule 11، کلاس IIa و در تریاژ IIb) و سلب مسئولیت آن را از دامنه بیرون نمی‌برد. ما هیچ ادعای انطباق نمی‌کنیم؛ این سند فقط محدوده را روشن می‌کند.

## ۶. چه چیزی اثبات نشده

- ۰.۱.۴ تا ۰.۱.۸ روی سایت زنده نصب و دیده نشده‌اند؛ آزمون‌ها در WordPress Playground و PHP واقعی (WASM) بوده، نه روی `hamyarjarahi.ir`.
- کش LiteSpeed روی سایت واقعی با `?ver=<sha8>` آزموده نشده.
- کوله‌سیستیت در داده و تست حاضر است ولی منتشر نشده و مقاله‌ای به آن وصل نیست.
- فاز ۵-ب ساخته و آزموده شده (۰.۱.۹) ولی روی سایت زنده اجرا نشده؛ ویرایش پیشخوان بی‌درنگ منتشر می‌شود و دروازهٔ بازبینی ندارد.
- فاز ۴ (ترتیب تطبیقی) در ۰.۱.۱۰ فقط **سایه** است: محاسبه و ثبت می‌شود، پرسیده نمی‌شود. معیارهای سوئیچ برآورده‌اند ولی سوئیچ انجام نشده؛ پیش‌شرط‌ها در گزارش فاز ۴.

# مقایسهٔ محلی فهرست بیماری‌ها

این ابزار فقط شناسه‌ها و نام فارسی را میان فایل ابزار و فایل پروفایل مقایسه می‌کند. شناسهٔ تکراری، شناسهٔ مفقود یا شکل اشتباه داده را خطا می‌گیرد و نام غایب یا متفاوت را جدا گزارش می‌کند. برابر بودن شناسه و نام، همسانی بالینی یا انتشار در وردپرس را ثابت نمی‌کند. هیچ درخواستی به سایت، پایگاه داده یا شبکه نمی‌فرستد.

از ریشهٔ مخزن:

```sh
python3 -m unittest discover -s tools/symptom-checker-audit -p 'test_*.py' -v

git show child-v0.30.0:themes/lax-theme-child/data/symptom-checker/diseases.json | \
  python3 tools/symptom-checker-audit/compare_inventory.py \
    --checker - --profiles themes/lax-theme-child/data/disease-profiles.json
```

برای مقایسهٔ فایل‌های دیگر، به جای `-` مسیر فایل JSON را بدهید. خروجی JSON روی stdout است. دادهٔ نامعتبر یا ورودی غیرقابل خواندن کد خروج ۲ می‌دهد؛ وجود شناسهٔ فقط در یکی از دو فایل یا نام متفاوت، خطای اجرا نیست و در خروجی ثبت می‌شود؛ قبل از هر استفاده باید جداگانه بررسی شود. خطوط راهنمایی در [تابلو وظایف](../../docs/symptom-checker/task-board.fa.md) و نتایج در [فهرست تطبیق](../../docs/symptom-checker/data-inventory.fa.md) آمده‌اند.

## مقایسه با خروجی CSV وردپرس

برای CSV دارای ستون‌های `id,post_type,status,title,slug,url`:

```sh
python3 tools/symptom-checker-audit/compare_wp_export.py \
  --export /path/to/wordpress-export.csv \
  --profiles themes/lax-theme-child/data/disease-profiles.json
```

فقط بیماری‌های منتشرشده مقایسه می‌شوند؛ شناسه و نامک تکراری یا فیلد اجباری مفقود خطا می‌دهد. برابری نامک با شناسه صرفاً نامزد تطبیق است و برابری متای داخلی را ثابت نمی‌کند. فایل ورودی را که ممکن است شامل نوشته‌های غیرعمومی باشد به مخزن اضافه نکنید.

## بررسی خروجی کامل JSON وردپرس

```sh
python3 tools/symptom-checker-audit/compare_wp_json.py \
  --export /path/to/wordpress-export.json \
  --profiles themes/lax-theme-child/data/disease-profiles.json
```

این بررسی فقط رکوردهای بیماری منتشرشده و شناسه‌های ضروری را گزارش می‌کند؛ محتوای کامل یا دادهٔ پیش‌نویس را در خروجی چاپ نمی‌کند. هر تطبیق مربوط به زمان تهیهٔ فایل است. اختلاف تبدیل خط جدید به حرف `n` شناسایی می‌شود اما هرگز خودکار اصلاح نمی‌شود.

## بازرس محرمانگی ابزار علائم

```sh
python3 tools/symptom-checker-audit/privacy_scan.py                          # همان ۱۱ فایل ارسالی از مخزن
python3 tools/symptom-checker-audit/privacy_scan.py --zip releases/hamyar-symptom-checker-0.1.0.zip
python3 tools/symptom-checker-audit/privacy_scan.py --json                   # خروجی ماشین‌خوان
```

هفت دسته بررسی می‌شود: ذخیره‌سازی (`localStorage`، `sessionStorage`، `indexedDB`، کوکی، cache storage)، شبکه (`XMLHttpRequest`، WebSocket، `sendBeacon`، `wp_remote_*`، `curl_*`، `serviceWorker`) و هر `fetch()` جز دو فراخوان مجاز، هر نشانی مطلق `http(s)://`، ردیابی (gtag/GTM، Analytics، Hotjar، Clarity، Sentry، Matomo، Plausible)، تزریق HTML (`innerHTML`، `document.write`، `eval`)، دارایی بیرونی (script/img/link/font/`@import`) و فرم ارسالی. خطوط توضیحی (کامنت) نادیده گرفته می‌شوند؛ فایل‌های آزمون و ابزار ساخت عمداً در فهرست نیستند، چون دربارهٔ همین موضوع‌ها حرف می‌زنند.

خروجی ۰ یعنی پاک، ۱ یعنی یافته، ۲ یعنی خطای فراخوانی. خود بازرس ۱۴ آزمون منفی دارد (هر قاعده با یک تخلف کاشته‌شده آزموده می‌شود) و در همان `unittest discover` بالا اجرا می‌شود. این بازرسی **ایستا** است؛ اثبات زمان اجرا در `symptom-checker/ui/tests/ui-smoke.mjs` و `tools/symptom-checker-plugin/check-wordpress.mjs` است و هیچ‌کدام سایت زنده یا افزونه‌های دیگر نصب‌شده را نمی‌سنجد.

## آزمون وردپرس محلی مستقل

`./tools/symptom-checker-audit/check-local-wordpress.sh` یک نسخهٔ موقت Playground و WordPress 6.8.3 را دریافت می‌کند، هش آرشیو وردپرس را بررسی و وردپرس را با PHP 8.2 و SQLite راه‌اندازی می‌کند. فایل `local-wp-fixture.php` فقط روی آن وردپرس موقت دادهٔ ساختگی می‌سازد و خروجی آزمون‌های انتشار، پیش‌نویس، خصوصی، تکرار، شناسهٔ ناموجود و نحوهٔ ذخیرهٔ JSON چندخطی با و بدون `wp_slash` را بررسی می‌کند. پس از پایان، فایل‌های موقت حذف می‌شوند؛ هیچ ارتباطی با سایت زنده یا دادهٔ بیمار ندارد.

وابستگی‌ها: bash، Node.js، npm، curl، Python 3 و sha256sum، همراه دسترسی به npm و GitHub. نسخهٔ فعلی Playground روی Node 22 هشدار نسخهٔ پشتیبانی‌شده داد، اما آزمون محلی موفق بود؛ این هشدار باید در اجرای آینده با Node پشتیبانی‌شده بررسی شود. این فقط یک smoke test است و نصب افزونه یا سازگاری با سایت زنده را اثبات نمی‌کند. وابستگی‌های گذرای npm در این روش lock نشده‌اند.

## آزمون مسیر واقعی همگام‌سازی قالب

`tools/symptom-checker-audit/tests/run-profile-sync-wordpress.sh` یک وردپرس Playground موقت ایجاد می‌کند، تابع واقعی `hj_sync_disease_profiles()` را روی دادهٔ مخزن اجرا می‌کند و فیلدهای ۱۲۶ پروفایل را با مرجع، به‌اضافهٔ رفتار اجرای تکراری در همان نسخه، مقایسه می‌کند. این آزمون سایت زنده یا سازگاری با نسخهٔ نصب‌شدهٔ آن را تأیید نمی‌کند و نسخهٔ همگام‌سازی سایت را تغییر نمی‌دهد. الزامات شبکه و هشدار Node مانند آزمون محلی بالاست.

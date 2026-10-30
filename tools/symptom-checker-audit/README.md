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

## آزمون وردپرس محلی مستقل

`./tools/symptom-checker-audit/check-local-wordpress.sh` یک نسخهٔ موقت Playground و WordPress 6.8.3 را دریافت می‌کند، هش آرشیو وردپرس را بررسی و وردپرس را با PHP 8.2 و SQLite راه‌اندازی می‌کند. فایل `local-wp-fixture.php` فقط روی آن وردپرس موقت دادهٔ ساختگی می‌سازد و خروجی آزمون‌های انتشار، پیش‌نویس، خصوصی، تکرار و شناسهٔ ناموجود را بررسی می‌کند. پس از پایان، فایل‌های موقت حذف می‌شوند؛ هیچ ارتباطی با سایت زنده یا دادهٔ بیمار ندارد.

وابستگی‌ها: bash، Node.js، npm، curl، Python 3 و sha256sum، همراه دسترسی به npm و GitHub. نسخهٔ فعلی Playground روی Node 22 هشدار نسخهٔ پشتیبانی‌شده داد، اما آزمون محلی موفق بود؛ این هشدار باید در اجرای آینده با Node پشتیبانی‌شده بررسی شود. این فقط یک smoke test است و نصب افزونه یا سازگاری با سایت زنده را اثبات نمی‌کند. وابستگی‌های گذرای npm در این روش lock نشده‌اند.

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

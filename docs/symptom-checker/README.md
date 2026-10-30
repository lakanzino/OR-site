# چک‌کنندهٔ علائم: راهنمای نگهداری و افزودن بیماری

وضعیت: **آزمایشی و بدون بازبینی پزشکی**. همهٔ بیماری‌ها `reviewStatus: "unreviewed"` هستند. این ابزار تشخیص نمی‌دهد.

## ساختار
| فایل | نقش |
|---|---|
| `themes/lax-theme-child/data/disease-seed.json` | فهرست پایهٔ بیماری‌ها (id، نام فارسی و انگلیسی) |
| `themes/lax-theme-child/data/disease-profiles.json` | شناسنامه: علائم، «چه زمانی فوراً مراجعه کنید»، منابع (نام، بدون URL) |
| `tools/build-symptom-db.py` | قواعد برچسب‌گذاری (`TAGS`، `RED_FLAGS`) و ساخت داده‌ی خروجی |
| `themes/lax-theme-child/data/symptom-checker/diseases.json` | خروجی ساخته‌شده (نباید دستی ویرایش شود) |
| `themes/lax-theme-child/data/symptom-checker/vocab.json` | برچسب‌ها، ناحیه‌ها و پرسش‌های خطر فوری (خروجی) |
| `themes/lax-theme-child/assets/js/symptom-checker-engine.js` | موتور امتیازدهی و انتخاب پرسش |
| `themes/lax-theme-child/assets/js/symptom-checker-ui.js` | رابط کاربری |
| `tools/validate-symptom-db.py` | اعتبارسنجی داده‌ی خروجی |
| `themes/lax-theme-child/tests/clinical-scenarios.json` | سناریوهای شبیه‌سازی‌شدهٔ ویزیت |

## افزودن یک بیماری (گام‌ها)
1. یک ردیف به `disease-seed.json` اضافه کنید: `id` (فقط حروف کوچک انگلیسی، عدد و `_`)، `nameFa`، `nameEn`.
2. شناسنامه را در `disease-profiles.json` بنویسید: حداقل ۲ علامت، حداقل ۲ مورد «چه زمانی فوراً مراجعه کنید»، و نام منابع.
3. اگر برچسب تازه‌ای لازم است، آن را در `TAGS` همان فایل `build-symptom-db.py` با برچسب فارسی، پرسش بله/خیر و ناحیه تعریف کنید.
4. اجرا کنید:
   ```bash
   python3 tools/build-symptom-db.py
   python3 tools/validate-symptom-db.py          # خطاها را رفع کنید
   python3 tools/validate-symptom-db.py --strict # هشدارها را هم بررسی کنید (بیماری‌های با کمتر از ۲ برچسب)
   node --test themes/lax-theme-child/tests/
   ```
   برای تست‌های Node، هر فایل را جدا اجرا کنید (`node --test <file>`).
5. اگر بیماری جدید مهم است، یک سناریو به `clinical-scenarios.json` اضافه کنید.

## قواعد که نباید شکسته شوند
- خروجی هیچ درمان خانگی ندارد. فهرست عبارت‌های ممنوع در `validate-symptom-db.py` است.
- درصد عددی نمایش داده نمی‌شود؛ سطح همخوانی (بالا، نسبی، ضعیف) نمایش داده می‌شود.
- پرسش‌های خطر فوری قبل از هر پرسش دیگری پرسیده می‌شوند. «بله» یعنی نتیجهٔ اورژانس.
- شمارهٔ اورژانس `115` است.
- هیچ منبعی بدون بازبینی اضافه نشود. منابع فعلی فقط نام‌اند.

## محدودیت‌های شناخته‌شده
- برچسب‌ها هنوز با کلیدواژه ساخته شده‌اند؛ ۳۹ بیماری کمتر از ۲ برچسب دارند و برای آن‌ها تطبیق ضعیف است.
- سناریوهای فعلی ۵ مورد است، نه مجموعهٔ آماری. دقت Top-3 هنوز اندازه‌گیری آماری نشده است.
- هیچ‌کدام از تست‌های مرورگر (Playwright) هنوز نوشته یا اجرا نشده‌اند.
- بازبینی پزشکی انجام نشده است.

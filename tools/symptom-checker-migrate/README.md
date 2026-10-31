# ابزارهای مهاجرت بستهٔ دانش (فاز ۱)

سه ابزار، به همین ترتیب:

```sh
python3 tools/symptom-checker-migrate/v1_to_v2.py            # ساخت/بازسازی بستهٔ پایهٔ v2
python3 tools/symptom-checker-migrate/v1_to_v2.py --check    # آیا فایل کامیت‌شده کهنه است؟
python3 tools/symptom-checker-migrate/validate_v2.py         # اعتبارسنجی ساختاری و معنایی
node tools/symptom-checker-migrate/equivalence.mjs           # دروازهٔ عبور فاز ۱
python3 -m unittest discover -s tools/symptom-checker-migrate -p 'test_*.py'
```

## `v1_to_v2.py`

`knowledge-bundle.v1.json` + `evidence-map.v1.json` → `knowledge-bundle.v2.base.json` (بستهٔ **پایه**).

از فاز ۳ به بعد، بستهٔ تحویل‌شدنی `knowledge-bundle.v2.json` از پایه + `docs/symptom-checker/authoring/` با `tools/symptom-checker-author/author.py build-bundle` ساخته می‌شود. این اسکریپت فقط پایه را می‌سازد تا بازسازی مهاجرت، کار نویسندگان را پاک نکند. ورودی دستی ممنوع است؛ اگر کسی فایل v2 را دستی عوض کند، `--check` و دروازهٔ هم‌ارزی هر دو می‌گیرند.

- شناسهٔ نشانه: `s_pain_rlq` → `abd.pain_rlq` و شناسهٔ قدیمی در `legacy_id`.
- `symptom_links` → `profile` با `level: typical` (v1 سطح ندارد).
- `paths` → `overrides`، بی‌تغییر.
- `safety_rules`، `coverage_rules`، `sources`، `scope`، `disclaimers`، `answer_states`، `change_notes` عیناً منتقل می‌شوند.
- خروجی UTF-8، `ensure_ascii=False`، ترتیب ثابت و بدون خواندن خروجی خودش → ایدمپوتنت؛ SHA-256 چاپ می‌شود.

## `validate_v2.py`

فهرست فیلدهای اجباری را از خود فایل schema می‌خواند، پس سند و دروازه از هم جدا نمی‌افتند. خطاها: ارجاع شکسته، شناسهٔ تکراری، پروفایل بدون `typical`، منبع ناشناس، نبود `review_status`، override روی بیماری ناموجود، ناهماهنگی نسخه، فیلد احتمال/امتیاز. هشدارها: نشانه‌ای که هیچ گزینه‌ای گزارشش نمی‌کند (باید `not_covered` بماند، نه «ندارد») و نشانه‌ای که در هیچ پروفایلی نیست.

## `equivalence.mjs`

پاسخ‌های ثبت‌شده در `tests/golden/v1/` (۲۵ سناریو + ۵۰۰۰ مسیر) را به یک نشست با بستهٔ v2 می‌دهد و `result()` را بایت‌به‌بایت مقایسه می‌کند؛ همچنین برابری و **ترتیب** شواهد به‌ازای هر نشانه. `--quick` فقط ۱۰۰ مسیر را می‌سنجد، `--json` گزارش ماشین‌خوان می‌دهد.

`--random --count N --seed S` دروازه را از ۵۰۰۰ مسیر قفل‌شده جلوتر می‌برد: N مسیر تازه با seed داده‌شده هم‌زمان روی v1 و v2 اجرا و نتیجه‌ها مقایسه می‌شوند. اجرای شبانهٔ CI از همین حالت با seed زمانی استفاده می‌کند.

اختلاف = توقف و گزارش. عددهای طلایی هرگز ویرایش نمی‌شوند.

# بسته‌های آماده‌ی انتشار — ۲۰۲۶-۱۰-۲۷ (۹ مقاله)

- **وضعیت:** همه `draft` در مانیفست؛ فیلتر فنی `content-lint.py` با صفر خطا، فیلتر تحریریه انسانی انجام شد.
- **ساختار هر ZIP:** پوشه‌ی ریشه `<slug>/` شامل `article.json` + `content.html` + `featured.webp` (۱۲۰۰×۶۷۵، زیر ۱۸۰KB)؛ دو بسته‌ی overview دارای پوشه‌ی `assets/` با دو تصویر درون‌متنی.
- **بسته‌ی ترکیبی:** `BATCH-PUBLISH-2026-10-27-9packages.zip` (هر ۹ ZIP یک‌جا).
- **مقصد Google Drive:** پوشه «4 — بسته‌های انتشار ۲۰۲۶-۱۰-۲۷ (۹ مقاله آماده ایمپورت)» داخل «OR-SITE — بسته‌های مقالات جراحی».

## فهرست و وضعیت فیلترها

| # | نامک | نوع/تاکسونومی | کلمه | H2 | لینک داخلی | منبع خارجی | فیلتر فنی |
|---:|---|---|---:|---:|---:|---:|---|
| ۱ | `tonsillectomy-overview` | surgery / لوزه و آدنوئید | ۱۰۴۹ | ۱۱ | ۴ | ۳ | PASS |
| ۲ | `laparoscopy-overview` | surgery / لاپاروسکوپی عمومی | ۱۰۲۱ | ۱۲ | ۷ | ۳ | PASS |
| ۳ | `laparoscopy-preparation` | surgery / لاپاروسکوپی عمومی | ۱۰۷۶ | ۱۳ | ۶ | ۳ | PASS |
| ۴ | `circumcision-overview` | surgery / ختنه | ۱۰۴۴ | ۱۱ | ۳ | ۳ | PASS |
| ۵ | `circumcision-preparation` | surgery / ختنه | ۱۰۷۲ | ۱۲ | ۴ | ۳ | PASS |
| ۶ | `circumcision-recovery` | surgery / ختنه | ۱۰۵۶ | ۱۲ | ۴ | ۳ | PASS |
| ۷ | `hemorrhoid-overview` | surgery / هموروئید و جراحی‌های مقعد | ۱۰۷۱ | ۱۲ | ۴ | ۳ | PASS |
| ۸ | `hemorrhoid-preparation` | surgery / هموروئید و جراحی‌های مقعد | ۱۰۳۷ | ۱۲ | ۵ | ۳ | PASS |
| ۹ | `hemorrhoid-recovery` | surgery / هموروئید و جراحی‌های مقعد | ۱۰۰۵ | ۱۲ | ۴ | ۳ | PASS |

## موارد needing توجه هنگام ایمپورت

1. **وابستگی لینک داخلی:** چهار مقاله به بسته‌های دیگری لینک دارند که هنوز زنده نیستند — `/patient-guide/children-and-anesthesia/`، `/patient-guide/home-wound-care/`، `/patient-guide/intubation-explained/`. تا انتشار آن‌ها این لینک‌ها موقتاً به صفحه‌ی جستجو/۴۰۴ می‌روند؛ هر سه بسته در `content/drafts/` موجود و از فیلتر فنی سبزند.
2. **تصاویر درون‌متنی:** فایل‌های `assets/` دو بسته‌ی overview داخل ZIP هست ولی در `content.html` هنوز تگ `<figure>` ندارند؛ ابزار «جراحی‌ها ← ترمیم تصاویر داخل مقاله» (قالب 0.15.1+) فقط `src` تگ‌های موجود را بازنویسی می‌کند و تزریق نمی‌کند. اتصال این تصاویر کار تحریریه‌ی جداگانه است و مانع ایمپورت نیست.
3. بعد از ایمپورت: LiteSpeed → Purge All، سپس مجموعه‌ی `LIVE` در `tools/content-lint.py` و شمارش `PUBLISH-PRIORITY` با عدد REST API به‌روز شود.

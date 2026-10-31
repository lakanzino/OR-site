# ابزار: symptom-checker-engine — نسخهٔ 0.1.7

**پوشه = نام ابزار = نسخه.** مسیر سورس: `symptom-checker/engine/src/` · همراه افزونهٔ `0.1.7` منتشر می‌شود.

## چه می‌کند

بستهٔ دانش + پاسخ‌های کاربر → شاهد، کارت موضوع، هشدار ایمنی، وضعیت پوشش. منطق خالص جاوااسکریپت: بدون شبکه، بدون DOM، بدون ذخیره‌سازی، بدون تصادف.

## فایل‌ها و مسئولیت

| فایل | مسئولیت |
| --- | --- |
| `load-bundle.js` | دو شکل بسته (v1/v2) → یک مدل داخلی + خطاهای تایپ‌شده |
| `adapter.js` | تنها جایی که تفاوت دو شکل را می‌داند؛ نگاشت از `legacy_id` |
| `validate-bundle.js` | ساختار پس از آداپت (از جمله `profile_ref` و `link_status`) |
| `strategy.js` | `selectNextQuestion(state)`؛ `static_order` پیش‌فرض، `adaptive` اسکلت |
| `profile-match.js` | گروه کیفی `strong/partial/weak`، پیش‌فرض خاموش، بدون عدد |
| `engine.js` | نشست: `answer`، `canAnswer`، `result`، `availableQuestions` |
| `evidence.js` | ساخت شاهد از `evidence[]` گزینه‌ها و اعتبارسنجی نقشهٔ شاهد |

## قرارداد

`createSession(bundle, { evidenceMap } = {})` → `{ answer, canAnswer, result, answers, availableQuestions }`.
`result()` = `{ state, coverage, cards_allowed, conditions, safety, answered, remaining, disclaimers, bundle_version }`. تنها راه تغییر آن، **افزودن** فیلد است.

## چه چیزی را تضمین می‌کند

- پرسش بی‌پاسخ «نه» نیست؛ «نمی‌دانم» هیچ چیزی را برآورده نمی‌کند.
- شاخه‌ها موازی‌اند؛ `out_of_scope` همهٔ کارت‌ها را خاموش می‌کند.
- عکس طلایی v1: ۲۵ سناریو + ۵۰۰۰ مسیر بایت‌به‌بایت.

## چه چیزی را **نه**

- تشخیص نمی‌دهد، نمی‌سنجد، رتبه‌بندی نمی‌کند.
- `adaptive` امروز تطبیقی نیست (فاز ۴).
- `level` پروفایل تا فاز ۴ هیچ اثری روی نتیجه ندارد.

## آزمون

`node --test symptom-checker/engine/tests/*.test.js` (بخشی از ۱۲۹/۱۲۹ همراه رابط) · `tools/symptom-checker-migrate/equivalence.mjs`.

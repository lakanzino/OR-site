# موتور دستیار نشانه‌ها — فاز ۲

موتور خالص جاوااسکریپت، بدون وردپرس، بدون شبکه و بدون DOM. ورودی: بستهٔ دانش فاز ۱ (و به‌صورت اختیاری نگاشت شواهد). خروجی: مسیرهای باز، هشدارهای ایمنی، وضعیت پوشش و تفکیک شواهد.

**این موتور تشخیص نمی‌دهد، امتیاز نمی‌دهد، رتبه‌بندی نمی‌کند و درصد احتمال نمی‌سازد.**

## اجرا

```sh
cd symptom-checker/engine
node --test            # ۷۶ آزمون
node bin/demo.js       # فهرست سناریوها
node bin/demo.js sc05  # اجرای یک سناریو
node bin/demo.js sc05 --json
```

## استفاده

```js
import { readFileSync } from 'node:fs';
import { createSession } from './src/engine.js';

const bundle = JSON.parse(readFileSync('docs/symptom-checker/phase-1/knowledge-bundle.v1.json', 'utf8'));
const evidenceMap = JSON.parse(readFileSync('symptom-checker/engine/data/evidence-map.v1.json', 'utf8'));

const session = createSession(bundle, { evidenceMap });

session.nextQuestion();          // سؤال بعدی یا null
session.answer('q_pain_present', 'yes');
session.safety();                // هشدارهای فعال، همین حالا
session.undo();                  // بازگشت به حالت پیشین
session.result();                // تنها شکلی که رابط کاربری مجاز است نمایش دهد
```

## قرارداد رفتاری

| موضوع | رفتار موتور |
|---|---|
| پاسخ نداده | «پرسیده‌نشده» است، هرگز «خیر» شمرده نمی‌شود. |
| «نمی‌دانم» | هیچ شرطی که پاسخ مثبت می‌خواهد را برآورده نمی‌کند. |
| شاخه‌ها | موازی‌اند؛ بازشدن یک مسیر، مسیر دیگر را نمی‌بندد. |
| هشدار ایمنی | مستقل از نتیجهٔ کارت‌ها و بلافاصله پس از پاسخ مربوط محاسبه می‌شود. |
| پوشش | `sufficient` / `insufficient` / `out_of_scope`؛ در دو حالت آخر کارت موضوع نمایش داده نمی‌شود. |
| `out_of_scope` | پرسش‌نامه متوقف می‌شود، سؤال تازه پرسیده نمی‌شود، اما ویرایش پاسخ قبلی مجاز است. |
| تغییر پاسخ | پاسخ سؤال‌هایی که دیگر نمایش داده نمی‌شوند از مجموعهٔ فعال خارج و در `inactive` نگه داشته می‌شوند. |
| بازگشت | تا ۵۰ گام، با بازگرداندن کامل پاسخ‌ها و پاسخ‌های غیرفعال. |
| دادهٔ ناقص یا نسخهٔ ناسازگار | `BundleValidationError` / `BundleVersionError`؛ موتور روی دادهٔ معیوب اجرا نمی‌شود. |
| خطای پاسخ | `AnswerValidationError` / `QuestionNotVisibleError` / `UnknownQuestionError`. |

## تفکیک شواهد

برای هر موضوع، هر نشانهٔ شناسنامه در یکی از این گروه‌ها قرار می‌گیرد:

`present` · `absent` · `unknown` · `not_reported` · `not_asked` · `conflict` · `not_covered`

- `conflict` یعنی پاسخ‌ها متناقض‌اند (مثلاً «تب دارم» در یک سؤال و «تب ندارم» در سؤال دیگر). موتور آن را پنهان نمی‌کند.
- `not_covered` یعنی آن نشانه در پرسش‌نامه قابل پرسیدن نیست؛ به معنی نداشتن آن نیست. امروز `s_bladder_pressure` و `s_abdominal_cramp` چنین‌اند.

## ساختار

```text
engine/
├── src/engine.js            موتور و API نشست
├── src/validate-bundle.js   دروازهٔ ساختاری داده (توقف امن)
├── src/evidence.js          نگاشت پاسخ→نشانه و تفکیک شواهد
├── src/errors.js            خطاهای نوع‌دار با کد پایدار
├── data/evidence-map.v1.json
├── bin/demo.js
└── tests/                   ۷۶ آزمون (واحد + ۱۲ سناریوی فاز ۱ + ۱۰ سناریوی حاشیه‌ای + ماتریس مسیر ایمنی)
```

## محدودیت‌ها

- دادهٔ `0.1.0-draft` است؛ متن سؤال‌ها و هشدارها `pending_team_review` و نگاشت مقاله‌ها `needs_live_verification` است.
- هیچ رابط کاربری، افزونهٔ وردپرس یا صفحهٔ عمومی در این فاز ساخته نشده است.
- آزمون‌ها منطق را می‌سنجند، نه دقت بالینی. عدد یا درصدی از آن‌ها قابل استخراج نیست.

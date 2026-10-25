# آینه‌ی محتوای زنده — وضعیت (به‌روزرسانی ۲۰۲۶-۱۰-۲۵)

این پوشه، آینه‌ی متنی محتوای منتشرشده‌ی `hamyarjarahi.ir` است.
طبق **قانون آینه**، محتوای هر پوشه باید کلمه‌به‌کلمه برابر سایت زنده باشد و **هیچ ردیفی بدون تأیید کارفرما اضافه/حذف/ویرایش نمی‌شود**.
این فایل فقط «وضعیت» را ثبت می‌کند، نه متن مقالات.

## وضعیت واقعی سایت (به‌روزرسانی ۲۰۲۶-۱۰-۲۵ — تأیید کارفرما؛ شمار پایه با REST API همان روز)

| CPT | زنده | در آینه | شکاف |
|---|---:|---:|---:|
| `patient_guide` | ۳۹ | ۵ | ۳۴ |
| `or_environment` | ۱ | ۰ | ۱ |
| `surgery` | ۳۳ | ۰ | ۳۳ |
| `student_content` | ۰ | ۰ | ۰ |
| `glossary` | ۰ | ۰ | ۰ |
| **جمع مقالات** | **۷۳** | **۵** | **۶۸** |

> جدول قدیمی این بخش (۳۱ زنده / شکاف ۲۶) به‌عنوان سابقه پایین‌تر نگه داشته شد؛ اعداد بالا پس از انتشار بستهٔ ۰۴ (۹ مقالهٔ جراحی: ختنه ×۳، هموروئید ×۳، لاپاروسکوپی ×۲، لوزه ×۱) با تأیید کارفرما جایگزین شد. تکمیل آینه در ردیف `T-05` بک‌لاگ دنبال می‌شود.

<!-- جدول تاریخی (نسخهٔ پیش از انتشار بستهٔ ۰۴)
| CPT | زنده | در آینه | شکاف |
|---|---:|---:|---:|
| `patient_guide` | ۲۴ | ۵ | ۱۹ |
| `or_environment` | ۱ | ۰ | ۱ |
| `surgery` | ۶ | ۰ | ۶ |
| `student_content` | ۰ | ۰ | ۰ |
| `glossary` | ۰ | ۰ | ۰ |
| **جمع مقالات** | **۳۱** | **۵** | **۲۶** |
| صفحه‌های ایستا | ۵ (+ خانه) | ۰ | ۵ |
-->

منبع شمارش: `wp-sitemap-posts-patient_guide-1.xml`، `wp-sitemap-posts-or_environment-1.xml`، `wp-sitemap-posts-surgery-1.xml`، `wp-sitemap-posts-page-1.xml`.
**قاعده‌ی تازه (ADR-019):** عدد «مقاله‌ی زنده» فقط از نقشه‌ی سایت **با پارامتر ضدکش** (`?v=<timestamp>`) گرفته می‌شود؛ اسناد قدیمی (صف پنج‌مقاله‌ای، مانیفست با عدد ۱۵) مرجع نیستند. خواندن بدون ضدکش در ۱۴۰۵/۰۸/۰۲ عدد کهنه‌ی ۲۸ را برگرداند.

## آنچه همین حالا در آینه هست (۵ مقاله)

`glossary-surgical-terms` · `what-is-operating-room` · `surgical-care-team` · `patient-journey` · `operating-room-moment-by-moment`
— هر کدام با `article.json` + `content.html` + `featured.webp`.

## شکاف‌های آینه (باید به‌ترتیب تکمیل شود)

> **[به‌روزرسانی ۲۰۲۶-۱۰-۲۵]** فهرست‌های تفصیلی پایین این بخش مربوط به وضعیت ۳۱ مقاله‌ای است و دیگر کامل نیست؛ شکاف کل الان **۶۸ مقاله** است (۷۳ زنده منهای ۵ آینه‌شده): ۳۴ راهنمای بیمار، ۳۳ جراحی و ۱ محیط اتاق عمل. فهرست قدیمی به‌عنوان سابقه نگه داشته شده.

1. **۱۹ مقاله‌ی `patient_guide`** که زنده‌اند ولی در آینه نیستند:
`general-anesthesia`، `preoperative-fasting`، `pacu-recovery`، `fear-of-anesthesia-awareness`،
`informed-consent-anesthesia-consult`، `general-vs-spinal-anesthesia`، `preoperative-tests`،
`what-not-to-eat-before-surgery`، `post-surgery-pain`، `suture-removal`، `surgical-site-infection`،
`post-surgery-nutrition`، `early-walking-after-surgery`، `companion-role-admission-day`،
`medications-before-surgery`، `mental-preparation-before-surgery`، `anesthesia-consultation`،
`anesthesia-side-effects`، `warning-signs-after-surgery`.
2. **۶ مقاله‌ی `surgery`**: `appendectomy-preparation`، `appendectomy-recovery`، `cesarean-overview`، `cesarean-preparation`، `cesarean-recovery`، `hernia-overview`.
3. **۱ مقاله‌ی `or_environment`**: `surgical-team-roles`.
4. **۵ صفحه‌ی ایستا**: `about`، `contact`، `faq`، `privacy`، `terms`.

> تا وقتی شکاف آینه پر نشود، آینه «مرجع رسمی وضعیت سایت» نیست و فقط ۵ مقاله‌ی اول را پوشش می‌دهد. بسته‌های آینه‌ی ۲۳ مقاله را می‌توان از سایت زنده یا از بسته‌های ایمپورت مخزن بازسازی کرد؛ انتقال باید بایت‌به‌بایت و با SHA-256 در `CHECKSUMS.txt` ثبت شود.

## بسته‌های ایمپورت در مخزن

| محل | تعداد | توضیح |
|---|---:|---|
| `themes/lax-theme-child/content-imports/` | ۵ | همان ۵ مقاله‌ی آینه‌شده (بستهٔ همراه قالب) |
| `releases/article-packages/` | ۴۴ بستهٔ مقاله (+ بسته‌های تجمیعی) | تا ۲۰۲۶-۱۰-۲۵ هر ۴۴ بسته روی سایت زنده منتشر شده‌اند (تأیید کارفرما) |

## گام بعدی

1. اولویت محتوا و صف مقالات: `docs/content/CONTENT-BACKLOG.fa.md` و `docs/content/CONTENT-ROADMAP-v2-MERGED.fa.md`.
2. پس از هر انتشار: کپی سه فایل مقاله به `articles/<slug>/` و افزودن ردیف به همین فایل.
3. تکمیل شکاف **۶۸ مقاله** + ۵ صفحه، سپس اعلام «آینه برابر سایت زنده» (ردیف `T-05`).

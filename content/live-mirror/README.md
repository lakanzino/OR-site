# آینه‌ی محتوای زنده — وضعیت (به‌روزرسانی ۲۰۲۶-۱۰-۲۴)

این پوشه، آینه‌ی متنی محتوای منتشرشده‌ی `hamyarjarahi.ir` است.
طبق **قانون آینه**، محتوای هر پوشه باید کلمه‌به‌کلمه برابر سایت زنده باشد و **هیچ ردیفی بدون تأیید کارفرما اضافه/حذف/ویرایش نمی‌شود**.
این فایل فقط «وضعیت» را ثبت می‌کند، نه متن مقالات.

## وضعیت واقعی سایت (بررسی‌شده از نقشه‌ی سایت در ۲۰۲۶-۱۰-۲۴)

| CPT | زنده | در آینه | شکاف |
|---|---:|---:|---:|
| `patient_guide` | ۲۳ | ۵ | ۱۸ |
| `or_environment` | ۱ | ۰ | ۱ |
| `surgery` | ۴ | ۰ | ۴ |
| `student_content` | ۰ | ۰ | ۰ |
| `glossary` | ۰ | ۰ | ۰ |
| **جمع مقالات** | **۲۸** | **۵** | **۲۳** |
| صفحه‌های ایستا | ۵ (+ خانه) | ۰ | ۵ |

منبع شمارش: `wp-sitemap-posts-patient_guide-1.xml`، `wp-sitemap-posts-or_environment-1.xml`، `wp-sitemap-posts-surgery-1.xml`، `wp-sitemap-posts-page-1.xml`.
**قاعده‌ی تازه (ADR-019):** عدد «مقاله‌ی زنده» فقط از نقشه‌ی سایت گرفته می‌شود؛ اسناد قدیمی (صف پنج‌مقاله‌ای، مانیفست با عدد ۱۵) مرجع نیستند.

## آنچه همین حالا در آینه هست (۵ مقاله)

`glossary-surgical-terms` · `what-is-operating-room` · `surgical-care-team` · `patient-journey` · `operating-room-moment-by-moment`
— هر کدام با `article.json` + `content.html` + `featured.webp`.

## شکاف‌های آینه (باید به‌ترتیب تکمیل شود)

1. **۱۸ مقاله‌ی `patient_guide`** که زنده‌اند ولی در آینه نیستند:
`general-anesthesia`، `preoperative-fasting`، `pacu-recovery`، `fear-of-anesthesia-awareness`،
`informed-consent-anesthesia-consult`، `general-vs-spinal-anesthesia`، `preoperative-tests`،
`what-not-to-eat-before-surgery`، `post-surgery-pain`، `suture-removal`، `surgical-site-infection`،
`post-surgery-nutrition`، `early-walking-after-surgery`، `companion-role-admission-day`،
`medications-before-surgery`، `mental-preparation-before-surgery`، `anesthesia-side-effects`،
`warning-signs-after-surgery`.
2. **۴ مقاله‌ی `surgery`**: `appendectomy-preparation`، `appendectomy-recovery`، `cesarean-overview`، `cesarean-preparation`.
3. **۱ مقاله‌ی `or_environment`**: `surgical-team-roles`.
4. **۵ صفحه‌ی ایستا**: `about`، `contact`، `faq`، `privacy`، `terms`.

> تا وقتی شکاف آینه پر نشود، آینه «مرجع رسمی وضعیت سایت» نیست و فقط ۵ مقاله‌ی اول را پوشش می‌دهد. بسته‌های آینه‌ی ۲۳ مقاله را می‌توان از سایت زنده یا از بسته‌های ایمپورت مخزن بازسازی کرد؛ انتقال باید بایت‌به‌بایت و با SHA-256 در `CHECKSUMS.txt` ثبت شود.

## بسته‌های ایمپورت در مخزن

| محل | تعداد | توضیح |
|---|---:|---|
| `themes/lax-theme-child/content-imports/` | ۵ | همان ۵ مقاله‌ی آینه‌شده (بستهٔ همراه قالب) |
| `releases/article-packages/` | ۱۶ | ۶ بستهٔ تحویل‌شدهٔ قبلی + ۱۰ بستهٔ تازه‌ی دستهٔ «اولویت انتشار» (همه `draft`) |

## گام بعدی

1. اولویت محتوا و صف مقالات: `docs/content/PUBLISH-PRIORITY.fa.md`.
2. پس از هر انتشار: کپی سه فایل مقاله به `articles/<slug>/` و افزودن ردیف به همین فایل.
3. تکمیل شکاف ۲۳ مقاله + ۵ صفحه، سپس اعلام «آینه برابر سایت زنده».

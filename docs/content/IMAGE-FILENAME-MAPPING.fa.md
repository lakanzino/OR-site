# جدول تبدیل نام انگلیسی تصاویر به نام استاندارد فایل

> **به‌روزرسانی ۲۰۲۶-۱۰-۲۷:** این سند فقط مرجع فرمول نام‌گذاری است. نگاشت نهایی و عملیاتی ۳۳ فایل Drive (به‌همراه متن alt و فایل ZIP ایمپورت) در [`FEATURED-IMAGES-BATCH-2026-10.fa.md`](FEATURED-IMAGES-BATCH-2026-10.fa.md) و `data/featured-images-2026-10.json` ثبت شده است.

- **تاریخ تدوین:** ۲۰۲۶-۱۰-۲۷ (۵ آبان ۱۴۰۵)
- **قاعده‌ی نام‌گذاری:** حروف کوچک انگلیسی، کلمات با خط تیره (`-`)، فرمت WebP، فرمول ثابت:

```
{slug عمل}-{مرحله}-featured.webp
```

- مرحله‌ها: `overview` (نمای کلی/اتاق عمل) · `preparation` (پیش از عمل/معاینه) · `recovery` (بهبودی/پس از عمل)
- این نام‌ها با نام‌گذاری ایمپورتر وردپرس هم‌خوان‌اند: فایل درون بسته `featured.webp` است و هنگام ایمپورت به `{slug}-featured.webp` تغییر نام می‌دهد.

## اسلاگ استاندارد ده عمل جراحی

| عمل جراحی | عبارت فارسی | اسلاگ استاندارد مخزن |
|---|---|---|
| Hernia | فتق | `hernia` |
| Thyroidectomy | تیروئید | `thyroidectomy` |
| Cataract | آب مروارید | `cataract` |
| Cholecystectomy | کیسه صفرا | `cholecystectomy` |
| Kidney Stone | سنگ کلیه | `kidney-stone` |
| Hemorrhoid | هموروئید | `hemorrhoid` |
| Appendectomy | آپاندیسیت | `appendectomy` |
| Circumcision | ختنه | `circumcision` |
| Cesarean Section | سزارین | `cesarean` (نه `cesarean-section`) |
| Tonsillectomy | لوزه | `tonsillectomy` |

> توجه: مخزن برای سزارین اسلاگ `cesarean` را به کار می‌برد (فایل موجود `cesarean-recovery.webp`)؛ پس `cesarean-section` هیچ‌جا استفاده نمی‌شود. برای سنگ کلیه نیز حتی وقتی عبارت «kidney surgery» آمده، اسلاگ کامل `kidney-stone` مبنا قرار می‌گیرد.

---

## دسته‌ی اول (۱۰ تصویر)

| # | عبارت انگلیسی داده‌شده | نام نهایی استاندارد |
|---|---|---|
| ۱ | overview hernia surgery | `hernia-overview-featured.webp` |
| ۲ | preparation cholecystectomy | `cholecystectomy-preparation-featured.webp` |
| ۳ | recovery hernia surgery | `hernia-recovery-featured.webp` |
| ۴ | preparation thyroidectomy | `thyroidectomy-preparation-featured.webp` |
| ۵ | thyroidectomy-overview-featured | `thyroidectomy-overview-featured.webp` |
| ۶ | recovery thyroidectomy | `thyroidectomy-recovery-featured.webp` |
| ۷ | cataract-overview-featured | `cataract-overview-featured.webp` |
| ۸ | preparation cataract surgery | `cataract-preparation-featured.webp` |
| ۹ | recovery cataract surgery | `cataract-recovery-featured.webp` |
| ۱۰ | preparation cholecystectomy | `cholecystectomy-preparation-featured.webp` ⚠️ تکراری با #۲ |

## دسته‌ی دوم (۱۰ تصویر)

| # | عبارت انگلیسی داده‌شده | نام نهایی استاندارد |
|---|---|---|
| ۱ | cholecystectomy-overview-featured.webp | `cholecystectomy-overview-featured.webp` |
| ۲ | recovery cholecystectomy | `cholecystectomy-recovery-featured.webp` |
| ۳ | preparation kidney stone surgery | `kidney-stone-preparation-featured.webp` |
| ۴ | kidney-stone-overview-featured.webp | `kidney-stone-overview-featured.webp` |
| ۵ | recovery kidney surgery | `kidney-stone-recovery-featured.webp` |
| ۶ | preparation hemorrhoid surgery | `hemorrhoid-preparation-featured.webp` |
| ۷ | overview appendectomy | `appendectomy-overview-featured.webp` |
| ۸ | recovery appendectomy | `appendectomy-recovery-featured.webp` |
| ۹ | preparation appendectomy | `appendectomy-preparation-featured.webp` |
| ۱۰ | overview appendectomy | `appendectomy-overview-featured.webp` ⚠️ تکراری با #۷ |

## دسته‌ی سوم (۱۰ تصویر)

| # | عبارت انگلیسی داده‌شده | نام نهایی استاندارد |
|---|---|---|
| ۱ | recovery hemorrhoid surgery | `hemorrhoid-recovery-featured.webp` |
| ۲ | overview hemorrhoid surgery | `hemorrhoid-overview-featured.webp` |
| ۳ | preparation circumcision | `circumcision-preparation-featured.webp` |
| ۴ | recovery circumcision | `circumcision-recovery-featured.webp` |
| ۵ | preparation hemorrhoid surgery | `hemorrhoid-preparation-featured.webp` ⚠️ تکراری با دسته‌ی دوم #۶ |
| ۶ | overview circumcision | `circumcision-overview-featured.webp` |
| ۷ | recovery cesarean section | `cesarean-recovery-featured.webp` |
| ۸ | overview cesarean section | `cesarean-overview-featured.webp` 🆕 اسلاگ جدید |
| ۹ | preparation cesarean section | `cesarean-preparation-featured.webp` 🆕 اسلاگ جدید |
| ۱۰ | recovery thyroidectomy | `thyroidectomy-recovery-featured.webp` ⚠️ تکراری با دسته‌ی اول #۶ |

---

## تصاویر با نام تکراری (۴ مورد) — نیاز به تصمیم

هر مقاله فقط یک تصویر شاخص دارد؛ دو عکس متفاوت نمی‌توانند هم‌زمان یک نام داشته باشند. برای هر مورد زیر باید یکی به‌عنوان تصویر اصلی انتخاب شود و دیگری کنار گذاشته شود (یا صرفاً به‌عنوان نسخه‌ی جایگزین با پسوند `-2` بایگانی شود):

| نام نهایی مشترک | محل‌های تکرار | تفاوت دو صحنه |
|---|---|---|
| `cholecystectomy-preparation-featured.webp` | دسته اول #۲ و #۱۰ | بیمار زن بدون حجاب در مقابل بیمار زن با حجاب |
| `appendectomy-overview-featured.webp` | دسته دوم #۷ و #۱۰ | نمای بالای لاپاراسکوپی در مقابل نمای نزدیک برش با چاقوی جراحی |
| `hemorrhoid-preparation-featured.webp` | دسته دوم #۶ و دسته سوم #۵ | بیمار زن در مقابل بیمار مرد خم‌شده از درد |
| `thyroidectomy-recovery-featured.webp` | دسته اول #۶ و دسته سوم #۱۰ | بیمار مرد در حال نوشیدن مایع در مقابل بیمار زن بلوند با بررسی پرستار |

در صورت بایگانی نسخه‌ی دوم، الگوی پیشنهادی: `{slug}-featured-2.webp` (مثلاً `appendectomy-overview-featured-2.webp`) — اما این نام هرگز نام تصویر شاخص مقاله نیست.

## خلاسه‌ی آماری

- ۳۰ تصویر ورودی ← **۲۶ نام یکتا**
- ۴ برخورد نام ناشی از تکرار مرحله‌ی یک عمل در دسته‌های مختلف
- ۲ اسلاگ جدیدِ فاقد فایل در مخزن: `cesarean-overview` و `cesarean-preparation` (بقیه‌ی ۲۴ اسلاگ معادل فایل موجود در `content/images/featured/` دارند)

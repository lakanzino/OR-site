# or-site — سایت آموزشی اتاق عمل

قالب‌های وردپرس و اسناد پروژه‌ی **«همیار جراحی»** — سایت آموزشی اتاق عمل و راهنمای بیمار.

🌐 دامنه‌ی رسمی: **[hamyarjarahi.ir](https://hamyarjarahi.ir)** — نام سایت: **همیار جراحی**

| جزء | نسخه | مسیر |
|---|---|---|
| قالب والد `lax-theme` | 1.0.1 | [`themes/lax-theme/`](themes/lax-theme/) |
| قالب فرزند `lax-theme-child` | 0.9.2 (کانال به‌روزرسانی GitHub) | [`themes/lax-theme-child/`](themes/lax-theme-child/) |
| ساختار محتوا (`ORSITE_STRUCTURE_VERSION`) | 1.0.0 | `themes/lax-theme-child/inc/content-types.php` |

## ساختار مخزن

- `themes/lax-theme/` — قالب والد (ارائه‌ی عمومی، راست‌چین، فونت وزیرمتن).
- `themes/lax-theme-child/` — قالب فرزند اختصاصی پروژه: ساختار محتوا (۵ نوع نوشته، ۶ تاکسونومی، ۱۱۲ اصطلاح)، قالب مقاله با فهرست مطالب خودکار، طراحی باکس‌دار تک‌رنگ سرمه‌ای و سایزهای تصویر بدون برش.
- `docs/` — قوانین فازبندی پروژه، تصمیم‌ها (ADR)، چنج‌لاگ کامل، رودمپ محتوا و گزارش گام‌ها.
- `design/` — پیش‌نمایش HTML طراحی جاری.
- `data/` — موجودی دسته‌بندی‌شده‌ی فایل‌های قالب والد.


## به‌روزرسانی قالب‌ها از GitHub (پیشخوان)

وردپرس فقط به‌روزرسانی‌های WordPress.org را می‌بیند؛ برای همین کانال GitHub ساخته شده است:

1. **یک‌بار** افزونه‌ی `lax-github-updater` را نصب و فعال کنید:
   [releases/lax-github-updater-1.0.0.zip](https://github.com/lakanzino/OR-site/raw/child-v0.9.2/releases/lax-github-updater-1.0.0.zip)
2. از آن به بعد، هر ریلیز `child-vX.Y.Z` در «پیشخوان → به‌روزرسانی‌ها» و «نمایش → پوسته‌ها» دیده می‌شود.
3. برای انتشار نسخه‌ی جدید: نسخه را در `style.css` و `LAX_THEME_CHILD_VERSION` بالا ببرید، ZIP را در `releases/` بگذارید، تگ `child-vX.Y.Z` و Release بسازید.

قطعه‌ی کد همان کانال داخل خود قالب هم هست (`themes/lax-theme-child/inc/github-updater.php`، ADR-011)؛ افزونه برای سایت‌هایی است که قالبشان هنوز آن فایل را ندارد.

## نصب

1. قالب والد `lax-theme` را نصب کنید (فعال نکنید).
2. قالب فرزند `lax-theme-child` را نصب و فعال کنید.
3. پیوندهای یکتا را روی «نام نوشته» (`/%postname%/`) بگذارید و یک بار ذخیره کنید.
4. یک بار وارد پیشخوان شوید تا اصطلاحات ساختار محتوا ساخته شوند.

جزئیات نسخه‌ها و تغییرات: [`docs/PROJECT-CHANGELOG.md`](docs/PROJECT-CHANGELOG.md) — تصمیم‌های معماری: [`docs/DECISIONS.md`](docs/DECISIONS.md) — برنامه‌ی محتوا و لینک‌دهی داخلی: [`docs/CONTENT-ROADMAP.md`](docs/CONTENT-ROADMAP.md)

> محتوای پزشکی داخل قالب‌ها ساخته نشده است؛ متن‌ها را تیم محتوا می‌نویسد و بازبینی می‌کند. قالب‌های ZIP آماده‌ی نصب، در بخش Releases هر نسخه منتشر می‌شوند.

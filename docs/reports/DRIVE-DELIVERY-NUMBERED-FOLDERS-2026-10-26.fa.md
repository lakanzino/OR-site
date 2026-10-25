# تحویل Google Drive — بسته‌های مقالات جراحی، پوشه‌های شماره‌دار (۲۰۲۶-۱۰-۲۶)

## قرارداد شماره‌گذاری (دستور کارفرما ۲۰۲۶-۱۰-۲۶)

- هر سری تحویل جدید داخل پوشهٔ والد، در یک **زیرپوشهٔ شماره‌دار** قرار می‌گیرد: سری ۱ در پوشهٔ `1`، سری بعدی در `2`، سپس `3` و همین‌طور ادامه می‌یابد.
- فایل‌های شل در پوشهٔ والد نمی‌مانند؛ والد فقط فهرست زیرپوشه‌های شماره‌دار است.
- پیش از ساخت زیرپوشهٔ جدید، فهرست پوشهٔ والد با Drive API بازبینی شود تا شماره تکراری ساخته نشود (شمارهٔ بعدی = بزرگ‌ترین شمارهٔ موجود + ۱).

## مشخصات پوشه‌ها

| مورد | نام | شناسه (ID) | لینک |
|---|---|---|---|
| پوشهٔ والد | `OR-SITE — بسته‌های مقالات جراحی (۳۳ زیپ قابل‌ایمپورت) 2026-10-26` | `1bIjSxNbsMvzdEeTbV-9wwxbrzhee7wr7` | https://drive.google.com/drive/folders/1bIjSxNbsMvzdEeTbV-9wwxbrzhee7wr7 |
| زیرپوشهٔ سری ۱ | `1` | `1tnVYe4qYKxUMfx6UWKaNiI7x52Tc-vbp` | https://drive.google.com/drive/folders/1tnVYe4qYKxUMfx6UWKaNiI7x52Tc-vbp |

## محتوای سری ۱ — ۳۳ زیپ قابل‌ایمپورت

- منبع: شاخهٔ `arena/43a7ddbf-or-site`، کامیت `e3de78e`، هر ۳۳ فایل محلی تست `unzip -t` را پاس کرده و حجم بایت‌به‌بایت با نسخهٔ Drive تطبیق داده شد (مجموع ۳٬۶۳۱٬۲۳۴ بایت).
- یازده خوشهٔ جراحی × سه مرحله (overview / preparation / recovery): appendectomy، hernia، cesarean، cataract، cholecystectomy، circumcision، hemorrhoid، kidney-stone، laparoscopy، thyroidectomy، tonsillectomy.
- ساختار هر ZIP: پوشهٔ `<slug>/` با `article.json` + `content.html` + `featured.webp`؛ نه زیپ overview بازنویسی‌شده پوشهٔ `assets/` (دو figure آموزشی) دارند.
- وضعیت: پوشهٔ والد فقط همان یک زیرپوشه را دارد و هر ۳۳ زیپ در `1` جای گرفته‌اند (شمارش API: ۳۳).
- یادداشت: زیپ‌های ختنه (circumcision) و هموروئید (hemorrhoid) نسخهٔ استاب فعلی مخزن‌اند؛ پس از بازنویسی SOP این دو خوشه، سری جدید در زیرپوشهٔ `2` تحویل می‌شود.
- مقالهٔ `why-operating-room-is-sterile` و مقالات راهنمای بیمار/بیهوشی در این سری نبودند؛ در صورت دستور کارفرما در سری بعدی افزوده می‌شوند.

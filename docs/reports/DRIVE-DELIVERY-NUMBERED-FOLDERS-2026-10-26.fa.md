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
| زیرپوشهٔ سری ۲ (تصاویر فرمت جدید) | `2` | `1AtsRuPINEKZo4OZBKzIsi2ccvJmXUcB8` | https://drive.google.com/drive/folders/1AtsRuPINEKZo4OZBKzIsi2ccvJmXUcB8 |
| زیرپوشهٔ سری ۳ (ایمپورت مجدد با تصاویر درون‌متنی) | `3` | `1btPFO1OWN4L4XzpgvN6ix75isLn2cvjg` | https://drive.google.com/drive/folders/1btPFO1OWN4L4XzpgvN6ix75isLn2cvjg |

## سری ۲ — تصاویر شاخص فرمت جدید (۲۰۲۶-۱۰-۲۶)

- محرک: تصاویر شاخص قبلی هنگام ایمپورت مجدد متن فارسی بدفرم/تصاویر تیره را برگردانده بودند؛ هر ۳۳ تصویر شاخص با فرمت مینیمال تازه بازسازی و در همان ۳۳ ZIP قابل‌ایمپورت بسته‌بندی شد.
- فرمت قفل‌شده: کارت شناور گوشه‌گرد با سایه نرم روی زمینه سفید، عکس واقعی سینمایی روشن و خوش‌رنگ (پالت سبز/آبی/سفید، آدم‌های خوش‌چهره و خوش‌استایل با پوشش کامل)، چیپ شیشه‌ای گوشهٔ پایین عکس با `H` سبز و بقیهٔ `HAMYARJARAHI` طوسی؛ بدون هیچ تیتر/متنی روی تصویر. alt همچنان از `featured_image.alt` مانیفست هنگام ایمپورت می‌آید.
- خروجی اسکریپت `tools/featured-card.py`؛ منبع خام و خروجی نهایی در `content/images/new-format/` (۳۳ webp نهایی + خام jpg) و سپس در `content/drafts/*/featured.webp` و کتابخانهٔ `content/images/featured/` جایگزین شد (هش یکسان تأیید شد).
- میانگین روشنایی مجموعه ۱۷۸ (۰–۲۵۵)؛ ۷ تصویر تیرهٔ نسخهٔ اول با نسخه‌های high-key روشن‌تر بازسازی شدند. همه ۱۲۰۰×۶۷۵ و زیر ۱۸۰ کیلوبایت.
- شمارش Drive: ۳۳ فایل در پوشهٔ `2`؛ تطبیق حجم بایت‌به‌بایت هر ۳۳ زیپ محلی با نسخهٔ Drive تأیید شد. کامیت مرجع: `e2a239a`.
- نکتهٔ ایمپورت: چون نامک‌ها تکراری‌اند، ایمپورتر در حالت `smart_update` پست‌های زنده را به‌روزرسانی و به‌دلیل تفاوت هش SHA-256، تصویر شاخص را با نسخهٔ جدید جایگزین می‌کند (`update_image=1`). پس از ایمپورت LiteSpeed Purge All فراموش نشود.

### فایل یکجای تصاویر سری ۲ (در همین پوشهٔ `2`)

- نام: `OR-SITE-NEW-FEATURED-IMAGES-2026-10-26.zip` — شناسه Drive: `17kPSRwMStv9gHNcJnepHX98A83kMGbIo` — حجم ۷٬۰۵۵٬۵۸۰ بایت (تطبیق بایت‌به‌بایت تأیید شد).
- محتوا: پوشهٔ `featured-webp/` (هر ۳۳ خروجی نهایی ۱۲۰۰×۶۷۵ با کارت و چیپ لوگو) + پوشهٔ `raw-jpg/` (۳۳ عکس خام پیش از کامپوزیت، بدون کادر و لوگو).
- دسترسی: هر کس با لینک، فقط خواننده (کشف عمومی غیرفعال).
- لینک دانلود مستقیم: https://drive.google.com/uc?id=17kPSRwMStv9gHNcJnepHX98A83kMGbIo&export=download

## سری ۳ — بسته‌های آمادهٔ ایمپورت مجدد با تصاویر درون‌متنی (۲۰۲۶-۱۰-۲۶)

- محرک: اینفوگرافی‌های داخل متن (پوشهٔ `assets/`، هر بسته دو فایل webp) هنگام ایمپورت‌های قبلی وارد کتابخانهٔ رسانه نمی‌شدند و تگ `<img src="assets/...">` با مسیر نسبی شکسته در پست می‌ماند؛ روی سایت جای تصویر خالی و فقط متن alt دیده می‌شد. ریشهٔ فنی: ایمپورتر تا نسخهٔ 0.14.0 فقط `featured.webp` را آپلود می‌کرد (بلاکر T-32).
- رفع فنی در قالب فرزند **0.15.0** (کامیت `b15bccc`، ایمپورتر داخلی v0.12.0): اسکن تگ‌های `<img>` محلی، آپلود `assets/*.webp` به کتابخانهٔ رسانه، الصاق به پست، بازنویسی خودکار src به URL رسانه با حفظ `<figure>`/`<figcaption>`/alt، ضدتکرار با متای `_hj_package_slug`+`_hj_package_image` و تطبیق SHA-256، و ترمیم هوشمند مقالات قدیمی در حالت `smart_update`. قاعدهٔ گیت جدید `content-lint` (alt غیرخالی، فایل موجود در بسته، کپشن فارسی) با هر ۴۹ بسته سبز است.
- این سری فقط شامل **۹ مقالهٔ overview جراحی است که figure درون‌متنی دارند** (مجموع ۱۸ اینفوگرافی)؛ مقالات preparation/recovery تصویر درون‌متنی ندارند و از این تحویل کنارند.
- شمارش Drive: دقیقاً ۹ فایل در پوشهٔ `3` (بدون صفحه‌بندی)؛ حجم و **SHA-256 هر ۹ فایل بایت‌به‌بایت برابر ZIPهای مخزن** تأیید شد. دسترسی همه: هر کس با لینک، فقط خواننده، کشف عمومی غیرفعال.

### فهرست ۹ بسته و لینک دانلود

| مقاله (نامک) | تصاویر درون‌متنی | حجم (بایت) | شناسهٔ Drive | لینک دانلود مستقیم |
|---|---|---:|---|---|
| appendectomy-overview | appendix-anatomy، appendectomy-steps | ۱۹۱٬۰۲۹ | `1Srre_HjwIbI2xffKVH9cRbD50MeEgoIE` | https://drive.google.com/uc?id=1Srre_HjwIbI2xffKVH9cRbD50MeEgoIE&export=download |
| cataract-overview | cataract-anatomy، cataract-steps | ۱۶۵٬۱۲۶ | `1D3ou-bg905pLU76769gPUZsoKNsRsXvB` | https://drive.google.com/uc?id=1D3ou-bg905pLU76769gPUZsoKNsRsXvB&export=download |
| cesarean-overview | cesarean-anatomy، cesarean-steps | ۲۲۲٬۵۱۵ | `1HF5s59uN1IKL94UZ2TNO01YrV8MV1w3b` | https://drive.google.com/uc?id=1HF5s59uN1IKL94UZ2TNO01YrV8MV1w3b&export=download |
| cholecystectomy-overview | cholecystectomy-anatomy، cholecystectomy-steps | ۱۴۳٬۸۶۸ | `10yPe0CP5eIpZ-lkqxYMB25KypLmdCqrp` | https://drive.google.com/uc?id=10yPe0CP5eIpZ-lkqxYMB25KypLmdCqrp&export=download |
| hernia-overview | hernia-anatomy، hernia-repair | ۲۲۲٬۶۰۶ | `1i-q4DgKA6f9sjZIwlGI4_0DG3nZGW098` | https://drive.google.com/uc?id=1i-q4DgKA6f9sjZIwlGI4_0DG3nZGW098&export=download |
| kidney-stone-overview | kidney-stone-anatomy، kidney-stone-steps | ۱۵۶٬۲۰۴ | `1ODUPJWXoXj0URuhiExUm7tYjqi3047yq` | https://drive.google.com/uc?id=1ODUPJWXoXj0URuhiExUm7tYjqi3047yq&export=download |
| laparoscopy-overview | laparoscopy-anatomy، laparoscopy-steps | ۱۵۴٬۷۲۲ | `1QCxQOWTr3cPlkpAziOuO2KUBnC12iWiz` | https://drive.google.com/uc?id=1QCxQOWTr3cPlkpAziOuO2KUBnC12iWiz&export=download |
| thyroidectomy-overview | thyroidectomy-anatomy، thyroidectomy-steps | ۱۴۹٬۹۶۰ | `16pz6N2TBt3WScuNz87gys6euJCm3jiXk` | https://drive.google.com/uc?id=16pz6N2TBt3WScuNz87gys6euJCm3jiXk&export=download |
| tonsillectomy-overview | tonsillectomy-anatomy، tonsillectomy-steps | ۱۴۷٬۱۱۸ | `1kjjO1nKpw8M4yQUyTSPVZmhmzx0Thxzh` | https://drive.google.com/uc?id=1kjjO1nKpw8M4yQUyTSPVZmhmzx0Thxzh&export=download |

### دستورالعمل ایمپورت مجدد (تکی)

1. ابتدا قالب فرزند به نسخهٔ **0.15.0** به‌روزرسانی شود (از پیشخوان → به‌روزرسانی گیت‌هاب)؛ بدون این نسخه، ایمپورت مجدد همان ارجاع شکسته را تکرار می‌کند.
2. هر ZIP تکی از منوی «جراحی‌ها → ایمپورت مقاله (ZIP)» آپلود شود؛ حالت `smart_update` و تیک «وضعیت فعلی پست تغییر نکند» فعال باشد تا مقالهٔ زنده، منتشرشده بماند.
3. **تصاویر شاخص دستی:** اگر تصویر شاخص به‌صورت دستی درست شده و نباید عوض شود، تیک «جایگزینی خودکار تصویر شاخص» برداشته شود؛ قابلیت جدید T-32 مستقل از این تیک کار می‌کند و فقط تصاویر درون‌متنی را منتقل می‌کند.
4. پس از هر ایمپورت، کارت نتیجه شمار و وضعیت آپلود تصاویر درون‌متنی را نشان می‌دهد؛ در پایان LiteSpeed Purge All.

## محتوای سری ۱ — ۳۳ زیپ قابل‌ایمپورت

- منبع: شاخهٔ `arena/43a7ddbf-or-site`، کامیت `e3de78e`، هر ۳۳ فایل محلی تست `unzip -t` را پاس کرده و حجم بایت‌به‌بایت با نسخهٔ Drive تطبیق داده شد (مجموع ۳٬۶۳۱٬۲۳۴ بایت).
- یازده خوشهٔ جراحی × سه مرحله (overview / preparation / recovery): appendectomy، hernia، cesarean، cataract، cholecystectomy، circumcision، hemorrhoid، kidney-stone، laparoscopy، thyroidectomy، tonsillectomy.
- ساختار هر ZIP: پوشهٔ `<slug>/` با `article.json` + `content.html` + `featured.webp`؛ نه زیپ overview بازنویسی‌شده پوشهٔ `assets/` (دو figure آموزشی) دارند.
- وضعیت: پوشهٔ والد فقط همان یک زیرپوشه را دارد و هر ۳۳ زیپ در `1` جای گرفته‌اند (شمارش API: ۳۳).
- یادداشت: زیپ‌های ختنه (circumcision) و هموروئید (hemorrhoid) نسخهٔ استاب فعلی مخزن‌اند؛ پس از بازنویسی SOP این دو خوشه، سری جدید در زیرپوشهٔ `2` تحویل می‌شود.
- مقالهٔ `why-operating-room-is-sterile` و مقالات راهنمای بیمار/بیهوشی در این سری نبودند؛ در صورت دستور کارفرما در سری بعدی افزوده می‌شوند.

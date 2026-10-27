# بسته‌های آماده‌ی انتشار — ۲۰۲۶-۱۰-۲۷ نوبت دوم (۵ مقاله‌ی تازه)

- **وضعیت:** هر پنج بسته `post_status: draft` در مانیفست؛ فیلتر فنی `tools/content-lint.py` با صفر خطا، فیلتر تحریریه انسانی (سبک ۲.۰، YMYL، صحت منابع) انجام شد. رکورد تصمیم: **ADR-022**.
- **ساختار هر ZIP:** پوشه‌ی ریشه `<slug>/` شامل `article.json` + `content.html` + `featured.webp` (۱۲۰۰×۶۷۵ بوم سفید، زیر ۱۸۰KB، alt فارسی ۱۰۰–۱۲۰ نویسه). تطبیق SHA-256 هر عضو با درخت `content/drafts/` راستی‌آزمایی شد.
- **بسته‌ی ترکیبی:** `BATCH-PUBLISH-2026-10-27-5new-articles.zip` (هر پنج ZIP یک‌جا، ۴۰۰٬۷۸۴ بایت).
- **مقصد Google Drive:** همان پوشه‌ی عمومی دسته‌ی پیشین — شناسه `17IYJBIfxV5W-vBu2-2PGskslw-TOcxuH` (دسترسی: هر کس با لینک، خواننده).
- **تغییر دروازه:** نه بسته‌ی دسته‌ی پیشین به‌دستور کارفرما به مجموعه‌ی `LIVE` منتقل شد؛ پنج مقاله‌ی این دسته و سه راهنمای وابسته در `BATCH` نشسته‌اند. هیچ تغییر کدی در قالب لازم نشد (اصطلاحات `septoplasty-sinus` و `sterile-environment` از قبل ثبت‌شده بودند).

## لینک‌های دانلود

### Google Drive (هر شش فایل، عمومی با لینک)
| نامک | لینک مستقیم |
|---|---|
| `septoplasty-overview` | https://drive.google.com/uc?id=1rn_Dxxur9vG7AZv-20wBFhb5YmtP2PuT&export=download |
| `septoplasty-preparation` | https://drive.google.com/uc?id=14zCG-x6v7FfowZV8CLpQwSY--PtCOayL&export=download |
| `septoplasty-recovery` | https://drive.google.com/uc?id=1iUP1OEYy9EUFBk2GHnmPjENPx3BslevQ&export=download |
| `why-operating-room-is-sterile` | https://drive.google.com/uc?id=17imsZcPVjrz_FTgcg5Jsj3ZW1DkBDASD&export=download |
| `blood-donation-reserve-before-surgery` | https://drive.google.com/uc?id=1ppym780IIr8nGELX58OMroGmiWfJYdb9&export=download |
| بسته‌ی ترکیبی پنج‌تایی | https://drive.google.com/uc?id=1NUbR10uTXqs6IzuJPLw2zhLj0qlfgjqr&export=download |

### گیت‌هاب (همه‌ی فایل‌ها روی برنچ `arena/95bf3749-or-site`)
الگو: `https://github.com/lakanzino/OR-site/raw/arena/95bf3749-or-site/releases/article-packages/<file>`

- `article-package-septoplasty-{overview,preparation,recovery}.zip`
- `article-package-why-operating-room-is-sterile.zip`
- `article-package-blood-donation-reserve-before-surgery.zip`
- `BATCH-PUBLISH-2026-10-27-5new-articles.zip`

## فهرست و وضعیت فیلترها

| # | نامک | نوع محتوا / تاکسونومی | کلمه | H2 | لینک داخلی | منبع خارجی | تصویر | فیلتر فنی |
|---:|---|---|---:|---:|---:|---:|---|---|
| ۱ | `septoplasty-overview` | surgery / `surgery-overview` · `septoplasty-sinus` | ۱۱۴۳ | ۱۱ | ۵ | ۳ | تولیدی، ۸۳KB | PASS |
| ۲ | `septoplasty-preparation` | surgery / `before-surgery` · `septoplasty-sinus` | ۱۰۶۹ | ۱۱ | ۵ | ۳ | تولیدی، ۶۴KB | PASS |
| ۳ | `septoplasty-recovery` | surgery / `after-surgery` · `septoplasty-sinus` | ۱۱۲۵ | ۱۱ | ۶ | ۳ | تولیدی، ۵۴KB | PASS |
| ۴ | `why-operating-room-is-sterile` | patient_guide / `sterile-environment` | ۱۲۵۰ | ۱۱ | ۵ | ۳ | عکس کارفرما، ۱۰۱KB | PASS |
| ۵ | `blood-donation-reserve-before-surgery` | patient_guide / `general-preparation` | ۱۲۰۵ | ۱۱ | ۵ | ۳ | تولیدی، ۶۰KB | PASS |

همه‌ی مقالات: بدون H1 در متن، صفر عبارت ربات‌گونه (AI tells)، صفر نام دارو/برند، صفر دوز/ساعت ناشتایی/درصد، جعبه‌ی هشدار ۱۱۵، سلب‌مسئولیت استاندارد، ۴ پرسش متداول منطبق با `article.json`، همه‌ی لینک‌های خارجی با `rel="noopener" target="_blank"`.

## منابع خارجی (همه نشانی‌ها راستی‌آزمایی شد)

- MedlinePlus (NLM): Septoplasty `ency/article/003012.htm`، دستورالعمل ترخیص `ency/patientinstructions/000246.htm`، Sinusitis `ency/article/000647.htm`، تزریق و اهدای خون `bloodtransfusionanddonation.html` و `ency/patientinstructions/000431.htm`، مراقبت زخم بسته `ency/patientinstructions/000738.htm`.
- سازمان جهانی بهداشت (WHO): ایمنی بیمار، پیشگیری و کنترل عفونت، برگه‌ی واقعیت ایمنی بیمار.
- آکادمی جراحان گوش و حلق و بینی آمریکا (AAO-HNS): منابع آموزش بیمار `entnet.org`.

## موارد needing تأیید کارفرما

1. **کوتاه‌سازی تیتر سه‌گانه‌ی سپتوپلاستی:** تیتر ورودی دستور (۶۸ نویسه) از سقف دروازه (۶۵) می‌گذشت؛ «/سینوس» حذف شد و تیترها ۶۲ نویسه‌ای‌اند. پوشش موضوع سینوس در تاکسونومی `septoplasty-sinus`، کلیدواژه‌ها و متن باقی است. اگر تیتر کامل با «سینوس» ترجیح دارید، باید سقف دروازه در `tools/content-lint.py` عوض شود.
2. **چهار تصویر تولیدی:** طبق سیاست «تولیدی برای مقاله‌ی بدون تصویر» ساخته شده‌اند (صحنه‌های واقع‌گرای اتاق عمل، مطب، ریکاوری خانه و مرکز اهدای خون؛ بدون متن و لوگو). تصویر ردشده‌ی قبلی ذخیره‌ی خون (`1000104416`) استفاده نشد. تأیید بصری نهایی با کارفرما پیش از ایمپورت توصیه می‌شود.
3. **وابستگی لینک داخلی به بسته‌های هنوز غیرزنده:** لینک `/patient-guide/home-wound-care/` در دو مقاله (`septoplasty-recovery`، `why-operating-room-is-sterile`) و `/patient-guide/general-preparation-before-surgery/` در مقاله‌ی ذخیره‌ی خون، به بسته‌های آماده ولی هنوز ایمپورت‌نشده اشاره دارند. لینک‌های متقابل سه‌گانه‌ی سپتو با ایمپورت یک‌جای هر سه حل می‌شوند.
4. **پس از ایمپورت:** LiteSpeed → Purge All، سپس مجموعه‌ی `LIVE` در `tools/content-lint.py` و شمارش `PUBLISH-PRIORITY` با عدد REST API بازتنظیم شود و آینه‌ی `content/live-mirror/` سه فایلی هر مقاله را بگیرد. شمارش مخزن پس از ایمپورت این پنج بسته: ۷۸ مقاله.

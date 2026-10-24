# آینه‌ی محتوای زنده — فهرست وضعیت (2026-10-22)

این پوشه، آینه‌ی متنی محتوای منتشرشده‌ی `hamyarjarahi.ir` را نگه می‌دارد.
طبق **قانون آینه** (Drive، ۳۰ مهر ۱۴۰۵) محتوای این پوشه باید کلمه‌به‌کلمه برابر
سایت زنده باشد و **هیچ ردیفی بدون تأیید کارفرما اضافه/حذف/ویرایش نمی‌شود**.

> وضعیت فعلی: **فهرست‌برداری انجام شده، انتقال متن‌ها در انتظار آپلود بستهٔ آینه است.**
> دلیل: در این محیط، خواندن فایل‌های فارسی از Google Drive فقط از راه واسط متنی ممکن است
> و برای ~۱۹۰KB متن، خطر خرابی/ناقص‌شدن کاراکترها وجود دارد. راه مطمئن: آپلود ZIP آینه
> در فضای کاری و انتقال بایت‌به‌بایت با checksum.

## فهرست مرجع (Drive → Live Mirror، آخرین تغییر 2026-10-22 02:12 UTC)

### صفحه‌های ایستا — `pages/` (۶)
| فایل | حجم | وضعیت در مخزن |
|---|---:|---|
| `about.md` | 7,118 B | ⏳ در انتظار انتقال |
| `contact.md` | 4,847 B | ⏳ |
| `faq.md` | 6,244 B | ⏳ |
| `medical-disclaimer.md` | 5,614 B | ⏳ |
| `privacy.md` | 6,765 B | ⏳ |
| `terms.md` | 5,617 B | ⏳ |
| ~~`editorial-policy.md`~~ | — | حذف‌شده در 0.9.1 (به زباله‌دان وردپرس منتقل شد) — نباید در آینه باشد |

### ستون‌ها — `pillars/` (۴)
| فایل | حجم | وضعیت |
|---|---:|---|
| `what-is-operating-room.md` | 16,061 B | ⏳ |
| `surgical-care-team.md` | 36,545 B | ⏳ |
| `patient-journey.md` | 30,493 B | ⏳ |
| `glossary-surgical-terms.md` | 24,778 B | ⏳ |

### راهنماها — `guides/` (۱)
| فایل | حجم | وضعیت |
|---|---:|---|
| `operating-room-moment-by-moment.md` | 30,170 B | ⏳ |

### تصاویر — `images/` (۵ WebP، ۱۲۰۰×۶۷۵)
`featured.webp`، `glossary-surgical-terms.webp`، `what-is-operating-room.webp`،
`surgical-care-team.webp`، `patient-journey.webp`، `operating-room-moment-by-moment.webp`
— ⏳ انتقال باینری لازم است (آپلود ZIP).

### بسته‌های ایمپورت — `_imported-packages/` (۵ ZIP)
`article-package-glossary-surgical-terms.zip`، `article-package-what-is-operating-room.zip`،
`article-package-surgical-care-team.zip`، `article-package-patient-journey.zip`،
`article-package-operating-room-moment-by-moment.zip` — ⏳ آپلود ZIP.

### اسناد حاکم بر آینه (Drive → همین پوشه)
| فایل | حجم | وضعیت |
|---|---:|---|
| `00-قانون-آینه.md` | 2,634 B | ⏳ |
| `00-مانيفست-آينه.md` | 6,141 B | ⏳ |
| `وضعیت-استقرار.md` | 1,897 B | ⏳ |

## واقعیت تأییدشده (همین حالا)
- قالب فعال روی سایت: **0.9.1** — کد آن در مخزن نیست (مخزن روی 0.4.0 بود).
- ۵ مقاله با پاسخ ۲۰۰ و بدنهٔ کامل تأیید شده‌اند (تأیید کارفرما، ۳۰ مهر ۱۴۰۵).
- CPT واژه‌نامه هنوز محتوای زنده ندارد؛ پوشه عمداً خالی است.
- `editorial-policy` از آینه و از درایو حذف شده و روی سایت ۴۰۴ می‌دهد.

## گام بعدی برای تکمیل آینه
1. آپلود `lax-theme-child-install-0.9.1.zip` → انتقال کد واقعی به `themes/lax-theme-child/` و تگ `child-v0.9.1`.
2. آپلود بستهٔ آینه (پوشه‌های `pages/`، `pillars/`، `guides/`، `glossary/`، `images/`) → انتقال بایت‌به‌بایت + SHA-256 در `content/live-mirror/CHECKSUMS.txt`.
3. پس از آن، نسخهٔ 0.9.2 واقعی (0.9.1 + کانال به‌روزرسانی) ساخته و منتشر می‌شود.

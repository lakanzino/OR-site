# 00 — نمای کلی

# Hamyar Doctors — Handover (hamyarjarahi.ir)

نسخهٔ تحویل: افزونه hamyar-doctors + قالب فرزند lax-theme-child (v0.32.0)

قانون طلایی: قالب والد `lax-theme` را هرگز تغییر نده. در `functions.php` فرزند فقط **اضافه** کن، چیزی حذف نکن.

======================================================================



## نقش‌ها

- AI: فقط کد نوشته؛ چیزی را روی سایت نصب نکرده و سایت را ندیده. تمام کدها اجرا نشده و فقط بازبینی ایستا شده‌اند.

- ایجنت (شما): همه‌چیز را کپی و نصب می‌کند و نتیجهٔ صفحه‌های وضعیت را برمی‌گرداند.

- کاربر نهایی ترمینال ندارد. همهٔ تست‌ها با پیشخوان و مرورگر انجام می‌شود.



## چه ساخته شده

| # | بخش | فایل اصلی |

|---|---|---|

| 1 | لودر و توابع پایه | hamyar-doctors.php |

| 2 | تاکسونومی‌ها (province/city/specialty_type) | includes/taxonomies.php, cpt.php |

| 3 | فیلدهای ACF | includes/acf-fields.php + acf-json/ |

| 4 | امنیت و نقش‌ها | includes/security.php, roles.php |

| 5 | ورود با گوگل | includes/auth-google.php |

| 6 | داشبورد پزشک | includes/dashboard.php + js/css |

| 7 | صفحهٔ پروفایل | includes/profile-render.php + single-doctor.php |

| 8 | آرشیو و کارت | includes/archive-render.php + قالب‌های taxonomy |

| 9 | پل امتیازدهی | includes/rating-bridge.php + js/rating.js |

| 10 | جست‌وجوی هوشمند و فیلتر | includes/search-filters.php |

| 11 | REST API عمومی | includes/rest-api.php |

| 12 | کارت جاسازی‌شدنی و embed | includes/shortcodes.php, embed.php |

| 13 | لایهٔ تم (فونت، پالت، دارک‌مود، اعتبارسنجی بدون پاپ‌آپ) | assets/css/theme*.css + کلاس HJ_Theme_Layer در functions.php فرزند |

| 14 | ابزار ورود داده + عکس پیش‌فرض | includes/seed-data.php |

| 15 | قلاب‌های نوبت‌دهی آینده | includes/hooks.php |

| 16 | همین سند | — |



## چیزهایی که **تغییر نکرده** (نباید تغییر کند)

CPT `doctor`، اسلاگ‌ها و URLهای موجود، جدول `hj_doctor_votes` (ساختار و داده)، منوهای `primary` و `footer`، symptom-checker (غیرفعال و دست‌نخورده)، قالب والد.



## URLهای موجود که باید سالم بمانند

/doctor/<slug>/ ، /doctor/114/ ، /doctor/ ، /specialty/<x>/ ، /my-profile/ ، /diseases/ ، /glossary/ ، /patient-guide/ ، /or-environment/ ، /surgery/ ، /student/ ، /about/ ، /contact/ ، /faq/ ، /privacy/ ، /terms/

پزشکان نمونه: /doctor/dr-demo-orthopedics/ ، /doctor/dr-demo-urology/ ، /doctor/dr-demo-general/



## صفحه‌های وضعیت (فقط مدیر کل، همه زیر /wp-admin/admin-post.php?action=...)

hj_auth_status ، hj_dash_status ، hj_pr_status ، hj_ar_status ، hj_rb_status ، hj_sf_status ،

hj_rest_status ، hj_wd_status ، hj_theme_status ، hj_seed_status ، hj_hooks_status

(بعضی پارامتر اختیاری دارند: &id=<شناسهٔ پزشک> یا &q=<عبارت>)



## ثابت‌ها (در wp-config.php، بالاتر از خط "That's all, stop editing")

| ثابت | مقدار | اثر |

|---|---|---|

| HJ_DOCTORS_SAFE_MODE | true | **کلید اضطراری**: ماژول‌های افزونه بارگذاری نمی‌شوند |

| HJ_FONTS_MODE | 'local' (پیش‌فرض) / 'cdn' / 'off' | منبع فونت |

| HJ_DARK_MODE | 'auto' (پیش‌فرض خاموش) | دارک‌مود |

| HJ_THEME_LAYER_DISABLED | true | خاموش‌کردن لایهٔ تم |

| HJ_FACETWP_ENABLED | true | پل FacetWP (پیش‌فرض خاموش) |

| HJ_FACETWP_SEARCH_MODE | 'get' | راه‌حل جایگزین جست‌وجو با FacetWP |

| HJ_HEADER_SEARCH_MENU | — | تزریق فرم جست‌وجو به منوی هدر (بخش ۱۰) |

| HJ_REST_DISABLED | true | خاموش‌کردن REST عمومی |

| HJ_REST_CACHE_TTL | 900 | عمر کش REST (ثانیه) |

| HJ_WD_DISABLED | true | خاموش‌کردن ویجت/embed |

| HJ_APPOINTMENTS_ENABLED | **تعریف نشود** | نوبت‌دهی در این مرحله باید خاموش بماند |

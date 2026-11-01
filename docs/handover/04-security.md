# 04 — امنیت

======================================================================



## ۱) Nginx (اجباری، قبل از جمع‌آوری مدارک واقعی)

در server block سایت اضافه کن و reload کن (اگر خودت دسترسی نداری، از میزبان بخواه):

```nginx

location ^~ /wp-content/uploads/hj-private/ {

    deny all;

    return 403;

}

```

- دانلود مجاز از طریق PHP (`hj_doc_download`) انجام می‌شود، پس این قانون چیزی را خراب نمی‌کند.

- روی Apache، `.htaccess` در همان پوشه توسط افزونه ساخته می‌شود؛ باز هم با تست مستقیم URL (چک‌لیست G) مطمئن شو.

- اگر پشت Cloudflare/CDN است، همین مسیر را کش نکن.



## ۲) قواعد کش (WP Rocket / LiteSpeed / W3TC / کش سرور)

**کش نشود:**

- صفحهٔ `/my-profile/`

- هر آدرس با querystring: `hj_*` (hj_google_start, hj_edit, hj_q, hj_sort, hj_specialty, hj_province, hj_city, hj_type, hj_widget, hj_widget_js, hj_ph…) ، `fwp_*` ، `hj_theme`

- مسیر `/wp-admin/admin-post.php` و `admin-ajax.php`

- REST خودش کش داخلی دارد؛ لایهٔ کش دیگری رویش نگذار.

- `/?hj_google_start=1` کوکی می‌گذارد؛ هرگز کش نشود.



## ۳) حداقل امنیت اجرایی

- هر پزشک فقط پروفایل خودش؛ مدیر برای moderation.

- فقط نقش `doctor` را به پزشکان بده؛ نقش‌های `hj_*` به کاربر دیگری اختصاص نده.

- آپلود فقط JPG/PNG/WebP (عکس) و انواع مجاز مدرک (سقف ۲MB) با بررسی MIME.

- پروفایل‌ها پیش‌فرض پس از بررسی مدیر منتشر می‌شوند.

- همهٔ فرم‌ها nonce دارند. rate limit برای ثبت‌نام، رأی، REST و ایمپورت فعال است.

- HTTPS اجباری؛ http→https و www/non-www یکسان (برای OAuth حیاتی).

- گزارش بده اگر `uploads/` لیست‌پذیر (Directory Listing) باشد.



## ۴) کلید اضطراری و بازگشت سریع

| وضعیت | اقدام |

|---|---|

| هر مشکل جدی | wp-config.php: `define('HJ_DOCTORS_SAFE_MODE', true);` |

| فقط لایهٔ تم | `define('HJ_THEME_LAYER_DISABLED', true);` |

| فقط REST | `define('HJ_REST_DISABLED', true);` |

| فقط ویجت/embed | `define('HJ_WD_DISABLED', true);` |

| فقط FacetWP | حذف `HJ_FACETWP_ENABLED` |

| قالب جدید خراب | نام همان فایل قالب در lax-theme-child را عوض کن (به قالب والد برمی‌گردد) |

| ابزار ایمپورت/عکس پیش‌فرض | نام includes/seed-data.php را عوض کن (دادهٔ واردشده می‌ماند) |

| پل‌های نوبت‌دهی | نام includes/hooks.php را عوض کن |

| functions.php فرزند خراب | بکاپ فایل را برگردان |



## ۵) نکات امنیتی ثابت

- قالب والد را تغییر نده.

- `HJ_APPOINTMENTS_ENABLED` را تعریف نکن.

- اطلاعات تماس شخصی پزشک (تلفن همراه، شمارهٔ نظام) در خروجی عمومی/REST/ویجت نیست؛ تست کن.

- CPT fallback را روی سایت زنده فعال نکن.

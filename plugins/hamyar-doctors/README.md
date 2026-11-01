# همیار پزشک‌ها (hamyar-doctors)

افزونهٔ مستقل «پزشک‌ها» برای سایت همیار جراحی: نوع محتوای `doctor` با taxonomyهای تخصص و شهر، زمینه‌های نمایه با JSON محلی ACF (بدون Sync دستی) و نقش «پزشک» که فقط به نمایهٔ خودش دسترسی دارد. مستقل از قالب فرزند است و در حالت عادی حتی یک خط به `debug.log` نمی‌نویسد.

## ساختار (بخش‌ها)

| بخش | فایل | محتوا |
| --- | --- | --- |
| ۱ | `hamyar-doctors.php` | helperها (`hj_doctors_path/url`، `hj_to_en_digits`، `hj_doctors_default_photo_url` همگی با نگهبان `function_exists`) + کلاس اصلی و قلاب فعال/غیرفعال |
| ۲ | `includes/taxonomies.php` + `includes/cpt.php` | taxonomyهای `doctor_specialty` / `doctor_city` و نوع محتوای `doctor` با `map_meta_cap` روشن (بدون capability_type سفارشی، طبق مدل بخش ۴) |
| ۳ | `includes/acf-fields.php` + `acf-json/group_hj_doctor_profile.json` | گروه زمینهٔ «اطلاعات پزشک» فقط به‌صورت JSON محلی؛ این فایل مسیرهای save/load را نشانه می‌گیرد و روی ذخیره سانیتایز می‌کند |
| ۴ | `includes/roles.php` + `includes/security.php` + `includes/dashboard.php` | کلاس `HJ_Doctors_Roles` (نقش پزشک بدون edit_posts، capهای سطح مدیر به‌صورت پویا برای دارندگان `manage_options`، مالکیت = نویسنده یا متا `hj_doctor_post_id`، قفل پیشخوان به نفع `/my-profile/`، سقف نوع/حجم/تعداد بارگذاری) + primitiveهای `hj_sec_*` + صفحهٔ ناحیه پزشک و شورت‌کد شروع |
| ۵ | `includes/auth-google.php` | پل ورود/ثبت‌نام با گوگل روی Nextend Social Login: کوکی نیت امضاشده (`hj_doc_intent`، HMAC با `wp_salt`، ۳۰ دقیقه)، ورودی پزشکان `/?hj_google_start=1`، هوک‌های `nsl_register_new_user`/`nsl_login` + آداپتور عمومی `hj_doctors_google_user_ready`، شورت‌کد `[hj_google_login]` و صفحهٔ وضعیت فقط-مدیر `admin-post.php?action=hj_auth_status` |
| دارایی | `assets/img/doctor-default.jpg` | عکس پیش‌فرض نمایه که `hj_doctors_default_photo_url()` برمی‌گرداند |

## زمینه‌های نمایه

`hj_medical_code` (کد نظام پزشکی، ارقام فارسی/عربی خودکار به لاتین)، `hj_phone`، `hj_address`، `hj_work_hours`، `hj_education`، `hj_profile_url`، `hj_accepts_new`.
خواندن در قالب: `hj_doctors_field( 'hj_phone', $post_id )` و تصویر: `hj_doctors_photo_url( $post_id )` — بدون ACF هم خطا نمی‌دهند.

## نصب و فعال‌سازی

1. پوشهٔ `hamyar-doctors` را در `wp-content/plugins/` بگذارید (یا از ZIP ریلیز نصب کنید).
2. افزونه را فعال کنید: نقش `doctor` ساخته می‌شود (با hash-check، بدون نوشتن cap در نقش مدیر)، صفحهٔ `/my-profile/` در صورت نبودن ساخته و rewriteها flush می‌شوند.
3. برای هر پزشک: یک کاربر با نقش «پزشک» بسازید و نوشتهٔ پزشک او را با **نویسنده = همان کاربر** ایجاد کنید (یا متا `hj_doctor_post_id` را بگذارید)؛ دسترسی خودش خودکار فقط همین نمایه است و پیشخوان به روی او بسته می‌شود.

## ورود با گوگل (بخش ۵)

- بدون Nextend Social Login، فایل بی‌خطر غیرفعال می‌ماند و فقط در لاگ یادداشت می‌شود.
- کوکی نیت امضاشده تعیین می‌کند چه کسی «قصد پزشک‌شدن» داشته: بیمارِ بدون کوکی همان `subscriber` می‌ماند؛ ادمین/ویراستار هرگز تنزل نمی‌گیرند (`hj_doctors_assign_doctor_role`).
- سقف ثبت‌نام: ۱۰ در ساعت **به ازای IP** (آرگومان چهارم `hj_sec_rate_limit`)؛ اگر سقف پر شود کاربر می‌ماند و نقش در ورود بعدی با متا `hj_role_pending` داده می‌شود.
- اتصال: پروژهٔ Google Cloud (OAuth consent خارجی + Publish)، Client ID/Secret در Nextend، و Redirect URI عیناً `https://<دامنه>/wp-login.php?loginSocial=google`. جزئیات کامل در چنج‌لاگ پروژه (بخش ۵).
- عیب‌یابی بدون ترمینال: `wp-admin/admin-post.php?action=hj_auth_status` (فقط مدیر، خروجی متن سادهٔ OK/MISSING).
- escape hatch: `define( 'HJ_DOCTORS_SAFE_MODE', true );` در `wp-config.php` همهٔ ماژول‌ها را لود نمی‌کند.

## Rollback

- فقط زمینه‌ها: حذف/rename کردن `includes/acf-fields.php` + پوشهٔ `acf-json/` کافی است (گروه زمینه فقط آنجا زندگی می‌کند).
- فقط قفل پیشخوان: `add_filter( 'hj_doctors_block_wp_admin', '__return_false' );` در یک mu-plugin یا قالب.
- کل افزونه: غیرفعال‌سازی داده‌ها را نگه می‌دارد؛ برای پاک‌سازی نقش: `remove_role( 'doctor' )` و حذف گزینه‌های `hj_doctors_roles_hash`، `hj_doctors_role_caps_added`، `hj_doctors_log` و `hj_doctors_dashboard_page`.

## چک‌لیست بعد از جایگذاری روی سرور

- فروش/نوبت‌دهی دستی لازم نیست؛ Sync های ACF فقط در `acf-json/` پایین می‌آیند، نه قالب فرزند.
- Purge All LiteSpeed بعد از جایگذاری.
- تست ویرایش یک پزشک نمونه (همهٔ تب‌ها) و یک کاربر با نقش پزشک: باید فقط نمایهٔ خودش را ببیند و پیشخوان به `profile.php` برگردد.
- `debug.log` باید خالی بماند.
- فهرست کاری برای مدیر: ساخت تخصص/شهر، افزودن پزشک، بررسی بایگانی `/doctors/`.

## آزمون‌ها

محیط گیت‌هاب باینری PHP ندارد؛ مثل بقیهٔ پروژه با `tools/php-check.py` (بررسی توازن پرانتز/رشته/تگ) و `python3 -m json.tool` برای JSON بررسی می‌شود:

```bash
python3 tools/php-check.py plugins/hamyar-doctors/*.php plugins/hamyar-doctors/includes/*.php
python3 -m json.tool plugins/hamyar-doctors/acf-json/group_hj_doctor_profile.json > /dev/null
```

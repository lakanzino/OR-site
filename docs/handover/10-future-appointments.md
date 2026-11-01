# 10 — نوبت‌دهی آینده

======================================================================

وضعیت فعلی: **خاموش**. هیچ جدول/CPT/CSS ساخته نشده.



## اتصال‌ها

- قلاب‌های منتشرشده: `hj_doctor_after_schedule` ← پل ← `hj_appt_profile_slot($doctor_id)` ؛ `hj_doctor_dashboard_appointments` ← پل ← `hj_appt_dashboard_panel($doctor_id)`

- کلید: `hj_appointments_enabled` (فیلتر) و `hj_appointments_doctor_enabled` (برای هر پزشک)

- داده: `hj_doctor_schedule_for_booking($id)` (ساعات کاری، فقط خواندنی) ؛ `hj_doctor_booking_context($id)` (اطلاعات عمومی)

- خروجی خالی = هیچ wrapper چاپ نمی‌شود.



## معماری پیشنهادی

- **افزونهٔ جدا** `hamyar-appointments` با جدول‌های اختصاصی: `{prefix}hj_appt_bookings` (با UNIQUE(doctor_id, starts_at)) و `{prefix}hj_appt_exceptions`؛ ساخت با dbDelta و نسخه در option `hj_appt_db_version`.

- نه CPT (حجم بالا + اطلاعات خصوصی بیمار + دیده‌شدن در جست‌وجو/REST).

- زمان‌ها UTC؛ نمایش با wp_timezone().

- REST فقط زیر `hj/v1/appointments` با permission_callback؛ API عمومی بخش ۱۱ هرگز نوبت/تلفن برنمی‌گرداند.

- کپبیلیتی‌ها: `hj_manage_own_appointments` و `hj_manage_all_appointments` (افزونهٔ آینده اضافه می‌کند؛ بخش ۴ تغییر نمی‌کند).

- تنظیمات هر پزشک: meta با پیشوند `hj_appt_` روی پست doctor.

- جدول `hj_doctor_votes` را لمس نمی‌کند. با غیرفعال‌کردن افزونه همه‌چیز مثل قبل کار می‌کند؛ حذف داده فقط با گزینهٔ صریح.

- کلاس‌ها با `hj-appt-`؛ پیام‌ها inline؛ بدون popup.



======================================================================

# پایان — قوانین ثابتی که ایجنت باید همیشه یادش باشد

======================================================================

1. قالب والد را تغییر نده؛ در functions.php فرزند فقط append.

2. اسلاگ و URL موجود را تغییر نده؛ CPT را بازسازی نکن؛ موتور امتیاز را عوض نکن؛ ساختار hj_doctor_votes را دست نزن.

3. قانون Nginx برای hj-private را تأیید کن؛ تا آن موقع مدرک واقعی نگیر.

4. /my-profile/ و querystringهای hj_* ، fwp_* ، hj_theme را از کش خارج کن.

5. کلید اضطراری: `define('HJ_DOCTORS_SAFE_MODE', true);`

6. هر نتیجهٔ ناموفق را همراه با نام صفحهٔ وضعیت و متن آن برای کاربر بفرست (کاربر ترمینال ندارد؛ لاگ را از پیشخوان یا File Manager بگیر).

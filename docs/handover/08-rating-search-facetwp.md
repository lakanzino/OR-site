# 08 — امتیاز، جست‌وجو، FacetWP

======================================================================



## امتیاز

- فقط ستاره؛ رأی مهمان مجاز؛ یک رأی برای هر IP هش‌شده؛ میانگین با ≥۵ رأی تأییدشده؛ تأیید دستی.

- جدول `hj_doctor_votes`: ساختار و داده دست‌نخورده. ستون‌ها خودکار تشخیص داده می‌شوند (فیلترهای hj_rb_table_name و hj_rb_column_map در صورت نیاز).

- ابزارهای مدیر روی هر پزشک: تعدیل دستی میانگین، override، نمایش اجباری، امتیاز پنهان کادر درمان/بیمارستان (فقط مدیر؛ در میانگین نهایی اثر دارد ولی رأی جدا نیست).

- قوانین امتیاز فقط در /privacy/ و /terms/؛ هیچ‌جا در UI نیست.

- (فصل ۰۳-H را اجرا کن.)



## جست‌وجو

- شاخص PHP داخل transient با نام `hj_sf_index_v1` (۶ ساعت). بعد از ایمپورت داده یا تغییر ترم‌ها خودکار پاک می‌شود. دکمهٔ ساخت مجدد: hj_sf_status.

- فیلدهای جست‌وجو: عنوان، name_fa، name_en، specialty_fa، specialty_en، نام استان/شهرستان.

- اگر کلید متای پزشکان قدیمی فرق داشت: فیلترهای `hj_sf_name_keys` و `hj_sf_spec_keys` (و `hj_rest_name_keys`, `hj_rest_spec_keys`).



## FacetWP (اختیاری، پیش‌فرض خاموش)

- فعال‌سازی: `define('HJ_FACETWP_ENABLED', true);` در wp-config.php.

- نام قلاب‌ها و کلیدهای تنظیمات از حافظه نوشته شده و آزمون‌نشده است. نام دقیق facetها را از صفحهٔ hj_sf_status بخوان.

- خط‌مشی پیشنهادی facetها (ترکیب AND): تخصص (taxonomy: specialty) + استان (province) + شهرستان (city)، هر سه Dropdown/Checkboxes با Data source = Taxonomy؛ قالب لیست: post_type=doctor, post_status=publish. نمایش فقط پزشکان عمومی توسط افزونه تضمین می‌شود.

- **اصلاح یک‌خطی (فقط با FacetWP):** در includes/archive-render.php، تابع render_page، درست قبل از `</section>` و بعد از `</div>` مربوط به `#hj-ar-results` اضافه کن:

  ```php

  <?php do_action('hj_doctor_archive_after_results'); ?>

  ```

- مشکل: ‌فیلتر `hj_sf_register_facets` → `__return_false` یا حذف ثابت، یا `HJ_FACETWP_SEARCH_MODE='get'`.

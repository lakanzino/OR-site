# 01 — درخت فایل‌ها

======================================================================



```

wp-content/

├─ plugins/

│ └─ hamyar-doctors/

│ ├─ hamyar-doctors.php (بخش ۱)

│ ├─ index.php (بخش ۱)

│ ├─ includes/

│ │ ├─ security.php (۴)

│ │ ├─ cpt.php (۲؛ ثبت CPT پیش‌فرض خاموش است)

│ │ ├─ taxonomies.php (۲)

│ │ ├─ acf-fields.php (۳)

│ │ ├─ roles.php (۴)

│ │ ├─ auth-google.php (۵)

│ │ ├─ dashboard.php (۶)

│ │ ├─ profile-render.php (۷)

│ │ ├─ archive-render.php (۸)

│ │ ├─ rating-bridge.php (۹)

│ │ ├─ search-filters.php (۱۰)

│ │ ├─ rest-api.php (۱۱)

│ │ ├─ shortcodes.php (۱۲)

│ │ ├─ embed.php (۱۲)

│ │ ├─ seed-data.php (۱۴)

│ │ └─ hooks.php (۱۵)

│ ├─ assets/

│ │ ├─ css/

│ │ │ ├─ profile.css (۷)

│ │ │ ├─ dashboard.css (۶)

│ │ │ ├─ archive.css (۸)

│ │ │ ├─ widget.css (۱۲)

│ │ │ ├─ theme.css (۱۳)

│ │ │ └─ theme-dark.css (۱۳)

│ │ ├─ js/

│ │ │ ├─ dashboard.js (۶)

│ │ │ ├─ rating.js (۹)

│ │ │ ├─ copy-embed.js (۱۲)

│ │ │ └─ theme.js (۱۳)

│ │ ├─ fonts/

│ │ │ ├─ fonts.css (۱۳)

│ │ │ ├─ Vazirmatn-Variable.woff2 ← ایجنت دانلود و می‌گذارد

│ │ │ └─ Inter-Variable.woff2 ← ایجنت دانلود و می‌گذارد

│ │ └─ img/

│ │ └─ operating-room.jpg ← ایجنت می‌گذارد (۱۴)

│ ├─ acf-json/

│ │ ├─ group_hj_doctor_profile.json (۳)

│ │ └─ group_hj_doctor_system.json (۳)

│ └─ languages/ (خالی؛ برای آینده)

│

├─ themes/

│ ├─ lax-theme/ ← دست نزن

│ └─ lax-theme-child/

│ ├─ functions.php ← فقط APPEND: (الف) body class بخش ۱ (ب) کلاس HJ_Theme_Layer بخش ۱۳

│ ├─ single-doctor.php (۷)

│ ├─ archive-doctor.php (۸)

│ ├─ taxonomy-specialty.php (۸)

│ ├─ taxonomy-province.php (۸)

│ ├─ taxonomy-city.php (۸)

│ └─ taxonomy-specialty_type.php (۸)

│

└─ uploads/

   └─ hj-private/ ← خودکار ساخته می‌شود (مدارک خصوصی پزشکان). باید در Nginx بسته شود. (فصل ۰۴)

```



نکته‌ها:

- پوشهٔ `docs/` و فایل‌های CSV داده **داخل wp-content نمی‌روند**؛ فقط در Drive (فصل ۰۵).

- در `style.css` فرزند فقط خط `Version:` را در صورت تمایل به 0.32.0 تغییر بده (اختیاری، بی‌اثر روی رفتار).

- نام فایل‌های ACF JSON و فونت‌ها دقیقاً مطابق جدول باشد.

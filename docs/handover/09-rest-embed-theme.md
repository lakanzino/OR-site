# 09 — REST، embed، تم

======================================================================



## REST (بخش ۱۱)

- `GET /wp-json/hj/v1/doctor/<id>` و `GET /wp-json/hj/v1/doctors?q=&specialty=&province=&city=&per_page=&page=` (حداکثر ۲۰ در صفحه).

- فقط خروجی عمومی؛ لیست سفید کلیدها + پاک‌سازی نهایی. کش transient با نسخهٔ سراسری؛ ۹۰۰ ثانیه؛ دکمهٔ پاک‌سازی در hj_rest_status.

- هدرها: noindex، CORS بدون credentials.



## ویجت/embed (بخش ۱۲)

- شورت‌کد: `[hj_doctor_card id="114" layout="auto|vertical|horizontal" en="1|0"]`

- iframe: `/?hj_widget=<ID>&layout=v|h` ؛ اسکریپت: `/?hj_widget_js=1` با عنصر `.hj-doctor-embed[data-hj-doctor][data-hj-layout]`.

- ایزولاسیون CSS با پیشوند `hj-` و `all:initial` (روی .hj-wd و .hj-eb)؛ روی تگ‌های سراسری مثل body و a استایل نمی‌دهد.



## تم (بخش ۱۳)

- پالت: navy `#061A2E`–`#0F3556`، teal `#14C8C0`–`#0EA5A0`، خاکستری `#6B7C8F`.

- فونت: Vazirmatn (فارسی) و Inter (انگلیسی)؛ وزن‌ها ۳۰۰–۸۰۰. حالت `HJ_FONTS_MODE`: local پیش‌فرض، cdn، off.

- اگر خطای MIME برای woff2 دیدی: `.htaccess` → `AddType font/woff2 .woff2`.

- لایه فقط در محدودهٔ صفحه‌های پزشکان/آرشیو/تاکسونومی‌ها/داشبورد/شورت‌کدها لود می‌شود (کلاس body: hj-scope).

- دارک‌مود: heuristic است (تطبیق نام کلاس‌ها) و خاموش؛ ابتدا با `?hj_theme=dark` و سپس HJ_DARK_MODE='auto'. اصلاحات دستی در بخش «exceptions» انتهای theme-dark.css.

- CSS درون‌خطی بخش‌های ۹ و ۱۰ دست‌نخورده است.



## محدودیت‌های شناخته‌شده (گزارش نشده یعنی شناخته‌شده)

1. چیپ‌های آرشیو بر اساس تعداد پست‌های منتشرشده شمرده می‌شوند؛ پزشک منتشرشدهٔ مخفی ممکن است صفحهٔ خالی بسازد (ظاهری).

2. پوشهٔ hj-private در Nginx فقط با قانون دستی محافظت می‌شود.

3. تأیید رأی در داشبورد قدیمی هوک ندارد؛ کش تا ۹۰۰ ثانیه عقب می‌ماند.

4. `has_post_thumbnail()` برای پزشک بدون عکس همچنان false می‌دهد؛ قالبی که با آن شرط بگذارد، fallback را نشان نمی‌دهد.

5. بخش ۹ `apply_sort` اشتباه بود؛ `fix_rating_sort` بخش ۱۰ جایگزین آن است (فایل بخش ۹ عمداً دست نخورد).

6. در بخش ۶ فرم سبک سفارشی (نه acf_form) و مدارک خصوصی در hj-private (نه فیلد ACF documents).

7. اگر dashboard.js هنوز `alert(` دارد، فایل را برای اصلاح بفرست.

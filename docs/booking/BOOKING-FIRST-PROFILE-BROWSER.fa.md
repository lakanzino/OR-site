# مرحلهٔ ۵.۲۷ — ساخت اولین پروفایل با ویرایش حین ذخیره

## نیاز و طرح

در مرحلهٔ ۵.۲۳ پاسخ `created` فقط با fetch ساختگی آزموده شده بود. این مرحله از کاربر جراح ساختگی بدون پروفایل و نشست واقعی WordPress در Chromium و مسیر واقعی `hj_dash_save` و MariaDB استفاده می‌کند. hook محدود به CI، ذخیرهٔ برنامه را بعد از ایجاد پست و گرفتن mutex نگه می‌دارد تا متن و ساعت حین انتظار تغییر کنند. سپس بررسی می‌شود پست و snapshot مقادیر ارسالی را دارند، مقادیر جدید در فرم مانده‌اند و reload خودکار رخ نداده است. این فقط یک ترتیب انتخاب‌شده است.

در [بحث Stack Overflow](https://stackoverflow.com/questions/55095790/how-to-prevent-duplicate-posts-with-wp-insert-post) روش بررسی وجود پیش از insert دیده می‌شود، ولی چک و درج جداگانه به تنهایی دربارهٔ هم‌زمانی تضمین ندارد؛ [گزارش wp-rocket](https://github.com/wp-media/wp-rocket/issues/9075) نیز خطر check-then-insert را برجسته می‌کند. کد از منابع کپی نشده است. این مرحله راهکار اتمی یا حفاظت ایجاد تکراری نیست؛ آن‌ها جداگانه بررسی می‌شوند.

آزمون، مرورگر و cookie واقعی در CI ولی HTML shortcode در fixture رندر شده و JS جدا بار می‌شود، OAuth، ACF، سایت و شبکهٔ واقعی نیست. کد افزونه و ZIP بدون تغییر؛ سه بدهی و migration باز و **۷۵٪ ثابت**. CI روی commit `5d7f0c9`: [رزرو 42416938576](https://github.com/lakanzino/OR-site/actions/runs/42416938576) و [MariaDB 42416938686](https://github.com/lakanzino/OR-site/actions/runs/42416938686) موفق.

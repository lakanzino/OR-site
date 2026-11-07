# مرحلهٔ ۵.۳۴ — آزمایش قطع اتصال writer پس از claim

پس از ایجاد claim پایدار آزمایشی و قبل از درج پست WordPress، درخواست اول در barrier توقف می‌کند. درخواست دوم که هنوز هیچ پستی نمی‌بیند با claim هماهنگ تلاش می‌کند. سپس اتصال MariaDB متعلق به `wpdb` درخواست اول با اتصال آزمایشی دیگر KILL می‌شود؛ پس از ادامهٔ درج، hook صرفاً CI شناسهٔ اتصال قبل و بعد را ثبت می‌کند. نتایج واقعی HTTP، claim، پست، usermeta و snapshot کنترل می‌شوند. همهٔ داده‌ها مصنوعی و محیط دورریختنی است.

[بحث Stack Overflow](https://stackoverflow.com/questions/9936699/mysql-rollback-on-transaction-with-lost-disconnected-connection) از دست رفتن transaction/lock و خطر reconnect را توضیح می‌دهد؛ [گزارش GitHub دربارهٔ wpdb](https://github.com/reformatus/kalaka/issues/40) خطر ارسال مجدد statement در اتصال جدید را مطرح می‌کند. این آزمایش فقط فرضیه‌ای دربارهٔ claim autocommit پایدار در این ترتیب را می‌سنجد؛ کد منابع کپی نشده است.

**در انتظار CI.** حتی در صورت موفقیت، حذف claim، writer غیرهماهنگ، قطع یا نتیجهٔ مبهم هنگام خود INSERT یا COMMIT، rollback/restore، migration، ACF و محیط زنده پوشش داده نمی‌شوند. claim و hook آزمایشی نیستند که محصول از آن‌ها استفاده کند؛ کد افزونه و ZIP بدون تغییر، **۷۵٪ ثابت، NO-GO**.

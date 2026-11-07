# مرحلهٔ ۵.۲۴ — اتصال مرورگر واقعی به ذخیرهٔ داشبورد

## انگیزه و دامنه

نمونهٔ ۵.۲۳ فرم وردپرس را در Chromium با شبکهٔ ساختگی و backend را جداگانه آزمود، نه اتصال میان این دو. [پرسش Stack Overflow دربارهٔ آزمون وضعیت pending](https://stackoverflow.com/questions/74360167/playwright-delay-hang-never-resolve-response-with-route-add-latency) و [بحث Playwright در GitHub](https://github.com/microsoft/playwright/issues/31631) تأکید می‌کنند مسیر درخواست و پاسخ و زمان‌بندی باید جداگانه آزموده شوند؛ کد پروژه‌ای کپی نشده است.

fixture دورریختنی جدید با ورود واقعی وردپرس و کوکی آن، فرم را از shortcode افزونه می‌گیرد، JS واقعی افزونه را در صفحه قرار می‌دهد و ذخیرهٔ واقعی را از Chromium روی HTTP loopback به `admin-ajax.php` می‌فرستد. revision، ساعت در snapshot MariaDB، نام و کپی نمایشی کنترل می‌شوند. HTML با endpoint کمکی CI رندر و JS به‌طور مستقیم بارگذاری می‌شود؛ این navigation واقعی صفحهٔ سایت یا OAuth، ACF، تنظیمات وب‌سرور/cache هدف و شبکهٔ اینترنت نیست. پزشک و تمام داده‌ها ساختگی‌اند؛ پس از آزمون، fixture برنامه را برای سایر آزمون‌ها بازمی‌گرداند. کد افزونه و ZIP تغییری نکردند؛ سه بدهی و migration باز، **۷۵٪ ثابت**. CI در انتظار است.

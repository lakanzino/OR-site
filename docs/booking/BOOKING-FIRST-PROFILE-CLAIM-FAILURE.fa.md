# مرحلهٔ ۵.۳۱ — مرگ worker و writer غیرهماهنگ در نمونهٔ claim

## آزمایش

نمونهٔ صرفاً CI مرحلهٔ ۵.۳۰ بدون تغییر محصول به‌کار می‌رود. یک worker واقعی PHP پس از ثبت claim و پیش از درج پست در barrier می‌ایستد؛ نشست دوم وجود claim و نبود پست را می‌خواند. آزمون فقط پس از تطبیق process group و PID با سرور دورریختنی همان worker را SIGKILL می‌کند. پس از مرگ، claim، پست‌ها و پاسخ retry هماهنگ دوباره بررسی می‌شوند. سپس عمداً همان مسیر اصلی بدون hook claim فراخوانی می‌شود تا نشان دهد writer غیرهماهنگ از جدول آزمایشی بی‌خبر است. هیچ داده یا سرور سایت درگیر نیست.

پژوهش [قطع اتصال و تراکنش](https://stackoverflow.com/questions/9936699/mysql-rollback-on-transaction-with-lost-disconnected-connection) و [ایراد ایجاد هم‌زمان WooCommerce](https://github.com/woocommerce/woocommerce/issues/62659) الهام آزمون جداگانهٔ شکست و پوشش writerها بودند. کد از منابع کپی نشده و این SIGKILL قطع شبکه یا قطع میانهٔ COMMIT نیست.

**نتیجهٔ آزمایش:** claim پیش از درج قابل مشاهده بود. پس از SIGKILL worker، claim باقی ماند و هیچ پستی ساخته نشده بود؛ retry هماهنگ ۴۰۹ گرفت و پستی نساخت. سپس writer اصلی بدون مشارکت در نمونه توانست با وجود claim پست و snapshot بسازد؛ بنابراین نمونه به تنهایی محافظ محصول نیست. [CI رزرو 42419441515](https://github.com/lakanzino/OR-site/actions/runs/42419441515) و [MariaDB 42419441465](https://github.com/lakanzino/OR-site/actions/runs/42419441465) موفق شدند.

دو اجرای پیشین رزرو [42418864566](https://github.com/lakanzino/OR-site/actions/runs/42418864566) و [42419148955](https://github.com/lakanzino/OR-site/actions/runs/42419148955) به ترتیب به علت نرسیدن یکی از دو درخواست به barrier نمونهٔ ۵.۳۰ و ۵.۲۸ شکست خوردند. علت مشخص نشده؛ برای تشخیص پاسخ‌های fixture در خطا ثبت می‌شوند، اما اجرای موفق بعدی رفع علت را ثابت نمی‌کند. این ناپایداری CI باید پیگیری شود.

نمونه عمداً claim بی‌انقضا را پاک نمی‌کند. مرگ worker یا موفقیت تست به‌تنهایی راه امن رفع claim، توقف همهٔ writerها، بررسی دادهٔ موجود، اتمی‌بودن فرم یا انتقال به محصول را ثابت نمی‌کند؛ نتیجهٔ نامعلوم، migration، cache و writer غیرهماهنگ بازند؛ **۷۵٪ ثابت و NO-GO**.

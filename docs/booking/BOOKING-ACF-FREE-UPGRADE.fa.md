# مرحلهٔ ۵.۳۶ — ارتقای آزمایشی با ACF رایگان واقعی

## سؤال و آزمایش

تصویر کاربر افزونهٔ ACF رایگان ۶٫۸٫۱۱ فعال و همیار پزشک‌ها ۰٫۳٫۲۲ را نشان می‌دهد؛ این تصویر تضمین پیکربندی، داده‌ها یا سلامت سایت نیست. در CI دورریختنی، کد واقعی ACF رایگان از [مخزن رسمی GitHub](https://github.com/AdvancedCustomFields/acf/tree/dae1d08f9da899a3386db5c479c91bb70ea96148) در commit `dae1d08f9da899a3386db5c479c91bb70ea96148` و SHA-256 آرشیو `aa22ad6c8c680146707c3869b8cc7a6c06d689523dfb2f640b7f40751b618352` دریافت می‌شود. ابتدا بستهٔ ثبت‌شدهٔ ۰٫۳٫۲۲ با ACF واقعی فعال، کاربر و پروفایل ساختگی و ساعات کار ساخته می‌شود. سپس سرور خاموش و فایل افزونه با نسخهٔ فعلی عوض می‌شود؛ روی همان MariaDB، بدون بازنشانی داده و با همان نشست، خواندن و ویرایش پروفایل و نمایش ساعت کنترل می‌شود.

این فقط جایگزینی فایل و smoke test است؛ WordPress upgrader، ACF Pro، دادهٔ زنده، cache واقعی، OAuth، مهاجرت برنامهٔ رزرو و پیکربندی کامل سایت را شبیه‌سازی نمی‌کند. رزرو برای اجتناب از فرض migration خاموش است. رویکرد [عدم وجود repeater در نسخهٔ رایگان](https://stackoverflow.com/questions/40108139/i-am-using-custom-fields-plugin-but-i-do-not-see-repeater-field-in-that) و [تفاوت مقادیر و field key](https://support.advancedcustomfields.com/forums/topic/update_field-inconsistency/) فقط برای طراحی آزمون استفاده شده‌اند، نه کپی کد.

**نتیجه:** [CI رزرو 42429319979](https://github.com/lakanzino/OR-site/actions/runs/42429319979) در جایگزینی فایل، حفظ و ویرایش نام و ساعات نمونه با ACF واقعی رایگان موفق بود؛ اجرای قبلی [42429062379](https://github.com/lakanzino/OR-site/actions/runs/42429062379) به‌علت فرض نادرست PID در آزمون SIGKILL شکست خورد و با انتخاب worker اختصاصی اصلاح شد. این نتیجه مجوز نصب روی سایت نیست، بستهٔ آزمایشی نصب نشده، پروفایل تکراری سایر writerها، بازیابی claim و اتمی‌بودن فرم باز، **۷۵٪ ثابت و NO-GO**.

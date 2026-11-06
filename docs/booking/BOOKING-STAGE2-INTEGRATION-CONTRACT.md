# قرارداد ادغام مرحله ۲ رزرو — پیش‌نویس، Gate 2 باز

این سند نقشهٔ کار است، نه مجوز نصب یا ادعای آماده‌بودن backend. کد اصلی افزونه و سایت زنده با این آزمایش‌ها تغییر نکرده‌اند. نسخهٔ قابل نصب و تأییدیهٔ مرحله ۲ هنوز وجود ندارد.

## تصمیم معماری

- جدول InnoDB با پیشوند پویای وردپرس `schedules(doctor_id, revision, rows_json, rows_hash)` تنها منبع حقیقت برنامه برای تولید اسلات و ساخت رزرو است. دادهٔ نامعتبر، hash ناسازگار، نبود snapshot و revision نامشخص باید fail-closed باشند؛ هیچ fallback به ACF، `get_field`، postmeta یا cache در مسیر محاسبه و رزرو مجاز نیست.
- `working_hours` در ACF/meta فقط نمایشی و قابل کهنه‌شدن است؛ خرابی یا کندی به‌روزرسانی نمایش نباید commit برنامه را باطل کند. مهاجرت اولیهٔ ACF به snapshot فقط یک فرآیند کنترل‌شده، قابل audit و قبل از فعال‌شدن رزرو است، نه fallback در زمان رزرو.
- همهٔ مسیرهای مؤثر بر ظرفیت (انتشار برنامه و تنظیمات مدت/بافر، ساخت، لغو، بازگشایی، تغییر وضعیت مرتبط و migration فعال) باید روی همان اتصال و تراکنش، ابتدا ردیف قفل همان پزشک را `SELECT ... FOR UPDATE` کنند. APIهای موجود writer مستقل، REST، ACF admin و import باید بررسی و مسدود یا به همین سرویس هدایت شوند؛ یک مسیر جاافتاده معماری را بی‌اعتبار می‌کند.
- زمان اسلات با `wp_timezone()` و ذخیرهٔ لحظه‌های رزرو در UTC؛ DST و روز/زمان نامعتبر با سیاست مشخص fail-closed. مدت پیش‌فرض ۳۰ دقیقه و بافر ثابت ۵ یا ۱۰ دقیقه است و توسعهٔ آینده باید تنظیمات نسخه‌دار را نگه دارد. `pending` و `confirmed` ظرفیت می‌گیرند؛ گذار وضعیت، لغو و رزرو مجدد باید زیر همان قفل و با قواعد مستند انجام شوند.

## چک‌لیست پورت `HJ_Doctors_Dashboard::ajax_save`

1. nonce، ورود، نقش/مالکیت دقیق و parse فعلی را حفظ کن؛ فرم و hook/shortcode تغییر نکنند. اعتبارسنجی سخت ساعات/روز، محدوده، ترتیب و overlap، و مقادیر خالی را صریح و مستقل از clamp فعلی طراحی و تست کن. همهٔ فیلدها و writerهای جداگانهٔ داشبورد را بازبینی کن.
2. روی اتصال اختصاصی امن و بدون reconnect/replay خودکار BEGIN کن؛ ردیف قفل پزشک را قبل از خواندن/نوشتن ظرفیت قفل کن؛ revision مورد انتظار را بررسی کن، JSON نرمال‌شده و hash را اتمیک بنویس و revision را افزایش بده. timeout/deadlock را فقط با retry محدود از اول تراکنش روی اتصال تازه و بدون عوارض جانبی بیرونی مدیریت کن.
3. خطای پیش از commit باید rollback شود؛ از دست رفتن اتصال یا پاسخ COMMIT نتیجهٔ نامعلوم است و نباید صرفاً «شکست قطعی» معرفی یا کورکورانه بازاجرا شود. شناسهٔ idempotency پایدار و reconcile روی اتصال تازه، برای ذخیرهٔ برنامه و رزرو، باید طراحی و آزموده شود.
4. بعد از commit، نمایش را best-effort و خارج از تراکنش اصلی به‌روز کن. دو writer هم‌زمان می‌توانند کپی قدیمی را بعد از جدید بنویسند؛ برای نمایش همگرا، outbox بادوام با revision و worker سریالی per-doctor، تأیید revision و repair/retry لازم است. شکست نمایش را ثبت/قابل ترمیم کن بدون ثبت اطلاعات حساس. تا پیاده‌سازی آن، نمایش ممکن است کهنه بماند. `update_field` واقعی، کلیدهای field، reference meta، hookها و object cache باید در محیط مناسب بررسی شوند؛ تست fallback کنونی اثبات سازگاری ACF Pro نیست.
5. `ajax_save` کنونی قبل از ذخیرهٔ ساعت، فیلدهای دیگر پروفایل را نیز جداگانه تغییر می‌دهد؛ 503 ساعت به معنی rollback کل فرم نیست. رفتار partial success و پیام کاربر باید قبل از ادغام به‌طور صریح طراحی و تست شود.
6. مراقب `wpdb::query` وردپرس stock باش: بعد از قطع اتصال ممکن است reconnect و SQL را بیرون تراکنش تکرار کند. بررسی connection ID *بعد از write* کافی نیست. یک اتصال اختصاصی با تضمین آزموده‌شدهٔ عدم reconnect در طول تراکنش لازم است. subclass آزمایشی موجود drop-in تأییدشده برای production نیست و با نسخه‌ها، drop-inهای DB، ابزارهای cache و failure modeهای PHP/MariaDB باید مستقلاً ارزیابی شود.

## نقشهٔ توابع برای ادغام نهایی

| نقطهٔ موجود / نمونه | کار لازم |
|---|---|
| `HJ_Doctors_Dashboard::ajax_save` در `plugins/hamyar-doctors/includes/dashboard.php` | به‌جای `self::set(..., 'working_hours', ...)` پس از guard و parse، سرویس انتشار snapshot را صدا بزند؛ سایر تغییرات فرم و outcome نامعلوم جداگانه مدیریت شوند. |
| `HJ_Doctors_Dashboard::val` و نمایش `working_hours` | همچنان دادهٔ نمایشی؛ هرگز مرجع رزرو نباشد. نمایش stale و repair مشخص شود. |
| writer مدیریت `plugins/hamyar-doctors/includes/review-admin.php` و ACF admin/import | شناسایی همهٔ writerها؛ همهٔ تغییرات مؤثر بر رزرو به سرویس snapshot و قفل مشترک منتقل یا در حالت رزرو غیرفعال شوند. |
| `hj_spike_normalize_schedule`، `hj_schedule_slots` در ابزارهای spike | مبنای استخراج validator/slot service؛ fail-closed و timezone/DST، buffer و عملکرد با دادهٔ واقعی دوباره تست شوند. |
| `hj_spike_snapshot_rows_locked` و `hj_spike_snapshot_create` | الگوی اولیه با mysqli؛ معادل دورریختنی `hj_ci_booking_operation` اکنون با `wpdb` و همان snapshot/قفل `ajax_save` آزموده می‌شود. هیچ‌کدام API production افزونه نیستند؛ احراز هویت، idempotency، status و جزئیات خصوصی باید در ادغام نهایی بازآزمایی شوند. |
| `hj_secured_create` | نسخهٔ فعلی هنوز مسیر متا و fail-closed هنگام ACF دارد؛ باید به reader snapshot متصل و وابستگی رزرو به متا حذف شود، سپس همهٔ تست‌ها تکرار شوند. |
| `hj_interval_transaction`، `hj_interval_create`، `hj_interval_cancel` | نمونهٔ mysqli، نه implementation وردپرس؛ قفل، overlap، retry محدود، عدم replay اتصال، idempotency و انتقال وضعیت به wrapper قابل اتکای production منتقل و با `wpdb` آزموده شوند. |
| `hj_spike_migrate` / migration v1–v3 | تنها نصاب fixture با prefix ثابت است؛ migration versioned production با prefix پویا، dbDelta و کنترل نصب/rollback/رقابت و seed امن لازم است. |
| `hj_ci_port_save_hours` در `tools/hj-booking-spike/wpdb-ajax-fixture-mu.php` | فقط آزمایش کپی disposable؛ جایگزین production نیست: expected revision، retry، idempotency، outbox و validator کامل ندارد و کپی ACF واقعی انجام نمی‌دهد. |

## شواهد و کار باقی‌مانده

- [CI 42134620828](https://github.com/lakanzino/OR-site/actions/runs/42134620828): ضدنمونهٔ reconnect خودکار `wpdb` stock؛ سبز بودن یعنی خطر بازتولید شده است، نه رفع خطر.
- [CI 42135352723](https://github.com/lakanzino/OR-site/actions/runs/42135352723): کپی disposable افزونه با یک خط جایگزین‌شده در `ajax_save`، AJAX واقعی وردپرس، کاربرهای مصنوعی و `wpdb` subclass آزمایشی؛ nonce/مالکیت، snapshot، شبیه‌سازی شکست کپی با صرف‌نظرکردن از مرحلهٔ نمایش، KILL پیش از commit و تلاش write پس از KILL، و هشت ذخیرهٔ هم‌زمان برنامه را آزموده است. این شبیه‌سازی اجرای `update_field` ناموفق یا خطای واقعی meta را ثابت نمی‌کند؛ نه ACF Pro واقعی است، نه سرویس کامل رزرو و نه مجموعهٔ کامل race/KILL روی همان پورت.
- [CI 42137379366](https://github.com/lakanzino/OR-site/actions/runs/42137379366)، commit `7626708`: وردپرس واقعی، AJAX واقعی `ajax_save` کپی‌شده، و endpoint رزرو صرفاً آزمایشی با `wpdb` subclass مشترک؛ slot/create از همان جدول و ردیف snapshot می‌خوانند، حتی وقتی متای نمایش عمداً متفاوت است. هشت بیمار و بستن برنامه هم‌زمان، هر دو ترتیب قفل به‌صورت کنترل‌شده، KILL پیش از commit و retry، قطع DB پس از commit و بستن socket کلاینت پیش از پاسخ، retryهای هم‌زمان با کلید یکسان، conflict و شمارش اتمیک رزرو/جزئیات بررسی شدند. timeout واقعی قفل و retry محدود، و نمونهٔ تأیید/لغو/رزرو مجدد با قفل مشترک هم در همین مسیر بررسی شدند. پس از بستن برنامه، درخواست جدید رد می‌شود؛ رزرو معتبری که پیش از بستن commit شده حذف نمی‌شود و replay همان کلید نتیجهٔ قبلی را برمی‌گرداند.
- این endpoint تولیدی یا پورت `hj_secured_create` نیست؛ سازگاری subclass با drop-inها، deadlock و همهٔ آزمون‌های نقش/انتقال وضعیت روی همین مسیر یکپارچه هنوز اثبات نشده‌اند. CI شامل آزمون‌های جداگانهٔ قبلی با mysqli نیز هست، ولی آنها را نباید آزمون همین مسیر `wpdb` نامید. یک اجرای پیشین [42136441435](https://github.com/lakanzino/OR-site/actions/runs/42136441435) به علت انتظار برای رسیدن هر هشت worker به barrier شکست؛ آزمون بعدی به شروع هم‌زمان هشت درخواست و رسیدن حداقل یکی به قفل، به‌علاوهٔ کنترل نتیجهٔ هر هشت درخواست، اصلاح شد.
- مجوز بیمار/جراح/منشی/مدیر، nonce و CSRF، validation تلفن و checksum کد ملی، عدم افشای نقش یا دادهٔ سلامت، migration و isolation همهٔ writerها باید قبل از release جداگانه تأیید شوند. هیچ اطلاعات سلامت در log، URL یا cache عمومی قرار نگیرد.
- Stage 3 و تغییر runtime/live تا بازبینی و مجوز کاربر شروع نمی‌شوند؛ وضعیت تأییدیهٔ مرحلهٔ آزمایش را نباید با آمادگی نصب یا release اشتباه گرفت.

# بازبینی ایستای PHP افزونهٔ همیار پزشک‌ها

این ابزار یک بازبینی به سبک **WPCS** روی درخت واقعی PHP انجام می‌دهد. PHPCS رسمی به افزونه‌های `simplexml`، `mbstring` و `dom` نیاز دارد و در محیط‌هایی که PHP کامل ندارند نصب‌شدنی نیست؛ این اسکریپت به‌جای آن از `token_get_all( $src, TOKEN_PARSE )` استفاده می‌کند، یعنی فایل را واقعاً پارس می‌کند و اگر نحو خراب باشد همان‌جا می‌شکند.

هیچ درخواستی به سایت، پایگاه داده یا شبکه نمی‌فرستد و هیچ فایلی را تغییر نمی‌دهد.

## اجرا

از ریشهٔ مخزن:

```sh
php tools/hamyar-php-review/analyze.php plugins/hamyar-doctors
```

کد خروج ۱ است اگر حتی یک `ERROR` وجود داشته باشد، وگرنه ۰؛ بنابراین در CI قابل استفاده است.

## آزمون قلاب‌های سیدر (PHP واقعی و شبیه‌ساز محدود)

این آزمون فایل‌های تغییرکرده را با PHP 8.3 واقعی در WASM پارس می‌کند، سپس دو فایل سیدر را با شبیه‌ساز کوچک قلاب‌های وردپرس بارگذاری و نبود سه اتصال خودکار و باقی‌ماندن اتصال‌های ابزار دستی را بررسی می‌کند. وردپرس یا پایگاه داده واقعی را اجرا نمی‌کند و عملکرد عملیات دستی، امنیت نصب یا افزونه‌های دیگر را اثبات نمی‌کند. فقط در محیط جداگانه اجرا شود:

```sh
npm install --prefix /tmp/or-site-php --no-audit --no-fund @php-wasm/node@3.1.54 @php-wasm/universal@3.1.54
HJ_PHP_WASM_ROOT=/tmp/or-site-php node tools/hamyar-php-review/run-seed-hook-test.mjs
```

## آزمون محافظ wrapperهای خودکار (فقط شبیه‌ساز)

پس از نصب وابستگی‌های بخش قبل، `HJ_PHP_WASM_ROOT=/tmp/or-site-php node tools/hamyar-php-review/run-seed-wrapper-guard-test.mjs` را اجرا کنید. چهار درخواست مجزای PHP حالت تعریف‌نشده، `false` بولی، `'true'` رشته و `true` بولی را با توابع هسته و گزینه‌های شبیه‌سازی‌شده می‌آزمایند؛ شرط‌های قبلی دسترسی و marker نیز کنترل می‌شوند. این آزمون اتصال به وردپرس یا دیتابیس و مسیر واقعی فرم و redirect را آزمون نمی‌کند. گارد فقط روی سه wrapper است؛ ثابت به‌تنهایی هیچ hookی ثبت نمی‌کند.

## قواعدی که بررسی می‌شود

| قاعده | چه چیزی را می‌گیرد |
| --- | --- |
| `dangerous` | `eval`، `extract`، `create_function`، `mysql_query`، `each` |
| `escaping` | متغیری که با `echo`/`print` بدون `esc_*`، `wp_kses`، `absint`، `intval`، `(int)` یا نام مستعار `$e = 'esc_html'` چاپ شود |
| `input` | `$_GET/$_POST/$_REQUEST/$_COOKIE/$_FILES` بدون پاک‌سازی؛ بررسی صرفِ `isset`/`empty` و نوشتن در `$_COOKIE` استثناست |
| `csrf` | فایلی که **وضعیت را تغییر می‌دهد** و ورودی می‌خواند ولی هیچ بررسی nonce ندارد |
| `i18n` | `__`، `_e`، `esc_html__` و مانند آن‌ها بدون دامنهٔ `hamyar-doctors` |
| `naming` | تابع سراسری بدون پیشوند پروژه (`hj_`، `wn_`، `orsite_`) |
| `sql` | `$wpdb->query/get_results/get_var/...` بدون `prepare` و بدون `SHOW TABLES/COLUMNS` |
| `paths` | مسیر ثابت `wp-content/plugins` داخل رشته |
| `suppression` | `@` پیش از فراخوانی تابع |
| `logging` | `error_log()` مستقیم |

همچنین توابع `hj_*` که فراخوانی می‌شوند ولی در هیچ فایلی تعریف نشده‌اند فهرست می‌شوند تا فراموشی `function_exists` دیده شود.

## استثنا گذاشتن

مثل خود PHPCS، هر یافته با `// phpcs:ignore <Rule>` در همان خط نادیده گرفته می‌شود:

```php
echo $this->render_single_surgeon_ribbon( $doc_post, $is_blurred ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
```

استثنا فقط وقتی درست است که در بازبینی دستی دلیلش تأیید شده باشد (مثلاً خروجی تابعی که خودش همه‌چیز را escape می‌کند).

## نتیجهٔ اجرای فعلی روی افزونه

۲۳ فایل، `errors=0 warnings=0 notices=0`. جزئیات و دلیل هر استثنا در `docs/reports/hamyar-doctors-0.3.2-review.fa.md` آمده است.

# چک‌لیست انتشار نسخه (برای هر بامپ)

این فایل از ۰٫۲۰٫۱ اضافه شد، چون کشف شد نسخه‌های ۰٫۱۶٫۰ تا ۰٫۲۰٫۰ فقط **push** شده بودند و **GitHub Release** نداشتند؛ در نتیجه «بروزرسانی گیت‌هاب» در پیشخوان آن‌ها را نمی‌دید و آخرین نسخهٔ قابل نصب ۰٫۱۵٫۳ بود.

## قرارداد افزونهٔ به‌روزرسان

- تگ انتشار: `child-vX.Y.Z`
- فایل‌های داخل همان تگ در پوشهٔ `releases/`: `lax-theme-child.zip`، `lax-theme.zip` (والد)، `orsite-release.json`
- افزونه ابتدا asset واقعی انتشار را می‌خواهد؛ اگر نبود، همان فایل را از **نسخهٔ داخل مخزن در همان تگ** می‌خواند (`github.com/<repo>/raw/<tag>/releases/...`)، بعد `raw.githubusercontent.com`، بعد contents API و در آخر zipball. پس کمینهٔ لازم این است که zip در همان commit تگ‌شده **کامیت شده** باشد.
- انتخاب نسخه با `version_compare()` روی همهٔ Releaseها انجام می‌شود؛ بالاترین برنده است.

## گام‌ها

1. **بامپ نسخه در چهار جا:** `themes/lax-theme-child/style.css` (`Version:`)، `themes/lax-theme-child/functions.php` (`LAX_THEME_CHILD_VERSION`)، `themes/lax-theme-child/readme.txt` (`Stable tag`) و `releases/orsite-release.json` (`child.version` + یک سطر changelog).
2. **چنج‌لاگ‌ها:** `themes/lax-theme-child/CHANGELOG.md`، `readme.txt`، `docs/PROJECT-CHANGELOG.md`، و اگر تغییر معماری بود `docs/DECISIONS.md`.
3. **بازسازی بسته‌ها:** `cd themes && zip -rq ../releases/lax-theme-child.zip lax-theme-child` + بستهٔ اسناد (`orsite-docs-X.Y.Z.zip`) + یادداشت `RELEASE-NOTE-X.Y.Z.fa.md`.
4. **بررسی برابری:** MD5 فایل‌های کلیدی داخل zip با درخت کاری (اسکریپت پایتون در گزارش‌ها).
5. **کامیت و پوش** روی همان شاخهٔ کار.
6. **تگ و انتشار:**
   ```bash
   git tag -a child-vX.Y.Z -m "…" && git push origin child-vX.Y.Z
   gh release create child-vX.Y.Z --title "…" --notes-file RELEASE-NOTE-X.Y.Z.fa.md
   ```
   (آپلود asset اختیاری است؛ اگر شبکه اجازه داد: `gh release upload child-vX.Y.Z releases/lax-theme-child.zip releases/lax-theme.zip releases/orsite-release.json`.)
7. **آرشیو Drive:** پوشهٔ `Hamyar Jarahi — Release child-vX.Y.Z` با قالب، افزونه (اگر بود)، اسناد و یادداشت.
8. **PR:** بدنهٔ PR با بخش نسخهٔ تازه و آرشیو به‌روز شود.

## بررسی نهایی

- [ ] `python3 tools/php-check.py` روی فایل‌های تغییر‌یافته
- [ ] `python3 tools/content-lint.py` (باید ۴۴ بسته · ۰ خطا)
- [ ] توازن براکت `child.css`
- [ ] `git show child-vX.Y.Z:releases/orsite-release.json` نسخهٔ درست را می‌دهد
- [ ] در پیشخوان، «بروزرسانی گیت‌هاب» نسخهٔ تازه را نشان می‌دهد و dry-run فقط فایل‌های تغییر‌یافته را می‌آورد

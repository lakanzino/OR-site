<div align="center">

<img src="branding/app-logo.svg" width="120" alt="Lax-1819 logo"/>

# Lax-1819 — دفتر روزانه‌ی جلالی

**Offline-first Persian daily notebook · Kotlin + Jetpack Compose + Material 3**

`minSdk 21` · `targetSdk 35` · `v1.0.0` · **MIT License** · Designed & developed by **mohamadreza.lak**

</div>

---

## فارسی

### معرفی
**Lax-1819** یک دفتر روزانه‌ی بسیار ساده، سریع و کاملاً آفلاین با تقویم **جلالی** است. برای هر روز می‌توانید یک یا چند برگه‌ی مستقل بسازید و با **ستون‌های کاملاً قابل تنظیم** هر چیزی را ثبت کنید؛ مناسب مغازه‌دار، آرایشگاه و کلینیک، تعمیرکار، فروش اقساطی، کالاهای دارای انقضا و هزینه‌های شخصی.

### امکانات
- تقویم جلالی بومی (بدون وابستگی خارجی)، راست‌به‌چپ کامل، ارقام فارسی (۰۱۲۳۴۵۶۷۸۹) و فونت وزیرمتن
- چند برگه برای هر روز، کپی از روز قبل، قالب‌ها، جستجو، کشیدن برای حذف/تکثیر (با بازگردانی)
- ستون‌های پویا: نام کامل، محصول، خدمات، تاریخ تولید/انقضا (جلالی)، ساعت مراجعه/پایان/ویزیت، مقدار با واحد، هزینه، بیعانه، اقساط (تعداد + مبلغ + مانده خودکار)، یادداشت، وضعیت رنگی و ستون‌های سفارشی
- **مدیریت ستون**: نمایش/پنهان، **قفل**، **تغییر نام**، **کشیدن و رها کردن** برای ترتیب، ستون سفارشی، حالت‌های سریع (مغازه / خدمات / شخصی / اقساطی)
- جداکننده‌ی هزارگان زنده هنگام تایپ (۱٬۲۳۴٬۵۶۷)، سوییچ **ریال ↔ تومان** (با تبدیل اختیاری ×۱۰ / ÷۱۰)
- جمع هوشمند پایین صفحه (تعداد، هزینه، بیعانه، مانده اقساط، مقدار)
- پشتیبان‌گیری JSON/ZIP، بازیابی، خروجی CSV (اکسل)، اشتراک خلاصه‌ی روز
- قفل رمز + اثر انگشت، حذف کامل داده با تأیید قوی
- راهنمای کامل داخل برنامه، معرفی ۴ صفحه‌ای، داده‌ی نمونه‌ی قابل حذف
- ظاهر شیشه‌ای (Glassmorphism) و عمق سه‌بعدی، پالت گرم کهربایی، تم روشن و تاریک

### ساخت پروژه
۱. Android Studio (Ladybug یا جدیدتر) و JDK 17.
۲. پوشه‌ی پروژه را باز کنید و Gradle Sync را اجرا کنید.
۳. اجرا روی گوشی/شبیه‌ساز (Android 5.0 به بالا) یا از ترمینال:
```bash
./gradlew assembleDebug        # APK دیباگ
./gradlew assembleRelease      # APK ریلیز (R8 + shrinkResources)
./gradlew bundleRelease        # AAB برای Google Play / Bazaar
```

### جایگزینی لوگو (app-logo)
- لوگوی پیش‌فرض یک **وکتور** است: `app/src/main/res/drawable/app_logo.xml` (برای آیکن اپ) و `app_logo_foreground.xml` (پیش‌زمینه‌ی آیکن Adaptive). نسخه‌ی SVG در `branding/app-logo.svg` است.
- نام منبع در اندروید نمی‌تواند خط‌تیره داشته باشد؛ بنابراین فایل شما باید `app_logo.png` یا `app_logo.webp` نام داشته باشد (همان `app-logo` با زیرخط).
- برای جایگزینی: فایل را در `app/src/main/res/drawable-nodpi/app_logo.png` بگذارید و `drawable/app_logo.xml` را حذف کنید. برای آیکن Adaptive یک PNG شفاف ۱۰۸×۱۰۸dp (۴۳۲×۴۳۲ پیکسل) را با نام `app_logo_foreground.png` در همان پوشه بگذارید و `app_logo_foreground.xml` را حذف کنید. یا در Android Studio: *New → Image Asset* با همان فایل.
- رنگ پس‌زمینه‌ی آیکن در `res/values/colors.xml` (`icon_bg`).

### تنظیمات تماس
در `app/src/main/java/com/lax1819/app/AppInfo.kt` ایمیل، تلگرام و آدرس GitHub را با اطلاعات واقعی خود جایگزین کنید.

### امضا و ساخت APK ریلیز
۱. **Build → Generate Signed Bundle / APK…** → *APK* (یا *Android App Bundle*) → Next.
۲. *Create new…* برای ساخت Keystore (مسیر، رمز، alias، اعتبار ≥ ۲۵ سال) → OK → Next.
۳. نوع ساخت `release` را انتخاب کنید، هر دو تیک V1 و V2 را بزنید → Finish.
۴. خروجی: `app/release/app-release.apk`.
۵. (اختیاری، برای ساخت از ترمینال) فایل `keystore.properties` را در ریشه‌ی پروژه بسازید (در `.gitignore` هست و هرگز commit نشود):
```properties
storeFile=my-release-key.jks
storePassword=******
keyAlias=lax1819
keyPassword=******
```
سپس `./gradlew assembleRelease` به‌صورت خودکار امضا می‌کند. **Keystore را حتماً پشتیبان بگیرید**؛ بدون آن نمی‌توانید به‌روزرسانی منتشر کنید.

### ارسال به GitHub (مخزن Lax-1819)
```bash
cd Lax-1819
git init
git add .
git commit -m "Lax-1819 v1.0.0 – initial release (MIT)"
git branch -M main
git remote add origin https://github.com/<USERNAME>/Lax-1819.git
git push -u origin main
git tag v1.0.0 && git push origin v1.0.0
```
فایل `LICENSE` (MIT) در ریشه هست و به‌صورت خودکار در GitHub نمایش داده می‌شود. APK امضاشده را از *Releases → Draft a new release* پیوست کنید.

---

## English

**Lax-1819** is a minimal, fully offline, highly customizable daily notebook built around the **Jalali (Persian) calendar**. Every day can hold many independent sheets; every column is optional, renamable, hideable, lockable, reorderable — and you can add your own.

### Tech stack
| Area | Choice |
|---|---|
| UI | Jetpack Compose + Material 3, custom warm glass/3D design system (`ui/components/Glass.kt`) |
| Architecture | MVVM + Repository + Flow/StateFlow, Hilt (KSP) |
| Storage | Room (indexed, Flow queries) + DataStore Preferences |
| Calendar | **Own pure-Kotlin Jalali core** (`util/Jalali.kt`), a port of the MIT-licensed *jalaali-js* algorithm – no dependency, API 21+, years −61…3177 |
| Number formatting | `VisualTransformation` (`ThousandsTransformation`) with exact cursor mapping |
| Backup | kotlinx.serialization JSON (+ ZIP with raw DB) through the Storage Access Framework |
| Drag & drop | `sh.calvin.reorderable` |
| Lock | PIN (salted SHA-256) + AndroidX Biometric |
| Font | Vazirmatn (bundled, SIL OFL) |

### Performance
- R8 full mode: `isMinifyEnabled = true`, `isShrinkResources = true`
- Baseline profile (`app/src/main/baseline-prof.txt`) + `profileinstaller`
- `LazyColumn` with stable `key` + `contentType`, `animateItem`, `remember`/`derivedStateOf`-style memoization, flows moved to `Dispatchers.Default`
- Room queries are indexed (`dateKey`, `sheetId`), all DB work is off the main thread
- Real blur (`RenderEffect`) on API 31+, gradient/alpha/shadow fallback below
- No network permission, no analytics, no background services

### Project layout
```
app/src/main/java/com/lax1819/app/
├─ data/      Room entities, DAOs, Repository, BackupManager, SettingsStore
├─ di/        Hilt module
├─ util/      Jalali core, Persian formatting, thousands VisualTransformation
├─ ui/
│  ├─ theme/       Color.kt, Type.kt, Theme.kt (warm palette, RTL)
│  ├─ components/  Glass cards/bars/FAB, calendar, fields, dialogs
│  ├─ home/ sheet/ columns/ settings/ guide/ about/ onboarding/ lock/ nav/
│  └─ viewmodel/
└─ MainActivity.kt, LaxApp.kt, AppInfo.kt
```

### Build
```bash
./gradlew assembleDebug      # debug APK
./gradlew assembleRelease    # signed release APK (see keystore.properties above)
./gradlew bundleRelease      # AAB
```
Requirements: Android Studio Ladybug+, JDK 17, Android SDK 35.

### Generating a measured Baseline Profile (optional)
Add a `:baselineprofile` Macrobenchmark module (Android Studio → *New Module → Baseline Profile Generator*), run it on a physical device and replace `baseline-prof.txt` with the generated output.

### Replacing the logo (`app-logo`)
Android resource names cannot contain `-`, so use `app_logo.png` / `app_logo.webp` in `res/drawable-nodpi/` (delete the vector `app_logo.xml`). For adaptive icons use *New → Image Asset* or replace `app_logo_foreground`. The default logo is a vector calendar + checklist in amber/orange/gold.

### License
**MIT** — see [`LICENSE`](LICENSE). Copyright (c) 2025 **mohamadreza.lak**. The license is also shown inside the app (About + Settings).

### Credits
Designed & developed by **mohamadreza.lak**. Vazirmatn font © Saber Rastikerdar (OFL). Jalali algorithm from *jalaali-js* (MIT). Reorderable © Calvin Liang (Apache-2.0).

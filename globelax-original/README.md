# 🌍 Globe.Lax — کشورها و پایتخت‌ها

**Version 1.0.0 · MIT License · Copyright © 2025 mohamadreza.lak**

> «این اپلیکیشن جهت آموزش به نوجوانان و جوانان ایران عزیز ساخته شده است.»
> *This app was built to teach teenagers and young people of beloved Iran.*

**طراحی و توسعه: محمدرضا لک (mohamadreza.lak)**

---

## فارسی

### امکانات
- 🌐 کرهٔ زمین سه‌بعدی و تعاملی (چرخش با انگشت، اینرسی، لمس کشورها) — کاملاً آفلاین
- 🗂️ ۱۹۵ کشور (۱۹۳ عضو سازمان ملل + واتیکان + فلسطین) با پرچم، پایتخت، واحد پول (نام + کد)، جمعیت، مساحت، زبان‌ها، قاره و نکتهٔ آموزشی
- 🔎 جستجو با نام فارسی/انگلیسی، پایتخت، ارز، کد ارز، زبان — فیلتر قاره‌ها
- 🃏 فلش‌کارت با چرخش سه‌بعدی (چهار حالت: کشور، پرچم، پایتخت، ارز)
- 🧠 آزمون: پایتخت، ارز، پرچم و ترکیبی؛ امتیاز، پیاپی‌ها، زمان‌سنج اختیاری، مرور اشتباهات
- ❤️ علاقه‌مندی‌ها، ✅ یادگرفته‌ها، 📈 پیشرفت به تفکیک قاره
- 🎨 پوستهٔ تیره و روشن با پالت زرد / قرمز / سرمه‌ای، فونت وزیرمتن، RTL کامل، تغییر اندازهٔ متن
- 🔒 قفل رمز اختیاری، پشتیبان‌گیری/بازیابی JSON، پاک‌کردن پیشرفت
- 🛡️ بدون اینترنت، بدون ردیابی، بدون تبلیغ، بدون مجوز

### اجرا و ساخت
```bash
npm install
npm run dev      # توسعه
npm run build    # خروجی: dist/index.html (یک فایل مستقل و آفلاین)
```

### ساخت APK اندروید (Capacitor)
خروجی وب این پروژه را می‌توان بدون تغییر کد به یک APK/AAB تبدیل کرد:
```bash
npm run build
npm i @capacitor/core @capacitor/cli @capacitor/android
npx cap init "Globe.Lax" com.globelax.app --web-dir=dist
npx cap add android
npx cap sync android
npx cap open android      # باز شدن در Android Studio
```
1. در `android/app/src/main/res/values/strings.xml` مقدار `app_name` باید دقیقاً `Globe.Lax` باشد.
2. آیکن: Android Studio → *New → Image Asset* → فایل `public/app-logo.svg` (یا `app-logo.png` خودتان) → Adaptive icon با پس‌زمینهٔ `#071544`.
3. برای «بدون مجوز»، خط `<uses-permission android:name="android.permission.INTERNET" />` را از `AndroidManifest.xml` حذف کنید (برنامه کاملاً آفلاین است).
4. `minSdkVersion 22` (حداقل Capacitor)، `targetSdk/compileSdk` آخرین نسخه.

#### امضای نسخهٔ نهایی (Signed Release APK)
```bash
keytool -genkey -v -keystore globelax.jks -keyalg RSA -keysize 2048 -validity 10000 -alias globelax
cd android
./gradlew assembleRelease            # یا bundleRelease برای Google Play (AAB)
# امضا و هم‌ترازسازی:
zipalign -v 4 app/build/outputs/apk/release/app-release-unsigned.apk globelax-aligned.apk
apksigner sign --ks ../globelax.jks --out Globe.Lax-1.0.0.apk globelax-aligned.apk
```
یا در Android Studio: *Build → Generate Signed Bundle / APK*. فایل `.jks` و رمزها را هرگز در گیت قرار ندهید.

### جایگزینی لوگو
فایل `public/app-logo.svg` (و کامپوننت `src/components/Logo.tsx`) را با لوگوی خودتان عوض کنید؛ سپس در Android Studio مجدداً Image Asset بسازید. فایل PNG هم قابل استفاده است (۵۱۲×۵۱۲).

### زیپ کردن پروژه و انتشار در GitHub (Globe.Lax)
```bash
# زیپ (بدون node_modules و dist)
zip -r Globe.Lax-1.0.0.zip . -x "node_modules/*" "dist/*" "android/build/*" "*.jks"

git init
git add .
git commit -m "Globe.Lax v1.0.0"
git branch -M main
git remote add origin https://github.com/<USERNAME>/Globe.Lax.git
git push -u origin main
```

### متن‌های آمادهٔ فروشگاه (Google Play / کافه‌بازار / مایکت)
**عنوان:** Globe.Lax – کشورها و پایتخت‌ها
**توضیح کوتاه:** یادگیری آفلاین کشورها، پایتخت‌ها، پرچم‌ها و واحد پول‌ها با کرهٔ سه‌بعدی، فلش‌کارت و آزمون.
**توضیح کامل:**
این اپلیکیشن جهت آموزش به نوجوانان و جوانان ایران عزیز ساخته شده است. ۱۹۵ کشور را با پرچم، پایتخت، واحد پول، جمعیت، مساحت و زبان بشناسید؛ کرهٔ زمین را بچرخانید، فلش‌کارت‌های سه‌بعدی را مرور کنید و با آزمون‌های پایتخت، ارز و پرچم خود را بسنجید. نام‌ها دو‌زبانه (فارسی/انگلیسی)، تم تیره و روشن، کاملاً آفلاین، بدون تبلیغ و بدون ردیابی.
**دسته‌بندی:** آموزشی · **رده سنی:** همه (آموزشی، بدون محتوای حساس) · **مجوزها:** هیچ
**حریم خصوصی:** هیچ داده‌ای جمع‌آوری یا ارسال نمی‌شود؛ پیشرفت فقط روی دستگاه ذخیره می‌شود.
**تگ‌ها:** جغرافیا، کشورها، پایتخت، پرچم، آموزش، آزمون، فلش‌کارت

### دقت اطلاعات و منابع
جمعیت و مساحت: بانک جهانی (۲۰۲۵ و عمدتاً ۲۰۲۴، با استثناها)؛ نقشه: Natural Earth؛ پرچم‌ها: hampusborgos/country-flags. شرح استثناها، مجوزها و جدول قبل و بعد: `audit/README.fa.md`.
قراردادها: قبرس در اروپا، ترکیه/گرجستان/ارمنستان/جمهوری آذربایجان/قزاقستان در آسیا، روسیه در اروپا، آمریکای مرکزی و کارائیب در آمریکای شمالی. بلغارستان از ۱ ژانویهٔ ۲۰۲۶ یورو دارد. پایتخت‌های دارای اختلاف یا چندگانه (بولیوی، آفریقای جنوبی، سری‌لانکا، نائورو، فلسطین، اسرائیل) با توضیح مشخص شده‌اند و بیانگر موضع سیاسی نیستند. هر گونه خطا را لطفاً از طریق Issue گزارش دهید.

> ⚠️ اطلاعات از منابع معتبر تهیه شده اما مسئولیت استفاده با کاربر است.

---

## English

**Globe.Lax** is a fully offline, bilingual (Persian + English) educational app about **195 countries** — flags, capitals, currencies (name + ISO code), population, area, languages, continent and a short fact — with an interactive 3D-style globe, 3D-flip flashcards and four quiz modes.

**Purpose:** built to teach teenagers and young people of beloved Iran.
**Designer & developer:** محمدرضا لک (mohamadreza.lak) · **License:** MIT · **Privacy:** 100 % offline, zero tracking, no permissions.

### Tech
React 19 · TypeScript · Vite 7 · Tailwind CSS 4 · Canvas + d3-geo (globe) · Natural Earth 110 m · country-flags SVG · Vazirmatn (bundled variable font) · localStorage (favorites, progress, settings).
The build is a **single self-contained `dist/index.html`**. Performance notes: draw-on-demand canvas, paused when off-screen, DPR capped at 2, progressive grid rendering, `content-visibility`, memoised cards, `prefers-reduced-motion` support.

### Project layout
```
src/data/      countries.ts (195 rows) · dictionaries.ts (languages, currencies, continents) · facts.ts
src/lib/       store.ts (persistent state) · router.ts · util.ts · legal.ts
src/components Globe · Flag · Logo · CountryCard · Lock · Onboarding · ui
src/pages/     Home · Detail · Flashcards · Quiz · MapPage · Saved · Settings · About
public/        app-logo.svg
```

### Android note
This repository is the web/PWA implementation of Globe.Lax; the Android APK/AAB is produced by wrapping `dist/` with Capacitor (see the Persian section for exact commands, signing, zipping and GitHub steps). A native Kotlin/Jetpack Compose port would reuse `src/data/*` as its data source.

### Data disclaimer
Data compiled from reliable sources; use is at the user's own responsibility.

Copyright © 2025 mohamadreza.lak — released under the [MIT License](LICENSE).

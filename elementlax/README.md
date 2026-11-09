# Element.Lax — جدول تناوبی آموزشی

> **«این اپلیکیشن جهت آموزش به نوجوانان و جوانان ایران عزیز ساخته شده است.»**

- **Designer & Developer / طراح و توسعه‌دهنده:** محمد رضا لک (mohamadreza.lak)
- **License / مجوز:** MIT — Copyright (c) 2025-2026 محمد رضا لک (mohamadreza.lak) (see [LICENSE](./LICENSE) and [licenses/THIRD_PARTY.md](./licenses/THIRD_PARTY.md))
- **Version:** 1.0.0 · **Launcher name:** `جدول تناوبی` · **Package id (Android wrapper):** `com.elementlax.app`
- **Android SDK:** `minSdk 23` · `compileSdk / targetSdk 35` · R8 (`minifyEnabled true`, `shrinkResources true`) · ۱۰۰٪ آفلاین و بدون مجوز (`INTERNET` حذف شده)
- **Disclaimer / سلب مسئولیت:** اطلاعات علمی با دقت از منابع معتبر تهیه شده اما مسئولیت استفاده نهایی با کاربر است.

---

## فارسی

### این پروژه چیست؟
یک جدول مندلیف تعاملی و کاملاً آفلاین با ۱۱۸ عنصر. روی هر عنصر بزنید تا کارت تمام‌صفحه با **چرخش سه‌بعدی** باز شود:
- **روی کارت (انگلیسی، سبک سایبر):** نماد بزرگ نئونی، عدد اتمی، نام، گروه/دوره و دسته.
- **پشت کارت (فارسی، فونت وزیرمتن):** نام فارسی، جرم اتمی، حالت ماده، کاشف و سال کشف، ریشه نام، کاربردها، خواص کلیدی و تاریخچه کوتاه.

### امکانات
- جدول اصلی ۱۸ ستونه + ردیف جداگانه لانتانید/اکتینید، قابل اسکرول و زوم (+ / − / اندازه خودکار)
- جستجوی هم‌زمان (نام فارسی، نام انگلیسی، نماد، عدد اتمی؛ ارقام فارسی هم پذیرفته می‌شود)
- فیلتر ۱۰ دسته با رنگ‌های پررنگ و متمایز + «همه»
- حالت مطالعه (تصادفی/ترتیبی، بر اساس دسته، فقط یاد نگرفته‌ها)
- نشان‌شده‌ها، یادگرفته‌ها و درصد پیشرفت (ذخیره محلی)
- پوسته **تیره (سایبر، پیش‌فرض)** و **روشن**
- قفل اختیاری با رمز ۴ رقمی، پاک‌کردن داده‌ها، اشتراک‌گذاری کارت
- RTL کامل، ارقام فارسی، حمایت از بزرگ‌نمایی فونت، ناوبری با دکمه Back
- ۱۰۰٪ آفلاین، بدون مجوز (Permission)، بدون جمع‌آوری داده

### نکته مهم درباره فناوری
نسخه موجود در این مخزن با **React + Vite + Tailwind + TypeScript** ساخته شده و یک اپ وب آفلاین (PWA) است که خروجی آن یک فایل `dist/index.html` است. برای انتشار در گوگل‌پلی، کافه‌بازار یا مایکت آن را با **Capacitor** در پوسته اندروید (WebView) بسته‌بندی می‌کنید؛ دستورها پایین‌تر آمده است.
> نسخه بومی Kotlin/Jetpack Compose در این مخزن وجود ندارد؛ ساختار داده‌ها (`src/data`) مستقل از رابط کاربری است و به‌راحتی به Kotlin منتقل می‌شود.

### اجرا و ساخت
```bash
npm install
npm run dev        # اجرای توسعه
npm run build      # خروجی در dist/index.html (تک‌فایل، همه چیز داخل آن)
```

### ساخت APK / AAB امضا‌شده (Capacitor)
```bash
npm install @capacitor/core @capacitor/cli @capacitor/android
npx cap init "Element.Lax" com.elementlax.app --web-dir=dist
npm run build
npx cap add android
npx cap sync android
npx cap open android      # باز شدن Android Studio
```
در Android Studio:
1. `app/src/main/res/values/strings.xml` ← `<string name="app_name">Element.Lax</string>` (نام دقیق زیر آیکون).
2. در `android/app/build.gradle`: `minSdkVersion 21`، `targetSdkVersion` آخرین نسخه، `versionName "1.0.0"`، `minifyEnabled true` و `shrinkResources true` برای R8.
3. فایل `AndroidManifest.xml` فقط باید مجوز `INTERNET` پیش‌فرض Capacitor را داشته باشد؛ برای آفلاین بودن کامل می‌توانید آن را حذف کنید.
4. آیکون: **File ▸ New ▸ Image Asset** ← `public/app-logo.png` (Foreground) و رنگ پس‌زمینه `#03070A`. لوگو را داخل ناحیه امن ۶۶٪ نگه دارید.
5. **Build ▸ Generate Signed Bundle / APK** ← ساخت Keystore (آن را امن نگه دارید) ← `release` ← APK برای کافه‌بازار/مایکت، AAB برای گوگل‌پلی.
6. خروجی: `android/app/release/app-release.apk`.

### زیپ کردن پروژه
```bash
zip -r element-lax-1.0.0.zip . -x "node_modules/*" "dist/*" "android/build/*" "android/app/build/*"
```

### جایگزینی لوگو (app-logo)
فایل‌های `public/app-logo.svg`، `public/app-logo.png` (۵۱۲×۵۱۲) و `public/icon-192.png` را عوض کنید و همان SVG را در `src/assets/app-logo.svg` کپی کنید (برای صفحه آغازین و هدر). سپس `npm run build`.

### متن معرفی فروشگاه
**توضیح کوتاه (۸۰ کاراکتر):** جدول مندلیف تعاملی با فلش‌کارت سه‌بعدی؛ فارسی، آفلاین، آموزشی.

**توضیح کامل:**
> Element.Lax یک اپلیکیشن آموزشی برای یادگیری جدول تناوبی است. این اپلیکیشن جهت آموزش به نوجوانان و جوانان ایران عزیز ساخته شده است.
>
> • جدول کامل ۱۱۸ عنصر با رنگ‌بندی پررنگ دسته‌ها
> • کارت سه‌بعدی: روی کارت انگلیسی با سبک سایبر، پشت کارت توضیحات فارسی (کاشف، سال کشف، ریشه نام، کاربرد، خواص و تاریخچه)
> • جستجوی سریع با نام فارسی، انگلیسی، نماد یا عدد اتمی
> • حالت مطالعه و فلش‌کارت، نشان‌شده‌ها و پیشرفت یادگیری
> • پوسته تیره (سایبر) و روشن
> • ۱۰۰٪ آفلاین، بدون تبلیغ، بدون مجوز و بدون جمع‌آوری اطلاعات
>
> طراح و توسعه‌دهنده: محمدرضا لک (mohamadreza.lak) · مجوز MIT
> اطلاعات علمی با دقت از منابع معتبر تهیه شده اما مسئولیت استفاده نهایی با کاربر است.

**دسته‌بندی:** آموزش · **رده سنی:** همه · **کلیدواژه‌ها:** جدول مندلیف، جدول تناوبی، شیمی، عنصر، فلش‌کارت، کنکور

### دقت علمی
- جرم‌های اتمی: مقادیر استاندارد فشرده IUPAC؛ مقدار داخل `[ ]` = عدد جرمی پایدارترین ایزوتوپ (عنصرهای رادیواکتیو).
- ۵۷–۷۱ و ۸۹–۱۰۳ در ردیف جدا آمده‌اند (چیدمان رایج آموزشی)؛ بنا بر IUPAC ۲۰۲۱ لوتتیم و لورنسیم در گروه ۳ قرار می‌گیرند.
- دسته‌بندی عنصرهای ۱۰۹ تا ۱۱۸ و حالت ماده آن‌ها بر پایه پیش‌بینی‌های نظری است (در برنامه ذکر شده).
- منابع: IUPAC، Royal Society of Chemistry، PubChem، ویکی‌پدیا (صفحه‌های بررسی‌شده). اگر خطایی دیدید، Issue ثبت کنید.

---

## English

**Element.Lax** is an offline-first, educational periodic table with 3D flip flashcards, built for Iranian teenagers and young adults.

- Front of card: English only, cyber/neon style (symbol, atomic number, name, group/period, category).
- Back of card: concise Persian info (name, mass, state, discoverer & year, etymology, uses, key properties, short history).
- Search (Persian/English/symbol/number), 10 vivid category filters, study mode, favorites/learned, progress, Dark (cyber) & Light themes, optional PIN lock, data reset.
- 100% offline, no permissions, no analytics, no data collection.

### Tech
React 19 · Vite 7 · TypeScript · Tailwind CSS 4 · lucide-react · Vazirmatn (Persian) and JetBrains Mono (symbols) fonts bundled via `@fontsource` (no CDN) · localStorage persistence · tiny service worker · hash routing (hardware Back works).

### Libraries
`react`, `react-dom`, `tailwindcss`, `vite`, `vite-plugin-singlefile`, `lucide-react` (ISC), `@fontsource/vazirmatn` (OFL), `@fontsource/jetbrains-mono` (OFL).

### Build
```bash
npm install && npm run build   # → dist/index.html (single self-contained file)
```
Android packaging with Capacitor: see the Persian section above (`npx cap add android`, set `app_name` to exactly `Element.Lax`, minSdk 21, R8 enabled, generate a signed bundle/APK).

### Store listing (short)
"Interactive periodic table with 3D flashcards — Persian, offline, educational."

### Credits
Designer & Developer: **محمدرضا لک (mohamadreza.lak)** — MIT License, © 2025 mohamadreza.lak.

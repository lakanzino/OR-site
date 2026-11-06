# Hamyar-j (همیار-ج) – Offline Clinic Manager

Offline Android app for small clinics: patients, visit fees, insurance and reports.
Kotlin · Jetpack Compose (Material 3) · Room · MVVM · RTL / Persian UI.

The Android project lives at the **root** of this repository: `app/`,
`settings.gradle.kts`, `build.gradle.kts`, `gradle.properties`. The CI workflow is
`.github/workflows/android.yml`.

## Build

### Android Studio
Open the repository root → let Gradle sync → Run. (Android Studio creates the Gradle
wrapper automatically; no `gradle-wrapper.jar` is committed.)

### GitHub Actions (APK)
Push to `main` (or run the `Build Android APK` workflow manually, Actions →
`Build Android APK` → Run workflow). CI installs Gradle 8.9 + Android SDK 34 and
uploads the APK as the `Hamyar-j-apk` artifact (Actions → latest run → Artifacts).

The output file is renamed to **`Hamyar-j.apk`** by the `applicationVariants` /
`BaseVariantOutputImpl` block in `app/build.gradle.kts`
(`app/build/outputs/apk/debug/Hamyar-j.apk`).

## Logo
The app uses `R.drawable.app_logo`:

* `app/src/main/res/drawable/app_logo.webp` — the custom 512×512 logo (launcher icon
  and top app bar).
* The old placeholder `app_logo.xml` was removed: two drawables with the same name in
  one folder break resource merging.
* `ui/HamyarApp.kt` renders it with `painterResource(id = R.drawable.app_logo)`, and
  `AndroidManifest.xml` sets `android:icon="@drawable/app_logo"`.

## App name
`app/src/main/res/values/strings.xml` → `app_name` = `Hamyar-j`
(referenced by `android:label` in the manifest).

## Custom Persian font (e.g. Vazir)
1. Put `vazir_regular.ttf` / `vazir_bold.ttf` in `app/src/main/res/font/`.
2. In `ui/theme/Type.kt` change `AppTypography.fontFamily` (an example is in the comment).

## Structure
```
app/src/main/java/com/hamyarj/clinic
├── MainActivity.kt / HamyarApplication.kt
├── data/        Room entities, DAOs, database, repository
├── util/        thousand-separator formatting, dates, CSV, backup/restore
├── viewmodel/   SettingsViewModel, PatientViewModel, ReportsViewModel
└── ui/
    ├── theme/       Color, AppTypography, Theme
    ├── components/  AutoSizeText, CompactTextField, Dropdown, Pickers, Cards
    ├── navigation/  Screen (bottom tabs)
    └── screens/     Settings, PatientList, AddPatient, Reports
```

## Backup / Restore
* **Backup** writes the Room `.db` file to any location chosen in the system file picker.
* **Restore** validates the chosen file, replaces the database and restarts the app.
* **CSV** is UTF-8 with BOM (opens correctly in Excel with Persian text) and is shared via the Android share sheet.

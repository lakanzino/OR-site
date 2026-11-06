# Hamyar-j (همیار-ج) – Offline Clinic Manager

Offline Android app for small clinics: patients, visit fees, insurance and reports.
Kotlin · Jetpack Compose (Material 3) · Room · MVVM · RTL / Persian UI.

## Build

### Android Studio
Open this folder → let Gradle sync → Run. (Android Studio creates the Gradle wrapper automatically.)

### GitHub Actions (APK)
Push this folder as the **root of a repository**. The workflow in `.github/workflows/android.yml`
builds `app-debug.apk` and uploads it as an artifact (Actions → latest run → Artifacts).

## Your logo
The code uses `R.drawable.app_logo`. A placeholder vector is included at
`app/src/main/res/drawable/app_logo.xml`.
1. Copy your `app_logo.png` to `app/src/main/res/drawable/`
2. **Delete** `app_logo.xml` (same resource name would clash).

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

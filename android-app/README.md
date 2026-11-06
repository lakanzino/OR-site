# منشی مطب – Clinic Secretary (Android, Kotlin, Room)

Offline Persian/RTL app for a clinic secretary. Data lives in a local SQLite database (Room).
The editable source of truth is this `android-app/` directory. The original
`medical-clinic-secretary-app.zip` at the repository root is kept as the initial upload snapshot;
CI builds from this directory, not from the ZIP.

## What is included

- Patient registration and editing: name, optional phone, insurance, supplemental insurance,
  reason, Shamsi visit date, time and fee.
- Patient list with live name/phone search, inclusive Shamsi date-range filter, insurance filter,
  column visibility chips, details, edit and confirmed delete.
- Financial/statistical reports: patient count, total fees, supplemental-insurance count and
  breakdown by insurance for the active filters. Quick ranges: today, last 7 days, last 30 days, all.
- Custom insurance management: add/remove options in Settings. Removing an option does not rewrite
  historical patient records. The four original insurance options remain available.
- Selectable display/input currency: Toman or Rial (1 Toman = 10 Rials). New/edited amounts are stored
  canonically in Rials; legacy version-1 amounts are preserved and interpreted as Toman. Changing the
  display unit converts values without changing their real amount.
- Offline data tools: CSV export (all or filtered patients) and JSON backup/restore through Android's
  Storage Access Framework. No INTERNET or broad storage permission is requested.
- Feedback colors: green for successful confirmations, amber for warnings and destructive actions.

## Backup, restore and privacy

Backups are versioned JSON snapshots of all app tables (patients, settings, custom insurances),
not raw SQLite files. Restore validates the whole file before replacing data in one atomic Room
transaction. **Exports are not encrypted** and contain patient information; store them only in a
trusted location. Restore replaces current data, so take a backup first. CSV export includes a UTF-8
BOM for Persian spreadsheet compatibility and neutralizes spreadsheet-formula prefixes.

## Open & build

Requirements: JDK 17, Android SDK 34 / Build Tools 34.0.0, Gradle 8.7.

```bash
cd android-app
gradle testDebugUnitTest lintDebug assembleDebug --no-daemon
```

APK output: `app/build/outputs/apk/debug/app-debug.apk`.
Android Studio can open this folder directly. The GitHub workflow
`.github/workflows/build-apk.yml` runs tests, lint and `assembleDebug`, then uploads `app-debug-apk`.

## Tests

JVM tests cover currency parsing/conversion/overflow, Shamsi leap-year and round-trip conversion,
search/filter/report calculations, CSV formula protection and backup validation/round-trip.
Robolectric tests cover the Room v1→v2 migration and atomic restore behavior.

## Structure

- `data/Entities.kt`, `data/Daos.kt`, `data/AppDatabase.kt` – Room schema v2 and migration from v1
- `data/ClinicRepository.kt` – validated writes, serialized mutations and atomic restore
- `data/ClinicBackup.kt` – versioned JSON backup codec and validation
- `util/PersianDate.kt` – offline Jalali conversion, validation and Persian digits
- `util/Currency.kt`, `util/PatientReports.kt`, `util/CsvExport.kt`, `util/Text.kt`
- `MainViewModel.kt` – shared state, filters and file operations
- `ui/RegisterFragment.kt` – register/edit form
- `ui/PatientsFragment.kt`, `ui/PatientAdapter.kt` – searchable/filterable list
- `ui/ReportsFragment.kt` – financial/statistical report and filtered CSV export
- `ui/SettingsFragment.kt` – doctor settings, currency, insurance management, backup/restore/CSV
- `ui/Feedback.kt`, `ui/ShamsiDatePicker.kt` – green/amber feedback and offline Shamsi date picker

import { useState } from "react";

type Node = { name: string; note?: string; children?: Node[] };

const tree: Node[] = [
  { name: ".github/workflows/android.yml", note: "GitHub Actions → builds app-debug.apk" },
  { name: "settings.gradle.kts / build.gradle.kts / gradle.properties" },
  {
    name: "app/",
    children: [
      { name: "build.gradle.kts", note: "Compose, Room (KSP), Navigation" },
      { name: "src/main/AndroidManifest.xml", note: "FileProvider for CSV sharing" },
      {
        name: "src/main/res/",
        children: [
          { name: "drawable/app_logo.xml", note: "placeholder → replace with app_logo.png" },
          { name: "xml/file_paths.xml" },
          { name: "values/ strings.xml, themes.xml" },
        ],
      },
      {
        name: "src/main/java/com/hamyarj/clinic/",
        children: [
          { name: "MainActivity.kt, HamyarApplication.kt", note: "RTL root, repository holder" },
          {
            name: "data/",
            children: [
              { name: "Patient.kt, AppSettings.kt", note: "Room entities" },
              { name: "Daos.kt, AppDatabase.kt, ClinicRepository.kt" },
            ],
          },
          {
            name: "util/",
            children: [
              { name: "Formatters.kt", note: "1,200,000 formatting + digit normalising" },
              { name: "CsvExporter.kt, BackupManager.kt, DateUtils.kt, InsuranceOptions.kt" },
            ],
          },
          {
            name: "viewmodel/",
            children: [
              { name: "SettingsViewModel.kt" },
              { name: "PatientViewModel.kt", note: "list, search, columns, add/edit form" },
              { name: "ReportsViewModel.kt", note: "date range + insurance breakdown" },
            ],
          },
          {
            name: "ui/",
            children: [
              { name: "HamyarApp.kt", note: "top bar with logo + bottom nav (4 tabs)" },
              { name: "theme/ Color.kt, Type.kt (AppTypography), Theme.kt" },
              {
                name: "components/",
                children: [
                  { name: "AutoSizeText.kt", note: "single line, shrinks to fit" },
                  { name: "CompactTextField.kt", note: "+ live thousand-separator transformation" },
                  { name: "CompactDropdown.kt, Pickers.kt, Common.kt" },
                ],
              },
              { name: "screens/ Settings, AddPatient, PatientList, Reports" },
            ],
          },
        ],
      },
    ],
  },
];

const features = [
  {
    t: "Settings",
    d: "Doctor name, specialty, default fee (live 1,200,000 formatting), currency unit, CSV export, DB backup / restore.",
  },
  {
    t: "Patient list",
    d: "Search by name, column visibility checkboxes, total patients + income bar, edit & delete (with confirmation).",
  },
  {
    t: "Add / edit patient",
    d: "Required name, numeric phone, basic insurance dropdown, supplemental checkbox revealing a second dropdown, time picker, pre-filled fee.",
  },
  {
    t: "Reports",
    d: "Start / end date pickers, totals for the range and a per-basic-insurance breakdown with proportional bars.",
  },
];

function Tree({ nodes, depth = 0 }: { nodes: Node[]; depth?: number }) {
  return (
    <ul className={depth ? "ml-4 border-l border-slate-200 pl-3" : ""}>
      {nodes.map((n) => (
        <li key={n.name} className="py-0.5">
          <span className={n.children ? "font-semibold text-slate-800" : "text-slate-700"}>{n.name}</span>
          {n.note && <span className="ml-2 text-xs text-slate-400">— {n.note}</span>}
          {n.children && <Tree nodes={n.children} depth={depth + 1} />}
        </li>
      ))}
    </ul>
  );
}

const steps = [
  "Download the ZIP and extract it. The extracted folder is the Android project root.",
  "Create a GitHub repository and push the folder contents (the .github folder must be at the repo root).",
  "Open the Actions tab → “Build APK” → download the app-debug.apk artifact.",
  "Optional: copy your app_logo.png to app/src/main/res/drawable/ and delete app_logo.xml.",
];

export default function App() {
  const [copied, setCopied] = useState(false);

  return (
    <div className="min-h-screen bg-[#f3f6fa] text-slate-800">
      <header className="border-b border-slate-300 bg-white">
        <div className="mx-auto flex max-w-5xl items-center gap-4 px-6 py-5">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#5c86b5]">
            <svg viewBox="0 0 24 24" className="h-7 w-7 text-white" fill="currentColor">
              <path d="M10 4h4v6h6v4h-6v6h-4v-6H4v-4h6z" />
            </svg>
          </div>
          <div>
            <h1 className="text-2xl font-bold">Hamyar-j · همیار-ج</h1>
            <p className="text-sm text-slate-500">Offline clinic manager · Kotlin · Jetpack Compose · Room · MVVM</p>
          </div>
          <a
            href="./hamyar-j-android.zip"
            download
            className="ml-auto whitespace-nowrap rounded-xl bg-[#5c86b5] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#4b739f]"
          >
            Download project (.zip)
          </a>
        </div>
      </header>

      <main className="mx-auto max-w-5xl space-y-8 px-6 py-8">
        <section className="rounded-2xl border border-slate-300 bg-white p-6">
          <h2 className="mb-2 text-lg font-bold">What you get</h2>
          <p className="text-sm text-slate-600">
            A complete, buildable Android Studio / Gradle project. This page only documents it: the real app is the Kotlin
            code inside the ZIP, so it has to be compiled with Android tooling (Android Studio or the included GitHub
            Actions workflow).
          </p>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {features.map((f) => (
              <div key={f.t} className="rounded-xl border border-slate-300 bg-[#f8fafc] p-4">
                <h3 className="font-bold text-[#1f3c5c]">{f.t}</h3>
                <p className="mt-1 text-sm text-slate-600">{f.d}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="grid gap-6 md:grid-cols-2">
          <div className="rounded-2xl border border-slate-300 bg-white p-6">
            <h2 className="mb-3 text-lg font-bold">Project structure</h2>
            <div className="overflow-x-auto font-mono text-[13px] leading-6">
              <Tree nodes={tree} />
            </div>
          </div>

          <div className="space-y-6">
            <div className="rounded-2xl border border-slate-300 bg-white p-6">
              <h2 className="mb-3 text-lg font-bold">Build the APK via GitHub</h2>
              <ol className="list-decimal space-y-2 pl-5 text-sm text-slate-600">
                {steps.map((s) => (
                  <li key={s}>{s}</li>
                ))}
              </ol>
            </div>

            <div className="rounded-2xl border border-slate-300 bg-white p-6">
              <h2 className="mb-3 text-lg font-bold">Design decisions</h2>
              <ul className="list-disc space-y-1.5 pl-5 text-sm text-slate-600">
                <li>
                  <b>AppTypography</b> object (FontFamily.Default, bold titles/numbers): swap in Vazir by changing one line.
                </li>
                <li>
                  Logo via <code className="rounded bg-slate-100 px-1">R.drawable.app_logo</code>.
                </li>
                <li>Pastel blue/grey palette with clear 1dp borders on cards and inputs.</li>
                <li>Compact custom text fields; every label/button is single-line and auto-shrinks.</li>
                <li>Thousand separators applied live through a VisualTransformation with correct cursor mapping.</li>
                <li>Backup/restore and CSV sharing use SAF + FileProvider, so no storage permissions are needed.</li>
                <li>RTL layout is forced for the Persian UI.</li>
              </ul>
              <button
                className="mt-4 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                onClick={() => {
                  navigator.clipboard?.writeText("gradle assembleDebug");
                  setCopied(true);
                  setTimeout(() => setCopied(false), 1500);
                }}
              >
                {copied ? "Copied!" : "Copy local build command: gradle assembleDebug"}
              </button>
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}

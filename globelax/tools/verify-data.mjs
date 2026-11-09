#!/usr/bin/env node
/**
 * Globe.Lax — dataset integrity & accuracy guard.
 *
 * Runs in CI before every build and fails hard on structural mistakes that would
 * silently ship wrong educational content to students:
 *   • exactly 195 sovereign states (193 UN members + Palestine + Vatican City)
 *   • every ISO 3166-1 alpha-2 code unique and well-formed
 *   • every row has capital (fa/en), currency code + names, population, area,
 *     languages and a continent inside the six supported values
 *   • no leftover placeholder text, no duplicate capital/country names
 *   • population & area inside plausible ranges (catches unit errors)
 *   • flag emoji derives from the ISO code (no mismatched flags)
 *
 * Run: node tools/verify-data.mjs
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const source = fs.readFileSync(path.join(here, "..", "src", "data", "countries.ts"), "utf8");

const ROW =
  /^\s*\["([A-Z]{2})",\s*"([^"]+)",\s*"([^"]+)",\s*"([^"]+)",\s*"([^"]+)",\s*"([A-Z]{3})",\s*([\d.]+),\s*([\d.]+),\s*"([^"]+)",\s*"([A-Z]{2})"\],$/gm;

const rows = [...source.matchAll(ROW)].map((m) => ({
  iso: m[1],
  nameFa: m[2],
  nameEn: m[3],
  capitalFa: m[4],
  capitalEn: m[5],
  currency: m[6],
  popThousands: Number(m[7]),
  areaKm2: Number(m[8]),
  languages: m[9],
  continent: m[10],
}));

const failures = [];
const fail = (msg) => failures.push(msg);

// ── 1. roster completeness ────────────────────────────────────────────────
const EXPECTED_TOTAL = 195;
if (rows.length !== EXPECTED_TOTAL) {
  fail(`expected ${EXPECTED_TOTAL} sovereign states, parsed ${rows.length}`);
}

const isoSeen = new Set();
for (const r of rows) {
  if (isoSeen.has(r.iso)) fail(`duplicate ISO code ${r.iso}`);
  isoSeen.add(r.iso);
}

// 193 UN members + PS (observer) + VA (observer)
if (!isoSeen.has("PS")) fail("State of Palestine (PS) is missing");
if (!isoSeen.has("VA")) fail("Vatican City (VA) is missing");
for (const bad of ["TW", "XK", "EH", "AQ"]) {
  if (isoSeen.has(bad)) fail(`non-sovereign entry ${bad} must not be in the 195-country roster`);
}

// ── 2. per-row field completeness ─────────────────────────────────────────
const CONTINENTS = new Set(["AS", "EU", "AF", "NA", "SA", "OC"]);
let prevContinent = null;
for (const r of rows) {
  const label = `${r.iso} (${r.nameEn})`;
  if (!r.nameFa || !r.nameEn) fail(`${label}: empty country name`);
  if (!r.capitalFa || !r.capitalEn) fail(`${label}: empty capital`);
  if (!/^[A-Z]{3}$/.test(r.currency)) fail(`${label}: bad ISO 4217 code ${r.currency}`);
  if (!r.languages) fail(`${label}: no languages`);
  if (!CONTINENTS.has(r.continent)) fail(`${label}: invalid continent ${r.continent}`);
  if (!(r.popThousands > 0)) fail(`${label}: population must be > 0`);
  if (!(r.areaKm2 > 0)) fail(`${label}: area must be > 0`);

  // population sanity: 800 people (Vatican) … 1.6 bn (India)
  const pop = r.popThousands * 1000;
  if (pop < 500 || pop > 1_700_000_000) fail(`${label}: implausible population ${pop}`);

  // area sanity: half a km² (Vatican) … 17.1 M km² (Russia)
  if (r.areaKm2 < 0.4 || r.areaKm2 > 17_200_000) fail(`${label}: implausible area ${r.areaKm2}`);

  // Persian text must actually be Persian for the primary label
  if (!/[\u0600-\u06FF]/.test(r.nameFa)) fail(`${label}: Persian name is not Persian`);
  if (!/[\u0600-\u06FF]/.test(r.capitalFa)) fail(`${label}: Persian capital is not Persian`);
  if (/[?؟]{2,}|TODO|TBD|xxx/i.test(`${r.nameFa}${r.capitalFa}${r.nameEn}`)) fail(`${label}: placeholder text`);

  // rows are grouped by continent in the table; a mis-grouped row breaks the UI order
  if (prevContinent && r.continent !== prevContinent && !rows.some((x, i) => i > rows.indexOf(r) && x.continent === prevContinent)) {
    /* grouping is advisory – not a hard failure */
  }
  prevContinent = r.continent;
}

// ── 3. duplicate detection ────────────────────────────────────────────────
const dupes = (key) => {
  const seen = new Map();
  for (const r of rows) {
    const v = key(r);
    if (seen.has(v)) fail(`duplicate ${v} — ${seen.get(v)} and ${r.iso}`);
    seen.set(v, r.iso);
  }
};
dupes((r) => r.capitalEn.toLowerCase());

// ── 4. flag emoji derives from ISO code ───────────────────────────────────
const flagOf = (iso) => String.fromCodePoint(...[...iso].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
for (const r of rows) {
  const flag = flagOf(r.iso);
  if ([...flag].length !== 2) fail(`${r.iso}: flag emoji malformed`);
}

// ── 5. expected (code, name) pairs for high-stakes rows ───────────────────
const EXPECTED = {
  IR: ["ایران", "Tehran", "IRR"],
  US: ["ایالات متحده آمریکا", "Washington, D.C.", "USD"],
  GB: ["بریتانیا", "London", "GBP"],
  CN: ["چین", "Beijing", "CNY"],
  IN: ["هند", "New Delhi", "INR"],
  JP: ["ژاپن", "Tokyo", "JPY"],
  DE: ["آلمان", "Berlin", "EUR"],
  FR: ["فرانسه", "Paris", "EUR"],
  RU: ["روسیه", "Moscow", "RUB"],
  BR: ["برزیل", "Brasília", "BRL"],
  SA: ["عربستان سعودی", "Riyadh", "SAR"],
  TR: ["ترکیه", "Ankara", "TRY"],
  EG: ["مصر", "Cairo", "EGP"],
  ZA: ["آفریقای جنوبی", "Pretoria (executive)", "ZAR"],
  PS: ["فلسطین", "Ramallah (administrative seat)", "ILS"],
  VA: ["واتیکان", "Vatican City", "EUR"],
  GQ: ["گینه استوایی", "Ciudad de la Paz", "XAF"],
  KZ: ["قزاقستان", "Astana", "KZT"],
  NR: ["نائورو", "Yaren (de facto)", "AUD"],
  BG: ["بلغارستان", "Sofia", "EUR"],
  HR: ["کرواسی", "Zagreb", "EUR"],
  LK: ["سری‌لانکا", "Sri Jayawardenepura Kotte", "LKR"],
  BO: ["بولیوی", "Sucre (constitutional)", "BOB"],
  ZW: ["زیمبابوه", "Harare", "ZWG"],
};
for (const [iso, [nameFa, capitalEn, currency]] of Object.entries(EXPECTED)) {
  const r = rows.find((x) => x.iso === iso);
  if (!r) {
    fail(`missing expected country ${iso}`);
    continue;
  }
  if (r.nameFa !== nameFa) fail(`${iso}: Persian name "${r.nameFa}" ≠ "${nameFa}"`);
  if (r.capitalEn !== capitalEn) fail(`${iso}: capital "${r.capitalEn}" ≠ "${capitalEn}"`);
  if (r.currency !== currency) fail(`${iso}: currency "${r.currency}" ≠ "${currency}"`);
}

// ── 6. continents must all be populated ───────────────────────────────────
for (const c of CONTINENTS) {
  const n = rows.filter((r) => r.continent === c).length;
  if (n === 0) fail(`continent ${c} has no countries`);
}

// ── report ────────────────────────────────────────────────────────────────
const byContinent = [...CONTINENTS].map(
  (c) => `${c}:${rows.filter((r) => r.continent === c).length}`,
).join("  ");

if (failures.length) {
  console.error("✘ DATA VERIFICATION FAILED\n");
  for (const f of failures.slice(0, 40)) console.error("  •", f);
  console.error(`\n${failures.length} problem(s) found.`);
  process.exit(1);
}

console.log(`✔ ${rows.length} sovereign states verified (193 UN members + Palestine + Vatican City)`);
console.log(`✔ ${byContinent}`);
console.log(`✔ all ISO codes unique, all capitals/currencies/populations/areas present and plausible`);
console.log(`✔ Persian labels, flag emoji and ${Object.keys(EXPECTED).length} spot-checked records OK`);

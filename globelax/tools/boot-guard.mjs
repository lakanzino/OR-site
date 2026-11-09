// Guards the Android launch path: the shipped index.html must always contain a
// visible boot layer plus an error handler, so a JS failure can never leave the
// user staring at a blank screen.
import fs from "node:fs";

const html = fs.readFileSync("dist/index.html", "utf8");
const checks = [
  ["boot layer in bundle", html.includes('id="boot"')],
  ["boot watchdog present", html.includes("__GLOBELAX_BOOTED__")],
  ["global error handler", html.includes('addEventListener("error"')],
  ["unhandledrejection handler", html.includes("unhandledrejection")],
  ["boot timeout armed", html.includes("timeout waiting for the app to start")],
  ["relative asset paths (Capacitor https://localhost)", !html.includes('href="/app-logo.svg"')],
  ["no absolute /src/ reference", !html.includes('src="/src/')],
];

let failed = 0;
for (const [name, ok] of checks) {
  console.log(`${ok ? "✔" : "✘"} ${name}`);
  if (!ok) failed++;
}
if (failed) {
  console.error(`\n${failed} boot-guard check(s) failed`);
  process.exit(1);
}
console.log(`\n${checks.length}/${checks.length} boot-guard checks passed`);

import { JSDOM } from "jsdom";
import fs from "fs";

const html = fs.readFileSync(new URL("../dist/index.html", import.meta.url), "utf8");
const m = html.match(/<script type="module"[^>]*>([\s\S]*?)<\/script>/);
const file = new URL("../node_modules/.cache/bundle-e2e.mjs", import.meta.url).pathname;
fs.mkdirSync(new URL("../node_modules/.cache", import.meta.url), { recursive: true });
const dom = new JSDOM('<!doctype html><html lang="fa" dir="rtl"><head></head><body><div id="root"></div></body></html>', { pretendToBeVisual: true, url: "https://localhost/" });
const w = dom.window;
for (const k of ["window","document","navigator","location","history","HTMLElement","Element","Node","Event","CustomEvent","KeyboardEvent","MouseEvent","PointerEvent","CSS","DOMParser","XMLSerializer","getComputedStyle","requestAnimationFrame","cancelAnimationFrame","matchMedia","ResizeObserver","IntersectionObserver","Image","Blob","URL","TextEncoder","TextDecoder","MutationObserver","NodeFilter","File","FileReader","FormData","AbortController","localStorage","sessionStorage","Storage"]) {
  if (w[k] !== undefined) { try { globalThis[k] = w[k]; } catch {} }
}
globalThis.self = w;
w.matchMedia = () => ({ matches:false, addEventListener(){}, removeEventListener(){}, addListener(){}, removeListener(){} });
w.ResizeObserver = class { observe(){} unobserve(){} disconnect(){} };
w.IntersectionObserver = class { observe(){} unobserve(){} disconnect(){} };
w.HTMLCanvasElement.prototype.getContext = () => null;
w.scrollTo = () => {};
globalThis.ResizeObserver = w.ResizeObserver;
globalThis.IntersectionObserver = w.IntersectionObserver;
if (!globalThis.matchMedia) globalThis.matchMedia = w.matchMedia;
if (!globalThis.requestAnimationFrame) globalThis.requestAnimationFrame = (cb)=>setTimeout(()=>cb(Date.now()),16);
if (!globalThis.cancelAnimationFrame) globalThis.cancelAnimationFrame = clearTimeout;

fs.writeFileSync(file, m[1]);
await import("file://" + file + "?t=" + Date.now());
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const txt = () => (w.document.body.textContent || "").replace(/\s+/g, " ").trim();
const has = (re) => re.test(txt());
const results = [];
const check = (name, cond) => { results.push([name, !!cond]); };

await sleep(1600);
check("home renders", has(/جهان را/));
check("search present", !!w.document.querySelector('input[aria-label="جستجو"]'));

// dismiss onboarding
const skip = [...w.document.querySelectorAll("button")].find(b => /رد کردن/.test(b.textContent));
if (skip) skip.click();
await sleep(400);
check("onboarding dismissed", !has(/به Globe\.Lax خوش آمدید/));

// open a country via history navigation
w.location.hash = "#/country/IR";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("country detail Iran", has(/ایران/) && has(/تهران/) && has(/ریال/));
check("detail shows population", has(/میلیون/));
check("detail shows area", has(/کیلومتر مربع/));
check("detail shows languages", has(/فارسی/));
check("detail currency code IRR", has(/IRR/));

// favorites toggle
const favBtn = w.document.querySelector('button[aria-label="علاقه‌مندی"]');
if (favBtn) favBtn.click();
await sleep(300);
const store = JSON.parse(globalThis.localStorage.getItem("globelax:v1") || "{}");
check("favorite persisted", (store.favorites || []).includes("IR"));

// learned toggle
const learnBtn = w.document.querySelector('button[aria-label="یاد گرفتم"]');
if (learnBtn) learnBtn.click();
await sleep(300);
const store2 = JSON.parse(globalThis.localStorage.getItem("globelax:v1") || "{}");
check("learned persisted", (store2.learned || []).includes("IR"));
check("xp awarded", (store2.stats?.xp || 0) >= 5);

// quiz
w.location.hash = "#/quiz";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("quiz setup", has(/آزمون/) && has(/پایتخت/));
const startQuiz = [...w.document.querySelectorAll("button")].find(b => /شروع|آغاز/.test(b.textContent));
if (startQuiz) startQuiz.click();
await sleep(700);
check("quiz playing", has(/سؤال/) || has(/امتیاز/));

// flashcards
w.location.hash = "#/flash";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("flashcards setup", has(/فلش‌کارت/));
const startFlash = [...w.document.querySelectorAll("button")].find(b => /شروع/.test(b.textContent));
if (startFlash) startFlash.click();
await sleep(700);
check("flashcard flipped card", has(/برای دیدن پاسخ بزنید/) || has(/بلدم/));

// settings
w.location.hash = "#/settings";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("settings screen", has(/تنظیمات/) && has(/پوسته/));
const lightBtn = [...w.document.querySelectorAll("button")].find(b => /روشن/.test(b.textContent));
if (lightBtn) lightBtn.click();
await sleep(400);
const store3 = JSON.parse(globalThis.localStorage.getItem("globelax:v1") || "{}");
check("theme switched to light", store3.settings?.theme === "light");
check("theme applied to DOM", w.document.documentElement.dataset.theme === "light");

// about
w.location.hash = "#/about";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("about purpose text", has(/جهت آموزش به نوجوانان و جوانان ایران عزیز/));
check("about author", has(/محمدرضا لک/));
check("about MIT", has(/MIT/));

// map
w.location.hash = "#/map";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("map page", has(/نقشه/));

// saved
w.location.hash = "#/saved";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
check("saved page", has(/پیشرفت من/));

// search filter
w.location.hash = "#/";
w.dispatchEvent(new w.Event("hashchange"));
await sleep(600);
const input = w.document.querySelector('input[aria-label="جستجو"]');
if (input) {
  const setter = Object.getOwnPropertyDescriptor(w.HTMLInputElement.prototype, "value").set;
  setter.call(input, "ژاپن");
  input.dispatchEvent(new w.Event("input", { bubbles: true }));
  await sleep(500);
  check("search narrows to Japan", has(/ژاپن/) && has(/توکیو/));
}

let pass = 0;
for (const [n, ok] of results) { console.log((ok ? "✔" : "✘"), n); if (ok) pass++; }
console.log(`\n${pass}/${results.length} checks passed`);
process.exit(pass === results.length ? 0 : 1);

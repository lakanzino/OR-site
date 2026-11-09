import { JSDOM } from "jsdom";
import fs from "fs";
import path from "path";

const html = fs.readFileSync(new URL("../dist/index.html", import.meta.url), "utf8");
const m = html.match(/<script type="module"[^>]*>([\s\S]*?)<\/script>/);
if (!m) throw new Error("no module script");

const dom = new JSDOM('<!doctype html><html lang="fa" dir="rtl"><head></head><body><div id="root"></div></body></html>', {
  pretendToBeVisual: true, url: "https://localhost/",
});
const w = dom.window;

// expose jsdom globals to the module runtime
for (const k of ["window","document","navigator","location","history","HTMLElement","Element","Node","Event","CustomEvent","KeyboardEvent","MouseEvent","PointerEvent","CSS","DOMParser","MutationObserver","NodeFilter","File","FileReader","FormData","AbortController","XMLSerializer","getComputedStyle","requestAnimationFrame","cancelAnimationFrame","matchMedia","ResizeObserver","IntersectionObserver","Image","Blob","URL","TextEncoder","TextDecoder"]) {
  if (w[k] !== undefined) { try { globalThis[k] = w[k]; } catch { /* read-only global */ } }
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
if (!globalThis.requestAnimationFrame) globalThis.requestAnimationFrame = (cb)=>setTimeout(()=>cb(performance.now()),16);
if (!globalThis.cancelAnimationFrame) globalThis.cancelAnimationFrame = clearTimeout;

const file = "/tmp/bundle-under-test.mjs";
fs.writeFileSync(file, m[1]);
try {
  fs.mkdirSync(new URL("../node_modules/.cache", import.meta.url), { recursive: true });
  await import(file + "?t=" + Date.now());
} catch (e) {
  console.log("IMPORT ERROR:", e.message.slice(0, 800));
}
await new Promise(r => setTimeout(r, 2000));
const text = w.document.body.textContent || "";
console.log("root children:", w.document.querySelector("#root").children.length);
console.log("text length:", text.length);
console.log("has Globe:", /Globe/.test(text));
console.log("has جهان:", /جهان/.test(text));
console.log("has 195 in Persian digits:", /۱۹۵/.test(text));
console.log("search input:", !!w.document.querySelector('input[aria-label="جستجو"]'));
console.log("bottom nav:", !!w.document.querySelector('nav[aria-label="ناوبری اصلی"]'));
console.log("sample:", text.replace(/\s+/g," ").slice(0, 260));

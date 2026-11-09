import { useEffect, useMemo, useRef, useState } from "react";
import { Search, X, Minus, Plus, Maximize2, Dices } from "lucide-react";
import { CATEGORIES, ELEMENTS, type CategoryId, type Element } from "../data/elements";
import { Logo } from "../components/Logo";
import { PeriodicTable } from "../components/PeriodicTable";
import { norm, toFa } from "../lib/fa";
import { go } from "../lib/router";
import { setState, useStore } from "../lib/store";

const INDEX = ELEMENTS.map((e) => ({
  z: e.z,
  keys: [norm(e.fa), norm(e.en), norm(e.sym)],
}));

function runSearch(q: string): Element[] | null {
  const n = norm(q.trim());
  if (!n) return null;
  const isNum = /^\d+$/.test(n);
  const out: Element[] = [];
  for (const it of INDEX) {
    let ok = false;
    if (isNum) ok = String(it.z) === n || (n.length < 3 && String(it.z).startsWith(n));
    else ok = it.keys.some((k) => k.includes(n));
    if (ok) out.push(ELEMENTS[it.z - 1]);
  }
  // exact symbol / number first
  out.sort((a, b) => Number(norm(b.sym) === n) - Number(norm(a.sym) === n) || a.z - b.z);
  return out;
}

export function Home() {
  const { cs: savedCs, favs, learned } = useStore();
  const [q, setQ] = useState("");
  const [cat, setCat] = useState<"all" | CategoryId>("all");
  const [w, setW] = useState(window.innerWidth);
  const box = useRef<HTMLDivElement>(null);
  const hdr = useRef<HTMLElement>(null);

  useEffect(() => {
    const on = () => setW(window.innerWidth);
    window.addEventListener("resize", on);
    return () => window.removeEventListener("resize", on);
  }, []);

  const fit = Math.max(28, Math.min(76, Math.floor((Math.min(w, 1200) - 20 - 17 * 3) / 18)));
  const cs = savedCs || Math.max(38, fit);

  const results = useMemo(() => runSearch(q), [q]);
  const matchSet = useMemo(() => (results ? new Set(results.map((r) => r.z)) : null), [results]);

  const zoom = (d: number) => setState({ cs: Math.max(28, Math.min(88, cs + d)) });
  const open = (z: number) => go("e/" + z);

  return (
    <div className="h-full flex flex-col bg-cyber relative">
      <header ref={hdr} className="shrink-0 px-3 pt-3 pb-2 flex flex-col gap-2.5">
        <div className="flex items-center gap-3">
          <Logo size={42} />
          <div className="flex-1 min-w-0" dir="ltr" style={{ textAlign: "right" }}>
            <h1 className="mono neon text-[1.35rem] font-extrabold leading-tight tracking-tight">Element.Lax</h1>
          </div>
          <button
            className="iconbtn"
            aria-label="عنصر تصادفی"
            onClick={() => open(1 + Math.floor(Math.random() * 118))}
          >
            <Dices size={22} />
          </button>
        </div>

        <div className="relative">
          <Search size={20} className="absolute top-1/2 -translate-y-1/2 text-muted pointer-events-none" style={{ insetInlineStart: 14 }} />
          <input
            type="search"
            inputMode="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="جستجو: نام فارسی، English، نماد یا عدد اتمی…"
            aria-label="جستجوی عنصر"
            enterKeyHint="search"
            className="w-full h-12 rounded-2xl bg-surf border-[1.5px] border-line text-fg placeholder:text-muted/80 text-[0.95rem] outline-none focus:border-accent transition-colors [&::-webkit-search-cancel-button]:hidden"
            style={{ paddingInlineStart: 44, paddingInlineEnd: 44 }}
          />
          {q && (
            <button
              aria-label="پاک کردن جستجو"
              onClick={() => setQ("")}
              className="absolute top-1/2 -translate-y-1/2 w-9 h-9 rounded-full flex items-center justify-center text-muted"
              style={{ insetInlineEnd: 6 }}
            >
              <X size={18} />
            </button>
          )}
        </div>

        <div className="flex gap-2 overflow-x-auto no-scrollbar -mx-3 px-3 pb-0.5" role="tablist" aria-label="دسته‌ها">
          <button className={"chip " + (cat === "all" ? "on" : "")} role="tab" aria-selected={cat === "all"} onClick={() => setCat("all")}>
            همه
          </button>
          {CATEGORIES.map((c) => (
            <button
              key={c.id}
              role="tab"
              aria-selected={cat === c.id}
              className={"chip " + (cat === c.id ? "on" : "")}
              style={{ "--c": c.color } as React.CSSProperties}
              onClick={() => setCat(cat === c.id ? "all" : c.id)}
            >
              <span className="dot" />
              {c.fa}
            </button>
          ))}
        </div>
      </header>

      {/* Search results dropdown */}
      {results && (
        <div className="absolute inset-x-3 z-30 panel rounded-2xl max-h-[45%] overflow-y-auto scroll-thin fade-up" style={{ top: (hdr.current?.offsetHeight ?? 168) - 2 }}>
          {results.length === 0 ? (
            <div className="p-5 text-center text-muted text-sm">عنصری پیدا نشد. نام، نماد یا عدد اتمی دیگری را امتحان کنید.</div>
          ) : (
            <ul>
              {results.slice(0, 30).map((r) => {
                const c = CATEGORIES.find((x) => x.id === r.cat)!;
                return (
                  <li key={r.z}>
                    <button
                      onClick={() => open(r.z)}
                      className="w-full flex items-center gap-3 px-3 py-2.5 min-h-[56px] text-start border-b border-line/60 last:border-0 active:bg-surf2"
                    >
                      <span
                        className="mono w-11 h-11 rounded-xl border-2 flex items-center justify-center font-extrabold shrink-0"
                        style={{ borderColor: c.color, background: `${c.color}26`, color: "var(--cell-text)" }}
                        dir="ltr"
                      >
                        {r.sym}
                      </span>
                      <span className="flex-1 min-w-0">
                        <span className="block font-bold truncate">{r.fa}</span>
                        <span className="block text-xs text-muted mono truncate" dir="ltr" style={{ textAlign: "start" }}>
                          {r.en}
                        </span>
                      </span>
                      <span className="text-sm text-muted font-bold">{toFa(r.z)}</span>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      )}

      <div ref={box} className="flex-1 min-h-0 overflow-auto scroll-thin px-2 pb-4" style={{ touchAction: "pan-x pan-y pinch-zoom" }}>
        <div className="w-max mx-auto py-1">
          <PeriodicTable cs={cs} cat={cat} matches={matchSet} favs={favs} learned={learned} onOpen={open} />
        </div>
      </div>

      {/* Zoom controls */}
      <div className="absolute bottom-3 flex flex-col gap-2 z-20" style={{ insetInlineEnd: 12 }}>
        <button className="iconbtn !w-11 !h-11 panel" onClick={() => zoom(6)} aria-label="بزرگ‌نمایی">
          <Plus size={20} />
        </button>
        <button className="iconbtn !w-11 !h-11 panel" onClick={() => setState({ cs: 0 })} aria-label="اندازه خودکار">
          <Maximize2 size={18} />
        </button>
        <button className="iconbtn !w-11 !h-11 panel" onClick={() => zoom(-6)} aria-label="کوچک‌نمایی">
          <Minus size={20} />
        </button>
      </div>
    </div>
  );
}

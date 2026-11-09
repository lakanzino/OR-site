import { lazy, Suspense, useCallback, useMemo, useState } from "react";
import { ChevronLeft, Search } from "lucide-react";
import { COUNTRIES, COUNTRY_MAP, normalize } from "@/data/countries";
import { CONTINENTS, CONTINENT_ORDER, type Continent } from "@/data/dictionaries";
import { Flag } from "@/components/Flag";
import { go, setNavList } from "@/lib/router";
import { popFa } from "@/lib/util";

const Globe = lazy(() => import("@/components/Globe"));

export default function MapPage() {
  const [sel, setSel] = useState<string | null>(null);
  const [cont, setCont] = useState<Continent | null>(null);
  const [q, setQ] = useState("");
  const onSelect = useCallback((iso: string | null) => setSel(iso), []);
  const c = sel ? COUNTRY_MAP[sel] : null;

  const hits = useMemo(() => {
    const n = normalize(q);
    if (!n) return [];
    return COUNTRIES.filter((x) => x.searchKey.includes(n)).slice(0, 5);
  }, [q]);

  return (
    <div className="space-y-4">
      <div className="anim-up">
        <h1 className="text-3xl font-black">نقشه <span className="en muted text-lg">World Map</span></h1>
        <p className="muted mt-1">کره را بکش و بچرخان؛ روی هر کشور بزن.</p>
      </div>

      <div className="relative">
        <Search className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 muted" size={20} />
        <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="پیدا کردن کشور روی کره…" aria-label="جستجوی کشور روی نقشه" />
        {hits.length > 0 && (
          <ul className="card3d absolute inset-x-0 top-14 z-30 overflow-hidden p-1">
            {hits.map((h) => (
              <li key={h.isoCode}>
                <button className="flex min-h-12 w-full items-center gap-3 rounded-xl p-2 text-start hover:bg-white/10" onClick={() => { setSel(h.isoCode); setQ(""); }}>
                  <Flag iso={h.isoCode} emoji={h.flagEmoji} className="w-10" />
                  <span className="font-extrabold">{h.nameFa}</span>
                  <span className="en muted text-sm">{h.nameEn}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="hide-scroll -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
        <button className="chip dark-text" style={{ ["--chip" as string]: "var(--yellow)" }} aria-pressed={cont === null} onClick={() => setCont(null)}>🌍 همه</button>
        {CONTINENT_ORDER.map((k) => (
          <button key={k} className={`chip ${k === "AF" ? "dark-text" : ""}`} style={{ ["--chip" as string]: CONTINENTS[k].color }} aria-pressed={cont === k} onClick={() => setCont(cont === k ? null : k)}>
            <span className="inline-block h-3 w-3 rounded-full" style={{ background: CONTINENTS[k].color, boxShadow: "0 0 0 2px rgba(255,255,255,.7)" }} /> {CONTINENTS[k].fa}
          </button>
        ))}
      </div>

      <div className="card3d relative overflow-hidden p-3 sm:p-6">
        <div className="mx-auto w-full max-w-[560px]">
          <Suspense fallback={<div className="aspect-square w-full animate-pulse rounded-full" style={{ background: "radial-gradient(circle at 35% 30%, var(--ocean-a), var(--ocean-b))" }} />}>
            <Globe selected={sel} onSelect={onSelect} highlightContinent={cont} lockTouch autoRotate />
          </Suspense>
        </div>
        <p className="muted mt-3 text-center text-xs">کشورهای بسیار کوچک روی نقشه دیده نمی‌شوند؛ از جستجو یا فهرست استفاده کنید.</p>
      </div>

      {c && (
        <button
          key={c.isoCode}
          onClick={() => { setNavList([c.isoCode, ...COUNTRIES.filter((x) => x.continent === c.continent && x.isoCode !== c.isoCode).map((x) => x.isoCode)]); go(`country/${c.isoCode}`); }}
          className="card3d press anim-pop flex w-full items-center gap-4 p-4 text-start"
        >
          <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-24 flex-none" />
          <div className="min-w-0 flex-1">
            <div className="truncate text-2xl font-black">{c.nameFa}</div>
            <div className="en muted truncate">{c.nameEn}</div>
            <div className="mt-1 truncate text-sm font-bold">📍 {c.capitalFa} · <span className="en">{c.capitalEn}</span></div>
            <div className="muted text-xs">👥 {popFa(c.population)} · 💰 <span className="en">{c.currencyCode}</span></div>
          </div>
          <ChevronLeft size={26} />
        </button>
      )}
    </div>
  );
}

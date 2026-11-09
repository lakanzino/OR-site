import { lazy, Suspense, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { BookOpen, Brain, Globe2, Layers, Search, X, ChevronLeft, Sparkles } from "lucide-react";
import { COUNTRIES, COUNTRY_MAP, normalize } from "@/data/countries";
import { CONTINENTS, CONTINENT_ORDER, type Continent } from "@/data/dictionaries";
import { CountryCard } from "@/components/CountryCard";
import { Flag } from "@/components/Flag";
import { Ring } from "@/components/ui";
import { go, setNavList } from "@/lib/router";
import { useAppState } from "@/lib/store";
import { toFa } from "@/lib/util";

const Globe = lazy(() => import("@/components/Globe"));
const SORTED = [...COUNTRIES].sort((a, b) => a.nameFa.localeCompare(b.nameFa, "fa"));

// Filters survive navigation to detail and back
const saved: { q: string; cont: Continent | null } = { q: "", cont: null };

export default function Home() {
  const app = useAppState();
  const [q, setQ] = useState(saved.q);
  const [cont, setCont] = useState<Continent | null>(saved.cont);
  const [limit, setLimit] = useState(48);
  const [picked, setPicked] = useState<string | null>(null);
  const heroRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const gridRef = useRef<HTMLDivElement>(null);
  const sentinel = useRef<HTMLDivElement>(null);

  useEffect(() => {
    saved.q = q;
    saved.cont = cont;
    setLimit(48);
  }, [q, cont]);

  const list = useMemo(() => {
    const nq = normalize(q);
    return SORTED.filter((c) => (!cont || c.continent === cont) && (!nq || c.searchKey.includes(nq)));
  }, [q, cont]);

  useEffect(() => setNavList(list.map((c) => c.isoCode)), [list]);

  // progressive rendering of the grid
  useEffect(() => {
    const el = sentinel.current;
    if (!el) return;
    const io = new IntersectionObserver((e) => e[0].isIntersecting && setLimit((l) => l + 48), { rootMargin: "600px" });
    io.observe(el);
    return () => io.disconnect();
  }, [list.length, limit]);

  // parallax on hero globe
  useEffect(() => {
    let raf = 0;
    const on = () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const y = Math.min(window.scrollY, 700);
        if (heroRef.current) {
          heroRef.current.style.transform = `translateY(${y * 0.18}px) scale(${1 - y / 2600}) rotateX(${y / 40}deg)`;
          heroRef.current.style.opacity = String(1 - y / 900);
        }
      });
    };
    window.addEventListener("scroll", on, { passive: true });
    return () => {
      window.removeEventListener("scroll", on);
      cancelAnimationFrame(raf);
    };
  }, []);

  const open = useCallback((iso: string) => go(`country/${iso}`), []);
  const onPick = useCallback((iso: string | null) => setPicked(iso), []);
  const favSet = useMemo(() => new Set(app.favorites), [app.favorites]);
  const learnedSet = useMemo(() => new Set(app.learned), [app.learned]);
  const pct = app.learned.length / COUNTRIES.length;
  const pk = picked ? COUNTRY_MAP[picked] : null;

  const scrollToGrid = () => gridRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });

  const quick = [
    { icon: Globe2, fa: "همه کشورها", en: "All Countries", grad: "linear-gradient(135deg,#FFC400,#FF9A00)", dark: true, act: () => { setQ(""); setCont(null); scrollToGrid(); } },
    { icon: Layers, fa: "بر اساس قاره", en: "By Continent", grad: "linear-gradient(135deg,#3B66F5,#1B2F9A)", act: () => { document.getElementById("chips")?.scrollIntoView({ behavior: "smooth", block: "center" }); } },
    { icon: Brain, fa: "آزمون", en: "Quiz", grad: "linear-gradient(135deg,#EF2B3A,#A10F1B)", act: () => go("quiz") },
    { icon: BookOpen, fa: "فلش‌کارت", en: "Flashcards", grad: "linear-gradient(135deg,#FF7A1A,#E5202E)", act: () => go("flash") },
    { icon: Search, fa: "جستجو", en: "Search", grad: "linear-gradient(135deg,#2FB8EA,#2456C8)", act: () => { scrollToGrid(); setTimeout(() => searchRef.current?.focus(), 450); } },
  ];

  return (
    <div className="space-y-6">
      {/* ── Hero: globe + welcome ── */}
      <section className="card3d anim-up relative overflow-hidden p-5 sm:p-8" style={{ perspective: 1200 }}>
        <div className="pointer-events-none absolute -end-16 -top-16 h-56 w-56 rounded-full opacity-40 blur-3xl" style={{ background: "var(--yellow)" }} />
        <div className="pointer-events-none absolute -bottom-20 -start-10 h-56 w-56 rounded-full opacity-30 blur-3xl" style={{ background: "var(--red)" }} />
        <div className="relative grid items-center gap-6 md:grid-cols-2">
          <div className="order-2 md:order-1">
            <div className="mb-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-extrabold" style={{ background: "color-mix(in srgb, var(--yellow) 22%, transparent)", color: "var(--accent-text)" }}>
              <Sparkles size={14} /> ۱۹۵ کشور · ۶ قاره · کاملاً آفلاین
            </div>
            <h2 className="text-3xl font-black leading-snug sm:text-4xl">
              جهان را <span className="title-grad">بشناس</span>
            </h2>
            <div className="en muted mt-1 text-base">Explore the world — countries, capitals & currencies</div>
            <p className="muted mt-3 leading-7">کره را بچرخان، روی کشورها بزن و پایتخت‌ها، پرچم‌ها و واحد پول‌ها را با آزمون و فلش‌کارت یاد بگیر.</p>

            <div className="mt-5 flex items-center gap-4">
              <Ring value={pct} label={<span className="text-lg">{toFa(Math.round(pct * 100))}٪</span>} />
              <div>
                <div className="font-extrabold">پیشرفت یادگیری شما</div>
                <div className="muted text-sm">
                  {toFa(app.learned.length)} از {toFa(COUNTRIES.length)} کشور یاد گرفته‌اید
                </div>
                <div className="mt-1 text-sm font-bold" style={{ color: "var(--accent-text)" }}>⭐ {toFa(app.stats.xp)} امتیاز</div>
              </div>
            </div>
          </div>

          <div className="order-1 md:order-2">
            <div ref={heroRef} className="relative mx-auto w-full max-w-[380px] will-change-transform" style={{ transformStyle: "preserve-3d" }}>
              <Suspense fallback={<div className="aspect-square w-full animate-pulse rounded-full" style={{ background: "radial-gradient(circle at 35% 30%, var(--ocean-a), var(--ocean-b))" }} />}>
                <Globe selected={picked} onSelect={onPick} highlightContinent={cont} />
              </Suspense>
              {pk && (
                <button
                  key={pk.isoCode}
                  onClick={() => open(pk.isoCode)}
                  className="card3d anim-pop absolute inset-x-2 -bottom-3 flex items-center gap-3 p-2.5 text-start"
                >
                  <Flag iso={pk.isoCode} emoji={pk.flagEmoji} className="w-14 flex-none" />
                  <div className="min-w-0 flex-1">
                    <div className="truncate font-extrabold">{pk.nameFa}</div>
                    <div className="en muted truncate text-xs">{pk.nameEn} · {pk.capitalEn}</div>
                  </div>
                  <ChevronLeft size={20} />
                </button>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* ── Quick actions ── */}
      <section className="grid grid-cols-2 gap-3 sm:grid-cols-5" aria-label="دسترسی سریع">
        {quick.map((a, i) => (
          <button
            key={a.en}
            onClick={a.act}
            className={`card3d press anim-up flex min-h-[112px] flex-col items-start justify-between overflow-hidden p-3.5 text-start ${i === 0 ? "col-span-2 sm:col-span-1" : ""}`}
            style={{ animationDelay: `${i * 60}ms`, background: a.grad, color: a.dark ? "#0b1f4b" : "#fff", borderColor: "transparent" }}
          >
            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/25 shadow-inner">
              <a.icon size={24} />
            </span>
            <span>
              <span className="block text-lg font-black leading-tight">{a.fa}</span>
              <span className="en block text-xs opacity-85">{a.en}</span>
            </span>
          </button>
        ))}
      </section>

      {/* ── Search + chips ── */}
      <section ref={gridRef} className="sticky top-[68px] z-20 -mx-4 space-y-3 px-4 py-2 backdrop-blur-xl" style={{ background: "color-mix(in srgb, var(--bg) 82%, transparent)" }}>
        <div className="relative">
          <Search className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 muted" size={20} />
          <input
            ref={searchRef}
            className="input"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="جستجو: کشور، پایتخت، ارز… / Search"
            aria-label="جستجو"
            enterKeyHint="search"
          />
          {q && (
            <button className="absolute end-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full" style={{ background: "var(--border)" }} onClick={() => setQ("")} aria-label="پاک کردن">
              <X size={18} />
            </button>
          )}
        </div>
        <div id="chips" className="hide-scroll -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
          <button className="chip dark-text" style={{ ["--chip" as string]: "var(--yellow)" }} aria-pressed={cont === null} onClick={() => setCont(null)}>
            🌍 همه <span className="en text-xs opacity-80">All</span>
          </button>
          {CONTINENT_ORDER.map((k) => (
            <button
              key={k}
              className={`chip ${k === "AF" ? "dark-text" : ""}`}
              style={{ ["--chip" as string]: CONTINENTS[k].color }}
              aria-pressed={cont === k}
              onClick={() => setCont(cont === k ? null : k)}
            >
              <span aria-hidden>{CONTINENTS[k].emoji}</span> {CONTINENTS[k].fa}
              <span className="en text-xs opacity-80">{CONTINENTS[k].en}</span>
            </button>
          ))}
        </div>
      </section>

      {/* ── Grid ── */}
      <section>
        <div className="mb-3 flex items-baseline justify-between px-1">
          <h2 className="text-xl font-black">{cont ? CONTINENTS[cont].fa : "همه کشورها"} <span className="en muted text-sm">{cont ? CONTINENTS[cont].en : "All Countries"}</span></h2>
          <span className="muted text-sm font-bold">{toFa(list.length)} کشور</span>
        </div>
        {list.length === 0 ? (
          <div className="card3d anim-pop p-10 text-center">
            <div className="text-5xl">🔎</div>
            <div className="mt-3 text-lg font-extrabold">کشوری پیدا نشد</div>
            <div className="en muted">No countries found</div>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            {list.slice(0, limit).map((c, i) => (
              <CountryCard key={c.isoCode} c={c} fav={favSet.has(c.isoCode)} learned={learnedSet.has(c.isoCode)} onOpen={open} index={i} />
            ))}
          </div>
        )}
        {limit < list.length && <div ref={sentinel} className="h-10" />}
      </section>
    </div>
  );
}

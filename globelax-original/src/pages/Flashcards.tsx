import { useCallback, useEffect, useMemo, useState } from "react";
import { Check, RotateCcw, Shuffle, X, ExternalLink, Play } from "lucide-react";
import { COUNTRIES, COUNTRY_MAP, type Country } from "@/data/countries";
import { CONTINENTS, CONTINENT_ORDER, type Continent } from "@/data/dictionaries";
import { Flag } from "@/components/Flag";
import { go, type Route } from "@/lib/router";
import { actions, useAppState } from "@/lib/store";
import { areaFa, popFa, shuffle, toFa } from "@/lib/util";

type Mode = "country" | "capital" | "flag" | "currency";
type Deck = "all" | "todo" | "fav" | "wrong";

const MODES: { id: Mode; fa: string; en: string; emoji: string }[] = [
  { id: "country", fa: "کشور ← اطلاعات", en: "Country → Info", emoji: "🌍" },
  { id: "flag", fa: "پرچم ← کشور", en: "Flag → Country", emoji: "🚩" },
  { id: "capital", fa: "پایتخت ← کشور", en: "Capital → Country", emoji: "🏛️" },
  { id: "currency", fa: "واحد پول ← کشور", en: "Currency → Country", emoji: "💰" },
];

function Front({ c, mode }: { c: Country; mode: Mode }) {
  if (mode === "country")
    return (
      <div className="flex h-full flex-col items-center justify-center gap-5 p-6 text-center">
        <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-full max-w-[300px] !rounded-2xl shadow-2xl" />
        <div>
          <div className="text-4xl font-black">{c.nameFa}</div>
          <div className="en text-2xl" style={{ color: "var(--accent-text)" }}>{c.nameEn}</div>
        </div>
      </div>
    );
  if (mode === "flag")
    return (
      <div className="flex h-full flex-col items-center justify-center gap-5 p-6 text-center">
        <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-full max-w-[320px] !rounded-2xl shadow-2xl" />
        <div className="text-xl font-black">این پرچم مال کدام کشور است؟</div>
        <div className="en muted">Which country does this flag belong to?</div>
      </div>
    );
  if (mode === "capital")
    return (
      <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
        <div className="text-6xl">🏛️</div>
        <div className="muted font-bold">پایتخت کدام کشور است؟</div>
        <div className="text-4xl font-black">{c.capitalFa}</div>
        <div className="en text-2xl" style={{ color: "var(--accent-text)" }}>{c.capitalEn}</div>
      </div>
    );
  return (
    <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
      <div className="text-6xl">💰</div>
      <div className="muted font-bold">واحد پول کدام کشور است؟</div>
      <div className="text-3xl font-black">{c.currencyNameFa}</div>
      <div className="en text-xl">{c.currencyNameEn}</div>
      <div className="en mt-1 rounded-2xl px-5 py-1.5 text-3xl font-black" style={{ background: "var(--yellow)", color: "#0b1f4b", boxShadow: "0 4px 0 var(--yellow-deep)" }}>{c.currencyCode}</div>
    </div>
  );
}

function Back({ c }: { c: Country }) {
  const row = (k: string, v: string, en?: string) => (
    <div className="flex items-baseline justify-between gap-3 border-b py-1.5 text-sm" style={{ borderColor: "rgba(255,255,255,.14)" }}>
      <span className="font-bold opacity-70">{k}</span>
      <span className="text-end font-extrabold">{v}{en && <span className="en block text-xs font-bold opacity-75">{en}</span>}</span>
    </div>
  );
  return (
    <div className="flex h-full flex-col gap-2 overflow-auto p-5">
      <div className="flex items-center gap-3">
        <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-20 flex-none" />
        <div className="min-w-0">
          <div className="truncate text-2xl font-black">{c.nameFa}</div>
          <div className="en truncate text-lg" style={{ color: "var(--yellow)" }}>{c.nameEn}</div>
        </div>
      </div>
      <div className="mt-1">
        {row("پایتخت", c.capitalFa, c.capitalEn)}
        {row("واحد پول", `${c.currencyNameFa} (${c.currencyCode})`, c.currencyNameEn)}
        {row("جمعیت", popFa(c.population))}
        {row("مساحت", `${areaFa(c.areaKm2)} km²`)}
        {row("زبان", c.languagesFa, c.languagesEn)}
        {row("قاره", c.continentFa, c.continentEn)}
      </div>
      <button
        className="btn btn-ghost mt-auto !min-h-11 self-center text-sm text-white"
        onClick={(e) => { e.stopPropagation(); go(`country/${c.isoCode}`); }}
      >
        <ExternalLink size={16} /> صفحهٔ کامل
      </button>
    </div>
  );
}

export default function Flashcards({ route }: { route: Route }) {
  const app = useAppState();
  const [phase, setPhase] = useState<"setup" | "play" | "done">("setup");
  const [mode, setMode] = useState<Mode>("country");
  const [deck, setDeck] = useState<Deck>((route.params.get("deck") as Deck) || "all");
  const [cont, setCont] = useState<Continent | null>((route.params.get("cont") as Continent) || null);
  const [shuf, setShuf] = useState(true);
  const [queue, setQueue] = useState<string[]>([]);
  const [pos, setPos] = useState(0);
  const [flipped, setFlipped] = useState(false);
  const [stat, setStat] = useState({ known: 0, again: 0 });

  const pool = useMemo(
    () =>
      COUNTRIES.filter((c) => {
        if (cont && c.continent !== cont) return false;
        if (deck === "todo") return !app.learned.includes(c.isoCode);
        if (deck === "fav") return app.favorites.includes(c.isoCode);
        if (deck === "wrong") return app.wrong.includes(c.isoCode);
        return true;
      }),
    [deck, cont, app.learned, app.favorites, app.wrong],
  );

  const start = () => {
    const ids = pool.map((c) => c.isoCode);
    setQueue(shuf ? shuffle(ids) : ids);
    setPos(0);
    setFlipped(false);
    setStat({ known: 0, again: 0 });
    setPhase("play");
  };

  const next = useCallback(
    (known: boolean) => {
      const iso = queue[pos];
      let q = queue;
      if (known) {
        actions.setLearned(iso, true);
        actions.removeWrong([iso]);
        setStat((s) => ({ ...s, known: s.known + 1 }));
      } else {
        setStat((s) => ({ ...s, again: s.again + 1 }));
        // re-queue the card at the end (max 2 repeats)
        if (q.slice(pos + 1).filter((x) => x === iso).length < 2) q = [...q, iso];
        setQueue(q);
      }
      setFlipped(false);
      if (pos + 1 >= q.length) setPhase("done");
      else setPos(pos + 1);
    },
    [queue, pos],
  );

  useEffect(() => {
    if (phase !== "play") return;
    const k = (e: KeyboardEvent) => {
      if (e.code === "Space" || e.key === "Enter") { e.preventDefault(); setFlipped((f) => !f); }
      if (e.key === "ArrowRight") next(true);
      if (e.key === "ArrowLeft") next(false);
    };
    window.addEventListener("keydown", k);
    return () => window.removeEventListener("keydown", k);
  }, [phase, next]);

  /* ───────── Setup ───────── */
  if (phase === "setup") {
    const decks: { id: Deck; fa: string; n: number }[] = [
      { id: "all", fa: "همه", n: COUNTRIES.length },
      { id: "todo", fa: "یادنگرفته‌ها", n: COUNTRIES.length - app.learned.length },
      { id: "fav", fa: "علاقه‌مندی‌ها", n: app.favorites.length },
      { id: "wrong", fa: "اشتباهات آزمون", n: app.wrong.length },
    ];
    return (
      <div className="space-y-5">
        <div className="anim-up">
          <h1 className="text-3xl font-black">فلش‌کارت <span className="en muted text-lg">Flashcards</span></h1>
          <p className="muted mt-1">کارت را بزن تا با چرخش سه‌بعدی برگردد.</p>
        </div>

        <section className="card3d anim-up p-4">
          <h2 className="mb-3 font-black">۱. حالت کارت <span className="en muted text-xs">Mode</span></h2>
          <div className="grid grid-cols-2 gap-2">
            {MODES.map((m) => (
              <button key={m.id} className="chip !justify-start !rounded-2xl !py-3" style={{ ["--chip" as string]: "var(--red)" }} aria-pressed={mode === m.id} onClick={() => setMode(m.id)}>
                <span className="text-xl">{m.emoji}</span>
                <span className="text-start leading-tight"><span className="block text-sm">{m.fa}</span><span className="en block text-[10px] opacity-80">{m.en}</span></span>
              </button>
            ))}
          </div>
        </section>

        <section className="card3d anim-up p-4">
          <h2 className="mb-3 font-black">۲. مجموعه کارت‌ها <span className="en muted text-xs">Deck</span></h2>
          <div className="mb-3 flex flex-wrap gap-2">
            {decks.map((d) => (
              <button key={d.id} className="chip dark-text" style={{ ["--chip" as string]: "var(--yellow)" }} aria-pressed={deck === d.id} onClick={() => setDeck(d.id)}>
                {d.fa} <span className="text-xs opacity-75">({toFa(d.n)})</span>
              </button>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            {CONTINENT_ORDER.map((k) => (
              <button key={k} className={`chip ${k === "AF" ? "dark-text" : ""}`} style={{ ["--chip" as string]: CONTINENTS[k].color }} aria-pressed={cont === k} onClick={() => setCont(cont === k ? null : k)}>
                {CONTINENTS[k].emoji} {CONTINENTS[k].fa}
              </button>
            ))}
          </div>
          <label className="mt-4 flex cursor-pointer items-center gap-3 font-bold">
            <input type="checkbox" className="h-5 w-5 accent-[var(--yellow)]" checked={shuf} onChange={(e) => setShuf(e.target.checked)} />
            <Shuffle size={18} /> ترتیب تصادفی
          </label>
        </section>

        <button className="btn btn-yellow w-full !min-h-14 text-lg" disabled={pool.length === 0} onClick={start}>
          <Play size={22} fill="currentColor" /> شروع ({toFa(pool.length)} کارت)
        </button>
        {pool.length === 0 && <p className="muted text-center text-sm">در این مجموعه کارتی وجود ندارد؛ فیلتر دیگری انتخاب کنید.</p>}
      </div>
    );
  }

  /* ───────── Done ───────── */
  if (phase === "done") {
    return (
      <div className="card3d anim-pop mx-auto mt-6 max-w-md p-8 text-center">
        <div className="text-6xl">🎉</div>
        <h2 className="mt-3 text-3xl font-black">آفرین!</h2>
        <p className="muted mt-1">مجموعه تمام شد.</p>
        <div className="my-6 grid grid-cols-2 gap-3">
          <div className="rounded-2xl p-4" style={{ background: "var(--yellow)", color: "#0b1f4b" }}><div className="text-3xl font-black">{toFa(stat.known)}</div><div className="text-sm font-bold">بلدم ✓</div></div>
          <div className="rounded-2xl p-4 text-white" style={{ background: "var(--red)" }}><div className="text-3xl font-black">{toFa(stat.again)}</div><div className="text-sm font-bold">تکرار ↻</div></div>
        </div>
        <div className="flex gap-3">
          <button className="btn btn-yellow flex-1" onClick={start}><RotateCcw size={18} /> دوباره</button>
          <button className="btn btn-ghost flex-1" onClick={() => setPhase("setup")}>تنظیمات</button>
        </div>
      </div>
    );
  }

  /* ───────── Play ───────── */
  const c = COUNTRY_MAP[queue[pos]];
  const progress = (pos / Math.max(1, queue.length)) * 100;
  return (
    <div className="mx-auto max-w-md space-y-4">
      <div className="flex items-center gap-3">
        <button className="btn btn-ghost !min-h-11 !w-11 !px-0" onClick={() => setPhase("setup")} aria-label="خروج"><X size={20} /></button>
        <div className="h-3 flex-1 overflow-hidden rounded-full" style={{ background: "var(--border)" }}>
          <div className="h-full rounded-full transition-all duration-500" style={{ width: `${progress}%`, background: "linear-gradient(90deg,var(--yellow),var(--red))" }} />
        </div>
        <span className="text-sm font-black">{toFa(pos + 1)}/{toFa(queue.length)}</span>
      </div>

      <div key={c.isoCode} className="flip-scene anim-slide">
        <div
          className="relative h-[min(62vh,500px)] min-h-[380px] w-full cursor-pointer"
          onClick={() => setFlipped((f) => !f)}
          role="button"
          tabIndex={0}
          aria-label="چرخاندن کارت"
          onKeyDown={(e) => e.key === "Enter" && setFlipped((f) => !f)}
        >
          <div className={`flip-inner ${flipped ? "flipped" : ""}`}>
            <div className="face card3d" style={{ background: "linear-gradient(155deg, var(--surface-hi), var(--surface))" }}>
              <Front c={c} mode={mode} />
              <div className="absolute inset-x-0 bottom-3 text-center text-xs font-bold muted">برای دیدن پاسخ بزنید ↻</div>
            </div>
            <div className="face back card3d text-white" style={{ background: "linear-gradient(155deg, #1c3380, #071544)", borderColor: "transparent" }}>
              <Back c={c} />
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <button className="btn btn-red !min-h-14" onClick={() => next(false)}><RotateCcw size={20} /> دوباره ببینم</button>
        <button className="btn btn-yellow !min-h-14" onClick={() => next(true)}><Check size={22} strokeWidth={3} /> بلدم</button>
      </div>
      <p className="muted text-center text-xs">میان‌بر: Space = چرخش · ← = دوباره · → = بلدم</p>
    </div>
  );
}

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Check, Flame, Play, RotateCcw, Timer, X } from "lucide-react";
import { COUNTRIES, COUNTRY_MAP, type Country } from "@/data/countries";
import { CONTINENTS, CONTINENT_ORDER, type Continent } from "@/data/dictionaries";
import { Flag } from "@/components/Flag";
import { Ring } from "@/components/ui";
import { go, type Route } from "@/lib/router";
import { actions, useAppState } from "@/lib/store";
import { shuffle, toFa } from "@/lib/util";

type QType = "capital" | "currency" | "flag" | "mixed";
type Kind = "capital" | "currency" | "flag" | "capitalRev";

interface Opt { key: string; fa: string; en?: string }
interface Q { kind: Kind; country: Country; options: Opt[]; answer: number }

const TYPES: { id: QType; fa: string; en: string; emoji: string; color: string }[] = [
  { id: "capital", fa: "پایتخت‌ها", en: "Capitals", emoji: "🏛️", color: "#E5202E" },
  { id: "currency", fa: "واحد پول", en: "Currency", emoji: "💰", color: "#E0A100" },
  { id: "flag", fa: "پرچم‌ها", en: "Flags", emoji: "🚩", color: "#3B66F5" },
  { id: "mixed", fa: "ترکیبی", en: "Mixed", emoji: "🎲", color: "#FF7A1A" },
];

const TIME = 15;

const optOf = (kind: Kind, c: Country): Opt =>
  kind === "capital" ? { key: c.capitalEn, fa: c.capitalFa, en: c.capitalEn }
  : kind === "currency" ? { key: c.currencyCode, fa: c.currencyNameFa, en: `${c.currencyNameEn} · ${c.currencyCode}` }
  : { key: c.isoCode, fa: c.nameFa, en: c.nameEn };

/** Build one multiple-choice question; distractors prefer the same continent (harder & fairer). */
function makeQ(kind: Kind, c: Country): Q {
  const want = optOf(kind, c);
  const same = shuffle(COUNTRIES.filter((x) => x.continent === c.continent && x.isoCode !== c.isoCode));
  const rest = shuffle(COUNTRIES.filter((x) => x.continent !== c.continent));
  const used = new Set([want.key]);
  const dist: Opt[] = [];
  for (const x of [...same.slice(0, 40), ...rest]) {
    const o = optOf(kind === "capitalRev" || kind === "flag" ? "flag" : kind, x);
    if (used.has(o.key)) continue;
    used.add(o.key);
    dist.push(o);
    if (dist.length === 3) break;
  }
  const opts = shuffle([{ ...want }, ...dist]);
  return { kind, country: c, options: opts, answer: opts.findIndex((o) => o.key === want.key) };
}

// For capitalRev / flag the answer options are country names
const build = (kind: Kind, c: Country) => makeQ(kind === "capitalRev" ? "capitalRev" : kind, c);

function buildSet(type: QType, cont: Continent | null, count: number, focus: Country | null): Q[] {
  if (focus) return (["capital", "currency", "flag"] as Kind[]).map((k) => build(k, focus));
  const pool = shuffle(COUNTRIES.filter((c) => !cont || c.continent === cont)).slice(0, count);
  const mixedKinds: Kind[] = ["capital", "currency", "flag", "capitalRev"];
  return pool.map((c, i) => build(type === "mixed" ? mixedKinds[i % 4] : (type as Kind), c));
}

function prompt(q: Q) {
  const c = q.country;
  switch (q.kind) {
    case "capital": return { fa: `پایتخت ${c.nameFa} کدام است؟`, en: `What is the capital of ${c.nameEn}?` };
    case "currency": return { fa: `واحد پول ${c.nameFa} چیست؟`, en: `What is the currency of ${c.nameEn}?` };
    case "flag": return { fa: "این پرچم مال کدام کشور است؟", en: "Which country does this flag belong to?" };
    default: return { fa: `${c.capitalFa} پایتخت کدام کشور است؟`, en: `${c.capitalEn} is the capital of…?` };
  }
}

export default function Quiz({ route }: { route: Route }) {
  const app = useAppState();
  const focusIso = route.params.get("focus");
  const focus = focusIso ? COUNTRY_MAP[focusIso] ?? null : null;

  const [type, setType] = useState<QType>("mixed");
  const [cont, setCont] = useState<Continent | null>(null);
  const [count, setCount] = useState(10);
  const [timer, setTimer] = useState(false);

  const [phase, setPhase] = useState<"setup" | "play" | "result">("setup");
  const [qs, setQs] = useState<Q[]>([]);
  const [i, setI] = useState(0);
  const [sel, setSel] = useState<number | null>(null);
  const [score, setScore] = useState(0);
  const [streak, setStreak] = useState(0);
  const [best, setBest] = useState(0);
  const [wrongs, setWrongs] = useState<Q[]>([]);
  const [left, setLeft] = useState(TIME);
  const deadline = useRef(0);
  const started = useRef(false);

  const begin = useCallback(
    (t: QType = type) => {
      const set = buildSet(t, cont, count, focus);
      setQs(set);
      setI(0);
      setSel(null);
      setScore(0);
      setStreak(0);
      setBest(0);
      setWrongs([]);
      setPhase("play");
      deadline.current = performance.now() + TIME * 1000;
      setLeft(TIME);
    },
    [type, cont, count, focus],
  );

  // “quiz on this country” starts immediately
  useEffect(() => {
    if (focus && !started.current) {
      started.current = true;
      begin("mixed");
    }
  }, [focus, begin]);

  const q = qs[i];
  const answered = sel !== null;

  const answer = useCallback(
    (idx: number) => {
      if (sel !== null || !q) return;
      setSel(idx);
      if (idx === q.answer) {
        setScore((s) => s + 1);
        setStreak((s) => {
          setBest((b) => Math.max(b, s + 1));
          return s + 1;
        });
      } else {
        setStreak(0);
        setWrongs((w) => [...w, q]);
      }
    },
    [sel, q],
  );

  // optional per-question countdown
  useEffect(() => {
    if (phase !== "play" || !timer || answered) return;
    deadline.current = performance.now() + TIME * 1000;
    setLeft(TIME);
    const id = setInterval(() => {
      const l = Math.max(0, (deadline.current - performance.now()) / 1000);
      setLeft(l);
      if (l <= 0) {
        clearInterval(id);
        answer(-1); // time out = wrong
      }
    }, 100);
    return () => clearInterval(id);
  }, [phase, timer, i, answered, answer]);

  const next = useCallback(() => {
    if (i + 1 >= qs.length) {
      const total = qs.length;
      const type2 = focus ? "focus" : type;
      actions.recordQuiz(type2, score, total);
      actions.addWrong(wrongs.map((w) => w.country.isoCode));
      const right = qs.filter((x) => !wrongs.includes(x)).map((x) => x.country.isoCode);
      actions.removeWrong(right.filter((r) => !wrongs.some((w) => w.country.isoCode === r)));
      setPhase("result");
    } else {
      setI(i + 1);
      setSel(null);
    }
  }, [i, qs, score, wrongs, type, focus]);

  useEffect(() => {
    if (phase !== "play") return;
    const k = (e: KeyboardEvent) => {
      if (/^[1-4]$/.test(e.key)) answer(Number(e.key) - 1);
      if ((e.key === "Enter" || e.key === " ") && answered) { e.preventDefault(); next(); }
    };
    window.addEventListener("keydown", k);
    return () => window.removeEventListener("keydown", k);
  }, [phase, answer, next, answered]);

  const stars = useMemo(() => {
    const p = qs.length ? score / qs.length : 0;
    return p >= 0.9 ? 3 : p >= 0.7 ? 2 : p >= 0.4 ? 1 : 0;
  }, [score, qs.length]);

  /* ───────── Setup ───────── */
  if (phase === "setup") {
    return (
      <div className="space-y-5">
        <div className="anim-up">
          <h1 className="text-3xl font-black">آزمون <span className="en muted text-lg">Quiz</span></h1>
          <p className="muted mt-1">دانسته‌هایت را بسنج و امتیاز بگیر.</p>
        </div>

        <section className="grid grid-cols-2 gap-3">
          {TYPES.map((t, k) => (
            <button
              key={t.id}
              onClick={() => setType(t.id)}
              aria-pressed={type === t.id}
              className="card3d press anim-up p-4 text-start"
              style={{
                animationDelay: `${k * 50}ms`,
                background: type === t.id ? `linear-gradient(145deg, ${t.color}, color-mix(in srgb, ${t.color} 55%, #000))` : undefined,
                color: type === t.id ? "#fff" : undefined,
                transform: type === t.id ? "translateY(-3px)" : undefined,
                borderColor: type === t.id ? "transparent" : undefined,
              }}
            >
              <div className="text-4xl">{t.emoji}</div>
              <div className="mt-2 text-lg font-black">{t.fa}</div>
              <div className="en text-xs opacity-80">{t.en}</div>
              <div className="mt-1 text-xs font-bold opacity-80">بهترین: {toFa(app.stats.best[t.id] ?? 0)}٪</div>
            </button>
          ))}
        </section>

        <section className="card3d anim-up space-y-4 p-4">
          <div>
            <h2 className="mb-2 font-black">قاره <span className="en muted text-xs">Continent</span></h2>
            <div className="flex flex-wrap gap-2">
              <button className="chip dark-text" style={{ ["--chip" as string]: "var(--yellow)" }} aria-pressed={cont === null} onClick={() => setCont(null)}>🌍 همه</button>
              {CONTINENT_ORDER.map((k) => (
                <button key={k} className={`chip ${k === "AF" ? "dark-text" : ""}`} style={{ ["--chip" as string]: CONTINENTS[k].color }} aria-pressed={cont === k} onClick={() => setCont(cont === k ? null : k)}>
                  {CONTINENTS[k].emoji} {CONTINENTS[k].fa}
                </button>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-2 font-black">تعداد سؤال <span className="en muted text-xs">Questions</span></h2>
            <div className="flex gap-2">
              {[10, 20, 30].map((n) => (
                <button key={n} className="chip dark-text flex-1 !justify-center" style={{ ["--chip" as string]: "var(--yellow)" }} aria-pressed={count === n} onClick={() => setCount(n)}>{toFa(n)}</button>
              ))}
            </div>
          </div>
          <label className="flex cursor-pointer items-center gap-3 font-bold">
            <input type="checkbox" className="h-5 w-5 accent-[var(--yellow)]" checked={timer} onChange={(e) => setTimer(e.target.checked)} />
            <Timer size={18} /> زمان‌سنج ({toFa(TIME)} ثانیه برای هر سؤال)
          </label>
        </section>

        <button className="btn btn-red w-full !min-h-14 text-lg" onClick={() => begin()}>
          <Play size={22} fill="currentColor" /> شروع آزمون
        </button>

        <div className="grid grid-cols-3 gap-3 text-center">
          {[
            { v: app.stats.played, l: "آزمون انجام‌شده" },
            { v: app.stats.answered ? Math.round((app.stats.correct / app.stats.answered) * 100) : 0, l: "دقت کل ٪" },
            { v: app.stats.xp, l: "امتیاز ⭐" },
          ].map((s) => (
            <div key={s.l} className="card3d p-3"><div className="text-2xl font-black" style={{ color: "var(--accent-text)" }}>{toFa(s.v)}</div><div className="muted text-xs font-bold">{s.l}</div></div>
          ))}
        </div>
      </div>
    );
  }

  /* ───────── Result ───────── */
  if (phase === "result") {
    const pct = Math.round((score / qs.length) * 100);
    return (
      <div className="mx-auto max-w-md space-y-4">
        <div className="card3d anim-pop p-6 text-center">
          <div className="text-5xl tracking-widest" aria-label={`${stars} ستاره`}>
            {[0, 1, 2].map((s) => <span key={s} style={{ filter: s < stars ? "none" : "grayscale(1) opacity(.3)", display: "inline-block", animation: s < stars ? `pop .5s ${s * 0.2}s both` : undefined }}>⭐</span>)}
          </div>
          <h2 className="mt-2 text-3xl font-black">{pct >= 90 ? "فوق‌العاده!" : pct >= 70 ? "عالی!" : pct >= 40 ? "خوب بود!" : "ادامه بده!"}</h2>
          <div className="my-5 flex justify-center">
            <Ring size={140} stroke={14} value={score / qs.length} label={<div><div className="text-3xl">{toFa(score)}/{toFa(qs.length)}</div><div className="muted text-xs">{toFa(pct)}٪</div></div>} />
          </div>
          <div className="flex justify-center gap-4 text-sm font-bold">
            <span>🔥 بیشترین پیاپی: {toFa(best)}</span>
            <span>⭐ +{toFa(score * 10 + (pct === 100 ? 50 : 0))}</span>
          </div>
        </div>

        {wrongs.length > 0 && (
          <div className="card3d p-4">
            <h3 className="mb-3 font-black">مرور اشتباه‌ها <span className="en muted text-xs">Review</span></h3>
            <ul className="space-y-2">
              {wrongs.map((w, k) => (
                <li key={k} className="flex items-center gap-3 rounded-2xl p-2" style={{ background: "var(--surface-lo)" }}>
                  <Flag iso={w.country.isoCode} emoji={w.country.flagEmoji} className="w-14 flex-none" />
                  <div className="min-w-0 flex-1">
                    <div className="truncate font-extrabold">{w.country.nameFa} <span className="en muted text-xs">{w.country.nameEn}</span></div>
                    <div className="truncate text-sm font-bold" style={{ color: "var(--accent-text)" }}>✓ {w.options[w.answer].fa} <span className="en text-xs opacity-80">{w.options[w.answer].en}</span></div>
                  </div>
                </li>
              ))}
            </ul>
            <button className="btn btn-yellow mt-4 w-full" onClick={() => go("flash?deck=wrong")}>مرور با فلش‌کارت</button>
          </div>
        )}

        <div className="flex gap-3">
          <button className="btn btn-red flex-1" onClick={() => begin()}><RotateCcw size={18} /> دوباره</button>
          <button className="btn btn-ghost flex-1" onClick={() => (focus ? go("") : setPhase("setup"))}>{focus ? "خانه" : "تنظیمات"}</button>
        </div>
      </div>
    );
  }

  /* ───────── Play ───────── */
  const p = prompt(q);
  const isFlagQ = q.kind === "flag";
  return (
    <div className="mx-auto max-w-xl space-y-4">
      <div className="flex items-center gap-3">
        <button className="btn btn-ghost !min-h-11 !w-11 !px-0" onClick={() => (focus ? go("") : setPhase("setup"))} aria-label="خروج"><X size={20} /></button>
        <div className="h-3 flex-1 overflow-hidden rounded-full" style={{ background: "var(--border)" }}>
          <div className="h-full rounded-full transition-all duration-500" style={{ width: `${((i + (answered ? 1 : 0)) / qs.length) * 100}%`, background: "linear-gradient(90deg,var(--yellow),var(--red))" }} />
        </div>
        <span className="text-sm font-black">{toFa(i + 1)}/{toFa(qs.length)}</span>
      </div>
      <div className="flex items-center justify-between text-sm font-black">
        <span className="rounded-full px-3 py-1" style={{ background: "var(--yellow)", color: "#0b1f4b" }}>✓ {toFa(score)}</span>
        {streak >= 2 && <span className="anim-pop flex items-center gap-1 rounded-full bg-[var(--red)] px-3 py-1 text-white"><Flame size={16} /> {toFa(streak)}</span>}
        {timer && (
          <Ring size={44} stroke={6} value={left / TIME} label={<span className="text-xs">{toFa(Math.ceil(left))}</span>} />
        )}
      </div>

      <div key={i} className="anim-slide space-y-4">
        <div className="card3d p-5 text-center">
          {isFlagQ ? (
            <div className="mx-auto mb-3 w-full max-w-[260px]" style={{ perspective: 700 }}>
              <Flag iso={q.country.isoCode} emoji={q.country.flagEmoji} className="w-full !rounded-2xl shadow-2xl" />
            </div>
          ) : (
            <div className="mx-auto mb-3 w-24"><Flag iso={q.country.isoCode} emoji={q.country.flagEmoji} className="w-full" /></div>
          )}
          {q.kind === "capitalRev" && <div className="mx-auto mb-1 text-4xl">🏛️</div>}
          <h2 className="text-2xl font-black leading-snug">{p.fa}</h2>
          <div className="en muted mt-1 text-sm">{p.en}</div>
        </div>

        <div className="grid gap-3" role="group" aria-label="گزینه‌ها">
          {q.options.map((o, k) => {
            const isRight = answered && k === q.answer;
            const isWrong = answered && k === sel && k !== q.answer;
            return (
              <button
                key={o.key}
                disabled={answered}
                onClick={() => answer(k)}
                className={`card3d press flex min-h-[64px] items-center gap-3 p-3 text-start disabled:cursor-default ${isWrong ? "anim-shake" : ""}`}
                style={{
                  background: isRight ? "linear-gradient(145deg,#26c77a,#0f8a52)" : isWrong ? "linear-gradient(145deg,#ff4d5a,#a10f1b)" : undefined,
                  color: isRight || isWrong ? "#fff" : undefined,
                  borderColor: isRight || isWrong ? "transparent" : undefined,
                  opacity: answered && !isRight && !isWrong ? 0.55 : 1,
                  transform: answered ? "none" : undefined,
                }}
              >
                <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl text-lg font-black" style={{ background: isRight || isWrong ? "rgba(255,255,255,.25)" : "var(--yellow)", color: isRight || isWrong ? "#fff" : "#0b1f4b" }}>
                  {isRight ? <Check size={22} strokeWidth={3.5} /> : isWrong ? <X size={22} strokeWidth={3.5} /> : toFa(k + 1)}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-lg font-black">{o.fa}</span>
                  {o.en && <span className="en block truncate text-xs opacity-75">{o.en}</span>}
                </span>
              </button>
            );
          })}
        </div>

        {answered && (
          <div className="anim-up space-y-3">
            <div className="card3d p-4 text-center font-bold" style={{ borderColor: sel === q.answer ? "#26c77a" : "var(--red)" }}>
              {sel === q.answer ? "🎉 درست گفتی!" : sel === -1 ? "⏰ زمان تمام شد." : "❌ نادرست"}
              <div className="muted mt-1 text-sm">پاسخ درست: <b style={{ color: "var(--accent-text)" }}>{q.options[q.answer].fa}</b> <span className="en">({q.options[q.answer].en})</span></div>
            </div>
            <button className="btn btn-yellow w-full !min-h-14 text-lg" onClick={next} autoFocus>
              {i + 1 >= qs.length ? "مشاهدهٔ نتیجه" : "سؤال بعدی"}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

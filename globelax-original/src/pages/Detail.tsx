import { lazy, Suspense, useRef, useState } from "react";
import { ArrowRight, Check, ChevronLeft, ChevronRight, Coins, Globe2, Heart, Landmark, Languages, Ruler, Share2, Users, Brain, Lightbulb, MapPin } from "lucide-react";
import { COUNTRIES, COUNTRY_MAP } from "@/data/countries";
import { CONTINENTS } from "@/data/dictionaries";
import { Flag } from "@/components/Flag";
import { useToast } from "@/components/ui";
import { actions, useAppState } from "@/lib/store";
import { back, getNavList, go } from "@/lib/router";
import { areaEn, areaFa, computedFacts, density, popEn, popFa, shareText, toFa } from "@/lib/util";

const MiniMap = lazy(() => import("@/components/MiniMap"));
const ALL = [...COUNTRIES].sort((a, b) => a.nameFa.localeCompare(b.nameFa, "fa")).map((c) => c.isoCode);

function InfoCard({ icon: Icon, color, fa, en, children, delay = 0, wide = false }: { icon: typeof Landmark; color: string; fa: string; en: string; children: React.ReactNode; delay?: number; wide?: boolean }) {
  return (
    <div className={`card3d anim-up p-4 ${wide ? "col-span-2" : ""}`} style={{ animationDelay: `${delay}ms` }}>
      <div className="mb-2 flex items-center gap-2">
        <span className="flex h-9 w-9 items-center justify-center rounded-xl text-white shadow-md" style={{ background: color }}>
          <Icon size={19} />
        </span>
        <div className="leading-tight">
          <div className="text-sm font-black">{fa}</div>
          <div className="en muted text-[11px]">{en}</div>
        </div>
      </div>
      {children}
    </div>
  );
}

export default function Detail({ iso }: { iso: string }) {
  const c = COUNTRY_MAP[iso.toUpperCase()];
  const app = useAppState();
  const toast = useToast();
  const [flipped, setFlipped] = useState(false);
  const flagRef = useRef<HTMLDivElement>(null);
  const touch = useRef<number | null>(null);

  if (!c) {
    return (
      <div className="card3d p-8 text-center">
        <div className="text-5xl">🧭</div>
        <p className="my-3 font-bold">کشور پیدا نشد</p>
        <button className="btn btn-yellow" onClick={() => go("")}>بازگشت به خانه</button>
      </div>
    );
  }

  const nav = getNavList().length ? getNavList() : ALL;
  const idx = nav.indexOf(c.isoCode);
  const step = (d: number) => {
    const list = idx === -1 ? ALL : nav;
    const base = idx === -1 ? ALL.indexOf(c.isoCode) : idx;
    const next = list[(base + d + list.length) % list.length];
    setFlipped(false);
    go(`country/${next}`, true);
  };

  const fav = app.favorites.includes(c.isoCode);
  const learned = app.learned.includes(c.isoCode);
  const col = CONTINENTS[c.continent].color;

  const share = async () => {
    const text = `${c.flagEmoji} ${c.nameFa} (${c.nameEn})\nپایتخت: ${c.capitalFa} (${c.capitalEn})\nواحد پول: ${c.currencyNameFa} – ${c.currencyCode}\nجمعیت: ${popFa(c.population)}\nمساحت: ${areaFa(c.areaKm2)} کیلومتر مربع\n— Globe.Lax`;
    const r = await shareText(text, `${c.nameFa} – Globe.Lax`);
    if (r === "copied") toast("متن کپی شد ✓");
  };

  const tilt = (e: React.PointerEvent) => {
    const el = flagRef.current;
    if (!el || e.pointerType === "touch") return;
    const r = el.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - 0.5;
    const y = (e.clientY - r.top) / r.height - 0.5;
    el.style.transform = `rotateY(${x * 18}deg) rotateX(${-y * 14}deg)`;
  };
  const untilt = () => flagRef.current && (flagRef.current.style.transform = "");

  const facts = computedFacts(c);
  const dens = density(c);

  return (
    <div
      className="pb-32"
      onTouchStart={(e) => (touch.current = e.touches[0].clientX)}
      onTouchEnd={(e) => {
        if (touch.current == null) return;
        const dx = e.changedTouches[0].clientX - touch.current;
        touch.current = null;
        if (Math.abs(dx) > 90) step(dx < 0 ? 1 : -1); // RTL: swipe left = next
      }}
    >
      <div key={c.isoCode} className="anim-slide">
        {/* Hero */}
        <section className="card3d relative overflow-hidden px-5 pb-6 pt-4" style={{ background: `linear-gradient(160deg, color-mix(in srgb, ${col} 55%, var(--surface-hi)), var(--surface) 70%)` }}>
          <div className="mb-4 flex items-center justify-between">
            <button className="btn btn-ghost !min-h-12 !w-12 !px-0" onClick={back} aria-label="بازگشت"><ArrowRight size={22} /></button>
            <span className="rounded-full px-3 py-1.5 text-sm font-extrabold text-white shadow" style={{ background: col }}>
              {CONTINENTS[c.continent].emoji} {c.continentFa} · <span className="en">{c.continentEn}</span>
            </span>
          </div>
          <div className="flex justify-center" style={{ perspective: 900 }} onPointerMove={tilt} onPointerLeave={untilt}>
            <div ref={flagRef} className="w-full max-w-[340px] transition-transform duration-300 ease-out" style={{ transformStyle: "preserve-3d" }}>
              <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-full !rounded-2xl shadow-2xl" alt={c.nameEn} />
              <div className="mx-auto mt-1 h-4 w-4/5 rounded-full opacity-40 blur-md" style={{ background: "#000" }} />
            </div>
          </div>
          <div className="mt-4 text-center">
            <h1 className="text-4xl font-black leading-tight">{c.nameFa}</h1>
            <div className="en mt-1 text-2xl" style={{ color: "var(--accent-text)" }}>{c.nameEn}</div>
            <div className="mt-3 flex flex-wrap justify-center gap-2 text-xs font-extrabold">
              <span className="rounded-full bg-black/25 px-3 py-1 text-white">رتبهٔ جمعیت #{toFa(c.popRank)}</span>
              <span className="rounded-full bg-black/25 px-3 py-1 text-white">رتبهٔ مساحت #{toFa(c.areaRank)}</span>
              <span className="en rounded-full bg-black/25 px-3 py-1 text-white">{c.isoCode}</span>
            </div>
          </div>
        </section>

        {/* Info */}
        <section className="mt-4 grid grid-cols-2 gap-3">
          <InfoCard icon={Landmark} color="var(--red)" fa="پایتخت" en="Capital" wide delay={40}>
            <div className="text-2xl font-black">{c.capitalFa}</div>
            <div className="en muted text-lg">{c.capitalEn}</div>
            {c.noteFa && <p className="mt-2 rounded-xl bg-[var(--yellow)]/15 p-2.5 text-xs font-bold leading-6" style={{ color: "var(--text)" }}>ℹ️ {c.noteFa}</p>}
          </InfoCard>
          <InfoCard icon={Coins} color="#E0A100" fa="واحد پول" en="Currency" wide delay={80}>
            <div className="flex items-center justify-between gap-3">
              <div>
                <div className="text-xl font-black">{c.currencyNameFa}</div>
                <div className="en muted">{c.currencyNameEn}</div>
              </div>
              <div className="en rounded-2xl px-4 py-2 text-2xl font-black" style={{ background: "var(--yellow)", color: "#0b1f4b", boxShadow: "0 4px 0 var(--yellow-deep)" }}>{c.currencyCode}</div>
            </div>
          </InfoCard>
          <InfoCard icon={Users} color="#3B66F5" fa="جمعیت" en="Population" delay={120}>
            <div className="text-xl font-black">{popFa(c.population)}</div>
            <div className="en muted text-sm">{popEn(c.population)}</div>
          </InfoCard>
          <InfoCard icon={Ruler} color="#FF7A1A" fa="مساحت" en="Area" delay={160}>
            <div className="text-xl font-black">{areaFa(c.areaKm2)}</div>
            <div className="muted text-xs">کیلومتر مربع</div>
            <div className="en muted text-sm">{areaEn(c.areaKm2)} km²</div>
          </InfoCard>
          <InfoCard icon={Languages} color="#2FB8EA" fa="زبان‌ها" en="Languages" delay={200}>
            <div className="font-black leading-7">{c.languagesFa}</div>
            <div className="en muted text-sm">{c.languagesEn}</div>
          </InfoCard>
          <InfoCard icon={Globe2} color={col} fa="قاره" en="Continent" delay={240}>
            <div className="text-xl font-black">{c.continentFa}</div>
            <div className="en muted">{c.continentEn}</div>
          </InfoCard>
        </section>

        {/* Flip fact card */}
        <section className="mt-4 flip-scene">
          <button className="block w-full text-start" onClick={() => setFlipped((f) => !f)} aria-label="چرخاندن کارت اطلاعات بیشتر" aria-pressed={flipped}>
            <div className={`flip-inner grid ${flipped ? "flipped" : ""}`}>
              <div className="face card3d !relative flex flex-col justify-center p-5" style={{ gridArea: "1/1", background: "linear-gradient(145deg, var(--red), #7d0d17)", color: "#fff", borderColor: "transparent" }}>
                <div className="mb-2 flex items-center gap-2 font-black"><Lightbulb size={22} className="text-[var(--yellow)]" /> آیا می‌دانستید؟ <span className="en text-xs opacity-80">Did you know?</span></div>
                <p className="text-base leading-8">{c.shortFactFa ?? facts[0]}</p>
                <div className="mt-3 text-xs font-bold opacity-80">برای دیدن اطلاعات بیشتر بزنید ↻</div>
              </div>
              <div className="face back card3d !relative p-5" style={{ gridArea: "1/1", background: "linear-gradient(145deg, #172b6b, #071544)", color: "#fff", borderColor: "transparent" }}>
                <div className="mb-2 font-black text-[var(--yellow)]">📊 اطلاعات بیشتر · <span className="en text-xs">More facts</span></div>
                <ul className="space-y-1.5 text-sm leading-7">
                  {facts.map((f) => <li key={f}>• {f}</li>)}
                  <li>• تراکم جمعیت: {dens < 1 ? "کمتر از ۱" : toFa(Math.round(dens))} نفر/km²</li>
                  <li className="en" dir="ltr">• {c.nameEn}: pop. rank #{c.popRank}, area rank #{c.areaRank}</li>
                </ul>
                <div className="mt-2 text-xs opacity-70">برگشت ↻</div>
              </div>
            </div>
          </button>
        </section>

        {/* Location */}
        <section className="card3d mt-4 p-4">
          <div className="mb-2 flex items-center gap-2 font-black"><MapPin size={20} className="text-[var(--red)]" /> موقعیت روی کره <span className="en muted text-xs">Location</span></div>
          <Suspense fallback={<div className="mx-auto aspect-square w-48 animate-pulse rounded-full bg-[var(--surface-lo)]" />}>
            <MiniMap iso={c.isoCode} />
          </Suspense>
        </section>
        <p className="muted mt-4 px-2 text-center text-xs leading-6">
          جمعیت: بانک جهانی ۲۰۲۵ · مساحت کل: بانک جهانی ۲۰۲۴ (بوسنی و نیکاراگوئه: ۲۰۲۳) · واتیکان: برآورد پیشین · رتبه‌ها در میان ۱۹۵ کشور برنامه
        </p>
      </div>

      {/* Action bar */}
      <div className="fixed inset-x-0 bottom-0 z-40 flex justify-center px-3 pb-3" style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}>
        <div className="bottom-nav flex w-full max-w-xl items-center justify-between gap-1 rounded-3xl p-2">
          <button className="btn btn-ghost !min-h-12 !w-12 !px-0" onClick={() => step(-1)} aria-label="قبلی"><ChevronRight size={24} /></button>
          <button className={`btn !min-h-12 !px-3 ${fav ? "btn-red" : "btn-ghost"}`} onClick={() => { actions.toggleFavorite(c.isoCode); toast(fav ? "از علاقه‌مندی‌ها حذف شد" : "به علاقه‌مندی‌ها افزوده شد ❤️"); }} aria-pressed={fav} aria-label="علاقه‌مندی">
            <Heart size={20} fill={fav ? "currentColor" : "none"} />
          </button>
          <button className={`btn !min-h-12 !px-3 ${learned ? "btn-yellow" : "btn-ghost"}`} onClick={() => { if (!learned) actions.addXp(5); actions.toggleLearned(c.isoCode); toast(learned ? "از یادگرفته‌ها حذف شد" : "یاد گرفتم ✓ +۵ امتیاز"); }} aria-pressed={learned} aria-label="یاد گرفتم">
            <Check size={22} strokeWidth={3} />
          </button>
          <button className="btn btn-ghost !min-h-12 !px-3" onClick={share} aria-label="اشتراک‌گذاری"><Share2 size={20} /></button>
          <button className="btn btn-red !min-h-12 !px-3" onClick={() => go(`quiz?focus=${c.isoCode}`)} aria-label="آزمون این کشور"><Brain size={20} /> <span className="hidden text-sm sm:inline">آزمون</span></button>
          <button className="btn btn-ghost !min-h-12 !w-12 !px-0" onClick={() => step(1)} aria-label="بعدی"><ChevronLeft size={24} /></button>
        </div>
      </div>
    </div>
  );
}

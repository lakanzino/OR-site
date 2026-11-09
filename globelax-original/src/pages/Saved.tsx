import { useMemo, useState } from "react";
import { BookOpen, ChevronLeft } from "lucide-react";
import { COUNTRIES } from "@/data/countries";
import { CONTINENTS, CONTINENT_ORDER } from "@/data/dictionaries";
import { Flag } from "@/components/Flag";
import { Ring } from "@/components/ui";
import { go, setNavList } from "@/lib/router";
import { useAppState } from "@/lib/store";
import { toFa } from "@/lib/util";

type Tab = "fav" | "learned" | "wrong";

export default function Saved() {
  const app = useAppState();
  const [tab, setTab] = useState<Tab>("fav");

  const ids = tab === "fav" ? app.favorites : tab === "learned" ? app.learned : app.wrong;
  const list = useMemo(() => {
    const s = new Set(ids);
    return COUNTRIES.filter((c) => s.has(c.isoCode)).sort((a, b) => a.nameFa.localeCompare(b.nameFa, "fa"));
  }, [ids]);

  const tabs: { id: Tab; fa: string; n: number; emoji: string }[] = [
    { id: "fav", fa: "علاقه‌مندی", n: app.favorites.length, emoji: "❤️" },
    { id: "learned", fa: "یادگرفته", n: app.learned.length, emoji: "✅" },
    { id: "wrong", fa: "اشتباهات", n: app.wrong.length, emoji: "🔁" },
  ];
  const total = COUNTRIES.length;

  return (
    <div className="space-y-5">
      <div className="anim-up">
        <h1 className="text-3xl font-black">پیشرفت من <span className="en muted text-lg">My Progress</span></h1>
      </div>

      <section className="card3d anim-up p-5">
        <div className="mb-4 flex items-center gap-4">
          <Ring size={96} stroke={11} value={app.learned.length / total} label={<span className="text-xl">{toFa(Math.round((app.learned.length / total) * 100))}٪</span>} />
          <div>
            <div className="text-xl font-black">{toFa(app.learned.length)} از {toFa(total)} کشور</div>
            <div className="muted text-sm">⭐ {toFa(app.stats.xp)} امتیاز · {toFa(app.stats.played)} آزمون</div>
          </div>
        </div>
        <div className="space-y-3">
          {CONTINENT_ORDER.map((k) => {
            const all = COUNTRIES.filter((c) => c.continent === k);
            const done = all.filter((c) => app.learned.includes(c.isoCode)).length;
            return (
              <div key={k}>
                <div className="mb-1 flex items-center justify-between text-sm font-bold">
                  <span>{CONTINENTS[k].emoji} {CONTINENTS[k].fa}</span>
                  <div className="flex items-center gap-2">
                    <span className="muted">{toFa(done)}/{toFa(all.length)}</span>
                    <button className="flex h-8 w-8 items-center justify-center rounded-full" style={{ background: CONTINENTS[k].color, color: k === "AF" ? "#0b1f4b" : "#fff" }} onClick={() => go(`flash?cont=${k}`)} aria-label={`مطالعهٔ ${CONTINENTS[k].fa}`}><BookOpen size={15} /></button>
                  </div>
                </div>
                <div className="h-3 overflow-hidden rounded-full" style={{ background: "var(--border)" }}>
                  <div className="h-full rounded-full transition-all duration-700" style={{ width: `${(done / all.length) * 100}%`, background: CONTINENTS[k].color }} />
                </div>
              </div>
            );
          })}
        </div>
      </section>

      <div className="grid grid-cols-3 gap-2" role="tablist">
        {tabs.map((t) => (
          <button key={t.id} role="tab" aria-selected={tab === t.id} className="chip dark-text !flex-col !justify-center !gap-0 !rounded-2xl !py-2.5" style={{ ["--chip" as string]: "var(--yellow)" }} aria-pressed={tab === t.id} onClick={() => setTab(t.id)}>
            <span className="text-lg">{t.emoji} {toFa(t.n)}</span>
            <span className="text-xs">{t.fa}</span>
          </button>
        ))}
      </div>

      {list.length === 0 ? (
        <div className="card3d anim-pop p-10 text-center">
          <div className="text-5xl">{tab === "fav" ? "💛" : tab === "learned" ? "🌱" : "🎯"}</div>
          <p className="mt-3 font-extrabold">
            {tab === "fav" ? "هنوز کشوری را به علاقه‌مندی‌ها اضافه نکرده‌اید." : tab === "learned" ? "هنوز کشوری را یاد نگرفته‌اید؛ با فلش‌کارت شروع کنید!" : "هیچ اشتباهی ثبت نشده. عالی!"}
          </p>
          <button className="btn btn-yellow mt-4" onClick={() => go(tab === "wrong" ? "quiz" : tab === "learned" ? "flash" : "")}>
            {tab === "wrong" ? "شروع آزمون" : tab === "learned" ? "شروع فلش‌کارت" : "مرور کشورها"}
          </button>
        </div>
      ) : (
        <ul className="space-y-2">
          {list.map((c, i) => (
            <li key={c.isoCode} className="anim-up" style={{ animationDelay: `${Math.min(i, 10) * 25}ms` }}>
              <button
                className="card3d press flex w-full items-center gap-3 p-3 text-start"
                onClick={() => { setNavList(list.map((x) => x.isoCode)); go(`country/${c.isoCode}`); }}
              >
                <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-16 flex-none" />
                <div className="min-w-0 flex-1">
                  <div className="truncate text-lg font-black">{c.nameFa} <span className="en muted text-xs">{c.nameEn}</span></div>
                  <div className="muted truncate text-sm">📍 {c.capitalFa} · <span className="en">{c.capitalEn}</span></div>
                </div>
                <ChevronLeft size={20} className="muted" />
              </button>
            </li>
          ))}
        </ul>
      )}
      {tab !== "learned" && list.length > 0 && (
        <button className="btn btn-red w-full" onClick={() => go(`flash?deck=${tab}`)}>
          <BookOpen size={20} /> مرور با فلش‌کارت
        </button>
      )}
    </div>
  );
}

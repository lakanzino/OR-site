import { useState } from "react";
import { Star, CheckCircle2, Trash2, Trophy } from "lucide-react";
import { BY_Z, CAT_BY_ID } from "../data/elements";
import { toFa } from "../lib/fa";
import { go } from "../lib/router";
import { setState, useStore } from "../lib/store";

export function Library() {
  const { favs, learned } = useStore();
  const [tab, setTab] = useState<"favs" | "learned">("favs");
  const list = (tab === "favs" ? favs : learned).slice().sort((a, b) => a - b);
  const pct = Math.round((learned.length / 118) * 100);

  const remove = (z: number) =>
    tab === "favs" ? setState({ favs: favs.filter((x) => x !== z) }) : setState({ learned: learned.filter((x) => x !== z) });

  return (
    <div className="h-full flex flex-col bg-cyber">
      <header className="shrink-0 px-4 pt-4 pb-3 flex flex-col gap-3">
        <h1 className="text-xl font-black">پیشرفت و نشان‌شده‌ها</h1>
        <div className="panel rounded-2xl p-4 flex items-center gap-4">
          <div
            className="relative w-16 h-16 rounded-full shrink-0 flex items-center justify-center"
            style={{ background: `conic-gradient(var(--accent) ${pct * 3.6}deg, var(--surface2) 0)` }}
            role="img"
            aria-label={`پیشرفت ${toFa(pct)} درصد`}
          >
            <div className="absolute inset-[6px] rounded-full bg-surf flex items-center justify-center font-black text-sm">{toFa(pct)}٪</div>
          </div>
          <div className="flex-1">
            <div className="font-extrabold flex items-center gap-2">
              <Trophy size={18} className="text-accent" />
              {toFa(learned.length)} از {toFa(118)} عنصر یاد گرفته‌اید
            </div>
            <p className="text-xs text-muted mt-1 leading-6">
              {learned.length === 118 ? "تبریک! همه عنصرها را یاد گرفتید 🎉" : "با هر کارت یک قدم به مندلیف نزدیک‌تر می‌شوید. ادامه بدهید!"}
            </p>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-2" role="tablist">
          <button role="tab" aria-selected={tab === "favs"} className={"btn " + (tab === "favs" ? "primary" : "")} onClick={() => setTab("favs")}>
            <Star size={18} /> نشان‌شده‌ها ({toFa(favs.length)})
          </button>
          <button role="tab" aria-selected={tab === "learned"} className={"btn " + (tab === "learned" ? "primary" : "")} onClick={() => setTab("learned")}>
            <CheckCircle2 size={18} /> یادگرفته‌ها ({toFa(learned.length)})
          </button>
        </div>
      </header>

      <div className="flex-1 min-h-0 overflow-y-auto scroll-thin px-4 pb-4">
        {list.length === 0 ? (
          <div className="text-center text-muted py-14 leading-8">
            <div className="text-4xl mb-2">{tab === "favs" ? "⭐" : "✅"}</div>
            {tab === "favs" ? "هنوز عنصری را نشان‌دار نکرده‌اید." : "هنوز عنصری را «یاد گرفتم» نزده‌اید."}
            <br />
            <button className="btn primary mt-4" onClick={() => go("")}>
              رفتن به جدول
            </button>
          </div>
        ) : (
          <ul className="grid gap-2">
            {list.map((z) => {
              const e = BY_Z[z];
              const c = CAT_BY_ID[e.cat];
              return (
                <li key={z} className="flex items-center gap-2">
                  <button
                    onClick={() => go("e/" + z)}
                    className="flex-1 min-w-0 flex items-center gap-3 panel rounded-2xl px-3 py-2.5 min-h-[60px] text-start"
                  >
                    <span
                      className="mono w-12 h-12 rounded-xl border-2 flex items-center justify-center font-extrabold shrink-0 relative"
                      style={{ borderColor: c.color, background: `${c.color}26`, color: "var(--cell-text)" }}
                      dir="ltr"
                    >
                      {e.sym}
                    </span>
                    <span className="flex-1 min-w-0">
                      <span className="block font-bold truncate">{e.fa}</span>
                      <span className="block text-xs text-muted truncate">
                        {c.fa} · عدد اتمی {toFa(e.z)}
                      </span>
                    </span>
                  </button>
                  <button className="iconbtn" aria-label={`حذف ${e.fa}`} onClick={() => remove(z)}>
                    <Trash2 size={18} />
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}

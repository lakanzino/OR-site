import { useEffect, useMemo, useState } from "react";
import { Check, RotateCcw, Shuffle, ListOrdered, ArrowLeft, PartyPopper, X } from "lucide-react";
import { CATEGORIES, ELEMENTS, type CategoryId } from "../data/elements";
import { FlipCard } from "../components/FlipCard";
import { toast } from "../components/Toast";
import { toFa } from "../lib/fa";
import { go } from "../lib/router";
import { setState, useStore } from "../lib/store";

function shuffle<T>(a: T[]): T[] {
  const r = [...a];
  for (let i = r.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [r[i], r[j]] = [r[j], r[i]];
  }
  return r;
}

export function Study() {
  const { learned } = useStore();
  const [cat, setCat] = useState<"all" | CategoryId>("all");
  const [random, setRandom] = useState(true);
  const [onlyNew, setOnlyNew] = useState(false);
  const [seed, setSeed] = useState(0);
  const [idx, setIdx] = useState(0);
  const [flipped, setFlipped] = useState(false);

  // The deck is rebuilt only when the options change (not when marking cards as learned).
  const deck = useMemo(() => {
    let list = ELEMENTS.filter((e) => (cat === "all" || e.cat === cat) && (!onlyNew || !learned.includes(e.z)));
    list = random ? shuffle(list) : list;
    return list.map((e) => e.z);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cat, random, onlyNew, seed]);

  useEffect(() => {
    setIdx(0);
    setFlipped(false);
  }, [deck]);

  const cur = deck[idx] !== undefined ? ELEMENTS[deck[idx] - 1] : null;
  const finished = idx >= deck.length;
  const advance = () => {
    setFlipped(false);
    setIdx((i) => i + 1);
  };

  const mark = (known: boolean) => {
    if (!cur) return;
    if (known && !learned.includes(cur.z)) setState({ learned: [...learned, cur.z] });
    if (!known && learned.includes(cur.z)) setState({ learned: learned.filter((x) => x !== cur.z) });
    if (known) toast("آفرین ✓");
    advance();
  };

  return (
    <div className="h-full flex flex-col bg-cyber">
      <header className="shrink-0 px-3 pt-3 pb-2 flex flex-col gap-2.5">
        <div className="flex items-center gap-2">
          <div className="flex-1">
            <h1 className="text-xl font-black">مطالعه با فلش‌کارت</h1>
            <p className="text-xs text-muted">
              {deck.length ? `کارت ${toFa(Math.min(idx + 1, deck.length))} از ${toFa(deck.length)}` : "کارتی برای نمایش نیست"}
            </p>
          </div>
          <button
            className={"iconbtn " + (random ? "active" : "")}
            aria-label={random ? "حالت تصادفی روشن" : "حالت ترتیبی"}
            aria-pressed={random}
            onClick={() => setRandom((r) => !r)}
          >
            {random ? <Shuffle size={20} /> : <ListOrdered size={20} />}
          </button>
          <button
            className={"chip " + (onlyNew ? "on" : "")}
            aria-pressed={onlyNew}
            onClick={() => setOnlyNew((v) => !v)}
          >
            فقط یاد نگرفته‌ها
          </button>
        </div>
        <div className="flex gap-2 overflow-x-auto no-scrollbar -mx-3 px-3">
          <button className={"chip " + (cat === "all" ? "on" : "")} onClick={() => setCat("all")}>
            همه
          </button>
          {CATEGORIES.map((c) => (
            <button
              key={c.id}
              className={"chip " + (cat === c.id ? "on" : "")}
              style={{ "--c": c.color } as React.CSSProperties}
              onClick={() => setCat(cat === c.id ? "all" : c.id)}
            >
              <span className="dot" />
              {c.fa}
            </button>
          ))}
        </div>
        <div className="h-1.5 rounded-full bg-surf2 overflow-hidden" aria-hidden>
          <div className="h-full bg-accent transition-all duration-300" style={{ width: `${deck.length ? (Math.min(idx, deck.length) / deck.length) * 100 : 0}%` }} />
        </div>
      </header>

      <div className="flex-1 min-h-0 px-4 pb-2 flex justify-center">
        <div className="w-full max-w-[440px] h-full max-h-[700px]">
          {cur && !finished ? (
            <div key={cur.z + "-" + idx} className="h-full fade-up">
              <FlipCard e={cur} flipped={flipped} onFlip={() => setFlipped((f) => !f)} />
            </div>
          ) : (
            <div className="h-full panel rounded-3xl flex flex-col items-center justify-center gap-4 p-6 text-center">
              <PartyPopper size={54} className="text-accent" />
              <h2 className="text-xl font-black">{deck.length ? "این دور تمام شد!" : "همه‌چیز را یاد گرفته‌اید!"}</h2>
              <p className="text-muted text-sm leading-7">
                {deck.length
                  ? "می‌توانید دوباره مرور کنید یا دسته دیگری را انتخاب کنید."
                  : "برای مرور دوباره، گزینه «فقط یاد نگرفته‌ها» را خاموش کنید."}
              </p>
              <button
                className="btn primary"
                onClick={() => {
                  if (deck.length === 0) setOnlyNew(false);
                  else setSeed((s) => s + 1);
                }}
              >
                <RotateCcw size={18} /> {deck.length ? "شروع دوباره" : "مرور همه"}
              </button>
            </div>
          )}
        </div>
      </div>

      <footer className="shrink-0 px-4 pb-3 pt-1 max-w-[480px] w-full mx-auto">
        {cur && !finished ? (
          <div className="grid grid-cols-[1fr_auto_1fr] gap-2">
            <button className="btn danger text-sm" onClick={() => mark(false)}>
              <X size={18} /> هنوز نه
            </button>
            <button className="iconbtn" aria-label="چرخاندن کارت" onClick={() => setFlipped((f) => !f)}>
              <RotateCcw size={20} />
            </button>
            <button className="btn primary text-sm" onClick={() => mark(true)}>
              <Check size={18} /> بلدم
            </button>
          </div>
        ) : (
          <button className="btn w-full" onClick={() => go("")}>
            <ArrowLeft size={18} /> بازگشت به جدول
          </button>
        )}
        {cur && !finished && (
          <button className="w-full text-center text-xs text-muted mt-2 py-1" onClick={advance}>
            رد کردن این کارت
          </button>
        )}
      </footer>
    </div>
  );
}

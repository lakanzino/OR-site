import { useEffect, useRef, useState } from "react";
import { ArrowRight, ChevronLeft, ChevronRight, CheckCircle2, Share2, Star, RotateCcw, Shuffle } from "lucide-react";
import { BY_Z, CAT_BY_ID } from "../data/elements";
import { FlipCard } from "../components/FlipCard";
import { toast } from "../components/Toast";
import { toFa } from "../lib/fa";
import { back, replace } from "../lib/router";
import { setState, toggleIn, useStore } from "../lib/store";

export function ElementScreen({ z }: { z: number }) {
  const e = BY_Z[z];
  const { favs, learned } = useStore();
  const [flipped, setFlipped] = useState(false);
  const touch = useRef<{ x: number; y: number } | null>(null);

  useEffect(() => setFlipped(false), [z]);

  const goTo = (n: number) => replace("e/" + ((n + 117) % 118 + 1));
  const prev = () => goTo(z - 1);
  const next = () => goTo(z + 1);

  useEffect(() => {
    const onKey = (k: KeyboardEvent) => {
      if (k.key === "ArrowLeft") goTo(z + 1);
      if (k.key === "ArrowRight") goTo(z - 1);
      if (k.key === "Escape") back();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  });

  if (!e) {
    return (
      <div className="h-full flex flex-col items-center justify-center gap-4 p-6">
        <p>عنصر پیدا نشد.</p>
        <button className="btn primary" onClick={() => replace("")}>بازگشت به جدول</button>
      </div>
    );
  }

  const cat = CAT_BY_ID[e.cat];
  const isFav = favs.includes(z);
  const isDone = learned.includes(z);

  const share = async () => {
    const title = `${e.fa} (${e.sym})`;
    const text = `${e.sym} — ${e.fa} (${e.en})\nعدد اتمی: ${toFa(e.z)} | جرم اتمی: ${toFa(e.mass)}\nدسته: ${cat.fa}\nکاشف: ${toFa(e.disc)} (${toFa(e.year)})\n\nجدول تناوبی (Element.Lax)`;
    try {
      const nativeBridge = (
        window as unknown as {
          ElementLaxNative?: { shareText?: (t: string, body: string) => boolean };
        }
      ).ElementLaxNative;
      if (nativeBridge?.shareText?.(title, text)) return;
      if (navigator.share) {
        await navigator.share({ title, text });
        return;
      }
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        toast("متن کارت کپی شد");
        return;
      }
    } catch {
      /* fallback below */
    }
    try {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
      toast("متن کارت کپی شد");
    } catch {
      /* ignore */
    }
  };

  const randomEl = () => replace("e/" + (1 + Math.floor(Math.random() * 118)));

  return (
    <div className="h-full flex flex-col bg-cyber" style={{ "--c": cat.color } as React.CSSProperties}>
      <header className="flex items-center gap-3 px-3 pt-3 pb-2">
        <button className="iconbtn" onClick={() => back()} aria-label="بازگشت">
          <ArrowRight size={22} />
        </button>
        <div className="flex-1 min-w-0">
          <div className="font-extrabold truncate">{e.fa}</div>
          <div className="text-xs text-muted">
            عنصر {toFa(e.z)} از {toFa(118)}
          </div>
        </div>
        <button className="iconbtn" onClick={randomEl} aria-label="عنصر تصادفی">
          <Shuffle size={20} />
        </button>
      </header>

      <div
        className="flex-1 min-h-0 px-4 pb-2 flex justify-center"
        onTouchStart={(t) => (touch.current = { x: t.touches[0].clientX, y: t.touches[0].clientY })}
        onTouchEnd={(t) => {
          const s = touch.current;
          touch.current = null;
          if (!s) return;
          const dx = t.changedTouches[0].clientX - s.x;
          const dy = t.changedTouches[0].clientY - s.y;
          if (Math.abs(dx) > 70 && Math.abs(dx) > Math.abs(dy) * 1.5) (dx < 0 ? next : prev)();
        }}
      >
        <div className="w-full max-w-[440px] h-full max-h-[720px] fade-up" key={z}>
          <FlipCard e={e} flipped={flipped} onFlip={() => setFlipped((f) => !f)} />
        </div>
      </div>

      <footer className="px-4 pb-4 pt-2 flex flex-col gap-3 max-w-[480px] w-full mx-auto">
        <div className="flex items-center gap-2">
          <button className="iconbtn" onClick={prev} aria-label="عنصر قبلی">
            <ChevronRight size={26} />
          </button>
          <button className="btn primary flex-1" onClick={() => setFlipped((f) => !f)}>
            <RotateCcw size={18} /> {flipped ? "برگشت کارت" : "چرخاندن کارت"}
          </button>
          <button className="iconbtn" onClick={next} aria-label="عنصر بعدی">
            <ChevronLeft size={26} />
          </button>
        </div>
        <div className="grid grid-cols-3 gap-2">
          <button
            className={"btn !px-2 text-sm " + (isFav ? "!border-[#ffd60a] !text-[#ffd60a]" : "")}
            aria-pressed={isFav}
            onClick={() => {
              setState({ favs: toggleIn(favs, z) });
              toast(isFav ? "از نشان‌شده‌ها حذف شد" : "به نشان‌شده‌ها اضافه شد");
            }}
          >
            <Star size={18} fill={isFav ? "currentColor" : "none"} /> نشان‌دار
          </button>
          <button
            className={"btn !px-2 text-sm " + (isDone ? "!border-accent !text-accent" : "")}
            aria-pressed={isDone}
            onClick={() => {
              setState({ learned: toggleIn(learned, z) });
              toast(isDone ? "از یادگرفته‌ها حذف شد" : "آفرین! یاد گرفتی ✓");
            }}
          >
            <CheckCircle2 size={18} /> یاد گرفتم
          </button>
          <button className="btn !px-2 text-sm" onClick={share}>
            <Share2 size={18} /> اشتراک
          </button>
        </div>
      </footer>
    </div>
  );
}

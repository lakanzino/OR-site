import { useEffect, useState } from "react";
import { Delete, Rocket, WifiOff, Layers, BrainCircuit } from "lucide-react";
import { Logo } from "../components/Logo";
import { toFa } from "../lib/fa";
import { DEVELOPER, DISCLAIMER, PURPOSE } from "../lib/legal";
import { hashPin, setState } from "../lib/store";

export function Splash({ onDone }: { onDone: () => void }) {
  useEffect(() => {
    const t = window.setTimeout(onDone, 1100);
    return () => window.clearTimeout(t);
  }, [onDone]);
  return (
    <div className="fixed inset-0 z-[200] bg-cyber flex flex-col items-center justify-center gap-4">
      <Logo size={132} glow />
      <div className="mono neon text-3xl font-extrabold tracking-tight" dir="ltr">
        Element.Lax
      </div>
    </div>
  );
}

export function Onboarding() {
  return (
    <div className="fixed inset-0 z-[150] bg-cyber overflow-y-auto">
      <div className="min-h-full max-w-md mx-auto flex flex-col items-center justify-center gap-5 px-6 py-8 text-center">
        <Logo size={112} glow />
        <h1 className="mono neon text-3xl font-extrabold" dir="ltr">
          Element.Lax
        </h1>
        <p className="text-lg font-bold leading-9">{PURPOSE}</p>

        <ul className="grid gap-2.5 w-full text-start">
          {[
            [Layers, "جدول واقعی ۱۱۸ عنصر", "روی هر عنصر بزنید تا کارت سه‌بعدی آن باز شود؛ رو: انگلیسی، پشت: توضیح فارسی."],
            [BrainCircuit, "یادگیری با فلش‌کارت", "حالت مطالعه، نشان‌شده‌ها و درصد پیشرفت شما."],
            [WifiOff, "۱۰۰٪ آفلاین و خصوصی", "بدون اینترنت، بدون ثبت‌نام، بدون جمع‌آوری اطلاعات."],
          ].map(([I, t, d]) => {
            const Icon = I as typeof Layers;
            return (
              <li key={t as string} className="panel rounded-2xl p-3.5 flex gap-3 items-start">
                <Icon size={24} className="text-accent shrink-0 mt-0.5" />
                <div>
                  <div className="font-extrabold">{t as string}</div>
                  <div className="text-sm text-muted leading-6">{d as string}</div>
                </div>
              </li>
            );
          })}
        </ul>

        <button className="btn primary w-full !min-h-[56px] text-lg" onClick={() => setState({ onboarded: true })}>
          <Rocket size={22} /> شروع یادگیری
        </button>
        <div className="text-xs text-muted leading-6">
          طراح و توسعه‌دهنده: {DEVELOPER} · مجوز MIT
          <br />
          {DISCLAIMER}
        </div>
      </div>
    </div>
  );
}

export function LockScreen({ pin, onUnlock }: { pin: string; onUnlock: () => void }) {
  const [v, setV] = useState("");
  const [err, setErr] = useState(false);

  useEffect(() => {
    if (v.length === 4) {
      if (hashPin(v) === pin) onUnlock();
      else {
        setErr(true);
        const t = window.setTimeout(() => {
          setV("");
          setErr(false);
        }, 600);
        return () => window.clearTimeout(t);
      }
    }
  }, [v, pin, onUnlock]);

  const press = (d: string) => v.length < 4 && !err && setV(v + d);

  return (
    <div className="fixed inset-0 z-[190] bg-cyber flex flex-col items-center justify-center gap-6 px-6">
      <Logo size={84} glow />
      <h1 className="font-black text-lg">رمز برنامه را وارد کنید</h1>
      <div className="flex gap-3" aria-label={`${toFa(v.length)} از ۴ رقم`}>
        {[0, 1, 2, 3].map((i) => (
          <span
            key={i}
            className="w-4 h-4 rounded-full border-2 transition-all"
            style={{
              borderColor: err ? "#ff4d4d" : "var(--accent)",
              background: i < v.length ? (err ? "#ff4d4d" : "var(--accent)") : "transparent",
            }}
          />
        ))}
      </div>
      <div className="grid grid-cols-3 gap-3 w-full max-w-[280px]" dir="ltr">
        {["1", "2", "3", "4", "5", "6", "7", "8", "9"].map((d) => (
          <button key={d} className="btn !h-16 text-2xl mono" onClick={() => press(d)}>
            {d}
          </button>
        ))}
        <span />
        <button className="btn !h-16 text-2xl mono" onClick={() => press("0")}>
          0
        </button>
        <button className="btn !h-16" aria-label="پاک کردن" onClick={() => setV(v.slice(0, -1))}>
          <Delete size={22} />
        </button>
      </div>
    </div>
  );
}

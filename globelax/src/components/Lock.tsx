// PIN lock screen + PIN setup dialog. PIN is stored only as a salted SHA-256 hash.
import { useEffect, useState } from "react";
import { Delete, Lock as LockIcon, X } from "lucide-react";
import { Logo } from "./Logo";
import { hashPin } from "@/lib/store";
import { toFa } from "@/lib/util";

function Keypad({ value, onChange, len = 4 }: { value: string; onChange: (v: string) => void; len?: number }) {
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^[0-9]$/.test(e.key) && value.length < len) onChange(value + e.key);
      if (e.key === "Backspace") onChange(value.slice(0, -1));
    };
    window.addEventListener("keydown", k);
    return () => window.removeEventListener("keydown", k);
  }, [value, onChange, len]);
  return (
    <div>
      <div className="mb-6 flex justify-center gap-4" aria-label={`${value.length} از ${len} رقم`}>
        {Array.from({ length: len }, (_, i) => (
          <span key={i} className="h-4 w-4 rounded-full transition-all" style={{ background: i < value.length ? "var(--yellow)" : "var(--border)", transform: i < value.length ? "scale(1.3)" : "none", boxShadow: i < value.length ? "0 0 12px var(--yellow)" : "none" }} />
        ))}
      </div>
      <div className="mx-auto grid max-w-[280px] grid-cols-3 gap-3" dir="ltr">
        {["1", "2", "3", "4", "5", "6", "7", "8", "9"].map((n) => (
          <button key={n} className="btn btn-ghost !min-h-16 text-2xl" onClick={() => value.length < len && onChange(value + n)}>{toFa(Number(n))}</button>
        ))}
        <span />
        <button className="btn btn-ghost !min-h-16 text-2xl" onClick={() => value.length < len && onChange(value + "0")}>{toFa(0)}</button>
        <button className="btn btn-ghost !min-h-16" onClick={() => onChange(value.slice(0, -1))} aria-label="حذف"><Delete size={24} /></button>
      </div>
    </div>
  );
}

export function LockScreen({ hash, onUnlock }: { hash: string; onUnlock: () => void }) {
  const [pin, setPin] = useState("");
  const [bad, setBad] = useState(false);
  useEffect(() => {
    if (pin.length !== 4) return;
    hashPin(pin).then((h) => {
      if (h === hash) onUnlock();
      else {
        setBad(true);
        setTimeout(() => { setBad(false); setPin(""); }, 500);
      }
    });
  }, [pin, hash, onUnlock]);
  return (
    <div className="fixed inset-0 z-[100] flex flex-col items-center justify-center p-6" style={{ background: "var(--bg)" }}>
      <Logo size={88} className="anim-float mb-3" />
      <h1 className="text-2xl font-black">Globe.Lax</h1>
      <p className="muted mb-6 mt-1 flex items-center gap-1.5"><LockIcon size={16} /> رمز عبور را وارد کنید</p>
      <div className={bad ? "anim-shake" : ""}><Keypad value={pin} onChange={setPin} /></div>
    </div>
  );
}

/** mode "set": (optionally verify old) → new → confirm. mode "remove": verify then remove. */
export function PinDialog({ mode, current, onDone, onClose }: { mode: "set" | "remove"; current: string | null; onDone: (hash: string | null) => void; onClose: () => void }) {
  type Step = "old" | "new" | "confirm";
  const [step, setStep] = useState<Step>(mode === "remove" || current ? "old" : "new");
  const [pin, setPin] = useState("");
  const [first, setFirst] = useState("");
  const [err, setErr] = useState("");

  useEffect(() => {
    if (pin.length !== 4) return;
    (async () => {
      if (step === "old") {
        if ((await hashPin(pin)) === current) {
          if (mode === "remove") return onDone(null);
          setStep("new");
          setErr("");
        } else setErr("رمز فعلی نادرست است");
      } else if (step === "new") {
        setFirst(pin);
        setStep("confirm");
        setErr("");
      } else if (pin === first) {
        onDone(await hashPin(pin));
      } else {
        setErr("دو رمز یکسان نیستند؛ دوباره تلاش کنید");
        setStep("new");
      }
      setPin("");
    })();
  }, [pin]); // eslint-disable-line react-hooks/exhaustive-deps

  const title = step === "old" ? "رمز فعلی را وارد کنید" : step === "new" ? "رمز جدید (۴ رقم)" : "رمز را تکرار کنید";
  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="card3d anim-pop relative w-full max-w-sm p-6">
        <button className="absolute end-3 top-3 flex h-10 w-10 items-center justify-center rounded-full" style={{ background: "var(--border)" }} onClick={onClose} aria-label="بستن"><X size={18} /></button>
        <h3 className="mb-1 text-center text-xl font-black">{title}</h3>
        <p className="mb-4 h-5 text-center text-sm font-bold text-[var(--red)]" role="alert">{err}</p>
        <Keypad value={pin} onChange={setPin} />
      </div>
    </div>
  );
}

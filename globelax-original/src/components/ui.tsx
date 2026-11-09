// Shared small UI pieces: icons-backed header, ring chart, toast, stat tile.
import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from "react";
import { ArrowRight } from "lucide-react";
import { back } from "@/lib/router";

export function PageHeader({ title, en, right }: { title: string; en?: string; right?: ReactNode }) {
  return (
    <div className="mb-4 flex items-center gap-3">
      <button className="btn btn-ghost !min-h-12 !w-12 !px-0" onClick={back} aria-label="بازگشت / Back">
        <ArrowRight size={22} />
      </button>
      <div className="min-w-0 flex-1">
        <h1 className="truncate text-2xl font-extrabold leading-tight">{title}</h1>
        {en && <div className="en muted text-sm">{en}</div>}
      </div>
      {right}
    </div>
  );
}

export function Ring({ value, size = 84, stroke = 10, label }: { value: number; size?: number; stroke?: number; label?: ReactNode }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const id = `ring${size}`;
  return (
    <div className="relative flex-none" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <defs>
          <linearGradient id={id} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#FFC400" />
            <stop offset="1" stopColor="#EF2B3A" />
          </linearGradient>
        </defs>
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--border)" strokeWidth={stroke} />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          stroke={`url(#${id})`}
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c * (1 - Math.min(1, Math.max(0, value)))}
          style={{ transition: "stroke-dashoffset 1s cubic-bezier(.22,1,.36,1)" }}
        />
      </svg>
      <div className="absolute inset-0 flex items-center justify-center text-center font-extrabold leading-none">{label}</div>
    </div>
  );
}

// ── Toasts ──
const ToastCtx = createContext<(msg: string) => void>(() => {});
export const useToast = () => useContext(ToastCtx);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [msg, setMsg] = useState<string | null>(null);
  const show = useCallback((m: string) => setMsg(m), []);
  useEffect(() => {
    if (!msg) return;
    const t = setTimeout(() => setMsg(null), 2200);
    return () => clearTimeout(t);
  }, [msg]);
  return (
    <ToastCtx.Provider value={show}>
      {children}
      {msg && (
        <div className="pointer-events-none fixed inset-x-0 bottom-28 z-[70] flex justify-center px-4" role="status" aria-live="polite">
          <div className="anim-pop card3d px-5 py-3 text-center font-bold">{msg}</div>
        </div>
      )}
    </ToastCtx.Provider>
  );
}

export function Confirm({ open, title, body, onYes, onNo, yesLabel = "تأیید" }: { open: boolean; title: string; body: string; onYes: () => void; onNo: () => void; yesLabel?: string }) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-5 backdrop-blur-sm" role="dialog" aria-modal="true">
      <div className="card3d anim-pop w-full max-w-sm p-6">
        <h3 className="mb-2 text-xl font-extrabold">{title}</h3>
        <p className="muted mb-5 leading-7">{body}</p>
        <div className="flex gap-3">
          <button className="btn btn-red flex-1" onClick={onYes}>{yesLabel}</button>
          <button className="btn btn-ghost flex-1" onClick={onNo}>انصراف</button>
        </div>
      </div>
    </div>
  );
}

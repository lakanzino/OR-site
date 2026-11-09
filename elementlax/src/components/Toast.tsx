import { useEffect, useState } from "react";

let push: ((m: string) => void) | null = null;

export function toast(msg: string) {
  push?.(msg);
}

export function ToastHost() {
  const [msg, setMsg] = useState<string | null>(null);
  useEffect(() => {
    let t: number | undefined;
    push = (m) => {
      setMsg(m);
      window.clearTimeout(t);
      t = window.setTimeout(() => setMsg(null), 2200);
    };
    return () => {
      push = null;
      window.clearTimeout(t);
    };
  }, []);
  if (!msg) return null;
  return (
    <div
      role="status"
      aria-live="polite"
      className="fixed bottom-24 inset-x-0 z-[100] flex justify-center px-4 pointer-events-none"
    >
      <div className="fade-up panel rounded-2xl px-5 py-3 text-sm font-medium text-fg">{msg}</div>
    </div>
  );
}

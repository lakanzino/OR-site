import { useState } from "react";
import { Moon, Sun, Lock, Info, Trash2, ChevronLeft, Scale, ShieldAlert } from "lucide-react";
import { toast } from "../components/Toast";
import { go } from "../lib/router";
import { hashPin, resetAll, setState, useStore } from "../lib/store";
import { DEVELOPER, DISCLAIMER, PURPOSE, APP_VERSION } from "../lib/legal";
import { toFa } from "../lib/fa";

function Modal({ children, onClose }: { children: React.ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[90] bg-black/70 flex items-end sm:items-center justify-center p-4" onClick={onClose} role="dialog" aria-modal="true">
      <div className="panel rounded-3xl p-5 w-full max-w-sm fade-up" onClick={(e) => e.stopPropagation()}>
        {children}
      </div>
    </div>
  );
}

export function Settings() {
  const { theme, pin, learned, favs } = useStore();
  const [dlg, setDlg] = useState<null | "pin" | "reset" | "unlock">(null);
  const [val, setVal] = useState("");

  const closeDlg = () => {
    setDlg(null);
    setVal("");
  };

  return (
    <div className="h-full flex flex-col bg-cyber">
      <header className="shrink-0 px-4 pt-4 pb-2">
        <h1 className="text-xl font-black">تنظیمات</h1>
      </header>
      <div className="flex-1 min-h-0 overflow-y-auto scroll-thin px-4 pb-6">
        <div className="max-w-xl mx-auto grid gap-3">
          {/* Theme */}
          <section className="panel rounded-2xl p-4">
            <h2 className="font-extrabold mb-3">پوسته</h2>
            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="پوسته">
              {(
                [
                  ["dark", "تیره (سایبر)", Moon],
                  ["light", "روشن", Sun],
                ] as const
              ).map(([id, label, Icon]) => (
                <button
                  key={id}
                  role="radio"
                  aria-checked={theme === id}
                  onClick={() => setState({ theme: id })}
                  className={"btn flex-col !h-24 gap-1 " + (theme === id ? "primary" : "")}
                >
                  <Icon size={26} />
                  {label}
                </button>
              ))}
            </div>
          </section>

          {/* App lock */}
          <section className="panel rounded-2xl p-4">
            <h2 className="font-extrabold mb-1 flex items-center gap-2">
              <Lock size={18} /> قفل برنامه (اختیاری)
            </h2>
            <p className="text-xs text-muted leading-6 mb-3">
              با یک رمز ۴ رقمی از ورود دیگران به برنامه جلوگیری کنید. رمز فقط روی همین دستگاه ذخیره می‌شود.
            </p>
            {pin ? (
              <div className="grid grid-cols-2 gap-2">
                <button className="btn" onClick={() => setDlg("pin")}>تغییر رمز</button>
                <button className="btn danger" onClick={() => setDlg("unlock")}>حذف قفل</button>
              </div>
            ) : (
              <button className="btn w-full" onClick={() => setDlg("pin")}>فعال‌سازی قفل</button>
            )}
          </section>

          {/* Links */}
          <section className="panel rounded-2xl overflow-hidden">
            <button className="w-full flex items-center gap-3 p-4 min-h-[60px] text-start" onClick={() => go("about")}>
              <Info size={20} className="text-accent" />
              <span className="flex-1 font-bold">درباره برنامه و مجوز</span>
              <ChevronLeft size={18} className="text-muted" />
            </button>
          </section>

          {/* Data */}
          <section className="panel rounded-2xl p-4">
            <h2 className="font-extrabold mb-1 flex items-center gap-2">
              <Trash2 size={18} /> داده‌ها
            </h2>
            <p className="text-xs text-muted leading-6 mb-3">
              {toFa(favs.length)} نشان‌شده و {toFa(learned.length)} یادگرفته روی این دستگاه ذخیره شده است.
            </p>
            <button className="btn danger w-full" onClick={() => setDlg("reset")}>
              پاک کردن پیشرفت و داده‌ها
            </button>
          </section>

          {/* Credit */}
          <section className="panel rounded-2xl p-4 text-sm leading-7">
            <div className="flex items-center gap-2 font-extrabold mb-1 text-accent">
              <Scale size={18} /> اعتبار و مجوز
            </div>
            <p>{PURPOSE}</p>
            <p className="mt-1">
              طراح و توسعه‌دهنده: <b>{DEVELOPER}</b>
            </p>
            <p className="text-muted">مجوز MIT · نسخه {toFa(APP_VERSION)}</p>
            <div className="flex items-start gap-2 mt-2 text-xs text-muted">
              <ShieldAlert size={16} className="shrink-0 mt-1" />
              <span>{DISCLAIMER}</span>
            </div>
          </section>
        </div>
      </div>

      {dlg === "pin" && (
        <Modal onClose={closeDlg}>
          <h3 className="font-black text-lg mb-3">رمز ۴ رقمی جدید</h3>
          <input
            autoFocus
            inputMode="numeric"
            type="password"
            maxLength={4}
            value={val}
            onChange={(e) => setVal(e.target.value.replace(/\D/g, ""))}
            aria-label="رمز جدید"
            className="w-full h-14 rounded-2xl bg-surf2 border-2 border-line focus:border-accent outline-none text-center text-2xl tracking-[0.6em] mono"
          />
          <div className="grid grid-cols-2 gap-2 mt-4">
            <button className="btn" onClick={closeDlg}>انصراف</button>
            <button
              className="btn primary"
              disabled={val.length !== 4}
              style={{ opacity: val.length === 4 ? 1 : 0.5 }}
              onClick={() => {
                setState({ pin: hashPin(val) });
                toast("قفل برنامه فعال شد");
                closeDlg();
              }}
            >
              ذخیره
            </button>
          </div>
        </Modal>
      )}

      {dlg === "unlock" && (
        <Modal onClose={closeDlg}>
          <h3 className="font-black text-lg mb-3">رمز فعلی را وارد کنید</h3>
          <input
            autoFocus
            inputMode="numeric"
            type="password"
            maxLength={4}
            value={val}
            onChange={(e) => setVal(e.target.value.replace(/\D/g, ""))}
            aria-label="رمز فعلی"
            className="w-full h-14 rounded-2xl bg-surf2 border-2 border-line focus:border-accent outline-none text-center text-2xl tracking-[0.6em] mono"
          />
          <div className="grid grid-cols-2 gap-2 mt-4">
            <button className="btn" onClick={closeDlg}>انصراف</button>
            <button
              className="btn danger"
              onClick={() => {
                if (hashPin(val) === pin) {
                  setState({ pin: null });
                  toast("قفل حذف شد");
                  closeDlg();
                } else toast("رمز اشتباه است");
              }}
            >
              حذف قفل
            </button>
          </div>
        </Modal>
      )}

      {dlg === "reset" && (
        <Modal onClose={closeDlg}>
          <h3 className="font-black text-lg mb-2">پاک کردن داده‌ها؟</h3>
          <p className="text-sm text-muted leading-7">همه نشان‌شده‌ها، یادگرفته‌ها و قفل برنامه پاک می‌شود. این کار قابل بازگشت نیست.</p>
          <div className="grid grid-cols-2 gap-2 mt-4">
            <button className="btn" onClick={closeDlg}>انصراف</button>
            <button
              className="btn danger"
              onClick={() => {
                resetAll();
                toast("داده‌ها پاک شد");
                closeDlg();
              }}
            >
              پاک کن
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}

import { useRef, useState } from "react";
import { Download, Info, Lock, Moon, RotateCcw, Sun, Trash2, Type, Upload, ChevronLeft } from "lucide-react";
import { Confirm, PageHeader, useToast } from "@/components/ui";
import { PinDialog } from "@/components/Lock";
import { go } from "@/lib/router";
import { actions, useAppState } from "@/lib/store";
import { APP_VERSION } from "@/lib/legal";
import { toFa } from "@/lib/util";

function Section({ title, en, children, delay = 0 }: { title: string; en: string; children: React.ReactNode; delay?: number }) {
  return (
    <section className="card3d anim-up p-5" style={{ animationDelay: `${delay}ms` }}>
      <h2 className="mb-3 font-black">{title} <span className="en muted text-xs">{en}</span></h2>
      {children}
    </section>
  );
}

export default function Settings() {
  const { settings } = useAppState();
  const toast = useToast();
  const file = useRef<HTMLInputElement>(null);
  const [pinMode, setPinMode] = useState<null | "set" | "remove">(null);
  const [confirmClear, setConfirmClear] = useState(false);

  const exportBackup = () => {
    const blob = new Blob([actions.exportJson()], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `globe-lax-backup-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
    toast("پشتیبان ذخیره شد ✓");
  };

  const importBackup = async (f: File | undefined) => {
    if (!f) return;
    const ok = actions.importJson(await f.text());
    toast(ok ? "بازیابی با موفقیت انجام شد ✓" : "فایل پشتیبان معتبر نیست");
    if (file.current) file.current.value = "";
  };

  return (
    <div className="space-y-4">
      <PageHeader title="تنظیمات" en="Settings" />

      <Section title="پوسته" en="Theme">
        <div className="grid grid-cols-2 gap-3">
          {([["dark", "تیره", "Dark", Moon], ["light", "روشن", "Light", Sun]] as const).map(([id, fa, en, Icon]) => (
            <button
              key={id}
              className={`btn !min-h-16 !flex-col !gap-0 ${settings.theme === id ? "btn-yellow" : "btn-ghost"}`}
              aria-pressed={settings.theme === id}
              onClick={() => actions.setSettings({ theme: id })}
            >
              <Icon size={22} />
              <span>{fa} <span className="en text-xs opacity-80">{en}</span></span>
            </button>
          ))}
        </div>
      </Section>

      <Section title="اندازهٔ متن" en="Font size" delay={50}>
        <div className="flex items-center gap-3">
          <Type size={16} />
          <input
            type="range" min={0.85} max={1.35} step={0.05}
            value={settings.fontScale}
            onChange={(e) => actions.setSettings({ fontScale: Number(e.target.value) })}
            className="h-10 flex-1 accent-[var(--yellow)]"
            aria-label="اندازهٔ متن"
          />
          <Type size={26} />
          <span className="w-10 text-center text-sm font-black">{toFa(Math.round(settings.fontScale * 100))}٪</span>
        </div>
      </Section>

      <Section title="قفل رمز (اختیاری)" en="PIN lock" delay={100}>
        <p className="muted mb-3 text-sm leading-7">با فعال‌سازی، هنگام باز کردن برنامه رمز ۴ رقمی پرسیده می‌شود. رمز فقط به‌صورت هش روی همین دستگاه ذخیره می‌شود.</p>
        <div className="flex flex-wrap gap-3">
          <button className="btn btn-yellow flex-1" onClick={() => setPinMode("set")}><Lock size={18} /> {settings.pinHash ? "تغییر رمز" : "فعال‌سازی"}</button>
          {settings.pinHash && <button className="btn btn-ghost flex-1" onClick={() => setPinMode("remove")}>حذف قفل</button>}
        </div>
      </Section>

      <Section title="پشتیبان‌گیری و بازیابی" en="Backup & restore" delay={150}>
        <p className="muted mb-3 text-sm leading-7">پیشرفت و علاقه‌مندی‌ها را در یک فایل JSON ذخیره یا از آن بازیابی کنید.</p>
        <div className="grid grid-cols-2 gap-3">
          <button className="btn btn-yellow" onClick={exportBackup}><Download size={18} /> ذخیره</button>
          <button className="btn btn-ghost" onClick={() => file.current?.click()}><Upload size={18} /> بازیابی</button>
          <input ref={file} type="file" accept="application/json,.json" className="hidden" onChange={(e) => importBackup(e.target.files?.[0])} />
        </div>
      </Section>

      <Section title="پاک کردن پیشرفت" en="Reset" delay={200}>
        <button className="btn btn-red w-full" onClick={() => setConfirmClear(true)}><Trash2 size={18} /> پاک کردن همهٔ پیشرفت‌ها</button>
      </Section>

      <button className="card3d press anim-up flex w-full items-center gap-3 p-5 text-start" style={{ animationDelay: "250ms" }} onClick={() => go("about")}>
        <Info className="text-[var(--yellow)]" />
        <div className="flex-1">
          <div className="font-black">دربارهٔ Globe.Lax</div>
          <div className="en muted text-xs">v{APP_VERSION} · MIT License · mohamadreza.lak</div>
        </div>
        <ChevronLeft />
      </button>

      <div className="muted flex items-center justify-center gap-1 pb-2 text-xs"><RotateCcw size={12} /> همه‌چیز آفلاین و محلی ذخیره می‌شود.</div>

      {pinMode && (
        <PinDialog
          mode={pinMode}
          current={settings.pinHash}
          onClose={() => setPinMode(null)}
          onDone={(h) => {
            actions.setSettings({ pinHash: h });
            setPinMode(null);
            toast(h ? "قفل رمز فعال شد 🔒" : "قفل رمز حذف شد");
          }}
        />
      )}
      <Confirm
        open={confirmClear}
        title="پاک کردن پیشرفت؟"
        body="علاقه‌مندی‌ها، کشورهای یادگرفته، اشتباهات و امتیازها حذف می‌شوند. این کار قابل بازگشت نیست."
        yesLabel="پاک کن"
        onNo={() => setConfirmClear(false)}
        onYes={() => { actions.clearProgress(); setConfirmClear(false); toast("پیشرفت پاک شد"); }}
      />
    </div>
  );
}

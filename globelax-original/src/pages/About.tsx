import { useState } from "react";
import { ChevronDown, Heart, ShieldCheck, WifiOff } from "lucide-react";
import { Logo } from "@/components/Logo";
import { PageHeader } from "@/components/ui";
import { APP_NAME, APP_VERSION, AUTHOR_FA, AUTHOR_HANDLE, DISCLAIMER_FA, MIT_TEXT, PURPOSE_FA } from "@/lib/legal";

export default function About() {
  const [open, setOpen] = useState(false);
  return (
    <div className="space-y-4">
      <PageHeader title="دربارهٔ برنامه" en="About" />

      <section className="card3d anim-up p-6 text-center">
        <div className="anim-float mx-auto w-fit drop-shadow-2xl"><Logo size={110} /></div>
        <h2 className="mt-3 text-3xl font-black">{APP_NAME}</h2>
        <div className="en muted">Countries & Capitals · Version {APP_VERSION}</div>
        <p className="mt-4 rounded-2xl p-4 text-lg font-extrabold leading-9" style={{ background: "color-mix(in srgb, var(--yellow) 18%, transparent)" }}>
          🇮🇷 {PURPOSE_FA}
        </p>
      </section>

      <section className="card3d anim-up space-y-2 p-5" style={{ animationDelay: "80ms" }}>
        <h3 className="flex items-center gap-2 font-black"><Heart className="text-[var(--red)]" size={20} fill="currentColor" /> سازنده / Credits</h3>
        <div className="text-xl font-black">طراحی و توسعه: {AUTHOR_FA}</div>
        <div className="en muted">Designed & developed by {AUTHOR_HANDLE}</div>
        <div className="muted text-sm">هدف آموزشی برای نوجوانان و جوانان ایران عزیز · Educational purpose for Iranian youth</div>
        <div className="en muted text-sm">Copyright © 2025 {AUTHOR_HANDLE} · MIT License</div>
      </section>

      <section className="card3d anim-up space-y-3 p-5" style={{ animationDelay: "140ms" }}>
        <h3 className="flex items-center gap-2 font-black"><ShieldCheck className="text-[var(--yellow)]" size={20} /> حریم خصوصی</h3>
        <ul className="muted space-y-1.5 leading-7">
          <li className="flex gap-2"><WifiOff size={18} className="mt-1 flex-none" /> ۱۰۰٪ آفلاین؛ هیچ داده‌ای به اینترنت ارسال نمی‌شود.</li>
          <li>• بدون ردیابی، بدون تبلیغات، بدون حساب کاربری.</li>
          <li>• پیشرفت، علاقه‌مندی‌ها و تنظیمات فقط روی همین دستگاه ذخیره می‌شوند.</li>
          <li>• نیازی به هیچ مجوز (Permission) خاصی نیست.</li>
        </ul>
      </section>

      <section className="card3d anim-up space-y-2 p-5" style={{ animationDelay: "200ms" }}>
        <h3 className="font-black">⚠️ سلب مسئولیت</h3>
        <p className="leading-7">{DISCLAIMER_FA}</p>
        <p className="en muted text-sm">Data compiled from reliable sources; use is at the user's own responsibility.</p>
        <p className="muted text-sm leading-7">
          جمعیت: بانک جهانی ۲۰۲۵؛ مساحت کل: بانک جهانی ۲۰۲۴ (بوسنی و نیکاراگوئه: ۲۰۲۳). واتیکان: برآورد پیشین. رتبه‌ها میان ۱۹۵ کشور این برنامه محاسبه شده‌اند. منابع و تغییرات: گزارش پروژه. نام‌گذاری و پایتخت‌ها بر پایهٔ شناسایی سازمان ملل و رایج‌ترین مراجع است و بیانگر موضع سیاسی نیست.
        </p>
      </section>

      <section className="card3d anim-up overflow-hidden" style={{ animationDelay: "260ms" }}>
        <button className="flex min-h-14 w-full items-center justify-between p-5 font-black" onClick={() => setOpen(!open)} aria-expanded={open}>
          <span>📜 مجوز MIT <span className="en muted text-xs">License</span></span>
          <ChevronDown className={`transition-transform ${open ? "rotate-180" : ""}`} />
        </button>
        {open && <pre className="en muted max-h-80 overflow-auto whitespace-pre-wrap px-5 pb-5 text-xs leading-6">{MIT_TEXT}</pre>}
      </section>
    </div>
  );
}

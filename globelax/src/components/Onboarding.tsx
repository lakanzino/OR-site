import { useState } from "react";
import { ChevronLeft } from "lucide-react";
import { Logo } from "./Logo";
import { actions } from "@/lib/store";
import { AUTHOR_FA, PURPOSE_FA } from "@/lib/legal";

const SLIDES = [
  {
    emoji: null as string | null,
    fa: "به Globe.Lax خوش آمدید",
    en: "Welcome to Globe.Lax",
    body: PURPOSE_FA,
    extra: `طراحی و توسعه: ${AUTHOR_FA} (mohamadreza.lak)`,
  },
  {
    emoji: "🌍",
    fa: "کره، کارت و آزمون",
    en: "Globe · Flashcards · Quiz",
    body: "۱۹۵ کشور با پرچم، پایتخت، واحد پول، جمعیت، مساحت و زبان — با کره زمین سه‌بعدی، فلش‌کارت‌های چرخان و آزمون‌های سرگرم‌کننده.",
    extra: "نام‌ها به فارسی و انگلیسی",
  },
  {
    emoji: "🔒",
    fa: "آفلاین و خصوصی",
    en: "Offline & Private",
    body: "همه‌چیز روی گوشی شما ذخیره می‌شود؛ بدون اینترنت، بدون تبلیغ، بدون ردیابی.",
    extra: "اطلاعات از منابع معتبر تهیه شده اما مسئولیت استفاده با کاربر است.",
  },
];

export default function Onboarding() {
  const [i, setI] = useState(0);
  const s = SLIDES[i];
  const last = i === SLIDES.length - 1;
  const finish = () => actions.setSettings({ onboarded: true });
  return (
    <div className="fixed inset-0 z-[95] flex flex-col items-center justify-between p-6" style={{ background: "var(--bg)" }}>
      <div className="flex w-full max-w-md justify-end">
        <button className="btn btn-ghost !min-h-11 text-sm" onClick={finish}>رد کردن</button>
      </div>
      <div key={i} className="anim-slide flex max-w-md flex-col items-center text-center">
        {s.emoji ? <div className="anim-float mb-4 text-8xl drop-shadow-2xl">{s.emoji}</div> : <Logo size={132} className="anim-float mb-4 drop-shadow-2xl" />}
        <h1 className="text-3xl font-black">{s.fa}</h1>
        <div className="en title-grad mt-1 text-lg">{s.en}</div>
        <p className="mt-5 text-xl font-extrabold leading-10">{s.body}</p>
        <p className="muted mt-3 text-sm leading-7">{s.extra}</p>
      </div>
      <div className="w-full max-w-md">
        <div className="mb-5 flex justify-center gap-2">
          {SLIDES.map((_, k) => <span key={k} className="h-2.5 rounded-full transition-all" style={{ width: k === i ? 32 : 10, background: k === i ? "var(--yellow)" : "var(--border)" }} />)}
        </div>
        <button className="btn btn-yellow w-full !min-h-14 text-lg" onClick={() => (last ? finish() : setI(i + 1))}>
          {last ? "شروع کنیم 🚀" : "بعدی"} {!last && <ChevronLeft size={20} />}
        </button>
      </div>
    </div>
  );
}

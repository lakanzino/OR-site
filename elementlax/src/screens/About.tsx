import { useState } from "react";
import { ArrowRight, Heart, Scale, ShieldCheck, FlaskConical, ChevronDown, UserCheck, BookOpen } from "lucide-react";
import { Logo } from "../components/Logo";
import { toFa } from "../lib/fa";
import { back } from "../lib/router";
import { APP_NAME, APP_NAME_FA, APP_VERSION, COPYRIGHT, DEVELOPER, DISCLAIMER, MIT_TEXT, PURPOSE, THIRD_PARTY_LICENSES } from "../lib/legal";

function Card({ icon, title, children }: { icon: React.ReactNode; title: string; children: React.ReactNode }) {
  return (
    <section className="panel rounded-2xl p-4">
      <h2 className="font-extrabold flex items-center gap-2 mb-2 text-accent">
        {icon}
        {title}
      </h2>
      <div className="text-[0.92rem] leading-7 text-fg/95">{children}</div>
    </section>
  );
}

export function About() {
  const [open, setOpen] = useState(false);
  const [openThirdParty, setOpenThirdParty] = useState(false);
  return (
    <div className="h-full flex flex-col bg-cyber">
      <header className="shrink-0 flex items-center gap-3 px-3 pt-3 pb-2">
        <button className="iconbtn" onClick={() => back("settings")} aria-label="بازگشت">
          <ArrowRight size={22} />
        </button>
        <h1 className="text-xl font-black">درباره برنامه</h1>
      </header>
      <div className="flex-1 min-h-0 overflow-y-auto scroll-thin px-4 pb-8">
        <div className="max-w-xl mx-auto grid gap-3">
          <div className="flex flex-col items-center text-center py-4">
            <Logo size={104} glow />
            <h2 className="text-2xl font-black mt-3">{APP_NAME_FA}</h2>
            <div className="mono neon text-lg font-extrabold mt-0.5" dir="ltr">
              {APP_NAME}
            </div>
            <div className="text-muted text-sm mt-1">نسخه {toFa(APP_VERSION)} · کاملاً آفلاین</div>
          </div>

          <Card icon={<Heart size={18} />} title="هدف">
            {PURPOSE}
          </Card>

          <Card icon={<UserCheck size={18} />} title="طراح و توسعه‌دهنده">
            <div className="font-black text-lg">{DEVELOPER}</div>
            <div className="text-muted text-sm mono" dir="ltr" style={{ textAlign: "right" }}>
              {COPYRIGHT}
            </div>
          </Card>

          <Card icon={<Scale size={18} />} title="مجوز: MIT">
            این برنامه متن‌باز است و با مجوز MIT منتشر شده؛ استفاده، تغییر و بازنشر آن با حفظ نام سازنده آزاد است.
            <button className="mt-3 flex items-center gap-2 text-accent font-bold text-sm" onClick={() => setOpen((o) => !o)} aria-expanded={open}>
              متن کامل مجوز
              <ChevronDown size={16} className={"transition-transform " + (open ? "rotate-180" : "")} />
            </button>
            {open && (
              <pre className="mono text-[0.7rem] leading-5 whitespace-pre-wrap mt-2 p-3 rounded-xl bg-surf2 text-fg/90" dir="ltr">
                {MIT_TEXT}
              </pre>
            )}
          </Card>

          <Card icon={<BookOpen size={18} />} title="مجوز کتابخانه‌های متن‌باز">
            در ساخت این برنامه از فناوری‌ها و فونت‌های متن‌باز معتبر با مجوزهای رسمی استفاده شده است.
            <button
              className="mt-3 flex items-center gap-2 text-accent font-bold text-sm"
              onClick={() => setOpenThirdParty((o) => !o)}
              aria-expanded={openThirdParty}
            >
              مشاهده فهرست مجوزها
              <ChevronDown size={16} className={"transition-transform " + (openThirdParty ? "rotate-180" : "")} />
            </button>
            {openThirdParty && (
              <ul className="mt-2 grid gap-1.5 text-xs bg-surf2 rounded-xl p-3" dir="ltr">
                {THIRD_PARTY_LICENSES.map((item) => (
                  <li key={item.name} className="flex items-center justify-between gap-2 border-b border-line/40 last:border-0 pb-1 last:pb-0">
                    <span className="font-bold text-fg">{item.name}</span>
                    <span className="mono text-muted">{item.license}</span>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card icon={<FlaskConical size={18} />} title="دقت علمی و منابع">
            {DISCLAIMER}
            <br />
            داده‌ها با تکیه بر منابع IUPAC (جرم‌های اتمی استاندارد، نام و نماد عنصرها)، انجمن سلطنتی شیمی بریتانیا (RSC) و PubChem گردآوری شده‌اند. جرم‌های داخل [کروشه] مربوط به پایدارترین ایزوتوپ عنصرهای رادیواکتیو است. عنصرهای ۱۰۴ به بعد فقط در آزمایشگاه ساخته شده‌اند و بخشی از خواص آن‌ها پیش‌بینی نظری است.
          </Card>

          <Card icon={<ShieldCheck size={18} />} title="حریم خصوصی">
            این برنامه ۱۰۰٪ آفلاین است، هیچ مجوز خاصی نمی‌خواهد و هیچ داده‌ای جمع‌آوری یا ارسال نمی‌کند. پیشرفت شما فقط روی همین دستگاه ذخیره می‌شود. رده سنی: همه · آموزشی.
          </Card>

          <p className="text-center text-xs text-muted pt-2">ساخته‌شده با ❤️ برای نوجوانان و جوانان ایران</p>
        </div>
      </div>
    </div>
  );
}

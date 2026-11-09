import { RotateCcw, Sparkles, Landmark, Wrench, Atom, ScrollText, FlaskConical } from "lucide-react";
import { CAT_BY_ID, type Element } from "../data/elements";
import { toFa } from "../lib/fa";

interface Props {
  e: Element;
  flipped: boolean;
  onFlip: () => void;
}

function Stat({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="rounded-xl bg-surf2/80 border border-line px-3 py-2 min-w-0">
      <div className="text-[0.7rem] text-muted font-medium">{label}</div>
      <div className={"font-bold text-[0.95rem] leading-snug break-words " + (mono ? "mono" : "")}>{value}</div>
    </div>
  );
}

function Section({ icon, title, children }: { icon: React.ReactNode; title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-2xl border border-line bg-surf2/50 p-3.5">
      <h3 className="sect-title flex items-center gap-2 mb-1.5">
        {icon}
        {title}
      </h3>
      <p className="text-[0.95rem] leading-7 text-fg/95">{children}</p>
    </section>
  );
}

export function FlipCard({ e, flipped, onFlip }: Props) {
  const cat = CAT_BY_ID[e.cat];
  const style = { "--c": cat.color } as React.CSSProperties;
  const groupEn = e.group ? String(e.group) : "f-block";
  const groupFa = e.group ? toFa(e.group) : "بلوک f (بدون شماره گروه)";
  const massFa = toFa(e.mass.replace(/[[\]]/g, ""));
  const radioactive = e.mass.startsWith("[");

  return (
    <div className="scene" style={style}>
      <div className={"card3d " + (flipped ? "flipped" : "")}>
        {/* ---------------- FRONT: English / cyber ---------------- */}
        <div
          className="face card-front"
          dir="ltr"
          role="button"
          tabIndex={flipped ? -1 : 0}
          aria-hidden={flipped}
          aria-label={`${e.en}. Tap to flip`}
          onClick={onFlip}
          onKeyDown={(k) => (k.key === "Enter" || k.key === " ") && (k.preventDefault(), onFlip())}
        >
          <i className="bracket" style={{ top: 12, left: 12, borderTop: "3px solid", borderLeft: "3px solid", borderTopLeftRadius: 10 }} />
          <i className="bracket" style={{ top: 12, right: 12, borderTop: "3px solid", borderRight: "3px solid", borderTopRightRadius: 10 }} />
          <i className="bracket" style={{ bottom: 12, left: 12, borderBottom: "3px solid", borderLeft: "3px solid", borderBottomLeftRadius: 10 }} />
          <i className="bracket" style={{ bottom: 12, right: 12, borderBottom: "3px solid", borderRight: "3px solid", borderBottomRightRadius: 10 }} />

          <div className="relative h-full flex flex-col px-6 pt-8 pb-7">
            <div className="flex items-start justify-between gap-3">
              <div className="mono font-extrabold leading-none" style={{ color: cat.color, fontSize: "clamp(2.4rem, 11vw, 3.4rem)", textShadow: `0 0 14px ${cat.color}88` }}>
                {e.z}
              </div>
              <div
                className="mono text-[0.7rem] font-bold uppercase tracking-wider rounded-full px-3 py-1.5 border-2 text-right"
                style={{ borderColor: cat.color, color: cat.color }}
              >
                {cat.en}
              </div>
            </div>

            <div className="flex-1 flex flex-col items-center justify-center min-h-0">
              <div className="front-sym" style={{ fontSize: "clamp(7rem, 38vw, 12rem)" }}>
                {e.sym}
              </div>
              <div className="mono mt-3 text-[clamp(1.25rem,6vw,1.8rem)] font-extrabold uppercase tracking-[0.18em] text-fg text-center">
                {e.en}
              </div>
              <div className="mono mt-1 text-sm text-muted">
                {e.mass} u{radioactive ? " · radioactive" : ""}
              </div>
            </div>

            <div className="grid grid-cols-3 gap-2 mono text-center">
              {[
                ["GROUP", groupEn],
                ["PERIOD", String(e.period)],
                ["STATE", e.state === "predicted" ? "Unknown" : e.stateEn],
              ].map(([k, v]) => (
                <div key={k} className="rounded-xl border px-2 py-2" style={{ borderColor: `${cat.color}88`, background: `${cat.color}14` }}>
                  <div className="text-[0.62rem] tracking-widest text-muted">{k}</div>
                  <div className="font-extrabold text-base text-fg">{v}</div>
                </div>
              ))}
            </div>
            <div className="mono text-center text-[0.68rem] tracking-[0.25em] text-muted mt-3 uppercase flex items-center justify-center gap-2">
              <RotateCcw size={13} /> tap to flip
            </div>
          </div>
        </div>

        {/* ---------------- BACK: Persian ---------------- */}
        <div className="face back card-back" dir="rtl" aria-hidden={!flipped}>
          <div
            className="h-full overflow-y-auto scroll-thin px-4 pt-4 pb-24"
            onClick={onFlip}
            role="button"
            tabIndex={flipped ? 0 : -1}
            aria-label="برگشت کارت"
            onKeyDown={(k) => k.key === "Enter" && onFlip()}
          >
            <div className="flex items-center gap-3 mb-3">
              <div
                className="mono shrink-0 w-[72px] h-[72px] rounded-2xl flex flex-col items-center justify-center border-2 relative"
                style={{ borderColor: cat.color, background: `${cat.color}22`, color: "var(--cell-text)" }}
                dir="ltr"
              >
                <span className="absolute top-1 left-2 text-[0.65rem] font-bold" style={{ color: cat.color }}>{e.z}</span>
                <span className="text-3xl font-extrabold neon" style={{ marginTop: 6 }}>{e.sym}</span>
              </div>
              <div className="min-w-0">
                <h2 className="text-2xl font-black leading-tight">{e.fa}</h2>
                <div className="mono text-sm text-muted" dir="ltr" style={{ textAlign: "right" }}>{e.en}</div>
                <div className="inline-flex items-center gap-1.5 mt-1 text-xs font-bold rounded-full px-2.5 py-0.5" style={{ background: cat.color, color: "#04120a" }}>
                  {cat.fa}
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2 mb-3">
              <Stat label="عدد اتمی" value={toFa(e.z)} />
              <Stat label={radioactive ? "جرم اتمی (پایدارترین ایزوتوپ)" : "جرم اتمی (u)"} value={massFa} />
              <Stat label="گروه" value={groupFa} />
              <Stat label="دوره" value={toFa(e.period)} />
              <Stat label="حالت در دمای اتاق" value={e.stateFa} />
              <Stat label="سال کشف" value={e.year === "باستان" ? "باستان (پیش از تاریخ مدون)" : toFa(e.year)} />
            </div>

            <div className="grid gap-2.5">
              <Section icon={<Sparkles size={16} />} title="کاشف">{toFa(e.disc)}</Section>
              <Section icon={<ScrollText size={16} />} title="دلیل نام‌گذاری">{toFa(e.etym)}</Section>
              <Section icon={<Wrench size={16} />} title="کاربردهای مهم">{toFa(e.uses)}</Section>
              <Section icon={<FlaskConical size={16} />} title="خواص کلیدی">{toFa(e.props)}</Section>
              <Section icon={<Landmark size={16} />} title="تاریخچه کوتاه">{toFa(e.hist)}</Section>
              {e.z >= 109 || e.z === 85 || e.z === 87 || e.z >= 100 ? (
                <Section icon={<Atom size={16} />} title="نکته آموزشی">
                  از این عنصر فقط مقدار بسیار اندکی ساخته شده و بسیاری از خواص آن (مانند حالت ماده) هنوز به‌صورت تجربی تأیید نشده و پیش‌بینی نظری است.
                </Section>
              ) : null}
            </div>
          </div>
          <div className="absolute bottom-3 inset-x-0 flex justify-center pointer-events-none">
            <button
              type="button"
              className="btn primary pointer-events-auto"
              onClick={(ev) => {
                ev.stopPropagation();
                onFlip();
              }}
              tabIndex={flipped ? 0 : -1}
            >
              <RotateCcw size={18} /> برگشت کارت
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

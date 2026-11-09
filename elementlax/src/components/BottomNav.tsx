import { Grid3x3, GraduationCap, Bookmark, Settings } from "lucide-react";
import { go } from "../lib/router";

const ITEMS = [
  { id: "", label: "جدول", Icon: Grid3x3 },
  { id: "study", label: "مطالعه", Icon: GraduationCap },
  { id: "library", label: "من", Icon: Bookmark },
  { id: "settings", label: "تنظیمات", Icon: Settings },
];

export function BottomNav({ active }: { active: string }) {
  return (
    <nav
      className="shrink-0 border-t border-line bg-surf/95 backdrop-blur grid grid-cols-4"
      style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
      aria-label="ناوبری اصلی"
    >
      {ITEMS.map(({ id, label, Icon }) => {
        const on = active === id;
        return (
          <button
            key={id}
            onClick={() => go(id)}
            aria-current={on ? "page" : undefined}
            className={"flex flex-col items-center justify-center gap-0.5 py-2 min-h-[58px] transition-colors " + (on ? "text-accent" : "text-muted")}
          >
            <span className={"px-4 py-1 rounded-full transition-colors " + (on ? "bg-accent/15" : "")}>
              <Icon size={22} style={on ? { filter: "drop-shadow(0 0 6px var(--accent))" } : undefined} />
            </span>
            <span className={"text-[0.72rem] " + (on ? "font-extrabold" : "font-medium")}>{label}</span>
          </button>
        );
      })}
    </nav>
  );
}

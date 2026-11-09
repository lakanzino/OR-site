import { memo } from "react";
import { Check, Heart } from "lucide-react";
import type { Country } from "@/data/countries";
import { Flag } from "./Flag";
import { CONTINENTS } from "@/data/dictionaries";

interface Props {
  c: Country;
  fav: boolean;
  learned: boolean;
  onOpen: (iso: string) => void;
  index?: number;
}

/** Premium grid card: big flag, bold bilingual name, capital preview. */
export const CountryCard = memo(function CountryCard({ c, fav, learned, onOpen, index = 0 }: Props) {
  const col = CONTINENTS[c.continent].color;
  return (
    <button
      onClick={() => onOpen(c.isoCode)}
      className="card3d press cv-auto anim-up w-full overflow-hidden p-3 text-start"
      style={{ animationDelay: `${Math.min(index, 12) * 35}ms`, perspective: 800 }}
      aria-label={`${c.nameFa} ${c.nameEn}`}
    >
      <div className="relative">
        <Flag iso={c.isoCode} emoji={c.flagEmoji} className="w-full" alt={c.nameEn} />
        <div className="absolute start-2 top-2 flex gap-1">
          {fav && (
            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--red)] text-white shadow-lg">
              <Heart size={14} fill="currentColor" />
            </span>
          )}
          {learned && (
            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--yellow)] text-[#0b1f4b] shadow-lg">
              <Check size={16} strokeWidth={3.5} />
            </span>
          )}
        </div>
        <span className="absolute bottom-1.5 end-1.5 h-3 w-3 rounded-full ring-2 ring-white/80" style={{ background: col }} />
      </div>
      <div className="mt-3 text-lg font-extrabold leading-tight">{c.nameFa}</div>
      <div className="en text-sm leading-tight" style={{ color: "var(--muted)" }}>{c.nameEn}</div>
      <div className="mt-2 flex items-center gap-1.5 border-t pt-2 text-sm" style={{ borderColor: "var(--border)" }}>
        <span aria-hidden>📍</span>
        <span className="min-w-0 flex-1 truncate font-bold">{c.capitalFa}</span>
      </div>
      <div className="en muted truncate text-xs">{c.capitalEn}</div>
    </button>
  );
});

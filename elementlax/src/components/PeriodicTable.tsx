import { memo } from "react";
import { CAT_BY_ID, ELEMENTS, type CategoryId, type Element } from "../data/elements";
import { toFa } from "../lib/fa";

interface CellProps {
  e: Element;
  dim: boolean;
  hit: boolean;
  fav: boolean;
  done: boolean;
  showName: boolean;
  onOpen: (z: number) => void;
}

const Cell = memo(function Cell({ e, dim, hit, fav, done, showName, onOpen }: CellProps) {
  const cls = ["cell", dim ? "dim" : "", hit ? "hit" : "", fav ? "fav" : "", done ? "done" : ""].join(" ");
  return (
    <button
      type="button"
      className={cls}
      aria-label={`${e.fa} ${e.en} ${toFa(e.z)}`}
      style={
        {
          "--c": CAT_BY_ID[e.cat].color,
          gridRow: e.row + 1,
          gridColumn: e.col,
        } as React.CSSProperties
      }
      onClick={() => onOpen(e.z)}
    >
      <span className="num">{e.z}</span>
      <span className="sym">{e.sym}</span>
      {showName && <span className="nm">{e.en}</span>}
    </button>
  );
});

interface Props {
  cs: number;
  cat: "all" | CategoryId;
  matches: Set<number> | null;
  favs: number[];
  learned: number[];
  onOpen: (z: number) => void;
}

export function PeriodicTable({ cs, cat, matches, favs, learned, onOpen }: Props) {
  const favSet = new Set(favs);
  const doneSet = new Set(learned);
  const gap = 3;
  const showName = cs >= 46;
  const lan = CAT_BY_ID.lanthanide.color;
  const act = CAT_BY_ID.actinide.color;

  return (
    <div
      dir="ltr"
      className="grid select-none"
      style={
        {
          "--cs": `${cs}px`,
          gap: `${gap}px`,
          gridTemplateColumns: `repeat(18, ${cs}px)`,
          gridTemplateRows: `16px repeat(7, ${Math.round(cs * 1.12)}px) 12px repeat(2, ${Math.round(cs * 1.12)}px)`,
          width: cs * 18 + gap * 17,
        } as React.CSSProperties
      }
    >
      {Array.from({ length: 18 }, (_, i) => (
        <div
          key={i}
          className="mono text-muted flex items-center justify-center"
          style={{ gridRow: 1, gridColumn: i + 1, fontSize: Math.max(9, cs * 0.2) }}
          aria-hidden
        >
          {i + 1}
        </div>
      ))}

      {/* Hero panel in the empty area of the table */}
      <div
        className="flex flex-col items-center justify-center text-center overflow-hidden"
        style={{ gridRow: "2 / 5", gridColumn: "3 / 13", gap: 2 }}
      >
        <div className="mono neon font-extrabold leading-none" style={{ fontSize: Math.max(18, cs * 0.62) }}>
          Element.Lax
        </div>
        <div className="text-muted font-medium" style={{ fontSize: Math.max(11, cs * 0.26) }}>
          جدول تناوبی عنصرها
        </div>
        <div className="text-fg/80" style={{ fontSize: Math.max(10, cs * 0.22) }}>
          {toFa(learned.length)} از {toFa(118)} عنصر یاد گرفته شد
        </div>
      </div>

      {/* f-block placeholders */}
      <div
        className="mono flex items-center justify-center rounded border-2 border-dashed font-bold"
        style={{ gridRow: 7, gridColumn: 3, borderColor: lan, color: lan, fontSize: Math.max(8, cs * 0.2), opacity: cat === "all" || cat === "lanthanide" ? 1 : 0.25 }}
        aria-hidden
      >
        57–71
      </div>
      <div
        className="mono flex items-center justify-center rounded border-2 border-dashed font-bold"
        style={{ gridRow: 8, gridColumn: 3, borderColor: act, color: act, fontSize: Math.max(8, cs * 0.2), opacity: cat === "all" || cat === "actinide" ? 1 : 0.25 }}
        aria-hidden
      >
        89–103
      </div>

      {ELEMENTS.map((e) => {
        const catDim = cat !== "all" && e.cat !== cat;
        const searchDim = matches !== null && !matches.has(e.z);
        const hit = matches !== null && matches.has(e.z) && !catDim;
        return (
          <Cell
            key={e.z}
            e={e}
            dim={catDim || searchDim}
            hit={hit}
            fav={favSet.has(e.z)}
            done={doneSet.has(e.z)}
            showName={showName}
            onOpen={onOpen}
          />
        );
      })}
    </div>
  );
}

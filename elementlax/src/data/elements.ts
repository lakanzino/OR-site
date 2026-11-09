import { RAW1 } from "./raw1";
import { RAW2 } from "./raw2";
import { RAW3 } from "./raw3";

export type CategoryId =
  | "alkali"
  | "alkaline"
  | "transition"
  | "post"
  | "metalloid"
  | "nonmetal"
  | "halogen"
  | "noble"
  | "lanthanide"
  | "actinide";

export interface Category {
  id: CategoryId;
  fa: string;
  en: string;
  color: string;
}

// Bold, saturated, clearly distinct category palette
export const CATEGORIES: Category[] = [
  { id: "alkali", fa: "فلزهای قلیایی", en: "Alkali metals", color: "#FF4D4D" },
  { id: "alkaline", fa: "قلیایی خاکی", en: "Alkaline earth metals", color: "#FF9F1C" },
  { id: "transition", fa: "فلزهای واسطه", en: "Transition metals", color: "#FFD60A" },
  { id: "post", fa: "فلزهای پس از واسطه", en: "Post-transition metals", color: "#4ADE80" },
  { id: "metalloid", fa: "شبه‌فلزها", en: "Metalloids", color: "#14E0C8" },
  { id: "nonmetal", fa: "نافلزها", en: "Nonmetals", color: "#38BDF8" },
  { id: "halogen", fa: "هالوژن‌ها", en: "Halogens", color: "#6C7BFF" },
  { id: "noble", fa: "گازهای نجیب", en: "Noble gases", color: "#FF4FD8" },
  { id: "lanthanide", fa: "لانتانیدها", en: "Lanthanides", color: "#C084FC" },
  { id: "actinide", fa: "اکتینیدها", en: "Actinides", color: "#D2A679" },
];

export const CAT_BY_ID: Record<CategoryId, Category> = Object.fromEntries(
  CATEGORIES.map((c) => [c.id, c])
) as Record<CategoryId, Category>;

export type StateId = "gas" | "liquid" | "solid" | "predicted";

export interface Element {
  z: number;
  sym: string;
  en: string;
  fa: string;
  mass: string;
  disc: string;
  year: string;
  etym: string;
  uses: string;
  props: string;
  hist: string;
  cat: CategoryId;
  state: StateId;
  stateFa: string;
  stateEn: string;
  group: number | null;
  period: number;
  row: number;
  col: number;
}

function categoryOf(z: number): CategoryId {
  if ([3, 11, 19, 37, 55, 87].includes(z)) return "alkali";
  if ([4, 12, 20, 38, 56, 88].includes(z)) return "alkaline";
  if (z >= 57 && z <= 71) return "lanthanide";
  if (z >= 89 && z <= 103) return "actinide";
  if ([5, 14, 32, 33, 51, 52].includes(z)) return "metalloid";
  if ([1, 6, 7, 8, 15, 16, 34].includes(z)) return "nonmetal";
  if ([9, 17, 35, 53, 85, 117].includes(z)) return "halogen";
  if ([2, 10, 18, 36, 54, 86, 118].includes(z)) return "noble";
  if ([13, 31, 49, 50, 81, 82, 83, 84, 113, 114, 115, 116].includes(z)) return "post";
  return "transition";
}

function positionOf(z: number): { row: number; col: number; period: number; group: number | null } {
  if (z === 1) return { row: 1, col: 1, period: 1, group: 1 };
  if (z === 2) return { row: 1, col: 18, period: 1, group: 18 };
  if (z <= 4) return { row: 2, col: z - 2, period: 2, group: z - 2 };
  if (z <= 10) return { row: 2, col: z + 8, period: 2, group: z + 8 };
  if (z <= 12) return { row: 3, col: z - 10, period: 3, group: z - 10 };
  if (z <= 18) return { row: 3, col: z, period: 3, group: z };
  if (z <= 36) return { row: 4, col: z - 18, period: 4, group: z - 18 };
  if (z <= 54) return { row: 5, col: z - 36, period: 5, group: z - 36 };
  if (z <= 56) return { row: 6, col: z - 54, period: 6, group: z - 54 };
  if (z <= 71) return { row: 9, col: z - 54, period: 6, group: z === 71 ? 3 : null };
  if (z <= 86) return { row: 6, col: z - 68, period: 6, group: z - 68 };
  if (z <= 88) return { row: 7, col: z - 86, period: 7, group: z - 86 };
  if (z <= 103) return { row: 10, col: z - 86, period: 7, group: z === 103 ? 3 : null };
  return { row: 7, col: z - 100, period: 7, group: z - 100 };
}

function stateOf(z: number): { state: StateId; fa: string; en: string } {
  if ([1, 2, 7, 8, 9, 10, 17, 18, 36, 54, 86].includes(z)) return { state: "gas", fa: "گاز", en: "Gas" };
  if ([35, 80].includes(z)) return { state: "liquid", fa: "مایع", en: "Liquid" };
  if (z === 112 || z === 114)
    return { state: "predicted", fa: "ناشناخته (احتمالاً گاز یا مایع فرار)", en: "Unknown (predicted volatile)" };
  if (z === 85 || z === 87 || z >= 100)
    return { state: "predicted", fa: "ناشناخته (پیش‌بینی: جامد)", en: "Unknown (predicted solid)" };
  return { state: "solid", fa: "جامد", en: "Solid" };
}

export const ELEMENTS: Element[] = [...RAW1, ...RAW2, ...RAW3].map((r) => {
  const [z, sym, en, fa, mass, disc, year, etym, uses, props, hist] = r;
  const p = positionOf(z);
  const s = stateOf(z);
  return {
    z, sym, en, fa, mass, disc, year, etym, uses, props, hist,
    cat: categoryOf(z),
    state: s.state,
    stateFa: s.fa,
    stateEn: s.en,
    group: p.group,
    period: p.period,
    row: p.row,
    col: p.col,
  };
});

export const BY_Z: Record<number, Element> = Object.fromEntries(ELEMENTS.map((e) => [e.z, e]));

export const ELEMENT_COUNT = ELEMENTS.length; // must be 118

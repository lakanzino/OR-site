import { COUNTRIES, type Country } from "@/data/countries";

const faNum = new Intl.NumberFormat("fa-IR", { maximumFractionDigits: 1 });
const faInt = new Intl.NumberFormat("fa-IR", { maximumFractionDigits: 0 });
const enInt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 });

/** Persian digits for any number. */
export const toFa = (n: number, frac = false) => (frac ? faNum.format(n) : faInt.format(n));

/** "۱۴۱٫۹ میلیون" style compact Persian population. */
export function popFa(n: number): string {
  if (n >= 1e9) return `${faNum.format(n / 1e9)} میلیارد`;
  if (n >= 1e6) return `${faNum.format(n / 1e6)} میلیون`;
  if (n >= 1e3) return `${faNum.format(n / 1e3)} هزار`;
  return `${faInt.format(n)} نفر`;
}
export const popEn = (n: number) => enInt.format(n);

export function areaFa(a: number): string {
  return a < 10 ? faNum.format(a) : faInt.format(a);
}
export function areaEn(a: number): string {
  return a < 10 ? String(a) : enInt.format(a);
}

export function density(c: Country): number {
  return c.population / c.areaKm2;
}

export function shuffle<T>(arr: readonly T[]): T[] {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

export function byIso(isos: string[]): Country[] {
  const set = new Set(isos);
  return COUNTRIES.filter((c) => set.has(c.isoCode));
}

/** Computed (always-correct) facts derived from the data table. */
export function computedFacts(c: Country): string[] {
  const out: string[] = [];
  out.push(`از نظر مساحت، رتبهٔ ${toFa(c.areaRank)} را در میان ۱۹۵ کشور دارد.`);
  out.push(`از نظر جمعیت، رتبهٔ ${toFa(c.popRank)} را در میان ۱۹۵ کشور دارد.`);
  const d = density(c);
  out.push(`تراکم جمعیت تقریبی: ${toFa(Math.round(d))} نفر در هر کیلومتر مربع.`);
  return out;
}

export { shareText } from "./native";

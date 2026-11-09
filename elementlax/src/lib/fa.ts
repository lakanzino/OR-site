const FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹";

/** Convert Latin digits to Persian digits. Keeps isotope names / formulas (e.g. Tc-99m, CO2) untouched. */
export function toFa(input: string | number): string {
  return String(input).replace(/[A-Za-z]+-?\d+[a-z]?|\d+(?:\.\d+)?/g, (m) => {
    if (/^[A-Za-z]/.test(m)) return m;
    return m.replace(/\d/g, (d) => FA_DIGITS[Number(d)]).replace(".", "٫");
  });
}

const AR_DIGITS = "٠١٢٣٤٥٦٧٨٩";

/** Normalise text for searching: Persian/Arabic digits, ی/ک variants, ZWNJ, case. */
export function norm(s: string): string {
  return s
    .toLowerCase()
    .replace(/[۰-۹]/g, (d) => String(FA_DIGITS.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String(AR_DIGITS.indexOf(d)))
    .replace(/[يى]/g, "ی")
    .replace(/ك/g, "ک")
    .replace(/[ؤ]/g, "و")
    .replace(/[أإآ]/g, "ا")
    .replace(/[\u200c\u200f\u064B-\u065F\s\-_.()]/g, "");
}

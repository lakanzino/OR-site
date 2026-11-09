// Tiny persistent store (localStorage) – 100% local, zero tracking.
// Exposed through useSyncExternalStore so every component re-renders efficiently.
import { useSyncExternalStore } from "react";

export type Theme = "dark" | "light";

export interface AppState {
  favorites: string[];
  learned: string[];
  wrong: string[];
  stats: {
    played: number;
    answered: number;
    correct: number;
    xp: number;
    best: Record<string, number>; // quiz type -> best percentage
  };
  settings: {
    theme: Theme;
    fontScale: number;
    pinHash: string | null;
    onboarded: boolean;
  };
}

const KEY = "globelax:v1";

const defaults: AppState = {
  favorites: [],
  learned: [],
  wrong: [],
  stats: { played: 0, answered: 0, correct: 0, xp: 0, best: {} },
  settings: { theme: "dark", fontScale: 1, pinHash: null, onboarded: false },
};

function load(): AppState {
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return defaults;
    const p = JSON.parse(raw);
    return {
      ...defaults,
      ...p,
      stats: { ...defaults.stats, ...(p.stats ?? {}) },
      settings: { ...defaults.settings, ...(p.settings ?? {}) },
    };
  } catch {
    return defaults;
  }
}

let state: AppState = load();
const listeners = new Set<() => void>();

function commit(next: AppState) {
  state = next;
  try {
    localStorage.setItem(KEY, JSON.stringify(state));
  } catch {
    /* storage full / blocked – app keeps working in-memory */
  }
  listeners.forEach((l) => l());
}

export function getState() {
  return state;
}

export function useAppState(): AppState {
  return useSyncExternalStore(
    (cb) => {
      listeners.add(cb);
      return () => listeners.delete(cb);
    },
    () => state,
  );
}

const toggleIn = (arr: string[], v: string) => (arr.includes(v) ? arr.filter((x) => x !== v) : [...arr, v]);

export const actions = {
  toggleFavorite: (iso: string) => commit({ ...state, favorites: toggleIn(state.favorites, iso) }),
  toggleLearned: (iso: string) =>
    commit({
      ...state,
      learned: toggleIn(state.learned, iso),
      wrong: state.wrong.filter((x) => x !== iso),
    }),
  setLearned: (iso: string, value: boolean) => {
    const has = state.learned.includes(iso);
    if (value === has) return;
    commit({ ...state, learned: value ? [...state.learned, iso] : state.learned.filter((x) => x !== iso) });
  },
  addWrong: (isos: string[]) =>
    commit({ ...state, wrong: Array.from(new Set([...state.wrong, ...isos])) }),
  removeWrong: (isos: string[]) =>
    commit({ ...state, wrong: state.wrong.filter((x) => !isos.includes(x)) }),
  recordQuiz: (type: string, correct: number, total: number) => {
    const pct = total ? Math.round((correct / total) * 100) : 0;
    commit({
      ...state,
      stats: {
        played: state.stats.played + 1,
        answered: state.stats.answered + total,
        correct: state.stats.correct + correct,
        xp: state.stats.xp + correct * 10 + (pct === 100 ? 50 : 0),
        best: { ...state.stats.best, [type]: Math.max(state.stats.best[type] ?? 0, pct) },
      },
    });
  },
  addXp: (n: number) => commit({ ...state, stats: { ...state.stats, xp: state.stats.xp + n } }),
  setSettings: (s: Partial<AppState["settings"]>) => commit({ ...state, settings: { ...state.settings, ...s } }),
  clearProgress: () =>
    commit({ ...defaults, settings: state.settings, stats: { ...defaults.stats, best: {} } }),
  exportJson: () =>
    JSON.stringify(
      {
        app: "Globe.Lax",
        version: "1.0.0",
        exportedAt: new Date().toISOString(),
        favorites: state.favorites,
        learned: state.learned,
        wrong: state.wrong,
        stats: state.stats,
        settings: { theme: state.settings.theme, fontScale: state.settings.fontScale },
      },
      null,
      2,
    ),
  importJson: (text: string): boolean => {
    try {
      const d = JSON.parse(text);
      if (d?.app !== "Globe.Lax") return false;
      const arr = (v: unknown) => (Array.isArray(v) ? v.filter((x) => typeof x === "string") : []);
      commit({
        ...state,
        favorites: arr(d.favorites),
        learned: arr(d.learned),
        wrong: arr(d.wrong),
        stats: { ...defaults.stats, ...(d.stats ?? {}) },
        settings: {
          ...state.settings,
          theme: d.settings?.theme === "light" ? "light" : "dark",
          fontScale: Math.min(1.4, Math.max(0.85, Number(d.settings?.fontScale) || 1)),
        },
      });
      return true;
    } catch {
      return false;
    }
  },
};

/** SHA-256 when available (secure contexts) with a simple fallback. */
export async function hashPin(pin: string): Promise<string> {
  const data = "globelax|" + pin;
  try {
    if (crypto?.subtle) {
      const buf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(data));
      return Array.from(new Uint8Array(buf)).map((b) => b.toString(16).padStart(2, "0")).join("");
    }
  } catch {
    /* fall through */
  }
  let h = 5381;
  for (let i = 0; i < data.length; i++) h = (h * 33) ^ data.charCodeAt(i);
  return "x" + (h >>> 0).toString(16);
}

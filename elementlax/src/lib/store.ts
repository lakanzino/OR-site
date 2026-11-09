import { useSyncExternalStore } from "react";

export type Theme = "dark" | "light";

export interface AppState {
  theme: Theme;
  onboarded: boolean;
  favs: number[];
  learned: number[];
  pin: string | null; // hashed
  cs: number; // table cell size (px)
}

const KEY = "elementlax.v1";

const defaults: AppState = {
  theme: "dark",
  onboarded: false,
  favs: [],
  learned: [],
  pin: null,
  cs: 0,
};

function load(): AppState {
  try {
    const raw = localStorage.getItem(KEY);
    if (raw) return { ...defaults, ...JSON.parse(raw) };
  } catch {
    /* ignore */
  }
  return { ...defaults };
}

let state: AppState = load();
const listeners = new Set<() => void>();

function persist() {
  try {
    localStorage.setItem(KEY, JSON.stringify(state));
  } catch {
    /* storage unavailable */
  }
}

export function setState(patch: Partial<AppState>) {
  state = { ...state, ...patch };
  persist();
  applyTheme(state.theme);
  listeners.forEach((l) => l());
}

export function applyTheme(t: Theme) {
  document.documentElement.setAttribute("data-theme", t);
  const m = document.querySelector('meta[name="theme-color"]');
  if (m) m.setAttribute("content", t === "dark" ? "#03070a" : "#f1f5fb");
  try {
    (
      window as unknown as {
        ElementLaxNative?: { setThemeMode?: (isDark: boolean) => void };
      }
    ).ElementLaxNative?.setThemeMode?.(t === "dark");
  } catch {
    /* ignore outside Android wrapper */
  }
}

export function useStore(): AppState {
  return useSyncExternalStore(
    (cb) => {
      listeners.add(cb);
      return () => listeners.delete(cb);
    },
    () => state
  );
}

export function toggleIn(list: number[], z: number): number[] {
  return list.includes(z) ? list.filter((x) => x !== z) : [...list, z];
}

export function resetAll() {
  state = { ...defaults, theme: state.theme, onboarded: true };
  persist();
  listeners.forEach((l) => l());
}

/** Lightweight hash for the optional PIN lock (casual privacy, not cryptographic). */
export function hashPin(pin: string): string {
  let h = 5381;
  const s = "elx:" + pin;
  for (let i = 0; i < s.length; i++) h = ((h << 5) + h + s.charCodeAt(i)) | 0;
  return String(h >>> 0);
}

applyTheme(state.theme);

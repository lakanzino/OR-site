// Thin, dependency-free bridge to the Android host (android/…/MainActivity.java).
// On the web every helper degrades to a no-op, so the same bundle runs everywhere.
import { useEffect } from "react";

type GlobeNative = {
  share?: (text: string, title: string) => void;
  setTheme?: (dark: boolean) => void;
  appVersion?: () => string;
  isNative?: () => boolean;
};

function bridge(): GlobeNative | null {
  const w = window as unknown as { GlobeNative?: GlobeNative };
  return w.GlobeNative ?? null;
}

export const isNative = (): boolean => {
  try {
    return bridge()?.isNative?.() === true;
  } catch {
    return false;
  }
};

/** Native share sheet when available, otherwise the Web Share API / clipboard. */
export async function shareText(text: string, title: string): Promise<"shared" | "copied" | "failed"> {
  const b = bridge();
  if (b?.share) {
    b.share(text, title);
    return "shared";
  }
  try {
    if (navigator.share) {
      await navigator.share({ title, text });
      return "shared";
    }
    await navigator.clipboard.writeText(text);
    return "copied";
  } catch {
    return "failed";
  }
}

/** Keep the Android status / navigation bars in sync with the app theme. */
export function useSystemBarTheme(dark: boolean): void {
  useEffect(() => {
    try {
      bridge()?.setTheme?.(dark);
    } catch {
      /* web build without the bridge – ignore */
    }
    const meta = document.querySelector('meta[name="theme-color"]');
    meta?.setAttribute("content", dark ? "#040c24" : "#f7f5ee");
  }, [dark]);
}

/** App version reported by the native shell (falls back to the bundled constant). */
export function nativeVersion(fallback: string): string {
  try {
    return bridge()?.appVersion?.() || fallback;
  } catch {
    return fallback;
  }
}

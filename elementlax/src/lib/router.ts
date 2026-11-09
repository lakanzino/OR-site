import { useSyncExternalStore } from "react";

// Hash based routing => native back button / gesture works everywhere.
function subscribe(cb: () => void) {
  window.addEventListener("hashchange", cb);
  return () => window.removeEventListener("hashchange", cb);
}
function snapshot() {
  return window.location.hash.replace(/^#\/?/, "");
}

export function useRoute(): string[] {
  const h = useSyncExternalStore(subscribe, snapshot, () => "");
  return h ? h.split("/") : [];
}

export function go(path: string) {
  window.location.hash = "#/" + path;
}

export function replace(path: string) {
  window.history.replaceState(null, "", "#/" + path);
  window.dispatchEvent(new HashChangeEvent("hashchange"));
}

export function back(fallback = "") {
  if (window.history.length > 1) window.history.back();
  else replace(fallback);
}

// Minimal hash router – back button works, deep links work, zero dependencies.
import { useEffect, useState } from "react";

export interface Route {
  path: string[]; // e.g. ["country","IR"]
  params: URLSearchParams;
}

function parse(): Route {
  const h = window.location.hash.replace(/^#\/?/, "");
  const [p, q] = h.split("?");
  return { path: p.split("/").filter(Boolean), params: new URLSearchParams(q ?? "") };
}

export function useRoute(): Route {
  const [r, setR] = useState<Route>(parse);
  useEffect(() => {
    const on = () => {
      setR(parse());
      window.scrollTo({ top: 0 });
    };
    window.addEventListener("hashchange", on);
    return () => window.removeEventListener("hashchange", on);
  }, []);
  return r;
}

export function go(to: string, replace = false) {
  const url = "#/" + to.replace(/^\/+/, "");
  if (replace) window.location.replace(url);
  else window.location.hash = url;
}

export function back() {
  if (window.history.length > 1) window.history.back();
  else go("");
}

/** Ordered list of ISO codes the user is currently browsing (for prev/next on detail). */
let navList: string[] = [];
export const setNavList = (l: string[]) => {
  navList = l;
};
export const getNavList = () => navList;

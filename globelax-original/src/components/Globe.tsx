// Interactive 3D-style globe drawn on <canvas> (orthographic projection, d3-geo).
// Offline: the Natural Earth 110m topology is bundled. Drag to spin (with inertia), tap a country.
// Performance: single canvas, draw-on-demand, paused when off-screen / tab hidden, DPR capped at 2.
import { useEffect, useMemo, useRef } from "react";
import { geoCentroid, geoContains, geoGraticule10, geoOrthographic, geoPath } from "d3-geo";
import { feature } from "topojson-client";
import countries from "i18n-iso-countries";
import topo from "world-atlas/countries-110m.json";
import { COUNTRY_MAP } from "@/data/countries";
import { CONTINENTS } from "@/data/dictionaries";
import { useAppState } from "@/lib/store";

interface Shape {
  iso: string | null;
  f: any;
  color: string;
}

let SHAPES: Shape[] | null = null;
function shapes(): Shape[] {
  if (SHAPES) return SHAPES;
  const fc: any = feature(topo as any, (topo as any).objects.countries);
  SHAPES = fc.features.map((f: any) => {
    let iso: string | null = null;
    try {
      iso = (f.id && countries.numericToAlpha2(String(f.id))) || null;
    } catch {
      iso = null;
    }
    const c = iso ? COUNTRY_MAP[iso] : undefined;
    return { iso: c ? iso : null, f, color: c ? CONTINENTS[c.continent].color : "#7d8bb5" };
  });
  return SHAPES!;
}

/** True when the 110m map has a polygon for this country (very small states are absent). */
export function hasShape(iso: string): boolean {
  return shapes().some((s) => s.iso === iso);
}

interface Props {
  selected?: string | null;
  onSelect?: (iso: string | null) => void;
  autoRotate?: boolean;
  lockTouch?: boolean; // true = fully capture touch (map page); false = allow vertical page scroll
  highlightContinent?: string | null;
  className?: string;
}

export default function Globe({ selected, onSelect, autoRotate = true, lockTouch = false, highlightContinent, className = "" }: Props) {
  const wrap = useRef<HTMLDivElement>(null);
  const cv = useRef<HTMLCanvasElement>(null);
  const { settings } = useAppState();
  const list = useMemo(shapes, []);

  // Mutable animation state (kept out of React state on purpose → no re-renders per frame)
  const st = useRef({
    rot: [-45, -22] as [number, number],
    vel: 0,
    dragging: false,
    down: null as null | { x: number; y: number; t: number; moved: number },
    last: { x: 0, y: 0 },
    dirty: true,
    visible: true,
    anim: null as null | { from: [number, number]; to: [number, number]; t0: number },
    selected: selected ?? null,
    hc: highlightContinent ?? null,
    auto: autoRotate,
    colors: { a: "#2a55c9", b: "#0a1d5c", accent: "#ffc400" },
  });

  // keep props in the mutable state
  useEffect(() => {
    const s = st.current;
    s.selected = selected ?? null;
    s.hc = highlightContinent ?? null;
    s.auto = autoRotate;
    s.dirty = true;
    if (selected) {
      const sh = list.find((x) => x.iso === selected);
      if (sh) {
        const [lon, lat] = geoCentroid(sh.f);
        // pick shortest rotation path
        let target = -lon;
        const from = s.rot[0];
        while (target - from > 180) target -= 360;
        while (target - from < -180) target += 360;
        s.anim = { from: [...s.rot], to: [target, Math.max(-55, Math.min(55, -lat))], t0: performance.now() };
        s.vel = 0;
      }
    }
  }, [selected, autoRotate, highlightContinent, list]);

  useEffect(() => {
    const cs = getComputedStyle(document.documentElement);
    st.current.colors = {
      a: cs.getPropertyValue("--ocean-a").trim() || "#2a55c9",
      b: cs.getPropertyValue("--ocean-b").trim() || "#0a1d5c",
      accent: settings.theme === "light" ? "#e5202e" : "#ffc400",
    };
    st.current.dirty = true;
  }, [settings.theme]);

  useEffect(() => {
    const canvas = cv.current!;
    const wrapEl = wrap.current!;
    const ctx = canvas.getContext("2d")!;
    const s = st.current;
    const proj = geoOrthographic().clipAngle(90).precision(0.6);
    const path = geoPath(proj, ctx);
    const grat = geoGraticule10();
    const sphere = { type: "Sphere" } as any;
    let W = 0;
    let raf = 0;
    let lastT = performance.now();
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const resize = () => {
      const r = wrapEl.getBoundingClientRect();
      const size = Math.max(120, Math.round(Math.min(r.width, r.height || r.width)));
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      W = size;
      canvas.width = size * dpr;
      canvas.height = size * dpr;
      canvas.style.width = size + "px";
      canvas.style.height = size + "px";
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      proj.translate([size / 2, size / 2]).scale(size / 2 - 4);
      s.dirty = true;
    };
    const ro = new ResizeObserver(resize);
    ro.observe(wrapEl);
    resize();

    const io = new IntersectionObserver(([e]) => {
      s.visible = e.isIntersecting;
      if (s.visible) s.dirty = true;
    });
    io.observe(wrapEl);

    function draw() {
      const R = W / 2 - 4;
      proj.rotate([s.rot[0], s.rot[1]]);
      ctx.clearRect(0, 0, W, W);

      // ocean
      const g = ctx.createRadialGradient(W * 0.38, W * 0.3, R * 0.1, W / 2, W / 2, R * 1.05);
      g.addColorStop(0, s.colors.a);
      g.addColorStop(1, s.colors.b);
      ctx.beginPath();
      path(sphere);
      ctx.fillStyle = g;
      ctx.fill();

      // graticule
      ctx.beginPath();
      path(grat);
      ctx.strokeStyle = "rgba(255,255,255,0.10)";
      ctx.lineWidth = 0.6;
      ctx.stroke();

      // countries
      ctx.lineJoin = "round";
      for (const sh of list) {
        const isSel = sh.iso !== null && sh.iso === s.selected;
        const dim = s.hc && sh.iso && COUNTRY_MAP[sh.iso].continent !== s.hc;
        ctx.beginPath();
        path(sh.f);
        ctx.globalAlpha = dim ? 0.28 : 1;
        ctx.fillStyle = isSel ? s.colors.accent : sh.color;
        ctx.fill();
        ctx.globalAlpha = 1;
        ctx.strokeStyle = isSel ? "#ffffff" : "rgba(5,12,40,0.55)";
        ctx.lineWidth = isSel ? 2 : 0.6;
        ctx.stroke();
      }

      // sphere shading → 3D volume (terminator darkening + specular)
      const sh1 = ctx.createRadialGradient(W * 0.4, W * 0.35, R * 0.45, W / 2, W / 2, R);
      sh1.addColorStop(0, "rgba(0,0,0,0)");
      sh1.addColorStop(0.75, "rgba(2,8,30,0.22)");
      sh1.addColorStop(1, "rgba(2,8,30,0.62)");
      ctx.beginPath();
      path(sphere);
      ctx.fillStyle = sh1;
      ctx.fill();
      const sp = ctx.createRadialGradient(W * 0.32, W * 0.24, 0, W * 0.32, W * 0.24, R * 0.7);
      sp.addColorStop(0, "rgba(255,255,255,0.38)");
      sp.addColorStop(1, "rgba(255,255,255,0)");
      ctx.fillStyle = sp;
      ctx.beginPath();
      path(sphere);
      ctx.fill();
      ctx.beginPath();
      path(sphere);
      ctx.strokeStyle = "rgba(255,255,255,0.28)";
      ctx.lineWidth = 1.5;
      ctx.stroke();
    }

    function frame(now: number) {
      raf = requestAnimationFrame(frame);
      const dt = Math.min(48, now - lastT);
      lastT = now;
      if (!s.visible || document.hidden) return;

      if (s.anim) {
        const p = Math.min(1, (now - s.anim.t0) / 900);
        const e = 1 - Math.pow(1 - p, 3);
        s.rot = [
          s.anim.from[0] + (s.anim.to[0] - s.anim.from[0]) * e,
          s.anim.from[1] + (s.anim.to[1] - s.anim.from[1]) * e,
        ];
        if (p >= 1) s.anim = null;
        s.dirty = true;
      } else if (!s.dragging) {
        if (Math.abs(s.vel) > 0.01) {
          s.rot[0] += s.vel * (dt / 16);
          s.vel *= 0.95;
          s.dirty = true;
        } else if (s.auto && !reduce && !s.selected) {
          s.rot[0] += 0.12 * (dt / 16);
          s.dirty = true;
        }
      }
      if (s.dirty) {
        s.dirty = false;
        draw();
      }
    }
    raf = requestAnimationFrame(frame);

    // ── pointer interaction ──
    const pos = (e: PointerEvent) => {
      const r = canvas.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    };
    const down = (e: PointerEvent) => {
      const p = pos(e);
      s.down = { ...p, t: performance.now(), moved: 0 };
      s.last = p;
      s.dragging = true;
      s.anim = null;
      s.vel = 0;
      canvas.setPointerCapture(e.pointerId);
    };
    const move = (e: PointerEvent) => {
      if (!s.dragging || !s.down) return;
      const p = pos(e);
      const dx = p.x - s.last.x;
      const dy = p.y - s.last.y;
      s.down.moved += Math.abs(dx) + Math.abs(dy);
      const k = 180 / (W * Math.PI * 0.5); // degrees per px
      s.rot[0] += dx * k * 2;
      s.rot[1] = Math.max(-70, Math.min(70, s.rot[1] - dy * k * 2));
      s.vel = dx * k * 2;
      s.last = p;
      s.dirty = true;
    };
    const up = (e: PointerEvent) => {
      if (!s.dragging) return;
      s.dragging = false;
      const d = s.down;
      s.down = null;
      try {
        canvas.releasePointerCapture(e.pointerId);
      } catch {
        /* ignore */
      }
      if (d && d.moved < 8 && performance.now() - d.t < 500) {
        proj.rotate([s.rot[0], s.rot[1]]);
        const ll = proj.invert?.([d.x, d.y]);
        let hit: string | null = null;
        if (ll) {
          for (const sh of list) {
            if (sh.iso && geoContains(sh.f, ll as [number, number])) {
              hit = sh.iso;
              break;
            }
          }
        }
        onSelect?.(hit);
      }
    };
    const cancel = () => {
      s.dragging = false;
      s.down = null;
    };
    canvas.addEventListener("pointerdown", down);
    canvas.addEventListener("pointermove", move);
    canvas.addEventListener("pointerup", up);
    canvas.addEventListener("pointercancel", cancel);

    return () => {
      cancelAnimationFrame(raf);
      ro.disconnect();
      io.disconnect();
      canvas.removeEventListener("pointerdown", down);
      canvas.removeEventListener("pointermove", move);
      canvas.removeEventListener("pointerup", up);
      canvas.removeEventListener("pointercancel", cancel);
    };
  }, [list, onSelect]);

  return (
    <div ref={wrap} className={`relative aspect-square w-full ${className}`}>
      <canvas
        ref={cv}
        className="absolute inset-0 m-auto cursor-grab rounded-full active:cursor-grabbing"
        style={{
          touchAction: lockTouch ? "none" : "pan-y",
          boxShadow: "0 0 60px 6px var(--glow), 0 30px 50px -20px var(--shadow)",
        }}
        aria-label="Interactive globe / کره زمین تعاملی"
        role="img"
      />
    </div>
  );
}

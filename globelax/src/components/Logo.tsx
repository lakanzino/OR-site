// Globe.Lax logo, rendered from the same artwork as /public/app-logo.svg.
// A glossy "tech globe": deep navy sphere, gold continents, neon red/yellow orbit.
// Kept as an inline SVG component so the mark stays crisp at any size and 100 % offline.
import { useId } from "react";
import { LAND_PATH } from "./landPath";

export function Logo({ size = 48, className = "" }: { size?: number; className?: string }) {
  const uid = useId().replace(/:/g, "");
  const id = (n: string) => `${n}${uid}`;
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 512 512"
      className={className}
      role="img"
      aria-label="Globe.Lax"
    >
      <defs>
        <radialGradient id={id("sphere")} cx="33%" cy="23%" r="90%">
          <stop offset="0" stopColor="#3F6BF0" />
          <stop offset="0.28" stopColor="#18349B" />
          <stop offset="0.6" stopColor="#0A1750" />
          <stop offset="1" stopColor="#030820" />
        </radialGradient>
        <linearGradient id={id("gold")} x1="0.1" y1="0" x2="0.9" y2="1">
          <stop offset="0" stopColor="#FFF7CC" />
          <stop offset="0.34" stopColor="#FFD22E" />
          <stop offset="1" stopColor="#FF9A00" />
        </linearGradient>
        <linearGradient id={id("red")} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#FF787F" />
          <stop offset="0.45" stopColor="#EF2B3A" />
          <stop offset="1" stopColor="#9E0B17" />
        </linearGradient>
        <radialGradient id={id("gloss")} cx="30%" cy="19%" r="50%">
          <stop offset="0" stopColor="#fff" stopOpacity="0.58" />
          <stop offset="0.46" stopColor="#fff" stopOpacity="0.12" />
          <stop offset="1" stopColor="#fff" stopOpacity="0" />
        </radialGradient>
        <radialGradient id={id("shade")} cx="35%" cy="28%" r="82%">
          <stop offset="0" stopColor="#000" stopOpacity="0" />
          <stop offset="0.62" stopColor="#02061C" stopOpacity="0.1" />
          <stop offset="1" stopColor="#02061C" stopOpacity="0.7" />
        </radialGradient>
        <clipPath id={id("ball")}>
          <circle cx="256" cy="256" r="159" />
        </clipPath>
      </defs>

      <rect width="512" height="512" rx="116" fill="#050B24" />
      <ellipse cx="256" cy="436" rx="126" ry="17" fill="#000" opacity="0.45" />

      {/* back half of the orbit */}
      <g fill="none" strokeLinecap="round" opacity="0.3">
        <path d="M97.7 271.2A219 56 -20 0 1 387 165.9M387 165.9A219 56 -20 0 1 414.3 240.8" stroke={`url(#${id("red")})`} strokeWidth="11" />
      </g>

      <circle cx="256" cy="256" r="159" fill={`url(#${id("sphere")})`} />
      <g clipPath={`url(#${id("ball")})`}>
        <g fill="none" stroke="#9DBBFF" strokeOpacity="0.14" strokeWidth="1.6">
          <ellipse cx="256" cy="256" rx="159" ry="42" />
          <ellipse cx="256" cy="256" rx="159" ry="86" />
          <ellipse cx="256" cy="256" rx="159" ry="128" />
          <ellipse cx="256" cy="256" rx="42" ry="159" />
          <ellipse cx="256" cy="256" rx="86" ry="159" />
          <ellipse cx="256" cy="256" rx="128" ry="159" />
          <line x1="97" y1="256" x2="415" y2="256" />
        </g>
        <path d={LAND_PATH} fill={`url(#${id("gold")})`} stroke="#FFE066" strokeOpacity="0.5" strokeWidth="1.1" />
        <circle cx="256" cy="256" r="159" fill={`url(#${id("shade")})`} />
        <circle cx="256" cy="256" r="159" fill={`url(#${id("gloss")})`} />
      </g>
      <circle cx="256" cy="256" r="159" fill="none" stroke="#8FB0FF" strokeOpacity="0.34" strokeWidth="2.6" />

      {/* front half of the orbit */}
      <g fill="none" strokeLinecap="round">
        <path d="M414.3 240.8A219 56 -20 0 1 125 346.1M125 346.1A219 56 -20 0 1 97.7 271.2" stroke={`url(#${id("red")})`} strokeWidth="13" opacity="0.95" />
        <path d="M414.3 240.8A219 56 -20 0 1 125 346.1M125 346.1A219 56 -20 0 1 97.7 271.2" stroke={`url(#${id("gold")})`} strokeWidth="6" opacity="0.9" />
      </g>

      {/* red marker on Iran */}
      <circle cx="339.5" cy="211.6" r="17.5" fill="#EF2B3A" opacity="0.32" />
      <circle cx="339.5" cy="211.6" r="11" fill={`url(#${id("red")})`} stroke="#FFFFFF" strokeWidth="2.6" strokeOpacity="0.92" />
      <circle cx="336" cy="208" r="3.2" fill="#FFFFFF" opacity="0.75" />

      {/* orbit satellite */}
      <circle cx="414.3" cy="240.8" r="8.5" fill="#FFD22E" />
      <circle cx="414.3" cy="240.8" r="3.4" fill="#FFF9DF" />
    </svg>
  );
}

// Globe.Lax logo – glossy 3D-ish globe: navy sphere, yellow ring/continents, red accent.
// Same artwork as /public/app-logo.svg (used for the favicon and installable icons).
import { useId } from "react";

export function Logo({ size = 48, className = "" }: { size?: number; className?: string }) {
  const id = useId().replace(/:/g, "");
  return (
    <svg width={size} height={size} viewBox="0 0 512 512" className={className} role="img" aria-label="Globe.Lax logo">
      <defs>
        <radialGradient id={`${id}s`} cx="35%" cy="28%" r="80%">
          <stop offset="0" stopColor="#4C78F0" />
          <stop offset="0.45" stopColor="#14307F" />
          <stop offset="1" stopColor="#050F33" />
        </radialGradient>
        <linearGradient id={`${id}y`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#FFE680" />
          <stop offset="1" stopColor="#FFB800" />
        </linearGradient>
        <linearGradient id={`${id}r`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#FF5461" />
          <stop offset="1" stopColor="#C4101E" />
        </linearGradient>
        <radialGradient id={`${id}g`} cx="32%" cy="22%" r="45%">
          <stop offset="0" stopColor="#fff" stopOpacity="0.75" />
          <stop offset="1" stopColor="#fff" stopOpacity="0" />
        </radialGradient>
        <clipPath id={`${id}c`}>
          <circle cx="256" cy="256" r="178" />
        </clipPath>
      </defs>
      <rect width="512" height="512" rx="112" fill="#071544" />
      <ellipse cx="256" cy="448" rx="150" ry="22" fill="#000" opacity="0.35" />
      <circle cx="256" cy="256" r="178" fill={`url(#${id}s)`} />
      <g clipPath={`url(#${id}c)`}>
        <path d="M110 190c30-40 80-58 118-44 18 8 6 34-14 44-22 12-18 40-44 46-26 6-48-8-60-46z" fill={`url(#${id}y)`} />
        <path d="M268 250c34-8 70 6 98 36 26 30 22 70-4 96-18 18-42 20-54-2-14-26-34-30-44-56-8-22-6-64 4-74z" fill={`url(#${id}y)`} />
        <path d="M300 120c22-10 54-4 74 18-24 4-34 22-52 26-14 4-34-10-22-44z" fill={`url(#${id}r)`} />
        <g fill="none" stroke="#fff" strokeOpacity="0.14" strokeWidth="3">
          <ellipse cx="256" cy="256" rx="178" ry="70" />
          <ellipse cx="256" cy="256" rx="70" ry="178" />
          <line x1="78" y1="256" x2="434" y2="256" />
        </g>
      </g>
      <circle cx="256" cy="256" r="178" fill={`url(#${id}g)`} />
      <ellipse cx="256" cy="256" rx="226" ry="64" fill="none" stroke={`url(#${id}r)`} strokeWidth="18" strokeLinecap="round" transform="rotate(-24 256 256)" strokeDasharray="330 600" strokeDashoffset="-70" />
      <ellipse cx="256" cy="256" rx="226" ry="64" fill="none" stroke={`url(#${id}y)`} strokeWidth="10" strokeLinecap="round" transform="rotate(-24 256 256)" strokeDasharray="250 800" strokeDashoffset="-520" />
      <circle cx="256" cy="256" r="178" fill="none" stroke="#fff" strokeOpacity="0.2" strokeWidth="3" />
    </svg>
  );
}

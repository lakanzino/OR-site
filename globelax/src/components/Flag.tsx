// Offline SVG flags (country-flag-icons, MIT) rendered as cached data-URIs.
import { memo } from "react";
import * as flags from "country-flag-icons/string/3x2";

const cache = new Map<string, string>();

function flagSrc(iso: string): string | null {
  const hit = cache.get(iso);
  if (hit) return hit;
  const svg = (flags as Record<string, string>)[iso];
  if (!svg) return null;
  const uri = "data:image/svg+xml;utf8," + encodeURIComponent(svg);
  cache.set(iso, uri);
  return uri;
}

interface Props {
  iso: string;
  emoji?: string;
  className?: string;
  alt?: string;
}

/** Flag with 3:2 frame; falls back to emoji if an SVG is missing. */
export const Flag = memo(function Flag({ iso, emoji, className = "", alt = "" }: Props) {
  const src = flagSrc(iso);
  return (
    <div className={`flag-frame ${className}`} role="img" aria-label={alt || `flag ${iso}`}>
      {src ? (
        <img src={src} alt="" className="flag-img" loading="lazy" decoding="async" draggable={false} />
      ) : (
        <div className="flex h-full w-full items-center justify-center text-5xl">{emoji}</div>
      )}
    </div>
  );
});

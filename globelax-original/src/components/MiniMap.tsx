import Globe, { hasShape } from "./Globe";

/** Small non-spinning globe centred on a country (used on the detail page). */
export default function MiniMap({ iso }: { iso: string }) {
  if (!hasShape(iso)) return null;
  return (
    <div className="mx-auto w-full max-w-[260px]">
      <Globe selected={iso} autoRotate={false} />
    </div>
  );
}

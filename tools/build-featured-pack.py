#!/usr/bin/env python3
"""Build the 2026-10 featured-image replacement pack.

Reads the canonical mapping in data/featured-images-2026-10.json and:
  1. fits every source image (1200x800) WITHOUT cropping onto a white
     1200x675 canvas (contain fit; white side padding), and writes the result
     to content/images/featured/<slug>.webp (plus a draft featured.webp copy
     when the draft folder exists);
  2. stages extra (alternate) images under tmp-img/extras/;
  3. emits the WordPress alt manifest consumed by inc/image-bulk-replace.php;
  4. zips the importable pack to releases/featured-images-batch-2026-10.zip
     and the alternates to releases/featured-images-batch-2026-10-extras.zip.

No pixel of the source artwork is cropped: the whole picture is scaled down
and centred, with the empty margin filled white so the output matches the
site's 1200x675 (16:9) featured-image standard.
"""
from __future__ import annotations

import json
import shutil
import zipfile
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "data" / "featured-images-2026-10.json"
SRC_DIR = ROOT / "tmp-img" / "source-2026-10"
LIB_DIR = ROOT / "content" / "images" / "featured"
DRAFTS_DIR = ROOT / "content" / "drafts"
TMP_EXTRAS = ROOT / "tmp-img" / "extras"
RELEASES = ROOT / "releases"
MAIN_ZIP = RELEASES / "featured-images-batch-2026-10.zip"
EXTRA_ZIP = RELEASES / "featured-images-batch-2026-10-extras.zip"

CANVAS_W, CANVAS_H = 1200, 675
CANVAS_BG = (255, 255, 255)
WEBP_QUALITY = 85
SIZE_LIMIT_KB = 180


def fit_on_white_canvas(src: Path, dst: Path) -> tuple[int, int, int]:
    """Contain-fit src onto a white 1200x675 canvas; return (w, h, margin)."""
    with Image.open(src) as im:
        im = im.convert("RGB")
        sw, sh = im.size
        scale = min(CANVAS_W / sw, CANVAS_H / sh)
        new_w = max(1, round(sw * scale))
        new_h = max(1, round(sh * scale))
        resized = im.resize((new_w, new_h), Image.LANCZOS)

        canvas = Image.new("RGB", (CANVAS_W, CANVAS_H), CANVAS_BG)
        offset_x = (CANVAS_W - new_w) // 2
        offset_y = (CANVAS_H - new_h) // 2
        canvas.paste(resized, (offset_x, offset_y))
        canvas.save(dst, "WEBP", quality=WEBP_QUALITY, method=6)

    return new_w, new_h, offset_x


def main() -> None:
    batch = json.loads(DATA.read_text(encoding="utf-8"))
    images = batch["images"]

    primaries: dict[str, dict] = {}
    extras: list[dict] = []
    for item in images:
        src = SRC_DIR / f"{item['source_id']}.webp"
        if not src.is_file():
            raise SystemExit(f"missing source file: {src}")
        if item["role"] == "primary":
            if item["slug"] in primaries:
                raise SystemExit(f"duplicate primary for {item['slug']}")
            primaries[item["slug"]] = item
        else:
            extras.append(item)

    print(f"sources verified: {len(images)} ({len(primaries)} primary, {len(extras)} extra)")

    # 1+2. Composite primaries onto the white canvas into library + draft folders.
    over_limit: list[str] = []
    copied_draft = 0
    for slug, item in sorted(primaries.items()):
        src = SRC_DIR / f"{item['source_id']}.webp"
        lib_dst = LIB_DIR / f"{slug}.webp"
        new_w, new_h, margin = fit_on_white_canvas(src, lib_dst)
        if lib_dst.stat().st_size > SIZE_LIMIT_KB * 1024:
            over_limit.append(lib_dst.name)
        draft_img = DRAFTS_DIR / slug / "featured.webp"
        if draft_img.parent.is_dir():
            shutil.copyfile(lib_dst, draft_img)
            copied_draft += 1
        print(f"  {slug}.webp -> 1200x675 (image {new_w}x{new_h}, side margin {margin}px)")

    if over_limit:
        raise SystemExit(f"files over {SIZE_LIMIT_KB} KB limit: {over_limit}")
    print(f"library files written: {len(primaries)}; draft featured.webp synced: {copied_draft}")

    # Extras get the same white-canvas treatment under tmp-img/extras.
    TMP_EXTRAS.mkdir(parents=True, exist_ok=True)
    for item in extras:
        src = SRC_DIR / f"{item['source_id']}.webp"
        fit_on_white_canvas(src, TMP_EXTRAS / f"{item['slug']}-alt-1.webp")
    print(f"extras composited: {len(extras)}")

    # 3. Alt manifest for the WordPress bulk-replace screen (flat slug -> alt).
    manifest = {
        "version": 1,
        "batch_date": batch["batch_date"],
        "canvas": f"{CANVAS_W}x{CANVAS_H} white, contain-fit (no crop)",
        "alt": {slug: item["alt"] for slug, item in sorted(primaries.items())},
    }
    manifest_bytes = json.dumps(manifest, ensure_ascii=False, indent=2).encode("utf-8")

    extras_manifest = {
        "version": 1,
        "batch_date": batch["batch_date"],
        "canvas": f"{CANVAS_W}x{CANVAS_H} white, contain-fit (no crop)",
        "note": "alternate images; not part of the main replacement pack",
        "alt": {f"{item['slug']}-alt-1": item["alt"] for item in extras},
    }

    # 4a. Main importable ZIP: flat layout, exact slug names + alt manifest.
    if MAIN_ZIP.exists():
        MAIN_ZIP.unlink()
    with zipfile.ZipFile(MAIN_ZIP, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for slug in sorted(primaries):
            zf.write(LIB_DIR / f"{slug}.webp", arcname=f"{slug}.webp")
        zf.writestr("alt-manifest.json", manifest_bytes)
    print(f"main pack: {MAIN_ZIP.relative_to(ROOT)} ({MAIN_ZIP.stat().st_size // 1024} KB)")

    # 4b. Extras ZIP with non-colliding names.
    if EXTRA_ZIP.exists():
        EXTRA_ZIP.unlink()
    with zipfile.ZipFile(EXTRA_ZIP, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for item in extras:
            name = f"{item['slug']}-alt-1.webp"
            zf.write(TMP_EXTRAS / name, arcname=name)
        zf.writestr(
            "alt-manifest.json",
            json.dumps(extras_manifest, ensure_ascii=False, indent=2).encode("utf-8"),
        )
    print(f"extras pack: {EXTRA_ZIP.relative_to(ROOT)} ({EXTRA_ZIP.stat().st_size // 1024} KB)")

    # Verification.
    with zipfile.ZipFile(MAIN_ZIP) as zf:
        bad = zf.testzip()
        if bad:
            raise SystemExit(f"corrupt zip entry: {bad}")
        names = sorted(n for n in zf.namelist() if n.endswith(".webp"))
    assert len(names) == len(primaries)
    print(f"main zip entries: {len(names)} webp + alt-manifest.json")
    print("OK")


if __name__ == "__main__":
    main()

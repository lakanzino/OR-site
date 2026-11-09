#!/usr/bin/env python3
"""
Globe.Lax — icon & splash generator.

Turns the master artwork (design/app-logo.svg) into every Android density:
  * legacy square launcher icons   (mipmap-*/ic_launcher.png)
  * legacy round launcher icons    (mipmap-*/ic_launcher_round.png)
  * adaptive-icon foregrounds      (mipmap-*/ic_launcher_foreground.png)
  * monochrome (themed icon) layer (mipmap-*/ic_launcher_monochrome.png)
  * splash logo                    (drawable-*/splash_logo.png)

Usage:  node tools/render-icons.mjs && python3 tools/gen-icons.py
Requires: Pillow  (pip install Pillow)
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
SVG = ROOT / "design" / "app-logo.svg"
TMP = ROOT / "tools" / ".cache"
ANDROID_RES = ROOT / "android" / "app" / "src" / "main" / "res"

# density -> (legacy icon px, adaptive foreground px, splash logo px)
DENSITIES = {
    "mdpi": (48, 108, 96),
    "hdpi": (72, 162, 144),
    "xhdpi": (96, 216, 192),
    "xxhdpi": (144, 324, 288),
    "xxxhdpi": (192, 432, 384),
}

BACKGROUND = "#050B24"


def render_svg(size: int, out: Path, without_background: bool = False) -> None:
    """Rasterise the master SVG at `size` px using @resvg/resvg-js (already a dev tool)."""
    src = SVG.read_text(encoding="utf-8")
    if without_background:
        # Drop the navy tile, its hairline border and the ground shadow:
        # adaptive icons supply their own background layer.
        src = src.replace('<rect width="512" height="512" rx="116" fill="#050B24"/>', "")
        src = src.replace(
            '<rect x="2" y="2" width="508" height="508" rx="114" fill="none" stroke="#2E5BE0" '
            'stroke-opacity="0.25" stroke-width="3"/>',
            "",
        )
        src = src.replace('<ellipse cx="256" cy="436" rx="126" ry="17" fill="#000" opacity="0.45"/>', "")
    tmp_svg = TMP / f"icon-{size}.svg"
    tmp_svg.write_text(src, encoding="utf-8")
    script = (
        "const { Resvg } = require(process.argv[1] + '/node_modules/@resvg/resvg-js');"
        "const fs = require('fs');"
        "const r = new Resvg(fs.readFileSync(process.argv[2]), { fitTo: { mode: 'width', value: +process.argv[4] } });"
        "fs.writeFileSync(process.argv[3], r.render().asPng());"
    )
    subprocess.run(
        ["node", "-e", script, str(ROOT), str(tmp_svg), str(out), str(size)],
        check=True,
    )


def round_mask(size: int) -> Image.Image:
    mask = Image.new("L", (size * 4, size * 4), 0)
    ImageDraw.Draw(mask).ellipse((0, 0, size * 4 - 1, size * 4 - 1), fill=255)
    return mask.resize((size, size), Image.LANCZOS)


def square_mask(size: int, radius_ratio: float = 0.227) -> Image.Image:
    mask = Image.new("L", (size * 4, size * 4), 0)
    ImageDraw.Draw(mask).rounded_rectangle(
        (0, 0, size * 4 - 1, size * 4 - 1), radius=int(size * 4 * radius_ratio), fill=255
    )
    return mask.resize((size, size), Image.LANCZOS)


def main() -> int:
    TMP.mkdir(parents=True, exist_ok=True)

    master = TMP / "master-1024.png"
    render_svg(1024, master)

    for density, (legacy, foreground, splash) in DENSITIES.items():
        mipmap = ANDROID_RES / f"mipmap-{density}"
        mipmap.mkdir(parents=True, exist_ok=True)

        base = Image.open(master).convert("RGBA")

        # ── legacy square icon (rounded-corner tile, as shipped by the artwork) ──
        sq = base.resize((legacy, legacy), Image.LANCZOS)
        sq.putalpha(square_mask(legacy))
        sq.save(mipmap / "ic_launcher.png", optimize=True)

        # ── legacy round icon ──
        rd = base.resize((legacy, legacy), Image.LANCZOS)
        rd.putalpha(round_mask(legacy))
        rd.save(mipmap / "ic_launcher_round.png", optimize=True)

        # ── adaptive foreground: artwork at 66 % inside the 108 dp safe zone ──
        fg_master = TMP / "foreground-1024.png"
        render_svg(1024, fg_master, without_background=True)
        fg_src = Image.open(fg_master).convert("RGBA")
        fg = Image.new("RGBA", (foreground, foreground), (0, 0, 0, 0))
        inner = int(foreground * 0.62)
        art = fg_src.resize((inner, inner), Image.LANCZOS)
        off = (foreground - inner) // 2
        fg.alpha_composite(art, (off, off))
        fg.save(mipmap / "ic_launcher_foreground.png", optimize=True)

        # ── monochrome (Material You) layer: soft silhouette of the globe + orbit ──
        mono_master = TMP / f"mono-{foreground}.png"
        render_svg(foreground, mono_master, without_background=True)
        mono_src = Image.open(mono_master).convert("L")
        mono = Image.new("RGBA", (foreground, foreground), (0, 0, 0, 0))
        mono.putalpha(mono_src.point(lambda v: 255 if v > 40 else 0))
        mono_bg = Image.new("RGBA", (foreground, foreground), (255, 255, 255, 255))
        mono_bg.putalpha(mono.getchannel("A"))
        mono_bg.save(mipmap / "ic_launcher_monochrome.png", optimize=True)

        # ── splash logo ──
        drawable = ANDROID_RES / f"drawable-{density}"
        drawable.mkdir(parents=True, exist_ok=True)
        Image.open(master).convert("RGBA").resize((splash, splash), Image.LANCZOS).save(
            drawable / "splash_logo.png", optimize=True
        )
        print(f"  {density:8s} icon={legacy:3d}  foreground={foreground:3d}  splash={splash:3d}")

    # ── brand splash screens (portrait + landscape, all densities) ──
    # Flat navy brand background with the finished logo centred — no white flash,
    # no Capacitor placeholder art.
    for orient, (w, h) in {"port": (1280, 1920), "land": (1920, 1280)}.items():
        for density, (_, _, splash) in DENSITIES.items():
            drawable = ANDROID_RES / f"drawable-{orient}-{density}"
            drawable.mkdir(parents=True, exist_ok=True)
            canvas = Image.new("RGB", (w, h), BACKGROUND)
            logo_px = int(splash * 1.9)
            art = Image.open(master).convert("RGBA").resize((logo_px, logo_px), Image.LANCZOS)
            canvas.paste(art, ((w - logo_px) // 2, (h - logo_px) // 2), art)
            canvas.save(drawable / "splash.png", optimize=True)
    print("  splash screens (portrait + landscape) generated")

    # ── Play Store listing icon (512×512, no alpha) ──
    store = ROOT / "store"
    store.mkdir(exist_ok=True)
    play = Image.new("RGB", (512, 512), BACKGROUND)
    play.paste(Image.open(master).convert("RGBA").resize((512, 512), Image.LANCZOS), (0, 0), Image.open(master).convert("RGBA").resize((512, 512), Image.LANCZOS))
    play.save(store / "play-store-icon-512.png", optimize=True)

    print("icons + splash generated ✔")
    return 0


if __name__ == "__main__":
    sys.exit(main())

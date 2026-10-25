#!/usr/bin/env python3
"""Strip the baked-in Persian badge from featured photos and re-crop tighter.

The badge (the navy brush label with two lines of Persian) sits at the top
centre of the picture. Instead of printing anything on the photo, the script
cuts the badge band away, crops the frame to 16:9 again (which also makes the
picture closer-up) and adds a small brightness/contrast lift.

No text is ever drawn on the image: the «HanyarJarahi» mark is applied later by
the child theme (CSS), so it can be changed or removed at any time.
"""
import argparse
from pathlib import Path

from PIL import Image, ImageEnhance

W, H = 1200, 675


def is_badge(px):
    """Navy of the brush badge: dark, blue-dominant, no strong other channel."""
    r, g, b = px[0], px[1], px[2]
    return r <= 55 and g <= 85 and 25 <= b <= 140 and (b - r) >= 22 and (b - g) >= 12


def badge_bottom(im):
    """Return the y of the last badge row, or 0 when no badge is found."""
    w, h = im.size
    px = im.load()
    limit = int(h * 0.55)
    def share(y):
        step = max(1, w // 240)
        n = hit = 0
        for x in range(0, w, step):
            n += 1
            if is_badge(px[x, y]):
                hit += 1
        return hit / n if n else 0.0

    last = 0
    for y in range(limit):
        if share(y) >= 0.20:
            last = y
    if not last:
        return 0
    # brush strokes have a ragged edge: follow faint rows a little further down
    for y in range(last + 1, min(last + 40, h)):
        if share(y) >= 0.10:
            last = y
        elif y - last > 6:
            break
    return last


def clean(src: Path, dst: Path, quality: int = 82, zoom: float = 1.0):
    im = Image.open(src).convert("RGB")
    w, h = im.size
    cut = badge_bottom(im)
    top = 0 if cut == 0 else min(cut + 8, int(h * 0.55))
    im = im.crop((0, top, w, h))
    w, h = im.size

    target = W / H
    if w / h > target:                      # too wide → trim the sides
        nw = int(round(h * target))
        left = (w - nw) // 2
        im = im.crop((left, 0, left + nw, h))
    else:                                   # too tall → trim the bottom
        nh = int(round(w / target))
        im = im.crop((0, 0, w, nh))

    if zoom and zoom > 1.0:
        w, h = im.size
        nw, nh = int(round(w / zoom)), int(round(h / zoom))
        left, t = (w - nw) // 2, int((h - nh) * 0.42)
        im = im.crop((left, t, left + nw, t + nh))

    im = im.resize((W, H), Image.LANCZOS)
    im = ImageEnhance.Brightness(im).enhance(1.055)
    im = ImageEnhance.Contrast(im).enhance(1.05)
    im = ImageEnhance.Sharpness(im).enhance(1.15)
    dst.parent.mkdir(parents=True, exist_ok=True)
    im.save(dst, "WEBP", quality=quality, method=6)
    return cut, top


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("src")
    ap.add_argument("dst")
    ap.add_argument("--quality", type=int, default=82)
    ap.add_argument("--zoom", type=float, default=1.06)
    a = ap.parse_args()
    cut, top = clean(Path(a.src), Path(a.dst), a.quality, a.zoom)
    print(f"{a.src} → {a.dst}  badge_bottom={cut} crop_top={top}")


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Render the house featured-image badge onto a 1200x675 photo.

The badge is the navy brush shape with the Persian keyword centred at the top of
the picture: white first line, yellow second line, exactly like the reference set.
Arabic shaping and bidi are done in Python because Pillow here is built without
libraqm.
"""
import argparse
import math
import random
from pathlib import Path

import arabic_reshaper
from bidi.algorithm import get_display
from PIL import Image, ImageDraw, ImageFilter, ImageFont

W, H = 1200, 675
NAVY = (0, 45, 95)
YELLOW = (255, 218, 31)
WHITE = (255, 255, 255)
FONT_BOLD = "/tmp/fonts/Vazirmatn-Bold.ttf"
FONT_MEDIUM = "/tmp/fonts/Vazirmatn-Medium.ttf"


def fa(text: str) -> str:
    return get_display(arabic_reshaper.reshape(text))


def brush_mask(w: int, h: int, seed: int = 7) -> Image.Image:
    """Rounded rectangle with a slightly ragged, paint-brush edge (bristles go inward)."""
    rnd = random.Random(seed)
    pad = 18
    mask = Image.new("L", (w + pad * 2, h + pad * 2), 0)
    d = ImageDraw.Draw(mask)
    radius = int(h * 0.30)
    d.rounded_rectangle([pad, pad, pad + w, pad + h], radius=radius, fill=255)

    # chew the outer edge with small notches so it reads as painted, not vector
    edge_px = (w + h) * 3
    for _ in range(edge_px):
        side = rnd.choice("tblr")
        if side in "tb":
            x = rnd.randint(pad, pad + w)
            y = pad if side == "t" else pad + h
            depth = rnd.randint(0, 7)
            d.line([(x, y + (-depth if side == "t" else depth)),
                    (x + rnd.randint(-1, 1), y + (-depth - 3 if side == "t" else depth + 3))],
                   fill=0, width=rnd.randint(1, 3))
        else:
            y = rnd.randint(pad, pad + h)
            x = pad if side == "l" else pad + w
            depth = rnd.randint(0, 7)
            d.line([(x + (-depth if side == "l" else depth), y),
                    (x + (-depth - 3 if side == "l" else depth + 3), y + rnd.randint(-1, 1))],
                   fill=0, width=rnd.randint(1, 3))

    mask = mask.filter(ImageFilter.GaussianBlur(0.8))
    mask = mask.point(lambda p: 255 if p > 120 else 0)
    return mask.crop((pad - 4, pad - 4, pad + w + 4, pad + h + 4))


def fit_lines(draw, lines, max_width, start_size, min_size=30):
    """Pick the largest font size whose widest line still fits."""
    size = start_size
    while size > min_size:
        fonts = [ImageFont.truetype(FONT_BOLD, size) for _ in lines]
        widths = [draw.textlength(fa(t), font=f) for t, f in zip(lines, fonts)]
        if max(widths) <= max_width:
            return size, fonts, widths
        size -= 2
    fonts = [ImageFont.truetype(FONT_BOLD, min_size) for _ in lines]
    return min_size, fonts, [draw.textlength(fa(t), font=f) for t, f in zip(lines, fonts)]


def wrap(text: str, max_lines: int = 3):
    if "|" in text:
        return [part.strip() for part in text.split("|") if part.strip()]
    words = text.split()
    if len(words) <= 2:
        return [text]
    lines, current = [], ""
    for word in words:
        trial = (current + " " + word).strip()
        if len(trial) <= 16 or not current:
            current = trial
        else:
            lines.append(current)
            current = word
    if current:
        lines.append(current)
    if len(lines) > max_lines:
        merged = lines[len(lines) - max_lines:]
        merged[0] = " ".join(lines[: len(lines) - max_lines + 1])
        lines = merged
    return lines


def render(photo: Path, out: Path, caption: str):
    base = Image.open(photo).convert("RGB")
    scale = max(W / base.width, H / base.height)
    base = base.resize((math.ceil(base.width * scale), math.ceil(base.height * scale)), Image.LANCZOS)
    left = (base.width - W) // 2
    top = (base.height - H) // 2
    base = base.crop((left, top, left + W, top + H))

    lines = wrap(caption)
    probe = ImageDraw.Draw(Image.new("RGB", (10, 10)))
    max_text_w = int(W * 0.50)
    size, fonts, widths = fit_lines(probe, lines, max_text_w, start_size=66)

    line_h = int(size * 1.32)
    text_h = line_h * len(lines)
    badge_w = int(max(widths)) + int(size * 1.55)
    badge_h = text_h + int(size * 0.90)
    badge_w = min(badge_w, int(W * 0.78))

    mask = brush_mask(badge_w, badge_h)
    layer = Image.new("RGB", (badge_w, badge_h), NAVY)
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    paste = Image.new("RGB", (badge_w + 8, badge_h + 8), NAVY)
    overlay.paste(paste, ((W - badge_w) // 2 - 4, 6), mask)
    base = Image.alpha_composite(base.convert("RGBA"), overlay).convert("RGB")

    draw = ImageDraw.Draw(base)
    x_center = W // 2
    y = 10 + int(size * 0.50)
    for idx, (text, font) in enumerate(zip(lines, fonts)):
        colour = WHITE if idx == 0 else YELLOW
        draw.text((x_center, y), fa(text), font=font, fill=colour, anchor="ma")
        y += line_h

    base.save(out, "WEBP", quality=82, method=6, strip=True)
    return out.stat().st_size


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("photo")
    ap.add_argument("out")
    ap.add_argument("caption")
    args = ap.parse_args()
    size = render(Path(args.photo), Path(args.out), args.caption)
    print(f"{args.out} {size // 1024} KB")


if __name__ == "__main__":
    main()

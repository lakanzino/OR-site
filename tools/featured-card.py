#!/usr/bin/env python3
"""Build the minimal featured card (format locked 2026-10-26, revision 2).

Layout (1200x675, white canvas):
  - large floating rounded photo card with soft shadow
  - no Persian/English title baked into the image (SEO title stays in the
    manifest; the importer writes featured_image.alt as attachment alt text)
  - bottom-corner frosted wordmark chip ON the photo: semi-transparent white
    pill, letter "H" in green, the rest of HAMYARJARAHI in gray, wide tracking
"""
import argparse
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

W, H = 1200, 675
WHITE = (255, 255, 255)
GREEN = (22, 163, 74)      # green-600
GRAY = (107, 114, 128)     # gray-500
CARD_BOX = (90, 44, 1110, 631)
CARD_RADIUS = 26
LATIN = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
WORDMARK = "HAMYARJARAHI"
CHIP_MARGIN = 28
CHIP_PAD_X = 26
CHIP_PAD_Y = 15
TRACKING = 7
FONT_SIZE = 22
FROST_ALPHA = 150          # white overlay alpha on the frosted chip
FROST_BLUR = 16


def cover(im: Image.Image, tw: int, th: int) -> Image.Image:
    w, h = im.size
    s = max(tw / w, th / h)
    im = im.resize((round(w * s), round(h * s)), Image.LANCZOS)
    w, h = im.size
    left = (w - tw) // 2
    top = (h - th) // 2
    return im.crop((left, top, left + tw, top + th))


def rounded_mask(w: int, h: int, radius: int) -> Image.Image:
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, w - 1, h - 1], radius=radius, fill=255)
    return mask


def soft_shadow(canvas: Image.Image, mask: Image.Image, offset, blur: int = 24, alpha: int = 42) -> None:
    sh = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    ImageDraw.Draw(sh).bitmap(offset, mask, fill=(15, 23, 42, alpha))
    canvas.alpha_composite(sh.filter(ImageFilter.GaussianBlur(blur)))


def wordmark_size(draw: ImageDraw.ImageDraw):
    font = ImageFont.truetype(LATIN, FONT_SIZE)
    widths = [draw.textlength(ch, font=font) for ch in WORDMARK]
    text_w = sum(widths) + TRACKING * (len(WORDMARK) - 1)
    bbox = draw.textbbox((0, 0), WORDMARK, font=font)
    text_h = bbox[3] - bbox[1]
    return font, widths, text_w, text_h, bbox[1]


def build(photo_path: Path, out_path: Path) -> None:
    canvas = Image.new("RGBA", (W, H), WHITE + (255,))
    x0, y0, x1, y1 = CARD_BOX
    cw, ch = x1 - x0, y1 - y0
    mask = rounded_mask(cw, ch, CARD_RADIUS)
    soft_shadow(canvas, mask, (x0, y0 + 14))
    photo = cover(Image.open(photo_path).convert("RGB"), cw, ch).convert("RGBA")
    photo.putalpha(mask)
    canvas.paste(photo, (x0, y0), photo)

    draw = ImageDraw.Draw(canvas)
    font, widths, text_w, text_h, text_top = wordmark_size(draw)
    chip_w = round(text_w) + 2 * CHIP_PAD_X
    chip_h = text_h + 2 * CHIP_PAD_Y
    cx1, cy1 = x1 - CHIP_MARGIN, y1 - CHIP_MARGIN
    cx0, cy0 = cx1 - chip_w, cy1 - chip_h
    radius = chip_h // 2

    # real frosted glass: blur the photo region behind the chip, then whiten it
    region = canvas.crop((cx0, cy0, cx1, cy1))
    region = region.filter(ImageFilter.GaussianBlur(FROST_BLUR))
    veil = Image.new("RGBA", region.size, (255, 255, 255, FROST_ALPHA))
    region = Image.alpha_composite(region, veil)
    chip_mask = rounded_mask(chip_w, chip_h, radius)
    canvas.paste(region, (cx0, cy0), chip_mask)
    border = Image.new("RGBA", (chip_w, chip_h), (0, 0, 0, 0))
    ImageDraw.Draw(border).rounded_rectangle(
        [0, 0, chip_w - 1, chip_h - 1], radius=radius, outline=(255, 255, 255, 235), width=2
    )
    canvas.alpha_composite(border, (cx0, cy0))

    tx = cx0 + CHIP_PAD_X
    ty = cy0 + CHIP_PAD_Y - text_top
    for i, ch_letter in enumerate(WORDMARK):
        draw.text((tx, ty), ch_letter, font=font, fill=GREEN if i == 0 else GRAY)
        tx += widths[i] + TRACKING

    out_path.parent.mkdir(parents=True, exist_ok=True)
    canvas.convert("RGB").save(out_path, "WEBP", quality=84, method=6)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("photo", type=Path)
    ap.add_argument("out", type=Path)
    args = ap.parse_args()
    build(args.photo, args.out)
    kb = args.out.stat().st_size / 1024
    print(f"{args.out} ({kb:.0f} KB)")
    return 0 if kb <= 180 else 1


if __name__ == "__main__":
    sys.exit(main())

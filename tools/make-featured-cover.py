#!/usr/bin/env python3
"""Build the simple 16:9 featured cover for each article draft.

Design (approved): solid, very light, soft background colour (random per
article, stored in tools/featured-cover-map.json); short Persian name in dark
navy with a very faint shadow; thin red rule; English subtitle in grey.

Requires: ImageMagick `convert`, Python packages arabic-reshaper and python-bidi
(pip install arabic-reshaper python-bidi). Persian is reshaped and bidi-ordered
before ImageMagick draws it, because IM6 here has no raqm/pango shaping.

Usage: python3 tools/make-featured-cover.py [slug ...]   (default: all in map)
"""
import json
import subprocess
import sys
from pathlib import Path

import arabic_reshaper
from bidi.algorithm import get_display

ROOT = Path(__file__).resolve().parents[1]
FONT_B = ROOT / "tools/fonts/Vazirmatn-Bold.ttf"
FONT_R = ROOT / "tools/fonts/Vazirmatn-Regular.ttf"
MAP = json.loads((ROOT / "tools/featured-cover-map.json").read_text(encoding="utf-8"))
W, H = 1200, 675


def shape(text: str) -> str:
    return get_display(arabic_reshaper.reshape(text))


def run(args):
    subprocess.run(args, check=True)


def build(slug: str, item: dict) -> Path:
    draft = ROOT / "content/drafts" / slug
    if draft.is_dir():
        out = draft / "featured.webp"
    else:
        # Live-only articles without a draft folder: write to releases/covers-extra/
        out_dir = ROOT / "releases/covers-extra"
        out_dir.mkdir(parents=True, exist_ok=True)
        out = out_dir / f"{slug}.webp"
    tmp = Path("/tmp/cover-build")
    tmp.mkdir(parents=True, exist_ok=True)
    text_png = tmp / f"{slug}-text.png"
    shadow_png = tmp / f"{slug}-shadow.png"
    base_png = tmp / f"{slug}-base.png"
    fa = shape(item["fa"])
    en = item["en"]

    # 1) transparent layer with the Persian title (navy) and English subtitle (grey)
    run([
        "convert", "-size", f"{W}x{H}", "xc:none",
        "-font", str(FONT_B), "-pointsize", "104", "-fill", "#0b1f3a",
        "-gravity", "center", "-annotate", "+0-72", fa,
        "-font", str(FONT_R), "-pointsize", "44", "-fill", "#6b7280",
        "-annotate", "+0+78", en,
        str(text_png),
    ])
    # 2) very faint shadow only under the Persian text
    run([
        "convert", str(text_png), "-background", "#00000018",
        "-shadow", "18x2+0+4", str(shadow_png),
    ])
    # 3) background + shadow + text + thin red rule
    run([
        "convert", "-size", f"{W}x{H}", f"xc:{item['bg']}",
        str(shadow_png), "-composite", str(text_png), "-composite",
        "-stroke", "#c62828", "-strokewidth", "4",
        "-draw", f"line {W//2 - 230},{H//2 + 22} {W//2 + 230},{H//2 + 22}",
        "-quality", "80", str(base_png),
    ])
    run(["convert", str(base_png), "-resize", f"{W}x{H}!", "-quality", "80", str(out)])
    return out


def update_alt(slug: str, item: dict) -> None:
    path = ROOT / "content/drafts" / slug / "article.json"
    if not path.is_file():
        return
    data = json.loads(path.read_text(encoding="utf-8"))
    data.setdefault("featured_image", {})["file"] = "featured.webp"
    data["featured_image"]["alt"] = (
        f"عنوان «{item['fa']}» با زیرعنوان انگلیسی {item['en']} روی زمینه‌ای روشن و ساده"
    )
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main(argv):
    slugs = argv or list(MAP)
    for slug in slugs:
        item = MAP[slug]
        out = build(slug, item)
        update_alt(slug, item)
        print("ok", slug, out.relative_to(ROOT))


if __name__ == "__main__":
    main(sys.argv[1:])

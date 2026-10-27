#!/usr/bin/env python3
"""Build the current child theme ZIP and keep its manifest in sync.

Usage: python3 tools/build-theme-release.py
No upload, tagging, deployment, or network requests are performed.
"""
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / "themes/lax-theme-child"
RELEASES = ROOT / "releases"


def build():
    style = (THEME / "style.css").read_text()
    version = re.search(r"^Version:\s*(\d+\.\d+\.\d+)\s*$", style, re.M).group(1)
    assert f"'LAX_THEME_CHILD_VERSION', '{version}'" in (THEME / "functions.php").read_text(), "PHP version mismatch"
    assert f"Stable tag: {version}" in (THEME / "readme.txt").read_text(), "Readme version mismatch"
    assets = sorted(THEME.glob("repair-media/*/assets/*.webp"))
    assert len(assets) == 18, "All eighteen repair images must be shipped"
    for dest in assets:
        slug = dest.parents[1].name
        source = ROOT / "content/drafts" / slug / "assets" / dest.name
        assert hashlib.sha256(source.read_bytes()).digest() == hashlib.sha256(dest.read_bytes()).digest(), f"Repair asset mismatch: {dest}"
    manifest_path = RELEASES / "orsite-release.json"
    manifest = json.loads(manifest_path.read_text())
    manifest["child"]["version"] = version
    manifest["published_at"] = None  # A build is not evidence of a GitHub Release.
    manifest["built_at"] = datetime.now(timezone.utc).isoformat()
    note = f"{version} — تجمیع خط 0.15.x (ترمیم تصاویر درون‌متنی و ۱۸ تصویر همراه قالب) با کارهای سئو: صفحه‌ی پیشخوان «سلامت ایندکس» با دوازده آزمون زنده‌ی ریشه‌یابی ایندکس‌نشدن و رفع بلوک خالی جراحی‌های صفحه‌ی اصلی (pad_counts و فهرست جایگزین)."
    if note not in manifest["changelog"]:
        manifest["changelog"].insert(0, note)
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")
    output = RELEASES / "lax-theme-child.zip"
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for path in sorted(THEME.rglob("*")):
            if path.is_file() and "__pycache__" not in path.parts and not path.name.startswith("."):
                z.write(path, "lax-theme-child/" + path.relative_to(THEME).as_posix())
    with zipfile.ZipFile(output) as z:
        assert z.testzip() is None
        assert f"Version: {version}" in z.read("lax-theme-child/style.css").decode()
        assert len([n for n in z.namelist() if "/repair-media/" in n and n.endswith(".webp")]) == 18
    print(f"Built {output.relative_to(ROOT)}: {version}, {output.stat().st_size:,} bytes, 18 repair images")
    print("SHA-256", hashlib.sha256(output.read_bytes()).hexdigest())


if __name__ == "__main__":
    build()

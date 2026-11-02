#!/usr/bin/env python3
"""Build the installable ZIP of the `hamyar-doctors` plugin.

The release packages in `releases/` are the ones a site owner uploads through
«افزونه‌ها → افزودن → بارگذاری افزونه». They must contain a single top-level
folder named exactly like the plugin folder, which is what this script does:

    python3 tools/build_doctors_package.py            # -> releases/hamyar-doctors-<ver>.zip
    python3 tools/build_doctors_package.py --out DIR  # custom destination

The version comes from the plugin header, so a package can never disagree with
the code it ships. Entries are written with a fixed timestamp so rebuilding the
same sources byte-for-byte reproduces the previous archive.
"""

from __future__ import annotations

import argparse
import re
import sys
import zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
PLUGIN_DIR = REPO / "plugins" / "hamyar-doctors"
PLUGIN_SLUG = "hamyar-doctors"
MAIN_FILE = PLUGIN_DIR / f"{PLUGIN_SLUG}.php"

EXCLUDE_DIRS = {"__MACOSX", "node_modules", ".git", "tests"}
EXCLUDE_NAMES = {".DS_Store", "Thumbs.db"}
EXCLUDE_SUFFIXES = {".zip", ".log"}

FIXED_DATE = (2020, 1, 1, 0, 0, 0)


def plugin_version() -> str:
    header = MAIN_FILE.read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*([0-9][0-9A-Za-z.\-]*)", header, re.MULTILINE)
    if not match:
        sys.exit(f"no Version: header found in {MAIN_FILE}")
    return match.group(1)


def collect() -> list[Path]:
    files = []
    for path in sorted(PLUGIN_DIR.rglob("*")):
        if not path.is_file():
            continue
        parts = set(path.relative_to(PLUGIN_DIR).parts[:-1])
        if parts & EXCLUDE_DIRS:
            continue
        if path.name in EXCLUDE_NAMES or path.suffix.lower() in EXCLUDE_SUFFIXES:
            continue
        files.append(path)
    return files


def build(out_dir: Path) -> Path:
    version = plugin_version()
    header_constant = re.search(
        r"define\(\s*'HJ_DOCTORS_VERSION'\s*,\s*'([^']+)'\s*\)",
        MAIN_FILE.read_text(encoding="utf-8"),
    )
    if not header_constant or header_constant.group(1) != version:
        sys.exit("HJ_DOCTORS_VERSION does not match the plugin header version")

    out_dir.mkdir(parents=True, exist_ok=True)
    target = out_dir / f"{PLUGIN_SLUG}-{version}.zip"

    files = collect()
    if not files:
        sys.exit("nothing to package")

    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
        for path in files:
            arcname = f"{PLUGIN_SLUG}/{path.relative_to(PLUGIN_DIR).as_posix()}"
            info = zipfile.ZipInfo(arcname, date_time=FIXED_DATE)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            archive.writestr(info, path.read_bytes())

    print(f"{target.relative_to(REPO)}  ({len(files)} files, {target.stat().st_size} bytes)")
    return target


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", default=str(REPO / "releases"), help="destination directory")
    args = parser.parse_args()
    build(Path(args.out).resolve())
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

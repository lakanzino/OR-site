#!/usr/bin/env python3
"""Build the installable hamyar-doctors ZIP reproducibly from the source tree.

The package is byte-stable: entries are sorted, timestamps are fixed and the
`tests/` directory is excluded (its scripts are destructive fixtures that must
never ship). Version in `hamyar-doctors.php` decides the file name.

Usage:
    python3 tools/build-doctors-package.py            # build releases/hamyar-doctors-<ver>.zip
    python3 tools/build-doctors-package.py --check    # verify the existing ZIP matches
"""
from __future__ import annotations

import argparse
import hashlib
import re
import sys
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "plugins" / "hamyar-doctors"
MAIN = PLUGIN / "hamyar-doctors.php"
EXCLUDE_PARTS = {"tests", "__pycache__", "node_modules", ".git", ".DS_Store"}
FIXED_TIME = (1980, 1, 1, 0, 0, 0)


def plugin_version() -> str:
    text = MAIN.read_text(encoding="utf-8")
    header = re.search(r"^\s*\* Version:\s*([0-9.]+)", text, re.M)
    constant = re.search(r"define\(\s*'HJ_DOCTORS_VERSION'\s*,\s*'([0-9.]+)'\s*\)", text)
    if not header or not constant:
        raise SystemExit("cannot read the plugin version")
    if header.group(1) != constant.group(1):
        raise SystemExit(f"version mismatch: header {header.group(1)} vs constant {constant.group(1)}")
    return header.group(1)


def runtime_files() -> list[Path]:
    files = [
        p
        for p in PLUGIN.rglob("*")
        if p.is_file() and not EXCLUDE_PARTS & set(p.relative_to(PLUGIN).parts)
    ]
    return sorted(files, key=lambda p: p.relative_to(PLUGIN).as_posix())


def build(out_dir: Path) -> Path:
    version = plugin_version()
    target = out_dir / f"hamyar-doctors-{version}.zip"
    out_dir.mkdir(parents=True, exist_ok=True)
    with ZipFile(target, "w", ZIP_DEFLATED, compresslevel=9) as zf:
        for path in runtime_files():
            info = ZipInfo(f"hamyar-doctors/{path.relative_to(PLUGIN).as_posix()}", date_time=FIXED_TIME)
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            zf.writestr(info, path.read_bytes())
    return target


def check(out_dir: Path) -> int:
    version = plugin_version()
    target = out_dir / f"hamyar-doctors-{version}.zip"
    if not target.exists():
        print(f"MISSING {target.relative_to(ROOT)} for version {version}")
        return 1
    expected = {p.relative_to(PLUGIN).as_posix(): p for p in runtime_files()}
    with ZipFile(target) as zf:
        if zf.testzip() is not None:
            print("CORRUPT archive")
            return 1
        names = zf.namelist()
        if any(not n.startswith("hamyar-doctors/") or n.endswith("/") for n in names):
            print("BAD layout: every entry must sit under hamyar-doctors/")
            return 1
        shipped = {n[len("hamyar-doctors/"):] for n in names}
        if shipped != set(expected):
            for extra in sorted(shipped - set(expected)):
                print(f"UNEXPECTED in package: {extra}")
            for missing in sorted(set(expected) - shipped):
                print(f"MISSING from package: {missing}")
            return 1
        if any("tests" in Path(n).parts for n in shipped):
            print("BAD package: tests/ must not ship")
            return 1
        for name, path in expected.items():
            if zf.read(f"hamyar-doctors/{name}") != path.read_bytes():
                print(f"DRIFT: {name} differs from source")
                return 1
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    print(f"OK {target.relative_to(ROOT)} matches sources — version {version}, sha256 {digest}")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", default=str(ROOT / "releases"), help="output directory (default: releases/)")
    parser.add_argument("--check", action="store_true", help="verify the existing package instead of building")
    args = parser.parse_args()
    out_dir = Path(args.out)
    if args.check:
        return check(out_dir)
    target = build(out_dir)
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    print(f"built {target.relative_to(ROOT)} — version {plugin_version()}, {len(runtime_files())} files, sha256 {digest}")
    return 0


if __name__ == "__main__":
    sys.exit(main())

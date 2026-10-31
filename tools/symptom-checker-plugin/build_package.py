#!/usr/bin/env python3
"""Build the installable WordPress package for the symptom-checker.

The repository keeps one copy of the UI (`symptom-checker/ui/`), the engine
(`symptom-checker/engine/src/`) and the data (`docs/symptom-checker/phase-1/`).
This script copies those sources into a temporary plugin tree, records the
SHA-256 of every copied asset and writes a deterministic zip into `releases/`.

Usage:
    python3 tools/symptom-checker-plugin/build_package.py            # build
    python3 tools/symptom-checker-plugin/build_package.py --out DIR  # build elsewhere
    python3 tools/symptom-checker-plugin/build_package.py --check    # verify a built zip

`--check` rebuilds in memory and fails when the committed zip no longer matches
the sources (drift guard), and also verifies the zip against itself.
"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import re
import sys
import tempfile
import zipfile
from dataclasses import dataclass, field
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
PLUGIN_DIR = REPO / "plugins" / "hamyar-symptom-checker"
RELEASES = REPO / "releases"
SLUG = "hamyar-symptom-checker"
ZIP_TIMESTAMP = (2026, 1, 1, 0, 0, 0)

# Source file → destination inside the plugin's `assets/` directory.
ASSET_SOURCES = {
    "symptom-checker/ui/app.js": "ui/app.js",
    "symptom-checker/ui/view-model.js": "ui/view-model.js",
    "symptom-checker/ui/question-help.js": "ui/question-help.js",
    "symptom-checker/ui/styles.css": "ui/styles.css",
    # styles.css loads it with a relative url(), so it must sit beside the CSS.
    "symptom-checker/ui/anatomy-hero.jpg": "ui/anatomy-hero.jpg",
    "symptom-checker/engine/src/engine.js": "engine/src/engine.js",
    "symptom-checker/engine/src/adapter.js": "engine/src/adapter.js",
    "symptom-checker/engine/src/evidence.js": "engine/src/evidence.js",
    "symptom-checker/engine/src/validate-bundle.js": "engine/src/validate-bundle.js",
    "symptom-checker/engine/src/errors.js": "engine/src/errors.js",
    "symptom-checker/engine/data/evidence-map.v1.json": "engine/data/evidence-map.v1.json",
    "docs/symptom-checker/phase-1/knowledge-bundle.v1.json": "data/knowledge-bundle.v1.json",
}

# Plugin files copied verbatim (relative to the plugin directory).
PLUGIN_FILES = [
    "hamyar-symptom-checker.php",
    "includes/mapping.php",
    "includes/page.php",
    "templates/checker-page.php",
    "README.md",
]


class BuildError(RuntimeError):
    """Raised when the package cannot be assembled."""


def plugin_version() -> str:
    main = (PLUGIN_DIR / "hamyar-symptom-checker.php").read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*(\S+)\s*$", main, re.MULTILINE)
    if not match:
        raise BuildError("plugin header has no Version line")
    return match.group(1)


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


@dataclass
class Package:
    version: str
    files: dict[str, bytes] = field(default_factory=dict)
    sources: dict[str, dict[str, str]] = field(default_factory=dict)

    def manifest(self) -> dict:
        return {
            "package": SLUG,
            "version": self.version,
            "asset_sources": self.sources,
        }

    def zip_bytes(self) -> bytes:
        buffer = io.BytesIO()
        with zipfile.ZipFile(buffer, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name in sorted(self.files):
                info = zipfile.ZipInfo(f"{SLUG}/{name}", date_time=ZIP_TIMESTAMP)
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = 0o644 << 16
                archive.writestr(info, self.files[name])
        return buffer.getvalue()


def collect() -> Package:
    version = plugin_version()
    package = Package(version=version)

    for relative in PLUGIN_FILES:
        source = PLUGIN_DIR / relative
        if not source.is_file():
            raise BuildError(f"missing plugin file: {relative}")
        package.files[relative] = source.read_bytes()

    for relative, target in ASSET_SOURCES.items():
        source = REPO / relative
        if not source.is_file():
            raise BuildError(f"missing source asset: {relative}")
        data = source.read_bytes()
        package.files[f"assets/{target}"] = data
        package.sources[target] = {"source": relative, "sha256": sha256(data)}

    package.files["assets/SOURCES.json"] = (
        json.dumps(package.manifest(), ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    ).encode("utf-8")
    package.files["assets/README.md"] = (
        "# دارایی‌های ساخته‌شده\n\n"
        "این فایل‌ها با `tools/symptom-checker-plugin/build_package.py` از منابع مخزن کپی می‌شوند؛ "
        "دستکاری دستی آن‌ها با ساخت بعدی بازنویسی می‌شود. منبع هر فایل و هش آن در `SOURCES.json` است.\n"
    ).encode("utf-8")

    return package


def build(out_dir: Path) -> tuple[Path, str]:
    package = collect()
    out_dir.mkdir(parents=True, exist_ok=True)
    target = out_dir / f"{SLUG}-{package.version}.zip"
    data = package.zip_bytes()
    target.write_bytes(data)
    return target, sha256(data)


def verify(zip_path: Path) -> list[str]:
    """Compare a built zip with a fresh build from the current sources."""
    problems: list[str] = []
    if not zip_path.is_file():
        return [f"missing package: {zip_path}"]

    package = collect()
    expected = package.files
    with zipfile.ZipFile(zip_path) as archive:
        names = {name for name in archive.namelist() if not name.endswith("/")}
        actual = {name.split("/", 1)[1]: archive.read(name) for name in names}
        prefixes = {name.split("/", 1)[0] for name in names}

    if prefixes != {SLUG}:
        problems.append(f"unexpected top-level folders in zip: {sorted(prefixes)}")

    for name in sorted(set(expected) | set(actual)):
        if name not in actual:
            problems.append(f"missing from zip: {name}")
        elif name not in expected:
            problems.append(f"unexpected file in zip: {name}")
        elif expected[name] != actual[name]:
            problems.append(f"content drift: {name}")

    for name in sorted(actual):
        if "__pycache__" in name or name.endswith(".pyc"):
            problems.append(f"python cache in package: {name}")

    return problems


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Build the symptom-checker WordPress package.")
    parser.add_argument("--out", type=Path, default=RELEASES, help="output directory (default: releases/)")
    parser.add_argument("--check", action="store_true", help="verify the existing package instead of building")
    parser.add_argument("--json", action="store_true", help="print a machine-readable summary")
    args = parser.parse_args(argv)

    try:
        if args.check:
            version = plugin_version()
            target = args.out / f"{SLUG}-{version}.zip"
            problems = verify(target)
            if problems:
                for problem in problems:
                    print(f"FAIL {problem}", file=sys.stderr)
                return 1
            print(f"OK {target.relative_to(REPO) if target.is_relative_to(REPO) else target} matches sources")
            return 0

        target, digest = build(args.out)
        package = collect()
        summary = {
            "package": str(target),
            "version": package.version,
            "sha256": digest,
            "files": len(package.files),
            "assets": sorted(package.sources),
        }
        if args.json:
            print(json.dumps(summary, ensure_ascii=False, indent=2))
        else:
            print(f"built {target} ({len(package.files)} files)")
            print(f"sha256 {digest}")
        return 0
    except BuildError as error:
        print(f"FAIL {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))

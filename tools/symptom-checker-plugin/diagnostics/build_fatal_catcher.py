#!/usr/bin/env python3
"""Build the temporary fatal-error catcher as an installable plugin zip.

    python3 tools/symptom-checker-plugin/diagnostics/build_fatal_catcher.py
    python3 tools/symptom-checker-plugin/diagnostics/build_fatal_catcher.py --check

The catcher is a diagnostic aid, not part of the shipped symptom-checker
package: it never ships inside `releases/hamyar-symptom-checker-*.zip`. It is
packaged separately so the owner can upload it from wp-admin (or drop the bare
PHP file into wp-content/mu-plugins/) and read the real fatal error that
WordPress hides behind "the plugin failed to activate due to a fatal error".
"""
from __future__ import annotations

import hashlib
import io
import sys
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
SOURCE = HERE / "hj-sc-fatal-catcher.php"
ZIP_PATH = REPO / "releases" / "hj-fatal-catcher-1.0.0.zip"
FOLDER = "hj-fatal-catcher"
FIXED_DATE = (2026, 1, 1, 0, 0, 0)


def build() -> bytes:
    source = SOURCE.read_bytes()
    if b"Plugin Name:" not in source:
        raise SystemExit("the catcher has no plugin header")

    stream = io.BytesIO()
    with zipfile.ZipFile(stream, "w", zipfile.ZIP_DEFLATED) as archive:
        info = zipfile.ZipInfo(f"{FOLDER}/{SOURCE.name}", date_time=FIXED_DATE)
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o644 << 16
        archive.writestr(info, source)
    return stream.getvalue()


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def main(argv: list[str]) -> int:
    data = build()
    if "--check" in argv:
        if not ZIP_PATH.exists():
            print(f"MISSING {ZIP_PATH.relative_to(REPO)}")
            return 1
        if ZIP_PATH.read_bytes() != data:
            print(f"STALE {ZIP_PATH.relative_to(REPO)} — rebuild it")
            return 1
        print(f"OK {ZIP_PATH.relative_to(REPO)} matches the source")
        return 0

    ZIP_PATH.parent.mkdir(parents=True, exist_ok=True)
    ZIP_PATH.write_bytes(data)
    print(f"built {ZIP_PATH.relative_to(REPO)}")
    print(f"  files: 1")
    print(f"  bytes: {len(data)}")
    print(f"  sha256: {digest(data)}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

#!/usr/bin/env python3
"""Fail when a plugin function name is also declared by a shipped theme.

Why this exists: on 2026-10-31 the plugin died with
`Cannot redeclare function hj_sc_enqueue_assets()` on the live site. The child
theme 0.30.1 loads `inc/symptom-checker-test.php`, which declared
`hj_sc_enqueue_assets()`; the plugin declared the same name in
`includes/page.php`. WordPress could only report «مشکل جدی».

The plugin now uses the `hjsc_` prefix and the theme keeps `hj_sc_`, but a
name can drift back in with any new file, so the build gate compares the two
sets. Theme sources are read from the release zips in `releases/`, which is
what actually runs on the site (a release-only commit may be newer than the
theme folder on `main`).

    python3 tools/symptom-checker-plugin/check-name-collisions.py
    python3 tools/symptom-checker-plugin/check-name-collisions.py --verbose
"""
from __future__ import annotations

import re
import sys
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
PLUGIN = REPO / "plugins" / "hamyar-symptom-checker"
RELEASES = REPO / "releases"
FUNCTION_RE = re.compile(r"^\s*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(", re.M)
GUARD_WINDOW = 240


def declared_names(text: str) -> list[str]:
    """Function names declared in a file, minus guarded fallback declarations.

    The plugin ships a fallback `hjsc_activate()` behind
    `if ( ! function_exists( 'hjsc_activate' ) )`, so the same name may appear
    twice on purpose; a real duplicate would fatal at parse time.
    """
    names = []
    for match in FUNCTION_RE.finditer(text):
        name = match.group(1)
        near = text[max(0, match.start() - GUARD_WINDOW):match.start()]
        if re.search(r"function_exists\(\s*['\"]%s['\"]" % re.escape(name), near):
            continue
        names.append(name)
    return names


def plugin_functions() -> dict[str, list[str]]:
    found: dict[str, list[str]] = {}
    for path in sorted(PLUGIN.rglob("*.php")):
        text = path.read_text(encoding="utf-8", errors="replace")
        for name in declared_names(text):
            found.setdefault(name, []).append(str(path.relative_to(PLUGIN.parent.parent)))
    return found


def theme_functions(zip_path: Path) -> dict[str, list[str]]:
    found: dict[str, list[str]] = {}
    with zipfile.ZipFile(zip_path) as archive:
        for info in archive.infolist():
            if not info.filename.endswith(".php") or info.is_dir():
                continue
            text = archive.read(info).decode("utf-8", errors="replace")
            for name in declared_names(text):
                found.setdefault(name, []).append(f"{zip_path.name}:{info.filename}")
    return found


def main(argv: list[str]) -> int:
    verbose = "--verbose" in argv
    mine = plugin_functions()
    themes = sorted(RELEASES.glob("lax-theme-*.zip"))
    if not themes:
        print("SKIP no theme release zip in releases/")
        return 0

    problems: list[str] = []
    for name, paths in sorted(mine.items()):
        if len(paths) > 1:
            problems.append(f"declared twice inside the plugin: {name} — {', '.join(paths)}")

    checked = 0
    for zip_path in themes:
        theirs = theme_functions(zip_path)
        checked += len(theirs)
        for name in sorted(set(mine) & set(theirs)):
            problems.append(
                f"{name} — plugin: {', '.join(mine[name])} | theme: {', '.join(theirs[name])}"
            )

    if verbose:
        print(f"plugin functions: {len(mine)}")
        for zip_path in themes:
            print(f"theme functions: {len(theme_functions(zip_path))} in {zip_path.name}")

    if problems:
        print("FATAL name collisions:")
        for line in problems:
            print(f"  {line}")
        return 1

    print(
        f"OK {len(mine)} plugin functions, {checked} theme function declarations, "
        f"no collision across {len(themes)} theme release(s)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

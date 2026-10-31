#!/usr/bin/env python3
"""Fail when the plugin and a shipped theme declare the same name.

Why this exists: on 2026-10-31 the plugin died with
`Cannot redeclare function hj_sc_enqueue_assets()` on the live site. The child
theme 0.30.1 loads `inc/symptom-checker-test.php`, which declared
`hj_sc_enqueue_assets()`; the plugin declared the same name in
`includes/page.php`. WordPress could only report «مشکل جدی».

Phase 0 of the migration retires that legacy theme page, so the theme source in
this repository must declare no `hj_sc_*` / `HJ_SC_*` symbol at all. This gate
now checks three things:

1. no function or constant is declared twice inside the plugin;
2. no symbol is declared by both the plugin and a theme (source folders **and**
   the release zips, because a release-only commit may be newer than the theme
   folder on `main`);
3. the theme source folders carry none of the legacy `hj_sc_*` / `HJ_SC_*`
   names — the family that broke the site. Release zips are reported as a
   warning instead: they are frozen artifacts that cannot be fixed in place.

    python3 tools/symptom-checker-plugin/check-name-collisions.py
    python3 tools/symptom-checker-plugin/check-name-collisions.py --verbose
"""
from __future__ import annotations

import re
import sys
import zipfile
from dataclasses import dataclass, field
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
PLUGIN = REPO / "plugins" / "hamyar-symptom-checker"
THEMES = REPO / "themes"
RELEASES = REPO / "releases"

FUNCTION_RE = re.compile(r"^\s*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(", re.M)
DEFINE_RE = re.compile(r"\bdefine\s*\(\s*['\"]([A-Za-z_][A-Za-z0-9_]*)['\"]")
CONST_RE = re.compile(r"\bconst\s+([A-Za-z_][A-Za-z0-9_]*)\s*=")
GUARD_WINDOW = 240

# The historical family. `hj_sc_` is the theme's, `hjsc_` is the plugin's, and
# the whole point of phase 0 is that the theme stops using its half.
LEGACY_FUNCTION_PREFIX = "hj_sc_"
LEGACY_CONSTANT_PREFIX = "HJ_SC_"

# The page that carried those functions and collided with the plugin. Phase 0
# retires it, so its presence in the theme source is a failure by itself.
LEGACY_FILES = ("symptom-checker-test.php",)


@dataclass
class Symbols:
    """Declared functions and constants, each mapped to the places it appears."""

    functions: dict[str, list[str]] = field(default_factory=dict)
    constants: dict[str, list[str]] = field(default_factory=dict)

    def add(self, kind: str, name: str, origin: str) -> None:
        self.__dict__[kind].setdefault(name, []).append(origin)

    def merge(self, other: "Symbols") -> None:
        for kind in ("functions", "constants"):
            for name, origins in other.__dict__[kind].items():
                self.__dict__[kind].setdefault(name, []).extend(origins)


def display(path: Path) -> str:
    """Path relative to the repo, or absolute when it lives outside (tests)."""
    try:
        return str(path.relative_to(REPO))
    except ValueError:
        return str(path)


def declared_functions(text: str) -> list[str]:
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


def declared_constants(text: str) -> list[str]:
    return [match.group(1) for match in DEFINE_RE.finditer(text)] + [
        match.group(1) for match in CONST_RE.finditer(text)
    ]


def symbols_from_text(text: str, origin: str) -> Symbols:
    symbols = Symbols()
    for name in declared_functions(text):
        symbols.add("functions", name, origin)
    for name in declared_constants(text):
        symbols.add("constants", name, origin)
    return symbols


def plugin_symbols() -> Symbols:
    symbols = Symbols()
    for path in sorted(PLUGIN.rglob("*.php")):
        text = path.read_text(encoding="utf-8", errors="replace")
        symbols.merge(symbols_from_text(text, display(path)))
    return symbols


def theme_folder_symbols() -> Symbols:
    symbols = Symbols()
    for path in sorted(THEMES.rglob("*.php")):
        text = path.read_text(encoding="utf-8", errors="replace")
        symbols.merge(symbols_from_text(text, display(path)))
    return symbols


def theme_zip_symbols(zip_path: Path) -> Symbols:
    symbols = Symbols()
    with zipfile.ZipFile(zip_path) as archive:
        for info in archive.infolist():
            if not info.filename.endswith(".php") or info.is_dir():
                continue
            text = archive.read(info).decode("utf-8", errors="replace")
            symbols.merge(symbols_from_text(text, f"{zip_path.name}:{info.filename}"))
    return symbols


def legacy_symbols(symbols: Symbols) -> list[tuple[str, str, list[str]]]:
    """(kind, name, origins) for every symbol in the historical family."""
    found = []
    for name, origins in symbols.functions.items():
        if name.lower().startswith(LEGACY_FUNCTION_PREFIX):
            found.append(("function", name, origins))
    for name, origins in symbols.constants.items():
        if name.upper().startswith(LEGACY_CONSTANT_PREFIX):
            found.append(("constant", name, origins))
    return sorted(found)


def collisions(mine: Symbols, theirs: Symbols) -> list[str]:
    """Same-name declarations that would fatal or warn at runtime."""
    problems = []
    # PHP function names are case-insensitive, constants are not.
    lowercase_mine = {name.lower(): name for name in mine.functions}
    for name, origins in theirs.functions.items():
        hit = lowercase_mine.get(name.lower())
        if hit:
            problems.append(
                f"function {hit} — plugin: {', '.join(mine.functions[hit])} | theme: {', '.join(origins)}"
            )
    for name, origins in theirs.constants.items():
        if name in mine.constants:
            problems.append(
                f"constant {name} — plugin: {', '.join(mine.constants[name])} | theme: {', '.join(origins)}"
            )
    return problems


def main(argv: list[str]) -> int:
    verbose = "--verbose" in argv
    mine = plugin_symbols()
    folder = theme_folder_symbols()
    zips = sorted(RELEASES.glob("lax-theme-*.zip"))

    problems: list[str] = []
    warnings: list[str] = []

    for name, origins in sorted(mine.functions.items()):
        if len(origins) > 1:
            problems.append(f"declared twice inside the plugin: {name} — {', '.join(origins)}")

    if not zips and not folder.functions:
        print("SKIP no theme sources and no theme release zip")
        return 0

    problems.extend(collisions(mine, folder))

    checked = len(folder.functions) + len(folder.constants)
    for zip_path in zips:
        theirs = theme_zip_symbols(zip_path)
        checked += len(theirs.functions) + len(theirs.constants)
        problems.extend(collisions(mine, theirs))
        legacy = legacy_symbols(theirs)
        if legacy:
            names = ", ".join(sorted({name for _, name, _ in legacy}))
            warnings.append(
                f"{zip_path.name} still ships {len(legacy)} legacy theme function(s): {names} "
                "(frozen release — retire by shipping a newer theme)"
            )

    for path in sorted(THEMES.rglob("*")):
        if path.is_file() and path.name in LEGACY_FILES:
            problems.append(
                f"legacy theme file {display(path)} — retired in phase 0 "
                "(its hj_sc_* functions collided with the plugin)"
            )

    legacy_source = legacy_symbols(folder)
    for kind, name, origins in legacy_source:
        problems.append(
            f"legacy {kind} {name} in the theme source — retired in phase 0: {', '.join(origins)}"
        )

    if verbose:
        print(f"plugin: {len(mine.functions)} functions, {len(mine.constants)} constants")
        print(f"theme source: {len(folder.functions)} functions, {len(folder.constants)} constants")
        for zip_path in zips:
            theirs = theme_zip_symbols(zip_path)
            print(f"  {zip_path.name}: {len(theirs.functions)} functions, {len(theirs.constants)} constants")

    for line in warnings:
        print(f"WARNING {line}")

    if problems:
        print("FATAL name collisions:")
        for line in problems:
            print(f"  {line}")
        return 1

    print(
        f"OK {len(mine.functions)} plugin functions + {len(mine.constants)} constants, "
        f"{checked} theme declarations checked across {len(zips)} release zip(s) and "
        f"{len(folder.functions) + len(folder.constants)} in the theme source, "
        f"no collision and no legacy hj_sc_/HJ_SC_ symbol in the source"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

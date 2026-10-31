#!/usr/bin/env python3
"""Tests for the installable WordPress package of the symptom-checker.

Run from the repository root:

    python3 -m unittest discover -s tools/symptom-checker-plugin -p 'test_*.py' -v

The tests check the package structure, that every bundled asset is byte-identical
to its repository source, that the build is deterministic, and that the drift
guard actually fails when a package is tampered with.
"""

from __future__ import annotations

import io
import json
import posixpath
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))

import build_package as build  # noqa: E402  (path setup above)


class PackageTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.package = build.collect()
        cls.data = cls.package.zip_bytes()

    def test_expected_tree(self) -> None:
        names = set(self.package.files)
        for required in (
            "hamyar-symptom-checker.php",
            "includes/mapping.php",
            "includes/page.php",
            "templates/checker-page.php",
            "README.md",
            "assets/SOURCES.json",
            "assets/ui/app.js",
            "assets/ui/view-model.js",
            "assets/ui/question-help.js",
            "assets/ui/styles.css",
            "assets/ui/anatomy-hero.jpg",
            "assets/engine/src/engine.js",
            "assets/engine/src/adapter.js",
            "assets/engine/src/load-bundle.js",
            "assets/engine/src/strategy.js",
            "assets/engine/src/profile-match.js",
            "assets/engine/data/evidence-map.v1.json",
            "assets/data/knowledge-bundle.v3.json",
        ):
            self.assertIn(required, names)

    def test_no_demo_or_cache_files(self) -> None:
        for name in self.package.files:
            self.assertNotIn("__pycache__", name)
            self.assertFalse(name.endswith(".pyc"), name)
            self.assertNotIn("index.html", name, "the WordPress page uses the plugin template")
            self.assertNotIn(".test.js", name, "tests must not ship to the site")
            self.assertNotIn("node_modules", name)

    def test_diagnostics_never_ship_inside_the_plugin(self) -> None:
        """`hj-sc-fatal-catcher` is a debugging tool, not part of the product.

        It must stay outside the shipped package: a fatal catcher inside the
        plugin would hide the very errors the owner needs to see in wp-admin.
        """
        for name in self.package.files:
            lowered = name.lower()
            self.assertNotIn("fatal-catcher", lowered, name)
            self.assertNotIn("fatal_catcher", lowered, name)
            self.assertNotIn("diagnostics", lowered, name)
            self.assertNotIn("mu-plugins", lowered, name)
            self.assertNotIn("golden", lowered, "golden snapshots are test fixtures")
            self.assertFalse(lowered.endswith(".jsonl") or lowered.endswith(".jsonl.gz"), name)

    def test_fatal_catcher_lives_only_in_tools(self) -> None:
        source = HERE / "diagnostics" / "hj-sc-fatal-catcher.php"
        self.assertTrue(source.is_file(), "the debug tool must keep its own source")
        for path in build.PLUGIN_DIR.rglob("*.php"):
            self.assertNotIn("fatal-catcher", path.name, f"debug tool inside the plugin: {path}")
        self.assertNotIn("diagnostics/hj-sc-fatal-catcher.php", build.PLUGIN_FILES)

    def test_assets_match_their_sources(self) -> None:
        for target, meta in self.package.sources.items():
            source = build.REPO / meta["source"]
            bundled = self.package.files[f"assets/{target}"]
            if target.endswith(".js"):
                # JS is copied with its internal imports content-addressed
                # (`?ver=<sha8>`); stripping those queries must give back the
                # repository source byte for byte.
                stripped = build.IMPORT_SPECIFIER.sub(
                    lambda match: f"{match.group(1)}{match.group(2)}{match.group(3)}{match.group(2)}",
                    bundled.decode("utf-8"),
                ).encode("utf-8")
                self.assertEqual(source.read_bytes(), stripped, f"{target} differs from {meta['source']}")
            else:
                self.assertEqual(source.read_bytes(), bundled, f"{target} differs from {meta['source']}")
            self.assertEqual(build.sha256(bundled), meta["sha256"], target)

    def test_internal_imports_are_content_addressed(self) -> None:
        """Every internal import carries the sha8 of the file it resolves to.

        A cache (browser or LiteSpeed) keys a module by its URL; without a
        query an upgraded package can boot a new entry script against an old
        cached module — the missing-export outage of 0.1.14. The query makes
        that mixture impossible and lets the page watchdog hash-check what
        the server serves.
        """
        manifest = json.loads(self.package.files["assets/SOURCES.json"])["asset_sources"]
        seen = 0
        for name in self.package.files:
            if not name.startswith("assets/") or not name.endswith(".js"):
                continue
            body = self.package.files[name].decode("utf-8")
            directory = posixpath.dirname(name[len("assets/") :])
            for match in build.IMPORT_SPECIFIER.finditer(body):
                seen += 1
                spec = match.group(3)
                query = match.group(4)
                self.assertIsNotNone(query, f"{name}: import {spec} ships without a cache-buster")
                target = posixpath.normpath(posixpath.join(directory, spec))
                self.assertIn(target, manifest, f"{name}: import {spec} leaves the package")
                self.assertEqual(
                    query,
                    f"?ver={manifest[target]['sha256'][:8]}",
                    f"{name}: import {spec} does not carry the sha8 of {target}",
                )
        self.assertGreater(seen, 0, "the module graph has no internal import at all?")

    def test_repository_sources_stay_plain(self) -> None:
        """The dev demo and the node tests run the sources without queries."""
        for relative in ("symptom-checker/ui/app.js", "symptom-checker/engine/src/engine.js"):
            text = (build.REPO / relative).read_text(encoding="utf-8")
            for match in build.IMPORT_SPECIFIER.finditer(text):
                self.assertIsNone(match.group(4), f"{relative} must stay query-free")

    def test_manifest_covers_every_asset(self) -> None:
        manifest = json.loads(self.package.files["assets/SOURCES.json"])
        asset_files = {
            name[len("assets/") :]
            for name in self.package.files
            if name.startswith("assets/") and name not in ("assets/SOURCES.json", "assets/README.md")
        }
        self.assertEqual(sorted(manifest["asset_sources"]), sorted(asset_files))
        self.assertEqual(manifest["version"], build.plugin_version())

    def test_build_is_deterministic(self) -> None:
        first = build.collect().zip_bytes()
        second = build.collect().zip_bytes()
        self.assertEqual(build.sha256(first), build.sha256(second))

    def test_zip_layout(self) -> None:
        with zipfile.ZipFile(io.BytesIO(self.data)) as archive:
            names = archive.namelist()
        self.assertTrue(all(name.startswith("hamyar-symptom-checker/") for name in names))
        self.assertEqual(len(names), len(set(names)), "duplicate entries in zip")

    def test_check_accepts_a_fresh_build(self) -> None:
        with tempfile.TemporaryDirectory() as work:
            target, _ = build.build(Path(work))
            self.assertEqual(build.verify(target), [])

    def test_check_reports_drift(self) -> None:
        with tempfile.TemporaryDirectory() as work:
            target, _ = build.build(Path(work))
            # Rewrite one entry so the packaged copy no longer matches its source.
            with zipfile.ZipFile(target) as archive:
                entries = {name: archive.read(name) for name in archive.namelist()}
            key = "hamyar-symptom-checker/assets/ui/app.js"
            entries[key] = entries[key] + b"\n// tampered\n"
            with zipfile.ZipFile(target, "w", compression=zipfile.ZIP_DEFLATED) as archive:
                for name, payload in entries.items():
                    archive.writestr(name, payload)
            problems = build.verify(target)
            self.assertTrue(any("content drift" in problem for problem in problems), problems)

    def test_check_reports_extra_files(self) -> None:
        with tempfile.TemporaryDirectory() as work:
            target, _ = build.build(Path(work))
            with zipfile.ZipFile(target, "a") as archive:
                archive.writestr("hamyar-symptom-checker/assets/ui/extra.js", "// extra")
            problems = build.verify(target)
            self.assertTrue(any("unexpected file" in problem for problem in problems), problems)

    def test_plugin_header_and_readme_agree_on_version(self) -> None:
        version = build.plugin_version()
        persian = version.translate(str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹"))
        title = (build.PLUGIN_DIR / "README.md").read_text(encoding="utf-8").splitlines()[0]
        self.assertTrue(
            version in title or persian in title,
            f"the README title must state the same version, got: {title}",
        )

    def test_php_files_guard_direct_access(self) -> None:
        for name in self.package.files:
            if not name.endswith(".php"):
                continue
            text = self.package.files[name].decode("utf-8")
            self.assertIn("defined( 'ABSPATH' )", text, name)


if __name__ == "__main__":
    unittest.main()

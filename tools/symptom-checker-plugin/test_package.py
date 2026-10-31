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
            "assets/engine/data/evidence-map.v1.json",
            "assets/data/knowledge-bundle.v1.json",
        ):
            self.assertIn(required, names)

    def test_no_demo_or_cache_files(self) -> None:
        for name in self.package.files:
            self.assertNotIn("__pycache__", name)
            self.assertFalse(name.endswith(".pyc"), name)
            self.assertNotIn("index.html", name, "the WordPress page uses the plugin template")
            self.assertNotIn(".test.js", name, "tests must not ship to the site")
            self.assertNotIn("node_modules", name)

    def test_assets_match_their_sources(self) -> None:
        for target, meta in self.package.sources.items():
            source = build.REPO / meta["source"]
            bundled = self.package.files[f"assets/{target}"]
            self.assertEqual(source.read_bytes(), bundled, f"{target} differs from {meta['source']}")
            self.assertEqual(build.sha256(bundled), meta["sha256"], target)

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

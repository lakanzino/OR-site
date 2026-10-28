#!/usr/bin/env python3
"""Check repository theme versions and release packages without external dependencies.

Run from the repository root:
    python3 themes/lax-theme-child/tests/test_release_versions.py

Published archives are immutable. Post-release documentation and tests can be
updated in the source tree without altering the already published archive.
"""

import json
import re
import unittest
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[3]
CHILD = ROOT / "themes" / "lax-theme-child"
PARENT = ROOT / "themes" / "lax-theme"
RELEASES = ROOT / "releases"
VERSION = r"\d+(?:\.\d+)+"


def header(text, name):
    match = re.search(rf"(?im)^\s*\*?\s*{re.escape(name)}:\s*({VERSION})\s*$", text)
    if not match:
        raise AssertionError(f"Missing {name} version header")
    return match.group(1)


def ordered(versions):
    return sorted(versions, key=lambda value: tuple(map(int, value.split("."))), reverse=True)


class ReleaseVersionsTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.manifest = json.loads((RELEASES / "orsite-release.json").read_text(encoding="utf-8"))
        cls.child_style = (CHILD / "style.css").read_text(encoding="utf-8")
        cls.parent_style = (PARENT / "style.css").read_text(encoding="utf-8")
        cls.child_readme = (CHILD / "readme.txt").read_text(encoding="utf-8")
        cls.child_changelog = (CHILD / "CHANGELOG.md").read_text(encoding="utf-8")
        cls.child_version = header(cls.child_style, "Version")
        cls.parent_version = header(cls.parent_style, "Version")

    def test_child_metadata_and_manifest_agree(self):
        functions = (CHILD / "functions.php").read_text(encoding="utf-8")
        match = re.search(
            rf"define\s*\(\s*['\"]LAX_THEME_CHILD_VERSION['\"]\s*,\s*['\"]({VERSION})['\"]",
            functions,
        )
        self.assertIsNotNone(match, "Missing LAX_THEME_CHILD_VERSION")
        self.assertEqual(match.group(1), self.child_version)
        self.assertEqual(header(self.child_readme, "Stable tag"), self.child_version)
        self.assertEqual(self.manifest["child"]["version"], self.child_version)
        self.assertEqual(self.manifest["child"]["asset"], "lax-theme-child.zip")

    def test_parent_metadata_and_manifest_agree(self):
        readme = (PARENT / "readme.txt").read_text(encoding="utf-8")
        self.assertEqual(header(readme, "Stable tag"), self.parent_version)
        self.assertEqual(self.manifest["parent"]["version"], self.parent_version)
        self.assertEqual(self.manifest["parent"]["asset"], "lax-theme.zip")

    def test_release_archives_have_valid_theme_versions(self):
        for slug, version in (("lax-theme-child", self.child_version), ("lax-theme", self.parent_version)):
            with self.subTest(theme=slug), ZipFile(RELEASES / f"{slug}.zip") as archive:
                self.assertIsNone(archive.testzip(), "Corrupt ZIP member")
                names = archive.namelist()
                self.assertTrue(names)
                self.assertTrue(all(name.startswith(f"{slug}/") for name in names))
                self.assertTrue(all(".." not in Path(name).parts for name in names))
                style = archive.read(f"{slug}/style.css").decode("utf-8")
                self.assertEqual(header(style, "Version"), version)

    def test_released_runtime_files_match_source(self):
        # Documentation and tests can receive post-release corrections without
        # rewriting an immutable release or triggering a new site update.
        excluded = {"CHANGELOG.md", "SOFT-UI.md", "readme.txt"}

        def is_runtime(relative):
            return relative.as_posix() not in excluded and relative.parts[0] != "tests"

        with ZipFile(RELEASES / "lax-theme-child.zip") as archive:
            source_files = {
                path.relative_to(CHILD)
                for path in CHILD.rglob("*")
                if path.is_file() and is_runtime(path.relative_to(CHILD))
            }
            archive_files = {
                Path(name).relative_to("lax-theme-child")
                for name in archive.namelist()
                if not name.endswith("/") and is_runtime(Path(name).relative_to("lax-theme-child"))
            }
            self.assertEqual(source_files, archive_files, "Runtime files differ between source and ZIP")
            for relative in sorted(source_files):
                with self.subTest(file=relative.as_posix()):
                    self.assertEqual(
                        archive.read(f"lax-theme-child/{relative.as_posix()}"), (CHILD / relative).read_bytes()
                    )

    def test_removed_overlay_and_legacy_tools_are_not_packaged_or_loaded(self):
        obsolete = ("image-watermark.php", "inline-image-repair.php", "seo-health-check.php")
        functions = (CHILD / "functions.php").read_text(encoding="utf-8")
        with ZipFile(RELEASES / "lax-theme-child.zip") as archive:
            names = set(archive.namelist())
            for filename in obsolete:
                with self.subTest(filename=filename):
                    self.assertNotIn(filename, functions)
                    self.assertNotIn(f"lax-theme-child/inc/{filename}", names)
            # Removing the obsolete diagnostic must not remove the actual policy.
            self.assertIn("inc/seo-index-policy.php", functions)
            self.assertIn("lax-theme-child/inc/seo-index-policy.php", names)
            for style in ("assets/css/child.css", "assets/css/soft-ui.css"):
                self.assertNotIn(".orsite-wm", archive.read(f"lax-theme-child/{style}").decode("utf-8"))

    def test_partial_file_update_has_care_helper_fallback(self):
        functions = (CHILD / "functions.php").read_text(encoding="utf-8")
        front_page = (CHILD / "front-page.php").read_text(encoding="utf-8")
        self.assertIn("is_readable( get_stylesheet_directory() . '/inc/care-tool.php' )", functions)
        self.assertGreaterEqual(front_page.count("function_exists( 'hj_care_option_label' )"), 2)

    def test_theme_histories_are_complete_unique_and_ordered(self):
        changelog = re.findall(rf"^## \[({VERSION})\]", self.child_changelog, re.MULTILINE)
        readme = re.findall(rf"^= ({VERSION}) =", self.child_readme, re.MULTILINE)
        for label, versions in (("CHANGELOG.md", changelog), ("readme.txt", readme)):
            with self.subTest(document=label):
                self.assertTrue(versions)
                self.assertEqual(versions[0], self.child_version)
                self.assertEqual(len(versions), len(set(versions)), f"Duplicate versions in {label}")
                self.assertEqual(versions, ordered(versions), f"Versions out of order in {label}")
        self.assertEqual(changelog, readme, "Version histories differ between the two documents")

    def test_manifest_has_current_changelog_and_valid_historical_subset(self):
        sections = [entry["version"] for entry in self.manifest["changelog_sections"]]
        history = set(re.findall(rf"^## \[({VERSION})\]", self.child_changelog, re.MULTILINE))
        self.assertTrue(sections)
        self.assertEqual(sections[0], self.child_version)
        self.assertEqual(len(sections), len(set(sections)))
        self.assertEqual(sections, ordered(sections))
        self.assertTrue(set(sections).issubset(history))
        self.assertTrue(self.manifest["changelog"][0].startswith(self.child_version + " "))

    def test_plugin_source_and_package_versions_agree(self):
        for slug in ("orsite-media-matcher", "hamyar-symptom-guide"):
            with self.subTest(plugin=slug):
                source = (ROOT / "plugins" / slug / f"{slug}.php").read_text(encoding="utf-8")
                with ZipFile(RELEASES / f"{slug}.zip") as archive:
                    self.assertIsNone(archive.testzip())
                    packaged = archive.read(f"{slug}/{slug}.php").decode("utf-8")
                self.assertEqual(header(source, "Version"), header(packaged, "Version"))


if __name__ == "__main__":
    unittest.main(verbosity=2)

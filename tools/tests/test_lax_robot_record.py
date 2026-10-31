#!/usr/bin/env python3
"""Tests for the versioned development record under `docs/lax-robot/`.

The record's promise is its naming rule — **tool name = folder name = version** —
and its manifest, which lets a reader prove what they are holding. Both are easy
to break by hand, so they are tested:

* every `lax-robot-<version>` folder says the same version inside;
* every `tools/<tool>-<version>` folder matches its own README;
* `MANIFEST.json` is exactly the files on disk, with their real hashes;
* the index links to every version that exists.
"""
from __future__ import annotations

import hashlib
import json
import re
import unittest
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
ROOT = REPO / "docs/lax-robot"
VERSION = re.compile(r"^lax-robot-(\d+\.\d+\.\d+)$")
TOOL = re.compile(r"^([a-z0-9-]+?)-(\d+\.\d+\.\d+)$")


class NamingTest(unittest.TestCase):
    def test_every_version_folder_names_its_version(self) -> None:
        versions = [p for p in ROOT.iterdir() if p.is_dir()]
        self.assertTrue(versions, "no version folder found")
        for folder in versions:
            match = VERSION.match(folder.name)
            self.assertIsNotNone(match, f"{folder.name} is not lax-robot-<x.y.z>")
            readme = (folder / "README.md").read_text(encoding="utf-8")
            self.assertIn(f"**نسخهٔ سند:** `{match.group(1)}`", readme, folder.name)

    def test_tool_folders_follow_tool_version(self) -> None:
        for folder in ROOT.glob("lax-robot-*/tools/*"):
            self.assertTrue(folder.is_dir(), folder)
            match = TOOL.match(folder.name)
            self.assertIsNotNone(match, f"{folder.name} is not <tool>-<x.y.z>")
            readme = (folder / "README.md").read_text(encoding="utf-8")
            self.assertIn(match.group(1), readme, folder.name)
            self.assertIn(match.group(2), readme, folder.name)

    def test_index_lists_every_version_folder(self) -> None:
        index = (ROOT / "README.md").read_text(encoding="utf-8")
        for folder in ROOT.iterdir():
            if folder.is_dir() and VERSION.match(folder.name):
                self.assertIn(folder.name, index, folder.name)


class ManifestTest(unittest.TestCase):
    def test_manifest_matches_the_files(self) -> None:
        for folder in ROOT.glob("lax-robot-*"):
            manifest_path = folder / "MANIFEST.json"
            self.assertTrue(manifest_path.is_file(), folder.name)
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            recorded = {entry["path"]: entry for entry in manifest["files"]}
            on_disk = {
                path.relative_to(folder).as_posix(): path
                for path in folder.rglob("*")
                if path.is_file() and path.name != "MANIFEST.json"
            }
            self.assertEqual(sorted(recorded), sorted(on_disk), folder.name)
            for relative, path in on_disk.items():
                digest = hashlib.sha256(path.read_bytes()).hexdigest()
                self.assertEqual(recorded[relative]["sha256"], digest, relative)
                self.assertEqual(recorded[relative]["bytes"], path.stat().st_size, relative)

    def test_manifest_declares_the_folder_version(self) -> None:
        for folder in ROOT.glob("lax-robot-*"):
            manifest = json.loads((folder / "MANIFEST.json").read_text(encoding="utf-8"))
            version = VERSION.match(folder.name).group(1)
            self.assertEqual(manifest["version"], version)
            self.assertEqual(manifest["name"], "lax-robot")


if __name__ == "__main__":
    unittest.main()

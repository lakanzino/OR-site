#!/usr/bin/env python3
from pathlib import Path
from zipfile import ZipFile
import unittest

ROOT = Path(__file__).resolve().parents[3]

class ManagerPackageTest(unittest.TestCase):
    def test_only_runtime_matches_source(self):
        with ZipFile(ROOT / 'releases/hj-vote-manager-0.2.0.zip') as archive:
            self.assertIsNone(archive.testzip())
            self.assertEqual(archive.namelist(), ['hj-vote-manager/hj-vote-manager.php'])
            self.assertEqual(archive.read(archive.namelist()[0]), (ROOT / 'tools/hj-vote-manager/hj-vote-manager.php').read_bytes())

if __name__ == '__main__':
    unittest.main(verbosity=2)

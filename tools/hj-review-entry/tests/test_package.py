#!/usr/bin/env python3
from pathlib import Path
from zipfile import ZipFile
import unittest

ROOT = Path(__file__).resolve().parents[3]

class ReviewEntryPackage(unittest.TestCase):
    def test_runtime_only(self):
        with ZipFile(ROOT / 'releases/hj-review-entry-1.1.0.zip') as z:
            self.assertIsNone(z.testzip())
            self.assertEqual(z.namelist(), ['hj-review-entry/hj-review-entry.php'])
            self.assertEqual(z.read(z.namelist()[0]), (ROOT / 'tools/hj-review-entry/hj-review-entry.php').read_bytes())

if __name__ == '__main__': unittest.main(verbosity=2)

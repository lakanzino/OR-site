#!/usr/bin/env python3
from pathlib import Path
from zipfile import ZipFile
import unittest

ROOT = Path(__file__).resolve().parents[3]

class LabPackageTest(unittest.TestCase):
    def test_only_runtime_matches_source(self):
        with ZipFile(ROOT / 'releases/hj-vote-lab-0.2.0.zip') as z:
            self.assertIsNone(z.testzip())
            self.assertEqual(z.namelist(), ['hj-vote-lab/hj-vote-lab.php'])
            self.assertEqual(z.read(z.namelist()[0]), (ROOT / 'tools/hj-vote-lab/hj-vote-lab.php').read_bytes())

if __name__ == '__main__':
    unittest.main(verbosity=2)

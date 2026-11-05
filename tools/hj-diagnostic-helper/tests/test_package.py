#!/usr/bin/env python3
"""Validate the intentionally single-file installable diagnostic ZIP."""
from pathlib import Path
from zipfile import ZipFile
import unittest

ROOT = Path(__file__).resolve().parents[3]
SOURCE = ROOT / 'tools/hj-diagnostic-helper/hj-diagnostic-helper.php'
ZIP = ROOT / 'releases/hj-diagnostic-helper-0.1.0.zip'

class DiagnosticPackageTest(unittest.TestCase):
    def test_zip_contains_only_exact_runtime_file(self):
        with ZipFile(ZIP) as package:
            self.assertIsNone(package.testzip())
            self.assertEqual(package.namelist(), ['hj-diagnostic-helper/hj-diagnostic-helper.php'])
            self.assertEqual(package.read(package.namelist()[0]), SOURCE.read_bytes())
        self.assertIn(b'Version: 0.1.0', SOURCE.read_bytes())

if __name__ == '__main__':
    unittest.main(verbosity=2)

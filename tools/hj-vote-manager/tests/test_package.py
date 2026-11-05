#!/usr/bin/env python3
from pathlib import Path
import re
from zipfile import ZipFile
import unittest

ROOT = Path(__file__).resolve().parents[3]

class ManagerPackageTest(unittest.TestCase):
    def test_only_runtime_matches_source(self):
        with ZipFile(ROOT / 'releases/hj-vote-manager-0.2.0.zip') as archive:
            self.assertIsNone(archive.testzip())
            self.assertEqual(archive.namelist(), ['hj-vote-manager/hj-vote-manager.php'])
            self.assertEqual(archive.read(archive.namelist()[0]), (ROOT / 'tools/hj-vote-manager/hj-vote-manager.php').read_bytes())

    def test_existing_audit_definition_unchanged(self):
        with ZipFile(ROOT / 'releases/hj-vote-manager-0.1.0.zip') as archive:
            old = archive.read('hj-vote-manager/hj-vote-manager.php').decode('utf-8')
        new = (ROOT / 'tools/hj-vote-manager/hj-vote-manager.php').read_text(encoding='utf-8')
        expression = r'\$sql = "CREATE TABLE .+?";'
        previous = re.search(expression, old)
        current = re.search(expression, new)
        self.assertIsNotNone(previous)
        self.assertIsNotNone(current)
        self.assertEqual(previous.group(), current.group())

if __name__ == '__main__':
    unittest.main(verbosity=2)

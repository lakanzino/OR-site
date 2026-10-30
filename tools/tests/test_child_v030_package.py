#!/usr/bin/env python3
"""Validate the separate installable v0.30.0 archive."""
import re
import unittest
from pathlib import Path, PurePosixPath
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[2]
PACKAGE = ROOT / 'releases/lax-theme-child-0.30.0-installable.zip'
SLUG = 'lax-theme-child/'


class ChildPackageTest(unittest.TestCase):
    def test_installable_child_package(self):
        with ZipFile(PACKAGE) as archive:
            self.assertIsNone(archive.testzip())
            names = archive.namelist()
            self.assertEqual(len(names), len(set(names)))
            self.assertTrue(all(name.startswith(SLUG) and '..' not in PurePosixPath(name).parts for name in names))
            style = archive.read(SLUG + 'style.css').decode('utf-8')
            self.assertTrue(style.startswith('/*'))
            header = style[:8192].split('*/', 1)[0]
            for field, expected in [('Theme Name', 'Lax Theme Child'), ('Template', 'lax-theme'),
                                    ('Version', '0.30.0'), ('Text Domain', 'lax-theme-child')]:
                self.assertRegex(header, rf'(?m)^{re.escape(field)}:\s*{re.escape(expected)}\s*$')
            self.assertIn(':root', style, 'Original CSS must be preserved')
            functions = archive.read(SLUG + 'functions.php').decode('utf-8')
            self.assertRegex(functions, r"define\s*\(\s*'LAX_THEME_CHILD_VERSION'\s*,\s*'0\.30\.0'")
            self.assertIn(SLUG + 'inc/symptom-checker-test.php', names)
            self.assertIn(SLUG + 'data/symptom-checker/diseases.json', names)


if __name__ == '__main__':
    unittest.main()

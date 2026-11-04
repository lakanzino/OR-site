#!/usr/bin/env python3
"""Check the installable hamyar-doctors ZIP against the working source tree."""
import re
import unittest
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[3]
PLUGIN = ROOT / 'plugins' / 'hamyar-doctors'


class DoctorPackageTest(unittest.TestCase):
    def test_current_package_matches_runtime_source(self):
        main = (PLUGIN / 'hamyar-doctors.php').read_text(encoding='utf-8')
        header = re.search(r'^\s*\* Version:\s*([0-9.]+)', main, re.M)
        constant = re.search(r"define\(\s*'HJ_DOCTORS_VERSION'\s*,\s*'([0-9.]+)'\s*\)", main)
        self.assertIsNotNone(header)
        self.assertIsNotNone(constant)
        self.assertEqual(header.group(1), constant.group(1))
        package = ROOT / 'releases' / f'hamyar-doctors-{header.group(1)}.zip'
        expected = {p.relative_to(PLUGIN) for p in PLUGIN.rglob('*') if p.is_file() and 'tests' not in p.relative_to(PLUGIN).parts and not any(x in p.relative_to(PLUGIN).parts for x in ('.git', 'node_modules', '__pycache__'))}
        with ZipFile(package) as z:
            self.assertIsNone(z.testzip())
            self.assertTrue(all(x.startswith('hamyar-doctors/') and not x.endswith('/') for x in z.namelist()))
            paths = {Path(x).relative_to('hamyar-doctors') for x in z.namelist()}
            self.assertEqual(paths, expected)
            self.assertFalse(any('tests' in path.parts for path in paths))
            for path in sorted(paths):
                with self.subTest(path=str(path)):
                    self.assertEqual(z.read(f'hamyar-doctors/{path.as_posix()}'), (PLUGIN / path).read_bytes())


if __name__ == '__main__':
    unittest.main(verbosity=2)

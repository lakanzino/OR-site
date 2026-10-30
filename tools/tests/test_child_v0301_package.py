#!/usr/bin/env python3
"""Validate the installable v0.30.1 hotfix archive against the v0.30.0 one."""
import hashlib
import re
import unittest
from pathlib import Path, PurePosixPath
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[2]
OLD_PACKAGE = ROOT / 'releases/lax-theme-child-0.30.0-installable.zip'
NEW_PACKAGE = ROOT / 'releases/lax-theme-child-0.30.1-installable.zip'
SLUG = 'lax-theme-child/'
SOURCE_DIRECTORY = ROOT / 'themes/lax-theme-child/inc/disease-directory.php'

OLD_LINE = "update_post_meta( $post_id, '_hj_disease_profile', wp_json_encode( $profile, JSON_UNESCAPED_UNICODE ) );"
FIXED_LINE = "update_post_meta( $post_id, '_hj_disease_profile', wp_slash( wp_json_encode( $profile, JSON_UNESCAPED_UNICODE ) ) );"
CHANGED = {SLUG + 'style.css', SLUG + 'functions.php', SLUG + 'inc/disease-directory.php'}


class Child0301PackageTest(unittest.TestCase):
    def test_installable_hotfix_package(self):
        with ZipFile(OLD_PACKAGE) as old, ZipFile(NEW_PACKAGE) as new:
            self.assertIsNone(new.testzip())
            names = new.namelist()
            self.assertEqual(len(names), len(set(names)))
            self.assertTrue(all(name.startswith(SLUG) and '..' not in PurePosixPath(name).parts for name in names))
            # Same file set as the verified installed package.
            self.assertEqual(names, old.namelist())
            # Only the three intended files differ; everything else is identical.
            for name in names:
                if name.endswith('/'):
                    continue
                if name in CHANGED:
                    self.assertNotEqual(old.read(name), new.read(name), name)
                else:
                    self.assertEqual(old.read(name), new.read(name), name)

            style = new.read(SLUG + 'style.css').decode('utf-8')
            self.assertTrue(style.startswith('/*'))
            header = style[:8192].split('*/', 1)[0]
            for field, expected in [('Theme Name', 'Lax Theme Child'), ('Template', 'lax-theme'),
                                    ('Version', '0.30.1'), ('Text Domain', 'lax-theme-child')]:
                self.assertRegex(header, rf'(?m)^{re.escape(field)}:\s*{re.escape(expected)}\s*$')
            self.assertIn(':root', style, 'Original CSS must be preserved')

            functions = new.read(SLUG + 'functions.php').decode('utf-8')
            self.assertRegex(functions, r"define\s*\(\s*'LAX_THEME_CHILD_VERSION'\s*,\s*'0\.30\.1'")
            self.assertNotIn("'0.30.0'", functions)

    def test_patched_file_matches_tested_repository_source(self):
        with ZipFile(NEW_PACKAGE) as new:
            packaged = new.read(SLUG + 'inc/disease-directory.php')
        # Byte-identical to the source file already exercised by the local
        # WordPress profile-sync test (tools/symptom-checker-audit/tests/).
        self.assertEqual(packaged, SOURCE_DIRECTORY.read_bytes())
        self.assertEqual(
            hashlib.sha256(packaged).hexdigest(),
            '8e5e662f8af23ab51203548e77c528febb42da7304f0854336281e67b0b59881',
        )
        text = packaged.decode('utf-8')
        # Read-only inspector expectations after installation.
        self.assertEqual(text.count(OLD_LINE), 0)
        self.assertEqual(text.count(FIXED_LINE), 1)
        # Sync gate unchanged: installing must not re-run the profile sync.
        self.assertIn("'5' === (string) get_option( 'hj_disease_profile_version', '0' )", text)
        self.assertIn("update_option( 'hj_disease_profile_version', '5', false )", text)


if __name__ == '__main__':
    unittest.main()

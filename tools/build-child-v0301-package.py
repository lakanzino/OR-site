#!/usr/bin/env python3
"""Build the installable v0.30.1 child package from the verified v0.30.0 one.

Input must be releases/lax-theme-child-0.30.0-installable.zip (the package
whose inc/disease-directory.php was verified byte-identical to the file
installed on the live site by the read-only inspector plugin:
SHA-256 e2f47715281ceaabaf49bf9da079de3dba446038126df9d8ffcba1b73f3bccf6).

Only three changes are applied; every other entry is copied byte for byte:
  1. style.css header: Version 0.30.0 -> 0.30.1
  2. functions.php: LAX_THEME_CHILD_VERSION '0.30.0' -> '0.30.1'
  3. inc/disease-directory.php: preserve JSON escape sequences on profile
     sync by wrapping wp_json_encode() in wp_slash() (F0-11B fix). The
     profile sync version gate ('5') is intentionally NOT changed, so
     installing this package does not re-run the sync or overwrite the
     already-repaired profiles.

The build aborts unless the input file hashes match the verified values and
the patched disease-directory.php matches the repository source file that
was tested locally (SHA-256 8e5e662f...b59881).
"""
import argparse
import hashlib
import re
from pathlib import Path, PurePosixPath
from zipfile import ZIP_DEFLATED, ZipFile

SLUG = 'lax-theme-child'
STYLE = f'{SLUG}/style.css'
FUNCTIONS = f'{SLUG}/functions.php'
DIRECTORY = f'{SLUG}/inc/disease-directory.php'

EXPECTED_INPUT_SHA256 = 'e2f47715281ceaabaf49bf9da079de3dba446038126df9d8ffcba1b73f3bccf6'
EXPECTED_OUTPUT_SHA256 = '8e5e662f8af23ab51203548e77c528febb42da7304f0854336281e67b0b59881'

OLD_LINE = "\t\tupdate_post_meta( $post_id, '_hj_disease_profile', wp_json_encode( $profile, JSON_UNESCAPED_UNICODE ) );"
NEW_LINES = (
    "\t\t// WordPress unslashes meta values on write; preserve JSON escape sequences such as \\n.\n"
    "\t\tupdate_post_meta( $post_id, '_hj_disease_profile', wp_slash( wp_json_encode( $profile, JSON_UNESCAPED_UNICODE ) ) );"
)


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def build(source, destination):
    if source.resolve() == destination.resolve():
        raise ValueError('Input and output must be different files')
    with ZipFile(source) as original:
        names = original.namelist()
        if len(names) != len(set(names)) or original.testzip() is not None:
            raise ValueError('Input has duplicate or corrupt ZIP entries')
        if any(not name.startswith(SLUG + '/') or '..' in PurePosixPath(name).parts for name in names):
            raise ValueError('Expected an archive containing only the child-theme folder')
        for required in (STYLE, FUNCTIONS, DIRECTORY):
            if required not in names:
                raise ValueError(f'Missing {required}')

        # 1. style.css version header (exactly one occurrence).
        style = original.read(STYLE).decode('utf-8')
        if not style.startswith('/*') or not re.search(r'(?m)^Template:\s*lax-theme\s*$', style[:8192]):
            raise ValueError('Missing or invalid WordPress theme header')
        style, count = re.subn(r'(?m)^Version:\s*0\.30\.0\s*$', 'Version: 0.30.1', style, count=1)
        if count != 1:
            raise ValueError('Expected exactly one Version: 0.30.0 header in style.css')

        # 2. PHP version constant (exactly one occurrence).
        functions = original.read(FUNCTIONS).decode('utf-8')
        functions, count = re.subn(
            r"(define\s*\(\s*'LAX_THEME_CHILD_VERSION'\s*,\s*')0\.30\.0(')", r'\g<1>0.30.1\g<2>', functions, count=1
        )
        if count != 1:
            raise ValueError("Expected exactly one LAX_THEME_CHILD_VERSION '0.30.0' define")

        # 3. wp_slash fix, guarded by the verified live-site hash.
        directory = original.read(DIRECTORY)
        if sha256(directory) != EXPECTED_INPUT_SHA256:
            raise ValueError('Input disease-directory.php does not match the verified live-site hash')
        text = directory.decode('utf-8')
        if text.count(OLD_LINE) != 1:
            raise ValueError('Expected exactly one unpatched profile-sync line')
        patched = text.replace(OLD_LINE, NEW_LINES).encode('utf-8')
        if sha256(patched) != EXPECTED_OUTPUT_SHA256:
            raise ValueError('Patched disease-directory.php does not match the tested repository source')
        # The sync gate must stay at '5' so installation cannot re-run the sync.
        if "'5' === (string) get_option( 'hj_disease_profile_version', '0' )" not in text:
            raise ValueError('Profile sync version gate not found unchanged')

        replacements = {
            STYLE: style.encode('utf-8'),
            FUNCTIONS: functions.encode('utf-8'),
            DIRECTORY: patched,
        }
        destination.parent.mkdir(parents=True, exist_ok=True)
        with ZipFile(destination, 'w', compression=ZIP_DEFLATED) as fixed:
            for info in original.infolist():
                data = replacements.get(info.filename, original.read(info.filename))
                fixed.writestr(info, data)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('destination', type=Path)
    args = parser.parse_args()
    build(args.source, args.destination)
    print(f'Built {args.destination}')

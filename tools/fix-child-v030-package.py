#!/usr/bin/env python3
"""Create the installable v0.30.0 child package from its tagged archive.

Input is releases/lax-theme-child.zip from the child-v0.30.0 Git tag,
not GitHub's automatically generated ZIP of the entire repository.
"""
import argparse
import re
from pathlib import Path, PurePosixPath
from zipfile import ZIP_DEFLATED, ZipFile

SLUG = 'lax-theme-child'
STYLE = f'{SLUG}/style.css'
FUNCTIONS = f'{SLUG}/functions.php'


def build(source, destination):
    if source.resolve() == destination.resolve():
        raise ValueError('Input and output must be different files')
    with ZipFile(source) as original:
        names = original.namelist()
        if len(names) != len(set(names)) or original.testzip() is not None:
            raise ValueError('Input has duplicate or corrupt ZIP entries')
        if any(not name.startswith(SLUG + '/') or '..' in PurePosixPath(name).parts for name in names):
            raise ValueError('Expected an archive containing only the child-theme folder')
        if STYLE not in names or FUNCTIONS not in names:
            raise ValueError('Missing theme style.css or functions.php')
        functions = original.read(FUNCTIONS).decode('utf-8')
        if not re.search(r"define\s*\(\s*['\"]LAX_THEME_CHILD_VERSION['\"]\s*,\s*['\"]0\.30\.0['\"]", functions):
            raise ValueError('Input is not the v0.30.0 child theme')
        style = original.read(STYLE).decode('utf-8')
        if not style.startswith('/*') or not re.search(r'(?m)^Template:\s*lax-theme\s*$', style[:8192]):
            raise ValueError('Missing or invalid WordPress theme header')
        style, count = re.subn(r'(?m)^Version:\s*0\.29\.1\s*$', 'Version: 0.30.0', style, count=1)
        if count != 1:
            raise ValueError('Expected the original v0.29.1 style header')
        destination.parent.mkdir(parents=True, exist_ok=True)
        with ZipFile(destination, 'w', compression=ZIP_DEFLATED) as fixed:
            for info in original.infolist():
                data = style.encode('utf-8') if info.filename == STYLE else original.read(info.filename)
                fixed.writestr(info, data)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('destination', type=Path)
    args = parser.parse_args()
    build(args.source, args.destination)

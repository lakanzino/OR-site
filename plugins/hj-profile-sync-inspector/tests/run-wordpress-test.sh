#!/usr/bin/env bash
set -euo pipefail
plugin="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
repo="$(cd "$plugin/../.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
for command in node npm curl python3 sha256sum git; do command -v "$command" >/dev/null || { echo "Missing: $command" >&2; exit 1; }; done
mkdir "$work/tag"
git -C "$repo" show child-v0.30.0:themes/lax-theme-child/inc/disease-directory.php > "$work/tag/disease-directory.php"
npm install --prefix "$work/playground" --no-audit --no-fund --ignore-scripts @wp-playground/cli@3.1.61
curl --fail --location --silent --show-error --max-time 120 https://codeload.github.com/WordPress/WordPress/zip/refs/tags/6.8.3 -o "$work/wordpress.zip"
echo "96ddc66e09427764dc7671361ef27542dcf642d820d4280307d32913208dca3f  $work/wordpress.zip" | sha256sum -c -
python3 - "$work" "$plugin/tests/wordpress-fixture.php" <<'PY'
import json, os, sys
from zipfile import ZipFile
with ZipFile(os.path.join(sys.argv[1], 'wordpress.zip')) as archive: archive.extractall(sys.argv[1])
with open(sys.argv[2], encoding='utf-8') as f: code=f.read()
with open(os.path.join(sys.argv[1], 'blueprint.json'), 'w', encoding='utf-8') as f:
    json.dump({'preferredVersions': {'php': '8.2', 'wp': '6.8'}, 'steps': [{'step': 'runPHP', 'code': code}]}, f)
PY
node "$work/playground/node_modules/@wp-playground/cli/wp-playground.js" run-blueprint \
 --mount-before-install "$work/WordPress-6.8.3:/wordpress" \
 --mount "$plugin:/mnt/inspector" --mount "$work/tag:/mnt/tag" \
 --wordpress-install-mode install-from-existing-files --php 8.2 --blueprint "$work/blueprint.json" --verbosity normal
python3 - "$work/WordPress-6.8.3/hjsi-results.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding='utf-8') as f: report=json.load(f)
assert len(report)==7 and all(report.values()), report
print(json.dumps(report,indent=2))
PY

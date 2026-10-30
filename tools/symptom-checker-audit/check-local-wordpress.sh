#!/usr/bin/env bash
# Isolated disposable WordPress smoke test; no connection to the live site.
set -euo pipefail
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
for command in node npm curl python3 sha256sum; do
  command -v "$command" >/dev/null || { echo "Missing dependency: $command" >&2; exit 1; }
done
npm install --prefix "$work/playground" --no-audit --no-fund --ignore-scripts @wp-playground/cli@3.1.61
curl --fail --location --silent --show-error --max-time 120 \
  https://codeload.github.com/WordPress/WordPress/zip/refs/tags/6.8.3 -o "$work/wordpress.zip"
echo "96ddc66e09427764dc7671361ef27542dcf642d820d4280307d32913208dca3f  $work/wordpress.zip" | sha256sum -c -
python3 - "$work" <<'PY'
import os, sys
from zipfile import ZipFile
with ZipFile(os.path.join(sys.argv[1], 'wordpress.zip')) as archive:
    archive.extractall(sys.argv[1])
PY
python3 - "$script_dir/local-wp-fixture.php" "$work/verify.json" <<'PYBLUEPRINT'
import json, sys
with open(sys.argv[1], encoding='utf-8') as source:
    php = source.read()
blueprint = {'preferredVersions': {'php': '8.2', 'wp': '6.8'}, 'steps': [{'step': 'runPHP', 'code': php}]}
with open(sys.argv[2], 'w', encoding='utf-8') as output:
    json.dump(blueprint, output)
PYBLUEPRINT
node "$work/playground/node_modules/@wp-playground/cli/wp-playground.js" run-blueprint \
  --mount-before-install "$work/WordPress-6.8.3:/wordpress" \
  --wordpress-install-mode install-from-existing-files --php 8.2 \
  --blueprint "$work/verify.json" --verbosity normal
python3 - "$work/WordPress-6.8.3/hj-environment-check.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding='utf-8') as source:
    data = json.load(source)
print(json.dumps(data, indent=2))
if data['wp_version'] != '6.8.3' or not data['php_version'].startswith('8.2.') or not data['site_installed'] or not data['sqlite_class'] or not all(data[key] for key in ('published_unique', 'draft_excluded', 'private_excluded', 'duplicate_excluded', 'missing_excluded')):
    raise SystemExit('Unexpected local WordPress environment')
PY

#!/usr/bin/env bash
set -euo pipefail
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
for command in node npm curl python3 sha256sum; do command -v "$command" >/dev/null || { echo "Missing: $command" >&2; exit 1; }; done
npm install --prefix "$work/playground" --no-audit --no-fund --ignore-scripts @wp-playground/cli@3.1.61
curl --fail --location --silent --show-error --max-time 120 https://codeload.github.com/WordPress/WordPress/zip/refs/tags/6.8.3 -o "$work/wordpress.zip"
echo "96ddc66e09427764dc7671361ef27542dcf642d820d4280307d32913208dca3f  $work/wordpress.zip" | sha256sum -c -
python3 - "$work" "$root/tests/wordpress-fixture.php" <<'PY'
import json, os, sys
from zipfile import ZipFile
with ZipFile(os.path.join(sys.argv[1], 'wordpress.zip')) as archive:
    archive.extractall(sys.argv[1])
with open(sys.argv[2], encoding='utf-8') as f:
    code = f.read()
with open(os.path.join(sys.argv[1], 'blueprint.json'), 'w', encoding='utf-8') as f:
    json.dump({'preferredVersions': {'php': '8.2', 'wp': '6.8'}, 'steps': [{'step': 'runPHP', 'code': code}]}, f)
PY
mount_args=()
if [[ $# -gt 0 ]]; then
  mkdir -p "$work/snapshot"
  cp -- "$1" "$work/snapshot/snapshot.json"
  mount_args=(--mount "$work/snapshot:/mnt/snapshot")
fi
node "$work/playground/node_modules/@wp-playground/cli/wp-playground.js" run-blueprint \
 --mount-before-install "$work/WordPress-6.8.3:/wordpress" --mount "$root:/mnt/hjnr" "${mount_args[@]}" \
 --wordpress-install-mode install-from-existing-files --php 8.2 --blueprint "$work/blueprint.json" --verbosity normal
python3 - "$work/WordPress-6.8.3/hjnr-test-result.json" <<'PY'
import json, sys
with open(sys.argv[1], encoding='utf-8') as f:
    report = json.load(f)
required = ('repair_and_restore', 'edited_profiles_untouched', 'private_excluded', 'duplicate_ids_excluded', 'duplicate_meta_excluded', 'restore_conflict_preserves_edits', 'restore_conflict_on_changed_identifier', 'admin_actions_registered')
assert all(report.get(k) is True for k in required), report
print(json.dumps(report, indent=2))
PY

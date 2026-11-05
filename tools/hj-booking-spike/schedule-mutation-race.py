#!/usr/bin/env python3
"""Race twelve authenticated requests against duration and fallback-hours edits."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
base = ['php', 'tools/hj-booking-spike/secured-race-fixture.php']
subprocess.run(base + ['mutation-setup'], check=True)
go = Path('/tmp/hj-mutation-go')
ready = [Path(f'/tmp/hj-mutation-ready-{i}') for i in range(13)]
for p in [go, *ready]:
    p.unlink(missing_ok=True)
workers = []
try:
    for i in range(13):
        args = ['mutation-writer'] if i == 12 else ['mutation-worker', str(i)]
        workers.append(subprocess.Popen(base + args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True))
    deadline = time.monotonic() + 25
    while not all(p.exists() for p in ready) and time.monotonic() < deadline:
        if any(w.poll() is not None for w in workers):
            raise RuntimeError('worker exited before barrier')
        time.sleep(.02)
    if not all(p.exists() for p in ready):
        raise RuntimeError('worker barrier timed out')
    go.touch()
    outputs = []
    for w in workers:
        stdout, stderr = w.communicate(timeout=35)
        if w.returncode:
            raise RuntimeError(stderr)
        outputs.append(stdout.strip())
    if outputs[12] != 'updated' or not all(x in ('created', 'invalid_slot', 'overlap') for x in outputs[:12]):
        raise AssertionError(outputs)
    print('PASS: twelve requests competed with coordinated duration and hours writes', flush=True)
finally:
    for w in workers:
        if w.poll() is None:
            w.kill()
            w.wait()
    for p in [go, *ready]:
        p.unlink(missing_ok=True)
subprocess.run(base + ['mutation-verify'], check=True)

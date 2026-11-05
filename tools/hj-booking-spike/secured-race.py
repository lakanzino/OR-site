#!/usr/bin/env python3
"""Concurrent secured WordPress backend operations in disposable CI only."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
base = ['php', 'tools/hj-booking-spike/secured-race-fixture.php']
subprocess.run(base + ['setup'], check=True)
go = Path('/tmp/hj-secured-go')
ready = [Path(f'/tmp/hj-secured-ready-{i}') for i in range(12)]
for path in [go, *ready]:
    path.unlink(missing_ok=True)
workers = []
try:
    for i in range(12):
        workers.append(subprocess.Popen(base + ['worker', str(i)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True))
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
    if outputs.count('created') != 1 or outputs.count('invalid_slot') + outputs.count('overlap') != 11:
        raise AssertionError(outputs)
    print('PASS: one secured booking among twelve synchronized patients', flush=True)
finally:
    for w in workers:
        if w.poll() is None:
            w.kill()
            w.wait()
    for path in [go, *ready]:
        path.unlink(missing_ok=True)
subprocess.run(base + ['finish'], check=True)

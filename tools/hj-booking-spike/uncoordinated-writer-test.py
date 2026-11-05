#!/usr/bin/env python3
"""Demonstrate why existing, non-cooperative WP hours writers prevent gate closure."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
base = ['php', 'tools/hj-booking-spike/secured-race-fixture.php']
held, done = Path('/tmp/hj-uncoordinated-held'), Path('/tmp/hj-uncoordinated-done')
for p in (held, done): p.unlink(missing_ok=True)
subprocess.run(base + ['uncoordinated-setup'], check=True)
worker = subprocess.Popen(base + ['uncoordinated-lock'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
try:
    deadline = time.monotonic() + 12
    while not held.exists() and time.monotonic() < deadline:
        if worker.poll() is not None:
            raise RuntimeError('worker exited before lock')
        time.sleep(.02)
    if not held.exists(): raise RuntimeError('worker failed to acquire lock')
    writer = subprocess.run(base + ['uncoordinated-write'], capture_output=True, text=True, timeout=5, check=True)
    if writer.stdout.strip() != 'wrote': raise AssertionError(writer.stdout)
    stdout, stderr = worker.communicate(timeout=15)
    if worker.returncode or stdout.strip() != 'created': raise RuntimeError((stdout, stderr))
    subprocess.run(base + ['uncoordinated-verify'], check=True)
finally:
    if worker.poll() is None:
        worker.kill()
        worker.wait()
    for p in (held, done): p.unlink(missing_ok=True)

#!/usr/bin/env python3
"""Test row locking against the fallback metadata writer; does not cover ACF repeaters."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
base = ['php', 'tools/hj-booking-spike/secured-race-fixture.php']
held, release = Path('/tmp/hj-meta-held'), Path('/tmp/hj-meta-release')
for p in (held, release): p.unlink(missing_ok=True)
subprocess.run(base + ['uncoordinated-setup'], check=True)
holder = subprocess.Popen(base + ['metadata-lock'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
writer = None
try:
    deadline = time.monotonic() + 12
    while not held.exists() and time.monotonic() < deadline:
        if holder.poll() is not None: raise RuntimeError('holder exited')
        time.sleep(.02)
    if not held.exists(): raise RuntimeError('metadata lock did not start')
    writer = subprocess.Popen(base + ['metadata-write'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    time.sleep(1)
    if writer.poll() is not None: raise RuntimeError('fallback writer did not block on metadata row')
    release.touch()
    out, err = holder.communicate(timeout=15)
    if holder.returncode or out.strip() != 'released': raise RuntimeError((out, err))
    out, err = writer.communicate(timeout=15)
    if writer.returncode or out.strip() != 'wrote': raise RuntimeError((out, err))
    print('PASS: locking the fallback metadata row blocks the existing single-row writer')
finally:
    for process in (holder, writer):
        if process and process.poll() is None:
            process.kill()
            process.wait()
    for p in (held, release): p.unlink(missing_ok=True)

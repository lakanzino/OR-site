#!/usr/bin/env python3
"""Race twelve authenticated patients against one canonical snapshot update."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
base = ['php', 'tools/hj-booking-spike/snapshot-race-fixture.php']
path = Path('/tmp/hj-snapshot-race-doctor')
go = Path('/tmp/hj-snapshot-go')
ready = [Path(f'/tmp/hj-snapshot-ready-{i}') for i in range(13)]
for p in [path, go, *ready]: p.unlink(missing_ok=True)
subprocess.run(base + ['setup'], check=True)
workers = []
try:
    for i in range(13):
        workers.append(subprocess.Popen(base + ['worker', str(i)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True))
    deadline = time.monotonic() + 25
    while not all(p.exists() for p in ready) and time.monotonic() < deadline:
        if any(w.poll() is not None for w in workers): raise RuntimeError('worker exited before barrier')
        time.sleep(.02)
    if not all(p.exists() for p in ready): raise RuntimeError('worker barrier timed out')
    go.touch()
    results = []
    for process in workers:
        stdout, stderr = process.communicate(timeout=35)
        if process.returncode: raise RuntimeError(stderr)
        results.append(stdout.strip())
    if results[12] != 'published' or results[:12].count('created') > 1 or not all(x in ('created', 'invalid_slot', 'overlap') for x in results[:12]):
        raise AssertionError(results)
    subprocess.run(base + ['verify'], check=True)
finally:
    for process in workers:
        if process.poll() is None:
            process.kill()
            process.wait()
    for p in [path, go, *ready]: p.unlink(missing_ok=True)

#!/usr/bin/env python3
"""Exercise real InnoDB lock wait timeouts and bounded retry, CI fixture only."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
marker = Path('/tmp/hj-interval-held')
base = ['php', 'tools/hj-booking-spike/interval-fixture.php']
for doctor, seconds, expected in ((107, 5, 'created'), (108, 12, 'busy')):
    marker.unlink(missing_ok=True)
    holder = subprocess.Popen(base + ['hold', str(doctor), str(seconds)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    try:
        deadline = time.monotonic() + 15
        while not marker.exists() and time.monotonic() < deadline:
            if holder.poll() is not None:
                raise RuntimeError('holder exited before lock')
            time.sleep(.02)
        if not marker.exists():
            raise RuntimeError('lock holder did not start')
        before = time.monotonic()
        result = subprocess.run(base + ['retry', str(doctor)], capture_output=True, text=True, timeout=20, check=True)
        elapsed = time.monotonic() - before
        if result.stdout.strip() != expected or elapsed < 2.5:
            raise AssertionError((result.stdout, result.stderr, elapsed, expected))
        stdout, stderr = holder.communicate(timeout=15)
        if holder.returncode or stdout.strip() != 'released':
            raise RuntimeError((stdout, stderr))
        if expected == 'busy':
            result = subprocess.run(base + ['retry', str(doctor)], capture_output=True, text=True, timeout=10, check=True)
            if result.stdout.strip() != 'created':
                raise AssertionError((result.stdout, result.stderr))
        print(f'PASS: lock wait {seconds}s produced {expected} after {elapsed:.1f}s; no stale lock')
    finally:
        if holder.poll() is None:
            holder.kill()
            holder.wait()
        marker.unlink(missing_ok=True)
